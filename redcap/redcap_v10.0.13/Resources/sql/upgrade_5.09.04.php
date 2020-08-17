<?php

## If `number_format_decimal` or `number_format_thousands_sep` columns don't exist in redcap_user_information, then add them.
// Get table info
$sql = "SHOW CREATE TABLE redcap_user_information";
$q = db_query($sql);
if ($q && db_num_rows($q) == 1)
{
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);

	// Set as lower case to prevent case sensitivity issues
	$createTableStatement = strtolower($result[1]);

	// Do regex
	if (!preg_match("/(`number_format_decimal`)/", $createTableStatement))
	{
		print "ALTER TABLE  `redcap_user_information` ADD  `number_format_decimal` ENUM(  '.',  ',' ) NOT NULL DEFAULT  '.' COMMENT  'User''s preferred decimal format';\n";
	}

	// Do regex
	if (!preg_match("/(`number_format_thousands_sep`)/", $createTableStatement))
	{
		print "ALTER TABLE  `redcap_user_information` ADD  `number_format_thousands_sep` ENUM( ',', '.', '', 'SPACE', '\'' ) NOT NULL DEFAULT  ',' COMMENT  'User''s preferred thousands separator';\n";
	}
}