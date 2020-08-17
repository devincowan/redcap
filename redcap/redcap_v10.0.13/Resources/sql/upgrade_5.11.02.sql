-- Remove some project-level levels if they match system-level values
set @project_contact_name = (select value from redcap_config where field_name = 'project_contact_name' limit 1);
set @project_contact_email = (select value from redcap_config where field_name = 'project_contact_email' limit 1);
set @institution = (select value from redcap_config where field_name = 'institution' limit 1);
set @site_org_type = (select value from redcap_config where field_name = 'site_org_type' limit 1);
set @grant_cite = (select value from redcap_config where field_name = 'grant_cite' limit 1);
set @headerlogo = (select value from redcap_config where field_name = 'headerlogo' limit 1);
update redcap_projects set project_contact_name = '' where project_contact_name = @project_contact_name and project_contact_name != '';
update redcap_projects set project_contact_email = '' where project_contact_email = @project_contact_email and project_contact_email != '';
update redcap_projects set institution = '' where institution = @institution and institution != '';
update redcap_projects set site_org_type = '' where site_org_type = @site_org_type and site_org_type != '';
update redcap_projects set grant_cite = '' where grant_cite = @grant_cite and grant_cite != '';
update redcap_projects set headerlogo = '' where headerlogo = @headerlogo and headerlogo != '';
