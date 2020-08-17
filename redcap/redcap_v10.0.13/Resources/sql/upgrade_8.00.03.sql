ALTER TABLE `redcap_surveys` ADD `save_and_return_code_bypass` TINYINT(1) NOT NULL DEFAULT '0' AFTER `save_and_return`;
INSERT INTO redcap_config (field_name, value) VALUES ('fhir_data_mart_create_project', '0');
ALTER TABLE `redcap_user_information` ADD `fhir_data_mart_create_project` TINYINT(1) NOT NULL DEFAULT '0' AFTER `api_token_auto_request`;
ALTER TABLE `redcap_projects` ADD `realtime_webservice_datamart_info` TEXT NULL DEFAULT NULL AFTER `realtime_webservice_offset_plusminus`;
UPDATE `redcap_validation_types` SET 
	`regex_js`  = '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]\\d{2})\\s*(?:[.-]\\s*)?(\\d{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/', 
	`regex_php` = '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]\\d{2})\\s*(?:[.-]\\s*)?(\\d{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/' 
	WHERE `redcap_validation_types`.`validation_name` = 'phone';