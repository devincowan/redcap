<?php

print "-- Remove unused database tables (trust us, these have never been used for anything)
drop table redcap_surveys_response_users;
drop table redcap_surveys_response_values;
";

// Convert S3 endpoint URL to region value
if (isset($GLOBALS['amazon_s3_endpoint']) && $GLOBALS['amazon_s3_endpoint'] != '')
{
	print "-- Modify AWS S3 region to new format
replace into redcap_config (field_name, value) values ('amazon_s3_endpoint', '".db_escape(Files::getAwsS3RegionFromEndpoint($GLOBALS['amazon_s3_endpoint']))."');\n";
}

// Add new db user password flag
print "replace into redcap_config (field_name, value) values ('redcap_updates_password_encrypted', '1');\n";

// Add project-level option to disable the Shared Library
print "ALTER TABLE `redcap_projects` ADD `shared_library_enabled` TINYINT(1) NOT NULL DEFAULT '1' AFTER `pdf_show_logo_url`;\n";