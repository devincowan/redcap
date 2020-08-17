ALTER TABLE `redcap_ddp_records_data` ADD `source_value2` TEXT NULL DEFAULT NULL AFTER `source_value`;
ALTER TABLE `redcap_ddp_records_data` 
	DROP INDEX `map_id_mr_id_timestamp_value`,
	ADD KEY `map_id_mr_id_timestamp_value` (`map_id`,`mr_id`,`source_timestamp`,`source_value2`(255));
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('DDPReencryptData', 'Re-encrypt all DDP data from the external source system.', 'ENABLED', 60, 1800, 10, 0, NULL, 0, NULL);
update redcap_crons set cron_enabled = 'DISABLED' where cron_name = 'DDPFetchRecordsAllProjects';
ALTER TABLE `redcap_mobile_app_log` ADD `longitude` DOUBLE NULL DEFAULT NULL AFTER `details`, ADD `latitude` DOUBLE NULL DEFAULT NULL AFTER `longitude`;
