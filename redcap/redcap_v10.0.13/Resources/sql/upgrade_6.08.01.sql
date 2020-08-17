-- Add ability to exempt projects from auto-calcs
ALTER TABLE `redcap_projects` ADD `disable_autocalcs` TINYINT(1) NOT NULL DEFAULT '0' ;