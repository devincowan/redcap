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
}

// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If we have a allowlist of records/events due to report filtering, unserialize it
$includeRecordsEvents = (isset($_POST['includeRecordsEvents'])) ? unserialize(decrypt($_POST['includeRecordsEvents'])) : array();
// Set flag if there are no records returned for a filter (so we can disguish this from a full data set with no filters)
$hasFilterWithNoRecords = (isset($_POST['hasFilterWithNoRecords']) && $_POST['hasFilterWithNoRecords'] == '1');

// Get data string to send
print DataExport::chartData($_POST['fields'], $user_rights['group_id'], $includeRecordsEvents, $hasFilterWithNoRecords);
