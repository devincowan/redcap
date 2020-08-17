<?php

global $format, $returnFormat, $post;

// disable for production
$Proj = new Project();
if($Proj->project['status'] > 0)
{
	RestUtility::sendResponse(400, $lang['api_102'], $returnFormat);
	exit;
}

// Check for required privileges
if ($post['design_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_124'], $returnFormat));

// save the mappings
$content = putMap();

# Logging
Logging::logEvent("", "redcap_event_forms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import instrument-event mappings (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function putMap()
{
	global $post, $format, $Proj, $lang;
	$data = $post['data'];

	switch($format)
	{
	case 'json':
		// Decode JSON into array
		$data = json_decode($data, true);
		if ($data == '') return $lang['data_import_tool_200'];
		break;
	case 'xml':
		// Decode XML into array
		$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
		if ($data == '' || !isset($data['items'])) return $lang['data_import_tool_200'];
		$data = $data['items']['item'];
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		// match JSON format
		$data = csvToArray($data);
		break;
	}

	list ($count, $errors) = Event::saveEventMapping(PROJECT_ID, $data);

	return $count;
}