-- Add new fields for Survey Login functionality
ALTER TABLE  `redcap_projects`
	ADD  `survey_auth_field1` VARCHAR( 100 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_event_id1` INT( 10 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_field2` VARCHAR( 100 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_event_id2` INT( 10 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_field3` VARCHAR( 100 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_event_id3` INT( 10 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_min_fields` ENUM(  '1',  '2',  '3' ) NULL,
	ADD  `survey_auth_apply_all_surveys` INT( 1 ) NOT NULL DEFAULT  '1',
	ADD  `survey_auth_custom_message` TEXT NULL DEFAULT NULL,
	ADD  `survey_auth_fail_limit` INT( 2 ) NULL DEFAULT NULL ,
	ADD  `survey_auth_fail_window` INT( 3 ) NULL DEFAULT NULL,
	ADD INDEX ( `survey_auth_event_id1` ),
	ADD INDEX ( `survey_auth_event_id2` ),
	ADD INDEX ( `survey_auth_event_id3` ),
	ADD FOREIGN KEY (  `survey_auth_event_id1` ) REFERENCES  `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
	ADD FOREIGN KEY (  `survey_auth_event_id2` ) REFERENCES  `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
	ADD FOREIGN KEY (  `survey_auth_event_id3` ) REFERENCES  `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE  `redcap_surveys`
	ADD  `display_page_number` INT( 1 ) NOT NULL DEFAULT  '1' AFTER  `question_by_section`,
	ADD  `edit_completed_response` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Allow respondents to return and edit a completed response?';