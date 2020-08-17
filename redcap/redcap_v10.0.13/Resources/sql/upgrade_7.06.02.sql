INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('ExternalModuleValidation', 'Perform various validation checks on External Modules that are installed.', 'ENABLED', 900, 300, 1, 0, NULL, 0, NULL);
ALTER TABLE `redcap_crons` 
	ADD `external_module_id` INT(11) NULL DEFAULT NULL AFTER `cron_name`, 
	DROP INDEX cron_name,
	ADD UNIQUE KEY `cron_name_module_id` (`cron_name`,`external_module_id`), 
	ADD KEY (`external_module_id`);
ALTER TABLE `redcap_crons` 
	ADD FOREIGN KEY (`external_module_id`) REFERENCES `redcap_external_modules`(`external_module_id`) ON DELETE CASCADE ON UPDATE CASCADE;