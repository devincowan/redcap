-- Modify project folders tables
ALTER TABLE `redcap_folders` CHANGE `name` `name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
RENAME TABLE `redcap_folder_projects` TO `redcap_folders_projects`;
-- Add survey themes functionality
ALTER TABLE `redcap_surveys`
	ADD `theme_text_buttons` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_bg_page` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_text_title` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_bg_title` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_text_sectionheader` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_bg_sectionheader` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_text_question` VARCHAR(6) NULL DEFAULT NULL ,
	ADD `theme_bg_question` VARCHAR(6) NULL DEFAULT NULL ;
CREATE TABLE `redcap_surveys_themes` (
`theme_id` int(10) NOT NULL AUTO_INCREMENT,
`theme_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`ui_id` int(10) DEFAULT NULL,
`theme_text_buttons` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_bg_page` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_text_title` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_bg_title` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_text_sectionheader` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_bg_sectionheader` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_text_question` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`theme_bg_question` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`theme_id`),
KEY `ui_id` (`ui_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_surveys_themes`
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_surveys_themes` CHANGE `ui_id` `ui_id` INT(10) NULL DEFAULT NULL COMMENT 'NULL = Theme is available to all users';
ALTER TABLE `redcap_surveys_themes` ADD INDEX(`theme_name`);
-- See themes table with some themes
INSERT INTO redcap_surveys_themes (theme_name, ui_id, theme_text_buttons, theme_bg_page, theme_text_title, theme_bg_title, theme_text_sectionheader, theme_bg_sectionheader, theme_text_question, theme_bg_question) VALUES
('Flat White', NULL, '000000', 'eeeeee', '000000', 'FFFFFF', 'FFFFFF', '444444', '000000', 'FFFFFF'),
('Slate and Khaki', NULL, '000000', 'EBE8D9', '000000', 'c5d5cb', 'FFFFFF', '909A94', '000000', 'f3f3f3'),
('Colorful Pastel', NULL, '000', 'f1fafc', '274e13', 'e9f1e3', '660000', 'F6C2C2', '660000', 'f7f8d7'),
('Blue Skies', NULL, '0C74A9', 'cfe2f3', '0b5394', 'FFFFFF', 'FFFFFF', '0b5394', '0b5394', 'ffffff'),
('Cappucino', NULL, '7d4627', '783f04', '7d4627', 'fff', 'FFFFFF', 'b18b64', '783f04', 'fce5cd'),
('Red Brick', NULL, '000000', '660000', 'ffffff', '990000', 'ffffff', '000000', '000000', 'ffffff'),
('Grayscale', NULL, '30231d', '000000', 'ffffff', '666666', 'ffffff', '444444', '000000', 'eeeeee'),
('Plum', NULL, '000000', '351c75', '000000', 'd9d2e9', 'FFFFFF', '8e7cc3', '000000', 'd9d2e9'),
('Forest Green', NULL, '7f6000', '274e13', 'ffffff', '6aa84f', 'ffffff', '38761d', '7f6000', 'd9ead3'),
('Sunny Day', NULL, 'B2400E', 'FFFF80', 'B2400E', 'FFFFFF', 'FFFFFF', 'f67719', 'b85b16', 'FEFFD3');
update redcap_surveys set theme = null;
ALTER TABLE `redcap_surveys`
	CHANGE `theme` `theme` INT(10) NULL DEFAULT NULL,
	ADD INDEX(`theme`);
ALTER TABLE `redcap_surveys` ADD  FOREIGN KEY (`theme`) REFERENCES `redcap_surveys_themes`(`theme_id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- Add tracking for instrument zips external libraries
CREATE TABLE `redcap_instrument_zip` (
`iza_id` int(10) NOT NULL DEFAULT '0',
`instrument_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
`upload_count` smallint(5) NOT NULL DEFAULT '1',
PRIMARY KEY (`iza_id`,`instrument_id`),
KEY `instrument_id` (`instrument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `redcap_instrument_zip_authors` (
`iza_id` int(10) NOT NULL AUTO_INCREMENT,
`author_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`iza_id`),
UNIQUE KEY `author_name` (`author_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `redcap_instrument_zip_origins` (
`server_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
`upload_count` smallint(5) NOT NULL DEFAULT '1',
PRIMARY KEY (`server_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_instrument_zip`
ADD FOREIGN KEY (`iza_id`) REFERENCES `redcap_instrument_zip_authors` (`iza_id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- Fix survey-completion bug
update redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r, redcap_data d
SET d.value = '0' WHERE s.edit_completed_response = 1 and s.survey_id = p.survey_id and p.participant_id = r.participant_id
and r.first_submit_time > '2014-09-01 00:00:00' and r.completion_time is null and s.project_id = d.project_id
and d.field_name = concat(s.form_name, '_complete') and d.record = r.record and d.value = '2'
and (select r1.completion_time from redcap_surveys_participants p1, redcap_surveys_response r1
where p1.participant_id = r1.participant_id and p1.survey_id = s.survey_id and p1.event_id = p.event_id
and r1.record = r.record and r1.completion_time is not null limit 1) is null;