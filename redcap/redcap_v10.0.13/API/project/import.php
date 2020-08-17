<?php

global $format, $returnFormat, $post;


/************************** log the event **************************/
$query = "SELECT username FROM redcap_user_information WHERE api_token = '" . db_escape($post['token']) . "'";

$content = createProject();

// Return any errors
if ($content === false) {
	die(RestUtility::sendResponse(400, $content));
} elseif (is_array($content)) {
	die(RestUtility::sendResponse(400, implode("\n", $content)));
}

# TODO: Logging
$usingOdmFile = (isset($post['odm']) && !empty($post['odm'])) ? " using REDCap XML file" : "";
Logging::logEvent("", "project_info", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Create project{$usingOdmFile} (API)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function createProject()
{
	global $post, $format, $lang;

	$data = $post['data'];

	// If ODM XML was sent, then get it
	$odm = (isset($post['odm']) && !empty($post['odm'])) ? $post['odm'] : null;

	switch($format)
	{
	case 'json':
		// Decode JSON into array
		$data = json_decode($data, true);
		$data = isset($data[0]) ? $data[0] : '';
		if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
		if (isset($data[0]) && count($data) > 1) die(RestUtility::sendResponse(400, $lang['api_123'], $format));
		break;
	case 'xml':
		// Decode XML into array
		$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
		if (count($data) > 1) die(RestUtility::sendResponse(400, $lang['api_123'], $format));
		$data = isset($data['item']) ? $data['item'] : '';
		if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		$data = csvToArray($data);
		if (count($data) > 1) die(RestUtility::sendResponse(400, $lang['api_123'], $format));
		$data = isset($data[0]) ? $data[0] : '';
		if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
		break;
	}

	$errors = Project::validateApiCreateProjectInput($data, $odm);

	if (!empty($errors)) return $errors;

	return Project::apiCreate(USERID, $data, $odm);
}
