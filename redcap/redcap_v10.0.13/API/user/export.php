<?php
global $format, $returnFormat, $post;



# get all the records to be exported
$result = getItems();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json_encode($result);
		break;
	case 'xml':
		$content = xml($result);
		break;
	case 'csv':
		$content = csv($result);
		break;
}

/************************** log the event **************************/



# Logging
Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export users (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	global $mobile_app_enabled;
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<users>\n";
	foreach ($dataset as $row)
	{
		$output .= "<item>";
		foreach ($row as $key=>$val)
		{
			if ($key == 'forms') {
				$output .= "<forms>";
				foreach ($row['forms'] as $form => $right) {
					$output .= "<$form>$right</$form>";
				}
				$output .= "</forms>";
			} else {
				$output .= "<$key>" . htmlspecialchars($val, ENT_XML1, 'UTF-8') . "</$key>";
			}
		}
		$output .= "</item>\n";
	}
	$output .= "</users>\n";
	return $output;
}

function csv($dataset)
{
	foreach ($dataset as $index => $user) {
		$forms_string = array();
		foreach($user['forms'] as $form => $right) {
			$forms_string[] = "$form:$right";
		}
		$dataset[$index]['forms'] = implode(",", $forms_string);
	}
	return arrayToCsv($dataset);
}

function getItems()
{
	global $post, $mobile_app_enabled;

	$Proj = new Project();

	// Get all user's rights (includes role's rights if they are in a role)
	$user_priv = UserRights::getPrivileges($post['projectid']);
	$user_priv = $user_priv[$post['projectid']];

	# get user information (does NOT include role-based rights for user)
	$sql = "SELECT ur.*, ui.user_email, ui.user_firstname, ui.user_lastname, ui.super_user
			FROM redcap_user_rights ur
			LEFT JOIN redcap_user_information ui ON ur.username = ui.username
			WHERE ur.project_id = ".PROJECT_ID;
	$users = db_query($sql);
	$result = array();
	$r = 0;
	while ($row = db_fetch_assoc($users))
	{
		// Decode and set any nulls to ""
		foreach ($row as &$val) {
			if (is_array($val)) continue;
			if ($val == null) $val = '';
			$val = html_entity_decode($val, ENT_QUOTES);
		}

		// Convert username to lower case to prevent case sensitivity issues with arrays
		$row["username"] = strtolower($row["username"]);

		// Parse data entry rights
		if ($row["super_user"]) {
			foreach ($Proj->forms as $this_form=>$attr) {
				$forms[$this_form] = (isset($attr['survey_id'])) ? 3 : 1;
			}
		} else {
			// Regular user
			$dataEntryArr = explode("][", substr(trim($user_priv[$row["username"]]['data_entry']), 1, -1));
			$forms = array();
			foreach ($dataEntryArr as $keyval)
			{
				list($key, $value) = explode(",", $keyval, 2);
				if ($key == '') continue;
				$forms[$key] = isinteger($value) ? (int)$value : $value;
			}
		}

		// Check group_id
		$unique_group_name = "";
		if (is_numeric($row['group_id'])) {
			$unique_group_name = $Proj->getUniqueGroupNames($row['group_id']);
			if (empty($unique_group_name)) {
				$unique_group_name = $row['group_id'] = "";
			}
		}

		// Set array entry for this user
		$result[$r] = array(
			'username'					=> $row['username'],
			'email'						=> $row['user_email'],
			'firstname'					=> $row['user_firstname'],
			'lastname'					=> $row['user_lastname'],
			'expiration'				=> $row['expiration'],
			'data_access_group'			=> $unique_group_name,
			'data_access_group_id'		=> (isinteger($row['group_id']) ? (int)$row['group_id'] : $row['group_id'])
		);

		// Rights that might be governed by roles
		$rights = array(
			'design', 'user_rights', 'data_access_groups', 'data_export_tool'=>'data_export',
			'reports', 'graphical'=>'stats_and_charts',
			'participants'=>'manage_survey_participants', 'calendar',
			'data_import_tool', 'data_comparison_tool', 'data_logging'=>'logging',
			'file_repository', 'data_quality_design'=>'data_quality_create', 'data_quality_execute',
			'api_export', 'api_import',
			'mobile_app', 'mobile_app_download_data',
			'record_create', 'record_rename', 'record_delete', 'record_create',
			'lock_record_multiform'=>'lock_records_all_forms',
			'lock_record'=>'lock_records',
			'lock_record_customize'=>'lock_records_customization'
		);

		foreach($rights as $right=>$right_formatted)
		{
			$thisPriv = $user_priv[$row['username']][(is_numeric($right) ? $right_formatted : $right)];
			$result[$r][$right_formatted] = (isinteger($thisPriv) ? (int)$thisPriv : $thisPriv);
		}

		// Add form rights at end
		$result[$r]['forms'] = $forms;

		// If mobile app is not enabled, then remove the mobile_app user privilege attributes
		if (!$mobile_app_enabled) {
			unset($result[$r]['mobile_app'], $result[$r]['mobile_app_download_data']);
		}

		// Set for next loop
		$r++;
	}

	return $result;
}
