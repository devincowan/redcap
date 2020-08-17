<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

# MAKE DATA DICTIONARY EXCEL FILE
$file_contents = MetaData::getDataDictionary('csv', true, array(), array(), false, isset($_GET['draft']), isset($_GET['rev_id']) ? $_GET['rev_id'] : '');
// Create filename
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)
		  . "_DataDictionary_".date("Y-m-d");
// If a revision number given, then append to filename
if (isset($_GET['revnum']) && is_numeric($_GET['revnum'])) {
	$filename .= "_rev" . $_GET['revnum'];
}
// Output to file
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header("Content-Disposition: attachment; filename=$filename.csv");
// Output the file contents
print addBOMtoUTF8($file_contents);
// Logging
Logging::logEvent("",isset($metadata_table) ? $metadata_table : '',"MANAGE",$project_id,"project_id = $project_id","Download data dictionary");