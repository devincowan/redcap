-- Enable new features
ALTER TABLE `redcap_surveys` ADD `end_survey_redirect_next_survey` TINYINT(1) NOT NULL DEFAULT '0' ;
update redcap_config set value = '1' where field_name = 'enable_field_attachment_video_url';
update redcap_config set value = '1' where field_name = 'enable_survey_text_to_speech';
update redcap_config set value = '1' where field_name = 'enable_ontology_auto_suggest';
INSERT INTO redcap_config (field_name, value) VALUES ('redcap_survey_base_url', '');
INSERT INTO redcap_config (field_name, value) VALUES ('bioportal_ontology_list', '');
INSERT INTO redcap_config (field_name, value) VALUES ('bioportal_ontology_list_cache_time', '');
delete from redcap_config where field_name = 'two_factor_auth_type';
ALTER TABLE `redcap_config` CHANGE `value` `value` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;

-- Add config placeholders for upcoming features
INSERT INTO redcap_config (field_name, value) VALUES
('two_factor_auth_duo_enabled', '0'),
('two_factor_auth_duo_ikey', ''),
('two_factor_auth_duo_skey', ''),
('two_factor_auth_duo_hostname', ''),
('two_factor_auth_ip_check_enabled', '0'),
('two_factor_auth_ip_range', ''),
('two_factor_auth_ip_range_include_private', '0');