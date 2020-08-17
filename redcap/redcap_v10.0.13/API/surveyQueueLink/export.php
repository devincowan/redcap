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

// Validate record
if ($record == '') {
	RestUtility::sendResponse(400, "The parameter 'record' is missing");
} elseif (!Records::recordExists(PROJECT_ID, $record)) {
	RestUtility::sendResponse(400, "The record '$record' does not exist");
}

// If survey queue is not enabled for this project yet, return error
if (!Survey::surveyQueueEnabled()) {
	RestUtility::sendResponse(400, "The Survey Queue has not been enabled in this project. You will need to enable the Survey Queue before using this method.");
}

// Get survey link
$survey_queue_link = REDCap::getSurveyQueueLink($record, PROJECT_ID);

// Check for errors
if ($survey_queue_link == null) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = "record = '$record'";
	$_GET['event_id'] = $eventId;
	Logging::logEvent("","redcap_surveys_participants","MANAGE",$record,$logging_data_values,"Download survey queue link (API$playground)");
	// Return the link text
	print $survey_queue_link;
}
