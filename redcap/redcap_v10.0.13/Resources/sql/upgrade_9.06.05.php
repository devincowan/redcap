<?php

$sql = "
-- Disable Email Alerts converter
set @email_alerts_converter_enabled = (select value from redcap_config where field_name = 'email_alerts_converter_enabled' limit 1);
REPLACE INTO redcap_config (field_name, value) VALUES ('email_alerts_converter_enabled', if (@email_alerts_converter_enabled is null, '0', @email_alerts_converter_enabled));
-- Add placeholder for future feature
ALTER TABLE `redcap_surveys_scheduler` 
    ADD `instance` ENUM('FIRST','AFTER_FIRST') NOT NULL DEFAULT 'FIRST' COMMENT 'survey instance being triggered' AFTER `event_id`,
    ADD `condition_surveycomplete_instance` ENUM('FIRST','PREVIOUS') NOT NULL DEFAULT 'FIRST' COMMENT 'instance of trigger' AFTER `condition_surveycomplete_event_id`,
    DROP INDEX `survey_event`, 
    ADD UNIQUE `survey_event_instance` (`survey_id`, `event_id`, `instance`);
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);