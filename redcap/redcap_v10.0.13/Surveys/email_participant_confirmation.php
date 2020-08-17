<?php


// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
} else {
	exit("ERROR!");
}

// Call config files
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Confirm participant_id, hash, and record number
if ($participant_id != Survey::getParticipantIdFromHash($hash)) exit('0');
// Check record name
$sql = "select 1 from redcap_surveys_participants p, redcap_surveys_response r
		where r.participant_id = p.participant_id and p.participant_id = $participant_id
		and p.survey_id = $survey_id and r.record = '".db_escape($_POST['record'])."' limit 1";
$q = db_query($sql);
if (!db_num_rows($q)) exit('0');

// Send email confirmation
$emailSent = Survey::sendSurveyConfirmationEmail($survey_id, $event_id, $_POST['record'], $_POST['email']);
// Return status message
if ($emailSent) {
	print 	RCView::div(array('style'=>'color:green;font-size:14px;'),
				RCView::img(array('src'=>'tick.png')) .
				RCView::tt('survey_181')
			);
} else {
	print "0";
}