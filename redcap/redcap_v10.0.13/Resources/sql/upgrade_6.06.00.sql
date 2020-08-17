-- Enable Twilio settings
update redcap_config set value = '1' where field_name = 'twilio_enabled_global';
update redcap_config set value = '1' where field_name = 'twilio_enabled_by_super_users_only';
update redcap_config set value = '0' where field_name = 'twilio_display_info_project_setup';
-- Modify tables for Twilio features
ALTER TABLE `redcap_projects` CHANGE `twilio_multiple_sms_behavior` `twilio_multiple_sms_behavior` ENUM('OVERWRITE','CHOICE','FIRST')
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'CHOICE';