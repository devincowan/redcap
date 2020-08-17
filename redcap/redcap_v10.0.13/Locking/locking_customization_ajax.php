<?php



include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


switch ($_POST['action'])
{
	// Add new custom label
	case 'add':
		// First check if in table already
		$sql = "select 1 from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "' limit 1";
		$rowExists = db_num_rows(db_query($sql));
		if ($rowExists)
		{
			$sql = "update redcap_locking_labels set label = '" . db_escape($_POST['label']) . "' where project_id = $project_id
					and form_name = '" . db_escape($_POST['form']) . "'";
		}
		else
		{
			$sql = "insert into redcap_locking_labels (project_id, form_name, label)
					values ($project_id, '" . db_escape($_POST['form']) . "', '" . db_escape($_POST['label']) . "')";
		}
		$response = db_query($sql) ? "1" : "0";
		// Logging
		if ($response) Logging::logEvent($sql,"redcap_locking_labels","MANAGE",$_POST['form'],"form_name = '{$_POST['form']}'","Customize record locking");
		exit($response);
		break;

	// Erase label
	case 'delete':
		// First check if 'display'=1. If so, delete row, else make label null
		$sql = "select display, display_esignature from redcap_locking_labels where project_id = $project_id
				and form_name = '" . db_escape($_POST['form']) . "' limit 1";
		$q = db_query($sql);
		if (db_result($q, 0, "display") == "1" && db_result($q, 0, "display_esignature") == "0")
		{
			$sql = "delete from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "'";
		}
		else
		{
			$sql = "update redcap_locking_labels set label = null where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "'";
		}
		$response = db_query($sql) ? "1" : "0";
		// Logging
		if ($response) Logging::logEvent($sql,"redcap_locking_labels","MANAGE",$_POST['form'],"form_name = '{$_POST['form']}'","Customize record locking");
		exit($response);
		break;

	// Edit label
	case 'edit':
		$sql = "update redcap_locking_labels set label = '" . db_escape($_POST['label']) . "' where project_id = $project_id
				and form_name = '" . db_escape($_POST['form']) . "'";
		$response = db_query($sql) ? "1" : "0";
		// Logging
		if ($response) Logging::logEvent($sql,"redcap_locking_labels","MANAGE",$_POST['form'],"form_name = '{$_POST['form']}'","Customize record locking");
		exit($response);
		break;

	// Set display value as 0 or 1
	case 'set_display':
		if (!is_numeric($_POST['display'])) exit("0");
		// First check if in table already
		$sql = "select label, display_esignature from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "' limit 1";
		$q = db_query($sql);
		$rowExists = db_num_rows($q);
		if ($rowExists)
		{
			// If no label is defined and display is being set to 1, then delete from table (because equivalent to not existing in table)
			if (db_result($q, 0, "label") == "" && db_result($q, 0, "display_esignature") == "0" && $_POST['display']) {
				$sql = "delete from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "'";
			} else {
				$sql = "update redcap_locking_labels set display = {$_POST['display']} where project_id = $project_id
						and form_name = '" . db_escape($_POST['form']) . "'";
			}
		}
		else
		{
			$sql = "insert into redcap_locking_labels (project_id, form_name, display)
					values ($project_id, '" . db_escape($_POST['form']) . "', {$_POST['display']})";
		}
		$response = db_query($sql) ? "1" : "0";
		// Logging
		if ($response) Logging::logEvent($sql,"redcap_locking_labels","MANAGE",$_POST['form'],"form_name = '{$_POST['form']}'","Customize record locking");
		exit($response);
		break;

	// Set e-signature display value as 0 or 1
	case 'set_display_esign':
		if (!is_numeric($_POST['display'])) exit("0");
		// First check if in table already
		$sql = "select label, display from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "' limit 1";
		$q = db_query($sql);
		$rowExists = db_num_rows($q);
		if ($rowExists)
		{
			// If no label is defined and display is being set to 1, then delete from table (because equivalent to not existing in table)
			if (db_result($q, 0, "label") == "" && db_result($q, 0, "display") == "1" && !$_POST['display']) {
				$sql = "delete from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_POST['form']) . "'";
			} else {
				$sql = "update redcap_locking_labels set display_esignature = {$_POST['display']} where project_id = $project_id
						and form_name = '" . db_escape($_POST['form']) . "'";
			}
		}
		else
		{
			$sql = "insert into redcap_locking_labels (project_id, form_name, display_esignature)
					values ($project_id, '" . db_escape($_POST['form']) . "', {$_POST['display']})";
		}
		$response = db_query($sql) ? "1" : "0";
		// Logging
		if ($response) Logging::logEvent($sql,"redcap_locking_labels","MANAGE",$_POST['form'],"form_name = '{$_POST['form']}'","Customize record locking");
		exit($response);
		break;

}

// Should not be here - send error
exit("0");

