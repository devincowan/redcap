<?php


// Config for non-project pages
require dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

// Must be accessed via AJAX
if (!$isAjax) exit("ERROR!");

// Instantiate Stats object
$Stats = new Stats();
// Get logging tables
$log_event_tables = Logging::getLogEventTables();

// If loading graphs
if (isset($_GET['plottime'])) {

	// Past week
	if ($_GET['plottime'] == "1w" || $_GET['plottime'] == "") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-7,date("Y")));
		$day_span = 7;
	// Past day
	} elseif ($_GET['plottime'] == "1d") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
		$day_span = 1;
	// Past month
	} elseif ($_GET['plottime'] == "1m") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m")-1,date("d"),date("Y")));
		$day_span = 30;
	// Past three months
	} elseif ($_GET['plottime'] == "3m") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m")-3,date("d"),date("Y")));
		$day_span = 90;
	// Past six months
	} elseif ($_GET['plottime'] == "6m") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m")-6,date("d"),date("Y")));
		$day_span = 180;
	// Past year
	} elseif ($_GET['plottime'] == "12m") {
		$date_limit = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y")-1));
		$day_span = 365;
	// All
	} elseif ($_GET['plottime'] == "all") {
		$date_limit = "2004-01-01 00:00:00";
		$day_span = 4000;
	}

	// Is the "date" in date format (YYYY-MM-DD)?
	$isDateFormat = true; //default
	// Should the stats be added to be viewed as cumulative?
	$isCumulative = true; //default

	// Page Hits
	if ($_GET['chartid'] == "chart1") {
		$sql = "select sum(page_hits) from redcap_page_hits where date <= '".substr($date_limit, 0, 10)."'";
		$base_count = db_result(db_query($sql), 0);
		$sql = "select date as Date, sum(page_hits) as Hits from redcap_page_hits where date > '".substr($date_limit, 0, 10)."' group by date";
	// User logins
	} elseif ($_GET['chartid'] == "chart9") {
		$base_count = 0;
		$sql = "select date(ts) as Date, count(1) as Count from redcap_log_view where ts > '$date_limit'
				and event = 'LOGIN_SUCCESS' group by Date";
		$isCumulative = false;
	// Projects Created
	} elseif ($_GET['chartid'] == "chart4") {
		$sql = "SELECT count(1) FROM redcap_projects WHERE creation_time <= '$date_limit' or creation_time is null";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT date(creation_time) as Date, count(1) as Count FROM redcap_projects WHERE creation_time > '$date_limit'
				and creation_time is not null group by Date";
	// Logged Events
	} elseif ($_GET['chartid'] == "chart2") {
		$base_count = 0;
		$sql_array = array();
		foreach ($log_event_tables as $this_log_event_table) {
			$sql = "SELECT count(1) FROM $this_log_event_table WHERE ts <= " . str_replace(array("-", ":", " "), array("", "", ""), $date_limit);
			$base_count += db_result(db_query($sql), 0);
			$sql_array[] = "(SELECT concat(left(ts,4),'-',substr(ts,5,2),'-',substr(ts,7,2)) as Date, count(1) as Events
							FROM $this_log_event_table WHERE ts > ".str_replace(array("-",":"," "), array("","",""), $date_limit)." group by Date)";
		}
		$sql = "select Date, sum(Events) as Events from (".implode(" UNION ", $sql_array).") x group by Date";
	// Active Users
	} elseif ($_GET['chartid'] == "chart5") {
		$sql = "select count(1) from redcap_user_information where user_firstactivity is not null and user_firstactivity <= '$date_limit'";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT date(user_firstactivity) as Date, count(1) as Count FROM redcap_user_information
				WHERE user_firstactivity > '$date_limit' and user_firstactivity is not null group by Date";
	// First time accessing REDCap
	} elseif ($_GET['chartid'] == "chart6") {
		$sql = "select count(1) from redcap_user_information where user_firstvisit is null or user_firstvisit <= '$date_limit'";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT date(user_firstvisit) as Date, count(1) as Count FROM redcap_user_information
				WHERE user_firstvisit > '$date_limit' and user_firstvisit is not null group by Date";
	// Concurrent users within 30min blocks
	} elseif ($_GET['chartid'] == "chart7") {
		$base_count = 0;
		$sql = "SELECT left(FROM_UNIXTIME(floor(UNIX_TIMESTAMP(ts)/1800)*1800),16) as Date, count(distinct(session_id)) as Count
				from redcap_log_view where ts > '$date_limit' group by floor(UNIX_TIMESTAMP(ts)/1800) order by ts limit 1,".($day_span*48);
		$isDateFormat = false;
		$isCumulative = false;
	// Projects moved to production
	} elseif ($_GET['chartid'] == "chart8") {
		$sql = "SELECT count(1) FROM redcap_projects WHERE production_time <= '$date_limit' and production_time is not null";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT date(production_time) as Date, count(1) as Count FROM redcap_projects WHERE production_time > '$date_limit'
				and production_time is not null group by Date";
	// Space usage by db
	} elseif ($_GET['chartid'] == "chart11") {
		$sql = "SELECT floor(min(size_db)) FROM redcap_history_size WHERE `date` > '$date_limit'";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT `date` as Date, size_db as Count FROM redcap_history_size WHERE `date` > '$date_limit'";
		$isCumulative = false;
	// Space usage by uploaded files
	} elseif ($_GET['chartid'] == "chart10") {
		$sql = "SELECT floor(min(size_files)) FROM redcap_history_size WHERE `date` > '$date_limit'";
		$base_count = db_result(db_query($sql), 0);
		$sql = "SELECT `date` as Date, size_files as Count FROM redcap_history_size WHERE `date` > '$date_limit'";
		$isCumulative = false;
	} else {
		unset($_GET['chartid']);
	}

	// Render chart
	if (isset($_GET['chartid'])) {
		ControlCenter::yui_chart($_GET['chartid'],"","","",$sql,$base_count,$date_limit,$isDateFormat,$isCumulative);
	}
	exit;
}









/**
 * GET TOTAL NUMBER OF PROJECT RECORDS FOR ALL PROJECTS
 */
if (isset($_GET['total_records'])) {
	$total_records = 0;
	$q = db_query("select count(distinct(d.record)) as count from redcap_metadata m, redcap_data d,
					  redcap_projects p where p.project_id = d.project_id and d.project_id = m.project_id and m.field_name = d.field_name
					  and m.field_order = 1 group by p.project_id");
	while ($row = db_fetch_array($q)) {
		$total_records += $row['count'];
	}
	exit(User::number_format_user($total_records));
}



/**
 * GET TOTAL NUMBER OF LOGGED EVENTS
 */
if (isset($_GET['logged_events_total'])) {
	//Get total number of logged events
	$logged_events_total = 0;
	foreach ($log_event_tables as $this_log_event_table) {
		$sql = "select count(1) from $this_log_event_table";
		$logged_events_total += db_result(db_query($sql), 0);
	}
	exit(User::number_format_user($logged_events_total)."");
}
if (isset($_GET['logged_events'])) {
	## Logged Events
	$logged_events_30min = $logged_events_today = $logged_events_week = $logged_events_month = 0;
	foreach ($log_event_tables as $this_log_event_table) {
		//Get total number of logged events in last 30 minutes
		$sql = "select count(1) from $this_log_event_table where ts >= " . date("YmdHis", mktime(date("H"), date("i") - 30, date("s"), date("m"), date("d"), date("Y")));
		$logged_events_30min += db_result(db_query($sql), 0);
		//Get total number of logged events for today
		$q = db_query("SELECT count(1) as count FROM $this_log_event_table WHERE ts >= " . date("Ymd") . "000000");
		$row = db_fetch_array($q);
		$logged_events_today += $row['count'];
		//Get total number of logged events for past week
		$q = db_query("select count(1) as count from $this_log_event_table where ts >= " . date("YmdHis", mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"))));
		$row = db_fetch_array($q);
		$logged_events_week += $row['count'];
		//Get total number of logged events for past month
		$q = db_query("select count(1) as count from $this_log_event_table where ts >= " . date("YmdHis", mktime(0, 0, 0, date("m"), date("d") - 29, date("Y"))));
		$row = db_fetch_array($q);
		$logged_events_month += $row['count'];
	}
	$string = User::number_format_user($logged_events_30min) . "|" .
			  User::number_format_user($logged_events_today) . "|" .
			  User::number_format_user($logged_events_week) . "|" .
			  User::number_format_user($logged_events_month);

	exit($string);
}


/**
 * GET THE TOTAL FIELDS
 */
if (isset($_GET['total_fields'])) {
	// Get total number of fields
	$sql = "select count(1) from redcap_metadata";
	$total_fields = db_result(db_query($sql),0);
	exit(User::number_format_user($total_fields));
}


/**
 * GET SIZE OF MYSQL SERVER
 */
if (isset($_GET['mysql_space'])) {
	// Get table row counts and also total MySQL space used by REDCap
	$total_mysql_space = getDbSpaceUsage();

	/* use gigabytes if we have them, otherwise use megabytes */
	if ($total_mysql_space > (1024*1024*1024)) {
		$total_mysql_space = User::number_format_user(round($total_mysql_space/(1024*1024*1024), 1), 2) . " GB";
	} else {
		$total_mysql_space = User::number_format_user(round($total_mysql_space/(1024*1024), 1), 2) . " MB";
	}

	exit($total_mysql_space);
}



/**
 * DDP Custom: Values imported
 */
if (isset($_GET['total_ddp_values_pulled']))
{
    // Get count of projects using Dynamic Data Pull (DDP)
    $ddp_project_ids = $Stats->getDDpProjectIds();

    // Get count of all data points that have been imported from source system in DDP-enabled projects (excludes any flushed values)
    $sql = "select count(*) from (select 1 from redcap_ddp_records r, redcap_ddp_records_data d
			where r.mr_id = d.mr_id and r.project_id in (".prep_implode($ddp_project_ids).")
			group by r.project_id, r.record, d.map_id, d.source_value2) x";
    exit(User::number_format_user(db_result(db_query($sql), 0)));
}



/**
 * GET THE TOTAL OF ALL FILES STORED ON WEB SERVER
 */
if (isset($_GET['webserver_space']))
{
	// Get total web server space used
	$redcap_directory = dirname(dirname(dirname(__FILE__)));
	$total_webserver_space = dir_size($redcap_directory);
	// If storing edocs in other directory on same server
	if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
	{
		// Check if the EDOCS folder is located outside parent "redcap" folder. If so, add its size to total size.
		$parent_dir_path_forwardslash = str_replace("\\", "/", $redcap_directory);
		$edoc_path_forwardslash = str_replace("\\", "/", EDOC_PATH);
		if (substr($edoc_path_forwardslash, 0, strlen($parent_dir_path_forwardslash)) != $parent_dir_path_forwardslash)
		{
			## Use total from edocs_metadata table instead of checking EVERY file in folder
			$total_webserver_space += Files::getEdocSpaceUsage();
		}
	}

	/* use gigabytes if we have them, otherwise use megabytes */
	if ($total_webserver_space > (1024*1024*1024)) {
		$total_webserver_space = User::number_format_user(round($total_webserver_space/(1024*1024*1024), 1), 2) . " GB";
	} else {
		$total_webserver_space = User::number_format_user(round($total_webserver_space/(1024*1024), 1), 2) . " MB";
	}

	exit($total_webserver_space);
}



/**
 * GET THE NUMBER OF SURVEY PARTICIPANTS
 */
if (isset($_GET['survey_participants'])) {
	// Count total survey responses
	$q = db_query("select count(1) from redcap_surveys_participants where participant_email is not null and participant_email != ''");
	$total_survey_participants = db_result($q,0);
	exit(User::number_format_user($total_survey_participants));
}



/**
 * GET THE NUMBER OF SURVEY INVITATIONS SENT AS WELL AS THE NUMBERS FOR RESPONDED AND UNRESPONDED
 */
if (isset($_GET['survey_invitations'])) {
	// Count total survey invitations sent
	$sql = "select count(distinct(p.participant_id)) from redcap_surveys_emails_recipients r, redcap_surveys_participants p,
			redcap_surveys_emails e	where e.email_id = r.email_id and e.email_sent is not null and p.participant_id = r.participant_id";
	$q = db_query($sql);
	$total_survey_invitations_sent = db_result($q,0);

	// Count of invitations that responded
	$sql = "select count(1) from (select distinct s.participant_id, s.record from redcap_surveys_emails_recipients r, redcap_surveys_participants p,
			redcap_surveys_emails e, redcap_surveys_response s where e.email_id = r.email_id and e.email_sent is not null 
			and p.participant_id = r.participant_id and p.participant_id = s.participant_id and s.first_submit_time is not null) x";
	$total_survey_invitations_responded = db_result(db_query($sql), 0);
	// Count of invitations that have not responded
	$total_survey_invitations_unresponded = $total_survey_invitations_sent - $total_survey_invitations_responded;

	$string = User::number_format_user($total_survey_invitations_sent) . "|" .
	          User::number_format_user($total_survey_invitations_responded) . "|" .
			  User::number_format_user($total_survey_invitations_unresponded);
	exit($string);
}



/**
 * DDP-SPECIFIC COUNTS THAT MIGHT TAKE A WHILE TO LOAD
 */
// CDP
if (isset($_GET['ddp1']) && isset($_GET['fhir']))
{
	// Get count of projects using Dynamic Data Pull (DDP)
	$ddp_project_ids = $Stats->getDDpProjectIds(isset($_GET['fhir']));

	// Get count of all adjudicated data points that have been imported from source system in DDP-enabled projects
	$total_ddp_values_adjudicated = 0;
	$total_ddp_records_imported = array();
	$total_ddp_projects_adjudicated = array();
	$sql = "select * from redcap_projects d, redcap_ehr_import_counts m 
			where d.project_id = m.project_id and m.type = 'CDP' and d.project_id in (" . prep_implode($ddp_project_ids) . ")";
	$q = db_query($sql, null, MYSQLI_USE_RESULT);
	while ($row = db_fetch_assoc($q)) {
		$total_ddp_projects_adjudicated[$row['project_id']] = true;
		$total_ddp_records_imported[$row['project_id']."-".$row['record']] = true;
		$total_ddp_values_adjudicated += (int)$row['counts_Patient']+(int)$row['counts_Observation']+(int)$row['counts_Condition']
										+(int)$row['counts_MedicationOrder']+(int)$row['counts_AllergyIntolerance'];
	}
	$total_ddp_projects_adjudicated = count($total_ddp_projects_adjudicated);
	$total_ddp_records_imported = count($total_ddp_records_imported);

	// Return values
	exit(	User::number_format_user($total_ddp_values_adjudicated) . "|" .
			User::number_format_user($total_ddp_records_imported) . "|" .
			User::number_format_user($total_ddp_projects_adjudicated));
}
// DDP Custom
elseif (isset($_GET['ddp1']) && !isset($_GET['fhir']))
{
	// Get count of projects using Dynamic Data Pull (DDP)
	$ddp_project_ids = $Stats->getDDpProjectIds(isset($_GET['fhir']));

	// Get count of all adjudicated data points that have been imported from source system in DDP-enabled projects
	$total_ddp_values_adjudicated = 0;
	foreach ($log_event_tables as $this_log_event_table) {
		$sql = "select sum(round((length(data_values)-length(replace(data_values,',\n','')))/2)+1)
			from $this_log_event_table e where e.page = 'DynamicDataPull/save.php'
			and e.description != 'Update record (Auto calculation)'
			and e.project_id in (" . prep_implode($ddp_project_ids) . ")";
		$temp = db_result(db_query($sql), 0);
		$total_ddp_values_adjudicated += ($temp == '' ? 0 : $temp);
	}

	// Get pid's of all DDP projects that have been flushed and thus do not have values
	// in the ddp_records_* tables (need to supplement with a check in the log_event table)
	$sql = "select distinct r.project_id from redcap_ddp_records r, redcap_ddp_records_data d
			where r.mr_id = d.mr_id and r.project_id in (".prep_implode($ddp_project_ids).")";
	$q = db_query($sql);
	$ddp_flushed_project_ids = $ddp_project_ids;
	while ($row = db_fetch_assoc($q)) {
		unset($ddp_flushed_project_ids[$row['project_id']]);
	}
	// Get count of projects with at least one *adjudicated* value imported via DDP
	$total_ddp_projects_adjudicated = 0;
	$sql = "select count(distinct(r.project_id)) from redcap_ddp_records r, redcap_ddp_records_data d
			where r.mr_id = d.mr_id and d.adjudicated = 1 and r.project_id in (".prep_implode($ddp_project_ids).")";
	if (!empty($ddp_flushed_project_ids)) {
		$sql .= " and r.project_id not in (".prep_implode($ddp_flushed_project_ids).")";
	}
	$temp = db_result(db_query($sql), 0);
	$total_ddp_projects_adjudicated += ($temp == '' ? 0 : $temp);
	if (!empty($ddp_flushed_project_ids)) {
		foreach ($log_event_tables as $this_log_event_table) {
			$sql = "select count(distinct(e.project_id)) from $this_log_event_table e where e.page = 'DynamicDataPull/save.php'
				and e.project_id in (" . prep_implode($ddp_flushed_project_ids) . ")";
			$temp = db_result(db_query($sql), 0);
			$total_ddp_projects_adjudicated += ($temp == '' ? 0 : $temp);
		}
	}

	// Return values
	exit(	User::number_format_user($total_ddp_values_adjudicated) . "|" .
			User::number_format_user($total_ddp_projects_adjudicated));
}
if (isset($_GET['ddp2']) && !isset($_GET['fhir']))
{
	// Get count of projects using Dynamic Data Pull (DDP)
	$ddp_project_ids = $Stats->getDDpProjectIds(isset($_GET['fhir']));

	// Get count of records that have had data adjudicated from source system in DDP-enabled projects
	$total_ddp_records_imported = 0;
	foreach ($log_event_tables as $this_log_event_table) {
		$sql = "select count(distinct(concat(e.project_id, e.pk))) from $this_log_event_table e where e.page = 'DynamicDataPull/save.php'
			and e.description != 'Update record (Auto calculation)' and e.project_id in (" . prep_implode($ddp_project_ids) . ")";
		$temp = db_result(db_query($sql), 0);
		$total_ddp_records_imported += ($temp == '' ? 0 : $temp);
	}
	exit(User::number_format_user($total_ddp_records_imported)."");
}
if (isset($_GET['data_mart']))
{
	// Get count of projects using Data Mart
	$dm_project_ids = $Stats->getDataMartProjectIds();
	$total_data_mart_records_imported = $Stats->getDataMartRecordsImported();
	// Get values count
	$total_data_mart_values_imported = 0;
	$sql = "select * from redcap_projects d, redcap_ehr_import_counts m 
			where d.project_id = m.project_id and m.type = 'CDM' and d.project_id in (" . prep_implode($dm_project_ids) . ")";
	$q = db_query($sql, null, MYSQLI_USE_RESULT);
	while ($row = db_fetch_assoc($q)) {
		$total_data_mart_values_imported += (int)$row['counts_Patient']+(int)$row['counts_Observation']+(int)$row['counts_Condition']
											+(int)$row['counts_MedicationOrder']+(int)$row['counts_AllergyIntolerance'];
	}
	// Return values
	exit(	User::number_format_user(count($dm_project_ids)) . "|" .
			User::number_format_user($total_data_mart_records_imported) . "|" .
			User::number_format_user($total_data_mart_values_imported)
	);
}



/**
 * STATISTICS TABLE
 */

//Get total number of projects for each status
$status_dev = 0;
$status_prod = 0;
$status_inactive = 0;
$status_archived = 0;
$status_completed = 0;
$q = db_query("select x.status, count(*) as count from (select if (completed_time is null, status, '3') as status from redcap_projects 
				where project_id not in (".$Stats->getIgnoredProjectIds().")) x group by x.status");
while ($row = db_fetch_array($q)) {
	switch ($row['status'])
	{
		case '0':
			$status_dev = $row['count'];
			break;
		case '1':
			$status_prod = $row['count'];
			break;
		case '2':
			$status_inactive = $row['count'];
			break;
		case '3':
			$status_completed = $row['count'];
	}
}
//Get total number of projects
$total_projects = $status_prod + $status_dev + $status_inactive + $status_archived;

// Get counts of project types
$type_forms = 0;
$type_surveyforms = 0;
$type_forms_prod = 0;
$type_surveyforms_prod = 0;
$type_forms_dev = 0;
$type_surveyforms_dev = 0;
$type_forms_inactive = 0;
$type_surveyforms_inactive = 0;
$type_forms_completed = 0;
$type_surveyforms_completed = 0;
//$q = db_query("select surveys_enabled, count(surveys_enabled) as count from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().") group by surveys_enabled");
$q = db_query("select surveys_enabled, status, completed_time from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().")");
while ($row = db_fetch_array($q))
{
	switch ($row['surveys_enabled']) {
		case '0':
			$type_forms++;
			if ($row['completed_time'] != '') {
				$type_forms_completed++;
			} elseif ($row['status'] == '0') {
				$type_forms_dev++;
			} elseif ($row['status'] == '1') {
				$type_forms_prod++;
			} elseif ($row['status'] == '2') {
				$type_forms_inactive++;
			}
			break;
		case '1':
			$type_surveyforms++;
			if ($row['completed_time'] != '') {
				$type_surveyforms_completed++;
			} elseif ($row['status'] == '0') {
				$type_surveyforms_dev++;
			} elseif ($row['status'] == '1') {
				$type_surveyforms_prod++;
			} elseif ($row['status'] == '2') {
				$type_surveyforms_inactive++;
			}
			break;
	}
}

// Get counts of project purposes
$purpose_operational = 0;
$purpose_research = 0;
$purpose_qualimprove = 0;
$purpose_other = 0;
$q = db_query("select purpose, count(purpose) as count from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().") group by purpose");
while ($row = db_fetch_array($q))
{
	switch ($row['purpose'])
	{
		case '4': $purpose_operational = $row['count']; break;
		case '2': $purpose_research = $row['count']; break;
		case '3': $purpose_qualimprove = $row['count']; break;
		case '1': $purpose_other = $row['count']; break;
	}
}

// Count average number of users per project (prod/inactive only)
$median_users_per_project = array();
$median_users_per_project_forms = array();
$median_users_per_project_surveyforms = array();
$sql = "select p.project_id, p.surveys_enabled, count(u.username) as usercount
		from redcap_projects p, redcap_user_rights u where p.project_id = u.project_id and p.status in (1,2)
		and p.project_id not in (".$Stats->getIgnoredProjectIds().") group by p.project_id order by usercount";
$q = db_query($sql);
while ($row = db_fetch_array($q))
{
	// Add to total user count
	$median_users_per_project[] = $row['usercount'];
	// Add to each project type
	switch ($row['surveys_enabled'])
	{
		case '0':
			$median_users_per_project_forms[] = $row['usercount'];
			break;
		default:
			$median_users_per_project_surveyforms[] = $row['usercount'];
	}
}
// Now find the averages
$median_users_per_project = round(median($median_users_per_project));
$median_users_per_project_forms = round(median($median_users_per_project_forms));
$median_users_per_project_surveyforms = round(median($median_users_per_project_surveyforms));

// Count average/median number of projects a user has access to (prod/inactive only)
$median_projects_per_user = array();
$sql = "select lower(trim(u.username)) as username, count(p.project_id) as projectcount
		from redcap_projects p, redcap_user_rights u where p.project_id = u.project_id
		and p.project_id not in (".$Stats->getIgnoredProjectIds().") group by lower(trim(u.username)) order by projectcount";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	$median_projects_per_user[] = $row['projectcount'];
}
$median_projects_per_user = round(median($median_projects_per_user));
$sql = "select avg(projectcount) from (select lower(trim(u.username)) as username, count(p.project_id) as projectcount
		from redcap_projects p, redcap_user_rights u where p.project_id = u.project_id
		and p.project_id not in (".$Stats->getIgnoredProjectIds().") group by lower(trim(u.username))) as x";
$q = db_query($sql);
$avg_projects_per_user = round(db_result($q, 0),1);

## Send-It files sent
//Get total number of Send-It files sent for past week
$q = db_query("select count(1) FROM redcap_sendit_docs WHERE date_added >= '" . date("Y-m-d H:i:s", mktime(0,0,0,date("m"),date("d")-6,date("Y"))) . "'");
$sendit_week = db_result($q,0);
//Get total number of Send-It files sent for past month
$q = db_query("select count(1) FROM redcap_sendit_docs WHERE date_added >= '" . date("Y-m-d H:i:s", mktime(0,0,0,date("m"),date("d")-29,date("Y"))) . "'");
$sendit_month = db_result($q,0);
//Get total number of Send-It files sent
$q = db_query("select count(1) from redcap_sendit_docs");
$sendit_total = db_result($q,0);



// Get total calendar events
$sql = "select count(1) from redcap_events_calendar";
$total_cal_events = db_result(db_query($sql),0);

// Get total number of table-based users
$sql = "select count(1) from redcap_auth";
$table_users = db_result(db_query($sql),0);

// Get user count
$sql = "select count(1) from redcap_user_information";
$total_users = db_result(db_query($sql),0);

// Get total number of data entry forms
$total_forms = db_result(db_query("select sum(forms) from (select count(distinct(form_name)) as forms from redcap_metadata group by project_id) as x"), 0);

// Get total number of active users
$sql = "select count(1) from redcap_user_information where user_firstactivity is not null and user_suspended_time is null";
$total_users_active = db_result(db_query($sql),0);

// Get total number of suspended users
$sql = "select count(1) from redcap_user_information where user_suspended_time is not null";
$suspended_users = db_result(db_query($sql),0);

// Get total times scheduling was performed
$sql = "select count(1) from (select distinct(record), project_id from redcap_events_calendar where event_id is not null
		and record is not null) as x";
$scheduling_performed = db_result(db_query($sql), 0);

// Get count of "longitudinal" projects (using more than one Event)
$sql = "select count(x.project_id) from (select a.project_id, count(a.project_id) as events from redcap_events_metadata m,
		redcap_events_arms a where a.arm_id = m.arm_id and a.project_id not in (".$Stats->getIgnoredProjectIds().")
		group by a.project_id) as x where x.events > 1";
$total_longitudinal = db_result(db_query($sql), 0);

// Repeating instruments/events
$sql = "select count(distinct(a.project_id)) as count from redcap_events_metadata m, redcap_events_arms a, redcap_events_repeat r 
		where a.arm_id = m.arm_id and r.event_id = m.event_id and a.project_id not in (".$Stats->getIgnoredProjectIds().") ";
$total_repeating_forms_events = db_result(db_query($sql), 0);
$sql = "select x.type, count(1) as count from (select a.project_id, if (r.form_name is null, 'event', 'form') as type 
		from redcap_events_metadata m, redcap_events_arms a, redcap_events_repeat r where a.arm_id = m.arm_id 
		and r.event_id = m.event_id and a.project_id not in (".$Stats->getIgnoredProjectIds().") 
		group by a.project_id, if (r.form_name is null, 'event', 'form')) x group by x.type";
$q = db_query($sql);
$projects_repeating_forms_events_both = array();
$total_repeating_events = $total_repeating_forms = 0;
while ($row = db_fetch_assoc($q))
{
	if ($row['type'] == "event") {
		$total_repeating_events = $row['count'];
	} else {
		$total_repeating_forms = $row['count'];
	}
}
$sql = "select count(1) from (select x.project_id from (select a.project_id, if (r.form_name is null, 'event', 'form')
		from redcap_events_metadata m, redcap_events_arms a, redcap_events_repeat r where a.arm_id = m.arm_id 
		and r.event_id = m.event_id and a.project_id and a.project_id not in (".$Stats->getIgnoredProjectIds().")
		group by a.project_id, if (r.form_name is null, 'event', 'form')) x
		group by x.project_id having count(*) > 1) y";
$total_repeating_forms_events_both = db_result(db_query($sql), 0);

// Get count of  projects using Double Data Entry module
$sql = "select count(1) from redcap_projects where double_data_entry = 1 and project_id not in (".$Stats->getIgnoredProjectIds().")";
$total_dde = db_result(db_query($sql), 0);

// DDP Custom
if ($realtime_webservice_global_enabled)
{
	// Get count of projects using Dynamic Data Pull (DDP)
	$ddp_project_ids = $Stats->getDDpProjectIds();
	$total_ddp = count($ddp_project_ids);
}
// DDP on FHIR
if ($fhir_ddp_enabled)
{
	// Get count of projects using Dynamic Data Pull (DDP)
	$ddp_fhir_project_ids = $Stats->getDDpProjectIds(true);
	$total_ddp_fhir = count($ddp_fhir_project_ids);
}

// Count DTS projects
$total_dts_enabled = 0; //default
if ($dts_enabled_global) {
	$q = db_query("select count(1) from redcap_projects where dts_enabled = 1 and project_id not in (".$Stats->getIgnoredProjectIds().")");
	$total_dts_enabled = db_result($q,0);
}

// Count currently logged-in users in the system
$logoutWindow = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
$sql = "select count(distinct(v.user)) from redcap_sessions s, redcap_log_view v
		where v.user != '[survey respondent]' and v.session_id = s.session_id and v.ts >= '$logoutWindow'";
$q = db_query($sql);
$loggedin_projectusers = db_result($q,0);
$logoutWindow = date("Y-m-d H:i:s", mktime(date("H"),date("i")-60,date("s"),date("m"),date("d"),date("Y"))); // Manually set 60 min as survey window
$sql = "select count(distinct(s.session_id)) from redcap_sessions s, redcap_log_view v
		where v.user = '[survey respondent]' and v.session_id = s.session_id and v.ts >= '$logoutWindow'";
$q = db_query($sql);
$loggedin_participants = db_result($q,0);
$loggedin_total = $loggedin_participants + $loggedin_projectusers;

// Count total survey responses
$q = db_query("select count(1) from redcap_surveys_response where first_submit_time is not null");
$total_survey_response = db_result($q,0);

// Get list of all research subcategories
$research_sub = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0);
$q = db_query("select purpose_other from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().") and purpose = 2");
while ($row = db_fetch_assoc($q))
{
	if ($row['purpose_other'] == "") {
		$research_sub[8]++;
	} elseif (is_numeric($row['purpose_other'])) {
		$research_sub[$row['purpose_other']]++;
	} else {
		foreach (explode(",", $row['purpose_other']) as $val) {
			$research_sub[$val]++;
		}
	}
}

// Get count of projects created from project templates
$sql = "select count(1) from redcap_projects where template_id is not null";
$q = db_query($sql);
$total_templates = db_result($q, 0);

// If storing edocs on other server via Amazon S3 or webdav method
if ($edoc_storage_option != '0' && $edoc_storage_option != '3')
{
	// Default
	$total_edoc_space_used = 0;
	// Get space used by edoc file uploading on data entry forms. Count using table values (since we cannot easily call external server itself).
	$sql = "select if(sum(doc_size) is null, 0, round(sum(doc_size)/(1024*1024),1)) from redcap_edocs_metadata where date_deleted_server is null";
	$total_edoc_space_used += db_result(db_query($sql), 0);
	// Additionally, get space used by send-it files (for location=1 only, because loc=3 is edocs duplication). Count using table values (since we cannot easily call external server itself).
	$sql = "select if(sum(doc_size) is null, 0, round(sum(doc_size)/(1024*1024),1)) from redcap_sendit_docs
			where location = 1 and expire_date > '".NOW."' and date_deleted is null";
	$total_edoc_space_used += db_result(db_query($sql), 0);
}

// Get count of alerts and projects utilizing them
$sql = "SELECT count(*) as alerts, count(distinct(project_id)) as alerts_projects 
        FROM redcap_alerts WHERE email_deleted = 0 AND project_id not in (".$Stats->getIgnoredProjectIds().")";
$q = db_query($sql);
$total_alerts = db_result($q, 0, 'alerts');
$total_alerts_projects = db_result($q, 0, 'alerts_projects');

// Set columns for both tables
$col_widths_headers = array(
						array(340,  "col1"),
						array(55,   "col2", "center")
					);

// Set indention strings
$indent1 = "&nbsp;&nbsp;&nbsp; - ";
$indent2 = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - ";
$indent2b = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
$indent3 = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - ";
$indentbullet = "&nbsp;&nbsp;&nbsp; &bull; ";

$row_data = array();
$row_data[] = array("<b>{$lang['dashboard_24']}</b><span style='padding-left:5px;color:#777;font-size:10px;'>{$lang['dashboard_60']}</span>", User::number_format_user($total_projects));
// Project status
$row_data[] = array("$indentbullet {$lang['index_26']}", "");
// Production
$row_data[] = array("$indent2 {$lang['global_30']}
					<a href='javascript:;' onclick=\"$('#prod_project_types').toggle();$('#prod_project_types_counts').toggle();\" style='padding-left:50px;text-decoration:underline;font-size:10px;'>{$lang['dashboard_81']}</a>
					<div id='prod_project_types' style='clear:both;display:none;color:#666;font-size:10px;'>
						$indent3 {$lang['survey_387']}<br>
						$indent3 {$lang['survey_386']}
					</div>",
					User::number_format_user($status_prod)
					.  "<div id='prod_project_types_counts' style='color:#666;font-size:10px;display:none;'>
						$type_forms_prod<br>$type_surveyforms_prod
						</div>" );
// Development
$row_data[] = array("$indent2 {$lang['global_29']}
					<a href='javascript:;' onclick=\"$('#dev_project_types').toggle();$('#dev_project_types_counts').toggle();\" style='padding-left:40px;text-decoration:underline;font-size:10px;'>{$lang['dashboard_81']}</a>
					<div id='dev_project_types' style='clear:both;display:none;color:#666;font-size:10px;'>
						$indent3 {$lang['survey_387']}<br>
						$indent3 {$lang['survey_386']}
					</div>",
					User::number_format_user($status_dev)
					.  "<div id='dev_project_types_counts' style='color:#666;font-size:10px;display:none;'>
						$type_forms_dev<br>$type_surveyforms_dev
						</div>" );
// Analysis/Cleanup
$row_data[] = array("$indent2 {$lang['global_159']}
					<a href='javascript:;' onclick=\"$('#inactive_project_types').toggle();$('#inactive_project_types_counts').toggle();\" style='padding-left:65px;text-decoration:underline;font-size:10px;'>{$lang['dashboard_81']}</a>
					<div id='inactive_project_types' style='clear:both;display:none;color:#666;font-size:10px;'>
						$indent3 {$lang['survey_387']}<br>
						$indent3 {$lang['survey_386']}
					</div>",
					User::number_format_user($status_inactive)
					.  "<div id='inactive_project_types_counts' style='color:#666;font-size:10px;display:none;'>
						$type_forms_inactive<br>$type_surveyforms_inactive
						</div>" );
// Completed
$row_data[] = array("$indent2 {$lang['edit_project_207']}
					<a href='javascript:;' onclick=\"$('#completed_project_types').toggle();$('#completed_project_types_counts').toggle();\" style='padding-left:65px;text-decoration:underline;font-size:10px;'>{$lang['dashboard_81']}</a>
					<div id='completed_project_types' style='clear:both;display:none;color:#666;font-size:10px;'>
						$indent3 {$lang['survey_387']}<br>
						$indent3 {$lang['survey_386']}
					</div>",
					User::number_format_user($status_completed)
					.  "<div id='completed_project_types' style='color:#666;font-size:10px;display:none;'>
						$type_forms_completed<br>$type_surveyforms_completed
						</div>" );
// Project types
$row_data[] = array("$indentbullet {$lang['global_63']}", "");
$row_data[] = array("$indent2 {$lang['survey_387']}", User::number_format_user($type_forms));
$row_data[] = array("$indent2 {$lang['survey_386']}", User::number_format_user($type_surveyforms));
// Project purpose
$row_data[] = array("$indentbullet {$lang['dashboard_70']}", "");
// Research and subcategories
$row_data[] = array("$indent2 {$lang['create_project_17']}
					<a href='javascript:;' onclick=\"$('#research_sub').toggle();$('#research_sub_counts').toggle();\" style='padding-left:20px;text-decoration:underline;font-size:10px;'>{$lang['dashboard_82']}</a>
					<div id='research_sub' style='clear:both;display:none;color:#666;font-size:10px;'>
						$indent3 {$lang['create_project_21']}<br>
						$indent3 {$lang['create_project_22']}<br>
						$indent3 {$lang['create_project_23']}<br>
						$indent3 {$lang['create_project_24']}<br>
						$indent3 {$lang['create_project_25']}<br>
						$indent3 {$lang['create_project_26']}<br>
						$indent3 {$lang['create_project_27']}<br>
						$indent3 {$lang['create_project_19']}<br>
						$indent3 {$lang['dashboard_83']}
					</div>",
					User::number_format_user($purpose_research)
					.  "<div id='research_sub_counts' style='color:#666;font-size:10px;display:none;'>
						{$research_sub[0]}<br>
						{$research_sub[1]}<br>
						{$research_sub[2]}<br>
						{$research_sub[3]}<br>
						{$research_sub[4]}<br>
						{$research_sub[5]}<br>
						{$research_sub[6]}<br>
						{$research_sub[7]}<br>
						{$research_sub[8]}
						</div>" );
$row_data[] = array("$indent2 {$lang['create_project_16']}", User::number_format_user($purpose_operational));
$row_data[] = array("$indent2 {$lang['create_project_18']}", User::number_format_user($purpose_qualimprove));
$row_data[] = array("$indent2 {$lang['create_project_19']}", User::number_format_user($purpose_other));
// Project attributes
$row_data[] = array("<b>".$lang['dashboard_61']."</b>", "");
$row_data[] = array("$indent1 {$lang['dashboard_36']}", User::number_format_user($total_forms));
$row_data[] = array("$indent1 {$lang['dashboard_37']}", '<span id="total_fields"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_38']}", '<span id="total_records"><a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick="getTotalRecordCount()">'.$lang['rights_280'].'</></span>');
$row_data[] = array("$indent1 {$lang['dashboard_79']}", User::number_format_user($total_survey_response));
$row_data[] = array("$indent1 {$lang['dashboard_80']}", '<span id="survey_participants"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_84']}", '<span id="survey_invitations_sent"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent2 {$lang['dashboard_85']}", '<span id="survey_invitations_responded"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent2 {$lang['dashboard_86']}", '<span id="survey_invitations_unresponded"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_41']}", User::number_format_user($total_cal_events));
$row_data[] = array("$indent1 {$lang['dashboard_42']}", User::number_format_user($scheduling_performed));
$row_data[] = array("$indent1 {$lang['dashboard_96']}", User::number_format_user($total_templates));
$row_data[] = array("$indent1 {$lang['dashboard_108']}", User::number_format_user($Stats->surveyTextToSpeechCount()));
// Modules
$row_data[] = array("<b>".$lang['dashboard_97']."</b><span style='padding-left:5px;color:#777;font-size:10px;'>{$lang['dashboard_60']}</span>", "");
$row_data[] = array("$indent1 {$lang['dashboard_43']}", User::number_format_user($total_longitudinal));

$row_data[] = array("$indent1 {$lang['dashboard_117']}", "");
$row_data[] = array("$indent2 {$lang['dashboard_113']}", User::number_format_user($total_repeating_forms_events));
$row_data[] = array("$indent2 {$lang['dashboard_114']}", User::number_format_user($total_repeating_forms));
$row_data[] = array("$indent2 {$lang['dashboard_115']}", User::number_format_user($total_repeating_events));
$row_data[] = array("$indent2 {$lang['dashboard_116']}", User::number_format_user($total_repeating_forms_events_both));

$row_data[] = array("$indent1 {$lang['global_154']}", "");
$row_data[] = array("$indent2 {$lang['dashboard_122']}", User::number_format_user($total_alerts));
$row_data[] = array("$indent2 {$lang['dashboard_123']}", User::number_format_user($total_alerts_projects));

$row_data[] = array("$indent1 {$lang['dashboard_44']}", User::number_format_user($total_dde));
$row_data[] = array("$indent1 {$lang['app_21']}", User::number_format_user(Stats::randomizationCount()));
if ($realtime_webservice_global_enabled) {
	$row_data[] = array("$indent1 {$lang['ws_63']} - {$lang['ws_251']}", User::number_format_user($total_ddp));
}
if ($fhir_ddp_enabled) {
	$row_data[] = array("$indent1 {$lang['ws_63']} - {$lang['ws_265']}", User::number_format_user($total_ddp_fhir));
}
$row_data[] = array("$indent1 {$lang['dashboard_65']}", User::number_format_user($total_dts_enabled));

$row_data[] = array("$indent1 {$lang['mobile_app_52']}", "");
$row_data[] = array("$indent2 {$lang['dashboard_104']}", User::number_format_user($Stats->mobileAppUserCount()));
$row_data[] = array("$indent2 {$lang['dashboard_105']}", User::number_format_user($Stats->mobileAppInitProjectCount()));
$row_data[] = array("$indent2 {$lang['dashboard_106']}", User::number_format_user($Stats->mobileAppSyncDataProjectCount()));

$row_data[] = array("$indent1 {$lang['dashboard_107']}", User::number_format_user($Stats->twilioProjectCount()));

$row_data[] = array("$indent1 {$lang['messaging_09']}", "");
$row_data[] = array("$indent2 {$lang['messaging_24']}", User::number_format_user(Messenger::countTotalConversations()));
$row_data[] = array("$indent2 {$lang['messaging_25']}", User::number_format_user(Messenger::countTotalLinkedConversations()));
$row_data[] = array("$indent2 {$lang['messaging_23']}", User::number_format_user(Messenger::countTotalMessages()));
$row_data[] = array("$indent2 {$lang['messaging_26']}", User::number_format_user(Messenger::countTotalMessages(7)));
$row_data[] = array("$indent2 {$lang['messaging_27']}", User::number_format_user(Messenger::countTotalMessages(30)));

// e-Consent Framework
$row_data[] = array("$indent1 {$lang['dashboard_119']}", "");
$row_data[] = array("$indent2 {$lang['dashboard_120']}", User::number_format_user($Stats->countProjectsUsingEconsent()));
$row_data[] = array("$indent2 {$lang['dashboard_121']}", User::number_format_user($Stats->countEconsentPdfsStored()));

// DDP Custom
if ($realtime_webservice_global_enabled) {
	$row_data[] = array("$indent1 {$lang['ws_63']} - {$lang['ws_251']}", "");
	$row_data[] = array("$indent2 {$lang['ws_198']}<div style='color:#888;margin-top: 1px;'>$indent2b {$lang['ws_330']}</div>", '<span id="total_ddp_values_pulled"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_199']}", '<span id="total_ddp_values_adjudicated"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_197']}", '<span id="total_ddp_records_imported"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_200']}", '<span id="total_ddp_projects_adjudicated"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
}
// Clinical Data Pull (CDP)
if ($fhir_ddp_enabled) {
	$row_data[] = array("$indent1 {$lang['ws_265']}", "");
	$row_data[] = array("$indent2 {$lang['ws_305']}", '<span id="total_ddp_fhir_values_adjudicated"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_303']}", '<span id="total_ddp_fhir_records_imported"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_306']}", '<span id="total_ddp_fhir_projects_adjudicated"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
}
// Clinical Data Mart
if ($fhir_data_mart_create_project) {
	$row_data[] = array("$indent1 {$lang['global_155']}", "");
	$row_data[] = array("$indent2 {$lang['ws_332']}", '<span id="total_data_mart_values_imported"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_307']}", '<span id="total_data_mart_records_imported"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
	$row_data[] = array("$indent2 {$lang['ws_308']}", '<span id="total_data_mart_projects"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
}

// Space usage
$row_data[] = array("<b>".$lang['dashboard_62']."</b>", "");
$row_data[] = array("$indent1 {$lang['dashboard_45']}", '<span id="mysql_space"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_46']}", '<span id="webserver_space"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
if ($edoc_storage_option != '0' && $edoc_storage_option != '3') {
	if ($total_edoc_space_used > 1024) {
		$row_data[] = array("$indent1 {$lang['dashboard_47']} \"edocs\"", User::number_format_user($total_edoc_space_used/1024, 2) . " GB");
	} else {
		$row_data[] = array("$indent1 {$lang['dashboard_47']} \"edocs\"", User::number_format_user($total_edoc_space_used, 2) . " MB");
	}
}
// Send-It
$row_data[] = array("<b>".$lang['dashboard_35']."</b>", User::number_format_user($sendit_total));
$row_data[] = array("$indent1 {$lang['dashboard_33']}", User::number_format_user($sendit_week));
$row_data[] = array("$indent1 {$lang['dashboard_34']}", User::number_format_user($sendit_month));
// Logged events
$row_data[] = array("<b>".$lang['dashboard_30']."</b>", '<span id="logged_events"><a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick="getTotalLogEventCount()">'.$lang['rights_280'].'</></span>');
$row_data[] = array("$indent1 {$lang['dashboard_31']}", '<span id="logged_events_30min"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_32']}", '<span id="logged_events_today"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_33']}", '<span id="logged_events_week"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
$row_data[] = array("$indent1 {$lang['dashboard_34']}", '<span id="logged_events_month"><span style="color:#999;">'.$lang['dashboard_39'].'...</span></span>');
// Users
$row_data[] = array("<b>".$lang['dashboard_51']."</b>", "");
$row_data[] = array("$indentbullet {$lang['dashboard_71']}", User::number_format_user($loggedin_total));
$row_data[] = array("$indent2 {$lang['dashboard_72']}", User::number_format_user($loggedin_projectusers));
$row_data[] = array("$indent2 {$lang['dashboard_73']}", User::number_format_user($loggedin_participants));
$row_data[] = array("$indentbullet {$lang['dashboard_98']}", User::number_format_user($total_users));
$row_data[] = array("$indent2 {$lang['dashboard_50']}
					[<a href='javascript:;' onclick=\"$(this).next('div').toggle();\" style='font-size:12px;color:red;'>?</a>]
					<div style='display:none;color:#666;white-space:normal;'>{$lang['dashboard_67']}</div>
					", User::number_format_user($total_users_active));
$row_data[] = array("$indent2 {$lang['dashboard_68']} {$lang['dashboard_100']}", User::number_format_user($total_users-$total_users_active-$suspended_users));
$row_data[] = array("$indent2 {$lang['dashboard_92']}", User::number_format_user($suspended_users));
$row_data[] = array("$indentbullet {$lang['dashboard_124']}", $median_users_per_project);
$row_data[] = array("$indent2 {$lang['survey_387']}", $median_users_per_project_forms);
$row_data[] = array("$indent2 {$lang['survey_386']}", $median_users_per_project_surveyforms);
$row_data[] = array("$indentbullet {$lang['dashboard_75']}","$avg_projects_per_user / $median_projects_per_user");
//Only show number of table users if using table auth
if (($auth_meth == 'table') || (($auth_meth == 'none' || $auth_meth == 'ldap_table' || strpos($auth_meth,'aaf')>-1) && $table_users > 0)) { //***<AAF Modification>***
	$row_data[] = array("$indentbullet {$lang['dashboard_66']}", User::number_format_user($table_users));
}

// Now render it
renderGrid("controlcenter_stats_inner", $lang['dashboard_48'], "auto", "auto", $col_widths_headers, $row_data, false);
