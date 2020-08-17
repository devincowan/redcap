<?php

// Twilio must be enabled first
if (!($twilio_enabled && $Proj->twilio_enabled_surveys)) exit($isAjax ? '0' : 'ERROR!');
// Initialize Twilio
TwilioRC::init();
// Instantiate a new Twilio Rest Client
$twilioClient = TwilioRC::client();
// Instantiate Twilio TwiML object
$twiml = new Services_Twilio_Twiml();


## INITIALIZE AND VALIDATE ALL VALUES
// Verify that this request is coming from Twilio
if (!isset($_SERVER["HTTP_X_TWILIO_SIGNATURE"]) && !isDev() && !isVanderbilt()) {
	exit("ERROR: You are not authorized!");
}


// Get regex validation so we can validate this field
$valTypes = getValTypes();

// Set flag to denote if record exists
$record_exists = $hidden_edit;

## PROMIS: Determine if instrument is a PROMIS instrument downloaded from the Shared Library
list ($isPromisInstrument, $isAutoScoringInstrument) = PROMIS::isPromisInstrument($form_name);


// Settings for SMS and Voice
$participant_phone = $_POST['From'];
if (VOICE) {
	// Set voice and language attributes for all Say commands
	$language = TwilioRC::getLanguage();
	$voice = TwilioRC::getVoiceGender();
	$say_array = array('voice'=>$voice, 'language'=>$language);
	// Set header to output TWIML/XML
	header('Content-Type: text/xml');
}


// If participant is sending an SMS, but SMS Conversation has not been enabled, then give custom response
if (SMS && !$twilio_option_sms_initiate) {
	TwilioRC::sendSMS($lang['survey_1286'], $participant_phone, $twilioClient);
	exit;
}


// If a voicemail/machine is detected, then stop and tell them to call back
if ($_POST['AnsweredBy'] == 'machine') {
	// Get the survey access code for this participant to add to table for when they call back
	$survey_access_code = Survey::getAccessCode(Survey::getParticipantIdFromHash($_GET['s']), false, false, true);
	TwilioRC::addSmsAccessCodeForPhoneNumber($_POST['To'], $twilio_from_number, $survey_access_code, PROJECT_ID);
	// Set Twiml
	$twiml->pause("");
	$twiml->say($lang['survey_1007'] . " ". formatPhone($twilio_from_number), $say_array);
	$twiml->pause("");
	$twiml->say($lang['survey_1007'] . " ". formatPhone($twilio_from_number), $say_array);
	exit($twiml);
}

// Check response limit, if enabled
if (Survey::reachedResponseLimit($project_id, $survey_id, $event_id)) 
{
	if (VOICE) {
		// Set Twiml
		$twiml->pause("");
		$twiml->say($response_limit_custom_text, $say_array);
		$twiml->pause("");
		$twiml->say($response_limit_custom_text, $say_array);
		exit($twiml);
	} else {
		TwilioRC::sendSMS($response_limit_custom_text, $participant_phone, $twilioClient);
		TwilioRC::deleteSmsAccessCodeFromPhoneNumber($participant_phone, $twilio_from_number,
			($twilio_multiple_sms_behavior == 'OVERWRITE' ? null : $_SESSION['survey_access_code']));
		exit;
	}
}


// Determine record name
if (isset($_SESSION['response_id']) && is_numeric($_SESSION['response_id'])) {
	// Record exists: If response_id is in session, then use it to obtain record name
	$sql = "select record, first_submit_time, completion_time from redcap_surveys_response
			where response_id = {$_SESSION['response_id']} and participant_id = $participant_id limit 1";
	$q = db_query($sql);
	$_GET['id'] = $fetched = db_result($q, 0, 'record');
	// This record already exists
	$record_exists = 1;
} else {
	// On first question, so we need to either generate a record name or (if exists already) determine it
	if ($public_survey) {
		// If a Public Survey, then set current record as auto-numbered value
		$_GET['id'] = $fetched = DataEntry::getAutoId();
	} else {
		// Get record name via the participant_id, if record exists
		$participant_ids = Survey::getRecordFromPartId(array($participant_id));
		if ($participant_ids[$participant_id] == '') {
			// No record exists yet, so auto-gen a new one
			$_GET['id'] = $fetched = DataEntry::getAutoId();
		} else {
			// Get existing record name
			$_GET['id'] = $fetched = $participant_ids[$participant_id];
			// Make sure that the response hasn't been completed already
			$sql = "select r.response_id, r.first_submit_time, r.completion_time
					from redcap_surveys_response r, redcap_surveys_participants p
					where p.participant_id = $participant_id and r.record = '".db_escape($fetched)."'
					and p.participant_id = r.participant_id and p.participant_email is not null limit 1";
			$q = db_query($sql);
			if (db_num_rows($q)) {
				$row = db_fetch_assoc($q);
				// If completed, then stop
				if ($row['completion_time'] != '') Survey::exitSurvey($lang['survey_111']);
				// Set response_id in session
				$_SESSION['response_id'] = $row['response_id'];
			}
			// This record already exists
			$record_exists = 1;
		}
	}
}


## GET DATA IF AN EXISTING RECORD
// Put saved record data in array for referencing later (branching logic, matrix ranking)
$record_data = ($record_exists) ? Records::getData('array', $fetched) : array();


## SAVE PREVIOUS QUESTION DATA
// Set answer value (if just submitted from user)
$answer = null;
if (SMS && isset($_POST['Body'])) {
	$answer = $_POST['Body'];
} elseif (VOICE && isset($_POST['Digits'])) {
	$answer = $_POST['Digits'];
}
// Set default value
$saveData = false;
$endSurveyViaStopAction = false;
// If question was just answered, then save the data before moving on
if ($answer != null && isset($_SESSION['field']) && $_SESSION['field'] != '' && isset($Proj->metadata[$_SESSION['field']]))
{
	// Set default value to save data from values just submitted
	$saveData = true;

	// Stop Action: If set for this field and answer warrants the survey to stop, then set flag
	if ($Proj->metadata[$_SESSION['field']]['stop_actions'] != '' && $Proj->isMultipleChoice($_SESSION['field'])) {
		$stop_actions = DataEntry::parseStopActions($Proj->metadata[$_SESSION['field']]['stop_actions']);
		$endSurveyViaStopAction = in_array($answer, $stop_actions);
	}
	//db_query("insert into aaa (mytext) values ('".db_escape("field = {$_SESSION['field']}\nanswer = $answer\n".print_r($stop_actions, true))."')");

	// Voice calls only: Repeat what the participant entered ("You entered 123.")
	if (!SMS) {
		$twiml->say($lang['survey_942'] . " $answer", $say_array);
		$twiml->pause("");
	}

	## VALIDATE INPUT
	// MULTIPLE CHOICE: If the field is a multiple choice field, make sure a valid choice was entered
	if ($Proj->isMultipleChoice($_SESSION['field']))
	{
		// Get array of choices
		if ($Proj->metadata[$_SESSION['field']]['element_type'] == 'sql') {
			// If an SQL field, parse the choices
			$fieldChoices = parseEnum(getSqlFieldEnum($Proj->metadata[$_SESSION['field']]['element_enum']));
		} else {
			// Parse choices normally
			$fieldChoices = parseEnum($Proj->metadata[$_SESSION['field']]['element_enum']);
			// If field is a radio button with matrix ranking, then remove any choices that already have a value
			$this_grid_name = $Proj->metadata[$_SESSION['field']]['grid_name'];
			if ($this_grid_name != '' && $Proj->matrixGroupHasRanking[$this_grid_name]) {
				// Loop through all choices in this matrix
				foreach ($Proj->matrixGroupNames[$this_grid_name] as $this_matrix_field) {
					// Does this field have a saved value?
					if ($record_data[$fetched][$event_id][$this_matrix_field] != '' && isset($fieldChoices[$record_data[$fetched][$event_id][$this_matrix_field]])) {
						// Remove this choice from $this_enum_array
						unset($fieldChoices[$record_data[$fetched][$event_id][$this_matrix_field]]);
					}
				}
			}
			// If a CAT, add blank option to allow skipping question, if enabled and user just skipped this question
			if ($isPromisInstrument && $promis_skip_question && $answer == '0') {
				$fieldChoices[''] = $answer = '';
				// Set $answer as blank since the CAT field itself will end up with a blank value
				if (SMS) {
					$_POST['Body'] = '';
				} else {
					$_POST['Digits'] = '';
				}
			}
		}
		if (!isset($fieldChoices[$answer])) {
			// Set flag so we don't save data
			$saveData = false;
			// An invalid choice was entered
			if (SMS) {
				TwilioRC::preventInvalidResponseLoop($project_id, $fetched, $event_id, $_SESSION['field'], $answer);
				TwilioRC::sendSMS($lang['survey_681'], $participant_phone, $twilioClient);
			} else {
				$twiml->say($lang['survey_681'], $say_array);
			}
			// Get previous field (if any)
			$prevField = $Proj->getPrevField($_SESSION['field']);
			$_SESSION['field'] = ($prevField === false) ? '' : $prevField;
		}
	}
	// TEXT VALIDATION: If field is number-validated, then check validation and range
	elseif ($Proj->metadata[$_SESSION['field']]['element_type'] == 'text'
			&& $Proj->metadata[$_SESSION['field']]['element_validation_type'] != '')
	{
		// Set the valType for this field
		$valType = convertLegacyValidationType($Proj->metadata[$_SESSION['field']]['element_validation_type']);
		$data_type = ($valType != '') ? $valTypes[$valType]['data_type'] : '';
		// Make sure dates have leading zeros
		if (in_array($data_type, array('date', 'datetime', 'datetime_seconds'))) {
			// Split into date and time components
			list ($this_date, $this_time) = explode(" ", $answer, 2);
			// Replace any dots or slashes with dashes
			$this_date = str_replace(array('.', '/'), array('-', '-'), $this_date);
			// Add leading zeros
			$this_date2 = explode("-", $this_date, 3);
			if (substr($valType, -4) == '_mdy' || substr($valType, -4) == '_dmy') {
				$this_date = sprintf("%02d-%02d-%04d", $this_date2[0], $this_date2[1], $this_date2[2]);
			} else {
				$this_date = sprintf("%04d-%02d-%02d", $this_date2[0], $this_date2[1], $this_date2[2]);
			}
			// Put answer back together again
			$answer = trim("$this_date $this_time");
		}
		// Set regex pattern to use for this field
		$regex_pattern = $valTypes[$valType]['regex_php'];
		// Run the value through the regex pattern
		if (!preg_match($regex_pattern, $answer)) {
			// Set flag so we don't save data
			$saveData = false;
			// An invalid choice was entered
			if (SMS) {
				TwilioRC::preventInvalidResponseLoop($project_id, $fetched, $event_id, $_SESSION['field'], $answer);
				TwilioRC::sendSMS($lang['survey_681'], $participant_phone, $twilioClient);
			} else {
				$twiml->say($lang['survey_681'], $say_array);
			}
			// Get previous field (if any)
			$prevField = $Proj->getPrevField($_SESSION['field']);
			$_SESSION['field'] = ($prevField === false) ? '' : $prevField;
		}
		// Do range check if has min or max and passed the data type validation
		elseif ($Proj->metadata[$_SESSION['field']]['element_validation_min'] != '' || $Proj->metadata[$_SESSION['field']]['element_validation_max'] != '') {
			// Check if answer is within range
			if ($Proj->metadata[$_SESSION['field']]['element_validation_min'] != '') {
				if ($answer < $Proj->metadata[$_SESSION['field']]['element_validation_min']) {
					$saveData = false;
				}
			}
			if ($saveData && $Proj->metadata[$_SESSION['field']]['element_validation_max'] != '') {
				if ($answer > $Proj->metadata[$_SESSION['field']]['element_validation_max']) {
					$saveData = false;
				}
			}
			// Set the out of range language
			if ($Proj->metadata[$_SESSION['field']]['element_validation_min'] != '' && $Proj->metadata[$_SESSION['field']]['element_validation_max'] != '') {
				// Both min and max
				$out_of_range_lang = $lang['config_functions_89'] . " " . $Proj->metadata[$_SESSION['field']]['element_validation_min'] . " " .
									 $lang['config_functions_90'] . " " . $Proj->metadata[$_SESSION['field']]['element_validation_max'] . $lang['period'];
			} elseif ($Proj->metadata[$_SESSION['field']]['element_validation_min'] != '') {
				// Just min
				$out_of_range_lang = $lang['config_functions_89'] . " " . $Proj->metadata[$_SESSION['field']]['element_validation_min'] . $lang['period'];
			} else {
				// Just max
				$out_of_range_lang = $lang['config_functions_88'] . " " . $Proj->metadata[$_SESSION['field']]['element_validation_max'] . $lang['period'];
			}
			// Value out of range, so give error message
			if (!$saveData) {
				// An invalid choice was entered
				if (SMS) {
					TwilioRC::preventInvalidResponseLoop($project_id, $fetched, $event_id, $_SESSION['field'], $answer);
					TwilioRC::sendSMS($lang['config_functions_57'] . " $out_of_range_lang", $participant_phone, $twilioClient);
				} else {
					$twiml->say($lang['config_functions_57'] . " $out_of_range_lang", $say_array);
				}
				// Get previous field (if any)
				$prevField = $Proj->getPrevField($_SESSION['field']);
				$_SESSION['field'] = ($prevField === false) ? '' : $prevField;
			}
		}
	}
	// SLIDERS: Value must be numeric between 0 and 100
	elseif ($Proj->metadata[$_SESSION['field']]['element_type'] == 'slider')
	{
		// Make sure it's an integer between 0 and 100
		if (!($answer == (int)$answer && preg_match("/^[-+]?\b\d+\b$/", $answer)) || $answer < 0 || $answer > 100) {
			// Set flag so we don't save data
			$saveData = false;
			// An invalid choice was entered
			if (SMS) {
				TwilioRC::preventInvalidResponseLoop($project_id, $fetched, $event_id, $_SESSION['field'], $answer);
				TwilioRC::sendSMS($lang['survey_681'], $participant_phone, $twilioClient);
			} else {
				$twiml->say($lang['survey_681'], $say_array);
			}
			// Get previous field (if any)
			$prevField = $Proj->getPrevField($_SESSION['field']);
			$_SESSION['field'] = ($prevField === false) ? '' : $prevField;
		}
	}

	## SAVE THE DATA: Simulate new Post submission (as if submitted via data entry form)
	if ($saveData)
	{
		// Reset Post array
		$_POST = array($table_pk=>$fetched, $form_name."_complete"=>'0', $_SESSION['field']=>$answer);
		// Save values and log it
		DataEntry::saveRecord($fetched);
		// INSERT/UPDATE RESPONSE TABLE: Double check to make sure this response isn't already in the response table (use record and participant_id to match)
		$sql  = "select response_id from redcap_surveys_response where participant_id = '" . db_escape($participant_id) . "' and ";
		if (isset($_SESSION['response_id']) && is_numeric($_SESSION['response_id'])) {
			$sql .= "response_id = {$_SESSION['response_id']} limit 1";
		} else {
			$sql .= "record = '" . db_escape($fetched) . "' limit 1";
		}
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			// UPDATE the response with first_submit_time (if null)
			$_SESSION['response_id'] = db_result($q, 0);
			$sql = "update redcap_surveys_response set first_submit_time = '" . NOW . "'
					where response_id = {$_SESSION['response_id']} and first_submit_time is null";
			db_query($sql);
		} else {
			// INSERT new incomplete response's first_submit_time if not in table yet
			$sql = "insert into redcap_surveys_response (participant_id, record, first_submit_time, instance)
					values (" . checkNull($participant_id) . ", " . checkNull($fetched) . ", '".NOW."', {$_GET['instance']})";
			$q = db_query($sql);
			if ($q && db_affected_rows() > 0) {
				$_SESSION['response_id'] = db_insert_id();
			}
		}
		// Set flag if not already set
		$record_exists = 1;
		// Reset $record_data with newly saved data
		$record_data = Records::getData('array', $fetched);
	}
}



## DETERMINE WHICH QUESTION TO SAY
// Get the current field from the session
$field = (!isset($_SESSION['field']) || (isset($_SESSION['field']) && $_SESSION['field'] == '')) ? null : $_SESSION['field'];
// If question is being repeated, then get previous field
if (isset($_GET['__prevpage']) && isset($_SESSION['field']) && !$isPromisInstrument) {
	$field = $_SESSION['field'] = $Proj->getPrevField($field);
}
if ($field == null) {
	## FIRST QUESTION
	// If an existing response for unique link has been started but not completed, and they are returning,
	// then erase all previous responses for this survey.
	if ($record_exists && !$public_survey && isset($_SESSION['response_id']) && !isset($_GET['__prevpage']))
	{
		// Check if survey was started and never completed
		$sql = "select 1 from redcap_surveys_response where response_id = {$_SESSION['response_id']}
				and first_submit_time is not null and completion_time is null";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			## ERASE PREVIOUS RESPONSES: Since we're restarting an uncompleted survey, erase all answers.
			// Get list of all fields with data for this record
			$sql = "select distinct field_name from redcap_data where project_id = $project_id and event_id = $event_id and record = '".db_escape($fetched)."'
					and field_name in (" . prep_implode(array_keys($Proj->forms[$form_name]['fields'])) . ") and field_name != '$table_pk'";
			$q = db_query($sql);
			$eraseFields = $eraseFieldsLogging = array();
			while ($row = db_fetch_assoc($q)) {
				// Add to field list
				$eraseFields[] = $row['field_name'];
				// Add default data values to logging field list
				if ($Proj->isCheckbox($row['field_name'])) {
					foreach (array_keys(parseEnum($Proj->metadata[$row['field_name']]['element_enum'])) as $this_code) {
						$eraseFieldsLogging[] = "{$row['field_name']}($this_code) = unchecked";
					}
				} else {
					$eraseFieldsLogging[] = "{$row['field_name']} = ''";
				}
			}
			// Delete all responses from data table for this form (do not delete actual record name - will keep same record name)
			$sql = "delete from redcap_data where project_id = $project_id and event_id = $event_id and record = '".db_escape($fetched)."'
					and field_name in (" . prep_implode($eraseFields) . ")";
			db_query($sql);
			// Log the data change
			Logging::logEvent($sql, "redcap_data", "UPDATE", $fetched, implode(",\n",$eraseFieldsLogging), "Erase survey responses and start survey over");
		}
	}
	// Get the name of the first field
	if ($isPromisInstrument) {
		// Get next CAT field, and let REDCap render it normally as SMS/VOICE
		$field = PROMIS::renderPromisForm(PROJECT_ID, $form_name, $participant_id);
	} else {
		// Get first field on instrument (excluding PK field)
		foreach ($Proj->forms[$form_name]['fields'] as $field=>$field_label) {
			if ($field != $table_pk) break;
		}
	}
	// SAY SURVEY INSTRUCTIONS: Get survey instruction text and say it (if "field" is NOT in the query string)
	// Get instruction text
	$instructions = trim(replaceNBSP(strip_tags(label_decode($Proj->surveys[$survey_id]['instructions']))));
	if (!isset($_SESSION['field']) && $instructions != '') {
		if ($record_exists) {
			$instructions = Piping::replaceVariablesInLabel($instructions, $fetched, $event_id, 1, $record_data, true, null, false);
		}
		// Pause initially to give participant time to pick up and begin hearing
		if (SMS) {
			TwilioRC::sendSMS($instructions, $participant_phone, $twilioClient);
		} else {
			$twiml->say($instructions, $say_array);
			$twiml->pause("");
		}
	}
} else {
	## ALL QUESTIONS (EXCLUDING THE FIRST)
	// Get next field (if not at end of survey)
	if ($isPromisInstrument) {
		$nextField = PROMIS::renderPromisForm(PROJECT_ID, $form_name, $participant_id);
	} else {
		$nextField = $Proj->getNextField($_SESSION['field']);
	}
	if ($nextField !== false)
	{
		// Now we have the next field
		$field = $nextField;

		## CHECK TO SKIP NEXT FIELD OR TO READ IT IF IT'S A DESCRIPTIVE FIELD (exclude CATs)
		if (!$isPromisInstrument)
		{
			do {
				// Determine if field should be skipped because it doesn't work for this medium (SMS or voice call)
				// and also if field is a Descriptive field
				$skipField = (TwilioRC::fieldUsageIVR((SMS ? 'SMS' : 'VOICE'), $field) !== true);
				$isDescriptiveField = ($Proj->metadata[$field]['element_type'] == 'descriptive');
				$branching_logic = $Proj->metadata[$field]['branching_logic'];

				// BRANCHING: Determine if field needs to be skipped due to branching logic (if record already exists)
				if (!$skipField && $record_exists && $branching_logic != '') {
					if ($longitudinal) {
						$branching_logic = LogicTester::logicPrependEventName($branching_logic, $Proj->getUniqueEventNames($event_id), $Proj);
					}
					$skipField = !LogicTester::evaluateLogicSingleRecord($branching_logic, $fetched, $record_data);
				}

				## SKIP FIELD
				if ($skipField) {
					// Get next field for next loop (if not at end of survey)
					$field = $Proj->getNextField($field);
				}
				## DESCRIPTIVE FIELD: If field is a Descriptive field, then simply say the label
				elseif ($isDescriptiveField) {
					// Section header for descriptive field
					if ($Proj->metadata[$field]['element_preceding_header'] != '') {
						// Piping (if record exists)
						$element_preceding_header = strip_tags(label_decode($Proj->metadata[$field]['element_preceding_header']));
						if ($record_exists) {
							$element_preceding_header = Piping::replaceVariablesInLabel($element_preceding_header, $fetched, $event_id, 1, $record_data, true, null, false);
						}
						if (SMS) {
							TwilioRC::sendSMS($element_preceding_header, $participant_phone, $twilioClient);
						} else {
							$twiml->say(replaceNBSP($element_preceding_header), $say_array);
						}
					}
					// Piping (if record exists)
					$label = strip_tags(label_decode($Proj->metadata[$field]['element_label']));
					if ($record_exists) {
						$label = Piping::replaceVariablesInLabel($label, $fetched, $event_id, 1, $record_data, true, null, false);
					}
					// Say descriptive label
					if (SMS) {
						TwilioRC::sendSMS($label, $participant_phone, $twilioClient);
					} else {
						$twiml->say(replaceNBSP($label), $say_array);
					}
					// Get next field for next loop (if not at end of survey)
					$field = $Proj->getNextField($field);
				}
			}
			while (($isDescriptiveField || $skipField) && $field !== false && $field != $form_name."_complete");
		}

		## END OF SURVEY (FORM STATUS FIELD)
		// If field is the Form Status field, then we're done with the call
		if ($field == $form_name."_complete" || $endSurveyViaStopAction)
		{
			// Delete the Survey Access Code for a given phone number from the redcap_surveys_phone_codes table (if applicable)
			if (isset($_POST[$table_pk])) {
				TwilioRC::deleteSmsAccessCodeFromPhoneNumber($participant_phone, $twilio_from_number,
					($twilio_multiple_sms_behavior == 'OVERWRITE' ? null : $_SESSION['survey_access_code']));
			}
			// Set survey response as "completed"
			if ($saveData) {
				// Set form status field as complete by adding it to Post array
				$_POST[$form_name."_complete"] = '2';
				// Save values and log it
				DataEntry::saveRecord($fetched);
				// Mark as complete in survey response table
				$sql = "update redcap_surveys_response set completion_time = '" . NOW . "'
						where response_id = {$_SESSION['response_id']}";
				$q = db_query($sql);
				// Delete any scheduled invitations for this survey since we just completed the survey
				SurveyScheduler::deleteInviteIfCompletedSurvey($survey_id, $_GET['event_id'], $fetched, $_GET['instance']);
				// If survey is officially completed, then send an email to survey admins AND send confirmation email to respondent, if enabled.
				Survey::sendSurveyConfirmationEmail($survey_id, $_GET['event_id'], $fetched);
				Survey::sendEndSurveyEmails($survey_id, $_GET['event_id'], $participant_id, $fetched, $_GET['instance']);
				// Alert
				$eta = new Alerts();
				$eta->saveRecordAction(PROJECT_ID, $fetched, $form_name, $_GET['event_id'], $_GET['instance']);
			}
			// If SURVEY AUTO-CONTINUE is enabled, then go to next surve
			if ($end_survey_redirect_next_survey) {
				// Get the next survey url
				$next_survey_url = Survey::getAutoContinueSurveyUrl($fetched, $form_name, $_GET['event_id'], $_GET['instance']);
				// Redirect to next survey (tells Twilio what URL to call next)
				if (SMS) {
					// Remove hash from URL
					list ($nothing, $next_survey_hash) = explode("?s=", $next_survey_url, 2);
					// Clear session data for this survey and set new survey access code for next survey
					$_SESSION = array();
					$_SESSION['survey_access_code'] = Survey::getAccessCode(Survey::getParticipantIdFromHash($next_survey_hash), false, false, true);
					// Redirect
					TwilioRC::redirectSurvey($next_survey_hash);
				} else {
					// Destroy the session
					$_SESSION = array();
					session_unset();
					session_destroy();
					// Redirect to next survey
					$twiml->redirect($next_survey_url, array('method'=>'POST'));
					exit($twiml);
				}
			}
			// SURVEY QUEUE: Is the queue enabled?
			$surveyQueueEnabled = Survey::surveyQueueEnabled();
			if ($surveyQueueEnabled) {
				// Get survey queue items for this record
				$survey_queue_items = Survey::getSurveyQueueForRecord($fetched);
				// If empty, then return and display nothing
				if (!empty($survey_queue_items)) {
					// AUTO-START: If enabled for the first incomplete survey in queue, then redirect there
					// Loop through queue to find the first incomplete survey
					foreach ($survey_queue_items as $queueAttr) {
						// If already completed, or if a repeating instance, then skip to next item
						if ($queueAttr['completed'] > 0 || (isset($queueAttr['instance']) && $queueAttr['instance'] > 1)) continue;
						if ($queueAttr['auto_start']) {
							// Redirect to next survey (tells Twilio what URL to call next)
							if (SMS) {
								// Clear session data for this survey and set new survey access code for next survey
								$_SESSION = array();
								$_SESSION['survey_access_code'] = Survey::getAccessCode($queueAttr['participant_id'], false, false, true);
								// Redirect
								TwilioRC::redirectSurvey($queueAttr['hash']);
							} else {
								// Destroy the session
								$_SESSION = array();
								session_unset();
								session_destroy();
								// Redirect next survey
								$twiml->redirect(APP_PATH_SURVEY_FULL . "?s={$queueAttr['hash']}", array('method'=>'POST'));
								exit($twiml);
							}
						}
						// Stop looping if first incomplete survey does not have auto-start enabled
						break;
					}
				}
			}
			// Destroy the session
			$_SESSION = array();
			session_unset();
			session_destroy();
			// SAY ACKNOWLEDGEMENT TEXT: Get acknowledgement text and say it
			$acknowledgement = trim(replaceNBSP(strip_tags(Piping::replaceVariablesInLabel(label_decode($Proj->surveys[$survey_id]['acknowledgement']), $fetched, $event_id, 1, array(), true, null, false))));
			if ($acknowledgement != '') {
				if (SMS) {
					TwilioRC::sendSMS($acknowledgement, $participant_phone, $twilioClient);
				} else {
					$twiml->say($acknowledgement, $say_array);
				}
			}
			// Survey Queue: If queue is enabled (but auto-start is not enabled), then give option to start next survey
			if ($surveyQueueEnabled && !empty($survey_queue_items)) {
				// Find the first incomplete survey in the queue
				foreach ($survey_queue_items as $queueAttr) {
					// Skip any that are completed
					if ($queueAttr['completed'] == '1') continue;
					// Skip the current survey if the participant didn't complete the current survey
					if ($hash == $queueAttr['hash']) continue;
					// Redirect to next survey (tells Twilio what URL to call next)
					if (SMS) {
						// Set new phone code for next survey
						$access_code = Survey::getAccessCode($queueAttr['participant_id'], false, false, true);
						// Redirect
						TwilioRC::addSmsAccessCodeForPhoneNumber($participant_phone, $twilio_from_number, $access_code, PROJECT_ID);
						// Redirect to next survey if reply with any text
						sleep(2);
						TwilioRC::sendSMS($lang['survey_900'], $participant_phone, $twilioClient);
					} else {
						// Redirect to next survey if press any key
						$twiml->pause("");
						$gather = $twiml->gather(array('method'=>'POST', 'action'=>APP_PATH_SURVEY_FULL . "?s={$queueAttr['hash']}", 'timeout'=>5, 'finishOnKey'=>TwilioRC::VOICE_SKIP_DIGIT));
						// Say the label(s)
						$gather->say($lang['survey_899'], $say_array);
						exit($twiml);
					}
					// Stop looping if first incomplete survey does not have auto-start enabled
					break;
				}
			}
			// End call
			if (!SMS) $twiml->hangup();
			exit($twiml);
		}
	}
}





/**
 * SAY QUESTION
 */
do {
	// Set field name in session for the next question
	$_SESSION['field'] = $field;

	// Set defaults
	$skipField = $isDescriptiveField = false;

	// Check if we need to display section headers, descriptive fields, or skip this question (exclude CATs)
	if (!$isPromisInstrument)
	{
		## SECTION HEADER: If field has SH, then say its label first
		if ($Proj->metadata[$field]['element_preceding_header'] != '') {
			// Piping (if record exists)
			$element_preceding_header = strip_tags(label_decode($Proj->metadata[$field]['element_preceding_header']));
			if ($record_exists) {
				$element_preceding_header = Piping::replaceVariablesInLabel($element_preceding_header, $fetched, $event_id, 1, $record_data, true, null, false);
			}
			if (SMS) {
				TwilioRC::sendSMS($element_preceding_header, $participant_phone, $twilioClient);
			} else {
				$twiml->say(replaceNBSP($element_preceding_header), $say_array);
			}
		}

		// Determine if field should be skipped because it doesn't work for this medium (SMS or voice call)
		// and also if field is a Descriptive field
		$skipField = (TwilioRC::fieldUsageIVR((SMS ? 'SMS' : 'VOICE'), $field) !== true);
		$isDescriptiveField = ($Proj->metadata[$field]['element_type'] == 'descriptive');
		$branching_logic = $Proj->metadata[$field]['branching_logic'];

		// BRANCHING: Determine if field needs to be skipped due to branching logic (if record already exists)
		if (!$skipField && $record_exists && $branching_logic != '') {
			if ($longitudinal) {
				$branching_logic = LogicTester::logicPrependEventName($branching_logic, $Proj->getUniqueEventNames($event_id), $Proj);
			}
			$skipField = !LogicTester::evaluateLogicSingleRecord($branching_logic, $fetched, $record_data);
		}

		## SKIP FIELD
		if ($skipField) {
			// Get next field for next loop (if not at end of survey)
			$field = $Proj->getNextField($field);
		}
		## DESCRIPTIVE FIELD: If field is a Descriptive field, then simply say the label
		elseif ($isDescriptiveField) {
			// Piping (if record exists)
			$label = strip_tags(label_decode($Proj->metadata[$field]['element_label']));
			if ($record_exists) {
				$label = Piping::replaceVariablesInLabel($label, $fetched, $event_id, 1, $record_data, true, null, false);
			}
			// Say descriptive label
			if (SMS) {
				TwilioRC::sendSMS($label, $participant_phone, $twilioClient);
			} else {
				$twiml->say(replaceNBSP($label), $say_array);
			}
			// Get next field for next loop (if not at end of survey)
			$field = $Proj->getNextField($field);
		}
	}
}
while (($isDescriptiveField || $skipField) && $field !== false);


## NORMAL FIELD
if ($field !== false)
{
	// Get the field's validation type
	$val_type = convertLegacyValidationType($Proj->metadata[$field]['element_validation_type']);
	$data_type = ($Proj->metadata[$field]['element_type'] == 'text' && $val_type != '') ? $valTypes[$val_type]['data_type'] : '';

	## BUILD THE LABEL(S)
	$label = replaceNBSP(strip_tags(label_decode($Proj->metadata[$field]['element_label']))) . " ";
	// Number of choices (only applicable for multiple choice fields)
	$choices_max_digits = '50'; // Set ridiculously large number as default
	// Build label based on field type and/or validation
	if (SMS && $twilio_append_response_instructions && in_array($data_type, array('date', 'datetime', 'datetime_seconds'))) {
		// Date/time fields (for SMS only)
		$label .= "(" . MetaData::getDateFormatDisplay($val_type) . ")";
	} elseif ($Proj->metadata[$field]['element_type'] == 'slider') {
		// Slider
		if ($twilio_append_response_instructions) $label .= $lang['survey_898'];
		// Set max digits as 3 (because max value is 100)
		$choices_max_digits = 3;
	} elseif ($twilio_append_response_instructions && $Proj->metadata[$field]['element_type'] == 'truefalse') {
		// TF
		if (SMS) {
			$label .= "(0) {$lang['design_187']}, (1) {$lang['design_186']}";
		} else {
			$label .= $lang['survey_952'];
		}
	} elseif ($twilio_append_response_instructions && $Proj->metadata[$field]['element_type'] == 'yesno') {
		// YN
		if (SMS) {
			$label .= "(0) {$lang['design_99']}, (1) {$lang['design_100']}";
		} else {
			$label .= $lang['survey_953'];
		}
	} elseif ($Proj->isMultipleChoice($field) || $Proj->metadata[$field]['element_type'] == 'sql') {
		// If this label does not end with period, then add it (for VOICE only to create pauses)
		if (!SMS && substr(trim($label), -1) != '.' && substr(trim($label), -1) != '?') {
			$label .= ". ";
		} elseif ($isPromisInstrument) {
			$label = trim($label) . ": ";
		}
		// Multiple Choice fields: Get enum
		$this_enum = ($Proj->metadata[$field]['element_type'] == 'sql') ? getSqlFieldEnum($Proj->metadata[$field]['element_enum']) : $Proj->metadata[$field]['element_enum'];
		$this_enum_array = parseEnum($this_enum);
		$choices_max_digits = TwilioRC::getChoicesMaxDigits($this_enum_array);
		$this_grid_name = $Proj->metadata[$field]['grid_name'];
		// If field is a radio button with matrix ranking, then remove any choices that already have a value
		if ($this_grid_name != '' && $Proj->matrixGroupHasRanking[$this_grid_name]) {
			// Loop through all choices in this matrix
			foreach ($Proj->matrixGroupNames[$this_grid_name] as $this_matrix_field) {
				// Does this field have a saved value?
				if ($record_data[$fetched][$event_id][$this_matrix_field] != '' && isset($this_enum_array[$record_data[$fetched][$event_id][$this_matrix_field]])) {
					// Remove this choice from $this_enum_array
					unset($this_enum_array[$record_data[$fetched][$event_id][$this_matrix_field]]);
				}
			}
		}
		// Loop and say each choice option label
		foreach ($this_enum_array as $code=>$this_label) {
			// Remove tags and set main label (for CATs, add the Code to the label)
			$this_label = replaceNBSP(strip_tags(label_decode($this_label)));
			// If auto-appending instructions OR if a CAT
			if ($twilio_append_response_instructions || $isPromisInstrument) {
				if (SMS) {
					$this_label = "($code) $this_label,";
				} else {
					$this_label = "$this_label. {$lang['survey_951']} $code. ";
				}
			}
			// If this choice does not end with period, then add it (for VOICE only to create pauses)
			if (!SMS && substr(trim($this_label), -1) != '.' && substr(trim($this_label), -1) != '?') {
				$this_label .= ".";
			}
			$label .= "$this_label ";
		}
		if (SMS && ($twilio_append_response_instructions || $isPromisInstrument)) {
			// Remove trailing comma from last choice
			if (substr(trim($label), -1) == ',') {
				$label = trim(substr(trim($label), 0, -1)) . ".";
			}
			// Append note at end of SMS that they can reply with 0 to skip the question
			if ($isPromisInstrument && $promis_skip_question) {
				$label = trim($label) . " " . $lang['survey_906'];
			}
		}
	}
	$label = trim($label);
	// Piping (if record exists)
	if ($record_exists) {
		$label = Piping::replaceVariablesInLabel($label, $fetched, $event_id, 1, $record_data, true, null, false);
	}
	// If an integer field and has a max validation, then use it to set $choices_max_digits
	if ($Proj->metadata[$field]['element_type'] == 'text' && $data_type == 'integer' && is_numeric(trim($Proj->metadata[$field]['element_validation_max'].""))) {
		$choices_max_digits = strlen(trim($Proj->metadata[$field]['element_validation_max'].""));
	}

	// SMS question
	if (SMS)
	{
		// Set SMS body for this question
		TwilioRC::sendSMS($label, $participant_phone, $twilioClient);
	}
	// VOICE question
	elseif (VOICE)
	{
		// Set base URL for question script (tells Twilio what URL to call next)
		$question_url = APP_PATH_SURVEY_FULL . "?s=$hash&voice=$voice&language=$language";
		// The the gather array params
		$gather_params = array('method'=>'POST', 'action'=>$question_url, 'timeout'=>3, 'finishOnKey'=>TwilioRC::VOICE_SKIP_DIGIT, 'numDigits'=>$choices_max_digits);

		// Ask question
		$gather = $twiml->gather($gather_params);
		$gather->say($label, $say_array);
		// Do another instance of the same quesetion but with no label for it to say so that it gives participant more initial time to respond
		$gather = $twiml->gather($gather_params);
		$gather->say("", $say_array);

		// If a CAT, ask user if they want to skip (press 0) else repeat the question if skipping is not enabled
		if ($isPromisInstrument) {
			// Tell user they can skip question by pressing 0
			if ($promis_skip_question) {
				$skip_cat_q = $twiml->gather($gather_params);
				$skip_cat_q->say($lang['survey_905'], $say_array);
			}
			// Repeat the question two more times in case participant doesn't respond
			else {
				for ($i = 1; $i <= 2; $i++) {
					$skip_cat_q = $twiml->gather($gather_params);
					$skip_cat_q->say($label, $say_array);
				}
			}
		}
		// If user doesn't respond, then ask again if the field is required, but if not, go to next question.
		// If this is a CAT and doesn't have question-skipping enabled, then force it as required.
		elseif ($Proj->metadata[$field]['field_req'] || ($isPromisInstrument && !$promis_skip_question)) {
			$question_url .= "&__prevpage=1";
		}
		// Ask the question one more time if they didn't answer it (if this is NOT a CAT and this is not a Required Field)
		elseif (!$isPromisInstrument) {
			$gather2 = $twiml->gather($gather_params);
			$gather2->say($label, $say_array);
			$gather = $twiml->gather($gather_params);
			$gather->say("", $say_array);
		}
		$twiml->redirect($question_url, array('method'=>'POST'));
	}
}

## Output call XML
print $twiml;