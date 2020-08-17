<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();
if (!isset($_GET['event_id']))  $_GET['event_id']  = getEventId();
// Ensure the survey_id belongs to this project and that Post method was used
if (!$Proj->validateEventIdSurveyId($_GET['event_id'], $_GET['survey_id']))	exit("0");

// Retrieve survey info
$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value, ENT_QUOTES));
}

// Default
$response = '';

if (isset($_POST['participant_id']) && is_numeric($_POST['participant_id']))
{
	// Save the email address
	if (isset($_POST['email']))
	{
		$_POST['email'] = strip_tags(label_decode(trim($_POST['email'])));
		// Set resonse to be JSON string of identifier and all participant IDS
		$response = array('item'=>$_POST['email'], 'participant_id'=>array($_POST['participant_id']));
		// In case this is a repeating instrument, then find the participant_id of instance #1 of this instrument
		// (since the identifier ultimately originated from instance #1)
		if ($Proj->hasRepeatingFormsEvents() && ($Proj->isRepeatingEvent($_GET['event_id']) || $Proj->isRepeatingForm($_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']))) {
			$sql = "select p2.participant_id, r2.instance
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys_participants p2, redcap_surveys_response r2
					where p.participant_id = {$_POST['participant_id']} and r.participant_id = p.participant_id and r2.participant_id = p2.participant_id
					and p.event_id = p2.event_id and p.survey_id = p2.survey_id and r.record = r2.record";
			$q = db_query($sql);
			$participant_ids = array();
			while ($row = db_fetch_assoc($q)) {
				$participant_ids[] = $row['participant_id'];
				// If this is instance #1, then set its participant_id as the first one which gets the identifier
				if ($row['instance'] == 1) $_POST['participant_id'] = $row['participant_id'];
			}
			// Add all participant_ids to array so they can all be updated in the UI
			$response['participant_id'] = $participant_ids;
		}
		// Update the table
		$sql = "update redcap_surveys_participants set participant_email = '" . db_escape($_POST['email']) . "'
				where participant_id = {$_POST['participant_id']}";
		if (db_query($sql))
		{
			// Return string as JSON
			$response = json_encode($response);
			// Logging
			Logging::logEvent($sql,"redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = " . $_POST['participant_id'],"Edit survey participant email address");
		}
	}
	// Save the identifier
	elseif (isset($_POST['identifier']))
	{
		$_POST['identifier'] = trim(strip_tags(label_decode($_POST['identifier'])));
		// Set resonse to be JSON string of identifier and all participant IDS
		$response = array('item'=>$_POST['identifier'], 'participant_id'=>array($_POST['participant_id']));
		// In case this is a repeating instrument, then find the participant_id of instance #1 of this instrument
		// (since the identifier ultimately originated from instance #1)
		if ($Proj->hasRepeatingFormsEvents() && ($Proj->isRepeatingEvent($_GET['event_id']) || $Proj->isRepeatingForm($_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']))) {
			$sql = "select p2.participant_id, r2.instance
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys_participants p2, redcap_surveys_response r2
					where p.participant_id = {$_POST['participant_id']} and r.participant_id = p.participant_id and r2.participant_id = p2.participant_id
					and p.event_id = p2.event_id and p.survey_id = p2.survey_id and r.record = r2.record";
			$q = db_query($sql);
			$participant_ids = array();
			while ($row = db_fetch_assoc($q)) {
				$participant_ids[] = $row['participant_id'];
				// If this is instance #1, then set its participant_id as the first one which gets the identifier
				if ($row['instance'] == 1) $_POST['participant_id'] = $row['participant_id'];
			}
			// Add all participant_ids to array so they can all be updated in the UI
			$response['participant_id'] = $participant_ids;
		}
		// Update the table
		$sql = "update redcap_surveys_participants set participant_identifier = '" . db_escape($_POST['identifier']) . "'
				where participant_id = {$_POST['participant_id']}";
		if (db_query($sql))
		{
			// Return string as JSON
			$response = json_encode($response);
			// Logging
			Logging::logEvent($sql,"redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = " . $_POST['participant_id'],"Edit survey participant identifier");
		}		
	}
	// Save the phone number
	elseif (isset($_POST['phone']))
	{
		$_POST['phone'] = preg_replace("/[^0-9]/", "", trim($_POST['phone']));
		// Set resonse to be JSON string of identifier and all participant IDS
		$response = array('item'=>formatPhone($_POST['phone']), 'participant_id'=>array($_POST['participant_id']));
		// In case this is a repeating instrument, then find the participant_id of instance #1 of this instrument
		// (since the identifier ultimately originated from instance #1)
		if ($Proj->hasRepeatingFormsEvents() && ($Proj->isRepeatingEvent($_GET['event_id']) || $Proj->isRepeatingForm($_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']))) {
			$sql = "select p2.participant_id, r2.instance
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys_participants p2, redcap_surveys_response r2
					where p.participant_id = {$_POST['participant_id']} and r.participant_id = p.participant_id and r2.participant_id = p2.participant_id
					and p.event_id = p2.event_id and p.survey_id = p2.survey_id and r.record = r2.record";
			$q = db_query($sql);
			$participant_ids = array();
			while ($row = db_fetch_assoc($q)) {
				$participant_ids[] = $row['participant_id'];
				// If this is instance #1, then set its participant_id as the first one which gets the identifier
				if ($row['instance'] == 1) $_POST['participant_id'] = $row['participant_id'];
			}
			// Add all participant_ids to array so they can all be updated in the UI
			$response['participant_id'] = $participant_ids;
		}
		// Update the table
		$sql = "update redcap_surveys_participants set participant_phone = '" . db_escape($_POST['phone']) . "'
				where participant_id = {$_POST['participant_id']}";
		if (db_query($sql))
		{
			// Return string as JSON
			$response = json_encode($response);
			// Logging
			Logging::logEvent($sql,"redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = " . $_POST['participant_id'],"Edit survey participant phone number");
		}
	}
}

print $response;