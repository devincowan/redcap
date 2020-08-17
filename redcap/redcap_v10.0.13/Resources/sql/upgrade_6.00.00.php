<?php

/**
 * Rework reports into
 */
print "-- Clear out reports table (just in case)\nDELETE FROM redcap_reports;\nALTER TABLE redcap_reports AUTO_INCREMENT = 1;\n\n";
// Set operator array
$operators = array('E'=>'=', 'NE'=>'!=', 'LT'=>'<', 'LTE'=>'<=', 'GT'=>'>', 'GTE'=>'>=');
// Set max inserts per group
$max_inserts = 100;
// Convert current reports into new table format
$sql = "select project_id, report_builder from redcap_projects
		where report_builder is not null and trim(report_builder) != ''
		order by project_id";
$q = db_query($sql);
if ($q && db_num_rows($q) > 0)
{
	$report_number_all = 1;
	while ($row = db_fetch_assoc($q))
	{
		// Initialize array
		$query_array = array();
		// Set new $query_array values by eval'ing report_builder
		eval(trim($row['report_builder']));
		$project_id = $row['project_id'];
		$report_number = 1;
		$report_count = count($query_array);
		if ($report_count == 0) continue;
	// print "-- MEMORY: ".round((memory_get_usage()/1024/1024),1)." MB, PEAK: ".round((memory_get_peak_usage()/1024/1024),1)." MB\n";
		print "-- PID{$project_id} ($report_count reports)\n";
		// Loop through each report
		foreach ($query_array as &$this_report)
		{
			// Set report values
			$title = trim(label_decode($this_report['__TITLE__']));
			$orderby1 = ""; $asc1 = ""; $orderby2 = ""; $asc2 = "";
			if (isset($this_report['__ORDERBY1__'])) {
				list ($orderby1, $asc1) = explode(" ", trim($this_report['__ORDERBY1__']));
			}
			if (isset($this_report['__ORDERBY2__'])) {
				list ($orderby2, $asc2) = explode(" ", trim($this_report['__ORDERBY2__']));
			}
			// Insert into table
			print "-- ID".($report_number_all++)." ($report_number/$report_count)\n";
			print "insert into redcap_reports (project_id,title,report_order,orderby_field1,orderby_sort1, "
				. "orderby_field2,orderby_sort2,user_access) values ($project_id,'".db_escape($title)."',".($report_number++)
				. ",".checkNull($orderby1).",".checkNull($asc1).",".checkNull($orderby2).",".checkNull($asc2).",'SELECTED');\n";
			print "set @r=LAST_INSERT_ID();\n";
			print "insert into redcap_reports_access_users select @r,u.username from redcap_user_rights u left join redcap_user_roles r on r.role_id=u.role_id where u.project_id=$project_id and ((u.reports=1 and r.reports is null) or r.reports=1);\n";
			// Now remove title and orderby from array so only fields are left
			unset($this_report['__TITLE__'], $this_report['__ORDERBY1__'], $this_report['__ORDERBY2__']);
			// Loop through fields and their limiters
			$report_fields = $report_limiters = array();
			$report_field_order = 1;
			foreach ($this_report as $field=>$raw_limiter)
			{
				$limiter = $raw_limiter = trim(html_entity_decode($raw_limiter, ENT_QUOTES));
				// Validate limiter (in case of tampering)
				$positionOperator = strlen($field)+1;
				$removedLimiter = false;
				// If value is blank
				if ($limiter != "" && $raw_limiter == "$field != ''") {
					$removedLimiter = false;
				} else {
					if ($limiter != "" && substr($limiter, 0, $positionOperator) != "$field ") {
						$limiter = "";
						$removedLimiter = true;
					}
					// Validate via regex
					if ($limiter != "") {
						$regex = "/^($field )(\>|\<|=|!=|\>=|\<=)( ')(.+)(')$/";
						if (!preg_match($regex, $limiter)) {
							$limiter = "";
							$removedLimiter = true;
						}
					}
				}
				// Set default values
				$limiterOperator = "";
				$limiterValue = "";
				if ($removedLimiter) {
					// Give note about any limiters that were removed
					print "-- NOTE - The following limiter was removed because of invalid format: $raw_limiter\n";
				} elseif ($limiter != "") {
					// Parse the limiter into components (remove any apostrophes around the value)
					list ($field_again, $limiterOperator, $limiterValue) = explode(' ', str_replace("'", "", $limiter), 3);
					// Convert operator to back-end value
					$limiterOperatorBackend = array_search($limiterOperator, $operators);
					if ($limiterOperatorBackend === false) $limiterOperatorBackend = 'E'; // Default to E (=) on error
					// Add limiter to array
					$report_limiters[$field] = array('limiter_group_operator'=>'AND', 'limiter_operator'=>$limiterOperatorBackend, 'limiter_value'=>$limiterValue);
				}
				// Insert into table
				$report_fields[] = $field;
			}
			// Add fields
			$report_fields_count = count($report_fields);
			$full = true;
			$field_num = 1;
			foreach ($report_fields as $field) {
				$end = ($field_num%$max_inserts == 0);
				if ($full) print "insert into redcap_reports_fields (report_id,field_name,field_order) values ";
				print "(@r,'".db_escape(trim($field))."',".($report_field_order++).")";
				print ($field_num == $report_fields_count || $end) ? ";\n" : ",";
				$full = ($end);
				$field_num++;
			}
			// Add limiters
			$report_fields_count = count($report_limiters);
			$full = true;
			$field_num = 1;
			foreach ($report_limiters as $field=>$attr) {
				$end = ($field_num%$max_inserts == 0);
				if ($full) print "insert into redcap_reports_fields (report_id,field_name,field_order,limiter_group_operator,limiter_operator,limiter_value) values ";
				print "(@r,'".db_escape(trim($field))."',".($report_field_order++).",'".db_escape($attr['limiter_group_operator'])."','".db_escape($attr['limiter_operator'])."','".db_escape($attr['limiter_value'])."')";
				print ($field_num == $report_fields_count || $end) ? ";\n" : ",";
				$full = ($end);
				$field_num++;
			}
		}
		print "\n";
	}
}

print "-- Add new config options
INSERT INTO redcap_config (field_name, value) VALUES ('from_email', '');
INSERT INTO redcap_config (field_name, value) VALUES ('field_comment_log_enabled_default', '1');
";
