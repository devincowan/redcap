<?php



// Check if coming from a survey
if (!isset($_GET['s']) || empty($_GET['s'])) exit;
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
// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set language/voice
if (!isset($_GET['q']) || !isset($text_to_speech_language)) $text_to_speech_language = '';

// Make sure we have the q parameter
$q = (isset($_GET['q'])) ? trim(urldecode(rawurlencode($_GET['q']))) : 'ERROR';

// REDCap Consortium server instance
$params = array('q'=>$q, 'voice'=>$text_to_speech_language, 'hostname'=>SERVER_NAME, 
				'hostkeyhash'=>Stats::getServerKeyHash(), 'surveyhash'=>$_GET['s'],
				'hosthash'=>hash('sha256', SERVER_NAME . 'bluecap' . Stats::getServerKeyHash()),
				'service'=>'watson');
$content = http_post('https://redcap.vanderbilt.edu/tts/index.php', $params);

$browser = new Browser();
if ($isMobileDevice || $browser->getBrowser() == 'Safari')
{
	// Save wav file to temp and then redirect there (only for special exceptions - e.g., mobile devices, Safari)
	$filename = date('YmdHis') . "_tts_" . substr(sha1(rand()), 0, 6) . ".wav";
	file_put_contents(APP_PATH_TEMP . $filename, $content);
	redirect(APP_PATH_WEBROOT_FULL . "temp/$filename");
}
else
{
	// Output WAV audio
	header('Pragma: anytextexeptno-cache', true);
	header("Content-Type: audio/wav");
	print $content;
}