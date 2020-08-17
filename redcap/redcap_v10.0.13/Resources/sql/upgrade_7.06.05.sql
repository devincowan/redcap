delete from redcap_config where field_name = 'fhir_ehr_type';
INSERT INTO redcap_config (field_name, value) VALUES
('fhir_data_fetch_interval', '24'),
('fhir_url_user_access', ''),
('fhir_custom_text', ''),
('fhir_display_info_project_setup', '1'),
('fhir_source_system_custom_name', 'EHR'),
('fhir_user_rights_super_users_only', '1'),
('fhir_stop_fetch_inactivity_days', '7'),
('fhir_ddp_expose', '0');
INSERT INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) VALUES
('mrn_generic', 'MRN (generic)', '/^[a-z0-9-_]+$/i', '/^[a-z0-9-_]+$/i', 'mrn', NULL, 0);
UPDATE `redcap_validation_types` SET `data_type` = 'mrn' WHERE `validation_name` like '%mrn%';
ALTER TABLE `redcap_surveys` ADD `confirmation_email_attach_pdf` TINYINT(1) default '0' AFTER `confirmation_email_from`;