<?php

// Remove edoc_id field and its corresponding foreign key from redcap_docs (if exists)
$drop_fk = "";
$sql = "SHOW CREATE TABLE redcap_docs";
$q = db_query($sql);
if ($q && db_num_rows($q) == 1)
{
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);

	// Set as lower case to prevent case sensitivity issues
	$createTableStatement = strtolower($result[1]);

	## REMOVE ALL EXISTING FOREIGN KEYS
	// Set regex to pull out strings
	$regex = "/(constraint `)(redcap_docs_ibfk_\d)(.+)(`edoc_id`)/";
	// Do regex
	preg_match_all($regex, $createTableStatement, $matches);
	if (isset($matches[0]) && !empty($matches[0]))
	{
		// Parse invididual foreign key names
		foreach ($matches[0] as $this_fk)
		{
			$fk_name = preg_replace($regex, "$2", $this_fk);
			$drop_fk = ",\n\tDROP FOREIGN KEY `$fk_name`,\n\tDROP `edoc_id`";
		}
	}
}


print "-- Modify redcap_docs table
ALTER TABLE  `redcap_docs`
	CHANGE  `docs_name` `docs_name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	DROP INDEX  `docs_name` ,
	ADD INDEX  `docs_name` (  `docs_name` ),
	ADD  `temp` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Is file only a temp file?',
	DROP INDEX  `project_id_export_file`,
	ADD INDEX  `project_id_export_file_temp` (`project_id`, `export_file`, `temp`){$drop_fk};
-- Add new field to redcap_edocs_metadata table
ALTER TABLE  `redcap_edocs_metadata` ADD  `gzipped` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Is file gzip compressed?' AFTER  `file_extension`;
-- Add column to reports table
ALTER TABLE  `redcap_reports` ADD  `advanced_logic` TEXT NULL DEFAULT NULL;
-- Remove unnecessary reports table
delete from redcap_reports_filter_records;
drop table if exists redcap_reports_filter_records;
-- Fix reports table
ALTER TABLE  `redcap_reports` CHANGE  `output_dags`  `output_dags` INT( 1 ) NOT NULL DEFAULT  '0',
	CHANGE  `output_survey_fields`  `output_survey_fields` INT( 1 ) NOT NULL DEFAULT  '0';";