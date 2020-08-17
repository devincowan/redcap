<?php

$sql = "";

## Find all foreign keys from redcap_mobile_app_log, then delete them, then re-add them (except for log_event_id)
$sql2 = "SHOW CREATE TABLE redcap_alerts_recurrence";
$q = db_query($sql2);
if ($q && db_num_rows($q) == 1)
{
	$sql .= "\n-- Remove foreign key from redcap_alerts_recurrence\n";
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);
	// Set as lower case to prevent case sensitivity issues
	$createTableStatement = strtolower($result[1]);
	## REMOVE ALL EXISTING FOREIGN KEYS
	// Set regex to pull out strings
	$regex = "/(constraint `)(redcap_alerts_recurrence_ibfk_\d)(`)/";
	// Do regex
	preg_match_all($regex, $createTableStatement, $matches);
	if (isset($matches[0]) && !empty($matches[0]))
	{
		// Parse invididual foreign key names
		foreach ($matches[0] as $this_fk)
		{
			$fk_name = preg_replace($regex, "$2", $this_fk);
			$sql .= "ALTER TABLE `redcap_alerts_recurrence` DROP FOREIGN KEY `$fk_name`;\n";
		}
	}
	## RE-ADD ALL FOREIGN KEYS
	$sql .= "ALTER TABLE `redcap_alerts_recurrence`
	ADD FOREIGN KEY (`alert_id`) REFERENCES `redcap_alerts` (`alert_id`) ON DELETE CASCADE ON UPDATE CASCADE,
	ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;\n";
}

$sql .= "
ALTER TABLE `redcap_alerts` ADD `cron_send_email_on_field` VARCHAR(255) NULL DEFAULT NULL AFTER `cron_send_email_on_time_lag_minutes`;
ALTER TABLE `redcap_surveys_scheduler` ADD `condition_send_time_lag_field` VARCHAR(255) NULL DEFAULT NULL AFTER `condition_send_time_lag_minutes`;
ALTER TABLE `redcap_alerts` ADD `cron_send_email_on_field_after` ENUM('before','after') NOT NULL DEFAULT 'after' AFTER `cron_send_email_on_field`;
ALTER TABLE `redcap_surveys_scheduler` ADD `condition_send_time_lag_field_after` ENUM('before','after') NOT NULL DEFAULT 'after' AFTER `condition_send_time_lag_field`;

CREATE TABLE `redcap_ehr_fhir_logs` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`user_id` int(11) DEFAULT NULL,
`fhir_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
`mrn` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
`project_id` int(11) NOT NULL,
`resource_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
`status` int(11) NOT NULL,
`created_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `project_id_mrn` (`project_id`,`mrn`),
KEY `user_project_mrn_resource` (`user_id`,`project_id`,`mrn`,`resource_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_ehr_fhir_logs`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_config (field_name, value) VALUES ('mandrill_api_key', '');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);

// Add Messenger system notification
$title = "New send-time setting for ASIs and Alerts";
$msg = "Great news! A new setting has been added to Automated Survey Invitations and Alerts & Notifications to allow you to specify the send-time of the invitation/alert based on the value of a date or datetime field in the project. When setting up an ASI or Alert, the third radio button option under the \"When to send\" section allows you to set the time delay and ALSO specify whether the time delay is based on when the invitation/alert was triggered or based on the time value of a specific field. It can be set in days, hours, and/or minutes  before *or* after the time value of a given date or datetime field. This should now make it much easier to more accurately specify the send-time of an ASI or Alert. Enjoy!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);