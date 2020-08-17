<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsEntry;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;

$sql = "
ALTER TABLE redcap_projects_user_hidden COLLATE utf8mb4_unicode_ci;
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);


// CDIS Stats backfill
function getClinicalDataPullProjects($ignore_deleted=true)
{
	$query_string = "SELECT project_id, date_deleted FROM redcap_projects
                        WHERE realtime_webservice_type='FHIR'
                        AND realtime_webservice_enabled=1
						AND purpose != '0'
						AND count_project != 0";
	if($ignore_deleted) $query_string .= " AND date_deleted IS NULL";

	$result = db_query($query_string);
	$projects = array();
	while($row = db_fetch_assoc($result))
		$projects[] = $row['project_id'];
	return $projects;
}

function getClinicalDataMartProjects($ignore_deleted=true)
{
	$query_string = "SELECT project_id, date_deleted FROM redcap_projects
                        WHERE datamart_enabled=1
						AND purpose != '0'
						AND count_project != 0";
	if($ignore_deleted) $query_string .= " AND date_deleted IS NULL";

	$result = db_query($query_string);
	$projects = array();
	while($row = db_fetch_assoc($result))
		$projects[] = $row['project_id'];
	return $projects;
}

function getProjectRecords($project_id)
{
	$query_string = sprintf("SELECT * FROM redcap_data
                        WHERE project_id=%u", $project_id);
	$result = db_query($query_string);
	$records = array();
	while($row = db_fetch_assoc($result))
		$records[] = $row;
	return $records;
}

/**
 * get calculated fields from a project
 *
 * @param integer $project_id
 * @return array
 */
function getProjectCalculatedFields($project_id)
{
	$type = 'calc';
	$project = new Project($project_id);
	$metadata = $project->metadata;
	$fields = array_filter($metadata, function($field) use($type) {
		return $field['element_type'] == $type;
	});
	return $fields;
}

/**
 * parse the string in redcap_log_event.data_values
 * and extract the keys
 *
 * @param string $data_values
 * @return array
 */
function parseDataValuesString($data_values)
{
	// regexp to capture inserted keys/values
	$reg_exp = "/(?<key>[^\[].+) = '(?<value>.+?)',?\n?/";
	preg_match_all($reg_exp, $data_values, $matches);
	$keys = array_map('trim', $matches['key']);
	return $keys;
}

/**
 * check if the values have been inserted in a repeated instance form
 *
 * @param string $data_values
 * @return boolean
 */
function isRepeatedInstance($data_values)
{
	$reg_exp = "/\[instance = \d+\],?/";
	$matched = preg_match($reg_exp, $data_values, $matches);
	return boolval($matched);
}

/**
 * Add EHR count logs for a project using the redcap_log_event tables
 *
 * logs for CDP have page = DataMartController:runRevision
 * logs for CDM have page = DynamicDataPull/save.php
 *
 * @param integer $project_id
 * @return FhirStatsEntry[] list of entries collected in the process
 */
function addCountEntriesFromEventLog($project_id)
{
	$calculated_fields = getProjectCalculatedFields($project_id);
	// get log table for project
	$log_event_table = Logging::getLogEventTable($project_id);
	$query_string = sprintf(
		"SELECT `data_values`, `pk`, `event_id`, `ts`, `page`
		FROM %s
		WHERE project_id = %u
		AND data_values IS NOT NULL
		AND (
			`page` = 'DataMartController:runRevision'
			OR `page` = 'DynamicDataPull/save.php'
		)", $log_event_table, $project_id);
	$result = db_query($query_string);
	
	$entries = array();
	while($row = db_fetch_assoc($result))
	{
		$type = ($row['page'] == 'DataMartController:runRevision') ? FhirStatsCollector::REDCAP_TOOL_TYPE_CDM : FhirStatsCollector::REDCAP_TOOL_TYPE_CDP;
		$fhir_stats_collector = new FhirStatsCollector($project_id, $type);
		$record_id = $row['pk'];
		$event_id = $row['event_id'];
		$data_values = $row['data_values'];
		$log_timestamp = $row['ts'];
		$datetime = DateTime::createFromFormat('YmdHis', $log_timestamp);
		$timestamp = $datetime->getTimestamp();
		$keys = parseDataValuesString($data_values);
		// check if the values have been inserted in a repeated instance form
		if(isRepeatedInstance($data_values))
		{
			// only use a key for repeated instances (Data Mart) to avoid counting unnecessary values
			if($first_key = reset($keys))
				$keys = array($first_key);
		}
		foreach($keys as $field_key)
		{
			if(array_key_exists($field_key, $calculated_fields)) continue; // skip calculated fields
			$fhir_stats_collector->addEntryUsingField($record_id, $field_key, $event_id);
		}
		$outputQueryToPage = ($_SERVER['SERVER_NAME'] != 'redcap.vanderbilt.edu'); // Vanderbilt production logs are too big to display on a page
		$thisQuery = $fhir_stats_collector->logEntries($timestamp, $outputQueryToPage);
		if ($outputQueryToPage && $thisQuery != '') {
			print $thisQuery.";\n";
		}
	}
}

/**
 * seed EHR counts using logs for CDM and CDP projects
 *
 * @return FhirStatsEntry[] list of entries collected in the process
 */
function seedFhirCounts()
{
	$clinical_data_mart_project_ids = getClinicalDataPullProjects(); // get all CDP projects
	$clinical_data_pull_project_ids = getClinicalDataMartProjects(); // get all CDM projects

	// merge CDIS projects
	$cdis_project_ids = array_merge($clinical_data_mart_project_ids, $clinical_data_pull_project_ids);
	if (!empty($cdis_project_ids)) {
		print "\n-- Backfill FHIR stats for Clinical Data Pull and Data Mart\n";
	}

	foreach ($cdis_project_ids as $project_id)
	{
		addCountEntriesFromEventLog($project_id);
	}
}

seedFhirCounts();