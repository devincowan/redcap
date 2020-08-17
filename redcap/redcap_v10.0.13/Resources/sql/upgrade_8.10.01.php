<?php

// DDP on FHIR fix:
// 1) Find all DDP on FHIR projects that have mapped Sex, Ethnicity, or Race.
// 2) Convert all data AND metadata to the new LOINC code values

// If on 8.5 LTS (specifically 8.5.25+) or NOT on 8.5 LTS (e.g., 8.10.0), then perform this DDP on FHIR translation
$is85LTS = (substr($current_version, 0, 4) == "8.5.");
if (!$is85LTS || ($is85LTS && Upgrade::getDecVersion($current_version) <= 80525))
{
	// Translate old values into new ones
	$data_translate = array('gender'=>array('0'=>'F', '1'=>'M', '2'=>'UNK'),
							'ethnicity'=>array('0'=>'2135-2', '1'=>'2186-5'),
							'race'=>array('0'=>'1002-5', '1'=>'2028-9', '2'=>'2076-8', '3'=>'2054-5', '4'=>'2106-3', '9'=>'2131-1'));
	// Loop through all DDP on FHIR projects
	$sq = "select project_id, status, draft_mode from redcap_projects 
			where realtime_webservice_enabled = 1 and realtime_webservice_type = 'FHIR'";
	$q = db_query($sq);
	$sql = "";
	while ($row = db_fetch_assoc($q)) 
	{
		$project_id = $row['project_id'];
		$add_metadata_temp = ($row['status'] > 0 && $row['draft_mode'] > 0);
		$metadata_tables = array('redcap_metadata');
		if ($add_metadata_temp) $metadata_tables[] = 'redcap_metadata_temp';
		$Proj = new Project($project_id);
		$DDP = new DynamicDataPull($project_id, 'FHIR');
		$psql = "";
		if (!$DDP->isMappingSetUp()) continue;
		// Get the external source fields in array format
		$mappings = $DDP->getMappedFields();
		// Loop through each field
		foreach ($data_translate as $fhir_field=>$coding_translate) {
			if (isset($mappings[$fhir_field])) {
				foreach ($mappings[$fhir_field] as $event_id=>$bttr) {
					foreach ($bttr as $field=>$attr) {
						// Update metadata table
						$this_enum = str_replace("|", "\\n", DynamicDataPull::$demography_mc_mapping[$fhir_field]);
						foreach ($metadata_tables as $metadata_table) {
							$psql .= "update $metadata_table\n\tset element_enum = '".db_escape($this_enum)."'\n\twhere field_name = '$field' and project_id = $project_id;\n";
						}
						// Update data table
						foreach ($coding_translate as $oldVal=>$newVal) {
							$psql .= "update redcap_data set value = '".db_escape($newVal)."' where field_name = '$field' and event_id = $event_id "
								  .  "and project_id = $project_id and value = '".db_escape($oldVal)."';\n";
						}
						// Re-encrypt all cached data points!
						$map_id = $attr['map_id'];
						$sql2 = "select md_id, source_value2 from redcap_ddp_records_data where map_id = $map_id";
						$q2 = db_query($sql2);
						while ($row2 = db_fetch_assoc($q2)) 
						{
							$decrypted_value = decrypt($row2['source_value2'], DynamicDataPull::DDP_ENCRYPTION_KEY);
							if (!isset($coding_translate[$decrypted_value])) continue;
							$translated_value = $coding_translate[$decrypted_value];
							$reencrypted_value = encrypt($translated_value, DynamicDataPull::DDP_ENCRYPTION_KEY);
							$psql .= "-- Convert \"$fhir_field\" value from \"$decrypted_value\" to \"$translated_value\"\n";
							$psql .= "update redcap_ddp_records_data set source_value2 = '" . db_escape($reencrypted_value) . "' where md_id = ".$row2['md_id'].";\n";
						}
					}
				}
			}
		}
		if ($psql != "") {
			$sql .= "\n-- PID $project_id: Translate data and metadata for DDP on FHIR\n$psql";
		}
	}
	// Output SQL
	print $sql;
}

// Add new project-level settings
print "
ALTER TABLE `redcap_projects` 
	ADD `secondary_pk_display_value` TINYINT(1) NOT NULL DEFAULT '1' AFTER `secondary_pk`, 
	ADD `secondary_pk_display_label` TINYINT(1) NOT NULL DEFAULT '1' AFTER `secondary_pk_display_value`;
";