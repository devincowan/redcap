<?php

// Determine if this is a real API call or merely a passthru call for an External Module
if (isset($_GET['type']) && $_GET['type'] == "module") {
	// Set constant to denote that this is an API call
	define("API_EXTMOD", true);
	// MYCAP TEMP SOLUTION: A security fix (commit f577725a36ba63a7f5bc325c6d4ce8695d924e6e) inadventently causes 
	// the MyCap module not to work anymore. The following section is temporarily added until the fix in the MyCap module and app
	// can be propagated to all institutions and participants. 6/14/2018
	if (isset($_GET['prefix']) && $_GET['prefix'] == 'mycap') {
		define("NOAUTH", true);
	}
	// Make sure to defind ANYAUTH if exist in URL for API calls. 
	if (isset($_GET['ANYAUTH'])) {
		define("ANYAUTH", true);
	    }
	
	// Check if proxying a PHP page - if yes, authorize, if no, allow passthru
	if (isset($_GET['page'])) {
		$page = rawurldecode(urldecode($_GET['page']));
		$pageExtension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
		if ($pageExtension != '' && $pageExtension != "php") {
			// This is not a php page, let's proxy it through
			define("NOAUTH", true);
		}
	}

	// Config
	require_once (dirname(dirname(__FILE__)) . '/Config/' . (isset($_GET['pid']) ? 'init_project.php' : 'init_global.php'));
	// Make sure that External Modules are installed
	if (defined("APP_PATH_EXTMOD")) {
		// Require ExternalModules/index.php
		unset($_GET['type']);
		require_once APP_PATH_EXTMOD . "index.php";
		exit;
	}
	// Disable REDCap's authentication (will use API tokens for authentication)
	define("NOAUTH", true);
} else {
	// Set constant to denote that this is an API call
	define("API", true);
	// Disable REDCap's authentication (will use API tokens for authentication)
	define("NOAUTH", true);
	// Config
	require_once (dirname(dirname(__FILE__)) . '/Config/init_global.php');
}

// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);

/**
 * API FUNCTIONALITY
 */

// detect playground for logging
$playground = isset($_POST['playground']) ? ' Playground' : '';


# globals
$format = "xml";
$returnFormat = "xml";


# set format (default = xml)
$format = $_POST['format'];
switch ($format)
{
	case 'json':
		break;
	case 'csv':
		break;
	case 'odm':
		break;
	default:
		$format = "xml";
}
$_POST['format'] = $format;

# set returnFormat for outputting error messages and other stuff (default = xml)
$tempFormat = ($_POST['returnFormat'] != "") ? strtolower($_POST['returnFormat']) : strtolower($_POST['format']);
switch ($tempFormat)
{
	case 'json':
		$returnFormat = "json";
		break;
	case 'csv':
		$returnFormat = "csv";
		break;
	case 'xml':
	default:
		$returnFormat = "xml";
		break;
}

# check if the API is enabled first
if (!$api_enabled) RestUtility::sendResponse(400, $lang['api_01']);


# certain actions do NOT require a token
$tokenRequired = !(!isset($_POST['token']) &&
					// Advanced project bookmark
					(isset($_POST['authkey']) ));

# process the incoming request
$data = RestUtility::processRequest($tokenRequired);

# get all the variables sent in the request
$post = $data->getRequestVars();
# initialize array variables if they were NOT sent or if they are empty
if (!isset($post['records']) or $post['records'] == '') $post['records'] = array();
if (!isset($post['events']) or $post['events'] == '') $post['events'] = array();
if (!isset($post['fields']) or $post['fields'] == '') $post['fields'] = array();
if (!isset($post['forms']) or $post['forms'] == '') $post['forms'] = array();
if (!isset($post['arms']) or $post['arms'] == '') $post['arms'] = array();

if (!isset($post['mobile_app'])) $post['mobile_app'] = "0";
if (!isset($post['uuid'])) $post['uuid'] = "";
if (!isset($post['project_init'])) $post['project_init'] = "0";

if (!isset($post['format'])) $post['format'] = "";
if (!isset($post['type'])) $post['type'] = "";
if (!isset($post['rawOrLabel'])) $post['rawOrLabel'] = "";
if (!isset($post['rawOrLabelHeaders'])) $post['rawOrLabelHeaders'] = "";
if (!isset($post['overwriteBehavior'])) $post['overwriteBehavior'] = "";
if (!isset($post['action'])) $post['action'] = "";
if (!isset($post['returnContent'])) $post['returnContent'] = "";
if (!isset($post['event'])) $post['event'] = "";
if (!isset($post['armNumber'])) $post['armNumber'] = "";
if (!isset($post['armName'])) $post['armName'] = "";
if (!isset($post['dateFormat'])) {
	$post['dateFormat'] = "YMD";
} else {
	$post['dateFormat'] = ($post['dateFormat'] == 'DMY' ? 'DMY' : ($post['dateFormat'] == 'MDY' ? 'MDY' : 'YMD'));
}
$post['exportCheckboxLabel'] = (isset($post['exportCheckboxLabel']) && ($post['exportCheckboxLabel'] == '1' || strtolower($post['exportCheckboxLabel']."") === 'true'));
$post['forceAutoNumber'] = (isset($post['forceAutoNumber']) && ($post['forceAutoNumber'] == '1' || strtolower($post['forceAutoNumber']."") === 'true'));

if (isset($post['authkey'])) $post['content'] = "authkey";
if (!isset($post['filterLogic'])) $post['filterLogic'] = false;

# determine if a valid content parameter was passed in
switch ($post['content'])
{
	case 'record':
		$post['exportSurveyFields'] = (isset($post['exportSurveyFields']) && ($post['exportSurveyFields'] == '1' || strtolower($post['exportSurveyFields']."") === 'true'));
		$post['exportDataAccessGroups'] = (isset($post['exportDataAccessGroups']) && ($post['exportDataAccessGroups'] == '1' || strtolower($post['exportDataAccessGroups']."") === 'true'));
		break;
	case 'metadata':
	case 'file':
	case 'filesize': // currently only used for mobile app usage to determine file size for API File Export - deprecate soon to replace with 'fileinfo'
	case 'fileinfo': // currently only used for mobile app usage to determine file size for API File Export
	case 'repeatingFormsEvents':
	case 'instrument':
	case 'event':
	case 'arm':
	case 'user':
	case 'project_settings':
	case 'report':
	case 'authkey':
	case 'version':
	case 'pdf':
	case 'surveyLink':
	case 'surveyQueueLink':
	case 'surveyReturnCode':
	case 'participantList':
	case 'exportFieldNames':
	case 'appRightsCheck':
	case 'formEventMapping':
	case 'fieldValidation':
	case 'attachment':
	case 'project':
    case 'generateNextRecordName':
	case 'project_xml':
		break;
	default:
		die(RestUtility::sendResponse(400, 'The value of the parameter "content" is not valid'));
		break;
}

# If content = file, determine if a valid action was passed in
if ($post['content'] == "file" || $post['content'] == "filesize" || $post['content'] == "fileinfo")
{
	switch (strtolower($post['action']))
	{
		case 'export':
		case 'import':
		case 'import_app':
		case 'delete':
			break;
		default:
			die(RestUtility::sendResponse(400, 'The value of the parameter "action" is not valid'));
			break;
	}
}
if ($post['content'] == 'version' || $post['content'] == 'event' || $post['content'] == "arm" || $post['content'] == "authkey" || $post['content'] == "repeatingFormsEvents")
{
	if ($post['action'] == "") $post['action'] = "export";
}

# set the import action option
if (strtolower($post['overwriteBehavior']) != 'normal' && strtolower($post['overwriteBehavior']) != 'overwrite') $post['overwriteBehavior'] = 'normal';

# set the type
if (strtolower($post['type']) != 'eav' && strtolower($post['type']) != 'flat') $post['type'] = 'flat';

# what content to return when importing data
switch (strtolower($post['returnContent']))
{
	case 'ids':
	case 'auto_ids':
	case 'nothing':
	case 'count':
		break;
	default:
		$post['returnContent'] = 'count';
		break;
}

# set the type of content to be returned for a field that has data/value pairs
switch (strtolower($post['rawOrLabel']))
{
	case 'raw':
	case 'label':
		break;
	default:
		$post['rawOrLabel'] = 'raw';
		break;
}
switch (strtolower($post['rawOrLabelHeaders']))
{
	case 'raw':
	case 'label':
		break;
	default:
		$post['rawOrLabelHeaders'] = 'raw';
		break;
}

# set the event name option (if not set, use rawOrLabel option)
// eventName is a deprecated feature, so align it with rawOrLabel value for EAV only (since EAV is only place it still deals with it in old code)
$post['eventName'] = ($post['rawOrLabel'] == 'raw') ? 'unique' : 'label';


# determine if we are exporting, importing, or deleting data
if(in_array($post['content'], array('file', 'event', 'arm', 'authkey')))
{
    if ($post['content'] == "authkey") {
        $action = "export";
    } else {
        $action = $post['action'];
    }
}
elseif ($post['content'] == 'record' && $post['action'] == 'delete') {
        $action = $post['action'];
}
else {
	$action = (!isset($post['data']) || $post['content'] == 'version') ? 'export' : 'import';
}


# determine if the user has the correct user rights
if ($tokenRequired && strlen($post['token']) != 64) {
	if ($action == "export") {
		if ($post['api_export'] != 1) {
			// Logging
			Logging::logEvent('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API Export privileges"));
		}
	}
	elseif ($action == "import") {
		if ($post['api_import'] != 1) {
			// Logging
			Logging::logEvent('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API Import/Update privileges"));
		}
	}
        elseif ($action == "delete") {
                if ($post['record_delete'] != 1) {
		        die(RestUtility::sendResponse(403, "You do not have Delete Record privileges"));
                }
        }
}

// For content=filesize, set content as file (method only used for mobile app to detect size of file)
if ($post['content'] == "filesize" || $post['content'] == "fileinfo") {
	$post['content'] = "file";
	$post['fileinfo'] = 1;
}

// If project was deleted or completed but still exists on back-end, then return error
if ($post['content'] !== "project" && $post['content'] !== "version" && !isset($post['authkey'])) {
	$Proj = new Project();
	if ($Proj->project['date_deleted'] != '') {
		RestUtility::sendResponse(400, $lang['api_11']);
	} elseif ($Proj->project['completed_time'] != '') {
		RestUtility::sendResponse(400, $lang['api_150']);
	}
}

// Build record list cache if not yet built for this project
$recordListCacheStatus = Records::getRecordListCacheStatus(PROJECT_ID);
if ($recordListCacheStatus != 'COMPLETE' && $recordListCacheStatus != 'PROCESSING')
{
	// Hit the cache-building end-point to trigger the list to be created. Add NOAUTH_BUILDRECORDLIST and API flags to bypass authentication and to prevent it from returning anything (faster), respectively.
	http_get(APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."&NOAUTH_BUILDRECORDLIST&API&route=DataEntryController:buildRecordListCache");
}

# include the necessary file, based off of content type and whether the "data" field was passed in
include ($post['content'] . "/$action.php");
