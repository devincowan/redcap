<?php
// Table changes
$sql = "
ALTER TABLE `redcap_projects` 
	ADD `allow_delete_record_from_log` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shared_library_enabled`, 
	ADD `delete_file_repository_export_files` INT(3) NOT NULL DEFAULT '0' 
		COMMENT 'Will auto-delete files after X days' AFTER `allow_delete_record_from_log`,
	ADD `custom_project_footer_text` TEXT NULL DEFAULT NULL AFTER `delete_file_repository_export_files`,
	ADD `custom_project_footer_text_link` VARCHAR(255) NULL DEFAULT NULL AFTER `custom_project_footer_text`,
	ADD INDEX(`delete_file_repository_export_files`);
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('DeleteFileRepositoryExportFiles', 'For projects with this feature enabled, delete all archived data export files older than X days.', 'ENABLED', 43200, 300, 1, 0, NULL, 0, NULL);
ALTER TABLE `redcap_web_service_cache` CHANGE `category` `category` VARCHAR(150) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);