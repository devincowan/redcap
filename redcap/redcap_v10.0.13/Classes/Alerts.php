<?php

class Alerts
{
    private $alerts_settings = array();

    private $alerts_queue = array();

    // Fields set by cacheProject method
    public $logic_fields = null;
    public $logic_events = null;
    public $parser_cache = null;
    public $record_data = null;
    public $alertsRecordsSent = array();
    public $alertsRecordsScheduled = array();
    public $alertAttachmentsToDelete = array();

    const participant_email_var = '[survey-participant-email]';
    const participant_phone_var = '[survey-participant-phone]';

    const notification_log_num_per_page = 100;

    const MAX_ATTACHMENT_SIZE_MB = 10;

    public function getAlertDefaults()
    {
        $alert = getTableColumns('redcap_alerts');
        unset($alert['project_id'], $alert['alert_id']);
        foreach ($alert as $key=>$val) {
            unset($alert[$key]);
            $key = str_replace("_", "-", $key);
            $alert[$key] = $val;
        }
        return $alert;
    }

    public function getAlertSettings($pid = null)
    {
        if(!isset($pid) && defined('PROJECT_ID')){
            $pid = PROJECT_ID;
        }
        // If we already have the structure, return it
        $alertNum = 1;
        if (!isset($this->alerts_settings[$pid])) {
            // Return values if row exists
            $sql = "select * from redcap_alerts where project_id = $pid order by alert_id";
            $q = db_query($sql);
            $this->alerts_settings[$pid] = array();
            while ($row = db_fetch_assoc($q)) {
                unset($row['project_id']);
                $row['alert_number'] = $alertNum++;
                $this->alerts_settings[$pid][$row['alert_id']] = $row;
            }
        }
        return $this->alerts_settings[$pid];
    }

    public function getAlertsQueue($pid = null)
    {
        if(!isset($pid) && defined('PROJECT_ID')){
            $pid = PROJECT_ID;
        }
        $Proj = new \Project($pid);
        // If we already have the structure, return it
        if (!isset($this->alerts_queue[$pid])) {
            // Return values if row exists
            $sql = "select a.project_id, a.email_deleted as deactivated, q.* 
                    from redcap_alerts a, redcap_alerts_recurrence q 
                    where a.project_id = $pid and q.alert_id = a.alert_id
                    order by a.alert_id, q.aq_id";
            $q = db_query($sql);
            $this->alerts_queue[$pid] = array();
            while ($row = db_fetch_assoc($q)) {
                $row['option'] = $row['send_option'];
                $row['event_id'] = ($row['event_id'] != '') ? $row['event_id'] : $Proj->firstEventId;
                $row['alert'] = $this->getKeyIdFromAlertId($pid, $row['alert_id']);
                unset($row['alert_id'], $row['send_option'], $row['form_name_event']);
                array_push($this->alerts_queue[$pid], $row);
                // $this->alerts_queue[$pid][] = $row;
            }
        }
        return $this->alerts_queue[$pid];
    }

    // Obtain array of records that have been queued for a given alert
    public function getAlertQueuedRecords($alert_id)
    {
        $sql = "select distinct record from redcap_alerts_recurrence where alert_id = $alert_id";
        $q = db_query($sql);
        $records = array();
        while ($row = db_fetch_assoc($q)) {
            $records[] = $row['record'];
        }
        natcasesort($records);
        return $records;
    }

    // Obtain array of records that have been sent for a given alert
    public function getAlertsSent($alert_id)
    {
        $sql = "select record from redcap_alerts_sent where alert_id = $alert_id";
        $q = db_query($sql);
        $records = array();
        while ($row = db_fetch_assoc($q)) {
            $records[] = $row['record'];
        }
        natcasesort($records);
        return $records;
    }

    public function getAlertSetting($key, $pid = null)
    {
        if (!isset($pid) && defined('PROJECT_ID')) $pid = PROJECT_ID;
        $key = str_replace("-", "_", $key);
        $settings = $this->getAlertSettings($pid);
        $thisSetting = array();
        foreach ($settings as $attr) {
            if (!array_key_exists($key, $attr)) return array();
            $thisSetting[] = $attr[$key];
        }
        return $thisSetting;
    }

    // Return array of alert_id's that match the form and event_id (or has null event_id) - ignore deleted alerts
    private function getAlertsForInstrumentSave($project_id, $record, $event_id, $instrument="", $repeat_instance=1, $repeat_instrument='')
    {
        $alerts = array();
        if ($repeat_instance == '') $repeat_instance = '1';
        // Get values from tables
        $sql = "select a.alert_id, if (r.aq_id is null, 0, 1) as scheduled, if (s.alert_sent_id is null, 0, 1) as sent
                from redcap_alerts a 
                left join redcap_alerts_recurrence r
                    on a.alert_id = r.alert_id and r.record = ".checkNull($record)." 
                    and (
                        (a.alert_stop_type = 'RECORD')
                        or (a.alert_stop_type = 'RECORD_EVENT' and r.event_id = '".db_escape($event_id)."')
                        or (a.alert_stop_type = 'RECORD_INSTRUMENT' and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT' and r.event_id = '".db_escape($event_id)."' 
                            and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT_INSTANCE' and r.event_id = '".db_escape($event_id)."' 
                            and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name) and r.instance = '".db_escape($repeat_instance)."')
                    )
                left join redcap_alerts_sent s 
                    on a.alert_id = s.alert_id and s.record = '".db_escape($record)."'
                    and (
                        (a.alert_stop_type = 'RECORD')
                        or (a.alert_stop_type = 'RECORD_EVENT' and s.event_id = '".db_escape($event_id)."')
                        or (a.alert_stop_type = 'RECORD_INSTRUMENT' and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT' and s.event_id = '".db_escape($event_id)."' 
                            and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT_INSTANCE' and s.event_id = '".db_escape($event_id)."' 
                            and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name) and s.instance = '".db_escape($repeat_instance)."')
                    )
                where a.project_id = $project_id and a.email_deleted = 0 
                and (
                    (a.form_name = '" . db_escape($instrument) . "' and a.form_name != '' 
                        and (a.form_name_event is null or a.form_name_event = '" . db_escape($event_id) . "'))
                    or
                    (a.alert_condition is not null and a.form_name is null)
                )";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $alerts[$row['alert_id']] = $row;
        }
        return $alerts;
    }

    public function saveRecordAction($project_id, $record, $instrument, $event_id, $repeat_instance=1, $survey_hash=null, $response_id=null, $dataValuesModified=null, $dataValuesModifiedIncludingCalcs=null)
    {
        if ($project_id == '' || $record == '') return;
        // Get data for this record
        $Proj = new Project($project_id);
        // Is the current instrument a repeating instrument? If so set $repeat_instrument to $instrument, else ''.
        $repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : '';
        // Get any viable alerts to trigger for this form/event
        $viableAlerts = $this->getAlertsForInstrumentSave($project_id, $record, $event_id, $instrument, $repeat_instance, $repeat_instrument);

        // Loop through all viable alerts to see if we need to trigger anything
        if (empty($viableAlerts)) return;

        $alerts = $this->getAlertSettings($project_id);

        // Remove non-viable alerts from $alerts
        $logicPipingVars = "";
        foreach ($alerts as $alert_id => $attr) {
            // Gather logic and piping variables as ONE BIG string
            $logicPipingVars .= " {$attr['alert_condition']} {$attr['email_to']} {$attr['email_cc']} {$attr['email_bcc']}"
                             .  " {$attr['email_subject']} {$attr['alert_message']} {$attr['email_attachment_variable']} {$attr['phone_number_to']} ";
            // Remove non-viable alerts from $alerts
            if (!isset($viableAlerts[$alert_id])) unset($alerts[$alert_id]);
        }

        // Get all data needed for any piping or logic parsing for ALL viable alerts in this project
        $logicPipingVars = trim($logicPipingVars);
        $logic_events = array($event_id); // Initially include the current event context
        $logic_fields = array();
        if ($logicPipingVars == "") $logicPipingVars = "[".$Proj->table_pk."]"; // At least add record ID field so that $data is not empty
        foreach (array_keys(getBracketedFields($logicPipingVars, true, true, false)) as $this_field)
        {
            // Check if has dot (i.e. has event name included)
            if (strpos($this_field, ".") !== false) {
                list ($this_event_name, $this_field) = explode(".", $this_field, 2);
                if (Piping::containsEventSpecialTags("[$this_event_name]")) {
                    $this_event_name = Piping::pipeSpecialTags("[$this_event_name]", $project_id, $record, $event_id, $repeat_instance, null, false, null, $instrument);
                }
                $logic_events[] = $this_event_name;
            }
            // Verify that the field really exists (may have been deleted). If so, skip it.
            if (!isset($Proj->metadata[$this_field])) continue;
            // Add field to array
            $logic_fields[] = $this_field;
        }
        // If any -event-name smart variables are used, then just pull data from all events, just in case
        if (Piping::containsEventSpecialTags($logicPipingVars)) {
            $logic_events = array();
        }
        // Get the relevant data
        $data = REDCap::getData($project_id, "array", $record, $logic_fields, $logic_events);
        // Set if the current record/event/form/instance is complete
        $formCompleted = $this->isFormStatusCompleted($project_id, $record, $event_id, $instrument, $repeat_instance);
        // Is this a repeating form/event?
        $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $instrument);
        // Get all alerts for this project
        foreach ($alerts as $alert_id => $attr)
        {
            // Get index of this alert
            $index = $this->getKeyIdFromAlertId($project_id, $alert_id);
            // Determine if notification is already sent or scheduled for this record/event/instrument/instance
            $recurrenceAlreadyCreated = $viableAlerts[$alert_id]['scheduled'];
            $alertAlreadySent = $viableAlerts[$alert_id]['sent'];
            // Determine status completion trigger setting
            $triggerOnCompleteStatus = !$triggerOnAnyStatus = ($attr['email_incomplete'] == '1');
            // Has conditional logic?
            $triggerOnLogic = ($attr['alert_condition'] != '');
            $triggerOnLogicOnly = ($triggerOnLogic && $attr['form_name'] == '');
            $ensureLogicStillTrue = ($triggerOnLogic && $attr['ensure_logic_still_true']);
            // Send alert every time data is added/modified? If no data was added/modified, then do nothing and skip this loop
            if ($attr['email_repetitive_change'] && !$attr['email_repetitive_change_calcs'] && $dataValuesModified === false) continue;
            if ($attr['email_repetitive_change'] && $attr['email_repetitive_change_calcs'] && $dataValuesModifiedIncludingCalcs === false) continue;
            // Send alert every time?
            $sendEveryTimeDataChanges = (  ($attr['email_repetitive_change'] && !$attr['email_repetitive_change_calcs'] && $dataValuesModified === true))
                                        || ($attr['email_repetitive_change'] && $attr['email_repetitive_change_calcs']  && $dataValuesModifiedIncludingCalcs === true);
            $sendEveryTime = ($attr['email_repetitive'] || $sendEveryTimeDataChanges);
            // Send now?
            $sendNow = ($attr['cron_send_email_on'] == 'now');
            $sendJustOnce = ($attr['cron_repeat_for'] == 0);
            $sendNowJustOnce = ($sendNow && $sendJustOnce);
            // Set as recurring?
            $recurring = (!$sendEveryTime && !$sendNowJustOnce);
            // Trigger it based on form status or by logic alone?
            if ($triggerOnLogicOnly || $triggerOnAnyStatus || ($triggerOnCompleteStatus && $formCompleted)) {
                // Unless sending EVERY time, do not send alert if already sent for this record/event/instrument/instance
                if (    $sendEveryTime
                    || (!$sendEveryTime && !$alertAlreadySent)
                    || ($recurrenceAlreadyCreated && $ensureLogicStillTrue)
                    || (!$recurrenceAlreadyCreated && $ensureLogicStillTrue && $alertAlreadySent && !$sendEveryTime)) // In case the original has been sent, and the recurrences have been removed but need to be re-added again
                {
                    // Trigger it based on logic?
                    if ($triggerOnLogic) {
                        if ($isRepeatingFormOrEvent) {
                            $passedLogicTest = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, $repeat_instance, $instrument, $instrument);
                        } else {
                            $passedLogicTest = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, 1, "", $instrument);
                        }
                        // If failed logic and has "ensure logic still true" enabled and already exists in recurrence table, then remove from table
                        if (!$passedLogicTest && $ensureLogicStillTrue && $recurrenceAlreadyCreated) {
                            $this->deleteRecurrence($alert_id, $record, $event_id, $attr['form_name'], $repeat_instance);
                        }
                        // If passed logic and has "ensure logic still true" enabled and has already been sent, then make sure it doesn't send again now
                        if ($passedLogicTest && $ensureLogicStillTrue && $alertAlreadySent && !$sendEveryTime) {
                            $sendNow = false;
                        }
                    }
                    if (!$triggerOnLogic || $passedLogicTest) {
                        // Send alert now?
                        if ($sendNow) {
                            $this->sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $repeat_instance, $data);
                        }
                        // Schedule this alert to recur?
                        if ($recurring && !$recurrenceAlreadyCreated && !($alertAlreadySent && $sendJustOnce)) {
                            $this->createRecurrence($alert_id, $project_id, $record, $event_id, ($repeat_instrument != '' ? $repeat_instrument : $attr['form_name']), $repeat_instance, 0, '', $instrument);
                        }
                    }
                }
            }
        }
    }

    // Return true if a record's Form Status value for a given instrument/event/instance is Complete (=2)
    public static function isFormStatusCompleted($project_id, $record, $event_id, $instrument, $instance=1)
    {
        if (empty($instrument)) return false;
        // Set SQL for instance
        $instance = (int)$instance;
        $instanceSql = ($instance > 1) ? "and instance = '".db_escape($instance)."'" : "and instance is null";
        // Query data table for value of 2
        $sql = "select 1 from redcap_data where project_id = $project_id
				and event_id = $event_id and record = '" . db_escape($record) . "'
				and field_name = '" . db_escape($instrument) . "_complete' and value = '2' $instanceSql limit 1";
        $q = db_query($sql);
        // Return true if has been completed
        return (db_num_rows($q) > 0);
    }

        // Convert the key from the alerts_settings array to its corresponding alert_id
    public function getAlertIdFromKeyId($project_id, $id)
    {
        $settings = array_values($this->getAlertSettings($project_id));
        return isset($settings[$id]) ? $settings[$id]['alert_id'] : null;
    }

    // Convert the alert_id to the key from the alerts_settings array
    public function getKeyIdFromAlertId($project_id, $alert_id)
    {
        if (!is_numeric($alert_id)) return null;
        $alert_id .= "";
        $settings = array_values($this->getAlertSettings($project_id));
        foreach ($settings as $key=>$attr) {
            if ($attr['alert_id']."" === $alert_id) return $key;
        }
        return null;
    }

    /**
     * Function to add queued emails from the user interface
     * @param $project_id
     * @param $alert
     * @param $record
     * @param $times_sent
     */
    function addQueueEmailFromInterface($project_id, $alert_id, $record, $times_sent, $event_id, $last_sent, $instance)
    {
        $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);

        $data = \REDCap::getData($project_id,"array",$record);

        $instrument = $this->getAlertSetting("form-name",$project_id)[$index];

        $isRepeatInstrument = false;
        if((array_key_exists('repeat_instances',$data[$record]) && ($data[$record]['repeat_instances'][$event_id][$instrument][$instance][$instrument.'_complete'] != '' || $data[$record]['repeat_instances'][$event_id][''][$repeat_instance][$instrument.'_complete'] != ''))){
            $isRepeatInstrument = true;
        }

        if (!$this->recurrenceAlreadyCreated($alert_id, $record, $instance))
        {
            $this->createRecurrence($alert_id, $project_id, $record, $event_id, $instrument, $instance, $times_sent, $last_sent);
        } else {
            return $record;
        }
        return "";
    }

    // Does the recurrence already exist in the redcap_alerts_recurrence table?
    function recurrenceAlreadyCreated($alert_id, $record, $instance)
    {
        $sql = "select 1 from redcap_alerts_recurrence where alert_id = $alert_id
                and record = '".db_escape($record)."' and instance = '".db_escape($instance)."' limit 1";
        $q = db_query($sql);
        return (db_num_rows($q) > 0);
    }

    // Function called by the CRON to parse the logic function for each alert with datediff (called once per project)
    public function cacheProjectAlertFunctions($alerts, $Proj)
    {
        // Create arrays to store logic fields, events, and parser functions used for this project
        $logic_fields = array();
        $logic_events = array();
        // $parser_cache = array();    // funcNames/argMaps for parsing the ASI logic, stored as an array of [survey_id][event_id] = array(funcName, argMap)

        // Get unique event names (with event_id as key)
        $unique_events = $Proj->getUniqueEventNames();

        // Loop through alerts
        foreach ($alerts as $alert_id => $data)
        {
            $condition_logic = $data['alert_condition'];

            // Optimization 1: Skip ASI if not datediff+today/now
            if (!(strpos($condition_logic, "datediff") !== false &&
                    (strpos($condition_logic, "today") !== false || strpos($condition_logic, "now") !== false)))
            {
                continue;
            }

            // If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
            // $logicContainsSmartVariables = Piping::containsSpecialTags($condition_logic);

            // Optimization 2: Cache the parser functions and arguments
//            $funcName = null;
//            if (!$logicContainsSmartVariables) {
//                try {
//                    // Instantiate logic parser
//                    $parser = new LogicParser();
//                    list ($funcName, $argMap) = $parser->parse($condition_logic, array_flip($unique_events));
//                    unset($parser);
//                    $parser_cache[$alert_id] = array( $funcName, $argMap );
//                }
//                catch (LogicException $e) {
//                    continue;
//                }
//            } else {
//                $parser_cache[$alert_id] = array();
//            }

            // Since we'll use logic_fields to build data used for piping and conditional logic testing, make sure we include all fields that might be used
            // Gather logic and piping variables as ONE BIG string
            $logicPipingVars = trim(" {$data['alert_condition']} {$data['email_to']} {$data['email_cc']} {$data['email_bcc']}"
                             .  " {$data['email_subject']} {$data['alert_message']} {$data['email_attachment_variable']} {$data['phone_number_to']} ");

            // Optimization 3: Limit the fields/events to those used in the ASI function
            foreach (array_keys(getBracketedFields($logicPipingVars, true, true, false)) as $this_field)
            {
                // Check if has dot (i.e. has event name included)
                if (strpos($this_field, ".") !== false) {
                    list ($this_event_name, $this_field) = explode(".", $this_field, 2);
                    $logic_events[] = $this_event_name;
                }
                // Verify that the field really exists (may have been deleted). If so, skip it.
                if (!isset($Proj->metadata[$this_field])) continue;
                // Add field to array
                $logic_fields[] = $this_field;
            }
        }

        // Remove duplicates fields/events
        $logic_fields = array_values(array_unique($logic_fields));
        $logic_events = array_values(array_unique($logic_events));

        // Store results in SurveyScheduler object
        $this->logic_fields = $logic_fields;
        $this->logic_events = $logic_events;
        // $this->parser_cache = $parser_cache;

        // Also add all form status fields for repeating instruments to ensure we pick up all data structures
        foreach (array_keys($Proj->forms) as $this_form) {
            if ($Proj->isRepeatingFormAnyEvent($this_form)) {
                $this->logic_fields[] = $this_form."_complete";
            }
        }

        // return !empty($this->parser_cache);
        return true;
    }

    // Function called by the CRON to fetch data for datediff+today/now alerts
    public function cacheProjectAlertDataDatadiffCron($Proj)
    {
        // Load the data for this project based on filters generated above
        $params = array('project_id'=>$Proj->project_id, 'fields'=>array_merge(array($Proj->table_pk), $this->logic_fields), 'returnEmptyEvents'=>true);
        $this->record_data = Records::getData($params);
    }

    public function setRecordsSentForAlerts($alert_ids=array())
    {
        $this->alertsRecordsSent = array();
        $sql = "select distinct alert_id, record, event_id, instrument, instance from redcap_alerts_sent 
                where alert_id in (".prep_implode($alert_ids).")";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $this->alertsRecordsSent[$row['alert_id']][$row['record']][$row['event_id']][$row['instrument']][$row['instance']] = true;
        }
    }

    public function setRecordsScheduledForAlerts($alert_ids=array())
    {
        $this->alertsRecordsScheduled = array();
        $sql = "select distinct alert_id, record, event_id, instrument, instance from redcap_alerts_recurrence 
                where alert_id in (".prep_implode($alert_ids).")";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $this->alertsRecordsScheduled[$row['alert_id']][$row['record']][$row['event_id']][$row['instrument']][$row['instance']] = true;
        }
    }

    // Function called by the CRON to check any alerts with datediff+today/now
    public function checkAlertsWithDatediffViaCron()
    {
        $num_scheduled_total = 0;

        // Get a list of all projects that are using active, time-based conditional logic for automated notifications
        $sql = "SELECT a.* FROM redcap_alerts a, redcap_projects p
				WHERE a.email_deleted = 0 AND p.status <= 1 AND p.date_deleted is null AND p.project_id = a.project_id AND a.form_name is null 
				AND (a.alert_condition like '%datediff%(%today%,%)%' or a.alert_condition like '%datediff%(%now%,%)%')
				order by p.project_id desc, a.alert_id";
        $q = db_query($sql);
        if (db_num_rows($q) == 0) return 0;
        $alerts = array();
        while ($row = db_fetch_assoc($q)) {
            $alerts[$row['project_id']][$row['alert_id']] = $row;
        }

        System::increaseMemory(2048); // Increase memory to 2GB to prevent timeout

        // Loop through each project with datediff+today
        foreach ($alerts as $project_id=>$attr2)
        {
            // Set Proj object and other project-specific things for this loop
            $Proj = new Project($project_id);
            // Preload all survey parsing fields/events/functions
            if (!$this->cacheProjectAlertFunctions($attr2, $Proj)) continue;
            // Preload all survey data and record schedules
            $this->cacheProjectAlertDataDatadiffCron($Proj);
            // Find any records that have already been sent or scheduled for these alerts
            $this->setRecordsSentForAlerts(array_keys($attr2));
            $this->setRecordsScheduledForAlerts(array_keys($attr2));
            // Get unique event names (with event_id as key)
            $events = $Proj->getUniqueEventNames();
            $eventsFlipped = array_flip($events);
            // Loop through each alert for this project
            foreach ($attr2 as $alert_id=>$attr)
            {
                // if (!isset($this->parser_cache[$alert_id])) continue;
                $funcName = null;
                // Send now?
                $sendNow = ($attr['cron_send_email_on'] == 'now');
                $sendNowJustOnce = ($sendNow && $attr['cron_repeat_for'] == 0);
                // Set as recurring?
                $recurring = (!$sendEveryTime && !$sendNowJustOnce);
                // Load the funcName and argMap once for all records
                // if (!empty($this->parser_cache[$alert_id])) {
                //     list($funcName, $argMap) = $this->parser_cache[$alert_id];
                // }
                // We don't really have an event_id here since we're record-specific, but try to get one
                // $event_id = empty($attr['form_name_event']) ? $Proj->firstEventId : $attr['form_name_event'];
                $instrument = empty($attr['form_name']) ? '' : $attr['form_name'];
                // Loop through each record and evaluate the function
				foreach ($this->record_data as $record=>&$event_data)
				{
					foreach ($event_data as $this_event_id1=>&$field_data)
					{
                        if ($this_event_id1 == 'repeat_instances') {
                            $eventNormalized = $event_data['repeat_instances'];
                        } else {
                            $eventNormalized = array();
                            $eventNormalized[$this_event_id1][""][1] = $event_data[$this_event_id1];
                        }
                        foreach ($eventNormalized as $event_id=>$data1)
                        {
                            $isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
                            foreach ($data1 as $repeat_instrument=>$data2)
                            {
                                $isRepeatingForm = ($repeat_instrument != '');
                                foreach ($data2 as $instance=>$data3)
                                {
                                    // Get current instrument (will not exist for Conditional Logic Only option UNLESS this is a repeating instrument)
                                    $current_instrument = ($repeat_instrument != '') ? $repeat_instrument : $instrument;
                                    // Based on stop type, determine if this alert has been sent/scheduled for this record/event/form/instance. If so, skip this loop.
                                    if (
                                        ($attr['alert_stop_type'] == 'RECORD' && (isset($this->alertsRecordsSent[$alert_id][$record])
                                            || isset($this->alertsRecordsScheduled[$alert_id][$record])))
                                        or ($attr['alert_stop_type'] == 'RECORD_EVENT' && (isset($this->alertsRecordsSent[$alert_id][$record][$event_id])
                                            || isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id])))
                                        or ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT_INSTANCE' && (isset($this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument][$instance])
                                            || isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument][$instance])))
                                        or ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT' && (isset($this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument])
                                            || isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument])))
                                    ) {
                                        continue;
                                    }
                                    // If RECORD_INSTRUMENT stop type, then loop through all events first to find it
                                    if ($attr['alert_stop_type'] == 'RECORD_INSTRUMENT')
                                    {
                                        if (isset($this->alertsRecordsSent[$alert_id][$record])) {
                                            foreach ($this->alertsRecordsSent[$alert_id][$record] as $formsAttr) {
                                                if (isset($formsAttr[$current_instrument])) {
                                                    continue 2;
                                                }
                                            }
                                        }
                                        if (isset($this->alertsRecordsScheduled[$alert_id][$record])) {
                                            foreach ($this->alertsRecordsScheduled[$alert_id][$record] as $formsAttr) {
                                                if (isset($formsAttr[$current_instrument])) {
                                                    continue 2;
                                                }
                                            }
                                        }
                                    }
                                    // Check logic
                                    if ($isRepeatingEvent || $isRepeatingForm) {
                                        $conditionsPassedLogic = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, $instance, $repeat_instrument, $current_instrument, $this->record_data);
                                    } else {
                                        $conditionsPassedLogic = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, 1, "", $current_instrument, $this->record_data);
                                    }
                                    // Schedule/send the alert
                                    if ($conditionsPassedLogic) {
                                        // Send alert now?
                                        if ($sendNow) {
                                            $this->sendNotification($alert_id, $project_id, $record, $event_id, ($repeat_instrument == '' ? $current_instrument : $repeat_instrument), $instance, $this->record_data);
                                            // Add this to array to prevent future loops from duplicating this when it should not
                                            $this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument][$instance] = true;
                                        }
                                        // Schedule this alert to recur?
                                        if ($recurring) {
                                            $this->createRecurrence($alert_id, $project_id, $record, $event_id, ($repeat_instrument == '' ? $current_instrument : $repeat_instrument), $instance);
                                            // Add this to array to prevent future loops from duplicating this when it should not
                                            $this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument][$instance] = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Return count of those that were scheduled
        return $num_scheduled_total;
    }

    // Function called by the CRON to send the scheduled or recurring alerts
    public function sendNotificationsViaCron()
    {
        // First, deactivate any alerts that are expiring right now
        $sql = "select project_id, alert_id, email_deleted from redcap_alerts 
                where alert_expiration is not null and alert_expiration <= '" . NOW . "'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $sql = "update redcap_alerts set email_deleted = 1, alert_expiration = null where alert_id = " . $row['alert_id'];
            db_query($sql);
            // Log it
            if (!$row['email_deleted']) {
                $index = $this->getKeyIdFromAlertId($row['project_id'], $row['alert_id']) + 1;
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $index,"Alert #{$index}", "Expire and deactivate alert", "",
                    "SYSTEM", $row['project_id']);
            }
        }

        // Second, if any alerts have been stuck in SENDING status for more than one hour (which means they likely won't ever send), then set them back to IDLE.
        $oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
        $sql = "update redcap_alerts_recurrence set status = 'IDLE', next_send_time = null 
                where next_send_time is not null and next_send_time <= '$oneHourAgo'";
        db_query($sql);

        // Set notifications with SENDING status if they should be sent right now
        $aq_ids = array();
        $sql = "select r.aq_id from redcap_alerts a, redcap_alerts_recurrence r, redcap_projects p
                where a.alert_id = r.alert_id and a.email_deleted = 0 and p.status <= 1 and p.date_deleted is null and p.project_id = a.project_id
                and a.cron_send_email_on = r.send_option and r.status = 'IDLE'
                and ((a.cron_repeat_for > 0 and (a.cron_repeat_for_max is null or r.times_sent < (a.cron_repeat_for_max - if(r.send_option != 'now',0,1)))) or (a.cron_repeat_for = 0 and r.times_sent = 0))
                and DATE_ADD(r.first_send_time, INTERVAL ((r.times_sent + if(r.send_option != 'now',0,1))*a.cron_repeat_for*(if(a.cron_repeat_for_units = 'DAYS', 1440, if(a.cron_repeat_for_units = 'HOURS', 60, 1)))) MINUTE) <= '" . NOW . "'
				limit " . SurveyScheduler::determineEmailsPerBatch();
        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            ## Get all aq_id's and put in array
            while ($row = db_fetch_assoc($q)) {
                // Set the aq_id's status as SENDING
                // (Don't change status unless still IDLE in case other simultaneous cron isn't lagging behind with the SELECT query above)
                $sql = "update redcap_alerts a, redcap_alerts_recurrence r set 
                        r.status = 'SENDING',
                        r.next_send_time = DATE_ADD(r.first_send_time, INTERVAL ((r.times_sent + if(r.send_option != 'now',0,1))*a.cron_repeat_for*(if(a.cron_repeat_for_units = 'DAYS', 1440, if(a.cron_repeat_for_units = 'HOURS', 60, 1)))) MINUTE)
						where a.alert_id = r.alert_id and r.aq_id = {$row['aq_id']} and r.status = 'IDLE'";
                db_query($sql);
                // If already set as SENDING, then skip it here because another cron must've picked it up
                if (db_affected_rows() == 0) continue;
                // Add ssq_id's to array
                $aq_ids[] = $row['aq_id'];
            }
        }
        // SEND NOTIFICATIONS
        // Initialize counter of number of notification sent
        $numSent = $numFailed = 0;
        if (empty($aq_ids)) return array($numSent, $numFailed);
        // Now loop though all aq_id's with status of SENDING and send notification for each
        $sql = "select r.aq_id, a.alert_id, a.project_id, r.record, r.event_id, r.instrument, r.instance,
                a.cron_repeat_for, r.times_sent, a.alert_condition, a.ensure_logic_still_true
                from redcap_alerts a, redcap_alerts_recurrence r
                where a.alert_id = r.alert_id and r.next_send_time is not null
                and r.aq_id in (" . prep_implode($aq_ids) . ")
                order by r.next_send_time";
        $q = db_query($sql);
        // Loop through all notification to be sent and then send them
        while ($row = db_fetch_assoc($q))
        {
            // Double check one last time that the notification has not already been sent (just in case a lagging simultaneous cron just sent it).
            // If not in SENDING state, then skip this invitation and move to next loop.
            $sql = "select 1 from redcap_alerts_recurrence where aq_id = {$row['aq_id']} and next_send_time is not null";
            $q1 = db_query($sql);
            if (db_num_rows($q1) < 1) continue;

            // Has conditional logic?
            $triggerOnLogic = ($row['alert_condition'] != '');
            $ensureLogicStillTrue = ($triggerOnLogic && $row['ensure_logic_still_true']);
            // Trigger it based on logic?
            if ($triggerOnLogic) {
                // Is this a repeating form/event?
                $Proj = new Project($row['project_id']);
                $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($row['event_id'], $row['instrument']);
                // Check logic
                if ($isRepeatingFormOrEvent) {
                    $passedLogicTest = REDCap::evaluateLogic($row['alert_condition'], $row['project_id'], $row['record'], $row['event_id'], $row['instance'], $row['instrument']);
                } else {
                    $passedLogicTest = REDCap::evaluateLogic($row['alert_condition'], $row['project_id'], $row['record'], $row['event_id']);
                }
                // If failed logic and has "ensure logic still true" enabled and already exists in recurrence table, then remove from table
                if (!$passedLogicTest && $ensureLogicStillTrue) {
                    $this->deleteRecurrence($row['alert_id'], $row['record'], $row['event_id'], $row['instrument'], $row['instance']);
                    // Stop loop here to go to next record
                    continue;
                }
            }

            // Send notification
            $sent = $this->sendNotification($row['alert_id'], $row['project_id'], $row['record'], $row['event_id'], $row['instrument'], $row['instance']);
//            if ($sent) {
                // If this is a one-time notification (no recurrence), then delete from recurrence table
                $deleteFromTable = ($row['cron_repeat_for'] == '0' && $row['times_sent'] == '0');
                // Successfully sent: Do we advance the recurrence schedule or remove it?
                if ($deleteFromTable) {
                    $sql = "delete from redcap_alerts_recurrence where aq_id = {$row['aq_id']}";
                } else {
                    $sql = "update redcap_alerts_recurrence 
                            set status = 'IDLE', next_send_time = NULL, times_sent = times_sent+1, last_sent = '".NOW."'
						    where aq_id = {$row['aq_id']}";
                    $numSent++;
                }
//            } else {
//                $sql = "update redcap_alerts_recurrence set status = 'IDLE', next_send_time = NULL
//						where aq_id = {$row['aq_id']}";
//                $numFailed++;
//            }
            db_query($sql);
        }
        // Free up memory
        db_free_result($q);
        unset($aq_ids);
        // Return count of successes and failures
        return array($numSent, $numFailed);
    }

    private function deleteRecurrence($alert_id, $record, $event_id, $instrument="", $repeat_instance=1)
    {
        if ($alert_id == '' || $record == '' || $event_id == '') return false;
        $sql = "delete from redcap_alerts_recurrence 
                where alert_id = ".checkNull($alert_id)." and record = ".checkNull($record)." and event_id = ".checkNull($event_id)." 
                and instrument = '".db_escape($instrument)."' and instance = ".checkNull($repeat_instance);
        return db_query($sql);
    }

    // Creates a new recurring notification
    function createRecurrence($alert_id, $project_id, $record, $event_id, $instrument, $instance, $times_sent=0, $last_sent='', $current_instrument='')
    {
        if ($alert_id == '' || $record == '' || $event_id == '') return false;
        $alert = $this->getKeyIdFromAlertId($project_id, $alert_id);
        $cron_send_email_on = $this->getAlertSetting("cron-send-email-on", $project_id)[$alert];
        // Prevent some NULLs so we can enforce unique keys properly
        if (!is_numeric($instance)) $instance = 1;
        if ($instrument === null) $instrument = '';
        // Determine when to send the first notification of this recurrence (all repetitions will be based on this)
        $first_send_time = $this->calculateNotificationFirstSendTime($project_id, $alert_id, $record, $event_id, $instrument, $instance, $current_instrument);
        if ($first_send_time === false) return false;
        // Add to table
        $sql = "insert into redcap_alerts_recurrence (alert_id, record, event_id, instrument, instance, creation_date, first_send_time, send_option, times_sent, last_sent) 
                values (".checkNull($alert_id).", ".checkNull($record).", ".checkNull($event_id).", '".db_escape($instrument)."', '".db_escape($instance)."', 
                ".checkNull(NOW).", ".checkNull($first_send_time).", ".checkNull($cron_send_email_on).", ".checkNull($times_sent).", ".checkNull($last_sent).")";
        $q = db_query($sql);
        if ($q) {
            return db_insert_id();
        }
        return false;
    }


    // Calculate the date/time when the survey invitation should be send to this participant
    private function calculateNotificationFirstSendTime($project_id, $alert_id, $record, $event_id, $instrument, $instance, $current_instrument='')
    {
        $alert = $this->getKeyIdFromAlertId($project_id, $alert_id);
        $cron_send_email_on = $this->getAlertSetting("cron-send-email-on", $project_id)[$alert];

        // SEND AT EXACT TIME
        if ($cron_send_email_on == 'date')
        {
            // Set invitation time as the "exact date/time" specified
            $invitationTime = $this->getAlertSetting("cron_send_email_on_date", $project_id)[$alert];
        }

        // IMMEDIATELY SEND
        elseif ($cron_send_email_on == 'now')
        {
            // Set invitation time as current time right now
            $invitationTime = NOW;
        }

        // SEND AFTER SPECIFIED LAPSE OF TIME
        elseif ($cron_send_email_on == 'time_lag')
        {
            // Get temporal components
            $days = $this->getAlertSetting("cron_send_email_on_time_lag_days", $project_id)[$alert];
            $hours = $this->getAlertSetting("cron_send_email_on_time_lag_hours", $project_id)[$alert];
            $minutes = $this->getAlertSetting("cron_send_email_on_time_lag_minutes", $project_id)[$alert];
            if ($days == '') $days = 0;
            if ($hours == '') $hours = 0;
            if ($minutes == '') $minutes = 0;
            // If using datetime field for time lag, get the field and its value
            $dataField = $this->getAlertSetting("cron_send_email_on_field", $project_id)[$alert];
            $beforeAfter = $this->getAlertSetting("cron_send_email_on_field_after", $project_id)[$alert];
            if ($dataField != '') {
                // Format the field logic to prep for piping
                $Proj = new Project($project_id);
                if ($Proj->longitudinal) $dataField = LogicTester::logicPrependEventName($dataField, 'event-name', $Proj);
                $dataField = LogicTester::logicAppendCurrentInstance($dataField, $Proj);
                $dataValue = trim(Piping::replaceVariablesInLabel($dataField, $record, $event_id, $instance, array(), false, $project_id, false, $instrument, 1, false, false, $current_instrument, null, true));
                // Make sure the date value is not a missing data code
                $missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
                $dataIsMissingCode = (!empty($missingDataCodes) && in_array($dataValue, $missingDataCodes));
                // Don't schedule this alert if we don't have a valid value
                if ($dataValue == '' || $dataIsMissingCode) return false;
                // If timing is set to send "before" the value of this field, change all the numbers to negative
                if ($beforeAfter == 'before') {
                    $days = -1*$days;
                    $hours = -1*$hours;
                    $minutes = -1*$minutes;
                }
                // Calculate invitation time from field value
                $invitationTime = date("Y-m-d H:i:s", strtotime($dataValue) + ($days*86400) + ($hours*3600) + ($minutes*60));
            } else {
                // Calculate invitation time by adding time lag to current time
                $invitationTime = date_mktime("Y-m-d H:i:s", date("H")+$hours,date("i")+$minutes,date("s"),date("m"),date("d")+$days,date("Y"));
            }
        }

        // SEND ON NEXT SPECIFIED DAY/TIME
        elseif ($cron_send_email_on == 'next_occurrence')
        {
            // Set time component of the timestamp
            $timeTS = $this->getAlertSetting("cron_send_email_on_next_time", $project_id)[$alert];
            $condition_send_next_day_type = $this->getAlertSetting("cron_send_email_on_next_day_type", $project_id)[$alert];
            // Set the date component of the timestamp
            // If day type is "WEEKEND DAY"
            if ($condition_send_next_day_type == 'WEEKENDDAY') {
                // If today is Saturday, then next weekend day = next Sunday (i.e. tomorrow)
                if (date('D') == 'Sat') {
                    $dateTS = date('Y-m-d', strtotime('NEXT SUNDAY'));
                }
                // If today is any day other than Saturday, then next weekend day is next Saturday
                else {
                    $dateTS = date('Y-m-d', strtotime('NEXT SATURDAY'));
                }
            }
            // Any other day type (can use strtotime to parse into date)
            else {
                $dateTS = date('Y-m-d', strtotime('NEXT '.$condition_send_next_day_type));
            }
            // Combine date and time components
            $invitationTime = "$dateTS $timeTS";
        }

        // Validate the date/time with regex (in case components are missing or are calculated incorrectly)
        $datetime_regex = '/^(\d{4})([-\/.])?(0[1-9]|1[012])\2?(0[1-9]|[12][0-9]|3[01])\s([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
        if (!preg_match($datetime_regex, $invitationTime)) $invitationTime = false;

        // Return invitation date/time
        return $invitationTime;
    }

    /**
     * Function that deletes a specific recurring notification
     */
    function deleteQueuedEmail($aq_id, $project_id){
        // Remove from table
        $aq_id = (int)$aq_id;
        $sql = "delete from redcap_alerts_recurrence where aq_id = ".$aq_id;
        if (db_query($sql)) {
            unset($this->alerts_queue[$project_id]);
            return true;
        } else {
            return false;
        }
    }

    // Sends a notification
    function sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
    {
        global $twilio_enabled_global, $twilio_display_info_project_setup, $twilio_enabled_by_super_users_only;
        // Get alert index id
        $id = $this->getKeyIdFromAlertId($project_id, $alert_id);
        // Get alert attributes
        $email_subject = $this->getAlertSetting("email-subject", $project_id)[$id];
        $alert_message = $this->getAlertSetting("alert-message", $project_id)[$id];
		$alert_type = $this->getAlertSetting("alert-type", $project_id)[$id];
        $prevent_piping_identifiers = $this->getAlertSetting("prevent-piping-identifiers", $project_id)[$id];
        // Set project and get data (if needed)
        $Proj = new \Project($project_id);
        $repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : "";
        $isLongitudinal = $Proj->longitudinal;
        if (empty($data)) {
            $data = Records::getData($project_id, 'array', $record);
        }
		$alertSentSuccesfully = false; // default
		$alertInstrument = $this->getAlertSetting("form-name", $project_id)[$id];
		$alertEventId = $this->getAlertSetting("form-name-event", $project_id)[$id];
		if (($alertInstrument == '' || $alertEventId == '') && is_numeric($event_id)) $alertEventId = $event_id;
		if ($alertEventId == '') $alertEventId = $Proj->firstEventId;
		$alertInstance = ($alertInstrument == '') ? 1 : $instance;

        // Piping
        $alert_message = Piping::replaceVariablesInLabel($alert_message, $record, $event_id, $instance, $data,false,
                            $project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers);
        $email_subject = Piping::replaceVariablesInLabel($email_subject, $record, $event_id, $instance, $data,false,
                            $project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers);

        // Initialize values (even if we aren't sending via EMAIL)
		$mail = new Message();
		// Email Addresses
		$mail = $this->setEmailAddresses($mail, $project_id, $record, $event_id, $instrument, $instance, $id, $data);

		if ($alert_type == 'EMAIL')
		{
		    // Email From: Get the Reply-To and Display Name for this message
            $fromDisplayName = trim($this->getAlertSetting("email-from-display", $project_id)[$id]);
            $email_from = trim($this->getAlertSetting("email-from", $project_id)[$id]);
            if (!empty($email_from)) {
                if (!isEmail($email_from)) {
                    $email_from = Piping::replaceVariablesInLabel($email_from, $record, $event_id, $instance, $data,false,
                                        $project_id, false, $repeat_instrument, 1, false, false, $instrument);
                }
                if (isEmail($email_from)) {
                    // Set From and From Name
                    $mail->setFrom($email_from);
                    $mail->setFromName($fromDisplayName);
                } else {
                    $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_55'], $lang['alerts_57']." (The \"From\" email address \"$email_from\" is not a valid email address - Project: $project_id, Record: $record, Alert #".($id+1).")");
                }
            } else {
                $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_56'], $lang['alerts_58']." (The \"From\" email address is missing - Project: $project_id, Record: $record, Alert #".($id+1).")");
            }
            // Body and subject
            $mail->setBody($alert_message);
            $mail->setSubject($email_subject);
            // Embedded images
            $mail = $this->setEmbeddedImages($mail, $project_id);
            // Attachments
            $mail = $this->setAttachments($mail, $project_id, $id);
            // Attchment from field variable
            $mail = $this->setAttachmentsREDCapVar($mail, $project_id, $data, $record, $event_id, $instrument, $instance, $id, $isLongitudinal);
		}
		// Get phone numbers if sending via SMS or VOICE CALL
        elseif ($alert_type == 'SMS' || $alert_type == 'VOICE_CALL')
        {
            $alertPhoneNumbersTo = array();
			// Init Twilio (in case SMS or Voice Calls are used)
			if ($twilio_enabled_global && $Proj->twilio_enabled_alerts) TwilioRC::init();
			// Instantiate a client to Twilio's REST API
			$twilioClient = new Services_Twilio($Proj->project['twilio_account_sid'], $Proj->project['twilio_auth_token']);
			// Gather all phone numbers to send the SMS to
			$phone_number_to = $this->getAlertSetting("phone_number_to", $project_id)[$id];
			foreach (explode(";", $phone_number_to) as $this_phone_number)
			{
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
                // Replace participant phone variable
                if ($this_phone_number == self::participant_phone_var) {
                    // Fetch email value
                    $emailArray = Survey::getResponsesEmailsIdentifiers(array($record), $Proj->forms[$instrument]['survey_id'], $project_id);
                    $participantPhone = isset($emailArray[$record]) ? $emailArray[$record]['phone'] : "";
                    // Replace variable with email value
                    $this_phone_number2 = str_replace(self::participant_phone_var, $participantPhone, $this_phone_number);
                } else {
                    // If this is a variable, then replace it
                    $this_phone_number2 = Piping::replaceVariablesInLabel($this_phone_number, $record, $event_id, $instance, $data,false,
                                            $project_id, false, $instrument, 1, false, false, $instrument, null, false);
                }
                // Remove all non-numerals
                $this_phone_number2 = preg_replace("/[^0-9]/", "", $this_phone_number2);
                if (isPhoneUS($this_phone_number2) && substr($this_phone_number2, 0, 1) != "1") $this_phone_number2 = "1".$this_phone_number2;
                // Add to array if not already in it
                if (!in_array($this_phone_number2, $alertPhoneNumbersTo)) {
                    $alertPhoneNumbersTo[] = $this_phone_number2;
                }
            }
        }

        // Send as SMS
        if ($alert_type == 'SMS')
        {
			// Send SMS messages
			foreach ($alertPhoneNumbersTo as $this_phone_number)
			{
				// Send SMS to the phone number
				$success = TwilioRC::sendSMS($alert_message, $this_phone_number, $twilioClient, $Proj->project['twilio_from_number'], true, $project_id);
				if ($success) $alertSentSuccesfully = true;
			}
        }

		// Send as VOICE CALL
		elseif ($alert_type == 'VOICE_CALL')
        {
			// Mark as sent (we will undo this later if the call fails for whatever reason) - for email and SMS, this is performed later
			list ($alert_sent_id, $alert_sent_log_id) = $this->addRecordSent($alert_id, $record, $alertEventId, ($alertInstrument == '' ? $repeat_instrument : $alertInstrument), $instance, $mail, $project_id);
			// Set the survey URL that Twilio will make the request to
			$twilio_url = APP_PATH_SURVEY_FULL . '?a=' . base64_encode(encrypt($alert_sent_log_id));
			// Call the phone numbers
			foreach ($alertPhoneNumbersTo as $this_phone_number)
			{
				try {
					// Create hash so that we can add it to callback url
					$callback_hash = generateRandomHash(50);
					$call = $twilioClient->account->calls->create(TwilioRC::formatNumber($Proj->project['twilio_from_number']), TwilioRC::formatNumber($this_phone_number), $twilio_url, array(
						"StatusCallback" => $twilio_url,
						"FallbackUrl" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash&__error=1",
						"IfMachine"=>"Continue"
					));
					// Add the sid and sid_hash to the db table so that we can delete the log for this event once it has completed
					TwilioRC::addEraseCall($project_id, $call->sid, $callback_hash);
					$alertSentSuccesfully = true;
				} catch (Exception $e) {  }
			}
			// Undo these rows from db tables if call fails somehow
			if (!$alertSentSuccesfully && is_numeric($alert_sent_id))
			{
				$sql = "delete from redcap_alerts_sent where alert_sent_id = $alert_sent_id";
				db_query($sql);
			}
		}
        // Send as EMAIL
        else
        {
            $alertSentSuccesfully = $mail->send();
		}

		// Delete any attachments stored temporarily in the Temp directory
		$this->deleteTempAttachments();

        if (!$alertSentSuccesfully)
        {
            // Failed to send
            $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id),"Alert Error" ,"Alert error occurred in Project ".$project_id.", Record ".$record.", Alert #".($id+1)."<br>\nError: ".$mail->ErrorInfo);
            $email_sent_ok = false;
        }
        else
        {
            // Set last time sent and sent count in alerts table
            $sql = "update redcap_alerts 
                    set email_timestamp_sent = '".date('Y-m-d H:i:s')."', email_sent = 1
                    where alert_id = $alert_id";
            db_query($sql);

			// Mark as sent (but not for voice calls, which were added the table before the call was made)
			if ($alert_type != 'VOICE_CALL') {
				list ($alert_sent_id, $alert_sent_log_id) = $this->addRecordSent($alert_id, $record, $alertEventId, ($alertInstrument == '' ? $repeat_instrument : $alertInstrument), $instance, $mail, $project_id);
			}

            #Add some logs
            $email_list = array();
            foreach ($mail->getAllRecipientAddresses() as $email) {
                $email_list[] = $email;
            }

            // Log this alert being sent
            $changes_made = "From: '".$mail->getFrom()."',\nTo: '".implode("; ", $email_list)."',\nSubject: '$email_subject',\nMessage: '".strip_tags($alert_message)."'";
            $alert_number = $id+1;
            // \REDCap::logEvent($action_description, $changes_made, null, $record, $event_id, $project_id);
            Logging::logEvent($sql, "redcap_alerts", "UPDATE", $record,"Alert #{$alert_number},\n$changes_made", "Send alert", "",
                (defined("USERID") ? USERID : "SYSTEM"), $project_id, true, $event_id, $instance);

            $email_sent_ok = true;
        }

        // Return status
        return $email_sent_ok;
    }

	// Delete any attachments stored temporarily in the Temp directory
	private function deleteTempAttachments()
	{
		foreach ($this->alertAttachmentsToDelete as $file) unlink($file);
		$this->alertAttachmentsToDelete = array();
	}

	// Get the alert message sent from the alert log
	public function getAlertMessageByAlertSentLogId($alert_sent_log_id)
	{
		$sql = "select message from redcap_alerts_sent_log where alert_sent_log_id = $alert_sent_log_id";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Get the project_id from the alert log id
	public function getAlertProjectIdByAlertSentLogId($alert_sent_log_id)
	{
		$sql = "select a.project_id from redcap_alerts_sent_log l, redcap_alerts_sent s, redcap_alerts a
                where l.alert_sent_log_id = $alert_sent_log_id and s.alert_sent_id = l.alert_sent_id and s.alert_id = a.alert_id";
		$q = db_query($sql);
		return db_result($q, 0);
	}

    /**
     * Function that adds the email addresses into the mail.
     * @param $mail
     * @param $project_id
     * @param $record
     * @param $event_id
     * @param $instrument
     * @param $instance
     * @param $data
     * @param $id
     * @param bool $isLongitudinal
     * @return mixed
     */
    function setEmailAddresses($mail, $project_id, $record, $event_id, $instrument, $instance, $id, $data=array())
    {
        $email_to = $this->getAlertSetting("email-to", $project_id)[$id];
        $email_cc = $this->getAlertSetting("email-cc", $project_id)[$id];
        $email_bcc = $this->getAlertSetting("email-bcc", $project_id)[$id];

        // Replace participant email variable in to/cc/bcc
        $replace_participant_email =   (strpos($email_to, self::participant_email_var) !== false ||
                                        strpos($email_cc, self::participant_email_var) !== false ||
                                        strpos($email_bcc, self::participant_email_var) !== false);
        if ($replace_participant_email) {
            // Fetch email value
            $emailArray = Survey::getResponsesEmailsIdentifiers(array($record), $survey_id, $project_id);
            $participantEmail = isset($emailArray[$record]) ? $emailArray[$record]['email'] : "";
            // Replace variable with email value
            $email_to = str_replace(self::participant_email_var, $participantEmail, $email_to);
            $email_cc = str_replace(self::participant_email_var, $participantEmail, $email_cc);
            $email_bcc = str_replace(self::participant_email_var, $participantEmail, $email_bcc);
        }

        // Perform normal piping to replace field variables in to/cc/bcc
        $email_to = Piping::replaceVariablesInLabel($email_to, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);
        $email_cc = Piping::replaceVariablesInLabel($email_cc, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);
        $email_bcc = Piping::replaceVariablesInLabel($email_bcc, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);

        $email_to_ok = $this->check_email($email_to,$project_id);
        $email_cc_ok = $this->check_email($email_cc,$project_id);
        $email_bcc_ok = $this->check_email($email_bcc,$project_id);

        if(!empty($email_to_ok)) {
            foreach ($email_to_ok as $email) {
                $mail = $this->check_single_email($mail,$email, 'to', $project_id);
            }
        }

        if(!empty($email_cc_ok)){
            foreach ($email_cc_ok as $email) {
                $mail = $this->check_single_email($mail, $email, 'cc', $project_id);
            }
        }

        if(!empty($email_bcc_ok)){
            foreach ($email_bcc_ok as $email) {
                $mail = $this->check_single_email($mail,$email, 'bcc', $project_id);
            }
        }
        return $mail;
    }

    /**
     * Function that adds attachments into the mail
     * @param $mail
     * @param $project_id
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    function setAttachments($mail, $project_id, $id){
        for($i=1; $i<6 ; $i++){
            $edoc = $this->getAlertSetting("email-attachment".$i,$project_id)[$id];
            if(is_numeric($edoc)){
                $mail = $this->addNewAttachment($mail, $edoc, $project_id,'files');
            }
        }
        return $mail;
    }

    /**
     * Function that adds piped attachments into the mail
     * @param $mail
     * @param $project_id
     * @param $data
     * @param $record
     * @param $event_id
     * @param $instrument
     * @param $repeat_instance
     * @param $id
     * @param bool $isLongitudinal
     * @return mixed
     * @throws \Exception
     */
    function setAttachmentsREDCapVar($mail, $project_id, $data, $record, $event_id, $instrument, $repeat_instance, $id, $isLongitudinal=false)
    {
        $email_attachment_variable = trim($this->getAlertSetting("email-attachment-variable", $project_id)[$id]);
        $edocs = array();
        if (!empty($email_attachment_variable)) {
            $Proj = new Project($project_id);
            $var = preg_split("/[;,]+/", $email_attachment_variable);
            foreach ($var as $attachment) {
                if (\LogicTester::isValid(trim($attachment))) {
                    if ($isLongitudinal) {
                        $attachment = LogicTester::logicPrependEventName($attachment, $Proj->getUniqueEventNames($event_id), $Proj);
                    }
                    if ($Proj->hasRepeatingFormsEvents()) {
                        $attachment = LogicTester::logicAppendInstance($attachment, $Proj, $event_id, $instrument, $repeat_instance);
                    }
                    $edoc = Piping::replaceVariablesInLabel($attachment, $record, $event_id, $repeat_instance, $data,false,
                            $project_id, false, $instrument, 1, false, false, $instrument, null, false);
                    $edoc = trim($edoc);
                    if (is_numeric($edoc)) $edocs[] = $edoc;
                }
            }
        }
        if (!empty($edocs))  {
            $edocs = array_unique($edocs);
            foreach ($edocs as $edoc) {
                $this->addNewAttachment($mail, $edoc, $project_id, 'files');
            }
        }
        return $mail;
    }

    /**
     * Function that attaches images into the mail (ONLY USED FOR LEGACY "EMAIL ALERTS" THAT WERE CONVERTED)
     * @param $mail
     * @param $project_id
     * @param $alert_message
     * @return mixed
     * @throws \Exception
     */
    function setEmbeddedImages($mail, $project_id)
    {
        preg_match_all('/src=[\"\'](.+?)[\"\'].*?/i', $mail->getBody(), $result);
        $result = array_unique($result[1]);
        foreach ($result as $img_src) {
            preg_match_all('/(?<=file=)\\s*([0-9]+)\\s*/', $img_src, $result_img);
            $edoc = array_unique($result_img[1])[0];
            if (is_numeric($edoc)) {
                $mail = $this->addNewAttachment($mail, $edoc, $project_id,'images');
				// Replace img SRC in message with CID
				$filename = Files::getEdocName($edoc);
				$mail->setBody(str_replace($img_src, "cid:$filename", $mail->getBody()));
            }
        }
        return $mail;
    }

    // Check if email has been sent for this alert-record-instrument-etc.
    function alertAlreadySent($alert_id, $record, $event_id, $instrument, $instance)
    {
        $sql = "select 1 from redcap_alerts_sent where alert_id = '".db_escape($alert_id)."' and record = '".db_escape($record)."' 
                and event_id = '".db_escape($event_id)."' and instrument = '".db_escape($instrument)."' and instance = '".db_escape($instance)."'";
        $q = db_query($sql);
        return ($q && db_num_rows($q) == 1);
    }

    /**
     * Function that if valid adds an email address to the mail
     * @param $mail
     * @param $email
     * @param $option, if they are To or CC emails
     * @param $project_id
     * @return mixed
     */
    function check_single_email($mail, $email, $option, $project_id)
    {
        global $lang;
        $email = trim($email);
        if (isEmail($email)) {
            if($option == "to"){
                $current = $mail->getTo();
                if ($current != '') $current .= ";";
                $mail->setTo($current.$email);
            }else if($option == "cc"){
				$current = $mail->getCc();
				if ($current != '') $current .= ";";
                $mail->setCc($current.$email);
            }else if($option == "bcc"){
				$current = $mail->getBcc();
				if ($current != '') $current .= ";";
                $mail->setBcc($current.$email);
            }
        } else {
            $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_55'], $lang['alerts_57']." ($email in Project: $project_id)");
		}
        return $mail;
    }

    /**
     * Function to send an extra error email if there is a value in the configuration
     * @param $emailFailed_var
     * @param $subject
     * @param $message
     */
    function sendFailedEmailRecipient($emailFailed_var, $subject, $message)
    {
        global $project_contact_email;
        if (!empty($emailFailed_var))
        {
            ## It's already an array, so don't convert
            if(is_array($emailFailed_var)) {
                $emailsFailed = $emailFailed_var;
            }
            else {
                $emailsFailed = preg_split("/[;,]+/", $emailFailed_var);
            }
            foreach ($emailsFailed as $failed) {
                REDCap::email(trim($failed), $project_contact_email, $subject, $message);
            }
        }
    }

    /**
     * Function that checks if the emails are valid and sends an error email in case there's an error
     * @param $emails
     * @param $project_id
     * @return array|string
     */
    function check_email($emails, $project_id)
    {
        global $lang;
        $email_list = array();
        $email_list_error = array();
        $emails = preg_split("/[;,]+/", $emails);
        foreach ($emails as $email){
            $email = trim($email);
            if(!empty($email)){
                if (isEmail($email)) {
                    //VALID
                    array_push($email_list,$email);
                }else{
                    array_push($email_list_error,$email);

                }
            }
        }
        if (!empty($email_list_error)) {
			$this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_55'], $lang['alerts_57']." ($email in Project: $project_id)");
		}
        return $email_list;
    }

    /**
     * Function that adds a ne attachment (file or image type) to the mail if the file exists in the DB and if it's no bigger than 3MB to send. Otherwise it sends an error email
     * @param $mail
     * @param $edoc
     * @param $project_id
     * @return mixed
     */
    function addNewAttachment($mail, $edoc, $project_id, $type='files')
    {
        global $edoc_storage_option, $lang;
        if (!empty($edoc))
        {
            list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($edoc);
            if (strlen($fileContent) > (self::MAX_ATTACHMENT_SIZE_MB*1024*1024)) {
               $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_59'],
				   $lang['alerts_60']." ".self::MAX_ATTACHMENT_SIZE_MB." MB".$lang['period']." (Project: ".$project_id.")");
            } else {
				// Save file to TEMP to handle non-local storage types
				$filename = APP_PATH_TEMP . date('YmdHis') . "_alerts_pid" . $project_id . "_" . substr(sha1(rand()), 0, 6) . getFileExt($docName, true);
				file_put_contents($filename, $fileContent);
				// Add the attachment
				$mail->setAttachment($filename, $docName);
				$this->alertAttachmentsToDelete[] = $filename;
            }
        }
        return $mail;
    }

    // Add this alert-record-instrument-etc to alerts_sent table
    function addRecordSent($alert_id, $record, $event_id, $instrument, $instance, $mailObject, $project_id)
    {
        if (!is_numeric($instance)) $instance = 1;
        // Get from address (pull directly from alert definition in case using Universal FROM address)
		$id = $this->getKeyIdFromAlertId($project_id, $alert_id);
		$phone_number_to = trim($this->getAlertSetting("phone-number-to", $project_id)[$id]);
		$alert_type = trim($this->getAlertSetting("alert-type", $project_id)[$id]);
		$email_from = $mailObject->getFrom();
        // Obtain message settings to add
		$subject = $mailObject->getSubject();
		$message = $mailObject->getBody();
		$email_to = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getTo());
		$email_cc = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getCc());
		$email_bcc = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getBcc());
		$phone_number_to = str_replace(array(" ",",",";"), array("",";","; "), $phone_number_to);
        $attachment_names = array();
        foreach ($mailObject->getAttachments() as $attachment_key=>$this_attachment_path) {
            $attachment_names[] = $mailObject->attachmentsNames[$attachment_key];
        }
        $attachment_names = implode("; ", $attachment_names);
        // Add to tables
        $sql = "insert into redcap_alerts_sent (alert_id, record, event_id, instrument, instance, last_sent) values 
                ('".db_escape($alert_id)."', '".db_escape($record)."', ".checkNull($event_id).", '".db_escape($instrument)."', 
                '".db_escape($instance)."', '".NOW."') 
                on duplicate key update alert_id = '".db_escape($alert_id)."', record = '".db_escape($record)."', 
                event_id = ".checkNull($event_id).", instrument = '".db_escape($instrument)."', instance = '".db_escape($instance)."', 
                last_sent = '".NOW."', alert_sent_id = LAST_INSERT_ID(alert_sent_id)";
        $q = db_query($sql);
        if ($q) {
            $alert_sent_id = db_insert_id();
            $sql = "insert into redcap_alerts_sent_log (alert_sent_id, time_sent, email_from, email_to, email_cc, email_bcc, subject, message, attachment_names, alert_type, phone_number_to) 
                    values ($alert_sent_id, '".NOW."', ".checkNull($email_from).", ".checkNull($email_to).", ".checkNull($email_cc).", 
                    ".checkNull($email_bcc).", ".checkNull($subject).", ".checkNull($message).", ".checkNull($attachment_names).", ".checkNull($alert_type).", ".checkNull($phone_number_to).")";
            if (db_query($sql)) {
				$alert_sent_log_id = db_insert_id();
                return array($alert_sent_id, $alert_sent_log_id);
            }
        }
        return array(null, null);
    }

    // Find filename of edoc by doc_id
    public function getEdocNameById($edoc)
    {
        header('Content-type: application/json');
        echo json_encode(array(
            'edoc_id' => $edoc,
            'doc_name' => Files::getEdocName($edoc),
            'status' => 'success'
        ));
    }

    // Check if an email address is acceptable regarding the "domain allowlist for user emails" (if enabled)
    public static function emailInDomainAllowlist($email='')
    {
        global $alerts_email_freeform_domain_allowlist;
        $email = trim($email);
        if ($alerts_email_freeform_domain_allowlist == '' || $email == '') return null;
        $email_domain_allowlist_array = explode("\n", str_replace("\r", "", $alerts_email_freeform_domain_allowlist));
        list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
        return (in_array($emailDomain, $email_domain_allowlist_array));
    }

    // Render the setup page
    public function renderSetup()
    {
        extract($GLOBALS);

        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

        $projectData = $this->getAlertSettings(PROJECT_ID);
        $indexSubSet = count($projectData);

        $hasRepeatingEvents = $Proj->hasRepeatingEvents();
        $hasRepeatingForms = $Proj->hasRepeatingForms();
        $hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);

        // Set the "just once" option text depending on the project type
        $justOnceText = $lang['alerts_61'];

        // Get email addresses and names from table
        $fromEmails = array();
        foreach (User::getEmailAllProjectUsers(PROJECT_ID) as $thisEmail) {
            $fromEmails[$thisEmail] = $thisEmail;
        }
        if (SUPER_USER && !isset($fromEmails[$GLOBALS['user_email']])) {
            // If admin is not a user in the project, add their primary email to the drop-down
            $fromEmails[$GLOBALS['user_email']] = $GLOBALS['user_email'] . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
        }

        // Get user phone numbers
        $userPhones = array();
        foreach (User::getPhoneAllProjectUsers(PROJECT_ID, false, true) as $thisPhone=>$thisFirstLastName) {
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            $userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $thisFirstLastName . $lang['rightparen'];
        }
        if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone']]) && $userPhones[$GLOBALS['user_phone']] != '') {
            $thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone']);
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            // If admin is not a user in the project, add their primary email to the drop-down
            $userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
        }
        if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone_sms']]) && $userPhones[$GLOBALS['user_phone_sms']] != '') {
            $thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone_sms']);
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            // If admin is not a user in the project, add their primary email to the drop-down
            $userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
        }
        ksort($userPhones);

        // Set DD options for all File Upload fields (across all events)
        $fileFieldLabelMaxLength = $Proj->longitudinal ? 35 : 55;
        $fieldUploadFieldOptions = $fieldUploadFieldOptionsEvents = array();
        foreach ($Proj->metadata as $this_field=>$attr1) {
            if ($attr1['element_type'] != 'file') continue;
            // Clean the label
            $attr1['element_label'] = trim(str_replace(array("\r\n", "\n", "&nbsp;"), array(" ", " ", " "), strip_tags($attr1['element_label'])));
            // Truncate label if long
            if (strlen($attr1['element_label']) > $fileFieldLabelMaxLength) {
                $attr1['element_label'] = trim(substr($attr1['element_label'], 0, ($fileFieldLabelMaxLength-18))) . "... " . trim(substr($attr1['element_label'], -15));
            }
            $fieldUploadFieldOptions["[$this_field]"] = "[$this_field] \"{$attr1['element_label']}\"";
            if ($Proj->longitudinal) {
                $fieldUploadFieldOptions["[$this_field]"] .= " [Current Event]";
                foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                    $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                    $thisForm = $Proj->metadata[$this_field]['form_name'];
                    if (in_array($thisForm, $theseForms)) {
                        $fieldUploadFieldOptionsEvents["[$thisEventName][$this_field]"] = "[$thisEventName][$this_field] \"{$attr1['element_label']}\" (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
                    }
                }
            }
        }
        $fieldUploadFieldOptions = $fieldUploadFieldOptions + $fieldUploadFieldOptionsEvents;

        // Set the To phone numbers as the projects users + survey participant
        $toPhones = array();
        $ddProjectUserLabel = $lang['alerts_66'];
        $ddProjectVarLabel1 = (!$alerts_allow_phone_variables && SUPER_USER) ? $lang['alerts_65'] : "";
        $ddProjectFreeformLabel = (!$alerts_allow_phone_freeform && SUPER_USER) ? " ".$lang['alerts_65'] : "";
        $ddProjectVarLabel = "-- {$lang['alerts_206']} $ddProjectVarLabel1 --";
        if (!empty($Proj->surveys)) {
            $toPhones[self::participant_phone_var] = $lang['alerts_67'];
            $ddProjectUserLabel = $lang['alerts_68'];
        }
        foreach ($userPhones as $thisUserPhone=>$thisUserPhoneDisplay) {
            $toPhones[$ddProjectUserLabel][$thisUserPhone] = $thisUserPhoneDisplay;
        }
        // Add email-validated fields to multi-select fields
        if ($alerts_allow_phone_variables || SUPER_USER)
        {
            // Gather all phone validation types + integer validation
            $valTypes = getValTypes();
            $valTypesPhoneInteger = array('int');
            foreach ($valTypes as $valName=>$valType) {
                if ($valType['data_type'] == 'phone') {
                    $valTypesPhoneInteger[] = $valName;
                }
            }
            // Get all phone and integer fields
            $phoneFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, $valTypesPhoneInteger);
            if (!empty($phoneFieldsLabels)) {
                foreach ($phoneFieldsLabels as $formLabel=>$thesePhoneFields) {
                    foreach ($thesePhoneFields as $thisVar=>$thisOptionLabel) {
                        list ($thisVarLabel, $thisOptionLabel) = explode(" ", $thisOptionLabel, 2);
                        if ($longitudinal) {
                            $toPhones[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel ".$lang['alerts_70'];
                            foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                                $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                                $thisForm = $Proj->metadata[$thisVar]['form_name'];
                                if (in_array($thisForm, $theseForms)) {
                                    $toPhones[$ddProjectVarLabel]["[$thisEventName][$thisVar]"] = "[$thisEventName][$thisVar] $thisOptionLabel (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
                                }
                            }
                        } else {
                            $toPhones[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel";
                        }
                    }
                }
            }
        }

        // Set the To email addresses as the projects users + survey participant
        $toEmails = array();
        $ddProjectUserLabel = $lang['alerts_66'];
        $ddProjectVarLabel1 = (!$alerts_allow_email_variables && SUPER_USER) ? $lang['alerts_65'] : "";
        $ddProjectFreeformLabel = (!$alerts_allow_email_freeform && SUPER_USER) ? " ".$lang['alerts_65'] : "";
        $ddProjectVarLabel = "-- {$lang['alerts_69']} $ddProjectVarLabel1 --";
        if (!empty($Proj->surveys)) {
            $toEmails[self::participant_email_var] = $lang['alerts_67'];
            $ddProjectUserLabel = $lang['alerts_68'];
        }
        foreach ($fromEmails as $thisFromEmail=>$thisFromEmailLabel) {
            $toEmails[$ddProjectUserLabel][$thisFromEmail] = $thisFromEmailLabel;
        }
        // Add email-validated fields to multi-select fields
        if ($alerts_allow_email_variables || SUPER_USER)
        {
            $emailFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, 'email');
            if (!empty($emailFieldsLabels)) {
                foreach ($emailFieldsLabels as $formLabel=>$emailFields) {
                    foreach ($emailFields as $thisVar=>$thisOptionLabel) {
                        list ($thisVarLabel, $thisOptionLabel) = explode(" ", $thisOptionLabel, 2);
                        if ($longitudinal) {
                            $toEmails[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel ".$lang['alerts_70'];
                            foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                                $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                                $thisForm = $Proj->metadata[$thisVar]['form_name'];
                                if (in_array($thisForm, $theseForms)) {
                                    $toEmails[$ddProjectVarLabel]["[$thisEventName][$thisVar]"] = "[$thisEventName][$thisVar] $thisOptionLabel (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
                                }
                            }
                        } else {
                            $toEmails[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel";
                        }
                    }
                }
            }
        }

        $message="";
        $message_text = array(
            //'C'=>'<b>Success!</b> The alert and its settings have been saved.',
            'A'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_71'],
            'U'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_72'],
			'P'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_73'],
            'D'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_74'],
            'B'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_75'],
			'R'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_76']
        );

        if (array_key_exists('message', $_REQUEST)){
            $message = $message_text[$_REQUEST['message']];
        }

        // HTML for form-event drop-down list
        $formAnyEventDropdownOptions = array('-'=>$lang['alerts_196']);
        $formEventDropdownOptions = array();
        foreach ($Proj->eventsForms as $this_event_id=>$these_forms)
        {
            foreach ($these_forms as $this_form)
            {
                if ($longitudinal) {
                    if (!isset($formEventDropdownOptions["$this_form-"])) {
                        $formAnyEventDropdownOptions["[Any event]"]["$this_form-"] = "\"{$Proj->forms[$this_form]['menu']}\" [Any event]";
                    }
                    $thisEvent = $Proj->eventInfo[$this_event_id]['name_ext'];
                    $formEventDropdownOptions["$thisEvent"]["$this_form-$this_event_id"] = "\"{$Proj->forms[$this_form]['menu']}\" ($thisEvent)";
                } else {
                    $formEventDropdownOptions["$this_form-$this_event_id"] = "\"{$Proj->forms[$this_form]['menu']}\"";
                }
            }
        }

        loadJS('Alerts.js');
        ?>
        <link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>Alerts.css" media="screen,print">
        <script type="text/javascript">
            var message = <?=json_encode($message)?>;
            var indexSubSet = <?=json_encode($indexSubSet)?>;
            var alerts_email_freeform_domain_allowlist = new Array(<?php echo ($alerts_email_freeform_domain_allowlist == '' ? '' : prep_implode(explode("\n", strtolower(str_replace("\r", "", $alerts_email_freeform_domain_allowlist))))) ?>);
        </script>
        <?php
        // Add language used to Alerts.js
		addLangToJS(array('alerts_24','alerts_36','alerts_37','alerts_38','docs_72','alerts_39','alerts_40','alerts_41','alerts_42','alerts_43','alerts_44','alerts_45', 'survey_1237',
                          'alerts_46','alerts_47','alerts_48','alerts_49','period','alerts_50','global_01','alerts_51','alerts_52','alerts_53','alerts_54','alerts_197','alerts_198','alerts_214'));

        $tr_class = 'in';
        if ($indexSubSet > 0) {
            //collapse columns as there is some existing info
            $tr_class = '';
        }
        // Set defaults for new alerts
        $alertDefaults = $this->getAlertDefaults();
        $alertDefaults['alert-stop-type'] = 'RECORD';
        $alertDefaults['email-from'] = $user_email;
        $alertDefaults['email-incomplete'] = '1';
        ?>

        <div class="projhdr"><i class="fas fa-bell"></i> <?=$lang['global_154']?></div>
        <div style="width:950px;max-width:950px;" class="d-none d-md-block mt-3 mb-2">
			<?=$lang['alerts_77']?>
            <a href='javascript:;' style='text-decoration:underline;' onclick="$(this).remove();$('.alert-instructions-more').addClass('d-md-block');"><?=$lang['alerts_32']?></a>
        </div>
        <div class="alert-instructions-more d-none mb-2" style="width:950px;max-width:950px;">
			<?=$lang['alerts_78']?>
        </div>
        <div class="alert-instructions-more d-none mb-2" style="width:950px;max-width:950px;">
			<?=$lang['alerts_79']?>
        </div>

        <div id='errMsgContainerIE9' class="alert alert-danger" role="alert" style="display:none;margin-bottom:20px;">
			<?=$lang['alerts_80']?>
        </div>

        <div class="clearfix">
            <div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 30px;width:900px;">
                <ul>
                    <li<?php echo (!isset($_GET['log']) ? ' class="active"' : '') ?>>
                        <a href="<?php echo APP_PATH_WEBROOT ?>index.php?pid=<?php echo $project_id ?>&route=AlertsController:setup" style="font-size:13px;color:#393733;padding:6px 12px 7px 13px;"><i class="fas fa-bell mr-1"></i><?=$lang['alerts_81']?></a>
                    </li>
                    <li<?php echo (isset($_GET['log']) ? ' class="active"' : '') ?>>
                        <a href="<?php echo APP_PATH_WEBROOT ?>index.php?pid=<?php echo $project_id ?>&route=AlertsController:setup&log=1" style="font-size:13px;color:#393733;padding:6px 12px 7px 13px;"><i class="fas fa-table mr-1"></i><?=$lang['alerts_20']?></a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="modal fade" id="external-modules-configure-modal-preview" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
            <div class="modal-dialog" role="document" style="width: 800px">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">
                            <span id="myModalLabelA"><?=$lang['alerts_82']?></span>
                            <span id="myModalLabelB"><?=$lang['alerts_83']?></span>
                            <span id="modalPreviewNumber"></span
                            ></h4>
                    </div>
                    <div class="modal-body">
                        <div id="modal_message_preview"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-defaultrc" data-dismiss="modal"><?=$lang['calendar_popup_01']?></button>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // LOGGING PAGE
        if (isset($_GET['log']))
        {
            ?>
            <div class="modal fade" id="delete-recurrence-modal" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true">
                <div class="modal-dialog" role="document" style="width: 800px">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title"><?=$lang['alerts_84']?></h4>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
								<?=$lang['alerts_85']?>
                            </div>
                            <div><?=$lang['alerts_86']?> <b id="delete-recurrence-modal-body-alert"></b></div>
                            <div>
                                <?=$lang['dataqueries_93']?> <b id="delete-recurrence-modal-body-record"></b>&nbsp;
                                <?php if ($Proj->longitudinal) { ?><b>(<span id="delete-recurrence-modal-body-event"></span>)</b><?php } ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button data-toggle="modal" class="btn btn-rcred" id="delete-recurrence-modal-body-submit" onclick="return false;"><?=$lang['alerts_87']?></button>
                            <button class="btn btn-defaultrc" data-dismiss="modal" onclick="return false;"><?=$lang['global_53']?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            print $this->renderNotificationLog();
            include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
            exit;
        }

        // Get all datetime/datetime_seconds fields and put in array
		$datetime_fields_pre = Form::getFieldDropdownOptions(true, false, false, false, array('date', 'date_ymd', 'date_mdy', 'date_dmy', 'datetime',
		                            'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds_ymd', 'datetime_seconds_dmy', 'datetime_seconds_mdy'), false, false);
        $datetime_fields = array();
        $datetime_fields[$lang['alerts_244']][''] = $lang['alerts_236'];
        foreach ($datetime_fields_pre as $this_field=>$this_label) {
            $this_form_label = strip_tags($lang['alerts_243']." \"".$Proj->forms[$Proj->metadata[$this_field]['form_name']]['menu']."\"");
            $this_form = $Proj->metadata[$this_field]['form_name'];
            $this_label = preg_replace('/'.$this_field.'/', "[$this_field]", $this_label, 1);
            list ($this_label2, $this_label1) = explode(" ", $this_label, 2);
            if ($longitudinal) {
                foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
                    if (in_array($this_form, $these_forms)) {
                        if (!isset($datetime_fields[$this_form_label]["[$this_field]"])) {
                            $datetime_fields[$this_form_label]["[$this_field]"] = "$this_label1 " . $lang['alerts_237'] . " - $this_label2";
                        }
                        $this_event_name = $Proj->getUniqueEventNames($this_event_id);
                        $datetime_fields[$this_form_label]["[$this_event_name][$this_field]"] = "$this_label1 (".$Proj->eventInfo[$this_event_id]['name_ext'].") - $this_label2";
                    }
                }
            } else {
                $datetime_fields[$this_form_label]["[$this_field]"] = "$this_label1 $this_label2";
            }
        }

        // Create array of multi-page survey instruments
        $multipageSurveys = array();
        foreach ($Proj->surveys as $attr) {
            if (!$attr['question_by_section']) continue;
            $multipageSurveys[] = $attr['form_name'];
        }

		// Add days of the week + work day + weekend day as drop-down list options
		$daysOfWeekDD = SurveyScheduler::daysofWeekOptions();
		unset($daysOfWeekDD['']);

        // Set the "stop type" drop-down options
        if (!$Proj->longitudinal && !$hasRepeatingFormsOrEvents) {
            // Classic, no repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen']);
        } elseif (!$Proj->longitudinal && $hasRepeatingFormsOrEvents) {
            // Classic, repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_EVENT_INSTRUMENT_INSTANCE'=>$lang['alerts_216']." ".$lang['alerts_217']);
        } elseif ($Proj->longitudinal && !$hasRepeatingFormsOrEvents) {
            // Longitudinal, no repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_INSTRUMENT'=>$lang['alerts_222'],
                               'RECORD_EVENT'=>$lang['alerts_218']);
        } elseif ($Proj->longitudinal && $hasRepeatingFormsOrEvents) {
            // Longitudinal, repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_INSTRUMENT'=>$lang['alerts_222'],
                               // 'RECORD_EVENT_INSTRUMENT'=>$lang['alerts_224'],
                               'RECORD_EVENT'=>$lang['alerts_218'],
                               'RECORD_EVENT_INSTRUMENT_INSTANCE'=>$lang['alerts_218']." ".$lang['alerts_223']);
        }
        ?>

        <div id='errMsgContainer' class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
        <div class="alert alert-success" style="max-width:800px;border-color: #b2dba1 !important;display: none;" id="succMsgContainer"></div>

        <script type="text/javascript">
        var multipageSurveys = new Array(<?=prep_implode($multipageSurveys)?>);
        $(function(){
            $('#addNewAlert').click(function() {
                editEmailAlert(<?=json_encode($alertDefaults)?>,"","");
                $('[field="cron-queue"]').hide();
            });
        });
        </script>

        <!-- ALERTS TABLE -->
        <div style="width:950px;max-width:950px;">
            <div class="mb-1 clearfix">
                <button id='addNewAlert' type="button" class="btn btn-sm btn-success float-left"><i class="fas fa-plus"></i> <?=$lang['alerts_88']?></button>
                <div class="float-right mt-2 mr-1">
                    <input value="" id="deleted_alerts" class="auto-submit" type="checkbox" name="deleted_alerts">
                    <label for="deleted_alerts"><?=$lang['alerts_89']?></label>
                </div>
            </div>
            <div>
                <?php if ($indexSubSet > 0) { ?>
                <table class="table table-bordered table-hover email_preview_forms_table" id="customizedAlertsPreview" style="display:none;width:100%;table-layout: fixed;">
                    <thead>
                        <tr class="table_header d-none">
                            <th><?=$lang['alerts_90']?></th>
                            <th style="width:350px;"><span class="fas fa-envelope"></span> <?=$lang['messaging_110']?></th>
                            <th style="display:none;">Active</th>
                            <th style="display:none;">Deleted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Loop through all alerts
                    $alert_number = 0;
                    $alerts = "";
                    foreach ($projectData as $alert_id=>$attr)
                    {
                        $index = $alert_number;
                        $email_sent = $attr['email_sent'];
                        $message_sent = "";
                        $alertNumDeleteClass = '';
                        $active_col = "Y";

                        $show_queue = "";
                        if($attr['email_repetitive'] == '1' || $attr['email_deleted'] == '1'){
                            $show_queue = "display:none;";
                        }

                        //DELETE
                        $deleted_text = $lang['alerts_91'];
                        if ($attr['email_deleted'] == '1') {
                            $alertNumDeleteClass = 'alert-deleted';
                            $deactivated_deleted_text = "<i class=\"fas fa-times\"></i> ".$lang['alerts_92'];
                            $message_sent .= "<div class='bg-danger text-white mt-2 p-2'>".$deactivated_deleted_text."</div>";
                            $deleted_modal = "external-modules-configure-modal-delete-confirmation";
                            $deleted_index = "index_modal_delete";
                            $deleted_col = "Y";
                            $deleted_text = $lang['alerts_93'];
                            $show_button = "display:none;";
                            $reactivate_button = '<a class="dropdown-item" href="#" onclick="reactivateEmailAlert('.$alert_id.');return true;"><i class="fas fa-power-off"></i> '.$lang['alerts_94'].'</a>';
                        } else {
                            $deleted_modal = "external-modules-configure-modal-delete-user-confirmation";
                            $deleted_index = "index_modal_delete_user";
                            $deleted_col = "N";
                            $show_button = "";
                            $reactivate_button = "";
                        }

                        $alert_number++;

                        $activity = $activityBox = "";

                        // List alerts that have been queued
                        $queuedRecords = $this->getAlertQueuedRecords($alert_id);
                        $numQueuedRecords = count($queuedRecords);
                        if ($numQueuedRecords > 0) {
                            $schedText = $numQueuedRecords == '1' ? $lang['alerts_95'] : $lang['alerts_96'];
                            $activity .= '<div class=""><i class="far fa-clock"></i> '.$numQueuedRecords.' '.$schedText.'
                                            &nbsp;(<a href="#" class="fs12" style="text-decoration:underline;margin-left:1px;margin-right:1px;" rel="popover" data-toggle="popover" data-target-selector="#scheduled-activated'.$index.'" 
                                            data-title="'.js_escape2($numQueuedRecords.' '.$lang['alerts_97'].' #'.$alert_number).'">'.$lang['alerts_98'].'</a>)
                                          </div>';
                        }
                        elseif ($attr['email_repetitive'] == '0' && $attr['cron_send_email_on'] != 'now' && $attr['cron_send_email_on_date'] != '')
                        {
                            // Only display this if alerts will ever be scheduled (as opposed to being sent immediately)
                            $activity .= '<div class="text-secondary"><i class="far fa-clock"></i> '.$lang['alerts_99'].'</div>';
                        }

                        // Get text for stop-type
                        if ($attr['alert_stop_type'] == 'RECORD_INSTRUMENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_222'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_224'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_218'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT_INSTANCE' && $Proj->hasRepeatingFormsEvents()) {
                            $stopTypeText = ($Proj->longitudinal ? $lang['alerts_218']." ".$lang['alerts_223'] : $lang['alerts_216']." ".$lang['alerts_217']);
                        } else {
                            $stopTypeText = $lang['alerts_216'] . " - " . $lang['alerts_228'];
                        }
                        $stopTypeText = RCView::span(array('class'=>'text-secondary ml-1 fs12'), $lang['leftparen'].$stopTypeText.$lang['rightparen']);

                        // Get text for maximum recurrence
                        $maxRecurText = '';
                        if (is_numeric($attr['cron_repeat_for_max'])) {
                             $maxRecurText = " &ndash; ".$lang['survey_737']." ".$attr['cron_repeat_for_max']." ".$lang['alerts_235'];
                        }

                        // List alerts that have been sent
                        $alerts_sent = $this->getAlertsSent($alert_id);
                        $num_alerts_sent = count($alerts_sent);
                        $email_records_sent = array_unique($alerts_sent);
                        if ($num_alerts_sent > 0) {
                            $sentText = $num_alerts_sent == '1' ? $lang['alerts_194'] : $lang['alerts_195'];
                            $activity .= '<div class="">
                                            <i class="far fa-envelope-open"></i> '.$num_alerts_sent.' '.$sentText.'
                                            &nbsp;(<a href="#" class="fs12" style="text-decoration:underline;margin-left:1px;margin-right:1px;" rel="popover" data-toggle="popover" data-target-selector="#records-activated'.$index.'" 
                                            data-title="'.js_escape2($num_alerts_sent.' '.$lang['alerts_100'].' #'.$alert_number).'">'.$lang['alerts_98'].'</a>)
                                            <span class="ml-4 fs11" style="color:green;"><i class="fas fa-check"></i> '.$lang['alerts_101'].' '.
                                                DateTimeRC::format_user_datetime($attr['email_timestamp_sent'], 'Y-M-D_24').'
                                            </span>
                                          </div>';
                        } else {
                            $activity .= '<div class="text-secondary"><i class="far fa-envelope-open"></i> '.$lang['alerts_102'].'</div>';
                        }
                        $activityBox .= '<div class="clearfix">
                                            <div class="float-left boldish" style="color:#6320ac;width:90px;">
                                                <i class="fs14 fas fa-tachometer-alt"></i> '.$lang['alerts_103'].'
                                            </div>
                                            <div class="float-left">'.$activity.'</div>
                                          </div>';
                        if ($numQueuedRecords > 0) {
                            $activityBox .= '<div id="scheduled-activated'.$index.'" class="hidden">
                                                    <div><a href="'.APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:setup&log=1&pagenum=1&filterBeginTime='.rawurlencode(substr(DateTimeRC::format_ts_from_ymd(NOW, true, false), 0, 16)).'&filterEndTime=&filterRecord=&filterAlert='.$alert_id.'"><i class="fas fa-table"></i> '.$lang['alerts_105'].'</a></div>
                                                    <p>'.$lang['alerts_104'].' '.implode(", ", $queuedRecords).'</p>
                                               </div>';
                        }
                        if ($num_alerts_sent > 0) {
                            $activityBox .= '<div id="records-activated'.$index.'" class="hidden">
                                                    <div><a href="'.APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:setup&log=1&pagenum=1&filterBeginTime=&filterEndTime='.rawurlencode(substr(DateTimeRC::format_ts_from_ymd(NOW, true, false), 0, 16)).'&filterRecord=&filterAlert='.$alert_id.'"><i class="fas fa-table"></i> '.$lang['alerts_106'].'</a></div>
                                                    <p>'.$lang['alerts_104'].' '.implode(", ", $email_records_sent).'</p>
                                               </div>';
                        }

                        $fileAttachments = 0;
                        $attachmentVar ='';
                        $attachmentFile ='';
                        $scheduled_email = '';
                        $formName = $triggerText = '';
                        $msg = '';
                        $previewMsgLinks = '';
                        $info_modal = array();
                        $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();

                        foreach ($attr as $configKey => $configVal) {
                            // Convert dates/times
                            if ($configKey == 'cron_send_email_on_date' || $configKey == 'alert_expiration') {
                                $configVal = DateTimeRC::format_user_datetime($configVal, 'Y-M-D_24', DateTimeRC::get_user_format_full(), true);
                            }
							// Format phone numbers (if applicable)
							if ($configKey == 'phone_number_to') {
								$phone_number_tos = array();
								foreach (explode(";", $configVal) as $this_phone_number)
								{
									$this_phone_number = trim($this_phone_number);
									if ($this_phone_number == '') continue;
									$firstCharacter = substr($this_phone_number, 0, 1);
									if (is_numeric($firstCharacter)) {
										$this_phone_number = formatPhone($this_phone_number);
									}
									$phone_number_tos[] = $this_phone_number;
								}
								$phone_number_tos = implode("; ", $phone_number_tos);
								$configVal = $phone_number_tos;
							}
                            // Store values in array to convert to JSON to use when loading the dialog
                            $info_modal[$index][str_replace("_", "-", $configKey)] = $configVal . "";
                        }

                        // Loop through this row's attributes
                        $scheduled_email = "";
                        foreach ($attr as $configKey => $configVal)
                        {
                            if ($configKey == 'cron_send_email_on' || $configKey == 'cron_send_email_on_date' || $configKey == 'cron_repeat_for') {
                                // SCHEDULE EMAIL INFO
                                if ($attr['email_repetitive'] != '1' && $attr['email_repetitive_change'] != '1')
                                {
                                    if ($configKey == 'cron_send_email_on') {
                                        $scheduled_email .= "<div class='mt-1' style='color:green;'>";
                                        if ($configVal == "date") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_107'];
                                            $scheduled_email .= " " . DateTimeRC::format_user_datetime($attr['cron_send_email_on_date'], 'Y-M-D_24');
                                        } elseif ($configVal == "next_occurrence") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_108'];
                                            $scheduled_email .= " ".$daysOfWeekDD[$attr['cron_send_email_on_next_day_type']];
                                            $scheduled_email .= " at ".DateTimeRC::format_user_datetime($attr['cron_send_email_on_next_time'], 'Y-M-D_24');
                                        } elseif ($configVal == "time_lag") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_246'] . " ";
                                            $time_lag_components = array();
                                            if ($attr['cron_send_email_on_time_lag_days'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_days']." ".$lang['survey_426'];
                                            if ($attr['cron_send_email_on_time_lag_hours'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_hours']." ".$lang['survey_427'];
                                            if ($attr['cron_send_email_on_time_lag_minutes'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_minutes']." ".$lang['survey_428'];
                                            $scheduled_email .= " " . implode(", ", $time_lag_components);
                                            if ($attr['cron_send_email_on_field'] != '') {
                                                if (!empty($time_lag_components)) $scheduled_email .= " " .($attr['cron_send_email_on_field_after'] == 'before' ? $lang['alerts_245'] : $lang['alerts_238']);
                                                $scheduled_email .= " " .$lang['alerts_240'] . " " .$attr['cron_send_email_on_field'];
                                            } else {
                                                $scheduled_email .= " " . $lang['alerts_238'] . " " .$lang['alerts_236'];
                                            }
                                        } else {
                                            $scheduled_email .= "<i class=\"fas fa-share\"></i> ".$lang['alerts_110'];
                                        }
                                        $scheduled_email .= "</div>";
                                    }
                                    if ($attr['cron_repeat_for'] == 0 && $configKey == "cron_send_email_on_date") {
                                        $scheduled_email .= "<div class='mt-1'><b class='code box-1x'>1x</b> {$lang['alerts_111']} $stopTypeText</div>";
                                    } elseif ($attr['cron_repeat_for'] > 0 && $configKey == "cron_send_email_on_date") {
                                        if ($attr['cron_repeat_for'] == '1'){
                                            $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['alerts_112']}";
                                            if ($attr['cron_repeat_for_units'] == 'MINUTES') {
                                                $scheduled_email .= " {$lang['alerts_113']}";
                                            } elseif ($attr['cron_repeat_for_units'] == 'HOURS') {
                                                $scheduled_email .= " {$lang['alerts_114']}";
                                            } else {
                                                $scheduled_email .= " {$lang['alerts_115']}";
                                            }
                                            $scheduled_email .= " $maxRecurText $stopTypeText</div>";
                                        }else{
                                            $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['alerts_112']} " . $attr['cron_repeat_for'];
                                            if ($attr['cron_repeat_for_units'] == 'MINUTES') {
                                                $scheduled_email .= " ".$lang['survey_428'];
                                            } elseif ($attr['cron_repeat_for_units'] == 'HOURS') {
                                                $scheduled_email .= " ".$lang['survey_427'];
                                            } else {
                                                $scheduled_email .= " ".$lang['survey_426'];
                                            }
                                            $scheduled_email .= " $maxRecurText $stopTypeText</div>";
                                        }
                                    }
                                } elseif ($attr['email_repetitive'] == '1') {
                                    $scheduled_email = "<div class='mt-1' style='color:green;'><i class=\"fas fa-share\"></i> {$lang['alerts_110']}</div>";
                                    $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['alerts_116']}</div>";
                                } elseif ($attr['email_repetitive_change'] == '1') {
                                    $scheduled_email = "<div class='mt-1' style='color:green;'><i class=\"fas fa-share\"></i> {$lang['alerts_110']}</div>";
                                    $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['alerts_226']}</div>";
                                }
                                if ($configKey == "cron_repeat_for" && $attr['alert_expiration'] != '') {
                                    $scheduled_email .= "<div id='expire-descrip".$index."' class='mt-1 expire-descrip' style='color:#A00000;'>
                                                                <i class='far fa-calendar-times'></i> {$lang['alerts_117']} " .
                                        DateTimeRC::format_user_datetime($attr['alert_expiration'], 'Y-M-D_24') .
                                        "</div>";
                                }
                            }else{
                                //NORMAL EMAIL
                                if (strpos($configKey, 'email_attachment') === 0 && $configKey != 'email_attachment_variable') {
                                    if (!empty($configVal)) {
                                        $fileAttachments++;
                                        $thisAttachName = Files::getEdocName($configVal);
                                        if ($thisAttachName) {
                                            $url = APP_PATH_WEBROOT."index.php?route=AlertsController:downloadAttachment&id=".$configVal."&alert_id=".$alert_id."&pid=".PROJECT_ID;
                                            $attachmentFile .= '<div class="pl-2 fs12 text-truncate"><i class="fas fa-paperclip mr-1" style="position:relative;top:1px;"></i><a href="'.$url.'" class="fs12" target="_blank">'.$thisAttachName.'</a></div>';
                                        }
                                    }
                                } else {
                                    if ($configKey == 'form_name')
                                    {
//                                      // Alert number
                                        $deletedAlertClass = ($attr['email_deleted'] == '1') ? "alert-danger" : "alert-primary";
                                        $alertTitle = (trim($attr['alert_title']) == '') ? '' : $lang['colon'].'<span class="font-weight-normal ml-1">'.RCView::escape($attr['alert_title']).'</span>';
                                        $formName .= '<div class="clearfix" style="margin-left: -11px;">
                                                        <div style="max-width:340px;" class="card-header alert-num-box '.$alertNumDeleteClass.' float-left text-truncate"><i class="fas fa-bell fs13" style="margin-right:5px;"></i>'.$lang['alerts_24'].' #'.$alert_number.$alertTitle.'</div>
                                                        <div class="btn-group nowrap float-left mb-1 ml-2" role="group">
                                                          <button type="button" class="btn btn-link fs13 py-1 pl-1 pr-2" onclick="__rcfunc_editEmailAlert_emailRow'.$index.'();">
                                                            <i class="fas fa-pencil-alt"></i> '.$lang['global_27'].'
                                                          </button>
                                                          <div class="btn-group" role="group">
                                                            <button id="btnGroupDrop1" type="button" class="btn btn-link fs13 py-1 pl-2 pr-0 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                              <i class="fas fa-cog"></i> '.$lang['alerts_119'].'
                                                            </button>
                                                            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                                                              '.$reactivate_button.'
                                                              <a class="dropdown-item" href="#" style="'.$show_button.'" onclick="duplicateEmailAlert('.$alert_id.');return true;"><i class="fas fa-copy"></i> '.$lang['alerts_118'].'</a>
                                                              <a class="dropdown-item" href="#" onclick="deleteEmailAlert('.$alert_id.',\''.$deleted_modal.'\',\''.$deleted_index.'\');return true;"><i class="fas fa-times"></i> '.$deleted_text.'</a>
                                                            </div>
                                                          </div>
                                                        </div>
                                                      </div>';
                                        $formName .= "<script type=\"text/javascript\">function __rcfunc_editEmailAlert_emailRow{$index}(){ editEmailAlert(".json_encode($info_modal[$index]).",".$alert_id.",".$alert_number.") }</script>";

                                        // Set form label text
                                         $formLabel = "";
                                        if ($configVal != '') {
                                            $formLabel .= $lang['alerts_120']." \"<span class='boldish'>" . $Proj->forms[$configVal]['menu'];
                                            // Set event text
                                            if (\REDCap::isLongitudinal()) {
                                                $formLabel .= " " . ($attr['form_name_event'] == '' ? $lang['alerts_70'] : "(".$Proj->eventInfo[$attr['form_name_event']]['name_ext'].")");
                                            }
                                            $formLabel .= "</span>\" ".($attr['email_incomplete'] ? $lang['alerts_121'] : $lang['alerts_122']);
                                            if (trim($attr['alert_condition']) == '') $formLabel .= $lang['period'];
                                        }
                                        if (trim($attr['alert_condition']) != '') {
                                            // Conditional logic
                                            if ($configVal != '') {
                                                if ($attr['email_repetitive']) {
                                                    $formLabel = $lang['alerts_123']." " . $formLabel.$lang['colon'];
                                                } else {
                                                    $formLabel = $lang['alerts_124']." " . $formLabel.$lang['colon'];
                                                }
                                            } else {
                                                if ($attr['email_repetitive']) {
                                                    $formLabel = $lang['alerts_125']." " . $formLabel;
                                                } else {
                                                    $formLabel = $lang['alerts_126']." " . $formLabel;
                                                }
                                                $formLabel .= " ".$lang['alerts_127'];
                                            }
                                            $formLabel .= " <span class='code' style='font-size:85%;'>{$attr['alert_condition']}</span>";
                                        } else {
                                            if ($attr['email_repetitive']) {
                                                $formLabel = $lang['alerts_125']." " . $formLabel;
                                            } else {
                                                $formLabel = $lang['alerts_126']." " . $formLabel;
                                            }
                                        }

                                        // Display trigger: form and/or logic
                                        $triggerText .= '<div id="trigger-descrip'.$index.'" class="mb-1 trigger-descrip"><b class="fs14"><i class="fas fa-hand-point-right"></i></b> '.$formLabel.'</div>';


                                    } else if($configKey == 'email_attachment_variable'){
                                        $attchVar = preg_split("/[;,]+/",  $configVal);
                                        foreach ($attchVar as $var){
                                            if (!empty($var)){
                                                $fileAttachments++;
                                                $attachmentVar .= '<div class="pl-2 fs12 text-truncate"><i class="fas fa-paperclip mr-1" style="position:relative;top:1px;"></i>'.trim($var).'</div>';
                                            }
                                        }
                                    }else if($configKey == 'email_from' && $attr['alert_type'] == "EMAIL") {
                                        $fromContent = '<a class="fs12" href="mailto:'.$configVal.'">'.($attr['email_from_display'] == '' ? $configVal : RCView::escape($attr['email_from_display'])." &lt;".$configVal."&gt;").'</a>';
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
                                                    <span class="mr-1 boldish">'.$lang['global_37'].'</span> 
                                                    '.$fromContent.'
                                                 </li>';
                                    }else if($configKey == 'email_to') {
										if ($Proj->twilio_enabled_alerts && ($attr['alert_type'] == "SMS" || $attr['alert_type'] == "VOICE_CALL")) {
											$phoneTos = array();
											foreach (preg_split("/[;,]+/", $attr['phone_number_to']) as $thisPhoneTo) {
												$thisPhoneTo = trim($thisPhoneTo);
												$phoneTos[] = (substr($thisPhoneTo, 0, 1) == "[") ? $thisPhoneTo : formatPhone($thisPhoneTo);
											}
											$to_text = implode('; ', $phoneTos);
										} else {
											$emailTos = array();
											foreach (preg_split("/[;,]+/", $configVal) as $thisEmailTo) {
												$thisEmailTo = trim($thisEmailTo);
												$emailTos[] = '<a class="fs12" href="mailto:' . $thisEmailTo . '">' . $thisEmailTo . '</a>';
											}
											$to_text = implode('; ', $emailTos);
										}
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
                                                    <span class="mr-1 boldish">'.$lang['global_38'].'</span> '.$to_text.'
                                                 </li>';
                                    } else if ($configKey == 'email_subject' && $attr['alert_type'] == "EMAIL") {
                                        $msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
                                                    <span class="mr-1 boldish">'.$lang['control_center_28'].'</span> '.RCView::escape($configVal) . '
                                                 </li>';
                                    } else if ($configKey == 'alert_message') {
                                        $configVal = substr(str_replace('&nbsp;', ' ', strip_tags(br2nl($configVal))), 0, 100);
                                        $msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
                                                    <span class="mr-1 boldish"'.$lang['messaging_105'].'></span> '.RCView::escape($configVal) . '
                                                 </li>';
                                    }
                                }
                            }
                        }
                        $fileAttachmentText = "";
                        if ($fileAttachments > 0) {
                            $fileAttachmentText = "<li class='list-group-item pt-1 pb-2 px-3 fs12'>
                                                   <div class='boldish'>{$lang['alerts_128']} (".$fileAttachments."):</div>
                                                   ".$attachmentFile.$attachmentVar.'</li>';
                        }

                        // Output row
                        $alerts .= "<tr>";
                        $alerts .= "<td class='pt-0 pb-4' style='border-right:0;' data-order='".$alert_number."'>
                                        ".$formName."
                                        <div class='card mt-3'>
                                            <div class='card-body p-2'>".$triggerText.$scheduled_email.$message_sent."</div>
                                        </div>
                                        <div class='card mt-3'>
                                            <div class='card-body p-2'>$activityBox</div>
                                        </div>
                                        
                                    </td>";
                        if ($attr['alert_type'] == "EMAIL") {
							$alertTypeText = "<i class='fas fa-envelope'></i> {$lang['global_33']}";
                        } elseif ($attr['alert_type'] == "SMS") {
							$alertTypeText = "<i class='fas fa-sms fs15'></i> {$lang['alerts_201']}";
						} elseif ($attr['alert_type'] == "VOICE_CALL") {
							$alertTypeText = "<i class='fas fa-phone'></i> {$lang['alerts_202']}";
						}
                        $alerts .= "<td class='pt-3 pb-4' style='width:350px;border-left:0;'>
                                        <div class='card'>
                                            <div class='card-header bg-light py-1 px-3 clearfix' style='color:#004085;background-color:#d5e3f3 !important;'>
                                                <div class='float-left'>$alertTypeText</div>
                                                <div class=\"btn-group nowrap float-right\" role=\"group\">
                                                  <div class=\"btn-group\" role=\"group\">
                                                    <button id=\"btnGroupDrop2\" type=\"button\" class=\"btn btn-link fs12 p-0 dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">
                                                      {$lang['design_699']}
                                                    </button>
                                                    <div class=\"dropdown-menu\" aria-labelledby=\"btnGroupDrop2\">
                                                      <a class=\"dropdown-item\" href=\"#\" onclick=\"previewEmailAlert('$index','$alert_number')\"><i class=\"far fa-envelope\"></i> {$lang['alerts_82']}</a>
                                                      <a class=\"dropdown-item\" href=\"#\" onclick=\"previewEmailAlertRecord('$index','$alert_number')\"><i class=\"far fa-envelope\"></i> {$lang['alerts_129']}</a>
                                                    </div>
                                                  </div>
                                                </div>
                                            </div>
                                            <div class='card-body p-0'>
                                                <ul class='list-group list-group-flush'>
                                                     ".$msg.$fileAttachmentText."       
                                                </ul>                                                                  
                                            </div>
                                        </div>
                                        ".$previewMsgLinks."
                                    </td>";
                        $alerts .= "<td style='display:none;'>".$active_col."</td>";
                        $alerts .= "<td style='display:none;'>".$deleted_col."</td>";
                        $alerts .= "</tr>";
                    }
                    echo $alerts;
                    }
                    ?>
                    <tbody>
                </table>
            </div>

            <div class="col-md-12">
                <form class="form-horizontal" action="" method="post" id="saveAlert">
                    <div class="modal fade" id="external-modules-configure-modal" name="external-modules-configure-modal" data-module="" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true">
                        <div class="modal-dialog" role="document" style="max-width: 950px !important;">
                            <div class="modal-content">
                                <div class="modal-header py-2">
                                    <button type="button" class="py-2 close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                    <h4 id="add-edit-title-text" class="modal-title form-control-custom"></h4>
                                </div>
                                <div class="modal-body pt-2">
                                    <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                                    <div class="mb-2">
                                        <?=$lang['alerts_130']?>
                                    </div>
                                    <table class="code_modal_table" id="code_modal_table_update">

                                        <!-- Triggers -->
                                        <tr class="form-control-custom">
                                            <td colspan="2" class="align-text-top pt-1">
                                                <label class="fs14 boldish"><?=$lang['alerts_131']?></label>
                                                <input type="text" name="alert-title" placeholder="add optional title"class="d-inline ml-3" style="font-size:15px;width:500px;" maxlength="100">
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom">
                                            <td colspan="2">
                                                <div class="form-control-custom-title clearfix">
                                                    <div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-hand-point-right"></i> <?=$lang['alerts_132']?></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="">
                                            <td class="align-text-top pt-1 pr-3">
                                                <label class="text-nowrap boldish"><?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_219'])." ".$lang['alerts_133']?></label>
                                            </td>
                                            <td class="external-modules-input-td">
                                                <div class="ml-2">
                                                    <input type="radio" id="alert-trigger1" name="alert-trigger" value="submit" style="height:20px;" class="external-modules-input-element align-middle">
                                                    <label for="alert-trigger1" class="m-0 align-middle"><?=$lang['alerts_134']?><span class='em-ast'>*</span></label>
                                                </div>
                                                <div class="ml-2">
                                                    <input type="radio" id="alert-trigger2" name="alert-trigger" value="submit-logic" style="height:20px;" class="external-modules-input-element align-middle">
                                                    <label for="alert-trigger2" class="m-0 align-middle"><?=$lang['alerts_135']?><span class='em-ast'>*</span></label>
                                                </div>
                                                <div class="ml-2">
                                                    <input type="radio" id="alert-trigger3" name="alert-trigger" value="logic" style="height:20px;" class="external-modules-input-element align-middle">
                                                    <label for="alert-trigger3" class="m-0 align-middle"><?=$lang['alerts_136']?></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="">
                                            <td colspan="2" class="external-modules-input-td pb-1 boldish">
												<?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_220'])." ".$lang['alerts_137']?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="form-name">
                                            <td colspan="2" class="external-modules-input-td pb-1 pl-3">
                                                <div class="nowrap">
                                                    <span class="mr-1 boldish"><?=$lang['alerts_140']?> </span>
                                                    <?=RCView::select(array('name'=>"form-name",'class'=>'external-modules-input-element d-inline p-1', 'style'=>'width:300px;max-width:300px;',
                                                        'onchange'=>""), $formAnyEventDropdownOptions+$formEventDropdownOptions, "", 200)?>
                                                    <?=RCView::select(array('name'=>"email-incomplete",'class'=>'external-modules-input-element d-inline p-1 ml-1', 'style'=>'width:250px;max-width:250px;'),
                                                        array('1'=>$lang['alerts_138'],'0'=>$lang['alerts_139']), "0")?>
                                                    <span class="ml-2 fs12" style="color:gray;"><?=$lang['alerts_141']?></span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="condition-andor">
                                            <td colspan="2" class="external-modules-input-td boldish pt-1 pb-1 pl-3">
												<?=$lang['alerts_142']?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="alert-condition">
                                            <td colspan="2" class="external-modules-input-td pb-0 pl-3">
                                                <div class="mb-1 boldish condition-andor-text2"><?=$lang['alerts_143']?></div>
                                                <textarea type="text" id="alert-condition" name="alert-condition" class="external-modules-input-element ml-4" style="max-width:95%;" onkeydown="logicSuggestSearchTip(this, event);" onblur="validate_logic($(this).val());"></textarea>
                                                <div class="clearfix">
                                                    <div class='my-1 ml-4 fs11 float-left text-secondary'><?php echo ($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")") ?></div>
                                                    <div class="float-right mr-3" style="margin-top:1px;">
                                                        <a href="javascript:;" style="text-decoration: underline;" class="fs11" onclick="simpleDialog('<?=js_escape($lang['alerts_34']."<br><br>".$lang['alerts_35'])?>','<?=js_escape($lang['alerts_33'])?>',null,650);"><i class="far fa-stop-circle mr-1"></i><?=$lang['alerts_33']?></a>
                                                    </div>
                                                </div>
                                                <div id='alert-condition_Ok' class='logicValidatorOkay ml-4'></div>
                                                <script type='text/javascript'>logicValidate($('#alert-condition'), false, 1);</script>
                                                <?php
                                                print logicAdd("alert-condition");
                                                print RCView::div(array('class'=>'mt-2 ml-4'),
                                                        RCView::checkbox(array('id'=>"ensure-logic-still-true", 'name'=>"ensure-logic-still-true", 'style'=>'width:15px;height:15px;position:relative;top:3px;')) .
                                                        '<label class="boldish" for="ensure-logic-still-true">'.$lang['alerts_30'] . '</label>' .
                                                        RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['survey_189'], 'style'=>'','onclick'=>"simpleDialog('".js_escape($lang['alerts_31'])."','".js_escape($lang['alerts_30'])."');"), '?')
                                                    );
                                                ?>
                                            </td>
                                        </tr>

                                        <tr class="form-control-custom" field="alert-stop-type">
                                            <?php if (count($stopTypes) > 1) { ?>
                                                <td colspan="2" class="external-modules-input-td pt-4">
                                                    <label class="text-nowrap boldish">
                                                        <?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_221'])." ".$lang['alerts_215']?>
                                                        <?=RCView::select(array('name'=>"alert-stop-type",'class'=>'external-modules-input-element d-inline p-1 ml-1', 'style'=>'width:600px;max-width:600px;',
                                                            'onchange'=>""), $stopTypes, "RECORD", 200)?>
                                                    </label>
                                                    <div class='mb-1 ml-3 fs11 text-secondary'><?=$lang['alerts_229']?></div>
                                                </td>
                                            <?php } else { ?>
                                                <td colspan="2" class="pt-1"></td>
                                            <?php } ?>
                                        </tr>

                                        <!-- Schedule settings -->
                                        <tr class="form-control-custom">
                                            <td colspan="2">
                                                <div class="form-control-custom-title boldish fs14"><i class="far fa-clock"></i> <?=$lang['alerts_145']?></div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="">
                                            <td class="align-text-top pr-2 pl-3" style="padding-top:0.3rem;">
                                                <label class="text-nowrap boldish"><?=$lang['alerts_146']?></label>
                                            </td>
                                            <td class="external-modules-input-td pb-2">
                                                <div class="ml-2">
                                                    <input type="radio" id="cron-send-email-on1" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="now">
                                                    <label for="cron-send-email-on1" class="m-0 align-middle"><?=$lang['alerts_110']?></label>
                                                </div>
                                                <div class="ml-2 mt-3">
                                                    <input type="radio" id="cron-send-email-on2" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="next_occurrence">
                                                    <label for="cron-send-email-on2" class="m-0 align-middle">
                                                        <?php
                                                        print   $lang['survey_423'] . RCView::SP . RCView::SP .
                                                                RCView::select(array('name'=>"cron-send-email-on-next-day-type", 'class'=>'external-modules-input-element d-inline py-0 px-1 mr-1 fs12', 'style'=>'height:24px;width: 110px;max-width: 110px;'), $daysOfWeekDD, "") .
                                                                $lang['survey_424'] . " " .
                                                                RCView::input(array('name'=>"cron-send-email-on-next-time",'type'=>'text', 'class'=>'ml-1 py-0 px-1 fs12 external-modules-input-element d-inline time2',
                                                                    'style'=>'text-align:center;width:48px;height:26px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
                                                                    'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}")) .
                                                                RCView::span(array('class'=>'df'), 'H:M');
                                                        ?>
                                                    </label>
                                                </div>
                                                <div class="ml-2" style="margin-top:17px;margin-bottom:18px;">
                                                    <input type="radio" id="cron-send-email-on3" name="cron-send-email-on" style="height:20px;margin-top:4px;" class="external-modules-input-element align-top" value="time_lag">
                                                    <label for="cron-send-email-on3" class="m-0 align-middle">
														<?=$lang['alerts_239']?>
														<?php
                                                        if (count($datetime_fields) == 1) print " " . $lang['survey_1293'];
                                                        ?>
                                                        <input type="text" name="cron-send-email-on-time-lag-days" maxlength="4" class="ml-1 fs12 external-modules-input-element d-inline text-right"
                                                               style="height:24px;width:44px;" onblur="redcap_validate(this,'0','9999','hard','integer',1)">
														<?=$lang['survey_426']?>
                                                        <input type="text" name="cron-send-email-on-time-lag-hours" maxlength="3" class="ml-1 fs12 external-modules-input-element d-inline text-right"
                                                               style="height:24px;width:35px;" onblur="redcap_validate(this,'0','999','hard','integer',1)">
														<?=$lang['survey_427']?>
                                                        <input type="text" name="cron-send-email-on-time-lag-minutes" maxlength="3" class="ml-1 fs12 external-modules-input-element d-inline text-right"
                                                               style="height:24px;width:35px;" onblur="redcap_validate(this,'0','999','hard','integer',1)">
														<?=$lang['survey_428']?>
                                                        <?php
                                                        if (count($datetime_fields) > 1) {
                                                            print  '<div class="mt-2 nowrap">'.
                                                                        RCView::select(array('name'=>"cron-send-email-on-field-after", 'class'=>'external-modules-input-element d-inline py-0 px-1 ml-1',
                                                                            'style'=>'height:24px;max-width:80px;width:80px;'), array('before'=>$lang['alerts_245'], 'after'=>$lang['alerts_238']), 'after', 200).
                                                                        RCView::select(array('name'=>"cron-send-email-on-field", 'class'=>'external-modules-input-element d-inline py-0 px-1 ml-1',
                                                                            'style'=>'height:24px;max-width:500px;width:500px;'), $datetime_fields, '', 200).
                                                                        '<a href="javascript:;" class="help2" data-toggle="popover" data-trigger="hover" data-title="'.js_escape2($lang['global_03']).'" data-content="'.js_escape2($lang['alerts_241']).'">?</a>
                                                                    </div>';
                                                        }
                                                        ?>
                                                    </label>
                                                </div>
                                                <div class="ml-2 mt-1">
                                                    <input type="radio" id="cron-send-email-on4" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="date">
                                                    <label for="cron-send-email-on4" class="m-0 align-middle">
														<?=$lang['survey_429']?>
                                                        <input type="text" name="cron-send-email-on-date" class="ml-1 fs12 alert-datetimepicker external-modules-input-element d-inline"
                                                               placeholder="<?=str_replace(array('M','D','Y'),array('MM','DD','YYYY'),DateTimeRC::get_user_format_label())." HH:MM"?>"
                                                               style="height:26px;width:140px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)">
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom">
                                            <td class="align-text-top pr-2 pl-3" style="padding-top:1.2rem;">
                                                <label class="text-nowrap boldish"><?=$lang['alerts_148']?></label>
                                            </td>
                                            <td class="external-modules-input-td pb-3 pt-3">
                                                <div class="ml-2">
                                                    <input type="radio" id="alert-send-how-many1" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="once">
                                                    <label for="alert-send-how-many1" class="m-0 align-middle"><?=$justOnceText?></label>
                                                </div>
                                                <div class="ml-2 mt-2">
                                                    <span class="align-top">
                                                        <input type="radio" id="alert-send-how-many2" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="every">
                                                    </span>
                                                    <label for="alert-send-how-many2" class="m-0 align-middle">
														<?=$lang['alerts_225']?>
														<?=RCView::select(array('id'=>"every-time-type", 'class'=>'external-modules-input-element d-inline py-0 px-1 ml-1', 'style'=>'height:24px;max-width:360px;width:360px;position:relative;top:2px;'),
                                                            array('every'=>$lang['alerts_227'], 'every-change'=>$lang['alerts_230']." ".$lang['alerts_231'], 'every-change-calcs'=>$lang['alerts_230']), "every")?>
                                                        <div class="fs12" style="color:#999;"><?=$lang['alerts_141']?></div>
                                                    </label>
                                                    <input type="hidden" name="email-repetitive" value="0">
                                                    <input type="hidden" name="email-repetitive-change" value="0">
                                                    <input type="hidden" name="email-repetitive-change-calcs" value="0">
                                                    <input type="hidden" name="email-deleted" value="0">
                                                </div>
                                                <div id="email-repetitive-multipage-warning" class="fs11" style="color:#C00000;margin-left:32px;">
													<?=$lang['alerts_150']?>
                                                </div>
                                                <div class="ml-2 mt-2">
                                                    <span class="align-top">
                                                        <input type="radio" id="alert-send-how-many3" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="schedule">
                                                    </span>
                                                    <label for="alert-send-how-many3" class="m-0 align-middle">
														<div><?=$lang['alerts_232']?></div>
														<div style="margin-top:0.15rem;">
                                                            <i class="fas fa-redo" style="margin-right:1px;"></i> <?=$lang['survey_735']?>
                                                            <input type="text" name="cron-repeat-for" onblur="if (redcap_validate(this,'0','9999','soft_typed','integer',1) && !isNumeric($(this).val())) $(this).val('0');" class="pl-1 pr-2 py-0 ml-1 text-right external-modules-input-element d-inline" maxlength="4" style="height:24px;width:42px;position:relative;top:2px;">
                                                            <?=RCView::select(array('name'=>"cron-repeat-for-units",'class'=>'external-modules-input-element d-inline py-0 px-1 mr-1', 'style'=>'height:24px;max-width:90px;width:90px;position:relative;top:2px;'),
                                                                array('MINUTES'=>$lang['survey_428'], 'HOURS'=>$lang['survey_427'], 'DAYS'=>$lang['survey_426']), "DAYS")?>
                                                            <?=$lang['alerts_152'].$lang['period']?>
														</div>
														<div style="margin-top:0.2rem;">
                                                            <i class="far fa-calendar-times" style="margin-right:3px;"></i> <?=$lang['survey_737']?>
                                                            <input type="text" name="cron-repeat-for-max" onblur="if (isNumeric($(this).val())) {$(this).val($(this).val()*1);} if (redcap_validate(this,'2','9999','soft_typed','integer',1) && $(this).val() < 2) {$(this).val('');}" class="pl-1 pr-2 py-0 mx-1 text-right external-modules-input-element d-inline" maxlength="4" style="height:24px;width:42px;position:relative;top:2px;">
                                                            <?=$lang['alerts_233']?> <i class="ml-1 text-secondary"><?=$lang['alerts_234']?></i>
														</div>
                                                    </label>
                                                </div>
                                                <input type="checkbox" name="cron-queue" class="d-none">
                                            </td>
                                        </tr>

                                        <!-- Expiration -->
                                        <tr class="form-control-custom">
                                            <td class="pl-3 pt-3 align-text-top">
                                                <label class="mb-1 boldish"><?=$lang['alerts_170']?></label>
                                                <div class="text-secondary"><?=$lang['global_06']?></div>
                                            </td>
                                            <td class="pt-3 external-modules-input-td">
                                                <input type="text" name="alert-expiration" class="ml-1 fs12 alert-datetimepicker external-modules-input-element d-inline"
                                                       placeholder="<?=str_replace(array('M','D','Y'),array('MM','DD','YYYY'),DateTimeRC::get_user_format_label())." HH:MM"?>"
                                                       style="height:26px;width:140px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)">
                                                <div class="ml-2 mt-1 fs12" style="color:gray;">
													<?=$lang['alerts_171']?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Message -->
                                        <tr class="form-control-custom">
                                            <td colspan="2">
                                                <div class="form-control-custom-title boldish fs14">
                                                    <i class="fas fa-envelope"></i> <?=$lang['alerts_153']?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($twilio_enabled_global && (UserRights::isSuperUserNotImpersonator() || $twilio_display_info_project_setup || !$twilio_enabled_by_super_users_only || $Proj->twilio_enabled_alerts)) { ?>
                                            <tr class="form-control-custom" field="alert-type">
                                                <td class="pl-3 pt-1 align-top">
                                                    <label class="mb-1 boldish"><?=$lang['alerts_199']?></label>
                                                </td>
                                                <td class="external-modules-input-td pb-3">
                                                    <div class="clearfix">
                                                        <div class="mr-4 d-inline">
                                                            <input type="radio" id="alert-type-email" name="alert-type" value="EMAIL" style="height:20px;" class="external-modules-input-element align-middle" onclick="checkMessageSettings();">
                                                            <label for="alert-type-email" class="m-0 align-middle"><i class='fas fa-envelope'></i> <?=$lang['global_33']?></label>
                                                        </div>
                                                        <div class="mr-4 d-inline <?=($Proj->twilio_enabled_alerts ? "" : "opacity50")?>">
                                                            <input type="radio" id="alert-type-sms" name="alert-type" value="SMS" style="height:20px;" class="external-modules-input-element align-middle" <?=($Proj->twilio_enabled_alerts ? "" : "disabled")?> onclick="checkMessageSettings();">
                                                            <label for="alert-type-sms" class="m-0 align-middle"><i class='fas fa-sms fs15'></i> <?=$lang['alerts_201']?></label>
                                                        </div>
                                                        <div class="d-inline <?=($Proj->twilio_enabled_alerts ? "" : "opacity50")?>">
                                                            <input type="radio" id="alert-type-voicecall" name="alert-type" value="VOICE_CALL" style="height:20px;" class="external-modules-input-element align-middle" <?=($Proj->twilio_enabled_alerts ? "" : "disabled")?> onclick="checkMessageSettings();">
                                                            <label for="alert-type-voicecall" class="m-0 align-middle"><i class='fas fa-phone'></i> <?=$lang['alerts_202']?></label>
                                                        </div>
                                                    </div>
                                                    <?php if (!$Proj->twilio_enabled_alerts) {
                                                        ?><div class="fs11 mt-2" style="color:#C00000;padding-left:100px;"><?=$lang['alerts_213']?></div><?php
                                                    } ?>
                                                </td>
                                            </tr>
                                            <tr class="form-control-custom" field="phone-number-to">
                                                <td class="pl-3 pt-2 align-top">
                                                    <label class="mb-1 boldish"><?=$lang['alerts_203']?></label>
                                                </td>
                                                <td class="external-modules-input-td pb-3">
                                                    <?php
                                                    print RCView::select(array('name'=>"phone-number-to", 'id'=>"phone-number-to", 'multiple'=>'',
                                                                'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toPhones, "", 200);
                                                    if ($alerts_allow_phone_freeform || SUPER_USER) {
                                                        ?><div class="my-2">
                                                            <div class="text-secondary fs11 pt-1">
                                                                <?=$lang['alerts_204']?>
                                                            </div>
                                                            <input type="text" name="phone-number-to-freeform" class="fs12 external-modules-input-element d-inline" style="width:100%;" placeholder="(615) 867-5309; +52 55 1234 5678; 2707545555">
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
										<?php } ?>
                                        <tr class="requiredm form-control-custom" field="email-from">
                                            <td class="pl-3">
                                                <label class="mb-1 boldish"><?=$lang['alerts_154']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
                                            </td>
                                            <td class="external-modules-input-td clearfix nowrap">
                                                <div class="float-left mr-2 mt-1" style="width:150px;">
                                                    <input type="text" name="email-from-display" class="fs12 external-modules-input-element d-inline" style="width:100%;" placeholder="<?=js_escape2($lang['survey_1270'])?>">
                                                </div>
                                                <div class="float-left mr-2 mt-1" style="width:65%;max-width:380px;">
                                                <?=RCView::select(array('name'=>"email-from",'class'=>'external-modules-input-element'), $fromEmails, $user_email, 200)?>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="email-to">
                                            <td class="align-text-top pt-2 pl-3">
                                                <label class="mb-1 boldish"><?=$lang['alerts_155']?></label>
                                                <div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
                                                <a id="showCC" href="javascript:;" class="d-block fs12 mt-2 ml-4 font-weight-light" style="text-decoration:underline;"><i class="fas fa-plus mr-1"></i><?=$lang['alerts_156']?></a>
                                            </td>
                                            <td class="external-modules-input-td pt-2">
                                                <?php
                                                print RCView::select(array('name'=>"email-to", 'id'=>"email-to", 'multiple'=>'',
                                                            'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
                                                if ($alerts_allow_email_freeform || SUPER_USER) {
                                                    ?><div class="fs12 text-secondary my-2">
                                                        <div class="float-left mr-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
                                                        <div style="overflow:hidden;">
                                                            <input type="text" name="email-to-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org">
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="email-cc">
                                            <td class="fs12 align-text-top pt-4 pr-5 text-right font-weight-light"><label class="boldish"><?=$lang['alerts_158']?></label></td>
                                            <td class="pt-3 pb-1 external-modules-input-td">
                                                <?php
                                                print RCView::select(array('name'=>"email-cc", 'id'=>"email-cc", 'multiple'=>'',
                                                        'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
                                                if ($alerts_allow_email_freeform || SUPER_USER) {
                                                    ?><div class="fs12 text-secondary my-2">
                                                        <div class="float-left mr-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
                                                        <div style="overflow:hidden;">
                                                            <input type="text" name="email-cc-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org">
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="email-bcc">
                                            <td class="fs12 align-text-top pt-4 pr-5 text-right font-weight-light"><label class="boldish"><?=$lang['alerts_159']?></label></td>
                                            <td class="pt-3 pb-1 external-modules-input-td">
                                                <?php
                                                print RCView::select(array('name'=>"email-bcc", 'id'=>"email-bcc", 'multiple'=>'',
                                                        'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
                                                if ($alerts_allow_email_freeform || SUPER_USER) {
                                                    ?><div class="fs12 text-secondary my-2">
                                                    <div class="float-left mr-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
                                                        <div style="overflow:hidden;">
                                                            <input type="text" name="email-bcc-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org">
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom" field="email-failed">
                                            <td class="fs12 pt-4 pb-4 pr-5 text-right font-weight-light"><label class="boldish wrap mb-0" style="max-width:140px;width:140px;"><?=$lang['alerts_160']?></label></td>
                                            <td class="pt-3 pb-3 external-modules-input-td">
                                                <?=RCView::select(array('name'=>"email-failed",'id'=>"email-failed",'class'=>'fs12 external-modules-input-element',
                                                    'style'=>"height:28px;"), array(''=>'')+$fromEmails, "", 200)?>
                                            </td>
                                        </tr>
                                        <tr class="requiredm form-control-custom" field="email-subject">
                                            <td class="pl-3">
                                                <label class="mb-1 boldish"><?=$lang['email_users_10']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
                                            </td>
                                            <td class="external-modules-input-td">
                                                <input type="text" name="email-subject" class="external-modules-input-element" value="">
                                            </td>
                                        </tr>
                                        <tr class="requiredm form-control-custom" field="alert-message">
                                            <td class="align-text-top pt-2 pl-3">
                                                <label class="mb-1 boldish"><?=$lang['messaging_105']?></label>
                                                <div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
                                                <div class="mt-4 mr-3 p-2" style="overflow:hidden;color:#C00000;background-color:#f7f7f7;border:1px solid #ddd;">
                                                    <div class="float-left" style="width:25px;"><input type="checkbox" id="prevent-piping-identifiers" name="prevent-piping-identifiers" style="height: 15px;position: relative;top: 4px;"></div>
                                                    <div style="overflow:hidden;">
                                                        <label class="boldish fs12 m-0" for="prevent-piping-identifiers">
                                                            <?=$lang['alerts_12']?>
                                                            <a href="javascript:;" class="help ml-1" onclick="simpleDialog('<?=js_escape($lang['alerts_13'])?>','<?=js_escape($lang['alerts_12'])?>');">?</a>
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="external-modules-input-td">
                                                <textarea class="external-modules-rich-text-field" name="alert-message" id="alert-message" onkeydown=""></textarea>
                                                <div id='errMsgContainerIE10' class="mt-1 text-danger fs11" role="alert" style="display:none;">
													<?=$lang['alerts_161']?>
                                                </div>
                                                <!-- Piping link -->
                                                <div style='padding:8px 0px 2px;color:#555;font-size:11px;'>
													<?=$lang['alerts_162']?>
                                                    <button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-left:3px;margin-right:2px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='<?=APP_PATH_IMAGES?>pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'><?=$lang['info_41']?></button>
                                                    <?=$lang['global_43']?>
                                                    <button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-left:3px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick="smartVariableExplainPopup();return false;">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] <?=$lang['global_146']?></button>
                                                    <div style='margin-top:5px;color:#aaa;font-size:10px;font-family:Verdana, "Open Sans", Helvetica, Arial, Helvetica;'>
														<?=$lang['alerts_163']?>
                                                    </div>
                                                </div>
                                                <?=logicAdd("alert-message_ifr")?>
                                            </td>
                                        </tr>
                                        <tr field="email-attachment-btn" class="form-control-custom">
                                            <td>
                                                <button id="showAttachments" class="btn btn-success btn-xs fs12 py-1 px-2 ml-2 mb-1" style="">
                                                    <i class="fas fa-paperclip mr-1"></i><?=$lang['alerts_164']?>
                                                </button>
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr field="email-attachment-hdr" class="form-control-custom"><td colspan="2"><div class="form-control-custom-title boldish"><i class="fas fa-paperclip"></i> <?=$lang['alerts_165']?></div></td></tr>
                                        <?php if (!empty($fieldUploadFieldOptions)) { ?>
                                        <tr field="email-attachment-variable" class="form-control-custom">
                                            <td class="align-text-top pt-2">
                                                <label><?=$lang['alerts_166']?></label>
                                                <div class="fs11" style="color:#888;">
													<?=$lang['alerts_167']?>
                                                </div>
                                            </td>
                                            <td class="external-modules-input-td pb-0">
                                                <?php
                                                print RCView::select(array('name'=>"email-attachment-variable", 'id'=>"email-attachment-variable", 'multiple'=>'',
                                                            'class'=>'external-modules-input-element fs12', 'style'=>(count($fieldUploadFieldOptions) > 2 ? 'height:80px;' : 'height:45px;')), $fieldUploadFieldOptions, "", 200);
                                                ?>
                                            </td>
                                        </tr>
                                        <tr class="form-control-custom email-attachment-andor">
                                            <td class="pl-3 fs12" colspan="2">
                                                <?=$lang['alerts_168']?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                        <tr field="email-attachment1" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #1:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment1" value="" class="external-modules-input-element"></td></tr>
                                        <tr field="email-attachment2" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #2:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment2" value="" class="external-modules-input-element"></td></tr>
                                        <tr field="email-attachment3" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #3:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment3" value="" class="external-modules-input-element"></td></tr>
                                        <tr field="email-attachment4" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #4:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment4" value="" class="external-modules-input-element"></td></tr>
                                        <tr field="email-attachment5" class="form-control-custom"><td class=" align-text-top"><label><?=$lang['alerts_169']?> #5:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment5" value="" class="external-modules-input-element"></td></tr>

                                    </table>
                                    <input type="hidden" value="" id="index_modal_update" name="index_modal_update">
                                </div>

                                <div class="simpleDialog" id="prevent-piping-dialog" title="<?=js_escape2($lang['alerts_14'])?>">
                                    <?=$lang['alerts_15']?> <b><?=$lang['alerts_16']?></b>
                                </div>

                                <div class="modal-footer">
                                    <button data-toggle="modal" class="btn btn-rcgreen" id="btnModalsaveAlert" onclick="return false;"><?=$lang['designate_forms_13']?></button>
                                    <button class="btn btn-defaultrc" id="btnCloseCodesModal" data-dismiss="modal" onclick="return false;"><?=$lang['global_53']?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="external-modules-configure-modal-schedule-confirmation" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">

                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                    <h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_172']?></h4>
                                </div>
                                <div class="modal-body">
									<?=$lang['alerts_173']?>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" form="saveAlert" class="btn btn-success" id="btnModalRescheduleForm"><?=$lang['alerts_174']?></button>
                                    <button type="submit" form="saveAlert" class="btn btn-warning" id="btnModalRescheduleForm2"><?=$lang['alerts_175']?></button>
                                    <button class="btn btn-defaultrc btn-cancel" id="btnCloseCodesModalDelete" data-dismiss="modal"><?=$lang['global_53']?></button>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <div class="modal fade" id="external-modules-configure-modal-delete-user-confirmation" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
                <form class="form-horizontal" action="" method="post" id='deleteUserForm'>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_176']?></h4>
                            </div>
                            <div class="modal-body">
                                <span><?=$lang['alerts_177']?></span>
                                <input type="hidden" value="" id="index_modal_delete_user" name="index_modal_delete_user">
                                <input type="hidden" value="<?=APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:deleteAlert'?>" id="url_modal_delete_user" name="url_modal_delete_user">
                            </div>

                            <div class="modal-footer">
                                <button type="submit" form="deleteUserForm" class="btn btn-danger"><?=$lang['alerts_178']?></button>
                                <button class="btn btn-defaultrc btn-cancel" data-dismiss="modal"><?=$lang['global_53']?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal fade" id="external-modules-configure-modal-delete-confirmation" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
                <form class="form-horizontal" action="" method="post" id='deleteForm'>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_179']?></h4>
                            </div>
                            <div class="modal-body">
                                <span><?=$lang['alerts_180']?></span>
                                <br/>
                                <span style="color:red;font-weight: bold"><?=$lang['alerts_181']?></span>
                                <input type="hidden" value="" id="index_modal_delete" name="index_modal_delete">
                            </div>

                            <div class="modal-footer">
                                <button type="submit" form="deleteForm" class="btn btn-default btn-delete"><?=$lang['global_19']?></button>
                                <button class="btn btn-defaultrc btn-cancel" data-dismiss="modal"><?=$lang['global_53']?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>


            <div class="modal fade" id="external-modules-configure-modal-record" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
                <form class="form-horizontal" action="" method="post" id='selectPreviewRecord'>
                    <div class="modal-dialog" role="document" style="width: 800px">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_183']?> <span id="modalRecordNumber"></span></h4>
                            </div>
                            <div class="modal-body form-control-custom">
                                <div style="padding-bottom: 10px;"><?=$lang['alerts_182']?></div>
                                <div id="load_preview_record"></div>
                                <div>
                                    <input type="hidden" value="" id="index_modal_record_preview" name="index_modal_record_preview">
                                    <div id="modal_message_record_preview"></div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-defaultrc" data-dismiss="modal"><?=$lang['calendar_popup_01']?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        <?php
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    // Create a new alert or update an existing alert
    public function saveAlert()
    {
        global $lang;
        $Proj = new Project(PROJECT_ID);

        // Are we creating a new alert or updating an existing one?
        $newAlert = !(isset($_POST['index_modal_update']) && is_numeric($_POST['index_modal_update']));

        // Get default values from table
        $alert = getTableColumns('redcap_alerts');
        unset($alert['alert_id']);

        // Rework POST keys/values
        if ($newAlert) {
            $_POST['project_id'] = PROJECT_ID;
        } else {
            unset($alert['project_id']);
            $alert_id = (int)$_POST['index_modal_update'];
            $updateQueue = isset($_POST['cron-queue']);
            unset($_POST['index_modal_update'], $_POST['cron-queue']);
        }

        // Gather existing values for this alert
        $alertBefore = array();
        if (!$newAlert) {
            $sql = "select * from redcap_alerts where project_id = " . PROJECT_ID . " and alert_id = $alert_id";
            $q = db_query($sql);
            $alertBefore = db_fetch_assoc($q);
        }

        // Make sure we have all values from POST
        if (isset($_POST['alert-message-editor'])) {
            $_POST['alert-message'] = $_POST['alert-message-editor'];
        }
        $_POST['alert-message'] = trim($_POST['alert-message']);
		$_POST['phone-number-to'] = str_replace(array(",", "(", ")", " ", "+"), array(";","","","",""), $_POST['phone-number-to']);
		$_POST['phone-number-to-freeform'] = str_replace(array(",", "(", ")", "-", " ", "+"), array(";","","","","",""), $_POST['phone-number-to-freeform']);
		$_POST['email-to'] = str_replace(array(","," "), array(";",""), $_POST['email-to']);
        $_POST['email-cc'] = str_replace(array(","," "), array(";",""), $_POST['email-cc']);
        $_POST['email-bcc'] = str_replace(array(","," "), array(";",""), $_POST['email-bcc']);
        $_POST['email-to-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-to-freeform']);
        $_POST['email-cc-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-cc-freeform']);
        $_POST['email-bcc-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-bcc-freeform']);
        $_POST['email-incomplete'] = (isset($_POST['email-incomplete']) && $_POST['email-incomplete'] == '0') ? '0' : '1';
        $_POST['email-repetitive'] = (isset($_POST['email-repetitive']) && $_POST['email-repetitive'] == '1') ? '1' : '0';
        $_POST['email-repetitive-change'] = (isset($_POST['email-repetitive-change']) && $_POST['email-repetitive-change'] == '1') ? '1' : '0';
        $_POST['email-repetitive-change-calcs'] = (isset($_POST['email-repetitive-change-calcs']) && $_POST['email-repetitive-change-calcs'] == '1') ? '1' : '0';
        if ($_POST['email-repetitive-change'] == '1') $_POST['email-repetitive'] = '0';
        if ($_POST['alert-send-how-many'] == 'once') {
            $_POST['cron-repeat-for'] = '0';
        }
        if ($_POST['email-repetitive'] || $_POST['email-repetitive-change']) {
            $_POST['cron-repeat-for'] = '0';
            $_POST['cron-repeat-for-max'] = '';
            $_POST['cron-send-email-on'] = 'now';
            $_POST['cron-send-email-on-date'] = '';
            $_POST['alert-stop-type'] = 'RECORD_EVENT_INSTRUMENT_INSTANCE';
        }
        if (!isset($_POST['alert-stop-type'])) {
            $_POST['alert-stop-type'] = 'RECORD';
        }
        if ($_POST['cron-repeat-for'] == '0') {
            $updateQueue = false;
        }
        if ($_POST['cron-repeat-for'] == 0 || ($_POST['cron-repeat-for-units'] != 'MINUTES' && $_POST['cron-repeat-for-units'] != 'HOURS')) {
            $_POST['cron-repeat-for-units'] = 'DAYS';
        }
        if (!is_numeric($_POST['cron-repeat-for-max']) || $_POST['cron-repeat-for-max'] < 2 || $_POST['cron-repeat-for'] == '0') {
            $_POST['cron-repeat-for-max'] = '';
        }
        if ($_POST['cron-send-email-on'] == 'next_occurrence') {
            if ($_POST['cron-send-email-on-next-time'] == '') {
                $_POST['cron-send-email-on'] = 'now';
            } else {
                $_POST['cron-send-email-on-next-time'] .= ":00";
                $_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
                    $_POST['cron-send-email-on-date'] = $_POST['cron-send-email-on-field'] = '';
            }
        }
        if ($_POST['cron-send-email-on'] == 'time_lag') {
            if (!is_numeric($_POST['cron-send-email-on-time-lag-days'])) $_POST['cron-send-email-on-time-lag-days'] = '0';
            if (!is_numeric($_POST['cron-send-email-on-time-lag-hours'])) $_POST['cron-send-email-on-time-lag-hours'] = '0';
            if (!is_numeric($_POST['cron-send-email-on-time-lag-minutes'])) $_POST['cron-send-email-on-time-lag-minutes'] = '0';
            if ($_POST['cron-send-email-on-field'] == '' && $_POST['cron-send-email-on-time-lag-days'] + $_POST['cron-send-email-on-time-lag-hours'] + $_POST['cron-send-email-on-time-lag-minutes'] <= 0) {
                $_POST['cron-send-email-on'] = 'now';
            } else {
                $_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
                    $_POST['cron-send-email-on-date'] = '';
            }
        }
        if ($_POST['cron-send-email-on'] == 'date') {
            if ($_POST['cron-send-email-on-date'] == '') {
                $_POST['cron-send-email-on'] = 'now';
            } else {
                $_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
                    $_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] = $_POST['cron-send-email-on-field'] = '';
            }
        }
        if ($_POST['cron-send-email-on'] == 'now') {
            $_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
                $_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
                $_POST['cron-send-email-on-date'] = $_POST['cron-send-email-on-field'] = '';
        }
        if ($_POST['cron-send-email-on'] == 'field') {
            $_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
                $_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
                $_POST['cron-send-email-on-date'] = '';
        }
        if ($_POST['cron-send-email-on-field'] == '') {
            $_POST['cron-send-email-on-field-after'] = 'after';
        }
        $_POST['cron-send-email-on-date'] = DateTimeRC::format_ts_to_ymd($_POST['cron-send-email-on-date']);
        $_POST['alert-expiration'] = DateTimeRC::format_ts_to_ymd($_POST['alert-expiration']);
        list ($_POST['form-name'], $_POST['form-name-event']) = explode("-", $_POST['form-name'], 2);
        if ($_POST['form-name'] != '' && !isset($Proj->forms[$_POST['form-name']])) $_POST['form-name'] = '';
        if ($_POST['alert-trigger'] == 'logic') $_POST['form-name'] = $_POST['form-name-event'] = '';
        if ($_POST['form-name-event'] != '' && !isset($Proj->eventInfo[$_POST['form-name-event']])) $_POST['form-name-event'] = '';
        $_POST['alert-condition'] = ($_POST['alert-trigger'] == 'submit') ? "" : trim($_POST['alert-condition']);
        $_POST['ensure-logic-still-true'] = (isset($_POST['ensure-logic-still-true']) && $_POST['alert-condition'] != '') ? '1' : '0';
        $_POST['prevent-piping-identifiers'] = (isset($_POST['prevent-piping-identifiers'])) ? '1' : '0';
        if ($_POST['cron-send-email-on-next-day-type'] == '') $_POST['cron-send-email-on-next-day-type'] = 'DAY';
        $_POST['email-attachment-variable'] = str_replace(array(","," "), array(";",""), $_POST['email-attachment-variable']);

        // If restricting users from using email-validated fields, then remove if any that don't previously exist (admins are exempt)
        $restrictedFields = array('email-to', 'email-cc', 'email-bcc');
        if (!$GLOBALS['alerts_allow_email_variables'] && !SUPER_USER)
        {
            foreach ($restrictedFields as $this_field) {
                if (!isset($_POST[$this_field])) continue;
                $pieces = explode(";", $_POST[$this_field]);
                foreach ($pieces as $pkey=>$piece) {
                    // Only remove email field variables
                    if ($piece != self::participant_email_var && !isEmail($piece)) {
                        if ($newAlert) {
                            // Remove this since this is a new alert being created
                            unset($pieces[$pkey]);
                        } else {
                            // Check if already existed for this existing alert
                            $this_field_underscore = str_replace("-", "_", $this_field);
                            $piecesBefore = explode(";", $alertBefore[$this_field_underscore]);
                            if (!in_array($piece, $piecesBefore)) {
                                unset($pieces[$pkey]);
                            }
                        }
                    }
                }
                $_POST[$this_field] = implode(";", $pieces);
            }
        }

        // If restricting users from using freeform emails, then remove if any that don't previously exist (admins are exempt)
        $restrictedFieldsFreeform = array('email-to-freeform', 'email-cc-freeform', 'email-bcc-freeform');
        if (!$GLOBALS['alerts_allow_email_freeform'] && !SUPER_USER) {
            foreach ($restrictedFieldsFreeform as $this_field) {
                if (!isset($_POST[$this_field])) continue;
                $pieces = explode(";", $_POST[$this_field]);
                foreach ($pieces as $pkey=>$piece) {
                    if ($newAlert || !isEmail($piece)) {
                        unset($pieces[$pkey]);
                    } elseif (!$newAlert) {
                        // Check if already existed for this existing alert
                        $this_field_underscore = str_replace("-", "_", $this_field);
                        $piecesBefore = explode(";", $alertBefore[$this_field_underscore]);
                        if (!in_array($piece, $piecesBefore)) {
                            unset($pieces[$pkey]);
                        }
                    }
                }
                $_POST[$this_field] = implode(";", $pieces);
            }
        }
        $_POST['email-to'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-to']), explode(";", $_POST['email-to-freeform']) ))),";");
        $_POST['email-cc'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-cc']), explode(";", $_POST['email-cc-freeform']) ))),";");
        $_POST['email-bcc'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-bcc']), explode(";", $_POST['email-bcc-freeform']) ))),";");
        $_POST['phone-number-to'] = trim(trim(implode(";", array_merge( explode(";", $_POST['phone-number-to']), explode(";", $_POST['phone-number-to-freeform']) ))),";");

		if (isset($_POST['alert-type']) && $_POST['alert-type'] != "EMAIL") {
			$_POST['email-from-display'] = $_POST['email-from'] = $_POST['email-to'] = $_POST['email-cc'] = $_POST['email-bcc'] = $_POST['email-subject'] = "";
        }

        // Add values from POST
        foreach ($_POST as $key=>$val) {
            $key = str_replace('-', '_', $key);
            if (!array_key_exists($key, $alert)) continue;
            $alert[$key] = $val;
        }

        // Add logging info
        $alertsLogging = array();
        $loggingIgnore = array('email_timestamp_sent', 'email_sent', 'email_deleted', 'project_id');
        foreach ($alert as $key => $val) {
            if (in_array($key, $loggingIgnore)) continue;
            // Rework some to be more user friendly
            if ($key == 'form_name_event') {
                if (!$Proj->longitudinal) continue;
                if ($val == '') {
                    $val = 'any';
                } else {
                    $val = $Proj->getUniqueEventNames($val);
                }
            }
            elseif ($key == 'email_incomplete') {
                if ($alert[$key]['form_name'] == '') continue;
                $key = 'trigger_on_instrument_save_status';
                $val = ($val == '1') ? 'complete_status_only' : 'any_status';
            }
            elseif ($key == 'alert_message') {
                $val = strip_tags($val);
            }
            elseif ($key == 'ensure_logic_still_true' || $key == 'prevent_piping_identifiers') {
                $val = ($val == '1') ? 'yes' : 'no';
            }
            elseif ($key == 'email_repetitive') {
                if ($alert[$key]['form_name'] == '' || $alert[$key]['cron_send_email_on'] != 'now' || $alert[$key]['cron_repeat_for'] != '0') {
                    continue;
                }
                $key = 'trigger_on_every_instrument_save';
                $val = ($val == '1') ? 'yes' : 'no';
            }
            elseif (in_array($key, array('email_to', 'email_cc', 'email_bcc'))) {
                $val = str_replace(';', '; ', $val);
            }
            // Add to logging array
            $alertsLogging[] = "$key = '$val'";
        }

        // ADD NEW ALERT
        if ($newAlert)
        {
            // Add to table
            $alertStatus = $alertMsg = '';
            $sql = "insert into redcap_alerts (".implode(', ', array_keys($alert)).") 
                    values (".prep_implode($alert, true, true).")";
            if (db_query($sql)) {
                $alertStatus = 'success';
                // Logging
                $alert_id = db_insert_id();
                unset($this->alerts_settings[$pid]); // Reset this so that the new one will be auto-added
                $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                    "Alert #" . $alert_number . ",\n" . implode(",\n", $alertsLogging), "Create alert", "", "", $pid);
            } else {
                $alertMsg = 'Error: Alert could not be created! '.db_error();
            }
        }
        // UPDATE ALERT
        else
        {
            // Gather values for SQL update
            $updates = array();
            foreach ($alert as $key => $val) {
                if ($key == 'email_timestamp_sent' || $key == 'email_sent') continue; // Do not overwrite these, which are for bookkeeping
                $updates[] = "$key = " . checkNull($val);
            }

            // Add to table
            $alertStatus = $alertMsg = '';
            $sql = "update redcap_alerts set " . implode(', ', $updates) . "
            where project_id = " . PROJECT_ID . " and alert_id = $alert_id";
            if (db_query($sql)) {
                $alertStatus = 'success';
                // Logging
                $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                    "Alert #" . $alert_number . ",\n" . implode(",\n", $alertsLogging), "Modify alert", "", "", $pid);
            } else {
                $alertMsg = 'Error: Alert could not be updated! ' . db_error();
            }

            // Already scheduled emails need to be updated
            if ($updateQueue && $alert['cron_send_email_on'] != $alertBefore['cron_send_email_on']) {
                if ($alert['cron_send_email_on'] == 'now' || ($alert['email_repetitive'] == '0' && ($alert['cron_send_email_on'] != 'now' && $alert['cron_send_email_on_date'] != ''))) {
                    // List records that have been queued
                    $queuedRecords = $this->getAlertQueuedRecords($alert_id);
                    if (!empty($queuedRecords)) {
                        // Update all queued records in the table with new send_option
                        $sql = "update redcap_alerts_recurrence set send_option = '" . db_escape($alert['cron_send_email_on']) . "' where alert_id = $alert_id";
                        $q = db_query($sql);
                        // Log the change
                        $changes_made = "Records with modified recurrences: " . prep_implode($queuedRecords);
                        Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                            "Alert #" . $alert_number . ",\n" . $changes_made, "Modify alert recurrences", "", "", $pid);
                    }
                }
            }
        }

        // Return message and status
        echo json_encode(array(
            'status' => $alertStatus,
            'message' => $alertMsg
        ));
    }

    // Download an alert's attachment file
    public function downloadAttachment()
    {
        global $lang;
        // If ID is not in query_string, then return error
        if (!is_numeric($_GET['id']) || !is_numeric($_GET['alert_id'])) exit("{$lang['global_01']}!");

        // Verify file
        $sql = "select m.* from redcap_edocs_metadata m, redcap_alerts a
		where m.project_id = ".PROJECT_ID." and m.doc_id = ".checkNull($_GET['id'])." and m.delete_date is null
		and a.alert_id = ".checkNull($_GET['alert_id'])." and (a.email_attachment1 = m.doc_id or a.email_attachment2 = m.doc_id or 
		a.email_attachment3 = m.doc_id or a.email_attachment4 = m.doc_id or a.email_attachment5 = m.doc_id)";
        $q = db_query($sql);
        if (!db_num_rows($q)) exit("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");

        // Get file content
        list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($_GET['id']);

        // Output file
        header('Content-type: application/octet-stream');
        header('Content-disposition: attachment; filename="'.$docName.'"');
        print $fileContent;
    }

    // Upload an alert's attachment file
    public function saveAttachment()
    {
        global $lang;
        $index = isset($_GET['index']) ? (int)$_GET['index'] : null;
        $edoc = null;
        $myfiles = $edoc_ids = array();
        foreach ($_FILES as $key=>$value)
        {
            $myfiles[] = $key;
            if ($value) {
                // Check if file is larger than max file upload limit
                if (($_FILES[$key]['size']/1024/1024) > self::MAX_ATTACHMENT_SIZE_MB || ($_FILES[$key]['size']/1024/1024) > maxUploadSizeAttachment() || $_FILES[$key]['error'] != UPLOAD_ERR_OK) {
                    // Delete temp file
                    unlink($_FILES[$key]['tmp_name']);
                    // Give error response
                    header('Content-type: application/json');
                    echo json_encode(array(
                        'status' => $lang['alerts_184']." ".round_up($_FILES[$key]['size']/1024/1024)." MB {$lang['alerts_185']} ".maxUploadSizeAttachment()." MB {$lang['alerts_186']} (10 MB){$lang['period']}"
                    ));
                    exit;
                }
                # use REDCap's uploadFile
                $edoc = \Files::uploadFile($_FILES[$key]);
                if ($edoc) {
                    $edoc_ids[] = $edoc;
                } else {
                    // Delete temp file
                    unlink($_FILES[$key]['tmp_name']);
                    // Give error response
                    header('Content-type: application/json');
                    echo json_encode(array(
                        'status' => $lang['alerts_187']
                    ));
                    exit;
                }
            }
        }

        header('Content-type: application/json');
        if ($edoc) {
            echo json_encode(array(
                'status' => 'success',
                'doc_ids' => implode(',', $edoc_ids)
            ));
        } else {
            echo json_encode(array(
                'myfiles' => json_encode($myfiles),
                '_POST' => json_encode($_POST),
                'status' => $lang['alerts_188']
            ));
        }
    }

    // Delete an alert's attachment file
    public function deleteAttachment()
    {
        $alertCols = getTableColumns('redcap_alerts');

        $edoc = (int)$_POST['edoc'];
        $key = str_replace("-", "_", $_POST['key']);
        $alert_id = (int)$_POST['index'];

        $statusMsg = 'fail';
        if ($alert_id && is_numeric($alert_id) && $edoc && is_numeric($edoc) && array_key_exists($key, $alertCols))
        {
            // Delete the file
            Files::deleteFileByDocId($edoc);
            // Set to null for the alert
            $sql = "update redcap_alerts set $key = null where alert_id = $alert_id and project_id = ".PROJECT_ID;
            if (db_query($sql)) $statusMsg = 'success';
            $type = "Delete $edoc";
        }

        header('Content-type: application/json');
        echo json_encode(array(
            'type' => $type,
            'status' => $statusMsg
        ));
    }

    // Copy an alert
    public function copyAlert()
    {
        $alert_id =  (int)$_REQUEST['index_duplicate'];
        $attachment_cols = array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5');

        // Copy an alert
        $sql = "select * from redcap_alerts where project_id = ".PROJECT_ID." and alert_id = $alert_id";
        $q = db_query($sql);
        $sql_all = array();
        while ($row = db_fetch_assoc($q)) {
            // Remove some columns that don't need to be copied
            unset($row['alert_id'], $row['email_timestamp_sent'], $row['email_sent']);
            // Add to table
            $sql_all[] = $sql = "insert into redcap_alerts (" . implode(", ", array_keys($row)) . ") 
                                 values (" . prep_implode($row, true, true) . ")";
            db_query($sql);
            $this_alert_id = db_insert_id();
            // Copy file(s)
            foreach ($attachment_cols as $col) {
                if (!empty($row[$col])) {
                    $edoc_id = copyFile($row[$col], PROJECT_ID);
                    if (!empty($edoc_id)) {
                        $sql_all[] = $sql = "update redcap_alerts set $col = $edoc_id where alert_id = $this_alert_id";
                        db_query($sql);
                    }
                }
            }
        }
        // Logging
        unset($this->alerts_settings[PROJECT_ID]); // Reset this so that the new one will be auto-added
        $action_description = "Copy alert";
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
        $new_alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $this_alert_id) + 1);
        Logging::logEvent(implode(";\n", $sql_all), "redcap_alerts", "MANAGE", $alert_number,"Alert #" . $new_alert_number . " copied from Alert #" . $alert_number, $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => ""
        ));
    }

    // Delete an alert
    public function deleteAlert()
    {
        $alert_id = (int)$_POST['index_modal_delete_user'];
        $delete = isset($_POST['enable']) ? '0' : '1';
        $msg = ($delete ? "D" : "R");

        $sql = "update redcap_alerts set email_deleted = $delete where project_id = ".PROJECT_ID." and alert_id = $alert_id";
        $q = db_query($sql);
        // Logging
        $action_description = ($delete ? "Deactivate alert" : "Reactivate alert");
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
        Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,"Alert #" . $alert_number, $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => $msg
        ));
    }

    // Delete an alert (permanently)
    public function deleteAlertPermanent()
    {
        $alert_id = (int)$_REQUEST['index_modal_delete'];
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);

        // First set any edocs as "deleted"
        $sql = "update redcap_alerts a, redcap_edocs_metadata e 
                set e.delete_date = '".NOW."'
                where a.alert_id = $alert_id and a.project_id = ".PROJECT_ID." and a.project_id = e.project_id
                and (a.email_attachment1 = e.doc_id or a.email_attachment2 = e.doc_id or 
                a.email_attachment3 = e.doc_id or a.email_attachment4 = e.doc_id or a.email_attachment5 = e.doc_id)";
        db_query($sql);

        // Delete frm alerts table
        $sql2 = "delete from redcap_alerts where alert_id = $alert_id and project_id = ".PROJECT_ID;
        db_query($sql2);

        $action_description = "Permanently delete alert";
        Logging::logEvent($sql."; ".$sql2, "redcap_alerts", "MANAGE", $alert_number,"Alert #" . $alert_number, $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => ''
        ));
    }

    // Determine if we need to display repeating instrument textbox option when manually queueing an alert for a record
    public function displayRepeatingFormTextboxQueue()
    {
        global $lang;
        $event_id = (int)$_REQUEST['event'];
        $alert_id = (int)$_REQUEST['index_modal_queue'];
        $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
        $form_name = $this->getAlertSetting('form-name')[$index];

        $show_instance = "";
        $Proj = new \Project(PROJECT_ID);
        if ($Proj->isRepeatingForm($event_id, $form_name)) {
            $show_instance = '<div style="float:left;width: 280px;"><label style="font-weight: normal;padding-left: 15px;padding-right: 15px">
                                '.$lang['alerts_189'].'<br><span style="color:red">'.$lang['alerts_190'].'</span></label></div>
                                <div style="float:left;"><textarea class="form-control" id="queue_instances" rows="6"></textarea></div>';
        }

        echo json_encode(array(
            'status' => 'success',
            'instance' => $show_instance
        ));
    }

    // Delete a queued record for a given alert
    public function deleteQueuedRecord()
    {
        $aq_id =  (int)$_REQUEST['aq_id'];
        $alert_id =  (int)$_REQUEST['alert_id'];
        // Get record name
        $sql = "select record, event_id from redcap_alerts_recurrence where aq_id = ".checkNull($aq_id)." and alert_id = ".checkNull($alert_id);
        $q = db_query($sql);
        $record = db_result($q, 0, 'record');
        $event_id = db_result($q, 0, 'event_id');
        $Proj = new Project(PROJECT_ID);
        $event_name = '';
        if ($Proj->longitudinal) {
            $event_name = ",\nEvent: '".$Proj->eventInfo[$event_id]['name_ext']."'";
        }
        // Delete the recurrence
        $this->deleteQueuedEmail($aq_id, PROJECT_ID);
        // Logging
        $action_description = "Delete alert recurrence";
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
        Logging::logEvent("", "redcap_alerts", "MANAGE", $alert_number,"Alert #".$alert_number.",\nRecord: '$record'".$event_name, $action_description);

        echo json_encode(array(
            'status' => 'success'
        ));
    }

    // Display table of an alert's message contents
    public function previewAlertMessage()
    {
        global $lang;
        $index = (int)$_REQUEST['index_modal_preview'];

		$alert_type = $this->getAlertSetting('alert-type')[$index];
		$phone_number_to = $this->getAlertSetting('phone-number-to')[$index];
        $email_from = $this->getAlertSetting('email-from')[$index];
        $email_to = $this->getAlertSetting('email-to')[$index];
        $email_cc = $this->getAlertSetting('email-cc')[$index];
        $email_bcc = $this->getAlertSetting('email-bcc')[$index];
        $email_subject = $this->getAlertSetting('email-subject')[$index];
        $alert_message = $this->getAlertSetting('alert-message')[$index];

        $preview = "<table style='margin:0 auto;width:100%'>";
        if ($alert_type == "EMAIL") {
			$preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$email_from\">$email_from</a></td></tr>";
            $preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">$email_to</a></td></tr>";
			if ($email_cc != '') {
            $preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">$email_cc</a></td></tr>";
			}
			if ($email_bcc != '') {
            $preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">$email_bcc</a></td></tr>";
			}
        $preview .= "<tr><td>{$lang['email_users_10']}</td><td>".strip_tags($email_subject)."</td></tr>";
		} else {
			$phone_number_tos = array();
			foreach (explode(";", $phone_number_to) as $this_phone_number)
			{
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
				$firstCharacter = substr($this_phone_number, 0, 1);
				if (is_numeric($firstCharacter)) {
					$this_phone_number = formatPhone($this_phone_number);
                }
				$phone_number_tos[] = $this_phone_number;
			}
			$phone_number_tos = implode("; ", $phone_number_tos);
			$preview .= "<tr><td>{$lang['global_38']}</td><td>$phone_number_tos</td></tr>";
			$alert_message = nl2br(TwilioRC::cleanSmsText($alert_message));
        }
        $preview .= "<tr><td>{$lang['messaging_105']}</td><td class='underline-all-links'>".filter_tags($alert_message)."</td></tr></table>";

        echo $preview;
    }

    // Display dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecordDialog()
    {
        global $lang, $user_rights;
        $index =  (int)$_REQUEST['index_modal_alert'];
        $Proj = new \Project(PROJECT_ID);

        $form_name = $this->getAlertSetting('form-name')[$index];
        $event_id = $this->getAlertSetting('form-name-event')[$index];
        $repeatable = (($event_id != "" && $Proj->isRepeatingForm($event_id, $form_name)) || ($event_id == "" && $Proj->isRepeatingFormAnyEvent($form_name)));

        $event_selector = "";
        $numRecords = Records::getRecordCount(PROJECT_ID);
        if ($Proj->longitudinal || $repeatable || $numRecords > 1000) {
            $event_selector = "<div style='padding-bottom: 60px;'><input type='text' name='preview_record_id' id='preview_record_id' placeholder='".js_escape($lang['alerts_205'])."' style='width: 80%;float: left;' onkeydown='if(event.keyCode==13) return false;'>
                        <a href='#' class='btn btn-default save' onclick=\"loadPreviewEmailAlertRecord('','','')\" id='preview_record_id_btn' style='float: left;margin-left: 20px;padding-top: 8px;padding-bottom: 7px;'>{$lang['design_699']}</a></div>";
        } else {
            $record_list = Records::getRecordList(PROJECT_ID, $user_rights['group_id'], true);
            if (!empty($record_list)) {
                // Get any Custom Record Labels or Secondary Unique Field labels
                $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($record_list);
                // Build drop-down list
                $event_selector = '<div style="padding-bottom:10px">'.
                    '<select class="external-modules-input-element" name="preview_record_id" onchange="loadPreviewEmailAlertRecord(\'\',\'\',\'\')"><option value="">'.$lang['alerts_193'].'</option>';
                if (empty($extra_record_labels)) {
                    foreach ($record_list as $this_record) {
                        $event_selector .= "<option value='$this_record'>$this_record</option>";
                        unset($record_list[$this_record]);
                    }
                } else {
                    foreach ($record_list as $this_record) {
                        $event_selector .= "<option value='$this_record'>$this_record {$extra_record_labels[$this_record]}</option>";
                        unset($record_list[$this_record]);
                    }
                }
                $event_selector .= '</select></div>';
            }
        }
        echo $event_selector;
    }

    // Display table inside dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecord($alert_sent_log_id=null, $aq_id=null)
    {
        global $lang;
        if (is_numeric($alert_sent_log_id)) {
            $sql = "select a.alert_id, s.record, s.event_id, s.instrument, s.instance, l.*
                    from redcap_alerts a, redcap_alerts_sent s, redcap_alerts_sent_log l 
                    where a.project_id = ".PROJECT_ID." and a.alert_id = s.alert_id and s.alert_sent_id = l.alert_sent_id
                    and l.alert_sent_log_id = ".checkNull($alert_sent_log_id);
            $q = db_query($sql);
            $row = db_fetch_assoc($q);
            $alert_id = $row['alert_id'];
            $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
            $record = $row['record'];
            $form_name_event = $row['event_id'];
            $form_name = $row['instrument'];
            $instance = $row['instance'];
            $email_from = $row['email_from'];
            $email_subject = $row['subject'];
            $alert_message = $row['message'];
			$alert_type = $row['alert_type'];
			$phone_number_to = $row['phone_number_to'];
        } elseif (is_numeric($aq_id)) {
            $sql = "select a.alert_id, a.alert_type, a.phone_number_to, r.record, r.event_id, r.instrument, r.instance
                    from redcap_alerts a, redcap_alerts_recurrence r
                    where a.project_id = " . PROJECT_ID . " and a.alert_id = r.alert_id and r.aq_id = ".checkNull($aq_id);
            $q = db_query($sql);
            $row = db_fetch_assoc($q);
            $alert_id = $row['alert_id'];
            $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
            $record = $row['record'];
            $form_name_event = $row['event_id'];
            $form_name = $row['instrument'];
            $instance = $row['instance'];
            $email_from = $row['email_from'];
            $email_subject = $this->getAlertSetting('email-subject')[$index];
            $alert_message = $this->getAlertSetting('alert-message')[$index];
			$alert_type = $row['alert_type'];
			$phone_number_to = $row['phone_number_to'];
        } else {
            $index = (int)$_POST['index_modal_record_preview'];
            $alert_id = $this->getAlertIdFromKeyId(PROJECT_ID, $index);
            $record = $_REQUEST['preview_record_id'];
            $form_name_event = $this->getAlertSetting('form-name-event')[$index];
            $form_name = $this->getAlertSetting('form-name')[$index];
            $email_from = $this->getAlertSetting('email-from')[$index];
            $email_subject = $this->getAlertSetting('email-subject')[$index];
            $alert_message = $this->getAlertSetting('alert-message')[$index];
			$alert_type = $this->getAlertSetting('alert-type')[$index];
			$phone_number_to = $this->getAlertSetting('phone-number-to')[$index];
            $instance = 1;
        }
        $prevent_piping_identifiers = $this->getAlertSetting("prevent-piping-identifiers")[$index];

		$phone_number_tos = "";
		if ($alert_type == "SMS" || $alert_type == "VOICE_CALL") {
			$phone_number_tos = array();
			foreach (explode(";", $phone_number_to) as $this_phone_number) {
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
				$firstCharacter = substr($this_phone_number, 0, 1);
				if (is_numeric($firstCharacter)) {
					$this_phone_number = formatPhone($this_phone_number);
				}
				$phone_number_tos[] = $this_phone_number;
			}
			$phone_number_tos = implode("; ", $phone_number_tos);
		}

        // Get record data for piping
        $Proj = new Project(PROJECT_ID);
        $project_id = $Proj->project_id;
        $data = \REDCap::getData($project_id, 'array', $record);
        if (empty($data)) exit("<b>Record \"$record\" does not exist.</b>");

        $alert_message = Piping::replaceVariablesInLabel($alert_message, $record, $form_name_event, $instance, $data,false,
                            $project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers);
        $email_subject = Piping::replaceVariablesInLabel($email_subject, $record, $form_name_event, $instance, $data,false,
                            $project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers);
        if (!isEmail($email_from)) {
            $email_from = Piping::replaceVariablesInLabel($email_from, $record, $form_name_event, $instance, $data,false,
                            $project_id, false, $form_name, 1, false, false, $form_name);
        }

        // Email Addresses
        if (is_numeric($alert_sent_log_id)) {
            $email_to = $row['email_to'];
            $email_cc = $row['email_cc'];
            $email_bcc = $row['email_bcc'];
        } else {
			$mail = new Message();
            $mail = $this->setEmailAddresses($mail, $project_id, $record, $form_name_event, $form_name, $instance, $index, $data);
            $email_to = $mail->getTo();
			$email_cc = $mail->getCc();
			$email_bcc = $mail->getBcc();
        }

        // Display table
        $preview = "<table style='margin:0 auto;width:100%'>";
		if ($alert_type == "EMAIL") {
            $preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$email_from\">$email_from</a></td></tr>";
            $preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">".str_replace(";", "; ", $email_to)."</a></td></tr>";
			if ($email_cc != '') {
            $preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">".str_replace(";", "; ", $email_cc)."</a></td></tr>";
			}
			if ($email_bcc != '') {
            $preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">".str_replace(";", "; ", $email_bcc)."</a></td></tr>";
			}
        $preview .= "<tr><td>{$lang['email_users_10']}</td><td>".strip_tags($email_subject)."</td></tr>";
		} else {
			$preview .= "<tr><td>{$lang['global_38']}</td><td>$phone_number_tos</td></tr>";
			// Clean string
			$alert_message = nl2br(TwilioRC::cleanSmsText($alert_message));
        }
        $preview .= "<tr><td>{$lang['messaging_105']}</td><td class='underline-all-links'>".filter_tags($alert_message)."</td></tr></table>";

        echo $preview;
    }

    // Manually add a queued record for a given alert
    public function addQueuedRecord()
    {
        $index = (int)$_REQUEST['index_modal_queue'];
        $times_sent =  $_REQUEST['times_sent'];
        $last_sent =  $_REQUEST['last_sent'];
        $queue_ids = $_POST['queue_ids'];
        $event_id = $_POST['queue_event_select'];
        $queue_instances = $_POST['queue_instances'];

        if($queue_instances == "") {
            $instance = "1";
        }
        if (strpos($queue_instances, ";") !== false) {
            $instance = explode(";", $queue_instances);
        } else if (strpos($queue_instances, ",") !== false) {
            $instance = explode(",", $queue_instances);
        } else if (strpos($queue_instances, "\n") !== false) {
            $instance = explode("\n", $queue_instances);
        }else if ($queue_instances != ""){
            $instance = $queue_instances;
        }


        $failed_records = array();

        if (strpos($queue_ids, ";") !== false) {
            $record = explode(";", $queue_ids);
        } else if (strpos($queue_ids, ",") !== false) {
            $record = explode(",", $queue_ids);
        } else if (strpos($queue_ids, "\n") !== false) {
            $record = explode("\n", $queue_ids);
        } else if ($queue_ids != "") {
            if(is_array($instance)){
                foreach ($instance as $one_instance){
                    $failed = $this->addQueueEmailFromInterface(PROJECT_ID, $index, $queue_ids, $times_sent, $event_id, $last_sent, $one_instance);
                    if($failed != ""){
                        array_push($failed_records,$failed);
                    }
                }
            }else{
                $failed = $this->addQueueEmailFromInterface(PROJECT_ID, $index, $queue_ids, $times_sent, $event_id, $last_sent, $instance);
                if($failed != ""){
                    array_push($failed_records,$failed);
                }
            }
        } else {
            //ERROR
            $message = "Incorrect format. Couldn't generate PDF.";
        }

        if ($record != "") {
            foreach ($record as $id) {

                if(is_array($instance)){
                    foreach ($instance as $one_instance){
                        $failed = $this->addQueueEmailFromInterface(PROJECT_ID, $index, $id, $times_sent, $event_id, $last_sent, $one_instance);
                        if($failed != ""){
                            array_push($failed_records,$failed);
                        }
                    }
                }else{
                    $failed = $this->addQueueEmailFromInterface(PROJECT_ID, $index, $id, $times_sent, $event_id, $last_sent, $instance);
                    if($failed != ""){
                        array_push($failed_records,$failed);
                    }
                }
            }
        }

        echo json_encode(array(
            'status' => 'success',
            'failed_records' => $failed_records
        ));
    }

    // Obtain the notification log as an array - (past, present, and future) with filters and paging
    public function getNotificationLog($record=null, $returnCountOnly=false)
    {
        // Initialize vars
        global $Proj, $longitudinal, $table_pk, $table_pk_label, $lang, $user_rights, $twilio_enabled;

        // Determine which active alert as the longest cron interval to determine the end time
        $alert_settings = $this->getAlertSettings(PROJECT_ID);
        $maxAlertCronInterval = 0;
        foreach ($alert_settings as $row) {
            if ($row['email_deleted']) continue;
            $intervalMinutes = $row['cron_repeat_for']*($row['cron_repeat_for_units'] == 'DAYS' ? 1440 : ($row['cron_repeat_for_units'] == 'HOURS' ? 60 : 1));
            if ($intervalMinutes > $maxAlertCronInterval) $maxAlertCronInterval = $intervalMinutes;
        }
        // Show end time as 5x the longest cron interval
        $maxAlertCronInterval = 5*$maxAlertCronInterval;

        // Set error msg default
        $errorMsg = '';

        // Set NOW in user defined date format but with military time
        $now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');

        ## DEFINE FILTERING VALUES
        // Set some flags to disable buttons
        $disableViewPastInvites = $disableViewFutureInvites = "";
        // Set defaults
        if (isset($_GET['pagenum']) && (is_numeric($_GET['pagenum']) || $_GET['pagenum'] == 'last')) {
            // do nothing
        } elseif (!isset($_GET['pagenum'])) {
            $_GET['pagenum'] = 1;
        } else {
            $_GET['pagenum'] = 'ALL';
        }
        $_GET['filterRecord'] = isset($_GET['filterRecord']) ? urldecode(rawurldecode($_GET['filterRecord'])) : '';
        $_GET['filterAlert'] = (isset($_GET['filterAlert']) && is_numeric($_GET['filterAlert'])) ? (int)$_GET['filterAlert'] : '';
        // Run the value through the regex pattern
        if (!isset($_GET['filterBeginTime'])) {
            // Default beginTime = right now
            $_GET['filterBeginTime'] = $now_user_date_military_time;
        }
        if ($maxAlertCronInterval > 0 && (!isset($_GET['filterEndTime']) || (isset($_GET['filterEndTime']) && $_GET['filterEndTime'] == ''))) {
            // Default endTime
            $_GET['filterEndTime'] = DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime(NOW . " + $maxAlertCronInterval minutes")), true, false);
        }
        // Set flags (if timestamp is within the same hour as now, then consider it now)
        if ($_GET['filterBeginTime'] == '' && substr($_GET['filterEndTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
            $disableViewPastInvites = "disabled";
        }
        if ($_GET['filterEndTime'] == '' && substr($_GET['filterBeginTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
            $disableViewFutureInvites = "disabled";
        }

        // If user is in a DAG, only allow them to see participants in their DAG
        $dag_records = array();
        if ($user_rights['group_id'] != '')
        {
            // Validate DAG that user is in
            $dags = $Proj->getGroups();
            if (isset($dags[$user_rights['group_id']])) {
                $dag_records = Records::getData('array', ($record===null ? array() : $record), $table_pk, array(), $user_rights['group_id']);
            }
        }

        // Get all notifications that have already been sent
        $notificationLog = array();
        $sql = "select l.alert_sent_log_id, a.alert_id, s.record, s.event_id, s.instrument, 
                s.instance, l.time_sent as send_time, l.email_to, l.subject, l.alert_type, l.phone_number_to
                from redcap_alerts a, redcap_alerts_sent s, redcap_alerts_sent_log l 
				where a.project_id = ".PROJECT_ID." and a.alert_id = s.alert_id and s.alert_sent_id = l.alert_sent_id";
        if ($record !== null) $sql .= " and s.record = '".db_escape($record)."'";
        if (!empty($_GET['filterAlert'])) $sql .= " and a.alert_id = '".db_escape($_GET['filterAlert'])."'";
        $sql .= " order by l.time_sent";
        $q = db_query($sql);
        // Loop through all rows and store values in array
        while ($row = db_fetch_assoc($q))
        {
            if ($row['instance'] == "") $row['instance'] = '1';
            //if ($record !== null && $row['record'] != $record) continue;
            $row['aq_id'] = '';
            $row['was_sent'] = '1';
            // Add this invitation to array
            $notificationLog[] = $row;
        }

        ## PERFORM MORE FILTERING
        // Now filter $notificationLog by filters defined
        if ($_GET['filterBeginTime'] != '') {
            $filterBeginTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
        }
        if ($_GET['filterEndTime'] != '') {
            $filterEndTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
        }
        // Make sure begin time occurs *before* end time. If not, display error message to user.
        if (isset($filterBeginTimeYmd) && isset($filterEndTimeYmd) && $filterBeginTimeYmd > $filterEndTimeYmd) {
            $errorMsg = RCView::div(array('class'=>'yellow','style'=>'margin-bottom:10px;'),
                RCView::b($lang['global_01'].$lang['colon']).' '.$lang['survey_402']
            );
        }
        // Loop through all invitations and remove those that should be filtered
        foreach ($notificationLog as $key=>$attr)
        {
            // Filter by *displayed* record named
            if ($_GET['filterRecord'] != '' && $attr['record'] != $_GET['filterRecord']) {
                unset($notificationLog[$key]); continue;
            }
            // Filter by begin time
            if (isset($filterBeginTimeYmd) && substr($attr['send_time'], 0, 16) < $filterBeginTimeYmd) {
                unset($notificationLog[$key]); continue;
            }
            // Filter by end time
            if (isset($filterEndTimeYmd) && substr($attr['send_time'], 0, 16) > $filterEndTimeYmd) {
                unset($notificationLog[$key]); continue;
            }
            // Filter by DAG (if current user is assigned to a DAG)
            if ($user_rights['group_id'] != '' && $attr['record'] != '' && !isset($dag_records[$attr['record']])) {
                unset($notificationLog[$key]); continue;
            }
        }

        // Now add all projected future notifications to the notification log
        // (SKIP THIS SECTION if we're looking at past timestamps only - this is only for future projections)
        $recurrences = array();
        if ($filterEndTimeYmd == '' || $filterEndTimeYmd > substr(NOW, 0, 16))
        {
            $sql = "select r.aq_id, a.alert_id, r.record, r.event_id, r.instrument, r.instance, a.email_to, a.email_subject as subject,
                    r.first_send_time, r.times_sent, a.cron_repeat_for_max, a.cron_repeat_for, a.cron_repeat_for_units, a.cron_repeat_for_units, 
                    a.alert_expiration, a.alert_type, a.phone_number_to, r.send_option
                    from redcap_alerts a, redcap_alerts_recurrence r
                    where a.project_id = " . PROJECT_ID . " and a.alert_id = r.alert_id and a.email_deleted = 0 
                    and a.cron_send_email_on = r.send_option
                    and (a.cron_repeat_for > 0 || (a.cron_repeat_for = 0 and r.times_sent = 0))";
            if ($record !== null) $sql .= " and r.record = '" . db_escape($record) . "'";
            if ($_GET['filterRecord'] != '') $sql .= " and r.record = '" . db_escape($_GET['filterRecord']) . "'";
            if (!empty($_GET['filterAlert'])) $sql .= " and a.alert_id = '".db_escape($_GET['filterAlert'])."'";
            $sql .= " order by r.first_send_time";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $row['alert_sent_log_id'] = '';
                $recurrences[] = $row;
            }

            // Loop through all rows and store values in array
            $maxLoops = 100; // How many instances of EACH recurrence should we show (max)?
            foreach ($recurrences as $key=>$row)
            {
                $intervalMinutes = ($row['cron_repeat_for_units'] == 'DAYS' ? 1440 : ($row['cron_repeat_for_units'] == 'HOURS' ? 60 : 1));
                $i = 0;
                for ($recurrenceNum = 0; $recurrenceNum < $maxLoops; $recurrenceNum++)
                {
                    $totalMinutes = ($row['times_sent'] + $recurrenceNum) * $row['cron_repeat_for'] * $intervalMinutes;
                    $row['send_time'] = date('Y-m-d H:i:s', strtotime($row['first_send_time'] . " + $totalMinutes minutes"));
                    // If this is a one-time scheduled alert (not a recurrence), then stop here
                    if ($row['cron_repeat_for'] == '0' && $recurrenceNum >= 1) break;
                    // If this projected time is in the past (how?) or if user set an end time filter, then skip to next
                    if ($row['send_time'] < NOW) continue;
                    if (isset($filterEndTimeYmd) && substr($row['send_time'], 0, 16) > $filterEndTimeYmd) {
                        break;
                    }
                    // If a recurrence maximum is set, then if we've already hit the max, don't show any more of this recurrence.
                    if ($row['cron_repeat_for_max'] != '' && ($row['times_sent'] + $i + ($row['send_option'] != 'now' ? 0 : 1)) >= $row['cron_repeat_for_max']) {
                       break;
                    }
                    // If alert will expiration at a certain time, then don't project any future notifications past that time
                    if ($row['alert_expiration'] != '' && $row['send_time'] > $row['alert_expiration']) break;
                    // Remove extras
                    $row2 = $row;
                    unset($row2['first_send_time'], $row2['times_sent'], $row2['cron_repeat_for'], $row2['cron_repeat_for_units']);
                    // Add others
                    $row2['was_sent'] = '0';
                    if ($row2['instance'] == "") $row2['instance'] = '1';
                    // Add to array
                    $notificationLog[] = $row2;
                    $i++;
                }
                unset($recurrences[$key]);
            }
        }

        // Loop through all notifications to get all the record names
        $displayed_records = $send_times = array();
        foreach ($notificationLog as $key=>$attr)
        {
            $send_times[$key] = $attr['send_time'];
            $displayed_records[$attr['record']] = $attr['record'];
        }
        natcasesort($displayed_records);
        array_multisort($send_times, SORT_REGULAR, $notificationLog);

        // Return log as array
        if ($returnCountOnly) {
            return count($notificationLog);
        } else {
            return array($notificationLog, $displayed_records);
        }
    }


    // Display a table listing all survey invitations (past, present, and future) with filters and paging
    public function renderNotificationLog($record=null, $showFullTableDisplay=true)
    {
        // Initialize vars
        global $Proj, $longitudinal, $table_pk, $table_pk_label, $lang, $user_rights, $twilio_enabled;

        // Get the invitation log
        list ($notificationLog, $displayed_records) = $this->getNotificationLog($record);

        $alerts_settings = $this->getAlertSettings(PROJECT_ID);
        $all_active_alerts = array();
        foreach ($alerts_settings as $attr) {
            if ($attr['email_deleted'] == '1') continue;
            $all_active_alerts[$attr['alert_id']] = $lang['alerts_24']." #".$attr['alert_number'];
            if ($attr['alert_title'] != '') {
                $all_active_alerts[$attr['alert_id']] .= $lang['colon'] . " " . $attr['alert_title'];
            }
        }

        // Set NOW in user defined date format but with military time
        $now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');

        ## BUILD THE DROP-DOWN FOR PAGING THE INVITATIONS
        // Get participant count
        $notificationCount = count($notificationLog);
        // Section the Participant List into multiple pages
        $num_per_page = self::notification_log_num_per_page;
        // Calculate number of pages of for dropdown
        $num_pages = ceil($notificationCount/$num_per_page);
        if ($num_pages == 0) {
            $pageDropdown .= "<option value=''>0</option>";
        } else {
            $pageDropdown .= "<option value='ALL'>-- {$lang['docs_44']} --</option>";
        }
        // Limit
        $limit_begin  = 0;
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
            $_GET['pagenum'] = $num_pages;
        }
        if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
            $limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
        }
        ## Build the paging drop-down for participant list
        $pageDropdown = "<select id='pageNumInviteLog' onchange='loadNotificationLog(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            $value_num = $end_num - $num_per_page;
            if ($end_num > $notificationCount) $end_num = $notificationCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $notificationCount";

        // If viewing ALL invitations, then set $num_per_page to null to return all invitations
        if ($_GET['pagenum'] == 'ALL' || !$showFullTableDisplay) $num_per_page = null;

        // Loop through all invitations for THIS PAGE and build table
        $rownum = 0;
        foreach (array_slice($notificationLog, $limit_begin, $num_per_page) as $row)
        {
            // Set color of timestamp (green if already sent, red if failed) and icon
            $tsIcon  = ($row['was_sent'] == '0') ? "clock_small.png" : ($row['was_sent'] == '1' ? "tick_small_circle.png" : "bullet_delete.png");
            $tsColor = ($row['was_sent'] == '0') ? "gray" : ($row['was_sent'] == '1' ? "green" : "red");

            $alert_number = $alerts_settings[$row['alert_id']]['alert_number'];

            // If scheduled and not sent yet, display cross icon to delete the invitation
            $deleteEditInviteIcons = '';
            if ($showFullTableDisplay && $row['was_sent'] == '0') {
                $deleteEditInviteIcons =
                    RCView::a(array('href'=>'javascript:;','style'=>'margin:0 2px 0 5px;','onclick'=>"deleteRecurrence({$row['aq_id']},{$row['alert_id']},'{$alert_number}','".js_escape($row['record'])."','".js_escape($Proj->eventInfo[$row['event_id']]['name_ext'])."')"),
                        RCView::img(array('src'=>'cross_small2.png','class'=>'inviteLogDelIcon opacity50','title'=>$lang['alerts_29']))
                    );
            }

            // Send time (and icon)
            $rows[$rownum][] = 	// Invisible YMD timestamp (for sorting purposes
                RCView::span(array('class'=>'hidden'), $row['send_time']) .
                // Display time and icon
                RCView::span(array('style'=>"color:$tsColor;"),
                    RCView::img(array('src'=>$tsIcon, 'style'=>'margin-right:2px;')) .
                    DateTimeRC::format_ts_from_ymd($row['send_time']) .
                    $deleteEditInviteIcons
                );

            $rows[$rownum][] = '#'.$alert_number;

            $onclick = "loadPreviewEmailAlertRecord('{$row['alert_sent_log_id']}','{$row['aq_id']}','{$alert_number}');";
            $rows[$rownum][] = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>$onclick."return false;"),
                                    RCView::img(array('src'=>'mail_open_document.png', 'title'=>$lang['alerts_28']))
                                );

            // Record ID (if not anonymous response)
            if ($row['instrument'] != '' && $row['event_id'] != '') {
                $recordLink = "DataEntry/index.php?pid=".PROJECT_ID."&page={$row['instrument']}&event_id={$row['event_id']}&id={$row['record']}&instance={$row['instance']}";
            } else {
                $recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$row['record']}";
                if ($Proj->multiple_arms) {
                    if ($row['event_id'] != '') {
                        $recordLink .= "&arm=" . $Proj->eventInfo[$row['event_id']]['arm_num'];
                    }
                }
            }
            $rows[$rownum][] = 	RCView::div(array('class'=>'wrap', 'style'=>'word-wrap:break-word;'),
                ($row['record'] == '' ? "" : ($row['record'] == '' ? '<i class="far fa-eye-slash" style="color:#ddd;"></i>' :
                    RCView::a(array('href'=>APP_PATH_WEBROOT.$recordLink, 'style'=>'font-size:12px;text-decoration:underline;'), $row['record']) .
                    ($Proj->isRepeatingFormOrEvent($row['event_id'], $row['instrument']) ? "&nbsp;&nbsp;<span style='color:#777;'>(#{$row['instance']})</span>" : "") .
                    (!$longitudinal ? "" : "&nbsp;&nbsp;<span style='color:#777;'>-&nbsp;".$Proj->eventInfo[$row['event_id']]['name_ext']."</span>")
                ))
            );

            if ($row['alert_type'] == "EMAIL") {
				$rows[$rownum][] = "<i class='fas fa-envelope mr-1 opacity35'></i>".$row['email_to'];
				$rows[$rownum][] = strip_tags($row['subject']);
            } else {
                // Format all the numbers
				$phone_number_tos = array();
				foreach (explode(";", $row['phone_number_to']) as $this_phone_number)
				{
					$this_phone_number = trim($this_phone_number);
					if ($this_phone_number == '') continue;
					$firstCharacter = substr($this_phone_number, 0, 1);
					if (is_numeric($firstCharacter)) {
						$this_phone_number = formatPhone($this_phone_number);
					}
					$phone_number_tos[] = $this_phone_number;
				}
				$phone_number_tos = implode("; ", $phone_number_tos);
				$rows[$rownum][] = ($row['alert_type'] == "SMS" ? "<i class='fas fa-sms mr-1 opacity35 fs15'></i>" : "<i class='fas fa-phone mr-1 opacity35'></i>") . $phone_number_tos;
				$rows[$rownum][] = "";
            }

            // Increment counter
            $rownum++;
        }

        // Give message if no invitations were sent
        if (empty($rows)) {
            $rows[$rownum] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['alerts_25']),"","","");
        }

        // Define table headers
        $headers = array();
        if ($showFullTableDisplay) {
            $headers[] = array(160, RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-down.png', 'style'=>'vertical-align:middle;')) .
                RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-up.png', 'style'=>'display:none;vertical-align:middle;')) .
                RCView::SP .
                $lang['alerts_21']);
            $headers[] = array(64,  RCView::span(array('class'=>'wrap'), $lang['alerts_24']), "center");
            $headers[] = array(64,  RCView::span(array('class'=>'wrap'), $lang['alerts_22']), "center");
            $headers[] = array(120, RCView::div(array('class'=>'wrap'), $lang['global_49']), "center");
            $headers[] = array(200, RCView::span(array('class'=>'wrap'), $lang['alerts_26']));
            $headers[] = array(260, RCView::span(array('class'=>'wrap'), $lang['alerts_23']));
        } else {
            // Limited display
            $headers[] = array(140, $lang['survey_436']);
            $headers[] = array(370, $lang['survey_437']);
        }
        // Add checkbox as checked for "Display reminders?"
        $filterRemindersChecked = ($_GET['filterReminders'] == '1') ? "checked" : "";
        // Define title
        $title = "";
        if ($showFullTableDisplay) {
            $title =	RCView::div(array('style'=>''),
                RCView::div(array('style'=>'padding:2px 20px 0 5px;float:left;font-size:14px;'),
                    $lang['alerts_20'] . RCView::br() .
                    RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                        $lang['survey_570']
                    ) . RCView::br() . RCView::br() .
                    RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),
                        $pageDropdown
                    )
                ) .
                ## QUICK BUTTONS
                RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
                    RCView::button(array($disableViewPastInvites=>$disableViewPastInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:green;display:block;',
                        'onclick'=>"$('#filterBeginTime').val('');$('#filterEndTime').val('$now_user_date_military_time');loadNotificationLog('last')"), $lang['alerts_18']) .
                    RCView::button(array($disableViewFutureInvites=>$disableViewFutureInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:#000066;display:block;',
                        'onclick'=>"$('#filterBeginTime').val('$now_user_date_military_time');$('#filterEndTime').val('');loadNotificationLog(1)"), $lang['alerts_19'])
                ) .
                ## FILTERS
                RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:15px;border-left:1px solid #ccc;'),
                    // Date/time range
                    $lang['survey_439'] .
                    RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    $lang['survey_440'] .
                    RCView::text(array('id'=>'filterEndTime','value'=>$_GET['filterEndTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                    // Display all active alerts displayed in this view
                    $lang['survey_441'] .
                    RCView::select(array('id'=>'filterAlert','style'=>'font-size:11px;margin:2px 3px;'),
                        (array(''=>$lang['alerts_27'])+$all_active_alerts), $_GET['filterAlert'],300) .
                    RCView::br() .
                    // Display record names displayed in this view
                    $lang['survey_441'] .
                    RCView::select(array('id'=>'filterRecord','style'=>'margin-left:3px;font-size:11px;'),
                        (array(''=>$lang['reporting_37'])+$displayed_records), $_GET['filterRecord'],300) .
                    RCView::br() .
                    // "Apply filters" button
                    RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadNotificationLog(1)"), $lang['survey_442']) .
                    RCView::a(array('href'=>PAGE_FULL."?pid=".PROJECT_ID."&route=AlertsController:setup&log=1",'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
                    // "Download log" button
//                    RCView::button(array('class'=>'btn btn-xs btn-defaultrc','style'=>'margin:5px 0 0 80px;font-size:11px;color:#006000;','onclick'=>"window.location.href = app_path_webroot+'Surveys/invitation_log_export.php'+window.location.search;"),
//                        RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
//                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_1053'])
//                    ) .
                    // "Delete selected" button
//                    RCView::button(array('class'=>'btn btn-xs btn-defaultrc','style'=>'margin:5px 0 0 150px;font-size:11px;color:#A00000;','onclick'=>"deleteMultipleInvites();"),
//                        '<i class="fas fa-check-square" style="vertical-align:middle;"></i> ' .
//                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_1212'])
//                    )
                ) .
                RCView::div(array('class'=>'clear'), '')
            );
        }
        $width = 948;
        // Build Invitation Log table
        return $errorMsg . renderGrid("notification_log_table", $title, $width, 'auto', $headers, $rows, true, true, false);
    }

    // Notice on page to migrate all Email Alerts (i.e., the external module) into Alerts for a given project
    public function migrateEmailAlertsNotice()
    {
        // Enable the EA->A&N converter at the system level (via redcap_config table)
        global $email_alerts_converter_enabled;
        if (!isset($email_alerts_converter_enabled) || $email_alerts_converter_enabled != '1') return;
        // Are we on the Email Alerts configure page?
        $onEMindexPage = (PAGE == 'ExternalModules/index.php' || (EXTMOD_EXTERNAL_INSTALL && strpos($_SERVER['REQUEST_URI'], "/external_modules/") !== false));
        $isEmailAlerts = (isset($_GET['prefix']) && ($_GET['prefix'] == 'vanderbilt_emailTrigger' || $_GET['prefix'] == 'email_alerts'));
        if (!($onEMindexPage && $isEmailAlerts && isset($_GET['page']) && $_GET['page'] == 'configure')) {
            return;
        }
        // Add button to page
        ?>
        <div id="migrateAlertsDialog" class="simpleDialog" title="Convert your Email Alerts?">
            <div>
                If you wish, you may convert all the Email Alerts in this project into Alerts & Notifications,
                which are an officially supported feature of REDCap. By doing this, all your Email Alerts will be converted to an
                equivalent form in Alerts & Notifications so that they will behave exactly the same way going forward (with the exception of several
                things listed below). So please consider these carefully before proceeding with the conversion. The conversion process will
                1) convert all Email Alerts (even deactivated alerts) into Alerts & Notifications, and 2) transfer all record recurrences
                in the queue into Alerts & Notifications and display them on the A&N Notification Log page.
            </div>
            <div class="mt-2">
                NOTE: After converting to Alerts & Notifications, it is possible (if necessary) to revert back to using Email Alerts again,
                in which you would need to delete the new Alerts & Notifications that were created,
                and then have your local REDCap administrator re-enable the Email Alerts module in this project, after which all your
                old Email Alerts would return again.
            </div>
            <div class="mt-2">
                Some caveats to consider before moving forward:
                <ul>
                    <li class="mt-1">
                        Once all your Email Alerts have been converted, the Email Alerts module will automatically be disabled for
                        this project. The module can only be re-enabled by a REDCap administrator (if need be).
                    </li>
                    <li class="mt-1">
                        The count/list of "records activated" for an Email Alert will not transfer to the new Alerts & Notifications
                        form of the same alert. So that information will not be carried over.
                    </li>
                    <li class="mt-1">
                        If you have an Email Alert that is piping [__SURVEYLINK_form_name] or [__FORMLINK_form_name], those will be
                        converted into the equivalent Smart Variables [survey-link:form_name] and [form-link:form_name], respectively.
                    </li>
                    <li class="mt-1">
                        If you have an Email Alert that is piping a survey link using [__SURVEYLINK_form_name],
                        it is important to know that while Email Alerts will force completed surveys to revert to incomplete status
                        when piping this survey link (to allow participants to return to them using the this link),
                        Alerts & Notifications will *not* revert the status of the survey in this way.
                        So if you would like participants to return to a completed survey, you should enable the option
                        to allow "return and modify completed responses" on the Survey Settings page.
                    </li>
                    <li class="mt-1">
                        If you have an Email Alert that has a "display name" listed after the "From" email address, the display name
                        will not be transferred to Alerts & Notifications.
                    </li>
                    <li class="mt-1">
                        If you have an Email Alert with an email schedule set to use a date value as the expiration date, this will be
                        converted into expiration date for the entire alert in Alerts & Notifications. So it is slightly different,
                        but essentially behaves the same way for most use cases.
                    </li>
                    <li class="mt-1">
                        If you have an Email Alert with an email schedule set to use conditional logic as the expiration mechanism
                        for the alert, this logic will be added to the new Alerts & Notifications alert's trigger conditional logic
                        (but in opposite logic format), and additionally the "Ensure logic is still true" option will be automatically enabled
                        for the alert in order to replicate the existing behavior in Alerts & Notifications.
                    </li>
                    <li class="mt-1">
                        All already-sent Email Alerts will be added to Alerts & Notifications' Notification Log, but since Email Alerts does
                        not record the timestamp of when each alert was sent, these alerts will all be added with their "send time" as the time of their
                        conversion into Alerts & Notifications.
                    </li>
                </ul>
            </div>
        </div>
        <script type="text/javascript">
        function migrateAlertsDialog() {
            simpleDialog(null,null,'migrateAlertsDialog',850,null,'Cancel',function(){
                showProgress(1);
                $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:migrateEmailAlerts', { }, function(data) {
                    showProgress(0);
                    $('#mainForm, #customizedAlertsPreview, #external_modules_panel, #footer, #center .col-md-12').remove();
                    modifyURL(app_path_webroot_full+'redcap_v'+redcap_version +'/index.php?pid='+pid+'&route=AlertsController:setup');
                    simpleDialog(data,null,null,600,function(){
                        window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:setup';
                    },'Close');
                });
            },'Convert to Alerts & Notifications');
            fitDialog($('#migrateAlertsDialog'));
            $('.ui-dialog-buttonpane button:eq(1)',$('#migrateAlertsDialog').parent()).css('font-weight','bold');
        }
        $(function(){
            if ($('#customizedAlertsPreview tr:visible').length >= 1) {
                $('.page_title').append('<div class="float-right ml-3 mr-5"><button class="btn btn-xs btn-rcgreen fs13 px-2 py-1" onclick="migrateAlertsDialog();return false;">'
                    + '<i class="fas fa-bell"></i> Convert to Alerts & Notifications</button></div>');
            }
        });
        </script>
        <?php
    }

    // Migrate all Email Alerts (i.e., the external module) into Alerts for a given project
    public function migrateEmailAlerts()
    {
        // Get current modules enabled in project
        $enabledModules = \ExternalModules\ExternalModules::getEnabledModules(PROJECT_ID);
        if (!isset($enabledModules['vanderbilt_emailTrigger']) && !isset($enabledModules['email_alerts'])) {
            // Module is not enabled, so nothing to do
            exit("ERROR: The Email Alerts module is not enabled for this project.");
        }

        // Get Email Alerts module prefix
        $sql = "SELECT m.directory_prefix FROM redcap_external_modules m, redcap_external_module_settings s WHERE m.external_module_id = s.external_module_id 
		        AND s.value = 'true' AND (m.directory_prefix = 'vanderbilt_emailTrigger' OR m.directory_prefix = 'email_alerts') AND s.`key` = 'enabled'
		        AND s.project_id = " . PROJECT_ID . " limit 1";
        $q = db_query($sql);
        $moduleDirectoryPrefix = db_result($q, 0);

        // Get module config settings
        $module = \ExternalModules\ExternalModules::getModuleInstance($moduleDirectoryPrefix);
        $settings = $module->getProjectSettings(PROJECT_ID);

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        // Loop through each alert
        $errors = array();
        $alertIdKeyMap = array();
        $alertCount = count($settings['alert-id']['value']);
        for ($i=0; $i<$alertCount; $i++)
        {
            // Transform certain values
            list ($email_from, $nothing) = explode(",", $settings['email-from']['value'][$i], 2);
            $email_from = trim($email_from);
            $alert_condition = trim($settings['email-condition']['value'][$i]);
            $email_to = str_replace(array(","," "), array(";",""), $settings['email-to']['value'][$i]);
            $email_cc = str_replace(array(","," "), array(";",""), $settings['email-cc']['value'][$i]);
            $email_bcc = str_replace(array(","," "), array(";",""), $settings['email-bcc']['value'][$i]);
            $cron_send_email_on = $settings['cron-send-email-on']['value'][$i];
            $cron_send_email_on_date = "";
            $alert_expiration = "";
            $ensure_logic_still_true = '0';
            if ($cron_send_email_on == 'calc') {
                // Append calc logic to regular logic with "now"
                $cron_send_email_on = 'now';
                if ($alert_condition != '') $alert_condition = "($alert_condition) and ";
                $alert_condition .= "(".$settings['cron-send-email-on-field']['value'][$i].")";
            } else if ($cron_send_email_on == 'date') {
                $cron_send_email_on_date = trim($settings['cron-send-email-on-field']['value'][$i])." 00:00:00";
            }
            $cron_repeat_for = is_numeric($settings['cron-repeat-for']['value'][$i]) ? $settings['cron-repeat-for']['value'][$i] : '0';
            $email_deleted = ($settings['email-deactivate']['value'][$i] == '1' || $settings['email-deleted']['value'][$i] == '1') ? '1' : '0';
            if ($settings['cron-queue-expiration-date']['value'][$i] == 'date') {
                $alert_expiration = trim($settings['cron-queue-expiration-date-field']['value'][$i])." 00:00:00";
            } elseif ($settings['cron-queue-expiration-date']['value'][$i] == 'cond') {
                // Append cond logic to regular logic as the "kill switch"
                if ($alert_condition != '') $alert_condition = "($alert_condition) and ";
                $alert_condition .= "!(".$settings['cron-queue-expiration-date-field']['value'][$i].")";
                $ensure_logic_still_true = '1';
            }
            $email_failed = isset($settings['emailFailed_var']['value']) ? $settings['emailFailed_var']['value'] : "";
            $email_failed = preg_split("/[;,]+/", $email_failed);
            $email_failed = trim($email_failed[0]);
            $alert_message = $settings['email-text']['value'][$i];
            $alert_message = str_replace("[__SURVEYLINK_", "[survey-link:", $alert_message);
            $alert_message = str_replace("[__FORMLINK_", "[form-link:", $alert_message);
            if (!isset($settings['email-sent']['value'][$i]) || $settings['email-sent']['value'][$i] == '') {
                $settings['email-sent']['value'][$i] = '0';
            }

            // Add values to array
            $newAlert = array(
                    'project_id' => PROJECT_ID,
                    'alert_title' => (isset($settings['alert-name']['value'][$i]) ? $settings['alert-name']['value'][$i] : ""),
                    'email_deleted' => $email_deleted,
                    'alert_expiration' => $alert_expiration,
                    'form_name' => $settings['form-name']['value'][$i],
                    'form_name_event' => $settings['form-name-event']['value'][$i],
                    'alert_condition' => $alert_condition,
                    'ensure_logic_still_true' => $ensure_logic_still_true,
                    'prevent_piping_identifiers' => '0',
                    'email_incomplete' => $settings['email-incomplete']['value'][$i],
                    'email_from' => $email_from,
                    'email_to' => $email_to,
                    'email_cc' => $email_cc,
                    'email_bcc' => $email_bcc,
                    'email_subject' => $settings['email-subject']['value'][$i],
                    'alert_message' => $alert_message,
                    'email_failed' => $email_failed,
                    'email_attachment_variable' => $settings['email-attachment-variable']['value'][$i],
                    'email_attachment1' => $settings['email-attachment1']['value'][$i],
                    'email_attachment2' => $settings['email-attachment2']['value'][$i],
                    'email_attachment3' => $settings['email-attachment3']['value'][$i],
                    'email_attachment4' => $settings['email-attachment4']['value'][$i],
                    'email_attachment5' => $settings['email-attachment5']['value'][$i],
                    'email_repetitive' => $settings['email-repetitive']['value'][$i],
                    'cron_send_email_on' => $cron_send_email_on,
                    'cron_send_email_on_date' => $cron_send_email_on_date,
                    'cron_repeat_for' => $cron_repeat_for,
                    'email_timestamp_sent' => $settings['email-timestamp-sent']['value'][$i],
                    'email_sent' => $settings['email-sent']['value'][$i]
            );

            // Add alert to table
            $sql = "insert into redcap_alerts (".implode(', ', array_keys($newAlert)).") 
                    values (".prep_implode($newAlert, true, true).")";
            if (db_query($sql)) {
                // Logging
                $alert_id = db_insert_id();
                unset($this->alerts_settings[PROJECT_ID]); // Reset this so that the new one will be auto-added
                $alertKey = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
                $alertIdKeyMap[$i] = $alert_id;
                $alert_number = ($alertKey + 1);
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                    "Alert #{$alert_number},\nMigrate Email Alert #".($i+1)." as Alert #{$alert_number}", "Create alert");
                // Disable each original Email Alert individually to deal with possible issue of them running via cron even though the module has been disabled for the project
				$email_deactivate = $module->getProjectSetting('email-deactivate');
				$email_deactivate[$alertKey] = "1"; // Set to deactivated status
				$module->setProjectSetting('email-deactivate', $email_deactivate);
            } else {
                $errors[] = $sql;
            }
        }

        // Get alert settings for all old and new ones
        $alertSettings = $this->getAlertSettings(PROJECT_ID);

        // Import the email queue
        $email_queue = $module->getProjectSetting('email-queue');
        foreach ($email_queue as $attr)
        {
            $this_alert_id = $alertIdKeyMap[$attr['alert']];
            $cron_send_email_on = $settings['cron-send-email-on']['value'][$attr['alert']];
            $creation_date = trim($attr['creation_date'])." 00:00:00";
            if ($cron_send_email_on == 'date') {
                $first_send_time = trim($settings['cron-send-email-on-field']['value'][$attr['alert']])." 00:00:00";
            } else {
                $first_send_time = $creation_date;
            }
            $newRecurrence = array(
                'alert_id'=>$this_alert_id,
                'creation_date'=>$creation_date,
                'record'=>$attr['record'],
                'event_id'=>$attr['event_id'],
                'instrument'=>$attr['instrument'],
                'instance'=>(is_numeric($attr['instance']) ? $attr['instance'] : '1'),
                'send_option'=>($attr['option'] == 'calc' ? 'now' : $attr['option']),
                'times_sent'=>(is_numeric($attr['times_sent']) ? $attr['times_sent'] : '0'),
                'last_sent'=>($attr['last_sent'] == '' ? '' : trim($attr['last_sent'])." 00:00:00"),
                'first_send_time'=>$first_send_time
            );
            $sql = "replace into redcap_alerts_recurrence (".implode(', ', array_keys($newRecurrence)).") 
                    values (".prep_implode($newRecurrence, true, true).")";
            if (!db_query($sql)) {
                $errors[] = $sql;
            }
        }

        // Import the records sent array
        $recordsSent = isset($settings['email-repetitive-sent']['value']) ? json_decode($settings['email-repetitive-sent']['value'], true) : array();
        foreach ($recordsSent as $form_name=>$attr1) {
            foreach ($attr1 as $alert_key=>$battr) {
                $this_alert_id = $alertIdKeyMap[$alert_key];
                foreach ($battr as $recordOrRepeatInstances=>$cattr) {
                    if ($recordOrRepeatInstances == 'repeat_instances') {
                        foreach ($cattr as $record=>$dattr) {
                            foreach ($dattr as $event_id=>$eattr) {
                                foreach ($eattr as $instance) {
                                    $newSent = array(
                                        'alert_id'=>$this_alert_id,
                                        'record'=>$record,
                                        'event_id'=>$event_id,
                                        'instrument'=>$form_name,
                                        'instance'=>(is_numeric($instance) ? $instance : '1'),
                                        'last_sent'=>NOW
                                    );
                                    $sql = "replace into redcap_alerts_sent (".implode(', ', array_keys($newSent)).") 
                                            values (".prep_implode($newSent, true, true).")";
                                    if (!db_query($sql))  $errors[] = $sql;
                                    $alertSentId = db_insert_id();
                                    $newSentLog = array(
                                        'alert_sent_id'=>$alertSentId,
                                        'time_sent'=>NOW,
                                        'email_from'=>$alertSettings[$this_alert_id]['email_from'],
                                        'email_to'=>$alertSettings[$this_alert_id]['email_to'],
                                        'email_cc'=>$alertSettings[$this_alert_id]['email_cc'],
                                        'email_bcc'=>$alertSettings[$this_alert_id]['email_bcc'],
                                        'subject'=>$alertSettings[$this_alert_id]['email_subject'],
                                        'message'=>$alertSettings[$this_alert_id]['alert_message']
                                    );
                                    $sql = "replace into redcap_alerts_sent_log (".implode(', ', array_keys($newSentLog)).") 
                                            values (".prep_implode($newSentLog, true, true).")";
                                    if (!db_query($sql))  $errors[] = $sql;
                                }
                            }
                        }
                    } else {
                        $record = $recordOrRepeatInstances;
                        foreach ($cattr as $event_id=>$eattr) {
                            foreach ($eattr as $instance) {
                                $newSent = array(
                                    'alert_id'=>$this_alert_id,
                                    'record'=>$record,
                                    'event_id'=>$event_id,
                                    'instrument'=>$form_name,
                                    'instance'=>(is_numeric($instance) ? $instance : '1'),
                                    'last_sent'=>NOW
                                );
                                $sql = "replace into redcap_alerts_sent (".implode(', ', array_keys($newSent)).") 
                                            values (".prep_implode($newSent, true, true).")";
                                if (!db_query($sql))  $errors[] = $sql;
                                $alertSentId = db_insert_id();
                                $newSentLog = array(
                                    'alert_sent_id'=>$alertSentId,
                                    'time_sent'=>NOW,
                                    'email_from'=>$alertSettings[$this_alert_id]['email_from'],
                                    'email_to'=>$alertSettings[$this_alert_id]['email_to'],
                                    'email_cc'=>$alertSettings[$this_alert_id]['email_cc'],
                                    'email_bcc'=>$alertSettings[$this_alert_id]['email_bcc'],
                                    'subject'=>$alertSettings[$this_alert_id]['email_subject'],
                                    'message'=>$alertSettings[$this_alert_id]['alert_message']
                                );
                                $sql = "replace into redcap_alerts_sent_log (".implode(', ', array_keys($newSentLog)).") 
                                            values (".prep_implode($newSentLog, true, true).")";
                                if (!db_query($sql))  $errors[] = $sql;
                            }
                        }
                    }
                }
            }
        }

        // Any errors?
        if (empty($errors)) {
            // Disable the Email Alerts module (we have to do this manually via SQL since on admins can do it normally via EM methods)
            $sql = "UPDATE redcap_external_modules m, redcap_external_module_settings s 
                    SET s.value = 'false'
                    WHERE m.external_module_id = s.external_module_id 
                    AND (m.directory_prefix = 'vanderbilt_emailTrigger' OR m.directory_prefix = 'email_alerts') 
                    AND s.`key` = 'enabled'
                    AND s.project_id = " . PROJECT_ID;
            $q = db_query($sql);
            $logText = "Disable external module \"{$moduleDirectoryPrefix}_{$version}\" for project";
            REDCap::logEvent($logText);
            // Success
            db_query("COMMIT");
            db_query("SET AUTOCOMMIT=1");
            // Return msg
            exit("<div class='green'><i class=\"fas fa-check\"></i> SUCCESS: All $alertCount Email Alerts were successfully converted into Alerts & Notifications. The Email Alerts module has now been disabled for this project, and you will be redirected to the Alerts & Notifications page.<br><br><i class='fs11'>NOTE: If you wish you revert back to using Email Alerts again, you should delete these new Alerts & Notifications, and then have your local REDCap administrator re-enable the Email Alerts module in this project.</i></div>");
        } else {
            // Failed: Display error message and roll back
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            $adminMsg = "";
            if (SUPER_USER) {
                $adminMsg = "Administrator Message - The following SQL queries failed:<br> - ".implode("<br> - ", $errors);
            }
            // Return msg
            exit("<div class='red'><i class=\"fas fa-times\"></i> ERROR: An error occurred during the migration of Email Alerts into Alerts & Notifications. No conversions were made. $adminMsg</div>");
        }
    }
}



