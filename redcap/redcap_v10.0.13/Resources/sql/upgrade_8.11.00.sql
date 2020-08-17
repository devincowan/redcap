-- Add system-level setting for enabling REDCaptcha
INSERT INTO redcap_config (field_name, value) VALUES
('google_recaptcha_site_key', ''),
('google_recaptcha_secret_key', '');
-- Add project-level setting for enabling REDCaptcha
ALTER TABLE `redcap_projects` ADD `google_recaptcha_enabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `custom_project_footer_text_link`;
-- Disable Codebook Concertina module at the system level
delete s.* from redcap_external_modules e, redcap_external_module_settings s where e.directory_prefix = 'codebook_concertina'
and e.external_module_id = s.external_module_id and s.project_id is null and `key` = 'version';
-- Disable Survey Link Lookup module at the system level
delete s.* from redcap_external_modules e, redcap_external_module_settings s where e.directory_prefix = 'survey_link_lookup'
and e.external_module_id = s.external_module_id and s.project_id is null and `key` = 'version';
-- Disable Sticky Matrix Header module at the system level
delete s.* from redcap_external_modules e, redcap_external_module_settings s where
(e.directory_prefix = 'sticky_matrix_headers' or e.directory_prefix = 'vanderbilt_sticky-matrix-headers')
and e.external_module_id = s.external_module_id and s.project_id is null and `key` = 'version';
ALTER TABLE `redcap_library_map` ADD `promis_battery_key` VARCHAR(255) DEFAULT NULL COMMENT 'PROMIS battery key' AFTER `battery`;