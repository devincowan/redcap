<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// In case 2 users are working at same time, prevent the metadata_temp table from being wiped out
$q1 = db_query("select count(1) from redcap_metadata_temp where project_id = $project_id");
if ($q1 && db_result($q1, 0) != '0') {
	// Errors occurred
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id");
}

// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Move all existing metadata fields to metadata_temp table
$q2 = db_query("insert into redcap_metadata_temp select * from redcap_metadata where project_id = $project_id");

//Now set draft_mode to "1" and send user back to previous page in Draft Mode
$q3 = db_query("update redcap_projects set draft_mode = 1 where project_id = $project_id");

if ($q1 && $q2 && $q3) {
	// All good
	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");
	// Logging
	Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Enter draft mode");
} else {
	// Errors occurred
	db_query("ROLLBACK");
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id");
}

// Redirect back to previous page
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
	redirect($_SERVER['HTTP_REFERER'] . "&msg=enabled_draft_mode");
} else {
	// If can't find referer, just send back to Online Designer
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&msg=enabled_draft_mode");
}
