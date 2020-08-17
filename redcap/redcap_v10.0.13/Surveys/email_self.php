<?php



if (!isset($_GET['survey_id']) && isset($_GET['email']) && isset($_GET['url']) && !empty($_GET['url']))
{
	define("NOAUTH", true);
}

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";


// Default response
$response = "0";



## Send email for viewing survey results
if (!isset($_GET['survey_id']) && isset($_GET['email']) && isset($_GET['url']) && !empty($_GET['url']))
{
	// Set URL to be sent within email
	$href = urldecode($_GET['url']);

	//Set the contents of the email
	$emailContents = "
	<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
	{$lang['global_21']}<br><br>
	{$lang['survey_182']}<br>
	<a href=\"$href\">{$lang['survey_167']}</a><br><br>
	{$lang['survey_135']}<br>
	$href
	</body></html>";

	//Sending email to self
	$email = new Message ();
	$email->setTo($_GET['email']);
	$email->setFrom($project_contact_email);
	$email->setFromName("");
	$email->setSubject('[REDCap] '.$lang['survey_183']);
	$email->setBody($emailContents);
	// If successful, send back user's email, which is included in alert msg as confirmation
	$response = ($email->send() ? "1" : "0");
}



## Survey admin sends email containing survey link to self
elseif (Survey::checkSurveyProject($_GET['survey_id']) && isset($_GET['url']) && !empty($_GET['url']))
{
	// Get survey title
	$q = db_query("select title from redcap_surveys where survey_id = " . $_GET['survey_id']);
	$survey_title = decode_filter_tags(db_result($q,0));

	// Set URL to be sent within email
	$href = urldecode($_GET['url']);

	//Set the contents of the email
	$emailContents = "
	<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
	{$lang['global_21']}<br><br>
	{$lang['survey_134']}<br>
	<a href=\"$href\">".($survey_title == "" ? $href : $survey_title)."</a><br><br>
	{$lang['survey_135']}<br>
	$href
	</body></html>";

	//Sending email to self
	$email = new Message ();
	$email->setTo($user_email);
	$email->setFrom($project_contact_email);
	$email->setFromName($GLOBALS['project_contact_name']);
	$email->setSubject('[REDCap] '.$lang['survey_136']);
	$email->setBody($emailContents);
	// If successful, send back user's email, which is included in alert msg as confirmation
	$response = ($email->send() ? $user_email : "0");

	// Logging
	Logging::logEvent("","redcap_surveys","MANAGE",$_GET['survey_id'],"survey_id = " . $_GET['survey_id'],"Email survey link to self");

}

exit($response);