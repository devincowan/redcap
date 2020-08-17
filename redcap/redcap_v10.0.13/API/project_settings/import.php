<?php
global $format, $returnFormat, $post;

// Get project object of attributes
$Proj = new Project(PROJECT_ID);

# Logging
Logging::logEvent("", "redcap_projects", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import project information (API$playground)");

$data = createData($format, $post['data']);
storeItems($data);
$content = count($data);

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function createData($format, $data)
{
    switch($format)
    {
        case 'json':
                // Decode JSON into array
                $data = json_decode($data, true);
                $data = isset($data) ? $data : '';
                if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
                break;
        case 'xml':
                // Decode XML into array
                $data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
                $data = isset($data['items']) ? $data['items'] : '';
                if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
                break;
        case 'csv':
                // Decode CSV into array
                $data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
                $data = csvToArray($data);
                $data = isset($data[0]) ? $data[0] : '';
                if ($data == '') die(RestUtility::sendResponse(400, $lang['data_import_tool_200'], $format));
                break;
    }
    return $data;
}

function storeItems($data)
{
	global $lang, $Proj;
	$project_fields = Project::getAttributesApiExportProjectInfo();
	foreach ($project_fields as $key => $hdr)
	{
		if (isset($data[$hdr]))
		{
			$Proj->project[$key] = $data[$hdr];
		}
	}
	$Proj->setProjectValues();
}

