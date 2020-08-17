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

require dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must be accessed via AJAX
if (!$isAjax || empty($_POST['service']) || empty($_POST['category']) || empty($_POST['value']) || empty($_POST['label'])) {
	exit;
}

// Place values into cache table
Form::addWebServiceCacheValues($project_id, $_POST['service'], $_POST['category'], $_POST['value'], $_POST['label']);