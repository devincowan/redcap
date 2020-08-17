<?php


## If `datetime_format` column doesn't exist in redcap_user_information, then add it.
// Only do this if user just upgraded from 5.8.1
if (getDecVersion($current_version) == 50801)
{
	// Get table info
	$sql = "SHOW CREATE TABLE redcap_user_information";
	$q = db_query($sql);
	if ($q && db_num_rows($q) == 1)
	{
		// Get the 'create table' statement to parse
		$result = db_fetch_array($q);

		// Set as lower case to prevent case sensitivity issues
		$createTableStatement = strtolower($result[1]);

		## REMOVE ALL EXISTING FOREIGN KEYS
		// Set regex to pull out strings
		$regex = "/(`datetime_format`)/";
		// Do regex
		if (!preg_match($regex, $createTableStatement))
		{
			print "-- Set config values for international date/number/time abstraction
ALTER TABLE  `redcap_user_information` ADD  `datetime_format` ENUM(  'M-D-Y_24',  'M-D-Y_12',  'M/D/Y_24',  'M/D/Y_12',
'M.D.Y_24',  'M.D.Y_12',  'D-M-Y_24',  'D-M-Y_12',  'D/M/Y_24',  'D/M/Y_12',  'D.M.Y_24', 'D.M.Y_12',  'Y-M-D_24',
'Y-M-D_12',  'Y/M/D_24',  'Y/M/D_12',  'Y.M.D_24',  'Y.M.D_12' ) NOT NULL DEFAULT  'M/D/Y_12'
COMMENT  'User''s preferred datetime viewing format';\n";
		}
	}
} else {
	print "-- Set config values for international date/number/time abstraction\n";
}
// Other static queries
?>
delete from redcap_config where field_name = 'default_datetime_format';
insert into redcap_config values ('default_datetime_format', 'M/D/Y_12');