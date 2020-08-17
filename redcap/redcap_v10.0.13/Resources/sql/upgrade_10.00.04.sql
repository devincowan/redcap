-- Add new index to prevent issues with Auto-Fix
ALTER TABLE `redcap_new_record_cache` ADD INDEX(`project_id`);