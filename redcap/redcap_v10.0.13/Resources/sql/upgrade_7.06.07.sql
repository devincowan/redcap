INSERT INTO redcap_config (field_name, value) VALUES
('external_modules_project_custom_text', '');
INSERT INTO redcap_config (field_name, value) VALUES
('is_development_server', '0');
set @aafAccessUrl = (select value from redcap_config where field_name = 'aafAccessUrl' limit 1);
insert into redcap_config values ('aafAccessUrl', '') on duplicate key update value = @aafAccessUrl;
set @aafAud = (select value from redcap_config where field_name = 'aafAud' limit 1);
insert into redcap_config values ('aafAud', '') on duplicate key update value = @aafAud;
set @aafIss = (select value from redcap_config where field_name = 'aafIss' limit 1);
insert into redcap_config values ('aafIss', '') on duplicate key update value = @aafIss;
set @aafScopeTarget = (select value from redcap_config where field_name = 'aafScopeTarget' limit 1);
insert into redcap_config values ('aafScopeTarget', '') on duplicate key update value = @aafScopeTarget;
set @aafAllowLocalsCreateDB = (select value from redcap_config where field_name = 'aafAllowLocalsCreateDB' limit 1);
insert into redcap_config values ('aafAllowLocalsCreateDB', '') on duplicate key update value = @aafAllowLocalsCreateDB;
set @aafDisplayOnEmailUsers = (select value from redcap_config where field_name = 'aafDisplayOnEmailUsers' limit 1);
insert into redcap_config values ('aafDisplayOnEmailUsers', '') on duplicate key update value = @aafDisplayOnEmailUsers;
set @aafPrimaryField = (select value from redcap_config where field_name = 'aafPrimaryField' limit 1);
insert into redcap_config values ('aafPrimaryField', '') on duplicate key update value = @aafPrimaryField;
ALTER TABLE `redcap_user_rights` ADD `external_module_config` INT(1) NOT NULL DEFAULT '0' AFTER `realtime_webservice_adjudicate`;
ALTER TABLE `redcap_user_roles` ADD `external_module_config` INT(1) NOT NULL DEFAULT '0' AFTER `realtime_webservice_adjudicate`;