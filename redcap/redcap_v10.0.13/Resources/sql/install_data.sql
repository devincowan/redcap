
-- REDCAP INSTALLATION INITIAL DATA --

INSERT INTO redcap_user_information (username, user_email, user_firstname, user_lastname, super_user, user_firstvisit) VALUES
('site_admin', 'joe.user@projectredcap.org', 'Joe', 'User', 1, now());

INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('PubMed', 'Query the PubMed API to find publications associated with PIs in REDCap, and store publication attributes and PI/project info. Emails will then be sent to any PIs that have been found to have publications in PubMed, and (if applicable) will be asked to associate their publication to a REDCap project.', 'DISABLED', 86400, 7200, 1, 0, NULL, 0, NULL),
('RemoveTempAndDeletedFiles', 'Delete all files from the REDCap temp directory, and delete all edoc and Send-It files marked for deletion.', 'ENABLED', 120, 600, 1, 0, NULL, 0, NULL),
('ExpireSurveys', 'For any surveys where an expiration timestamp is set, if the timestamp <= NOW, then make the survey inactive.', 'ENABLED', 120, 600, 1, 0, NULL, 0, NULL),
('SurveyInvitationEmailer', 'Mailer that sends any survey invitations that have been scheduled.', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL),
('DeleteProjects', 'Delete all projects that are scheduled for permanent deletion', 'ENABLED', 300, 1200, 1, 0, NULL, 0, NULL),
('ClearIPCache',  'Clear all IP addresses older than X minutes from the redcap_ip_cache table.',  'ENABLED',  180,  60,  1,  0, NULL , 0, NULL),
('ExpireUsers', 'For any users whose expiration timestamp is set, if the timestamp <= NOW, then suspend the user''s account and set expiration time back to NULL.', 'ENABLED', 120, 600, 1, 0, NULL, 0, NULL),
('WarnUsersAccountExpiration', 'For any users whose expiration timestamp is set, if the expiration time is less than X days from now, then email the user to warn them of their impending account expiration.', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL),
('SuspendInactiveUsers', 'For any users whose last login time exceeds the defined max days of inactivity, auto-suspend their account (if setting enabled).', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL),
('ReminderUserAccessDashboard', 'At a regular interval, email all users to remind them to visit the User Access Dashboard page. Enables the ReminderUserAccessDashboardEmail cron job.', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL),
('ReminderUserAccessDashboardEmail', 'Email all users in batches to remind them to visit the User Access Dashboard page. Will disable itself when done.', 'DISABLED', 60, 1800, 5, 0, NULL, 0, NULL),
('DDPQueueRecordsAllProjects', 'Queue records that are ready to be fetched from the external source system via the DDP service.', 'ENABLED', 300, 600, 1, 0, NULL, 0, NULL),
('DDPFetchRecordsAllProjects', 'Fetch data from the external source system for records already queued by the DDP service.', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL),
('PurgeCronHistory', 'Purges all rows from the crons history table that are older than one week.', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL),
('UpdateUserPasswordAlgo', 'Send email to all Table-based users telling them to log in for the purpose of upgrading their password security (one time only)', 'DISABLED', 86400, 7200, 1, 0, NULL, 0, NULL),
('AutomatedSurveyInvitationsDatediffChecker', 'Check all conditional logic in Automated Surveys Invitations that uses "today" inside datediff() function', 'DISABLED', 43200, 7200, 1, 0, NULL, 0, NULL),
('AutomatedSurveyInvitationsDatediffChecker2', 'Check all conditional logic in Automated Surveys Invitations that uses "today" inside datediff() function - replacement for AutomatedSurveyInvitationsDatediffChecker', 'ENABLED', 14400, 7200, 1, 0, NULL, 0, NULL),
('ClearSurveyShortCodes', 'Clear all survey short codes older than X minutes.',  'ENABLED',  300,  60,  1,  0, NULL , 0, NULL),
('ClearLogViewRequests', 'Clear all items from redcap_log_view_requests table older than X hours.',  'ENABLED',  1800,  300,  1,  0, NULL , 0, NULL),
('EraseTwilioLog', 'Clear all items from redcap_surveys_erase_twilio_log table.',  'ENABLED',  120,  300,  1,  0, NULL , 0, NULL),
('ClearNewRecordCache', 'Clear all items from redcap_new_record_cache table older than X hours.',  'ENABLED',  10800,  300,  1,  0, NULL , 0, NULL),
('FixStuckSurveyInvitations', 'Reset any survey invitations stuck in SENDING status for than X hours back to QUEUED status.',  'ENABLED',  3600,  300,  1,  0, NULL , 0, NULL),
('DbUsage', 'Record the daily space usage of the database tables and the uploaded files stored on the server.', 'ENABLED', 86400, 600, 1, 0, NULL, 0, NULL),
('RemoveOutdatedRecordCounts', 'Delete all rows from the record counts table older than X days.', 'ENABLED', 3600, 60, 1, 0, NULL, 0, NULL),
('DDPReencryptData', 'Re-encrypt all DDP data from the external source system.', 'ENABLED', 60, 1800, 10, 0, NULL, 0, NULL),
('UserMessagingEmailNotifications', 'Send notification emails to users who are logged out but have received a user message or notification.', 'ENABLED', 60, 600, 1, 0, NULL, 0, NULL),
('CacheStatsReportingUrl', 'Generate the stats reporting URL and store it in the config table.', 'ENABLED', 10800, 1200, 1, 0, NULL, 0, NULL),
('ExternalModuleValidation', 'Perform various validation checks on External Modules that are installed.', 'ENABLED', 1800, 300, 1, 0, NULL, 0, NULL),
('CheckREDCapRepoUpdates', 'Check if any installed External Modules have updates available on the REDCap Repo.', 'ENABLED', 10800, 300, 1, 0, NULL, 0, NULL),
('CheckREDCapVersionUpdates', 'Check if there is a newer REDCap version available', 'ENABLED', 10800, 300, 1, 0, NULL, 0, NULL),
('DeleteFileRepositoryExportFiles', 'For projects with this feature enabled, delete all archived data export files older than X days.', 'ENABLED', 43200, 300, 1, 0, NULL, 0, NULL),
('AlertsNotificationsSender', 'Sends notifications for Alerts', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL),
('AlertsNotificationsDatediffChecker', 'Check all conditional logic in Alerts that uses "today" inside datediff() function', 'ENABLED', 14400, 7200, 1, 0, NULL, 0, NULL),
('ClinicalDataMartDataFetch', 'Fetches EHR data for all Clinical Data Mart projects', 'ENABLED', 43200, 3600, 1, 0, NULL, 0, NULL);

INSERT INTO redcap_auth_questions (qid, question) VALUES
(1, 'What was your childhood nickname?'),
(2, 'In what city did you meet your spouse/significant other?'),
(3, 'What is the name of your favorite childhood friend?'),
(4, 'What street did you live on in third grade?'),
(5, 'What is your oldest sibling''s birthday month and year? (e.g., January 1900)'),
(6, 'What is the middle name of your oldest child?'),
(7, 'What is your oldest sibling''s middle name?'),
(8, 'What school did you attend for sixth grade?'),
(9, 'What was your childhood phone number including area code? (e.g., 000-000-0000)'),
(10, 'What is your oldest cousin''s first and last name?'),
(11, 'What was the name of your first stuffed animal?'),
(12, 'In what city or town did your mother and father meet?'),
(13, 'Where were you when you had your first kiss?'),
(14, 'What is the first name of the boy or girl that you first kissed?'),
(15, 'What was the last name of your third grade teacher?'),
(16, 'In what city does your nearest sibling live?'),
(17, 'What is your oldest brother''s birthday month and year? (e.g., January 1900)'),
(18, 'What is your maternal grandmother''s maiden name?'),
(19, 'In what city or town was your first job?'),
(20, 'What is the name of the place your wedding reception was held?'),
(21, 'What is the name of a college you applied to but didn''t attend?');

INSERT INTO redcap_config (field_name, value) VALUES
('fhir_break_the_glass_enabled', ''),
('fhir_break_the_glass_ehr_usertype', 'SystemLogin'),
('fhir_break_the_glass_token_usertype', 'EMP'),
('fhir_break_the_glass_token_username', ''),
('fhir_break_the_glass_token_password', ''),
('fhir_break_the_glass_username_token_base_url', ''),
('record_locking_pdf_vault_filesystem_type', ''),
('record_locking_pdf_vault_filesystem_host', ''),
('record_locking_pdf_vault_filesystem_username', ''),
('record_locking_pdf_vault_filesystem_password', ''),
('record_locking_pdf_vault_filesystem_path', ''),
('record_locking_pdf_vault_filesystem_private_key_path', ''),
('mandrill_api_key', ''),
('shibboleth_table_config', '{\"splash_default\":\"non-inst-login\",\"table_login_option\":\"Use local REDCap login\",\"institutions\":[{\"login_option\":\"Shibboleth Login\",\"login_text\":\"Click the image below to login using Shibboleth\",\"login_image\":\"https:\/\/wiki.shibboleth.net\/confluence\/download\/attachments\/131074\/atl.site.logo?version=2&modificationDate=1502412080059&api=v2\",\"login_url\":\"\"}]}'),
('survey_pid_create_project', ''),
('survey_pid_move_to_prod_status', ''),
('survey_pid_move_to_analysis_status', ''),
('survey_pid_mark_completed', ''),
('email_alerts_converter_enabled', '0'),
('use_email_display_name', '1'),
('alerts_allow_phone_variables', '1'),
('alerts_allow_phone_freeform', '1'),
('fhir_standalone_authentication_flow', 'standalone_launch'),
('external_modules_allow_activation_user_request', '1'),
('dkim_private_key', ''),
('enable_url_shortener_redcap', '1'),
('from_email_domain_exclude', ''),
('fhir_include_email_address', '0'),
('file_upload_vault_filesystem_type', ''),
('file_upload_vault_filesystem_host', ''),
('file_upload_vault_filesystem_username', ''),
('file_upload_vault_filesystem_password', ''),
('file_upload_vault_filesystem_path', ''),
('file_upload_vault_filesystem_private_key_path', ''),
('file_upload_versioning_enabled', '1'),
('file_upload_versioning_global_enabled', '1'),
('allow_outbound_http', '1'),
('drw_upload_option_enabled', '1'),
('pdf_econsent_system_custom_text', ''),
('alerts_email_freeform_domain_allowlist', ''),
('alerts_allow_email_variables', '1'),
('alerts_allow_email_freeform', '1'),
('azure_quickstart', '0'),
('google_recaptcha_site_key', ''),
('google_recaptcha_secret_key', ''),
('aws_quickstart', '0'),
('user_messaging_prevent_admin_messaging', '0'),
('homepage_announcement_login', '1'),
('azure_app_name', ''),
('azure_app_secret', ''),
('azure_container', ''),
('redcap_updates_community_user', ''),
('redcap_updates_community_password', ''),
('redcap_updates_user', ''),
('redcap_updates_password', ''),
('redcap_updates_password_encrypted', '1'),
('redcap_updates_available', ''),
('redcap_updates_available_last_check', ''),
('realtime_webservice_convert_timestamp_from_gmt', '0'),
('fhir_convert_timestamp_from_gmt', '0'),
('db_collation', 'utf8mb4_unicode_ci'),
('db_character_set', 'utf8mb4'),
('external_modules_updates_available', ''),
('external_modules_updates_available_last_check', ''),
('pdf_econsent_system_ip', '1'),
('pdf_econsent_filesystem_type', ''),
('pdf_econsent_filesystem_host', ''),
('pdf_econsent_filesystem_username', ''),
('pdf_econsent_filesystem_password', ''),
('pdf_econsent_filesystem_path', ''),
('pdf_econsent_filesystem_private_key_path', ''),
('pdf_econsent_system_enabled', '1'),
('enable_edit_prod_repeating_setup', '1'),
('user_sponsor_set_expiration_days', '365'),
('user_sponsor_dashboard_enable', '1'),
('clickjacking_prevention', '0'),
('external_module_alt_paths', ''),
('aafAccessUrl', ''),
('aafAllowLocalsCreateDB', ''),
('aafAud', ''),
('aafDisplayOnEmailUsers', ''),
('aafIss', ''),
('aafPrimaryField', ''),
('aafScopeTarget', ''),
('external_modules_project_custom_text', ''),
('is_development_server', '0'),
('fhir_data_mart_create_project', '0'),
('fhir_data_fetch_interval', '24'),
('fhir_url_user_access', ''),
('fhir_custom_text', ''),
('fhir_display_info_project_setup', '1'),
('fhir_source_system_custom_name', 'EHR'),
('fhir_user_rights_super_users_only', '1'),
('fhir_stop_fetch_inactivity_days', '7'),
('fhir_ddp_enabled', '0'),
('api_token_request_type', 'admin_approve'),
('fhir_endpoint_authorize_url', ''),
('fhir_endpoint_token_url', ''),
('fhir_ehr_mrn_identifier', ''),
('fhir_client_id', ''),
('fhir_client_secret', ''),
('fhir_endpoint_base_url', ''),
('report_stats_url', ''),
('user_messaging_enabled', '1'),
('auto_prod_changes_check_identifiers', '0'),
('bioportal_api_url', 'https://data.bioontology.org/'),
('send_emails_admin_tasks', '1'),
('display_project_xml_backup_option', '1'),
('cross_domain_access_control', ''),
('google_cloud_storage_edocs_bucket', ''),
('google_cloud_storage_temp_bucket', ''),
('amazon_s3_endpoint', ''),
('proxy_username_password', ''),
('homepage_contact_url', ''),
('bioportal_api_token', ''),
('two_factor_auth_ip_range_alt', ''),
('two_factor_auth_trust_period_days_alt', '0'),
('two_factor_auth_trust_period_days', '0'),
('two_factor_auth_email_enabled', '1'),
('two_factor_auth_authenticator_enabled', '1'),
('two_factor_auth_ip_check_enabled', '0'),
('two_factor_auth_ip_range', ''),
('two_factor_auth_ip_range_include_private', '0'),
('two_factor_auth_duo_enabled', '0'),
('two_factor_auth_duo_ikey', ''),
('two_factor_auth_duo_skey', ''),
('two_factor_auth_duo_hostname', ''),
('bioportal_ontology_list_cache_time', ''),
('bioportal_ontology_list', ''),
('redcap_survey_base_url', ''),
('enable_ontology_auto_suggest', '1'),
('enable_survey_text_to_speech', '1'),
('enable_field_attachment_video_url', '1'),
('google_oauth2_client_id', ''),
('google_oauth2_client_secret', ''),
('two_factor_auth_twilio_enabled', '0'),
('two_factor_auth_twilio_account_sid', ''),
('two_factor_auth_twilio_auth_token', ''),
('two_factor_auth_twilio_from_number', ''),
('two_factor_auth_enabled', '0'),
('allow_kill_mysql_process', '0'),
('mobile_app_enabled', '1'),
('twilio_display_info_project_setup', '0'),
('twilio_enabled_global', '1'),
('twilio_enabled_by_super_users_only', '0'),
('field_comment_log_enabled_default', '1'),
('from_email', ''),
('promis_enabled', '1'),
('promis_api_base_url', 'https://www.redcap-cats.org/promis_api/'),
('sams_logout', ''),
('promis_registration_id', ''),
('promis_token', ''),
('hook_functions_file', ''),
('project_encoding', ''),
('default_datetime_format', 'M/D/Y_12'),
('default_number_format_decimal', '.'),
('default_number_format_thousands_sep', ','),
('homepage_announcement', ''),
('password_algo', 'md5'),
('password_recovery_custom_text', ''),
('user_access_dashboard_enable', '1'),
('user_access_dashboard_custom_notification', ''),
('suspend_users_inactive_send_email', 1),
('suspend_users_inactive_days', 180),
('suspend_users_inactive_type', ''),
('page_hit_threshold_per_minute', '600'),
('enable_http_compression', '1'),
('realtime_webservice_data_fetch_interval', '24'),
('realtime_webservice_url_metadata', ''),
('realtime_webservice_url_data', ''),
('realtime_webservice_url_user_access', ''),
('realtime_webservice_global_enabled', '0'),
('realtime_webservice_custom_text', ''),
('realtime_webservice_display_info_project_setup', '1'),
('realtime_webservice_source_system_custom_name', ''),
('realtime_webservice_user_rights_super_users_only', '1'),
('realtime_webservice_stop_fetch_inactivity_days', '7'),
('amazon_s3_key', ''),
('amazon_s3_secret', ''),
('amazon_s3_bucket', ''),
('system_offline_message', ''),
('openid_provider_url', ''),
('openid_provider_name', ''),
('file_attachment_upload_max', ''),
('data_entry_trigger_enabled', '1'),
('redcap_base_url_display_error_on_mismatch', '1'),
('email_domain_allowlist', ''),
('helpfaq_custom_text', ''),
('randomization_global', '1'),
('login_custom_text', ''),
('auto_prod_changes', '4'),
('enable_edit_prod_events', '1'),
('allow_create_db_default', '1'),
('api_enabled', '1'),
('auth_meth_global', 'none'),
('auto_report_stats', '1'),
('auto_report_stats_last_sent', '2000-01-01'),
('autologout_timer', '30'),
('certify_text_create', ''),
('certify_text_prod', ''),
('homepage_custom_text', ''),
('dts_enabled_global', '0'),
('display_nonauth_projects', '1'),
('display_project_logo_institution', '0'),
('display_today_now_button', '1'),
('edoc_field_option_enabled', '1'),
('edoc_upload_max', ''),
('edoc_storage_option', '0'),
('file_repository_upload_max', ''),
('file_repository_enabled', '1'),
('temp_files_last_delete', now()),
('edoc_path', ''),
('enable_edit_survey_response', '1'),
('enable_plotting', '2'),
('enable_plotting_survey_results', '1'),
('enable_projecttype_singlesurvey', '1'),
('enable_projecttype_forms', '1'),
('enable_projecttype_singlesurveyforms', '1'),
('enable_url_shortener', '1'),
('enable_user_allowlist', '0'),
('logout_fail_limit', '5'),
('logout_fail_window', '15'),
('footer_links', ''),
('footer_text', ''),
('google_translate_enabled', '0'),
('googlemap_key',''),
('grant_cite', ''),
('headerlogo', ''),
('homepage_contact', ''),
('homepage_contact_email', ''),
('homepage_grant_cite', ''),
('identifier_keywords', 'name, street, address, city, county, precinct, zip, postal, date, phone, fax, mail, ssn, social security, mrn, dob, dod, medical, record, id, age'),
('institution', ''),
('language_global','English'),
('login_autocomplete_disable', '0'),
('login_logo', ''),
('my_profile_enable_edit','1'),
('password_history_limit','0'),
('password_reset_duration','0'),
('project_contact_email', ''),
('project_contact_name', ''),
('project_language', 'English'),
('proxy_hostname', ''),
('pub_matching_enabled', '0'),
('redcap_base_url', ''),
('pub_matching_emails', '0'),
('pub_matching_email_days', '7'),
('pub_matching_email_limit', '3'),
('pub_matching_email_text', ''),
('pub_matching_email_subject', ''),
('pub_matching_institution', 'Vanderbilt\nMeharry'),
('redcap_last_install_date', CURRENT_DATE),
('redcap_version', '4.0.0'),
('sendit_enabled', '1'),
('sendit_upload_max', ''),
('shared_library_enabled', '1'),
('shibboleth_logout', ''),
('shibboleth_username_field', 'none'),
('site_org_type', ''),
('superusers_only_create_project', '0'),
('superusers_only_move_to_prod', '1'),
('system_offline', '0');

INSERT INTO `redcap_pub_sources` (`pubsrc_id`, `pubsrc_name`, `pubsrc_last_crawl_time`) VALUES
(1, 'PubMed', NULL);

INSERT INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) VALUES
('alpha_only', 'Letters only', '/^[a-z]+$/i', '/^[a-z]+$/i', 'text', NULL, 0),
('date_dmy', 'Date (D-M-Y)', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4}))$/', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4}))$/', 'date', NULL, 1),
('date_mdy', 'Date (M-D-Y)', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4}))$/', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4}))$/', 'date', NULL, 1),
('date_ymd', 'Date (Y-M-D)', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31))))$/', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31))))$/', 'date', 'date', 1),
('datetime_dmy', 'Datetime (D-M-Y H:M)', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4})) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4})) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', 'datetime', NULL, 1),
('datetime_mdy', 'Datetime (M-D-Y H:M)', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4})) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4})) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', 'datetime', NULL, 1),
('datetime_seconds_dmy', 'Datetime w/ seconds (D-M-Y H:M:S)', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4})) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', '/^((29([-\\/])02\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1\\d|2[0-8])([-\\/])(0[1-9]|1[012]))|((29|30)([-\\/])(0[13-9]|1[012]))|(31([-\\/])(0[13578]|1[02])))(\\11|\\15|\\18)\\d{4})) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', 'datetime_seconds', NULL, 1),
('datetime_seconds_mdy', 'Datetime w/ seconds (M-D-Y H:M:S)', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4})) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', '/^((02([-\\/])29\\3(\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00)))|((((0[1-9]|1[012])([-\\/])(0[1-9]|1\\d|2[0-8]))|((0[13-9]|1[012])([-\\/])(29|30))|((0[13578]|1[02])([-\\/])31))(\\11|\\15|\\19)\\d{4})) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', 'datetime_seconds', NULL, 1),
('datetime_seconds_ymd', 'Datetime w/ seconds (Y-M-D H:M:S)', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31)))) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31)))) (\\d|[0-1]\\d|[2][0-3])(:[0-5]\\d){2}$/', 'datetime_seconds', 'datetime_seconds', 1),
('datetime_ymd', 'Datetime (Y-M-D H:M)', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31)))) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', '/^(((\\d{2}([13579][26]|[2468][048]|04|08)|(1600|2[048]00))([-\\/])02(\\6)29)|(\\d{4}([-\\/])((0[1-9]|1[012])(\\9)(0[1-9]|1\\d|2[0-8])|((0[13-9]|1[012])(\\9)(29|30))|((0[13578]|1[02])(\\9)31)))) (\\d|[0-1]\\d|[2][0-3]):[0-5]\\d$/', 'datetime', 'datetime', 1),
('email', 'Email', '/^(?!\\.)((?!.*\\.{2})[a-zA-Z0-9\\u0080-\\u02AF\\u0300-\\u07FF\\u0900-\\u18AF\\u1900-\\u1A1F\\u1B00-\\u1B7F\\u1D00-\\u1FFF\\u20D0-\\u214F\\u2C00-\\u2DDF\\u2F00-\\u2FDF\\u2FF0-\\u2FFF\\u3040-\\u319F\\u31C0-\\uA4CF\\uA700-\\uA71F\\uA800-\\uA82F\\uA840-\\uA87F\\uAC00-\\uD7AF\\uF900-\\uFAFF!#$%&\'*+\\-/=?^_`{|}~\\d]+)(\\.[a-zA-Z0-9\\u0080-\\u02AF\\u0300-\\u07FF\\u0900-\\u18AF\\u1900-\\u1A1F\\u1B00-\\u1B7F\\u1D00-\\u1FFF\\u20D0-\\u214F\\u2C00-\\u2DDF\\u2F00-\\u2FDF\\u2FF0-\\u2FFF\\u3040-\\u319F\\u31C0-\\uA4CF\\uA700-\\uA71F\\uA800-\\uA82F\\uA840-\\uA87F\\uAC00-\\uD7AF\\uF900-\\uFAFF!#$%&\'*+\\-/=?^_`{|}~\\d]+)*@(?!\\.)([a-zA-Z0-9\\u0080-\\u02AF\\u0300-\\u07FF\\u0900-\\u18AF\\u1900-\\u1A1F\\u1B00-\\u1B7F\\u1D00-\\u1FFF\\u20D0-\\u214F\\u2C00-\\u2DDF\\u2F00-\\u2FDF\\u2FF0-\\u2FFF\\u3040-\\u319F\\u31C0-\\uA4CF\\uA700-\\uA71F\\uA800-\\uA82F\\uA840-\\uA87F\\uAC00-\\uD7AF\\uF900-\\uFAFF\\-\\.\\d]+)((\\.([a-zA-Z\\u0080-\\u02AF\\u0300-\\u07FF\\u0900-\\u18AF\\u1900-\\u1A1F\\u1B00-\\u1B7F\\u1D00-\\u1FFF\\u20D0-\\u214F\\u2C00-\\u2DDF\\u2F00-\\u2FDF\\u2FF0-\\u2FFF\\u3040-\\u319F\\u31C0-\\uA4CF\\uA700-\\uA71F\\uA800-\\uA82F\\uA840-\\uA87F\\uAC00-\\uD7AF\\uF900-\\uFAFF]){2,63})+)$/i', '/^(?!\\.)((?!.*\\.{2})[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)(\\.[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)*@(?!\\.)([a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\-\\.\\d]+)((\\.([a-zA-Z\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}]){2,63})+)$/u', 'email', NULL, 1),
('integer', 'Integer', '/^[-+]?\\b\\d+\\b$/', '/^[-+]?\\b\\d+\\b$/', 'integer', 'int', 1),
('mrn_10d', 'MRN (10 digits)', '/^\\d{10}$/', '/^\\d{10}$/', 'mrn', NULL, 0),
('mrn_generic', 'MRN (generic)', '/^[a-z0-9-_]+$/i', '/^[a-z0-9-_]+$/i', 'mrn', NULL, 0),
('number', 'Number', '/^[-+]?[0-9]*\\.?[0-9]+([eE][-+]?[0-9]+)?$/', '/^[-+]?[0-9]*\\.?[0-9]+([eE][-+]?[0-9]+)?$/', 'number', 'float', 1),
('number_1dp', 'Number (1 decimal place)', '/^-?\\d+\\.\\d$/', '/^-?\\d+\\.\\d$/', 'number', NULL, 0),
('number_2dp', 'Number (2 decimal places)', '/^-?\\d+\\.\\d{2}$/', '/^-?\\d+\\.\\d{2}$/', 'number', NULL, 0),
('number_3dp', 'Number (3 decimal places)', '/^-?\\d+\\.\\d{3}$/', '/^-?\\d+\\.\\d{3}$/', 'number', NULL, 0),
('number_4dp', 'Number (4 decimal places)', '/^-?\\d+\\.\\d{4}$/', '/^-?\\d+\\.\\d{4}$/', 'number', NULL, 0),
('phone', 'Phone (North America)', '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]\\d{2})\\s*(?:[.-]\\s*)?(\\d{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/', '/^(?:\\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\\)?)\\s*(?:[.-]\\s*)?([2-9]\\d{2})\\s*(?:[.-]\\s*)?(\\d{4})(?:\\s*(?:#|x\\.?|ext\\.?|extension)\\s*(\\d+))?$/', 'phone', NULL, 1),
('phone_australia', 'Phone (Australia)', '/^(\\(0[2-8]\\)|0[2-8])\\s*\\d{4}\\s*\\d{4}$/', '/^(\\(0[2-8]\\)|0[2-8])\\s*\\d{4}\\s*\\d{4}$/', 'phone', NULL, 0),
('postalcode_australia', 'Postal Code (Australia)', '/^\\d{4}$/', '/^\\d{4}$/', 'postal_code', NULL, 0),
('postalcode_canada', 'Postal Code (Canada)', '/^[ABCEGHJKLMNPRSTVXY]{1}\\d{1}[A-Z]{1}\\s*\\d{1}[A-Z]{1}\\d{1}$/i', '/^[ABCEGHJKLMNPRSTVXY]{1}\\d{1}[A-Z]{1}\\s*\\d{1}[A-Z]{1}\\d{1}$/i', 'postal_code', NULL, 0),
('ssn', 'Social Security Number (U.S.)', '/^\\d{3}-\\d\\d-\\d{4}$/', '/^\\d{3}-\\d\\d-\\d{4}$/', 'ssn', NULL, 0),
('time', 'Time (HH:MM)', '/^([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9])$/', '/^([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9])$/', 'time', NULL, 1),
('time_mm_ss', 'Time (MM:SS)', '/^[0-5]\\d:[0-5]\\d$/', '/^[0-5]\\d:[0-5]\\d$/', 'time', NULL, 0),
('vmrn', 'Vanderbilt MRN', '/^[0-9]{4,9}$/', '/^[0-9]{4,9}$/', 'mrn', NULL, 0),
('zipcode', 'Zipcode (U.S.)', '/^\\d{5}(-\\d{4})?$/', '/^\\d{5}(-\\d{4})?$/', 'postal_code', NULL, 1),
('number_comma_decimal', 'Number (comma as decimal)', '/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/', '/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/', 'number_comma_decimal', NULL, 0),
('number_1dp_comma_decimal',  'Number (1 decimal place - comma as decimal)',  '/^-?\\d+,\\d$/',  '/^-?\\d+,\\d$/',  'number_comma_decimal', NULL ,  '0'),
('number_2dp_comma_decimal',  'Number (2 decimal places - comma as decimal)',  '/^-?\\d+,\\d{2}$/',  '/^-?\\d+,\\d{2}$/',  'number_comma_decimal', NULL ,  '0'),
('number_3dp_comma_decimal',  'Number (3 decimal places - comma as decimal)',  '/^-?\\d+,\\d{3}$/',  '/^-?\\d+,\\d{3}$/',  'number_comma_decimal', NULL ,  '0'),
('number_4dp_comma_decimal',  'Number (4 decimal places - comma as decimal)',  '/^-?\\d+,\\d{4}$/',  '/^-?\\d+,\\d{4}$/',  'number_comma_decimal', NULL ,  '0'),
('postalcode_germany', 'Postal Code (Germany)', '/^(0[1-9]|[1-9]\\d)\\d{3}$/',  '/^(0[1-9]|[1-9]\\d)\\d{3}$/', 'postal_code', NULL, 0),
('postalcode_french', 'Code Postal 5 caracteres (France)', '/^((0?[1-9])|([1-8][0-9])|(9[0-8]))[0-9]{3}$/', '/^((0?[1-9])|([1-8][0-9])|(9[0-8]))[0-9]{3}$/', 'postal_code', NULL, 0);

INSERT INTO redcap_surveys_themes (theme_name, ui_id, theme_text_buttons, theme_bg_page, theme_text_title, theme_bg_title, theme_text_sectionheader, theme_bg_sectionheader, theme_text_question, theme_bg_question) VALUES
('Flat White', NULL, '000000', 'eeeeee', '000000', 'FFFFFF', 'FFFFFF', '444444', '000000', 'FFFFFF'),
('Slate and Khaki', NULL, '000000', 'EBE8D9', '000000', 'c5d5cb', 'FFFFFF', '909A94', '000000', 'f3f3f3'),
('Colorful Pastel', NULL, '000', 'f1fafc', '274e13', 'e9f1e3', '660000', 'F6C2C2', '660000', 'f7f8d7'),
('Blue Skies', NULL, '0C74A9', 'cfe2f3', '0b5394', 'FFFFFF', 'FFFFFF', '0b5394', '0b5394', 'ffffff'),
('Cappucino', NULL, '7d4627', '783f04', '7d4627', 'fff', 'FFFFFF', 'b18b64', '783f04', 'fce5cd'),
('Red Brick', NULL, '000000', '660000', 'ffffff', '990000', 'ffffff', '000000', '000000', 'ffffff'),
('Grayscale', NULL, '30231d', '000000', 'ffffff', '666666', 'ffffff', '444444', '000000', 'eeeeee'),
('Plum', NULL, '000000', '351c75', '000000', 'd9d2e9', 'FFFFFF', '8e7cc3', '000000', 'd9d2e9'),
('Forest Green', NULL, '7f6000', '274e13', 'ffffff', '6aa84f', 'ffffff', '38761d', '7f6000', 'd9ead3'),
('Sunny Day', NULL, 'B2400E', 'FFFF80', 'B2400E', 'FFFFFF', 'FFFFFF', 'f67719', 'b85b16', 'FEFFD3');

INSERT INTO redcap_messages_threads (thread_id, type, channel_name, invisible, archived) VALUES
(1, 'NOTIFICATION', 'What''s new', 0, 0),
(2, 'NOTIFICATION', NULL, 0, 0),
(3, 'NOTIFICATION', 'Notifications', 0, 0);

INSERT INTO redcap_messages_recipients (recipient_id, thread_id, all_users) VALUES 
(1, 1, 1), 
(2, 2, 1),
(3, 3, 1);
