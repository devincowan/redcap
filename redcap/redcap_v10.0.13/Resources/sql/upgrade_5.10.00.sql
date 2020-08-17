-- Add tables for Survey Queue functionality
ALTER TABLE  `redcap_projects` ADD  `survey_queue_custom_text` TEXT NULL DEFAULT NULL;
CREATE TABLE `redcap_surveys_queue` (
  `sq_id` int(10) NOT NULL AUTO_INCREMENT,
  `survey_id` int(10) DEFAULT NULL,
  `event_id` int(10) DEFAULT NULL,
  `active` int(1) NOT NULL DEFAULT '1' COMMENT 'Is it currently active?',
  `auto_start` int(1) NOT NULL DEFAULT '0' COMMENT 'Automatically start if next after survey completion',
  `condition_surveycomplete_survey_id` int(10) DEFAULT NULL COMMENT 'survey_id of trigger',
  `condition_surveycomplete_event_id` int(10) DEFAULT NULL COMMENT 'event_id of trigger',
  `condition_andor` enum('AND','OR') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Include survey complete AND/OR logic',
  `condition_logic` text COLLATE utf8_unicode_ci COMMENT 'Logic using field values',
  PRIMARY KEY (`sq_id`),
  UNIQUE KEY `survey_event` (`survey_id`,`event_id`),
  KEY `event_id` (`event_id`),
  KEY `condition_surveycomplete_event_id` (`condition_surveycomplete_event_id`),
  KEY `condition_surveycomplete_survey_event` (`condition_surveycomplete_survey_id`,`condition_surveycomplete_event_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `redcap_surveys_queue_hashes` (
  `project_id` int(10) NOT NULL DEFAULT '0',
  `record` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hash` varchar(10) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  PRIMARY KEY (`project_id`,`record`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_surveys_queue`
  ADD CONSTRAINT `redcap_surveys_queue_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `redcap_surveys` (`survey_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `redcap_surveys_queue_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `redcap_surveys_queue_ibfk_3` FOREIGN KEY (`condition_surveycomplete_survey_id`) REFERENCES `redcap_surveys` (`survey_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `redcap_surveys_queue_ibfk_4` FOREIGN KEY (`condition_surveycomplete_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `redcap_surveys_queue_hashes`
  ADD CONSTRAINT `redcap_surveys_queue_hashes_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
