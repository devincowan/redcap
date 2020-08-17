alter table redcap_projects add key `app_title` (`app_title`(255));
-- Reduce number of allowed simultaneous DDP data pulls
update redcap_crons set cron_instances_max = 5 where cron_name = 'DDPFetchRecordsAllProjects';
-- Add e-consent fields to surveys table
ALTER TABLE `redcap_surveys` 
	ADD `pdf_econsent_firstname_event_id` INT(11) NULL DEFAULT NULL AFTER `pdf_econsent_firstname_field`, 
	ADD INDEX (`pdf_econsent_firstname_event_id`),
	ADD `pdf_econsent_lastname_event_id` INT(11) NULL DEFAULT NULL AFTER `pdf_econsent_lastname_field`, 
	ADD INDEX (`pdf_econsent_lastname_event_id`),
	ADD `pdf_econsent_dob_event_id` INT(11) NULL DEFAULT NULL AFTER `pdf_econsent_dob_field`, 
	ADD INDEX (`pdf_econsent_dob_event_id`);
ALTER TABLE `redcap_surveys`
	ADD FOREIGN KEY (`pdf_econsent_firstname_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
	ADD FOREIGN KEY (`pdf_econsent_lastname_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
	ADD FOREIGN KEY (`pdf_econsent_dob_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `redcap_projects` 
	ADD `pdf_econsent_filesystem_type` ENUM('SFTP','WEBDAV') NULL DEFAULT NULL AFTER `custom_public_survey_links`, 
	ADD `pdf_econsent_filesystem_host` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_filesystem_type`, 
	ADD `pdf_econsent_filesystem_port` INT(5) NULL DEFAULT NULL AFTER `pdf_econsent_filesystem_host`, 
	ADD `pdf_econsent_filesystem_username` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_filesystem_port`, 
	ADD `pdf_econsent_filesystem_password` VARCHAR(255) NULL DEFAULT NULL AFTER `pdf_econsent_filesystem_username`, 
	ADD `pdf_econsent_filesystem_path` VARCHAR(255) NULL DEFAULT NULL AFTER `pdf_econsent_filesystem_password`;