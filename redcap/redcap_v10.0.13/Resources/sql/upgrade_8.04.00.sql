ALTER TABLE `redcap_external_modules_downloads` ADD INDEX(`time_downloaded`);
ALTER TABLE `redcap_external_modules_downloads` ADD INDEX(`time_deleted`);
update redcap_config set value = 'https://data.bioontology.org/' where field_name = 'bioportal_api_url' and value = 'http://data.bioontology.org/';
-- Add new table for future functionality
drop table if exists redcap_record_list;
CREATE TABLE `redcap_record_list` (
`project_id` int(10) NOT NULL,
`arm` tinyint(2) NOT NULL,
`record` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
`dag_id` int(10) DEFAULT NULL,
`sort` mediumint(7) DEFAULT NULL,
PRIMARY KEY (`project_id`,`arm`,`record`),
UNIQUE KEY `sort_project_arm` (`sort`,`project_id`,`arm`),
KEY `dag_project_arm` (`dag_id`,`project_id`,`arm`),
KEY `project_record` (`project_id`,`record`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_record_list`
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;