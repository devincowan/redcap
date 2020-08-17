<?php


/**
 * Logging Class
 * Contains methods used with regard to logging
 */
class Logging
{
	// Set up array of pages to ignore for logging page views and counting page hits or IP hashes
	public static $noCountPages = array(
		"DataEntry/auto_complete.php", // "DataEntry/search.php",
		"ControlCenter/report_site_stats.php", "Calendar/calendar_popup_ajax.php",
		"Reports/report_builder_ajax.php", "ControlCenter/check.php", "DataEntry/image_view.php", "ProjectGeneral/project_stats_ajax.php",
		"SharedLibrary/image_loader.php", "DataExport/plot_chart.php", "Surveys/theme_view.php", "Design/logic_validate.php", 
		"Design/logic_field_suggest.php", "Messenger/messenger_ajax.php", "DataEntryController:openSurveyValuesChanged", "DataEntry/web_service_auto_suggest.php",
		"ProjectGeneral/project_menu_collapse.php"
	);
	
	// Set user first/last activity timestamp
	public static function setUserActivityTimestamp()
	{
		global $user_firstactivity, $rc_connection;
		// Make sure we have valid userid
		if (!defined("USERID") || strpos(USERID, "[") !== false) return;
		// SET FIRST ACTIVITY TIMESTAMP
		// If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
		if ($user_firstactivity == "") {
			$sql = "update redcap_user_information set user_firstactivity = '".NOW."'
					where username = '".db_escape(USERID)."' and user_firstactivity is null and user_suspended_time is null";
			db_query($sql, $rc_connection);
		}
		// SET LAST ACTIVITY TIMESTAMP FOR USER
		// (but NOT if they are suspended - could be confusing if last activity occurs AFTER suspension)
		$sql = "update redcap_user_information set user_lastactivity = '".NOW."'
				where username = '".db_escape(USERID)."' and user_suspended_time is null";
		db_query($sql, $rc_connection);
	}
	
	// Set project's last activity timestamp
	public static function setProjectActivityTimestamp($project_id=null)
	{
		global $rc_connection;
		if (!(is_numeric($project_id) && $project_id > 0)) return; 		
		$sql = "update redcap_projects set last_logged_event = '".NOW."' where project_id = $project_id";
		db_query($sql, $rc_connection);
	}
	
	// Logs an action. Returns log_event_id from db table.
	public static function logEvent($sql, $table, $event, $record, $display, $descrip="", $change_reason="",
									$userid_override="", $project_id_override="", $useNOW=true, $event_id_override=null, $instance=null,
									$bulkProcessing=false)
	{
		global $rc_connection;

		// Log the event in the project's log_event table
		$ts 	 	= ($useNOW && !defined("CRON") ? str_replace(array("-",":"," "), array("","",""), NOW) : date('YmdHis'));
		$page 		= (defined("PAGE") ? PAGE : (defined("PLUGIN") ? "PLUGIN" : ""));
		$userid		= ($userid_override != "" ? $userid_override : (in_array(PAGE, Authentication::$noAuthPages) ? "[non-user]" : (defined("USERID") ? USERID : "")));
		if ($userid == "" && defined("CRON")) $userid = "SYSTEM";
		$ip 	 	= (isset($userid) && $userid == "[survey respondent]") ? "" : System::clientIpAddress(); // Don't log IP for survey respondents
		$event	 	= strtoupper($event);
		$event_id	= (is_numeric($event_id_override) ? $event_id_override : (isset($_GET['event_id']) && is_numeric($_GET['event_id']) ? $_GET['event_id'] : "NULL"));
		$project_id = (is_numeric($project_id_override) ? $project_id_override : (defined("PROJECT_ID") && is_numeric(PROJECT_ID) ? PROJECT_ID : 0));
		$instance   = is_numeric($instance) ? (int)$instance : 1;
		
		// Set instance (only if $instance>1)
		if ($instance > 1) {
			$display = "[instance = $instance]".($display == '' ? '' : ",\n").$display;
		}

		// Query
		$sql = "INSERT INTO ".self::getLogEventTable($project_id)."
				(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
				VALUES ($project_id, $ts, '".db_escape($userid)."', ".checkNull($ip).", '$page', '$event', '$table', ".checkNull($sql).",
				".checkNull($record).", $event_id, ".checkNull($display).", ".checkNull($descrip).", ".checkNull($change_reason).")";
		$q = db_query($sql, $rc_connection);
		$log_event_id = ($q ? db_insert_id() : false);

        // See if the record list has alrady been cached. If so, use it.
        $recordListCacheStatus = Records::getRecordListCacheStatus($project_id);
		
		// If we're processing in bulk, then don't run this block since it'll be run outside this method afterward (e.g., for data imports).
		if (!$bulkProcessing)
		{
			// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
			self::setUserActivityTimestamp();
			// SET LAST ACTIVITY TIMESTAMP FOR PROJECT
			self::setProjectActivityTimestamp($project_id);
			// RESET RECORD COUNT CACHE: If a record has beendeleted, then remove the count of records in the cache table
			if ($project_id > 0 && ($event == 'INSERT' || $event == 'DELETE'))
			{
				$resetRecordCache = true;
				if (is_numeric($event_id) && $recordListCacheStatus == 'COMPLETE') {
					// Get arm
					$arm = db_result(db_query("select arm_num from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and e.event_id = " .$event_id, $rc_connection), 0);
					// Delete record
					if ($event == 'DELETE') {
						$resetRecordCache = !Records::deleteRecordFromRecordListCache($project_id, $record, $arm);
					}
					// Create record
					elseif ($event == 'INSERT') {
						$resetRecordCache = !Records::addRecordToRecordListCache($project_id, $record, $arm);
					}
				}
				if ($resetRecordCache) {
					// Reset record list cache
					Records::resetRecordCountAndListCache($project_id);
				}
			}
		}
		
		// RESET RECORD COUNT CACHE: If a record was created, add to the queue of records to add to record list cache (for data imports)
		if ($bulkProcessing && $project_id > 0 && $record != '' && is_numeric($event_id) && $event == 'INSERT' && $recordListCacheStatus == 'COMPLETE')
		{
			Records::addRecordToRecordListCacheQueue($project_id, $record, $event_id);
		}

		// Return log_event_id PK if true or false if failed
		return $log_event_id;
	}
	
	// Add the total execution time of this PHP script to the current script's row in log_open_requests when this script finishes
	public static function updateLogViewRequestTime()
	{
		if (!defined("LOG_VIEW_REQUEST_ID") || !defined("SCRIPT_START_TIME")) return;
		// Calculate total execution time (rounded to milliseconds)
		$total_time = round((microtime(true) - SCRIPT_START_TIME), 3);
		// Update table
		$sql = "update redcap_log_view_requests set script_execution_time = '$total_time' where lvr_id = " . LOG_VIEW_REQUEST_ID;
		db_query($sql);
	}
	
	// If the current user has any currently running queries in another mysql process, than gather than in array
	public static function getUserCurrentQueries()
	{
		// Set conditions
		if (!(defined("UI_ID") && is_numeric(UI_ID) && !defined("API") && !defined("CRON") && PAGE != 'surveys/index.php')) {
			return;
		}
		// Get current mysql process id
		$mysql_process_id = db_thread_id();
		// Check the db table in the past hour (max request time)
		$oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
		// Set array and query to see if the current user has any currently running queries
		$sql = "select r.mysql_process_id from redcap_log_view_requests r, redcap_log_view v 
				where v.log_view_id = r.log_view_id and r.script_execution_time is null 
				and r.ui_id = ".UI_ID." and v.ts > '$oneHourAgo' and v.session_id = '".session_id()."'";
		$sql .= " and v.full_url = '".db_escape(curPageURL())."'";
		$q = db_query($sql);
		if (db_num_rows($q) == 0) return;
		// Loop to gather all mysql process IDs
		$CurrentUserQueries = array();
		while ($row = db_fetch_assoc($q)) {
			if ($row['mysql_process_id'] == $mysql_process_id) continue;
			$CurrentUserQueries[$row['mysql_process_id']] = true;
		}		
		// Gather all existing mysql queries from the process list
		$sql = "show full processlist";
		$q = db_query($sql);
		$GLOBALS['CurrentUserQueries'] = array();
		if (db_num_rows($q) > 0) 
		{
			while ($row = db_fetch_assoc($q)) {
				if ($row['Id'] == $mysql_process_id || !isset($CurrentUserQueries[$row['Id']])) continue;
				$GLOBALS['CurrentUserQueries'][$row['Id']] = $row['Info'];
			}
		}
		if (!empty($GLOBALS['CurrentUserQueries'])) {
			// Set var as a quick-check reference
			$GLOBALS['REDCapCurrentUserHasQueries'] = true;
		}
	}

	// Log page and user info for page being viewed (but only for specified pages)
	public static function logPageView($event="PAGE_VIEW", $userid, $twoFactorLoginMethod=null, $twoFactorForceLoginSuccess=false, $forceTs=null)
	{
		global $query_array, $custom_report_sql, $Proj, $isAjax, $two_factor_auth_enabled;

		// If using TWO FACTOR AUTH, then don't log "LOGIN_SUCCESS" until we do the second factor
		if ($two_factor_auth_enabled && $event == "LOGIN_SUCCESS" && $twoFactorLoginMethod == null && !$twoFactorForceLoginSuccess) {
			return;
		}

		// If a plugin or other page should be excluded from here, then just return
        if (defined("SKIP_LOG_PAGE_VIEW")) return;

		// Set userid as blank if USERID is not defined
		if (!defined("USERID") && $userid == "USERID") $userid = "";

		// If current page view is to be logged (i.e. if not set as noCountPages and is not a survey passthru page)
		// If this is the REDCap cron job, then skip this
		if (!defined('CRON') && !in_array(PAGE, self::$noCountPages) 
			&& !(PAGE == 'surveys/index.php' && ((isset($_GET['__passthru']) && $_GET['__passthru'] != "Surveys/email_participant_return_code.php")
                || isset($_GET[Authentication::TWILIO_2FA_SUCCESS_FLAG]))))
		{
			// Obtain browser info
			$browser = new Browser();
			$browser_name = strtolower($browser->getBrowser());
			$browser_version = isIE11compat() ? '11.0' : $browser->getVersion();
			// Do not include more than one decimal point in version
			if (substr_count($browser_version, ".") > 1) {
				$browser_version_array = explode(".", $browser_version);
				$browser_version = $browser_version_array[0] . "." . $browser_version_array[1];
			}

			// Obtain other needed values
			$ip 	 	= System::clientIpAddress();
			$page 	  	= (defined("PAGE") ? PAGE : "");
			$event	  	= strtoupper($event);
			$project_id = defined("PROJECT_ID") ? PROJECT_ID : "";
			$full_url	= curPageURL();
			$session_id = (!session_id() ? "" : substr(session_id(), 0, 32));

			// Defaults
			$event_id 	= "";
			$record		= "";
			$form_name 	= "";
			$miscellaneous = "";

			// Check if user's IP has been banned
			Logging::checkBannedIp($ip);
			// Save IP address as hashed value in cache table to prevent automated attacks
			Logging::storeHashedIp($ip);

			// Special logging for certain pages
			if ($event == "PAGE_VIEW") {
				switch (PAGE)
				{
					// Data Quality rule execution
					case "DataQuality/execute_ajax.php":
						$miscellaneous = "// rule_ids = '{$_POST['rule_ids']}'";
						break;
					// External Links clickthru page
					case "ExternalLinks/clickthru_logging_ajax.php":
						$miscellaneous = "// url = " . $_POST['url'];
						break;
					// Survey page
					case "surveys/index.php":
						// Set username and erase ip to maintain anonymity survey respondents
						$ip = "";
						if (isset($_GET['s']))
						{
							$userid = "[survey respondent]";
							// Set all survey attributes as global variables
							Survey::setSurveyVals($_GET['s']);
							$event_id = $GLOBALS['event_id'];
							$form_name = $GLOBALS['form_name'];
							// Capture the response_id if we have it
							if (isset($_POST['__response_hash__']) && !empty($_POST['__response_hash__'])) {
								$response_id = Survey::decryptResponseHash($_POST['__response_hash__'], $GLOBALS['participant_id']);
								// Get record name
								$sql = "select r.record from redcap_surveys_participants p, redcap_surveys_response r
										where r.participant_id = p.participant_id and r.response_id = $response_id";
								$q = db_query($sql);
								$record = db_result($q, 0);
								$miscellaneous = "// response_id = $response_id";
							} elseif (isset($GLOBALS['participant_email']) && $GLOBALS['participant_email'] !== null) {
								// Get record name for existing record (non-public survey)
								$sql = "select r.record, r.response_id from redcap_surveys_participants p, redcap_surveys_response r
										where r.participant_id = p.participant_id and p.hash = '".db_escape($_GET['s'])."'
										and p.participant_id = {$GLOBALS['participant_id']}";
								$q = db_query($sql);
								$record = db_result($q, 0, 'record');
								$response_id = db_result($q, 0, 'response_id');
								$miscellaneous = "// response_id = $response_id";
							}
							// If a Post request and is NOT a normal survey page submission, then log the Post parameters passed
							if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['submit-action'])) {
								if ($miscellaneous != "") $miscellaneous .= "\n";
								$miscellaneous .= "// POST = " . print_r($_POST, true);
							}
						}
						break;
					// API
					case "API/index.php":
					case "api/index.php":
						// If downloading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							// Set values needed for logging
							if (isset($_POST['token']) && !empty($_POST['token']))
							{
								$q = db_query("select project_id, username from redcap_user_rights where api_token = '" . db_escape($_POST['token']) . "'");
								$userid = db_result($q, 0, "username");
								$project_id = db_result($q, 0, "project_id");
							}
							$post = $_POST;
							// Remove data from $_POST for logging (if this is an API import)
							if (isset($post['data'])) $post['data'] = '[not displayed]';
							$miscellaneous = "// API Request: ";
							foreach ($post as $key=>$value) {
								$miscellaneous .= "$key = '" . ((is_array($value)) ? implode("', '", $value) : $value) . "'; ";
							}
							$miscellaneous = substr($miscellaneous, 0, -2);
						}
						break;
					// Data history
					case "DataEntry/data_history_popup.php":
						if (isset($_POST['event_id']))
						{
							$form_name = $Proj->metadata[$_POST['field_name']]['form_name'];
							$event_id = $_POST['event_id'];
							$record = $_POST['record'];
							$miscellaneous = "field_name = '" . $_POST['field_name'] . "'";
						}
						break;
					// Send it download
					case "SendIt/download.php":
						// If downloading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							$miscellaneous = "// Download file (Send-It)";
						}
						break;
					// Send it upload
					case "SendItController:upload":
						// Get project_id
						$fileLocation = (isset($_GET['loc']) ? $_GET['loc'] : 1);
						if ($fileLocation != 1) {
							if ($fileLocation == 2) //file repository
								$query = "SELECT project_id FROM redcap_docs WHERE docs_id = '" . db_escape($_GET['id']) . "'";
							else if ($fileLocation == 3) //data entry form
								$query = "SELECT project_id FROM redcap_edocs_metadata WHERE doc_id = '" . db_escape($_GET['id']) . "'";
							$project_id = db_result(db_query($query), 0);
						}
						// If uploading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							$miscellaneous = "// Upload file (Send-It)";
						}
						break;
					// Data entry page and other related pages that have the same query string params
					case "DataEntry/index.php":
					case "DataEntry/check_unique_ajax.php":
					case "DataEntry/file_upload.php":
					case "DataEntry/file_download.php":
					case "DataEntry/file_delete.php":
					case "ProjectGeneral/keep_alive.php":
						if (isset($_GET['page'])) {
							$form_name = $_GET['page'];
							$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : getSingleEvent(PROJECT_ID);
							if (isset($_GET['record'])) $record = $_GET['record'];
							elseif (isset($_GET['id'])) $record = $_GET['id'];
						}
						break;
					// PDF form export
					case "PdfController:index":
						if (isset($_GET['page'])) $form_name = $_GET['page'];
						$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : getSingleEvent(PROJECT_ID);
						if (isset($_GET['id'])) $record = $_GET['id'];
						break;
					// Longitudinal grid
					case "DataEntry/record_home.php":
						if (isset($_GET['id'])) $record = $_GET['id'];
						break;
					// Calendar
					case "Calendar/index.php":
						// Obtain mm, dd, yyyy being viewed
						if (!isset($_GET['year'])) {
							$_GET['year'] = date("Y");
						}
						if (!isset($_GET['month'])) {
							$_GET['month'] = date("n")+1;
						}
						$month = $_GET['month'] - 1;
						$year  = $_GET['year'];
						if (isset($_GET['day']) && $_GET['day'] != "") {
							$day = $_GET['day'];
						} else {
							$day = $_GET['day'] = 1;
						}
						$days_in_month = date("t", mktime(0,0,0,$month,1,$year));
						// Set values
						$view = (!isset($_GET['view']) || $_GET['view'] == "") ? "month" : $_GET['view'];
						$miscellaneous = "view: $view\ndates viewed: ";
						switch ($view) {
							case "day":
								$miscellaneous .= "$month/$day/$year";
								break;
							case "week":
								$miscellaneous .= "week of $month/$day/$year";
								break;
							default:
								$miscellaneous .= "$month/1/$year - $month/$days_in_month/$year";
						}
						break;
					// Edoc download
					case "DataEntry/file_download.php":
						$record    = $_GET['record'];
						$event_id  = $_GET['event_id'];
						$form_name = $_GET['page'];
						break;
					// Calendar pop-up
					case "Calendar/calendar_popup.php":
						// Check if has record or event
						if (isset($_GET['cal_id'])) {
							$q = db_query("select record, event_id from redcap_events_calendar where cal_id = '".db_escape($_GET['cal_id'])."'");
							$record   = db_result($q, 0, "record");
							$event_id = db_result($q, 0, "event_id");
						}
						break;
					// Scheduling module
					case "Calendar/scheduling.php":
						if (isset($_GET['record'])) {
							$record = $_GET['record'];
						}
						break;
					// Graphical Data View page
					case "Graphical/index.php":
						if (isset($_GET['page'])) {
							$form_name = $_GET['page'];
						}
						break;
					// Graphical Data View highest/lowest/missing value
					case "DataExport/stats_highlowmiss.php":
						$form_name 	= $_GET['form'];
						$miscellaneous = "field_name: '{$_GET['field']}'\n"
									   . "action: '{$_GET['svc']}'\n"
									   . "group_id: " . (($_GET['group_id'] == "undefined") ? "" : $_GET['group_id']);
						break;
					// Viewing a report
					case "DataExport/report_ajax.php":
						// Report Builder reports
						if (isset($_POST['report_id'])) {
							$report = DataExport::getReports($_POST['report_id']);
							$miscellaneous = "// Report attributes for \"" . $report['title'] . "\" (report_id = {$_POST['report_id']}):\n";
							$miscellaneous .= json_encode($report);
						}
						break;
					// Data comparison tool
					case "DataComparisonController:index":
						if (isset($_POST['record1'])) {
							list ($record1, $event_id1) = explode("[__EVTID__]", $_POST['record1']);
							if (isset($_POST['record2'])) {
								list ($record2, $event_id2) = explode("[__EVTID__]", $_POST['record2']);
								$record = "$record1 (event_id: $event_id1)\n$record2 (event_id: $event_id2)";
							} else {
								$record = "$record1 (event_id: $event_id1)";
							}
						}
						break;
					// File repository and data export docs
					case "FileRepository/file_download.php":
						if (isset($_GET['id'])) {
							$miscellaneous = "// Download file from redcap_docs (docs_id = {$_GET['id']})";
						}
						break;
					// Logging page
					case "Logging/index.php":
						if (isset($_GET['record']) && $_GET['record'] != '') {
							$record = $_GET['record'];
						}
						if (isset($_GET['usr']) && $_GET['usr'] != '') {
							$miscellaneous = "// Filter by user name ('{$_GET['usr']}')";
						}
						break;
				}
			}

			// TWO FACTOR AUTH: Set login method (e.g., SMS) for miscellaneous
			if ($two_factor_auth_enabled && $event == "LOGIN_SUCCESS" && $twoFactorLoginMethod != null) {
				$miscellaneous = $twoFactorLoginMethod;
			}

			// If forcing a specific timestamp, then set it here
			$ts = ($forceTs == null) ? NOW : $forceTs;

			// Do logging
			$sql = "insert into redcap_log_view (ts, user, event, ip, browser_name, browser_version, full_url, page, project_id, event_id,
					record, form_name, miscellaneous, session_id) values ('".db_escape($ts)."', " . checkNull($userid) . ", '".db_escape($event)."', " . checkNull($ip) . ",
					'" . db_escape($browser_name) . "', '" . db_escape($browser_version) . "',
					'" . db_escape($full_url) . "', '".db_escape($page)."', " . checkNull($project_id) . ", " . checkNull($event_id) . ", " . checkNull($record) . ",
					" . checkNull($form_name) . ", " . checkNull($miscellaneous) . ", " . checkNull($session_id) . ")";
			db_query($sql);
			if (!defined("LOG_VIEW_ID")) define("LOG_VIEW_ID", db_insert_id());
		}
		// Add to log_open_requests table
		if ($event == "LOGOUT" && !defined("UI_ID")) {
			// Obtain UI_ID since we don't have it when logging out
			$userInfo = User::getUserInfo($userid);
			define("UI_ID", $userInfo['ui_id']);
		}
		$sql = "replace into redcap_log_view_requests (log_view_id, mysql_process_id, php_process_id, is_ajax, ui_id)
				values (" . checkNull(defined("LOG_VIEW_ID") ? LOG_VIEW_ID : '') . ", " . checkNull(db_thread_id()) . ", " .
				checkNull(getmypid()) . ", " . ($isAjax ? '1' : '0') . ", " . 
				checkNull(defined("UI_ID") ? UI_ID : '') . ")";
		db_query($sql);
		if (!defined("LOG_VIEW_REQUEST_ID")) define("LOG_VIEW_REQUEST_ID", db_insert_id());
	}

	// Count page hits (but not for specified pages, or for AJAX requests, or for survey passthru pages)
	public static function logPageHit()
	{
		global $isAjax;
		if (!defined("CRON") && !in_array(PAGE, self::$noCountPages) && !$isAjax && !(PAGE == 'surveys/index.php' && isset($_GET['__passthru'])))
		{
			//Add one to daily count
			$ph = db_query("update redcap_page_hits set page_hits = page_hits + 1 where date = CURRENT_DATE and page_name = '" . PAGE . "'");
			//Do insert if previous query fails (in the event of being the first person to hit that page that day)
			if (!$ph || db_affected_rows() != 1) {
				db_query("insert into redcap_page_hits (date, page_name) values (CURRENT_DATE, '" . PAGE . "')");
			}
		}
	}
	
	
	public static function renderLogRow($row, $html_output=true)
	{
		global 	$lang, $longitudinal, $user_rights, $double_data_entry, $multiple_arms,
				$require_change_reason, $event_ids, $Proj, $dq_rules, $table_pk, $DDP;

        // Set CDP or DDP to display in logging if using either
        $ddpText = (is_object($DDP) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) ? $lang['ws_30'] : $lang['ws_292'];

        if ($row['legacy'])
		{
			// For v2.1.0 and previous
			switch ($row['event'])
			{
				case 'UPDATE':

					$pos_set = strpos($row['sql_log'],' SET ') + 4;
					$pos_where = strpos($row['sql_log'],' WHERE ') - $pos_set;
					$sql_log = trim(substr($row['sql_log'],$pos_set,$pos_where));
					$sql_log = str_replace(",","{DELIM}",$sql_log);

					$pos_id1 = strrpos($row['sql_log']," = '") + 4;
					if (strpos($row['sql_log'],"LIMIT 1") == true) {
						$id = substr($row['sql_log'],$pos_id1,-10);
					} else {
						$id = substr($row['sql_log'],$pos_id1,-1);
					}
					$sql_log_array = explode("{DELIM}",$sql_log);
					$sql_log = '';
					foreach ($sql_log_array as $value) {
						if (substr(trim($value),-4) == 'null') $value = substr($value,0,-4)."''";
						$sql_log .= stripslashes($value) . ",<br>";
					}
					$sql_log = substr($sql_log,0,-5);
					if (strpos($row['sql_log']," redcap_auth ") == true) {
						$event = "<font color=#000066>{$lang['reporting_24']}</font>"; //User updated
					} elseif (strpos($row['sql_log'],"INSERT INTO redcap_edocs_metadata ") == true) {
						$event = "<font color=green>{$lang['reporting_39']}</font><br><font color=#000066>{$lang['reporting_25']}</font>"; //Document uploaded
						$id = substr($id,0,strpos($id,"'"));
						$sql_log = substr($sql_log,0,strpos($sql_log,"="));
					} elseif (strpos($row['sql_log'],"UPDATE redcap_edocs_metadata ") == true) {
						$event = "<font color=red>{$lang['reporting_40']}</font><br><font color=#000066>{$lang['reporting_25']}</font>"; //Document uploaded
						$id = substr($id,0,strpos($id,"'"));
						$sql_log = substr($sql_log,0,strpos($sql_log,"="));
					} else {
						$event = "<font color=#000066>{$lang['reporting_25']}</font>"; //Record updated
					}
					break;

				case 'INSERT':

					$pos1a = strpos($row['sql_log'],' (') + 2;
					$pos1b = strpos($row['sql_log'],') ') - $pos1a;
					$sql_log = trim(substr($row['sql_log'],$pos1a,$pos1b));
					$pos2a = strpos($row['sql_log'],'VALUES (') + 8;
					$sql_log2 = trim(substr($row['sql_log'],$pos2a,-1));
					$sql_log2 = str_replace(",","{DELIM}",$sql_log2);

					$pos_id1 = strpos($row['sql_log'],") VALUES ('") + 11;
					$id_a = substr($row['sql_log'],$pos_id1,-1);
					$pos_id2 = strpos($id_a,"'");
					$id = substr($row['sql_log'],$pos_id1,$pos_id2);

					$sql_log_array = explode(",",$sql_log);
					$sql_log_array2 = explode("{DELIM}",$sql_log2);
					$sql_log = '';
					for ($k = 0; $k < count($sql_log_array); $k++) {
						if (trim($sql_log_array2[$k]) == 'null') $sql_log_array2[$k] = "''";
						$sql_log .= stripslashes($sql_log_array[$k]) . " = " . stripslashes($sql_log_array2[$k]) . ",<br>";
					}
					$sql_log = substr($sql_log,0,-5);
					if (strpos($row['sql_log']," redcap_auth ") == true) {
						$event = "<font color=#800000>{$lang['reporting_26']}</font>";
					} elseif (strpos($row['sql_log'],"INSERT INTO redcap_edocs_metadata ") == true) {
						$event = "<font color=green>{$lang['reporting_39']}</font><br><font color=#800000>{$lang['reporting_27']}</font>"; //Document uploaded
						$sql_log1 = explode("=",$sql_log);
						if (count($sql_log1) == 2) {
							$sql_log = substr($sql_log,0,strrpos($sql_log,";")-1);
						} else {
							$sql_log = substr($sql_log,0,strrpos($sql_log,"="));
						}
					} else {
						$event = "<font color=#800000>{$lang['reporting_27']}</font>";
					}
					break;

				case 'DATA_EXPORT':

					$pos1 = strpos($row['sql_log'],"SELECT ") + 7;
					$pos2 = strpos($row['sql_log']," FROM ") - $pos1;
					$sql_log = substr($row['sql_log'],$pos1,$pos2);
					$sql_log_array = explode(",",$sql_log);
					$sql_log = '';
					foreach ($sql_log_array as $value) {
						list ($table, $this_field) = explode(".",$value);
						if (strpos($this_field,")") === false) $sql_log .= "$this_field, ";
					}
					$sql_log = substr($sql_log,0,-2);
					$event = "<font color=green>{$lang['reporting_28']}</font>";
					$id = "";
					break;

				case 'DELETE':

					$pos1 = strpos($row['sql_log'],"'") + 1;
					$pos2 = strrpos($row['sql_log'],"'") - $pos1;
					$id = substr($row['sql_log'],$pos1,$pos2);
					$event = "<font color=red>{$lang['reporting_30']}</font>";
					$sql_log = "$table_pk = '$id'";
					break;

				case 'OTHER':

					$sql_log = "";
					$event = "<font color=gray>{$lang['reporting_31']}</font>";
					$id = "";
					break;

			}

		}








		// For v2.2.0 and up
		else
		{
			switch ($row['event']) {

				case 'UPDATE':
					//$sql_log = str_replace("\n","<br>",$row['data_values']);
					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data")
					{
						if ($row['user'] == "[survey respondent]") {
							$event = "<font color=#000066>{$lang['reporting_47']}";
						} else {
							$event  = "<font color=#000066>{$lang['reporting_25']}";
							if ($row['page'] == "DynamicDataPull/save.php") $event .= " ($ddpText)";
							// Keep DTS page reference for legacy reasons
							elseif ($row['page'] == "DTS/index.php") $event .= " (DTS)";
						}
						if (strpos($row['description'], " (import)") !== false || $row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
							$event .= " (import)";
						}
						elseif (strpos($row['description'], " (API)") !== false) {
							$event .= " (API)";
						}
						elseif ($row['description'] == "Erase survey responses and start survey over") {
							$sql_log = "{$lang['survey_1079']}\n$sql_log";
						}
						if (strpos($row['description'], " (Auto calculation)") !== false) {
							$event .= "<br>(Auto calculation)";
						}
						$event .= "</font>";
						// DQ: If fixed values via the Data Quality module, then note that
						if ($row['page'] == "DataQuality/execute_ajax.php") {
							$event  = "<font color=#000066>{$lang['reporting_25']}<br>(Data Quality)</font>";
						}
						// DAGs: If assigning to or removing from DAG
						elseif (strpos($row['description'], "Remove record from Data Access Group") !== false || strpos($row['description'], "Assign record to Data Access Group") !== false)
						{
							$event  = "<font color=#000066>{$lang['reporting_25']}";
							if ($row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
								$event .= " (import)";
							} elseif (strpos($row['description'], " (API)") !== false) {
								$event .= " (API)";
							}
							$event .= "</font>";
							$sql_log = str_replace(" (API)", "", $row['description'])."\n(" . $row['data_values'] . ")";
						}
					}
					elseif ($row['object_type'] == "redcap_user_rights")
					{
						if ($row['description'] == 'Edit user expiration') {
							// Renamed role
							$event = "<font color=#800000>{$lang['rights_204']}</font>";
						} elseif ($row['description'] == 'Rename role') {
							// Renamed role
							$event = "<font color=#800000>{$lang['rights_200']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Edit role') {
							// Edited role
							$event = "<font color=#800000>{$lang['rights_196']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Remove user from role') {
							// Removed user from role
							$event = "<font color=#800000>{$lang['rights_177']}</font>";
						} else {
							// Edit user
							$event = "<font color=#000066>{$lang['reporting_24']}</font>";
						}
					}
                    elseif ($row['object_type'] == "redcap_alerts")
                    {
                        $event = "<font color=green>{$lang['alerts_17']}</font><br><font color=#000066>{$lang['global_49']} $id</font>";
                        $sql_log = $row['data_values'];
                        $id = '';
                    }
					// Survey confirmation email was sent
					elseif ($row['description'] == "Send survey confirmation email to participant") {
						$event = "<font color=green>{$lang['survey_1290']}</font><br><font color=#000066>{$lang['global_49']} $id</font>";
						$sql_log = $row['data_values'];
						$id = '';
					}
					break;

				case 'INSERT':

					//$sql_log = str_replace("\n","<br>",$row['data_values']);
					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data") {
						if ($row['user'] == "[survey respondent]") {
							$event = "<font color=#800000>{$lang['reporting_46']}";
						} else {
							$event = "<font color=#800000>{$lang['reporting_27']}";
							if ($row['page'] == "DynamicDataPull/save.php") $event .= " ($ddpText)";
						}
						if (strpos($row['description'], " (import)") !== false || $row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
							$event .= " (import)";
						}
						elseif (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						}
						$event .= "</font>";
					} elseif ($row['object_type'] == "redcap_user_rights") {
						if ($row['description'] == 'Add role' || $row['description'] == 'Copy role') {
							// Created role
							$event = "<font color=#000066>{$lang['rights_195']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Assign user to role') {
							// Assigned to user role
							$event = "<font color=#000066>{$lang['rights_167']}</font>";
						} else {
							// Added user
							$event = "<font color=#800000>{$lang['reporting_26']}</font>";
						}
						//print_array($row);
					}
					break;

				case 'DATA_EXPORT':

					// Display fields and other relevant export settings
					$sql_log = $row['data_values'];
					if (substr($sql_log, 0, 1) == '{') {
						// If string is JSON encoded (i.e. v6.0.0+), then parse JSON to display all export settings
						$sql_log_array = array();
						foreach (json_decode($sql_log, true) as $key=>$val) {
							if (is_array($val)) {
								$sql_log_array[] = "$key: \"".implode(", ", $val)."\"";
							} else {
								$sql_log_array[] = "$key: $val";
							}
						}
						$sql_log = implode(",\n", $sql_log_array);
					}
					// Set other values
					$event = "<font color=green>{$lang['reporting_28']}";
					if (strpos($row['description'], '(API)') !== false) {
						$event .= " (API)";
					} elseif (strpos($row['description'], '(API Playground)') !== false) {
						$event .= "<br>(API Playground)";
					}
					$event .= "</font>";
					$id = "";
					break;

				case 'DOC_UPLOAD':
					if (strpos($row['page'], 'Design/') === 0) {
						$sql_log = $row['description'];
						$event = "<font color=#000066>{$lang['reporting_33']}</font>";
					} else {
						$sql_log = $row['data_values'];
						$locationEquals = strpos($sql_log, ' = \'');
                        if ($locationEquals !== false) {
                            $sql_log = substr($sql_log, 0, $locationEquals);
                        }
						$event = "<font color=green>{$lang['reporting_39']}";
						if (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						} elseif (strpos($row['description'], '(API Playground)') !== false) {
							$event .= "<br>(API Playground)";
						}
						$event .= "</font><br><font color=#000066>{$lang['reporting_25']}</font>";
						$id = $row['pk'];
					}
					break;

				case 'DOC_DELETE':

					$sql_log = $row['data_values'];
					$event = "<font color=red>{$lang['reporting_40']}</font><br><font color=#000066>{$lang['reporting_25']}</font>";
                    if (strpos($row['description'], ' (V') !== false) {
                        list($nothing,$version) = explode(' (V', $row['description'], 2);
                        $version = preg_replace("/[^0-9]/", "", $version);
                        $sql_log .= " (V{$version})";
                    }
					$id = $row['pk'];
					break;

				case 'DELETE':

					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data") {
						$event = "<font color=red>{$lang['reporting_30']}";
						if (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						}else if (strpos($row['description'], '(API Playground)') !== false) {
							$event .= " (API Playground)";
						}
						$event .= "</font>";
					} elseif ($row['object_type'] == "redcap_user_rights") {
						if ($row['description'] == 'Delete role') {
							// Deleted role
							$event = "<font color=red>{$lang['rights_197']}</font>";
							$id = '';
						} else {
							// Deleted user
							$event = "<font color=red>{$lang['reporting_29']}</font>";
						}
					}
					break;

				case 'OTHER':
					$id = ($row['pk'] == "") ? "" : $lang['global_49'] . " " . $row['pk'];
					$event = "<font color=#800000>{$row['description']}</font>";
					$sql_log = $row['data_values'];
					break;

				case 'MANAGE':
					$sql_log = $row['description'];
					$event = "<font color=#000066>{$lang['reporting_33']}</font>";
					$id = "";
					// Parse activity differently for arms, events, calendar events, and scheduling
					if (in_array($sql_log, array("Create calendar event","Delete calendar event","Edit calendar event","Create event","Edit event",
												 "Delete event","Create arm","Delete arm","Edit arm name/number"))) {
						$sql_log .= "\n(" . $row['data_values'] . ")";
					}
                    // Consider PDF exports with data AND downloading exported data files as "Data Export"
                    if (strpos($sql_log, "PDF (with data)") !== false || strpos($sql_log, "Download exported") === 0) {
                        $event = "<font color=green>{$lang['reporting_28']}</font>";
                    }
					// Render record name for edoc downloads
					elseif ($sql_log == "Download uploaded document") {
						$event = "<font color=#000066>$sql_log</font>";
						// Deal with legacy logging, in which the record was not known and data_values contained "doc_id = #"
						if ($row['pk'] != "") {
							$sql_log = $row['data_values'];
							$id = $row['pk'];
							$event .= "<br>{$lang['global_49']}";
						} else {
							$sql_log = "";
						}
					}
					// Mobile App file upload to mobile app archive from app
					elseif ($sql_log == "Upload document to mobile app archive") {
						$event = "<font color=green>{$lang['reporting_39']}<br>{$lang['mobile_app_21']}</font>";
					}
                    // Alerts
                    elseif (strpos($sql_log, " alert") !== false) {
                        $event .= "<br><font color=green>$sql_log</font>";
                        $sql_log = $row['data_values'];
                    }
                    // Assign user to DAG or remove from DAG
					elseif ($sql_log == "Assign user to data access group" || $sql_log == "Remove user from data access group"
						|| $sql_log == "DAG Switcher: Assign user to additional DAGs" || $sql_log == "DAG Switcher: Remove user from multiple DAG assignment") {
						$sql_log .= "\n".$row['data_values'];
					}				
					// ASI was scheduled or removed
					elseif ($sql_log == "Automatically schedule survey invitation" || $sql_log == "Automatically remove scheduled survey invitation"
						|| $sql_log == "Delete scheduled survey invitation" || $sql_log == "Modify send time for scheduled survey invitation") 
					{
						$asiDetails = array();
						$asiLog = explode(",\n", $row['data_values']);
						foreach ($asiLog as $val) {
							list ($key, $val) = explode(" = ", $val, 2);
							if ($key == 'survey_id') {
								$asiDetails[1] = $lang['survey_437'].$lang['colon'].' "'.RCView::escape($Proj->surveys[$val]['title']).'"';
							} elseif ($Proj->longitudinal && $key == 'event_id') {
								$asiDetails[2] = $lang['global_141'].$lang['colon'].' "'.RCView::escape($Proj->eventInfo[$val]['name_ext']).'"';
							} elseif ($key == 'record') {
								$val = substr($val, 1, -1);
								$asiDetails[0] = $lang['global_49'].$lang['colon'].' "'.RCView::escape($val).'"';
							} elseif ($key == 'instance') {
								$asiDetails[3] = $lang['data_entry_246'].$lang['colon'].' "'.RCView::escape($val).'"';
							}
						}
						ksort($asiDetails);
						$asiText = implode(", ", $asiDetails);
						$sql_log .= "\n($asiText)";
					}
					// Render Download PDF Auto-Archive File so that it displays the record name
					elseif ($sql_log == "Download PDF Auto-Archive File") {
						$sql_log .= "\n(".$lang['global_49']." ".$row['pk'].")";
					}
					// Render randomization of records so that it displays the record name
					elseif ($sql_log == "Randomize record") {
						$id = $row['pk'];
						$event = "<font color=#000066>{$lang['random_117']}</font>";
					}
					// Render the email recipient's email if "Send email"
					elseif ($sql_log == "Send email" && $row['pk'] != '') {
						$sql_log .= "\n({$lang['reporting_48']}{$lang['colon']} {$row['pk']})";
					}
					// For super user action of viewing another user's API token, add username after description for clarification
					elseif ($sql_log == "View API token of another user") {
						$sql_log .=  "\n(".$row['data_values'].")";
					}
					// For sending public survey invites via Twilio services
					elseif (strpos($sql_log, "Send public survey invitation to participants") === 0) {
						$sql_log .=  "\n".$row['data_values'];
					}
                    // Data Mart data fetch
                    elseif ($sql_log == "Fetch data for Clinical Data Mart") {
                        $event = "<font color=#000066>{$lang['ws_293']}</font>";
                    }
					// Field Comment Log or Data Resolution Workflow
					elseif ($sql_log == "Edit field comment" || $sql_log == "Delete field comment" || $sql_log == "Add field comment" || $sql_log == "De-verified data value" || $sql_log == "Verified data value" || strpos($sql_log, "data query") !== false) {
						// Parse JSON values
						$jsonLog = json_decode($row['data_values'],true);
						// Record
						$sql_log .= "\n({$lang['dataqueries_93']} {$row['pk']}";
						// Event name (if longitudinal)
						if ($longitudinal && is_numeric($row['event_id'])) {
							$sql_log .= ", {$lang['bottom_23']} " . strip_tags(label_decode($event_ids[$row['event_id']]));
						}
						// Field name (unless is a multi-field custom DQ rule)
						if ($jsonLog['field'] != '') {
							$sql_log .= ", {$lang['reporting_49']} ".$jsonLog['field'];
						}
						// DQ rule (if applicable)
						if ($jsonLog['rule_id'] != '') {
							$sql_log .= ", {$lang['dataqueries_169']} ".(is_numeric($jsonLog['rule_id']) ? "#" : "")
									 .  $dq_rules[$jsonLog['rule_id']]['order'];
						}
						// Field Comment text
						if ($jsonLog['comment'] != '') {
							$sql_log .= ", {$lang['dataqueries_195']}{$lang['colon']} \"".$jsonLog['comment']."\"";
						}
						$sql_log .= ")";
					}
					break;

				case 'LOCK_RECORD':
					$sql_log = $lang['reporting_44'] . $row['description'] . "\n" . $row['data_values'];
					$event = "<font color=#A86700>{$lang['reporting_41']}</font>";
					$id = $row['pk'];
					break;

				case 'ESIGNATURE':
					$sql_log = $lang['reporting_44'] . $row['description'] . "\n" . $row['data_values'];
					$event = "<font color=#008000>{$lang['global_34']}</font>";
					$id = $row['pk'];
					break;

				case 'PAGE_VIEW':
					$sql_log = $lang['reporting_45']."\n" . $row['full_url'];
					// if ($row['record'] != "") $sql_log .= ",<br>record: " . $row['record'];
					// if ($row['event_id'] != "") $sql_log .= ",<br>event_id: " . $row['event_id'];
					$event = "<font color=#000066>{$lang['reporting_43']}</font>";
					$id = "";
					$row['data_values'] = "";
					break;

			}

		}

		// Append Event Name (if longitudinal)
		$dataEvents = array("OTHER","UPDATE","INSERT","DELETE","DOC_UPLOAD","DOC_DELETE","LOCK_RECORD","ESIGNATURE");
		if ($longitudinal && $row['legacy'] == '0'
			 && (($row['object_type'] == "redcap_data" || $row['object_type'] == "redcap_alerts" || $row['object_type'] == "") && in_array($row['event'], $dataEvents))
			 && !(strpos($row['description'], "Remove record from Data Access Group") !== false || strpos($row['description'], "Assign record to Data Access Group") !== false)
			)
		{
			// If missing, set to first event_id
			if ($row['event_id'] == "" && $row['event'] != 'OTHER') {
				$row['event_id'] = $Proj->firstEventId;
			}
			
			// If a record was deleted, don't show event name, and if multiple arms, then display the arm name from which it was deleted
			if ($row['description'] == 'Delete record') {
				if ($multiple_arms) {
					$eventInfo = $Proj->eventInfo[$row['event_id']];
					$id .= " <span style='color:#777;'>(" . strip_tags(label_decode($lang['global_08']." ".$eventInfo['arm_num'].$lang['colon']." ".$eventInfo['arm_name'])) . ")</span>";
				}
			}			
			// If event_id is not valid, then don't display event name
			elseif (isset($event_ids[$row['event_id']])) {
				$id .= " <span style='color:#777;'>(" . strip_tags(label_decode($event_ids[$row['event_id']])) . ")</span>";
			}
		}

		unset($sql_log_array);
		unset($sql_log_array2);

		// Set description
		$description = "$event<br>$id";

		// If outputting to non-html format (e.g., csv file), then remove html
		if (!$html_output)
		{
			$row['ts']   = DateTimeRC::format_ts_from_int_to_ymd($row['ts']);
			$description = strip_tags(str_replace("<br>", " ", $description));
			$sql_log 	 = filter_tags(str_replace(array("<br>","\n"), array(" "," "), label_decode($sql_log)));
		}
		// html output (i.e. Logging page)
		else
		{
			$row['ts'] = DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts']));
		}

		// Set values for this row
		$new_row = array($row['ts'], $row['user'], $description, $sql_log);

		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			$new_row[] = $html_output ? nl2br(filter_tags($row['change_reason'])) : str_replace("\n", " ", html_entity_decode($row['change_reason'], ENT_QUOTES));
		}

		// Return values for this row
		return $new_row;
	}


	public static function setEventFilterSql($logtype)
	{
		switch ($logtype)
		{
			case 'page_view':
				$filter_logtype =  "AND event = 'PAGE_VIEW'";
				break;
			case 'lock_record':
				$filter_logtype =  "AND event in ('LOCK_RECORD', 'ESIGNATURE')";
				break;
			case 'manage':
				$filter_logtype =  "AND event = 'MANAGE' and description not like 'Download exported%' and description not like '%PDF (with data)%'";
				break;
			case 'export':
				$filter_logtype =  "AND (event = 'DATA_EXPORT' or (event = 'MANAGE' and (description like 'Download exported%' or description like '%PDF (with data)%')))";
				break;
			case 'record':
				$filter_logtype =  "AND (
									(
										(
											legacy = '1'
											AND
											(
												left(sql_log,".strlen("INSERT INTO redcap_data").") = 'INSERT INTO redcap_data'
												OR
												left(sql_log,".strlen("UPDATE redcap_data").") = 'UPDATE redcap_data'
												OR
												left(sql_log,".strlen("DELETE FROM redcap_data").") = 'DELETE FROM redcap_data'
											)
										)
										OR
										(legacy = '0' AND object_type = 'redcap_data')
									)
									AND
										(event != 'DATA_EXPORT')
									)";
				break;
			case 'record_add':
				$filter_logtype =  "AND (
										(legacy = '1' AND left(sql_log,".strlen("INSERT INTO redcap_data").") = 'INSERT INTO redcap_data')
										OR
										(legacy = '0' AND object_type = 'redcap_data' and event = 'INSERT')
									)";
				break;
			case 'record_edit':
				$filter_logtype =  "AND (
										(legacy = '1' AND left(sql_log,".strlen("UPDATE redcap_data").") = 'UPDATE redcap_data')
										OR
										(legacy = '0' AND object_type = 'redcap_data' and event in ('UPDATE','DOC_DELETE','DOC_UPLOAD'))
										OR
										(legacy = '0' AND page = 'PLUGIN' and event in ('OTHER'))
									)";
				break;
			case 'record_delete':
				$filter_logtype =  "AND object_type = 'redcap_data' AND event = 'DELETE'";
				break;
			case 'user':
				$filter_logtype =  "AND object_type = 'redcap_user_rights'";
				break;
			default:
				$filter_logtype = '';
		}

		return $filter_logtype;

	}

	public function getEventById($logEventId){
		$result = db_query("select * from redcap_log_event where log_event_id = $logEventId");
		$row = db_fetch_assoc($result);

		$secondRow = db_fetch_assoc($result);
		if($secondRow !== null){
			throw new Exception("Multiple redcap_log_event rows exist for log_event_id $logEventId!");
		}

		return $row;
	}

	/**
     * convert an array to a string indenting nested values
     *
     * @param array $array
     * @param integer $indentation indent nested values
     * @return void
     */
    public static function printArray($array, $indentation=0)
    {
        if(!is_array($array)) return;
        $string = '';
        foreach($array as $key=>$value)
        {
            $string .= str_repeat("\t", $indentation); // add indentation
            $string .= sprintf("%s = '%s'", $key, $value); // print key and value
            $string .= PHP_EOL; // add end of line
            if(is_array($value)) $string .= self::printArray($value, $indentation+1);
        }
        return $string;
	}

	/**
	 * log in a text file
	 *
	 * @param string $filename
	 * @param string $data
	 * @return void
	 */
	public static function writeToFile($filename='', $data='')
	{
		$now = date("Ymd_His");
		$row = sprintf("%s: %s%s",$now, $data, PHP_EOL);

		$filename = EDOC_PATH.$filename;
		file_put_contents ( $filename , $row , $flags=FILE_APPEND );
	}

	// Return the specific redcap_log_event* db table being used for a given project
	public static function getLogEventTable($project_id=null)
	{
		if (!is_numeric($project_id) || $project_id < 1) return 'redcap_log_event';
		$sql = "select log_event_table from redcap_projects where project_id = $project_id";
		$q = db_query($sql);
		if (!$q || db_num_rows($q) < 0) {
			return 'redcap_log_event';
		} else {
			return db_result($q, 0);
		}
	}

	// Return array of all redcap_log_event* db tables
	public static function getLogEventTables()
	{
		$tables = array();
		$sql = "show tables like 'redcap\_log\_event%'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) {
			$tables[] = $row[0];
		}
		// Sort tables alphabetically for consistency
		ksort($tables);
		// Return tables
		return $tables;
	}

	// Return an estimated row count for a given redcap_log_event* db table
	// (use MySQL EXPLAIN to do row count quickly, not not super accurate, which is fine for these purposes)
	public static function getLogEventTableRows($log_table='redcap_log_event')
	{
		$sql = "EXPLAIN SELECT COUNT(log_event_id) FROM $log_table USE INDEX (PRIMARY)";
		$q = db_query($sql);
		return db_result($q, 0, 'rows');
	}

	// Return the table name of the redcap_log_event* db table with fewest rows (based on MySQL EXPLAIN approximation)
	public static function getSmallestLogEventTable()
	{
		$tableRows = array();
		foreach (self::getLogEventTables() as $table) {
			$tableRows[$table] = self::getLogEventTableRows($table);
		}
		$smallest_tables = array_keys($tableRows, min($tableRows));
		$smallest_table = $smallest_tables[0];
		return $smallest_table;
	}

	// Save IP address as hashed value in cache table to prevent automated attacks
	public static function storeHashedIp($ip)
	{
		global $salt, $__SALT__, $project_contact_email, $page_hit_threshold_per_minute, $redcap_version;

		// If not a project-level page, then instead use md5 of $salt in place of $__SALT__
		$projectLevelSalt = ($__SALT__ == '') ? md5($salt) : $__SALT__;

		// Hash the IP (because we shouldn't know the IP of survey respondents)
		$ip_hash = md5($salt . $projectLevelSalt . $ip . $salt);

		// Add IP to the table for this request
		db_query("insert into redcap_ip_cache values ('$ip_hash', '" . NOW . "')");

		// Get timestamp of 1 minute ago
		$oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));

		// Check if ip is found more than a set threshold of times in the past 1 minute
		$sql = "select count(1) from redcap_ip_cache where ip_hash = '$ip_hash' and timestamp > '$oneMinAgo'";
		$q = db_query($sql);
		$total_hits = db_result($q, 0);
		if ($ip != '' && $page_hit_threshold_per_minute != '' && $page_hit_threshold_per_minute > 0 && $total_hits > $page_hit_threshold_per_minute)
		{
			// Threshold reached, so add IP to banned IP table
			db_query("insert into redcap_ip_banned values ('".db_escape($ip)."', '" . NOW . "')");

			// Also send an email to the REDCap admin to notify them of this
			$email = new Message();
			$email->setFrom($project_contact_email);
			$email->setFromName($GLOBALS['project_contact_name']);
			$email->setTo($project_contact_email);
			$email->setSubject('[REDCap] IP address banned due to suspected abuse');
			$this_user = defined("USERID") ? "named <b>".USERID."</b>" : "";
			$this_page = !defined("PROJECT_ID")
				? "<a href=\"".APP_PATH_WEBROOT_FULL."\">REDCap</a>"
				: "<a href=\"" . APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/index.php?pid=" . PROJECT_ID . "\">this REDCap project</a>";
			$msg = "REDCap administrator,<br><br>
				As of " . DateTimeRC::format_ts_from_ymd(NOW) . ", the IP address <b>$ip</b> has been permanently banned from REDCap due to suspected abuse.
				A user $this_user at that IP address was found to have accessed $this_page over $page_hit_threshold_per_minute times within the same minute. If this is incorrect,
				you may un-ban the IP address by executing the SQL query below.<br><br>
				DELETE FROM redcap_ip_banned WHERE ip = '$ip';";
			$email->setBody($msg, true);
			$email->send();
		}
	}

	// Check if IP address has been banned. If so, stop everything NOW.
	public static function checkBannedIp($ip)
	{
		// Check for IP in banned IP table
		$q = db_query("select 1 from redcap_ip_banned where ip = '".db_escape($ip)."' limit 1");
		if (db_num_rows($q) > 0)
		{
			// Output message and stop here to prevent using further server resources (in case of attack)
			header('HTTP/1.1 429'); // Set a "too many requests" HTTP error 429
			exit("Your IP address ($ip) has been banned due to suspected abuse.");
		}
	}
}