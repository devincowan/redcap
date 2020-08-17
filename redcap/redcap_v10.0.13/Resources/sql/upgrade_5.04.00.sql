-- Set all projects to have Field Comment Log enabled
update redcap_projects set data_resolution_enabled = 1;
-- Remove table no longer needed in v5.0.0+
drop table redcap_migration_script;
