-- Add new tables and cron job
CREATE TABLE `redcap_history_size` (
`date` date NOT NULL DEFAULT '1000-01-01',
`size_db` float DEFAULT NULL COMMENT 'MB',
`size_files` float DEFAULT NULL COMMENT 'MB',
PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Space usage of REDCap database and uploaded files.';
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('DbUsage', 'Record the daily space usage of the database tables and the uploaded files stored on the server.', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL);
CREATE TABLE `redcap_history_version` (
`date` date NOT NULL DEFAULT '1000-01-01',
`redcap_version` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='History of REDCap versions installed on this server.';
REPLACE INTO redcap_history_version (`date`, redcap_version) values
	((select value from redcap_config WHERE field_name = 'redcap_last_install_date'), (select value from redcap_config WHERE field_name = 'redcap_version'));
-- Placeholders for Google Cloud Storage buckets
insert into redcap_config values ('google_cloud_storage_edocs_bucket', '');
insert into redcap_config values ('google_cloud_storage_temp_bucket', '');