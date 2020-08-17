-- Fix mistaken timestamp if user has a user_firstactivity that occurs after their suspended time
update redcap_user_information x, (SELECT i.username, timestamp(max(e.ts)) as user_firstactivity
	FROM redcap_user_information i, redcap_log_event e WHERE i.user_suspended_time is not null and i.user_firstactivity is not null
	and i.user_suspended_time < i.user_firstactivity and e.user = i.username and e.ts < i.user_suspended_time*1 group by i.username) y
	set x.user_firstactivity = y.user_firstactivity where x.username = y.username;
-- Change column for user_rights for upcoming feature
ALTER TABLE  `redcap_user_rights` CHANGE  `data_quality_resolution`  `data_quality_resolution` INT( 1 )
	NOT NULL DEFAULT  '0' COMMENT  '0=No access, 1=View only, 2=Respond, 3=Open, close, respond';
ALTER TABLE  `redcap_projects` CHANGE  `data_resolution_enabled`  `data_resolution_enabled` INT( 1 )
	NOT NULL DEFAULT  '1' COMMENT  '0=Disabled, 1=Field comment log, 2=Data Quality resolution workflow';
-- Set all projects to disabled for data_resolution_enabled
update redcap_projects set data_resolution_enabled = 0;
update redcap_user_rights set data_quality_resolution = 0;