-- Add new table for upcoming web service auto suggest feature
CREATE TABLE `redcap_web_service_cache` (
`cache_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`service` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`category` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`value` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
`label` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`cache_id`),
UNIQUE KEY `project_service_cat_value` (`project_id`,`service`,`category`,`value`),
KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_cache`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- Add new config setting
INSERT INTO redcap_config (field_name, value) VALUES ('enable_ontology_auto_suggest', '0');
INSERT INTO redcap_config (field_name, value) VALUES ('bioportal_api_token', '');