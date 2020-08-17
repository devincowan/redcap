<?php


if (isset($_GET['pid']) && $_GET['pid'] == '') {
	// Super users: Non-project page
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// If not a super user, then stop here
	if (!SUPER_USER && !ACCOUNT_MANAGER) exit("");
} else {
	// Project page
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
	// Make sure has Design or Survey Participant or User Rights privileges
	if (!$user_rights['user_rights'] && !$user_rights['design'] && !$user_rights['participants']) exit("");
}

// Look for 'contents' parameter
if (!isset($_POST['contents'])) exit("");

// Replace line breaks with <br>
$message = label_decode($_POST['contents']);

// If survey_id is provided, then obtain the survey title
$surveyLinkText = '';
if (isset($_POST['survey_id']) && is_numeric($_POST['survey_id'])) {
	$surveyLinkText = Survey::getSurveyTitleFromId($_POST['survey_id']);
}
if ($surveyLinkText == '') {
	$surveyLinkText = $lang['survey_1081'];
}

// Filter any potentially harmful tags/attributes
$message = filter_tags($message);

$surveyLink = APP_PATH_SURVEY_FULL . '?s=SAMPLE_LINK';
$orig = array("[survey-link]", "[survey-url]");
$repl = array('<a style="text-decoration:underline;" target="_blank" href="' . $surveyLink . '">'.$surveyLinkText.'</a>', $surveyLink);
$message = str_replace($orig, $repl, $message);

// Simulate piping using fake data (project-level pages only)
if (defined("PROJECT_ID")) {
	$message = Piping::replaceVariablesInLabel($message, 1, $Proj->firstEventId, 1, array(), true, null, false, "" , 1, true);
	// For odd reasons, multi-arm piping does not work for non-first arms when $simulation=true for Piping::replaceVariablesInLabel(),
	// so replace the underscores manually afterward with "PIPED DATA".
	if ($multiple_arms) {
		$message = str_replace(Piping::missing_data_replacement, $lang['survey_1082'], $message);
	}
}

// If sending a test message, then get subject and from email address also, then send email to user's primary email account
if (isset($_POST['from'])) 
{
	$subject = trim(strip_tags($_POST['subject']));
	if ($subject == '') $subject = '[No subject]';
	$email = new Message();
	$email->setTo($user_email);
	$email->setFrom(trim(strip_tags($_POST['from'])));
	$email->setSubject($subject);
	$email->setBody("<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">$message</body></html>");
	print ($email->send()) ? $user_email : ": <b>ERROR: Could not send email!</b>";
}
// Return just the email message contents
else 
{
	print $message;
}