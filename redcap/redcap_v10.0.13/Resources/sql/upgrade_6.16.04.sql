ALTER TABLE `redcap_user_information` ADD `super_user_administration` INT(1) NOT NULL DEFAULT '0' AFTER `super_user`;
ALTER TABLE `redcap_events_metadata` ADD `custom_event_label` TEXT NULL DEFAULT NULL AFTER `external_id`;
ALTER TABLE `redcap_events_repeat` ADD `custom_repeat_form_label` TEXT NULL DEFAULT NULL AFTER `form_name`;