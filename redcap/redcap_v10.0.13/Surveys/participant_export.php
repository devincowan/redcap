<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = Survey::getSurveyId();
}

// Ensure the survey_id belongs to this project
if (!Survey::checkSurveyProject($_GET['survey_id']))
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
}

// Retrieve survey info
$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value, ENT_QUOTES));
}

// Obtain current arm_id
$_GET['event_id'] = getEventId();
$_GET['arm_id'] = getArmId();
$hasRepeatingInstances = ($Proj->isRepeatingEvent($_GET['event_id']) || $Proj->isRepeatingForm($_GET['event_id'], $form_name));
$surveyQueueEnabled = Survey::surveyQueueEnabled();

// Gather participant list (with identfiers and if Sent/Responded)
$part_list = REDCap::getParticipantList($Proj->surveys[$_GET['survey_id']]['form_name'], $_GET['event_id']);

// Check if time limit is enabled for survey
$timeLimitEnabled = (Survey::calculateSurveyTimeLimit($Proj->surveys[$_GET['survey_id']]['survey_time_limit_days'], $Proj->surveys[$_GET['survey_id']]['survey_time_limit_hours'], $Proj->surveys[$_GET['survey_id']]['survey_time_limit_minutes']) > 0);

// Add headers for CSV file
$headers = array($lang['control_center_56'], $lang['survey_69']);
if ($twilio_enabled && $Proj->twilio_enabled_surveys) $headers[] = $lang['design_89'];
$headers[] = $lang['global_49'];
if ($hasRepeatingInstances) $headers[] = $lang['global_133'];
$headers[] = $lang['survey_46'];
$headers[] = $lang['survey_47'];
$headers[] = $lang['survey_628'];
if ($timeLimitEnabled) {
	$headers[] = $lang['survey_1117'];
}
$headers[] = $lang['global_90'];
if (isset($surveyQueueEnabled) && $surveyQueueEnabled) $headers[] = $lang['survey_553'];

// Begin writing file from query result
$fp = fopen('php://memory', "x+");

if ($fp)
{
	// Write headers to file
	fputcsv($fp, $headers);

	// Set values for this row and write to file
	foreach ($part_list as $row)
	{
		
		// Remove attr not needed here
		unset($row['email_occurrence'], $row['invitation_send_time']);
		// If not have repeating instances for this event or event/form, then remove attr
		if (!$hasRepeatingInstances) unset($row['repeat_instance']);
		// Convert boolean to text
		$row['invitation_sent_status'] = ($row['invitation_sent_status'] == '1') ? $lang['design_100'] : $lang['design_99'];
		switch ($row['response_status']) {
			case '2':
				$row['response_status'] = $lang['design_100'];
				break;
			case '1':
				$row['response_status'] = $lang['survey_27'];
				break;
			default:
				$row['response_status'] = $lang['design_99'];
		}
		// Add row to CSV
		fputcsv($fp, $row);
	}

	// Logging
	Logging::logEvent("","redcap_surveys_participants","MANAGE",$_GET['survey_id'],"survey_id = {$_GET['survey_id']}\narm_id = {$_GET['arm_id']}","Export survey participant list");

	// Open file for downloading
	$download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_Participants_" . date("Y-m-d_Hi") . ".csv";
	header('Pragma: anytextexeptno-cache', true);
	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=$download_filename");

	// Open file for reading and output to user
	fseek($fp, 0);
	print addBOMtoUTF8(stream_get_contents($fp));

}
else
{
	print $lang['global_01'];
}
