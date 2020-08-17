<?php
global $format, $returnFormat, $post;

// Get user's user rights
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges();

$Proj = new Project(PROJECT_ID);

// Prevent data imports for projects in inactive or archived status
if ($Proj->project['status'] > 1) {
	if ($Proj->project['status'] == '2') {
		$statusLabel = "Analysis/Cleanup";
	} else {
		$statusLabel = "[unknown]";
	}
	die(RestUtility::sendResponse(403, "Data may not be imported because the project is in $statusLabel status."));
}

if ($post['uuid'] !== "")
{
        $presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($post['uuid'])."') AND (project_id = ".PROJECT_ID.") LIMIT 1;";
        $preq1 = db_query($presql1);
        $row = db_fetch_assoc($preq1);
        if (!$row)
        {
                $presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($post['uuid'])."', ".PROJECT_ID.");";
                db_query($presql2);
                $preq1 = db_query($presql1);
                $row = db_fetch_assoc($preq1);
        }
        if ($row && ($row['revoked'] == "0"))
        {
				$userInfo = User::getUserInfo(USERID);
                $sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, device_id, ui_id) 
						values (".PROJECT_ID.", null, '$mobile_app_event', ".$row['device_id'].", '".db_escape($userInfo['ui_id'])."')";
                db_query($sql);
                # proceed as below
        }
        else
        {
                die(RestUtility::sendResponse(403, "Your device does not have appropriate permissions to upload the records."));
        }
}

// Save the data
$result = Records::saveData($post['projectid'], $format, $post['data'], $post['overwriteBehavior'], $post['dateFormat'], $post['type'],
							$user_rights['group_id'], true, true, true, false, true, array(), false, (strtolower($format) != 'odm'), 
							false, $post['forceAutoNumber']);
// Check if error occurred
if (count($result['errors']) > 0) {
	$response = is_array($result['errors']) ? implode("\n", $result['errors']) : $result['errors'];
	die(RestUtility::sendResponse(400, $response));
}

# format the response
$response = "";
$returnContent = $post['returnContent'];
if ($returnContent == "auto_ids" && !$post['forceAutoNumber']) $returnContent = "count";
if ($returnFormat == "json") {
	if ($post['forceAutoNumber'] && $returnContent == "auto_ids") {
		$ids = array();
		foreach ($result['ids'] as $oldId=>$newId) {
			$ids[] = "$newId,$oldId";
		}
		$response = json_encode($ids);
	}
	elseif ($returnContent == "ids") {
		$response = json_encode(array_values($result['ids']));
	}
	elseif ($returnContent == "count") {
		$response = '{"count": '.count($result['ids'])."}";
	}
}
elseif ($returnFormat == "xml") {
	$response = '<?xml version="1.0" encoding="UTF-8" ?>';
	if ($post['forceAutoNumber'] && $returnContent == "auto_ids") {
		$response .= "<ids>";
		foreach ($result['ids'] as $key=>$line) {
			$line .= ",$key";
			$response .= "<id>$line</id>";
		}
		$response .= "</ids>";
	}
	elseif ($returnContent == "ids") {
		$response .= "<ids>";
		foreach ($result['ids'] as $key=>$line) {
			$response .= "<id>$line</id>";
		}
		$response .= "</ids>";
	}
	elseif ($returnContent == "count") {
		$response .= '<count>'.count($result['ids'])."</count>";
	}
}
else {
	if ($post['forceAutoNumber'] && $returnContent == "auto_ids") {
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		fputcsv($fp, array("id", "original_id"));
		// Loop through array and output line as CSV
		foreach ($result['ids'] as $key=>$line) {
			fputcsv($fp, array($line, $key));
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		$response = trim(stream_get_contents($fp));
		fclose($fp);
	}
	elseif ($returnContent == "ids") {
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		fputcsv($fp, array("id"));
		// Loop through array and output line as CSV
		foreach ($result['ids'] as $key=>$line) {
			fputcsv($fp, array($line));
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		$response = trim(stream_get_contents($fp));
		fclose($fp);
	}
	elseif ($returnContent == "count") {
		$response = count($result['ids']);
	}
}

// MOBILE APP ONLY
if ($post['mobile_app'])
{
	// If importing values via REDCap Mobile App, then enforce form-level privileges to
	// allow app to remain consistent with normal data entry rights
	// Loop through each field and check if user has form-level rights to its form.
	$fieldsNoAccess = array();
	foreach ($fieldList as $this_field) {
		// Skip record ID field
		if ($this_field == $Proj->table_pk) continue;
		// If field is a checkbox field, then remove the ending to get the real field
		if (isset($fullCheckboxFields[$this_field])) {
			list ($this_field, $nothing) = explode("___", $this_field, 2);
		}
		// If not a real field (maybe a reserved field), then skip
		if (!isset($Proj->metadata[$this_field])) continue;
		// Check form rights
		$this_form = $Proj->metadata[$this_field]['form_name'];
		if (isset($user_rights['forms'][$this_form]) && ($user_rights['forms'][$this_form] == '0'
			|| $user_rights['forms'][$this_form] == '2')) {
			// Add field to $fieldsNoAccess array
			$fieldsNoAccess[] = $this_field;
		}
	}
	// Send error message back
	if (!empty($fieldsNoAccess)) {
		throw new Exception("The following fields exist on data collection instruments to which you currently " .
			"do not have Data Entry Rights access or to which you have Read-Only privileges, and thus you are not able to import data for them from the REDCap Mobile App. Fields: \"".implode("\", \"", $fieldsNoAccess)."\"");
	}
}

# Send the response to the requester
RestUtility::sendResponse(200, $response);
