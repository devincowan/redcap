INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('CheckREDCapVersionUpdates', 'Check if there is a newer REDCap version available', 'ENABLED', 10800, 300, 1, 0, NULL, 0, NULL);

INSERT INTO redcap_config (field_name, value) VALUES
('redcap_updates_available', ''),
('redcap_updates_available_last_check', ''),
('redcap_updates_user', ''),
('redcap_updates_password', ''),
('redcap_updates_community_user', ''),
('redcap_updates_community_password', '');