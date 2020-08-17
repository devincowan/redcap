<?php
global $format, $returnFormat, $post;


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
$form_name = (isset($post['instrument']) && $post['instrument'] != '') ? $post['instrument'] : '';
$eventName = (isset($post['event']) && $post['event'] != '') ? $post['event'] : '';

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

// Get list
$participant_list = REDCap::getParticipantList($form_name, $eventId, $format);

// Check for errors
if ($participant_list == null) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = "form_name = '$form_name'";
	if ($longitudinal) $logging_data_values .= ",\nevent_id = $eventId";
	$_GET['event_id'] = $eventId;
	Logging::logEvent("","redcap_surveys_participants","MANAGE",$form_name,$logging_data_values,"Download survey participant list (API$playground)");
	// Return the list in the desired format
	RestUtility::sendResponse(200, $participant_list, $format);
}
