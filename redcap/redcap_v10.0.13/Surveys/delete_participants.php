<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();
if (!isset($_GET['event_id']))  $_GET['event_id']  = getEventId();
// Ensure the survey_id belongs to this project and that Post method was used
if (!$Proj->validateEventIdSurveyId($_GET['event_id'], $_GET['survey_id']))	exit("0");

$response = "0"; //Default

// Obtain list of participant_id's that cannot be deleted because they already have data


// Delete the participant from the participants table (for this survey-event only)
$sql = "delete from redcap_surveys_participants where event_id = {$_GET['event_id']} and survey_id = {$_GET['survey_id']}
		and participant_email is not null and participant_id not in (".pre_query("select p.participant_id from
		redcap_surveys_participants p, redcap_surveys_response r where p.event_id = {$_GET['event_id']} and p.survey_id = {$_GET['survey_id']}
		and p.participant_email is not null and p.participant_id = r.participant_id").")";
if (db_query($sql))
{
	// Logging
	Logging::logEvent($sql,"redcap_surveys_participants","MANAGE",$_GET['survey_id'],"survey_id = {$_GET['survey_id']}\nevent_id = {$_GET['event_id']}","Delete all survey participants");
	// Set response
	$response = "1";
}


exit($response);