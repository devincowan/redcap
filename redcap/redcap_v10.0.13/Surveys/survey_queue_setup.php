<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$popupContent = $popupTitle = $survey_queue_enabled = "";


## RENDER DIALOG CONTENT FOR SETTING UP SURVEY QUEUE
if (isset($_POST['action']) && $_POST['action'] == "view")
{
	// Response
	$popupTitle = RCView::img(array('src'=>'list_red.gif', 'style'=>'vertical-align:middle;position:relative;top:1px;')) .
				  RCView::span(array('style'=>'margin-left:3px;color:#800000;vertical-align:middle;'), $lang['survey_525']);
	$popupContent = Survey::displaySurveyQueueSetupTable();
}


## SAVE SURVEY QUEUE SETTINGS
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
	// Loop through Post elements to find the sqcondoption-andor-- one's, which we'll use as the basis for each row processed
	$sql_all = array();
	foreach ($_POST as $key=>$val) {
		if (strpos($key, 'sqcondoption-andor-') === false) continue;
		// Get event_id and survey_id
		list ($survey_id, $event_id) = explode("-", substr($key, 19), 2);
		// Get other values
		$andOr = ($val == 'OR') ? 'OR' : 'AND';
		$active = (isset($_POST["sqactive-$survey_id-$event_id"]) && $_POST["sqactive-$survey_id-$event_id"] == 'on') ? '1' : '0';
		list ($surveyCompSurveyId, $surveyCompEventId) = explode("-", $_POST["sqcondoption-surveycompleteids-$survey_id-$event_id"], 2);
		$conditionLogic = trim(html_entity_decode($_POST["sqcondlogic-$survey_id-$event_id"], ENT_QUOTES));
		$autoStart = (isset($_POST["ssautostart-$survey_id-$event_id"]) && $_POST["ssautostart-$survey_id-$event_id"] == 'on') ? '1' : '0';
		// Build SQL
		if ($conditionLogic == '' && ($surveyCompSurveyId == '' || $surveyCompEventId == '')) {
			// Delete from table (if in table)
			$sql_all[] = $sql = "delete from redcap_surveys_queue where survey_id = '".db_escape($survey_id)."' and event_id = '".db_escape($event_id)."'";
		} else {
			// Insert/update
			$sql_all[] = $sql = "insert into redcap_surveys_queue (survey_id, event_id, active, condition_surveycomplete_survey_id, condition_surveycomplete_event_id,
					condition_andor, condition_logic, auto_start) values
					('".db_escape($survey_id)."', '".db_escape($event_id)."', $active, ".checkNull($surveyCompSurveyId).", ".checkNull($surveyCompEventId).", '$andOr',
					".checkNull($conditionLogic).", $autoStart)
					on duplicate key update active = $active, condition_surveycomplete_survey_id = ".checkNull($surveyCompSurveyId).",
					condition_surveycomplete_event_id = ".checkNull($surveyCompEventId).", condition_andor = '$andOr',
					condition_logic = ".checkNull($conditionLogic).", auto_start = $autoStart";
		}
		$q = db_query($sql);
	}

	// Get custom text provided (if any)
	$_POST['survey_queue_custom_text'] = trim($_POST['survey_queue_custom_text']);
	$sql_all[] = $sql = "update redcap_projects set survey_queue_custom_text = ".checkNull($_POST['survey_queue_custom_text'])."
						 where project_id = ".PROJECT_ID;
	$q = db_query($sql);

	// Response
	$popupTitle = $lang['design_243'];
	$popupContent = RCView::img(array('src'=>'tick.png')) .
					RCView::span(array('style'=>"color:green;"), $lang['survey_528']);
	$survey_queue_enabled = (Survey::surveyQueueEnabled()) ? '1' : '0';

	// Log the event
	Logging::logEvent(implode(";\n", $sql_all), "redcap_surveys_queue", "MANAGE", $project_id, "project_id = $project_id", "Edit settings for survey queue");
}

// Send back JSON response
print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle, 'survey_queue_enabled'=>$survey_queue_enabled));