<?php

$sql = "
-- Add new Data Mart setting
ALTER TABLE  `redcap_projects`
    ADD `datamart_cron_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If true, the cron job will pull data automatically for all records at a specified interval X times per day.';    
-- Add new Data Mart cron
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('ClinicalDataMartDataFetch', 'Fetches EHR data for all Clinical Data Mart projects', 'ENABLED', 43200, 3600, 1, 0, NULL, 0, NULL);
-- Add FHIR endpoint permissions
ALTER TABLE `redcap_ehr_access_tokens` 
    ADD `permission_Patient` TINYINT(1) NULL DEFAULT NULL AFTER `refresh_token`, 
    ADD `permission_Observation` TINYINT(1) NULL DEFAULT NULL AFTER `permission_Patient`, 
    ADD `permission_Condition` TINYINT(1) NULL DEFAULT NULL AFTER `permission_Observation`, 
    ADD `permission_MedicationOrder` TINYINT(1) NULL DEFAULT NULL AFTER `permission_Condition`, 
    ADD `permission_AllergyIntolerance` TINYINT(1) NULL DEFAULT NULL AFTER `permission_MedicationOrder`;
-- New config
INSERT INTO `redcap_config` (`field_name`, `value`) VALUES
('allow_outbound_http', '1');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);