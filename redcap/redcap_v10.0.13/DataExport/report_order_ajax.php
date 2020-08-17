<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate ids
if (!isset($_POST['report_ids'])) exit('0');

// Remove comma on end
if (substr($_POST['report_ids'], -1) == ',') $_POST['report_ids'] = substr($_POST['report_ids'], 0, -1);

// Create array of report_ids
$new_report_ids = explode(",", $_POST['report_ids']);

// Get existing list of reports to validate and compare number of items
$old_report_ids = array();
foreach(DataExport::getReportNames() as $attr) {
	$old_report_ids[] = $attr['report_id'];
}

// Determine if any new report_ids were maliciously added
$extra_report_ids = array_diff($new_report_ids, $old_report_ids);
if (!empty($extra_report_ids)) exit('0');

// Determine if any new reports were added by another user simultaneously and are not in this list
$append_report_ids = array_diff($old_report_ids, $new_report_ids);

// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");
$errors = 0;
// Set all report_orders to null
$sql = "update redcap_reports set report_order = null where project_id = $project_id";
if (!db_query($sql)) $errors++;
// Loop through report_ids and set new report_order
$report_order = 1;
foreach ($new_report_ids as $this_report_id) {
	$sql = "update redcap_reports set report_order = ".$report_order++."
			where project_id = $project_id and report_id = $this_report_id";
	if (!db_query($sql)) $errors++;
}
// Deal with orphaned report_ids added simultaneously by other user while this user reorders
foreach ($append_report_ids as $this_report_id) {
	$sql = "update redcap_reports set report_order = ".$report_order++."
			where project_id = $project_id and report_id = $this_report_id";
	if (!db_query($sql)) $errors++;
}
// If errors, do not commit
$commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
db_query($commit);
if ($errors > 0) exit('0');
// Set back to initial value
db_query("SET AUTOCOMMIT=1");

// Logging
Logging::logEvent("", "redcap_projects", "MANAGE", $project_id, "report_id = ".$_POST['report_ids'], "Reorder reports");

// Return Value: If there are some extra reports that exist that are not currently in the list, then refresh the user's page
print (!empty($append_report_ids)) ? '2' : '1';
