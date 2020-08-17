<?php


/**
 * SURVEY Class
 * Contains methods used with regard to surveys
 */
class Survey
{
	// Time period after which survey short codes will expire
	const SHORT_CODE_EXPIRE = 60; // minutes

	// Character length of survey short codes
	const SHORT_CODE_LENGTH = 5;

	// Character length of survey access codes
	const ACCESS_CODE_LENGTH = 9;

	// Character length of numeral survey access codes
	const ACCESS_CODE_NUMERAL_LENGTH = 10;

	// Character to prepend to numeral survey access codes to denote for REDCap to call them back
	const PREPEND_ACCESS_CODE_NUMERAL = "V";

	// Number of signature fields usable by e-Consent Framework
	const numEconsentSignatureFields = 5;

	// Return array of form_name and survey response status (0=partial,2=complete)
	// for a given project-record-event. $record may be a single record name or array of record names.
	public static function getResponseStatus($project_id, $record=null, $event_id=null, $returnCompletionTimestamp=false)
	{
		$surveyResponses = array();
		$sql = "select r.record, p.event_id, s.form_name, r.completion_time,
				if(r.completion_time is null,0,2) as survey_complete, r.instance
				from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r
				where s.survey_id = p.survey_id and p.participant_id = r.participant_id
				and s.project_id = $project_id and r.first_submit_time is not null";
		if ($record != null && is_array($record)) {
			$sql .= " and r.record in (".prep_implode($record).")";
		} elseif ($record != null) {
			$sql .= " and r.record = '".db_escape($record)."'";
		}
		if (is_numeric($event_id)) 	$sql .= " and p.event_id = $event_id";
		$sql .= " order by survey_complete";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$surveyResponses[$row['record']][$row['event_id']][$row['form_name']][$row['instance']] 
				= ($returnCompletionTimestamp ? $row['completion_time'] : $row['survey_complete']);
		}
		return $surveyResponses;
	}


	// Survey Notifications: Return array of surveys/users with attributes regarding email notifications for survey responses
	public static function getSurveyNotificationsList()
	{
		// First get list of all project users to fill default values for array
		$endSurveyNotify = array();
		$sql = "select if(u.ui_id is null,0,1) as hasEmail, u.user_email as email1, u.user_firstname, u.user_lastname,
				if (u.email2_verify_code is null, u.user_email2, null) as email2,
				if (u.email3_verify_code is null, u.user_email3, null) as email3,
				lower(r.username) as username from redcap_user_rights r
				left outer join redcap_user_information u on u.username = r.username
				where r.project_id = ".PROJECT_ID." order by lower(r.username)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// where 0 is default value for hasEmail
			$endSurveyNotify[$row['username']] = array('surveys'=>array(), 'hasEmail'=>$row['hasEmail'], 'email1'=>$row['email1'],
													   'email2'=>$row['email2'], 'email3'=>$row['email3'],
													   'name'=>label_decode($row['user_firstname'] == '' ? '' : trim("{$row['user_firstname']} {$row['user_lastname']}")) );
		}
		// Get list of users who have and have not been set up for survey notification via email
		$sql = "select lower(u.username) as username, a.survey_id, a.action_response
				from redcap_actions a, redcap_user_information u, redcap_user_rights r
				where a.project_id = ".PROJECT_ID." and r.project_id = a.project_id 
				and r.username = u.username and a.action_trigger = 'ENDOFSURVEY'
				and a.action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY')
				and u.ui_id = a.recipient_id order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$email_acct = ($row['action_response'] == 'EMAIL_TERTIARY' ? 3 : ($row['action_response'] == 'EMAIL_SECONDARY' ? 2 : 1));
			$endSurveyNotify[$row['username']]['surveys'][$row['survey_id']] = $email_acct;
		}
		// Return array
		return $endSurveyNotify;
	}


	// Return boolean regarding if Survey Notifications are enabled
	public static function surveyNotificationsEnabled()
	{
		// Get list of users who have and have not been set up for survey notification via email
		$sql = "select 1 from redcap_actions where project_id = ".PROJECT_ID." and action_trigger = 'ENDOFSURVEY'
				and action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY') limit 1";
		$q = db_query($sql);
		// Return boolean
		return (db_num_rows($q) > 0);
	}


	// Return boolean if Survey Queue is enabled for at least one instrument in this project
	public static function surveyQueueEnabled($project_id=null)
	{
		if (!is_numeric($project_id)) $project_id = PROJECT_ID;
		if (!is_numeric($project_id)) return false;
		$Proj = new Project($project_id);
		// Order by event then by form order
		if (!$Proj->longitudinal) {
			$sql = "select count(1) from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
					redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = ".$Proj->project_id." and m.project_id = s.project_id
					and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id and q.active = 1 and s.survey_enabled = 1";
		} else {
			$sql = "select count(1) from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
					redcap_events_arms a, redcap_events_forms f where s.survey_id = q.survey_id and s.project_id = ".$Proj->project_id." and m.project_id = s.project_id
					and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id and q.active = 1 and s.survey_enabled = 1
					and f.event_id = e.event_id and f.form_name = s.form_name";
		}
		$q = db_query($sql);
		return (db_result($q, 0) > 0);
	}


	// Return the complete Survey Queue prescription for this project
	public static function getProjectSurveyQueue($ignoreInactives=true)
	{
		global $Proj;
		$project_queue = array();
		// Order by event then by form order
		$sql = "select distinct q.* from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = ".PROJECT_ID." and m.project_id = s.project_id
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id and s.survey_enabled = 1";
		if ($ignoreInactives) $sql .= " and q.active = 1";
		$sql .= " order by a.arm_num, e.day_offset, e.descrip, m.field_order";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				$survey_id = $row['survey_id'];
				$event_id = $row['event_id'];
				unset($row['survey_id'], $row['event_id']);
				$form = $Proj->surveys[$survey_id]['form_name'];
				if (!in_array($form, $Proj->eventsForms[$event_id])) continue;
				$project_queue[$survey_id][$event_id] = $row;
			}
		}
		return $project_queue;
	}


	// Return the Survey Queue of completed/incomplete surveys for a given record.
	// If $returnTrueIfOneOrMoreItems is set to TRUE, then return boolean if one or more items exist in this record's queue.
	public static function getSurveyQueueForRecord($record, $returnTrueIfOneOrMoreItems=false)
	{
		global $Proj;
		// Add queue itmes for record to array
		$record_queue = array();
		// First, get the project's survey queue and loop to see how many are applicable for this record
		$project_queue = self::getProjectSurveyQueue();

		// Collect all survey/events where surveys have been completed for this record
		$completedSurveyEvents = array();
		$sql = "select p.event_id, p.survey_id, r.instance, r.completion_time from redcap_surveys_participants p, redcap_surveys_response r
				where r.participant_id = p.participant_id and p.survey_id in (".prep_implode(array_keys($Proj->surveys)).")
				and p.event_id in (".prep_implode(array_keys($Proj->eventInfo)).") and r.record = '" . db_escape($record) . "'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If completion time is not null, then add
			if ($row['completion_time'] != ''
				// If not a completed survey, but is a completed form, then add (this replicates ASI behavior for checking form status)
				|| SurveyScheduler::isFormStatusCompleted($row['survey_id'], $row['event_id'], $record, $row['instance'])
			) {
				$completedSurveyEvents[$row['survey_id']][$row['event_id']][$row['instance']] = true;
			}
		}

		// GET DATA for all fields used in Survey Queue conditional logic (for all queue items)
		$fields = array();
		$events = ($Proj->longitudinal) ? array() : array($Proj->firstEventId);
		// Loop through project queue for this record to get conditional logic used
		foreach ($project_queue as $survey_id=>$sattr) {
			foreach ($sattr as $event_id=>$queueItem) {
				if (trim($queueItem['condition_logic']) == '') continue;
				// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
				foreach (array_keys(getBracketedFields($queueItem['condition_logic'], true, true, false)) as $this_field)
				{
					// Check if has dot (i.e. has event name included)
					if (strpos($this_field, ".") !== false) {
						list ($this_event_name, $this_field) = explode(".", $this_field, 2);
						$events[] = $Proj->getEventIdUsingUniqueEventName($this_event_name);
					}
					// Add field to array
					$fields[] = $this_field;
				}
			}
		}
		// Add form status fields for Repeating Instrument surveys
		foreach ($Proj->getRepeatingFormsEvents() as $this_event_id=>$repeatForms) {
			if (!is_array($repeatForms)) continue;
			$events[] = $this_event_id;
			foreach (array_keys($repeatForms) as $thisRepeatForm) {
				$fields[] = $thisRepeatForm . "_complete";
			}
		}
		// Set params for getData
		$events = array_unique($events);
		$fields = array_unique($fields);
		// Retrieve data from data table since $record_data array was not passed as parameter
		$record_data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
		if (empty($record_data[$record])) $record_data = null;

		// If some events don't exist in $record_data because there are no values in data table for that event,
		// then add empty event with default values to $record_data (or else the parse will throw an exception).
		if (count($events) > count($record_data[$record])) {
			// Loop through each event	
			foreach ($events as $this_event_id) {				
				if (!isset($record_data[$record][$this_event_id])) {
					// Add all fields from $fields with defaults for this event
					foreach ($fields as $this_field) {
						// If a checkbox, set all options as "0" defaults
						if ($Proj->isCheckbox($this_field)) {
							foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
								$record_data[$record][$this_event_id][$this_field][$this_code] = "0";
							}
						}
						// If a Form Status field, give "0" default
						elseif ($this_field == $Proj->metadata[$this_field]['form_name']."_complete") {
							$record_data[$record][$this_event_id][$this_field] = "0";
						} else {
							$record_data[$record][$this_event_id][$this_field] = "";
						}
					}
				}
			}
		}
		
		// Loop through project queue for this record and reconfigure the $project_queue array
		$project_queue2 = array();
		foreach ($project_queue as $survey_id=>$sattr) {
			foreach ($sattr as $event_id=>$queueItem) {
				$project_queue2[$event_id][$survey_id] = $queueItem;
			}
			unset($project_queue[$survey_id]);
		}
		$project_queue = $project_queue2;
		unset($project_queue2);

		$eventInfoKeys = array_keys($Proj->eventInfo);
		
		// Loop through project queue for this record
		foreach ($project_queue as $event_id=>$sattr) {
			// Loop through events
			foreach ($sattr as $survey_id=>$queueItem) {
				// Get survey's form_name
				$form_name = $Proj->surveys[$survey_id]['form_name'];
				// Set instance array depending on how many instances exist for this record
				$instances = array(1);
				if (isset($record_data[$record]['repeat_instances'][$event_id][$form_name])) {
					$instances = array_merge($instances, array_keys($record_data[$record]['repeat_instances'][$event_id][$form_name]));
				}
				// If this form is not designated for this event, then go to next loop
				if (!in_array($form_name, $Proj->eventsForms[$event_id])) continue;
				// Loop through all instances
				foreach ($instances as $instance) {				
					// Should this item be displayed in the record's queue?
					$displayInQueue = self::checkConditionsOfRecordToDisplayInQueue($record, $queueItem, $completedSurveyEvents, $record_data, $event_id, $form_name, $instance,
                                                ($Proj->isRepeatingForm($event_id, $form_name) ? $form_name : null));
					// Determine if participant has completed this survey-event already
					$completedSurvey = (isset($completedSurveyEvents[$survey_id][$event_id][$instance]));
					// Check response limit (if enabled) - skip survey/event in queue if hit limit already
					if ($displayInQueue && !$completedSurvey && $Proj->surveys[$survey_id]['response_limit'] > 0 && Survey::reachedResponseLimit(PROJECT_ID, $survey_id, $event_id)) {
						$displayInQueue = false;
					}
					// If will be displayed in queue, get their survey link hash and then add to array
					if ($displayInQueue) {
						// If set flag to return boolean, then stop here and return TRUE
						if ($returnTrueIfOneOrMoreItems) return true;
						// Get the survey hash for this survey-event-record
						list ($participant_id, $hash) = self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id, false, $instance);
						// Set event number and form number for ordering purposes
                        $form_number = $Proj->forms[$form_name]['form_number'];
                        $event_number = array_search($event_id, $eventInfoKeys) + 1;
						// Add to array
						$record_queue["$event_number-$form_number-$survey_id-$event_id-$instance"] = array(
							'survey_id'=>$survey_id, 'event_id'=>$event_id, 'instance'=>$instance, 'title'=>$Proj->surveys[$survey_id]['title'],
							'participant_id'=>$participant_id, 'hash'=>$hash, 'auto_start'=>$queueItem['auto_start'], 'completed'=>($completedSurvey ? 1 : 0)
						);
					}
				}
			}
		}

        // For longitudinal projects, ensure the queue items are in correct order (by event, then by instrument)
        if ($Proj->longitudinal) {
            // Sort by event, then instrument
            natcaseksort($record_queue);
            // Now fix the array keys back
            $record_queue2 = array();
            foreach ($record_queue as $key=>$attr) {
                list ($a, $b, $c, $d, $e) = explode("-", $key);
                $record_queue2["$c-$d-$e"] = $attr;
                unset($record_queue[$key]);
            }
            $record_queue = $record_queue2;
            unset($record_queue2);
        }
		/* 
		// Loop through all surveys and add to record's queue if a survey has already been completed
		$record_queue_all = array();
		// Loop through all arms
		foreach ($Proj->events as $arm_num=>$attr) {
			// Loop through each event in this arm
			foreach (array_keys($attr['events']) as $event_id) {
				// Loop through forms designated for this event
				foreach ($Proj->eventsForms[$event_id] as $form_name) {
					// If form is enabled as a survey
					if (isset($Proj->forms[$form_name]['survey_id'])) {
						// Get survey_id
						$survey_id = $Proj->forms[$form_name]['survey_id'];
						// Set instance array depending on how many instances exist for this record
						$instances = array(1);
						if (isset($record_data[$record]['repeat_instances'][$event_id][$form_name])) {
							$instances = array_merge($instances, array_keys($record_data[$record]['repeat_instances'][$event_id][$form_name]));
						}
						// Loop through all instances
						foreach ($instances as $instance) {
							// If we have already saved this survey in our record_queue, then just copy the existing attributes
							if (isset($record_queue["$survey_id-$event_id-$instance"])) {
								// Add to array
								$record_queue_all["$survey_id-$event_id-$instance"] = $record_queue["$survey_id-$event_id-$instance"];
							} elseif (isset($completedSurveyEvents[$survey_id][$event_id][$instance])) {
								// Check if survey was completed. Only add survey to queue if completed
								// Get the survey hash for this survey-event-record
								list ($participant_id, $hash) = self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id, false, $instance);
								// Prepend to array
								$record_queue_all["$survey_id-$event_id-$instance"] = array(
									'survey_id'=>$survey_id, 'event_id'=>$event_id, 'instance'=>$instance, 'title'=>$Proj->surveys[$survey_id]['title'],
									'participant_id'=>$participant_id, 'hash'=>$hash, 'auto_start'=>'0', 'completed'=>1
								);
							}
						}
					}
				}
			}
		}
		*/		
		// If set flag to return boolean, then stop here
		if ($returnTrueIfOneOrMoreItems) return (!empty($record_queue));

		// Return the survey queue for this record
		return $record_queue;
	}


	// Display the Survey Queue of completed/incomplete surveys for a given record in HTML table format
	public static function displaySurveyQueueForRecord($record, $isSurveyAcknowledgement=false, $justCompletedSurvey=false)
	{
		global $Proj, $lang, $isAjax, $survey_queue_custom_text, $isMobileDevice, $survey_id;
		// Increase memory when processing large data arrays
		System::increaseMemory(2048);
		// Get survey queue items for this record
		$survey_queue_items = self::getSurveyQueueForRecord($record);
		if ($justCompletedSurvey) {
		    // If just finishing a survey, there's a chance that not all the rows have been populated yet in the surveys response table, which is
            // sometimes done inside getSurveyQueueForRecord(), so run getSurveyQueueForRecord() again in this situation to make sure we pick them all up.
			$survey_queue_items = self::getSurveyQueueForRecord($record);
		}
		// Obtain the survey queue hash for this record
		$survey_queue_hash = self::getRecordSurveyQueueHash($record);
		$survey_queue_link = APP_PATH_SURVEY_FULL . '?sq=' . $survey_queue_hash;
		// If empty, then return and display nothing
		if (empty($survey_queue_items)) return "";
		// Obtain participant's email address, if we have one
		$participant_emails_idents = self::getResponsesEmailsIdentifiers(array($record), $survey_id);
		foreach ($participant_emails_idents as $participant_id=>$pattr) {
			$participant_email = $pattr['email'];
		}
		// AUTO-START: If enabled for the first incomplete survey in queue, then redirect there
		if ($isSurveyAcknowledgement) {
			// Loop through queue to find the first incomplete survey
			foreach ($survey_queue_items as $queueAttr) {
				// If already completed, or if a repeating instance, then skip to next item
				if ($queueAttr['completed'] > 0 || (isset($queueAttr['instance']) && $queueAttr['instance'] > 1)) continue;
				if ($queueAttr['auto_start']) {
					// If just completed the survey, then execute the redcap_survey_complete hook
					if ($justCompletedSurvey) {
						// Store a completed survey response as a PDF in the File Repository
						if ($Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['pdf_auto_archive'] > 0) {
							self::archiveResponseAsPDF($record, $_GET['event_id'], $_GET['page'], $_GET['instance']);
						}
						// REDCap Hook injection point: Pass project/record/survey attributes to method
						$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $record);
						if (!is_numeric($group_id)) $group_id = null;
						Hooks::call('redcap_survey_complete', array(PROJECT_ID, (is_numeric($_POST['__response_id__']) ? $record : null), $_GET['page'], $_GET['event_id'], $group_id, $_GET['s'], $_POST['__response_id__'], $_GET['instance']));
						Survey::outputCustomJavascriptProjectStatusPublicSurveyCompleted(PROJECT_ID);
					}
					// Redirect to first incomplete survey in queue
					redirect(APP_PATH_SURVEY_FULL . '?s=' . $queueAttr['hash']);
				}
				// Stop looping if first incomplete survey does not have auto-start enabled
				break;
			}
		}
		// Get a count of the number of surveys in queue that have been completed already. If more than 4, then compact them.
		$numSurveysCompleted = 0;
		foreach ($survey_queue_items as $queueAttr) {
			if ($queueAttr['completed'] > 0) $numSurveysCompleted++;
		}
		// Collect all html as variable
		$html = "";
		$row_data = array();
		// Loop through items to display each as a row
		$isFirstIncompleteSurvey = true;
		$hideCompletedSurveys = ($numSurveysCompleted > 5);
		$num_survey_queue_items = count($survey_queue_items);
		$allSurveysCompleted = ($num_survey_queue_items == $numSurveysCompleted);
		$cumulRowHtml = $thisRowHtml = '';
		$rowCounter = 1;
		$instancesAllForms = array();
        $showAllSurveysCompletedText = true;
		$surveyCompleteIconText = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'font-weight:normal;vertical-align:middle;line-height:22px;font-size:12px;color:green;'), $lang['survey_507']);
		$survey_queue_items = array_values($survey_queue_items); // Re-index the survey queue array so that they key is predictable		
		foreach ($survey_queue_items as $key=>$queueAttr)
		{
			// Get survey's form_name
			$form_name = $Proj->surveys[$queueAttr['survey_id']]['form_name'];
			// If this instrument is a repeating instrument
			$isRepeatingForm = $Proj->isRepeatingForm($queueAttr['event_id'], $form_name);
			// Get list of all instances for this repeating instrument
			$pipedLabel = "";
			if ($isRepeatingForm) {
				// CUSTOM FORM LABEL PIPING: Gather field names of all custom form labels (if any)
				$pipedFormLabels = RepeatInstance::getPipedCustomRepeatingFormLabels($record, $queueAttr['event_id'], $form_name);
				// Get pre-piped custom repeating form label
				$pre_piped_label = $Proj->RepeatingFormsEvents[$queueAttr['event_id']][$form_name];
				// Pipe any custom form labels
				if ($pre_piped_label != "" && isset($pipedFormLabels[$record][$queueAttr['instance']])) {
					$pipedLabel = ": " . $pipedFormLabels[$record][$queueAttr['instance']];
				}
			}
			// Set onclick action for link/button
			$onclick = ($isAjax) ? "window.open('".APP_PATH_SURVEY_FULL."?s={$queueAttr['hash']}','_blank');"
								 : "window.location.href = '".APP_PATH_SURVEY_FULL."?s={$queueAttr['hash']}';";
			// Set button text
			$rowClass = $title_append = '';
			if ($queueAttr['completed']) {
				// If completed and more than $maxSurveysCompletedHide are completed, then hide row
				$rowClass = ($hideCompletedSurveys) ? 'hidden' : '';
				// Set image and text
				$button = $surveyCompleteIconText;
				$title_style = 'color:#aaa;';
				// If this survey has Save&Return + Edit Completed Response setting enabled, give link to open response
				if ($Proj->surveys[$queueAttr['survey_id']]['save_and_return']
					&& $Proj->surveys[$queueAttr['survey_id']]['edit_completed_response'])
				{
					$title_append .= RCView::div(array('class'=>"opacity75 nowrap $rowClass", 'onmouseover'=>"$(this).removeClass('opacity75');", 'onmouseout'=>"$(this).addClass('opacity75');", 'style'=>'float:right;margin:0 10px 0 20px;'),
										RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'style'=>'color:#000;background-color:#f0f0f0;', 'onclick'=>$onclick),
											RCView::i(array('class'=>'fas fa-pencil-alt', 'style'=>'top:2px;margin-right:5px;'), '') . 
											$lang['data_entry_174']
										)
									);
				}
			} else {
				// Set button and text
				$button = RCView::button(array('class'=>'jqbuttonmed', 'style'=>'vertical-align:middle;', 'onclick'=>$onclick), $lang['survey_504']);
				$title_style = '';
			}
			
			// If this instrument is a repeating instrument and is the last
			if ($isRepeatingForm && $Proj->surveys[$queueAttr['survey_id']]['repeat_survey_enabled']) {
				// See if the next queue item is a different event or different instrument
				if ($queueAttr['completed'] > 0 && (!isset($survey_queue_items[$key+1]) || (isset($survey_queue_items[$key+1])
						&& !($queueAttr['event_id'] == $survey_queue_items[$key+1]['event_id'] && $queueAttr['survey_id'] == $survey_queue_items[$key+1]['survey_id']))))
				{
					// Get the custom repeat btn text
					$repeat_survey_btn_text = $Proj->surveys[$queueAttr['survey_id']]['repeat_survey_btn_text'];
					// Get count of existing instances and find next instance number
					list ($instanceTotal, $instanceMax) = RepeatInstance::getRepeatFormInstanceMaxCount($record, $queueAttr['event_id'], $form_name, $Proj);
					// Get the next instance's survey url
					$repeatSurveyLink = REDCap::getSurveyLink($record, $form_name, $queueAttr['event_id'], $instanceMax + 1);
					// Add button to add a new instance
					$title_append .= RCView::div(array('class'=>"opacity75 nowrap $rowClass", 'onmouseover'=>"$(this).removeClass('opacity75');", 'onmouseout'=>"$(this).addClass('opacity75');", 'style'=>'margin:5px 0 2px;'),
										RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'style'=>'color:#000;background-color:#f0f0f0;', 'onclick'=>"window.location.href='$repeatSurveyLink';"),
											RCView::span(array('class'=>'fas fa-plus', 'style'=>'top:2px;margin-right:5px;'), '') . 
											(trim($repeat_survey_btn_text) == '' ? $lang['survey_1090'] : RCView::escape($repeat_survey_btn_text))
										)
									);
					 // If participants can add new instances at any time, then never show the text "All surveys in your queue have been completed!", which could be confusing
                    $showAllSurveysCompletedText = false;
				}
			}
			
			// Add extra row to allow participant to display all completed surveys
			if (($allSurveysCompleted && $rowCounter == $num_survey_queue_items && $hideCompletedSurveys)
				|| (!$queueAttr['completed'] && $hideCompletedSurveys && $isFirstIncompleteSurvey))
			{
				// Set flag so that this doesn't get used again
				$isFirstIncompleteSurvey = false;
				// Add extra row
				$row_data[] = 	array(
									RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;padding:2px 0;'), $surveyCompleteIconText),
									RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;line-height:22px;font-size:13px;color:#444;'),
										(($allSurveysCompleted && $showAllSurveysCompletedText)
											? RCView::span(array('style'=>'font-size:13px;color:green;font-weight:bold;'), $lang['survey_536'])
											: $numSurveysCompleted . " " . $lang['survey_534']
										) .
										RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:8px;font-size:11px;font-weight:normal;', 'onclick'=>"
											$(this).parents('tr:first').hide();
											$('table#table-survey_queue .hidden').removeClass('hidden').hide().show('fade');
										"),
											$lang['survey_535']
										)
									)
								);
			}
			// If title is blank, then use the form name instead
			if ($queueAttr['title'] == "") {
				$queueAttr['title'] = $Proj->forms[$form_name]['menu'];
			}
			// Add this row's HTML
			$row_data[] = 	array(
								RCView::div(array('class'=>"wrap $rowClass", 'style'=>'padding:2px 0;'), $button),
								RCView::div(array('class'=>"wrap $rowClass", 'style'=>$title_style.'padding:4px 0;font-size:13px;font-weight:bold;'),
									strip_tags($queueAttr['title']) .
									(!$Proj->longitudinal ? '' :
										RCView::span(array('class'=>"wrap $rowClass", 'style'=>'font-weight:normal; padding:0 10px;'),
											"&ndash;"
										) .
										RCView::span(array('class'=>"wrap $rowClass", 'style'=>'font-weight:normal;'),
											RCView::escape($Proj->eventInfo[$queueAttr['event_id']]['name_ext'])
										)
									) .
									(!$Proj->isRepeatingForm($queueAttr['event_id'], $form_name) ? '' :
										RCView::span(array('class'=>"wrap $rowClass", 'style'=>'color:#800000;'.$title_style.'font-weight:normal;padding:0 10px 0 3px;'),
											"<span style='color:#999;margin:0 6px;'>&ndash;</span>#{$queueAttr['instance']}{$pipedLabel}"
										)
									)
								) .
								$title_append
							);
			// Increment counter
			$rowCounter++;
		}
		// Survey queue header text
		$table_title = 	RCView::div(array('style'=>''),
							RCView::div(array('style'=>'float:left;color:#800000;font-size:14px;'),
								RCView::img(array('src'=>'list_red.gif', 'style'=>'vertical-align:middle;position:relative;top:2px;')) .
								RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_505'])
							) .
							RCView::div(array('style'=>'float:right;margin-right:10px;'),
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'', 'onclick'=>"simpleDialog(null,null,'survey_queue_link_dialog',600);"),
									RCView::img(array('src'=>'link.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_510'])
								)
							) .
							RCView::div(array('class'=>'wrap', 'style'=>'clear:both;padding-top:2px;font-weight:normal;font-size:12px;'),
								// Display custom survey queue text (invoke piping also) OR the default text
								($survey_queue_custom_text != ''
									?  label_decode(Piping::replaceVariablesInLabel(nl2br(decode_filter_tags($survey_queue_custom_text)), $record, $Proj->firstEventId))
									: $lang['survey_506'] . RCView::br() . $lang['survey_511']
								)
							)
						);
		// Set table headers
		$table_hdrs = array(
			array(120, $lang['dataqueries_23'], "center"),
			array(($isMobileDevice ? 255 : 655), $lang['survey_49'])
		);
		// Build table
		$html .= renderGrid("survey_queue", $table_title, ($isMobileDevice ? 400 : 800), 'auto', $table_hdrs, $row_data, true, false, false);
		// Hidden dialog div for getting link to survey queue
		$html .= RCView::div(array('id'=>'survey_queue_link_dialog', 'class'=>'simpleDialog', 'style'=>'z-index: 9999;', 'title'=>$lang['survey_510']),
					RCView::div(array('style'=>'margin:0 0 20px;'),
						$lang['survey_516']
					) .
					RCView::div(array(),
						RCView::img(array('src'=>'link.png')) .
						RCView::b($lang['survey_513']) .
						RCView::div(array('style'=>'margin:5px 0 10px 25px;'),
							RCView::text(array('readonly'=>'readonly', 'class'=>'staticInput', 'style'=>'width:90%;', 'onclick'=>"this.select();", 'value'=>$survey_queue_link))
						)
					) .
					RCView::div(array('style'=>'margin:20px 0 15px 10px;color:#999;'),
						"&mdash; ".$lang['global_46']. " &mdash;"
					) .
					RCView::div(array('style'=>'margin-bottom:20px;'),
						RCView::img(array('src'=>'email.png', 'style'=>'margin-right:1px;')) .
						RCView::b($lang['survey_514']) .
						RCView::div(array('style'=>'margin:5px 0 10px 25px;'),
							RCView::text(array('id'=>'survey_queue_email_send', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:8px;width:250px;'.($participant_email == '' ? "color:#777777;" : ''),
								'onblur'=>"if(this.value==''){this.value='".js_escape($lang['survey_515'])."';this.style.color='#777777';} if(this.value != '".js_escape($lang['survey_515'])."'){redcap_validate(this,'','','soft_typed','email')}",
								'value'=>($participant_email == '' ? $lang['survey_515'] : $participant_email),
								'onfocus'=>"if(this.value=='".js_escape($lang['survey_515'])."'){this.value='';this.style.color='#000000';}",
								'onclick'=>"if(this.value=='".js_escape($lang['survey_515'])."'){this.value='';this.style.color='#000000';}"
							)) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'', 'onclick'=>"
								var emailfld = document.getElementById('survey_queue_email_send');
								if (emailfld.value == '".js_escape($lang['survey_515'])."') {
									simpleDialog('".js_escape($lang['survey_515'])."',null,null,null,'document.getElementById(\'survey_queue_email_send\').focus();');
								} else if (redcap_validate(emailfld, '', '', '', 'email')) {
									$.post('$survey_queue_link',{ to: emailfld.value },function(data){
										if (data != '1') {
											alert(woops);
										} else {
											$('#survey_queue_link_dialog').dialog('close');
											simpleDialog('".js_escape($lang['survey_225'])." '+emailfld.value+'".js_escape($lang['period'])."','".js_escape($lang['survey_524'])."');
										}
									});
								}
							"), $lang['survey_180']) .
							($participant_email != '' ? '' :
								RCView::div(array('style'=>'color:#800000;font-size:11px;margin:5px 10px 0;'), '* '.$lang['survey_125'])
							)
						)
					)
				 );
		// If ajax call, then add a Close button to close the dialog
		if ($isAjax) {
			$html .= RCView::div(array('style'=>'text-align:right;background-color:#fff;padding:8px 15px;'),
						RCView::button(array('class'=>'jqbutton', 'onclick'=>"$('#survey_queue_corner_dialog').hide();$('#overlay').hide();"),
							RCView::span(array('style'=>'line-height:22px;margin:5px;color:#555;'), $lang['calendar_popup_01'])
						)
					 );
		}
		// If this is the Acknowledgement section of a survey (and not the Survey Queue page itself),
		// then change the URL to the survey queue link, in case they decide to bookmark the page.
		if ($isSurveyAcknowledgement && !$isAjax) {
			$html .=   "<script type='text/javascript'>
						modifyURL('$survey_queue_link');
						</script>";
		}
		// Return all html
		return RCView::div(array(), $html);
	}


	// Get the survey title using the survey_id
	public static function getSurveyTitleFromId($survey_id=null)
	{
		// Validate survey id
		if (!is_numeric($survey_id)) return null;
		$sql = "select title from redcap_surveys where survey_id = $survey_id";
		$q = db_query($sql);
		if (db_num_rows($q) == 0) return null;
		return label_decode(db_result($q, 0));
	}


	// Get the Survey Queue hash for this record. If doesn't exist yet, then generate it.
	// Use $hashExistsOveride=true to skip the initial check that the hash exists for this record if you know it does not.
	public static function getRecordSurveyQueueHash($record=null, $hashExistsOveride=false, $project_id=null)
	{
		// Validate record name
		if ($record == '') return null;
		if (!is_numeric($project_id)) $project_id = PROJECT_ID;
		if (!is_numeric($project_id)) return false;
		$Proj = new Project($project_id);
		// Default value
		$hashExists = false;
		// Check if record already has a hash
		if (!$hashExistsOveride) {
			$sql = "select hash from redcap_surveys_queue_hashes where project_id = ".$Proj->project_id."
					and record = '".db_escape($record)."' limit 1";
			$q = db_query($sql);
			$hashExists = (db_num_rows($q) > 0);
		}
		// If hash exists, then get it from table
		if ($hashExists) {
			// Hash already exists
			$hash = db_result($q, 0);
		} else {
			// Hash does NOT exist, so generate a unique one
			do {
				// Generate a new random hash
				$hash = generateRandomHash(10);
				// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
				$sql = "select hash from redcap_surveys_queue_hashes where hash = '$hash' limit 1";
				$hashExists = (db_num_rows(db_query($sql)) > 0);
			} while ($hashExists);
			// Add newly generated hash for record
			$sql = "insert into redcap_surveys_queue_hashes (project_id, record, hash)
					values (".$Proj->project_id.", '".db_escape($record)."', '$hash')";
			if (!db_query($sql) && $hashExistsOveride) {
				// The override failed, so apparently the hash DOES exist, so get it
				$hash = self::getRecordSurveyQueueHash($record, false, $project_id);
			}
		}
		// Return the hash
		return $hash;
	}


	// Get the Survey Queue hash for LOTS of records in an array.
	// Return hashes as array values with record name as array key.
	public static function getRecordSurveyQueueHashBulk($records=array())
	{
		// Put hashes in array
		$hashes = array();
		// Get all existing hashes
		$sql = "select record, hash from redcap_surveys_queue_hashes where project_id = ".PROJECT_ID."
				and record in (".prep_implode($records).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$hashes[$row['record']] = $row['hash'];
		}
		// For those without a hash, go generate one
		foreach (array_diff($records, array_keys($hashes)) as $this_record) {
			if ($this_record == '') continue;
			$hashes[$this_record] = self::getRecordSurveyQueueHash($this_record);
		}
		// Order by record
		natcaseksort($hashes);
		// Return hashes
		return $hashes;
	}


	// Determine if this survey-event should be displayed in the Survey Queue for this record.
	// Parameter $completedSurveyEvents can be optionally passed, in which it contains the survey_id (first level key)
	// and event_id (second level key) of all completed survey responses for this record.
	public function checkConditionsOfRecordToDisplayInQueue($record, $queueItem, $completedSurveyEvents=null, $record_data=null, $event_id=null, $form_name=null, $repeat_instance=1, $repeat_instrument=null)
	{		
		// If conditional upon survey completion, check if completed survey
		$conditionsPassedSurveyComplete = ($queueItem['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if (is_numeric($queueItem['condition_surveycomplete_survey_id']) && is_numeric($queueItem['condition_surveycomplete_event_id']))
		{
			// Is it a completed response?
			if (is_array($completedSurveyEvents)) {
				$conditionsPassedSurveyComplete = (isset($completedSurveyEvents[$queueItem['condition_surveycomplete_survey_id']][$queueItem['condition_surveycomplete_event_id']]));
			} else {
				$conditionsPassedSurveyComplete = Survey::isResponseCompleted($queueItem['condition_surveycomplete_survey_id'], $record, $queueItem['condition_surveycomplete_event_id'], $repeat_instance);
			}
			// If not listed as a completed response, then also check Form Status (if entered as plain record data instead of as response), just in case
			if (!$conditionsPassedSurveyComplete) {
				$conditionsPassedSurveyComplete = SurveyScheduler::isFormStatusCompleted($queueItem['condition_surveycomplete_survey_id'], $queueItem['condition_surveycomplete_event_id'], $record, $repeat_instance);
			}
		}
		// If conditional upon custom logic
		$conditionsPassedLogic = ($queueItem['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if ($queueItem['condition_logic'] != ''
			// If using AND and $conditionsPassedSurveyComplete is false, then no need to waste time checking evaluateLogicSingleRecord().
			// If using OR and $conditionsPassedSurveyComplete is true, then no need to waste time checking evaluateLogicSingleRecord().
			&& (($queueItem['condition_andor'] == 'OR' && !$conditionsPassedSurveyComplete)
				|| ($queueItem['condition_andor'] == 'AND' && $conditionsPassedSurveyComplete)))
		{
		    // Replace any Smart Variables, if needed
            if (Piping::containsSpecialTags($queueItem['condition_logic'])) {
                $queueItem['condition_logic'] = Piping::replaceVariablesInLabel($queueItem['condition_logic'], $record, $event_id, $repeat_instance, array(),
                            false, PROJECT_ID, false, $repeat_instrument, 1, false, false, $form_name);
            }
			// Does the logic evaluate as true?
			$conditionsPassedLogic = LogicTester::evaluateLogicSingleRecord($queueItem['condition_logic'], $record, $record_data, null, $repeat_instance, $repeat_instrument);
		}
		// Check pass/fail values and return boolean if record is ready to have its invitation for this survey/event
		if ($queueItem['condition_andor'] == 'OR') {
			// OR
			return ($conditionsPassedSurveyComplete || $conditionsPassedLogic);
		} else {
			// AND (default)
			return ($conditionsPassedSurveyComplete && $conditionsPassedLogic);
		}
	}

	// Validate and clean the survey queue hash, while also returning the record name to which it belongs
	public static function checkSurveyQueueHash($survey_queue_hash)
	{
		global $lang, $project_language;
		// Language: Call the correct language file for this project (default to English)
		if (empty($lang)) {
			$lang = Language::getLanguage($project_language);
		}
		// Trim hash, just in case
		$survey_queue_hash = trim($survey_queue_hash);
		// Ensure integrity of hash, and if extra characters have been added to hash somehow, chop them off.
		if (strlen($survey_queue_hash) > 10) {
			$survey_queue_hash = substr($survey_queue_hash, 0, 10);
		}
		// Check if hash is valid
		$sql = "select project_id, record from redcap_surveys_queue_hashes
				where hash = '".db_escape($survey_queue_hash)."' limit 1";
		$q = db_query($sql);
		$hashValid = (db_num_rows($q) > 0);
		// If the hash is valid, then return project_id and record, else stop and give error message
		if ($hashValid) {
			$row = db_fetch_assoc($q);
			return array($row['project_id'], $row['record']);
		} else {
			Survey::exitSurvey($lang['survey_508'], true, $lang['survey_509']);
		}
	}


	// Validate and clean the survey hash, while also returning if a legacy hash
	public static function checkSurveyHash()
	{
		global $lang, $project_language;
		// Obtain hash from GET or POST
		$hash = isset($_GET['s']) ? $_GET['s'] : (isset($_POST['s']) ? $_POST['s'] : "");
		// If could not find hash, try as legacy hash
		if (empty($hash)) {
			$hash = isset($_GET['hash']) ? $_GET['hash'] : (isset($_POST['hash']) ? $_POST['hash'] : "");
		}
		// Trim hash, just in case
		$hash = trim($hash);
		// Language: Call the correct language file for this project (default to English)
		if (empty($lang)) {
			$lang = Language::getLanguage($project_language);
		}
		// Ensure integrity of hash, and if extra characters have been added to hash somehow, chop them off.
		$hash_length = strlen($hash);
		if ($hash_length >= 4 && $hash_length <= 10 && preg_match("/^([a-z0-9]+)$/i", $hash)) {
			$legacy = false;
		} elseif ($hash_length > 10 && $hash_length < 32 && preg_match("/^([a-z0-9]+)$/i", $hash)) {
			$hash = substr($hash, 0, 10);
			$legacy = false;
		} elseif ($hash_length >= 32 && preg_match("/^([a-z0-9]+)$/", $hash)) {
			$hash = substr($hash, 0, 32);
			$legacy = true;
		} elseif (empty($hash)) {
			Survey::exitSurvey("{$lang['survey_11']}
						<a href='javascript:;' style='font-size:16px;color:#800000;' onclick=\"
							window.location.href = app_path_webroot+'Surveys/create_survey.php?pid='+getParameterByName('pid',true)+'&view=showform';
						\">{$lang['survey_12']}</a> {$lang['survey_13']}");
		} else {
			Survey::exitSurvey($lang['survey_14']);
		}
		// If legacy hash, then retrieve newer hash to return
		if ($legacy)
		{
			$q = db_query("select hash from redcap_surveys_participants where legacy_hash = '".db_escape($hash)."'");
			if (db_num_rows($q) > 0) {
				$hash = db_result($q, 0);
			} else {
				Survey::exitSurvey($lang['survey_14']);
			}
		}
		// Return hash
		return $hash;
	}


	// Repeating forms/events: Obtain the instance number of a given participant_id in participants table
	// (assuming this is NOT a public survey). Return default of '1' if no rows returned.
	public static function getInstanceNumFromParticipantId($participant_id)
	{
		// Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select r.instance from redcap_surveys_response r, redcap_surveys_participants p 
				where p.participant_id = '".db_escape($participant_id)."' and p.participant_id = r.participant_id 
				and p.participant_email is not null limit 1";
		$q = db_query($sql);
		return db_num_rows($q) ? db_result($q, 0) : '1';
	}


	// Obtain the record name of a given participant_id in participants table
	// (assuming this is NOT a public survey). Return default of '1' if no rows returned.
	public static function getRecordFromParticipantId($participant_id)
	{
		// Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select r.record from redcap_surveys_response r, redcap_surveys_participants p 
				where p.participant_id = '".db_escape($participant_id)."' and p.participant_id = r.participant_id 
				and p.participant_email is not null limit 1";
		$q = db_query($sql);
		return db_num_rows($q) ? db_result($q, 0) : false;
	}


	// Return the survey link for a given participant_id
	public static function getSurveyLinkFromParticipantId($participant_id)
	{
		// Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select hash from redcap_surveys_participants where participant_id = '".db_escape($participant_id)."'";
		$q = db_query($sql);
		return db_num_rows($q) ? APP_PATH_SURVEY_FULL . '?s=' . db_result($q, 0) : '';
	}


	// Return the survey_id for a given participant_id
	public static function getSurveyIdFromParticipantId($participant_id)
	{
		// Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select survey_id from redcap_surveys_participants where participant_id = '".db_escape($participant_id)."'";
		$q = db_query($sql);
		return db_num_rows($q) ? db_result($q, 0) : '';
	}
	

	// Pull survey values from tables and set as global variables
	public static function setSurveyVals($hash)
	{
		global $lang;
		// Ensure that hash exists for a real form and a real event. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select s.*, h.* from redcap_surveys s, redcap_surveys_participants h, redcap_metadata m
                where h.hash = '".db_escape($hash)."' and s.survey_id = h.survey_id and m.project_id = s.project_id 
                and m.form_name = s.form_name and h.event_id is not null limit 1";
		$q = db_query($sql);
		if (!$q || !db_num_rows($q)) {
			Survey::exitSurvey($lang['survey_14']);
		}
		foreach (db_fetch_assoc($q) as $key => $value)
		{
			if ($value === null) {
				$GLOBALS[$key] = $value;
			} else {
				// Replace non-break spaces because they cause issues with html_entity_decode()
				$value = str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $value);
				// Don't decode if cannnot detect encoding
				if (function_exists('mb_detect_encoding') && (
					(mb_detect_encoding($value) == 'UTF-8' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === false)
					|| (mb_detect_encoding($value) == 'ASCII' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === 'UTF-8')
				)) {
					$GLOBALS[$key] = trim($value);
				} else {
					$GLOBALS[$key] = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
				}
			}
		}
	}


	// Returns array of emails, identifiers, phone numbers, and delivery preference for a list of records
	public static function getResponsesEmailsIdentifiers($records=array(), $survey_id=null, $project_id_override=null, $returnEmailsOnly=false)
	{
		if (defined("PROJECT_ID") && !is_numeric($project_id_override)) {
            $project_id_override = PROJECT_ID;
		}
        $Proj = new Project($project_id_override);
        $survey_email_participant_field = $Proj->project['survey_email_participant_field'];
        $survey_phone_participant_field = $Proj->project['survey_phone_participant_field'];
        $twilio_enabled = $Proj->project['twilio_enabled'];

		// If pass in empty array of records, pass back empty array
		if (empty($records)) return array();

		// Get the first event_id of every Arm and place in array
		$firstEventIds = array();
		foreach ($Proj->events as $this_arm_num=>$arm_attr) {
			$arm_events_keys = array_keys($arm_attr['events']);
			$firstEventIds[] = print_r(array_shift($arm_events_keys), true);
		}

		// Create an array to return with participant_id as key and attributes as subarray
		$responseAttributes = array();
		// Pre-fill with all records passed in first
		foreach ($records as $record) {
			if ($record == '') continue;
			$record = label_decode($record);
			if ($returnEmailsOnly) {
				$responseAttributes[$record] = array('email' => '');
            } else {
				$responseAttributes[$record] = array('email' => '', 'identifier' => '', 'phone' => '', 'delivery_preference' => 'EMAIL');
			}
		}

		## GET EMAILS FROM INITIAL SURVEY'S PARTICIPANT LIST (if there is an initial survey)
		if ($Proj->firstFormSurveyId != null)
		{
			// Create record list to query participant table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($records as $record) {
				if ($record == '') continue;
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the original email from first survey's participant list
			$sql = "select r.record, p.participant_email, p.participant_identifier, p.participant_phone, p.delivery_preference
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
					where s.project_id = ".$Proj->project_id." and p.survey_id = s.survey_id and p.participant_id = r.participant_id
					and r.record in (".prep_implode($partRecordsSql).") and s.form_name = '".$Proj->firstForm."'
					and p.event_id in (".prep_implode($firstEventIds).") and p.participant_email is not null";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$row['record'] = label_decode($row['record']);
				if ($row['participant_email'] != '') {
					$responseAttributes[$row['record']]['email'] = label_decode($row['participant_email']);
				}
				if ($returnEmailsOnly) continue;
				if ($row['participant_identifier'] != '') {
					$responseAttributes[$row['record']]['identifier'] = strip_tags(label_decode($row['participant_identifier']));
				}
				if ($row['participant_phone'] != '') {
					$responseAttributes[$row['record']]['phone'] = $row['participant_phone'];
				}
				if ($row['delivery_preference'] != '') {
					$responseAttributes[$row['record']]['delivery_preference'] = $row['delivery_preference'];
				}
			}
		}
		// If using Twilio and first instrument is not a survey, then re-check (and possibly fix) delivery pref
		// (since it is set for EACH participant_id, which can have different values)
		elseif ($Proj->firstFormSurveyId == null && $twilio_enabled && !$returnEmailsOnly)
		{
			// Create record list to query participant table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($records as $record) {
				if ($record == '') continue;
				$partRecordsSql[] = label_decode($record);
			}
			// Obtain delivery pref for records in case they are out of sync and incorrect
			$sql = "select r.record, p.participant_id, p.delivery_preference
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
					where s.project_id = ".$Proj->project_id." and p.survey_id = s.survey_id and p.participant_id = r.participant_id
					and r.record in (".prep_implode($partRecordsSql).") and s.form_name != '".$Proj->firstForm."'
					and p.participant_email is not null";
			$q = db_query($sql);
			$blankDelivPref = $changeDelivPref = array();
			while ($row = db_fetch_assoc($q)) {
				$row['record'] = label_decode($row['record']);
				if ($row['delivery_preference'] != '' && $row['delivery_preference'] != 'EMAIL') {
					$responseAttributes[$row['record']]['delivery_preference'] = $row['delivery_preference'];
					$changeDelivPref[$row['record']] = $row['delivery_preference'];
				} elseif ($row['delivery_preference'] == '') {
					$blankDelivPref[$row['record']][] = $row['participant_id'];
				}
			}
			// Loop through participants where we need to retroactively fix their delivery pref
			foreach ($blankDelivPref as $this_record=>$participant_ids) {
				if (isset($changeDelivPref[$this_record])) {
					// Change their preference in the participants table
					$sql = "update redcap_surveys_participants set delivery_preference = '".db_escape($changeDelivPref[$this_record])."'
							where participant_id in (".prep_implode($participant_ids).")";
					$q = db_query($sql);
				}
			}

		}

		## GET ANY REMAINING MISSING EMAILS FROM SPECIAL EMAIL FIELD IN REDCAP_PROJECTS TABLE
		$this_survey_email_participant_field = $survey_email_participant_field;
		if (isset($Proj->surveys[$survey_id]) && $Proj->surveys[$survey_id]['email_participant_field'] != '' && isset($Proj->metadata[$Proj->surveys[$survey_id]['email_participant_field']])) {
			$this_survey_email_participant_field = $Proj->surveys[$survey_id]['email_participant_field'];
		}
		if ($this_survey_email_participant_field != '')
		{
			// Create record list of responses w/o emails to query data table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($responseAttributes as $record=>$attr) {
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the email value from the data table
			$sql = "select record, value from redcap_data where project_id = ".$Proj->project_id."
					and field_name = '".db_escape($this_survey_email_participant_field)."'
					and record in (".prep_implode($partRecordsSql).")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Skip if blank
				if ($row['value'] == '') continue;
				// Trim and decode, just in case
				$email = trim(label_decode($row['value']));
				// Don't use it unless it's a valid email address
				if (isEmail($email)) {
					$responseAttributes[label_decode($row['record'])]['email'] = $email;
				}
			}
		}

		## GET ANY REMAINING MISSING PHONE NUMBERS FROM SPECIAL PHONE FIELD IN REDCAP_PROJECTS TABLE
		if ($survey_phone_participant_field != '' && !$returnEmailsOnly)
		{
			// Create record list of responses w/o emails to query data table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($responseAttributes as $record=>$attr) {
				if ($attr['phone'] != '') continue;
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the phone value from the data table
			$sql = "select record, value from redcap_data where project_id = ".$Proj->project_id."
					and field_name = '".db_escape($survey_phone_participant_field)."'
					and record in (".prep_implode($partRecordsSql).") and value != ''";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$phone = preg_replace("/[^0-9]/", "", label_decode($row['value']));
				// Don't use it unless it's a valid phone number
				if ($phone != '') {
					$responseAttributes[label_decode($row['record'])]['phone'] = $phone;
				}
			}
		}

		// Return array
		return $responseAttributes;
	}


	// Display the Survey Queue setup table in HTML table format
	public static function displaySurveyQueueSetupTable()
	{
		global $lang, $longitudinal, $Proj, $survey_queue_custom_text;

		// Get this project's currently saved queue
		$projectSurveyQueue = self::getProjectSurveyQueue(false);
		// Get array of all records
		$recordsArray = Records::getRecordsAsArray($Proj->project_id, true, 100);

		// Create list of all surveys/event instances as array to use for looping below and also to feed a drop-down
		$surveyEvents = array();
		$surveyDD = array(''=>'--- '.$lang['survey_404'].' ---');
		// Loop through all events (even for classic)
		foreach ($Proj->eventsForms as $this_event_id=>$forms)
		{
			// Go through each form and see if it's a survey
			foreach ($forms as $form)
			{
				// Get survey_id
				$this_survey_id = isset($Proj->forms[$form]['survey_id']) ? $Proj->forms[$form]['survey_id'] : null;
				// Only display surveys, so ignore if does not have survey_id
				if (!is_numeric($this_survey_id)) continue;
				// Add form, event_id, and survey_id to drop-down array
				$title = strip_tags($Proj->surveys[$this_survey_id]['title']);
				$event = $Proj->eventInfo[$this_event_id]['name_ext'];
                // If has no survey title, then substitute it with form label
                if (trim($title) == '') $title = strip_tags($Proj->forms[$form]['menu']);
                // Add survey to array
                $surveyDD["$this_survey_id-$this_event_id"] = "\"$title\"" . ($longitudinal ? " - $event" : "");
				// Add values to array
				$surveyEvents[] = array('event_id'=>$this_event_id, 'event_name'=>$event, 'form'=>$form,
										'survey_id'=>$this_survey_id, 'survey_title'=>$title);
			}
		}
		// Loop through surveys-events
		$hdrs = RCView::tr(array(),
					RCView::td(array('class'=>'header', 'style'=>'width:75px;text-align:center;font-size:11px;'), $lang['survey_430']) .
					RCView::td(array('class'=>'header'), $lang['survey_49']) .
					RCView::td(array('class'=>'header', 'style'=>'width:400px;'), $lang['survey_526']) .
					RCView::td(array('class'=>'header', 'style'=>'width:42px;text-align:center;font-size:11px;line-height:13px;'), $lang['survey_529'])
				);
		$rows = '';
		foreach ($Proj->eventsForms as $event_id=>$these_forms) {
			// Loop through forms
			$alreadyDisplayedEventHdr = false;
			foreach ($these_forms as $form_name) {
				// If form is not enabled as a survey, then skip it
				if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
				// Get survey_id
				$survey_id = $Proj->forms[$form_name]['survey_id'];
				// Skip the first instrument survey since it is naturally not included in the queue till after it is completed
				// if ($survey_id == $Proj->firstFormSurveyId) continue; // In 8.0.2+, now allow the first instrument to be displayed in the survey queue
				// If longitudinal, display Event Name as header
				if ($longitudinal && !$alreadyDisplayedEventHdr) {
					$rows .= RCView::tr(array(),
								RCView::td(array('class'=>'header blue', 'colspan'=>'4', 'style'=>'padding:3px 6px;font-weight:bold;'),
									$Proj->eventInfo[$event_id]['name_ext']
								)
							);
					$alreadyDisplayedEventHdr = true;
				}
				// Set form+event+arm label
				$form_event_label = $Proj->forms[$form_name]['menu'] . (!$longitudinal ? '' : " (" . $Proj->eventInfo[$event_id]['name_ext'] . ")");
				// Get any saved attributes for this survey/event
				if (isset($projectSurveyQueue[$survey_id][$event_id])) {
					$queue_item = $projectSurveyQueue[$survey_id][$event_id];
					$conditionSurveyActivatedChecked = ($queue_item['active']) ? 'checked' : '';
					$conditionSurveyActivatedDisabled = '';
					$conditionSurveyCompChecked = (is_numeric($queue_item['condition_surveycomplete_survey_id']) && is_numeric($queue_item['condition_surveycomplete_event_id'])) ? 'checked' : '';
					$conditionSurveyCompSelected = (is_numeric($queue_item['condition_surveycomplete_survey_id']) && is_numeric($queue_item['condition_surveycomplete_event_id'])) ? $queue_item['condition_surveycomplete_survey_id'].'-'.$queue_item['condition_surveycomplete_event_id'] : '';
					$conditionAndOr = ($queue_item['condition_andor'] == 'OR') ? 'OR' : 'AND';
					$conditionLogicChecked = (trim($queue_item['condition_logic']) == '') ? '' : 'checked';
					$conditionLogic = $queue_item['condition_logic'];
					$conditionAutoStartChecked = ($queue_item['auto_start']) ? 'checked' : '';
					$queue_item_class = $queue_item_class_firstcell = 'darkgreen';
					$queue_item_active_flag = 'active';
					$queue_item_active_flag_value = '1';
					$queue_item_icon_enabled_style = '';
					$queue_item_icon_disabled_style = 'display:none;';
				} else {
					$conditionSurveyActivatedChecked = $conditionSurveyCompChecked = $conditionSurveyCompSelected = '';
					$conditionAndOr = $conditionLogicChecked = $conditionLogic = $conditionAutoStartChecked = '';
					$queue_item_class_firstcell = $queue_item_active_flag = $queue_item_active_flag_value = '';
					$queue_item_class = 'opacity35';
					$queue_item_icon_enabled_style = 'display:none;';
					$queue_item_icon_disabled_style = '';
					$conditionSurveyActivatedDisabled = 'disabled';
				}
				// Set survey title for this row
				$title = $Proj->surveys[$survey_id]['title'];
				// If has no survey title, then substitute it with form label
				if (trim($title) == '') $title = $Proj->forms[$form_name]['menu'];
				// Render row
				$rows .= RCView::tr(array('id'=>"sqtr-$survey_id-$event_id", $queue_item_active_flag=>$queue_item_active_flag_value),
							RCView::td(array('class'=>"data $queue_item_class_firstcell", 'valign'=>'top', 'style'=>'text-align:center;padding:6px;padding-top:10px;'),
								// "Enabled" text/icon
								RCView::div(array('id'=>"div_sq_icon_enabled-$survey_id-$event_id", 'style'=>$queue_item_icon_enabled_style),
									RCView::img(array('src'=>'checkbox_checked.png')) .
									RCView::div(array('style'=>'color:green;'), $lang['survey_544']) .
									RCView::div(array('style'=>'padding:20px 0 0;'),
										RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:9px;font-family:tahoma;',
											'onclick'=>"surveyQueueSetupActivate(0, $survey_id, $event_id);return false;"),
											$lang['survey_546']
										)
									)
								) .
								// "Not enabled" text/icon
								RCView::div(array('id'=>"div_sq_icon_disabled-$survey_id-$event_id", 'style'=>$queue_item_icon_disabled_style),
									RCView::img(array('src'=>'checkbox_cross.png')) .
									RCView::div(array('style'=>'color:#F47F6C;'), $lang['survey_543']) .
									RCView::div(array('style'=>'padding:20px 0 0;'),
										RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:9px;font-family:tahoma;',
											'onclick'=>"surveyQueueSetupActivate(1, $survey_id, $event_id);return false;"),
											$lang['survey_547']
										)
									)
								) .
								// Hidden checkbox to denote activation
								RCView::checkbox(array('name'=>"sqactive-$survey_id-$event_id", 'id'=>"sqactive-$survey_id-$event_id", 'class'=>'hidden', $conditionSurveyActivatedChecked=>$conditionSurveyActivatedChecked))
							) .
							RCView::td(array('class'=>"data $queue_item_class_firstcell", 'style'=>'padding:6px;', 'valign'=>'top'),
								RCView::div(array('style'=>'padding:3px 8px 8px 2px;font-size:13px;'),
									// Survey title
									RCView::span(array('style'=>'font-size:13px;'),
										'"'.RCView::b(RCView::escape(strip_tags($title))).'"'
									) .
									// Event name (if longitudinal)
									(!$longitudinal ? '' :
										RCView::span(array(),
											" &nbsp;-&nbsp; ".RCView::escape($Proj->eventInfo[$event_id]['name_ext'])
										)
									)
								)
							) .
							RCView::td(array('class'=>"data $queue_item_class", 'style'=>'padding:6px 6px 3px;font-size:12px;'),
								// When survey is completed
								RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;padding:1px 0;'),
									RCView::checkbox(array('name'=>"sqcondoption-surveycomplete-$survey_id-$event_id",'id'=>"sqcondoption-surveycomplete-$survey_id-$event_id",$conditionSurveyCompChecked=>$conditionSurveyCompChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled)) .
									$lang['survey_419'] .
									RCView::br() .
									// Drop-down of surveys/events
									RCView::select(array('name'=>"sqcondoption-surveycompleteids-$survey_id-$event_id",'id'=>"sqcondoption-surveycompleteids-$survey_id-$event_id",'class'=>'x-form-text x-form-field','style'=>'font-size:11px;width:100%;max-width:360px;', $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled,
										'onchange'=>"$('#sqcondoption-surveycomplete-$survey_id-$event_id').prop('checked', (this.value.length > 0) );  if (this.value.length > 0) hasDependentSurveyEvent(this);"), $surveyDD, $conditionSurveyCompSelected, 200)
								) .
								// AND/OR drop-down list for conditions
								RCView::div(array('style'=>'padding:2px 0 1px;'),
									RCView::select(array('name'=>"sqcondoption-andor-$survey_id-$event_id",'id'=>"sqcondoption-andor-$survey_id-$event_id",'style'=>'font-size:11px;', $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled), array('AND'=>$lang['global_87'],'OR'=>$lang['global_46']), $conditionAndOr)
								) .
								// When logic becomes true
								RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;'),
									RCView::checkbox(array('name'=>"sqcondoption-logic-$survey_id-$event_id",'id'=>"sqcondoption-logic-$survey_id-$event_id",$conditionLogicChecked=>$conditionLogicChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled)) .
									$lang['survey_420'] .
									RCView::a(array('href'=>'javascript:;','class'=>'opacity65','style'=>'margin-left:50px;text-decoration:underline;font-size:10px;','onclick'=>"helpPopup('ss69')"), $lang['survey_527']) .
									RCView::br() .
									RCView::textarea(array('name'=>"sqcondlogic-$survey_id-$event_id",'id'=>"sqcondlogic-$survey_id-$event_id",'class'=>'x-form-field', 'style'=>'line-height:12px;font-size:11px;width:100%;max-width:350px;height:50px;resize:auto;','onkeydown' => 'logicSuggestSearchTip(this, event);', 'onblur'=>"var val = this; setTimeout(function() { logicHideSearchTip(val); this.value=trim(val.value); if(val.value.length > 0) { $('#sqcondoption-logic-$survey_id-$event_id').prop('checked',true); } if(!checkLogicErrors(val.value,1,true)){validate_auto_invite_logic($(val));} }, 0);"), $conditionLogic) .
                                                                        logicAdd("sqcondlogic-$survey_id-$event_id") .
									RCView::br() .
									RCView::span(array('style'=>'font-family:tahoma;font-size:10px;color:#888;'),
										($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")") .
									RCView::br() .
									RCView::table(array('style'=>'padding: 0px; border: 0; font-size: 12px; margin-left: 25px; margin-right: 15px; width: 310px'),
										RCView::tr(array('style'=>'padding: 0px; border: 0;'),
											RCView::td(array('style' => 'color: green; font-weight: bold; padding: 0px; text-align: left; vertical-align: middle; border: 0; height: 20px;', 'id' => "sqcondlogic-$survey_id-$event_id"."_Ok"), "&nbsp;")
										) .
										RCView::tr(array('style'=>'padding: 0px; border: 0;'),
											RCView::td(array('style'=>'border: 0; padding: 0px; text-align: left;'),
												"<span class='logicTesterRecordDropdownLabel'>{$lang['design_705']}</span> ".
												RCView::select(array('style'=>'font-size:11px !important;color:#333;max-width: 220px;', 'onchange'=>'var circle="'.APP_PATH_IMAGES.'progress_circle.gif"; if (this.value !== "") $("#sqcondlogic-'.$survey_id.'-'.$event_id.'_res").html("<img src="+circle+">"); else $("#sqcondlogic-'.$survey_id.'-'.$event_id.'_res").html(""); logicCheck($("#sqcondlogic-'.$survey_id.'-'.$event_id.'"), "branching", '.($longitudinal ? 'true' : 'false').', "", this.value, "'.js_escape2($lang['design_706']).'", "'.js_escape2($lang['design_707']).'", "'.js_escape2($lang['design_713']).'", ["'.js_escape2($lang['design_714']).'", "'.js_escape2($lang['design_715']).'", "'.js_escape2($lang['design_708']).'"], "sqcondlogic-'.$survey_id.'-'.$event_id.'");'), $recordsArray)
											)
										) .
										RCView::tr(array('style'=>'padding: 0px; border: 0;'),
											RCView::td(array('style'=>'border: 0; padding: 0px; text-align: left;'), 
												RCView::span(array('id' => "sqcondlogic-".$survey_id."-".$event_id."_res", 'style' => 'color: green; font-weight: bold;'), "")
											)
										)
									)

									)
								)
							) .
							RCView::td(array('class'=>"data $queue_item_class", 'valign'=>'top', 'style'=>'text-align:center;padding:6px;padding-top:10px;'),
								// Auto start?
								RCView::checkbox(array('name'=>"ssautostart-$survey_id-$event_id", 'id'=>"ssautostart-$survey_id-$event_id", $conditionAutoStartChecked=>$conditionAutoStartChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled))
							)
						);
			}
		}

		// HTML
		$html = '';

		// Instructions
		$html .= RCView::div(array('style'=>'margin:0 0 5px;'.($Proj->firstFormSurveyId != null ? '' : 'margin-bottom:20px;')),
					$lang['survey_531'] . " " .
					RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"$(this).hide();$('#survey_queue_form_hidden_instr').show();fitDialog($('#surveyQueueSetupDialog'));"),
						$lang['global_58']) .
					RCView::span(array('id'=>'survey_queue_form_hidden_instr', 'style'=>'display:none;'),
						$lang['survey_542'])
				);

		// If custom text already exists, then display it's textarea box
		$survey_queue_custom_text_style = ($survey_queue_custom_text == '') ? 'display:none;' : '';
		$survey_queue_custom_text_add_style = ($survey_queue_custom_text == '') ? '' : 'display:none;';

		// Add table/form html if there is something to display
		if (strlen($rows) > 0)
		{
			// Add table/form html
			$html .= "<form id='survey_queue_form'>" .
						// Header
						RCView::div(array('id'=>'div_survey_queue_custom_text_link', 'style'=>'padding:0 0 20px 4px;'.$survey_queue_custom_text_add_style),
							RCView::img(array('src'=>'add.png')) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;color:green;', 'onclick'=>"$('#div_survey_queue_custom_text_link').hide();$('#div_survey_queue_custom_text').show('fade');"),
								$lang['survey_541']
							)
						) .
						// Custom text (optional)
						RCView::div(array('id'=>'div_survey_queue_custom_text', 'class'=>'data', 'style'=>'padding:8px 8px 4px;margin:0 0 15px;'.$survey_queue_custom_text_style),
							RCView::div(array('style'=>'margin:0 0 5px;font-weight:bold;'),
								$lang['survey_537'] . ' &#8211; ' .
								RCView::span(array('style'=>'font-weight:normal;'),
									$lang['survey_538'] . " " .
									RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;',
										'onclick'=>"simpleDialog('".js_escape("\"{$lang['survey_506']} {$lang['survey_511']}\"")."','".js_escape("{$lang['survey_538']} {$lang['survey_539']} {$lang['survey_540']}")."',null,600);"), $lang['survey_539']) . " " .
									$lang['survey_540'] .
									RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:40px;font-family:tahoma;font-size:11px;color:#888;', 'onclick'=>"$('#div_survey_queue_custom_text_link').show('fade');$('#div_survey_queue_custom_text').hide();$('#survey_queue_custom_text').val('');"),
										'['.$lang['ws_144'].']'
									)
								)
							) .
							RCView::div(array(),
								RCView::textarea(array('name'=>"survey_queue_custom_text",'id'=>"survey_queue_custom_text",'class'=>'x-form-field', 'style'=>'line-height:13px;font-size:12px;width:98%;height:38px;'),
									htmlspecialchars(label_decode($survey_queue_custom_text), ENT_QUOTES)
								) .
								RCView::div(array('style'=>'float:right;margin:0 10px 0 20px;'),
									RCView::img(array('src'=>'pipe_small.gif')) .
									RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;color:#3E72A8;text-decoration:underline;', 'onclick'=>"pipingExplanation();"),
										$lang['design_456']
									)
								) .
								RCView::div(array('style'=>'float:right;color:#777;font-size:11px;'),
									$lang['survey_554'] .
									' &lt;b&gt; bold, &lt;u&gt; underline, &lt;i&gt; italics, &lt;a href="..."&gt; link, etc.'
								) .
								RCView::div(array('class'=>'clear'), '')
							)
						) .
						// Table of surveys
						"<table cellspacing=0 class='form_border' style='width:100%;table-layout:fixed;'>{$hdrs}{$rows}</table>" .
					"</form>";
		} else {
			// No rows to display, so give notice that they can't use the Survey Queue yet
			$html .= 	RCView::div(array('class'=>'yellow', 'style'=>'max-width:100%;margin:20px 0;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							RCView::b($lang['global_03'].$lang['colon'])." ".$lang['survey_552']
						);
		}

		// Return all html to display
		return $html;
	}


	// Obtain the survey hash for array of participant_id's
	public static function getParticipantHashes($participant_id=array())
	{
		// Collect hashes in array with particpant_id as key
		$hashes = array();
		// Retrieve hashes
		$sql = "select participant_id, hash from redcap_surveys_participants
				where participant_id in (".prep_implode($participant_id, false).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$hashes[$row['participant_id']] = $row['hash'];
		}
		// Return hashes
		return $hashes;
	}

	// Create a new survey participant for followup survey (email will be '' and not null)
	// Return participant_id (set $forceInsert=true to bypass the Select query if we already know it doesn't exist yet)
	public static function getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id=null, $forceInsert=false, $instance=null)
	{
		global $Proj;
		$twilio_enabled = $Proj->project['twilio_enabled'];
		$twilio_default_delivery_preference = $Proj->project['twilio_default_delivery_preference'];
		// Make sure record isn't blank
		if ($record == '') return false;
		// Check event_id
		if (!is_numeric($event_id)) return false;
		// Set $instance
		$instance = is_numeric($instance) ? (int)$instance : 1;
		// Set flag to perform the insert query
		if ($forceInsert) {
			$doInsert = true;
		}
		// Check if participant_id for this event-record-survey exists yet
		else {
			$sql = "select p.participant_id, p.hash from redcap_surveys_participants p, redcap_surveys_response r
					where p.survey_id = $survey_id and p.participant_id = r.participant_id
					and p.event_id = $event_id and p.participant_email is not null
					and r.record = '".db_escape($record)."' and r.instance = $instance limit 1";
			$q = db_query($sql);
			// If participant_id exists, then return it
			if (db_num_rows($q) > 0) {
				$participant_id = db_result($q, 0, 'participant_id');
				$hash = db_result($q, 0, 'hash');
			} else {
				$doInsert = true;
			}
		}
		// Create placeholder in participants and response tables
		if (isset($doInsert) && $doInsert) {
			// Generate random hash
			$hash = self::getUniqueHash();
			// If participant has a non-NULL delivery preference already, then find it to use in teh Insert query below
			$sql = "select p.delivery_preference from redcap_surveys_participants p, redcap_surveys_response r
					where p.participant_id = r.participant_id and p.participant_email is not null
					and r.record = '".db_escape($record)."' and r.instance = $instance 
					and p.survey_id in (select s2.survey_id from redcap_surveys s left join redcap_surveys s2
					on s.project_id = s2.project_id where s.survey_id = $survey_id) order by p.delivery_preference desc";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				// Get first non-null preference
				$delivery_preference = db_result($q, 0);
			} elseif (defined("PROJECT_ID") && isset($twilio_enabled) && $Proj->twilio_enabled_surveys && $twilio_enabled == '1' && $twilio_default_delivery_preference != '') {
				// No rows exist for this record yet in this project, so add the default deliver pref if using Twilio
				$delivery_preference = $twilio_default_delivery_preference;
			} else {
				// Default null
				$delivery_preference = '';
			}
			// Since participant_id does NOT exist yet, create it.
			$sql = "insert into redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash, delivery_preference)
					values ($survey_id, $event_id, '', null, '$hash', ".checkNull($delivery_preference).")";
			if (!db_query($sql)) return false;
			$participant_id = db_insert_id();
			// Now place empty record in surveys_responses table to complete this process (sets first_submit_time as NULL - very crucial for followup)
			$sql = "insert into redcap_surveys_response (participant_id, record, instance) values ($participant_id, '".db_escape($record)."', $instance)";
			if (!db_query($sql)) {
				// If query failed (likely to the fact that it already exists, which it shouldn't), then undo
				db_query("delete from redcap_surveys_participants where participant_id = $participant_id");
				// If $forceInsert flag was to true, then try with it set to false (in case there was a mistaken determining that this placeholder existed already)
				if (!$forceInsert) {
					return false;
				} else {
					// Run recursively with $forceInsert=false
					return self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id, false, $instance);
				}
			}
			## CHECK FOR RACE CONDITION
			// Now make sure that we didn't somehow end up with duplicate rows in participants table (due to race conditions)
			$sql = "select p.participant_id from redcap_surveys_participants p, redcap_surveys_response r
					where p.participant_id = r.participant_id and p.survey_id = $survey_id and p.participant_email is not null
					and p.event_id = $event_id and r.record = '".db_escape($record)."' and r.instance = $instance order by p.participant_id";
			$q = db_query($sql);
			if (db_num_rows($q) > 1) {
				// Delete all rows except one
				$del_parts = array();
				while ($row = db_fetch_assoc($q)) {
					$del_parts[] = $row['participant_id'];
				}
				// Remove the first one (because we don't want to delete the original participant_id)
				$participant_id = array_shift($del_parts);
				$sql = "delete from redcap_surveys_participants where participant_id in (" . prep_implode($del_parts) . ")";
				db_query($sql);
				// Get new hash for this new participant_id
				$sql = "select hash from redcap_surveys_participants where participant_id = $participant_id";
				$q = db_query($sql);
				$hash = db_result($q, 0);
			}
		}
		// Return nothing if could not store hash
		return array($participant_id, $hash);
	}

	// Creates unique return_code (that is, unique within that survey) and returns that value
	public static function getUniqueReturnCode($survey_id=null, $response_id=null)
	{
		// Make sure we have a survey_id value
		if (!is_numeric($survey_id)) return false;
		// If response_id is provided, then fetch existing return code. If doesn't have a return code, then generate one.
		if (is_numeric($response_id))
		{
			// Query to get existing return code
			$sql = "select r.return_code from redcap_surveys_participants p, redcap_surveys_response r
					where p.survey_id = $survey_id and r.response_id = $response_id
					and p.participant_id = r.participant_id limit 1";
			$q = db_query($sql);
			$existingCode = (db_num_rows($q) > 0) ? db_result($q, 0) : "";
			if ($existingCode != "") {
				return strtoupper($existingCode);
			}
		}
		// Generate a new unique return code for this survey (keep looping till we get a non-existing unique value)
		do {
			// Generate a new random hash
			$code = strtolower(generateRandomHash(8, false, true));
			// Ensure that the hash doesn't already exist
			$sql = "select r.return_code from redcap_surveys_participants p, redcap_surveys_response r
					where p.survey_id = $survey_id and r.return_code = '$code'
					and p.participant_id = r.participant_id limit 1";
			$q = db_query($sql);
			$codeExists = (db_num_rows($q) > 0);
		}
		while ($codeExists);
		// If the response_id provided does not have an existing code, then save the new one we just generated
		if (is_numeric($response_id) && $existingCode == "")
		{
			$sql = "update redcap_surveys_response set return_code = '$code' where response_id = $response_id";
			$q = db_query($sql);
		}
		// Code is unique, so return it
		return strtoupper($code);
	}


	// Obtain survey return code for record-instrument[-event]
	public static function getSurveyReturnCode($record='', $instrument='', $event_id='', $instance=1, $overrideSaveAndReturn=false)
	{
		global $longitudinal, $Proj;
		// Return NULL if no record name or not instrument name
		if ($record == '' || $instrument == '') return null;
		// If a longitudinal project and no event_id is provided, return null
		if ($longitudinal && !is_numeric($event_id)) return null;
		// If a non-longitudinal project, then set event_id automatically
		if (!$longitudinal) $event_id = $Proj->firstEventId;
		// If instrument is not a survey, return null
		if (!isset($Proj->forms[$instrument]['survey_id'])) return null;
		// Get survey_id
		$survey_id = $Proj->forms[$instrument]['survey_id'];
		// If "Save & Return Later" is not enabled, then return null
		if (!$overrideSaveAndReturn && !$Proj->surveys[$survey_id]['save_and_return'] && !self::surveyLoginEnabled()) return null;
		// If instance is provided for a non-repeating form or event, then revert to 1
		if (!is_numeric($instance)) $instance = 1;
		if (!$Proj->isRepeatingForm($event_id, $instrument) && !($Proj->longitudinal && $Proj->isRepeatingEvent($event_id))) {
			$instance = 1;
		}
		// Check if return code exists already
		$sql = "select r.response_id, r.return_code from redcap_surveys_participants p, redcap_surveys_response r
				where p.survey_id = $survey_id and p.participant_id = r.participant_id
				and record = '".db_escape($record)."' and p.event_id = $event_id and r.instance = '".db_escape($instance)."'
				order by p.participant_email desc limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			// Get return code that already exists in table
			$return_code = db_result($q, 0, 'return_code');
			$response_id = db_result($q, 0, 'response_id');
			// If code is blank, then try to generate a return code
			if ($return_code == '') {
				$return_code = self::getUniqueReturnCode($survey_id, $response_id);
			}
		} else {
			// Make sure the record exists first, else return null
			if (!Records::recordExists(PROJECT_ID, $record)) return null;
			// Create new row in response table
			self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id, false, $instance);
			// Row now exists in response table, but it has no return code, so recursively re-run this method to generate it.
			return self::getSurveyReturnCode($record, $instrument, $event_id, $instance);
		}
		// Return the code
		return ($return_code == '' ? null : strtoupper($return_code));
	}


	// Obtain the survey hash for specified event_id (return public survey hash if participant_id is not provided)
	public static function getSurveyHash($survey_id = null, $event_id = null, $participant_id=null, $Proj=null)
	{
	    if ($Proj == null) {
			global $Proj;
        }
		
		if ($participant_id === null && $event_id !== null && $event_id != $Proj->getFirstEventIdInArmByEventId($event_id)) return "";
		if ($participant_id === null && $survey_id !== null && $survey_id != $Proj->firstFormSurveyId) return "";

		// Check event_id (use first event_id in project if not provided)
		if (!is_numeric($event_id)) $event_id = $Proj->firstEventId;
		if (!is_numeric($survey_id)) $survey_id = $Proj->firstFormSurveyId;

		// Retrieve hash ("participant_email=null" means it's a public survey)
		$sql = "select hash from redcap_surveys_participants where survey_id = $survey_id and event_id = $event_id ";
		if (!is_numeric($participant_id)) {
			// Public survey
			$sql .= "and participant_email is null ";
		} else {
			// Specific participant
			$sql .= "and participant_id = $participant_id ";
		}
		$sql .= "order by participant_id limit 1";
		$q = db_query($sql);

		// Hash exists
		if (db_num_rows($q) > 0) {
			$hash = db_result($q, 0);
		}
		// Create hash
		else {
			$hash = self::setHash($survey_id, null, $event_id, null, (!is_numeric($participant_id)));
		}

		return $hash;
	}


	// Create a new survey hash [for current arm]
	public static function setHash($survey_id, $participant_email=null, $event_id=null, $identifier=null,
								   $isPublicSurvey=false, $phone="", $delivery_preference="")
	{
		// Check event_id
		if (!is_numeric($event_id)) return false;

		// Set string for email (null = public survey
		$sql_participant_email = ($participant_email === null) ? "null" : "'" . db_escape($participant_email) . "'";

		// Create unique hash
		$hash = self::getUniqueHash(10, $isPublicSurvey);
		$sql = "insert into redcap_surveys_participants (survey_id, event_id, participant_email, participant_phone, participant_identifier, hash, delivery_preference)
				values ($survey_id, $event_id, $sql_participant_email, " . checkNull($phone) . ", " . checkNull($identifier) . ", '$hash', " . checkNull($delivery_preference) . ")";
		$q = db_query($sql);

		// Return nothing if could not store hash
		return ($q ? $hash : "");
	}


	// Creates unique hash after checking current hashes in tables, and returns that value
	static function getUniqueHash($hash_length=10, $isPublicSurvey=false)
	{
		do {
			// Generate a new random hash
			$hash = generateRandomHash($hash_length, false, $isPublicSurvey);
			// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
			$sql = "select hash from redcap_surveys_participants where hash = '$hash' limit 1";
			$hashExists = (db_num_rows(db_query($sql)) > 0);
		} while ($hashExists);
		// Hash is unique, so return it
		return $hash;
	}


	// Return boolean for if Survey Login is enabled
	public static function surveyLoginEnabled()
	{
		global $survey_auth_enabled;
		return ($survey_auth_enabled == '1');
	}


	// Survey Login: Display survey login form for respondent to log in
	public static function getSurveyLoginForm($record=null, $surveyLoginFailed=false, $surveyTitle=null)
	{
		global $survey_auth_field1, $survey_auth_event_id1, $survey_auth_field2, $survey_auth_event_id2,
			   $survey_auth_field3, $survey_auth_event_id3, $survey_auth_min_fields, $longitudinal,
			   $survey_auth_custom_message, $Proj, $lang, $multiple_arms;
		// Put html in $html
		$html = $rows = "";
		// Set array of fields/events
		$surveyLoginFieldsEvents = self::getSurveyLoginFieldsEvents();
		// Count auth fields
		$auth_field_count = count($surveyLoginFieldsEvents);
		
		// If this project has multiple arms, then do not force login if the login fields exist
		// on an arm where the record does not exist.
		$recordArms = array();
		if ($multiple_arms) {
			$recordArms = Records::getRecordListPerArm($Proj->project_id, array($record));
			foreach ($surveyLoginFieldsEvents as $key=>$fieldEvent) {
				$thisEventArm = $Proj->eventInfo[$fieldEvent['event_id']]['arm_num'];
				// If this event exists on an arm where the record doesn't exist, then remove the login field (not applicable)
				if (!isset($recordArms[$thisEventArm])) {					
					// Remove the field
					unset($surveyLoginFieldsEvents[$key]);
					$auth_field_count--;
				}
			}
			// If we've removed all the fields because they exist on other arms with no data, then don't show login form
			if (empty($surveyLoginFieldsEvents)) {
				return false;
			}
		}

		// If record already exists, then retrieve its data to see if we need to display all fields in login form.
		if ($record != '' && $auth_field_count >= $survey_auth_min_fields ) {
			$data_fields = $data_events = array();
			foreach ($surveyLoginFieldsEvents as $fieldEvent) {
				$data_fields[] = $fieldEvent['field'];
				$data_events[] = $fieldEvent['event_id'];
			}
			// Get data for record
			$survey_login_data = Records::getData('array', $record, $data_fields, $data_events);
			// Loop through fields again and REMOVE any where the value is empty for this record
			foreach ($surveyLoginFieldsEvents as $key=>$fieldEvent) {
				if (isset($survey_login_data[$record][$fieldEvent['event_id']][$fieldEvent['field']])
					&& $survey_login_data[$record][$fieldEvent['event_id']][$fieldEvent['field']] == '' ) {
					// Remove the field
					unset($surveyLoginFieldsEvents[$key]);
					$auth_field_count--;
				}
			}
		}

		// Count auth fields again (in case some were removed)
		$auth_field_count = count($surveyLoginFieldsEvents);


		// Loop through array of login fields
		if ($auth_field_count >= $survey_auth_min_fields) {
			foreach ($surveyLoginFieldsEvents as $fieldEvent)
			{
				// Get field and event_id
				$survey_auth_field_variable = $fieldEvent['field'];
				$survey_auth_event_id_variable = $fieldEvent['event_id'];
				// Set some attributes
				$dformat = $width = $onblur = "";
				$val_type = $Proj->metadata[$survey_auth_field_variable]['element_validation_type'];
				if ($val_type != '') {
					$onblur = "redcap_validate(this,'','','soft_typed','$val_type',1);";
					// Adjust size for date/time fields
					if ($val_type == 'time' || substr($val_type, 0, 4) == 'date' || substr($val_type, 0, 5) == 'date_') {
						$dformat = RCView::span(array('class'=>'df'), MetaData::getDateFormatDisplay($val_type));
						$width = "width:".MetaData::getDateFieldWidth($val_type).";";
					}
				}
				$field_note = "";
				if ($Proj->metadata[$survey_auth_field_variable]['element_note'] != "") {
					$field_note = RCView::div(array('class'=>'note', 'style'=>'width:100%;'), $Proj->metadata[$survey_auth_field_variable]['element_note']);
                }
				// Add row
				$rows .= RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'font-size:14px;'),
								Piping::replaceVariablesInLabel(filter_tags($Proj->metadata[$survey_auth_field_variable]['element_label']),
									$record, $survey_auth_event_id_variable)
							) .
							RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'width:320px;'),
								RCView::input(array('type'=>'password', 'name'=>$survey_auth_field_variable, 'class'=>"x-form-text x-form-field $val_type",
									'onblur'=>$onblur, 'size'=>'30', 'style'=>$width, 'autocomplete'=>'new-password'), '') .
								$dformat .
								$field_note .
								RCView::div(array('style'=>'margin-top:2px;font-size:11px;color:#666;font-weight:normal;'),
									RCView::checkbox(array('onclick'=>"passwordMask($('#survey_login_dialog input[name=\"$survey_auth_field_variable\"]'),$(this).prop('checked'));")) .
									$lang['survey_1066']
								)
							)
						);
		    }
		}
		// Instructions
		$numOutOfNum = ($survey_auth_min_fields < $auth_field_count)
			? "{$lang['survey_575']} $survey_auth_min_fields {$lang['survey_576']} $auth_field_count {$lang['survey_577']}"
			: ($auth_field_count > 1 ? $lang['survey_578'] : $lang['survey_587']);
		$html .= RCView::div(array('id'=>'survey-login-instructions'),
					RCView::p(array('style'=>'font-size:14px;margin:5px 0 15px;color:#800000;'),
						$lang['survey_310'] . " \"" . RCView::b(RCView::escape($surveyTitle)) . "\""
					) .
					RCView::p(array('style'=>'font-size:14px;margin:5px 0 15px;'),
						$lang['survey_574'] . " " .
						RCView::b($numOutOfNum) . " " . $lang['survey_594']
					)
				);
		// If previous login attempt failed, then display error message
		if ($surveyLoginFailed === true) {
			// Display default error message
			$html .= RCView::div(array('class'=>'red survey-login-error-msg', 'style'=>'margin:0 0 20px;'),
						RCView::img(array('src'=>'exclamation.png')) .
						RCView::b($lang['global_01'] . $lang['colon']) . " " .
						($survey_auth_min_fields == '1' ? $lang['survey_580'] : $lang['survey_579']) .
						// Display custom message (if set)
						(trim($survey_auth_custom_message) == '' ? '' :
							RCView::div(array('style'=>'margin:10px 0 0;'),
								nl2br(filter_tags(br2nl(trim($survey_auth_custom_message))))
							)
						)
					);
		}
		// If there are no fields to display (most likely because the participant has no data for the required fields),
		// then display an error message explaining this.
		if ($rows == '') {
			// Display default error message
			$html .= RCView::div(array('class'=>'red survey-login-error-msg', 'style'=>'margin:0 0 20px;'),
						RCView::img(array('src'=>'exclamation.png')) .
						RCView::b($lang['global_01'] . $lang['colon']) . " " .
						$lang['survey_589'] .
						// Display custom message (if set)
						(trim($survey_auth_custom_message) == '' ? '' :
							RCView::div(array('style'=>'margin:10px 0 0;'),
								nl2br(filter_tags(br2nl(trim($survey_auth_custom_message))))
							)
						)
					);
		}
		// Add form and table
		$html .= RCView::form(array('id'=>'survey_auth_form', 'action'=>$_SERVER['REQUEST_URI'], 'enctype'=>'multipart/form-data', 'target'=>'_self', 'method'=>'post'),
					RCView::table(array('cellspacing'=>0, 'class'=>'form_border'), $rows) .
					// Hidden input to denote this specific action
					RCView::hidden(array('name'=>'survey-auth-submit'), '1')
				);
		// Return html
		return RCView::div(array('id'=>'survey_login_dialog', 'class'=>'simpleDialog', 'style'=>'margin-bottom:10px;'), $html);
	}


	// Get list of Text variables in project that can be used for Survey Login fields that can
	// be used as options in a drop-down.
	public static function getTextFieldsForDropDown()
	{
		global $Proj, $lang;
		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = array(''=>'-- '.$lang['random_02'].' --');
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Text fields only
			if ($attr1['element_type'] != 'text') continue;
			// Exclude record ID field?
			if ($this_field == $Proj->table_pk) continue;
			// Add to fields/forms array. Get form of field.
			$this_form_label = strip_tags($Proj->forms[$attr1['form_name']]['menu']);
			// Truncate label if long
            $attr1['element_label'] = trim(strip_tags($attr1['element_label']));
			if (strlen($attr1['element_label']) > 65) {
				$attr1['element_label'] = trim(substr($attr1['element_label'], 0, 47)) . "... " . trim(substr($attr1['element_label'], -15));
			}
			$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
		}
		// Return all options
		return $rc_fields;
	}


	// Return array of field name and event_id of the survey auth fields (up to 3)
	public static function getSurveyLoginFieldsEvents()
	{
		global $survey_auth_field1, $survey_auth_event_id1, $survey_auth_field2, $survey_auth_event_id2,
			   $survey_auth_field3, $survey_auth_event_id3, $Proj;
		// Set array of fields
		$survey_auth_fields = array(1, 2, 3);
		$loginFieldsEvents = array();
		// Loop through array of login fields
		foreach ($survey_auth_fields as $num)
		{
			// Get global variable for this login field
			$survey_auth_field = 'survey_auth_field'.$num;
			$survey_auth_event_id = 'survey_auth_event_id'.$num;
			$survey_auth_field_variable = $$survey_auth_field;
			$survey_auth_event_id_variable = $$survey_auth_event_id;
			if ($survey_auth_field_variable != '' && isset($Proj->metadata[$survey_auth_field_variable])) {
				// Make sure event_id is valid, else default to first event_id
				if (!isset($Proj->eventInfo[$survey_auth_event_id_variable])) $survey_auth_event_id_variable = $Proj->firstEventId;
				// Add to array
				$loginFieldsEvents[] = array('field'=>$survey_auth_field_variable, 'event_id'=>$survey_auth_event_id_variable);
			}
		}
		// Return array
		return $loginFieldsEvents;
	}


	// Return boolean if the "check survey login failed attempts" is enabled
	public static function surveyLoginFailedAttemptsEnabled()
	{
		global $survey_auth_fail_limit, $survey_auth_fail_window;
		return (is_numeric($survey_auth_fail_limit) && $survey_auth_fail_limit > 0 && is_numeric($survey_auth_fail_window) && $survey_auth_fail_window > 0);
	}


	// Return the auto-logout time (in minutes) for Survey Login (based on $autologout_timer for REDCap sessions). Default = 30 if not set.
	public static function getSurveyLoginAutoLogoutTimer()
	{
		global $autologout_timer;
		return ($autologout_timer == "0" || !is_numeric($autologout_timer)) ? 30 : $autologout_timer;
	}


	// Generate or retrieve a Survey Access Codes for multiple participants and return array with
	// participant_id's as key and access code as value.
	public static function getAccessCodes($participant_ids=array())
	{
		if (!is_array($participant_ids)) return false;
		// Query to see if Survey Access Code has already been generated
		$partIdsAccessCodes = array();
		$sql = "select participant_id, access_code from redcap_surveys_participants
				where participant_id in (".prep_implode($participant_ids).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If access code is null, then generate it
			if ($row['access_code'] == '') {
				$partIdsAccessCodes[$row['participant_id']] = self::getAccessCode($row['participant_id'], false, true);
			} else {
				$partIdsAccessCodes[$row['participant_id']] = $row['access_code'];
			}
		}
		// Return array
		return $partIdsAccessCodes;
	}


	// Generate a new Survey Access Code (or retrieve existing one) OR generate new Short Code
	public static function getAccessCode($participant_id, $shortCode=false, $forceGenerate=false, $return_numeral=false)
	{
		if (!is_numeric($participant_id)) return false;
		if (!$shortCode) {
			## SURVEY ACCESS CODE
			// Determine access code's column name in db table
			$code_colname = ($return_numeral) ? "access_code_numeral" : "access_code";
			// Query to see if Survey Access Code has already been generated
			if (!$forceGenerate) {
				$sql = "select $code_colname from redcap_surveys_participants where participant_id = $participant_id
						and $code_colname is not null limit 1";
				$q = db_query($sql);
			}
			if (!$forceGenerate && db_num_rows($q)) {
				// Get existing code
				$code = db_result($q, 0);
			} else {
				// Generate random non-existing code
				do {
					// Generate a new random code
					if ($return_numeral) {
						$maxRand = mt_getrandmax();
						if ($maxRand > 9999999999) $maxRand = 9999999999;
						$code = sprintf("%010d", mt_rand(0, $maxRand));
					} else {
						$code = generateRandomHash(9, false, true);
					}
					// Ensure that the code doesn't already exist in the table
					$sql = "select $code_colname from redcap_surveys_participants where $code_colname = '".db_escape($code)."' limit 1";
					$codeExists = (db_num_rows(db_query($sql)) > 0);
				} while ($codeExists);
				// Add code to table
				$sql = "update redcap_surveys_participants set $code_colname = '".db_escape($code)."' 
						where participant_id = $participant_id and $code_colname is null";
				if (!db_query($sql)) return false;
				if (db_affected_rows() == 0) {
					// If no changes were made, that means that there was already a non-null value, so return that value					
					$sql = "select $code_colname from redcap_surveys_participants where participant_id = $participant_id";
					$q = db_query($sql);
					$code = db_result($q, 0);
				}
			}
		} else {
			## SHORT CODE
			// Generate random non-existing code
			do {
				// Generate a new random code
				$code = generateRandomHash(2, false, true, true) . sprintf("%03d", mt_rand(0, 999));
				// Ensure that the code doesn't already exist in the table
				$sql = "select code from redcap_surveys_short_codes where code = '".db_escape($code)."' limit 1";
				$codeExists = (db_num_rows(db_query($sql)) > 0);
			} while ($codeExists);
			// Add code to table
			$sql = "insert into redcap_surveys_short_codes (ts, code, participant_id) values
					('".NOW."', '".db_escape($code)."', $participant_id)";
			if (!db_query($sql)) return false;
		}
		// Code is unique, so return it
		return $code;
	}


	// Validate the Survey Code and redirect to the survey
	public static function validateAccessCodeForm($code)
	{
		global $redcap_version, $lang;
		// Get length of code
        $code_orig = $code;
		$code_length = strlen($code);
		// Is a short code?
		$isShortCode = ($code_length == self::SHORT_CODE_LENGTH && preg_match("/^[A-Za-z0-9]+$/", $code));
		// Is an access code?
		$isAccessCode = ($code_length == self::ACCESS_CODE_LENGTH && preg_match("/^[A-Za-z0-9]+$/", $code));
		// Is a numeral access code?
		$isNumeralAccessCode = ($code_length == self::ACCESS_CODE_NUMERAL_LENGTH && is_numeric($code));
		// Is a numeral access code beginning with "V", which denotes that Twilio should call them?
		$lengthPrependAccessCodeNumeral = strlen(self::PREPEND_ACCESS_CODE_NUMERAL);
		$isNumeralAccessCodeReceiveCall = (!$isShortCode && !$isAccessCode && !$isNumeralAccessCode
			&& $code_length == (self::ACCESS_CODE_NUMERAL_LENGTH + $lengthPrependAccessCodeNumeral)
			&& strtolower(substr($code, 0, $lengthPrependAccessCodeNumeral)) == strtolower(self::PREPEND_ACCESS_CODE_NUMERAL)
			&& is_numeric(substr($code, $lengthPrependAccessCodeNumeral)));
		if ($isNumeralAccessCodeReceiveCall) {
			$code = substr($code, $lengthPrependAccessCodeNumeral);
			$isNumeralAccessCode = true;
		}
		// If not a valid code based on length or content alone, then stop
		if (!$isShortCode && !$isAccessCode && !$isNumeralAccessCode && !$isNumeralAccessCodeReceiveCall) return false;
		// Determine if Short Code or normal Access Code
		if ($isShortCode) {
			## SHORT CODE
			// Get timestamp older than X minutes
			$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-Survey::SHORT_CODE_EXPIRE,date("s"),date("m"),date("d"),date("Y")));
			$sql = "select p.hash from redcap_surveys_participants p, redcap_surveys_short_codes c
					where p.participant_id = c.participant_id and c.code = '".db_escape($code)."' and c.ts > '$xMinAgo' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) == 0) return false;
			$hash = db_result($q, 0);
			// Now remove the code since it only gets used once
			$sql = "delete from redcap_surveys_short_codes where code = '".db_escape($code)."' limit 1";
			db_query($sql);
		} elseif (!$isShortCode) {
			## SURVEY ACCESS CODE
			$sql = "select hash from redcap_surveys_participants where "
				 . ($isNumeralAccessCode ? "access_code_numeral = '".db_escape($code)."'" : "access_code = '".db_escape($code)."'");
			$q = db_query($sql);
			if (db_num_rows($q) == 0) return false;
			$hash = db_result($q, 0, 'hash');
            // If user submitted code in order to receive phone call, then initiate survey by calling them
            // if ($isNumeralAccessCodeReceiveCall && isset($_POST['From'])) {
            if ($isNumeralAccessCodeReceiveCall && substr($code_orig, 0, 1) == self::PREPEND_ACCESS_CODE_NUMERAL && isset($_POST['From'])) {
                // Obtain project_id via the access codes
                $_GET['pid'] = TwilioRC::getProjectIdFromNumericAccessCode($code);
                $Proj = new Project($_GET['pid']);
                $twilio_from_number = $Proj->project['twilio_from_number'];
                $twilio_multiple_sms_behavior = $Proj->project['twilio_multiple_sms_behavior'];
                // Remove the access code from the db table since we're about to initiate the call (so we no longer need it)
                TwilioRC::deleteSmsAccessCodeFromPhoneNumber($_POST['From'], $twilio_from_number,
                                ($twilio_multiple_sms_behavior == 'OVERWRITE' ? null : $_SESSION['survey_access_code']));
                // Redirect to the correct page to make the call to the respondent
                redirect(APP_PATH_SURVEY_FULL . "index.php?s=$hash&action=init&delivery_type=VOICE_INITIATE&phone=".$_POST['From']."&__passthru=".urlencode("Surveys/twilio_initiate_call_sms.php"));
                /**
                // Instantiate a client to Twilio's REST API
                $twilioClient = TwilioRC::client();
                // Set voice and language for all statements in call
                $language = TwilioRC::getLanguage();
                $voice = TwilioRC::getVoiceGender();
                // Set the survey URL that Twilio will make the request to
                $question_url = APP_PATH_SURVEY_FULL . "?s={$_GET['s']}&voice=$voice&language=$language";
                // Get number
                $number_to_call = preg_replace("/[^0-9]/", "", $_POST['From']);
                // Obtain project_id via the access codes
                $_GET['pid'] = TwilioRC::getProjectIdFromNumericAccessCode($code);
                $Proj = new Project($_GET['pid']);
                $twilio_from_number = $Proj->project['twilio_from_number'];
                // Call the phone number
                try {
                    // Remove access code from session
                    unset($_SESSION['survey_access_code']);
                    // Create hash so that we can add it to callback url
                    $callback_hash = generateRandomHash(50);
                    $call = $twilioClient->account->calls->create(TwilioRC::formatNumber($twilio_from_number), TwilioRC::formatNumber($number_to_call), $question_url, array(
                        "StatusCallback" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash",
                        "FallbackUrl" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash&__error=1",
                        "IfMachine"=>"Continue"
                    ));
                    // Add the sid and sid_hash to the db table so that we can delete the log for this event once it has completed
                    TwilioRC::addEraseCall($_GET['pid'], $call->sid, $callback_hash);
                } catch (Exception $e) { }
                */
                // Stop here
                exit;
            }
		}
		// Return hash
		return $hash;
	}


	// Return array of available delivery methods for surveys (e.g. email, sms_invite, voice_initiate, sms_initiate).
	// To be used as drop-down list options.
	public static function getDeliveryMethods($addParticipantPrefOption=false, $addDropdownGroups=false, $appendPreferenceTextToOption=null, $addEmailOption=true)
	{
		global $lang, $twilio_enabled, $twilio_option_voice_initiate, $twilio_option_sms_initiate,
			   $twilio_option_sms_invite_make_call, $twilio_option_sms_invite_receive_call, $twilio_option_sms_invite_web;
		// Add array of delivery methods (email by default)
		$delivery_methods = array();
		// Email option
		if ($addEmailOption) {
			$delivery_methods[$lang['survey_804']]['EMAIL'] = $lang['survey_688'] .
										 ($appendPreferenceTextToOption == 'EMAIL' ? " " . $lang['survey_782'] : '');
		}
		// If using Twilio, add the SMS/Voice choices
		if ($twilio_enabled) {
			if ($twilio_option_sms_invite_web) {
				$delivery_methods[$lang['survey_804']]['SMS_INVITE_WEB'] = $lang['survey_955'] .
													($appendPreferenceTextToOption == 'SMS_INVITE_WEB' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_sms_initiate) {
				$delivery_methods[$lang['survey_803']]['SMS_INITIATE'] = $lang['survey_767'] .
													($appendPreferenceTextToOption == 'SMS_INITIATE' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_voice_initiate) {
				$delivery_methods[$lang['survey_802']]['VOICE_INITIATE'] = $lang['survey_884'] .
													  ($appendPreferenceTextToOption == 'VOICE_INITIATE' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_sms_invite_make_call) {
				$delivery_methods[$lang['survey_802']]['SMS_INVITE_MAKE_CALL'] = $lang['survey_690'] .
												  ($appendPreferenceTextToOption == 'SMS_INVITE_MAKE_CALL' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_sms_invite_receive_call) {
				$delivery_methods[$lang['survey_802']]['SMS_INVITE_RECEIVE_CALL'] = $lang['survey_801'] .
												  ($appendPreferenceTextToOption == 'SMS_INVITE_RECEIVE_CALL' ? " " . $lang['survey_782'] : '');
			}
		}
		// Add participant's preference as option?
		if ($addParticipantPrefOption) {
			$delivery_methods[$lang['survey_805']]['PARTICIPANT_PREF'] = $lang['survey_768'];
		}
		// If we're not adding the optgroups, then remove them
		if (!$addDropdownGroups) {
			$delivery_methods2 = array();
			foreach ($delivery_methods as $key=>$attr) {
				if (is_array($attr)) {
					foreach ($attr as $key2=>$attr2) {
						$delivery_methods2[$key2] = $attr2;
					}
				} else {
					$delivery_methods2[$key] = $attr;
				}
			}
			$delivery_methods = $delivery_methods2;
		}
		// Return array
		return $delivery_methods;
	}


	// Display the Survey Code form for entering the code
	public static function displayAccessCodeForm($displayErrorMsg=false)
	{
		global $lang;
		return 	RCView::form(array('id'=>'survey_code_form', 'style'=>'font-weight:bold;margin:0 0 10px;font-size:16px;', 'action'=>$_SERVER['REQUEST_URI'], 'enctype'=>'multipart/form-data', 'target'=>'_self', 'method'=>'post'),
					RCView::div(array('style'=>'margin:-32px 0 5px;text-align:right;'),
						RCView::a(array('href'=>'https://projectredcap.org/', 'target'=>'_blank'),
							RCView::img(array('src'=>'redcap-logo-small.png'))
						)
					) .
					RCView::div(array(),
						$lang['survey_619'] .
						RCView::text(array('name'=>'code', 'maxlength'=>'20', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 4px 0 10px;font-size:16px;width:120px;padding:4px 6px;')) .
						RCView::button(array('class'=>'jqbutton', 'onclick'=>"
							var ob = $('input[name=\"code\"]');
							ob.val( trim(ob.val()) );
							if (ob.val() == '') {
								simpleDialog('".js_escape($lang['survey_634'])."');
								return false;
							}
							$('#survey_code_form').submit();
						"), $lang['survey_200'])
					) .
					// Error msg
					(!$displayErrorMsg ? '' :
						RCView::div(array('class'=>'red', 'style'=>'font-size:14px;margin-top:20px;padding:10px 15px 12px;'),
							RCView::img(array('src'=>'exclamation.png')) .
							RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['survey_622']
						)
					) .
					RCView::div(array('style'=>'font-size:14px;font-weight:normal;color:#777;margin-top:30px;margin-bottom:50px;'),
						$lang['survey_642']
					)
				) .
				"<style type='text/css'>
				#footer { display:none; }
				</style>
				<script type='text/javascript'>
				$(function(){
					$('input[name=\"code\"]').focus();
				});
				</script>";
	}


	// OBTAIN SHORT URL VIA BIT.LY API
	public static function getShortUrl($original_url)
	{
		// URL shortening service
		$service = "j.mp";
		// Set parameters for URL shortener service
		$serviceurl = "http://api.bit.ly/v3/shorten?domain=$service&format=txt&login=projectredcap&apiKey=R_6952a44cd93f2c200047bb81cf3dbb71&longUrl=";
		$urlbase	= "https://$service/";
		// Retrieve shortened URL from URL shortener service
		$shorturl = trim(http_get($serviceurl . urlencode($original_url)));
		// Ensure that we received a link in the expected format
		if (!empty($shorturl) && substr($shorturl, 0, strlen($urlbase)) == $urlbase) {
			// Output
			return $shorturl;
		}
		// On error, return false
		return false;
	}

	// OBTAIN CUSTOM SHORT URL
	public static function getCustomShortUrl($url, $customUrl)
	{
		$path = "https://is.gd/create.php?format=simple&url=".urlencode($url);
		if ($customUrl) $path .= "&shorturl=$customUrl";
		return trim(http_get($path));
	}


	// OBTAIN HTML ICON FOR A GIVEN SMS/VOICE DELIVERY PREFERENCE IN THE PARTICIPANT LIST
	public static function getDeliveryPrefIcon($delivery_pref)
	{
		global $lang;
		// Deliever preference
		if ($delivery_pref == 'VOICE_INITIATE') {
			$deliv_pref_icon = RCView::img(array('src'=>'phone.gif', 'title'=>$lang['survey_884']));
		} else if ($delivery_pref == 'SMS_INITIATE') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloons_box.png', 'title'=>$lang['survey_767']));
		} else if ($delivery_pref == 'SMS_INVITE_MAKE_CALL') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone.gif', 'title'=>$lang['survey_690']));
		} else if ($delivery_pref == 'SMS_INVITE_RECEIVE_CALL') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone_receive.gif', 'title'=>$lang['survey_801']));
		} else if ($delivery_pref == 'SMS_INVITE_WEB') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloon_link.gif', 'title'=>$lang['survey_955']));
		} else {
			$deliv_pref_icon = RCView::img(array('src'=>'email.png', 'title'=>$lang['global_33']));
		}
		return $deliv_pref_icon;
	}


	// DETERMINE THE NEXT SURVEY URL IN THE SAME EVENT AND RETURN URL OR NULL
	public static function getAutoContinueSurveyUrl($record, $current_form_name, $event_id, $instance=1)
	{
		global $Proj;
		// Get all forms from this event
		$forms_array = $Proj->eventsForms[$event_id];

		// Get all forms after the current one
		$forms_array = array_slice($forms_array, array_search($current_form_name, $forms_array) + 1);

		// Create array of valid surveys remaining
		$next_surveys = array();
		foreach ($forms_array as $k => $form) {
			$this_survey_id = isset($Proj->forms[$form]['survey_id']) ? $Proj->forms[$form]['survey_id'] : 0;
			if ($this_survey_id) {
				$this_survey = $Proj->surveys[$this_survey_id];
				// Check it is enabled
				if ($this_survey['survey_enabled'] == 1 && 
					// Check response limit (if enabled) - do not do AutoContinue for this survey/event if hit limit already
					!self::reachedResponseLimit($Proj->project_id, $this_survey_id, $event_id)
				) {
					// Check it isn't expired
					if (!($this_survey['survey_expiration'] != '' && $this_survey['survey_expiration'] <= NOW)) {
						$next_surveys[] = $this_survey_id;
					}
				}
			}
		}

		// Is there another valid survey in this event
		if (empty($next_surveys)) {
			$next_survey_url = null;
		} else {
			$next_survey_id = current($next_surveys);
            $next_form_name = $Proj->surveys[$next_survey_id]['form_name'];
            // Repeating Forms ONLY: Get count of existing instances and find next instance number
            if ($Proj->isRepeatingForm($event_id, $next_form_name)) {
                list ($instanceTotal, $instanceMax) = RepeatInstance::getRepeatFormInstanceMaxCount($record, $event_id, $next_form_name, $Proj);
                $instance = $instanceMax + 1;
            } elseif (!$Proj->isRepeatingEvent($event_id)) {
                // If next form/event is not a repeating form or repeating event, always use instance 1
                $instance = 1;
            }
			// Use survey_functions to generate a hash for this survey
			list($next_participant_id, $next_hash) = self::getFollowupSurveyParticipantIdHash($next_survey_id, $record, $event_id, false, $instance);
			$next_survey_url = APP_PATH_SURVEY_FULL . "?s=$next_hash";
		}
		return $next_survey_url;
	}


	// OBTAIN ARRAY OF ALL LANGUAGES AVAILABLE FOR GOOGLE TEXT-TO-SPEECH API
	public static function getTextToSpeechLanguages()
	{
		return array(
			"af"=>"Afrikaans",
			"sq"=>"Albanian",
			"ar"=>"Arabic",
			"hy"=>"Armenian",
			"ca"=>"Catalan",
			"zh-CN"=>"Mandarin (simplified)",
			"zh-TW"=>"Mandarin (traditional)",
			"hr"=>"Croatian",
			"cs"=>"Czech",
			"da"=>"Danish",
			"nl"=>"Dutch",
			"en"=>"English",
			"eo"=>"Esperanto",
			"fi"=>"Finnish",
			"fr"=>"French",
			"de"=>"German",
			"el"=>"Greek",
			"ht"=>"Haitian Creole",
			"hi"=>"Hindi",
			"hu"=>"Hungarian",
			"is"=>"Icelandic",
			"id"=>"Indonesian",
			"it"=>"Italian",
			"ja"=>"Japanese",
			"ko"=>"Korean",
			"la"=>"Latin",
			"lv"=>"Latvian",
			"mk"=>"Macedonian",
			"no"=>"Norwegian",
			"pl"=>"Polish",
			"pt"=>"Portuguese",
			"ro"=>"Romanian",
			"ru"=>"Russian",
			"sr"=>"Serbian",
			"sk"=>"Slovak",
			"es"=>"Spanish",
			"sw"=>"Swahili",
			"sv"=>"Swedish",
			"ta"=>"Tamil",
			"th"=>"Thai",
			"tr"=>"Turkish",
			"vi"=>"Vietnamese",
			"cy"=>"Welsh"
		);
	}


	// Text Size: See appropriage text size by outputting CSS file
	public static function setTextSize($text_size, $HtmlPageObject)
	{
		global $isMobileDevice;
		// Init var
		if ($text_size == '') $text_size = 0;
		// For mobile devices, increase the font one step for better viewability
		if ($isMobileDevice) $text_size++;
		// Set CSS file
		if ($text_size == '1') {
			$HtmlPageObject->addStylesheet("survey_text_large.css", 'screen,print');
		} elseif ($text_size > 1) {
			$HtmlPageObject->addStylesheet("survey_text_very_large.css", 'screen,print');
		}
		// Return HTML object
		return $HtmlPageObject;
	}


	// Get list of index numbers from getFonts() where the font is non-Latin
	public static function getNonLatinFontIndex()
	{
		return array(12, 13, 14, 15);
	}


	// Get list of survey themes
	public static function getFonts($font=null)
	{
		// Array of all available survey fonts
		$fonts = array();
		// Latin fonts
		$fonts[16] = "'Open Sans',Helvetica,Arial,sans-serif";
		$fonts[0] = "'Arial Black',Gadget,sans-serif";
		$fonts[1] = "'Comic Sans MS',cursive,sans-serif";
		$fonts[2] = "'Courier New',Courier,monospace";
		$fonts[3] = "Georgia,serif";
		$fonts[4] = "'Lucida Console',Monaco,monospace";
		$fonts[5] = "'Lucida Sans Unicode','Lucida Grande',sans-serif";
		$fonts[6] = "'Palatino Linotype','Book Antiqua',Palatino,serif";
		$fonts[7] = "Tahoma,Geneva,sans-serif";
		$fonts[8] = "'Times New Roman',Times,serif";
		$fonts[9] = "'Trebuchet MS',Helvetica,sans-serif";
		$fonts[10] = "Verdana,Geneva,sans-serif";
		$fonts[11] = "'Gill Sans',Geneva,sans-serif";
		// Non-Latin fonts
		$fonts[12] = "Meiryo,sans-serif";
		$fonts[13] = "'Meiryo UI',sans-serif";
		$fonts[14] = "'Hiragino Kaku Gothic Pro',sans-serif";
		$fonts[15] = "'MS PGothic',Osaka,Arial,sans-serif";
		// Set default font if returning single font and invalid parameter is passed
		if ($font !== null && !isset($fonts[$font])) $font = 0;
		// Return single font
		if ($font !== null) return $fonts[$font];
		// Return array
		return $fonts;
	}


	// Survey Theme: Provide $theme as /redcap/themes/ subdirectory, and output CSS and JS files inside that directory
	public static function applyFont($font_num, $HtmlPageObject)
	{
		if (!is_numeric($font_num)) {
			// Default font
			$font = 'Arial';
		} else {
			// Validate font
			$font = self::getFonts($font_num);
		}
		// Add CSS to HtmlObject
		$HtmlPageObject->addInlineStyle("body * { font-family: $font !important; }");
		// Return HtmlPage object
		return $HtmlPageObject;
	}


	// Get list of user-saved survey themes
	public static function getUserThemes($selected_theme=null)
	{
		// Put themes into array
		$themes_array = array();
		// Get themes for this user
		if ($selected_theme === null) {
			$sql = "select t.* from redcap_surveys_themes t, redcap_user_information i
					where i.ui_id = t.ui_id and i.username = '".db_escape(USERID)."' order by t.theme_name";
		} else {
			$sql = "select t.* from redcap_surveys_themes t where t.theme_id = '".db_escape($selected_theme)."' order by t.theme_name";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$theme_id = $row['theme_id'];
			unset($row['ui_id'], $row['theme_id']);
			// Prepend color values with hash
			foreach ($row as $attr_name=>$attr_val) {
				if ($attr_name != 'theme_name' && $attr_val != '') {
					$row[$attr_name] = "#$attr_val";
				}
			}
			$themes_array[$theme_id] = $row;
		}
		// Return array of user's themes
		return $themes_array;
	}


	// Return boolean regarding if current user has one or more saved custom survey themes
	public static function userHasCustomThemes($theme_id=null)
	{
		$sql = "select 1 from redcap_surveys_themes t, redcap_user_information i
				where i.ui_id = t.ui_id and i.username = '".db_escape(USERID)."' ";
		if (is_numeric($theme_id)) {
			$sql .= "and t.theme_id = $theme_id";
		} else {
			$sql .= "limit 1";
		}
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}


	// Get utilization of a user's saved survey theme
	// Return array of user's themes with theme_id as key where value = count of # surveys using the theme
	public static function getUserThemeUtilization($username)
	{
		$sql = "select t.theme_id, count(1) as thiscount
				from redcap_surveys_themes t, redcap_user_information i, redcap_surveys s, redcap_projects p
				where i.ui_id = t.ui_id and s.theme = t.theme_id and i.username = '".db_escape($username)."'
				and s.project_id = p.project_id and p.date_deleted is null
				and s.form_name = (select m.form_name from redcap_metadata m where m.project_id = p.project_id and s.form_name = m.form_name limit 1)
				group by t.theme_id";
		$q = db_query($sql);
		$themeCounts = array();
		while ($row = db_fetch_assoc($q)) {
			$themeCounts[$row['theme_id']] = $row['thiscount'];
		}
		return $themeCounts;
	}


	// Get list of survey themes
	public static function getThemes($theme=null, $return_attributes=true, $return_user_themes=false)
	{
		// Put themes into array
		$themes_array = $user_themes = array();
		$preset_themes = self::getPresetThemes();
		// Obtain all themes saved by user
		if ($return_user_themes) {
			$user_themes = self::getUserThemes($theme);
			$preset_themes = $preset_themes+$user_themes;
		}
		// Obtain all themes (and attributes, if applicable)
		foreach ($preset_themes as $this_theme=>$theme_attr) {
			// Add theme to array
			if ($return_attributes) {
				$themes_array[$this_theme] = $theme_attr;
			} else {
				$themes_array[$this_theme] = $theme_attr['theme_name'];
			}
			// If $theme parameter was provided, then just return this theme attributes
			if ($theme !== null && $this_theme."" === $theme."") return $themes_array[$this_theme];
		}
		// Return array
		asort($themes_array);
		return $themes_array;
	}


	// Survey Theme: Provide $theme as /redcap/themes/ subdirectory, and output CSS and JS files inside that directory
	public static function applyTheme($theme, $HtmlPageObject, $custom_attr=array())
	{
		// Get the current theme
		$theme_attr = self::getThemes($theme, true, true);
		if ($theme_attr === null && empty($custom_attr)) return $HtmlPageObject;
		// Add any customizations on top of theme and prepend color values with hash
		if ($theme == '' && !empty($custom_attr)) {
			$theme_attr = array();
			foreach ($custom_attr as $attr_name=>$attr_val) {
				if ($attr_val != '') $theme_attr[$attr_name] = "#$attr_val";
			}
		}
		// Convert theme attr into CSS
		$css = self::getThemeCSS($theme_attr);
		// Add CSS to HtmlObject
		$HtmlPageObject->addInlineStyle($css);
		// Return HtmlPage object
		return $HtmlPageObject;
	}



	// Convert survey theme attributes into CSS
	public static function getThemeCSS($theme_attr)
	{
		$css = "";
		if (isset($theme_attr['theme_bg_page'])) {
			$css .= "body { background-image: none; background-color: {$theme_attr['theme_bg_page']}; }\n";
			// Determine a white or black footer based upon body bg color
			$footer_color = (is_numeric(substr($theme_attr['theme_bg_page'], 1, 1))) ? "#FFFFFF" : "#000000";
			$css .= "#footer, #footer a { color: $footer_color !important; }\n";
		}
		if (isset($theme_attr['theme_text_buttons'])) {
			$css .= "button, .btn-fileupload {color: {$theme_attr['theme_text_buttons']} !important; }\n";
			$css .= ".btn-fileupload {background-color: #e6e6e6 !important; border-color: #999 !important; }\n";
			$css .= ".btn-fileupload:hover {background-color: #ededed !important; border-color: #ccc !important; }\n";
		}
		if (isset($theme_attr['theme_text_question'])) {
			$css .= "#questiontable td, .floatMtxHdr td { color: {$theme_attr['theme_text_question']}; }\n";
			$css .= ".matrix_first_col_hdr { color: {$theme_attr['theme_text_question']} !important; }\n";
			// Enhanced choices radios/checkboxes
			$css .= "div.enhancedchoice label { color: {$theme_attr['theme_text_question']} !important; border-color: {$theme_attr['theme_text_question']} !important; }\n";
			$css .= "div.enhancedchoice label.selectedradio, div.enhancedchoice label.selectedchkbox, div.enhancedchoice label.hover:hover { background-color: {$theme_attr['theme_text_question']}; }\n";
		}
		if (isset($theme_attr['theme_bg_question'])) {
			$css .= "#questiontable td, .floatMtxHdr, .floatMtxHdr td { background-image: none; background-color: {$theme_attr['theme_bg_question']}; }\n";
			// Enhanced choices radios/checkboxes
			$css .= "div.enhancedchoice label { background-color: {$theme_attr['theme_bg_question']}; }\n";
			$css .= "div.enhancedchoice label.selectedradio, div.enhancedchoice label.selectedchkbox, div.enhancedchoice label.hover:hover { !important; border-color: {$theme_attr['theme_bg_question']} !important; }\n";
			$css .= "div.enhancedchoice label.selectedradio, div.enhancedchoice label.selectedradio a, div.enhancedchoice label.selectedchkbox, div.enhancedchoice label.selectedchkbox a, div.enhancedchoice label.hover:hover, div.enhancedchoice label.hover:hover a { color: {$theme_attr['theme_bg_question']} !important; }\n";
		}
		if (isset($theme_attr['theme_text_sectionheader'])) {
			$css .= ".header { color: {$theme_attr['theme_text_sectionheader']} !important; }\n";
		}
		if (isset($theme_attr['theme_bg_sectionheader'])) {
			$css .= ".header { background-image: none; border: none; background-color: {$theme_attr['theme_bg_sectionheader']} !important; }\n";
		}
		if (isset($theme_attr['theme_text_title'])) {
			$css .= "#surveypagenum, #surveytitle, #surveyinstructions, #surveyinstructions p, #surveyinstructions span, #surveyinstructions div, #surveyacknowledgment, #surveyacknowledgment p, #surveyacknowledgment span, #surveyacknowledgment div { color: {$theme_attr['theme_text_title']}; } \n";
			$css .= "#return_corner a, #survey_queue_corner a { color: {$theme_attr['theme_text_title']} !important; } \n";
		}
		if (isset($theme_attr['theme_bg_title'])) {
			$css .= "#pagecontent, #container, #surveytitle, #surveyinstructions, #surveyinstructions p, #surveyinstructions span, #surveyinstructions div, #surveyacknowledgment, #surveyacknowledgment p, #surveyacknowledgment span, #surveyacknowledgment div { background-image: none; background-color: {$theme_attr['theme_bg_title']}; } \n";
			// Determine a white or black footer based upon body bg color
			$changeFont_color = (is_numeric(substr($theme_attr['theme_bg_title'], 1, 1))) ? "#FFFFFF" : "#000000";
			$css .= "#changeFont { color: $changeFont_color !important; }\n";
		}
		if (isset($theme_attr['misc_css'])) {
			$css .= $theme_attr['misc_css'] . "\n";
		}
		return $css;
	}


	// Get Preset Survey Theme attributes
	public static function getPresetThemes($theme=null)
	{
		// Add preset themes to array
		$themes = array();
		$sql = "select * from redcap_surveys_themes where ui_id is null";
		if ($theme !== null) $sql .= " and theme_id = '".db_escape($theme)."'";
		$sql .= " order by theme_name";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$theme_id = $row['theme_id'];
			unset($row['ui_id'], $row['theme_id']);
			// Prepend color values with hash
			foreach ($row as $attr_name=>$attr_val) {
				if ($attr_name != 'theme_name' && $attr_val != '') {
					$row[$attr_name] = "#$attr_val";
				}
			}
			$themes[$theme_id] = $row;
		}
		// If $theme parameter is passed but is invalid, then return NULL
		if ($theme !== null && !isset($themes[$theme])) return null;
		// If $theme parameter is passed, return only its attributes
		if ($theme !== null && isset($themes[$theme])) return $themes[$theme];
		// Return themes array
		return $themes;
	}


	// Render drop-down list of Survey Themes
	public static function renderSurveyThemeDropdown($selected_theme='', $disabled=false)
	{
		global $lang;
		// Default theme's attributes
		$theme_attr_json_default = json_encode(array(
			'theme_text_buttons'=>'#000000', 'theme_bg_page'=>'#1A1A1A',
			'theme_text_title'=>'#000000', 'theme_bg_title'=>'#FFFFFF',
			'theme_text_sectionheader'=>'#000000', 'theme_bg_sectionheader'=>'#BCCFE8',
			'theme_text_question'=>'#000000', 'theme_bg_question'=>'#F3F3F3'
		));
		// Get survey theme attributes and set them as JSON
		$themes_attr_json = array();
		$survey_themes = self::getThemes(null, true, true);
		$user_themes = self::getUserThemes();
		$hasUserThemes = (!empty($user_themes));
		foreach ($survey_themes as $this_theme=>$attr) {
			unset($attr['theme_name']);
			$themes_attr_json[$this_theme] = json_encode($attr);
		}
		// If the currently saved theme is a user's theme but does NOT belong to the CURRENT user, then add to end of drop-down list
		if ($selected_theme != '' && !isset($survey_themes[$selected_theme])) {
			$other_user_theme = self::getThemes($selected_theme, true, true);
			$hasOtherUserThemes = true;
		}
		// Build drop-down options for survey theme choice
		$this_selected = ($selected_theme == '') ? " selected" : "";
		$survey_themes_opts = $survey_themes_user_opts = $survey_themes_other_user_opts = "";
		if ($hasUserThemes) {
			$survey_themes_user_opts = "<optgroup label='".js_escape($lang['survey_1039'])."'>";
		}
		if ($hasUserThemes || $hasOtherUserThemes) {
			$survey_themes_opts = "<optgroup label='".js_escape($lang['survey_1038'])."'>";
		}
		if ($hasOtherUserThemes) $survey_themes_other_user_opts = "<optgroup label='".js_escape($lang['survey_1040'])."'>";
		$survey_themes_opts .= "<option value='' attr='".$theme_attr_json_default."'$this_selected>{$lang['survey_1017']}</option>";
		foreach ($survey_themes as $this_theme=>$attr) {
			$this_theme_name = $attr['theme_name'];
			$this_selected = ($this_theme == $selected_theme) ? " selected" : "";
			if (isset($user_themes[$this_theme])) {
				$survey_themes_user_opts .= "<option value='$this_theme' attr='".$themes_attr_json[$this_theme]."'$this_selected>".RCView::escape($this_theme_name)."</option>";
			} else {
				$survey_themes_opts .= "<option value='$this_theme' attr='".$themes_attr_json[$this_theme]."'$this_selected>".RCView::escape($this_theme_name)."</option>";
			}
		}
		// If theme was created by another user (other than current user), then add at end of drop-down list
		if (!empty($other_user_theme)) {
			$this_theme_name = $other_user_theme['theme_name'];
			unset($other_user_theme['theme_name']);
			$theme_attr_json_other_user = json_encode($other_user_theme);
			$survey_themes_other_user_opts .= "<option value='$selected_theme' attr='".$theme_attr_json_other_user."' selected>".RCView::escape($this_theme_name)."</option>";
		}
		$survey_themes_opts .= $survey_themes_user_opts . $survey_themes_other_user_opts;
		// Return HTML for drop-down
		return "<select id='theme' name='theme' class='x-form-text x-form-field' style='' "
			 . "onchange='updateThemeIframe()'".($disabled ? ' disabled' : '').">$survey_themes_opts</select>";
	}
	
	// Serially-running AJAX request for when user opens a survey via data entry form to check if the survey has been modified since the survey was
	// initially opened. This prevents users from closing the survey tab to return to the data entry form, save it, and thus mistakenly
	// overwrite all the survey responses.
	public function openSurveyValuesChanged($time_opened="", $survey_hash="")
	{
		$sql = "select 1 from redcap_surveys_participants p, redcap_surveys_response r 
				where p.participant_id = r.participant_id and p.participant_email is not null 
				and p.hash = '".prep($survey_hash)."' and ((r.first_submit_time is not null and r.first_submit_time > '".prep($time_opened)."')
				or (r.completion_time is not null and r.completion_time > '".prep($time_opened)."')) limit 1";
		$q = db_query($sql);
		// Return 1 if values have not changed since the survey was opened, else return 0 so we can warn user
		return (db_num_rows($q) > 0) ? '0' : '1';
	}
	
	// Count survey responses for a given survey (exclude data entered via data entry form)
	public static function countResponses($survey_id, $event_id, $include_partials="1")
	{
		$sql = "select count(distinct(r.record)) from redcap_surveys_participants p, redcap_surveys_response r 
				where p.participant_id = r.participant_id and p.survey_id = '".prep($survey_id)."'
				and p.event_id = '".prep($event_id)."' and ";
		$sql .= ($include_partials != '1') ? "r.completion_time is not null" : "r.first_submit_time is not null";
		$q = db_query($sql);
		// Return response count
		return db_result($q, 0);
	}
	
	// Check if Response Limit is enabled, and if so, check number of responses to display stop message to respondent
	public static function reachedResponseLimit($project_id, $survey_id, $event_id)
	{
		global $lang;
		// Get Proj object
		$Proj = new Project($project_id);
		// Is response limit enabled?
		if (!(is_numeric($Proj->surveys[$survey_id]['response_limit']) && $Proj->surveys[$survey_id]['response_limit'] > 0)) return false;
		// Check response count
		$response_count = self::countResponses($survey_id, $event_id, $Proj->surveys[$survey_id]['response_limit_include_partials']);
		if ($response_count < $Proj->surveys[$survey_id]['response_limit']) return false;
		// We've hit the response limit, so display message to respondent
		return true;
	}
	
	// Calculate survey time limit's time limit in seconds, if enabled
	public static function calculateSurveyTimeLimit($days=0, $hours=0, $minutes=0) 
	{
		// Check temporal components
		if (!(is_numeric($days) && $days > 0)) $days = 0;
		if (!(is_numeric($hours) && $hours > 0)) $hours = 0;
		if (!(is_numeric($minutes) && $minutes > 0)) $minutes = 0;
		return ($days*24*3600 + $hours*3600 + $minutes*60);
	}
	
	// Calculate a survey respondent's time limit timestamp based on their first invite time.
	// Return FALSE if participant invitation is past the time limit.
	public static function checkSurveyTimeLimit($participant_id="", $days=0, $hours=0, $minutes=0) 
	{
		global $lang;
		// Get time limit in seconds
		$timeLimitSeconds = self::calculateSurveyTimeLimit($days, $hours, $minutes);
		if ($timeLimitSeconds == 0) return true;
		// Check invite time
		$linkExpirationTimes = self::getLinkExpirationTimes(array($participant_id));
		$linkExpirationTime = $linkExpirationTimes[$participant_id];
		if ($linkExpirationTime == "") return true;
		// If initial survey invite time + time limit is not > now, then do nothing
		if (strtotime(NOW) < strtotime($linkExpirationTime)) return true;
		// We've hit the time limit, so display message to respondent
		return false;
	}
	
	// Get the initial invitation time of non-public survey links
	public static function getLinkExpirationTimes($participant_ids=array())
	{
		// Build placeholder array first
		$linkExpirationTimes = array();
		foreach ($participant_ids as $part_id) {
			$linkExpirationTimes[$part_id] = "";
		}
		// First, get any link expirations that are stored in the participant table
		$sql = "select p.participant_id, p.link_expiration from redcap_surveys_participants p
				where p.participant_email is not null and p.participant_id in (".prep_implode($participant_ids).") 
				and p.link_expiration is not null group by p.participant_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$linkExpirationTimes[$row['participant_id']] = $row['link_expiration'];
			// Remove from participant_id's array so that they are not used in the next query
			$thisPartKey = array_search($row['participant_id'], $participant_ids);
			unset($participant_ids[$thisPartKey]);
		}
		// Obtain initial send times of non-cached participants and convert to expiration times
		if (!empty($participant_ids)) 
		{
			// Store the survey-level time limit in an array so that we don't recalculate it for every participant
			$surveyTimeLimitSeconds = array();
			// Get initial send time
			$sql = "select p.participant_id, min(q.time_sent) as time_sent, s.survey_id, 
					s.survey_time_limit_days, s.survey_time_limit_hours, s.survey_time_limit_minutes
					from redcap_surveys_participants p, redcap_surveys_emails_recipients e, redcap_surveys_scheduler_queue q, redcap_surveys s		
					where p.participant_email is not null and e.participant_id = p.participant_id and p.survey_id = s.survey_id
					and q.email_recip_id = e.email_recip_id and q.time_sent is not null and q.time_sent != '' 
                   	and p.participant_id in (".prep_implode($participant_ids).") group by p.survey_id, p.participant_id";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Calculate time limit in seconds for this survey
				if (isset($surveyTimeLimitSeconds[$row['survey_id']])) {
					$timeLimitSeconds = $surveyTimeLimitSeconds[$row['survey_id']];
				} else {
					$timeLimitSeconds = $surveyTimeLimitSeconds[$row['survey_id']] 
						= self::calculateSurveyTimeLimit($row['survey_time_limit_days'], $row['survey_time_limit_hours'], $row['survey_time_limit_minutes']);
				}
				// Convert initial send time to expiration time using time limit
				if ($timeLimitSeconds > 0) {
					$linkExpirationTime = $linkExpirationTimes[$row['participant_id']] = date("Y-m-d H:i:s", strtotime($row['time_sent']) + $timeLimitSeconds);
				} else {
					$linkExpirationTime = $linkExpirationTimes[$row['participant_id']] = "";
				}
				// Add to participant table to cache it for the future
				$sql = "update redcap_surveys_participants 
						set link_expiration = ".checkNull($linkExpirationTime).", link_expiration_override = 0 
						where participant_id = ".$row['participant_id'];
				db_query($sql);
			}
		}
		// Return send times array
		return $linkExpirationTimes;
	}
	
	// Change a participant's Link Expiration time (time limit)
	public static function changeLinkExpiration()
	{
		global $lang;
		if (!isset($_POST['time_limit_expiration']) || !isset($_POST['participant_id']) || !is_numeric($_POST['participant_id'])) exit;
		// Change it
		$expiration = DateTimeRC::format_ts_to_ymd($_POST['time_limit_expiration']).":00";
		$sql = "update redcap_surveys_participants set link_expiration = '".db_escape($expiration)."', link_expiration_override = 1
				where participant_id = '".db_escape($_POST['participant_id'])."'";
		db_query($sql);
		// Display success message
		print 	RCView::div(array('class'=>'darkgreen'),
					RCView::img(array('src'=>'tick.png')) .
					$lang['survey_1123'] . " " .
					RCView::b(DateTimeRC::format_ts_from_ymd($expiration))
				);
	}
	
	// Display dialog for changing a participant's Link Expiration time (time limit)
	public static function changeLinkExpirationRenderDialog()
	{
		global $lang;
		$init_survey_invite_times = self::getLinkExpirationTimes(array($_POST['participant_id']));
		$init_survey_invite_time = DateTimeRC::format_user_datetime($init_survey_invite_times[$_POST['participant_id']], 'Y-M-D_24', null, true);
		print 	RCView::div(array(),
					$lang['survey_1122']
				) .
				RCView::div(array('style'=>'margin-top:15px;'), 
					RCView::b($lang['survey_1126']) . 
					RCView::span(array('id'=>'changeLinkExpirationEmailDup', 'style'=>'color:#A00000;margin-left:10px;'), '')
				) .
				RCView::div(array('style'=>'margin-top:15px;'),
					RCView::b($lang['survey_1125']) . 
					RCView::text(array('id'=>'time_limit_expiration', 'style'=>'margin-left:10px;width:90%;max-width:118px;', 'class'=>'x-form-text x-form-field',
						'value'=>$init_survey_invite_time)) .
					RCView::span(array('class'=>'df','style'=>'padding-left:5px;'), DateTimeRC::get_user_format_label().' H:M') .		
					// 'Expire it now' button
					RCView::span(array('style'=>'color:#777;margin:0 15px;'), '&ndash; '.$lang['global_46'] . ' &ndash;') .
					RCView::button(array('class'=>'btn btn-primaryrc btn-xs', 'style'=>'', 'onclick'=>"
						var timeLimitNow = getCurrentDate('datetime_'+user_date_format_validation)+' '+currentTime('both',0);
						timeLimitNow = timeLimitNow.replace(/-/g, user_date_format_delimiter);
						$('#time_limit_expiration').val(timeLimitNow).effect('highlight',{},2500);"), $lang['survey_1127'])
				);
	}

    // Render the File Repository table for displaying files for PDF Auto-Archiver
    public static function renderPdfAutoArchiveFileTable(&$Proj)
    {
        global $lang, $user_rights, $pdf_econsent_system_ip;
        // Selected record
        $selectedRecord = (isset($_GET['record']) ? $_GET['record'] : '');
        // Display eConsent columns in the table and filter?
        $eConsentSurveyCount = self::pdfAutoArchiverEconsentEnabledCount($Proj);
        $displayEconsentFields = ($eConsentSurveyCount > 0);
        $econsentOnlyFilter = "";
        if (!isset($_GET['econsentOnly']) || $_GET['econsentOnly'] != '1') $_GET['econsentOnly'] = '0';
        if ($displayEconsentFields && count($Proj->surveys) > $eConsentSurveyCount) {
            $econsentOnlyFilter = RCView::select(array('class'=>'x-form-text x-form-field', 'id'=>'filterEconsent', 'onchange'=>"showProgress(1);window.location.href=app_path_webroot+'FileRepository/index.php?pid='+pid+'&type=pdf_archive&record='+$('#filterRecord').val()+($('#filterEconsent').length ? '&econsentOnly='+$('#filterEconsent').val() : '');"),
                array(0=>$lang['docs_70'], 1=>$lang['docs_69']), $_GET['econsentOnly']);
        }
        // Add file into array for display as table
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        // Put file info in array
        $files = self::getPdfAutoArchiveFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null));
        // Build drop-down list of records to filter on
        $recordDropdown = "";
        $recordList = array();
        if (isset($files[0])) {
            $recordList[''] = $lang['docs_68'];
            foreach ($files as $attr) {
                $recordList[$attr['record']] = $attr['record'];
            }
        }
        natcaseksort($recordList);
        if (count($recordList) > 2) {
            $recordDropdown = RCView::select(array('class'=>'x-form-text x-form-field', 'id'=>'filterRecord', 'onchange'=>"showProgress(1);window.location.href=app_path_webroot+'FileRepository/index.php?pid='+pid+'&type=pdf_archive&record='+$('#filterRecord').val()+($('#filterEconsent').length ? '&econsentOnly='+$('#filterEconsent').val() : '');"),
                $recordList, $selectedRecord);
        }
        $rows = '';
        $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordList, false);
        foreach ($files as $file)
        {
            if ($selectedRecord != '' && $selectedRecord != $file['record']) continue;
            // Filter by e-Consent files
            if ($_GET['econsentOnly'] && !($Proj->surveys[$file['survey_id']]['pdf_auto_archive'] == '2' || trim($file['identifier'].$file['version'].$file['type']) != '')) {
                continue;
            }
            // Secondary ID / CRL
            $record_extra_label = isset($extra_record_labels[$file['record']]) ? " ".$extra_record_labels[$file['record']] : "";
            // If this a repeating form or event?
            $isRepeatingFormOrEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingFormOrEvent($file['event_id'], $Proj->surveys[$file['survey_id']]['form_name']));
            // Output row
            $rows .= "<tr>
						<td style='text-align:center;'>
							<span style='display:none;'>".$file['stored_date']."</span>
							".DateTimeRC::format_ts_from_ymd($file['stored_date'])."
						</td>
						<td style='text-align:center;'>
							<a style='text-decoration:underline;' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=".$Proj->project_id."&instance={$file['instance']}&event_id={$file['event_id']}&page={$Proj->surveys[$file['survey_id']]['form_name']}&id={$file['record']}'>".RCView::escape($file['record'])."</a>$record_extra_label
						</td>
						<td>".RCView::escape($Proj->surveys[$file['survey_id']]['title'])."</td>
						".($Proj->longitudinal ? "<td>".RCView::escape($Proj->eventInfo[$file['event_id']]['name_ext'])."</td>" : "")."
						".($hasRepeatingFormsEvents ? "<td class='wrap' style='text-align:center;'>".($isRepeatingFormOrEvent ? $file['instance'] : "")."</td>" : "")."
						".(!$displayEconsentFields ? "" :
                    "<td style='text-align:center;'>".RCView::escape($file['identifier'])."</td>
							".($pdf_econsent_system_ip ? "<td style='text-align:center;'>".RCView::escape($file['ip'])."</td>" : "")."
							<td style='text-align:center;'>".RCView::escape($file['version'])."</td>
							<td style='text-align:center;'>".RCView::escape($file['type'])."</td>"
                )."
						<td style='text-align:center;'>
							".($user_rights['data_export_tool'] == '1'
                    ? "<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$Proj->project_id."&doc_id={$file['doc_id']}' title='".js_escape($lang['docs_58'])."'
									style='text-decoration:none;'><img src='".APP_PATH_IMAGES."download_pdf.gif'></a>"
                    : "<img class='opacity35' src='".APP_PATH_IMAGES."download_pdf.gif'>"
                )."
						</td>
					  </tr>";
        }
        // Set table headers
        $hdrs = "<thead><tr>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;border-left:1px solid #ccc;'>{$lang['survey_1161']}</th>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['global_49']}</th>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['survey_437']}</th>
					".($Proj->longitudinal ? "<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['global_141']}</th>" : "")."
					".($hasRepeatingFormsEvents ? "<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['global_133']}</th>" : "")."
					".(!$displayEconsentFields ? "" :
                "<th class='nowrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['survey_1172']}</th>
						".($pdf_econsent_system_ip ? "<th style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['survey_1221']}</th>" : "")."
						<th style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['survey_1173']}</th>
						<th style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['survey_1174']}</th>"
            )."
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['api_46']}</th>
				</tr></thead>";
        // Build table
        $html = "<div style='max-width:800px;margin:10px 0;'>
				<div style='margin-bottom:20px;'>{$lang['survey_1181']}</div>
				<table id='pdf_autoarchive_table' class='dataTable cell-border' style='table-layout:fixed;margin:10px 0;'>
					$hdrs
					<tbody>$rows</tbody>
				</table>
				</div>";
        // JavaScript
        $downloadAllDisabled = ($user_rights['data_export_tool'] == '1') ? "" : "disabled";
        $html .= "<script type='text/javascript'>
				$(function() {
					$('#pdf_autoarchive_table').DataTable( {
						'autoWidth': false,
						'processing': true,
						'paging': true,
						'pageLength': 10,
						'info': false,
						'aaSorting': [],
						'fixedHeader': { header: true, footer: false },
						'searching': true,
						'ordering': true,
						'oLanguage': { 'sSearch': '' }
					} );
					$('#pdf_autoarchive_table_filter input[type=search]').attr('type','text').prop('placeholder','Search');
					$('#pdf_autoarchive_table_filter').parent().parent().find('div.col-md-6:eq(0) label').css('font-weight','normal');
					$('#pdf_autoarchive_table_filter').parent().parent().find('div.col-md-6:eq(0)').removeClass('col-md-6').addClass('col-md-9').append('".js_escape(
                RCView::div(array('class'=>'float-right', 'style'=>''), RCView::button(array('class'=>'btn btn-xs btn-defaultrc', $downloadAllDisabled=>$downloadAllDisabled, 'style'=>'color:green;font-size:13px;', 'onclick'=>"window.location.href=app_path_webroot+'FileRepository/file_download.php?pid=".$Proj->project_id."&doc_id=pdf_archive_all';"), '<i class="fas fa-file-archive"></i> '.$lang['docs_71'] )) .
                RCView::div(array('class'=>'float-left', 'style'=>'margin-left:20px;'), $lang['survey_45'].RCView::SP.RCView::SP.$recordDropdown) .
                RCView::div(array('class'=>'float-left', 'style'=>'margin-left:20px;margin-top:5px;'), ($econsentOnlyFilter=='' ? '' : $lang['survey_45'].RCView::SP.RCView::SP.$econsentOnlyFilter))
            )."');
					$('#pdf_autoarchive_table_filter').parent().parent().find('div.col-md-6:eq(0)').removeClass('col-md-6').addClass('col-md-3');
					$('#pdf_autoarchive_table img.opacity35').click(function(){
						simpleDialog('".js_escape($lang['docs_67'])."');
					});
				});
				</script>";
        // Return html
        return $html;
    }

    // Output the JavaScript for survey invitation checking for [survey-link] in compose text
    public function renderCheckComposeForSurveyLink()
    {
        global $lang;
        ?>
        <script type="text/javascript">
            // Reminder to add [survey-link] to email compose text
            function checkComposeForSurveyLink(ob) {
                var text = $(ob).val().trim();
                var select = $('select[name="delivery_type"]');
                // Detect any hard-coded survey links
                var regex = /(?:(?:https?):\/\/|www\.)(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[-A-Z0-9+&@#\/%=~_|$?!:,.])*(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[A-Z0-9+&@#\/%=~_|$])/igm,
                    matches, i=0, urls = new Array();
                while (matches = regex.exec(text)) {
                    if (starts_with(matches[0], app_path_webroot_full+'surveys/')) {
                        if (!in_array(matches[0], urls)) {
                            urls[i++] = matches[0];
                        }
                    }
                }
                if (urls.length > 0) {
                    simpleDialog('<?php print js_escape($lang['survey_1228']) ?>'+"<br><br>"+
                        '<?php print js_escape($lang['survey_1229']) ?>'+"<ul><li>"+urls.join("</li><li>")+"</li></ul>",
                        '<?php print js_escape($lang['survey_1230']) ?>',
                        'remove-hardcoded-links-warning',650,null,'<?php print js_escape($lang['design_401']) ?>');
                    return;
                }
                // If has survey link, then return
                if (!(text.indexOf('[survey-link]') < 0 && text.indexOf('[survey-url]') < 0)) return;
                // Get invitation type
                var suggestLink = (!select.length || select.val() == 'EMAIL' || select.val() == 'SMS_INVITE_WEB');
                if (!suggestLink) return;
                if (select.val() == 'SMS_INVITE_WEB') {
                    var textAppend = text+(text != '' ? ' -- ' : '')+'<?php print js_escape($lang['survey_956']) ?>'+" [survey-url]";
                } else {
                    var textAppend = text+(text == '' ? '<p><?php print js_escape($lang['survey_1224']) ?>' : '')+"</p><p>"+'<?php print js_escape($lang['survey_134']) ?>'+
                        "<br/>[survey-link]</p><p>"+'<?php print js_escape($lang['survey_135']) ?>'+"<br/>[survey-url]</p><p>"+'<?php print js_escape($lang['survey_137']) ?></p>';
                }
                // Dialog
                simpleDialog('<?php print js_escape($lang['piping_48']) ?>','<?php print js_escape($lang['global_03'].$lang['colon']." ".$lang['piping_49']) ?>','checkComposeForSurveyLink',600,null,
                    '<?php print js_escape($lang['piping_50']) ?>',
                    function(){
                        $(ob).val(textAppend);
                        try {
                            tinyMCE.activeEditor.setContent(textAppend);
                        } catch(e) { }
                    },'<?php print js_escape($lang['piping_51']) ?>');
                $('#checkComposeForSurveyLink').parent().find('.ui-dialog-buttonpane button:eq(1)').css('font-weight','bold');
            }
        </script>
        <?php
    }

    // Get the default append_survey_link value that should be set in redcap_emails table
    // (this is mostly to deal with the fact that it could cause issues with not appending survey links inside emails during mid-upgrade)
    public function getAppendSurveyLink($delivery_type='EMAIL')
    {
        // If delivery_type is not EMAIL nor SMS_INVITE_WEB, then always append link
        if ($delivery_type != 'EMAIL' && $delivery_type != 'SMS_INVITE_WEB') return '1';
        // Set the min version at which append_survey_link should be "0"
        $versionsWithOne = '8.4.0';
        // If on this version or higher, return 0, else 1
        return (REDCap::versionCompare(REDCAP_VERSION, $versionsWithOne) >= 0 ? '0' : '1');
    }

    // Return array of users who were contributors to a survey response
    public function getResponseContributors($response_id, $returnOnlyAfterCompletion=false)
    {
        global $user_rights;
        $contributors = array();
        // Get the record, event, form, etc.
        $sql = "select s.project_id, s.form_name, p.event_id, r.record, r.instance, r.completion_time
				from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r
				where s.survey_id = p.survey_id and p.participant_id = r.participant_id and r.response_id = $response_id limit 1";
        $q = db_query($sql);
        $attr = db_fetch_assoc($q);
        $Proj = new Project($attr['project_id']);
        // Users who have edited response SINCE COMPLETION
        $surveyFieldsLogging = array();
        foreach (array_keys($Proj->forms[$attr['form_name']]['fields']) as $this_field) {
            // Do not check for record ID field or calc fields
            if ($this_field == $Proj->table_pk || $Proj->metadata[$this_field]['element_type'] == 'calc') {
                continue;
            }
            $surveyFieldsLogging[] = $this_field;
        }
        // Ensure user can access this info (must have Data Entry Form level access)
        if (isset($user_rights['forms'][$attr['form_name']]) && $user_rights['forms'][$attr['form_name']] == '0') {
            return false;
        }
        // If we want to return only contributers AFTER the survey was completed
        $sqlComplete = "";
        if ($returnOnlyAfterCompletion) {
            if ($attr['completion_time'] == "") {
                // The survey has not been completed yet, so return no one
                return array();
            }
            $sqlComplete = " and ts > " . str_replace(array('-',' ',':'), array('','',''), $attr['completion_time']);
        }
        // Build query
        $sql2 = "and (data_values like '%\\n" . implode(" = \'%' or data_values like '%\\n", $surveyFieldsLogging) . " = \'%'
				or data_values like '" . implode(" = \'%' or data_values like '", $surveyFieldsLogging) . " = \'%')";
        $sql = "select distinct user from ".Logging::getLogEventTable($attr['project_id'])." where project_id = " . $attr['project_id'] . "
				and event in ('UPDATE','INSERT') and object_type = 'redcap_data'
				and pk = '" . db_escape($attr['record']) . "' and event_id = {$attr['event_id']} $sqlComplete" .
            //" and instance ".($attr['instance'] == '1' ? "is NULL" : "= ".$_GET['instance'])
            // Search only for fields on the CURRENT form (this *might* produce false positives if a
            // variable name matches insisde another variable's name in the data values log). For API imports,
            // we'll check the actualy fields listed in the log because API scripts can import multiple forms at once.
            " $sql2";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $contributors[] = $row['user'];
        }
        sort($contributors);
        return $contributors;
    }

    // Enable/disable Google reCaptcha
    public function enableCaptcha($enable=0)
    {
        $enable = (int)$enable;
        $sql = "update redcap_projects set google_recaptcha_enabled = $enable where project_id = ".PROJECT_ID;
        $q = db_query($sql);
        if ($q) {
            $descrip = ($enable ? "Enable" : "Disable") . " Google reCAPTCHA on public survey";
            Logging::logEvent($sql,"redcap_projects","manage",PROJECT_ID,"google_recaptcha_enabled = $enable", $descrip);
            return true;
        }
        return false;
    }
	
	// Obtain the name, dob, econsent version and type for this record/form
	public static function getEconsentOptionsData($project_id, $record, $form)
	{
		global $lang;
		$Proj = new Project($project_id);
		$surveySettings = $Proj->surveys[$Proj->forms[$form]['survey_id']];
		// Set version and type string
		$versionText = $typeText = "";
		if ($surveySettings['pdf_econsent_version'] != '') {
			$versionText = $surveySettings['pdf_econsent_version'];
		}
		if ($surveySettings['pdf_econsent_type'] != '') {
			$typeText = $surveySettings['pdf_econsent_type'];
		}		
		// Validate the event_ids
		$fields = $events = array();
		if ($surveySettings['pdf_econsent_firstname_field'] != '') {
			if (!isset($Proj->eventInfo[$surveySettings['pdf_econsent_firstname_event_id']])) {
				$surveySettings['pdf_econsent_firstname_event_id'] = $Proj->firstEventId;
			}
			$fields[] = $surveySettings['pdf_econsent_firstname_field'];
			$events[] = $surveySettings['pdf_econsent_firstname_event_id'];
		}
		if ($surveySettings['pdf_econsent_lastname_field'] != '') {
			if (!isset($Proj->eventInfo[$surveySettings['pdf_econsent_lastname_event_id']])) {
				$surveySettings['pdf_econsent_lastname_event_id'] = $Proj->firstEventId;
			}
			$fields[] = $surveySettings['pdf_econsent_lastname_field'];
			$events[] = $surveySettings['pdf_econsent_lastname_event_id'];
		}
		if ($surveySettings['pdf_econsent_dob_field'] != '') {
			if (!isset($Proj->eventInfo[$surveySettings['pdf_econsent_dob_event_id']])) {
				$surveySettings['pdf_econsent_dob_event_id'] = $Proj->firstEventId;
			}
			$fields[] = $surveySettings['pdf_econsent_dob_field'];
			$events[] = $surveySettings['pdf_econsent_dob_event_id'];
		}
		// Get data for these fields
		$nameDobText = $wholename = $firstname = $lastname = $dob = "";
		if (!empty($fields)) {
			$data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
			// Combine first/last name, if separate (can also be single field with whole name)
            // First name
			$thisFieldForm = $Proj->metadata[$surveySettings['pdf_econsent_firstname_field']]['form_name'];
            $thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['pdf_econsent_firstname_event_id'], $thisFieldForm);
			$thisFieldRepeatInstrument = "";
            if ($thisFieldRepeating) {
				$thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['pdf_econsent_firstname_event_id'], $thisFieldForm) ? $thisFieldForm : "";
            }
			if ($surveySettings['pdf_econsent_firstname_field'] != '') {
			    if ($thisFieldRepeating) {
					$firstname = $data[$record]['repeat_instances'][$surveySettings['pdf_econsent_firstname_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['pdf_econsent_firstname_field']];
                } else {
			        $firstname = $data[$record][$surveySettings['pdf_econsent_firstname_event_id']][$surveySettings['pdf_econsent_firstname_field']];
				}
			}
			// Last name
			$thisFieldForm = $Proj->metadata[$surveySettings['pdf_econsent_lastname_field']]['form_name'];
			$thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['pdf_econsent_lastname_event_id'], $thisFieldForm);
			$thisFieldRepeatInstrument = "";
			if ($thisFieldRepeating) {
				$thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['pdf_econsent_lastname_event_id'], $thisFieldForm) ? $thisFieldForm : "";
			}
			if ($surveySettings['pdf_econsent_lastname_field'] != '') {
				if ($thisFieldRepeating) {
					$lastname = $data[$record]['repeat_instances'][$surveySettings['pdf_econsent_lastname_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['pdf_econsent_lastname_field']];
				} else {
					$lastname = $data[$record][$surveySettings['pdf_econsent_lastname_event_id']][$surveySettings['pdf_econsent_lastname_field']];
				}
			}
			// DOB
			$thisFieldForm = $Proj->metadata[$surveySettings['pdf_econsent_dob_field']]['form_name'];
			$thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['pdf_econsent_dob_event_id'], $thisFieldForm);
			$thisFieldRepeatInstrument = "";
			if ($thisFieldRepeating) {
				$thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['pdf_econsent_dob_event_id'], $thisFieldForm) ? $thisFieldForm : "";
			}
			if ($surveySettings['pdf_econsent_dob_field'] != '') {
				if ($thisFieldRepeating) {
					$dob = $data[$record]['repeat_instances'][$surveySettings['pdf_econsent_dob_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['pdf_econsent_dob_field']];
				} else {
					$dob = $data[$record][$surveySettings['pdf_econsent_dob_event_id']][$surveySettings['pdf_econsent_dob_field']];
				}
				$dob = trim($dob);
			}
			// Add to aray
			$nameDobArray = array();
			$wholename = trim("$firstname $lastname");
			if ($wholename != '') $nameDobArray[] = $wholename;
			if ($dob != '') $nameDobArray[] = $dob;
			$nameDobText = trim(implode(', ', $nameDobArray));
		}
		// Return both the name/dob and version/type as 2 parts of an array
		return array($nameDobText, $versionText, $typeText);
	}
	
	// Store a completed survey response as a PDF in the File Repository. Return boolean on whether successful.
	public static function archiveResponseAsPDF($record, $event_id, $form, $instance)
	{
		global $Proj, $pdf_econsent_system_enabled, $pdf_auto_archive, $pdf_econsent_system_ip, $lang, $pdf_custom_header_text;
		// If survey is offline, then do nothing
        if ($Proj->surveys[$Proj->forms[$form]['survey_id']]['survey_enabled'] == '0') return false;
		// For eConsent, get eConsent Options data
		$nameDobText = $versionText = $typeText = $nameDobFilename = "";
		if ($pdf_econsent_system_enabled && $pdf_auto_archive == 2) {
			list ($nameDobText, $versionText, $typeText) = self::getEconsentOptionsData($Proj->project_id, $record, $form);
			$versionTypeTextArray = array();
			if ($nameDobText != '')	$versionTypeTextArray[] = $nameDobText;
			if ($versionText != '')	$versionTypeTextArray[] = $lang['data_entry_428']." ".$versionText;
			if ($typeText != '') 	$versionTypeTextArray[] = $lang['data_entry_429']." ".$typeText;
			$versionTypeText = implode(', ', $versionTypeTextArray);
			// Format the name+dob+pid+record+form to put in the PDF filename
			$nameDobFilename = str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z- ]/", "", $nameDobText))) . "_";
		}
		// Format the name+dob+pid+record+form to put in the PDF filename
		$recordFilename = str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z- ]/", "", $record)));
		$formFilename = str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z- ]/", "", $Proj->forms[$form]['menu'])));
		$pdf_filename = APP_PATH_TEMP . $nameDobFilename . "pid" . $Proj->project_id . "_form" . $formFilename . "_id" . $recordFilename . "_" . date('Y-m-d_His') . ".pdf";
		// Obtain the compact PDF of the response
		$pdf_contents = REDCap::getPDF($record, $form, $event_id, false, $instance, true, $pdf_custom_header_text, $versionTypeText);
		// Temporarily store file in temp
		file_put_contents($pdf_filename, $pdf_contents);
		// Add PDF to edocs_metadata table		
		$pdfFile = array('name'=>basename($pdf_filename), 'type'=>'application/pdf', 
						 'size'=>filesize($pdf_filename), 'tmp_name'=>$pdf_filename);
		$pdf_edoc_id = Files::uploadFile($pdfFile);
		unlink($pdf_filename);
		if ($pdf_edoc_id == 0) return false;
		// Get survey_id from form
		$survey_id = $Proj->forms[$form]['survey_id'];
		// Add values to redcap_surveys_pdf_archive table
		$ip = $pdf_econsent_system_ip ? System::clientIpAddress() : "";
		$sql = "insert into redcap_surveys_pdf_archive (doc_id, record, event_id, survey_id, instance, identifier, version, type, ip) values
				($pdf_edoc_id, '".db_escape($record)."', '".db_escape($event_id)."', '".db_escape($survey_id)."', '".db_escape($instance)."', 
				".checkNull($nameDobText).", ".checkNull($versionText).", ".checkNull($typeText).", ".checkNull($ip).")";
		$q = db_query($sql);
		// If project has External Storage enabled for the e-Consent PDF Auto-Archiver, then store file on that external server
		if ($pdf_econsent_system_enabled && $pdf_auto_archive == 2 && $GLOBALS['pdf_econsent_filesystem_type'] != '') {
			$storedFileExternal = Files::writeFilePdfAutoArchiverToExternalServer(basename($pdf_filename), $pdf_contents);
		}		
		// Return boolean on success
		return $q;
	}
	
	// Return boolean based on whether any survey in the project has the PDF Auto-Archiver enabled
	public static function pdfAutoArchiverEnabled(&$Proj)
	{
		// Loop through all surveys
		foreach ($Proj->surveys as $attr) {
			if ($attr['pdf_auto_archive']) return true;
		}
		return false;
	}
	
	// Return boolean based on whether any survey in the project has the PDF Auto-Archiver + ECONSENT enabled
	public static function pdfAutoArchiverEconsentEnabled(&$Proj)
	{
		global $pdf_econsent_system_enabled;
		// Loop through all surveys
		if ($pdf_econsent_system_enabled) {
			foreach ($Proj->surveys as $attr) {
				if ($attr['pdf_auto_archive'] == '2') return true;
			}
		}
		return false;
	}
	
	// Return a count of the number of surveys in the project that have the PDF Auto-Archiver + ECONSENT enabled
	public static function pdfAutoArchiverEconsentEnabledCount(&$Proj)
	{
		global $pdf_econsent_system_enabled;
		$count = 0;
		// Loop through all surveys
		if ($pdf_econsent_system_enabled) {
			foreach ($Proj->surveys as $attr) {
				if ($attr['pdf_auto_archive'] == '2') $count++;
			}
		}
		return $count;
	}
	
	// Get all files stored by PDF Auto-Archiver. If provide $doc_id, then return just that file's attributes as an array.
	public static function getPdfAutoArchiveFiles(&$Proj, $group_id=null, $doc_id=null) 
	{
		// Filter by DAG, if needed
		$dagsql = "";
		if (is_numeric($group_id)) {
			$dagsql = "and a.record in (" . prep_implode(Records::getRecordList($Proj->project_id, $group_id)) . ")";
		}
		// Query table
		$files = array();
		$sql = "select e.stored_date, e.doc_size, e.doc_name, a.*
				from redcap_surveys_pdf_archive a, redcap_edocs_metadata e
				where e.doc_id = a.doc_id and a.event_id in (".prep_implode(array_keys($Proj->eventInfo)).")
				and a.survey_id in (".prep_implode(array_keys($Proj->surveys)).") $dagsql
				and e.delete_date is null and e.project_id = " . $Proj->project_id;
		if (is_numeric($doc_id)) {
			$sql .= " and e.doc_id = $doc_id";
		}
		$sql .= " order by e.doc_id desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($doc_id !== null) return $row;
			else $files[] = $row;			
		}
		return $files;
	}
	
	
	// Display e-Consent PDF confirmation page, if applicable
	public static function renderEconsentPdfFrame()
	{
		global $lang, $hide_back_button, $isTablet, $isMobileDevice, $isIOS, $pdf_custom_header_text;
		// Get survey URL for this participant	
		$private_link = REDCap::getSurveyLink($_GET['id'], $_GET['page'], $_GET['event_id'], $_GET['instance']);
		$return_code = Survey::getSurveyReturnCode($_GET['id'], $_GET['page'], $_GET['event_id'], $_GET['instance'], true);
		$pdfUrl = "$private_link&compact=1&display=inline&return_code=$return_code&instance={$_GET['instance']}&hideSurveyTimestamp=1&route=PdfController:index&__passthru=index.php";
		// Append anything to header/footer of PDF?
		list ($nameDobText, $versionText, $typeText) = self::getEconsentOptionsData(PROJECT_ID, $_GET['id'], $_GET['page']);
		$versionTypeTextArray = array();
		if ($nameDobText != '')	$versionTypeTextArray[] = $nameDobText;
		if ($versionText != '')	$versionTypeTextArray[] = $lang['data_entry_428']." ".$versionText;
		if ($typeText != '') 	$versionTypeTextArray[] = $lang['data_entry_429']." ".$typeText;
		$versionTypeText = implode(', ', $versionTypeTextArray);
		if ($versionTypeText != "") $pdfUrl .= "&appendToFooter=".urlencode($versionTypeText);
		if ($pdf_custom_header_text != "") $pdfUrl .= "&appendToHeader=".urlencode($pdf_custom_header_text);
		// Javscript file
        loadJS('eConsent.js');
		// Determine if we're using signature fields that might need to be reset if participant clicks Previous Page button
        if (self::surveyHasEconsentSignatureValues(PROJECT_ID, $_GET['id'], $_GET['page'], $_GET['event_id'], $_GET['instance'])) {
            ?>
            <script type="text/javascript">
                var langSig = {};
                langSig.global_53 = '<?=js_escape($lang['global_53'])?>';
                langSig.survey_1266 = '<?=js_escape($lang['survey_1266'])?>';
                $(function(){ resetSignatureValuesPrep(); });
            </script>
            <div id="resetSignatureValuesDialog" class="simpleDialog" title="<?=js_escape2($lang['survey_1264'])?>">
                <?=$lang['survey_1265']?>
            </div>
            <?php
        }
		// Output iframe html
		$backBtnText = $hide_back_button ? "" : "<div style='margin:0 10px 15px;text-align:center;font-size:14px;'>{$lang['survey_1170']}</div>";
		$height = ($isTablet && $isIOS) ? "700px" : "400px";
		print  "<div style='margin:20px;font-size:14px;'>{$lang['survey_1169']}</div>
				<div style='margin:5px;padding:3px;border:1px solid #ccc;'>
					".($isIOS || $isMobileDevice
                        ? "<div class='p-2'>This browser does not support inline PDFs. Please open the PDF <b>in a new tab</b> to view it: 
                            <button class='btn btn-xs btn-defaultrc ml-2 fs14' style='color:#C00000;' onclick='window.open(\"$pdfUrl\", \"_blank\");'><i class='far fa-file-pdf'></i> View PDF</button>
                            </div>"
                        : "<object data='$pdfUrl' type='application/pdf' style='width:100%;height:$height;'>
                            <iframe src='$pdfUrl' style='border:none;width:100%;height:$height;'>
                            This browser does not support PDFs. Please download the PDF <b>in a new tab</b> to view it: <a target='_blank' href='$pdfUrl'>Download PDF</a>
                            </iframe>
                            </object>"
                    )."
				</div>
				<div id='econsent_confirm_checkbox_div' class='yellow' style='font-size:14px;margin:20px 20px 10px;'>
					<label id='econsent_confirm_checkbox_label' class='opacity50' style='text-indent:-22px;margin-left:40px;cursor:pointer;'>
						<input type='checkbox' id='econsent_confirm_checkbox'> {$lang['survey_1171']} 
					</label>
				</div>
				$backBtnText";
	}

    // e-Consent Framework: Erase all signature field values before loading the survey page
    public static function eraseEconsentSignatures($project_id, $record, $form, $event_id, $instance=1)
    {
        // Make sure that the e-consent signature fields are set and have data and that the participant just clicked the Prev Page button while on the last page
        if (!(isset($_GET['__prevpage']) && isset($_GET['__es'])
            && self::surveyHasEconsentSignatureValues($project_id, $record, $form, $event_id, $instance))) {
            return false;
        }
        // Remove flag
        unset($_GET['__es']);
        // Erase the values
        return self::eraseEconsentSignatureValues($project_id, $record, $form, $event_id, $instance);
    }

    // Erase the participant's signature values and log this event
    public static function eraseEconsentSignatureValues($project_id, $record, $form, $event_id, $instance=1)
    {
        global $lang;
        $Proj = new Project($project_id);
        // Get signature fields designated for this survey
        $sigFields = self::getEconsentSignatureFields($project_id, $form);
        if (empty($sigFields)) return false;
        // Set them as blank in POST array so that they don't get resubmitted on this save
        $fieldOrder = array();
        foreach ($sigFields as $field) {
            $_POST[$field] = '';
            $fieldOrder[$field] = $Proj->metadata[$field]['field_order'];
        }
        // Add flag to help us determine the page number downstream
        $min = min($fieldOrder);
        $_GET['__esf'] = array_search($min, $fieldOrder);
        // Return true since we got this far
        return true;
    }

    // Get all options for drop-down displaying fields that could be used as e-Consent signature fields (text boxes + wet signature)
    public static function getFieldDropdownOptionsSignatures($project_id, $form, $returnOnlyRequiredFields=false)
    {
        global $lang;
        $Proj = new Project($project_id);
        $rc_fields = array();
        $rc_fields[''] = '-- '.$lang['random_02'].' --';
        // Build an array of drop-down options listing all REDCap fields
        foreach (array_keys($Proj->forms[$form]['fields']) as $this_field)
        {
            $attr1 = $Proj->metadata[$this_field];
            if ($this_field == $Proj->table_pk) continue;
            if ($attr1['element_type'] != 'textarea'
                && !($attr1['element_type'] == 'text' && ($attr1['element_validation_type'] == '' || $attr1['element_validation_type'] == 'int' || $attr1['element_validation_type'] == 'float'))
                && !($attr1['element_type'] == 'file' && $attr1['element_validation_type'] == 'signature')
            ) {
                continue;
            }
            // Return only required fields?
            if ($returnOnlyRequiredFields && $attr1['field_req'] != '1') continue;
            // Clean the label
            $attr1['element_label'] = trim(str_replace(array("\r\n", "\n"), array(" ", " "), strip_tags($attr1['element_label'])));
            // Truncate label if long
            if (strlen($attr1['element_label']) > 65) {
                $attr1['element_label'] = trim(substr($attr1['element_label'], 0, 47)) . "... " . trim(substr($attr1['element_label'], -15));
            }
            $rc_fields[$this_field] = "$this_field \"{$attr1['element_label']}\"";
        }
        // Return all options
        return $rc_fields;
    }

    // Return boolean if a survey instrument has e-Consent signature fields designated that have values
    public static function surveyHasEconsentSignatureValues($project_id, $record, $form, $event_id, $instance=1)
    {
        global $lang;
        $Proj = new Project($project_id);
        // Get signature fields designated for this survey
        $sigFields = self::getEconsentSignatureFields($project_id, $form);
        if (empty($sigFields)) return false;
        // Get data
        $sql = "select count(1) from redcap_data where project_id = $project_id and record = ".checkNull($record)."
                and event_id = ".checkNull($event_id)." and field_name in (".prep_implode($sigFields).")
                and instance ".($instance == '1' ? "is null" : "= '$instance' and value != ''");
        $q = db_query($sql);
        return (db_result($q, 0) > 0);
    }

    // Return array of all e-Consent signature fields designated for a given survey
    public static function getEconsentSignatureFields($project_id, $form)
    {
        global $lang;
        $Proj = new Project($project_id);
        $fields = array();
        $survey_id = $Proj->forms[$form]['survey_id'];
        $surveySettings = $Proj->surveys[$survey_id];
        // Loop through all signature fields
        for ($sn=1; $sn<=self::numEconsentSignatureFields; $sn++) {
            $this_pdf_econsent_signature_field = 'pdf_econsent_signature_field'.$sn;
            if (isset($surveySettings[$this_pdf_econsent_signature_field]) && $surveySettings[$this_pdf_econsent_signature_field] != '') {
                $fields[] = $surveySettings[$this_pdf_econsent_signature_field];
            }
        }
        return $fields;
    }

	// Return the Public Survey Link of a survey required for creating a project, moving to prod, moving to analysis mode, marking as completed
	public static function getProjectStatusPublicSurveyLink($config_key)
	{
	    $pid = $GLOBALS[$config_key];
	    if (!is_numeric($pid)) return '';
		$Proj = new Project($pid);
		$hash = self::getSurveyHash(null, null, null, $Proj);
		if ($hash == '') return '';
		return APP_PATH_SURVEY_FULL."index.php?s=$hash";
	}

	// If this project's public survey is used as a custom project status transition survey, then output custom JS that will be executed inside an iframe
	public static function outputCustomJavascriptProjectStatusPublicSurveyCompleted($current_project_id)
	{
		if (!is_numeric($current_project_id)) return;
	    // Is this project's public survey registered in redcap_config?
		$config_keys = array('survey_pid_create_project', 'survey_pid_move_to_prod_status', 'survey_pid_move_to_analysis_status', 'survey_pid_mark_completed');
		$config_key = '';
		foreach ($config_keys as $this_config_key) {
			if ($GLOBALS[$this_config_key] == $current_project_id) {
				$config_key = $this_config_key;
                break;
			}
        }
		if ($config_key == '') return;
		// Custom action based on config key
        $js = "";
        if ($config_key == 'survey_pid_create_project') {
            $js = 'window.parent.document.createdb.submit();';
        } elseif ($config_key == 'survey_pid_move_to_prod_status') {
            if ($GLOBALS['superusers_only_move_to_prod'] == '1') {
				$js = "window.parent.window.doChangeStatus2();";
            } else {
				$randomizationStatus = ($GLOBALS['randomization'] && Randomization::setupStatus()) ? '1' : '0';
				$randProdAllocTableExists = ($randomizationStatus == '1' && Randomization::allocTableExists(1)) ? '1' : '0';
				$js = "window.parent.window.doChangeStatus(0,'','',$randomizationStatus,$randProdAllocTableExists);";
            }
		} elseif ($config_key == 'survey_pid_move_to_analysis_status') {
			$js = "window.parent.window.btnMoveToProd();";
		} elseif ($config_key == 'survey_pid_mark_completed') {
			$js = "window.parent.window.markProjectAsCompleted();";
		}
        // Output JS and call exit()
        if ($js == '') return;
		exit(  "<script type='text/javascript'>
                if (inIframe()) { 
                    document.body.innerHTML = '';
                    $(function(){
                        showProgress(1); 
                        $js                        
                    })
                }
                </script>");
	}

	// Retrieve survey_id using form_name
	public static function getSurveyId($form_name = null)
	{
		global $Proj;
		if (empty($form_name)) $form_name = $Proj->firstForm;
		return (isset($Proj->forms[$form_name]['survey_id']) ? $Proj->forms[$form_name]['survey_id'] : "");
	}

	// Creates unique results_code (that is, unique within that survey) and returns that value
	public static function getUniqueResultsCode($survey_id=null)
	{
		if (!is_numeric($survey_id)) return false;
		do {
			// Generate a new random hash
			$code = strtolower(generateRandomHash(8));
			// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
			$sql = "select r.results_code from redcap_surveys_participants p, redcap_surveys_response r
					where p.participant_id = r.participant_id and p.survey_id = $survey_id and r.results_code = '$code' limit 1";
			$codeExists = (db_num_rows(db_query($sql)) > 0);
		} while ($codeExists);
		// Code is unique, so return it
		return $code;
	}

	// Exit the survey and give message to participant
	public static function exitSurvey($text, $largeFont=true, $closeSurveyBtnText=null, $justCompletedSurvey=true)
	{
		global $lang, $text_to_speech, $font_family, $text_size, $theme, $custom_theme_attr;

		// If paths have not been set yet, call functions that set them (need paths set for HtmlPage class)
		if (!defined('APP_PATH_WEBROOT'))
		{
			// Pull values from redcap_config table and set as global variables
			System::setConfigVals();
			// Set directory definitions
			System::defineAppConstants();
		}

		// If a Twilio SMS or Voice Call
		if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']))
		{
			// Initialize Twilio
			TwilioRC::init();
			// An invalid choice was entered
			if (SMS) {
				// Instantiate a new Twilio Rest Client
				$twilioClient = TwilioRC::client();
				TwilioRC::sendSMS(strip_tags($text), $_POST['From'], $twilioClient);
			} else {
				// Set voice and language attributes for all Say commands
				$language = TwilioRC::getLanguage();
				$voice = TwilioRC::getVoiceGender();
				$say_array = array('voice'=>$voice, 'language'=>$language);
				// Set header to output TWIML/XML
				header('Content-Type: text/xml');
				// Output Twilio TwiML object
				$twiml = new Services_Twilio_Twiml();
				$twiml->say(strip_tags($text), $say_array);
			}
			exit;
		}

		// Class for html page display system
		$objHtmlPage = new HtmlPage();
		$objHtmlPage->addExternalJS(APP_PATH_JS . "FontSize.js");
		$objHtmlPage->addExternalJS(APP_PATH_JS . "Survey.js");
		$objHtmlPage->addExternalJS(APP_PATH_JS . "DataEntrySurveyCommon.js");
		if (($text_to_speech == '1' && (!isset($_COOKIE['texttospeech']) || $_COOKIE['texttospeech'] == '1'))
			|| ($text_to_speech == '2' && isset($_COOKIE['texttospeech']) && $_COOKIE['texttospeech'] == '1')) {
			$objHtmlPage->addExternalJS(APP_PATH_JS . "TextToSpeech.js");
		}
		$objHtmlPage->addStylesheet("survey.css", 'screen,print');
		// Set the font family
		$objHtmlPage = Survey::applyFont($font_family, $objHtmlPage);
		// Set the size of survey text
		$objHtmlPage = Survey::setTextSize($text_size, $objHtmlPage);
		// If survey theme is being used, then apply it here
		$objHtmlPage = Survey::applyTheme($theme, $objHtmlPage, $custom_theme_attr);
		$objHtmlPage->PrintHeader();
		print "<div style='margin:10px;'>";
		// Display a "close" button at top
		if ($closeSurveyBtnText !== false) {
			print 	RCView::div(array('style'=>'padding:10px 10px 0;'),
				RCView::button(array('onclick'=>"
				            var pidurl = (typeof pid == 'undefined') ? '' : '&pid='+pid;
							try{ modifyURL('index.php?__closewindow=1'+pidurl); }catch(e){} 
							try{ window.open('', '_self', ''); }catch(e){} 
							try{ window.close(); }catch(e){} 
							try{ window.top.close(); }catch(e){} 
							try{ open(window.location, '_self').close(); }catch(e){} 
							try{ self.close(); }catch(e){} 
							window.location.href = app_path_webroot_full+'surveys/index.php?__closewindow=1'+pidurl;
						", 'class'=>'jqbuttonmed'),
					($closeSurveyBtnText == null ? $lang['dataqueries_278'] : $closeSurveyBtnText)
				)
			);
		}
		// Display the text
		if ($largeFont) {
			print "<div style='font-size: 16px;margin:30px 0;font-weight:bold;'>$text</div>";
		} else {
			print "<div style='margin:30px 0 0;line-height: 1.5em;'>$text</div>";
		}
		// Only do the following if we just completed a survey
		if ($justCompletedSurvey) {
			global $fetched, $Proj, $pdf_auto_archive;
			// Store a completed survey response as a PDF in the File Repository
			if ($pdf_auto_archive > 0) {
				Survey::archiveResponseAsPDF($fetched, $_GET['event_id'], $_GET['page'], $_GET['instance']);
			}
			// REDCap Hook injection point: Pass project/record/survey attributes to method
			$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
			if (!is_numeric($group_id)) $group_id = null;
			$response_id = isset($_POST['__response_id__']) ? $_POST['__response_id__'] : '';
			if ($response_id == '' && isset($_GET['__rh'])) {
				$response_id = Survey::decryptResponseHash($_GET['__rh'], $GLOBALS['participant_id']);
			}
			if (!isset($_GET['__endpublicsurvey'])) { // Don't call this hook again; it has already been called before redirecting with __endpublicsurvey in the URL
				Hooks::call('redcap_survey_complete', array(PROJECT_ID, (is_numeric($response_id) || $fetched != '' ? $fetched : null), $_GET['page'], $_GET['event_id'], $group_id, $_GET['s'], $response_id, $_GET['instance']));
				Survey::outputCustomJavascriptProjectStatusPublicSurveyCompleted(PROJECT_ID);
			}
			## Destroy the session on server and session cookie in user's browser
			$_SESSION = array();
			session_unset();
			session_destroy();
			unset($_COOKIE['survey']);
			deletecookie('survey');
			// To prevent refreshing the page and resubmitting data, redirect for force a GET request to end the survey
            if ($_SERVER['REQUEST_METHOD'] != 'GET' && $GLOBALS['public_survey'] && !isset($_GET['__endpublicsurvey'])) {
				$responseHash = Survey::encryptResponseHash($response_id, $GLOBALS['participant_id']);
				redirect($_SERVER['REQUEST_URI'] . "&__endpublicsurvey=1&__rh=$responseHash");
			}
		}
		// Footer
		print "</div>";
		$objHtmlPage->PrintFooter();
		exit;
	}

	// Return participant_id when passed the hash
	public static function getParticipantIdFromHash($hash=null)
	{
		if ($hash == null) return false;
		$sql = "select participant_id from redcap_surveys_participants where hash = '" . db_escape($hash) . "' limit 1";
		$q = db_query($sql);
		// If participant_id exists, then return it
		return (db_num_rows($q) > 0) ? db_result($q, 0) : false;
	}

	// Make sure the survey belongs to this project
	public static function checkSurveyProject($survey_id)
	{
		global $Proj;
		return (is_numeric($survey_id) && isset($Proj->surveys[$survey_id]));
	}


	// Create array of field names designating their survey page with page number as key
	public static function getPageFields($form_name, $question_by_section)
	{
		global $Proj, $table_pk, $pdf_auto_archive;
		// Set page counter at 1
		$page = 1;
		// Field counter
		$i = 1;
		// Create empty array
		$pageFields = array();
		// Loop through all form fields and designate fields to page based on location of section headers
		foreach (array_keys($Proj->forms[$form_name]['fields']) as $field_name)
		{
			// Do not include record identifier field nor form status field (since they are not shown on survey)
			if ($field_name == $table_pk || $field_name == $form_name."_complete") continue;
			// If field has a section header, then increment the page number (ONLY for surveys that have paging enabled)
			if ($question_by_section && $Proj->metadata[$field_name]['element_preceding_header'] != "" && $i != 1) $page++;
			// Add field to array
			$pageFields[$page][$i] = $field_name;
			// Increment field count
			$i++;
		}
		// If e-consent option is enabled, then auto-add one extra page to end of survey
		if ($pdf_auto_archive == 2) {
			$pageFields[$page+1] = array();
		}
		// Count total pages in the survey
		$totalPages = count($pageFields);
		// Return array
		return array($pageFields, $totalPages);
	}

	// Find the page number that a survey question is on based on variable name
	public static function getQuestionPage($variable,$pageFields)
	{
		$foundField = false;
		foreach ($pageFields as $this_page=>$these_fields) {
			foreach ($these_fields as $this_field) {
				if ($variable == $this_field) {
					// Found the page
					return $this_page;
				}
				if ($foundField) break;
			}
			if ($foundField) break;
		}
		// If not found, set to page 1
		return 1;
	}

	// Obtain a survey return code from a private link's hash. On fail, return "".
	public static function getReturnCodeFromHash($hash)
	{
		$return_code = '';
		$sql = "select p.survey_id, r.response_id, r.return_code 
				from redcap_surveys_participants p, redcap_surveys_response r
				where p.hash = '".db_escape($hash)."' and p.participant_id = r.participant_id 
				and p.participant_email is not null limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			// Get return code that already exists in table
			$return_code = db_result($q, 0, 'return_code');
			$response_id = db_result($q, 0, 'response_id');
			$survey_id = db_result($q, 0, 'survey_id');
			// If code is blank, then try to generate a return code
			if ($return_code == '') {
				$return_code = Survey::getUniqueReturnCode($survey_id, $response_id);
			}
		}
		return $return_code;
	}

	// Track the page number as a GET variable (not seen in query string).
	// Return the label for the Save button and array of fields to hide on this page.
	public static function setPageNum($pageFields, $totalPages, $bypassReturnCodeSection=false)
	{
		global $table_pk, $participant_id, $return_code, $lang, $save_and_return_code_bypass, $public_survey;
		// Set flag if __page__ is passed in query string
		$__page__ = (isset($_GET['__page__']) && isset($pageFields[$_GET['__page__']])) ? $_GET['__page__'] : null;
		// Manually obtain return code value if returning and bypassing return code
		if ($_SERVER['REQUEST_METHOD'] == 'GET' && $save_and_return_code_bypass == '1' && $return_code == "" && !$public_survey) {
			$return_code = Survey::getReturnCodeFromHash($_GET['s']);
		}
		// e-Consent Framework: All signature field values are being erased, so take us to the page where the first signature field is located
		if ($_SERVER['REQUEST_METHOD'] != 'GET' && isset($_GET['__esf'])) {
			// Now find the page of this field
			$_GET['__page__'] = Survey::getQuestionPage($_GET['__esf'], $pageFields);
			unset($_GET['__esf']);
		}
		// FIRST PAGE OF SURVEY (i.e. request method = GET)
        elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && $save_and_return_code_bypass != '1') {
			$_GET['__page__'] = 1;
		}
		// If returning and just entered return code, determine page based upon last field with data entered
        elseif (!$bypassReturnCodeSection && isset($return_code) && !empty($return_code)) {
			// Query data table for data and retrieve field with highest field order on this form
			// (exclude calc fields because may allow participant to pass up required fields that occur earlier)
			$sql = "select m.field_name from redcap_data d, redcap_metadata m where m.project_id = " . PROJECT_ID . "
					and d.record = ". pre_query("select record from redcap_surveys_response where return_code = '" . db_escape($return_code) . "'
					and participant_id = $participant_id and completion_time is null limit 1") . "
					and m.project_id = d.project_id and m.field_name = d.field_name and d.event_id = {$_GET['event_id']}
					and d.instance ".($_GET['instance'] == '1' ? "is null" : "= ".$_GET['instance'])."
					and m.field_name != '$table_pk' and m.field_name != concat(m.form_name,'_complete') and m.form_name = '{$_GET['page']}'
					and m.element_type != 'calc' and d.value != '' and (m.misc is null or
						(m.misc not like '%@HIDDEN-SURVEY%' and trim(m.misc) not like '%@HIDDEN' and trim(m.misc) not like '%@HIDDEN %'))
					order by m.field_order desc limit 1";
			$lastFieldWithData = db_result(db_query($sql), 0);
			// Now find the page of this field
			$_GET['__page__'] = Survey::getQuestionPage($lastFieldWithData, $pageFields);
		}
		// Reduce page number if clicked previous page button
        elseif (isset($_POST['submit-action']) && isset($pageFields[$_POST['__page__']]) && is_numeric($_POST['__page__']))
		{
			if (isset($_GET['__reqmsg']) || isset($_GET['serverside_error_fields']) || isset($_GET['maxchoice_error_fields'])) {
				// If reloaded page for REQUIRED FIELDS or SERVER-SIDE VALIDATION, then set Get page as Post page (i.e. no increment)
				$_GET['__page__'] = $_POST['__page__'];
			} else {
				// PREV PAGE
				if (isset($_GET['__prevpage'])) {
					// Decrement $_POST['__page__'] value by 1
					$_GET['__page__'] = $_POST['__page__'] - 1;
				}
				// NEXT PAGE
				else {
					// Increment $_POST['__page__'] value by 1
					$_GET['__page__'] = $_POST['__page__'] + 1;
				}
			}
		}

		// Set flag if __page__ is passed in query string
		// if ($__page__ !== null) $_GET['__page__'] = $__page__;

		// Make sure page num is not in error
		if (!isset($_GET['__page__']) || $_GET['__page__'] < 1 || !is_numeric($_GET['__page__'])) {
			$_GET['__page__'] = 1;
		}

		// Set the label for the Submit button
		if ($totalPages > 1 && $totalPages != $_GET['__page__']) {
			$saveBtn = $lang['data_entry_213']." >>";
			$isLastPage = false;
		} else {
			$saveBtn = $lang['survey_200'];
			$isLastPage = true;
		}

		// Given the current page number, determine the fields on this form that should be hidden
		$hideFields = array();
		foreach ($pageFields as $this_page=>$these_fields) {
			if ($this_page != $_GET['__page__']) {
				foreach ($these_fields as $this_field) {
					$hideFields[] = $this_field;
				}
			}
		}

		// Return the label for the Save button and array of fields to hide on this page
		return array($saveBtn, $hideFields, $isLastPage);
	}

	// Gather record names and participant_ids of potential records (if an Initial Survey) and return as 2 arrays
	public static function getParticipantListSinglePage($survey_id, $event_id = null, $limit_begin=0, $num_per_page=50)
	{
		global $user_rights;
		// Set Proj object
		$Proj = new Project(PROJECT_ID);
		// Check event_id (if not provided, then use first one - i.e. for public surveys)
		if (!is_numeric($event_id)) $event_id = getEventId();

		// Get the first event_id of every Arm and place in array
		$firstEventIds = array();
		foreach ($Proj->events as $this_arm_num=>$arm_attr) {
			$arm_events_keys = array_keys($arm_attr['events']);
			$firstEventIds[] = print_r(array_shift($arm_events_keys), true);
		}

		// Is this a repeating event or form?
		$isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
		$isRepeatingForm = $Proj->isRepeatingForm($event_id, $Proj->surveys[$survey_id]['form_name']);
		$isRepeatingFormOrEvent = ($isRepeatingForm || $isRepeatingEvent);

		// Get existing records for this survey
		$sorting = array();
		// Keep count of email addresses
		$emailAddressCount = $emailAddressCountThisPage = array();

		// Get emails for existing records
		$allRecords = Records::getRecordList($Proj->project_id, array(), false, false, $Proj->eventInfo[$event_id]['arm_num']);
		$recordEmails = Survey::getResponsesEmailsIdentifiers($allRecords, $survey_id, $Proj->project_id, true);

		// If this is a repeating instrument/event, then gather all existing instances for existing records
		if ($isRepeatingFormOrEvent && !empty($allRecords)) {
			$instanceData = json_decode(Records::getData($Proj->project_id, 'json', $allRecords, array($Proj->table_pk, $Proj->surveys[$survey_id]['form_name']."_complete"), array($event_id)), true);
			unset($allRecords);
			foreach ($instanceData as $thisItem) {
				$this_record = $thisItem[$Proj->table_pk];
				$participant_email = isset($recordEmails[$this_record]['email']) ? $recordEmails[$this_record]['email'] : "";
				$sorting[] = $participant_email.'"'.$this_record.'"'.$thisItem['redcap_repeat_instance'].'"';
				// Increment the email address count
				if (isset($emailAddressCount[$participant_email])) {
					$emailAddressCount[$participant_email]++;
				} else {
					$emailAddressCount[$participant_email] = 1;
				}
			}
		} else {
			foreach ($allRecords as $this_record) {
				$participant_email = isset($recordEmails[$this_record]['email']) ? $recordEmails[$this_record]['email'] : "";
				$sorting[] = $participant_email.'"'.$this_record.'""';
				unset($allRecords[$this_record]);
				// Increment the email address count
				if (isset($emailAddressCount[$participant_email])) {
					$emailAddressCount[$participant_email]++;
				} else {
					$emailAddressCount[$participant_email] = 1;
				}
			}
			unset($allRecords);
		}
		unset($recordEmails);

		// Get participant_ids of potential records (if an Initial Survey)
		$initialSurvey = ($survey_id == $Proj->firstFormSurveyId && $Proj->isFirstEventIdInArm($event_id));
		if ($initialSurvey) {
			$this_instance = ($isRepeatingFormOrEvent) ? "1" : "";
			$sql = "select p.participant_id, p.participant_email
					from redcap_surveys_participants p left join redcap_surveys_response r on p.participant_id = r.participant_id 
					where p.participant_email is not null and p.event_id = $event_id and p.survey_id = $survey_id and r.record is null";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$sorting[] = $row['participant_email'] . '""' . $this_instance . '"' . $row['participant_id'];
				// Increment the email address count
				if (isset($emailAddressCount[$row['participant_email']])) {
					$emailAddressCount[$row['participant_email']]++;
				} else {
					$emailAddressCount[$row['participant_email']] = 1;
				}
			}
		}

		// Sort the entire participant list
		$sorting = array_unique($sorting);
		natcasesort($sorting);

		// Get count
		$participant_count = count($sorting);

		// Now get only THIS page's participants
		$sorting = array_slice($sorting, $limit_begin, $num_per_page);

		// Now gather record names + participant_ids to only display on this page
		$records = $participant_ids = array();
		foreach ($sorting as $key=>$item) {
			list ($email, $record, $instance, $id) = explode('"', $item);
			if ($record != '') {
				$records[] = $record;
			} elseif ($id != '') {
				$participant_ids[] = $id;
			}
			unset($sorting[$key]);
			// Increment the email address count for THIS page
			if (isset($emailAddressCountThisPage[$email])) {
				$emailAddressCountThisPage[$email]++;
			} else {
				$emailAddressCountThisPage[$email] = 1;
			}
		}

		// For this page, find every email address's first occurrence number (because the numbering for duplicates is important with multiple pages)
		$emailAddressFirstNumThisPage = array();
		foreach ($emailAddressCountThisPage as $thisEmail=>$thisPageCount) {
			$thisPageFirstNum = $emailAddressCount[$thisEmail] - $thisPageCount - 1;
			if ($thisPageFirstNum <= 0) $thisPageFirstNum = 1;
			$emailAddressFirstNumThisPage[$thisEmail] = $thisPageFirstNum;
			unset($emailAddressCount[$thisEmail], $emailAddressCountThisPage[$thisEmail]);
		}

		// Add blank record to each array if they are empty in order to force queries/etc to return nothing
		if (empty($records)) $records[] = '';
		if (empty($participant_ids)) $participant_ids[] = '';

		// Return records and participant_ids as arrays
		return array($records, $participant_ids, $participant_count, $emailAddressFirstNumThisPage);
	}

	// Gather participant list (with identfiers and if Sent/Responded) and return as array
	public static function getParticipantList($survey_id, $event_id=null, $recordsAllowlist=array(), $participantIdsAllowlist=array())
	{
		global $user_rights;
		// Set Proj object
		$Proj = new Project(PROJECT_ID);

		// Check event_id (if not provided, then use first one - i.e. for public surveys)
		if (!is_numeric($event_id)) $event_id = getEventId();
		// Ensure the survey_id belongs to this project
		if (!Survey::checkSurveyProject($survey_id))
		{
			redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
		}

		$table_pk = $Proj->table_pk;

		// Check if this is a follow-up survey
		$isFollowUpSurvey = !($survey_id == $Proj->firstFormSurveyId && $Proj->isFirstEventIdInArm($event_id));

		$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $Proj->surveys[$survey_id]['form_name']);

		// Check if time limit is enabled for survey
		$timeLimitEnabled = (Survey::calculateSurveyTimeLimit($Proj->surveys[$survey_id]['survey_time_limit_days'], $Proj->surveys[$survey_id]['survey_time_limit_hours'], $Proj->surveys[$survey_id]['survey_time_limit_minutes']) > 0);

		// For longitudinal projects with multiple arms, make sure that another arm's records are not displayed in this event's participant list
		$armSql = $armSql2 = $recordSql = $recordSql2 = "";
		$armRecords = array();
		if ($Proj->multiple_arms) {
			// Get list of records for the current arm (to which this event_id belongs)
			$armRecords = Records::getRecordList(PROJECT_ID, null, false, false, $Proj->eventInfo[$event_id]['arm_num']);
			if (!empty($recordsAllowlist)) {
				$armRecords = array_intersect($recordsAllowlist, $armRecords);
			}
			$armSql = " and d.record in (".prep_implode($armRecords).")";
			$armSql2 = " and record in (".prep_implode($armRecords).")";
		} elseif (!empty($recordsAllowlist)) {
			$recordSql = " and d.record in (".prep_implode($recordsAllowlist).")";
			$recordSql2 = "and record in (".prep_implode($recordsAllowlist).")";
		}

		## Pre-populate the participants table and responses table with row for each record (if not already there)
		// First, get forms WITH data values on them
		$sql = "select distinct d.record, if(d.instance is null,1,d.instance) as instance from redcap_data d 
				left join (redcap_surveys_participants p, redcap_surveys_response r) on p.event_id = d.event_id and p.survey_id = $survey_id
					and r.participant_id = p.participant_id and r.record = d.record and if(d.instance is null,1,d.instance) = r.instance
					and p.participant_email is not null 
				where d.project_id = " . PROJECT_ID . " and d.event_id = $event_id
					and d.field_name = '{$Proj->surveys[$survey_id]['form_name']}_complete' and r.response_id is null $armSql $recordSql";
		if (!$isRepeatingFormOrEvent) {
			$sql .= " and d.instance is null";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			Survey::getFollowupSurveyParticipantIdHash($survey_id, $row['record'], $event_id, true, $row['instance']);
		}
		// Second, get forms WITHOUT data values on them (this assumes that no repeating instances exist for them if the form has no data at all)
		$sql = "select distinct x.record from (select distinct project_id, record, '$event_id' as event_id from redcap_data 
					where project_id = " . PROJECT_ID . " and field_name in ('$table_pk', '{$Proj->surveys[$survey_id]['form_name']}_complete') $armSql2 $recordSql2) x
				left join redcap_data d on d.project_id = x.project_id and d.event_id = x.event_id and d.record = x.record
					and field_name = '{$Proj->surveys[$survey_id]['form_name']}_complete'
				left join (redcap_surveys_participants p, redcap_surveys_response r) on p.event_id = x.event_id and p.survey_id = $survey_id
					and r.participant_id = p.participant_id and r.record = x.record and p.participant_email is not null
				where d.record is null and r.response_id is null";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			Survey::getFollowupSurveyParticipantIdHash($survey_id, $row['record'], $event_id, true, 1);
		}

		// Build participant list
		$part_list = $participantIdsLimit = array();
		$addToParticipantIdsLimit = !empty($participantIdsAllowlist);
		if (empty($participantIdsAllowlist)) {
			$sql = "select p.* from redcap_surveys_participants p
					where p.survey_id = $survey_id and p.event_id = $event_id and p.participant_email is not null";
		} else {
			$sql = "select p.* from redcap_surveys_participants p
					left join redcap_surveys_response r on r.participant_id = p.participant_id
					where p.survey_id = $survey_id and p.event_id = $event_id and p.participant_email is not null";
			$sqlsub = array();
			if (!empty($participantIdsAllowlist) && $participantIdsAllowlist[0] != '') {
				$sqlsub[] = "p.participant_id in (" . prep_implode($participantIdsAllowlist) . ")";
			}
			if (!empty($recordsAllowlist) && $recordsAllowlist[0] != '') {
				$sqlsub[] = "r.record in (".prep_implode($recordsAllowlist).")";
			}
			if (!empty($sqlsub)) $sql .= " and (".implode(" or ", $sqlsub).")";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Set with email, identifier, and basic defaults for counts
			$part_list[$row['participant_id']] = array(
				'record'=>'', 'repeat_instance'=>1, 'email'=>$row['participant_email'],
				'identifier'=>$row['participant_identifier'],
				'phone'=>$row['participant_phone'], 'hash'=>$row['hash'], 'sent' =>0, 'response'=>0, 'return_code'=>'',
				'scheduled'=>'', 'next_invite_is_reminder'=>0,
				'delivery_preference'=>($row['delivery_preference'] == '' ? 'EMAIL' : $row['delivery_preference'])
			);
			if ($timeLimitEnabled) {
				$part_list[$row['participant_id']]['link_expiration'] = $row['link_expiration'];
			}
			if ($addToParticipantIdsLimit) {
				$participantIdsLimit[] = $row['participant_id'];
			}
		}

		// Query email invitations sent
		$sql = "select p.participant_id from redcap_surveys_emails e1, redcap_surveys_participants p, redcap_surveys_emails_recipients r
				left join redcap_surveys_scheduler_queue q on q.email_recip_id = r.email_recip_id
				where e1.survey_id = $survey_id and e1.email_id = r.email_id and p.survey_id = e1.survey_id
				and p.participant_id = r.participant_id and p.event_id = $event_id
				and ((q.ssq_id is not null and q.time_sent is not null) or (q.ssq_id is null and e1.email_sent is not null))";
		if (!empty($participantIdsLimit)) {
			$sql .= " and p.participant_id in (".prep_implode($participantIdsLimit).")";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$part_list[$row['participant_id']]['sent'] = 1;
		}

		// Query for any responses AND return codes
		$saveAndReturnEnabled = ($Proj->surveys[$survey_id]['save_and_return']);
		$sql = "select p.participant_id, r.first_submit_time, r.completion_time, r.return_code, r.record, p.participant_email, r.instance
				from redcap_surveys_participants p, redcap_surveys_response r
				where p.survey_id = $survey_id and r.participant_id = p.participant_id and p.participant_email is not null
				and p.event_id = $event_id";
		if (!empty($participantIdsLimit)) {
			$sql .= " and p.participant_id in (".prep_implode($participantIdsLimit).")";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$part_list[$row['participant_id']]['record'] = $row['record'];
			$part_list[$row['participant_id']]['repeat_instance'] = $row['instance'];
			if ($row['participant_email'] === null) {
				// Initial survey
				$part_list[$row['participant_id']]['response'] = ($row['completion_time'] == "" ? 1 : 2);
			} else {
				// Followup surveys (participant_email will be '' not null)
				if ($row['completion_time'] == "" && $row['first_submit_time'] == "") {
					$part_list[$row['participant_id']]['response'] = 0;
				} elseif ($row['completion_time'] == "" && $row['first_submit_time'] != "") {
					$part_list[$row['participant_id']]['response'] = 1;
				} else {
					$part_list[$row['participant_id']]['response'] = 2;
				}
			}
			// If save and return enabled, then include return code, if exists.
			if ($saveAndReturnEnabled) {
				$part_list[$row['participant_id']]['return_code'] = $row['return_code'];
			}
		}

		// If this is an INITIAL SURVEY, then it is possible that a double entry in the response table exists if the response
		// was create via a Public Survey, so the completions timestamps (and thus completion status) may belong to the public survey
		// response but NOT to the unique link response. In that case, get all public survey response status and overwrite any
		// that are missing as a unique link response status.
		if (!$isFollowUpSurvey) {
			$sql = "SELECT p.participant_id, if (rpub.completion_time is null, 1, 2) as response, rpub.instance
					FROM redcap_surveys_participants pub, redcap_surveys_response rpub, redcap_surveys_participants p, redcap_surveys_response r
					where pub.participant_email is null and pub.participant_id = rpub.participant_id and rpub.first_submit_time is not null
					and pub.survey_id = p.survey_id and pub.event_id = p.event_id and p.participant_id = r.participant_id
					and r.record = rpub.record and p.event_id = $event_id and p.survey_id = $survey_id";
			if (!empty($participantIdsLimit)) {
				$sql .= " and p.participant_id in (".prep_implode($participantIdsLimit).")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if (!isset($part_list[$row['participant_id']])) continue;
				// Add response status
				if ($part_list[$row['participant_id']]['response'] == 0) {
					$part_list[$row['participant_id']]['response'] = $row['response'];
					$part_list[$row['participant_id']]['repeat_instance'] = $row['instance'];
				}
			}
		}

		// SCHEDULED: Query for any responses that have been scheduled via the Invitation Scheduler
		// Store the reminder_num of the next invitation
		$next_reminder_num = array();
		// Order by send time desc because there might be several reminders, and we want to capture the NEXT one in the array (as well as its reminder_num)
		$sql = "select p.participant_id, q.scheduled_time_to_send, q.reminder_num from redcap_surveys_participants p,
				redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r where p.survey_id = $survey_id
				and p.event_id = $event_id and p.participant_email is not null and q.email_recip_id = r.email_recip_id
				and p.participant_id = r.participant_id and q.status = 'QUEUED'";
		if (!empty($participantIdsLimit)) {
			$sql .= " and p.participant_id in (".prep_implode($participantIdsLimit).")";
		}
		$sql .= " order by q.scheduled_time_to_send desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$part_list[$row['participant_id']]['scheduled'] = $row['scheduled_time_to_send'];
			$next_reminder_num[$row['participant_id']] = $row['reminder_num'];
		}
		// Loop through reminder_nums and add next_invite_is_reminder for each if > 0
		foreach ($next_reminder_num as $this_part=>$this_reminder_num) {
			if ($this_reminder_num > 0) {
				$part_list[$this_part]['next_invite_is_reminder'] = 1;
			}
		}

		## OBTAIN EMAIL ADDRESSES FOR FOLLOWUP SURVEYS (SINCE THEY DON'T HAVE THEM NATURALLY)
		// Follow-up surveys will not have an email in the participants table, so pull from initial survey's Participant List (if exists there)
		// Store record as key so we can retrieve this survey's participan_id for this record later
		$partRecords = array();
		foreach ($part_list as $this_part=>$attr)
		{
			// If record is blank, then remove from participant list and do not add to array
			// (How did a blank record get here, btw?)
			if ($isFollowUpSurvey && $attr['record'] == '') {
				unset($part_list[$this_part]);
				continue;
			}
			// Add to array
			$partRecords[] = $attr['record'];
		}
		// Get all participant attributes for this followup survey
		$participantAttributes = Survey::getResponsesEmailsIdentifiers($partRecords, $survey_id);

		foreach ($part_list as $this_part_id=>$attr) {
			// If found repeating instances when this form/event is not repeating, then remove them
			if (!$isRepeatingFormOrEvent && $attr['repeat_instance'] > 1) {
				unset($part_list[$this_part_id]);
				continue;
			}
			// Now use that record list to get the original email from first survey's participant list
			if (isset($participantAttributes[$attr['record']])) {
				$thisRecord = $participantAttributes[$attr['record']];
				// Add email and identifier
				$part_list[$this_part_id]['email'] = $thisRecord['email'];
				$part_list[$this_part_id]['identifier'] = $thisRecord['identifier'];
				$part_list[$this_part_id]['phone'] = $thisRecord['phone'];
				if ($part_list[$this_part_id]['delivery_preference'] == "" && $thisRecord['delivery_preference'] != "") {
					$part_list[$this_part_id]['delivery_preference'] = $thisRecord['delivery_preference'];
				}
			}
		}

		// Order array first by email address, record, instance, then by participant_id
		foreach ($part_list as $this_part_id=>$attr) {
			$attr['participant_id'] = $this_part_id;
			unset($part_list[$this_part_id]);
			$instance = isset($attr['repeat_instance']) ? $attr['repeat_instance'] : '';
			$part_list[$attr['email']."--".$attr['record']."--".$instance."--".$this_part_id] = $attr;
		}
		natcaseksort($part_list);
		foreach ($part_list as $this_email_part_id=>$attr) {
			$this_part_id = $attr['participant_id'];
			unset($part_list[$this_email_part_id], $attr['participant_id']);
			$part_list[$this_part_id] = $attr;
		}

		// DUPLICATE EMAIL ADDRESSES: Track when there are email duplicates so we can pre-pend with #) when displaying it multiple times in table
		$part_list_duplicates = array();
		foreach ($part_list as $this_part_id=>$attr) {
			if ($attr['email'] == '') continue;
			// Set to lowercase to group same emails together regardless of case
			$attr['email'] = strtolower($attr['email']);
			if (isset($part_list_duplicates[$attr['email']])) {
				$part_list_duplicates[$attr['email']]['total']++;
			} else {
				$part_list_duplicates[$attr['email']]['total'] = 1;
				$part_list_duplicates[$attr['email']]['current'] = 1;
			}
		}

		// If user is in a DAG, only allow them to see participants in their DAG
		if ($user_rights['group_id'] != '')
		{
			// Validate DAG that user is in
			$dags = $Proj->getGroups();
			if (isset($dags[$user_rights['group_id']])) {
				$dag_records = Records::getData('array', array(), $table_pk, array(), $user_rights['group_id']);
				// Loop through participants and remove any that have records NOT in user's DAG
				foreach ($part_list as $this_part_id=>$attr) {
					// If record not in user's DAG, remove participant from array
					if ($attr['record'] != '' && !isset($dag_records[$attr['record']])) {
						unset($part_list[$this_part_id]);
					}
				}
			}
		}

		// If survey time limit for completion setting is set, then obtain the link expiration times
		if ($timeLimitEnabled) {
			$initialInviteTimes = Survey::getLinkExpirationTimes(array_keys($part_list));
			foreach ($initialInviteTimes as $this_part_id=>$this_invite_time) {
				$part_list[$this_part_id]['link_expiration'] = $this_invite_time;
			}
		}

		// For longitudinal projects with multiple arms, make sure that another arm's records are not displayed in this event's participant list
		if ($Proj->multiple_arms && empty($participantIdsAllowlist)) {
			$noRecordsInArm = empty($armRecords);
			foreach ($part_list as $this_part_id=>$attr) {
				// Skip participants with no associated record
				if ($attr['record'] == '') continue;
				// Is record in this arm?
				if (!isset($armRecords[$attr['record']]) || $noRecordsInArm) {
					// Remove from participant list
					unset($part_list[$this_part_id]);
				}
			}
		}

		// Return array
		return array($part_list, $part_list_duplicates);
	}

	// Returns array of record names from an array of participant_ids (with participant_id as array key)
	// NOTE: For FOLLOWUP SURVEYS ONLY (assumes row exists in response table)
	public static function getRecordFromPartId($partIds=array())
	{
		$records = array();
		$sql = "select p.participant_id, r.record from redcap_surveys_participants p, redcap_surveys_response r
				where r.participant_id = p.participant_id and p.participant_id in (".prep_implode($partIds, false).")
				order by abs(r.record), r.record";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$records[$row['participant_id']] = $row['record'];
		}
		return $records;
	}

	// SEND CONFIRMATION EMAIL TO RESPONDENT
	public static function sendSurveyConfirmationEmail($survey_id, $event_id, $record, $respondent_email_override=null)
	{
		global $Proj, $lang, $pdf_custom_header_text;

		// Get survey attributes
		$survey_attr = $Proj->surveys[$survey_id];
		// Set boolean flag to determine if email sends
		$emailSentSuccessfully = false;
		$attachmentCount = 0;

		// See if this is enabled
		if ($survey_attr['confirmation_email_subject'] != '' && $survey_attr['confirmation_email_content'] != '')
		{
			// Get respondent's email, if we have it
			if ($respondent_email_override == null) {
				$emailsIdents = Survey::getResponsesEmailsIdentifiers(array($record), $survey_id);
				$respondent_email = $emailsIdents[$record]['email'];
			} else {
				$respondent_email = $respondent_email_override;
			}
			if (!empty($respondent_email)) {
				// Perform piping on subject and message
				$this_subject = strip_tags(Piping::replaceVariablesInLabel($survey_attr['confirmation_email_subject'], $record, $event_id));
				$this_content = Piping::replaceVariablesInLabel($survey_attr['confirmation_email_content'],  $record, $event_id);
				// Determine if email message was created via rich text editor, and if so, remove all line breaks first
				if (substr($this_content, 0, 3) === '<p>' && substr($this_content, -4) === '</p>') {
					$this_content = str_replace(array("\r", "\n"), array("", ""), $this_content);
				}
				// Send email
				$email = new Message ();
				$email->setTo($respondent_email);
				$email->setFrom($survey_attr['confirmation_email_from']);
				$email->setFromName($survey_attr['confirmation_email_from_display']); // Add secondary and tertiary display name that matches an email in the project?
				$email->setSubject($this_subject);
				$email->setBody('<html><body style="font-family:arial,helvetica;font-size:10pt;">'.nl2br($this_content).'</body></html>');

				// Get Record / Event / Instrument
				$instrument = $survey_attr['form_name'];

				// Attach a PDF of the completed instrument
				if ($survey_attr['confirmation_email_attach_pdf'] == '1') {
					list ($nameDobText, $versionText, $typeText) = Survey::getEconsentOptionsData($Proj->project_id, $record, $instrument);
					$versionTypeTextArray = array();
					if ($nameDobText != '')	$versionTypeTextArray[] = $nameDobText;
					if ($versionText != '')	$versionTypeTextArray[] = $lang['data_entry_428']." ".$versionText;
					if ($typeText != '') 	$versionTypeTextArray[] = $lang['data_entry_429']." ".$typeText;
					$versionTypeText = implode(', ', $versionTypeTextArray);

					$compactPdf = ($survey_attr['pdf_auto_archive'] == '2');
					$pdf = REDCap::getPDF($record, $instrument, $event_id, false, $_GET['instance'], $compactPdf, $pdf_custom_header_text, $versionTypeText);

					$pdf_filename_tmp = APP_PATH_TEMP . date('YmdHis') . "_survey_" . substr(sha1(rand()), 0, 8) . '.pdf';
					file_put_contents($pdf_filename_tmp, $pdf);
					$email->setAttachment($pdf_filename_tmp);
					$attachmentCount++;
				}
				if (is_numeric($survey_attr['confirmation_email_attachment'])) {
					## ATTACHMENT
					// Move file to temp directory using edoc_id
					$attachment_full_path = Files::copyEdocToTemp($survey_attr['confirmation_email_attachment']);
					// Add attachment
					$email->setAttachment($attachment_full_path);
					$attachmentCount++;
				}
				$emailSentSuccessfully = $email->send();
				// Delete temp file, if applicable
				if (isset($attachment_full_path) && !empty($attachment_full_path)) {
					unlink($attachment_full_path);
				}
				if (isset($pdf_filename_tmp) && !empty($pdf_filename_tmp)) {
					unlink($pdf_filename_tmp);
				}
			}
		}

		// Log the sending of the confirmation email
        if ($emailSentSuccessfully) {
			$changes_made = "From: '".$survey_attr['confirmation_email_from']."',\nTo: '$respondent_email',\nSubject: '$this_subject'";
			if ($attachmentCount > 0) {
				$changes_made .= ",\nAttachments: $attachmentCount";
				if ($survey_attr['confirmation_email_attach_pdf'] == '1') {
					$changes_made .= " (including PDF of survey responses)";
				}
			}
			Logging::logEvent("", "redcap_surveys", "UPDATE", $record,$changes_made, "Send survey confirmation email to participant");
		}

		// Return if email was sent
		return $emailSentSuccessfully;
	}

	// Send emails to survey admins when a survey is completed, if enabled for any admin
	public static function sendEndSurveyEmails($survey_id, $event_id, $participant_id, $record, $instance=1)
	{
		global $Proj, $redcap_version, $lang, $project_contact_email;

		// Get survey attributes
		$survey_title = strip_tags($Proj->surveys[$survey_id]['title']);

		## SEND EMAILS TO SURVEY ADMINS
		// Check if any emails need to be sent and to whom
		$sql = "select distinct trim(if (a.action_response = 'EMAIL_TERTIARY', u.user_email3, 
					if (a.action_response = 'EMAIL_SECONDARY', u.user_email2, u.user_email))) as user_email, u.datetime_format
				from redcap_actions a, redcap_user_information u, redcap_user_rights r
				where a.project_id = " . PROJECT_ID . " and a.survey_id = $survey_id and a.project_id = r.project_id
				and r.username = u.username and a.action_trigger = 'ENDOFSURVEY' and u.user_suspended_time is null
				and a.action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY') and u.ui_id = a.recipient_id";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			// If this participant has an identifier, display identifier name in email
			$participantAttributes = Survey::getResponsesEmailsIdentifiers(array($record), $survey_id);
			$identifier = ($participantAttributes[$record]['identifier'] == '') ? '' : '('.$participantAttributes[$record]['identifier'].') ';
			// Initialize email
			$email = new Message();
			$email->setSubject('[REDCap] '.$lang['survey_21'].' "'.$survey_title.'"');
			// Loop through all applicable admins and send email to each
			while ($row = db_fetch_assoc($q))
			{
				// Convert NOW into user's preferred datetime format
				$ts = DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', $row['datetime_format']);
				// Set email content
				$emailContents = "
					{$lang['survey_15']} {$identifier}{$lang['survey_16']} \"<b>$survey_title</b>\" {$lang['global_51']} {$ts}{$lang['period']}
					{$lang['survey_17']} <a href='".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/DataEntry/index.php?pid=".PROJECT_ID."&page={$_GET['page']}&event_id=$event_id&id=$record&instance=$instance'>{$lang['survey_18']}</a>{$lang['period']}<br><br>
					{$lang['survey_371']} <a href='".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/Design/online_designer.php?pid=".PROJECT_ID."'>{$lang['design_25']}</a>
					{$lang['survey_20']}";
				$email->setBody($emailContents,true);
				// Set to/from
				$email->setTo($row['user_email']);
				$email->setFrom($project_contact_email);
				$email->setFromName($GLOBALS['project_contact_name']);
				// Send it
				$email->send();
			}
		}
	}

	// Encrypt the survey participant's response id as a hash
	public static function encryptResponseHash($response_id, $participant_id)
	{
		global $__SALT__;
		return md5($__SALT__ . $response_id) . md5($__SALT__ . $participant_id);
	}

	// Decrypt the survey participant's response hash as the response id
	public static function decryptResponseHash($hash, $participant_id)
	{
		global $__SALT__;
		// Make sure it's 64 chars long
		if (empty($hash) || (!empty($hash) && strlen($hash) != 64)) return '';
		// Break into two pieces
		$response_id_hash = substr($hash, 0, 32);
		$participant_id_hash = substr($hash, 32);
		// Verify participant_id value
		if ($participant_id_hash != md5($__SALT__ . $participant_id)) return '';
		// Now we must find the response_id by running a query to find it using one-way md5 hashing
		$sql = "select response_id from redcap_surveys_response where participant_id = $participant_id
				and md5(concat('$__SALT__',response_id)) = '$response_id_hash' limit 1";
		$q = db_query($sql);
		if ($q) {
			// Return the response_id
			return db_result($q, 0);
		}
		// Return false (as opposed to '') to denote legitimate reason for not finding it (due to complex technical reasons)
		return false;
	}

	// Obtain the response_hash value from the results code in the query string
	public static function getResponseHashFromResultsCode($results_code, $participant_id)
	{
		$sql = "select response_id from redcap_surveys_response where participant_id = $participant_id
				and results_code = '".db_escape($results_code)."' limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q))
		{
			$response_id = db_result($q, 0);
			if (is_numeric($response_id)) {
				return Survey::encryptResponseHash($response_id, $participant_id);
			}
		}
		return '';
	}

	// Encrypt the page number __page__ on the form in order to later verify against the real value
	public static function getPageNumHash($page)
	{
		global $__SALT__;
		return md5($__SALT__ . $page . $__SALT__);
	}

	// Verify that the page number hash is correct for the page number sent via Post
	public static function verifyPageNumHash($hash, $page)
	{
		return ($hash == Survey::getPageNumHash($page));
	}

	// GET RESPONSE ID: If $_POST['__response_hash__'] exists and is not empty, then set $_POST['__response_id__']
	public static function initResponseId()
	{
		global $participant_id;
		// If somehow __response_id__ was posted on form (it should NOT), then remove it here
		unset($_POST['__response_id__']);
		// If response_hash exists, convert to response_id
		if (isset($_POST['__response_hash__']) && !empty($_POST['__response_hash__']))
		{
			$_POST['__response_id__'] = Survey::decryptResponseHash($_POST['__response_hash__'], $participant_id);
			// Somehow it failed to get response_id, then unset it
			if (empty($_POST['__response_id__'])) unset($_POST['__response_id__']);
		}
	}

    // CHECK POSTED PAGE NUMBER (verify if correct to prevent gaming the system)
	public static function initPageNumCheck()
	{
		if (isset($_POST['__page__']))
		{
			if (!isset($_POST['__page_hash__']) || (isset($_POST['__page_hash__']) && !Survey::verifyPageNumHash($_POST['__page_hash__'], $_POST['__page__'])))
			{
				// Could not verify page hash, so set to 0 (so gets set to page 1)
				$_POST['__page__'] = 0;
			}
		}
		// Remove page_hash from Post
		unset($_POST['__page_hash__']);
	}

    // Regarding the record-survey-event-instance, returns FALSE if response has not been started,
    // 0 if it is a partial response, or 1 if a completed response.
	public static function isResponseCompleted($survey_id = null, $record = null, $event_id = null, $instance = 1)
	{
		// Check event_id/survey_id/record
		if (!is_numeric($event_id) || !is_numeric($survey_id) || !is_numeric($instance) || $record == '') return false;
		// Query response table
		$sql = "select r.completion_time from redcap_surveys_participants p, redcap_surveys_response r
			where r.participant_id = p.participant_id and p.survey_id = $survey_id
			and p.event_id = $event_id and r.record = '" . db_escape($record) . "' and r.instance = $instance
			and r.first_submit_time is not null
			order by r.completion_time desc, r.first_submit_time desc limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) == 0) {
			return false;
		} elseif (db_result($q, 0) == '') {
			return 0;
		} else {
			return 1;
		}
	}

    // REMOVE QUEUED SURVEY INVITATIONS
    // If any participants have already been scheduled, then remove all those instances so they can be
    // scheduled again here (first part of query returns those where record=null - i.e. from initial survey
    // Participant List, and second part return those that are existing records).
	public static function removeQueuedSurveyInvitations($survey_id, $event_id, $email_ids=array())
	{
		$deleteErrors = 0;
		if (!empty($email_ids))
		{
			$ssq_ids_delete = array();
			$sql = "(select q.ssq_id from redcap_surveys_participants p, redcap_surveys_scheduler_queue q,
				redcap_surveys_emails_recipients e where p.survey_id = $survey_id and p.event_id = $event_id
				and p.participant_email is not null and q.email_recip_id = e.email_recip_id
				and p.participant_id = e.participant_id and q.status = 'QUEUED'
				and p.participant_id in (".prep_implode($email_ids, false)."))
				union
				(select q.ssq_id from redcap_surveys_participants p, redcap_surveys_response r,
				redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e where p.survey_id = $survey_id
				and p.event_id = $event_id and r.participant_id = p.participant_id and p.participant_email is not null
				and q.email_recip_id = e.email_recip_id and p.participant_id = e.participant_id and r.record = q.record
				and q.status = 'QUEUED' and p.participant_id in (".prep_implode($email_ids, false)."))";
			$q = db_query($sql);
			if (db_num_rows($q) > 0)
			{
				// Gather all ssq_id's and email_recip_id's into arrays so we know what to delete
				while ($row = db_fetch_assoc($q)) {
					$ssq_ids_delete[] = $row['ssq_id'];
				}
				// Delete those already scheduled in redcap_surveys_emails_recipients (this will cascade to also delete in redcap_surveys_scheduler_queue)
				$sql = "update redcap_surveys_scheduler_queue set status = 'DELETED'
					where ssq_id in (".implode(",", $ssq_ids_delete).")";
				if (!db_query($sql)) $deleteErrors++;
			}
		}
		// Return false if errors occurred
		return ($deleteErrors > 0);
	}

	// Return boolean if a URL generally appears to be a survey link or survey queue link on this REDCap installation
    public static function isSurveyLink($url)
    {
        return (strpos($url, APP_PATH_SURVEY_FULL) === 0);
	}

}
