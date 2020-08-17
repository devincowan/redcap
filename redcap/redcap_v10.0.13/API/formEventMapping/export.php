<?php
global $format, $returnFormat, $post;



# get all the records to be exported
$result = Project::getInstrEventMapRecords($post);
$res['formEventMapping'] = $result;

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
		$content = Project::instrEventMapToCSV($result);
		break;
}

# Logging
Logging::logEvent("", "redcap_events_forms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export instrument-event mappings (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<items>\n";
	foreach ($dataset as $items)
	{
		$output .= "<item><arm_num>{$items['arm_num']}</arm_num><unique_event_name>{$items['unique_event_name']}</unique_event_name><form>{$items['form']}</form></item>\n";
	}
	$output .= "</items>\n";
	return $output;
}
