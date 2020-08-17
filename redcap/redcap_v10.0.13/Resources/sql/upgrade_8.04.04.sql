ALTER TABLE `redcap_projects` 
	ADD `pdf_custom_header_text` TEXT NULL DEFAULT NULL AFTER `custom_public_survey_links`, 
	ADD `pdf_show_logo_url` TINYINT(1) NOT NULL DEFAULT '1' AFTER `pdf_custom_header_text`;