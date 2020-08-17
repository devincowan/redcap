-- Add new config setting and rename cron
INSERT INTO redcap_config (field_name, value) VALUES ('allow_kill_mysql_process', '0');
update redcap_crons set cron_name = 'ClearLogViewRequests',
	cron_description = 'Clear all items from redcap_log_view_requests table older than X hours.'
	where cron_name = 'ClearLogOpenRequests';
-- Modify new logging table
delete from redcap_log_open_requests;
RENAME TABLE `redcap_log_open_requests` TO `redcap_log_view_requests` ;
ALTER TABLE  `redcap_log_view_requests`
	ADD  `script_execution_time` FLOAT NULL DEFAULT NULL COMMENT  'Total PHP script execution time (seconds)',
	ADD  `is_ajax` TINYINT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Is request an AJAX request?',
	CHANGE  `log_view_id`  `log_view_id` INT( 10 ) NOT NULL DEFAULT  '0' COMMENT  'FK from redcap_log_view';
ALTER TABLE  `redcap_log_view_requests`
	CHANGE  `mysql_process_id`  `mysql_process_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'Process ID for MySQL',
	CHANGE  `php_process_id`  `php_process_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'Process ID for PHP',
	DROP PRIMARY KEY ,
	ADD UNIQUE  `log_view_id` (  `log_view_id` ),
	CHANGE  `log_view_id`  `log_view_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'FK from redcap_log_view';
ALTER TABLE  `redcap_log_view_requests` ADD  `lvr_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
ALTER TABLE  `redcap_log_view_requests` ADD UNIQUE  `log_view_id_time` (  `log_view_id` ,  `script_execution_time` );
-- Add new field and new index for performance improvements
ALTER TABLE  `redcap_surveys_response`
	ADD INDEX `record_participant` (  `record` ,  `participant_id` ),
	ADD `start_time` DATETIME NULL DEFAULT NULL AFTER `record`,
	ADD INDEX (`start_time`) ;