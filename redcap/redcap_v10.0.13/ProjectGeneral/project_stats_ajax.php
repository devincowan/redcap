<?php

// Default
$response = "0";




## If calling this file for a project, return the document space usage, number of records, and most recent logged event
if (isset($_GET['pid']) && is_numeric($_GET['pid']))
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
	
	// Count file upload usage (edocs and docs, including data export saves)
	$sql = "select round(sum(doc_size)/1024/1024,2) from redcap_edocs_metadata where stored_date > '$creation_time'
			and delete_date is null and project_id = " . PROJECT_ID;
	$edoc_usage = db_result(db_query($sql),0);
	if ($edoc_usage == '') $edoc_usage = 0;
	$sql = "select round(sum(docs_size)/1024/1024,2) from redcap_docs where docs_date > '".substr($creation_time, 0, 10)."'
			and docs_file is not null and project_id = " . PROJECT_ID;
	$doc_usage  = db_result(db_query($sql),0);
	if ($doc_usage == '') $doc_usage = 0;
	$file_space_usage = User::number_format_user($edoc_usage + $doc_usage, 2);
	
	// Get most recent logged event
	if ($last_logged_event == "") {
		// If not set yet, then get it and set it now
		$sql = "select timestamp(ts) from ".Logging::getLogEventTable(PROJECT_ID)."
				where ts > ".str_replace(array("-"," ",":"), array("","",""), $creation_time)."
				and project_id = " . PROJECT_ID . " order by log_event_id desc limit 1";
		$most_recent_event = db_result(db_query($sql), 0);
		if ($most_recent_event != "") {
			$sql = "update redcap_projects set last_logged_event = '".db_escape($most_recent_event)."' where project_id = " . PROJECT_ID;
			db_query($sql);
		}
	} else {
		$most_recent_event = $last_logged_event;
	}
	if ($most_recent_event != "") $most_recent_event = DateTimeRC::format_user_datetime($most_recent_event, 'Y-M-D_24');
	
	// Count project records
	$num_records = User::number_format_user(Records::getRecordCount(PROJECT_ID));
	
	// Get extra record count in user's data access group, if they are in one
	if ($user_rights['group_id'] != "")
	{
		$sql  = "select count(distinct(record)) from redcap_data where project_id = " . PROJECT_ID . " and field_name = '$table_pk'"
			  . " and record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])) . ")";
		$num_records_group = db_result(db_query($sql),0);
		$num_records = "{$lang['data_entry_103']} $num_records / {$lang['data_entry_104']} ".User::number_format_user($num_records_group);
	}
	
	// Send response delimited with line breaks
	$response = json_encode(array($num_records, $most_recent_event, "$file_space_usage MB"));
}




## If calling this file on the My Projects page (or equivalent page in Control Center),
## then return the number of records and fields for all projects requested
elseif (isset($_POST['pids']))
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Parse and validate the project_id's sent
	$pids = explode(",", $_POST['pids']);
	// Make sure uiid's are numeric first
	foreach ($pids as $key=>$this_pid)
	{
		if (!is_numeric($this_pid)) {
			// Remove pid from original if not numeric
			unset($pids[$key]);
		}
	}
	// If user is not a super user, then make sure they have access to all projects for the project_id's given (for security reasons)
	if (!$super_user)
	{
		$sql = "select project_id from redcap_user_rights where username = '" . db_escape($userid) . "'
				and project_id in (" . prep_implode($pids) . ")";
		$q = db_query($sql);
		// Reset $pids and re-fill with valid project_id's
		$pids = array();
		while ($row = db_fetch_assoc($q))
		{
			$pids[] = $row['project_id'];
		}
	}
	// If there are no project_ids to report, then return failure value
	if (empty($pids)) exit("0");

	// Initialize arrays/vars
	$pid_counts = array();
	$next_pids = '';

	## RECORD COUNT
	if ($_POST['type'] == 'records')
	{
		// Set value of max number of pids to process per ajax request (to scale the record counts better)
		$max_pids_per_batch = 50;
		// Split the pids into batches. Set aside some for next batch.
		$next_pids = implode(',', array_splice($pids, $max_pids_per_batch));
		// Loop through pids and set default value to 0
		foreach ($pids as $this_pid) {
			$pid_counts[$this_pid]['r'] = 0;
		}
		// First, get counts from the record count cache table
		$cached_pids = array();
		$sql = "select project_id, record_count from redcap_record_counts
				where project_id in (" . prep_implode($pids) . ") order by project_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$cached_pids[] = $row['project_id'];
			$pid_counts[$row['project_id']]['r'] = User::number_format_user($row['record_count']);
			// Remove pid from $pids array
			$pidkey = array_search($row['project_id'], $pids);
			unset($pids[$pidkey]);
		}
		// Now get record count for each project (calculated manually for those not in the cache table)
		if (!empty($pids))
		{
			$sql = "select m.project_id, count(distinct(d.record)) as count from redcap_metadata m
					left join redcap_data d on d.project_id = m.project_id and m.field_name = d.field_name
					where m.field_order = 1 and m.project_id in (" . prep_implode($pids) . ")
					group by m.project_id order by m.project_id";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$pid_counts[$row['project_id']]['r'] = User::number_format_user($row['count']);
				// Add this count to the cache table to retrieve it faster next time
				db_query("replace into redcap_record_counts (project_id, record_count, time_of_count) 
					  			values ({$row['project_id']}, {$row['count']}, '" . NOW . "')");
			}
		}
	}
	## FIELD/INSTRUMENT COUNT
	elseif ($_POST['type'] == 'fields')
	{
		// Loop through pids and set default values to 0
		foreach ($pids as $this_pid) {
			$pid_counts[$this_pid] = array('f'=>0, 'fo'=>0, 's'=>0);
		}
		// Get count of fields, instruments, and surveys for each project
		$sql = "select p.project_id, count(m.field_name) as num_fields, count(distinct(m.form_name)) as num_instruments,
				if(p.surveys_enabled = 0, 0, count(distinct(s.survey_id))) as num_surveys
				from redcap_projects p, redcap_metadata m left outer join redcap_surveys s
				on m.project_id = s.project_id and m.form_name = s.form_name
				where p.project_id in (" . prep_implode($pids) . ") and m.project_id = p.project_id
				group by m.project_id order by m.project_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$pid_counts[$row['project_id']]['f'] = User::number_format_user($row['num_fields']);
			$pid_counts[$row['project_id']]['fo'] = User::number_format_user($row['num_instruments']-$row['num_surveys']);
			$pid_counts[$row['project_id']]['s'] = User::number_format_user($row['num_surveys']);
		}
		// For "fields/instruments" counts, reformat it for display purposes
		foreach ($pid_counts as $this_pid=>$attr)
		{
			// Set text label for "form(s)" and "survey(s)"
			$langForm   = ($pid_counts[$this_pid]['fo'] == 1) ? $lang['global_112'] : $lang['global_113'];
			$langSurvey = ($pid_counts[$this_pid]['s'] == 1)  ? $lang['global_59'] : $lang['global_111'];
			// Set HTML for instrument column
			$pid_counts[$this_pid]['i'] = RCView::div(array('class'=>'pid-cnt-i'),
											($pid_counts[$this_pid]['fo'] == 0 ? '' : $pid_counts[$this_pid]['fo'] . " " . RCView::span('', $langForm) . "<br>") .
											($pid_counts[$this_pid]['s']  == 0 ? '' : $pid_counts[$this_pid]['s']  . " " . RCView::span('', $langSurvey))
										  );
			// Remove separate form/surveys values
			unset($pid_counts[$this_pid]['fo'], $pid_counts[$this_pid]['s']);
		}
	}

	// If we have some counts to return
	if (!empty($pid_counts))
	{
		// Add next_pids counts
		$pid_counts['next_pids'] = $next_pids;
		// Output JSON response
		$response = json_encode($pid_counts);
	}
}




// Return the response
print $response;
