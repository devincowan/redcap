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
Logging::logEvent("", "project_info", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export project information (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$content = '<?xml version="1.0" encoding="UTF-8" ?>';
	$content .= "\n<items>\n";
	foreach ($dataset as $item => $value) {
		if ($value != "")
			$content .= "<$item><![CDATA[" . $value . "]]></$item>";
		else
			$content .= "<$item></$item>";
	}
	$content .= "\n</items>\n";
	return $content;
}

function csv($dataset)
{
	// Open connection to create file in memory and write to it
	$fp = fopen('php://memory', "x+");
	// Add headers
	fputcsv($fp, array_keys($dataset));
	// Add values
	fputcsv($fp, $dataset);
	// Open file for reading and output to user
	fseek($fp, 0);
	return stream_get_contents($fp);
}

function getItems()
{
	global $lang;
	// Get project object of attributes
	$Proj = new Project();
	// Set array of fields we want to return, along with their user-facing names
	$project_fields = Project::getAttributesApiExportProjectInfo();
	//print_array($Proj->project);
	// Add values for all the project fields
	$project_values = array();
	foreach ($project_fields as $key=>$hdr) {
		// Add to array
		if (!isset($Proj->project[$key])) {
			// Leave blank if not in array above
			$val = '';
		} elseif (is_bool($Proj->project[$key])) {
			// Convert boolean to 0 and 1
			$val = ($Proj->project[$key] === false) ? 0 : 1;
		} else {
			// Normal value
			$val = label_decode($Proj->project[$key]);
		}
		$project_values[$hdr] = isinteger($val) ? (int)$val : $val;
	}
	// Add longitudinal
	$project_values['is_longitudinal'] = $Proj->longitudinal ? 1 : 0;
	// Add repeating instruments and events flag
	$project_values['has_repeating_instruments_or_events'] = ($Proj->hasRepeatingFormsEvents() ? 1 : 0);
	// Add any External Modules that are enabled in the project
	$versionsByPrefix = \ExternalModules\ExternalModules::getEnabledModules($Proj->project_id);
	$project_values['external_modules'] = implode(",", array_keys($versionsByPrefix));
	// Reformat the missing data codes to be pipe-separated
	$theseMissingCodes = array();
	foreach (parseEnum($project_values['missing_data_codes']) as $key=>$val) {
		$theseMissingCodes[] = "$key, $val";
	}
	$project_values['missing_data_codes'] = implode(" | ", $theseMissingCodes);
	// Mobile App only: Add list of records that have been locked at the record-level
	if ($_POST['mobile_app'] == '1') {
		$Locking = new Locking();
		$Locking->findLockedWholeRecord($Proj->project_id);
		$project_values['locked_records'] = implode("\n", array_keys($Locking->lockedWhole));
	}
	// Return array
	return $project_values;
}
