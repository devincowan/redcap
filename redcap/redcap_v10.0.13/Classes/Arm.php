<?php

class Arm
{
	// Add new arms to a given project (with option to delete all)
	// Return array with count of arms added and array of errors, if any
	public static function addArms($project_id, $data, $override=false)
	{
		global $lang;

		if($override)
		{
			Arm::delArms($project_id);
		}

		$count = 0;
		$errors = array();

		// Check for basic attributes needed
		if (empty($data) || !isset($data[0]['name']) || !isset($data[0]['arm_num'])) {
			$msg = $errors[] = $lang['design_641'] . " arm_num, name";
			if (defined("API")) {
				die(RestUtility::sendResponse(400, $msg));
			} else {
				return array($count, $errors);
			}
		}

		foreach($data as $arm)
		{
			$arm['name'] = trim($arm['name']);

			// Ensure that arm number is numeric
			if (!is_numeric($arm['arm_num']) || $arm['arm_num'] < 0) {
				$errors[] = "{$lang['design_636']} \"{$arm['arm_num']}\" {$lang['design_639']}";
				continue;
			}
			if ($arm['name'] == '') {
				$errors[] = "{$lang['design_637']} \"{$arm['arm_num']}\" {$lang['design_638']}";
				continue;
			}
			if (strlen($arm['name']) > 50) {
				$errors[] = "{$lang['design_651']} \"{$arm['name']}\" {$lang['design_652']}";
				continue;
			}

			if (empty($errors))
			{
				if (!$override && Arm::getArm($project_id, $arm['arm_num']))
				{
					Arm::updateArmName($project_id, $arm['arm_num'], $arm['name']);
					++$count;
					continue;
				}

				Arm::addArm($project_id, $arm['arm_num'], $arm['name']);
				++$count;
			}
		}

		// Return count and array of errors
		return array($count, $errors);
	}

	public static function getArm($project_id, $arm_num)
	{
		$project_id = (int)$project_id;
		$arm_num = (int)$arm_num;

		$sql = "
			SELECT
				arm_id,
				arm_num,
				arm_name
			FROM redcap_events_arms
			WHERE project_id = $project_id
			AND arm_num = $arm_num
		";

		$q = db_query($sql);

		if($q && $q !== false)
		{
			$array = db_fetch_assoc($q);
			return $array;
		}

		return null;
	}

	public static function delArm($project_id, $arm_num)
	{
		$project_id = (int)$project_id;
		$arm_num = (int)$arm_num;

		$sql = "
			DELETE FROM redcap_events_arms
			WHERE project_id = $project_id
			AND arm_num = $arm_num
			LIMIT 1
		";

		$q = db_query($sql);
		if($q && $q !== false)
		{
			return db_affected_rows();
		}
		return 0;
	}

	public static function IDs($project_id)
	{
		$project_id = (int)$project_id;

		$ids = array();

		$sql = "
			SELECT arm_id
			FROM redcap_events_arms
			WHERE project_id = $project_id
		";

		$q = db_query($sql);
		if($q && $q !== false)
		{
			while($row = db_fetch_assoc($q))
			{
				$ids[] = $row['arm_id'];
			}
		}
		return $ids;
	}

	public static function delArms($project_id)
	{
		$project_id = (int)$project_id;

		$sql = "
			DELETE FROM redcap_events_arms
			WHERE project_id = $project_id
		";

		$q = db_query($sql);
		if($q && $q !== false)
		{
			return db_affected_rows();
		}
		return 0;
	}

	public static function updateArmName($project_id, $arm_num, $arm_name)
	{
		$project_id = (int)$project_id;
		$arm_num = (int)$arm_num;
		$arm_name = db_escape($arm_name);

		$sql = "
			UPDATE redcap_events_arms
			SET	arm_name = '$arm_name'
			WHERE arm_num = $arm_num
			AND project_id = $project_id
			LIMIT 1
		";

		$q = db_query($sql);
		return ($q && $q !== false);
	}

	public static function addArm($project_id, $arm_num, $arm_name)
	{
		$project_id = (int)$project_id;
		$arm_num = (int)$arm_num;
		$arm_name = db_escape($arm_name);

		$sql = "
			INSERT INTO redcap_events_arms (
				project_id, arm_num, arm_name
			) VALUES (
				$project_id, $arm_num, '$arm_name'
			)
		";

		$q = db_query($sql);
		return ($q && $q !== false);
	}
}
