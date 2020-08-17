<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Page is only usable is Field Comment Log is enabled
if ($data_resolution_enabled != '1')
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}

// Instantiate DataQuality object
$dq = new DataQuality();

// Get comment log as CSV
$csv_file = $dq->getFieldCommentLogCSV();

// Create filename
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)
		  . "_CommentLog_".date("Y-m-d");

// Output to file
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header("Content-Disposition: attachment; filename=$filename.csv");
print $csv_file;

// Log it
Logging::logEvent("","redcap_data_quality_resolutions","MANAGE",$project_id,"project_id = $project_id","Export entire field comment log");