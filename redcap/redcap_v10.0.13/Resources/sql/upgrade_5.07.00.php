<?php

## INCORRECT USER FIRST ACTIVITY TIMESTAMP
// Don't do this if user just upgraded from 5.0.20+ LTS branch (since 5.0.20 already did this)
if (getDecVersion($current_version) != 50020 && getDecVersion($current_version) != 50021 && getDecVersion($current_version) != 50022) {
	// Fix issue with user's time of first activity not being correct
	$sql = "select ui_id, username from redcap_user_information where user_firstactivity is not null
			and username is not null and username != '' order by ui_id";
	$q = db_query($sql);
	// Loop through each user and get each's first activity in log_event table
	print "-- Fix issue with user's time of first activity not being correct\n";
	while ($row = db_fetch_assoc($q))
	{
		// Get first activity timestamp
		$sql2 = "select timestamp(ts) from redcap_log_event where user = '".db_escape($row['username'])."' order by log_event_id limit 1";
		$q2 = db_query($sql2);
		if (db_num_rows($q2) > 0) {
			$ts = db_result($q2, 0);
			print "update redcap_user_information set user_firstactivity = '$ts' where ui_id = {$row['ui_id']};\n";
		}
	}
}

## USER FIRST ACTIVITY TIMESTAMP IS MISSING
// Fix issue with user's time of first activity missing
$sql = "select ui_id, username from redcap_user_information where user_firstactivity is null
		and username is not null and username != '' order by ui_id";
$q = db_query($sql);
// Loop through each user and get each's first activity in log_event table
print "-- Fix issue with user missing time of first activity\n";
while ($row = db_fetch_assoc($q))
{
	// Get first activity timestamp
	$sql2 = "select timestamp(ts) from redcap_log_event where user = '".db_escape($row['username'])."' order by log_event_id limit 1";
	$q2 = db_query($sql2);
	if (db_num_rows($q2) > 0) {
		$ts = db_result($q2, 0);
		print "update redcap_user_information set user_firstactivity = '$ts' where ui_id = {$row['ui_id']};\n";
	}
}

## USER LAST ACTIVITY TIMESTAMP IS MISSING
// Fix issue with user's time of last activity missing
$sql = "select ui_id, username from redcap_user_information where user_lastactivity is null and user_firstvisit is not null
		and username is not null and username != '' order by ui_id";
$q = db_query($sql);
// Loop through each user and get each's last activity in log_event table
print "-- Fix issue with user missing time of last activity\n";
while ($row = db_fetch_assoc($q))
{
	// Get first activity timestamp
	$sql2 = "select timestamp(ts) from redcap_log_event where user = '".db_escape($row['username'])."' order by log_event_id desc limit 1";
	$q2 = db_query($sql2);
	if (db_num_rows($q2) > 0) {
		$ts = db_result($q2, 0);
		print "update redcap_user_information set user_lastactivity = '$ts' where ui_id = {$row['ui_id']};\n";
	}
}

## USER LAST LOGIN TIMESTAMP IS MISSING
// Fix issue with user's time of last login missing
$sql = "select ui_id, username from redcap_user_information where user_lastlogin is null and user_firstvisit is not null
		and username is not null and username != '' order by ui_id";
$q = db_query($sql);
// Loop through each user and get each's last activity in log_view table
print "-- Fix issue with user missing time of last login\n";
while ($row = db_fetch_assoc($q))
{
	// Get session_id of last session and use the last session_id to get the first timestamp of that session
	$sql2 = "select timestamp(ts) from redcap_log_view where user = '".db_escape($row['username'])."'
			and session_id = (select session_id from redcap_log_view where user = '".db_escape($row['username'])."' order by log_view_id desc limit 1)
			order by log_view_id limit 1";
	$q2 = db_query($sql2);
	if (db_num_rows($q2) > 0) {
		$ts = db_result($q2, 0);
		print "update redcap_user_information set user_lastactivity = '$ts' where ui_id = {$row['ui_id']};\n";
	}
}


// Other upgrade scripts
?>
-- If user has a last activity timestamp but not a first activity timestamp, set both as same
update redcap_user_information set user_firstactivity = user_lastactivity where user_lastactivity is not null and user_firstactivity is null;
-- If user has a first login timestamp but not a last login timestamp, set both as same
update redcap_user_information set user_lastlogin = user_firstvisit where user_firstvisit is not null and user_lastlogin is null;
-- Add new user attributes
ALTER TABLE  `redcap_user_information` ADD  `user_creation` DATETIME NULL DEFAULT NULL COMMENT  'Time user account was created' AFTER  `super_user`;
-- Set default user creation time as same as "first visit" time
update redcap_user_information set user_creation = user_firstvisit;
-- Set user creation time of any table-based users (using log_event table)
update redcap_user_information u, (select timestamp(max(l.ts)) as ts, l.pk as username from redcap_log_event l
	where (l.page = 'ControlCenter/create_user.php' or l.page = 'ControlCenter/create_user_bulk.php')
	and l.event = 'MANAGE' and l.object_type = 'redcap_auth'
	and l.description = 'Create username' group by l.pk) x set u.user_creation = x.ts where x.username = u.username;
-- User-level expiration
ALTER TABLE  `redcap_user_information` ADD  `user_expiration` DATETIME NULL DEFAULT NULL COMMENT
	'Time at which the user will be automatically suspended from REDCap' AFTER  `user_suspended_time`;
-- Add new crons and remove old cron
delete from redcap_crons where cron_name = 'DbCleanup';
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('ExpireUsers',  'For any users whose expiration timestamp is set, if the timestamp <= NOW, then suspend the user''s account and set expiration time back to NULL.',
	'ENABLED',  '120',  '600',  '1', '0', NULL , NULL ,  '0', NULL);
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('WarnUsersAccountExpiration',  'For any users whose expiration timestamp is set, if the expiration time is less than X days from now, then email the user to warn them of their impending account expiration.',
	'ENABLED',  '86400',  '600',  '1', '0', NULL , NULL ,  '0', NULL);
-- Set new config option for auto-suspension if exceed max number of days inactive
insert into redcap_config values ('suspend_users_inactive_days', 180);
insert into redcap_config values ('suspend_users_inactive_type', '');
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('SuspendInactiveUsers',  'For any users whose last login time exceeds the defined max days of inactivity, auto-suspend their account (if setting enabled).',
	'ENABLED',  '86400',  '600',  '1', '0', NULL , NULL ,  '0', NULL);
-- Add other attributes
ALTER TABLE  `redcap_user_information` ADD  `user_sponsor` VARCHAR( 255 ) NULL DEFAULT NULL
	COMMENT  'Username of user''s sponsor or contact person' AFTER  `user_expiration`;
ALTER TABLE  `redcap_user_information` ADD  `user_access_dashboard_view` DATETIME NULL DEFAULT NULL AFTER  `user_expiration` ,
	ADD INDEX (  `user_access_dashboard_view` );
ALTER TABLE  `redcap_user_information` ADD INDEX (  `user_creation` ), ADD INDEX (  `user_firstvisit` ), ADD INDEX (  `user_firstactivity` ),
	ADD INDEX (  `user_lastactivity` ), ADD INDEX (  `user_lastlogin` ), ADD INDEX (  `user_suspended_time` ), ADD INDEX (  `user_expiration` );
insert into redcap_config values ('user_access_dashboard_enable', '1');
insert into redcap_config values ('user_access_dashboard_custom_notification', '');
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('ReminderUserAccessDashboard',  'At a regular interval, email all users to remind them to visit the User Access Dashboard page. Enables the ReminderUserAccessDashboardEmailer cron job.',
	'ENABLED',  '86400',  '600',  '1', '0', NULL , NULL ,  '0', NULL);
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('ReminderUserAccessDashboardEmail',  'Email all users in batches to remind them to visit the User Access Dashboard page. Will disable itself when done.',
	'DISABLED',  '60',  '1800',  '5', '0', NULL , NULL ,  '0', NULL);
ALTER TABLE  `redcap_user_information` ADD  `user_access_dashboard_email_queued` ENUM(  'QUEUED',  'SENDING' ) NULL DEFAULT NULL
	COMMENT  'Tracks status of email reminder for User Access Dashboard' AFTER `user_access_dashboard_view` ,
	ADD INDEX (  `user_access_dashboard_email_queued` );
ALTER TABLE  `redcap_user_information` ADD INDEX (  `user_email` );
ALTER TABLE  `redcap_user_information` ADD  `user_comments` TEXT NULL DEFAULT NULL COMMENT  'Miscellaneous comments about user' AFTER  `user_sponsor`;


insert into redcap_config values ('password_recovery_custom_text', '');