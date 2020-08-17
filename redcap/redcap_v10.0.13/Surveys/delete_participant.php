<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();
if (!isset($_GET['event_id']))  $_GET['event_id']  = getEventId();
// Ensure the survey_id belongs to this project and that Post method was used
if (!$Proj->validateEventIdSurveyId($_GET['event_id'], $_GET['survey_id']))	exit("0");

$response = "0"; //Default

if (isset($_POST['participant_id']) && is_numeric($_POST['participant_id']))
{
	// Remove from table
	$sql = "delete from redcap_surveys_participants where participant_id = {$_POST['participant_id']}
			and survey_id = {$_GET['survey_id']}";
	if (db_query($sql))
	{
		// Logging
		Logging::logEvent($sql,"redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = {$_POST['participant_id']}","Delete survey participant");
		// Set response
		$response = "1";
	}

}

exit($response);