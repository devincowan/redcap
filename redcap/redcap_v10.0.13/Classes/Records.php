<?php


/**
 * RECORDS Class
 */
class Records
{
	// Use replacements for \r and \n when doing data exports that write to serialized file to force one record-event per line
	const RC_NL_R = "{~RC_R~}";
	const RC_NL_N = "{~RC_N~}";
	
	// Delete query for record list cache
	const SQL_DELETE_RECORD_LIST = "delete from redcap_record_list where project_id = ";

	// Set export records batch size
	const EXPORT_BATCH_SIZE_CLASSIC = 5000;
	const EXPORT_BATCH_SIZE_REPEATING = 1000;

	//Function for deleting a record (if option is enabled) - if multiple arms exist, will only delete record for current arm
	public static function deleteRecord($fetched, $table_pk, $multiple_arms, $randomization, $status,
										$require_change_reason, $arm_id=null, $appendLoggingDescription="", $allow_delete_record_from_log=0)
	{
	    $Proj = new Project(PROJECT_ID);
		// Collect all queries in array for logging
		$sql_all = array();
		// If $arm_id exists, tack on all event_ids from that arm
		if (is_numeric($arm_id)) {
			$eventid_list = pre_query("select e.event_id from redcap_events_metadata e, redcap_events_arms a where a.arm_id = e.arm_id and a.arm_id = $arm_id and a.project_id = ".PROJECT_ID);
		} else {
			$eventid_list = pre_query("select e.event_id from redcap_events_metadata e, redcap_events_arms a where a.arm_id = e.arm_id and a.project_id = ".PROJECT_ID);
		}
		$event_sql = $event_sql_d = "";
		if ($multiple_arms) {
			$event_sql = "AND event_id IN ($eventid_list)";
			$event_sql_d = "AND d.event_id IN ($eventid_list)";
		}
		// "Delete" edocs for 'file' field type data (keep its row in table so actual files can be deleted later from web server, if needed).
		// NOTE: If *somehow* another record has the same doc_id attached to it (not sure how this would happen), then do NOT
		// set the file to be deleted (hence the left join of d2).
		$sql_all[] = $sql = "update redcap_metadata m, redcap_edocs_metadata e, redcap_data d left join redcap_data d2
							on d2.project_id = d.project_id and d2.value = d.value and d2.field_name = d.field_name and d2.record != d.record
							set e.delete_date = '".NOW."' where m.project_id = " . PROJECT_ID . " and m.project_id = d.project_id
							and e.project_id = m.project_id and m.element_type = 'file' and d.field_name = m.field_name
							and d.value = e.doc_id and e.delete_date is null and d.record = '" . db_escape($fetched) . "'
							and d2.project_id is null $event_sql_d";
		db_query($sql);
		// "Delete" edoc attachments for Data Resolution Workflow (keep its record in table so actual files can be deleted later from web server, if needed)
		$sql_all[] = $sql = "update redcap_data_quality_status s, redcap_data_quality_resolutions r, redcap_edocs_metadata m
							set m.delete_date = '".NOW."' where s.project_id = " . PROJECT_ID . " and s.project_id = m.project_id
							and s.record = '" . db_escape($fetched) . "' $event_sql and s.status_id = r.status_id
							and r.upload_doc_id = m.doc_id and m.delete_date is null";
		db_query($sql);
		// Delete record from data table
		$sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' $event_sql";
		db_query($sql);
		// Also delete from locking_data and esignatures tables
		$sql_all[] = $sql = "DELETE FROM redcap_locking_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' $event_sql";
		db_query($sql);
		$sql_all[] = $sql = "DELETE FROM redcap_locking_records WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' AND arm_id = $arm_id";
		db_query($sql);
		$sql_all[] = $sql = "DELETE FROM redcap_esignatures WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' $event_sql";
		db_query($sql);
		// Delete from calendar
		$sql_all[] = $sql = "DELETE FROM redcap_events_calendar WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' $event_sql";
		db_query($sql);
		// Delete records in survey invitation queue table
		// Get all ssq_id's to delete (based upon both email_id and ssq_id)
		$subsql =  "select q.ssq_id from redcap_surveys_scheduler_queue q, redcap_surveys_emails e,
					redcap_surveys_emails_recipients r, redcap_surveys_participants p
					where q.record = '" . db_escape($fetched) . "' and q.email_recip_id = r.email_recip_id and e.email_id = r.email_id
					and r.participant_id = p.participant_id and p.event_id in ($eventid_list)";
		// Delete all ssq_id's
		$subsql2 = pre_query($subsql);
		if ($subsql2 != "''") {
			$sql_all[] = $sql = "delete from redcap_surveys_scheduler_queue where ssq_id in ($subsql2)";
			db_query($sql);
		}
		// Delete responses from survey response table for this arm
		$sql = "select r.response_id, p.participant_id, p.participant_email
				from redcap_surveys s, redcap_surveys_response r, redcap_surveys_participants p
				where s.project_id = " . PROJECT_ID . " and r.record = '" . db_escape($fetched) . "'
				and s.survey_id = p.survey_id and p.participant_id = r.participant_id and p.event_id in ($eventid_list)";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			// Get all responses to add them to array
			$response_ids = array();
			while ($row = db_fetch_assoc($q))
			{
				// If email is blank string (rather than null or an email address), then it's a record's follow-up survey "participant",
				// so we can remove it from the participants table, which will also cascade to delete entries in response table.
				if ($row['participant_email'] === '') {
						// Delete from participants table (which will cascade delete responses in response table)
						$sql_all[] = $sql = "DELETE FROM redcap_surveys_participants WHERE participant_id = ".$row['participant_id'];
						db_query($sql);
				} else {
						// Add to response_id array
						$response_ids[] = $row['response_id'];
				}
			}
			// Remove responses
			if (!empty($response_ids)) {
					$sql_all[] = $sql = "delete from redcap_surveys_response where response_id in (".implode(",", $response_ids).")";
					db_query($sql);
			}
		}
		// Delete record from randomization allocation table (if have randomization module enabled)
		if ($randomization && Randomization::setupStatus())
		{
			// If we have multiple arms, then only undo allocation if record is being deleted from the same arm
			// that contains the randomization field.
			$removeRandomizationAllocation = true;
			if ($multiple_arms) {
				$Proj = new Project(PROJECT_ID);
				$randAttr = Randomization::getRandomizationAttributes();
				$randomizationEventId = $randAttr['targetEvent'];
				// Is randomization field on the same arm as the arm we're deleting the record from?
				$removeRandomizationAllocation = ($Proj->eventInfo[$randomizationEventId]['arm_id'] == $arm_id);
			}
			// Remove randomization allocation
			if ($removeRandomizationAllocation) 
			{
				$sql_all[] = $sql = "update redcap_randomization r, redcap_randomization_allocation a set a.is_used_by = null
									 where r.project_id = " . PROJECT_ID . " and r.rid = a.rid and a.project_status = $status
									 and a.is_used_by = '" . db_escape($fetched) . "'";
				db_query($sql);
			}
		}
		// Delete record from Data Quality status table
		$sql_all[] = $sql = "DELETE FROM redcap_data_quality_status WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' $event_sql";
		db_query($sql);
		// Delete all records in redcap_ddp_records
		$sql_all[] = $sql = "DELETE FROM redcap_ddp_records WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'";
		db_query($sql);
		// Delete all records in redcap_surveys_queue_hashes
		$sql_all[] = $sql = "DELETE FROM redcap_surveys_queue_hashes WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'";
		db_query($sql);
		// Delete all records in redcap_new_record_cache
		$sql_all[] = $sql = "DELETE FROM redcap_new_record_cache WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'";
		db_query($sql);
		// Delete record from redcap_surveys_pdf_archive table
		$sql_all[] = $sql = "DELETE FROM redcap_surveys_pdf_archive WHERE record = '" . db_escape($fetched) . "' AND event_id IN ($eventid_list)";
		db_query($sql);
        // Delete record in alerts tables
        $sql_all[] = $sql = "DELETE FROM redcap_alerts_recurrence
                             WHERE event_id in (".implode(', ', array_keys($Proj->eventInfo)).") 
                             AND record = '" . db_escape($fetched) . "' AND event_id IN ($eventid_list)";
        db_query($sql);
        $sql_all[] = $sql = "DELETE FROM redcap_alerts_sent
                             WHERE event_id in (".implode(', ', array_keys($Proj->eventInfo)).") 
                             AND record = '" . db_escape($fetched) . "' AND event_id IN ($eventid_list)";
        db_query($sql);
		// If we're required to provide a reason for changing data, then log it here before the record is deleted.
		$change_reason = ($require_change_reason && isset($_POST['change-reason'])) ? $_POST['change-reason'] : "";
		// Now delete all this record's logging for the current arm IF the project-level setting is enabled
		$appendLoggingDescription2 = "";
		if ($allow_delete_record_from_log) {
			$sql_all[] = $sql = "UPDATE ".Logging::getLogEventTable(PROJECT_ID)." 
								 SET data_values = '".db_escape("[*DATA REMOVED*]")."'
								 WHERE project_id = " . PROJECT_ID . " and pk = '".db_escape($fetched)."'
								 AND (event in ('ESIGNATURE','LOCK_RECORD','UPDATE','INSERT','DELETE','DOC_UPLOAD','DOC_DELETE','OTHER') 
									OR (event = 'MANAGE' and object_type in ('redcap_data', 'redcap_edocs_metadata')))
								 AND (event_id is null OR event_id IN ($eventid_list))";
			db_query($sql);
			$appendLoggingDescription2 = "\n[All data values were removed from this record's logging activity.]";
		}
		//Logging
		Logging::logEvent(implode(";\n", $sql_all),"redcap_data","delete",$fetched,"$table_pk = '$fetched'$appendLoggingDescription2","Delete record{$appendLoggingDescription}",$change_reason);
	}

	// Return count of all records in project
	public static function getRecordCount($project_id)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;	
		$Proj = new Project($project_id);
		// Get cached record count, else query the data table
		$record_count = self::getCachedRecordCount($Proj->project_id);
		if ($record_count === null) {
			// Query to get record count from table
			$sql = "select count(distinct(record)) from redcap_data where project_id = ".$Proj->project_id."
					and field_name = '" . db_escape($Proj->table_pk) . "' 
					and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
			$q = db_query($sql);
			if (!$q) return false;
			// Set record count
			$record_count = db_result($q, 0);
			// Add this count to the cache table to retrieve it faster next time
			db_query("replace into redcap_record_counts (project_id, record_count, time_of_count) 
					  values (" . $Proj->project_id . ", $record_count, '" . NOW . "')");
		}
		// Return count
		return $record_count;
	}

	// Obtain the next record in a record list after the one provided (for a given arm, if multi-arms exist).
	// Return null if no next record exists.
	public static function getNextRecord($project_id, $record, $arm=null)
	{
		global $user_rights;
		// Get all records for this arm
		$records = self::getRecordList($project_id, $user_rights['group_id'], true, false, $arm);
		// Loop through fields until we find our record, then return the one after it
		$foundRecord = false;
		$record = $record."";
		foreach ($records as $this_record) {
			if ($foundRecord) {
				return $this_record;
			}
			if ($this_record."" === $record) {
				$foundRecord = true;
			}
		}
		// Didn't find it, so return null
		return null;
	}

	// Return array record list with record name as key and DAG group_id as value (use "0" as default if not assigned to a DAG)
	// By default, only return records in DAGs, or set $returnAllRecords=true to return all records with DAG designations or "0" for none.
	public static function getRecordListAllDags($project_id, $returnAllRecords=false, $onlyTheseRecords=array())
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;	
		$Proj = new Project($project_id);
		// Add DAGs to array
		$groups = $Proj->getGroups();	
		$recordDag = array();
		$fields  = $returnAllRecords ? "'{$Proj->table_pk}', '__GROUPID__'" : "'__GROUPID__'";
		$orderBy = $returnAllRecords ? "order by record, field_name desc" : "";
		// If needing to only return a subset of records (via $onlyTheseRecords), then add to query
		$checkRecordNameEachLoop = false;
		$sqlr = "";
		if (!empty($onlyTheseRecords)) {
			// If we're querying more than 25% of the project's records, then don't put record names in query but check via PHP each loop.
			$recordCount = self::getRecordCount($project_id);
			$checkRecordNameEachLoop = ((count($onlyTheseRecords) / $recordCount) > 0.25);
			if (!$checkRecordNameEachLoop) {
				$sqlr = "and record in (".prep_implode($onlyTheseRecords).")";
			}
		}
		// Determine which records are in which group
		$sql = "select record, field_name, value from redcap_data 
				where project_id = $project_id and field_name in ($fields) $sqlr
				group by record, field_name $orderBy";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			if ($row['record'] == '') continue;
			$row['record'] = fixUTF8($row['record'], true);
			if ($checkRecordNameEachLoop && !in_array($row['record'], $onlyTheseRecords)) continue;
			if ($returnAllRecords) {
				if (!isset($recordDag[$row['record']]) && $row['field_name'] != '__GROUPID__') {
					$recordDag[$row['record']] = 0;
				} elseif ($row['field_name'] == '__GROUPID__') {
					$recordDag[$row['record']] = (isset($groups[$row['value']]) ? $row['value'] : 0);
				}
			} else {
				if (!isset($groups[$row['value']])) continue;
				$recordDag[$row['record']] = $row['value'];
			}
		}
		return $recordDag;
	}

	// Return list of all record names as an array with the arms that they exist on (uses data table, not record list cache)
	public static function getArmsForAllRecords($project_id)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Get $Proj object
		$Proj = new Project($project_id);
		// Put list in array (arm is first key and record name is second key)
		$records = array();
		// Query to get resources from table
		$sql = "select distinct a.arm_num, d.record
				from redcap_data d, redcap_events_metadata e, redcap_events_arms a
				where a.project_id = $project_id and a.project_id = d.project_id
				and a.arm_id = e.arm_id and e.event_id = d.event_id and d.field_name = '" . db_escape($Proj->table_pk) . "'
				and d.event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
		$q = db_query($sql, null, MYSQLI_USE_RESULT); // Use unbuffered query method
		if (!$q) return false;
		while ($row = db_fetch_assoc($q)) {
			$row['record'] = label_decode($row['record']);
			$records[$row['record']][] = $row['arm_num'];
		}
		// Sort by record
		natcaseksort($records);
		// Sort arms within each record
		foreach ($records as &$arms) sort($arms);
		// Return record list
		return $records;
	}

	// Return list of all record names as an array for EACH arm (assuming multiple arms)
	public static function getRecordListPerArm($project_id, $records_input=array(), $arm=null, $useCache=true)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;	
		// Get $Proj object
		$Proj = new Project($project_id);
		// Put list in array (arm is first key and record name is second key)
		$records = array();
		if ($useCache) {
			// Use record_list table to get record list
			$arms = ($arm == null) ? array_keys($Proj->events) : array($arm);
			$checkRecordsInput = !empty($records_input);
			foreach ($arms as $thisArm) {
				// Arm is first key and record name is second key in array
				$recordsThisArm = self::getRecordList($project_id, null, false, false, $thisArm);
				if ($checkRecordsInput) {
					foreach ($recordsThisArm as $key=>$thisRecord) {
						if (!in_array($thisRecord, $records_input)) {
							unset($recordsThisArm[$key]);
						}
					}
				}
				$records[$thisArm] = $recordsThisArm;
			}
		} else {
			// Query to get resources from table
			$sql = "select distinct a.arm_id, a.arm_num, d.record
					from redcap_data d, redcap_events_metadata e, redcap_events_arms a
					where a.project_id = $project_id and a.project_id = d.project_id
					and a.arm_id = e.arm_id and e.event_id = d.event_id and d.field_name = '" . db_escape($Proj->table_pk) . "'
					and d.event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
			if (!empty($records_input)) $sql .= " and d.record in (" . prep_implode($records_input) . ")";
			if (!empty($arm)) $sql .= " and a.arm_num = '" . db_escape($arm) . "'";
			$q = db_query($sql);
			if (!$q) return false;
			if (db_num_rows($q) > 0) {
				while ($row = db_fetch_assoc($q)) {
					// Arm is first key and record name is second key in array
					$records[$row['arm_num']][$row['record']] = true;
				}
			}
			// Sort by arm
			ksort($records);
			foreach ($records as $this_arm=>&$records2) {
				// Sort by record name within each arm
				natcaseksort($records2);
			}
			unset($records2);
		}
		// Return record list
		return $records;
	}

	// Return list of all record names as an array for a single DAG
	public static function getRecordListSingleDag($project_id, $dag_id)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		return self::getRecordList($project_id, $dag_id);
		/* 
		// Get $Proj object
		$Proj = new Project($project_id);
		// Generate from data table
		$records = array();
		$sql = "select record from redcap_data where project_id = $project_id
			   and field_name = '__GROUPID__' and value = '".db_escape($dag_id)."'
			   and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				$records[$row['record']] = $row['record'];
			}
		}
		// Order records
		natcaseksort($records);
		// Return array
		return $records;
		 */
	}
	
	// Determine if a query is already in the MySQL process list. Return boolean.
	public static function isQueryCurrentlyRunning($sql, $matchBeginningOnly=false)
	{
		$orig = array("\r\n", "\r", "\n", "\t");
		$repl = array(" ", " ", " ", " ");
		// Format query
		$sql = trim(str_replace($orig, $repl, $sql));
		if ($sql == '') return false;
		// Get processlist
		$result = db_query("SHOW FULL PROCESSLIST");
		while ($row = db_fetch_assoc($result)) {
			$thisQuery = trim(str_replace($orig, $repl, $row['Info']));
			if ($thisQuery == '') continue;
			// If exists in the processlist, then return true
			if ($matchBeginningOnly) {
				// Match only beginning of query
				if (strpos($thisQuery, $sql) === 0) return true;
			} else {
				// Match full query
				if ($thisQuery == $sql) return true;
			}
		}
		// If we got this far, query is not already running
		return false;
	}

	// Return list of all record names as an "array" or as a "csv" string. Returns record name as both key and value.
	// If $returnRecordEventPairs=true, it will record "record-event_id" as key and "record - event" as value.
	public static function getRecordList($project_id=null, $filterByGroupID=array(), $filterByDDEuser=false, $returnRecordEventPairs=false, 
										 $arm=null, $limit=null, $limitOffset=0, $filterByRecords=array(), $recursive=false, $recordBeginsWith='', $returnNoOutput=false)
	{
		global $user_rights, $isAjax;
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Format $filterByGroupID as array
		if (!empty($filterByGroupID) && is_numeric($filterByGroupID)) {
			$filterByGroupID = array($filterByGroupID);
		} elseif (!is_array($filterByGroupID)) {
			$filterByGroupID = array();
		}
		// Format $filterByRecords as array
		if (!empty($filterByRecords) && !is_array($filterByRecords)) {
			$filterByRecords = array($filterByRecords);
		} elseif (!is_array($filterByRecords)) {
			$filterByRecords = array();
		}
		// Get $Proj object
		$Proj = new Project($project_id);
		// Set events
		$events = (!empty($arm) && isset($Proj->events[$arm])) ? array_keys($Proj->events[$arm]['events']) : array_keys($Proj->eventInfo);
		// Determine if using Double Data Entry and if DDE user (if so, add --# to end of Study ID when querying data table)
		$isDDEuser = false; // default
		if ($filterByDDEuser) {
			$isDDEuser = ($Proj->project['double_data_entry'] && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
		}
		// Set "record" field in query if a DDE user
		$record_dde_field = ($isDDEuser) ? "substr(record,1,length(record)-3) as record" : "record";
		$record_dde_where = ($isDDEuser) ? "and record like '%--{$user_rights['double_data']}'" : "";
		// Perform a LIKE for record name (if applicable)
		$recordBeginsWith = trim($recordBeginsWith);
		$record_like = '';
		if ($recordBeginsWith != '') {
			$record_like = "and record like '".db_escape($recordBeginsWith)."%'";
		}
		// Put list in array
		$records = array();
		// Flag if need to build record list from data table
		$buildListFromDataTable = true;
		// Set limit (and offset)
		$limitSql = "";
		if (is_numeric($limit)) {
			$limitSql = " limit " . ((is_numeric($limitOffset) && $limitOffset > 0) ? "$limitOffset, $limit" : $limit);
		}

		// See if the record list has alrady been cached. If so, use it.
		$recordListCacheStatus = self::getRecordListCacheStatus($project_id);

		// If not exist in the record count table yet, then run getRecordCount() to add to table
		if ($recordListCacheStatus == null) {
			self::getRecordCount($project_id);
			$recordListCacheStatus = 'NOT_STARTED';
		}

		if ($recordListCacheStatus == 'FIX_SORT') 
		{
			// All the rows are correct except for the SORT column, which should be fixed
			if (self::fixOrderRecordListCache($project_id)) {
				$recordListCacheStatus = 'COMPLETE';
			} else {
				$buildListFromDataTable = true;	
			}
		}

		if ($recordListCacheStatus == 'COMPLETE')
		{
			// GET RECORD LIST FROM RECORD_LIST TABLE
			$buildListFromDataTable = false;
			// Query to get record list from table
			$sql = "select distinct $record_dde_field from redcap_record_list 
					where project_id = $project_id $record_dde_where $record_like";
			if (is_numeric($arm)) $sql .= " and arm = $arm";
			if (!empty($filterByGroupID)) $sql .= " and dag_id in (".prep_implode($filterByGroupID).")";
			if (!empty($filterByRecords)) $sql .= " and record in (".prep_implode($filterByRecords).")";
			$sql .= " order by sort $limitSql";
			$q = db_query($sql, null, MYSQLI_USE_RESULT); // Use unbuffered query method
			if (!$q) return false;
			while ($row = db_fetch_assoc($q)) {
				$records[$row['record']] = $row['record'];
			}
			// Return record list
			return $records;
		}

		// BUILD THE RECORD LIST, BUT ONLY IF WE'RE ON ROUTE "DataEntryController:buildRecordListCache"
		if ($recordListCacheStatus == 'NOT_STARTED' && PAGE != "DataEntryController:buildRecordListCache" && isset($_GET['pid']) && $_GET['pid'] == $project_id
			&& !$recursive && $_SERVER['REQUEST_METHOD'] == 'GET'
			&& !defined("CRON") && !$isAjax
			// Make sure we're not inside a hook right now
			&& !isset($GLOBALS['__currently_inside_hook'])
			// Make sure we don't end up in an infinite loop if the building of the record list cache somehow fails
			&& !isset($_GET['__record_cache_complete']) && !isset($_GET['__record_cache_processing']))
		{
			$insideRedcapVersionDirectory = (strpos($_SERVER['REQUEST_URI'], "/redcap_v".REDCAP_VERSION."/") !== false);
			if (defined("NOAUTH") || !$insideRedcapVersionDirectory) {
				// If we're inside a plugin or a NOAUTH space, then set flag to disable authentication during the passthru
				// Hit the cache-building end-point to trigger the list to be created. Add NOAUTH_BUILDRECORDLIST and PLUGIN flags to bypass authentication and to prevent it from returning anything (faster), respectively.
				http_get(APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."&NOAUTH_BUILDRECORDLIST&PLUGIN&route=DataEntryController:buildRecordListCache");
			} else {
				// If this is GET request in the project and not an AJAX request, then redirect to the route to build the record list
				redirect(APP_PATH_WEBROOT . "index.php?pid=" . $project_id . "&__redirect=" . urlencode(curPageURL()) . "&route=DataEntryController:buildRecordListCache");
			}
		}

		if ($recordListCacheStatus == 'NOT_STARTED' && PAGE == "DataEntryController:buildRecordListCache" && isset($_GET['pid']) && $_GET['pid'] == $project_id
			&& !$recursive && $_SERVER['REQUEST_METHOD'] == 'GET'
			&& !defined("CRON") && !$isAjax
			// Make sure we're not inside a hook right now
			&& !isset($GLOBALS['__currently_inside_hook']))
		{
			// BUILD THE RECORD LIST AND ADD TO DB TABLE
			// Set delete list query
			$deleteRecordListSql = self::SQL_DELETE_RECORD_LIST . $project_id;
			// Set insert query prefix
			$insertPre = "insert into redcap_record_list (project_id, arm, record, sort, dag_id) values ";
			// First, if the delete list query is already being run by someone else, then do not even start this process (because it's already running)
			$buildListFromDataTable = self::isQueryCurrentlyRunning($deleteRecordListSql);
			if (!$buildListFromDataTable) {
				// If we also see any INSERTs where the record list is being built, then do not start.
				// NOTE: $insertPre has a special field order that does not match any other inserts to that table. This is for the purpose of matching it here during the build process.
				$buildListFromDataTable = self::isQueryCurrentlyRunning("$insertPre($project_id,", true);
			}
			// Begin building list from data table
			if (!$buildListFromDataTable)
			{
				// Set record list status as PROCESSING at the very beginning to prevent users having overlap when starting this processing
				$sql = "update redcap_record_counts set record_list_status = 'PROCESSING', time_of_list_cache = NULL where project_id = $project_id";
				$q = db_query($sql);
				if (!($q && db_affected_rows() > 0)) $buildListFromDataTable = true;
				// Set flag to reset the record list status back to NOT_STARTED
				$resetStatus = false;
				// Get DAG designations
				$dags = $Proj->getGroups();
				$recordsDags = empty($dags) ? array() : self::getRecordListAllDags($project_id);
				// Classic/Longi vs Multi-Arm Longitudinal
				if ($Proj->longitudinal && $Proj->multiple_arms) {
					// Multi-arm longitudinal
					$recordArms = self::getArmsForAllRecords($project_id);
				} else {
					// Classic & 1-arm Longitudinal
					$recordArms = array();
					$sql = "select distinct record from redcap_data where project_id = $project_id
							and field_name = '" . db_escape(self::getTablePK($project_id)) . "'
							and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
					$q = db_query($sql, null, MYSQLI_USE_RESULT); // Use unbuffered query method
					if (!$q) $buildListFromDataTable = true;
					while ($row = db_fetch_assoc($q)) {
						$row['record'] = label_decode($row['record']);
						$recordArms[$row['record']][] = $Proj->firstArmNum;
					}
					// Order records
					natcaseksort($recordArms);
				}
				if (!$buildListFromDataTable) {
					// Erase any existing records in the list table
					if (!db_query($deleteRecordListSql)) $buildListFromDataTable = true;
				}
				// Populate db table with record list
				if (!$buildListFromDataTable)
				{
					// Set flag to true because we only want to reset the status if things go wrong halfway through building the record list (because we want to revert it and rebuild it)
					$resetStatus = true;
					// Add records array to record list table
					$sort = 1;
					$inserts = array();
					foreach ($recordArms as $this_record=>$these_arms)
					{
						// Get DAG ID
						$dag_id = isset($recordsDags[$this_record]) ? (int)$recordsDags[$this_record] : 'NULL';
						// Loop through each record
						foreach ($these_arms as $thisArm)
						{
							// Add this record to array
							$inserts[] = "($project_id,$thisArm,'".db_escape($this_record)."',".$sort++.",$dag_id)";
							// Insert 100 rows at once
							if ($sort % 100 == 0) {
								// Insert records into table
								if (!db_query($insertPre . implode(", ", $inserts))) { $buildListFromDataTable = true; break; }
								// Reset array for next loop
								$inserts = array();
							}
						}
						unset($recordArms[$this_record]);
					}
					// Pick up any records left over in $inserts
					if (!empty($inserts)) {
						if (!db_query($insertPre . implode(", ", $inserts))) $buildListFromDataTable = true;
					}
				}
				unset($recordsDags, $recordArms, $inserts);
				// Check if there are DAG assignments to process OR reset status back to NOT_STARTED
				if ($buildListFromDataTable && $resetStatus) {
					// Roll back
					$sql = "update redcap_record_counts set record_list_status = 'NOT_STARTED', time_of_list_cache = NULL where project_id = $project_id";
					$q = db_query($sql);
				} elseif (!$buildListFromDataTable) {
					// Set record list status as COMPLETE
					$sql = "update redcap_record_counts set record_list_status = 'COMPLETE', time_of_list_cache = '".NOW."' where project_id = $project_id";
					$q = db_query($sql);

					// If any records were created since this script began running, then we'll need to reset the cache
					$sql = "select count(1) from ".Logging::getLogEventTable($project_id)." where project_id = $project_id and event = 'INSERT' 
							and object_type = 'redcap_data' and ts >= '".preg_replace("/[^0-9]/", "", NOW)."'";
					$q = db_query($sql);
					if (db_result($q, 0) > 0) {
						// Reset the cache (even though we just built it)
						self::resetRecordCountAndListCache($project_id);
						$buildListFromDataTable = true;
					}
					// If we're merely building the record list and not really returning it, then return here
					if ($returnNoOutput) return array();
					if (!$buildListFromDataTable) {
						// Get records recursively since the cache table is now filled
						return self::getRecordList($project_id, $filterByGroupID, $filterByDDEuser, $returnRecordEventPairs, $arm, $limit, $limitOffset, $filterByRecords, true);
					}
				}
			}
		}
		// BUILD RECORD LIST FROM DATA TABLE	
		if ($buildListFromDataTable)
		{
			// Filter by DAG, if applicable
			$dagSql = "";
			if (!empty($filterByGroupID)) {
				$dagSql = "and record in (" . pre_query("SELECT record FROM redcap_data where project_id = $project_id
						   and field_name = '__GROUPID__' AND value in (".prep_implode($filterByGroupID).")
						   and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")") . ")";
			}
			// Add event_id to query
			$event_field = ($returnRecordEventPairs) ? ", event_id" : "";
			// Query to get resources from table
			$sql = "select distinct $record_dde_field $event_field from redcap_data where project_id = $project_id
					and field_name = '" . db_escape(self::getTablePK($project_id)) . "' $record_dde_where $dagSql
					and event_id in (".prep_implode($events).")";
			if (!empty($filterByRecords)) $sql .= " and record in (".prep_implode($filterByRecords).")";
			// If $limit is provided, then use mysql ordering to get first X records
			if (is_numeric($limit)) $sql .= " order by abs(record), record $limitSql";
			// Execute query and put in array
			$records = array();
			$q = db_query($sql, null, MYSQLI_USE_RESULT); // Use unbuffered query method
			if (!$q) return false;
			while ($row = db_fetch_assoc($q)) {
				$record = label_decode($row['record']);
				if ($returnRecordEventPairs) {
					$records[$record."-".$row['event_id']] = $record . " - " . $Proj->eventInfo[$row['event_id']]['name_ext'];
				} else {
					$records[$record] = $record;
				}
			}
			// Order records
			natcaseksort($records);
			// Return record list
			return $records;
		}
	}

	// Determine if we need to build record list cache (but only while on certain pages)
	public static function determineBuildRecordListCache()
	{
		$recordCacheRebuildPages = array('index.php', 'ProjectSetup/index.php', 'DataEntry/record_status_dashboard.php',
										 'DataEntry/record_home.php', 'DataExport/index.php', 'DataImportController:index');
		if (!isset($_GET['__record_cache_complete']) && !isset($_GET['__record_cache_processing']) && in_array(PAGE, $recordCacheRebuildPages)
			&& Records::getRecordListCacheStatus(PROJECT_ID) != 'COMPLETE')
		{
			redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID."&__redirect=".urlencode(curPageURL())."&route=DataEntryController:buildRecordListCache");
		}
	}

	// Clear the record list cache so that it will be regenerated
	public function clearRecordListCache()
	{
		self::resetRecordCountAndListCache(PROJECT_ID);
	}

	// Build record list cache (but only while on a specific end-point)
	public function buildRecordListCache()
	{
		global $redcap_base_url;
		if (!isset($_GET['pid'])) System::redirectHome();
		$redirectAppend = "&__record_cache_processing=1";
		// Check record cache status
		$recordListCacheStatus = self::getRecordListCacheStatus($_GET['pid']);
		if ($recordListCacheStatus != 'COMPLETE' && $recordListCacheStatus != 'PROCESSING')
		{
			// Make sure that no other user in this project is on this same route
			$windowTimeMinutes = 30;
			$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"), date("i") - $windowTimeMinutes, date("s"), date("m"), date("d"), date("Y")));
			$sql = "select 1 from redcap_log_view_requests r, redcap_log_view v 
					where v.log_view_id = r.log_view_id and r.script_execution_time is null and r.mysql_process_id != '" . db_thread_id() . "' 
					and v.ts > '$xMinAgo' and v.page = 'DataEntryController:buildRecordListCache' and v.project_id = " . $_GET['pid'] . " limit 1";
			$q = db_query($sql);
			// Is anyone else on this page also building the cache? If so, don't do anything.
			$someoneElseBuildingCache = (db_num_rows($q) > 0);
			if (!$someoneElseBuildingCache) {
				// Create the cache but don't return anything
				Records::getRecordList($_GET['pid'], array(), false, false,null, null, 0, array(), false, '', true);
				$redirectAppend = "&__record_cache_complete=1";
			}
		}
		// If the API or a PLUGIN is making an inline CURL request directly to this endpoint, then no need to redirect anywhere
		if (isset($_GET['API']) || isset($_GET['PLUGIN'])) exit;
		// Determine the path to which we need to redirect to next
		if (isset($_GET['__redirect']) && !empty($_GET['__redirect'])) {
			$redirectUrl = urldecode($_GET['__redirect']);
		} elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'pid=') !== false) {
			$redirectUrl = $_SERVER['HTTP_REFERER'];
		} else {
			$redirectUrl = APP_PATH_WEBROOT."index.php?pid=".$_GET['pid'];
		}
		if (strpos($redirectUrl, '?') === false) {
			$redirectAppend = "?" . $redirectAppend;
		}
		// Validate that the redirect URL is not an external server
		if (strpos($redirectUrl, $redcap_base_url) !== 0) {
			$redirectUrl = APP_PATH_WEBROOT."index.php?pid=".$_GET['pid'];
		}
		// Redirect
		redirect($redirectUrl . $redirectAppend);
	}

	// Return name of record identifier variable (i.e. "table_pk") in a given project
	public static function getTablePK($project_id=null)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// First, if project-level variables are defined, then there's no need to query the database table
		if (defined('PROJECT_ID') && $project_id == PROJECT_ID) {
			// Get table_pk from global scope variable UNLESS we're in a plugin, in which case
			// we can't assume the $Proj is the right project we need for this (e.g. if using getData(project_id))
			global $Proj;
			$metadata_fields = array_keys($Proj->metadata);
			return $metadata_fields[0];
		}
		// Query metadata table
		$sql = "select field_name from redcap_metadata where project_id = $project_id
				order by field_order limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			// Return field name
			return db_result($q, 0);
		} else {
			// Return false is query fails or doesn't exist
			return false;
		}
	}


	// Get list of all records (or specific ones) with their Form Status for all forms/events
	// If user is in a DAG, then limits results to only their DAG.
	// if user is a DDE user, then limits results to only their DDE records (i.e. ending in --1 or --2).
	public static function getFormStatus($project_id=null, $records=array(), $arm=null, $dag=null, $selected_forms_events_array=array(), $preserve_record_order=false)
	{
		global $user_rights, $double_data_entry, $Proj;
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Get array list of form_names
		$allForms = self::getFormNames($project_id);
		// Get table_pk
		$table_pk = self::getTablePK($project_id);
		// Determine if using Double Data Entry and if DDE user (if so, add --# to end of Study ID when querying data table)
		$isDDEuser = ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
		// Determine if records array was provided, if provided
		$recordsSpecified = (is_array($records) && !empty($records));
		if (!$recordsSpecified) return array();
		// Has repeating events/forms?
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// Set events
		$limitForms = !empty($selected_forms_events_array);
		$events = (!empty($arm) && isset($Proj->events[$arm])) ? array_keys($Proj->events[$arm]['events']) : array();
		if (empty($selected_forms_events_array)) {
			if (empty($events)) $events = array_keys($Proj->eventInfo);
		} else {
			$events = empty($events) ? array_keys($selected_forms_events_array) : array_intersect($events, array_keys($selected_forms_events_array));
			// Limit by form
			$allForms = array();
			foreach ($selected_forms_events_array as $these_forms) {
				$allForms = array_merge($allForms, $these_forms);
			}
			$allForms = array_unique($allForms);
		}
		// Array to collect the record data
		$data = array();
		$grayStatusForms = $grayStatusEvents = $grayStatusRecords = array();
		// If records array was provided, then seed $data with all records there
		if ($recordsSpecified) {
			// If record is not in the array yet, prefill forms with blanks
			foreach ($records as &$this_record) {
				if ($isDDEuser) $this_record = addDDEending($this_record);
				foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
					if (!in_array($this_event_id, $events)) continue;
					if (!isset($data[$this_record][$this_event_id])) {
						foreach ($these_forms as $this_form) {
							if ($limitForms && !in_array($this_form, $allForms)) continue;
							$data[$this_record][$this_event_id][$this_form] = array();
						}
					}
				}
			}
			unset($this_record);
		}
		// Create "where" clause for records provided, if provided
		$recordSql = ($recordsSpecified) ? "and d.record in (" . prep_implode($records) . ")" : "";
		// Limit by DAGs, if in a DAG
		$dagSql = '';
		if (is_array($user_rights) && $user_rights['group_id'] != "") {
			$dag = $user_rights['group_id'];
		}
		if (is_numeric($dag)) {
			$dagSql = "and d.record in (" . prep_implode(Records::getRecordListSingleDag($project_id, $dag)) . ")";
		}
		// Set "record" field in query if a DDE user
		$record_dde_where = ($isDDEuser) ? "and d.record like '%--{$user_rights['double_data']}'" : "";
		// Query to get resources from table
		$sql = "select d.record, d.event_id, d.field_name, d.value, d.instance from redcap_data d 
				where d.project_id = $project_id and d.field_name in ('".implode("_complete', '", $allForms)."_complete')
				$recordSql $dagSql $record_dde_where and d.event_id in (".prep_implode($events).")
				order by d.record";
		$q = db_query($sql);
		if (!$q) return false;
		while ($row = db_fetch_assoc($q))
		{
			if ($row['value'] == '') continue;
			// If record is not in the array yet, prefill forms sub-arrays
			if (!isset($data[$row['record']][$row['event_id']])) {
				foreach ($Proj->eventsForms[$row['event_id']] as $this_form) {
					$data[$row['record']][$row['event_id']][$this_form] = array();
				}
			}
			// Set form name
			$form = substr($row['field_name'], 0, -9);
			// Add the form values to array (ignore table_pk value since it was only used as a record placeholder anyway)
			if ($hasRepeatingFormsEvents) {
				if ($row['instance'] == '') $row['instance'] = '1';
				if (!$Proj->isRepeatingFormOrEvent($row['event_id'], $form) && $row['instance'] > 1) {
					continue;
				}
				$data[$row['record']][$row['event_id']][$form][$row['instance']] = $row['value'];
			} else {
				$data[$row['record']][$row['event_id']][$form][1] = $row['value'];
			}
		}
		// Check if we have any blank values (gray status icons), and if so, place in arrays
		foreach ($data as $this_record=>&$these_events) {
			foreach ($these_events as $this_event_id=>&$these_forms) {
				foreach ($these_forms as $this_form=>&$these_instances) {
					if (empty($these_instances)) {
						$grayStatusForms[$this_form] = true;
						$grayStatusEvents[$this_event_id] = true;
						$grayStatusRecords[$this_record] = true;
					} else {
						foreach ($these_instances as $this_instance=>$this_value) {
							// If status value is blank, place form/event/record in arrays to query after this
							if ($this_value == '') {
								$grayStatusForms[$this_form] = true;
								$grayStatusEvents[$this_event_id] = true;
								$grayStatusRecords[$this_record] = true;
							}
						}
					}
				}
			}
		}
		unset($these_events, $these_forms, $these_instances);
		
		// Now deal with forms with NO STATUS VALUE saved but might have other values for fields in the form (occurs due to data imports)
		if (!empty($grayStatusRecords)) 
		{
			$sql = "select distinct d.record, d.event_id, m.form_name, d.instance
					from redcap_data d, redcap_metadata m where d.project_id = $project_id and d.project_id = m.project_id 
					and m.element_type != 'calc' and m.field_name != '$table_pk' and d.field_name = m.field_name 
					and m.form_name in (".prep_implode(array_keys($grayStatusForms)).")
					and d.field_name not in ('".implode("_complete', '", array_keys($grayStatusForms))."_complete')
					and d.record in (".prep_implode(array_keys($grayStatusRecords)).") 
					and d.event_id in (".prep_implode(array_keys($grayStatusEvents)).")";
			$q = db_query($sql);
			if (!$q) return false;
			while ($row = db_fetch_assoc($q))
			{
				// Add the form values to array (ignore table_pk value since it was only used as a record placeholder anyway)
				if ($hasRepeatingFormsEvents) {
					if ($row['instance'] == '') $row['instance'] = '1';
					if ($data[$row['record']][$row['event_id']][$row['form_name']][$row['instance']] == '') {
						$data[$row['record']][$row['event_id']][$row['form_name']][$row['instance']] = '0';
					}
				} else {
					if ($data[$row['record']][$row['event_id']][$row['form_name']][1] == '') {
						$data[$row['record']][$row['event_id']][$row['form_name']][1] = '0';
					}
				}
			}
		}
		
		// Order by record
		if (!$preserve_record_order) natcaseksort($data);
		// Return array of form status data for records
		return $data;
	}


	// Return form_names as array of all instruments in a given project
	public static function getFormNames($project_id=null)
	{
		// First, if project-level variables are defined, then there's no need to query the database table
		if (defined('PROJECT_ID')) {
			// Get table_pk from global scope variable
			global $Proj;
			return array_keys($Proj->forms);
		}
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Query metadata table
		$sql = "select distinct form_name from redcap_metadata where project_id = $project_id
				order by field_order";
		$q = db_query($sql);
		if (!$q) return false;
		// Return form_names
		$forms = array();
		while ($row = db_fetch_assoc($q)) {
			$forms[] = $row['form_name'];
		}
		return $forms;
	}


	// Return the Data Access Group group_id for a record. If record not in a DAG, return false.
	public static function getRecordGroupId($project_id=null, $record=null)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Make sure record is not null
		if ($record == null) return false;
		// Query data table
		$sql = "select d.value from redcap_data d, redcap_data_access_groups g
				where d.project_id = $project_id and g.project_id = d.project_id and d.record = '".db_escape($record)."'
				and d.field_name = '__GROUPID__' and d.value = g.group_id limit 1";
		$q = db_query($sql);
		if (!$q || ($q && !db_num_rows($q))) return false;
		// Get group_id
		$group_id = db_result($q, 0);
		// Return group_id
		return $group_id;
	}

	// Obtain custom record label & secondary unique field labels for ALL records.
	// Limit by array of record names. If provide $records parameter as a single record string, then return string (not array).
	// Return array with record name as key and label as value.
	// If $arm == 'all', then get labels for the first event in EVERY arm (assuming multiple arms),
	// and also return
	public static function getCustomRecordLabelsSecondaryFieldAllRecords($records=array(), $removeHtml=false, $arm=null, $boldSecondaryPkValue=false, $cssClass='crl')
	{
		global $secondary_pk, $custom_record_label, $Proj, $user_rights, $secondary_pk_display_value, $secondary_pk_display_label;
		// Get arm
		if ($arm === null) $arm = getArm();
		// Get event_ids
		$event_ids = ($arm == 'all') ? array_keys($Proj->eventInfo) : array_keys($Proj->events[$arm]['events']);
		// Place all records/labels in array
		$extra_record_labels = array();
		// If $records is a string, then convert to array
		$singleRecordName = null;
		if (!is_array($records)) {
			$singleRecordName = $records;
			$records = array($records);
		}
		// Set flag to limit records
		$limitRecords = !empty($records);
		// Customize the Record ID pulldown menus using the SECONDARY_PK appended on end, if set.
		if ($secondary_pk != '' && $secondary_pk_display_value)
		{
			// Determine if we need to remove identifier fields via de-id process (for PDF export)
			$deidFieldsToRemove = (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] > 1)
								? DataExport::deidFieldsToRemove(array($secondary_pk), ($user_rights['data_export_tool'] == '3'))
								: array();
			$deidRemove = (in_array($secondary_pk, $deidFieldsToRemove) && defined("DEID_TEXT"));
			// Get validation type of secondary unique field
			$val_type = $Proj->metadata[$secondary_pk]['element_validation_type'];
			$convert_date_format = (substr($val_type, 0, 5) == 'date_' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_mdy'));
			// Set secondary PK field label
			$secondary_pk_label = $secondary_pk_display_label ? strip_tags(br2nl(label_decode($Proj->metadata[$secondary_pk]['element_label'], false))) : '';
			// PIPING: Obtain saved data for all piping receivers used in secondary PK label
			if ($secondary_pk_display_label && strpos($secondary_pk_label, '[') !== false && strpos($secondary_pk_label, ']') !== false) {
				// Get fields in the label
				$secondary_pk_label_fields = array_keys(getBracketedFields($secondary_pk_label, true, true, true));
				// If has at least one field piped in the label, then get all the data for these fields and insert one at a time below
				if (!empty($secondary_pk_label_fields)) {
					$piping_record_data = Records::getData('array', $records, $secondary_pk_label_fields, $event_ids);
				}
			}
			// Get back-end data for the secondary PK field
			$sql = "select record, event_id, value from redcap_data
					where project_id = ".PROJECT_ID." and field_name = '$secondary_pk'
					and event_id in (" . prep_implode($event_ids) . ")";
			if ($limitRecords) {
				$sql .= " and record in (" . prep_implode($records) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				if ($row['value'] == '') continue;
				// Set the label for this loop (label may be different if using piping in it)
				if (isset($piping_record_data)) {
					// Piping: pipe record data into label for each record
					$this_secondary_pk_label = Piping::replaceVariablesInLabel($secondary_pk_label, $row['record'], $event_ids, 1, $piping_record_data);
				} else {
					// Static label for all records
					$this_secondary_pk_label = $secondary_pk_label;
				}
				// Remove value via de-id process?
				if ($deidRemove) {
					$row['value'] = DEID_TEXT;
				}
				// If the secondary unique field is a date/time field in MDY or DMY format, then convert to that format
				elseif ($convert_date_format) {
					$row['value'] = DateTimeRC::datetimeConvert($row['value'], 'ymd', substr($val_type, -3));
				}
				// Set text value
				$this_string = "(" . remBr($this_secondary_pk_label . " " .
							   ($boldSecondaryPkValue ? "<b>" : "") .
							   decode_filter_tags($row['value'])) .
							   ($boldSecondaryPkValue ? "</b>" : "") .
							   ")";
				// Add HTML around string (unless specified otherwise)
				$extra_record_labels[$Proj->eventInfo[$row['event_id']]['arm_num']][$row['record']] = ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
			}
			db_free_result($q);
		}
		// [Retrieval of ALL records] If Custom Record Label is specified (such as "[last_name], [first_name]"), then parse and display
		// ONLY get data from FIRST EVENT
		if (!empty($custom_record_label))
		{
			$removeIdentifiers = (defined("DEID_TEXT") && isset($user_rights) && ($user_rights['data_export_tool'] == '2' || $user_rights['data_export_tool'] == '3'));
			// Loop through each event (will only be one UNLESS we are attempting to get label for multiple arms)
			$customRecordLabelsArm = array();
			foreach ($event_ids as $this_event_id) {
				$this_arm = is_numeric($arm) ? $arm : $Proj->eventInfo[$this_event_id]['arm_num'];
				if (isset($customRecordLabelsArm[$this_arm])) continue;
				$customRecordLabels = getCustomRecordLabels($custom_record_label, $this_event_id, ($singleRecordName ? $records[0] : $records), $removeIdentifiers);
				if (!is_array($customRecordLabels)) $customRecordLabels = array($records[0]=>$customRecordLabels);
				$customRecordLabelsArm[$this_arm] = $customRecordLabels;
			}
			foreach ($customRecordLabelsArm as $this_arm=>&$customRecordLabels)
			{
				foreach ($customRecordLabels as $this_record=>$this_custom_record_label)
				{
					// If limiting by records, ignore if not in $records array
					if ($limitRecords && !in_array($this_record, $records)) continue;
					// Set text value
					$this_string = remBr(decode_filter_tags($this_custom_record_label));
					// Add initial space OR add placeholder
					if (isset($extra_record_labels[$this_arm][$this_record])) {
						$extra_record_labels[$this_arm][$this_record] .= ' ';
					} else {
						$extra_record_labels[$this_arm][$this_record] = '';
					}
					// Add HTML around string (unless specified otherwise)
					$extra_record_labels[$this_arm][$this_record] .= ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
				}
			}
			unset($customRecordLabels);
		}
		// If we're not collecting multiple arms here, then remove arm key
		if ($arm != 'all') {
			$extra_record_labels = array_shift($extra_record_labels);
		}
		// Return string (single record only)
		if ($singleRecordName != null) {
			return (isset($extra_record_labels[$singleRecordName])) ? $extra_record_labels[$singleRecordName] : '';
		} else {
			// Return array
			return $extra_record_labels;
		}
	}

	// Make sure that there is a case sensitivity issue with the record name. Check value with back-end value.
	// Return the true back-end value as it is already stored.
	public static function checkRecordNameCaseSensitive($record)
	{
		global $table_pk;
		// Make sure record is a string
		$record = "$record";
		// Query to get back-end record name
		$sql = "select trim(record) from redcap_data where project_id = " . PROJECT_ID . " and field_name = '$table_pk'
				and record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$backEndRecordName = "".db_result($q, 0);
			if ($backEndRecordName != "" && $backEndRecordName !== $record)
			{
				// They don't match, return the back-end value.
				return $backEndRecordName;
			}
		}
		// Return same value submitted. Trim it, just in case.
		return trim($record);
	}
	
	// Get field list array of all fields that need to have their decimal converted based on $decimalCharacter param
	public static function getFieldsDecimalConvert($project_id, $decimalCharacter='', $fields=array())
	{
		$Proj = new Project($project_id);
		// Determine any fields that we need to convert its decimal character
		$fieldsDecimalConvert = array();
		if (empty($fields)) $fields = array_keys($Proj->metadata);
		if ($decimalCharacter != '') {
			$valTypes = getValTypes();
			if ($decimalCharacter == '.') {
				// Force dot/period decimal (only number_comma_decimal-validated fields will have comma decimals)
				foreach ($fields as $this_field) {
					if ($Proj->metadata[$this_field]['element_type'] == 'text' && $valTypes[$Proj->metadata[$this_field]['element_validation_type']]['data_type'] == 'number_comma_decimal') {
						$fieldsDecimalConvert[$this_field] = true;
					}
				}
			} elseif ($decimalCharacter == ',') {
				// Force comma decimal (only calcs and number-validated fields will have dot decimals)
				foreach ($fields as $this_field) {
					if ($Proj->metadata[$this_field]['element_type'] == 'calc' || $Proj->metadata[$this_field]['element_validation_type'] == 'float' || 
						($Proj->metadata[$this_field]['element_type'] == 'text' && $valTypes[$Proj->metadata[$this_field]['element_validation_type']]['data_type'] == 'number')) {
						$fieldsDecimalConvert[$this_field] = true;
					}
				}
			}
		}
		// Return array of fields
		return $fieldsDecimalConvert;
	}

	/**
	 * GET DATA FOR RECORDS
	 * [@param int $project_id - (optional) Manually supplied project_id for this project.]
	 * @param string $return_format - Default 'array'. Return record data in specified format (array, csv, json, xml).
	 * @param string/array $records - if provided as a string, will convert to an array internally.
	 * @param string/array $fields - if provided as a string, will convert to an array internally.
	 * @param string/array $events - if provided as a string, will convert to an array internally.
	 * @param string/array $groups - if provided as a string, will convert to an array internally.
	 * @param bool $combine_checkbox_values is only an option for $return_format csv, json, and xml, in which it determines whether
	 * checkbox option values are returned as multiple fields with triple underscores or as a combined single field with all *checked*
	 * options as comma-delimited (e.g., "1,3,4" if only choices 1, 3, and 4 are checked off).
	 * NOTE: 'array' returnFormat will always have event_id as 2nd key and will always have checkbox options as a sub-array
	 * for each given checkbox field.
	 */
	public static function getData()
	{
		global $salt, $lang, $user_rights, $redcap_version, $edoc_storage_option,
			   $record_data_tmp_file, $record_data_tmp_filename, $record_data_tmp_line, $record_data_tmp_line_num;
		// Array that maps args to local variables
		$args_array = array(
			0=>'project_id', 1=>'return_format', 2=>'records', 3=>'fields', 4=>'events', 5=>'groups', 
			6=>'combine_checkbox_values', 7=>'exportDataAccessGroups', 8=>'exportSurveyFields', 
			9=>'filterLogic', 10=>'exportAsLabels', 11=>'exportCsvHeadersAsLabels', 
			// The parameters below are not documented in REDCap::getData
			12=>'hashRecordID', 13=>'dateShiftDates', 14=>'dateShiftSurveyTimestamps', 15=>'sortArray', 16=>'removeLineBreaksInValues', 
			17=>'replaceFileUploadDocId', 18=>'returnIncludeRecordEventArray', 19=>'orderFieldsAsSpecified', 
			20=>'outputSurveyIdentifier', 21=>'outputCheckboxLabel', 22=>'filterType', 23=>'includeOdmMetadata', 
			24=>'write_data_to_file', 25=>'returnEmptyEvents', 26=>'removeNonDesignatedFieldsFromArray',
            27=>'replaceDoubleQuotes', 
			// These parameters cannot be used with "array" return_format (only for flat data sets returned)
			28=>'rowLimit', 29=>'rowBegin', 30=>'returnRecordListAndPagingList',
			// CSV only
			31=>'csvDelimiter', 
			// Decimal character option
			32=>'decimalCharacter',
			// Batching options
			33=>'doBatching', 34=>'batchNum', 35=>'includeRecordEvents',
			// Remove any Missing Data Code values
			36=>'removeMissingDataCodes'
		);
		// Get function arguments
		$args = func_get_args();
		// If first parameter is an array, it is assumed to contain all the parameters
		$paramsPassedAsArray = is_array($args[0]);
		if ($paramsPassedAsArray) {
			$new_args = $args[0];
			$args = array();
			foreach ($args_array as $key=>$val) {
				if (isset($new_args[$val])) {
					$args[$key] = $new_args[$val];
				}
			}
			unset($new_args);
		}
		// If first parameter is numerical, then assume it is $project_id and that second parameter is $return_format
		if (!is_numeric($args[0]) && defined("PROJECT_ID")) {
			// Prepend project_id to args
			if ($paramsPassedAsArray) {
				$args[0] = PROJECT_ID;
			} else {
				array_unshift($args, PROJECT_ID);
			}
		}
		ksort($args);
		// Make sure we have a project_id
		if (!is_numeric($args[0]) && !defined("PROJECT_ID")) throw new Exception('No project_id provided!');
		// Instantiate object containing all project information
		$Proj = new Project($args[0]);
		$longitudinal = $Proj->longitudinal;
		$table_pk = $Proj->table_pk;
		// Args
		$project_id = $args[0];
		$return_format = (isset($args[1])) ? $args[1] : 'array';
		$records = (isset($args[2])) ? $args[2] : array();
		$fields = (isset($args[3])) ? $args[3] : array();
		$events = (isset($args[4])) ? $args[4] : array();
		$groups = (isset($args[5])) ? $args[5] : array();
		$combine_checkbox_values = (isset($args[6])) ? $args[6] : false;
		$exportDataAccessGroups = (isset($args[7])) ? $args[7] : false;
		$exportSurveyFields = (isset($args[8])) ? $args[8] : false;
		$filterLogic = (isset($args[9])) ? $args[9] : false;
		$exportAsLabels = (isset($args[10])) ? $args[10] : false;
		$exportCsvHeadersAsLabels = (isset($args[11])) ? $args[11] : false;
		$hashRecordID = (isset($args[12])) ? $args[12] : false;
		$dateShiftDates = (isset($args[13])) ? $args[13] : false;
		$dateShiftSurveyTimestamps = (isset($args[14])) ? $args[14] : false;
		$sortArray = (isset($args[15])) ? $args[15] : array();
		$removeLineBreaksInValues = (isset($args[16])) ? $args[16] : false;
		$replaceFileUploadDocId = (isset($args[17])) ? $args[17] : false;
		$returnIncludeRecordEventArray = (isset($args[18])) ? $args[18] : false;
		$orderFieldsAsSpecified = (isset($args[19])) ? $args[19] : false;
		$outputSurveyIdentifier = (isset($args[20])) ? $args[20] : $exportSurveyFields;
		$outputCheckboxLabel = (isset($args[21])) ? $args[21] : false;
		$filterType = (isset($args[22])) ? $args[22] : 'EVENT';
		$includeOdmMetadata = (isset($args[23])) ? $args[23] : false;
		$write_data_to_file = (isset($args[24])) ? $args[24] : false;
		$returnEmptyEvents = (isset($args[25])) ? $args[25] : false;
		$removeNonDesignatedFieldsFromArray = (isset($args[26]) && $longitudinal && $return_format == 'array') ? $args[26] : false;
        $replaceDoubleQuotes = (isset($args[27])) ? $args[27] : true;
        $rowLimit = (isset($args[28])) ? $args[28] : null;
        $rowBegin = (isset($args[29])) ? $args[29] : 0;
		$returnRecordListAndPagingList = (isset($args[30])) ? $args[30] : false;
		$csvDelimiter = (isset($args[31]) && DataExport::isValidCsvDelimiter($args[31])) ? $args[31] : ",";
		$decimalCharacter = (isset($args[32]) && $args[32] != "") ? $args[32] : ''; // Only 2 options: dot (default) or comma
		$doBatching = ($return_format == 'array' || $return_format == 'html' || PAGE == 'DataExport/report_ajax.php' || !isset($args[33]) || !$args[33]) ? false : true;
		$batchNum = (!isset($args[34]) || !is_integer($args[34]) || $args[34] < 0) ? 0 : $args[34];
		$includeRecordEvents = (isset($args[35])) ? $args[35] : array();
		$missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
		$removeMissingDataCodes = (isset($args[36]) && $args[36] != false && !empty($missingDataCodes));

		// Add more time for processing data exports using file-save method
		if ($write_data_to_file) {
			System::increaseMaxExecTime(3600);
			$doBatching = false;
			$batchNum = 0;
		}

		// Get current memory limit in bytes
		$memory_limit = System::getMemoryLimit() * 1024 * 1024;

		// Set array of valid $return_format values
		$validReturnFormats = array('html', 'csv', 'xml', 'json', 'array', 'odm');

		// Set array of valid MC field types (don't include "checkbox" because it gets dealt with on its own)
		$mc_field_types = array("radio", "select", "yesno", "truefalse", "sql");

		// If $return_format is not valid, set to default 'csv'
		if (!in_array($return_format, $validReturnFormats)) $return_format = 'csv';

		// Make sure we keep the edoc_id for ODM export so we can base64 encode them. Ensure that we don't remove line breaks in values.
		if ($return_format == 'odm') $removeLineBreaksInValues = false;

		// Cannot use $exportAsLabels for 'array' output
		if ($return_format == 'array') $exportAsLabels = false;

		// Can only use $exportCsvHeadersAsLabels for 'csv' output
		if ($return_format != 'csv') $exportCsvHeadersAsLabels = false;

		// If surveys are not enabled, then set $exportSurveyFields to false
		if (!$Proj->project['surveys_enabled'] || empty($Proj->surveys)) $exportSurveyFields = $outputSurveyIdentifier = false;

		// Use for replacing strings in labels (if needed)
		$orig = array("\"", "\r\n", "\n", "\r");
		$repl = array("'" , "  "  , "  ", ""  );

		// Replace only line breaks, not double quotes with single quotes
        $origLineBreak = array("\r\n", "\n", "\r");
        $replLineBreak = array("  "  , "  ", ""  );

		// Determine if we should apply sortArray
		$applySortFields = (is_array($sortArray) && !empty($sortArray));
		
		// Does project have repeating forms or events?
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();

		## Set all input values
		// Get unique event names (with event_id as key)
		$unique_events = $Proj->getUniqueEventNames();
		// Create array of formatted event labels
		if ($longitudinal && $exportAsLabels) {
			$event_labels = array();
			foreach (array_keys($unique_events) as $this_event_id) {
				$event_labels[$this_event_id] = str_replace($orig, $repl, strip_tags(label_decode($Proj->eventInfo[$this_event_id]['name_ext'])));
			}
		}

		// If $fields is a string, convert to array
		if (!is_array($fields) && $fields != null) {
			$fields = array($fields);
		}
		// If $fields is empty, replace it with array of ALL fields.
		$removeTablePk = false;
		if (empty($fields)) {
			foreach (array_keys($Proj->metadata) as $this_field) {
				// Make sure field is not a descriptive field (because those will never have data)
				if ($Proj->metadata[$this_field]['element_type'] != 'descriptive') {
					$fields[] = $this_field;
				}
			}
			$checkFieldNameEachLoop = true;
		} else {
			// If only returning the record-event array (as the subset record list for a report),
			// then make sure the record ID is added, or else it'll break some things downstream (not ideal solution but works as quick patch).
			// Also do this for longitudinal projects because if we don't, it might not pull data for an entire event if data doesn't exist
			// for any fields here except the record ID field. NOTE: Make sure we remove the record ID field in the end though (so it doesn't get returned
			if (($Proj->longitudinal || $returnIncludeRecordEventArray) && !in_array($Proj->table_pk, $fields)) {
				$fields = array_merge(array($Proj->table_pk), $fields);
				if (!$returnIncludeRecordEventArray) $removeTablePk = true;
			}
			// Validate all field names and order fields according to metadata field order
			$field_order = array();
			foreach ($fields as $this_key=>$this_field) {
				// Make sure field exists AND is not a descriptive field (because those will never have data)
				if (isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] != 'descriptive') {
					// Put in array for sorting
					$field_order[] = $Proj->metadata[$this_field]['field_order'];
				} else {
					// Remove any invalid field names
					unset($fields[$this_key]);
				}
			}
			// Sort fields by metadata field order (unless passing a flag to prevent reordering)
			if (!$orderFieldsAsSpecified) {
				array_multisort($field_order, SORT_NUMERIC, $fields);
			}
			unset($field_order);
			// If we're querying more than 25% of the project's fields, then don't put field names in query but check via PHP each loop.
			$checkFieldNameEachLoop = ((count($fields) / count($Proj->metadata)) > 0.25);
		}
		## SORTING IN REPORTS: If the sort fields are NOT in $fields (i.e. should be returned as data),
		// then temporarily add them to $fields and then remove them later when performing sorting.
		if ($applySortFields) {
			$sortArrayRemoveFromData = array();
			foreach (array_keys($sortArray) as $this_field) {
				if (!in_array($this_field, $fields)) {
					$sortArrayRemoveFromData[] = $this_field;
				}
			}
			// Add sorting fields (if not in report)
			$fields = array_values(array_unique(array_merge($fields, array_keys($sortArray))));
		}
		// Create array of fields with field name as key
		$fieldsKeys = array_fill_keys($fields, true);
		
		// Determine any fields that we need to convert its decimal character
		$fieldsDecimalConvert = self::getFieldsDecimalConvert($project_id, $decimalCharacter, $fields);
		$decimalCharacterConvertFrom = ($decimalCharacter == '.') ? ',' : '.';
		$convertDecimal = !empty($fieldsDecimalConvert);

		// If $events is a string, convert to array
		if (!is_array($events) && $events != null) {
			$events = array($events);
		}
		// If $events is empty, replace it with array of ALL fields.
		if (empty($events)) {
			$events = array_keys($Proj->eventInfo);
		} else {
			// If $events has unique event name (instead of event_ids), then convert all to event_ids
			$events_temp = array();
			foreach ($events as $this_key=>$this_event) {
				// If numeric, validate event_id
				if (is_numeric($this_event)) {
					if (!isset($Proj->eventInfo[$this_event])) {
						// Remove invalid event_id
						unset($events[$this_key]);
					} else {
						// Valid event_id
						$events_temp[] = $this_event;
					}
				}
				// If unique event name is provided
				else {
					// Get array key of unique event name provided
					$event_id_key = array_search($this_event, $unique_events);
					if ($event_id_key !== false) {
						// Valid event_id
						$events_temp[] = $event_id_key;
					}
				}
			}
			// Now swap out $events_temp for $events
			$events = $events_temp;
			unset($events_temp);
		}

		// Get array of all DAGs
		$allDags = $Proj->getUniqueGroupNames();
		// Validate DAGs
		if (empty($allDags)) {
			// If no DAGs exist, then automatically set array as empty
			$groups = array();
			// Automatically set $exportDataAccessGroups as false (in case was set to true mistakenly)
			$exportDataAccessGroups = false;
		} else {
			// If $groups is a string, convert to array
			if (!is_array($groups) && $groups != null) {
				$groups = array($groups);
			}
			// If $groups is not empty, replace it with array of ALL data access group IDs.
			if (!empty($groups)) {
				// If $groups has unique group name (instead of group_ids), then convert all to group_ids
				$groups_temp = array();
				foreach ($groups as $this_key=>$this_group) {
					// If numeric, validate group_id
					if (is_numeric($this_group)) {
						if (!isset($allDags[$this_group])) {
							// Check to see if its really the unique group name (and not the group_id)
							$group_id_key = array_search($this_group, $allDags);
							if ($group_id_key !== false) {
								// Valid group_id
								$groups_temp[] = $group_id_key;
							} else {
								// Remove invalid group_id
								unset($groups[$this_key]);
							}
						} else {
							// Valid group_id
							$groups_temp[] = $this_group;
						}
					}
					// If unique group name is provided
					else {
						// Get array key of unique group name provided
						$group_id_key = array_search($this_group, $allDags);
						if ($group_id_key !== false) {
							// Valid group_id
							$groups_temp[] = $group_id_key;
						}
					}
				}
				// Now swap out $groups_temp for $groups
				$groups = $groups_temp;
				unset($groups_temp);
			}
		}

		## RECORDS
		// If $records is a string, convert to array
		if (!is_array($records) && $records != null) {
			$records = array($records);
		}
		// If $records is empty, replace it with array of ALL records.
		$recordsEmpty = false;
		$recordCount = null;
		if (empty($records)) {
			$records = self::getRecordList($project_id);
			// Set flag that $records was originally passed as empty
			$recordsEmpty = true;
			$checkRecordNameEachLoop = true;
		} else {
			// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
			if ($recordCount == null) $recordCount = self::getRecordCount($project_id);
			$checkRecordNameEachLoop = $recordCount > 0 ? ((count($records) / $recordCount) > 0.25) : true;
		}
		// Create array of fields with field name as key
		$recordsKeys = array_fill_keys($records, true);

		## DAG RECORDS: If pulling data for specific DAGs, get list of records in DAGs specified and replace $records with them
		$hasDagRecords = false;
		if (!empty($groups))
		{
			// Collect all DAG records into array
			$dag_records = array_values(self::getRecordList($project_id, $groups, false, false, null, null, 0, $records));
			// Set flag if returned some DAG records
			$hasDagRecords = (!empty($dag_records));
			// Replace $records array
			$records = $dag_records;
			unset($dag_records);
			// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
			if ($recordCount == null) $recordCount = self::getRecordCount($project_id);
			$checkRecordNameEachLoop = ((count($records) / $recordCount) > 0.25);
			// Create array of fields with field name as key
			$recordsKeys = array_fill_keys($records, true);
		}


		## APPLY FILTERING LOGIC: Get records-events where filter logic is true
		$filterResults = false;
		$filterReturnedEmptySet = false;
		$record_events_filtered = array();
		if ($filterLogic != '' && (empty($groups) || (!empty($groups) && $hasDagRecords))) // If returning only specific DAGs' records, but no records are in DAGs, then no need to apply filter logic.
		{
			// Get array of applicable record-events (only pass $project_id if already passed explicitly to getData)
			$record_events_filtered = self::applyFilteringLogic($filterLogic, $records, array(), (is_numeric($args[0]) ? $project_id : null));
			$filterResults = ($record_events_filtered !== false);
			// If logic returns zero record/events, then manually set $records to ''/blank
			if ($filterResults) {
				if (empty($record_events_filtered)) {
					$records = array('');
					$checkRecordNameEachLoop = false;
					$filterReturnedEmptySet = true;
				} else {
					// Replace headers
					$records = array_keys($record_events_filtered);
					// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
					if ($recordCount == null) $recordCount = self::getRecordCount($project_id);
					$checkRecordNameEachLoop = ((count($records) / $recordCount) > 0.25);
					// Create array of fields with field name as key
					$recordsKeys = array_fill_keys($records, true);
					// If we're just getting a record list via $fields=record_id, make sure to add all repeating form status fields so that
                    // the data is pulling via query (otherwise some records can get skipped if they only have data in the repeating form.
                    if (count($fields) == 1 && $fields[0] == $Proj->table_pk && $Proj->hasRepeatingForms())
                    {
                        // Loop through all repeating forms and get form status field
                        $repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
                        foreach ($repeatingFormsEvents as $these_forms) {
                            if (!is_array($these_forms)) continue;
                            foreach (array_keys($these_forms) as $this_form) {
                                // Add form status field for each repeating form
                                $fields[] = $this_form . "_complete";
                            }
                        }
                        $fields = array_unique($fields);
                        $checkFieldNameEachLoop = false;
                    }
                }
			}
		}

		// Set array of repeating events/forms
		$Proj->setRepeatingFormsEvents();
		$hasRepeatingEvents = $Proj->hasRepeatingEvents();
		$hasRepeatingForms = $Proj->hasRepeatingForms();
		$hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);

		$estimatedDataPoints = count($records) * count($events) * count($fields);

		if (!$filterReturnedEmptySet) {
			// APPLY MULTI-FIELD SORTING
			$doSorting = false;
			if ($applySortFields
				// (NOTE: Currently will not be performed with repeating instances + temp file method - too difficult right now)
				&& !($write_data_to_file && $hasRepeatedInstances))
			{
				$doSorting = true;
				// Move array keys to array with them as values
				$sortFields = array_keys($sortArray);
				$sortTypes = array_values($sortArray);
				// Determine if any of the sort fields are numerical fields (number, integer, calc, slider)
				$sortFieldIsNumber = array();
				foreach ($sortFields as $this_sort_field) {
					$field_type = $Proj->metadata[$this_sort_field]['element_type'];
					$val_type = $Proj->metadata[$this_sort_field]['element_validation_type'];
					// Is this field a number data type?
					$isNumberType = ($val_type == 'float' || $val_type == 'int' || $field_type == 'calc' || $field_type == 'slider');
					// The record ID field is missing number/integer validation but is auto-numbered, then loop through all values to determine if ALL are numeric
					$containsOnlyNumbers = (!$isNumberType && $this_sort_field == $Proj->table_pk && $Proj->project['auto_inc_set']
						// Quickest way to determine if all values in an array are numeric
						&& ctype_digit(str_replace('.', '', implode('', array_keys($record_data)))));
					// Is this a number field?
					$sortFieldIsNumber[] = ($isNumberType || $containsOnlyNumbers);
				}
				// If writing data to file, then sort via different method
				$sortFieldValues = array();
			}
		}

		## BATCHING
		if (!$doBatching && $batchNum == 0 && !$write_data_to_file && $return_format != 'array' && $return_format != 'html' && PAGE != 'DataExport/report_ajax.php' && $estimatedDataPoints > 100000) {
			// Auto-enable batch processing if we have a lot of data (~100k data points) and its in a format we can batch
			$doBatching = true;
		}
		if ($doBatching)
		{
			## SORTING: Sort the order of the records before splitting into batches
			if ($doSorting)
			{
				// If record ID field not included in sortFields but is in this export, then add it to getData fields
				$sortFieldsBatching = $sortFields;
				if (!in_array($Proj->table_pk, $sortFields) && in_array($Proj->table_pk, $fields)) {
					$sortFieldsBatching[] = $Proj->table_pk;
				}
				// If project has repeating forms/events, then add Form Status fields to force them to show up from getData
				if ($Proj->hasRepeatingFormsEvents()) {
					foreach ($fields as $this_field) {
						$sortFieldsBatching[] = $Proj->metadata[$this_field]['form_name']."_complete";
					}
				}
				$sortFieldsBatching = array_unique($sortFieldsBatching);
				// Get data for sorting fields
				$record_data_sorting = self::getData($Proj->project_id, 'array', $records, $sortFieldsBatching);
				// Loop through array and output line as CSV
				$record_data_formatted = array();
				foreach ($record_data_sorting as $this_record => &$event_data) {
					// Loop through events in this record
					foreach ($event_data as $this_event_id=>&$field_data) {
						$isRepeatEventOrForm = ($this_event_id == 'repeat_instances');
						// Add repeating events data
						if ($this_event_id != 'repeat_instances') {
							$field_data_instance = array($this_event_id => array('' => array(1 => $field_data)));
						}
						if ($this_event_id == 'repeat_instances') {
							$field_data_instance = $field_data;
						} elseif ($hasRepeatingEvents && $Proj->isRepeatingEvent($this_event_id) && isset($field_data[$this_event_id][''])) {
							// Repeating events only
							$field_data_instance[''] = $field_data_instance[''] + $field_data[$this_event_id][''];
						} // Add repeating forms data
						elseif ($hasRepeatingForms && !$Proj->isRepeatingEvent($this_event_id) && isset($field_data[$this_event_id])) {
							$field_data_instance = $field_data_instance + $field_data[$this_event_id];
						}
						$field_data = array(); // reset to save memory
						// Loop through fields in this event/repeat_instrument/instance
						foreach ($field_data_instance as $this_event_id => &$field_data_instance2) {
							foreach ($field_data_instance2 as $this_repeat_instrument => &$these_instances) {
								foreach ($these_instances as $this_instance => &$field_data2) {
									if (!$isRepeatEventOrForm) $this_instance = "";
									// If filtering the results using a logic string, then skip this record-event if doesn't match valid logic
									if ($filterResults) {
										if (   ($filterType == 'RECORD' && !isset($record_events_filtered[$this_record]))
											|| ($filterType == 'EVENT' && !isset($record_events_filtered[$this_record][$this_event_id][$this_repeat_instrument][$this_instance]))
										) {
											continue;
										}
									}
									// Add value to array as lower case (since we need to do case insensitive sorting)
									foreach ($sortFields as $key => $this_sort_field) {
										$sortFieldValues[$key][] = strtolower($field_data2[$this_sort_field]);
										$record_data_formatted[$this_record."-".$this_event_id."-".$this_repeat_instrument."-".$this_instance] = $this_record;
									}
								}
							}
						}
					}
					unset($record_data_sorting[$this_record]);
				}
				unset($record_data_sorting, $event_data, $field_data, $field_data_instance2, $these_instances, $field_data2);
				// Sort the data array
				if (count($sortFieldValues) == 1) {
					// One sort field
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
						$record_data_formatted);
				} elseif (count($sortFieldValues) == 2) {
					// Two sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
						$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING),
						$record_data_formatted);
				} else {
					// Three sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
						$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING),
						$sortFieldValues[2], ($sortTypes[2] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[2] ? SORT_NUMERIC : SORT_STRING),
						$record_data_formatted);
				}
				$records = array_values($record_data_formatted);
			}

			// Put all data into string to store as file
			$dataString = '';
			// Set batch size based on project type
			$batchSize = ($longitudinal || $hasRepeatingFormsOrEvents) ? self::EXPORT_BATCH_SIZE_REPEATING : self::EXPORT_BATCH_SIZE_CLASSIC;
			// Place records into chunked array
			$records = array_chunk($records, $batchSize);
			if ($doSorting) {
				$record_data_formatted = array_chunk(array_keys($record_data_formatted), $batchSize);
			}
			// Set default arguments for the batches
			$batchArgs = array();
			foreach ($args_array as $key=>$val) {
				if (!isset($args[$key])) continue;
				$batchArgs[$val] = $args[$key];
			}
			unset($args);
			// Loop through each batch
			foreach ($records as $key=>$recordsBatch) {
				unset($records[$key]);
				// Set arguments for this batch
				$batchArgs['records'] = $recordsBatch;
				$batchArgs['doBatching'] = false;
				$batchArgs['batchNum'] = $key+1;
				// If sorting, then allowlist each record-event-repeat_instrument-instance that will be in this batch
				if ($doSorting) {
					$batchArgs['includeRecordEvents'] = array();
					foreach ($recordsBatch as $rkey=>$this_record) {
						if (!isset($record_data_formatted[$key][$rkey])) continue;
						list ($this_id, $this_event_id, $repeat_instrument, $repeat_instance) = explode_right("-", $record_data_formatted[$key][$rkey], 4);
						$batchArgs['includeRecordEvents'][$this_record][$this_event_id][$repeat_instrument][$repeat_instance] = true;
					}
				}
				// Get data for this batch
				$thisBatch = self::getData($batchArgs);
				// Add character between collected batches
				$charBetweenBatches = ($return_format == 'json' && $dataString != '' && !empty($thisBatch)) ? ',' : '';
				// Add this batch to all previous
				$dataString .= $charBetweenBatches . $thisBatch;
			}
			// Final additions
			if ($return_format == 'csv') {
				// CSV headers are always returned for first batch when batching, so if all batches end up blank, remove the CSV headers
				$csvContainsOnlyHeaders = (strpos(trim($dataString), "\n") === false && strpos(trim($dataString), "\r") === false);
				if ($csvContainsOnlyHeaders) {
					$dataString = ""; // Set CSV as empty with no headers since no data was returned
				}
			} elseif ($return_format == 'xml') {
				$dataString .= "</records>";
			} elseif ($return_format == 'json') {
				$dataString .= "]";
				// If the beginning of the JSON is malformed (how?), then fix it
				if (strpos($dataString, "[,{") === 0) {
					$dataString = "[{" . substr($dataString, 3);
				}
			} elseif ($return_format == 'odm') {
				$dataString .= "</ClinicalData>\n</ODM>\n";
			}
			return $dataString;
		}

		## PIPING and ONTOLOGY AUTO-SUGGEST (only for exporting labels OR for displaying reports)
		## Ontology auto-suggest: Obtain labels for the raw notation values.
		$piping_receiver_fields = array();
		$ontology_auto_suggest_fields = $ontology_auto_suggest_cats = $ontology_auto_suggest_labels = array();
		$do_label_piping = false;
		if ($exportAsLabels || $return_format == 'html') {
			// If any dropdowns, radios, or checkboxes are using piping in their option labels, then get data for those and then inject them
			$piping_transmitter_fields = $piping_record_data = array();
			foreach ($fields as $this_field) {
				// Get field type
				$this_field_type = $Proj->metadata[$this_field]['element_type'];
				// Get choices
				$this_field_enum = $Proj->metadata[$this_field]['element_enum'];
				// If Text field with ontology auto-suggest
				if ($this_field_type == 'text' && $this_field_enum != '' && strpos($this_field_enum, ":") !== false) {
					// Get the name of the name of the web service API and the category (ontology) name
					list ($this_autosuggest_service, $this_autosuggest_cat) = explode(":", $this_field_enum, 2);
					// Add to arrays
					$ontology_auto_suggest_fields[$this_field] = array('service'=>$this_autosuggest_service, 'category'=>$this_autosuggest_cat);
					$ontology_auto_suggest_cats[$this_autosuggest_service][$this_autosuggest_cat] = true;
				}
				// If multiple choice
				elseif (in_array($this_field_type, array('dropdown','select','radio','checkbox'))) {
					// If has at least one left and right square bracket
					if ($this_field_enum != '' && strpos($this_field_enum, '[') !== false && strpos($this_field_enum, ']') !== false) {
						// If has at least one field piped
						$these_piped_fields = array_keys(getBracketedFields($this_field_enum, true, true, true));
						if (!empty($these_piped_fields)) {
							$piping_receiver_fields[] = $this_field;
							$piping_transmitter_fields = array_merge($piping_transmitter_fields, $these_piped_fields);
						}
					}
				}
			}
			// GET CACHED LABELS AUTO-SUGGEST ONTOLOGIES
			if (!empty($ontology_auto_suggest_fields)) {
				// Obtain all the cached labels for these ontologies used
				$subsql = array();
				foreach ($ontology_auto_suggest_cats as $this_service=>$these_cats) {
					$subsql[] = "(service = '".db_escape($this_service)."' and category in (".prep_implode(array_keys($these_cats))."))";
				}
				$sql = "select service, category, value, label from redcap_web_service_cache
						where project_id = $project_id and (" . implode(" or ", $subsql) . ")";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					$ontology_auto_suggest_labels[$row['service']][$row['category']][$row['value']] = $row['label'];
				}
				// Remove unneeded variable
				unset($ontology_auto_suggest_cats);
			}
			// GET DATA FOR PIPING FIELDS
			if (!empty($piping_receiver_fields)) {
				// Get data
				$piping_record_data = self::getData('array', $records, $piping_transmitter_fields);
				// Remove unneeded variables
				unset($piping_transmitter_fields, $potential_piping_fields);
				// Set flag
				$do_label_piping = true;
			}
		}

		## GATHER DEFAULT VALUES
		// Get default values for all records (all fields get value '', except Form Status and checkbox fields get value 0)
		$default_values = $mc_choice_labels = $field_event_designation = array();
		$prev_form = null;
		foreach ($fields as $this_field)
		{
			// Get field's field type
			$field_type = $Proj->metadata[$this_field]['element_type'];
			// If exporting labels for multiple choice questions, store codes/labels in array for later use when replacing
			if ($exportAsLabels && ($field_type == 'checkbox' || in_array($field_type, $mc_field_types))) {
				if ($field_type == "yesno") {
					$mc_choice_labels[$this_field] = parseEnum("1, {$lang['design_100']} \\n 0, {$lang['design_99']}");
				} elseif ($field_type == "truefalse") {
					$mc_choice_labels[$this_field] = parseEnum("1, {$lang['design_186']} \\n 0, {$lang['design_187']}");
				} else {
					$enum = ($field_type == "sql") ? getSqlFieldEnum($Proj->metadata[$this_field]['element_enum']) : $Proj->metadata[$this_field]['element_enum'];
					foreach (parseEnum($enum) as $this_value=>$this_label) {
						// Decode (just in case)
						$this_label = html_entity_decode($this_label, ENT_QUOTES);
						// Replace double quotes with single quotes
						$this_label = str_replace("\"", "'", $this_label);
						// Replace line breaks with two spaces
						$this_label = str_replace("\r\n", "  ", $this_label);
						// Add to array
						$mc_choice_labels[$this_field][$this_value] = $this_label;
					}
				}
			}
			// Loop through all designated events so that each event
			foreach (array_keys($Proj->eventInfo) as $this_event_id)
			{
				// If event_id isn't in list of event_ids provided, then skip
				if (!in_array($this_event_id, $events)) continue;
				// Get the form_name of this field
				$this_form = $Proj->metadata[$this_field]['form_name'];
				// If we're starting a new survey, then add its Timestamp field as the first field in the instrument
				if ($exportSurveyFields && $this_field != $table_pk && isset($Proj->forms[$this_form]['survey_id'])) {
					$default_values[$this_event_id][$this_form.'_timestamp'] = '';
				}
				// If longitudinal, is this form designated for this event
				$validFormEvent = (!$longitudinal || ($longitudinal && in_array($this_form, $Proj->eventsForms[$this_event_id])));
				// If longitudinal with 'array' format and flag is set to not add non-designated fields to array, then ignore
				if ($removeNonDesignatedFieldsFromArray && !$validFormEvent) continue;
				// Add any fields that do not belong on this event to an array (reports only)
				if ($return_format == 'html' && !$validFormEvent && $this_field != $table_pk) {
					if ($Proj->isCheckbox($this_field)) {
						foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $choice) {
							$this_field2 = $Proj->getExtendedCheckboxFieldname($this_field, $choice);
							$field_event_designation[$Proj->getUniqueEventNames($this_event_id)][$this_field2] = true;
						}
					} else {
						$field_event_designation[$Proj->getUniqueEventNames($this_event_id)][$this_field] = true;
					}
				}
				// Check a checkbox or Form Status field
				if ($Proj->isCheckbox($this_field)) {
					// Loop through all choices and set each as 0
					foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $choice) {
						// Set default value as 0 (unchecked)
						if (!$validFormEvent || ($exportAsLabels && $outputCheckboxLabel)) {
							$default_values[$this_event_id][$this_field][$choice] = '';
						} elseif ($exportAsLabels) {
							$default_values[$this_event_id][$this_field][$choice] = $lang['global_144'];
						} else {
							$default_values[$this_event_id][$this_field][$choice] = '0';
						}
					}
					// Add all Missing Data Codes as extra options (unless field has @NOMISSING action tag)
					if (!$removeMissingDataCodes && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_field]['misc'])) {
						foreach ($missingDataCodes as $choice=>$choiceLabel) {
							if (!$validFormEvent || ($exportAsLabels && $outputCheckboxLabel)) {
								$default_values[$this_event_id][$this_field][$choice] = '';
							} elseif ($exportAsLabels) {
								$default_values[$this_event_id][$this_field][$choice] = $lang['global_144'];
							} else {
								$default_values[$this_event_id][$this_field][$choice] = '0';
							}
						}
					}
				} elseif ($this_field == $this_form . "_complete") {
					// Set default Form Status as 0
					if (!$validFormEvent) {
						$default_values[$this_event_id][$this_field] = '';
					} elseif ($exportAsLabels) {
						$default_values[$this_event_id][$this_field] = 'Incomplete';
					} else {
						$default_values[$this_event_id][$this_field] = '0';
					}
				} else {
					// Set as ''
					$default_values[$this_event_id][$this_field] = '';
					// If this is the Record ID field and we're exporting DAG names and/or survey fields, them add them.
					// If the Record ID field is not included in the report, then add DAG names and/or survey fields if not already added.
					if ($this_field == $table_pk || !in_array($table_pk, $fields)) {
						// DAG field
						if ($exportDataAccessGroups && !isset($default_values[$this_event_id]['redcap_data_access_group'])) {
							$default_values[$this_event_id]['redcap_data_access_group'] = '';
						}
						if ($outputSurveyIdentifier && !isset($default_values[$this_event_id]['redcap_survey_identifier'])) {
							// Survey Identifier field
							$default_values[$this_event_id]['redcap_survey_identifier'] = '';
							// Survey Timestamp field (first instrument only - for other instruments, it's doing this same thing above in the loop)
							// if ($prev_form == null && isset($Proj->forms[$this_form]['survey_id'])) {
								// $default_values[$this_event_id][$this_form.'_timestamp'] = '';
							// }
						}
					}
				}
				// Set for next loop
				$prev_form = $this_form;
			}
		}

		## QUERY DATA TABLE
		// Set main query
		$sql = "select record, event_id, field_name, value, instance from redcap_data
				where project_id = " . $project_id . " and record != ''";
		if (!empty($events)) {
			$sql .= " and event_id in (" . prep_implode($events) . ")";
		}
		if (!$checkFieldNameEachLoop && !empty($fields)) {
			$sql_fields = " and field_name in (" . prep_implode($fields) . ")";
            if (strlen($sql.$sql_fields) > 1000000) {
                $checkFieldNameEachLoop = true;
            } else {
                $sql .= $sql_fields;
            }
		}
		if (!$checkRecordNameEachLoop && !empty($records)) {
			$sql_records = " and record in (" . prep_implode($records) . ")";
            if (strlen($sql.$sql_records) > 1000000) {
                $checkRecordNameEachLoop = true;
            } else {
                $sql .= $sql_records;
            }
		}
		// If we are to return records for specific DAG(s) but those DAGs contain no records, then cause the query to return nothing.
		if (!$hasDagRecords && !empty($groups)) {
			$sql .= " and 1 = 2";
		}
		// If project does not have repeating forms or events, only return instance 1 data
		if (!$hasRepeatingFormsEvents) {
			$sql .= " and instance is null";
		}
		// Order query results by record name if constant has been defined
		if ($write_data_to_file) {
			$sql .= " order by record, event_id";
		}
		// Use unbuffered query method
		$q = db_query($sql, null, MYSQLI_USE_RESULT);
		// Return database query error to super users
		if (defined('SUPER_USER') && SUPER_USER && db_error() != '') {
			print "<br><b>MySQL Error:</b> ".db_error()."<br><b>Query:</b> $sql<br><br>";
		}
		// Set flag is record ID field is a display field
		$recordIdInFields = (in_array($Proj->table_pk, $fields));
		// Remove unnecessary things for memory usage purposes
		unset($fields);
		// Set intial values
		$downloadDocBaseUrl = APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id";
		$num_rows_returned = 0;
		$event_id = 0;
		$record = "";
		$record_data = array();
		$hasRepeatedInstances = false;
		$repeatingInstanceRecordMap = array();
		$days_to_shift = array();
		$record_data_tmp_line = array();
		$record_data_tmp_line_num = 1;
		// If writing data to a file instead to memory, create temp file
		if ($write_data_to_file) {
			$record_data_tmp_filename = self::generateTempFileName(60);
			$record_data_tmp_file = fopen($record_data_tmp_filename, 'w+');
			// Set the file to be deleted automatically when the script ends
			Files::delete_file_on_shutdown($record_data_tmp_file, $record_data_tmp_filename);
		}
		// Loop through data one record at a time
		$dataPtCount = 1;
		$dataPtCheckRam = 5000;
		$checkIncludeRecordEvents = !empty($includeRecordEvents);
		while ($row = db_fetch_assoc($q))
		{
			// Increment counter
			$dataPtCount++;
			// If value is blank, then skip
			if ($row['value'] == '') continue;
			// If we need to validate the record name in each loop, then check.
			if ($checkRecordNameEachLoop && !isset($recordsKeys[$row['record']])) continue;
			// If we need to validate the field name in each loop, then check.
			if ($checkFieldNameEachLoop && !isset($fieldsKeys[$row['field_name']])) continue;
			// Repeating forms/events
			$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
			$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $Proj->metadata[$row['field_name']]['form_name']));
			$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
			$repeat_instrument = $isRepeatForm ? $Proj->metadata[$row['field_name']]['form_name'] : "";
			if ($row['instance'] === null) {
				$row['instance'] = $isRepeatEventOrForm ? 1 : "";
				$instance = 1;
			} else {
				$instance = $row['instance'];
			}
			// If this is not a repeating form/event, but instance > 1, then skip this value because apparently it is an orphaned value
			if (!$isRepeatEventOrForm && $instance > 1) continue;
			// If filtering the results using a logic string, then skip this record-event if doesn't match valid logic
			if ($filterResults) {
				if (   ($filterType == 'RECORD' && !isset($record_events_filtered[$row['record']]))
					|| ($filterType == 'EVENT' && !isset($record_events_filtered[$row['record']][$row['event_id']][$repeat_instrument][$row['instance']]))
				) {
					continue;
				}
			}
			// If $includeRecordEvents was provided as an argument, then use it as a allowlist for record-event-repeat_instrument-instance
			if ($checkIncludeRecordEvents && !isset($includeRecordEvents[$row['record']][$row['event_id']][$repeat_instrument][$row['instance']])) {
				continue;
			}
			// Add initial default data for this record-event
			if (!isset($record_data[$row['record']][$row['event_id']]))
			{
				// If over X data points since last check, then reset
				if ($dataPtCount >= $dataPtCheckRam) $dataPtCount = 0;
				// MEMORY CHECK: If we're close to running out of memory, then set contstant and call this method recursively
				// to start over and write data to file instead of to memory (to allow full data export w/o hitting memory limits).
				if (!$write_data_to_file && $dataPtCount == 0 && $return_format != 'array' && memory_get_usage() >= $memory_limit*0.8) {
					// Unset variables to clear up memory
					unset($record_data, $records, $recordsKeys, $fieldsKeys, $record_events_filtered, $repeatingInstanceRecordMap);
					// Close the unbuffered query
					db_free_result($q);
					// Call this function with constant defined to start over while writing data to file
					$write_data_to_file_key = (is_numeric($args[0])) ? 24 : 23;
					$newargs = array();
					for ($i = 0; $i < $write_data_to_file_key; $i++) {
						$newargs[$i] = ($args[$i]) ? $args[$i] : null;
					}
					$newargs[$write_data_to_file_key] = true;
					return call_user_func_array('Records::getData', $newargs);
				} elseif ($write_data_to_file && $event_id > 0) {
					// Write data to temp file
					if (isset($record_data[$record]['repeat_instances']) && isset($record_data_tmp_line[$record]['repeat_instances'])) {
						// Add to existing repeat instances
						self::updateDataToFile($record_data, $default_values);
					} else {
						// Add new line in file
						self::insertDataToFile($record_data, $removeLineBreaksInValues);
					}
				}
				// DEFAULT VALUES: Add default data to pre-fill new record
				if (!$isRepeatEventOrForm) {
					$record_data[$row['record']][$row['event_id']] = $default_values[$row['event_id']];
				}
				// Get date shift amount for this record (if applicable)
				if ($dateShiftDates) {
					$days_to_shift[$row['record']] = self::get_shift_days($row['record'], $Proj->project['date_shift_max'], $Proj->project['__SALT__']);
				}
			}
			// Add initial default data for this record-event
			if ($isRepeatEventOrForm && !isset($record_data[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$instance])
				// Ignore adding defaults for this data point if this is record ID field is on non-base instance
				&& !($isRepeatForm && $instance > 1 && $row['field_name'] == $Proj->table_pk)
			) {
				// Add default data
				$record_data[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$instance] = $default_values[$row['event_id']];
				if (isset($default_values[$row['event_id']][$Proj->table_pk])) {
					// Add record name to repeated instance sub-arrays
					$record_data[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$instance][$Proj->table_pk] = $row['record'];
				}
				// Set flag
				$hasRepeatedInstances = true;
				// Set mapping for record-event-repeat_instrument-instance
				if ($exportSurveyFields || $exportDataAccessGroups) {
					$repeatingInstanceRecordMap[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$instance] = array();
				}
			}
			// Decode the value
			$row['value'] = html_entity_decode($row['value'], ENT_QUOTES);
			// If passing flag to remove any missing data codes, then set any codes to blank value
			if ($removeMissingDataCodes && isset($missingDataCodes[$row['value']])) {
				$row['value'] = '';
			}
			// Convert the decimal character?
			if ($convertDecimal && isset($fieldsDecimalConvert[$row['field_name']])) {
				$row['value'] = str_replace($decimalCharacterConvertFrom, $decimalCharacter, $row['value']);
			}
			// Set values for this loop
			$event_id = $row['event_id'];
			$record   = $row['record'];
			// Add the value into the array (double check to make sure the event_id still exists)
			if (isset($unique_events[$event_id]))
			{
				// Get field's field type
				$field_type = $Proj->metadata[$row['field_name']]['element_type'];
				if ($field_type == 'checkbox') {
					//check for missing data code. 
					//This part not working as expected, seems to add and extra cell to the table row for each missing data code in a checkbox, putting the data out of line with the column headers.
					
					// $thisValue=$row['value'];
					// if (in_array($thisValue, array_keys($missingDataCodes))){
						// //If missing data code, do this:
						
						
							// if ($exportAsLabels) {
								// // If using $outputCheckboxLabel API flag, then output the choice label
								// if ($outputCheckboxLabel) {
									// // Get MC option label
									// $this_mc_label = $missingDataCodes[$value];
									// // PIPING (if applicable)
									// //not sure if this works or is relevant?
									// // if ($do_label_piping && in_array($row['field_name'], $piping_receiver_fields)) {
										// // $this_mc_label = strip_tags(Piping::replaceVariablesInLabel($this_mc_label, $record, $event_id, 1, $piping_record_data));
									// // }
									// // Add option label
									// if ($isRepeatEventOrForm) {
										// $record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = $this_mc_label;
									// } else {
										// $record_data[$record][$event_id][$row['field_name']][$row['value']] = $this_mc_label;
									// }
								// } else {
									// if ($isRepeatEventOrForm) {
										// $record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = $lang['global_143'];
									// } else {
										// $record_data[$record][$event_id][$row['field_name']][$row['value']] = $lang['global_143'];
									// }
								// }
							// } else {
								// if ($isRepeatEventOrForm) {
									// $record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = $thisValue;
								// } else {
									// $record_data[$record][$event_id][$row['field_name']][$row['value']] = $thisValue;
									// }
							// }
						
						
						
						
					// }else{
						//if not a missing data code,  do this
							

						// Make sure that this checkbox option still exists
						if (isset($default_values[$event_id][$row['field_name']][$row['value']]) ) {
							// Add checkbox field value
							if ($exportAsLabels) {
								// If using $outputCheckboxLabel API flag, then output the choice label
								if ($outputCheckboxLabel) {
									// Get MC option label
									$this_mc_label = $mc_choice_labels[$row['field_name']][$row['value']];
									// PIPING (if applicable)
									if ($do_label_piping && in_array($row['field_name'], $piping_receiver_fields)) {
										$this_mc_label = strip_tags(Piping::replaceVariablesInLabel($this_mc_label, $record, $event_id, 1, $piping_record_data));
									}
									// Add option label
									if ($isRepeatEventOrForm) {
										$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = $this_mc_label;
									} else {
										$record_data[$record][$event_id][$row['field_name']][$row['value']] = $this_mc_label;
									}
								} else {
									if ($isRepeatEventOrForm) {
										$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = $lang['global_143'];
									} else {
										$record_data[$record][$event_id][$row['field_name']][$row['value']] = $lang['global_143'];
									}
								}
							} else {
								if ($isRepeatEventOrForm) {
									$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']][$row['value']] = '1';
								} else {
									$record_data[$record][$event_id][$row['field_name']][$row['value']] = '1';
									}
							}
						}
					
					// }
				} else {
					// Non-checkbox field value
					// When outputting labels for TEXT fields with ONTOLOGY AUTO-SUGGEST, replace value with cached label
					if ($exportAsLabels && isset($ontology_auto_suggest_fields[$row['field_name']]) && !isset($missingDataCodes[$row['value']])) {
						// Replace value with label
						if ($ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$row['field_name']]['service']][$ontology_auto_suggest_fields[$row['field_name']]['category']][$row['value']]) {
							$row['value'] = $ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$row['field_name']]['service']][$ontology_auto_suggest_fields[$row['field_name']]['category']][$row['value']];
						}

                        // Check if we should replace any line breaks with spaces
						if ($removeLineBreaksInValues) {
							$row['value'] = str_replace($origLineBreak, $replLineBreak, $row['value']);

							// Check if we should replace double quotes with single quotes
							if ($replaceDoubleQuotes) {
								$row['value'] = str_replace("\"", "'", $row['value']);
							}
						}
						// Add cached label
						if ($isRepeatEventOrForm) {
							$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $row['value'];
						} else {
							$record_data[$record][$event_id][$row['field_name']] = $row['value'];
						}
					}
					// When outputting labels for MULTIPLE CHOICE questions (excluding checkboxes), add choice labels to answers_labels
					elseif ($exportAsLabels && isset($mc_choice_labels[$row['field_name']]) && !isset($missingDataCodes[$row['value']])) {
						// Get MC option label
						$this_mc_label = $mc_choice_labels[$row['field_name']][$row['value']];
						// PIPING (if applicable)
						if ($do_label_piping && in_array($row['field_name'], $piping_receiver_fields)) {
							$this_mc_label = strip_tags(Piping::replaceVariablesInLabel($this_mc_label, $record, $event_id, 1, $piping_record_data));
						}
						// Add option label
						if ($isRepeatEventOrForm) {
							$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $this_mc_label;
						} else {
							$record_data[$record][$event_id][$row['field_name']] = $this_mc_label;
						}
					} else {
						// Shift all date[time] fields, when applicable
						if ($dateShiftDates && $field_type == 'text' && !isset($missingDataCodes[$row['value']])
							&& (substr($Proj->metadata[$row['field_name']]['element_validation_type'], 0, 8) == 'datetime'
								|| in_array($Proj->metadata[$row['field_name']]['element_validation_type'], array('date', 'date_ymd', 'date_mdy', 'date_dmy'))))
						{
							if ($isRepeatEventOrForm) {
								$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = Records::shift_date_format($row['value'], $days_to_shift[$record]);
							} else {
								$record_data[$record][$event_id][$row['field_name']] = Records::shift_date_format($row['value'], $days_to_shift[$record]);
							}
						}
						// For "File Upload" fields, replace doc_id value with [document] if flag is set
						elseif ($replaceFileUploadDocId && $field_type == 'file' && $row['value'] != '') {
							if (isset($missingDataCodes[$row['value']])) {
								$thisFileValue = $removeMissingDataCodes ? '' : $row['value'];
								if ($isRepeatEventOrForm) {
									$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $thisFileValue;
								} else {
									$record_data[$record][$event_id][$row['field_name']] = $thisFileValue;
								}
							} elseif ($return_format == 'html') { // On reports, display a download button
								$downloadDocUrl = "<button class='btn btn-defaultrc btn-xs' style='font-size:8pt;' onclick=\"window.open('$downloadDocBaseUrl&s=&record=$record&event_id=$event_id&instance=$instance&field_name={$row['field_name']}&id={$row['value']}&doc_id_hash=".Files::docIdHash($row['value'])."','_blank');\">{$lang['design_121']}</button>";
								if ($isRepeatEventOrForm) {
									$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $downloadDocUrl;
								} else {
									$record_data[$record][$event_id][$row['field_name']] = $downloadDocUrl;
								}
							} elseif ($return_format != 'odm') { // Don't swap the edoc_id for [document] in ODM format
								if ($isRepeatEventOrForm) {
									$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = '[document]';
								} else {
									$record_data[$record][$event_id][$row['field_name']] = '[document]';
								}
							}
						}
						// Add raw value
						else {

                            // Check if we should replace any line breaks with spaces
							if ($removeLineBreaksInValues) {
								$row['value'] = str_replace($origLineBreak, $replLineBreak, $row['value']);

								// Check if we should replace double quotes with single quotes
								if ($replaceDoubleQuotes) {
									$row['value'] = str_replace("\"", "'", $row['value']);
								}
							}
							// Add value
							if ($isRepeatEventOrForm) {
								// Ignore adding this data point if this is record ID field is on non-base instance
								if (!($isRepeatForm && $instance > 1 && $row['field_name'] == $Proj->table_pk)) {
									$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $row['value'];
								}
							} else {
								$record_data[$record][$event_id][$row['field_name']] = $row['value'];
							}
						}
					}
					// Exporting LABELS for Missing Data Codes
					if ($exportAsLabels && $row['value'] != '' && isset($missingDataCodes[$row['value']])
						&& !Form::hasActionTag("@NOMISSING", $Proj->metadata[$row['field_name']]['misc']))
					{
						if ($isRepeatEventOrForm) {
							$record_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$row['field_name']] = $missingDataCodes[$row['value']];
						} else {
							$record_data[$record][$event_id][$row['field_name']] = $missingDataCodes[$row['value']];
						}
					}
				}
			}
			// Increment row counter
			$num_rows_returned++;
		}
		// If writing data to file and $record_data is not empty, then write it to file
		if ($write_data_to_file && !empty($record_data)) {
			// Write data to temp file
			if (isset($record_data[$record]['repeat_instances']) && isset($record_data_tmp_line[$record]['repeat_instances'])) {
				// Add to existing repeat instances
				self::updateDataToFile($record_data, $default_values);
			} else {
				// Add new line in file
				self::insertDataToFile($record_data, $removeLineBreaksInValues);
			}
		}
		// Free MySQL results
		db_free_result($q);

		// If query returns 0 rows, then simply put default values for $record_data as placeholder for blanks and other defaults.
		// If DAGs were specified as input parameters but there are no records in those DAGs, then output NOTHING but a blank array.
		if ($num_rows_returned < 1 && !($hasDagRecords && !empty($groups))) {
			// If no records were explicitly provided to be returned
			if ($recordsEmpty) {
				// Loop through ALL records and add default values for each.
				// If no events were explicitly provided to be returned...
				if (!$filterReturnedEmptySet && empty($events)) {
					// If we're dealing with multiple arms, then make sure we exclude arms that have no data
					if ($Proj->multiple_arms) {
						// Get list of arms for the events we need data for
						$arms = array();
						foreach ($events as $this_event_id) {
							$arms[] = $Proj->eventInfo[$this_event_id]['arm_num'];
						}
						$arms = array_unique($arms);
						$recordsArms = array();
						foreach ($arms as $arm) {
							$recordsArms = array_merge($recordsArms, self::getRecordList($project_id, null, false, false, $arm));
						}
						$records = array_values($recordsArms);
						natcasesort($records);
					}
					// Output default values for these records
					foreach ($records as $this_record) {
						$record_data[$this_record] = $default_values;
					}
				}
            } elseif ($returnEmptyEvents) {
				// Validate the records passed in $records and loop through them and add default values for each
				foreach (array_intersect($records, self::getRecordList($project_id)) as $this_record) {
					$record_data[$this_record] = $default_values;
				}
			}
		}
		
		// If returning empty events of data (longitudinal only), then go through each record to make sure it has all project events
		if ($returnEmptyEvents)
		{
			foreach ($record_data as $this_record=>$eattr) {
				// Find all events that a missing for this record and add them to array
				foreach (array_diff_key($default_values, $eattr) as $this_event_id=>$attr) {
					$record_data[$this_record][$this_event_id] = $attr;
				}
			}
		}
		
		// In a longitudinal project with repeating forms, if all forms on a given event are repeating forms, 
		// then remove the base instance of that event because it would otherwise be empty and unnecessary.
		if ($longitudinal && $hasRepeatedInstances && $Proj->hasRepeatingForms())
		{
			$Proj->setAllEventFormsAreRepeatingForms();
			if (!empty($Proj->eventsWhereAllFormsAreRepeatingForms)) {
				foreach ($record_data as $this_record=>$eattr) {
					foreach (array_keys($eattr) as $this_event_id) {
						if ($this_event_id == 'repeat_instances') continue;
						if (!$Proj->allEventFormsAreRepeatingForms($this_event_id)) continue;
						// Remove the empty base instance
						unset($record_data[$this_record][$this_event_id]);
					}
				}
				unset($eattr);
			}
		}
		
		// REPORTS ONLY: If the Record ID field is included in the report, then also display the Custom Record Label
		$extra_record_labels = array();
		if ($return_format == 'html' && $recordIdInFields) {
			$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, false, 'all');
		}

		## SORT RECORDS BY RECORD NAME (i.e., array keys) using case insensitive natural sort
		// Sort by record and event name ONLY if we are NOT sorting by other fields
		if (empty($sortArray))
		{
			if ($write_data_to_file) {
				// WRITING TO FILE
				// Now use newly-sorted array to rewrite the data temp file
				self::resortDataToFileByRecordEvent($Proj);
			} else {
				// Sort array using case insensitive natural sort
				natcaseksort($record_data);
				## SORT EVENTS WITHIN EACH RECORD (LONGITUDINAL ONLY)
				if ($longitudinal || $hasRepeatedInstances) {
					// Create array of event_id's in order by arm_num, then by event order
					$event_order = array_keys($Proj->eventInfo);
					// Loop through each record and reorder the events (if has more than one event of data per record)
					foreach ($record_data as $this_record=>&$these_events) {
						// Set array to collect the data for this record in reordered form
						$this_record_data_reordered = array();
						// Skip if there's only one event with data
						if (count($these_events) == 1) continue;
						// Loop through all existing PROJECT events in their proper order
						if ($longitudinal) {
							foreach (array_intersect($event_order, array_keys($these_events)) as $this_event_id) {
								// Skip this event if it's a repeating event (because it will be ordered below, not here)
								if ($Proj->isRepeatingEvent($this_event_id)) continue;
								// Add this event's data to reordered data array
								$this_record_data_reordered[$this_event_id] = $these_events[$this_event_id];
							}
						} else {
							$this_record_data_reordered[$Proj->firstEventId] = $these_events[$Proj->firstEventId];
						}
						// If we have repeating events/formsform
						$this_record_data_reordered2 = array();
						if ($hasRepeatedInstances && isset($record_data[$this_record]['repeat_instances'])) {
							// Loop through all existing PROJECT events in their proper order
							foreach (array_intersect($event_order, array_keys($record_data[$this_record]['repeat_instances'])) as $this_event_id) {
								// Make sure th repeating instruments are in correct form order
								if (count($record_data[$this_record]['repeat_instances'][$this_event_id]) > 1) {									
									// Loop through all existing PROJECT events in their proper order
									$this_record_data_repeat_instrument_reordered = array();
									foreach (array_intersect(array_keys($Proj->forms), array_keys($record_data[$this_record]['repeat_instances'][$this_event_id])) as $this_repeat_form) {
										if ($this_repeat_form == '') continue;
										// Add this repeating instrument's data to reordered data array
										$this_record_data_repeat_instrument_reordered[$this_repeat_form] = $record_data[$this_record]['repeat_instances'][$this_event_id][$this_repeat_form];
										unset($record_data[$this_record]['repeat_instances'][$this_event_id][$this_repeat_form]);
									}
									// Add reordered repeating instruments
									$record_data[$this_record]['repeat_instances'][$this_event_id] = $this_record_data_repeat_instrument_reordered;
									unset($this_record_data_repeat_instrument_reordered);
								}
								// Loop through repeating instruments/instances to reorder the instances inside the instruments
								foreach ($record_data[$this_record]['repeat_instances'][$this_event_id] as $this_repeat_form=>&$these_instances) {
									// If this is a repeating event ($this_repeat_form=''), then make sure its 
									// first instance falls right before instance 2 so that everything is ordered by record, event, repeat_form, instance
									if ($this_repeat_form == '' && isset($this_record_data_reordered[$this_event_id])) {
										// Move the first instance to right before other instances for this repeating event
										$these_instances[1] = $this_record_data_reordered[$this_event_id];
										// Remove from original array
										unset($this_record_data_reordered[$this_event_id]);
									}
									// Sort by instance number
									ksort($these_instances);
									// Add this event's data to reordered data array
									$this_record_data_reordered2[$this_event_id][$this_repeat_form] = $these_instances;
								}
								unset($these_instances);
							}
						}
						// Replace old data with reordered data
						$record_data[$this_record] = $this_record_data_reordered;
						if (!empty($this_record_data_reordered2)) {
							$record_data[$this_record]['repeat_instances'] = $this_record_data_reordered2;
						}
					}
					// Remove unnecessary things for memory usage purposes
					unset($this_record_data_reordered, $this_record_data_reordered2, $these_events, $event_order);
				}
			}
		}
		
		// Classic: Sort repeating instances by number
		if (!$longitudinal && !$write_data_to_file && $hasRepeatedInstances) 
		{
			// Loop through each record and reorder the events (if has more than one event of data per record)
			foreach ($record_data as $this_record=>&$these_events) {
				// If we have repeating events/formsform
				$this_record_data_reordered2 = array();
				if (isset($record_data[$this_record]['repeat_instances'])) {
					// Loop through all existing PROJECT events in their proper order
					foreach ($record_data[$this_record]['repeat_instances'][$Proj->firstEventId] as $this_repeat_form=>&$these_instances) {
						// Sort by instance number
						ksort($these_instances);
						// Add this event's data to reordered data array
						$this_record_data_reordered2[$Proj->firstEventId][$this_repeat_form] = $these_instances;
					}
					unset($these_instances);
				}
				// Replace old data with reordered data
				if (!empty($this_record_data_reordered2)) {
					$record_data[$this_record]['repeat_instances'] = $this_record_data_reordered2;
				}
			}
			unset($these_events);
		}
		
		## ADD DATA ACCESS GROUP NAMES (IF APPLICABLE)
		if ($exportDataAccessGroups) {
			// If exporting labels, then create array of DAG labels
			if ($exportAsLabels) {
				$allDagLabels = $Proj->getGroups();
			}
			// Get all DAG values for the records
			$sql = "select distinct record, value from redcap_data
					where project_id = $project_id and field_name = '__GROUPID__'";
			if (!$checkRecordNameEachLoop) {
				// For performance reasons, don't use "record in ()" unless we really need to
				$sql .= " and record in (" . prep_implode($records, false) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Validate record name and DAG group_id value
				if (isset($allDags[$row['value']]) && (isset($record_data[$row['record']]) || isset($record_data_tmp_line[$row['record']]))) {
					// Add unique DAG name to every event for this record
					$eventList = $write_data_to_file ? array_keys($record_data_tmp_line[$row['record']]) : array_keys($record_data[$row['record']]);
					foreach ($eventList as $dag_event_id) {
						// Add DAG name or unique DAG name
						if ($dag_event_id == 'repeat_instances') {
							// Repeating instrument/events
							foreach ($repeatingInstanceRecordMap[$row['record']]['repeat_instances'] as $dag_event_id2=>&$fattr) {
								foreach ($fattr as $this_repeat_instrument=>$gattr) {
									foreach (array_keys($gattr) as $this_instance) {
										$record_data[$row['record']]['repeat_instances'][$dag_event_id2][$this_repeat_instrument][$this_instance]['redcap_data_access_group']
											= ($exportAsLabels) ? $allDagLabels[$row['value']] : $allDags[$row['value']];
									}
								}
							}
							unset($fattr);
						} else {
							$record_data[$row['record']][$dag_event_id]['redcap_data_access_group']
								= ($exportAsLabels) ? $allDagLabels[$row['value']] : $allDags[$row['value']];
						}
					}
				}
			}
			unset($allDagLabels, $eventList);
			// If writing data to file, add it now
			if ($write_data_to_file) self::updateDataToFile($record_data, $default_values);
		}

		## ADD SURVEY IDENTIFIER AND TIMESTAMP FIELDS FOR ALL SURVEYS
		if ($exportSurveyFields)
		{
			$sql = "select r.record, r.completion_time, p.participant_identifier, s.form_name, p.event_id, r.instance
					from redcap_surveys s, redcap_surveys_response r, redcap_surveys_participants p
					where p.participant_id = r.participant_id and s.project_id = $project_id and s.survey_id = p.survey_id
					and r.first_submit_time is not null and p.event_id in (" . prep_implode($events) . ")";
			if (!$checkRecordNameEachLoop) {
				// For performance reasons, don't use "record in ()" unless we really need to
				$sql .= " and r.record in (" . prep_implode($records, false) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Get instance number and repeat instrument
				$isRepeatEventOrForm = false; // default
				if ($hasRepeatingFormsEvents) {
					$isRepeatEvent = $Proj->isRepeatingEvent($row['event_id']);
					$isRepeatForm  = $isRepeatEvent ? false : $Proj->isRepeatingForm($row['event_id'], $row['form_name']);
					$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
					$repeat_instrument = $isRepeatForm ? $row['form_name'] : "";
				}
				// Make sure we have this record-event in array first
				if (!(isset($record_data[$row['record']][$row['event_id']]) || isset($record_data[$row['record']]['repeat_instances'][$row['event_id']])) && 
					!(isset($record_data_tmp_line[$row['record']][$row['event_id']]) || isset($record_data_tmp_line[$row['record']]['repeat_instances']))) {
					continue;
				}
				// Add participant identifier
				if ($row['participant_identifier'] != "" && isset($default_values[$row['event_id']]['redcap_survey_identifier'])) {
                    // Add identifier to EVERY event
                    foreach ($record_data[$row['record']] as $this_event_id=>$pattr) {
                       if (isset($pattr['redcap_survey_identifier'])) {
                           $record_data[$row['record']][$this_event_id]['redcap_survey_identifier'] = html_entity_decode($row['participant_identifier'], ENT_QUOTES);
                       }
                    }
                    if ($isRepeatEventOrForm && isset($repeatingInstanceRecordMap[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$row['instance']])) {
                    // Add identifier to EVERY event
                    foreach ($record_data[$row['record']]['repeat_instances'] as $this_event_id=>$pattr) {
                        foreach ($pattr as $this_ri=>$pattr2) {
                            foreach ($pattr2 as $this_i=>$pattr3) {
                                if (isset($pattr3['redcap_survey_identifier'])) {
						$record_data[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$row['instance']]['redcap_survey_identifier'] = html_entity_decode($row['participant_identifier'], ENT_QUOTES);
					}
				}
                        }
                    }
					}
				}
				// If response exists but is not completed, note this in the export
				if ($dateShiftSurveyTimestamps && $row['completion_time'] != "") {
					// Shift the survey timestamp, if applicable
					$row['completion_time'] = Records::shift_date_format($row['completion_time'], $days_to_shift[$row['record']]);
				} elseif ($row['completion_time'] == "") {
					// Replace with text "[not completed]" if survey wasn't completed
					$row['completion_time'] = "[not completed]";
				}
				// Add to record_data array
				if (isset($default_values[$row['event_id']][$row['form_name'].'_timestamp'])) {
					if (!$isRepeatEventOrForm) {
						$record_data[$row['record']][$row['event_id']][$row['form_name'].'_timestamp'] = $row['completion_time'];
					} elseif ($isRepeatEventOrForm && isset($repeatingInstanceRecordMap[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$row['instance']])) {
						$record_data[$row['record']]['repeat_instances'][$row['event_id']][$repeat_instrument][$row['instance']][$row['form_name'].'_timestamp'] = $row['completion_time'];
					}
				}
			}
			// If writing data to file, add it now
			if ($write_data_to_file) self::updateDataToFile($record_data, $default_values);
		}
		unset($days_to_shift, $repeatingInstanceRecordMap);

		## HASH THE RECORD ID (replace record names with hash value)
		if ($hashRecordID)
		{
			if ($write_data_to_file) {
				// WRITING TO FILE
				// Instantiate FileObject for our opened temp file to extract single lines of data
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				$record_data_tmp_line2 = array();
				// Loop through each record
				foreach ($record_data_tmp_line as $this_record=>$eattr) {
					// Hash the record name using a system-level AND project-level salt
					$this_new_record = md5($salt . $this_record . $Proj->project['__SALT__']);
					// Loop through each event, get its line number in file, then get and replace that line
					foreach ($eattr as $this_event_id=>$this_line_num) {
						// Move pointer to line for this record-event
						$fileSearch->seek($this_line_num-1); // this is zero based so need to subtract 1
						// Unserialize the line to an array
						$attr = unserialize(trim($fileSearch->current()));
						$attr = $attr[$this_record][$this_event_id];
						// If Record ID field exists in the report, then set it too at the value level
						if (isset($attr[$Proj->table_pk])) {
							$attr[$Proj->table_pk] = $this_new_record;
						}
						// Now re-add line to file under new record name
						Files::replaceLineInFile($record_data_tmp_filename, serialize(array($this_new_record=>array($this_event_id=>$attr))), $this_line_num);
					}
					// Add new record name
					$record_data_tmp_line2[$this_new_record] = $record_data_tmp_line[$this_record];
				}
				$record_data_tmp_line = $record_data_tmp_line2;
				$fileSearch = null;
			} else {
				// ARRAY
				foreach ($record_data as $this_record=>$eattr) {
					// Hash the record name using a system-level AND project-level salt
					$this_new_record = md5($salt . $this_record . $Proj->project['__SALT__']);
					// Add new record name
					$record_data[$this_new_record] = $record_data[$this_record];
					// Remove the old one
					unset($record_data[$this_record]);
					// If Record ID field exists in the report, then set it too at the value level
					foreach ($eattr as $this_event_id=>$attr) {
						if (isset($attr[$Proj->table_pk])) {
							$record_data[$this_new_record][$this_event_id][$Proj->table_pk] = $this_new_record;
						}
					}
				}
			}
			unset($eattr, $attr);
		}

		// Remove unnecessary things for memory usage purposes
		unset($records, $fieldsKeys, $recordsKeys, $record_events_filtered);
		db_free_result($q);

		// IF WE NEED TO REMOVE THE RECORD ID FIELD, then loop through all events of data and remove it
		// OR DEFAULT ZERO VALUES FOR REPEATING FORMS: Remove any 0s for checkboxes and form status fields from other forms on a repeating form instance
		if ($removeTablePk || $hasRepeatingForms)
		{
			if ($write_data_to_file) {
				// WRITING TO FILE
				// Instantiate FileObject for our opened temp file to extract single lines of data
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				// Loop through each record 
				foreach ($record_data_tmp_line as $this_record=>$eattr) {
					// Loop through each event, get its line number in file, then get and replace that line
					foreach ($eattr as $this_event_id=>$this_line_num) {
						// Move pointer to line for this record-event
						$fileSearch->seek($this_line_num-1); // this is zero based so need to subtract 1
						// Unserialize the line to an array
						$attr = unserialize(trim($fileSearch->current()));
						$attr = $attr[$this_record][$this_event_id];
						// Repeating forms
						if ($this_event_id == 'repeat_instances') {
							foreach ($attr as $this_real_event_id=>$battr) {
								foreach ($battr as $this_repeat_instrument=>$cattr) {
									foreach ($cattr as $this_instance=>$dattr) {
										// If Record ID field exists in the report, then set it too at the value level
										if ($removeTablePk && isset($dattr[$Proj->table_pk])) {
											unset($attr[$this_real_event_id][$this_repeat_instrument][$this_instance][$Proj->table_pk]);
										}
										// Repeating forms: Remove default 0s where needed
										if ($hasRepeatingForms) { 
											foreach ($dattr as $this_field2=>$this_val2) {
												$isCheckbox = is_array($this_val2);
												if ($isCheckbox || $Proj->isFormStatus($this_field2)) {
													// Get this field's form
													$this_form = $Proj->metadata[$this_field2]['form_name'];
													// Is this field's form a repeating form? If not, then move along to next loop.
													//if (!$Proj->isRepeatingForm($this_real_event_id, $this_form)) continue;
													// If the field is on the current repeating form, then leave its defaults as-is and move to next loop.
													if ($this_form == $this_repeat_instrument || $Proj->isRepeatingEvent($this_real_event_id)) continue;
													// Clear out the values of this field
													if ($isCheckbox) {
														// Checkbox field
														foreach ($this_val2 as $this_code=>$this_val3) {
															$attr[$this_real_event_id][$this_repeat_instrument][$this_instance][$this_field2][$this_code] = '';
														}
													} else {
														// Form Status field
														$attr[$this_real_event_id][$this_repeat_instrument][$this_instance][$this_field2] = '';
													}
												}
											}
										}
									}
								}
							}
						} else {
							// If Record ID field exists in the report, then set it too at the value level
							if ($removeTablePk && isset($attr[$Proj->table_pk])) {
								unset($attr[$Proj->table_pk]);
							}
							// Non-repeating instance (base instance)
							if ($hasRepeatingForms) {
								foreach ($attr as $this_field2=>$this_val2) {
									$isCheckbox = is_array($this_val2);
									if ($isCheckbox || $Proj->isFormStatus($this_field2)) {
										// Get this field's form
										$this_form = $Proj->metadata[$this_field2]['form_name'];
										// Is this field's form a repeating form? If not, then move along to next loop.
										if (!$Proj->isRepeatingForm($this_event_id, $this_form)) continue;
										// Clear out the values of this field
										if ($isCheckbox) {
											// Checkbox field
											foreach ($this_val2 as $this_code=>$this_val3) {
												$attr[$this_field2][$this_code] = '';
											}
										} else {
											// Form Status field
											$attr[$this_field2] = '';
										}
									}
								}
							}
						}
						// Now re-add line to file under new record name
						Files::replaceLineInFile($record_data_tmp_filename, serialize(array($this_record=>array($this_event_id=>$attr))), $this_line_num);
					}
				}
				$fileSearch = null;
			} else {
				// ARRAY
				foreach ($record_data as $this_record=>&$these_events) {
					foreach ($these_events as $this_event_id=>&$attr) {
						// Repeating forms
						if ($this_event_id == 'repeat_instances') {
							foreach ($attr as $this_real_event_id=>&$battr) {
								foreach ($battr as $this_repeat_instrument=>&$cattr) {
									foreach ($cattr as $this_instance=>&$dattr) {
										// If Record ID field exists in the report, then set it too at the value level
										if ($removeTablePk && isset($dattr[$Proj->table_pk])) {
											unset($record_data[$this_record][$this_event_id][$this_real_event_id][$this_repeat_instrument][$this_instance][$Proj->table_pk]);
										}
										// Repeating forms: Remove default 0s where needed
										if ($hasRepeatingForms) { 
											foreach ($dattr as $this_field2=>$this_val2) {
												$isCheckbox = is_array($this_val2);
												if ($isCheckbox || $Proj->isFormStatus($this_field2)) {
													// Get this field's form
													$this_form = $Proj->metadata[$this_field2]['form_name'];
													// Is this field's form a repeating form? If not, then move along to next loop.
													//if (!$Proj->isRepeatingForm($this_real_event_id, $this_form)) continue;
													// If the field is on the current repeating form, then leave its defaults as-is and move to next loop.
													if ($this_form == $this_repeat_instrument || $Proj->isRepeatingEvent($this_real_event_id)) continue;
													// Clear out the values of this field
													if ($isCheckbox) {
														// Checkbox field
														foreach ($this_val2 as $this_code=>$this_val3) {
															$dattr[$this_field2][$this_code] = '';
														}
													} else {
														// Form Status field
														$dattr[$this_field2] = '';
													}
												}
											}
										}
									}
								}
							}
						} else {
							// If Record ID field exists in the report, then set it too at the value level
							if ($removeTablePk && isset($attr[$Proj->table_pk])) {
								unset($record_data[$this_record][$this_event_id][$Proj->table_pk]);
							}
							// Non-repeating instance (base instance)
							if ($hasRepeatingForms) {
								foreach ($attr as $this_field2=>$this_val2) {
									$isCheckbox = is_array($this_val2);
									if ($isCheckbox || $Proj->isFormStatus($this_field2)) {
										// Get this field's form
										$this_form = $Proj->metadata[$this_field2]['form_name'];
										// Is this field's form a repeating form? If not, then move along to next loop.
										if (!$Proj->isRepeatingForm($this_event_id, $this_form)) continue;
										// Clear out the values of this field
										if ($isCheckbox) {
											// Checkbox field
											foreach ($this_val2 as $this_code=>$this_val3) {
												$record_data[$this_record][$this_event_id][$this_field2][$this_code] = '';
											}
										} else {
											// Form Status field
											$record_data[$this_record][$this_event_id][$this_field2] = '';
										}
									}
								}
							}
						}
					}
				}
				unset($these_events, $attr, $battr, $cattr, $dattr);
			}
		}

		## RETURN DATA IN SPECIFIED FORMAT
		// ARRAY format
		if ($return_format == 'array') {
			// Return as-is (already in array format)			
			return $record_data;
		}
		else
		{
			## For non-array formats, reformat data array (e.g., add unique event names, separate check options)
			
			// If writing data to file, then obtain line 1 of file, and put it as $record_data
			if ($write_data_to_file) {
				/* 
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				$fileSearch->seek(0);
				$record_data = unserialize(trim($fileSearch->current()));
				// Empty $record_data now that we're done with it for headers
				$record_data = array();	
				 */
				// Create new file for formatted data
				$record_data_formatted_tmp_filename = self::generateTempFileName(60);
				$record_data_formatted_tmp_file = fopen($record_data_formatted_tmp_filename, 'w+');
				// Set the file to be deleted automatically when the script ends
				Files::delete_file_on_shutdown($newFile, $record_data_formatted_tmp_filename);
			}

			// PLACE FORMATTED DATA INTO $record_data_formatted
			$record_data_formatted = $headers = $checkbox_choice_labels = array();
			// Set line/item number for each record/event
			$recordEventNum = 0;
			// If we're on batch 1 of a CSV file and it's empty, then add placeholder record to force it to build the headers
			$removeCsvPlaceholderRecord = false;
			if ($return_format == 'csv' && $batchNum == 1 && empty($record_data)) {
				$record_data = array(""=>$default_values);
				$filterReturnedEmptySet = false;
				$removeCsvPlaceholderRecord = true;
			}
			unset($default_values);

			$check_for_csv_injection = ($return_format == 'csv' && PAGE == 'DataExport/data_export_ajax.php' && ($_POST['export_format'] == 'csvraw' || $_POST['export_format'] == 'csvlabels'));

			// If no results were returned (empty array with no values), then output row with message stating that.
			if (!$filterReturnedEmptySet)
			{
				// Get loopable record-event array
				if ($write_data_to_file) {
					$fileSearch = new SplFileObject($record_data_tmp_filename);
					$record_events = &$record_data_tmp_line;
				} else {
					$record_events = &$record_data;
				}

				// Loop through array and output line as CSV
				foreach ($record_events as $this_record=>&$event_data2) {
					// Loop through events in this record
					foreach (array_keys($event_data2) as $this_event_id) {
						// Get array of field data in this record-event
						if ($write_data_to_file) {
							$fileSearch->seek($record_data_tmp_line[$this_record][$this_event_id]-1);
							$field_data = unserialize(trim($fileSearch->current()));
							$field_data = $field_data[$this_record][$this_event_id];
						} else {
							$field_data = $record_data[$this_record][$this_event_id];
						}
						// Add repeating events data
						if ($this_event_id != 'repeat_instances') {
							$field_data_instance = array($this_event_id=>array(''=>array(1=>$field_data)));
						}
						if ($this_event_id == 'repeat_instances') {
							$field_data_instance = $field_data;
						} elseif ($hasRepeatingEvents && $Proj->isRepeatingEvent($this_event_id) && isset($field_data[$this_event_id][''])) {
							// Repeating events only
							$field_data_instance[''] = $field_data_instance[''] + $field_data[$this_event_id][''];
						}
						// Add repeating forms data
						elseif ($hasRepeatingForms && !$Proj->isRepeatingEvent($this_event_id) && isset($field_data[$this_event_id])) {
							$field_data_instance = $field_data_instance + $field_data[$this_event_id];
						}
						// Set "event_id" for later because it might really be 'repeat_instances'
						$this_event_id_tmp = $this_event_id;
						$field_data = array(); // reset to save memory				
						// Loop through fields in this event/repeat_instrument/instance
						foreach ($field_data_instance as $this_event_id=>&$field_data_instance2) {
							foreach ($field_data_instance2 as $this_repeat_instrument=>&$these_instances) {
								foreach ($these_instances as $this_instance=>&$field_data2) 
								{
									## SORTING: Gather values
									if ($doSorting)
									{									
										foreach ($sortFields as $key=>$this_sort_field) {
											// Add value to array as lower case (since we need to do case insensitive sorting)
											$sortFieldValues[$key][] = strtolower($field_data2[$this_sort_field]);
										}
									}
									## END SORTING
									
									// Loop through fields in this event
									foreach ($field_data2 as $this_field=>$this_value) 
									{
										// If field is only a sorting field and not a real data field to return, then skip it
										if ($applySortFields && in_array($this_field, $sortArrayRemoveFromData)) continue;
										
										## HEADERS
										if ($recordEventNum == 0) 
										{
											// If a checkbox split into multiple fields
											if (is_array($this_value) && !$combine_checkbox_values) {
												// If exporting labels, get labels for this field
												if ($exportCsvHeadersAsLabels) {
													$this_field_enum = parseEnum($Proj->metadata[$this_field]['element_enum']);
												}
												// Loop through all checkbox choices and add as separate "fields"
												foreach ($this_value as $this_code=>$this_checked_value) {
													// Store original code before formatting
													$this_code_orig = $this_code;
													// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
													$this_code = Project::getExtendedCheckboxCodeFormatted($this_code);
													if (!$removeMissingDataCodes && isset($missingDataCodes[$this_code_orig])) {
														$this_label = str_replace(array("'","\""), array("",""), $missingDataCodes[$this_code_orig]);
													} else {
														$this_label = str_replace(array("'","\""), array("",""), $this_field_enum[$this_code_orig]);
													}
													// Add choice to header
													$headers[] = ($exportCsvHeadersAsLabels)
														? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])))." (choice=$this_label)"
														: $this_field."___".$this_code;
												}
											// If a normal field or DAG/Survey fields/Repeat Instance
											} else {
												// Get this field's form
												$this_form = isset($Proj->metadata[$this_field]) ? $Proj->metadata[$this_field]['form_name'] : '';
												// If the record ID field
												if ($this_field == $table_pk) {
													// Add the record ID field
													$headers[] = ($exportCsvHeadersAsLabels) ? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$table_pk]['element_label']))) : $table_pk;
													// If longitudinal, add unique event name to line
													if ($longitudinal) {
														$headers[] = ($exportCsvHeadersAsLabels) ? 'Event Name' : 'redcap_event_name';
													}
													// If a repeated instance (and form), add instance number (and form name)
													if ($hasRepeatingFormsOrEvents) {
														// If using form-repeating, then add redcap_repeat_instrument column
														//if ($hasRepeatingForms) {
															$headers[] = ($exportCsvHeadersAsLabels) ? 'Repeat Instrument' : 'redcap_repeat_instrument';
														//}
														// Add repeat instance column
														$headers[] = ($exportCsvHeadersAsLabels) ? 'Repeat Instance' : 'redcap_repeat_instance';
													}
												}
												// Check if a special field or a normal field
												elseif (!$exportCsvHeadersAsLabels || ($combine_checkbox_values && $Proj->isCheckbox($this_field))) {
													// Add field to header array
													$headers[] = ($exportCsvHeadersAsLabels ? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label']))) : $this_field);
													// Add checkbox labels to array (only for $combine_checkbox_values=TRUE)
													if (is_array($this_value) && $combine_checkbox_values) {
														foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $raw_coded_value=>$checkbox_label) {
															$checkbox_choice_labels[$this_field][$raw_coded_value] = $checkbox_label;
														}
													}
												// Output labels for normal field or DAG/Survey fields/Repeat Instance
												} elseif ($this_field == 'redcap_data_access_group') {
													$headers[] = 'Data Access Group';
												} elseif ($this_field == 'redcap_survey_identifier') {
													$headers[] = 'Survey Identifier';
												} elseif (substr($this_field, -10) == '_timestamp' && isset($Proj->forms[substr($this_field, 0, -10)])) {
													$headers[] = 'Survey Timestamp';
												} else {
													$headers[] = str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])));
												}
											}
										}
										## DONE WITH HEADERS						
										
										
										// Is value an array? (i.e. a checkbox)
										$value_is_array = is_array($this_value);
										// Check field type
										if ($value_is_array && !$combine_checkbox_values) {
											// Loop through all checkbox choices and add as separate "fields"
											foreach ($this_value as $this_code=>$this_checked_value) {
												// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
												$this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
												$record_data_formatted[$recordEventNum][$this_field."___".$this_code] = $this_checked_value;
											}
										} elseif ($value_is_array && $combine_checkbox_values) {
											// Loop through all checkbox choices and create comma-delimited list of all *checked* options as value of single field
											$checked_off_options = array();
											foreach ($this_value as $this_code=>$this_checked_value) {
												// If value is 0 (unchecked), then skip it here. (Also skip if blank, which means that this form not designated for this event.)
                                                if ($this_checked_value == '0' || $this_checked_value == '' || $this_checked_value == $lang['global_144']) continue;
												// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
												// $this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
												// Add checked off option code to array of checked off options
												$checked_off_options[] = ($exportAsLabels ? $checkbox_choice_labels[$this_field][$this_code] : $this_code);
											}
											// Add checkbox as single field
											$record_data_formatted[$recordEventNum][$this_field] = implode(",", $checked_off_options);
										} else {
											// Add record name to line
											if ($this_field == $table_pk) {
												$record_data_formatted[$recordEventNum][$table_pk] = (string)$this_record; // Ensure record is a string for consistency (especially for JSON exports)
												// If longitudinal, add unique event name to line
												if ($longitudinal) {
													if ($exportAsLabels) {
														$record_data_formatted[$recordEventNum]['redcap_event_name'] = $event_labels[$this_event_id];
													} else {
														$record_data_formatted[$recordEventNum]['redcap_event_name'] = $unique_events[$this_event_id];
													}
												}
												// Add event instance to array
												if ($hasRepeatingFormsOrEvents) {
													// If using form-repeating, then add redcap_repeat_instrument column
													//if ($hasRepeatingForms) {
														$record_data_formatted[$recordEventNum]['redcap_repeat_instrument'] = ($exportAsLabels) ? $Proj->forms[$this_repeat_instrument]['menu'] : $this_repeat_instrument;
													//}
													// If instance=1, then display as blank (to prevent user confusion since instance 1 is base instance)
													$record_data_formatted[$recordEventNum]['redcap_repeat_instance'] = ($this_repeat_instrument != '' || $Proj->isRepeatingEvent($this_event_id)) ? $this_instance : "";
												}
											}
											// General text values
											else
											{
												// Prevent CSV injection for Excel - add space in front if first character is -, @, +, or = (http://georgemauer.net/2017/10/07/csv-injection.html)
												if ($check_for_csv_injection) {
													if (in_array(substr($this_value, 0, 1), array("-", "@", "+", "="))) {
														$this_value = " $this_value";
													}
												}
												// Add field and its value
												$record_data_formatted[$recordEventNum][$this_field] = $this_value;
											}
										}
									}
									
									// Increment item counter
									$recordEventNum++;
								}
							}
						}
						
						// If writing data to file, then replace the line with new formatted data
						if ($write_data_to_file) {
							Files::replaceLineInFile($record_data_tmp_filename, serialize($record_data_formatted), $record_data_tmp_line[$this_record][$this_event_id_tmp]);
							// Clear out formatted array for each event
							$record_data_formatted = array();
						}
					}
					// Remove record from array to free up memory as we go
					unset($record_data[$this_record]);
				}
				if ($removeCsvPlaceholderRecord) {
					$record_data_formatted = array();
				}
			}			
			unset($record_data, $field_data_instance, $field_data_instance2, $event_data2);
			
			// APPLY MULTI-FIELD SORTING
			if ($doSorting)
			{
				// If writing data to file, then sort via different method
				if ($write_data_to_file) {
					// Instantiate FileObject for our opened temp file to extract single lines of data
					$fileSearch = new SplFileObject($record_data_tmp_filename);
					// Loop through all rows in file
					$record_data_formatted = array();
					foreach ($record_data_tmp_line as $this_record=>$these_events) {
						foreach ($these_events as $this_event_id=>$this_record_data_tmp_line_num) {
							// Move pointer to line for this record-event
							$fileSearch->seek($this_record_data_tmp_line_num-1); // this is zero based so need to subtract 1
							// Unserialize the line to an array
							foreach (unserialize(trim($fileSearch->current())) as $this_record_data) {
								// Loop through each sort field for all records
								foreach ($sortFields as $key=>$this_sort_field) {
									// Add temp key placeholders to $record_data_formatted so we can use this to resort the file after the array is sorted
									$record_data_formatted[] = $this_record_data_tmp_line_num."-".$this_record_data['redcap_repeat_instance']."-".$this_record_data['redcap_repeat_instrument'];
								}
							}
						}
					}
				}
				// Sort the data array
				if (count($sortFieldValues) == 1) {
					// One sort field
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
									$record_data_formatted);
				} elseif (count($sortFieldValues) == 2) {
					// Two sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
									$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING),
									$record_data_formatted);
				} else {
					// Three sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING),
									$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING),
									$sortFieldValues[2], ($sortTypes[2] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[2] ? SORT_NUMERIC : SORT_STRING),
									$record_data_formatted);
				}
				// If writing to file, then use placeholer data in $record_data_formatted to resort file contents
				if ($write_data_to_file) {
					// Resort contents of file and also delete any fields if they were only used for sorting and not originally in the report
					self::resortDataToFileByArray($record_data_formatted, $sortArrayRemoveFromData);
					// Renumber $record_data_formatted so that array values correspond to line numbers in file
					$record_data_formatted_count = count($record_data_formatted);
					$record_data_formatted = array();
					for ($i = 1; $i <= $record_data_formatted_count; $i++) {
						$record_data_formatted[] = $i;
					}
				}
				else
				{
					// If any sorting fields did NOT exist in $fields originally (but were added so their data could be obtained for
					// sorting purposes only), then remove them now.
					if (!empty($sortArrayRemoveFromData)) {
						foreach ($sortArrayRemoveFromData as $this_field) {
							foreach ($record_data_formatted as &$this_item) {
								// Remove field from this record-event
								unset($this_item[$this_field]);
							}
						}
					}
				}
				// Remove vars to save memory
				unset($sortFieldValues);
			}

			// If writing to file, then set file object to use
			if ($write_data_to_file) {
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				// Loop through all records in file and add to $record_data_formatted
				if ($recordIdInFields) $record_data_formatted = array();
				$line_num = 0;
				while (!$fileSearch->eof())
				{
					$fileSearch->seek($line_num); // this is zero based so need to subtract 1
					if ($recordIdInFields) {
						foreach (unserialize(trim($fileSearch->current())) as $line) {
							$record_data_formatted[$line_num] = array($Proj->table_pk=>$line[$Proj->table_pk]);
						}
					}
					$line_num++;
					// If we're at the end of the file, then stop here
					if ($fileSearch->eof()) break;
				}
				// Now that we're done, set back to 0
				$fileSearch->seek(0);
				unset($line);
				// Set number of results
				$num_results_returned = $line_num - 1;
			} else {
				// Set number of results
				$num_results_returned = count($record_data_formatted);
			}
			
			// If a batch limit or beginning has been set for this, then filter it down to those definitions
			$pagingDropdownRecordList = array();
			if ($rowBegin > 0 || $rowLimit != null) {			
				// Create paging record list array
				if ($returnRecordListAndPagingList) {
					$i = 0;
					$record_data_formatted_count = count($record_data_formatted);
					foreach ($record_data_formatted as $item) {
						$i++;
						// Get only bookend record numbers for pages
						if ($i % $rowLimit > 1 && $i < $record_data_formatted_count) continue;
						$this_record = $item[$Proj->table_pk];
						$pagingDropdownRecordListPre[] = $this_record;
					}
					if (count($pagingDropdownRecordListPre) % 2 > 0) {
						$pagingDropdownRecordListPre[] = $this_record;
					}
					for ($k = 0; $k < count($pagingDropdownRecordListPre); $k++) {
						$pagingDropdownRecordList[($k/2)+1] = array($pagingDropdownRecordListPre[$k], $pagingDropdownRecordListPre[$k+1]);
						$k++;
					}
					unset($pagingDropdownRecordListPre);
				}
				// Slice the record_data_formatted array so that only this page's data remains
				$record_data_formatted = array_slice($record_data_formatted, $rowBegin, $rowLimit, true);
			}			
			// Return only the list of records (if applicable)
			if ($returnRecordListAndPagingList) {
				$recordList = array();
				foreach ($record_data_formatted as $item) {
					$recordList[$item[$Proj->table_pk]] = true;
				}
				return array(array_keys($recordList), $pagingDropdownRecordList);
			}

			## HTML format (i.e., report)
			if ($return_format == 'html')
			{
				// Build array of events with unique event name as key and full event name as value
				$eventsUniqueFullName = $eventsUniqueEventId = array();
				if ($longitudinal) {
					foreach ($unique_events as $this_event_id=>$this_unique_name) {
						// Arrays event name and event_id with unique event name as key
						$eventsUniqueFullName[$this_unique_name] = str_replace($orig, $repl, strip_tags(label_decode($Proj->eventInfo[$this_event_id]['name_ext'])));
						$eventsUniqueEventId[$this_unique_name] = $this_event_id;
					}
				}

				// CHECKBOXES: Create new arrays with all checkbox fields and the original field name as the value
				$fullCheckboxFields = array();
				foreach (MetaData::getCheckboxFields($project_id) as $field=>$value) {
					foreach ($value as $code=>$label) {
						$fullCheckboxFields[$field . "___" . Project::getExtendedCheckboxCodeFormatted($code)] = $field;
					}
					// Add all Missing Data Codes as extra options (unless field has @NOMISSING action tag)
					if (!$removeMissingDataCodes && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$field]['misc'])) {
						foreach ($missingDataCodes as $code=>$label) {
							$fullCheckboxFields[$field . "___" . Project::getExtendedCheckboxCodeFormatted($code)] = $field;
						}
					}
				}

				// Build array of DAGs with unique DAG names as key and
				$dagUniqueFullName = array();
				foreach ($Proj->getUniqueGroupNames() as $this_group_id=>$this_unique_dag) {
					$dagUniqueFullName[$this_unique_dag] = str_replace($orig, $repl, strip_tags(label_decode($Proj->getGroups($this_group_id))));
				}

				// If we're JUST returning Records/Events array and NOT the html report, then collect all records/event_ids and return
				if ($returnIncludeRecordEventArray)
				{
					// Collect records/event_ids in array
					$includeRecordsEvents = array();
					foreach ($record_data_formatted as $key=>$item) {
						// Add record/event
						$this_event_id = ($longitudinal) ? $eventsUniqueEventId[$item['redcap_event_name']] : $Proj->firstEventId;
						//if (isset($item['redcap_repeat_instance']) && $item['redcap_repeat_instance'] > 1) {
						if (isset($item['redcap_repeat_instance'])) {
							$includeRecordsEvents[$item[$Proj->table_pk]][$this_event_id][$item['redcap_repeat_instance']."-".$item['redcap_repeat_instrument']] = true;
						} else {
							$includeRecordsEvents[$item[$Proj->table_pk]][$this_event_id][1] = true;
						}
						// Remove each as we go to save memory
						unset($record_data_formatted[$key]);
					}
					// Return array of the whole table, number of results returned, and total number of items queried
					return array($includeRecordsEvents, $num_results_returned);
				}

				// PAGING FOR REPORTS: If has more than $num_per_page results, then page it $num_per_page per page
				// (only do this for pre-defined reports though)
				// Get page params
				$num_per_page = DataExport::NUM_RESULTS_PER_REPORT_PAGE;
				$limit_begin  = 0;
				if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
					$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
				} elseif (!isset($_GET['pagenum'])) {
					$_GET['pagenum'] = 1;
				} else {
					$_GET['pagenum'] = 'ALL';
				}
				// If running report A or B, these are special, so obtain page numbers differently
				$isReportA = (isset($_POST['report_id']) && $_POST['report_id'] == 'ALL');
				$isReportBnofilter = (isset($_POST['report_id']) && $_POST['report_id'] == 'SELECTED' && (!isset($_GET['events']) || empty($_GET['events'])));
				$isReportAorBnofilter = ($isReportA || $isReportBnofilter);
				if ($isReportAorBnofilter) {
					$num_results_returned = $GLOBALS['num_results_returned'];
				}
				$pageNumDropdown = "";
				if ($num_results_returned > $num_per_page)
				{
					// Build drop-down list of page numbers
					if ($isReportAorBnofilter) {
						$num_pages = count($GLOBALS['pagingDropdownRecordList']);
					} else {
						$num_pages = ceil($num_results_returned/$num_per_page);
					}
					// Only display drop-down if we have more than one page
					if ($num_pages > 1) {
						// Initialize array of options for drop-down
						$pageNumDropdownOptions = array('ALL'=>'-- '.$lang['docs_44'].' --');
						// Loop through pages
						for ($i = 1; $i <= $num_pages; $i++) {
							$end_num   = $i * $num_per_page;
							$begin_num = $end_num - $num_per_page + 1;
							$value_num = $end_num - $num_per_page;
							if ($end_num > $num_results_returned) $end_num = $num_results_returned;
							// Special processing of record drop-down for Report A/B
							if ($isReportAorBnofilter) {
								$resultName1 = "\"".$GLOBALS['pagingDropdownRecordList'][$i][0]."\"";
								$resultName2 = "\"".$GLOBALS['pagingDropdownRecordList'][$i][1]."\"";
							// If Record ID field not included in report, then use "results 1 through 100" instead of "A101 through B203" using record names
							} elseif ($recordIdInFields) {
								$resultNamePrefix = $lang['data_entry_177'] . " ";
								$resultName1 = "\"".$record_data_formatted[$begin_num-1][$Proj->table_pk]."\"";
								$resultName2 = "\"".$record_data_formatted[$end_num-1][$Proj->table_pk]."\"";
							} else {
								$resultNamePrefix = $lang['report_builder_112']." ";
								$resultName1 = $begin_num;
								$resultName2 = $end_num;
							}
							$pageNumDropdownOptions[$i] = "{$resultName1} {$lang['data_entry_216']} {$resultName2}";
						}
						// Prevent users from selecting ALL pages if more than X data points might be displayed
						$preventLoadAll = (is_numeric($_GET['pagenum']) && $estimatedDataPoints >= 500000) ? 'true' : 'false';
						// Create HTML for pagenum drop-down
						$pageNumDropdown =  RCView::div(array('class'=>'chklist d-print-none report_pagenum_div'),
												// Display page number (if performing paging)
												(!(isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) ? '' :
													RCView::span(array('style'=>'font-weight:bold;margin-right:7px;font-size:13px;'),
														"{$lang['survey_132']} {$_GET['pagenum']} {$lang['survey_133']} $num_pages{$lang['colon']}"
													)
												) .
												$resultNamePrefix .
												RCView::select(array('class'=>'report_page_select x-form-text x-form-field','style'=>'font-size:11px;margin-left:6px;margin-right:4px;', 'onchange'=>"loadReportNewPage(this.value,$preventLoadAll);"),
															   $pageNumDropdownOptions, $_GET['pagenum'], 500) .
												$lang['survey_133'].
												RCView::span(array('style'=>'font-weight:bold;margin:0 4px;font-size:13px;'),
													User::number_format_user($num_results_returned)
												) .
												$lang['report_builder_113']
											) .
											// Note about paging when clicking headers to sort
											RCView::div(array('class'=>'report_sort_msg'),
												$lang['report_builder_146']
											);
						unset($pageNumDropdownOptions);
					}
					// Filter the results down to just a single page
					if (is_numeric($_GET['pagenum']) && !$isReportAorBnofilter) {
						if ($write_data_to_file) {
							// The record_data_formatted array is empty, so remove all data from file so that only this page's data remains
							Files::removeLinesInFile($record_data_tmp_filename, $limit_begin+1, $num_per_page);
						} else {
							// Slice the record_data_formatted array so that only this page's data remains
							$record_data_formatted = array_slice($record_data_formatted, $limit_begin, $num_per_page, true);
						}
					}
				}

				// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
				$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
									   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");
				$extra_reserved_field_names = Project::$reserved_field_names + array_fill_keys($extra_reserved_field_names, 'Survey Timestamp');
				// Place all html in $html
				$html = $pageNumDropdown . "<table id='report_table' class='dataTable cell-border' style='table-layout:fixed;margin:0;font-family:Verdana;font-size:11px;'>";
				$mc_choices = array();

				// Array to store fields to which user has no form-level access
				$fields_no_access = array();
				// Add form fields where user has no access
				foreach ($user_rights['forms'] as $this_form=>$this_access) {
					if ($this_access == '0') {
						$fields_no_access[$this_form . "_timestamp"] = true;
					}
				}
				
				// Do we have checkbox fields in the report?
				$hasCheckboxFields = false;
				foreach ($headers as $this_hdr) {
					if (!isset($Proj->metadata[$this_hdr]) && strpos($this_hdr, "___") !== false) {
						list ($this_hdr, $raw_coded_value_formatted) = explode("___", $this_hdr, 2);
						if (isset($Proj->metadata[$this_hdr])) {
							$hasCheckboxFields = true;
							break;
						}
					}
				}

				// REPORT HEADER: Loop through header fields and build HTML row
				$datetime_convert = array();
				$row = $row2 = "";
				$displayedCheckboxRow1 = $checkboxChoices = array();
				foreach ($headers as $this_hdr) {
					// Set original field name
					$this_hdr_orig = $this_hdr;
					// Determine if a checkbox
					$isCheckbox = false;
					$checkbox_label_append = "";
					if (!isset($Proj->metadata[$this_hdr]) && strpos($this_hdr, "___") !== false) {
						// Set $this_hdr as the true field name
						list ($this_hdr, $raw_coded_value_formatted) = explode("___", $this_hdr, 2);
						$isCheckbox = true;
						// Obtain the label for this checkbox choice
						$checkboxChoices[$this_hdr] = parseEnum($Proj->metadata[$this_hdr]['element_enum']);
						foreach ($checkboxChoices[$this_hdr] as $raw_coded_value=>$checkbox_label) {
							if ($this_hdr_orig == Project::getExtendedCheckboxFieldname($this_hdr, $raw_coded_value)) {
								$checkbox_label_append = strip_tags(label_decode($checkbox_label));
								$row2 .= "<th class=\"rpthdrc\">$checkbox_label_append<div>" . implode("_<wbr>", explode("_", $this_hdr_orig)) . "</div></th>";
								// If user does not have form-level access to this field's form
								if ($user_rights['forms'][$Proj->metadata[$this_hdr]['form_name']] == '0') {
									$fields_no_access[$this_hdr_orig] = true;
								}
								break;
							}
						}
						// Add all Missing Data Codes as extra options (unless field has @NOMISSING action tag)
						if (!$removeMissingDataCodes && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_hdr]['misc'])) {
							foreach ($missingDataCodes as $raw_coded_value=>$checkbox_label) {
								$checkboxChoices[$this_hdr][$raw_coded_value] = $checkbox_label;
								if ($this_hdr_orig == Project::getExtendedCheckboxFieldname($this_hdr, $raw_coded_value)) {
									$checkbox_label_append = strip_tags(label_decode($checkbox_label));
									$row2 .= "<th class=\"rpthdrc\">$checkbox_label_append<div>" . implode("_<wbr>", explode("_", $this_hdr_orig)) . "</div></th>";
								}
							}
						}
					}
					// If user does not have form-level access to this field's form
					if (isset($Proj->metadata[$this_hdr]) && $this_hdr != $Proj->table_pk && $user_rights['forms'][$Proj->metadata[$this_hdr]['form_name']] == '0') {
						$fields_no_access[$this_hdr] = true;
					}
					// If field is a reserved field name (redcap_event_name, redcap_data_access_group)
					if (!isset($Proj->metadata[$this_hdr]) && isset($extra_reserved_field_names[$this_hdr_orig])) {
						$field_type = '';
						$field_label_display = strip_tags(label_decode($extra_reserved_field_names[$this_hdr_orig]));
					} else {
						$field_type = $Proj->metadata[$this_hdr]['element_type'];
						$field_label = strip_tags(label_decode($Proj->metadata[$this_hdr]['element_label']));
						if (strlen($field_label) > 100) $field_label = substr($field_label, 0, 67)." ... ".substr($field_label, -30);
						$field_label_display = $field_label;
					}
					// Add field to header html row
					$hdrRowSpan = (!$isCheckbox && $hasCheckboxFields) ? " rowspan=\"2\"" : "";
					$hdrColSpan = !isset($checkboxChoices[$this_hdr]) ? "" : " colspan=\"".count($checkboxChoices[$this_hdr])."\"";
					$thClass = "";
					if ($isCheckbox) $thClass .= "rptchclbl";
					if (isset($fields_no_access[$this_hdr])) $thClass .= " form_noaccess";
					if ($thClass != "") $thClass = " class=\"$thClass\"";
					if (!$isCheckbox || ($isCheckbox && !isset($displayedCheckboxRow1[$this_hdr]))) {
						$row .= "<th".$thClass.$hdrColSpan.$hdrRowSpan.">$field_label_display";
						if (!$isCheckbox) $row .= "<div class=\"rpthdr\">" . implode("_<wbr>", explode("_", $this_hdr_orig)) . "</div>";
						$row .= "</th>";
						$displayedCheckboxRow1[$this_hdr] = true;
					}
					// Place only MC fields into array to reference
					if (in_array($field_type, array('yesno', 'truefalse', 'sql', 'select', 'radio', 'advcheckbox', 'checkbox'))) {
						// Convert sql field types' query result to an enum format
						$enum = ($field_type == "sql") ? getSqlFieldEnum($Proj->metadata[$this_hdr]['element_enum']) : $Proj->metadata[$this_hdr]['element_enum'];
						// Add to array
						if ($isCheckbox) {
							// Reformat checkboxes to export format field name
							foreach (parseEnum($enum) as $raw_coded_value=>$checkbox_label) {
								$this_hdr_chkbx = $Proj->getExtendedCheckboxFieldname($this_hdr, $raw_coded_value);
								$mc_choices[$this_hdr_chkbx] = array('0'=>$lang['global_144'], '1'=>$lang['global_143']);
							}
						} else {
							$mc_choices[$this_hdr] = parseEnum($enum);
						}
					}
					// Put all date/time fields into array for quick converting of their value to desired date format
					if (!$isCheckbox) {
						$val_type = isset($Proj->metadata[$this_hdr]) ? $Proj->metadata[$this_hdr]['element_validation_type'] : '';
						if (substr($val_type, 0, 4) == 'date' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy')) {
							// Add field name as key to array with 'mdy' or 'dmy' as value
							$datetime_convert[$this_hdr] = substr($val_type, -3);
						}
					}
				}
				$html .= "<thead><tr>$row</tr>";
				if ($row2 != "") {
					$html .= "<tr>$row2</tr>";
				}
				$html .= "</thead>";
				// If no data, then output row with message noting this
				if ((!$write_data_to_file && empty($record_data_formatted)) || ($write_data_to_file && filesize($record_data_tmp_filename) == 0)) {
					$html .= RCView::tr(array('class'=>'odd'),
								RCView::td(array('style'=>'color:#777;border:1px solid #ccc;padding:10px 15px !important;', 'colspan'=>count($headers)),
									$lang['report_builder_87']
								)
							 );
				}

				// If record ID is in report for a classic project and will thus be displayed as a link, then get
				// the user's first form based on their user rights (so we don't point to a form that they don't have access to.)
				$first_form = "";
				if ($recordIdInFields && !$longitudinal) {
					foreach (array_keys($Proj->forms) as $this_form) {
						if ($user_rights['forms'][$this_form] == '0') continue;
						$first_form = $this_form;
						break;
					}
				}

				// DATA: Loop through each row of data (record-event) and output to html
				$j = $line_num = 1;
				reset($record_data_formatted);
				while ((!$write_data_to_file && !empty($record_data_formatted)) || ($write_data_to_file && !$fileSearch->eof()))
				{
					// If written to file, then unserialize it
					$lines = array();
					if ($write_data_to_file) {
						foreach (unserialize(trim($fileSearch->current())) as $key=>$line) { 
							$lines[] = $line;
						}
						$fileSearch->seek($line_num++); // this is zero based so need to subtract 1
					} else {
						// Extract from array
						$line = each($record_data_formatted);
						if ($line === false) break;
						$key = $line[0];
						$line = $line[1];
						$lines[] = $line;
					}
					// Loop through each element in row
					foreach ($lines as &$line) {
						$row = "";
						foreach ($line as $this_fieldname=>$this_value)
						{
							// Check for form-level user access to this field
							if (isset($fields_no_access[$this_fieldname])) {
								// User has no rights to this field
								$row .= "<td class=\"form_noaccess\">-</td>";
							} else {
                                $data_sort = "";
								// If data was written to file, then make sure we restore any line breaks that were removed earlier
								if ($write_data_to_file) {
									$this_value = str_replace(array(self::RC_NL_R, self::RC_NL_N), array("\r", "\n"), $this_value);
								}
								// If redcap_event_name field
								if ($this_fieldname == 'redcap_event_name') {
									$cell = $eventsUniqueFullName[$this_value];
								}
								// If DAG field
								elseif ($this_fieldname == 'redcap_data_access_group') {
									$cell = $dagUniqueFullName[$this_value];
								}
								// If repeat instrument field
								elseif ($this_fieldname == 'redcap_repeat_instrument') {
									$cell = $Proj->forms[$this_value]['menu'];
								}
								// For a radio, select, or advcheckbox, show both num value and text
								elseif (isset($mc_choices[$this_fieldname])) {
									//if missing data code, use corresponding label
									if (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_fieldname]['misc'])) {
										$cell = "<span class=\"dmiss\">".$missingDataCodes[$this_value]." <span class=\"ch\">($this_value)</span></span>";
									} else {
                                        // Get option label
                                        if ($combine_checkbox_values && strpos($this_value, ",") !== false) {
                                            // Explode values to gather all labels
                                            $cell = array();
                                            $these_values = explode(",", $this_value);
                                            foreach ($these_values as $thisChkValue) {
                                                $cell[] = $mc_choices[$this_fieldname][$thisChkValue];
                                            }
                                            $cell = implode(", ", $cell);
                                            $this_value = implode(", ", $these_values);
                                        } else {
                                            $cell = $mc_choices[$this_fieldname][$this_value];
                                        }
                                        // PIPING (if applicable)
                                        if ($do_label_piping && in_array($this_fieldname, $piping_receiver_fields)) {
                                            $cell = strip_tags(Piping::replaceVariablesInLabel($cell, $line[$Proj->table_pk],
                                                    ($longitudinal ? $Proj->getEventIdUsingUniqueEventName($line['redcap_event_name']) : $Proj->firstEventId),
                                                    1, $piping_record_data));
                                        }
                                        // Append raw coded value
                                        if (trim($this_value) != "") {
                                            $cell .= " <span class=\"ch\">($this_value)</span>";
                                        }
									}
								}
								// For survey timestamp fields
								elseif (substr($this_fieldname, -10) == '_timestamp' && isset($extra_reserved_field_names[$this_fieldname])) {
									// Convert datetime to user's preferred date format
									if ($this_value == "[not completed]") {
										$cell = $this_value;
									} else {
                                        $data_sort = $this_value;
										$cell = DateTimeRC::datetimeConvert(substr($this_value, 0, 16), 'ymd', DateTimeRC::get_user_format_base());
									}
								}
								// All other fields (text, etc.)
								else
								{
									// If an auto-suggest ontology field, then get cached label
									if (isset($ontology_auto_suggest_fields[$this_fieldname])) {
										$cell = "";
										if ($this_value != '') {
											$cell = $ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$this_fieldname]['service']][$ontology_auto_suggest_fields[$this_fieldname]['category']][$this_value] . " <span class=\"ch\">($this_value)</span>";
										}
									}
									// If a date/time field, then convert value to its designated date format (YMD, MDY, DMY)
									elseif (isset($datetime_convert[$this_fieldname])) {
									    if (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_fieldname]['misc'])) {
                                            $cell = "<span class=\"dmiss\">".$missingDataCodes[$this_value]." <span class=\"ch\">($this_value)</span></span>";
										} else {
											$data_sort = $this_value;
										    $cell = DateTimeRC::datetimeConvert($this_value, 'ymd', $datetime_convert[$this_fieldname]);
										}
									}
									// File upload field download link (do not escape it)
									elseif ($Proj->metadata[$this_fieldname]['element_type'] == 'file') {
                                        if (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_fieldname]['misc'])) {
                                            $cell = "<span class=\"dmiss\">".$missingDataCodes[$this_value]." <span class=\"ch\">($this_value)</span></span>";
                                        } else {
									        $cell = $this_value;
                                        }
									}
									// Replace line breaks with HTML <br> tags for display purposes
									else {
                                        if (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$this_fieldname]['misc'])) {
                                            $cell = "<span class=\"dmiss\">".$missingDataCodes[$this_value]." <span class=\"ch\">($this_value)</span></span>";
                                        } else {
                                        	// Checkbox with missing data code value
											if (!isset($Proj->metadata[$this_fieldname]) && isset($fullCheckboxFields[$this_fieldname])) {
												if ($this_value == '1') {
													$cell = $lang['global_143']." <span class=\"ch\">($this_value)</span>";
												} elseif ($this_value == '0') {
													$cell = $lang['global_144']." <span class=\"ch\">($this_value)</span>";
												} else {
													$cell = '';
												}
											} else {
												$cell = nl2br(htmlspecialchars($this_value, ENT_QUOTES));
											}
                                        }
									}
								}
								// If record name, then convert it to a link (unless project is archived/inactive)
								if ($Proj->project['status'] < 2 && $this_fieldname == $Proj->table_pk) 
								{
									$this_arm = ($Proj->longitudinal) ? $Proj->eventInfo[$eventsUniqueEventId[$line['redcap_event_name']]]['arm_num'] : $Proj->firstArmNum;
									// Link URL
									if ($Proj->longitudinal && (!isset($line['redcap_repeat_instrument']) || $line['redcap_repeat_instrument'] == '')
										&& (!isset($line['redcap_repeat_instance']) || $line['redcap_repeat_instance'] == '')) {
										// Link to record home page
										$this_url = "DataEntry/record_home.php?pid={$Proj->project_id}&id=".removeDDEending($this_value)."&arm=$this_arm";
									} else {
										// Get first form (for links)
										$this_first_form = $first_form;
										if (isset($line['redcap_repeat_instrument']) && $line['redcap_repeat_instrument'] != '') {
											$this_first_form = $line['redcap_repeat_instrument'];
										} elseif ($Proj->longitudinal) {
											// Longitudinal repeating event: Find the first form they have access to on this event
											foreach ($Proj->eventsForms[$eventsUniqueEventId[$line['redcap_event_name']]] as $this_form) {
												if ($user_rights['forms'][$this_form] == '0') continue;
												$this_first_form = $this_form;
												break;
											}
										}
										// Link to data entry page
										$this_url = "DataEntry/index.php?pid={$Proj->project_id}&id=".removeDDEending($this_value)."&page=".$this_first_form;
										if ($Proj->longitudinal) {
											$this_url .= "&event_id=" . $eventsUniqueEventId[$line['redcap_event_name']];
										}
										// If this is a repeated instance, then point to the first repeated form (not the first form)
										if (isset($line['redcap_repeat_instance']) && $line['redcap_repeat_instance'] != '') {
											$this_url .= "&instance=" . $line['redcap_repeat_instance'];
										}
									}
									// If has custom record label, then display it
									$this_custom_record_label = (isset($extra_record_labels[$this_arm][$this_value])) ? "&nbsp; ".$extra_record_labels[$this_arm][$this_value] : '';
									// Wrap record name with link HTML
									$cell = RCView::a(array('href'=>APP_PATH_WEBROOT.$this_url, 'class'=>'rl'),
												removeDDEending($cell)
											) .
											$this_custom_record_label;
								}
								// Set any CSS classes for table cells
								$td_class = "";
								$thisFieldForm = isset($fullCheckboxFields[$this_fieldname]) ? $Proj->metadata[$fullCheckboxFields[$this_fieldname]]['form_name'] : $Proj->metadata[$this_fieldname]['form_name'];
								if ($this_fieldname != $Proj->table_pk && 
									// If a base instance, then gray out the repeating instance/instrument fields
									((($this_fieldname == 'redcap_repeat_instance' || $this_fieldname == 'redcap_repeat_instrument') && $line['redcap_repeat_instance'] == '')
									// Check other fields
									|| (!isset($extra_reserved_field_names[$this_fieldname]) && (
										// If field is not designated for this event
										($longitudinal && isset($field_event_designation[$line['redcap_event_name']][$this_fieldname]))
										// OR if row is a repeating form but field does not exist on that form
										|| (isset($line['redcap_repeat_instrument']) && $line['redcap_repeat_instrument'] != '' && $thisFieldForm != $line['redcap_repeat_instrument'])
										// OR if row is NOT a repeating form but field exists on a repeating form or event
										|| (isset($line['redcap_repeat_instance']) && $line['redcap_repeat_instance'] == ''
											// Repeating event
											&& (($longitudinal && $Proj->isRepeatingEvent($Proj->getEventIdUsingUniqueEventName($line['redcap_event_name'])))
												// Repeating form: longitudinal
												|| (($longitudinal && $Proj->isRepeatingForm($Proj->getEventIdUsingUniqueEventName($line['redcap_event_name']), $thisFieldForm))
													// Repeating form: classic
													|| (!$longitudinal && $Proj->isRepeatingForm($Proj->firstEventId, $thisFieldForm))
												)
											  )
											)
									))
								)) {
									$td_class = " class='nodesig'";
								}
								// Set data sorting attribute
                                if ($data_sort != "") {
                                    $data_sort = " data-sort=\"$data_sort\"";
                                }
								// Add cell to row
								$row .= "<td{$data_sort}{$td_class}>$cell</td>";
							}
						}
						// Set row class
						$class = ($j%2==1) ? "odd" : "even";
						$html .= "<tr class=\"$class\">$row</tr>";
						// Remove line from array to free up memory as we go
						unset($record_data_formatted[$key]);
						$j++;
					}
				}
				unset($row, $lines, $line);
				// Build entire HTML table
				$html .= "</table>" . $pageNumDropdown;
				// Return array of the whole table, number of results returned, and total number of items queried
				return array($html, $num_results_returned);
			}

			## CSV format
			elseif ($return_format == 'csv') {
				// If writing data to file, then retrieve data from file and convert to CSV
				if ($write_data_to_file) {
					return self::convertSerializedFileToCsv($headers, $csvDelimiter);
				}
				// Convert flat data array to CSV and return string
				else {
					return self::convertFlatDataToCsv($record_data_formatted, $headers, $csvDelimiter, ($batchNum < 2));
				}
			}

			## XML format
			elseif ($return_format == 'xml') {
				// If writing data to file, then retrieve data from file and convert to XML
				if ($write_data_to_file) {
					return self::convertSerializedFileToXml();
				}
				// Convert flat data array to XML and return string
				else {
					return self::convertFlatDataToXml($record_data_formatted, ($batchNum < 2), ($batchNum == 0));
				}
			}

			## JSON format
			elseif ($return_format == 'json') {
				// If writing data to file, then retrieve data from file and convert to JSON
				if ($write_data_to_file) {
					return self::convertSerializedFileToJson();
				}
				// Convert flat data array to JSON and return string
				else {
					return self::convertFlatDataToJson($record_data_formatted, ($batchNum < 2), ($batchNum == 0));
				}
			}

			## ODM format
			elseif ($return_format == 'odm') {
				if ($batchNum < 2) {
					// Get opening XML tags
					$xml = ODM::getOdmOpeningTag($Proj->project['app_title']);
					// MetadataVersion section
					if ($includeOdmMetadata) {
						$xml .= ODM::getOdmMetadata($Proj, $exportSurveyFields, $exportDataAccessGroups, (isset($_GET['xml_metadata_options']) ? $_GET['xml_metadata_options'] : ""));
					}
				}
				// CinicalData section (Note: if exporting metadata, then don't export any blank values - to save space -
				// since we're essentially doing a project snapshot.)
				$xml .= ODM::getOdmClinicalData($record_data_formatted, $Proj, $exportSurveyFields, $exportDataAccessGroups, !$includeOdmMetadata, $write_data_to_file, ($batchNum < 2), ($batchNum == 0));
				// End XML string
				if ($batchNum == 0) $xml .= ODM::getOdmClosingTag();
				// Return XML string
				return $xml;
			}
		}
	}




	// CONVERT FLAT DATA ARRAY TO JSON AND RETURN STRING
	public static function convertFlatDataToJson(&$record_data_formatted, $returnHeaders=true, $returnFooters=true)
	{
		// Convert all data into JSON string (do record by record to preserve memory better)
		$json = '';
		foreach ($record_data_formatted as $key=>&$item) {
			// Loop through each record and encode
			$json .= ",".json_encode_rc($item);
			// Remove line from array to free up memory as we go
			unset($record_data_formatted[$key]);
		}
		return ($returnHeaders ? '[' : '') . substr($json, 1) . ($returnFooters ? ']' : '');
	}


	// CONVERT SERIALIZED DATA FILE TO JSON AND RETURN STRING
	public static function convertSerializedFileToJson()
	{
		global $record_data_tmp_filename;
		// Convert all data into JSON string (do record by record to preserve memory better)
		$json = '';
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		$line_num = 1;
		while (!$fileSearch->eof()) {
			// Move pointer to line for this record-event
			$fileSearch->seek($line_num-1); // this is zero based so need to subtract 1
			// Write this line to CSV file
			foreach (unserialize(trim($fileSearch->current())) as $item) {
				// If data was written to file, then make sure we restore any line breaks that were removed earlier
				foreach ($item as &$this_value) {
					$this_value = str_replace(array(self::RC_NL_R, self::RC_NL_N), array("\r", "\n"), $this_value);
				}
				// Loop through each record and encode
				$json .= ",".json_encode($item);
			}
			// Increment line
			$line_num++;
		}
		return '[' . substr($json, 1) . ']';
	}


	// CONVERT FLAT DATA ARRAY TO XML AND RETURN STRING
	public static function convertFlatDataToXml(&$record_data_formatted, $returnHeaders=true, $returnFooters=true)
	{
		// Convert all data into XML string
		$xml = $returnHeaders ? "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<records>\n" : "";
		// Loop through array and add to XML string
		foreach ($record_data_formatted as $key=>&$item) {
			// Begin item
			$xml .= "<item>";
			// Loop through all fields/values
			foreach ($item as $this_field=>$this_value) {
				// If ]]> is found inside this value, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
				if (strpos($this_value, "]]>") !== false) {
					$this_value = str_replace("]]>", "]]]]><![CDATA[>", $this_value);
				}
				if ($this_value != '') $this_value = "<![CDATA[$this_value]]>";
				// Add value
				$xml .= "<$this_field>$this_value</$this_field>";
			}
			// End item
			$xml .= "</item>\n";
			// Remove line from array to free up memory as we go
			unset($record_data_formatted[$key]);
		}
		// End XML string
		if ($returnFooters) $xml .= "</records>";
		// Return XML string
		return $xml;
	}


	// CONVERT SERIALIZED DATA FILE TO XML AND RETURN STRING
	public static function convertSerializedFileToXml()
	{
		global $record_data_tmp_filename;
		// Convert all data into XML string
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<records>\n";
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$line_num = 1;
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		while (!$fileSearch->eof()) {
			// Move pointer to line for this record-event
			$fileSearch->seek($line_num-1); // this is zero based so need to subtract 1
			// Write this line to CSV file
			foreach (unserialize(trim($fileSearch->current())) as $item) {
				// Begin item
				$xml .= "<item>";
				// Loop through all fields/values
				foreach ($item as $this_field=>$this_value) {
					// If ]]> is found inside this value, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
					if (strpos($this_value, "]]>") !== false) {
						$this_value = str_replace("]]>", "]]]]><![CDATA[>", $this_value);
					}
					// If data was written to file, then make sure we restore any line breaks that were removed earlier
					$this_value = str_replace(array(self::RC_NL_R, self::RC_NL_N), array("\r", "\n"), $this_value);
					// Add value
					$xml .= "<$this_field><![CDATA[$this_value]]></$this_field>";
				}
				// End item
				$xml .= "</item>\n";
			}
			// Increment line
			$line_num++;
		}
		// End XML string
		$xml .= "</records>";
		// Return XML string
		return $xml;
	}


	// CONVERT FLAT DATA ARRAY TO CSV AND RETURN STRING
	public static function convertFlatDataToCsv(&$record_data_formatted, $headers=array(), $delimiter=",", $returnHeaders=true)
	{
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		if (empty($headers)) {
			foreach ($record_data_formatted as $these_fields) {
				$headers = array_keys($these_fields);
				break;
			}
		}
		if ($returnHeaders) fputcsv($fp, $headers, $delimiter);
		// Loop through array and output line as CSV
		foreach ($record_data_formatted as $key=>&$line) {
			// Write this line to CSV file
			fputcsv($fp, $line, $delimiter);
			// Remove line from array to free up memory as we go
			unset($record_data_formatted[$key]);
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		// Return CSV string
		return stream_get_contents($fp);
	}


	// CONVERT SERIALIZED DATA FILE TO CSV AND RETURN STRING
	public static function convertSerializedFileToCsv($headers=array(), $delimiter=",")
	{
		global $record_data_tmp_filename;
		// Open connection to create file in memory and write to it
		$fp = fopen('php://temp', "x+");
		// Add header row to CSV
		fputcsv($fp, $headers, $delimiter);
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$line_num = 1;
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		while (!$fileSearch->eof()) {
			// Move pointer to line for this record-event
			$fileSearch->seek($line_num-1); // this is zero based so need to subtract 1
			// Write this line to CSV file
			foreach (unserialize(trim($fileSearch->current())) as $line) {
				// If data was written to file, then make sure we restore any line breaks that were removed earlier
				foreach ($line as &$this_value) {
					$this_value = str_replace(array(self::RC_NL_R, self::RC_NL_N), array("\r", "\n"), $this_value);
				}
				fputcsv($fp, $line, $delimiter);
			}
			// Increment line
			$line_num++;
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		// Return CSV string
		return stream_get_contents($fp);
	}


	// CONVERT SERIALIZED DATA FILE TO ARRAY AND RETURN IT
	public static function convertSerializedFileToArray()
	{
		global $record_data_tmp_filename;
		// Convert all data into JSON string (do record by record to preserve memory better)
		$array = array();
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		$line_num = 1;
		while (!$fileSearch->eof()) {
			// Move pointer to line for this record-event
			$fileSearch->seek($line_num-1); // this is zero based so need to subtract 1
			// Write this line to CSV file
			$array[] = unserialize(trim($fileSearch->current()));
			// Increment line
			$line_num++;
			if ($fileSearch->eof()) break;
		}
		return $array;
	}


	// WRITE DATA ARRAY VALUES TO A TEMP FILE DURING EXPORT PROCESS
	// From the $record_data array, write each event sub-key to the temp file $record_data_tmp_file and then empty $record_data.
	public static function insertDataToFile(&$record_data, $removeLineBreaksInValues=true)
	{
		global $record_data_tmp_filename, $record_data_tmp_file, $record_data_tmp_line, $record_data_tmp_line_num;
		// Make sure the pointer is at the end of the file
		fseek($record_data_tmp_file, 0, SEEK_END);
		// Add all data to file
		foreach ($record_data as $record=>&$eattr) {
			foreach ($eattr as $event_id=>&$attr) {				
				// Temporarily replace new lines in serialize data (because it will cause issues) - will re-replace later
				if (!$removeLineBreaksInValues) {
					// Repeating forms
					if ($event_id == 'repeat_instances') {
						foreach ($attr as $this_real_event_id=>&$battr) {
							foreach ($battr as $this_repeat_instrument=>&$cattr) {
								foreach ($cattr as $this_instance=>&$dattr) {
									// If serialized data contains line breaks, then replace them one at a time
									$valuesConcat = implode("", $dattr);
									if (strpos($valuesConcat, "\r") !== false || strpos($valuesConcat, "\n") !== false) {
										foreach ($dattr as &$this_val) {
											$this_val = str_replace(array("\r", "\n"), array(self::RC_NL_R, self::RC_NL_N), $this_val);
										}
									}
								}
							}
						}
					} else {
						// If serialized data contains line breaks, then replace them one at a time
						$valuesConcat = implode("", $attr);
						if (strpos($valuesConcat, "\r") !== false || strpos($valuesConcat, "\n") !== false) {
							foreach ($attr as &$this_val) {
								$this_val = str_replace(array("\r", "\n"), array(self::RC_NL_R, self::RC_NL_N), $this_val);
							}
						}
					}
				}
				// Serialize the data array and add this record-event to file
				fwrite($record_data_tmp_file, serialize(array($record=>array($event_id=>$attr))) . "\n");
				// Add this to record line count array
				$record_data_tmp_line[$record][$event_id] = $record_data_tmp_line_num++;
			}
		}
		// Clear array for next record-event
		$record_data = array();
	}
	

	// UPDATE THE DATA TEMP FILE DURING EXPORT PROCESS USING THE $record_data_tmp_line ARRAY
	public static function updateDataToFile(&$record_data, &$default_values=array())
	{
		global $record_data_tmp_file, $record_data_tmp_filename, $record_data_tmp_line, $record_data_tmp_line_num;
		// Stop here if no values in array
		if (empty($record_data)) return;
		// Update all data to file
		foreach ($record_data as $record=>&$eattr) {
			foreach ($eattr as $event_id=>&$attr) {
				// Get the line num in the file
				if (!isset($record_data_tmp_line[$record][$event_id])) continue;
				$this_record_data_tmp_line_num = $record_data_tmp_line[$record][$event_id];				
				// Instantiate FileObject for our opened temp file to extract single lines of data
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				// Move pointer to line for this record-event
				$fileSearch->seek($this_record_data_tmp_line_num-1); // this is zero based so need to subtract 1
				// Unserialize the line to an array
				$currentLine = trim($fileSearch->current());
				$this_record_data = unserialize($currentLine);
				 // Close the file object
				$fileSearch = null;				
				// Loop through new values and update $this_record_data
				if ($event_id == 'repeat_instances') {
					foreach ($attr as $real_event_id=>&$battr) {
						foreach ($battr as $this_repeat_instrument=>&$cattr) {
							foreach ($cattr as $this_instance=>$dattr) {
								foreach ($dattr as $this_field=>$this_value) {
									if (isset($default_values[$real_event_id][$this_field])) {
										$this_record_data[$record]['repeat_instances'][$real_event_id][$this_repeat_instrument][$this_instance][$this_field] = $this_value;
									}
								}
							}
						}
					}
				} else {
					foreach ($attr as $this_field=>$this_value) {
						$this_record_data[$record][$event_id][$this_field] = $this_value;
					}
				}
				// Now replace this line in the file
				Files::replaceLineInFile($record_data_tmp_filename, serialize($this_record_data), $this_record_data_tmp_line_num);
			}
		}
		// Clear array for next record-event
		$record_data = array();
	}


	// RESORT THE DATA TEMP FILE DURING EXPORT PROCESS USING AN ALREADY-SORTED ARRAY CONTAINING NEW LINE NUMBERS
	// Parameter $fields_to_delete will contain array of fields to delete for each row of data while we are sorting.
	public static function resortDataToFileByArray($new_line_numbers=array(), $fields_to_delete=array())
	{
		global $record_data_tmp_file, $record_data_tmp_filename, $record_data_tmp_line;
		// Set flag to delete any fields
		$deleteFields = (is_array($fields_to_delete) && !empty($fields_to_delete));
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		// Create new file that we'll put the sorted records in
		$record_data_tmp_filename_sorted = self::generateTempFileName(60);
		$record_data_tmp_file_sorted = fopen($record_data_tmp_filename_sorted, 'w+');
		// Loop through sorted line number array
		$line_num = 0;
		foreach ($new_line_numbers as $attr)
		{
			list ($new_line_num, $this_instance, $this_repeat_instrument) = explode("-", $attr, 3);
			// Move pointer to line for this record-event
			$fileSearch->seek($new_line_num-1); // this is zero based so need to subtract 1
			// Get row of serialized data
			$this_row_serialized = trim($fileSearch->current()) . "\n";
			// If any sorting fields did NOT exist in $fields originally (but were added so their data
			// could be obtained for sorting purposes only), then remove them now.
			if ($deleteFields) {
				// Unserialize the data
				foreach (unserialize($this_row_serialized) as $this_row_data) {
					// Remove each field from the data
					foreach ($fields_to_delete as $this_field) {
						// Remove field from this record-event
						unset($this_row_data[$this_field]);
					}
				}
				// Reserialize the data (make sure to add line break)
				$this_row_serialized = serialize(array($line_num=>$this_row_data)) . "\n";
				// Increment line counter
				$line_num++;
			}
			// Now write the line to the new sorted file
			fwrite($record_data_tmp_file_sorted, $this_row_serialized);
		}
		// Now replace the original file with the new one with sorted contents
		$record_data_tmp_file = $record_data_tmp_file_sorted;
		$record_data_tmp_filename = $record_data_tmp_filename_sorted;
		// Set the file to be deleted automatically when the script ends
		Files::delete_file_on_shutdown($record_data_tmp_file_sorted, $record_data_tmp_filename_sorted);
	}


	// GENERATE A RANDOM FILENAME FOR A NEW TEMP FILE (SET TIMESTAMP OF DELETION TIME, e.g. 60 = Will be deleted in 60 minutes by cron)
	public static function generateTempFileName($timestampXmin=0)
	{
		$xMinFromNow = date("YmdHis", mktime(date("H"),date("i")+$timestampXmin,date("s"),date("m"),date("d"),date("Y")));
		return APP_PATH_TEMP . $xMinFromNow . "_" . substr(sha1(rand()), 0, 10);
	}


	// RESORT THE DATA TEMP FILE DURING EXPORT PROCESS USING THE $record_data_tmp_line ARRAY
	public static function resortDataToFileByRecordEvent(&$Proj)
	{
		global $record_data_tmp_file, $record_data_tmp_filename, $record_data_tmp_line;
		// Sort array using case insensitive natural sort
		natcaseksort($record_data_tmp_line);
		// Create array of event_id's in order by arm_num, then by event order
		$event_order = array_keys($Proj->eventInfo);
		// Instantiate FileObject for our opened temp file to extract single lines of data
		$fileSearch = new SplFileObject($record_data_tmp_filename);
		// Create new file that we'll put the sorted records in (set temp file with 1 hour in the future)
		$record_data_tmp_filename_sorted = self::generateTempFileName(60);
		$record_data_tmp_file_sorted = fopen($record_data_tmp_filename_sorted, 'w+');
		// Put new sorting line numbers in other array
		$record_data_tmp_line_sorted = array();
		$new_line_num = 1;
		// Loop through sorted data array. Get each record-event's line from original file and move to new sorted file.
		foreach ($record_data_tmp_line as $record=>$these_events)
		{
			// Sort events within each record (longitudinal only)
			if ($Proj->longitudinal) {
				// Set array to collect the data for this record in reordered form
				$this_record_data_reordered = array();
				// Skip if there's only one event with data
				if (count($these_events) > 1) {
					// Loop through all existing PROJECT events in their proper order
					foreach (array_intersect($event_order, array_keys($these_events)) as $event_id) {
						// Add this event's data to reordered data array
						$this_record_data_reordered[$event_id] = $these_events[$event_id];
					}
					// If we have repeating events/formsform
					if (isset($these_events['repeat_instances'])) {
						$this_record_data_reordered['repeat_instances'] = $these_events['repeat_instances'];
					}
					// Replace old data with reordered data
					$these_events = $this_record_data_reordered;
				}
			}
			// Loop through events and write each record-event to line in file
			foreach ($these_events as $event_id=>$this_line) {
				// Move pointer to line for this record-event
				$fileSearch->seek($this_line-1); // this is zero based so need to subtract 1
				// Serialized data
				$serialized_data = trim($fileSearch->current());
				// Reorder inside the sub-array
				if ($event_id == 'repeat_instances') {
					// Unserialize this so we can order the instances
					$this_record_data = unserialize($serialized_data);					
					// Loop through all existing PROJECT events in their proper order
					foreach ($this_record_data[$record]['repeat_instances'] as $this_event_id=>&$attr) {
						foreach ($attr as $this_repeat_form=>&$these_instances) {
							// Sort by instance number
							ksort($these_instances);
						}
					}
					// Re-serialize the data
					$serialized_data = serialize($this_record_data);
				}
				// Now write the line to the new sorted file
				fwrite($record_data_tmp_file_sorted, $serialized_data . "\n");
				// Add new line number to sorted array
				$record_data_tmp_line_sorted[$record][$event_id] = $new_line_num++;
			}
		}
		// Now replace the original file with the new one with sorted contents
		$record_data_tmp_file = $record_data_tmp_file_sorted;
		$record_data_tmp_filename = $record_data_tmp_filename_sorted;
		$record_data_tmp_line = $record_data_tmp_line_sorted;
		// Set the file to be deleted automatically when the script ends
		Files::delete_file_on_shutdown($record_data_tmp_file_sorted, $record_data_tmp_filename_sorted);
	}


	// APPLY RECORD FILTERING FROM A LOGIC STRING: Get record-events where logic is true
	public static function applyFilteringLogic($logic, $records=array(), $eventsFilter=array(), $project_id=null)
	{
		// Skip this if no filtering will be performed
		if ($logic == '') return false;

		// Get or create $Proj object
		if (is_numeric($project_id)) {
			// Instantiate object containing all project information
			// This only occurs when calling getData for a project in a plugin in another project's context
			$Proj = new Project($project_id);
		} else {
			// Set global var
			global $Proj;
		}

		// For fastest processing, preformat the logic by prepending [evnet-name] and/or appending [current-instance] where appropriate
		$logic = LogicTester::preformatLogicEventInstanceSmartVariables($logic, $Proj);

		// Place record list in array
		$records_filtered = array();

		// Parse the label to pull out the events/fields used therein
		$fields = array_keys(getBracketedFields($logic, true, true, false));

		// If no fields were found in string, then return the label as-is
		if (empty($fields)) return false;

		// Instantiate logic parse
		$parser = new LogicParser();

		// Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
		// if (!LogicTester::isValid($logic)) return false;
		
		// Check for special piping tags
		$logicContainsSpecialTags = Piping::containsSpecialTags($logic);

		// Loop through fields, and if is longitudinal with prepended event names, separate out those with non-prepended fields
		$events = array();
		$fields_classic = array();
		$fields_no_events = array();
		foreach ($fields as $this_key=>$this_field)
		{
			// If longitudinal with a dot, parse it out and put unique event name in $events array
			if (strpos($this_field, '.') !== false) {
				// Separate event from field
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				// Add field to fields_no_events array
				$fields_no_events[] = $this_field;
				// Put event in $events array
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
				if (!isset($events[$this_event_id]) && isset($Proj->eventInfo[$this_event_id])) $events[$this_event_id] = $this_event;
			} else {
				// Add field to fields_no_events array
				$fields_no_events[] = $fields_classic[] = $this_field;
			}
		}
		// If project has repeating forms/events, then add Form Status fields to force them to show up from getData
		$fields_form_status = array();
		if ($Proj->hasRepeatingFormsEvents()) {
			foreach ($fields_no_events as $this_field) {
				$fields_form_status[] = $Proj->metadata[$this_field]['form_name']."_complete";
			}
		}
		// Perform unique on $events and $fields arrays
        $fields_utilized_in_logic = $fields_no_events;
		$fields_no_events = array_unique(array_merge(array($Proj->table_pk), $fields_no_events, $fields_form_status));
		$fields_classic = array_unique($fields_classic);
		// If a longitudinal project and some fields in logic are to be evaluated on ALL events, then include all events
		$hasLongitudinalAllEventLogic = false;
		if ($Proj->longitudinal && !empty($fields_classic)) {
			$events = $Proj->getUniqueEventNames();
			// Add flag to denote that some fields need to be checked for ALL events
			$hasLongitudinalAllEventLogic = true;
		}
		// Get all data for these records, fields, events
		if ($logicContainsSpecialTags) {
			$events = $Proj->getUniqueEventNames();
			$eventsGetData = array_keys($Proj->eventInfo);
		} elseif (empty($eventsFilter)) {
			$eventsGetData = empty($events) ? array_keys($Proj->eventInfo) : array_keys($events);
		} else {
			$eventsGetData = $eventsFilter;
		}
		$record_data = self::getData($Proj->project_id, 'array', $records, $fields_no_events, $eventsGetData);

		// If we're including any checkbox fields in $fields_no_events, then store their enum array in another array for quick referencing
		$checkbox_enums = array();
		foreach ($fields_no_events as $this_field) {
			if ($Proj->isCheckbox($this_field)) {
				foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
					$checkbox_enums[$this_field][$this_code] = '0';
				}
			}
		}

		// Due to issues where a record contains only BLANK values for the fields $fields_no_events, the record will be removed.
		// In this case, re-add that record manually as empty to allow logic parsing to work as intended.
		$blank_records = array_diff($records, array_keys($record_data));
		if (!empty($blank_records)) {
			foreach ($blank_records as $this_record) {
				// Add only empty record with no event data so that the next code section will auto add blank values for it
				$record_data[$this_record] = array();
			}
		}

		// If some events of data don't include ALL events in the project, then add event data with default values
		// in case there are some instances of cross-event "OR" logic.
		foreach (array_keys($record_data) as $this_record) {
			// Loop through all relevant events
			foreach ($eventsGetData as $this_event_id) {
				// Add this event data if missing
				if (!isset($record_data[$this_record][$this_event_id])) {
					// Loop through all fields
					foreach ($fields_no_events as $this_field) {
						// Set default values for checkboxes and form status fields
						if ($this_field == $Proj->table_pk) {
							$value = $this_record;
						} elseif ($Proj->isCheckbox($this_field)) {
							$value = $checkbox_enums[$this_field];
						} elseif ($Proj->isFormStatus($this_field)) {
							$value = '0';
						} else {
							$value = '';
						}
						// Add value
						$record_data[$this_record][$this_event_id][$this_field] = $value;
					}
				}
			}
		}

        // If first form is repeating and record ID field is not included in logic, make sure we're not looping through the
        // record ID form's repeating instrument data when eval'ing logic.
        $skipFormRepeating = null;
        if ($Proj->hasRepeatingFormsEvents() && !in_array($Proj->table_pk, $fields_utilized_in_logic) &&
            $Proj->isRepeatingFormAnyEvent($Proj->firstForm))
        {
            // Loop through fields and determine if any are on first form
            $fieldsOnFirstForm = false;
            foreach ($fields_utilized_in_logic as $this_field) {
                if ($Proj->metadata[$this_field]['form_name'] == $Proj->firstForm) {
                    $fieldsOnFirstForm = true;
                    break;
                }
            }
            if (!$fieldsOnFirstForm) {
                // Set flag if none of the logic fields are from the first form (including record ID field)
                $skipFormRepeating = $Proj->firstForm;
            }
        }

		// Place all logic functions in array so we can call them quickly
		$logicFunctions = array();
		// Loop through all relevant events and build event-specific logic and anonymous logic function
		$event_ids = array_flip($events);
		if (!$Proj->longitudinal) {		
			$events = $Proj->getUniqueEventNames();
		}
		// Pre-format the logic so that we don't run it every loop
		$logic = LogicTester::formatLogicToPHP($logic);
		// Build record-event-instance array if we're using Smart Variables in $logic
		if ($logicContainsSpecialTags) 
		{
			foreach ($record_data as $this_record=>&$event_data) {
				foreach ($event_data as $this_event_id=>&$attr) {
					if ($this_event_id == 'repeat_instances') {
						foreach ($attr as $real_event_id=>&$battr) {
							foreach ($battr as $this_repeat_instrument=>&$cattr) {
                                // Should we skip this repeating instrument?
                                if (isset($skipFormRepeating) && $skipFormRepeating == $this_repeat_instrument) continue;
								foreach (array_keys($cattr) as $this_instance) {
									$this_logic = $logic;
									if ($hasLongitudinalAllEventLogic) {
										$this_logic = LogicTester::logicPrependEventName($this_logic, $events[$real_event_id], $Proj);
									}
									if ($Proj->longitudinal && $Proj->hasRepeatingFormsEvents()) {
										// If we have a field on multiple events in which it exists on a repeating instrument/event and a non-repeating one,
										// then we need to remove [current-instance] and append the real instance for this context to get accurate results.
										// The problem this solves is when a field from a repeating form accidentally gets assigned the current instance when we're on a repeating event,
										// in which the instance number is completely a different context.
										$this_logic = LogicTester::logicAppendInstance(str_replace("][current-instance]", "]", $this_logic), $Proj, $real_event_id, $this_repeat_instrument, $this_instance);
									}
									$this_logic = Piping::pipeSpecialTags($this_logic, $Proj->project_id, $this_record, $real_event_id, $this_instance, null, true, null, $this_repeat_instrument, false);
									try {
										list ($funcName, $argMap) = $parser->parse($this_logic, $event_ids, true, false, false, true);
									} catch (Exception $e) { continue; }
                                    $logicFunctions[$real_event_id][$this_record][$this_instance] = array('funcName'=>$funcName, 'argMap'=>$argMap); //, 'code'=>$parser->generatedCode);
								}
							}
						}
					} else {
						$this_logic = $logic;
						if ($hasLongitudinalAllEventLogic) {
							$this_logic = LogicTester::logicPrependEventName($this_logic, $events[$this_event_id], $Proj);
						}
						$this_logic = Piping::pipeSpecialTags($this_logic, $Proj->project_id, $this_record, $this_event_id, null, null, true, null, null, false);
						try {
							list ($funcName, $argMap) = $parser->parse($this_logic, $event_ids, true, false, false, true);
						} catch (Exception $e) { continue; }
						$logicFunctions[$this_event_id][$this_record][""] = array('funcName'=>$funcName, 'argMap'=>$argMap); //, 'code'=>$parser->generatedCode);
					}
				}
			}
			unset($event_data, $attr, $battr, $cattr);
		} 
		else 
		{
			// Generate logic functions and argument maps, then store in array
			foreach ($events as $this_event_id=>$this_unique_event) {
				$this_logic = $logic;
				if ($hasLongitudinalAllEventLogic) {
					// Customize logic for this event (longitudinal only)
					$this_logic = LogicTester::logicPrependEventName($this_logic, $events[$this_event_id], $Proj);
				}
                try {
					list ($funcName, $argMap) = $parser->parse($this_logic, $event_ids);
				} catch (ErrorException $e) { return false; }
				$logicFunctions[$this_event_id] = array('funcName'=>$funcName, 'argMap'=>$argMap); //, 'code'=>$parser->generatedCode);
			}
		}

		// Loop through each record-event and apply logic
		$record_events_logic_true = array();
		foreach ($record_data as $this_record=>&$event_data) {
			// Loop through events in this record
			foreach ($event_data as $this_event_id=>&$attr) {
				// Repeating instances
				if ($this_event_id == 'repeat_instances') {
					foreach ($attr as $real_event_id=>&$battr) {
						foreach ($battr as $this_repeat_instrument=>&$cattr) {
						    // Should we skip this repeating instrument?
                            if (isset($skipFormRepeating) && $skipFormRepeating == $this_repeat_instrument) continue;
							foreach ($cattr as $this_instance=>&$dattr) {
								// $dattr becomes the new $event_data for repeating
								// $event_data_instance = array($real_event_id=>$dattr);
								// Make sure that all array values are strings (because integers, especially 1, can cause logic to return true mistakenly)
								foreach ($dattr as $this_field2=>$this_val2) {
									// if (!is_array($this_val2)) $event_data_instance[$real_event_id][$this_field2] = (string)$this_val2;
                                    if (!is_array($this_val2)) $event_data[$this_event_id][$real_event_id][$this_repeat_instrument][$this_instance][$this_field2] = (string)$this_val2;
								}
								// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE) and add record-event if valid.
								if (isset($logicFunctions[$real_event_id][$this_record][$this_instance]['funcName'])) {
									$funcName = $logicFunctions[$real_event_id][$this_record][$this_instance]['funcName'];
									$argMap = $logicFunctions[$real_event_id][$this_record][$this_instance]['argMap'];
								} elseif (isset($logicFunctions[$real_event_id]['funcName'])) {
									$funcName = $logicFunctions[$real_event_id]['funcName'];
									$argMap = $logicFunctions[$real_event_id]['argMap'];
								} else {
									continue;
								}
								// If the fields in $argMap are on the current repeating event/form and they don't have an instance
                                // number specified, then manually added instance number to $argMap.
                                foreach ($argMap as $argMapKey=>$argMapAttr) {
                                    if ($argMapAttr[3] != '') continue;
                                    if ($argMapAttr[0] != $real_event_id) continue;
                                    $arMapFieldForm = $Proj->metadata[$argMapAttr[1]]['form_name'];
                                    if ($this_repeat_instrument != '' && $arMapFieldForm != $this_repeat_instrument) continue;
                                    if ($this_repeat_instrument == '' && !in_array($arMapFieldForm, $Proj->eventsForms[$real_event_id])) continue;
                                    // Add current instance number to the argMap only if the field is on this repeating form/event
                                    $argMap[$argMapKey][3] = $this_instance;
                                }
                                if (LogicTester::applyLogic($funcName, $argMap, $event_data, $Proj->firstEventId, false, $Proj->project_id) === 1) {
                                // if (LogicTester::applyLogic($funcName, $argMap, $event_data_instance, $Proj->firstEventId, false, $Proj->project_id) === 1) {
									$record_events_logic_true[$this_record][$real_event_id][$this_repeat_instrument][$this_instance] = true;
								}
							}
						}
					}
				} else {
					// Make sure that all array values are strings (because integers, especially 1, can cause logic to return true mistakenly)
					foreach ($attr as $this_field2=>$this_val2) {
						if (!is_array($this_val2)) $event_data[$this_event_id][$this_field2] = (string)$this_val2;
					}
					// Isolate this non-repeating event_id by removing 'repeat_instances' sub-array					
					//$event_data2 = array($this_event_id=>$event_data[$this_event_id]);
					// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE) and add record-event if valid.
					if (isset($logicFunctions[$this_event_id][$this_record][""]['funcName'])) {
						$funcName = $logicFunctions[$this_event_id][$this_record][""]['funcName'];
						$argMap = $logicFunctions[$this_event_id][$this_record][""]['argMap'];
					} elseif (isset($logicFunctions[$this_event_id]['funcName'])) {
						$funcName = $logicFunctions[$this_event_id]['funcName'];
						$argMap = $logicFunctions[$this_event_id]['argMap'];
					} else {
						continue;
					}
					if (LogicTester::applyLogic($funcName, $argMap, $event_data, $Proj->firstEventId, false, $Proj->project_id) === 1) {
					    $record_events_logic_true[$this_record][$this_event_id][''][''] = true;
					}
				}
			}
			// Remove each record as we go to conserve memory
			unset($event_data2, $record_data[$this_record]);
		}
			
		// Return array of records-events where logic is true
		return $record_events_logic_true;
	}


	/**
	 * DATE SHIFTING: Get number of days to shift for a record
	 */
	public static function get_shift_days($idnumber, $date_shift_max, $__SALT__)
	{
		global $salt;
		if ($date_shift_max == "") {
			$date_shift_max = 0;
		}
		$dec = hexdec(substr(md5($salt . $idnumber . $__SALT__), 10, 8));
		// Set as integer between 0 and $date_shift_max
		$days_to_shift = round($dec / pow(10,strlen($dec)) * $date_shift_max);
		return $days_to_shift;
	}


	/**
	 * DATE SHIFTING: Shift a date by providing the number of days to shift
	 */
	public static function shift_date_format($date, $days_to_shift)
	{
		if ($date == "") return $date;
		// Explode into date/time pieces (in case a datetime field)
		list ($date, $time) = explode(' ', $date, 2);
		// Separate date into components
		$mm   = substr($date, 5, 2) + 0;
		$dd   = substr($date, 8, 2) + 0;
		$yyyy = substr($date, 0, 4) + 0;
		// Shift the date
		$newdate = date("Y-m-d", mktime(0, 0, 0, $mm , $dd - $days_to_shift, $yyyy));
		// Re-add time component (if applicable)
		$newdate = trim("$newdate $time");
		// Return new date/time
		return $newdate;
	}


	// Return count of all record-event pairs in project (longitudinal only) - also count repeating instances within
	public static function getCountRecordEventPairs($dags=array())
	{
		global $Proj;		
		// Get all repeating events
		$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
		// Gather all repeating forms by pulling their form status field
		$fields = array($Proj->table_pk);		
		if (!$Proj->longitudinal && !$Proj->hasRepeatingFormsEvents() && empty($dags)) {
			// If classic with no repeating forms or events, then simply return record count
			return self::getRecordCount($Proj->project_id);
		}
		elseif ($Proj->hasRepeatingForms()) {
			foreach ($repeatingFormsEvents as $these_forms) {
				if (!is_array($these_forms)) continue;
				foreach (array_keys($these_forms) as $this_form) {
					// Add form status field for each form
					$fields[] = $this_form . "_complete";
				}
			}
			$fields = array_unique($fields);
		}
		// Quick and dirty way is to get CSV data output and count the rows
		$params = array('project_id'=>$Proj->project_id, 'return_format'=>'csv', 'fields'=>$fields, 'groups'=>$dags);
		$csv_data = trim(self::getData($params));
		return substr_count($csv_data, "\n");
	}


	// Return record list array representing all rows returned in a batch of x size with y offset.
	public static function getRecordListOfTotalRowsReturned($batchSize=null, $batchLineBegin=0, $dags=array())
	{
		global $Proj;		
		// Get all repeating events
		$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
		// Gather all repeating forms by pulling their form status field
		$fields = array($Proj->table_pk);
		if ($Proj->hasRepeatingForms()) {
			foreach ($repeatingFormsEvents as $these_forms) {
				if (!is_array($these_forms)) continue;
				foreach (array_keys($these_forms) as $this_form) {
					// Add form status field for each form
					$fields[] = $this_form . "_complete";
				}
			}
			$fields = array_unique($fields);
		}
		// Get a record list for a certain size batch
		$params = array('project_id'=>$Proj->project_id, 'return_format'=>'csv', 'fields'=>$fields, 'groups'=>$dags, 
						'rowLimit'=>$batchSize, 'rowBegin'=>$batchLineBegin, 'returnRecordListAndPagingList'=>true);
		return self::getData($params);
	}


	// Add new record name as place holder to prevent race conditions when creating lots of new records in a small amount of time.
	// Assumes parameter $record has already been generated via DataEntry::getAutoId().
	// Note: The older rows in table redcap_new_record_cache get routinely purged via cron job.
	public static function addNewAutoIdRecordToCache($project_id, $record)
	{
		// Set flag to denote if record was added to cache table successfully
		$newRecord = null;
		// Loop till we find a new record name that definitely doesn't already exist
		do {
			// Check if record is already in table
			$sql = "select 1 from redcap_new_record_cache where project_id = ".$project_id." and record = '".db_escape($record)."'";
			$q = db_query($sql);
			// Add to table if not in table yet
			if (!db_num_rows($q)) {
				// Not in table yet, so add it
				$sql = "insert into redcap_new_record_cache (project_id, record, creation_time)
						values (".$project_id.", '".db_escape($record)."', '".db_escape(NOW)."')";
				if (db_query($sql)) {
					$newRecord = $record;
				}
			}
			// Record already exists/is already cached, so generate a new record name for next loop
			if ($newRecord === null) {
				// Record has already been added, so generate a new record name to try.
				$tentativeRecord = DataEntry::getAutoId($project_id);
				// If the proposed next record is the same as the current one, then increment by 1 since
				// that record name is probably still in the process of being saved
				if ($tentativeRecord."" === $record."" || $tentativeRecord <= $record) {
					$record++;
				}
				// Set generated record name as next record to check in next loop
				else {
					$record = $tentativeRecord;
				}
			}
		} while ($newRecord === null);
		// Set global flag to know that we've already been through this method once
		$GLOBALS['__addNewRecordToCache'] = true;
		// Return the new record name that definitely doesn't already exist
		return $newRecord;
	}


	// Decode XML in standard REDCap API format
	public static function xmlDecode($contents, $get_attributes = 0, $priority = 'tag', &$error_ary = null)
	{
	    if(!$contents)
            {
                if ($error_ary)
                {
                    array_push($error_ary, "The contents are empty.");
                }
                return array();
            }

	    if(!function_exists('xml_parser_create'))
            {
                if ($error_ary)
                {
                    array_push($error_ary, "The XML Parser could not be created.");
                }
                return array();
            }
			
		$xml = simplexml_load_string(trim($contents), "SimpleXMLElement", LIBXML_NOCDATA);
		
		if ($xml === false) {
			if ($error_ary) {
				array_push($error_ary, "The XML Parser encountered a problem. Please check the inputs you provided."); 
			}
			return array();
		}
		
		// Return the array
		return array($xml->getName() => json_decode(json_encode($xml), true));
	}


	/**
	 * SAVE DATA FOR RECORDS
	 * [@param int $project_id - (optional) Manually supplied project_id for this project.]
	 * @param string $dataFormat - Default 'array'. Format of the data provided (array, csv, json, xml).
	 * @param string/array $data - The data being imported (in the specified format).
	 * @param string $overwriteBehavior - "normal" or "overwrite" - Determines if blank values overwrite existing non-blank values.
	 * @param boolean $dataLogging - If TRUE, then it will automatically perform data logging (like data entered on data entry form).
	 * Returns TRUE on success, and on failure returns any error messages.
	 */
	public static function saveData()
	{
		global 	$data_resolution_enabled, $realtime_webservice_global_enabled, $lang;
		// Init vars
		$log_event_id = null;
		// Get function arguments
		$args = func_get_args();
		// Make sure we have a project_id
		if (!is_numeric($args[0]) && !defined("PROJECT_ID")) throw new Exception('No project_id provided!');
		// If first parameter is numerical, then assume it is $project_id and that second parameter is $dataFormat
		if (is_numeric($args[0])) {
			$project_id = $args[0];
			$dataFormat = (isset($args[1])) ? strToLower($args[1]) : 'array';
			$data = (isset($args[2])) ? $args[2] : "";
			$overwriteBehavior = (isset($args[3])) ? strToLower($args[3]) : 'normal';
			$dateFormat = (isset($args[4])) ? strToUpper($args[4]) : 'YMD';
			$type = (isset($args[5])) ? strToLower($args[5]) : 'flat';
			$group_id = (isset($args[6])) ? $args[6] : null;
			$dataLogging = (isset($args[7])) ? $args[7] : true;
			$performAutoCalc = (isset($args[8])) ? $args[8] : true;
			$commitData = (isset($args[9])) ? $args[9] : true;
			$logAsAutoCalculations = (isset($args[10])) ? $args[10] : false;
			$skipCalcFields = (isset($args[11])) ? $args[11] : true;
			$changeReasons = (isset($args[12])) ? $args[12] : array();
			$returnDataComparisonArray = (isset($args[13])) ? $args[13] : false;
			$skipFileUploadFields = (isset($args[14])) ? $args[14] : true;
			$removeLockedFields = (isset($args[15])) ? $args[15] : false;
			$addingAutoNumberedRecords = (isset($args[16])) ? $args[16] : false;
			$bypassPromisCheck = (isset($args[17])) ? $args[17] : false;
		} else {
			$project_id = PROJECT_ID;
			$dataFormat = (isset($args[0])) ? strToLower($args[0]) : 'array';
			$data = (isset($args[1])) ? $args[1] : "";
			$overwriteBehavior = (isset($args[2])) ? strToLower($args[2]) : 'normal';
			$dateFormat = (isset($args[3])) ? strToUpper($args[3]) : 'YMD';
			$type = (isset($args[4])) ? strToLower($args[4]) : 'flat';
			$group_id = (isset($args[5])) ? $args[5] : null;
			$dataLogging = (isset($args[6])) ? $args[6] : true;
			$performAutoCalc = (isset($args[7])) ? $args[7] : true;
			$commitData = (isset($args[8])) ? $args[8] : true;
			$logAsAutoCalculations = (isset($args[9])) ? $args[9] : false;
			$skipCalcFields = (isset($args[10])) ? $args[10] : true;
			$changeReasons = (isset($args[11])) ? $args[11] : array();
			$returnDataComparisonArray = (isset($args[12])) ? $args[12] : false;
			$skipFileUploadFields = (isset($args[13])) ? $args[13] : true;
			$removeLockedFields = (isset($args[14])) ? $args[14] : false;
			$addingAutoNumberedRecords = (isset($args[15])) ? $args[15] : false;
			$bypassPromisCheck = (isset($args[16])) ? $args[16] : false;
		}
        // Instantiate object containing all project information
        $Proj = new Project($project_id);
        $longitudinal = $Proj->longitudinal;
        $table_pk = $Proj->table_pk;
        $secondary_pk = $Proj->project['secondary_pk'];
		$missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
		$status = $Proj->project['status'];
		$data_locked = $Proj->project['data_locked'];

		// If project is in Analysis/Cleanup Mode with Data Locked enabled, return an error
		if ($status == '2' && $data_locked == '1') {
			return "{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_287']}";
		}

		// If $dataFormat is not valid, return error
		$validDataFormats = array('csv', 'xml', 'json', 'array', 'odm');
		if (!in_array($dataFormat, $validDataFormats)) {
			return $lang['data_import_tool_202'] . $dataFormat . $lang['data_import_tool_203'] . " " . implode(", ", $validDataFormats);
		}

		// If $dateFormat is not valid, return error
		$validDateFormats = array('YMD', 'MDY', 'DMY');
		if (!in_array($dateFormat, $validDateFormats)) {
			return $lang['data_import_tool_205'] . $dateFormat . $lang['data_import_tool_203'] . " " . implode(", ", $validDateFormats);
		}

		// If $overwriteBehavior is not valid, return error
		$validOverwriteBehavior = array('normal', 'overwrite');
		if (!in_array($overwriteBehavior, $validOverwriteBehavior)) {
			return $lang['data_import_tool_207'] . $overwriteBehavior . $lang['data_import_tool_203'] . " " . implode(", ", $validOverwriteBehavior);
		}

		// If $type is not valid, return error
		$validTypes = array('flat', 'eav');
		if (!in_array($type, $validTypes)) {
			return $lang['data_import_tool_204'] . $type . $lang['data_import_tool_203'] . " " . implode(", ", $validTypes);
		}

		// If format is 'array', then force as 'flat'
		if ($dataFormat == 'array' && $type == 'eav') $type = 'flat';
		
		// If not using auto-numbering in project, set addingAutoNumberedRecords to false
		if ($addingAutoNumberedRecords && !$Proj->project['auto_inc_set']) $addingAutoNumberedRecords = false;

		// If $data is empty, return error
		if ($data == "") return $lang['data_import_tool_201'];

		// In case we need to trigger Twilio based on data being saved, call the Twilio init
		TwilioRC::init();

		// GROUP_ID: Get array of all DAGs and validate group_id
		$allDags = $Proj->getUniqueGroupNames();
		if (empty($allDags)) {
			$group_id = null;
		} else {
			if (is_numeric($group_id)) {
				if (!isset($allDags[$group_id])) {
					return $lang['data_import_tool_208'] . $group_id . $lang['data_import_tool_209'];
				}
			} elseif ($group_id != "" && $group_id != null) {
				$group_id_key = array_search($group_id, $allDags);
				if ($group_id_key !== false) {
					// Valid group_id
					$group_id = $group_id_key;
				} else {
					return $lang['data_import_tool_208'] . $group_id . $lang['data_import_tool_209'];
				}
			} else {
				$group_id = null;
			}
		}

		// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
		$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
							   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");
		
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        $hasRepeatingEvents = $Proj->hasRepeatingEvents();

		try
		{
			// Make sure data is in specified format
			switch ($dataFormat)
			{
				case 'array':
					if (!is_array($data)) return $lang['data_import_tool_200'];
					// Reconfigure array to flat format
					$data1 = array();
					$i = 0;
					// Determine if the array contains any repeated instances
					$arrayHasRepeatInstances = false;
					if ($hasRepeatingFormsEvents) {
						foreach ($data as $this_record=>&$this_event1) {
							foreach (array_keys($this_event1) as $this_event_id) {
								if ($this_event_id == 'repeat_instances') {
									$arrayHasRepeatInstances = true;
									break 2;
								}
							}
						}
						unset($this_event1);
					}
					// check the event that was entered to make sure it is valid
					$invalidEventIds = array();
					foreach ($data as $this_record=>&$this_event1) {
						foreach ($this_event1 as $this_event_id=>&$attr) {
							// Repeating instances?
							if ($this_event_id == 'repeat_instances') {
								// Repeating instances								
								foreach ($attr as $this_event_id=>$battr) {
									foreach ($battr as $this_repeat_instrument=>$cattr) {
										foreach ($cattr as $this_instance=>$dattr) {
											// Add record name
											$data1[$i][$table_pk] = $this_record;
											if ($arrayHasRepeatInstances) {
												$data1[$i]['redcap_repeat_instrument'] = $this_repeat_instrument;
												$data1[$i]['redcap_repeat_instance'] = $this_instance;
											}
											// Regault non-repeating: Get unique event name from event_id
											if ($longitudinal) {
												$data1[$i]['redcap_event_name'] = $Proj->getUniqueEventNames($this_event_id);
												// If not a valid event_id, then add it for error msg
												if ($data1[$i]['redcap_event_name'] == '') {
													$invalidEventIds[] = $this_event_id;
												}
											}						
											// Loop through all fields in event
											foreach ($dattr as $this_field=>$this_value) {
												// Convert checkbox to flat format
												if (is_array($this_value)) {
													foreach ($this_value as $this_code=>$this_checkbox_value) {
														$this_checkbox_field = Project::getExtendedCheckboxFieldname($this_field, $this_code);
														$data1[$i][$this_checkbox_field] = $this_checkbox_value;
													}
												} else {
													$data1[$i][$this_field] = $this_value;
												}
											}
											// Increment key
											$i++;
										}
									}
								}
							} else {
								// Add record name
								$data1[$i][$table_pk] = $this_record;
								if ($arrayHasRepeatInstances) {
									$data1[$i]['redcap_repeat_instrument'] = "";
									$data1[$i]['redcap_repeat_instance'] = "";
								}
								// Regault non-repeating: Get unique event name from event_id
								if ($longitudinal) {
									$data1[$i]['redcap_event_name'] = $Proj->getUniqueEventNames($this_event_id);
									// If not a valid event_id, then add it for error msg
									if ($data1[$i]['redcap_event_name'] == '') {
										$invalidEventIds[] = $this_event_id;
									}
								}
								// Loop through all fields in event
								foreach ($attr as $this_field=>$this_value) {
									// Convert checkbox to flat format
									if (is_array($this_value)) {
										foreach ($this_value as $this_code=>$this_checkbox_value) {
											$this_checkbox_field = Project::getExtendedCheckboxFieldname($this_field, $this_code);
											$data1[$i][$this_checkbox_field] = $this_checkbox_value;
										}
									} else {
										$data1[$i][$this_field] = $this_value;
									}
								}
								// Increment key
								$i++;
							}
						}
						// Remove record from $data to save space
						unset($data[$this_record]);
					}
					unset($this_event1, $attr);
					if (!empty($invalidEventIds)) {
						$invalidEventIds = array_unique($invalidEventIds);
						throw new Exception($lang['data_import_tool_210']." ".htmlspecialchars(implode(", ", $invalidEventIds), ENT_QUOTES));
					}
					// Reset $data
					$data = $data1;
					unset($data1);
					break;
				case 'json':
					// Decode JSON into array
					$data = json_decode($data, true);
					if ($data == '') return $lang['data_import_tool_200'];
					break;
				case 'xml':
					// Decode XML into array
					$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
					if ($data == '' || !isset($data['records']['item'])) return $lang['data_import_tool_200'];
					$data = (isset($data['records']['item'][0])) ? $data['records']['item'] : array($data['records']['item']);
					break;
				case 'csv':
					// Convert CSV to array
					if (PAGE == 'api/index.php' && isset($_POST['playground'])) {	
						$data = html_entity_decode($data, ENT_QUOTES);
					}
					$data = csvToArray($data);
					break;
				case 'odm':
					// Convert ODM to array
					$data = ODM::convertOdmClinicalDataToCsv($data, $Proj);
					if (isset($data['errors']) && count($data['errors']) > 0) {
						return implode("\n", $data['errors']);
					}
					$data = csvToArray($data);
					break;
			}
			
			// Return error if uploading repeating fields when project is not set to repeat forms/events
			if (!$hasRepeatingFormsEvents && (isset($data[0]['redcap_repeat_instrument']) || isset($data[0]['redcap_repeat_instance']))) {
				throw new Exception("{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_252']} {$lang['data_import_tool_253']}");
			}

			// CHECKBOXES: Create new arrays with all checkbox fields and the translated checkbox field names
			$checkboxFields = MetaData::getCheckboxFields($project_id);
			$fullCheckboxFields = array();
			foreach ($checkboxFields as $field=>$value) {
				foreach ($value as $code=>$label) {
					$code = (Project::getExtendedCheckboxCodeFormatted($code));
					$fullCheckboxFields[$field . "___" . $code] = $label;
				}
				// Also add any missing data code checkboxes
				if (!Form::hasActionTag("@NOMISSING", $Proj->metadata[$field]['misc'])) {
					foreach ($missingDataCodes as $code => $label) {
						// Add missing codes to $checkboxFields
						$checkboxFields[$field][$code] = $label;
						$code = (Project::getExtendedCheckboxCodeFormatted($code));
						$fullCheckboxFields[$field . "___" . $code] = $label;
					}
				}
			}

			// Get an array of the events with their unique key names and ids
			$events = array_flip($Proj->getUniqueEventNames());

			$counter = 0;

			$records = array();
			$newIds = array();
			$idArray = array();
			$duplicateIds = array();
			$illegalCharsInRecordName = array();
			$eventList = array();
			$fieldList = array();

			if ($type == 'eav')
			{
				# add incoming data to array
				foreach ($data as $index => $record)
				{
					$studyId = trim($record['record']);
					$eventName = (isset($record['redcap_event_name']) ? trim($record['redcap_event_name']) : $Proj->getUniqueEventNames($Proj->firstEventId));
					if (!isset($record['redcap_event_name']) && $Proj->longitudinal) {
						$record['redcap_event_name'] = $eventName;
					}
					$fieldName = trim($record['field_name']);
					$fieldValue = trim($record['value']);

					// make sure the primary key and event name are not empty
					if ( $studyId == '' )
						throw new Exception($lang['data_import_tool_211']);
					if (strlen($studyId) > 100)
						throw new Exception($lang['data_import_tool_233']." $studyId");
					if ( $Proj->table_pk == $fieldName && $fieldValue == '' )
						throw new Exception($lang['data_import_tool_212'].$Proj->table_pk.$lang['data_import_tool_213']);
					if ( $longitudinal && $eventName == '' )
						throw new Exception($lang['data_import_tool_214']);

					// Make sure record names do NOT contain a +, &, #, or apostrophe
					if (strpos($studyId, '+') !== false || strpos($studyId, "'") !== false || strpos($studyId, '&') !== false || strpos($studyId, '#') !== false) {
						throw new Exception($lang['data_import_tool_215'].$studyId.$lang['data_import_tool_216']);
					}

					// get unique key for each record
					$this_repeat_instrument = (isset($record['redcap_repeat_instrument']) && $record['redcap_repeat_instance'] != '') ? $record['redcap_repeat_instrument'] : "";
					$this_repeat_instance = (isset($record['redcap_repeat_instance']) && $record['redcap_repeat_instance'] != '' && is_numeric($record['redcap_repeat_instance'])) ? $record['redcap_repeat_instance'] : "";
					$key = "$studyId-$eventName-$this_repeat_instrument-$this_repeat_instance";

					if ($hasRepeatingFormsEvents) {
						if (isset($record['redcap_repeat_instrument'])) {
							$records[$key]['redcap_repeat_instrument'] = array('new' => $this_repeat_instrument, 'old' => '', 'status' => '');
						}
						if (isset($record['redcap_repeat_instance'])) {
							$records[$key]['redcap_repeat_instance'] = array('new' => $this_repeat_instance, 'old' => '', 'status' => '');
						}
					}

					if ( !in_array($studyId, $newIds, true) ) $newIds[] = $studyId;

					// set fieldname, new value, and default value
					if (isset($checkboxFields[$fieldName]))
					{
						$newFieldName = $fieldName . "___" . $fieldValue;
						$newValue = "1";
						$defaultValue = "0";
					}
					else
					{
						$newFieldName = $fieldName;
						$newValue = str_replace('\"', '"', $fieldValue);
						$defaultValue = (isset($fullCheckboxFields[$fieldName])) ? "0" : "";
					}

					// save fieldname in array
					if ( !in_array($newFieldName, $fieldList) ) $fieldList[] = $newFieldName;

					// if longitudinal, save the event name in array and add field to global array
					if ($longitudinal)
					{
						if ( !in_array($eventName, $eventList) )
							$eventList[] = $eventName;

						$records[$key]['redcap_event_name'] = array('new' => $eventName, 'old' => '', 'status' => '');
					}

					// add field and information to global array
					$records[$key][$newFieldName] = array('new' => $newValue, 'old' => $defaultValue, 'status' => '');

					// check to see if the primary key is in the array as its own element.  If not add it
					if ( !isset($record[$key][$Proj->table_pk]) ) {
						$records[$key][$Proj->table_pk] = array('new' => $studyId, 'old' => '', 'status' => '');

						// save fieldname in array
						if (!in_array($Proj->table_pk, $fieldList)) $fieldList[] = $Proj->table_pk;
					}

					// Free up memory
					unset($data[$index]);
				}
			}
			else
			{
				# add incoming data to array
				foreach ($data as $index => $record)
				{
					$studyId = trim($record[$Proj->table_pk]);
					if ($Proj->longitudinal) {
						$eventName = (isset($record['redcap_event_name']) ? trim($record['redcap_event_name']) : $Proj->getUniqueEventNames($Proj->firstEventId));
						if (!isset($record['redcap_event_name'])) {
							$record['redcap_event_name'] = $eventName;
						}
					} else {
						$eventName = $Proj->getUniqueEventNames($Proj->firstEventId);
					}
					
					// make sure the primary key and event name are not empty
					if ($studyId == '')
						throw new Exception($lang['data_import_tool_212'].$Proj->table_pk.$lang['data_import_tool_213']);
					if (strlen($studyId) > 100)
						throw new Exception($lang['data_import_tool_233']." $studyId");
					if ($longitudinal && $eventName == '')
						throw new Exception($lang['data_import_tool_214']);
					// Make sure record names do NOT contain a +, &, #, or apostrophe
					if (strpos($studyId, '+') !== false || strpos($studyId, "'") !== false || strpos($studyId, '&') !== false || strpos($studyId, '#') !== false) {
						throw new Exception($lang['data_import_tool_215'].$studyId.$lang['data_import_tool_216']);
					}

					// get unique key for each record
					$this_repeat_instrument = (isset($record['redcap_repeat_instrument']) && $record['redcap_repeat_instance'] != '') ? $record['redcap_repeat_instrument'] : "";
					$this_repeat_instance = (isset($record['redcap_repeat_instance']) && $record['redcap_repeat_instance']."" !== '' && is_numeric($record['redcap_repeat_instance'])) ? $record['redcap_repeat_instance'] : "";
					$key = "$studyId-$eventName-$this_repeat_instrument-$this_repeat_instance";

                    // Make sure that there is a redcap_repeat_instance if this is a repeating event
                    if ($longitudinal && $hasRepeatingEvents && !isset($record['redcap_repeat_instance']) && isset($record['redcap_event_name'])) {
                        $thisEventRepeating = $Proj->isRepeatingEvent($Proj->getEventIdUsingUniqueEventName($record['redcap_event_name']));
                        if ($thisEventRepeating) {
                            throw new Exception($lang['data_import_tool_215'] . $studyId . $lang['data_import_tool_282'] . " \"{$record['redcap_event_name']}\" " .
                                $lang['data_import_tool_283']);
                        }
                    }

                    // Make sure that there is a redcap_repeat_instrument if this is a classic project with fields from a repeating form
                    if (!$longitudinal && $hasRepeatingFormsEvents && is_numeric($this_repeat_instance) && $this_repeat_instrument == '') {
                        throw new Exception($lang['data_import_tool_215'] . $studyId . $lang['data_import_tool_284'] . " \"{$record['redcap_repeat_instance']}\" " .
                            $lang['data_import_tool_285']);
                    }

                    if ($this_repeat_instance."" === "0")
                        throw new Exception(serialize(array("$studyId,redcap_repeat_instance,0,".str_replace(array("\r\n","\n"),array(" "," "),"{$lang['data_import_tool_279']} $studyId"))));

					// check for duplicate ids
					if (in_array(strtolower($key), array_map('strtolower', $idArray), true) && !in_array($key, $duplicateIds)) {
						$duplicateIds[] = $key;
					}
					
					// save ID and Key in arrays
					if ( !in_array($studyId, $newIds, true) ) $newIds[] = $studyId;
					if ( !in_array($key, $idArray, true) ) $idArray[] = $key;

					foreach($record as $field => $value)
					{
						$fieldName = trim($field);
						$fieldValue = trim($value);

						// if longitudinal, save the event name in array
						if ($longitudinal)
						{
							if ( $fieldName == "redcap_event_name" )
								if (!in_array($fieldValue, $eventList)) $eventList[] = $fieldValue;
						}

						// format value
						$newValue = str_replace('\"', '"', $fieldValue);

						// Checkbox fields get default of 0, all others default of ""
						if (isset($fullCheckboxFields[$fieldName])) {
							$oldValue = "0";
							// If checkbox doesn't exist yet in $records, then seed it with all choices
							if (!isset($records[$key][$fieldName])) {
								$fieldNameReal = substr($fieldName, 0, strrpos($fieldName, "___"));
								// If choice value begins with negative, then will leave an undersccore on end of field_name. If so, remove underscore.
								if (substr($fieldNameReal, -1) == '_') $fieldNameReal = substr($fieldNameReal, 0, -1);
								foreach ($checkboxFields[$fieldNameReal] as $code => $label) {
									$this_checkbox_fieldname = $fieldNameReal . "___" . Project::getExtendedCheckboxCodeFormatted($code);
									// Only add this individual checkbox choice (not all)
									if ($this_checkbox_fieldname == $fieldName) {
										$records[$key][$this_checkbox_fieldname] = array('new' => $oldValue, 'old' => $oldValue, 'status' => '');
									}
								}
							}
							// if overwrite and value is blank, set value to 0 so item will be deleted
							if ($newValue == "" && $overwriteBehavior == "overwrite") $newValue = "0";
						}
						else {
							$oldValue = "";
						}

						// save fieldname in array
						if (!in_array($fieldName, $fieldList)) $fieldList[] = $fieldName;

						// add field and information to global array
						$records[$key][$fieldName] = array('new' => $newValue, 'old' => $oldValue, 'status' => '');
					}

					// Free up memory
					unset($data[$index]);
				}

				// throw error if duplicates were found
				if (count($duplicateIds) > 0 && !$hasRepeatingFormsEvents) {
					$message = $lang['data_import_tool_217']." ".implode(", ", $duplicateIds);
					throw new Exception($message);
				}
			}
			unset($record, $data, $duplicateIds);
			## PROCESS DATA
			
			// Data Resolution Workflow: If enabled, create array to capture record/event/fields that
			// had their data value changed just now so they can be De-verified, if already Verified.
			$autoDeverify = array();

			# create array of all form fields
			// $fullFieldList = array_merge($fieldList, array_keys($checkboxFields));
			$fullFieldList = array();
			foreach ($fieldList as $this_field) {
				$pos_3underscore = strpos($this_field, "___");
				if ($pos_3underscore !== false && isset($checkboxFields[substr($this_field, 0, $pos_3underscore)])) {
					// Checkbox
					$this_checkbox_field = substr($this_field, 0, $pos_3underscore);
					$fullFieldList[] = $this_checkbox_field;
					// Add all choices as fields too
					foreach ($checkboxFields[$this_checkbox_field] as $code => $label) {
						$fullFieldList[] = $this_checkbox_field . "___" . Project::getExtendedCheckboxCodeFormatted($code);
					}
				} else {
					// Non-checkbox field
					$fullFieldList[] = $this_field;
				}
			}

			// If redcap_data_access_group is in the field list AND user is NOT in a DAG, then set flag
			$hasDagField = (in_array('redcap_data_access_group', $fullFieldList));

			# get all metadata information
			$metaData = MetaData::getFields2($project_id, $fullFieldList);

			# create list of fields based off the metadata to determine later if any fields are trying to be
			# uploaded that are not in the metadata
			$rsMeta = db_query("SELECT field_name FROM redcap_metadata WHERE project_id = $project_id ORDER BY field_order");
			$metadataFields = array();
			while($row = db_fetch_array($rsMeta))
			{
				if (isset($checkboxFields[$row['field_name']]))
				{
					foreach ($checkboxFields[$row['field_name']] as $code => $label) {
						$metadataFields[] = $row['field_name'] . "___" . Project::getExtendedCheckboxCodeFormatted($code);
					}
				}
				else
				{
					$metadataFields[] = $row['field_name'];
				}
			}

			// Add all Missing Data Codes as checkbox options in an array (unless field has @NOMISSING action tag)
			$checkboxChoicesMissing = array();
			if (!empty($missingDataCodes)) {
				foreach ($Proj->metadata as $field=>$attr) {
					if (!$Proj->isCheckbox($field)) continue;
					if (Form::hasActionTag("@NOMISSING", $attr['misc'])) continue;
					foreach ($missingDataCodes as $raw_coded_value => $checkbox_label) {
						$checkboxChoicesMissing[] = Project::getExtendedCheckboxFieldname($field, $raw_coded_value);
					}
				}
			}
			
			$unknownFields = array();
			foreach ($fieldList as $field)
			{
				if ( ($field != "redcap_event_name" && $field != "redcap_data_access_group" && !in_array($field, $metadataFields)
					&& !in_array($field, $extra_reserved_field_names) && !isset(Project::$reserved_field_names[$field]))
					&& !in_array($field, $checkboxChoicesMissing)
					// Make sure it's not a Descriptive field
				|| (isset($Proj->metadata[$field]['element_type']) && $Proj->metadata[$field]['element_type'] == 'descriptive')
				) {
					$unknownFields[] = $field;
				}
			}

			if (!defined("CREATE_PROJECT_ODM") && count($unknownFields) > 0)
			{
				$message = $lang['data_import_tool_218']." ".htmlspecialchars(implode(", ", $unknownFields), ENT_QUOTES);
				throw new Exception($message);
			}

			// Check and fix any case sensitivity issues in record names
			if (!$addingAutoNumberedRecords) {
				$records = Records::checkRecordNamesCaseSensitive($project_id, $records, $table_pk, $longitudinal);
			}
			
			// Set new id's from records array keys
			$newIds = $updatedIds = array();
			$thisAutoId = null;
			foreach ($records as $key=>&$record) {
				// Get current record name
				$id = $oldId = $record[$table_pk]['new']."";
				// If auto-numbering all records, get new record id
				if ($addingAutoNumberedRecords) {
					list ($this_id, $this_event_name, $repeat_instrument, $repeat_instance) = explode_right("-", $key, 4);
					if (isset($autoNumberedRecords[$oldId])) { 
						// Get the current translated ID
						$id = $autoNumberedRecords[$oldId]."";
					} else {
						// Determine the next auto-ID and swap it for the imported one
						$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event_name);
						$this_arm_id = $Proj->eventInfo[$this_event_id]['arm_id'];
						if ($commitData) {
							$id = Records::addNewAutoIdRecordToCache($project_id, DataEntry::getAutoId($project_id)) . "";
						} else {
							if (!is_numeric($thisAutoId)) {
								$thisAutoId = DataEntry::getAutoId($project_id);
							} else {
								$thisAutoId++;
							}
							$id = $thisAutoId;
						}
						$autoNumberedRecords[$oldId] = $id;
					}
					// Change the key to the new record name
					unset($records[$key]);
					$key = $id."-".$this_event_name."-".$repeat_instrument."-".$repeat_instance;
					$record[$table_pk]['new'] = $id;
					$records2[$key] = $record;
				}
				// Add id to newIds array
				if (!in_array($id, $newIds, true)) {
					$newIds[] = $id;
				}
				// Add id to updatedIds array (with oldId as the key)
				if (!in_array($id, $updatedIds)) {
					$updatedIds[$oldId] = $id;
				}	
			}
			if ($addingAutoNumberedRecords) $records = $records2;
			unset($record, $records2);

			# if user is in a DAG, filter records accordingly
			$dagIds = array();
			if ($group_id != "" && !$addingAutoNumberedRecords)
			{
				// Get records already in our DAG
				$dagIds = Records::getRecordList($project_id, $group_id, true);
			}

			if ($longitudinal)
			{
				// check the event that was entered to make sure it is valid
				$invalidEventIds = $eventIds = array();
				foreach ($eventList as $this_event_name) {
					if ($Proj->uniqueEventNameExists($this_event_name)) {
						$eventIds[] = $Proj->getEventIdUsingUniqueEventName($this_event_name);
					} else {
						$invalidEventIds[] = $this_event_name;
					}
				}
				if (!empty($invalidEventIds)) {
					$invalidEventIds = array_unique($invalidEventIds);
					throw new Exception($lang['data_import_tool_219']." ".htmlspecialchars(implode(", ", $invalidEventIds), ENT_QUOTES));
				}
			} else {
				$eventIds = array($Proj->firstEventId);
			}
			
			// EXISTING VALUES: Load current values into array for comparison
			$existingIdList = array();
			if (!$addingAutoNumberedRecords)
			{
				$sql = "SELECT record, event_id, field_name, value, instance FROM redcap_data
						WHERE project_id = $project_id AND event_id IN (" . prep_implode($eventIds) . ")";
                // Deal with long queries
                $sql_fields = " AND field_name IN (" . prep_implode($fullFieldList) . ")";
                if (strlen($sql.$sql_fields) > 1000000) {
                    $checkFieldNameEachLoop = true;
                } else {
                    $sql .= $sql_fields;
                    $checkFieldNameEachLoop = false;
                }
                $sql_records = " AND record IN (" . prep_implode($newIds) . ")";
                if (strlen($sql.$sql_records) > 1000000) {
                    $checkRecordNameEachLoop = true;
                } else {
                    $sql .= $sql_records;
                    $checkRecordNameEachLoop = false;
                }
                // Execute query
				$rsExistingData = db_query($sql);
				while ($row = db_fetch_assoc($rsExistingData))
				{
                    // If we need to validate the field name in each loop, then check.
                    if ($checkFieldNameEachLoop && !in_array($row['field_name'], $fullFieldList)) continue;
                    // If we need to validate the record in each loop, then check.
                    if ($checkRecordNameEachLoop && !in_array($row['record'], $newIds)) continue;

					$this_repeat_instrument = ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $Proj->metadata[$row['field_name']]['form_name'])) ? $Proj->metadata[$row['field_name']]['form_name'] : "";
					if ($row['instance'] == '' && $hasRepeatingFormsEvents 
						&& ($Proj->isRepeatingEvent($row['event_id']) || $this_repeat_instrument != '')) {
						$row['instance'] = '1';
					}
					if (!$longitudinal && $row['instance'] > 1 && $this_repeat_instrument == '') continue; // For classic, we can't have any repeating events
					$key = $row['record']."-".$Proj->getUniqueEventNames($row['event_id'])."-$this_repeat_instrument-".$row['instance'];

					if ( isset($checkboxFields[$row['field_name']]) )
					{
						$fieldname = $row['field_name']."___".Project::getExtendedCheckboxCodeFormatted($row['value']);
						$records[$key][$fieldname]['old'] = "1";
					}
					else
					{
						$fieldname = $row['field_name'];
						$records[$key][$fieldname]['old'] = $row['value'];

						# if the old value is blank set flag
						if ($row['value'] == "") {
							$records[$key][$fieldname]['old_blank'] = true;
						}
					}

					// add id to list if not already in there
					$this_record_arm = $Proj->multiple_arms ? $Proj->eventInfo[$row['event_id']]['arm_num'] : 1;
					if (!isset($existingIdList[$row['record']][$this_record_arm])) {
						$existingIdList[$row['record']][$this_record_arm] = true;
					}

					// If this is a repeating instance value, make sure the record ID field and repeat instance/instrumnet fields are populated as "old" for this $key
					if ($this_repeat_instrument . $row['instance'] != '')
					{
						if (isset($records[$key][$table_pk]) && $records[$key][$table_pk]['old'] == '') {
							$records[$key][$table_pk]['old'] = $records[$key][$table_pk]['new'];
						}
						if (isset($records[$key]['redcap_repeat_instance']) && $records[$key]['redcap_repeat_instance']['old'] == '') {
							$records[$key]['redcap_repeat_instance']['old'] = $records[$key]['redcap_repeat_instance']['new'];
						}
						if ($this_repeat_instrument != '' && isset($records[$key]['redcap_repeat_instrument']) && $records[$key]['redcap_repeat_instrument']['old'] == '') {
							$records[$key]['redcap_repeat_instrument']['old'] = $records[$key]['redcap_repeat_instrument']['new'];
						}
					}
				}
				db_free_result($rsExistingData);
			}
			
			// DAGS: If user is in a DAG and is trying to edit a record not in their DAG, return error
			if ($group_id == '' && $hasDagField)
			{
				$invalidGroupNames = array();
				// Validate the unique group names submitted. If invalid, return error.
				foreach ($records as &$record) {
					// Get group name
					$group_name = $record['redcap_data_access_group']['new'];
					// Ignore if blank
					if ($group_name != '' && !$Proj->uniqueGroupNameExists($group_name)) {
						$invalidGroupNames[] = $group_name;
					}
				}
				unset($record);
				$invalidGroupNames = array_unique($invalidGroupNames);
				// Check for errors
				if (!empty($invalidGroupNames)) {
					// ERROR: Group name is invalid. Return error.
					$invalidGroupNamesErrMsg = $lang['data_import_tool_220']." ".implode(", ", $invalidGroupNames);
					throw new Exception($invalidGroupNamesErrMsg);
				}
				## If no errors exist, then get existing DAG designations and add to $records for each record				
				if (!$addingAutoNumberedRecords)
				{
					$sql = "select distinct record, value from redcap_data where project_id = $project_id and field_name = '__GROUPID__'
							and record in ('".implode("', '", array_keys($existingIdList))."')";
					$q = db_query($sql);
					$recordDags = array();
					while ($row = db_fetch_assoc($q)) {
						// Obtain and verify unique group name
						$group_name = $Proj->getUniqueGroupNames($row['value']);
						if (!empty($group_name)) {
							$recordDags[$row['record']] = $group_name;
						}
					}
					// Loop through all records/items and add existing DAG value
					foreach (array_keys($records) as $key) {
						list ($this_record, $nothing, $nothing, $nothing) = explode_right("-", $key, 4);
						if (isset($recordDags[$this_record])) {
							// Add exist group name to $records
							$records[$key]['redcap_data_access_group']['old'] = $recordDags[$this_record];
						}
					}
				}
			}
			elseif (is_numeric($group_id) && $hasDagField)
			{
				// ERROR: User cannot assign records to DAGs. Return error.
				throw new Exception("{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_171']} {$lang['data_import_tool_172']}");
			} 
			elseif (is_numeric($group_id) && !$hasDagField)
			{
				$invalidRecordsDag = array();
				// User is in a DAG, so make sure any existing records being modified are in their DAG
				$sql = "select distinct record, value from redcap_data where project_id = $project_id and field_name = '__GROUPID__'
						and record in ('".implode("', '", array_keys($existingIdList))."')";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q))
				{
					if (is_numeric($row['value']) && $group_id != $row['value']) {
						// Add non-DAG record to array
						$invalidRecordsDag[] = $row['record'];
					}
				}
				$invalidRecordsDag = array_unique($invalidRecordsDag);
				// Check for errors
				if (!empty($invalidRecordsDag)) {
					// ERROR: Group name is not user's DAG. Return error.
					$invalidGroupNamesErrMsg = $lang['data_import_tool_251']." ".implode(", ", $invalidRecordsDag);
					throw new Exception($invalidGroupNamesErrMsg);
				}
			}
			
			# compare new and old values and set status

			$datetime_warnings = array();

			$record_names = array();
			foreach ($records as $key => &$record3)   // loop through each record
			{
				foreach ($record3 as $fieldname => $data)    // loop through each field
				{
					// Get true record name
					if ($fieldname == $table_pk) {
						$record_names[] = html_entity_decode($data['new'], ENT_QUOTES);
					}

					$isFileUploadField = ($metaData[$fieldname]['element_type'] == 'file');

					if ( isset($data['new']) && $fieldname != "redcap_event_name"
						&& (!isset($metaData[$fieldname]) || !$isFileUploadField || (!$skipFileUploadFields && $isFileUploadField)))
					{
						$isOldValueBlank = (isset($data['old_blank']));
						$newValue = $data['new'];
						$oldValue = html_entity_decode($data['old'], ENT_QUOTES);
						
						// If have record ID on repeating instance, always set to keep, not add (otherwise it gets re-added every time)
						if ($hasRepeatingFormsEvents && $fieldname == $table_pk) {
							list ($nothing, $nothing, $repeat_instrument, $repeat_instance) = explode_right("-", $key, 4);
							if ($repeat_instance > 1) {
								$oldValue = $newValue;
							}
						}

						## PERFORM SOME PRE-CHECKS FIRST FOR FORMATTING ISSUES OF CERTAIN VALIDATION TYPES
						## UNLESS value is a missing data code
						
						// Ensure all dates are in correct format (yyyy-mm-dd hh:mm and yyyy-mm-dd hh:mm:ss)
						if (!isset($missingDataCodes[$newValue]))
						{
							if (substr(isset($metaData[$fieldname]) ? $metaData[$fieldname]['element_validation_type'] : '', 0, 8) == 'datetime')
							{
								if ($newValue != "")
								{
									// If contains a "T" instead of a " " before the time, then replace with a space
									if (strpos($newValue, "T") !== false) $newValue = str_replace("T", " ", $newValue);
									// Break up into date and time
									list ($thisdate, $thistime) = explode(' ', $newValue, 2);

									// Format date based on / delimiter
									if (strpos($records[$key][$fieldname]['new'],"/") !== false && ($dateFormat == 'DMY' || $dateFormat == 'MDY')) {
										// Determine if D/M/Y or M/D/Y format
										if ($dateFormat == 'DMY') {
											list ($day, $month, $year) = explode('/', $thisdate);
										} else {
											list ($month, $day, $year) = explode('/', $thisdate);
										}
										// Convert 2 year date to 4 year date based on assumption that dates more than 10 years in the future were from the previous century
										if (strlen($year) == 2) {
											$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
											$datetime_warnings[] = array(
												'record' => $records[$key][$table_pk]['new'],
												'field_name' => $fieldname,
												'value' => $newValue,
												'message' => $lang['data_import_tool_291'].' ' . $year
											);
										}
										$thisdate = sprintf("%04d-%02d-%02d", $year, $month, $day);
									} else {
										// Make sure has correct amount of digits with proper leading zeros
										$thisdate = clean_date_ymd($thisdate);
									}

									// Format time
									list ($hour, $min, $sec) = explode(':', $thistime);
									if (substr($metaData[$fieldname]['element_validation_type'], 0, 16) == 'datetime_seconds') {
										// datetime_seconds
										$thistime = sprintf("%02d:%02d:%02d", $hour, $min, $sec);
										if (is_null($min)) {
											$datetime_warnings[] = array(
												'record' => $records[$key][$table_pk]['new'],
												'field_name' => $fieldname,
												'value' => $newValue,
												'message' => $lang['data_import_tool_292'].' ' . $thistime
											);
										}
										if (is_null($sec)) {
											$datetime_warnings[] = array(
												'record' => $records[$key][$table_pk]['new'],
												'field_name' => $fieldname,
												'value' => $newValue,
												'message' => $lang['data_import_tool_293'].' ' . $thistime
											);
										}
									} else {
										// datetime
										$thistime = sprintf("%02d:%02d", $hour, $min);
										if (is_null($min)) {
											$datetime_warnings[] = array(
												'record' => $records[$key][$table_pk]['new'],
												'field_name' => $fieldname,
												'value' => $newValue,
												'message' => $lang['data_import_tool_294'].' ' . $thistime
											);
										}
									}
    								$records[$key][$fieldname]['new'] = $newValue = clean_date_ymd($thisdate) . " " . $thistime;
								}
							}
							// First ensure all dates are in correct format (yyyy-mm-dd)
							elseif (substr(isset($metaData[$fieldname]) ? $metaData[$fieldname]['element_validation_type'] : '', 0, 4) == 'date')
							{
								if ($newValue != "")
								{
									if (strpos($records[$key][$fieldname]['new'],"/") !== false && ($dateFormat == 'DMY' || $dateFormat == 'MDY')) {
										// Assume American format (mm/dd/yyyy) if contains forward slash
										// Determine if D/M/Y or M/D/Y format
										if ($dateFormat == 'DMY') {
											list ($day, $month, $year) = explode('/', $newValue);
										} else {
											list ($month, $day, $year) = explode('/', $newValue);
										}
										// Make sure year is 4 digits
										if (strlen($year) == 2) {
											$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
											$datetime_warnings[] = array(
												'record' => $records[$key][$table_pk]['new'],
												'field_name' => $fieldname,
												'value' => $newValue,
												'message' => $lang['data_import_tool_295'].' ' . $year
											);
										}
										$records[$key][$fieldname]['new'] = $newValue = sprintf("%04d-%02d-%02d", $year, $month, $day);
									} else {
										// Make sure has correct amount of digits with proper leading zeros
										$records[$key][$fieldname]['new'] = $newValue = clean_date_ymd($newValue);
									}
								}
							}
							// Ensure all times are in correct format (hh:mm)
							elseif (isset($metaData[$fieldname]) && $metaData[$fieldname]['element_validation_type'] == 'time' && strpos($records[$key][$fieldname]['new'],":") !== false)
							{
								if (strlen($newValue) < 5) {
									$records[$key][$fieldname]['new'] = $newValue = "0".$newValue;
								}
							}
							// Vanderbilt MRN: Remove any non-numerical characters. Add leading zeros, if needed.
							elseif (isset($metaData[$fieldname]) && $metaData[$fieldname]['element_validation_type'] == 'vmrn')
							{
								if ($newValue != "") $newValue = sprintf("%09d", preg_replace("/[^0-9]/", "", $newValue));
								$records[$key][$fieldname]['new'] = $newValue;
							}
							// Phone: Remove any unneeded characters
							elseif (isset($metaData[$fieldname]) && $metaData[$fieldname]['element_validation_type'] == 'phone')
							{
								$tempVal = str_replace(array(".","(",")"," "), array("","","",""), $newValue);
								if (strlen($tempVal) >= 10 && is_numeric(substr($tempVal, 0, 10))) {
									// Now add our own formatting
									$records[$key][$fieldname]['new'] = $newValue = trim("(" . substr($tempVal, 0, 3) . ") " . substr($tempVal, 3, 3) . "-" . substr($tempVal, 6, 4) . " " . substr($tempVal, 10));
								}
							}
						
						}

						# determine the action to take with the data
						if ($oldValue == "" && $newValue != "")
						{
							# if the old value is blank but the new value isn't blank, then this is a new value being imported
							if ($isOldValueBlank)
								$records[$key][$fieldname]['status'] = 'update';
							else
								$records[$key][$fieldname]['status'] = 'add';
						}
						elseif ($oldValue != "" && $newValue == "")
						{
							# if the import action is 'overwrite' and the new value is blank, update the data
							if ($overwriteBehavior == "overwrite")
								$records[$key][$fieldname]['status'] = 'update';
							else
								$records[$key][$fieldname]['status'] = 'keep';
						}
						elseif ($newValue."" === $oldValue."")
						{
							# if the new value equals the old value, then nothing is changed
							$records[$key][$fieldname]['status'] = 'keep';
						}
						else
						{
							$records[$key][$fieldname]['status'] = 'update';
						}
					}
					else
					{
						# do nothing -- there are no values for this field in the import data
						$records[$key][$fieldname]['status'] = 'keep';
					}

					// Make sure event old/new values are same (slight bookkeeping discrepancy)
					if ($longitudinal && $fieldname == "redcap_event_name" && $data['new'] != "") {
						$records[$key][$fieldname]['old'] = $data['new'];
						if ($records[$key][$table_pk]['old'] == "") {
							$records[$key][$fieldname]['status'] = 'add';
						}
					}
				}
			}
			unset($record3);

			## VALIDATE DATA: Perform validation against the metadata on new and updated data fields
			$errors = $warnings = 0;

			## LOCKING CHECK: Get all forms that are locked for the uploaded records
			$Locking = new Locking();
			if (!$addingAutoNumberedRecords) {
				$Locking->findLocked($Proj, $record_names, $fullFieldList, ($longitudinal ? $events : $Proj->firstEventId));
				$Locking->findLockedWholeRecord($project_id, $record_names);
			}
			
			// Obtain an array of all Validation Types (from db table)
			$valTypes = getValTypes();

			// Force all dates to be validated in YYYY-MM-DD format (any that were imported as M/D/Y will have been reformatted to YYYY-MM-DD)
			foreach ($metaData as $fieldname=>$fieldattr)
			{
				$metaData[$fieldname]['element_validation_type'] = convertLegacyValidationType(convertDateValidtionToYMD($fieldattr['element_validation_type']));
			}

			// Is randomization enabled and setup?
			$randomizationIsSetUp = Randomization::setupStatus($project_id);
			if ($randomizationIsSetUp)
			{
				$randomizationCriteriaFields = Randomization::getRandomizationFields(true, false, $project_id, $longitudinal);
				$randTargetField = array_shift($randomizationCriteriaFields);
				if ($longitudinal) {
					$randTargetEvent = array_shift($randomizationCriteriaFields);
				} else {
					$randTargetEvent = $event_id = $Proj->firstEventId;
				}
				$randCritFieldsEvents = array();
				while (!empty($randomizationCriteriaFields)) {
					$field = array_shift($randomizationCriteriaFields);
					if ($longitudinal) {
						$event_id = array_shift($randomizationCriteriaFields);
					}
					$randCritFieldsEvents[$field] = $event_id;
				}
			}

			// Create array of records that are survey responses (either partial or completed)
			$responses = $responsesCompleted = array();
			if (!empty($records) && !empty($Proj->surveys) && !$addingAutoNumberedRecords) {
				$sql = "select r.record, p.event_id, p.survey_id, r.instance, r.completion_time
						from redcap_surveys_participants p, redcap_surveys_response r
						where p.survey_id in (".prep_implode(array_keys($Proj->surveys)).") and p.participant_id = r.participant_id
						and r.record in (".prep_implode($record_names).") and r.first_submit_time is not null";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					// Add record-event_id-survey_id to array
					$responses[$row['record']][$row['event_id']][$row['survey_id']][$row['instance']] = true;
					// If response is completed
					if ($row['completion_time'] != '') {
						$responsesCompleted[$row['record']][$row['event_id']][$row['survey_id']][$row['instance']] = true;
					}
				}
			}

			// If using SECONDARY UNIQUE FIELD, then check for any duplicate values in imported data
			$checkSecondaryPk = ($secondary_pk != '' && isset($metaData[$secondary_pk]));


			// MATRIX RANKING CHECK: Give error if 2 fields in a ranked matrix have the same value
			$fields_in_ranked_matrix = $fields_in_ranked_matrix_all = $saved_matrix_data_preformatted = $matrixes_in_upload = array();
			if (!empty($Proj->matrixGroupHasRanking))
			{
				// Loop through all ranked matrixes and add to array
				foreach (array_keys($Proj->matrixGroupHasRanking) as $this_ranked_matrix) {
					// Loop through each field in each matrix group
					foreach ($Proj->matrixGroupNames[$this_ranked_matrix] as $this_field) {
						// If fields is in this upload file, add its matrix group name to array
						if (isset($metaData[$this_field])) {
							$matrixes_in_upload[] = $this_ranked_matrix;
						}
					}
				}
				// Make unique
				$matrixes_in_upload = array_unique($matrixes_in_upload);
				// Add all fields from matrixes in this upload
				if (!empty($matrixes_in_upload)) {
					foreach ($matrixes_in_upload as $this_ranked_matrix) {
						// Add to array
						$fields_in_ranked_matrix[$this_ranked_matrix] = $Proj->matrixGroupNames[$this_ranked_matrix];
						$fields_in_ranked_matrix_all = array_merge($fields_in_ranked_matrix_all, $Proj->matrixGroupNames[$this_ranked_matrix]);
					}
					// Now go get all data for these matrix fields for the records being uploaded
					$saved_matrix_data_preformatted = Records::getData($project_id, 'array', $record_names, $fields_in_ranked_matrix_all);
				}
			}

			// PROMIS: Create array of all fields that belong to a PROMIS CAT assessment downloaded from the Shared Library
			$promis_fields = array();
			foreach (PROMIS::getPromisInstruments($project_id) as $this_form) {
				$promis_fields = array_merge($promis_fields, array_keys($Proj->forms[$this_form]['fields']));
			}
			$promis_fields = array_fill_keys($promis_fields, true);
			
			// Loop through all records (If we're creating a project via ODM/XML file, then we'll allow some errors because we trust it)
			if (!defined("CREATE_PROJECT_ODM")) 
			{
				foreach ($records as $key => &$record)
				{
					// Get real record name (because $key will not truly be record name for longitudinal)
					$this_record_name = $record[$table_pk]['new'];
					// Get the repeat instance
					list ($nothing1, $nothing2, $repeat_instrument, $repeat_instance) = explode_right("-", $key, 4);
					if ($repeat_instance == '') $repeat_instance = 1;
					// Set a $repeat_instance type that is always an integer and never blank (because $repeat_instance may get set to blank later)
					$repeat_instance_int = $repeat_instance;

					// Retrieve the current event_id (used for Locking)
					if ($longitudinal) {
						$this_event_id = $events[$record['redcap_event_name']['new']];
					} else {
						$this_event_id = isset($this_event_id) ? $this_event_id : $Proj->firstEventId;
					}

					// RECORD-LEVEL LOCKING CHECK: Ensure that this record is not wholly locked
					$this_arm_id = $Proj->eventInfo[$this_event_id]['arm_id'];
					if (isset($Locking->lockedWhole[$record[$table_pk]['new']][$this_arm_id]))
					{
						if ($removeLockedFields) {
							unset($records[$key]);
						} else {
							$records[$key][$fieldname]['validation'] = 'error';
							$records[$key][$fieldname]['message'] = $lang['data_import_tool_288']." ".$lang['data_import_tool_289'];
							$errors++;
						}
						continue;
					}
						
					// Is this row of data a repeating form or event?
					$repeat_instance = isset($records[$key]['redcap_repeat_instance']) ? $records[$key]['redcap_repeat_instance']['new'] : "";
					$repeat_instrument = isset($records[$key]['redcap_repeat_instrument']) ? $records[$key]['redcap_repeat_instrument']['new'] : "";
					
					foreach ($record as $fieldname => $data)
					{
						// If $removeLockedFields flag is set, then remove any locked values
						if ($removeLockedFields && $fieldname != $Proj->table_pk && isset($Locking->locked[$this_record_name][$this_event_id][$repeat_instance_int][$fieldname])) {
							unset($records[$key][$fieldname]);
							continue;
						}
						
						// Set defaults for repeating instruments/events
						$isRepeatEventOrForm = $isRepeatEvent = $isRepeatForm = false;
						if ($hasRepeatingFormsEvents) {
							$isRepeatEvent = $Proj->isRepeatingEvent($this_event_id);
							$isRepeatForm  = $isRepeatEvent ? false : ($repeat_instrument != "" && $Proj->isRepeatingForm($this_event_id, $repeat_instrument));
							$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);							
						}

						// VALIDATION OF REPEATING INSTRUMENT NAME AND INSTANCE NUMBER (if applicable)
						if ($hasRepeatingFormsEvents && ($fieldname == 'redcap_repeat_instrument' || $fieldname == 'redcap_repeat_instance'))
						{
							// ERROR if passing a value for repeat_instrument for a repeating event
							if ($fieldname == 'redcap_repeat_instrument' && $isRepeatEvent && $repeat_instrument != "") {
								$records[$key][$fieldname]['message'] = isset($records[$key][$fieldname]['validation']) ? $records[$key][$fieldname]['message']." ".$lang['data_import_tool_258']." " : "";
								if (!isset($records[$key][$fieldname]['validation'])) { $records[$key][$fieldname]['validation'] = 'error'; $errors++; }
								$records[$key][$fieldname]['message'] .= "(redcap_event_name=\"{$record['redcap_event_name']['new']}\") $fieldname {$lang['data_import_tool_255']}";
							}
							// ERROR if passing a value for repeat_instrument when redcap_repeat_instance is blank
							if ($fieldname == 'redcap_repeat_instance' && ($repeat_instrument != "" || $repeat_instance != "") &&
								(!isinteger($repeat_instance) || $repeat_instance < 1 || $repeat_instance > 9999))
							{
								$records[$key][$fieldname]['message'] = isset($records[$key][$fieldname]['validation']) ? $records[$key][$fieldname]['message']." ".$lang['data_import_tool_258']." " : "";
								if (!isset($records[$key][$fieldname]['validation'])) { $records[$key][$fieldname]['validation'] = 'error'; $errors++; }
								$records[$key][$fieldname]['message'] .= "(" . ($longitudinal ? "redcap_event_name=\"{$record['redcap_event_name']['new']}\"" : "redcap_repeat_instrument=\"$repeat_instrument\"");
								$records[$key][$fieldname]['message'] .= ") $fieldname {$lang['data_import_tool_286']}";
							}
							// ERROR if passing an invalid redcap_repeat_instrument
							if ($fieldname == 'redcap_repeat_instrument' && !$isRepeatEvent && !$isRepeatForm && $records[$key][$fieldname]['new'] != "") {
								$records[$key][$fieldname]['message'] = isset($records[$key][$fieldname]['validation']) ? $records[$key][$fieldname]['message']." ".$lang['data_import_tool_258']." " : "";
								if (!isset($records[$key][$fieldname]['validation'])) { $records[$key][$fieldname]['validation'] = 'error'; $errors++; }
								$records[$key][$fieldname]['message'] .= "$fieldname {$lang['data_import_tool_259']}";
							}
                            // ERROR if passing a value for redcap_repeat_instance when this is not a repeating instrument or event
                            if ($overwriteBehavior != "overwrite" && $fieldname == 'redcap_repeat_instance' && !$isRepeatEvent && $records[$key][$fieldname]['new'] != "") {
                                // Determine if a repeating form (this is tricky because we're not currently on a real field for the instance pseudo-field
                                $isRepeatFormReally = false;
                                foreach (array_keys($records[$key]) as $thisInstanceField) {
                                	if ($records[$key]['redcap_repeat_instrument']['new'] == '') break;
                                    if (in_array($thisInstanceField, array($Proj->table_pk, 'redcap_repeat_instance', 'redcap_repeat_instrument'))) continue;
                                    if (!isset($Proj->metadata[$thisInstanceField])) {
                                        $true_fieldname = $Proj->getTrueVariableName($thisInstanceField);
                                        if ($true_fieldname !== false) $thisInstanceField = $true_fieldname;
									}
                                    if (!isset($Proj->metadata[$thisInstanceField])) continue;
									if (!$Proj->isRepeatingForm($this_event_id, $Proj->metadata[$thisInstanceField]['form_name'])) continue;
									if ($records[$key][$thisInstanceField]['new'] == '' && $Proj->metadata[$thisInstanceField]['form_name'] != $records[$key]['redcap_repeat_instrument']['new']) continue;
                                    $isRepeatFormReally = true;
                                    break;
                                }
                                // If not a repeating form, then give error
                                if (!$isRepeatFormReally) {
                                    $records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_281']}";
                                    if (!isset($records[$key][$fieldname]['validation'])) { $records[$key][$fieldname]['validation'] = 'error'; $errors++; }
                                }
                            }
						}

						//if the field contains new or updated data, then check it against the metadata
						if($records[$key][$fieldname]['status'] == 'add' || $records[$key][$fieldname]['status'] == 'update')
						{
							$newValue = $records[$key][$fieldname]['new'];
							$oldValue = $records[$key][$fieldname]['old'];

							// Record name cannot be empty
							if (trim($key) == "") throw new Exception("Record name is blank");

							// PREVENT SURVEY COMPLETE STATUS MODIFICATION
							// If this is a form status field for a survey response, then prevent from modifying it
							$true_fieldname = $Proj->getTrueVariableName($fieldname);
							$fieldForm = $true_fieldname ? $Proj->metadata[$true_fieldname]['form_name'] : "";
							if ($fieldname == $fieldForm."_complete" && isset($Proj->forms[$fieldForm]['survey_id'])
								&& isset($responses[$this_record_name][$this_event_id][$Proj->forms[$fieldForm]['survey_id']])) 
							{
								// Get repeat instance number to check further
								if (isset($responses[$this_record_name][$this_event_id][$Proj->forms[$fieldForm]['survey_id']][$repeat_instance])) {
									$records[$key][$fieldname]['validation'] = 'error';
									$records[$key][$fieldname]['message'] = js_escape2($lang['survey_403']);
									$errors++;
								}
							}

							// Does this survey have the e-Consent Framework enabled? If so, and if the survey is complete, then prevent users from editing
							$repeat_instance_num = ($repeat_instance == '') ? '1' : $repeat_instance;
							if ($fieldname != $Proj->table_pk && isset($Proj->forms[$fieldForm]['survey_id'])
								&& isset($responsesCompleted[$this_record_name][$this_event_id][$Proj->forms[$fieldForm]['survey_id']][$repeat_instance_num])
								&& $Proj->surveys[$Proj->forms[$fieldForm]['survey_id']]['pdf_auto_archive'] == '2'
							) {
								$records[$key][$fieldname]['validation'] = 'error';
								$records[$key][$fieldname]['message'] = $lang['data_import_tool_278'];
								$errors++;
							}

							// LOCKING CHECK: Ensure that this field's form is not locked. If so, then give error and force user to unlock form before proceeding.
							// Assumes that the $removeLockedFields flag is not set
							if ($fieldname != $Proj->table_pk && isset($Locking->locked[$record[$table_pk]['new']][$this_event_id][$repeat_instance_int][$fieldname]))
							{
								$records[$key][$fieldname]['validation'] = 'error';
								$records[$key][$fieldname]['message'] = $lang['data_import_tool_221'];
								$errors++;
							}

							// Skip this field if a CALC field (will perform auto-calculation after save)
							if ($skipCalcFields && $Proj->metadata[$fieldname]['element_type'] == "calc") {
								// If returning warnings, then add this
								$records[$key][$fieldname]['validation'] = 'warning';
								$records[$key][$fieldname]['message'] = "(calc) " . $lang['data_import_tool_197'];
								$warnings++;
								// Stop processing this one and skip to next field
								continue;
							}
							
							// If a field is on a repeating form but no value is provided for redcap_repeat_instrument, then give error
							if ($hasRepeatingFormsEvents && isset($records[$key]['redcap_repeat_instrument']['new']) 
								&& $records[$key]['redcap_repeat_instrument']['new'] == ''
								&& $Proj->isRepeatingForm($this_event_id, $Proj->metadata[$fieldname]['form_name'])) 
							{
								$records[$key][$fieldname]['validation'] = 'error';
								$records[$key][$fieldname]['message'] = $lang['data_import_tool_274'];
								$errors++;
							}

							if ($metaData[$fieldname]['element_type'] == 'text' && $metaData[$fieldname]['element_validation_type'] != '')
							{
								if ($newValue != "" && !isset($missingDataCodes[$newValue]))
								{
									## Use RegEx to evaluate the value based upon validation type
									// Set regex pattern to use for this field
									$regex_pattern = $valTypes[$metaData[$fieldname]['element_validation_type']]['regex_php'];
									// Run the value through the regex pattern
									preg_match($regex_pattern, $newValue, $regex_matches);
									// Was it validated? (If so, will have a value in 0 key in array returned.)
									$failed_regex = (!isset($regex_matches[0]));
									// Set error message if failed regex
									if ($failed_regex)
									{
										$records[$key][$fieldname]['validation'] = 'error';
										$errors++;
										// Validate the value based upon validation type
										switch ($metaData[$fieldname]['element_validation_type'])
										{
											case "int":
												$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_83']} $fieldname {$lang['data_import_tool_84']}";
												break;
											case "float":
												$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_83']} $fieldname {$lang['data_import_tool_85']}";
												break;
											case "phone":
												$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_86']}";
												break;
											case "email":
												$records[$key][$fieldname]['message'] = $lang['data_import_tool_87'];
												break;
											case "vmrn":
												$records[$key][$fieldname]['message'] = $lang['data_import_tool_138'];
												break;
											case "zipcode":
												$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_153']}";
												break;
											case "date":
											case "date_ymd":
											case "date_mdy":
											case "date_dmy":
												if ($dateFormat == 'MDY') {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_238'];
												} elseif ($dateFormat == 'DMY') {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_239'];
												} else {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_190'];
												}
												break;
											case "time":
												$records[$key][$fieldname]['message'] = $lang['data_import_tool_137'];
												break;
											case "datetime":
											case "datetime_ymd":
											case "datetime_mdy":
											case "datetime_dmy":
											case "datetime_seconds":
											case "datetime_seconds_ymd":
											case "datetime_seconds_mdy":
											case "datetime_seconds_dmy":
												if ($dateFormat == 'MDY') {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_194'];
												} elseif ($dateFormat == 'DMY') {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_195'];
												} else {
													$records[$key][$fieldname]['message'] = $lang['data_import_tool_193'];
												}
												break;
											default:
												// General regex failure message for any new, non-legacy validation types (e.g., postalcode_canada)
												$records[$key][$fieldname]['message'] = $lang['config_functions_77'];
										}
									}
								}
							} //end if for having validation

							# If value is an enum, check that it's valid
							if ($metaData[$fieldname]['element_type'] != 'slider' && isset($metaData[$fieldname]['element_enum']) && $metaData[$fieldname]['element_enum'] != ""  && !isset($missingDataCodes[$newValue]))
							{
								// Make sure the raw value is a coded value in the enum
								if (!isset($metaData[$fieldname]["enums"][$newValue]) && $metaData[$fieldname]['element_type'] != "calc")
								{
									if ($overwriteBehavior == "overwrite" && $newValue == "") {
										# do nothing (inserting a blank value is fine)
									}
									elseif ($metaData[$fieldname]['element_type'] == 'text' && $metaData[$fieldname]['element_enum'] != ''
											&& count($metaData[$fieldname]["enums"]) == 1  && strpos($metaData[$fieldname]['element_enum'], ":") !== false) {
										// This is an auto-suggest web service (e.g., BioPortal), so return an error that data cannot be imported for it.
										if ($newValue != "") { // Don't give error if blank value
											$records[$key][$fieldname]['validation'] = 'error';
											$records[$key][$fieldname]['message'] = $lang['data_import_tool_232'];
											$errors++;
										}
									}
									else {
										// Value is not a valid category for this multiple choice field
										$records[$key][$fieldname]['validation'] = 'error';
										$records[$key][$fieldname]['message'] = $lang['data_import_tool_222']." $fieldname";
										$errors++;
									}
								}
							}

							# Check that value is within range specified in metadata (max/min), if a range is given.
							if ( isset($metaData[$fieldname]['element_validation_min']) || isset($metaData[$fieldname]['element_validation_max'])  && !isset($missingDataCodes[$newValue]))
							{
								$elementValidationMin = $metaData[$fieldname]['element_validation_min'];
								$elementValidationMax = $metaData[$fieldname]['element_validation_max'];

								//if lower bound is specified
								if ($elementValidationMin !== "" && $elementValidationMin !== null)
								{
									$newValueComp = $newValue;
									$elementValidationMinComp = $elementValidationMin;
									// If field is a number with commas for decimals (European style), then replace with real decimal when comparing
									if ($valTypes[$metaData[$fieldname]['element_validation_type']]['data_type'] == 'number_comma_decimal') {
										$newValueComp = str_replace(",", ".", $newValueComp);
										$elementValidationMinComp = str_replace(",", ".", $elementValidationMinComp);
									}
									//if new value is smaller than lower bound
									if ($newValueComp < $elementValidationMinComp)
									{
										//if hard check
										if ($metaData[$fieldname]['element_validation_checktype'] == 'hard')
										{
											$records[$key][$fieldname]['validation'] = 'error';
											$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_223']} ($elementValidationMin)";
											$errors++;
										}
										//if not hard check
										elseif ($records[$key][$fieldname]['validation'] != 'error')
										{
											$records[$key][$fieldname]['validation'] = 'warning';
											$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_224']} ($elementValidationMin)";
											$warnings++;
										}
									}
								}

								//if upper bound is specified
								if ($elementValidationMax !== "" && $elementValidationMax !== null)
								{
									$newValueComp = $newValue;
									$elementValidationMaxComp = $elementValidationMax;
									// If field is a number with commas for decimals (European style), then replace with real decimal when comparing
									if ($valTypes[$metaData[$fieldname]['element_validation_type']]['data_type'] == 'number_comma_decimal') {
										$newValueComp = str_replace(",", ".", $newValueComp);
										$elementValidationMaxComp = str_replace(",", ".", $elementValidationMaxComp);
									}
									//if new value is greater than upper bound
									if ($newValueComp > $elementValidationMaxComp)
									{
										//if hard check
										if ($metaData[$fieldname]['element_validation_checktype'] == 'hard')
										{
											$records[$key][$fieldname]['validation'] = 'error';
											$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_225']} ($elementValidationMax)";
											$errors++;
										}
										//if not hard check
										elseif ($records[$key][$fieldname]['validation'] != 'error')
										{
											$records[$key][$fieldname]['validation'] = 'warning';
											$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_226']} ($elementValidationMax)";
											$warnings++;
										}
									}
								}
							} //end if for range

							// If field is a checkbox, make sure value is either 0 or 1
							if (isset($fullCheckboxFields[$fieldname]) && $newValue != "1" && $newValue != "0")
							{
								$records[$key][$fieldname]['validation'] = 'error';
								$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_227']}";
								$errors++;
							}

							// If using SECONDARY UNIQUE FIELD, then check for any duplicate values in imported data
							if ($checkSecondaryPk && $secondary_pk == $fieldname)
							{
								// Check for any duplicated values for the $secondary_pk field (exclude current record name when counting)
								$sql = "select 1 from redcap_data where project_id = $project_id and field_name = '$secondary_pk'
										and value = '" . db_escape($newValue) . "' and record != '" . db_escape($this_record_name) . "' limit 1";
								$q = db_query($sql);
								$uniqueValueExists = (db_num_rows($q) > 0);
								// If the value already exists for a record, then throw an error
								if ($uniqueValueExists)
								{
									$errors++;
									$records[$key][$fieldname]['validation'] = 'error';
									$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_154']} (i.e. \"$secondary_pk\"){$lang['period']} {$lang['data_import_tool_155']}";
								}
							}

							// PROMIS Assessment: If field belongs to a PROMIS CAT, do NOT allow user to import data for it
							if (!$bypassPromisCheck && isset($promis_fields[$fieldname]) && $newValue != "")
							{
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = "$fieldname {$lang['data_import_tool_196']}";
							}
							
							// REPEATING FORM: If field does not exist on repeating form but has a value, then display error
							if ($hasRepeatingFormsEvents && $repeat_instrument != "" && $repeat_instance != ""
								&& !in_array($fieldname, array($table_pk, 'redcap_repeat_instrument', 'redcap_repeat_instance', 'redcap_event_name', 'redcap_data_access_group'))
								&& $repeat_instrument != $fieldForm) 
							{				
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_162']} ('$fieldname') {$lang['data_import_tool_260']} '$repeat_instrument'{$lang['period']} {$lang['data_import_tool_261']}";
							}							
							// NON-REPEATING FORM: If field exists on repeating form but this row is non-repeating, then display error
							elseif ($hasRepeatingFormsEvents && ($repeat_instrument == "" || $repeat_instance == "")
								&& !in_array($fieldname, array($table_pk, 'redcap_repeat_instrument', 'redcap_repeat_instance', 'redcap_event_name', 'redcap_data_access_group'))
								&& $Proj->isRepeatingForm($this_event_id, $fieldForm))
							{				
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_162']} ('$fieldname') {$lang['data_import_tool_262']} '{$Proj->metadata[$fieldname]['form_name']}'{$lang['period']} {$lang['data_import_tool_263']}";
							}

						} //end if for status check

						// RANDOMIZATION CHECK: Make sure that users cannnot import data into a randomiztion field OR into a criteria field
						// if the record has already been randomized
						if ($randomizationIsSetUp)
						{
							// Check if this is target randomization field, which CANNOT be edited. If so, give error.
							if ($fieldname == $randTargetField)
							{
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_162']} ('$fieldname') {$lang['data_import_tool_161']}";
							}
							// Check if this is a criteria field AND is criteria event_id AND if the record has already been randomized
							elseif (isset($randCritFieldsEvents[$fieldname]) && $randCritFieldsEvents[$fieldname] == $this_event_id
								&& $records[$key][$fieldname]['new'] != "" && Randomization::wasRecordRandomized($this_record_name))
							{
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = $Proj->table_pk_label." '$this_record_name' {$lang['data_import_tool_163']} ('$fieldname'){$lang['data_import_tool_164']}";
							}
						}

						// Field-Event Mapping Check: Make sure this field exists on a form that is designated to THIS event. If not, then error.
						if ($longitudinal && is_numeric($this_event_id) && $fieldname != 'redcap_event_name' && $fieldname != 'redcap_data_access_group' && $records[$key][$fieldname]['new'] != "" && $fieldname != $Proj->table_pk)
						{
							// Check fieldname (in case a modified checkbox fieldname)
							$true_fieldname = $chkbox_fieldname = $fieldname; // Begin with default	
							$bypass_form_event_designation = false;
							if (!isset($Proj->metadata[$fieldname]) && strpos($fieldname, '___') !== false)
							{
								// Checkbox pattern
								$re = "/(.*[^_])___(.*)/";
								preg_match($re, $fieldname, $matches);
								$chkbox_fieldname = isset($matches[1]) ? $matches[1] : $fieldname;								
								$this_code = isset($matches[2]) ? $matches[2] : null;
								// list ($chkbox_fieldname, $this_code) = explode('___', $fieldname);
								if (isset($Proj->metadata[$chkbox_fieldname]) && $Proj->metadata[$chkbox_fieldname]['element_type'] == 'checkbox') {
									// It is a checkbox, so set true fieldname
									$true_fieldname = $chkbox_fieldname;
									// If overwriteBehavior = overwrite, then check if this was an empty value that got set to 0 by default. 
									// If so, bypass the form-event designation check since there is no data being imported here.
									if ($overwriteBehavior == "overwrite" && $records[$key][$fieldname]['old'] == "0" && $records[$key][$fieldname]['status'] == 'keep') {
										$bypass_form_event_designation = true;
									}
								}
							}							
							// Now check form-event designation
							if (!$bypass_form_event_designation && !in_array($Proj->metadata[$true_fieldname]['form_name'], $Proj->eventsForms[$this_event_id])
								&& !in_array($fieldname, $extra_reserved_field_names) && !isset(Project::$reserved_field_names[$fieldname])) 
							{
								$records[$key][$fieldname]['validation'] = 'error'; $errors++;
								$records[$key][$fieldname]['message'] = "{$lang['data_import_tool_162']} ('$fieldname') {$lang['data_import_tool_165']} '{$Proj->eventInfo[$this_event_id]['name_ext']}'{$lang['period']} {$lang['data_import_tool_166']}";
							}
						}

					} //end foreach

					// MATRIX RANKING CHECK: Give error if 2 fields in a ranked matrix have the same value
					if (!empty($fields_in_ranked_matrix))
					{
						// Loop through ranked matrix fields and overlay values being imported (ignoring blank values)
						foreach ($fields_in_ranked_matrix as $this_ranked_matrix=>$matrix_fields) 
						{
							// Get already saved values for ranked matrix fields
							$this_record_saved_matrix_data_preformatted = $saved_matrix_data_preformatted[$this_record_name][$this_event_id];
							// Remove the non-relevant fields for this matrix
							foreach ($this_record_saved_matrix_data_preformatted as $mkey=>$this_record_saved_field) {
								if (!in_array($this_record_saved_field, $matrix_fields)) {
									unset($this_record_saved_matrix_data_preformatted[$mkey]);
								}
							}
							// Through though this matrix's fields
							foreach ($matrix_fields as $this_matrix_field) {
								// If in data being imported, add on top
								if (isset($records[$key][$this_matrix_field])
									&& $records[$key][$this_matrix_field]['new'] != '')
								{
									$this_record_saved_matrix_data_preformatted[$this_matrix_field]
										= $records[$key][$this_matrix_field]['new'];
								}
								// If not in array yet and not being imported, set with default blank value
								elseif (!isset($this_record_saved_matrix_data_preformatted[$this_matrix_field])) {
									$this_record_saved_matrix_data_preformatted[$this_matrix_field] = '';
								}
							}
							// If any value is duplicated within the matrix, then report an error
							if (count($this_record_saved_matrix_data_preformatted) != count(array_unique($this_record_saved_matrix_data_preformatted))) {
								// Loop through all duplicated fields and add error (if the field doesn't already have an error )
								$matrix_count_values = array_count_values($this_record_saved_matrix_data_preformatted);
								foreach ($this_record_saved_matrix_data_preformatted as $this_matrix_field=>$matrix_value) {
									// If not a duplicate or is a blank value, then ignore it
									if ($records[$key][$this_matrix_field]['new'] == '' || $matrix_count_values[$matrix_value] < 2) continue;
									// If field already has an error for it, then ignore it (for now until the original error is removed in next upload)
									if (isset($records[$key][$this_matrix_field]['validation']) && $records[$key][$this_matrix_field]['validation'] == 'error') continue;
									// Add error
									$records[$key][$this_matrix_field]['validation'] = 'error'; $errors++;
									$records[$key][$this_matrix_field]['message'] = "{$lang['data_import_tool_162']} (\"<b>$this_matrix_field</b>\") {$lang['data_import_tool_185']}";
								}
							}
						}
					}
				} //end foreach
			}
			unset($record, $data, $responses, $responsesCompleted);

			# if there were any warnings and we're set to return warning, add to array to return
			$warnings_array = array();
			if ($warnings > 0)
			{
				foreach ($records as $key => $record)
				{
					foreach ($record as $fieldname => $data)
					{
						if (isset($records[$key][$fieldname]['validation']) && $records[$key][$fieldname]['validation'] == 'warning')
						{
							list ($this_record, $nothing, $nothing, $nothing) = explode_right("-", $key, 4);
							$message  = '"' . str_replace('"', '""', $this_record) . '"' . ",\"$fieldname\",";
							$message .= '"' . str_replace('"', '""', $records[$key][$fieldname]['new']) . '",';
							$message .= '"' . strip_tags(str_replace(array("\r\n","\n","\t",'"'), array(" "," "," ", '""'), $records[$key][$fieldname]['message'])) . "\"";
							// Add message to array
							$warnings_array[] = $message;
						}
					}
				}
			}

			# add warnings from pre-processing steps for datetime fields
			foreach ($datetime_warnings as $w) {
				$warnings_array[] = implode(",",$w);
			}

			# if there were any errors, out them and end the process
			if ($errors > 0)
			{
				$errors_array = array();

				foreach ($records as $key => $record)
				{
					foreach ($record as $fieldname => $data)
					{
						list ($this_record, $nothing, $nothing, $nothing) = explode_right("-", $key, 4);
							
						if (isset($records[$key][$fieldname]['validation']) && $records[$key][$fieldname]['validation'] == 'error')
						{
							$message = '"' . str_replace('"', '""', $this_record) . '"' . ",\"$fieldname\",";
							$message .= '"' . str_replace('"', '""', $records[$key][$fieldname]['new']) . '",';
							$message .= '"' . strip_tags(str_replace(array("\r\n","\n","\t",'"'), array(" "," "," ", '""'), $records[$key][$fieldname]['message'])) . "\"";
							$errors_array[] = $message;
						}

						// Field name does not exist in database
						if ( !in_array($fieldname, $metadataFields) && !in_array($fieldname, array('redcap_event_name', 'redcap_data_access_group', 'redcap_repeat_instrument', 'redcap_repeat_instance', 'redcap_survey_identifier')))
						{
							$message = '"' . str_replace('"', '""', $this_record) . '"' . ",\"$fieldname\",";
							if ($records[$key][$fieldname]['new'] == "")
								$message .= ",";
							else
								$message .= '"' . str_replace('"', '""', $records[$key][$fieldname]['new']) . '",';
							if (isset($checkboxFields[$fieldname])) {
								$message .= '"'.str_replace('"', '""', $lang['data_import_tool_229']).'"';
							} else {
								$message .= '"'.str_replace('"', '""', $lang['data_import_tool_230']).'"';
							}
							$errors_array[] = $message;
						}
					}
				}
				// Serialize the array to pass many error msgs at once
				throw new Exception(serialize($errors_array));
			}

			// If not Longitudinal, get single event_id
			if (!$longitudinal)
			{
				$singleEventId = $Proj->firstEventId;
			}

			$counter = 0;

			## DDP: If using DDP and the source identifier field's value just changed, then purge that record's cached data
			## so it can be reobtained from the source system.
			$check_ddp_id_changed = false;
			if ($commitData && $realtime_webservice_global_enabled && $Proj->project['realtime_webservice_enabled']) {
				// Make sure DDP has been mapped in this project
				$DDP = new DynamicDataPull($project_id, $Proj->project['realtime_webservice_type']);
				if ($DDP->isMappingSetUp()) {
					// Get the DDP identifier field
					list ($ddp_id_field, $ddp_id_event) = $DDP->getMappedIdRedcapFieldEvent();
					$check_ddp_id_changed = true;
				}
			}

			## INSTANTIATE SURVEY INVITATION SCHEDULE LISTENER
			// If the form is designated as a survey, check if a survey schedule has been defined for this event.
			// If so, perform check to see if this record/participant is NOT a completed response and needs to be scheduled for the mailer.
			$surveyScheduler = new SurveyScheduler($project_id);

			// Create array to place record-events to be assigned to a DAG
			$dagRecordEvent = $dagIdsLogged = array();
			
			// Store the event/instrument/instance of any imported repeating instances into this array to ensure
			// that we have a Form Status value for each instance (because the UI is driven by the form status field).
			$repeatingFormsStatus = array();
			
			// Put translation of auto-numbered records in array
			$autoNumberedRecords = array();
			$alertsCheck = array();

			# import records into database
			foreach ($records as $key => &$record)
			{
				// Clear array values for this record
				$sql_all = array();
				$display = array();
			
				// Keep array of all fields being imported
				$importedFieldForms = array();

				// get id for record
				if (!isset($records[$key][$table_pk]['new'])) continue;
				$id = $records[$key][$table_pk]['new'];
				
				// get repeat instance number (set to NULL if is 1 because we store it as NULL in redcap_data)
				list ($nothing, $nothing, $nothing, $repeat_instance) = explode_right("-", $key, 4);
				if ($repeat_instance == '1') $repeat_instance = null;

				// If event name is the only field for the record, then nothing to do here
				if (empty($records[$key]) || ($longitudinal && isset($records[$key]['redcap_event_name']) && count($records[$key]) == 1)) {
					continue;
				}

				// Get event id for this record
				$thisEventId = ($longitudinal) ? $events[$record['redcap_event_name']['new']] : $singleEventId;
				
				// Loop through all values for this record
				foreach ($record as $fieldname => $data)
				{
					// If importing DAGs, collect their values in an array to perform DAG designation later
					if ($group_id == null && $hasDagField && $fieldname == 'redcap_data_access_group') {
						$dagRecordEvent[$record[$Proj->table_pk]['new']][$thisEventId] = $data['new'];
						continue;
					}

					// Ignore pseudo-fields
					if (in_array($fieldname, $extra_reserved_field_names) || isset(Project::$reserved_field_names[$fieldname])) {
						continue;
					}

					// Skip this field if a CALC field (will perform auto-calculation after save)
					if ($skipCalcFields && $Proj->metadata[$fieldname]['element_type'] == "calc") {
						continue;
					}

					if ($records[$key][$fieldname]['status'] != 'keep')
					{
						// CHECKBOXES
						if (isset($fullCheckboxFields[$fieldname]))
						{
							// Since only checked values are saved in data table, we must ONLY do either Inserts or Deletes. Reconfigure.
							if ($data['new'] == "1" && ($data['old'] == "0" || $data['old'] == ""))
							{
								// If changed from "0" to "1", change to Insert
								$records[$key][$fieldname]['status'] = 'add';
							}
							elseif ($data['new'] == "0" && $data['old'] == "1")
							{
								// If changed from "1" to "0", change to Delete
								$records[$key][$fieldname]['status'] = 'delete';
							}

							// Re-configure checkbox variable name and value
							list ($field, $data['new']) = explode("___", $fieldname, 2);
							// Since users can designate capital letters as checkbox codings AND because variable names force those codings to lower case,
							// we need to loop through this field's codings to find the matching coding for the converted value.
							foreach (array_keys($checkboxFields[$field]) as $this_code)
							{
								if (Project::getExtendedCheckboxCodeFormatted($this_code) == Project::getExtendedCheckboxCodeFormatted($data['new'])) {
									$data['new'] = $this_code;
								}
							}
						}
						// NON-CHECKBOXES
						else
						{
							// Regular fields keep same variable name
							$field = $fieldname;
						}
						
						// Add form to form list for fields modified/added (except calc fields)
						if ($Proj->metadata[$field]['element_type'] != 'calc') {
							$importedFieldForms[$Proj->metadata[$field]['form_name']] = 1;
						}

						if ($commitData) {
							// insert query
							if ($records[$key][$fieldname]['status'] == 'add') {
								$sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) "
									. "VALUES ($project_id, $thisEventId, '" . db_escape($id) . "', '" . db_escape($field) . "', "
									. "'" . db_escape($data['new']) . "', " . checkNull($repeat_instance) . ")";
							} // update query
							elseif ($records[$key][$fieldname]['status'] == 'update') {
								if ($data['new'] != '') {
									$sql_all[] = $sql = "UPDATE redcap_data SET value = '" . db_escape($data['new']) . "' WHERE project_id = $project_id "
										. "AND record = '" . db_escape($id) . "' AND field_name = '" . db_escape($field) . "' AND event_id = $thisEventId "
										. ($repeat_instance == null ? "AND instance is null" : "AND instance = '" . db_escape($repeat_instance) . "'");
								} else {
									$sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = $project_id AND record = '" . db_escape($id) . "' "
										. "AND field_name = '" . db_escape($field) . "' AND event_id = $thisEventId "
										. ($repeat_instance == null ? "AND instance is null" : "AND instance = '" . db_escape($repeat_instance) . "'");
								}
								// If the field is a File Upload field, then make sure that the old file gets marked for deletion
								if ($Proj->metadata[$fieldname]['element_type'] == 'file' && is_numeric($data['old'])) {
									$sql_all[] = $sqle = "UPDATE redcap_edocs_metadata SET delete_date = '" . NOW . "'
													  WHERE doc_id = '" . db_escape($data['old']) . "' AND project_id = $project_id";
									db_query($sqle);
								}
							} // delete query (only for checkboxes)
							elseif ($records[$key][$fieldname]['status'] == 'delete') {
								$sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = $project_id AND record = '" . db_escape($id) . "' "
									. "AND field_name = '" . db_escape($field) . "' AND event_id = $thisEventId AND value = '" . db_escape($data['new']) . "' "
									. ($repeat_instance == null ? "AND instance is null" : "AND instance = '" . db_escape($repeat_instance) . "'");
							}
							// Execute the query
							db_query($sql);
						}

						// Add to De-verify array
						if (!$addingAutoNumberedRecords) {
							$autoDeverify[$id][$thisEventId][$field] = true;
						}
						
						if (isset($fullCheckboxFields[$fieldname]))
						{
							// Checkbox logging display
							$display[] = "$field({$data['new']}) = " . (($records[$key][$fieldname]['status'] == 'add') ? "checked" : "unchecked");
						}
						else
						{
							// Logging display for normal fields
							$display[] = "$field = '{$data['new']}'";
						}

						## DDP: Did source identifier value change?
						if ($commitData && $check_ddp_id_changed && $thisEventId == $ddp_id_event && $fieldname == $ddp_id_field ) {
							$DDP->purgeDataCache($id);
						}
						
						// Counter increment
						$counter++;
					} // end if status check
				} //end inside foreach loop		
				unset($record);
				
				// If importing a repeated event/instrument instance, then make sure it has a form status value (the UI for repeat instances depends on this)
				if ($commitData && $hasRepeatingFormsEvents && !empty($sql_all))
				{
					list ($nothing, $nothing, $repeat_instrument, $repeat_instance) = explode_right("-", $key, 4);
					$isRepeatingEvent = $Proj->isRepeatingEvent($thisEventId);
					if ($repeat_instrument != '' || $isRepeatingEvent) 
					{
						if ($repeat_instance == '1') $repeat_instance = null;
						// Loop through each form
						foreach (array_keys($importedFieldForms) as $thisFieldForm)
						{
                            // Is it a repeating instrument or event?
							if (!$isRepeatingEvent && !$Proj->isRepeatingForm($thisEventId, $thisFieldForm)) continue;
                            // Add record ID value if missing
                            $sql = "SELECT * FROM redcap_data WHERE project_id = $project_id AND record = '".db_escape($id)."' "
                                . "AND field_name = '".db_escape($Proj->table_pk)."' AND event_id = $thisEventId "
                                . ($repeat_instance == null ? "AND instance is null" : "AND instance = '".db_escape($repeat_instance)."'");
                            $q = db_query($sql);
                            if (!db_num_rows($q)) {
                                // Add form status default value (0) for this repeating instrument/event
                                $sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) "
                                    . "VALUES ($project_id, $thisEventId, '".db_escape($id)."', '".db_escape($Proj->table_pk)."', "
                                    . "'".db_escape($id)."', ".checkNull($repeat_instance).")";
                                $q = db_query($sql);
                                Logging::logEvent($sql, "redcap_data", "update", $id, $Proj->table_pk." = '$id'",
                                    "Create record","","",$project_id,true, $thisEventId, $repeat_instance, true);
                            }
                            // Add form status value if missing
							$sql = "SELECT * FROM redcap_data WHERE project_id = $project_id AND record = '".db_escape($id)."' "
								  . "AND field_name = '".db_escape($thisFieldForm)."_complete' AND event_id = $thisEventId "
								  . ($repeat_instance == null ? "AND instance is null" : "AND instance = '".db_escape($repeat_instance)."'");
							$q = db_query($sql);
							if (!db_num_rows($q)) {
								// Add form status default value (0) for this repeating instrument/event
								$sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) "
												  . "VALUES ($project_id, $thisEventId, '".db_escape($id)."', '".db_escape($thisFieldForm)."_complete', "
												  . "'0', ".checkNull($repeat_instance).")";
								$q = db_query($sql);
                                Logging::logEvent($sql, "redcap_data", "update", $id, $thisFieldForm."_complete = '0'",
                                    "Update record","","",$project_id,true, $thisEventId, $repeat_instance, true);
							}
						}
					}
				}	

				# If user is in a Data Access Group, do insert query for Group ID number so that record will be tied to that group
				if ($commitData && $group_id != "")
				{
					// If record did not exist previously OR was not in a DAG previously, then add group_id value for it
					if (!in_array($id, $dagIds, true) || !isset($existingIdList[$id]))
					{
						// Add to data table
						$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) "
							 . "VALUES ($project_id, $thisEventId, '".db_escape($id)."', '__GROUPID__', '$group_id')";
						db_query($sql);
						// Log the DAG assignment
						if (!in_array($id, $dagIdsLogged, true)) {
							// Add to array
							$dagIdsLogged[] = $id;
							// Log it
							$dag_log_descrip  = "Assign record to Data Access Group";
							$dag_log_descrip .= (defined("PAGE") && PAGE == 'api/index.php' ? " (API)" : "");
							$group_name = $Proj->getUniqueGroupNames($group_id);
							$log_event_id = Logging::logEvent($sql, "redcap_data", "update", $id, "redcap_data_access_group = '$group_name'",
											 $dag_log_descrip,"","",$project_id,true,$thisEventId, null, true);
							// Update record list table
							self::updateRecordDagInRecordListCache($project_id, $id, $group_id);
						}
					}
				}

				// Logging - determine if we're updating an existing record or creating a new one
                $isNewRecord = false;
				if ($commitData && $dataLogging && !empty($sql_all))
				{
					$this_record_arm = $Proj->multiple_arms ? $Proj->eventInfo[$thisEventId]['arm_num'] : 1;
					if (isset($existingIdList[$id][$this_record_arm])) {
						$this_event_type  = "update";
						$this_log_descrip = "Update record";
					} else {
						$this_event_type  = "insert";
						$this_log_descrip = "Create record";
                        $isNewRecord = true;
						// Add id to existingIdList in case it has more rows (only creating it with first event)
						$existingIdList[$id][$this_record_arm] = true;
					}
					// Append note if we're doing an API import
					$this_log_descrip .= (defined("PAGE") && PAGE == 'api/index.php') ? " (API)" : "";
					// Append note if we're doing automatic calculations
					$this_log_descrip .= ($logAsAutoCalculations) ? " (Auto calculation)" : "";
					// Set the change reason (if applicable)
					$this_change_reason = isset($changeReasons[$id][$thisEventId]) ? $changeReasons[$id][$thisEventId] : "";
					// Log it
					$log_event_id = Logging::logEvent(implode(";\n", $sql_all), "redcap_data", $this_event_type, $id, implode(",\n", $display),
										$this_log_descrip, $this_change_reason, "", $project_id, true, $thisEventId, $repeat_instance, true);
				}

				// SURVEY INVITATION SCHEDULER: Return count of invitation scheduled, if any
				if ($commitData && !empty($Proj->surveys)) {
					$numInvitationsScheduled = $surveyScheduler->checkToScheduleParticipantInvitation($id, $isNewRecord);
				}

                // ALERTS
                if ($commitData) {
                    list ($nothing, $nothing, $eta_repeat_instrument, $eta_repeat_instance) = explode_right("-", $key, 4);
                    $alertsCheck[] = "$id-$eta_repeat_instrument-$thisEventId-$eta_repeat_instance";
                }

                // Seed the Twilio delivery preference (set for new records only)
                if ($commitData && $isNewRecord && $Proj->project['twilio_enabled'] && $Proj->twilio_enabled_surveys && $Proj->project['twilio_default_delivery_preference'] != 'EMAIL' && !empty($Proj->surveys))
                {
                    $surveyIds = array_keys($Proj->surveys);
                    $firstAvailableSurveyId = $surveyIds[0];
                    Survey::getFollowupSurveyParticipantIdHash($firstAvailableSurveyId, $id, $thisEventId, false, $repeat_instance);
                }

			} // end outside foreach loop

			if ($commitData) {
				// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
				Logging::setUserActivityTimestamp();
				// SET LAST ACTIVITY TIMESTAMP FOR PROJECT
				Logging::setProjectActivityTimestamp($project_id);
				// Now deal with record list caching updates that need to be processed
				self::processRecordListCacheQueue($project_id);
			}

			# If importing DAGs by user NOT in a DAG
			if ($commitData && $group_id == null && $hasDagField)
			{
				// Loop through each record-event and set DAG designation
				foreach ($dagRecordEvent as $record=>$eventdag)
				{
					// Set flag to log DAG designation
					$dag_sql_all = array();
					// Loop through each event in this record
					foreach ($eventdag as $event_id=>$group_name)
					{
						// Ignore if group name is blank UNLESS special flag is set
						if ($group_name == '' && $overwriteBehavior != 'overwrite') continue;
						// Delete existing values first
						if ($group_name == '' && $overwriteBehavior == 'overwrite') {
							// Clear out existing values for ALL EVENTS if group is blank AND overwrite behavior is "overwrite"
							$sql = $dag_sql_all[] = "DELETE FROM redcap_data WHERE project_id = $project_id AND record = '".db_escape($record)."' "
												  . "AND field_name = '__GROUPID__'";
						} else {
							// Clear out any existing values for THIS EVENT before adding this one
							$sql = $dag_sql_all[] = "DELETE FROM redcap_data WHERE project_id = $project_id AND record = '".db_escape($record)."'  "
												  . "AND field_name = '__GROUPID__' AND event_id = $event_id";
						}
						db_query($sql);
						// Add to data table if group_id not blank
						if ($group_name != '') {
							// Get group_id
							$group_id = array_search($group_name,  $Proj->getUniqueGroupNames());
							// Update ALL OTHER EVENTS to new group_id (if other events have group_id stored)
							$sql = $dag_sql_all[] = "UPDATE redcap_data SET value = '$group_id' WHERE project_id = $project_id  "
												  . "AND record = '".db_escape($record)."' AND field_name = '__GROUPID__'";
							db_query($sql);
							// Insert group_id for THIS EVENT
							$sql = $dag_sql_all[] = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) "
												  . "VALUES ($project_id, $event_id, '".db_escape($record)."', '__GROUPID__', '$group_id')";
							db_query($sql);
							// Update any calendar events tied to this group_id
							$sql = $dag_sql_all[] = "UPDATE redcap_events_calendar SET group_id = " . checkNull($group_id) . " "
												  . "WHERE project_id = $project_id AND record = '" . db_escape($record) . "'";
							db_query($sql);
						}
					}
					// Log DAG designation (if occurred)
					if ($dataLogging && is_array($records[$key]) && $records[$key]['redcap_data_access_group']['status'] != 'keep' && isset($dag_sql_all) && !empty($dag_sql_all))
					{
						$dag_log_descrip  = ($group_name == '') ? "Remove record from Data Access Group" : "Assign record to Data Access Group";
						$dag_log_descrip .= (defined("PAGE") && PAGE == 'api/index.php' ? " (API)" : "");
						$log_event_id = Logging::logEvent(implode(";\n",$dag_sql_all), "redcap_data", "update", $record, "redcap_data_access_group = '$group_name'", 
										$dag_log_descrip,"","",$project_id, true, null, null, true);
						// Update record list table
						self::updateRecordDagInRecordListCache($project_id, $record, $group_id);	
					}
				}
			}

            // ALERTS
            if ($commitData) {
                $eta = new Alerts();
                foreach ($alertsCheck as $key=>$val) {
                    list ($id, $eta_repeat_instrument, $thisEventId, $eta_repeat_instance) = explode_right("-", $val, 4);
                    unset($alertsCheck[$key]);
                    $eta->saveRecordAction($project_id, $id, $eta_repeat_instrument, $thisEventId, $eta_repeat_instance);
                }
            }

			## DATA RESOLUTION WORKFLOW: If enabled, deverify any record/event/fields that
			// are Verified but had their data value changed just now.
			if ($commitData && $Proj->project['data_resolution_enabled'] == '2' && !empty($autoDeverify))
			{
				$num_deverified = DataQuality::dataResolutionAutoDeverify($autoDeverify, $project_id);
			}

			## DO CALCULATIONS
			if ($commitData && $performAutoCalc && !$Proj->project['disable_autocalcs']) {
				// For performaing server-side calculations, get list of all fields being imported
				foreach ($records as &$record) {
					$updated_fields = array_keys($record);
					break;
				}
				// Save calculations
				$calcFields = Calculate::getCalcFieldsByTriggerField($updated_fields, true, $Proj);
				if (!empty($calcFields)) {
					$calcValuesUpdated = Calculate::saveCalcFields($record_names, $calcFields, 'all', array(), $Proj, $dataLogging, $group_id);
				}
			}

			// API and Mobile App: For the Mobile App only, set $log_event_id as global variable
			if ($commitData && $log_event_id !== null && defined("PAGE") && PAGE == 'api/index.php' && isset($_POST['mobile_app'])
				&& isset($_POST['data']) && $_POST['content'] == 'record')
			{
				$userInfo = User::getUserInfo(USERID);
				if ($_POST['uuid'] !== "")
				{
					$presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($_POST['uuid'])."') AND (project_id = ".PROJECT_ID.") LIMIT 1;";
					$preq1 = db_query($presql1);
					$row = db_fetch_assoc($preq1);
					if (!$row)  // no devices
					{
						$presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($_POST['uuid'])."', ".PROJECT_ID.");";
						db_query($presql2);
						$preq1 = db_query($presql1);
						$row = db_fetch_assoc($preq1);
					}

					if ($row && ($row['revoked'] == "0"))
					{
						if (isset($_POST['longitude']) && isset($_POST['latitude']))
						{
							$sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, device_id, longitude, latitude, ui_id) 
									values (".PROJECT_ID.", $log_event_id, 'SYNC_DATA', ".$row['device_id'].", 
									".db_escape($_POST['longitude']).", ".db_escape($_POST['latitude']).", '{$userInfo['ui_id']}')";
							db_query($sql);
						}
						else
						{
							$sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, details, device_id, ui_id) values
									($project_id, $log_event_id, 'SYNC_DATA', '".db_escape($counter)."', ".$row['device_id'].", '{$userInfo['ui_id']}')";
							db_query($sql);
						}
					}
					else
					{
						// revoked/blocked device
						return array();
					}
				 }
				 else
				 {
					$sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, details, ui_id) values
							($project_id, $log_event_id, 'SYNC_DATA', '".db_escape($counter)."', '{$userInfo['ui_id']}')";
					db_query($sql);
				}
				 // If this is the mobile app initializing a project, then log that in the mobile app log
			}

			// Set response array to return
			$response = array('errors'=>array(), 'warnings'=>$warnings_array, 'ids'=>$updatedIds, 'item_count'=>$counter);

			// If we're supposed to return the data comparison array, then add it
			if ($returnDataComparisonArray) {
				$response['values'] = $records;
			}

			// Return response array
			return $response;
		}
		catch (Exception $e)
		{
			// Get message
			$msg_orig = $e->getMessage();
			$msg = @unserialize($msg_orig); // Try to unserialize, just in case it's serialized.
			if ($msg === false) {
				// Data Import Tool only
				if (defined("PAGE") && PAGE == 'DataImportController:index') {
					$msg_orig = '"' . str_replace('"', '""', $msg_orig) . '"';
					$msg = array(",,,$msg_orig");
				} else {
				// All other pages
					$msg = $msg_orig;
				}
			}
			return array('errors'=>$msg, 'warnings'=>array(), 'ids'=>array(), 'item_count'=>0);
		}
	}


	// Check and fix any case sensitivity issues in record names
	public static function checkRecordNamesCaseSensitive($project_id, $records, $table_pk, $longitudinal)
	{
		// For case sensitivity issues, check actual record name's case against its value in the back-end. Use MD5 to differentiate.
		// Modify $records accordingly, if different.
		$records_md5 = $records_lower = $recordsKeyMap = array();
		foreach ($records as $key => $attr) {
			// Get record name from array
			$id = $attr[$table_pk]['new'];
			// Add key and record to $recordsKeyMap
			$recordsKeyMap[$key] = $id;
			// Longitudinal: Make sure that all rows being imported for a single record have the same case
			if ($longitudinal) {
				$id_lower = strtolower($id);
				if (isset($records_lower[$id_lower])) {
					// If the cases don't match for the record name within this same record, then set all to same case as first record row
					if ($records_lower[$id_lower]."" !== $id."") {
						// Set record ID value to original case
						$id = $attr[$table_pk]['new'] = $records_lower[$id_lower];
						// Now set array key to record_id+" (unique event name)"
						unset($records[$key]);
						list ($nothing, $event_name, $repeat_instrument, $repeat_instance) = explode_right("-", $key, 4);
						$records["$id-$event_name-$repeat_instrument-$repeat_instance"] = $attr;						
					} else {
						$id = $records_lower[$id_lower];
					}
				} else {
					$records_lower[$id_lower] = $id;
					$records_md5[$id] = md5($id);
				}
			} 
			// Classic: Just add to md5 array
			else {
				$records_md5[$id] = md5($id);
			}
		}
		unset($records_lower);

		// Query using MD5 to find values that are different from uploaded values only on the case-level
		$sql = "select distinct record from redcap_data where project_id = $project_id and field_name = '$table_pk'
				and md5(record) not in ('" . implode("', '", $records_md5) . "')
				and record in ('" . implode("', '", array_keys($records_md5)) . "')";
		$q = db_query($sql);
		
		unset($records_md5);
		$records2 = array();

		while ($row = db_fetch_assoc($q))
		{
			// Using array_key_exists won't work, so loop through all imported record names for a match.
			foreach ($records as $key => $this_record) {
				// Do case insensitive comparison
				if (strcasecmp($this_record[$table_pk]['new'], $row['record']) == 0) {
					// Record name exists in two different cases, so modify $records to align with back-end value.
					// Replace sub-array with sub-array containing other case value.
					$records2[$key] = $records[$key];
					$records2[$key][$table_pk]['new'] = $row['record'];
					unset($records[$key]);
				}
			}
		}

		// Merge arrays (don't user array_merge because keys will get lost if numerical)
		foreach ($records2 as $key=>$attr) {
			$records[$key] = $attr;
		}
		unset($records2);

		return $records;
	}


	// Does this form/event/record have any data saved for any fields on it (including Form Status)? 
	// Excludes calc fields and record ID field. Return boolean.
	public static function formHasData($record, $form, $event_id, $instance=1)
	{
		global $table_pk;
		$sql = "select 1 from redcap_data d, redcap_metadata m
				where d.project_id = ".PROJECT_ID." and d.project_id = m.project_id
				and d.record = '".db_escape($record)."' and d.event_id = $event_id and m.element_type != 'calc' and m.field_name != '$table_pk'
				and d.field_name = m.field_name and m.form_name = '".db_escape($form)."'";
		$sql .= (is_numeric($instance) && $instance > 1) ? " and d.instance = $instance" : " and d.instance is null";
		$sql .= " limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}


	// Does this set of fields on this event/record have any data saved.
	// Excludes calc fields and record ID field. Return boolean.
	public static function fieldsHaveData($record, $fields=array(), $event_id, $instance=1)
	{
		global $table_pk;
		$sql = "select 1 from redcap_data d, redcap_metadata m
				where d.project_id = ".PROJECT_ID." and d.project_id = m.project_id
				and d.record = '".db_escape($record)."' and d.event_id = $event_id and m.element_type != 'calc' and m.field_name != '$table_pk'
				and d.field_name = m.field_name and m.field_name in (".prep_implode($fields).")";
        $sql .= (is_numeric($instance) && $instance > 1) ? " and d.instance = $instance" : " and d.instance is null";
        $sql .= " limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Check if a record exists in the redcap_data table
	public static function recordExists($project_id, $record, $arm_num=null)
	{		
		// Get $Proj object
		$Proj = new Project($project_id);
		// See if the record list has alrady been cached. If so, use it.
		$recordListCacheStatus = self::getRecordListCacheStatus($project_id);
		if ($recordListCacheStatus == 'COMPLETE') {
			// Query record list cache table for record
			$sql = "select 1 from redcap_record_list where project_id = $project_id
					and record = '" . db_escape($record) . "'";
			if (is_numeric($arm_num) && isset($Proj->events[$arm_num])) {
				$sql .= " and arm = '" . db_escape($arm_num) . "'";
			}
		} else {
			// Query data table for record
			$sql = "select 1 from redcap_data where project_id = $project_id and field_name = '{$Proj->table_pk}'
					and record = '" . db_escape($record) . "'";
			if (is_numeric($arm_num) && isset($Proj->events[$arm_num])) {
				$sql .= " and event_id in (" . prep_implode(array_keys($Proj->events[$arm_num]['events'])) . ")";
			}
			$sql .= " limit 1";
		}
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Return array of all records in project
	public static function getRecordsAsArray($pid, $includeBlankOption=true, $limit=null)
	{
		global $lang, $user_rights;
		$Proj = new Project($pid);
		$recs = self::getRecordList($pid, $user_rights['group_id'], true, $Proj->longitudinal, null, $limit);
		$opt = array();
        // If limit was applied and the list was truncated, then add note at end that only first $limit records are shown
        if (is_numeric($limit) && count($recs) == $limit) {
            $opt[$lang['design_768']." $limit ".$lang['design_769']][''] = $lang['data_entry_91'];
        } elseif ($includeBlankOption) {
            $opt[''] = $lang['data_entry_91'];
        }
		foreach ($recs as $rec)                    {    
			$opt[$rec] = $rec;
		}
		return $opt;
	}

	// Return array of all records (or record/event pairs) as <option>s for a drop-down (includes Custom Record Label)
	public static function getRecordsAsOptions($pid, $limit=null)
	{
		global $user_rights, $longitudinal, $Proj, $lang;
		// Get custom record labels, if applicable
		if (!is_numeric($limit)) {
			$customLabel = self::getCustomRecordLabelsSecondaryFieldAllRecords(array(), true, ($Proj->longitudinal && $Proj->multiple_arms ? 'all' : 1));
		}
		// Get record array
		$recs = self::getRecordList($pid, $user_rights['group_id'], true, $longitudinal, null, $limit);
		// Get custom record labels, if applicable
		if (is_numeric($limit)) {
			$customLabel = self::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($recs), true, ($Proj->longitudinal && $Proj->multiple_arms ? 'all' : 1));
		}
		$str = "";
		foreach ($recs as $rec=>$recEv) {
			if ($longitudinal) {
				// Split record-event_id
				$posLastDash = strrpos($rec, '-');
				$record = substr($rec, 0, $posLastDash);
				if (isset($customLabel[$record])) $recEv .= " " . $customLabel[$record];
			} else {
				if (isset($customLabel[$rec])) $recEv .= " " . $customLabel[$rec];
			}
			$str .= "<option value='$rec'>$recEv</option>";
		}
		// If limit was applied and the list was truncated, then add note at end that only first $limit records are shown
		if (is_numeric($limit) && count($recs) == $limit) {
			$str .= "<optgroup label='".js_escape($lang['design_768']." $limit ".$lang['design_769'])."'></optgroup>";
		}
		// Return options
		return $str;
	}
	
	// Take a data array from getData() and remove fields that are not applicable for a given instance 
	// (e.g., remove repeating form/event fields from base instance).
	// Returns void.
	public static function removeNonApplicableFieldsFromDataArray(&$data=array(), $Proj, $removeNonBlankFields=false)
	{
		foreach ($data as $record=>&$rttr) {
			foreach ($rttr as $event_id=>&$attr) {
				if ($event_id == 'repeat_instances') {
					foreach ($attr as $this_real_event_id=>&$battr) {
						foreach ($battr as $this_repeat_instrument=>&$cattr) {
							foreach ($cattr as $this_instance=>&$dattr) {
								foreach ($dattr as $field=>$value) {
									$field_form = $Proj->metadata[$field]['form_name'];
									if (($this_repeat_instrument != "" && $field_form != $this_repeat_instrument)
										|| ($Proj->longitudinal && !in_array($field_form, $Proj->eventsForms[$this_real_event_id]))
										|| ($removeNonBlankFields && $value != '')
									) {
										if ($removeNonBlankFields && $Proj->isCheckbox($field)) {
											$valueUnique = array_unique($value);
											if (!(count($valueUnique) === 1 && current($valueUnique) == '0')) {
												unset($dattr[$field]);
											}
										} else {
											unset($dattr[$field]);
										}
									}
								}
								if (empty($dattr)) unset($cattr[$this_instance]);
							}
							if (empty($cattr)) unset($battr[$this_repeat_instrument]);
						}
						if (empty($battr)) unset($attr[$this_real_event_id]);
					}
				} else {
					foreach ($attr as $field=>$value) {
						$field_form = $Proj->metadata[$field]['form_name'];
						if ($Proj->isRepeatingForm($event_id, $field_form)
							|| ($Proj->longitudinal && !in_array($field_form, $Proj->eventsForms[$event_id]))
							|| ($removeNonBlankFields && $value != '')
						) {
							if ($removeNonBlankFields && $Proj->isCheckbox($field)) {
								$valueUnique = array_unique($value);
								if (!(count($valueUnique) === 1 && current($valueUnique) == '0')) {
									unset($attr[$field]);
								}
							} else {
								unset($attr[$field]);
							}
						}
					}
				}
				if (empty($attr)) unset($rttr[$event_id]);
			}
			if (empty($rttr)) unset($data[$record]);
		}
	}
	
	
	// Take a data array from a SINGLE RECORD from getData ($records[$id]) and move a specific repeating instance 
	// from the 'repeat_instances' subarray to the base instance.
	// This is useful when certain methods are looking for the legacy data array structure.
	public static function moveRepeatingDataToBaseInstance($data=array(), $event_id, $repeat_instrument="", $instance=1, $Proj=null)
	{
		if ($event_id < 1 || !is_numeric($event_id)) return $data;
		// Make sure instance is always >= 1
		if ($instance < 1 || !is_numeric($instance)) $instance = 1;
		if ($repeat_instrument === null) $repeat_instrument = "";
		// See if we have a repeating instance of this event/instrument/instance
		if (isset($data['repeat_instances'][$event_id][$repeat_instrument][$instance])) {
			// Loop through the instance and transfer data to base instance, overwriting any base instance data that exists
			foreach ($data['repeat_instances'][$event_id][$repeat_instrument][$instance] as $field=>$value) {
				// Get this field's form
				$form = $Proj->metadata[$field]['form_name'];
				// If this is a repeating form, and this field is not on this form, then skip
				if ($repeat_instrument != "" && $form != $repeat_instrument) continue;				
				// Overwrite or create field on base instance
				$data[$event_id][$field] = $value;
			}
		}
		// Remove repeat_instances sub-array (no longer needed) and return the data array
		unset($data['repeat_instances']);
		return $data;
	}
	
	// Assign record to a Data Access Group
	public static function assignRecordToDag($record, $group_id='')
	{
		global $Proj;
		$sql_all = array();
		// First, delete all existing rows (easier to add them in next step)
		$sql_all[] = $sql = "delete from redcap_data where project_id = " . PROJECT_ID
						  . " and record = '" . db_escape($record) . "' and field_name = '__GROUPID__'";
		db_query($sql);
		// Insert row for ALL existing events for this record
		if ($group_id != '') {
			$sql_all[] = $sql = "insert into redcap_data (project_id, event_id, record, field_name, value, instance) 
								select distinct '" . PROJECT_ID . "', event_id, '" . db_escape($record) . "', '__GROUPID__', 
								'" . db_escape($group_id) . "', instance from redcap_data where project_id = " . PROJECT_ID . " 
								and record = '" . db_escape($record) . "'";
			db_query($sql);
		}
		// Update calendar table (just in case)
		$sql_all[] = $sql = "UPDATE redcap_events_calendar SET group_id = " . checkNull($group_id) . " WHERE project_id = " . PROJECT_ID
						  . " AND record = '" . db_escape($record) . "'";
		db_query($sql);	
		
		// Logging
		$group_name = ($group_id == '') ? '' : $Proj->getUniqueGroupNames($group_id);
		$dag_log_descrip = "Assign record to Data Access Group";
		Logging::logEvent(implode(";\n",$sql_all), "redcap_data", "update", $record, "redcap_data_access_group = '$group_name'", $dag_log_descrip);
		
		// Update record list table
		self::updateRecordDagInRecordListCache(PROJECT_ID, $record, $group_id);	
	}
	
	// RESET RECORD COUNT CACHE: Remove the count of records in the cache table.
	public static function resetRecordCountAndListCache($project_ids=array())
	{
		if (empty($project_ids)) return false;
		// Convert to array if project_ids was passed as a string/int
		if (!is_array($project_ids)) {
			$project_ids = array($project_ids);
		}
		// Delete
		$sql = "delete from redcap_record_counts where project_id in (".prep_implode($project_ids).")";
		db_query($sql);
		// Return the number of rows deleted
		return db_affected_rows();
	}
	
	// Update record counts table with new record count based on count from redcap list table	
	public static function updateRecordCountFromRecordListCache($project_id)
	{
		global $rc_connection;
		$sql = "update redcap_record_counts set record_count = (select count(distinct(record)) from redcap_record_list 
				where project_id = $project_id) where project_id = $project_id";
		return db_query($sql, $rc_connection);
	}
	
	// ADD RECORD TO RECORD LIST CACHE QUEUE (to be added to record list cache afterward)
	public static function addRecordToRecordListCacheQueue($project_id, $record, $event_id)
	{
		global $RCRecordListCacheQueue;
		if (!isset($RCRecordListCacheQueue) || !is_array($RCRecordListCacheQueue)) $RCRecordListCacheQueue = array();
		$RCRecordListCacheQueue[$project_id][$record][] = $event_id;
	}
	
	// Re-order the existing record list cache table (only changes the sort value - does not insert or delete anything)
	public static function fixOrderRecordListCache($project_id)
	{
		$recordArmSort = array();
		$sql = "select arm, record, sort from redcap_record_list 
				where project_id = $project_id order by arm";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$recordArmSort[$row['record']][$row['arm']] = $row['sort'];
		}
		// Sort the keys using natural sort
		natcaseksort($recordArmSort);
		// Set null sort order for all at first
		$sql = "update redcap_record_list set sort = null
				where project_id = $project_id";
		if (!db_query($sql)) return false;		
		// Set insert SQL prefix and postfix
		$insertPre = "INSERT INTO redcap_record_list (project_id, arm, record, sort) VALUES ";
		$insertPost = " ON DUPLICATE KEY UPDATE project_id = VALUES(project_id), arm = VALUES(arm), record = VALUES(record), sort = VALUES(sort)";
		// Loop through all records and set new sort order
		$counter = 1;
		$inserts = array();
		foreach ($recordArmSort as $record=>$arms) {
			foreach ($arms as $arm=>$sort) {
				// Add to array to gather all the insert values
				$inserts[] = "($project_id,$arm,'" . db_escape($record) . "',".$counter++.")";
				// Insert 100 rows at once
				if ($counter % 100 == 0) {
					if (!db_query($insertPre . implode(", ", $inserts) . $insertPost)) {
						Records::resetRecordCountAndListCache($project_id);
						return false;
					}
					$inserts = array();
				}
			}
		}
		if (!empty($inserts)) {
			if (!db_query($insertPre . implode(", ", $inserts) . $insertPost)) {
				Records::resetRecordCountAndListCache($project_id);
				return false;
			}
		}
		// Set status to COMPLETE if was FIX_SORT
		$sql = "update redcap_record_counts set record_list_status = 'COMPLETE', time_of_list_cache = '".NOW."'
				where project_id = $project_id and record_list_status = 'FIX_SORT'";
		if (!db_query($sql)) return false;
		// If we made it this far, then all is well
		return true;
	}
	
	// Process the record list cache queue to add new records to record list cache (e.g., done at end of import)
	public static function processRecordListCacheQueue($project_id)
	{
		global $RCRecordListCacheQueue;
		if (!isset($RCRecordListCacheQueue) || empty($RCRecordListCacheQueue[$project_id])) return false;
		$Proj = new Project($project_id);
		// Build array of records being added to cache
		$recordArmList = array();
		foreach ($RCRecordListCacheQueue[$project_id] as $record=>$event_ids) {
			$armsThisRecord = array();
			foreach (array_unique($event_ids) as $event_id) {
				$armsThisRecord[] = $Proj->eventInfo[$event_id]['arm_num'];
			}
			$recordArmList[$record] = array_unique($armsThisRecord);
		}
		// Get DAG designations
		$dags = $Proj->getGroups();
		$recordsDags = empty($dags) ? array() : self::getRecordListAllDags($project_id, false, array_keys($recordArmList));
		// Add all the records to the record list cache table with sort=null (because we'll sort afterward)		
		foreach ($recordArmList as $record=>$arms) {
			// Get DAG ID
			$dag_id = isset($recordsDags[$record]) ? (int)$recordsDags[$record] : 'NULL';
			// Loop through all arms for this record
			foreach ($arms as $arm) {
				// Insert record with NULL sort and also add DAG designation
				$sql = "insert into redcap_record_list (project_id, arm, record, dag_id) values
						($project_id, $arm, '".db_escape($record)."', $dag_id)";
				db_query($sql);
			}
		}
		// Remove so that it doesn't get used again
		unset($RCRecordListCacheQueue[$project_id]);
		// Update recod count with new records
		self::updateRecordCountFromRecordListCache($project_id);
		// Fix the sort order now that these records have been added
		self::fixOrderRecordListCache($project_id);
		// If we got this far, then return true
		return true;
	}
	
	// Update a record's DAG designation in the record list cache table
	public static function updateRecordDagInRecordListCache($project_id, $records, $dag_id)
	{
	    // Make sure we have records as an array
        if (!is_array($records)) {
            if ($records == '') return false;
            $records = array($records);
        }
        if (count($records) == 0) return false;
        // Segment into 100 records at a time to reduce the total queries needed
        $records = array_chunk($records, 100);
        foreach ($records as $key=>$records_batch) {
            // Run this batch
            $sql = "update redcap_record_list set dag_id = " . checkNull($dag_id) . " 
				    where project_id = $project_id and record in (" . prep_implode($records_batch) . ")";
            db_query($sql);
            unset($records[$key]);
        }
		return true;
	}
	
	// REMOVE RECORD FROM RECORD LIST CACHE
	public static function deleteRecordFromRecordListCache($project_id, $record, $arm)
	{
		global $rc_connection;
		if (!is_numeric($arm)) return false;
		// Remove record from record list cache				
		$sql = "delete from redcap_record_list where project_id = $project_id
				and record = '".db_escape($record)."' and arm = $arm";
		if (db_query($sql, $rc_connection)) {
			return self::updateRecordCountFromRecordListCache($project_id);
		} else {
			return false;
		}
	}
	
	// RENAME RECORD IN RECORD LIST CACHE
	public static function renameRecordInRecordListCache($project_id, $newRecordId, $oldRecordId, $arm)
	{
		global $rc_connection;
		if (!is_numeric($arm)) return false;
        // See if the record list has alrady been cached. If so, use it.
        $recordListCacheStatus = self::getRecordListCacheStatus($project_id);
        if ($recordListCacheStatus != 'COMPLETE') return false;
		// Remove record from record list cache				
		$sql = "update redcap_record_list set record = '" . db_escape($newRecordId) . "' 
				where arm = '" . db_escape($arm) . "' and record = '" . db_escape($oldRecordId) . "'
				and project_id = $project_id";
		if (db_query($sql, $rc_connection)) {
			return (Records::fixOrderRecordListCache($project_id) && Records::updateRecordCountFromRecordListCache($project_id));
		} else {
			return false;
		}
	}
	
	// ADD RECORD TO RECORD LIST CACHE
	public static function addRecordToRecordListCache($project_id, $record, $arm)
	{
		global $rc_connection;
		$resetRecordCache = true;
		if (!is_numeric($arm)) return false;
		// Get ordered record list with arm-record as array key and sort as value
		$sql = "select concat(record, '-', arm) as record, sort from redcap_record_list
				where project_id = $project_id";
		$q = db_query($sql, $rc_connection);
		$recordArmList = array($record."-".$arm=>0);
		while ($row = db_fetch_assoc($q)) {
			$recordArmList[$row['record']] = $row['sort'];
		}
		// Sort the keys using natural sort
		natcaseksort($recordArmList);
		// Now find the record we added
		$lastSort = 0;
		foreach ($recordArmList as $recordArm=>$thisSort) {
			// Found this record
			if ($thisSort == 0) {
				$resetRecordCache = false;
				$thisSort = $lastSort+1;
				break;
			}
			$lastSort = $thisSort;
		}
		if (!$resetRecordCache) {
			// Increase sort by all records ordered after the new record
			$sql = "update redcap_record_list set sort = sort+1 
					where sort >= $thisSort and project_id = $project_id 
					order by sort desc";
			$resetRecordCache = !db_query($sql, $rc_connection);
		}
		if (!$resetRecordCache) {
			// Now add the new record to the record list cache				
			$sql = "insert into redcap_record_list (project_id, arm, record, dag_id, sort) values
					($project_id, $arm, '".db_escape($record)."', 
					(select value from redcap_data where project_id = $project_id and record = '".db_escape($record)."'
						and field_name = '__GROUPID__' limit 1), 
					$thisSort)";
			$resetRecordCache = !db_query($sql, $rc_connection);
		}
		if ($resetRecordCache) {
			// Reset record list cache
			Records::resetRecordCountAndListCache($project_id);
			return false;
		} else {
			return Records::updateRecordCountFromRecordListCache($project_id);
		}
	}
	
	// GET CACHED RECORD COUNT FOR A PROJECT
	public static function getCachedRecordCount($project_id)
	{
		if (empty($project_id) || !is_numeric($project_id)) return false;
		// Get count
		$sql = "select record_count from redcap_record_counts where project_id = $project_id";
		$q = db_query($sql);
		// Return the cached count (or NULL if not cached)
		return db_num_rows($q) ? db_result($q, 0) : null;
	}
	
	// HAS THE ENTIRE RECORD LIST BEEN CACHED FOR A PROJECT? Return the cache status
	public static function getRecordListCacheStatus($project_id)
	{
		if (empty($project_id) || !is_numeric($project_id)) return false;
		$sql = "select record_list_status from redcap_record_counts where project_id = $project_id";
		$q = db_query($sql);
		// Return the cached status (or NOT_STARTED if not cached)
		return db_num_rows($q) ? db_result($q, 0) : null;
	}

	// Check if a record exists on arms other than the current arm. Return true if so.
	public static function recordExistOtherArms($record, $current_arm)
	{
		global $multiple_arms, $table_pk, $table_pk_label;

		if (!$multiple_arms || !is_numeric($current_arm)) return false;

		// Query if exists on other arms
		$sql = "select 1 from redcap_events_metadata m, redcap_events_arms a, redcap_data d
                where a.project_id = " . PROJECT_ID . " and a.project_id = d.project_id and a.arm_num != $current_arm
                and a.arm_id = m.arm_id and d.event_id = m.event_id and d.record = '" . db_escape($record). "'
                and d.field_name = '$table_pk' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// When viewing a record on a data entry form, obtain record info (name, hidden_edit/existing_record, and DDE number)
	public static function getRecordAttributes()
	{
		global $double_data_entry, $user_rights, $table_pk, $hidden_edit;
		$fetched = $entry_num = NULL;
		if (PAGE == "DataEntry/index.php" && isset($_GET['page']))
		{
			// Alter how records are saved if project is Double Data Entry (i.e. add --# to end of Study ID)
			$entry_num = ($double_data_entry && $user_rights['double_data'] != '0') ? "--".$user_rights['double_data'] : "";
			// First, define $fetched for use in the data entry form list
			if (isset($_POST['submit-action']) && $_POST['submit-action'] != "submit-btn-delete" && isset($_POST[$table_pk]))
			{
				$fetched = trim($_POST[$table_pk]);
				// Rework $fetched for DDE if just posted (will have --1 or --2 on end)
				if ($double_data_entry && $user_rights['double_data'] != '0' && substr($fetched, -3) == $entry_num) {
					$fetched = substr($fetched, 0, -3);
				}
				// This record already exists
				$hidden_edit = 1;
			}
			elseif (isset($_GET['id']))
			{
				$fetched = trim($_GET['id']);
			}
			// Check if record exists (hidden_edit == 1)
			if (isset($fetched) && (!isset($hidden_edit) || (isset($hidden_edit) && !$hidden_edit)))
			{
				$hidden_edit = (Records::recordExists(PROJECT_ID, $fetched . $entry_num) ? 1 : 0);
			}
		}
		// Return values in form of array
		return array($fetched, $hidden_edit, $entry_num);
	}
}

