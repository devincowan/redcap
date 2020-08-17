-- Add new project-level field
ALTER TABLE `redcap_projects` ADD `custom_public_survey_links` TEXT NULL DEFAULT NULL AFTER `disable_autocalcs`;