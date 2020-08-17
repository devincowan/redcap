<?php

global $redcap_version, $redcap_base_url;

// Set parent/child Project Bookmark bridge
$sql = "select p.project_id as child_id, p.app_title as child_title, a.project_id as parent_id, a.app_title as parent_title
		from redcap_projects p, redcap_projects a
		where p.is_child_of != '' and p.is_child_of is not null and a.project_name = p.is_child_of";
$q = db_query($sql);
$url_base = $redcap_base_url."redcap_v{$redcap_version}/DataEntry/parent_child.php?pid=";
while ($row = db_fetch_assoc($q))
{
	$child_id = $row['child_id'];
	$child_title = "CHILD: ".strip_tags(label_decode($row['child_title']));
	$parent_id = $row['parent_id'];
	$parent_title = "PARENT: ".strip_tags(label_decode($row['parent_title']));
	// Add bookmark to child that links to parent
	print "INSERT INTO redcap_external_links (project_id, link_order, link_url, link_label, append_record_info) "
		. "VALUES ($child_id, '999', '".db_escape($url_base . $parent_id)."', "
		. "'".db_escape($parent_title)."', '1');\n";
	// Add bookmark to parent that links to child
	print "INSERT INTO redcap_external_links (project_id, link_order, link_url, link_label, append_record_info) "
		. "VALUES ($parent_id, '999', '".db_escape($url_base . $child_id)."', "
		. "'".db_escape($child_title)."', '1');\n";
}

// Remove old standards tables that were never used
print "-- Remove old standards tables that were never used
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `redcap_standard`, `redcap_standard_code`, `redcap_standard_map`, `redcap_standard_map_audit`, `redcap_standard_map_audit_action`;
SET FOREIGN_KEY_CHECKS=1;
";

// Add email reset key to redcap_auth
print "-- Add email reset key to redcap_auth
ALTER TABLE `redcap_auth` ADD `password_reset_key` VARCHAR(255) NULL DEFAULT NULL , ADD UNIQUE (`password_reset_key`) ;
";

// Add ability for longitudinal reports to filter at record level
print "-- Add ability for longitudinal reports to filter at record level
ALTER TABLE `redcap_reports` ADD `filter_type` ENUM('RECORD','EVENT') NOT NULL DEFAULT 'EVENT' ;
";

// Add new user attribute
print "-- Add new user attribute
ALTER TABLE `redcap_user_information` ADD `display_on_email_users` INT(1) NOT NULL DEFAULT '1' ;
";

// Add two factor auth setting
print "-- Add two factor auth setting
INSERT INTO redcap_config (field_name, value) values
('two_factor_auth_type', '0'),
('two_factor_auth_twilio_enabled', '0'),
('two_factor_auth_twilio_account_sid', ''),
('two_factor_auth_twilio_auth_token', ''),
('two_factor_auth_twilio_from_number', ''),
('google_oauth2_client_id', ''),
('google_oauth2_client_secret', '');
";

?>
-- Fix DDP encryption issue by removing all DDP data fetched from the source system
-- so that it will force the cron to refresh it.
delete from redcap_ddp_records;
-- Fix phone (U.S.) validation type
UPDATE `redcap_validation_types` SET `validation_label` = 'Phone (North America)',
	`regex_js` = '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]1[02-9]|[2-9][02-9]1|[2-9][02-9]{2})\\s*(?:[.-]\\s*)?([0-9]{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/',
	`regex_php` = '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]1[02-9]|[2-9][02-9]1|[2-9][02-9]{2})\\s*(?:[.-]\\s*)?([0-9]{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/'
	WHERE `validation_name` = 'phone';
-- Fix email validation type
UPDATE `redcap_validation_types` SET
	`regex_js`  = '/^([_a-z0-9-'']+)([.+][_a-z0-9-'']+)*@([a-z0-9-]+)(\\.[a-z0-9-]+)*(\\.[a-z]{2,4})$/i',
	`regex_php` = '/^([_a-z0-9-'']+)([.+][_a-z0-9-'']+)*@([a-z0-9-]+)(\\.[a-z0-9-]+)*(\\.[a-z]{2,4})$/i'
	WHERE `validation_name` = 'email';
-- Remove config setting that is no longer used
delete from redcap_config where field_name = 'doc_to_edoc_transfer_complete';
-- Modify IVR table
ALTER TABLE redcap_surveys_phone_codes
	DROP PRIMARY KEY,
	ADD KEY `participant_twilio_phone` (`phone_number`,`twilio_number`),
	ADD `pc_id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
-- Add setting for Automated Survey Invitations
ALTER TABLE `redcap_surveys_scheduler` ADD `reeval_before_send` INT(1) NOT NULL DEFAULT '0' ;
-- Remove and add settings for projects table
ALTER TABLE `redcap_projects` DROP `twilio_pre_enabled`,
	ADD `twilio_default_delivery_preference` ENUM('EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL') NOT NULL DEFAULT 'EMAIL',
	ADD `twilio_request_inspector_checked` DATETIME NULL DEFAULT NULL,
	ADD `twilio_request_inspector_enabled` INT(1) NOT NULL DEFAULT '1' ;
CREATE TABLE `redcap_surveys_erase_twilio_log` (
`tl_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`ts` datetime DEFAULT NULL,
`sid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`sid_hash` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`tl_id`),
UNIQUE KEY `sid` (`sid`),
UNIQUE KEY `sid_hash` (`sid_hash`),
KEY `project_id` (`project_id`),
KEY `ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Temporary storage of Twilio logs to be deleted';
ALTER TABLE `redcap_surveys_erase_twilio_log`
	ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_projects` ADD `twilio_append_response_instructions` INT(1) NOT NULL DEFAULT '1' ;
ALTER TABLE `redcap_library_map` ADD `scoring_type` ENUM('EACH_ITEM','END_ONLY') NULL DEFAULT NULL COMMENT 'If has scoring, what type?';
CREATE TABLE `redcap_events_repeat` (
`event_id` int(10) DEFAULT NULL,
`form_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
UNIQUE KEY `event_id_form` (`event_id`,`form_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_events_repeat`
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_surveys_phone_codes`
	ADD `project_id` INT(10) NULL DEFAULT NULL,
	ADD INDEX(`project_id`),
	ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects`(`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('EraseTwilioLog', 'Clear all items from redcap_surveys_erase_twilio_log table.',  'ENABLED',  120,  300,  1,  0, NULL , 0, NULL);

ALTER TABLE `redcap_projects`
	CHANGE `twilio_default_delivery_preference` `twilio_default_delivery_preference`
		ENUM('EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL','SMS_INVITE_WEB')
		CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'EMAIL',
	ADD `twilio_option_sms_invite_web` TINYINT(1) NOT NULL DEFAULT '0' AFTER `twilio_option_sms_invite_receive_call`,
	ADD `twilio_multiple_sms_behavior` ENUM('OVERWRITE','CHOICE') NOT NULL DEFAULT 'CHOICE',
	ADD UNIQUE KEY `twilio_from_number` (`twilio_from_number`);
ALTER TABLE `redcap_surveys_emails` CHANGE `delivery_type` `delivery_type`
	ENUM('PARTICIPANT_PREF','EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL','SMS_INVITE_WEB')
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'EMAIL';
ALTER TABLE `redcap_surveys_emails_recipients` CHANGE `delivery_type` `delivery_type`
	ENUM('EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL','SMS_INVITE_WEB')
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'EMAIL';
ALTER TABLE `redcap_surveys_participants` CHANGE `delivery_preference` `delivery_preference`
	ENUM('EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL','SMS_INVITE_WEB')
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `redcap_surveys_scheduler` CHANGE `delivery_type` `delivery_type`
	ENUM('EMAIL','VOICE_INITIATE','SMS_INITIATE','SMS_INVITE_MAKE_CALL','SMS_INVITE_RECEIVE_CALL','PARTICIPANT_PREF','SMS_INVITE_WEB')
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'EMAIL';

-- Table changes for Field Annotation feature (field-level and project-level) - increased to 16MB max size
ALTER TABLE `redcap_projects`
	CHANGE `project_note` `project_note` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	ADD KEY `project_note` (`project_note`(255));
ALTER TABLE `redcap_metadata` CHANGE `misc` `misc` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Miscellaneous field attributes';
ALTER TABLE `redcap_metadata_temp` CHANGE `misc` `misc` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Miscellaneous field attributes';
ALTER TABLE `redcap_metadata_archive` CHANGE `misc` `misc` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Miscellaneous field attributes';

-- -------------------------------------------------------
-- WARNING: THE FOLLOWING QUERY MAY TAKE *SEVERAL HOURS* TO RUN --
--
ALTER TABLE `redcap_data`
	ADD `instance` SMALLINT(4) NULL DEFAULT NULL,
	DROP INDEX `event_id`,
	ADD KEY `event_id_instance` (`event_id`, `instance`);
-- -------------------------------------------------------
