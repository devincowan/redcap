<?php

// Fix missing row in redcap_data for non-first events of longitudinal projects affected in v6.2.4 only
if ($current_version == '6.2.4')
{
	// Get date when 6.2.4 was installed
	global $redcap_last_install_date;
	$upgradeTs = str_replace("-", "", $redcap_last_install_date);
	if ($upgradeTs == "" || $upgradeTs < 20141205) $upgradeTs = "20141205"; // 6.2.4 was released on Dec 5th (min date to check)
	$upgradeTs .= "000000"; // Append time to date
	// Query to add missing rows
	print "-- Fix missing rows in redcap_data
insert into redcap_data select distinct p.project_id, l.event_id, l.pk, m.field_name, l.pk
from (redcap_log_event l, redcap_projects p, redcap_metadata m)
left join redcap_data d on d.project_id = m.project_id and d.field_name = m.field_name and l.pk = d.record and l.event_id = d.event_id
left join redcap_data d2 on d2.project_id = m.project_id and l.pk = d2.record
where l.ts > $upgradeTs and l.event = 'INSERT' and l.object_type = 'redcap_data' and p.project_id = l.project_id
and p.repeatforms = 1 and l.description = 'Create record' and l.page = 'DataEntry/index.php' and l.event_id is not null
and m.project_id = p.project_id and m.field_order = 1 and d.project_id is null and d2.record is not null;
";
}