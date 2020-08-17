ALTER TABLE `redcap_record_counts` 
	ADD `record_list_status` ENUM('NOT_STARTED','PROCESSING','COMPLETE') 
	NOT NULL DEFAULT 'NOT_STARTED' AFTER `time_of_count`;
ALTER TABLE `redcap_reports` 
	ADD `description` TEXT NULL DEFAULT NULL AFTER `user_access`, 
	ADD `combine_checkbox_values` TINYINT(1) NOT NULL DEFAULT '0' AFTER `description`;