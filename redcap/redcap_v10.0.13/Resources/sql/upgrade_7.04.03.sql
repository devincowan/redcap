ALTER TABLE `redcap_surveys` 
	ADD `email_participant_field` VARCHAR(255) NULL DEFAULT NULL AFTER `survey_time_limit_minutes`, 
	ADD `end_of_survey_pdf_download` TINYINT NOT NULL DEFAULT '0' AFTER `email_participant_field`;
INSERT INTO redcap_config (field_name, value) VALUES
	('api_token_request_type', 'admin_approve');
ALTER TABLE `redcap_user_information` ADD `api_token_auto_request` TINYINT(1) NOT NULL DEFAULT '0' AFTER `ui_state`;