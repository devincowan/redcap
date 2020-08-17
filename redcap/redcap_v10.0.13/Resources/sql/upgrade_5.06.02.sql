-- Table structure for table 'redcap_web_service_mapping'
DROP TABLE IF EXISTS `redcap_web_service_mapping`;
CREATE TABLE `redcap_web_service_mapping` (
  `map_id` int(10) NOT NULL AUTO_INCREMENT,
  `external_source_field_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Unique name of field mapped from external data source',
  `is_record_identifier` int(1) DEFAULT NULL COMMENT '1=Yes, Null=No',
  `project_id` int(10) DEFAULT NULL,
  `event_id` int(10) DEFAULT NULL,
  `field_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `temporal_field` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'REDCap date field',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `project_identifier` (`project_id`,`is_record_identifier`),
  UNIQUE KEY `project_field_event_source` (`project_id`,`event_id`,`field_name`,`external_source_field_name`),
  KEY `project_id` (`project_id`),
  KEY `field_name` (`field_name`),
  KEY `event_id` (`event_id`),
  KEY `external_source_field_name` (`external_source_field_name`),
  KEY `project_field_event` (`project_id`,`field_name`,`event_id`),
  KEY `temporal_field` (`temporal_field`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping`
  ADD CONSTRAINT `redcap_web_service_mapping_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `redcap_web_service_mapping_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
