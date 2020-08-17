INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('CheckREDCapRepoUpdates', 'Check if any installed External Modules have updates available on the REDCap Repo.', 'ENABLED', 10800, 300, 1, 0, NULL, 0, NULL);
INSERT INTO redcap_config (field_name, value) VALUES
('external_modules_updates_available', ''),
('external_modules_updates_available_last_check', '');