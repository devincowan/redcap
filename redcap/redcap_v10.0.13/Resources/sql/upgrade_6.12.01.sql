-- Placeholders for new report features
ALTER TABLE `redcap_reports`
	ADD `dynamic_filter1` VARCHAR(255) NULL DEFAULT NULL AFTER `filter_type`,
	ADD `dynamic_filter2` VARCHAR(255) NULL DEFAULT NULL AFTER `dynamic_filter1`,
	ADD `dynamic_filter3` VARCHAR(255) NULL DEFAULT NULL AFTER `dynamic_filter2`;