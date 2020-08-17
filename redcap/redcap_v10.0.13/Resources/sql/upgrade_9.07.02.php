<?php

$sql = "
CREATE TABLE `redcap_projects_user_hidden` (
`project_id` int(10) NOT NULL,
`ui_id` int(10) NOT NULL,
PRIMARY KEY (`project_id`,`ui_id`),
KEY `ui_id` (`ui_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_projects_user_hidden`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_config (field_name, value) VALUES
('survey_pid_create_project', ''),
('survey_pid_move_to_prod_status', ''),
('survey_pid_move_to_analysis_status', ''),
('survey_pid_mark_completed', '');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);