<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Confirm the participant_id, survey_id, and event_id
$sql = "select 1 from redcap_surveys s, redcap_surveys_participants p
		where s.project_id = $project_id and s.survey_id = p.survey_id and s.survey_id = '".db_escape($_POST['survey_id'])."'
		and p.event_id = '".db_escape($_POST['event_id'])."' and p.participant_id = '".db_escape($_POST['participant_id'])."'";
$q = db_query($sql);
if (!db_num_rows($q)) exit("0");

// Get first event_id in the current arm using the given event_id
$first_event_id = $Proj->getFirstEventIdInArmByEventId($_POST['event_id']);
// Get first survey_id
$first_survey_id = $Proj->firstFormSurveyId;
// Is this the first event and first survey?
$is_first_survey_event = ($first_event_id == $_POST['event_id'] && $first_survey_id == $_POST['survey_id']);

// Make sure to seed the participant row of the first event and first survey, just in case
if (!$is_first_survey_event && $_POST['record'] != '' && $first_survey_id != '') {
	list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($first_survey_id, $_POST['record'], $first_event_id);
}

// Set this preference on all events/surveys for this record
$sql1 = "update redcap_surveys_participants set delivery_preference = '".db_escape($_POST['delivery_preference'])."'
		where participant_id = '".db_escape($_POST['participant_id'])."'";
$sql2 = "update redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s, redcap_surveys t,
		redcap_surveys_participants a, redcap_surveys_response b
		set a.delivery_preference = '".db_escape($_POST['delivery_preference'])."'
		where p.participant_id = '".db_escape($_POST['participant_id'])."' and r.participant_id = p.participant_id
		and s.survey_id = p.survey_id and s.project_id = t.project_id and t.survey_id = a.survey_id
		and a.participant_id = b.participant_id and b.record = r.record";
if (db_query($sql1) && db_query($sql2)) {
	// Logging
	Logging::logEvent("$sql1;\n$sql2","redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = {$_POST['participant_id']}","Change participant invitation preference");
	// Return html for delivery preference icon
	print Survey::getDeliveryPrefIcon($_POST['delivery_preference']);
} else {
	print "0";
}