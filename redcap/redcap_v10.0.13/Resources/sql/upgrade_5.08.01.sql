-- Set config values and upcoming feature
insert into redcap_config values ('default_datetime_format', 'M/D/Y_12');
ALTER TABLE  `redcap_user_information` ADD  `datetime_format` ENUM(  'M-D-Y_24',  'M-D-Y_12',  'M/D/Y_24',  'M/D/Y_12',
	'M.D.Y_24',  'M.D.Y_12',  'D-M-Y_24',  'D-M-Y_12',  'D/M/Y_24',  'D/M/Y_12',  'D.M.Y_24', 'D.M.Y_12',  'Y-M-D_24',
	'Y-M-D_12',  'Y/M/D_24',  'Y/M/D_12',  'Y.M.D_24',  'Y.M.D_12' ) NOT NULL DEFAULT  'M/D/Y_12'
	COMMENT  'User''s preferred datetime viewing format';
-- Add new DDP column
ALTER TABLE  `redcap_ddp_records` ADD  `future_date_count` INT( 10 )
	NOT NULL DEFAULT  '0' COMMENT  'Count of datetime reference fields with values in the future';