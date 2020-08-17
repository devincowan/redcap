<?php

$sql = "
-- Add all Archived projects to redcap_projects_user_hidden table
replace into redcap_projects_user_hidden (project_id, ui_id)
	select distinct p.project_id, i.ui_id from redcap_projects p, redcap_user_rights r, redcap_user_information i
	where p.status = 3 and p.project_id = r.project_id and r.username = i.username;
-- Set inactive_time for all Archived projects
update redcap_projects p
	set p.inactive_time = '".NOW."'
	where p.status = 3 and p.log_event_table = 'redcap_log_event';
update redcap_projects p
	left join redcap_log_event2 l on l.project_id = p.project_id and l.description = 'Archive project'
	set p.inactive_time = if (l.ts is null, '".NOW."', timestamp(l.ts))
	where p.status = 3 and p.log_event_table = 'redcap_log_event2';
update redcap_projects p
	left join redcap_log_event3 l on l.project_id = p.project_id and l.description = 'Archive project'
	set p.inactive_time = if (l.ts is null, '".NOW."', timestamp(l.ts))
	where p.status = 3 and p.log_event_table = 'redcap_log_event3';
update redcap_projects p
	left join redcap_log_event4 l on l.project_id = p.project_id and l.description = 'Archive project'
	set p.inactive_time = if (l.ts is null, '".NOW."', timestamp(l.ts))
	where p.status = 3 and p.log_event_table = 'redcap_log_event4';
update redcap_projects p
	left join redcap_log_event5 l on l.project_id = p.project_id and l.description = 'Archive project'
	set p.inactive_time = if (l.ts is null, '".NOW."', timestamp(l.ts))
	where p.status = 3 and p.log_event_table = 'redcap_log_event5';
-- Add new columns
ALTER TABLE `redcap_projects` 
	ADD `completed_time` DATETIME NULL DEFAULT NULL AFTER `inactive_time`,
	ADD `data_locked` TINYINT(1) NOT NULL DEFAULT '0' AFTER `completed_time`,
	ADD `completed_by` VARCHAR(100) NULL DEFAULT NULL AFTER `completed_time`,
	ADD INDEX(`completed_time`),
	ADD INDEX (`completed_by`);
-- Convert Archived projects to Analysis/Cleanup status
update redcap_projects set status = 2 where status = 3;
-- Automatically turn on 'Data Locked' feature for all current Archived/Inactive (i.e., Analysis/Cleanup) projects
update redcap_projects set data_locked = 1 where status = 2;
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);


// Add Messenger system notification
$title = "Notice: Project Status Changes";
$msg = "A few things have changed and improved upon in REDCap of which you should be aware.

1) The \"Archived\" project status has been removed and converted into a built-in Project Folder named \"My Hidden Projects\", as now seen at the bottom of your My Projects page. If you wish to hide any projects from your My Projects list, just click the Organize button on that page and place the projects into that new Project Folder. NOTE: Any already-archived projects will be automatically placed there for you.

2) The \"Inactive\" project status has been renamed to \"Analysis/Cleanup\" status to help reinforce that cleaning and analyzing your data is the next logical step after data collection in Production status.

3) Projects that are in \"Analysis/Cleanup\" status can now optionally have their project data set as \"Locked/Read-only\" or \"Editable\" (see the top of the Project Setup or Home page). This will give you more control to prevent data collection from happening while in this project status.

4) Mark a project as \"Completed\": If you are finished with a project and wish to make it completely inaccessible, you may mark the project as Completed. Doing so will take it offline and remove it from everyone's project list, after which it can only be seen again by clicking the Show Completed Projects link at the bottom of the My Projects page. Once marked as Completed, no one in the project (except for REDCap administrators) can access the project, and only administrators may undo the Completion and return it back to an accessible state for all project users. Marking a project as Completed is typically only done when you are sure that no one needs to access the project anymore, and you want to ensure that the project and its data remain intact for a certain amount of time.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);