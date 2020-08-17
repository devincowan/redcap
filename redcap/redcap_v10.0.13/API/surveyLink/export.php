<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// Get user's user rights


$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges();

// If user has "No Access" export rights, then return error
if ($user_rights['participants'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you do not have "Manage Survey Participants" privileges, which are required for this operation.'));
}

// Set vars
$project_id = $_GET['pid'] = $post['projectid'];
$record = (isset($post['record']) && $post['record'] != '') ? $post['record'] : '';
$form_name = (isset($post['instrument']) && $post['instrument'] != '') ? $post['instrument'] : '';
$eventName = (isset($post['event']) && $post['event'] != '') ? $post['event'] : '';

// Validate record
if ($record == '') {
	RestUtility::sendResponse(400, "The parameter 'record' is missing");
} elseif (!Records::recordExists(PROJECT_ID, $record)) {
	RestUtility::sendResponse(400, "The record '$record' does not exist");
}

// Validate instrument
if ($form_name == '') {
	RestUtility::sendResponse(400, "The parameter 'instrument' is missing");
} elseif ($form_name != '' && !isset($Proj->forms[$form_name])) {
	RestUtility::sendResponse(400, "Invalid instrument");
} elseif ($form_name != '' && !isset($Proj->forms[$form_name]['survey_id'])) {
	RestUtility::sendResponse(400, "The instrument '$form_name' has not been enabled as a survey");
}

// Validate event
if ($longitudinal) {
	# check the event that was passed in and get the id associated with it
	if ($eventName == '') {
		RestUtility::sendResponse(400, "The parameter 'event' is missing");
	} elseif ($eventName != '') {
		$eventId = $Proj->getEventIdUsingUniqueEventName($eventName);
		if (!is_numeric($eventId)) {
			RestUtility::sendResponse(400, "Invalid event");
		}
	}
} else {
	$eventId = $Proj->firstEventId;
}

// If project has repeating forms/events, then use the repeat_instance
$instance = (isset($post['repeat_instance']) && is_numeric($post['repeat_instance']) && $post['repeat_instance'] > 0) ? $post['repeat_instance'] : 1;
if (!$Proj->isRepeatingForm($eventId, $form_name) && !($Proj->longitudinal && $Proj->isRepeatingEvent($eventId))) {
	$instance = 1;
}

// Get survey link
$survey_link = REDCap::getSurveyLink($record, $form_name, $eventId, $instance);

// Check for errors
if ($survey_link == null) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = "record = '$record',\nform_name = '$form_name'";
	if ($longitudinal) $logging_data_values .= ",\nevent_id = $eventId";
	$_GET['event_id'] = $eventId;
	Logging::logEvent("","redcap_surveys_participants","MANAGE",$record,$logging_data_values,"Download survey link (API$playground)", 
						"", "", "", true, null, $instance);
	// Return the link text
	print $survey_link;
}
