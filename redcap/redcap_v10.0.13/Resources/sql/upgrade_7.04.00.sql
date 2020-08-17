-- Enable REDCap Messenger
UPDATE redcap_config SET value = '1' WHERE field_name = 'user_messaging_enabled';
-- Disable cron
update redcap_crons set cron_enabled='DISABLED' where cron_name = 'AutomatedSurveyInvitationsDatediffChecker';
-- Add new cron
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('AutomatedSurveyInvitationsDatediffChecker2', 'Check all conditional logic in Automated Surveys Invitations that uses "today" inside datediff() function - replacement for AutomatedSurveyInvitationsDatediffChecker', 'ENABLED', 14400, 7200, 1, 0, NULL, 0, NULL);