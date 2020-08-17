<?php

class Event
{
	// Save form-event mapping to a given project
	// Return array with count of events added and array of errors, if any
	public static function saveEventMapping($project_id, $data=array(), $forms=array())
	{
		global $lang;

		$count = 0;
		$errors = array();

		// Instantiate Project because we'll need it below
		$Proj = new Project($project_id);

		// Check structure (skip for ODM project creation)
		if (!defined("CREATE_PROJECT_ODM") && (empty($data) || !isset($data[0]['form']) || !isset($data[0]['unique_event_name']) || !isset($data[0]['arm_num']))) {
			$msg = $errors[] = $lang['design_641'] . " arm_num,unique_event_name,form";
			if (defined("API")) {
				die(RestUtility::sendResponse(400, $msg));
			} else {
				return array($count, $errors);
			}
		} else {
			// Error checking
			foreach ($data as $e)
			{
				if (!is_numeric($e['arm_num']) || $e['arm_num'] < 0) {
					$errors[] = "{$lang['design_636']} \"{$e['arm_num']}\" {$lang['design_639']}";
				}
				elseif (!Arm::getArm($project_id, $e['arm_num'])) {
					$errors[] = "{$lang['design_644']} \"{$e['arm_num']}\" {$lang['design_643']}";
				}
				if (!$Proj->getEventIdUsingUniqueEventName($e['unique_event_name'])) {
					$errors[] = "{$lang['design_642']} \"{$e['unique_event_name']}\" {$lang['design_643']}";
				}
				if (!(isset($Proj->forms[$e['form']]) || (defined("CREATE_PROJECT_ODM") && isset($forms[$e['form']])))) {
					$errors[] = "{$lang['design_653']} \"{$e['form']}\" {$lang['design_643']}";
				}
			}
			if (empty($errors))
			{
				// Set new mappings
				$Proj->clearEventForms();
				return array($Proj->addEventForms($data), $errors);
			}
			else
			{
				if (defined("API")) {
					die(RestUtility::sendResponse(400, implode("\n", $errors)));
				} else {
					return array($count, $errors);
				}
			}
		}
	}


	// Add new events to a given project (with option to delete all)
	// Return array with count of events added and array of errors, if any
	public static function addEvents($project_id, $data, $override=false)
	{
		global $lang;

		if($override)
		{
			Event::deleteAll($project_id);
		}

		$count = 0;
		$day_offset = 0;
		$errors = array();

		// Check for basic attributes needed
		if (empty($data) || !isset($data[0]['event_name']) || !isset($data[0]['arm_num'])) {
			$msg = $errors[] = $lang['design_641'] . " event_name,arm_num" . ($override ? "" : ",unique_event_name");
			if (defined("API")) {
				die(RestUtility::sendResponse(400, $msg));
			} else {
				return array($count, $errors);
			}
		}

		foreach($data as $e)
		{
			$day_offset++;

			// Set defaults
			if (!isset($e['day_offset'])) $e['day_offset'] = $day_offset;
			if (!isset($e['offset_min'])) $e['offset_min'] = 0;
			if (!isset($e['offset_max'])) $e['offset_max'] = 0;

			// Error checking
			if (!is_numeric($e['arm_num']) || $e['arm_num'] < 0) {
				$errors[] = "{$lang['design_636']} \"{$e['arm_num']}\" {$lang['design_639']}";
				continue;
			}
			if (!Arm::getArm($project_id, $e['arm_num'])) {
				$errors[] = "{$lang['design_644']} \"{$e['arm_num']}\" {$lang['design_643']}";
				continue;
			}
			if (!is_numeric($e['day_offset'])) {
				$errors[] = "{$lang['design_645']} \"{$e['day_offset']}\" {$lang['design_646']}";
				continue;
			}
			if (!is_numeric($e['offset_min']) || $e['offset_min'] < 0) {
				$errors[] = "{$lang['design_647']} \"{$e['offset_min']}\" {$lang['design_639']}";
				continue;
			}
			if (!is_numeric($e['offset_max']) || $e['offset_max'] < 0) {
				$errors[] = "{$lang['design_648']} \"{$e['offset_max']}\" {$lang['design_639']}";
				continue;
			}			
			if ((function_exists('mb_strlen') && mb_strlen($e['event_name']) > 30) 
				|| (!function_exists('mb_strlen') && strlen($e['event_name']) > 30)) {
				$errors[] = "{$lang['design_649']} \"{$e['event_name']}\" {$lang['design_650']}";
				continue;
			}

			if(!$override)
			{
				$Proj = new Project($project_id);
				$id = $Proj->getEventIdUsingUniqueEventName($e['unique_event_name']);

				if($id)
				{
					Event::update($id, $e);
					++$count;
					continue;
				}
			}

			Event::create($project_id, $e);
			++$count;
		}

		// Return count and array of errors
		return array($count, $errors);
	}


	public static function deleteAll($project_id)
	{
		$project_id = (int)$project_id;
		$ids = Arm::IDs($project_id);
		$count = 0;

		foreach($ids as $arm_id)
		{
			$sql = "
				DELETE FROM redcap_events_metadata
				WHERE arm_id = $arm_id
			";

			$q = db_query($sql);
			if($q && $q !== false)
			{
				$count += db_affected_rows();
			}
		}

		return $count;
	}

	public static function delete($event_id)
	{
		$event_id = (int)$event_id;

		$sql = "
			DELETE FROM redcap_events_metadata
			WHERE event_id = $event_id
			LIMIT 1
		";

		$q = db_query($sql);
		if($q && $q !== false)
		{
			return db_affected_rows();
		}
		return 0;
	}

	public static function create($projectId, $event)
	{
		$projectId = (int)$projectId;
		$arm = Arm::getArm($projectId, $event['arm_num']);
		if(!$arm) return false;

		$arm_id = (int)$arm['arm_id'];
		$day_offset = (int)$event['day_offset'];
		$offset_min = (int)$event['offset_min'];
		$offset_max = (int)$event['offset_max'];
		$descrip = db_escape($event['event_name']);
		$custom_event_label = db_escape($event['custom_event_label']);

		$sql = "
			INSERT INTO redcap_events_metadata (
				arm_id, day_offset, offset_min, offset_max, descrip, custom_event_label
			) VALUES (
				$arm_id, $day_offset, $offset_min, $offset_max, '$descrip', '$custom_event_label'
			)
		";

		$q = db_query($sql);
		return ($q && $q !== false);
	}

	public static function update($event_id, $event)
	{
		$event_id = (int)$event_id;
		$day_offset = (int)$event['day_offset'];
		$offset_min = (int)$event['offset_min'];
		$offset_max = (int)$event['offset_max'];
		$descrip = db_escape($event['event_name']);
		$custom_event_label = db_escape($event['custom_event_label']);

		$sql = "
			UPDATE redcap_events_metadata
			SET
				descrip = '$descrip',
				day_offset = $day_offset,
				offset_min = $offset_min,
				offset_max = $offset_max,
				custom_event_label = '$custom_event_label'
			WHERE event_id = $event_id
			LIMIT 1
		";

		$q = db_query($sql);
		return ($q && $q !== false);
	}

	public static function getByProjArmNumEventName($projectId, $arm_num, $event_name)
	{
		$projectId = (int)$projectId;
		$arm = Arm::getArm($projectId, $arm_num);
		if(!$arm) return null;

		$arm_id = (int)$arm['arm_id'];
		$descrip = db_escape($event_name);

		$sql = "
			SELECT *
			FROM redcap_events_metadata
			WHERE arm_id = $arm_id
			AND descrip = '$descrip'
		";

		$q = db_query($sql);

		if($q && $q !== false)
		{
			$array = db_fetch_assoc($q);
			return $array;
		}

		return null;
	}

	public static function getEventsByProject($projectId)
	{
		$eventList = array();

		$sql = "SELECT *
				FROM redcap_events_metadata rem
					JOIN redcap_events_arms rea ON rem.arm_id = rea.arm_id
				WHERE project_id = $projectId";
		$events = db_query($sql);

		while ($row = db_fetch_array($events))
		{
			$eventList[$row['event_id']] = $row['descrip'];
		}

		return $eventList;
	}

	public static function getEventIdByName($projectId, $name)
	{
		$idList = self::getEventIdByKey($projectId, array($name));
		$id = (count($idList) > 0) ? $idList[0] : 0;

		return $id;
	}

	public static function getUniqueKeys($projectId)
	{
		global $Proj;
		if (empty($Proj)) {
			$Proj2 = new Project($projectId);
			return $Proj2->getUniqueEventNames();
		} else {
			return $Proj->getUniqueEventNames();
		}
	}

	public static function getEventNameById($projectId, $id)
	{
		$uniqueKeys = array_flip(Event::getUniqueKeys($projectId));

		$name = array_search($id, $uniqueKeys);

		return $name;
	}

	public static function getEventIdByKey($projectId, $keys)
	{
		$uniqueKeys = Event::getUniqueKeys($projectId);
		$idList = array();

		foreach($keys as $key)
		{
			$idList[] = array_search($key, $uniqueKeys);
		}

		return $idList;
	}

	/**
	 * Retrieve logging-related info when adding/updating/deleting Events on Define My Events page using the event_id
	 */
	public static function eventLogChange($event_id) {
		if ($event_id == "" || $event_id == null || !is_numeric($event_id)) return "";
		$logtext = array();
		$sql = "select * from redcap_events_metadata m, redcap_events_arms a where m.event_id = $event_id and a.arm_id = m.arm_id limit 1";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$logtext[] = "Event: ".$row['descrip'];
			// Display arm name if more than one arm exists
			$armCount = db_result(db_query("select count(1) from redcap_events_arms where project_id = ".PROJECT_ID), 0);
			if ($armCount > 1) $logtext[] = "Arm: ".$row['arm_name'];
			$logtext[] = "Days Offset: ".$row['day_offset'];
			$logtext[] = "Offset Range: -{$row['offset_min']}/+{$row['offset_max']}";
		}
		return implode(", ", $logtext);
	}

	// Retrieve list of all Events utilized by DTS for a specified project
	public static function getDtsEvents()
	{
		global $Proj, $dtsHostname, $dtsUsername, $dtsPassword, $dtsDb;
		// Connect to DTS database
		$dts_connection = mysqli_connect(remove_db_port_from_hostname($dtsHostname), $dtsUsername, $dtsPassword, $dtsDb, get_db_port_by_hostname($dtsHostname));
		if (!$dts_connection) { db_connect(); return array(); }
		// Set default
		$eventIdsDts = array();
		// Get list of all event_ids for this project
		$ids = implode(",",array_keys($Proj->eventsForms));
		// Now get list of all event_ids used by DTS for this project
		$query = "SELECT DISTINCT md.event_id
                  FROM project_map_definition md
                    LEFT JOIN project_transfer_definition td ON md.proj_trans_def_id = td.id
                  WHERE td.redcap_project_id = " . PROJECT_ID . "
                    AND event_id IN ($ids)";
		$recommendations = db_query($query);
		while ($row = db_fetch_assoc($recommendations))
		{
			// Add event_id as key for quick checking
			$eventIdsDts[$row['event_id']] = true;
		}
		// Set default connection back to REDCap core database
		db_connect();
		// Return the event_ids as array keys
		return $eventIdsDts;
	}

	// Retrieve list of all Events-Forms utilized by DTS for a specified project
	public static function getDtsEventsForms()
	{
		global $Proj, $dtsHostname, $dtsUsername, $dtsPassword, $dtsDb;
		// Connect to DTS database
		$dts_connection = mysqli_connect(remove_db_port_from_hostname($dtsHostname), $dtsUsername, $dtsPassword, $dtsDb, get_db_port_by_hostname($dtsHostname));
		if (!$dts_connection) { db_connect(); return array(); }
		// Set default
		$eventsForms = array();
		// Now get list of all events-forms used by DTS for this project
		$query = "SELECT DISTINCT event_id, target_field, target_temporal_field
                  FROM project_map_definition md
                    LEFT JOIN project_transfer_definition td ON md.proj_trans_def_id = td.id
                  WHERE td.redcap_project_id = " . PROJECT_ID;
		$targets = db_query($query);
		while ($row = db_fetch_assoc($targets))
		{
			$eventsForms[$row['event_id']][$Proj->metadata[$row['target_field']]['form_name']] = true;
			$eventsForms[$row['event_id']][$Proj->metadata[$row['target_temporal_field']]['form_name']] = true;
		}
		// Set default connection back to REDCap core database
		db_connect();
		// Return the event_ids as array keys with form_names as sub-array keys
		return $eventsForms;
	}

	// Retrieve list of all field_names utilized by DTS for a specified project
	public static function getDtsFields()
	{
		global $dtsHostname, $dtsUsername, $dtsPassword, $dtsDb;
		// Connect to DTS database
		$dts_connection = mysqli_connect(remove_db_port_from_hostname($dtsHostname), $dtsUsername, $dtsPassword, $dtsDb, get_db_port_by_hostname($dtsHostname));
		if (!$dts_connection) { db_connect(); return array(); }
		// Set default
		$dtsFields = array();
		// Now get list of all field_names used by DTS for this project
		$query = "SELECT DISTINCT event_id, target_field, target_temporal_field
                  FROM project_map_definition md
                    LEFT JOIN project_transfer_definition td ON md.proj_trans_def_id = td.id
                  WHERE td.redcap_project_id = " . PROJECT_ID;
		$fields = db_query($query);
		while ($row = db_fetch_assoc($fields))
		{
			// Add field_name as key for quick checking
			$dtsFields[$row['target_field']] = true;
			$dtsFields[$row['target_temporal_field']] = true;
		}
		// Set default connection back to REDCap core database
		db_connect();
		// Return the field_names as array keys
		return $dtsFields;
	}
}
