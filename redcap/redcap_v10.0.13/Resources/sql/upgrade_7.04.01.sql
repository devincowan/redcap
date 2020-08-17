DROP TABLE IF EXISTS `redcap_ehr_user_projects`;
CREATE TABLE `redcap_ehr_user_projects` (
`project_id` int(11) DEFAULT NULL,
`redcap_userid` int(11) DEFAULT NULL,
UNIQUE KEY `project_id_userid` (`project_id`,`redcap_userid`),
KEY `redcap_userid` (`redcap_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_ehr_user_projects`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`redcap_userid`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
DROP TABLE IF EXISTS `redcap_ehr_user_map`;
CREATE TABLE `redcap_ehr_user_map` (
`ehr_username` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`redcap_userid` int(11) DEFAULT NULL,
UNIQUE KEY `ehr_username` (`ehr_username`),
UNIQUE KEY `redcap_userid` (`redcap_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_ehr_user_map`
ADD FOREIGN KEY (`redcap_userid`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;