<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Get invitation log as array
$surveyScheduler = new SurveyScheduler;
list ($invitationLog, $displayed_records) = $surveyScheduler->getSurveyInvitationLog();




$headers = array($lang['survey_436'], $lang['survey_1054']);
if ($twilio_enabled && $Proj->twilio_enabled_surveys) $headers[] = $lang['survey_687'];
$headers[] = $lang['survey_392'];
if ($twilio_enabled && $Proj->twilio_enabled_surveys) $headers[] = $lang['survey_1055'];
$headers[] = $lang['global_49'];
$headers[] = $lang['survey_250'];
$headers[] = $lang['survey_437'];
$headers[] = $lang['global_90'];
$headers[] = $lang['survey_47'];
$headers[] = $lang['survey_1056'];

// Begin writing file from query result
$fp = fopen('php://memory', "x+");

if ($fp)
{
	// Write headers to file
	fputcsv($fp, $headers);

	// Set values for this row and write to file
	foreach ($invitationLog as $row)
	{
		// Add elements to this line
		$line = array($row['send_time']);
		$line[] = ($row['reminder_num'] == '0') ? '' : $row['reminder_num'];
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			if ($row['delivery_type'] == 'VOICE_INITIATE') {
				$line[] = $lang['survey_884'];
			} else if ($row['delivery_type'] == 'SMS_INITIATE') {
				$line[] = $lang['survey_767'];
			} else if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
				$line[] = $lang['survey_690'];
			} else if ($row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
				$line[] = $lang['survey_801'];
			} else if ($row['delivery_type'] == 'SMS_INVITE_WEB') {
				$line[] = $lang['survey_955'];
			} else {
				$line[] = $lang['pub_014'];
			}
		}
		$line[] = $row['participant_email'];
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) $line[] = formatPhone($row['participant_phone']);
		$line[] = $row['display_id'];
		$line[] = $row['participant_identifier'];
		$line[] = ($Proj->surveys[$row['survey_id']]['title'] == '' ? $Proj->forms[$Proj->surveys[$row['survey_id']]['form_name']]['menu'] : $Proj->surveys[$row['survey_id']]['title'])
				. (!$longitudinal ? "" : " ".$Proj->eventInfo[$row['event_id']]['name_ext']);
		$line[] = ($row['completed'] == "2") ? '' : APP_PATH_SURVEY_FULL . "?s=" . $row['hash'];
		switch ($row['completed']) {
			case '2':
				$line[] = $lang['design_100'];
				break;
			case '1':
				$line[] = $lang['survey_27'];
				break;
			default:
				$line[] = $lang['design_99'];
		}
		$line[] = $row['reason_not_sent'];
		// Add row to CSV
		fputcsv($fp, $line);
	}

	// Logging
	Logging::logEvent("","redcap_surveys_scheduler_queue","MANAGE","","","Export survey invitation log");

	// Open file for downloading
	$download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_InvitationLog_" . date("Y-m-d_Hi") . ".csv";
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
