<?php
global $format, $returnFormat, $post;


# get all the items to be exported
$result = getItems();

# structure the output data accordingly
switch($format){
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
Logging::logEvent("", "redcap_events_repeat", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export repeating instruments and events (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset){
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<repeatingFormsEvents>\n";

	foreach ($dataset as $row){
		$line = '';
		foreach ($row as $item => $value){
			if ($value != ""){
				if($item == "custom_form_label"){
					$line .= "<$item><![CDATA[$value]]></$item>";
				}else{
					$line .= "<$item>$value</$item>";
				}
			}else{
				$line .= "<$item></$item>";
			}
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</repeatingFormsEvents>\n";

	return $output;
}

function csv($dataset){
	$Proj = new Project();
	$output = "";

	foreach ($dataset as $index => $row) {
		if($Proj->longitudinal){
			$output .= $row['event_name'].",".$row['form_name'].",".str_replace('"', '""', $row['custom_form_label'])."\n";
		}else{
			$output .= $row['form_name'].",".str_replace('"', '""', $row['custom_form_label'])."\n";
		}
	}

	$fieldList = ($Proj->longitudinal ? "event_name,form_name,custom_form_label" : "form_name,custom_form_label");
	$output = $fieldList . "\n" . $output;

	return $output;
}

function getItems(){
	global $lang;
	// Get project object of attributes
	$Proj = new Project();
	// if project has not repeating forms or events
	if(!$Proj->hasRepeatingFormsEvents()){
		die(RestUtility::sendResponse(400, 'You cannot export repeating instruments and events because the project does not contain any repeating instruments and events'));
	}
	$raw_values = $Proj->getRepeatingFormsEvents();
	if($Proj->longitudinal){
		$eventForms = $Proj->eventsForms;
		foreach ($eventForms as $dkey=>$row){
			$event_name = Event::getEventNameById($Proj->project_id,$dkey);
			$sql = "select form_name, custom_repeat_form_label from redcap_events_repeat where event_id = " . db_escape($dkey) . "";
			$q = db_query($sql);
			if(db_num_rows($q) > 0){
				while ($row = db_fetch_assoc($q)){
					$form_name = ($row['form_name'] ? $row['form_name'] : '');
					$form_label = ($row['custom_repeat_form_label'] ? $row['custom_repeat_form_label'] : '');
					$results[] = array('event_name'=>$event_name, 'form_name'=>$form_name, 'custom_form_label'=>$form_label);
				}
			}
		}
	}else{//classic project
		foreach (array_values($raw_values)[0] as $dkey=>$row){
			$results[] = array('form_name'=>$dkey, 'custom_form_label'=>$row);
		}
	}
	// Return array
	return $results;
}
