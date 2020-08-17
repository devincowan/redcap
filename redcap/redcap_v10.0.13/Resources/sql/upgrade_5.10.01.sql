-- Add new cron job
Update redcap_crons set cron_enabled = 'DISABLED' where cron_name = 'AutoNotificationScheduleChecker';
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('AutomatedSurveyInvitationsDatediffChecker', 'Check all conditional logic in Automated Surveys Invitations that uses "today" inside datediff() function', 'ENABLED', 43200, 7200, 1, 0, NULL, 0, NULL)
on duplicate key update cron_enabled = 'ENABLED', cron_frequency = 43200, cron_max_run_time = 7200, cron_instances_max = 1,
cron_instances_current = 0, cron_last_run_end = NULL, cron_times_failed = 0, cron_external_url = NULL;
-- Remove unneeded config option
delete from redcap_config where field_name = 'custom_verify_user_or_exit';