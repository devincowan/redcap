<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Build CSV list of all fields
$fields_csv_list = "'" . implode("', '", array_keys($Proj->metadata)) . "'";

// Set type of output and filename prefix
$getReturnCodes = false; // Default value for flag to return the survey Return Codes
if (!isset($_GET['type']) || (isset($_GET['type']) && $_GET['type'] != 'labels')) {
	// Export raw data (may contain return codes)
	if ($_GET['type'] == 'return_codes') {
		$logging_description = "Export data (CSV raw with return codes)";
		$getReturnCodes = true;
	} else {
		$logging_description = "Export data (CSV raw)";
	}
	$_GET['type'] = 'raw';
	$filename_prefix = "_DATA_";
} else {
	// Export data with labels
	$logging_description = "Export data (CSV labels)";
	$filename_prefix = "_DATA_LABELS_";
}

// For RETURN CODES in new Report Builder, only have the record ID field + Form Status fields + the return code fields
if ($getReturnCodes) {
	$fields_csv_list = "'$table_pk'";
	foreach ($Proj->surveys as $this_survey_id=>$attr) {
		// If using survey login, then do not count this survey as having return codes
		// Always include the first instrument if save & return is enabled (regardless of survey login - due to Public Survey)
		if ($attr['save_and_return'] && ($this_survey_id == $Proj->firstFormSurveyId || !(Survey::surveyLoginEnabled() && ($survey_auth_apply_all_surveys || $attr['survey_auth_enabled_single'])))) {
			$fields_csv_list .= ", '{$attr['form_name']}_complete'";
		}
	}
}

// Retrieve project data (raw & labels) and headers in CSV format
list ($headers, $headers_labels, $data_csv, $data_csv_labels, $field_names) = DataExport::fetchDataCsv($fields_csv_list,"",$getReturnCodes);
// Log the event
Logging::logEvent("","redcap_data","data_export","",str_replace("'","",$fields_csv_list),$logging_description);

// Write headers for the file to be saved
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20) . $filename_prefix . date("Y-m-d-H-i-s") . ".csv";
// $filename = $filename_prefix.strtoupper($app_name."_".$userid).date("_Y-m-d-H-i-s").".CSV";
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header("Content-Disposition: attachment; filename=$filename");

// Output content
if ($_GET['type'] == 'raw') {
	print addBOMtoUTF8($headers . $data_csv);
} elseif ($_GET['type'] == 'labels') {
	print addBOMtoUTF8($headers_labels . $data_csv_labels);
} else {
	print "ERROR!";
}