ALTER TABLE `redcap_log_view_requests` 
	DROP INDEX `log_view_id_time`, 
	ADD UNIQUE `log_view_id_time_ui_id` (`log_view_id`, `script_execution_time`, `ui_id`);
ALTER TABLE `redcap_surveys` 
	ADD `pdf_auto_archive` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=Disabled, 1=Normal, 2=eConsent' AFTER `end_of_survey_pdf_download`, 
	ADD `pdf_econsent_version` VARCHAR(64) NULL DEFAULT NULL AFTER `pdf_auto_archive`, 
	ADD `pdf_econsent_type` VARCHAR(64) NULL DEFAULT NULL AFTER `pdf_econsent_version`, 
	ADD `pdf_econsent_firstname_field` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_type`, 
	ADD `pdf_econsent_lastname_field` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_firstname_field`, 
	ADD `pdf_econsent_dob_field` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_lastname_field`;
CREATE TABLE `redcap_surveys_pdf_archive` (
	`doc_id` int(10) DEFAULT NULL,
	`record` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
	`event_id` int(10) DEFAULT NULL,
	`survey_id` int(10) DEFAULT NULL,
	`instance` smallint(4) NOT NULL DEFAULT '1',
	UNIQUE KEY `doc_id` (`doc_id`),
	UNIQUE KEY `record_event_survey_instance` (`record`,`event_id`,`survey_id`,`instance`),
	KEY `event_id` (`event_id`),
	KEY `survey_id` (`survey_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_surveys_pdf_archive`
	ADD FOREIGN KEY (`doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
	ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
	ADD FOREIGN KEY (`survey_id`) REFERENCES `redcap_surveys` (`survey_id`) ON DELETE CASCADE ON UPDATE CASCADE;
INSERT INTO redcap_config (field_name, value) VALUES
('enable_edit_prod_repeating_setup', '1');