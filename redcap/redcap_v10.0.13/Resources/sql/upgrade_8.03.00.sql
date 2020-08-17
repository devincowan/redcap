ALTER TABLE `redcap_projects` CHANGE `realtime_webservice_offset_days` 
	`realtime_webservice_offset_days` FLOAT NOT NULL DEFAULT '7' COMMENT 'Default value of days offset';
ALTER TABLE `redcap_surveys_pdf_archive` ADD `ip` varchar(100) NULL DEFAULT NULL AFTER `type`;
INSERT INTO redcap_config (field_name, value) VALUES
('pdf_econsent_system_ip', '1');