<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// Get user's user rights
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges();

// Set vars
$project_id = $_GET['pid'] = $post['projectid'];
$field = (isset($post['field']) && $post['field'] != '') ? $post['field'] : '';

// Validate field
if ($field != '' && !isset($Proj->metadata[$field])) {
	RestUtility::sendResponse(400, "Invalid field");
}

// Get export field names as array
$exportFieldNames = REDCap::getExportFieldNames($field);

// Check for errors
if ($exportFieldNames == false) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = ($field == '') ? '' : "field_name = '$field'";
	Logging::logEvent("","redcap_metadata","MANAGE",$project_id,$logging_data_values,"Download export field names (API$playground)");

	// Set headers
	$headers = array('original_field_name', 'choice_value', 'export_field_name');

	// Return the field names in the desired format
	if ($format == 'csv') {
		// CSV
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		fputcsv($fp, $headers);
		// Loop through array and output line as CSV
		foreach ($exportFieldNames as $key=>&$line) {
			// If $line is an array (checkbox), then loop through the array
			if (is_array($line)) {
				foreach ($line as $key2=>$line2) {
					fputcsv($fp, array($key, $key2, $line2));
				}
			} else {
				fputcsv($fp, array($key, '', $line));
			}
			// Remove line from array to free up memory as we go
			unset($exportFieldNames[$key]);
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		$data = stream_get_contents($fp);
		fclose($fp);
	} elseif ($format == 'json') {
		// JSON
		// Convert all data into JSON string (do record by record to preserve memory better)
		$data = '';
		foreach ($exportFieldNames as $key=>&$line) {
			// If $line is an array (checkbox), then loop through the array
			if (is_array($line)) {
				foreach ($line as $key2=>$line2) {
					$data .= ",".json_encode(array($headers[0]=>$key, $headers[1]=>"".$key2, $headers[2]=>$line2));
				}
			} else {
				$data .= ",".json_encode(array($headers[0]=>$key, $headers[1]=>'', $headers[2]=>$line));
			}
			// Remove line from array to free up memory as we go
			unset($exportFieldNames[$key]);
		}
		$data = '[' . substr($data, 1) . ']';
	} elseif ($format == 'xml') {
		// XML
		// Convert all data into XML string
		$data = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<fields>\n";
		// Loop through array and add to XML string
		foreach ($exportFieldNames as $key=>&$item) {
			// If $item is an array (checkbox), then loop through the array
			if (is_array($item)) {
				foreach ($item as $key2=>$item2) {
					$data .= "<field>";
					$data .= "<{$headers[0]}><![CDATA[$key]]></{$headers[0]}>";
					$data .= "<{$headers[1]}><![CDATA[$key2]]></{$headers[1]}>";
					$data .= "<{$headers[2]}><![CDATA[$item2]]></{$headers[2]}>";
					$data .= "</field>\n";
				}
			} else {
				$data .= "<field>";
				$data .= "<{$headers[0]}><![CDATA[$key]]></{$headers[0]}>";
				$data .= "<{$headers[1]}><![CDATA[]]></{$headers[1]}>";
				$data .= "<{$headers[2]}><![CDATA[$item]]></{$headers[2]}>";
				$data .= "</field>\n";
			}
			// Remove line from array to free up memory as we go
			unset($exportFieldNames[$key]);
		}
		// End XML string
		$data .= "</fields>";
	}

	// Return the data
	RestUtility::sendResponse(200, $data, $format);
}
