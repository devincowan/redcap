<?php

$sql = "
CREATE TABLE `redcap_log_event2` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `description_project` (`description`,`project_id`),
KEY `event_project` (`event`,`project_id`),
KEY `object_type` (`object_type`),
KEY `page_project` (`page`(191),`project_id`),
KEY `pk_project` (`pk`(191),`project_id`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_log_event3` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `description_project` (`description`,`project_id`),
KEY `event_project` (`event`,`project_id`),
KEY `object_type` (`object_type`),
KEY `page_project` (`page`(191),`project_id`),
KEY `pk_project` (`pk`(191),`project_id`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_log_event4` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `description_project` (`description`,`project_id`),
KEY `event_project` (`event`,`project_id`),
KEY `object_type` (`object_type`),
KEY `page_project` (`page`(191),`project_id`),
KEY `pk_project` (`pk`(191),`project_id`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_log_event5` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `description_project` (`description`,`project_id`),
KEY `event_project` (`event`,`project_id`),
KEY `object_type` (`object_type`),
KEY `page_project` (`page`(191),`project_id`),
KEY `pk_project` (`pk`(191),`project_id`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_projects` ADD `log_event_table` VARCHAR(20) NOT NULL DEFAULT 'redcap_log_event' COMMENT 'Project redcap_log_event table' AFTER `inactive_time`;
";

## Find all foreign keys from redcap_mobile_app_log, then delete them, then re-add them (except for log_event_id)
$sql2 = "SHOW CREATE TABLE redcap_mobile_app_log";
$q = db_query($sql2);
if ($q && db_num_rows($q) == 1)
{
	$sql .= "\n-- Remove log_event_id foreign key from redcap_mobile_app_log\n";
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);
	// Set as lower case to prevent case sensitivity issues
	$createTableStatement = strtolower($result[1]);
	## REMOVE ALL EXISTING FOREIGN KEYS
	// Set regex to pull out strings
	$regex = "/(constraint `)(redcap_mobile_app_log_ibfk_\d)(`)/";
	// Do regex
	preg_match_all($regex, $createTableStatement, $matches);
	if (isset($matches[0]) && !empty($matches[0]))
	{
		// Parse invididual foreign key names
		foreach ($matches[0] as $this_fk)
		{
			$fk_name = preg_replace($regex, "$2", $this_fk);
			$sql .= "ALTER TABLE `redcap_mobile_app_log` DROP FOREIGN KEY `$fk_name`;\n";
		}
	}
	## RE-ADD ALL FOREIGN KEYS (except for log_event_id, which we're removing
	$sql .= "ALTER TABLE `redcap_mobile_app_log`
	ADD FOREIGN KEY (`device_id`) REFERENCES `redcap_mobile_app_devices` (`device_id`) ON DELETE SET NULL ON UPDATE CASCADE,
	ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
	ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;\n";
}

$sql .= "
ALTER TABLE `redcap_projects` ADD `twilio_modules_enabled` ENUM('SURVEYS','ALERTS','SURVEYS_ALERTS') NOT NULL DEFAULT 'SURVEYS' AFTER `twilio_enabled`;
ALTER TABLE `redcap_alerts` ADD `alert_type` ENUM('EMAIL','SMS','VOICE_CALL') NOT NULL DEFAULT 'EMAIL' AFTER `alert_title`;
ALTER TABLE `redcap_alerts` ADD `phone_number_to` TEXT NULL DEFAULT NULL AFTER `email_to`;
ALTER TABLE `redcap_alerts_sent_log` ADD `alert_type` ENUM('EMAIL','SMS','VOICE_CALL') NOT NULL DEFAULT 'EMAIL' AFTER `alert_sent_id`;
ALTER TABLE `redcap_alerts_sent_log` ADD `phone_number_to` TEXT NULL DEFAULT NULL AFTER `email_to`;
ALTER TABLE `redcap_alerts` ADD `alert_stop_type` ENUM('RECORD','RECORD_EVENT','RECORD_EVENT_INSTRUMENT','RECORD_INSTRUMENT','RECORD_EVENT_INSTRUMENT_INSTANCE') 
    NOT NULL DEFAULT 'RECORD_EVENT_INSTRUMENT_INSTANCE' AFTER `alert_type`;
INSERT INTO redcap_config (field_name, value) VALUES
('alerts_allow_phone_variables', '1'),
('alerts_allow_phone_freeform', '1');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);