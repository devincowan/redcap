<?php

$sql = "
ALTER TABLE `redcap_alerts` ADD `email_repetitive_change` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Re-send alert on form re-save if data has been added or modified?' AFTER `email_repetitive`;
ALTER TABLE `redcap_alerts` CHANGE `email_repetitive` `email_repetitive` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Re-send alert on form re-save?';
ALTER TABLE `redcap_alerts` ADD `email_repetitive_change_calcs` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Include calc fields for email_repetitive_change?' AFTER `email_repetitive_change`;
ALTER TABLE `redcap_alerts` ADD `cron_repeat_for_max` SMALLINT(4) NULL DEFAULT NULL AFTER `cron_repeat_for_units`;
ALTER TABLE `redcap_alerts_recurrence` 
    ADD INDEX `alert_id_status_times_sent` ( `status`, `alert_id`, `times_sent`),
    ADD INDEX `send_option` (`send_option`);
INSERT INTO redcap_config (field_name, value) VALUES ('use_email_display_name', '1');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);