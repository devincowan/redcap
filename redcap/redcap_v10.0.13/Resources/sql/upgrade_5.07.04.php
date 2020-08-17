<?php

/**
 * Find all foreign keys from redcap_projects, then delete them, then re-add them
 */

// First, remove all orphaned created_by's from the table
print "update redcap_projects set created_by = null where created_by not in (select ui_id from redcap_user_information);\n";

// Get table info
$sql = "SHOW CREATE TABLE redcap_projects";
$q = db_query($sql);
if ($q && db_num_rows($q) == 1)
{
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);

	// Set as lower case to prevent case sensitivity issues
	$createTableStatement = strtolower($result[1]);

	// Turn off FK checks first
	print "SET foreign_key_checks = 0;\n";

	## REMOVE ALL EXISTING FOREIGN KEYS
	// Set regex to pull out strings
	$regex = "/(constraint `)(redcap_projects_ibfk_\d)(`)/";
	// Do regex
	preg_match_all($regex, $createTableStatement, $matches);
	if (isset($matches[0]) && !empty($matches[0]))
	{
		// Parse invididual foreign key names
		foreach ($matches[0] as $this_fk)
		{
			$fk_name = preg_replace($regex, "$2", $this_fk);
			print "ALTER TABLE `redcap_projects` DROP FOREIGN KEY `$fk_name`;\n";
		}
	}

	## RE-ADD ALL FOREIGN KEYS
	print "ALTER TABLE  `redcap_projects` ADD FOREIGN KEY (  `created_by` ) "
		. "REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;\n"
		. "ALTER TABLE  `redcap_projects` ADD FOREIGN KEY (  `template_id` ) "
		. "REFERENCES `redcap_projects` (`project_id`) ON DELETE SET NULL ON UPDATE CASCADE;\n";

	// Finish operation
	print "SET foreign_key_checks = 1;\n";
}
