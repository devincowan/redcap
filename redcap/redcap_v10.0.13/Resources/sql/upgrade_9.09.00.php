<?php

// Create SQL to importing existing DAG Switcher module settings
$dagSwitcherImport = "";
$sql = "select distinct s.project_id, t.`value` from redcap_external_modules e, redcap_external_module_settings s, redcap_external_module_settings t
		where e.directory_prefix = 'dag_switcher' and e.external_module_id = s.external_module_id and s.project_id is not null 
		and s.`key` = 'enabled' and s.`value` = 'true' and t.project_id = s.project_id and t.external_module_id = s.external_module_id and t.`key` = 'user-dag-mapping'";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$pid = $row['project_id'];
	$json = json_decode($row['value'], true);
	if (!is_array($json) || empty($json)) continue;
	foreach ($json as $user=>$dags) {
		$dags = array_unique($dags);
		$user = trim(strtolower($user));
		foreach ($dags as $this_dag_id) {
			if (!is_numeric($this_dag_id)) continue;
			$this_dag_id = (int)$this_dag_id;
			if ($this_dag_id == '0') $this_dag_id = 'null';
			$dagSwitcherImport .= "replace into redcap_data_access_groups_users (project_id, group_id, username) values ($pid, $this_dag_id, '".db_escape($user)."');\n";
		}
	}
}
if ($dagSwitcherImport != '') {
	$dagSwitcherImport = "-- Import existing DAG Switcher module settings\n$dagSwitcherImport";
}


$sql = "
CREATE TABLE `redcap_data_access_groups_users` (
`project_id` int(10) DEFAULT NULL,
`group_id` int(10) DEFAULT NULL,
`username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `group_id` (`group_id`,`username`),
UNIQUE KEY `username` (`username`,`project_id`,`group_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_data_access_groups_users`
ADD FOREIGN KEY (`group_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

$dagSwitcherImport
-- Disable DAG Switcher module at the system level
delete s.* from redcap_external_modules e, redcap_external_module_settings s where e.directory_prefix = 'dag_switcher'
and e.external_module_id = s.external_module_id and s.project_id is null and `key` = 'version';
";

$sql .= "
ALTER TABLE `redcap_projects` ADD `fhir_include_email_address_project` TINYINT(1) NULL DEFAULT NULL AFTER `datamart_cron_enabled`;
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);


// Add Messenger system notification
$title = "Assign users to multiple DAGs";
$msg = "A new feature called the DAG Switcher allows users in Data Access Groups (DAGs) to be assigned to multiple *potential* DAGs in a project, in which they may be given the privilege of switching in and out of specific DAGs	on their own whenever they wish. To assign a user to multiple DAGs, navigate to the Data Access Groups page in your project where you will see the DAG Switcher near the bottom of the page. Then follow the directions provided there. The DAG Switcher feature is completely optional and can be used in any project that has Data Access Groups. Enjoy!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);