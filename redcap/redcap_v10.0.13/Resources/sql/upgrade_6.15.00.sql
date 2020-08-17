-- Add enhanced survey radios and checkboxes
ALTER TABLE `redcap_surveys` ADD `enhanced_choices` SMALLINT(1) NOT NULL DEFAULT '0' AFTER `theme_bg_question`;