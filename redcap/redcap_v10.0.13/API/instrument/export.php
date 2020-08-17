<?php
global $format, $returnFormat, $post;



# get all the records to be exported
$result = getRecords();

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
Logging::logEvent("", "redcap_metadata", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export instruments (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<instruments>\n";

	foreach ($dataset as $row)
	{
		$line = '';
		foreach ($row as $item => $value)
		{
			if ($value != "")
				$line .= "<$item><![CDATA[$value]]></$item>";
			else
				$line .= "<$item></$item>";
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</instruments>\n";

	return $output;
}

function csv($dataset)
{
	$output = "";

	foreach ($dataset as $index => $row) {
		$output .= $row['instrument_name'].",\"".str_replace('"', '""', $row['instrument_label'])."\"\n";
	}

	$fieldList = "instrument_name,instrument_label";
	$output = $fieldList . "\n" . $output;

	return $output;
}

function getRecords()
{
	global $post;
	// Don't output any CATs because they cannot be used in the Mobile App
	$cat_list = ($post['mobile_app']) ? PROMIS::getPromisInstruments() : array();
	// Loop through instruments
	$forms = array();
	$Proj = new Project();
	foreach ($Proj->forms as $form=>$attr) {
		if (in_array($form, $cat_list)) continue;
		$forms[] = array('instrument_name'=>$form, 'instrument_label'=>strip_tags(html_entity_decode($attr['menu'], ENT_QUOTES)));
	}
	return $forms;
}