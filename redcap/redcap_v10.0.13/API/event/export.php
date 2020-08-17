<?php
global $format, $returnFormat, $post;



# get all the records to be exported
$result = Project::getEventRecords($post);

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
		$content = Project::eventsToCSV($result);
		break;
}

/************************** log the event **************************/



# Logging
Logging::logEvent("", "redcap_events_metadata", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export events (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<events>\n";

	foreach ($dataset as $row)
	{
		$line = '';
		foreach ($row as $item => $value)
		{
			if ($value != "")
				$line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
			else
				$line .= "<$item></$item>";
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</events>\n";

	return $output;
}
