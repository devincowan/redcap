<?php



/**
 * Stats is a class of functions used to output general dashboard stats for the REDCap system
 */
class Stats
{
	// Store comma-delimited list of project_id's that should be ignored in some counts
	// (not counted or 'just for fun' purpose or 'deleted' projects)
	private $ignored_project_ids = null;

	// Get delimited list of project_id's that should be ignored in some counts (not counted or 'just for fun' purpose or 'deleted' projects)
	public function getIgnoredProjectIds()
	{
		if ($this->ignored_project_ids == null) {
			$sql = "select project_id from redcap_projects where purpose = '0' or count_project = 0 or date_deleted is not null";
			$this->ignored_project_ids = pre_query($sql);
		}
		return $this->ignored_project_ids;
	}

	// Return array of project_id's using DDP
	public function getDDpProjectIds($fhir=false)
	{
		$ddp_project_ids = array();
		$type = ($fhir !== false) ? "FHIR" : "CUSTOM";
		$sql = "select project_id from redcap_projects where realtime_webservice_enabled = 1
				and project_id not in (".$this->getIgnoredProjectIds().") and realtime_webservice_type = '$type'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$ddp_project_ids[$row['project_id']] = $row['project_id'];
		}
		return $ddp_project_ids;
	}

	// Return array of project_id's using Data Mart
	public function getDataMartProjectIds()
	{
		$dm_project_ids = array();
		$sql = "select project_id from redcap_projects where datamart_enabled = 1
				and project_id not in (".$this->getIgnoredProjectIds().")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$dm_project_ids[] = $row['project_id'];
		}
		return $dm_project_ids;
	}
	// Get count of records that have had data imported from the EHR via Data Mart
	public function getDataMartRecordsImported()
	{
		// Get count of projects using Data Mart
		$dm_project_ids = $this->getDataMartProjectIds();
		$total_data_mart_records_imported = 0;
		$sql = "select count(distinct(concat(m.project_id, '-', m.record)))
				from redcap_projects d, redcap_ehr_import_counts m 
				where d.project_id = m.project_id and m.type = 'CDM' and d.project_id in (" . prep_implode($dm_project_ids) . ")";
		$temp = db_result(db_query($sql), 0);
		$total_data_mart_records_imported += ($temp == '' ? 0 : $temp);
		return $total_data_mart_records_imported;
	}

	// Get count of users that were active in the past X days
	public static function getActiveUsers($days=null)
	{
		if (!is_numeric($days) && $days != null) return false;
		// If null, then return ALL active users since beginning (exclude suspended users)
		$sql_interval = ($days == null) ? "" : "and DATE_SUB('".TODAY."', INTERVAL $days DAY) <= user_lastactivity";
		$sql = "select count(1) from redcap_user_information where username != '' $sql_interval
				and user_lastactivity is not null and user_suspended_time is null order by username";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Get count of users who have logged in to REDCap in the past X days
	public static function getUserLogins($days=null)
	{
		if (!is_numeric($days) && $days != null) return false;
		// If null, then return ALL users who have logged in since beginning
		$sql_interval = ($days == null) ? "" : "and DATE_SUB('".TODAY."', INTERVAL $days DAY) <= user_lastlogin";
		$sql = "select count(1) from redcap_user_information where username != '' $sql_interval
				and user_lastlogin is not null order by username";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// RANDOMIZATION: Get count of production projects using the randomization module (and have a prod alloc table uploaded).
	// Exclude "practice" projects.
	public static function randomizationCount()
	{
		$sql = "select 1 from redcap_projects p, redcap_randomization r, redcap_randomization_allocation a
				where p.status > 0 and p.count_project = 1 and (p.purpose is null or p.purpose > 0)
				and r.project_id = p.project_id and r.rid = a.rid and a.project_status = 1 group by p.project_id";
		$q = db_query($sql);
		return db_num_rows($q);
	}

	// MOBILE APP: Get count of all users that have some kind of Mobile App activity in a project (e.g., init project in app)
	// and still have "mobile_app" privileges.
	// Exclude "practice" projects.
	public function mobileAppUserCount($pastXmonths=null)
	{
		$pastXmonthsSql = "";
		if (is_numeric($pastXmonths)) {
			$xMonthsAgo = date("YmdHis", mktime(date("H"),date("i"),date("s"),date("m")-$pastXmonths,date("d"),date("Y")));
			$sql = "select min(log_event_id) from redcap_log_event where ts > $xMonthsAgo";
			$log_event_id = db_result(db_query($sql), 0);
			if ($log_event_id != '') $pastXmonthsSql = "and log_event_id >= $log_event_id";
		}
		$sql = "select count(distinct(ui_id)) from redcap_mobile_app_log 
				where project_id not in (" . $this->getIgnoredProjectIds() . ") $pastXmonthsSql";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// MOBILE APP: Get count of all projects where a user has initialized a project in the app.
	// Exclude "practice" projects.
	public function mobileAppInitProjectCount()
	{
		$sql = "select count(distinct(project_id)) from redcap_mobile_app_log
				where event = 'INIT_PROJECT' and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// MOBILE APP: Get count of all projects where a user has synced/imported data from the app.
	// Exclude "practice" projects.
	public function mobileAppSyncDataProjectCount()
	{
		$sql = "select count(distinct(project_id)) from redcap_mobile_app_log
				where event = 'SYNC_DATA' and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// TWILIO: Get count of all projects using Twilio telephony services for app.
	// Exclude "practice" projects.
	public function twilioProjectCount()
	{
		if (!$GLOBALS['twilio_enabled_global']) return 0;
		$sql = "select count(project_id) from redcap_projects
				where twilio_enabled = 1 and project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}
	
	// e-Consent: Count projects using e-Consent Framework
	public function countProjectsUsingEconsent()
	{
		$sql = "select count(distinct(p.project_id)) from redcap_projects p, redcap_surveys s 
				where s.project_id = p.project_id and s.pdf_auto_archive = '2' 
				and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}
	
	// e-Consent: Count projects using e-Consent Framework
	public function countEconsentPdfsStored()
	{
		$sql = "select count(1) from redcap_projects p, redcap_surveys s, redcap_edocs_metadata e, redcap_surveys_pdf_archive a
				where s.project_id = p.project_id and s.pdf_auto_archive = '2' and e.project_id = p.project_id
				and a.doc_id = e.doc_id and a.survey_id = s.survey_id and e.delete_date is null
				and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// SURVEY TEXT-TO-SPEECH: Get count of all surveys using the survey TTS feature.
	// Exclude "practice" projects.
	public function surveyTextToSpeechCount()
	{
		$sql = "select count(distinct(s.survey_id)) from redcap_projects p, redcap_surveys s, redcap_metadata m
				where s.text_to_speech = 1 and p.project_id = s.project_id and m.form_name = s.form_name
				and m.project_id = p.project_id and p.project_id not in (" . $this->getIgnoredProjectIds() . ")";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// PUBLICATION MATCHES: Send to consortium the list of pub IDs that have been matched to REDCap projects
	// Parameter $returnParamsOnly will make Post request if FALSE, but if TRUE will not make request but
	// merely return the parameters for that request.
	public static function sendPubMatchList($returnParamsOnly=false)
	{
		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";
		// Set URL to call
		$url = CONSORTIUM_WEBSITE . "collect_stats_pubs.php?rnd982g45av390p9&app=0&hostname=".SERVER_NAME."&ip=".getServerIP()."&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash();
		// Query table to get matches
		$sql = "select distinct s.pubsrc_name, a.pub_id from
				redcap_pub_matches m, redcap_pub_articles a, redcap_pub_sources s
				where m.matched = 1 and m.article_id = a.article_id
				and a.pubsrc_id = s.pubsrc_id order by s.pubsrc_name, a.pub_id";
		$q = db_query($sql);
		$pubsrc_matches = array();
		while ($row = db_fetch_assoc($q))
		{
			$pubsrc_matches[$row['pubsrc_name']][] = $row['pub_id'];
		}
		// Convert sub-array into comma delimited string for each pub src
		foreach ($pubsrc_matches as $src=>$pubids)
		{
			$pubsrc_matches[$src] = implode(",", $pubids);
		}
		// Return params or the result of the Post request
		if ($returnParamsOnly) {
			return array('url'=>$url, 'params'=>$pubsrc_matches);
		} else {
			// Send stats via Post request
			return http_post($url, $pubsrc_matches);
		}
	}


	// Get all the options used in Two Factor Authentication delimited by commas.
	// If not use 2FA, return 'DISABLED'
	public static function getTwoFactorAuthOptionsUsed()
	{
		// Enabled
		if (!$GLOBALS['two_factor_auth_enabled']) return "DISABLED";
		// Init array for storing options
		$options = array();
		if ($GLOBALS['two_factor_auth_duo_enabled']) 			$options[] = "DUO";
		if ($GLOBALS['two_factor_auth_twilio_enabled']) 		$options[] = "TWILIO";
		if ($GLOBALS['two_factor_auth_authenticator_enabled']) 	$options[] = "GOOGLE_AUTHENTICATOR";
		if ($GLOBALS['two_factor_auth_email_enabled']) 			$options[] = "EMAIL";
		// Return options
		return implode(",", $options);
	}

	// SEND SHARED LIBRARY STATS: Obtain local library stats to send to consortium
	// ALSO SEND EXTERNAL LIBRARY STATS (FOR INSTRUMENT ZIPS)
	// Parameter $returnParamsOnly will make Post request if FALSE, but if TRUE will not make request but
	// merely return the parameters for that request.
	public static function sendSharedLibraryStats($returnParamsOnly=false)
	{
		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";
		// Set URL to call
		$url = CONSORTIUM_WEBSITE . "collect_stats_library.php?rnd982g45av390r1&app=0&hostname=".SERVER_NAME."&ip=".getServerIP()."&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash();
		// Initialize vars
		$params = array("total"=>array("dev_up"=>0, "dev_down"=>0, "prod_up"=>0, "prod_down"=>0));
		// Uploads for dev projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 0 and l.type = 2 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['dev_up'] = $row['count'];
			$params['total']['dev_up'] += $row['count'];
		}
		// Downloads for dev projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 0 and l.type = 1 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['dev_down'] = $row['count'];
			$params['total']['dev_down'] += $row['count'];
		}
		// Uploads for prod projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 1 and l.type = 2 group by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['prod_up'] = $row['count'];
			$params['total']['prod_up'] += $row['count'];
		}
		// Downloads for prod projects
		$sql = "select l.library_id, count(1) as count from redcap_library_map l, redcap_projects p where p.project_id = l.project_id
				and p.status = 1 and l.type = 1 group by l.library_id order by l.library_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params[$row['library_id']]['prod_down'] = $row['count'];
			$params['total']['prod_down'] += $row['count'];
		}
		// Convert array to string for passing below
		$params2 = array();
		foreach ($params as $lib_id=>$values)
		{
			$params2[$lib_id] = (isset($values['dev_up'])    ? $values['dev_up']    : "0") . ","
							  . (isset($values['dev_down'])  ? $values['dev_down']  : "0") . ","
							  . (isset($values['prod_up'])   ? $values['prod_up']   : "0") . ","
							  . (isset($values['prod_down']) ? $values['prod_down'] : "0");
		}

		// Get instrument zip stats for external libraries
		$params2['instrument_zip_ext_libs'] = array();
		$params2['instrument_zip_origins'] = array();
		$sql = "select a.author_name, z.instrument_id, z.upload_count
				from redcap_instrument_zip_authors a, redcap_instrument_zip z where a.iza_id = z.iza_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params2['instrument_zip_ext_libs'][$row['author_name']][$row['instrument_id']] = $row['upload_count'];
		}
		$sql = "select * from redcap_instrument_zip_origins";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$params2['instrument_zip_origins'][$row['server_name']] = $row['upload_count'];
		}
		$params2['instrument_zip_ext_libs'] = serialize($params2['instrument_zip_ext_libs']);
		$params2['instrument_zip_origins'] = serialize($params2['instrument_zip_origins']);


		// Return params or the result of the Post request
		if ($returnParamsOnly) {
			return array('url'=>$url, 'params'=>$params2);
		} else {
			// Send stats via Post request
			return http_post($url, $params2);
		}
	}

	// Return a one-way hash of the server's unique key (i.e. $salt) for server identification purposes
	public static function getServerKeyHash()
	{
		global $salt;
		return md5($salt);
	}

	// Return URL for reporting stats to consortium. Includes all calculated stat counts.
	public function cacheStatsReportingUrl()
	{
		global $auto_report_stats;
		$url = $this->getUrlReportingStats(!$auto_report_stats, true);
		// Return TRUE if returned a URL
		return ($url != '');
	}

	// Return array of external modules counts with the modules installed and how many dev/prod projects have them enabled.
	// Note: These counts ONLY include those that have been downloaded from the REDCap Repo.
	public function getExternalModuleCounts()
	{
		$modules = array();
		$sql = "select d.module_id, if(p.status is null, if(y.key is not null, 'ALL', 'SYSTEM'), if(p.status > 0, 1, 0)) as status, count(*) as status_count 
				from redcap_external_modules m, redcap_external_modules_downloads d , redcap_external_module_settings s
				left join redcap_external_module_settings x on s.external_module_id = x.external_module_id and x.project_id is not null and x.key = 'enabled' and x.value = 'true'
				left join redcap_external_module_settings y on s.external_module_id = y.external_module_id and y.project_id is null and y.key = 'enabled' and y.value = 'true'
				left join redcap_projects p on x.project_id = p.project_id and p.project_id not in (".$this->getIgnoredProjectIds().")
				where m.directory_prefix = reverse(substr(reverse(d.module_name), locate('v_', reverse(d.module_name))+2)) 
				and s.external_module_id = m.external_module_id and s.project_id is null and s.key = 'version' and d.time_deleted is null
				and ((x.project_id is null and p.project_id is null) or (x.project_id is not null and p.project_id is not null) or y.key is not null)
				group by d.module_id, p.status";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$modules[$row['module_id']][$row['status']] = $row['status_count'];
		}
		return $modules;
	}

    // Get count of alerts and projects utilizing them
    public function getAlertCounts()
    {
        $sql = "SELECT count(*) as alerts, count(distinct(a.project_id)) as alerts_projects 
                FROM redcap_alerts a WHERE a.email_deleted = 0 AND a.project_id not in (" . $this->getIgnoredProjectIds() . ")";
        $q = db_query($sql);
        $total_alerts = db_result($q, 0, 'alerts');
        $total_alerts_projects = db_result($q, 0, 'alerts_projects');
        $sql = "SELECT count(distinct(a.project_id))
                FROM redcap_alerts a, redcap_projects p 
                WHERE a.email_deleted = 0 AND a.project_id not in (" . $this->getIgnoredProjectIds() . ")
                and a.project_id = p.project_id and p.status > 0";
        $q = db_query($sql);
        $total_alerts_projects_prod = db_result($q, 0);
        return array($total_alerts, $total_alerts_projects, $total_alerts_projects_prod);
    }

	// Return URL for reporting stats to consortium. Includes all calculated stat counts.
	public function getUrlReportingStats($reportManually=false, $forceGenerateNew=false)
	{
		global $dts_enabled_global, $realtime_webservice_global_enabled, $auth_meth, $redcap_version, $fhir_ddp_enabled, $fhir_data_mart_create_project,
			   $institution, $homepage_contact, $homepage_contact_email, $site_org_type, $report_stats_url, $aws_quickstart, $azure_quickstart;
			   
		// If we're not force-generating a new url, then return the cached URL
		if (!$forceGenerateNew && $report_stats_url != '')
		{
			return $report_stats_url;
		}

		// Get server's IP address
		$server_ip = getServerIP();

		// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
		$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";

		// Get project count
		$num_prototypes = 0;
		$num_production = 0;
		$num_inactive   = 0;
		$num_archived   = 0;
		$q = db_query("select status, count(status) as count from redcap_projects where project_id not in (".$this->getIgnoredProjectIds().") group by status");
		while ($row = db_fetch_assoc($q)) {
			if ($row['status'] == '0') $num_prototypes = $row['count'];
			if ($row['status'] == '1') $num_production = $row['count'];
			if ($row['status'] == '2') $num_inactive   = $row['count'];
			if ($row['status'] == '3') $num_archived   = $row['count'];
		}

		// Get counts of project purposes
		$purpose_other = 0;
		$purpose_research = 0;
		$purpose_qualimprove = 0;
		$purpose_operational = 0;
		$q = db_query("select purpose, count(purpose) as count from redcap_projects where project_id not in (".$this->getIgnoredProjectIds().") group by purpose");
		while ($row = db_fetch_array($q))
		{
			switch ($row['purpose'])
			{
				case '1': $purpose_other = $row['count']; break;
				case '2': $purpose_research = $row['count']; break;
				case '3': $purpose_qualimprove = $row['count']; break;
				case '4': $purpose_operational = $row['count']; break;
			}
		}

		// DTS: Get count of production projects utilizing DTS
		$dts_count = 0;
		if ($dts_enabled_global)
		{
			$q = db_query("select count(1) from redcap_projects where status > 0 and dts_enabled = 1 and project_id not in (".$this->getIgnoredProjectIds().")");
			$dts_count = db_result($q, 0);
		}

		// DDP: Get count of production projects utilizing DDP
		$ddp_count = $ddp_records_imported = 0;
		if ($realtime_webservice_global_enabled)
		{
			$q = db_query("select count(1) from redcap_projects where status > 0 and realtime_webservice_enabled = 1 and realtime_webservice_type = 'CUSTOM' and project_id not in (".$this->getIgnoredProjectIds().")");
			$ddp_count = db_result($q, 0);
			// Get count of records that have had data imported from source system in DDP-enabled projects
			$sql = "select count(distinct(concat(p.project_id, '-', r.record)))
					from redcap_projects p, redcap_ddp_records r, redcap_ddp_records_data d
					where p.status > 0 and p.realtime_webservice_enabled = 1 and realtime_webservice_type = 'CUSTOM' 
					and p.project_id = r.project_id and r.mr_id = d.mr_id
					and p.project_id not in (".$this->getIgnoredProjectIds().")";
			$ddp_records_imported = db_result(db_query($sql), 0);
		}

		// DDP on FHIR: Get count of production projects utilizing DDP
		$ddp_fhir_count = $ddp_fhir_records_imported = 0;
		if ($fhir_ddp_enabled)
		{
			$q = db_query("select count(1) from redcap_projects where status > 0 and realtime_webservice_enabled = 1 and realtime_webservice_type = 'FHIR' and project_id not in (".$this->getIgnoredProjectIds().")");
			$ddp_fhir_count = db_result($q, 0);
			// Get count of records that have had data imported from source system in DDP-enabled projects
			$sql = "select count(distinct(concat(p.project_id, '-', r.record)))
					from redcap_projects p, redcap_ddp_records r, redcap_ddp_records_data d
					where p.status > 0 and p.realtime_webservice_enabled = 1 and realtime_webservice_type = 'FHIR' 
					and p.project_id = r.project_id and r.mr_id = d.mr_id
					and p.project_id not in (".$this->getIgnoredProjectIds().")";
			$ddp_fhir_records_imported = db_result(db_query($sql), 0);
		}

		// Randomization: Get count of production projects using the randomization module (and have a prod alloc table uploaded)
		$rand_count = Stats::randomizationCount();

		// Get user count
		$num_users = db_result(db_query("select count(1) from redcap_user_information"), 0);

		// Get count of projects using Double Data Entry module (production only)
		$sql = "select count(1) from redcap_projects where status > 0 and double_data_entry = 1 and project_id not in (".$this->getIgnoredProjectIds().")";
		$total_dde = db_result(db_query($sql), 0);

		// Count CAT assessment responses (partial and completed)
		$cat_responses_dev = $cat_responses_prod = 0;
		$sql = "select p.status, count(1) as count
				from redcap_library_map l, redcap_surveys s, redcap_projects p, redcap_surveys_participants sp, redcap_surveys_response r
				where p.project_id not in (".$this->getIgnoredProjectIds().") and l.promis_key is not null
				and l.promis_key != '' and s.project_id = l.project_id and p.project_id = s.project_id
				and s.survey_id = sp.survey_id and sp.participant_id = r.participant_id
				and r.first_submit_time is not null and s.form_name = l.form_name
				group by p.status";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['status'] == '0') {
				// Dev
				$cat_responses_dev += $row['count'];
			} else {
				// Prod (includes Inactive and Archived)
				$cat_responses_prod += $row['count'];
			}
		}
        // Get count of alerts and projects utilizing them
        list ($total_alerts, $total_alerts_projects, $total_alerts_projects_prod) = $this->getAlertCounts();
		// Data Mart
		$data_mart_projects = $data_mart_records = "";
		if ($fhir_data_mart_create_project) {
			$data_mart_projects = count($this->getDataMartProjectIds());
			$data_mart_records = $this->getDataMartRecordsImported();
		}

		// Send site stats to the REDCap Consortium and get response back
		$url = CONSORTIUM_WEBSITE."collect_stats.php?hostname=".SERVER_NAME."&ip=$server_ip"
			 . "&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash()
			 . "&num_prots=$num_prototypes&num_prods=$num_production&num_archived=$num_archived"
			 . "&rnd982g4078393ae839z1".($reportManually ? "" : "_auto")
			 . "&purposes=$purpose_other,$purpose_research,$purpose_qualimprove,$purpose_operational"
			 . "&num_inactive=$num_inactive&num_users=$num_users&auth_meth=$auth_meth&version=$redcap_version"
			 . "&activeusers1m=".Stats::getActiveUsers(30)."&activeusers6m=".Stats::getActiveUsers(183)."&activeuserstotal=".Stats::getActiveUsers()
			 . "&usersloggedin1m=".Stats::getUserLogins(30)."&usersloggedin6m=".Stats::getUserLogins(183)."&usersloggedintotal=".Stats::getUserLogins()
			 . "&hostlabel=" . urlencode($institution)
			 . "&homepage_contact=".urlencode($homepage_contact)."&homepage_contact_email=$homepage_contact_email"
			 . "&dts=$dts_count&ddp=$ddp_count&ddp_records=$ddp_records_imported&ddp_fhir=$ddp_fhir_count&ddp_fhir_records=$ddp_fhir_records_imported"
			 . "&data_mart=$data_mart_projects&data_mart_records=$data_mart_records"
			 . "&rand=$rand_count&dde=$total_dde&parentchild=0"
			 . "&cats_dev=$cat_responses_dev&cats_prod=$cat_responses_prod&mobile_app_users=".$this->mobileAppUserCount()
			 . "&mobile_app_users1m=".$this->mobileAppUserCount(1)."&mobile_app_users6m=".$this->mobileAppUserCount(6)
			 . "&mobile_app_projects_init=".$this->mobileAppInitProjectCount()."&mobile_app_projects_sync=".$this->mobileAppSyncDataProjectCount()
			 . "&two_factor=".Stats::getTwoFactorAuthOptionsUsed()."&twilio_projects=".$this->twilioProjectCount()
			 . "&full_url=".urlencode(APP_PATH_WEBROOT_FULL)."&site_org_type=".urlencode($site_org_type)
			 . "&econsent_projects=".$this->countProjectsUsingEconsent()."&econsent_files=".$this->countEconsentPdfsStored()
             . "&aws_quickstart=$aws_quickstart&azure_quickstart=$azure_quickstart"
             . "&alerts=$total_alerts&alerts_projects=$total_alerts_projects&alerts_projects_prod=$total_alerts_projects_prod"
			 . "&php_version=".PHP_VERSION."&mysql_version=".db_get_version()
             // Keep the modules param at the very end because it is chopped off if the URL is too long
			 . "&modules=".urlencode(json_encode($this->getExternalModuleCounts()));

		// Save value in redcap_config table
		$sql = "update redcap_config set value = '".db_escape($url)."' where field_name = 'report_stats_url'";
		db_query($sql);

		return $url;
	}

	// Check if need to report institutional stats to REDCap consortium
	public static function checkReportStats()
	{
		global $auto_report_stats, $auto_report_stats_last_sent;
		// If auto stat reporting is set, check if more than 7 days have passed in order to report current stats
		// Only do checking when user is on a project's index page
		if ($auto_report_stats && (PAGE == "index.php" || PAGE == "ProjectSetup/index.php" || strpos(PAGE, "ControlCenter") === 0))
		{
			list ($yyyy, $mm, $dd) = explode("-", $auto_report_stats_last_sent);
			$daydiff = ceil((mktime(0, 0, 0, date("m"), date("d"), date("Y")) - mktime(0, 0, 0, $mm, $dd, $yyyy)) / (3600 * 24));
			// If not reported in 7 days, trigger AJAX call to report them
			if ($daydiff >= 7)
			{
				// Instantiate Stats object
				$Stats = new Stats();
				// Render javascript for AJAX call
				?>
				<script type='text/javascript'>
                    $(function(){
                        reportStatsAjax('<?php print js_escape($Stats->getUrlReportingStats(false)) ?>');
                    });
				</script>
				<?php
			}
		}
	}
}