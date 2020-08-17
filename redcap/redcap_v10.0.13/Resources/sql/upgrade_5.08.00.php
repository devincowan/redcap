<?php


// Get highest SHA-based algorithm
global $password_algo;
$password_algo = Authentication::getBestHashAlgo();
print "-- Set SHA-based algorithm for password hashing
insert into redcap_config values ('password_algo', '".db_escape($password_algo)."');
";

// Static upgrade SQL
?>
-- Fix issue where uploaded files might get marked for deletion if a record with uploaded files was created during a merge via Double Data Entry
update redcap_metadata m, redcap_data d, redcap_edocs_metadata e
set e.delete_date = null where m.element_type = 'file' and m.field_name = d.field_name and m.project_id = d.project_id
and e.project_id = m.project_id and e.doc_id = d.value and e.delete_date is not null and e.date_deleted_server is null;
-- Add matrix ranking
ALTER TABLE `redcap_metadata` ADD `grid_rank` INT( 1 ) NOT NULL DEFAULT '0' AFTER `grid_name`;
ALTER TABLE `redcap_metadata_temp` ADD `grid_rank` INT( 1 ) NOT NULL DEFAULT '0' AFTER `grid_name`;
ALTER TABLE `redcap_metadata_archive` ADD `grid_rank` INT( 1 ) NOT NULL DEFAULT '0' AFTER `grid_name`;
-- Modify password hashing algorithm
ALTER TABLE  `redcap_auth_history` CHANGE  `password`  `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  '';
ALTER TABLE  `redcap_auth` CHANGE  `password`  `password` VARCHAR( 255 )
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT  'Hash of user''s password';
ALTER TABLE  `redcap_auth` ADD  `password_salt` VARCHAR( 255 ) NULL COMMENT  'Unique random salt for password' AFTER  `password`;
update redcap_auth set password_salt = '';
ALTER TABLE  `redcap_auth` ADD  `legacy_hash` INT( 1 ) NOT NULL DEFAULT  '0'
	COMMENT  'Using older legacy hash for password storage?' AFTER  `password_salt`;
update redcap_auth set legacy_hash = '1';
-- Add new config settings
INSERT INTO redcap_config VALUES ('custom_functions_file', '');
INSERT INTO redcap_config VALUES ('homepage_announcement', '');
-- Add new cron job
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('UpdateUserPasswordAlgo', 'Send email to all Table-based users telling them to log in for the purpose of upgrading their password security (one time only)', 'DISABLED', 86400, 7200, 1, 0, NULL, 0, NULL);
