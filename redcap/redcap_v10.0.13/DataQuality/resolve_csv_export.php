<?php


// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Do user rights check (normally this is done by init_project.php, but we actually have multiple rights
// levels here for a single page (so it's not applicable).
if ($data_resolution_enabled != '2' || $user_rights['data_quality_resolution'] == '0')
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}

// Logging
Logging::logEvent("","redcap_data_quality_resolutions","MANAGE","","","Export data resolution dashboard");

// Open file for downloading
$download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_DataResolutionDashboard_" . date("Y-m-d_Hi") . ".csv";
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header("Content-Disposition: attachment; filename=$download_filename");

// Instantiate DataQuality object
$dq = new DataQuality();
// Output CSV content
print addBOMtoUTF8($dq->renderResolutionTable($_GET['status_type'], $_GET['field_rule_filter'], $_GET['event_id'], $_GET['group_id'], $_GET['assigned_user_id'], true));