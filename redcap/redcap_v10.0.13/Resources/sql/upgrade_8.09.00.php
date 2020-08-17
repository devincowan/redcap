<?php
// New tables
$newTables = "
CREATE TABLE `redcap_reports_edit_access_dags` (
`report_id` int(10) NOT NULL AUTO_INCREMENT,
`group_id` int(10) NOT NULL DEFAULT '0',
PRIMARY KEY (`report_id`,`group_id`),
KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_reports_edit_access_roles` (
`report_id` int(10) NOT NULL DEFAULT '0',
`role_id` int(10) NOT NULL DEFAULT '0',
PRIMARY KEY (`report_id`,`role_id`),
KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_reports_edit_access_users` (
`report_id` int(10) NOT NULL AUTO_INCREMENT,
`username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
PRIMARY KEY (`report_id`,`username`),
KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_reports_edit_access_dags`
ADD FOREIGN KEY (`group_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports_edit_access_roles`
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`role_id`) REFERENCES `redcap_user_roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports_edit_access_users`
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports` ADD `user_edit_access` ENUM('ALL', 'SELECTED') NOT NULL DEFAULT 'ALL' AFTER `user_access`;

";
// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($newTables);

// Add Messenger system notification
$title = "Enhancements to Reports";
$msg = "Reports can now be organized into folders (called Report Folders) in any given project. If you have \"Add/Edit Reports\" privileges, you will see an \"Organize\" link on the left-hand project menu above your reports. You will be able to create folders and then assign your reports to a folder, after which the project's reports will be displayed in collapsible groups on the left-hand menu. Report Folders are a great way to organize reports if your project has a lot of them.

Also, in addition to setting \"View Access\" when creating or editing a report, you can now set the report's \"Edit Access\" (under Step 1) to control who in the project can edit, copy, or delete the report. This setting will be very useful if you wish to prevent certain users from modifying or deleting particular reports.

There is also a new search feature on the left-hand menu to allow you to search within the title of your reports to help you navigate to a report very quickly. ENJOY!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);