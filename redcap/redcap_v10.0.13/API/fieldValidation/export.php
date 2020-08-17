<?php
global $format, $returnFormat, $post;



# get all the records to be exported
$result = getValTypes();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json($result);
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
Logging::logEvent("", "redcap_validation_types", "MANAGE", '', '', "Export field validations (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<field_validations>\n";
	foreach ($dataset as $valtype => $attr) {
		$output .= "<field_validation><validation_type>$valtype</validation_type>"
				 . "<regex><![CDATA[{$attr['regex_js']}]]></regex></field_validation>\n";
	}
	$output .= "</field_validations>\n";
	return $output;
}

function csv($dataset)
{
	$output = "";
	foreach ($dataset as $valtype => $attr) {
		$output .= $valtype.",\"".str_replace('"', '""', $attr['regex_js'])."\"\n";
	}
	$fieldList = "validation_type,regex";
	return $fieldList . "\n" . $output;
}

function json($dataset)
{
	$output = array();
	foreach ($dataset as $valtype => $attr) {
		$output[] = array('validation_type'=>$valtype, 'regex'=>$attr['regex_js']);
	}
	return json_encode($output);
}