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

// save the metadata
$content = putMetaData();
	
// Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
MetaData::createDataDictionarySnapshot();

# Logging
Logging::logEvent("", "redcap_metadata", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload data dictionary (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function putMetaData()
{
	global $post, $format;
	$data = removeBOMfromUTF8($post['data']);

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
		if ($data == '' || !isset($data['records']['item'])) return $lang['data_import_tool_200'];
		$data = (isset($data['records']['item'][0])) ? $data['records']['item'] : array($data['records']['item']);
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		$data = csvToArray($data);
		break;
	}

	// Save a flat item-based metadata array
	list ($count, $errors) = MetaData::saveMetadataFlat($data);

	// Return any errors found when attempting to commit
	if (!empty($errors)) {
		die(RestUtility::sendResponse(400, strip_tags(implode("\n", $errors))));
	} else {
		return $count;
	}
}
