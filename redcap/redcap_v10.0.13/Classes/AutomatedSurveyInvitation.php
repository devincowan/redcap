<?php

class AutomatedSurveyInvitation
{

    private $project_id = null;
    private static $API_call_prefix = 'AutomatedSurveyInvitation';
    private static $ASI_fields_defaults = null;
    private static $ASI_fields = array(
        'survey_id',
        'event_id',
        'email_subject',
        'email_content',
        'email_sender',
        'email_sender_display',
        'condition_send_time_exact',
        'condition_surveycomplete_survey_id',
        'condition_surveycomplete_event_id',
        'condition_logic',
        'condition_send_time_option',
        'condition_send_next_day_type',
        'condition_send_next_time',
        'condition_send_time_lag_days',
        'condition_send_time_lag_hours',
        'condition_send_time_lag_minutes',
        'condition_andor',
        'active',
        'reminder_num',
        'reminder_type',
        'reminder_timelag_days',
        'reminder_timelag_hours',
        'reminder_timelag_minutes',
        'reminder_nextday_type',
        'reminder_nexttime',
        'reminder_exact_time',
        'delivery_type',
        'reeval_before_send'
    );

    function __construct($project_id = null)
    {
        $this->project_id = $project_id;
        $this->project = new \Project($this->project_id);

        // Get list of surveys/events in a project’s Survey Queue (comes from db table redcap_surveys_queue)
        $surveyQueue = Survey::getProjectSurveyQueue(true);
    }
    
    /**
     * import settings from CSV
     * in case of error rollback every change made to the database
     *
     * @return void
     */
    public function import()
    {
        // get the uploaded files
        $files = FileManager::getUploadedFiles();
        // check that it's just one file 
        if(count($files)>1)
        {
            throw new Exception("only one file can be uploaded", 1);
        }
        else if (count($files)==0)
        {
            throw new Exception("no file uploaded", 1);
        }
        $file = array_pop($files);
        // get the settings from the csv file
        $csv = FileManager::readCSV($file['tmp_name']);
        $all_ASI_settings = FileManager::csvToAssociativeArray($csv);
        $response = array(
            'success' => true,
            'data' => array(),
        );
        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        
        foreach ($all_ASI_settings as $settings) {
            // convert event and survey names to ids
            $event_id = $this->project->getEventIdUsingUniqueEventName($settings['event_name']);
            $survey_id = $this->getSurveyIdByname($settings['form_name']);
            $settings['condition_surveycomplete_survey_id'] = $this->getSurveyIdByname($settings['condition_surveycomplete_form_name']);
            $settings['condition_surveycomplete_event_id'] = $this->project->getEventIdUsingUniqueEventName($settings['condition_surveycomplete_event_name']);
            
            $survey_title = $this->getSurveyPropertyById($survey_id, 'title');
            $event_title = $this->getEventPropertyById($event_id, 'name_ext');
            try {
                $this->setASISettings($settings, $survey_id, $event_id);
                $response['data'][] = compact(array('survey_id', 'event_id', 'survey_title', 'event_title'));
            } catch (\Exception $e) {
                db_query("ROLLBACK");
			    db_query("SET AUTOCOMMIT=1");
                $error = $e->getMessage();
                $response = array(
                    'error' => true,
                    'message' => $error
                );
                $this->printJSON($response);
            }
        }
        db_query("COMMIT");
        db_query("SET AUTOCOMMIT=1");
        $this->printJSON($response);
    }

    /**
     * @return array
     */
    public function getHelpFieldsList()
    {
        global $lang;
        $Proj = new Project($this->project_id);
        $ASIexport_help_fields_list = array(
            'active ('.$lang['asi_011'].')',
            'form_name ('.$lang['design_244'].')',
            'event_name ('.$lang['define_events_65'].')',
            'email_sender ('.$lang['asi_010'].')',
            'email_subject ('.$lang['pub_063'].')',
            'email_content ('.$lang['pub_065'].')',
            'condition_surveycomplete_form_name ('.$lang['asi_008'].')',
            'condition_surveycomplete_event_name ('.$lang['asi_009'].')',
            'condition_andor (AND, OR)',
            'condition_logic ('.$lang['asi_012'].')',
            'reeval_before_send ('.$lang['asi_013']." - ".$lang['survey_922'].')',
            'condition_send_time_option (IMMEDIATELY, NEXT_OCCURRENCE, TIME_LAG, EXACT_TIME)',
            'condition_send_next_day_type (DAY, WEEKDAY, WEEKENDDAY, SUNDAY, MONDAY, TUESDAY, WEDNESDAY, THURSDAY, FRIDAY, SATURDAY)',
            'condition_send_next_time (HH:MM)',
            'condition_send_time_lag_days ('.$lang['design_86'].')',
            'condition_send_time_lag_hours ('.$lang['design_86'].')',
            'condition_send_time_lag_minutes ('.$lang['design_86'].')',
            'condition_send_time_exact (YYYY-MM-DD HH:MM:SS)',
            'reminder_num ('.$lang['design_86'].')',
            'reminder_type (TIME_LAG, NEXT_OCCURRENCE, EXACT_TIME)',
            'reminder_timelag_days ('.$lang['design_86'].')',
            'reminder_timelag_hours ('.$lang['design_86'].')',
            'reminder_timelag_minutes ('.$lang['design_86'].')',
            'reminder_nextday_type (DAY, WEEKDAY, WEEKENDDAY, SUNDAY, MONDAY, TUESDAY, WEDNESDAY, THURSDAY, FRIDAY, SATURDAY)',
            'reminder_nexttime (HH:MM)',
            'reminder_exact_time (YYYY-MM-DD HH:MM:SS)',
            'delivery_type (EMAIL, PARTICIPANT_PREF, VOICE_INITIATE, SMS_INITIATE, SMS_INVITE_MAKE_CALL, SMS_INVITE_RECEIVE_CALL, SMS_INVITE_WEB)'
        );

        // Remove event columns for non-longitudinal projects
        foreach ($ASIexport_help_fields_list as $key=>$val) {
            list ($this_col, $val2) = explode(" ", $val, 2);
            if (!$Proj->longitudinal && strpos($this_col, 'event_name') !== false) {
                unset($ASIexport_help_fields_list[$key]);
                continue;
            }
            // Style the column name as bold
            $ASIexport_help_fields_list[$key] = RCView::b($this_col)." $val2";
            // Only use delivery type for Twilio enabled projects
            if (!($Proj->project['$twilio_enabled'] && $Proj->twilio_enabled_surveys) && $this_col == 'delivery_type') {
                unset($ASIexport_help_fields_list[$key]);
            }
        }
        return  $ASIexport_help_fields_list;
    }
 
    /**
     * export settings to CSV
     * replace ids with names 
     *
     * @return void
     */
    public function export($filename, $options)
    {
        $Proj = new Project($this->project_id);
        $asi = $this->getScheduledASI();
        $data_rows = array();
        foreach ($asi as $survey_id => $event) {
            foreach($event as $event_id => $settings)
            {
                 // convert event and survey ids to name
                $form_name = $this->getSurveyPropertyById($survey_id, 'form_name');
                $event_name = Event::getEventNameById($this->project_id, $event_id);
                $condition_surveycomplete_form_name = $this->getSurveyPropertyById($settings['condition_surveycomplete_survey_id'], 'form_name');
                $condition_surveycomplete_event_name = Event::getEventNameById($this->project_id,$settings['condition_surveycomplete_event_id']);

                // remove columns with ids from the settings
                unset($settings['condition_surveycomplete_survey_id']);
                unset($settings['condition_surveycomplete_event_id']);

                // add the new columns to the exported settings
                $this_row = array_merge(compact('form_name','event_name', 'condition_surveycomplete_form_name', 'condition_surveycomplete_event_name'), $settings);
                // Remove event_name columns if non-longitudinal
                if (!$Proj->longitudinal) {
                    foreach (array_keys($this_row) as $key) {
                        if (strpos($key, 'event_name') !== false) {
                            unset($this_row[$key]);
                        }
                    }
                }
                // Only use delivery type for Twilio enabled projects
                if (!$Proj->project['twilio_enabled'] && $Proj->twilio_enabled_surveys) {
                    unset($this_row['delivery_type']);
                }
                // Add to rows
                $data_rows[] = $this_row;
            }
        }
        FileManager::exportCSV($data_rows, 'asi_export_pid'.$this->project_id);
    }

    public function clone_asi($from, $to=array())
    {
        if(!isset($from->survey_id) || !isset($from->event_id) )
            throw new \Exception('missing values');

        $from->survey_title = $this->getSurveyPropertyById($from->survey_id, 'title');
        $from->event_title = $this->getEventPropertyById($from->event_id, 'name_ext');
        $settings = $this->getASISettings($from->survey_id, $from->event_id);
        $response = array(
            'success' => true,
            'from' => $from,
            'to' => array()
        );
        foreach ($to as $ASI) {
            $survey_id = $ASI->survey_id;
            $event_id = $ASI->event_id;
            $ASI->survey_title = $this->getSurveyPropertyById($survey_id, 'title');
            $ASI->event_title = $this->getEventPropertyById($event_id, 'name_ext');
            try {
                $this->setASISettings($settings, $survey_id, $event_id);
                $response['to'][] = $ASI;
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $response = array(
                    'error' => true,
                    'message' => $error
                );
                $this->printJSON($response);
            }
        }
        $this->printJSON($response);
    }

    /**
     * get the settings of an ASI
     *
     * @param int $survey_id
     * @param int $event_id
     * @return array settings
     */
    private function getASISettings($survey_id, $event_id)
    {
        $query = sprintf("SELECT %s FROM redcap_surveys_scheduler WHERE survey_id=%d AND event_id=%d",implode(', ', self::$ASI_fields), $survey_id, $event_id );
        
        $result = db_query($query);

        if($error = db_error()){
            throw new \Exception($query.': '.$error);
        }

        // $settings = db_fetch_object($result);
        $settings = db_fetch_array($result, MYSQLI_ASSOC);
        return $settings;
    }


    // Get ASI field defaults
    private static function getFieldDefaults()
    {
        if (self::$ASI_fields_defaults == null) {
            $cols = getTableColumns('redcap_surveys_scheduler');
            unset($cols['ss_id']);
            $cols['condition_andor'] = 'AND';
            self::$ASI_fields_defaults = $cols;
        }
        return self::$ASI_fields_defaults;
    }


    /**
     * create the INSERT or UPDATE query with placeholders for the survey_id and event_id
     *
     * @param array $settings
     * @return void
     */
    private function getInsertSettingsQuery($settings)
    {
        global $lang;

        $Proj = new Project($this->project_id);

        // convert event and survey names to ids
        if (isset($settings['event_name'])) $settings['event_id'] = $this->project->getEventIdUsingUniqueEventName($settings['event_name']);
        if (isset($settings['form_name']))  $settings['survey_id'] = $this->getSurveyIdByname($settings['form_name']);
        if (isset($settings['condition_surveycomplete_form_name'])) $settings['condition_surveycomplete_survey_id'] = $this->getSurveyIdByname($settings['condition_surveycomplete_form_name']);
        if (isset($settings['condition_surveycomplete_event_name'])) $settings['condition_surveycomplete_event_id'] = $this->project->getEventIdUsingUniqueEventName($settings['condition_surveycomplete_event_name']);

//        error_log("------");
//        error_log( print_r($settings, TRUE) );

        // Add settings on top of defaults
        $settings_values = self::getFieldDefaults();
        foreach ($settings_values as $key=>$val) {
            $settings_values[$key] = isset($settings[$key]) ? $settings[$key] : $val;
        }

        // Set any missing ones
        if (!$Proj->longitudinal && $settings_values['condition_surveycomplete_survey_id'] != '') {
            $settings_values['condition_surveycomplete_event_id'] = $Proj->firstEventId;
        }
        if (!$Proj->longitudinal) {
            $settings_values['event_id'] = $Proj->firstEventId;
        }

        // Make sure we have all the values we need
        $errors = array();
        if ($settings_values['condition_andor'] != 'AND' && $settings_values['condition_andor'] != 'OR') $settings_values['condition_andor'] = 'AND';
        if ($settings_values['active'] != '0' && $settings_values['active'] != '1') $settings_values['active'] = '0';
        if ($settings_values['reeval_before_send'] != '0' && $settings_values['reeval_before_send'] != '1') $settings_values['reeval_before_send'] = '0';
        if ($Proj->longitudinal && $settings_values['event_id'] == '') $errors[] = 'event_name';
        if ($Proj->longitudinal && $settings_values['condition_surveycomplete_survey_id'] != '' && $settings_values['condition_surveycomplete_event_id'] == '') {
            $errors[] = 'condition_surveycomplete_event_name';
        }
        if ($settings_values['survey_id'] == '') $errors[] = 'form_name';
        // if ($settings_values['email_subject'] == '') $errors[] = 'email_subject';
        // if ($settings_values['email_content'] == '') $errors[] = 'email_content';
        if ($settings_values['email_sender'] == '') $errors[] = 'email_sender';
        if ($settings_values['condition_surveycomplete_survey_id'] == '' && $settings_values['condition_logic'] == '') {
            $errors[] = 'condition_surveycomplete_form_name OR condition_logic';
        }

        // Make sure the From email address is a valid one in the project
        $allUserEmails = User::getEmailAllProjectUsers($this->project_id);
        if (SUPER_USER) {
            $user_info = User::getUserInfo(USERID);
            $allUserEmails[] = $user_info['user_email'];
            if ($user_info['user_email2'] != '') $allUserEmails[] = $user_info['user_email2'];
            if ($user_info['user_email3'] != '') $allUserEmails[] = $user_info['user_email3'];
            $allUserEmails = array_unique($allUserEmails);
        }
        if (!in_array($settings_values['email_sender'], $allUserEmails)) {
            $errors[] = 'email_sender';
        }

        // Any errors to send back?
        if (!empty($errors) && isset($_POST['AutomatedSurveyInvitation-import'])) {
            throw new \Exception($lang['asi_022']."<ul style='margin-top:10px;font-weight:bold;'><li>".implode("</li><li>", array_unique($errors))."</li></ul>");
        }

        $insert_vals = $update_vals = $settings_values;
        unset($update_vals['event_id'], $update_vals['survey_id']); // exclude these fields from the update part of the query

        $update_strings = array(); // store ths "ON DUPLICATE KEY UPDATE" strings
        foreach ($update_vals as $field=>$val) {
            $update_strings[] = " {$field} = ".checkNull($val);
        }

        $query = "INSERT INTO redcap_surveys_scheduler (".implode(", ", array_keys($insert_vals)).") 
                  VALUES (".prep_implode($insert_vals, true, true).")
                  ON DUPLICATE KEY UPDATE ".implode(", ", $update_strings);

        return $query;
    }

    /**
     * set the settings for the specified targets
     *
     * @param array $settings
     * @param int $survey_id
     * @param int $event_id
     * @return void
     */
    private function setASISettings($settings, $survey_id, $event_id)
    {
        if (isset($settings['event_id'])) $settings['event_id'] = $event_id;
        if (isset($settings['survey_id'])) $settings['survey_id'] = $survey_id;

        $query = $this->getInsertSettingsQuery($settings);

        $result = db_query($query);

        if($error = db_error()){
            throw new \Exception($query.': '.$error);
        }

        // Logging
        $existingSchedule = (db_affected_rows() != 1);
        $logDescrip = ($existingSchedule) ? "Edit settings for automated survey invitations" : "Add settings for automated survey invitations";
        Logging::logEvent($query,"redcap_surveys_scheduler","MANAGE",$this->project_id,"survey_id = {$survey_id}\nevent_id = {$event_id}",$logDescrip);
    }
    
    /**
     * get the property of a survey in a project from it's id
     *
     * @param int $survey_id
     * @return string
     */
    private function getSurveyPropertyById($survey_id, $property_name='')
    {
        $surveys = $this->project->surveys;
        $survey = $surveys[$survey_id];
        $property = ( array_key_exists($property_name, $survey) ) ? $survey[$property_name] : '';
        return $property;
    }

    /**
     * get the property of a event in a project from it's id
     *
     * @param int $survey_id
     * @return string
     */
    private function getEventPropertyById($event_id, $property_name='')
    {
        $eventInfo = $this->project->eventInfo;
        $event = $eventInfo[$event_id];
        $property = ( array_key_exists($property_name, $event) ) ? $event[$property_name] : '';
        return $property;
    }

    /**
     * get the id of survey in a project from it's name
     *
     * @param string $survey_name
     * @return int
     */
    private function getSurveyIdByname($survey_name)
    {
        $surveys = $this->project->surveys;
        foreach ($surveys as $survey_id => $survey) {
            if($survey['form_name']==$survey_name)
            {
                return $survey_id;
            }
        }
        return false;
    }
    
    /**
     * list the Automated Survey Invitations
     *
     * @return void
     */
    public function listAutomatedSurveyInvitations()
    {        
        // helper function to get a normalized array of data
        $normalize_data = function($asi_list) {
            $response = array('data'=>array());
            foreach ($asi_list as $survey_id => $events) {
                $survey_data = array(
                    'type' => 'survey',
                    'name' => $this->getSurveyPropertyById($survey_id, 'form_name'),
                    'title' => $this->getSurveyPropertyById($survey_id, 'title'),
                    'id' => $survey_id,
                    'data' => array(),
                );
                foreach ($events as $event_id => $settings) {
                    $event = array(
                        'type' => 'event',
                        'id' => $event_id,
                        'name' => Event::getEventNameById($this->project_id, $event_id),
                        'title' => $this->getEventPropertyById($event_id, 'name_ext'),
                        'data' => array(
                            'type' => 'settings',
                            'data' => $settings,
                        ),
                    );
                    $survey_data['data'][] = $event;
                }
                $response['data'][] = $survey_data;
            };
            return $response;
        };

        $asi = $this->getScheduledASI();
        $response = $normalize_data($asi);
        $this->printJSON($response);
    }

    /**
     * list the forms that are enabled as surveys
     *
     * @return object JSON
     */
    public function listSurveyEnabledForms()
    {
        // helper function to get a normalized array of data
        $normalize_data = function($surveys)
        {
            $data = array('data'=>array());
            foreach ($surveys as $survey_id => $survey_data) {
                $survey = array(
                    'type' => 'survey',
                    'name' => $survey_data['form_name'],
                    'title' => $survey_data['title'],
                    'data' => $survey_data,
                    'id' => $survey_id,
                    'relationships' => array(
                        'events' => array()
                    )
                );
                $formEvents = $this->getFormEvents($survey_data['form_name']);
                foreach ($formEvents as $event_id => $event_data) {
                    $event = array(
                        'type' => 'event',
                        'id' => $event_id,
                        'name' => Event::getEventNameById($this->project_id, $event_id),
                        'title' => $this->getEventPropertyById($event_id, 'name_ext'),
                        'data' => $event_data
                    );
                    $survey['relationships']['events'][] = $event;
                }
                $data['data'][] = $survey;
            }
            return $data;
        };

        $surveys = $this->project->surveys;
        $response = $normalize_data($surveys);
        $this->printJSON($response);
    }

    /**
     * get a list of events related to a form
     *
     * @param string $form_name
     * @return array
     */
    private function getFormEvents($form_name)
    {
        $eventsForms = $this->project->eventsForms;
        $events = array();
        foreach ($eventsForms as $event_id => $forms) {
            if(in_array($form_name, $forms))
            $events[$event_id] = $this->project->eventInfo[$event_id];
        }
        return $events;
    }

    /**
     * get a list of the scheduled Automatic Survey Invitations
     *
     * @return array of ASI: [survey_id => event_id => settings]
     */
    public function getScheduledASI()
    {
        // Get list of Automated Survey Invitations (ASIs) for a project (comes from db table redcap_surveys_scheduler)
        $surveyScheduler = new SurveyScheduler($this->project_id);
        $surveyScheduler->setSchedules(true);
        // Get the ASI list as array 
        $asi = $surveyScheduler->schedules;
        return $asi;
    }

    /**
     * print a JSON response and exit
     *
     * @param array|object $response
     * @return void
     */
	private static function printJSON($response, $status_code=200)
	{
		header('Content-Type: application/json', true, $status_code);
		print json_encode_rc( $response );
		exit;
    }
    
    /**
     * listen for remote requests
     *
     * @return void
     */
    public function listen()
    {
        if(isset($_GET[self::$API_call_prefix."-listASI"]))
        {
            return $this->listAutomatedSurveyInvitations();
        }
        if(isset($_GET[self::$API_call_prefix."-listSurveyEnabledForms"]))
        {
            return $this->listSurveyEnabledForms();
        }
        if(isset($_GET[self::$API_call_prefix."-export"]))
        {
            $filename = $_GET['filename'];
            $options = $_GET['options']; //CSV options: delimiter, enclosure, escape_char
            return $this->export($filename, $options);
        }
        if(isset($_POST[self::$API_call_prefix."-clone"]))
        {
            $from = json_decode($_POST['from']);
            $to = json_decode($_POST['to']);
            return $this->clone_asi($from, $to);
        }
        else if(isset($_POST[self::$API_call_prefix."-import"]))
        {
            return $this->import();
        }
    }
}