<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

Logging::logEvent("", "redcap_events_arms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download arms");

$content = arrayToCsv(Project::getArmRecords($post));
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)
		  . "_Arms_".date("Y-m-d").".csv";

header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header('Content-Disposition: attachment; filename=' . $filename);
echo $content;