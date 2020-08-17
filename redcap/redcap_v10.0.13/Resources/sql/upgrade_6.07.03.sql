-- Add new cron job
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('FixStuckSurveyInvitations', 'Reset any survey invitations stuck in SENDING status for than X hours back to QUEUED status.',  'ENABLED',  3600,  300,  1,  0, NULL , 0, NULL);
-- Add more 2FA settings
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_ip_range_alt', '');
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_trust_period_days_alt', '0');
ALTER TABLE `redcap_user_information` ADD `two_factor_auth_code_expiration` INT(3) NOT NULL DEFAULT '2' ;
-- Some features that were mistakenly not enabled in fresh install of v6.7.X
update redcap_config set value = '1' where field_name = 'enable_field_attachment_video_url';
update redcap_config set value = '1' where field_name = 'enable_survey_text_to_speech';
update redcap_config set value = '1' where field_name = 'enable_ontology_auto_suggest';
set @bioportal_api_token = (select value from redcap_config where field_name = 'bioportal_api_token' limit 1);
REPLACE INTO redcap_config (field_name, value) VALUES ('bioportal_api_token', if (@bioportal_api_token is null, '', @bioportal_api_token));