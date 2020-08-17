<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = Survey::getSurveyId();
}

// Ensure the survey_id belongs to this project and that Post method was used
if (!Survey::checkSurveyProject($_GET['survey_id']) || $_SERVER['REQUEST_METHOD'] != "POST" || !isset($_POST['participants']))
{
	redirect(APP_PATH_WEBROOT . "Surveys/invite_participants.php?pid=" . PROJECT_ID);
}

// Ensure that participant_id's are all numerical
foreach (explode(",", $_POST['participants']) as $this_part)
{
	if (!is_numeric($this_part)) redirect(APP_PATH_WEBROOT . "Surveys/invite_participants.php?pid=" . PROJECT_ID);
}



// Check if this is a follow-up survey
$isFollowUpSurvey = ($_GET['survey_id'] != $Proj->firstFormSurveyId);

// Obtain current event_id
$_GET['event_id'] = getEventId();

// If cron is not running, then stop here
if (!Cron::checkIfCronsActive()) {
	print  "ERROR: The REDCap cron job is not running. The cron job must be running in order to send all survey invitations.
			If you are not a REDCap administrator, then please notify your local REDCap administrator about this issue.";
	exit;
}

// Get the delivery type - default to EMAIL
$delivery_methods = Survey::getDeliveryMethods(true);
if (!($twilio_enabled && $Proj->twilio_enabled_surveys) || !isset($_POST['delivery_type']) || !isset($delivery_methods[$_POST['delivery_type']])) {
	$_POST['delivery_type'] = 'EMAIL';
}

// Get user info
$user_info = User::getUserInfo($userid);

// Set page header text
if ($_POST['emailSendTime'] != 'IMMEDIATELY') {
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB'
		|| $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		$hdr_icon = ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE') ? "<img src='".APP_PATH_IMAGES."phone.png'> ": "";
		renderPageTitle("$hdr_icon{$lang['survey_708']}");
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		renderPageTitle("<img src='".APP_PATH_IMAGES."phone.gif'> {$lang['survey_709']}");
	} else {
		// EMAIL
		renderPageTitle("<img src='".APP_PATH_IMAGES."clock_frame.png'> {$lang['survey_334']}");
	}
} else {
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		$hdr_icon = ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE') ? "<img src='".APP_PATH_IMAGES."phone.png'> ": "";
		renderPageTitle("$hdr_icon{$lang['survey_698']}");
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		renderPageTitle("<img src='".APP_PATH_IMAGES."phone.gif'> {$lang['survey_699']}");
	} else {
		// EMAIL
		renderPageTitle("<img src='".APP_PATH_IMAGES."email.png'> {$lang['survey_138']}");
	}
}


// Get email address for each participant_id (whether it's an initial survey or follow-up survey)
$participant_emails_ids = $participant_delivery_pref = array();
if ($isFollowUpSurvey) {
	// Follow-up surveys (may not have an email stored for this specific survey/event, so can't simply query participants table)
	$participant_records = Survey::getRecordFromPartId(explode(",",$_POST['participants']));
	$responseAttr = Survey::getResponsesEmailsIdentifiers($participant_records, $_GET['survey_id']);
	foreach ($participant_records as $partId=>$record) {
		$participant_emails_ids[$partId] = $responseAttr[$record]['email'];
		$participant_delivery_pref[$partId] = $responseAttr[$record]['delivery_preference'];
	}
} else {
	// Initial survey: Obtain email from participants table
	$sql = "select participant_email, participant_id, delivery_preference from redcap_surveys_participants
			where survey_id = {$_GET['survey_id']} and participant_id in (".prep_implode(explode(",",$_POST['participants'])).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$participant_emails_ids[$row['participant_id']] = $row['participant_email'];
		$participant_delivery_pref[$row['participant_id']] = $row['delivery_preference'];
	}
	$participant_records = Survey::getRecordFromPartId(array_keys($participant_emails_ids));
}


// Set the From address for the emails sent
$fromEmail = $_POST['emailFrom'];

// Validate email
$allProjectEmails = User::getEmailAllProjectUsers($project_id);
if (!isEmail($fromEmail) || !in_array($fromEmail, $allProjectEmails)) {
	$fromEmail = $user_email;
}

// Perform filtering on email subject/content
$_POST['emailTitle'] = decode_filter_tags($_POST['emailTitle']);
$_POST['emailCont']  = decode_filter_tags($_POST['emailCont']);
// Remove line breaks because rich text editor doesn't have them
$_POST['emailCont'] = str_replace(array("\r", "\n"), array('', ''), $_POST['emailCont']);

## PIPING CHECK: See if any fields have been inserted into the subject or content for piping purposes
$doPiping = $doPipingContent = $doPipingSubject = false;
$piping_fields = array();
$piping_fields_content = array();
$piping_fields_subject = array();
// EMAIL CONTENT PIPING
if (strpos($_POST['emailCont'], '[') !== false && strpos($_POST['emailCont'], ']') !== false) {
	// Parse the label to pull out the field names
	$piping_fields_content = array_keys(getBracketedFields($_POST['emailCont'], true, true, true));
	// Validate the field names
	foreach ($piping_fields_content as $key=>$this_field) {
		// If not a valid field name, then remove
		if (!isset($Proj->metadata[$this_field])) unset($piping_fields_content[$key]);
	}
	// Set flag to true if some fields were indeed piped
	if (!empty($piping_fields_content)) {
		$doPiping = $doPipingContent = true;
	}
	if (!$doPipingContent && Piping::containsSpecialTags($_POST['emailCont'])) {
		$doPiping = $doPipingContent = true;
	}
}
// EMAIL SUBJECT PIPING
if (strpos($_POST['emailTitle'], '[') !== false && strpos($_POST['emailTitle'], ']') !== false) {
	// Parse the label to pull out the field names
	$piping_fields_subject = array_keys(getBracketedFields($_POST['emailTitle'], true, true, true));
	// Validate the field names
	foreach ($piping_fields_subject as $key=>$this_field) {
		// If not a valid field name, then remove
		if (!isset($Proj->metadata[$this_field])) unset($piping_fields_subject[$key]);
	}
	// Set flag to true if some fields were indeed piped
	if (!empty($piping_fields_subject)) {
		$doPiping = $doPipingSubject = true;
	}
	if (!$doPipingSubject && Piping::containsSpecialTags($_POST['emailTitle'])) {
		$doPiping = $doPipingSubject = true;
	}
}

// Initialize array where key will be participant_id and value will be email_id
$email_ids_by_participant = array();

$log_descrip = ($_POST['delivery_type'] == 'EMAIL') ? "(via email)" : ($_POST['delivery_type'] == 'VOICE_INITIATE' ? "(via voice call)" : ($_POST['delivery_type'] == 'PARTICIPANT_PREF' ? "(via participant preference)" : "(via SMS)"));


if (!$doPiping)
{
	// NO PIPING, so add email info to table only as a single row
	// OR sending via ajax because Cron is not working, so it will insert any piped data in ajax request.
	$sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_sender,
			email_account, email_static, email_sent, delivery_type, append_survey_link, email_sender_display) values
			({$_GET['survey_id']}, '" . db_escape($_POST['emailTitle']) . "',
			'" . db_escape($_POST['emailCont']) . "', {$user_info['ui_id']},
			null, ".checkNull($fromEmail).", null,
			'" . db_escape($_POST['delivery_type']) . "', '".Survey::getAppendSurveyLink($_POST['delivery_type'])."', ".checkNull($_POST['email_sender_display']).")";
	db_query($sql);
	// Get email_id
	$email_id = db_insert_id();
	// Loop through each participant and add the same email_id to all in $email_ids_by_participant
	foreach (array_keys($participant_emails_ids) as $this_part) {
		$email_ids_by_participant[$this_part] = $email_id;
	}
	// Logging
	Logging::logEvent($sql,"redcap_surveys_emails","MANAGE",null,"email_id = $email_id,\nsurvey_id = {$_GET['survey_id']},\nevent_id = {$_GET['event_id']}","Send survey invitation to participants $log_descrip");
}
else
{
	// DO PIPING, so insert once for EACH participant to record each's unique values for email subject/content
	// Get array of all piping fields and records for data pull
	$piping_data = array();
	if (!empty($participant_records)) {
		$piping_fields = array_unique(array_merge($piping_fields_subject, $piping_fields_content));
		$piping_data = Records::getData(PROJECT_ID, 'array', $participant_records, $piping_fields);
	}
	// Get form name
	$form = $Proj->surveys[$_GET['survey_id']]['form_name'];
	// Loop through each participant/record and customize the email subject/content with piped data
	foreach (array_keys($participant_emails_ids) as $this_part)
	{
		// Set record (if exists)
		$this_record = isset($participant_records[$this_part]) ? $participant_records[$this_part] : "";
		if ($this_record != "") {
			// Set subject
			$this_subject = ($doPipingSubject) ? strip_tags(Piping::replaceVariablesInLabel($_POST['emailTitle'], $this_record, $_GET['event_id'], 1, $piping_data, true, null, false, "", 1, false, false, $form, $this_part)) : $_POST['emailTitle'];
			// Set content
			$this_content = ($doPipingContent) ? Piping::replaceVariablesInLabel($_POST['emailCont'], $this_record, $_GET['event_id'], 1, $piping_data, true, null, false, "", 1, false, false, $form, $this_part) : $_POST['emailCont'];
		} else {
			// Set subject
			$this_subject = ($doPipingSubject) ? strip_tags(Piping::pipeSpecialTags($_POST['emailTitle'], PROJECT_ID, null, null, null, null, false, $this_part, null, true)) : $_POST['emailTitle'];
			// Set content
			$this_content = ($doPipingContent) ? Piping::pipeSpecialTags($_POST['emailCont'], PROJECT_ID, null, null, null, null, false, $this_part, null, true) : $_POST['emailCont'];
		}
		// Insert email into table
		$sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_sender,
				email_account, email_static, email_sent, delivery_type, append_survey_link, email_sender_display) values
				({$_GET['survey_id']}, '" . db_escape($this_subject) . "',
				'" . db_escape($this_content) . "', {$user_info['ui_id']},
				null, ".checkNull($fromEmail).", null,
				'" . db_escape($_POST['delivery_type']) . "', '".Survey::getAppendSurveyLink($_POST['delivery_type'])."', ".checkNull($_POST['email_sender_display']).")";
		db_query($sql);
		// Get email_id
		$email_id = db_insert_id();
		// Add to array
		$email_ids_by_participant[$this_part] = $email_id;
	}
	// Logging
	Logging::logEvent($sql,"redcap_surveys_emails","MANAGE",null,"email_ids = ".implode(",", $email_ids_by_participant).",\nsurvey_id = {$_GET['survey_id']},\nevent_id = {$_GET['event_id']}","Send survey invitation to participants $log_descrip");
}


// Get count of recipients
$recipCount = count($participant_emails_ids);


## SCHEDULE ALL EMAILS: Since cron is running, offload all emails to the cron emailer (even those to be sent immediately)
// If specified exact date/time, convert timestamp from mdy to ymd for saving in backend
if ($_POST['emailSendTimeTS'] != '') {
	list ($this_date, $this_time) = explode(" ", $_POST['emailSendTimeTS']);
	$_POST['emailSendTimeTS'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
}
if ($_POST['reminder_exact_time'] != '') {
	list ($this_date, $this_time) = explode(" ", $_POST['reminder_exact_time']);
	$_POST['reminder_exact_time'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
}

// Set the send time for the emails
$sendTime = ($_POST['emailSendTime'] == 'IMMEDIATELY') ? NOW : $_POST['emailSendTimeTS'];

## REMOVE INVITATIONS ALREADY QUEUED: If any participants have already been scheduled,
## then remove all those instances so they can be scheduled again here (first part of query returns those where
## record=null - i.e. from initial survey Participant List, and second part return those that are existing records).
Survey::removeQueuedSurveyInvitations($_GET['survey_id'], $_GET['event_id'], array_keys($participant_emails_ids));



## REMINDERS
$participantSendTimes = array(0=>$sendTime);
## If reminders are enabled, then add times of all reminders in array
$addReminders = (isset($_POST['reminder_type']) && $_POST['reminder_type'] != '');
if ($addReminders) {
	// Set reminder num
	if (!is_numeric($_POST['reminder_num'])) $_POST['reminder_num'] = 1;
	// Loop through each reminder
	$thisReminderTime = $sendTime;
	for ($k = 1; $k <= $_POST['reminder_num']; $k++) {
		// Get reminder time for next reminder
		$participantSendTimes[$k] = $thisReminderTime = SurveyScheduler::calculateReminderTime($_POST, $thisReminderTime);
	}
}


// Get the instance number if using repeating events/forms
$ParticipantInstanceNum = array();
if ($Proj->hasRepeatingFormsEvents()) 
{
	$sql = "select p.participant_id, r.instance from redcap_surveys_participants p, redcap_surveys_response r
			where p.participant_id = r.participant_id and p.participant_email is not null
			and p.participant_id in (".prep_implode(array_keys($participant_emails_ids)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$ParticipantInstanceNum[$row['participant_id']] = $row['instance'];
	}
}


## ADD PARTICIPANTS TO THE EMAIL QUEUE (i.e. the emails_recipients table - since email_sent=NULL)
$insertErrors = 0;
foreach (array_keys($participant_emails_ids) as $this_part)
{
	// If using participant preference for "delivery method", then get the person's delivery preference
	if ($_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		$this_part_pref = (isset($participant_delivery_pref[$this_part])) ? $participant_delivery_pref[$this_part] : 'EMAIL' ;
	} else {
		$this_part_pref = $_POST['delivery_type'];
	}
	// Add to emails_recipients table
	$sql = "insert into redcap_surveys_emails_recipients (email_id, participant_id, delivery_type)
			values (".$email_ids_by_participant[$this_part].", $this_part, '" . db_escape($this_part_pref) . "')";
	if (db_query($sql)) {
		// Get email_recip_id
		$email_recip_id = db_insert_id();
		// Get record name (may not have one if this is an initial survey's Participant List)
		$this_record = (isset($participant_records[$this_part])) ? $participant_records[$this_part] : "";
		// Now add to scheduler_queue table (loop through orig invite + any reminder invites)
		foreach ($participantSendTimes as $reminder_num=>$thisSendTime) {
			// Get the instance number if using repeating events/forms
			$instance = isset($ParticipantInstanceNum[$this_part]) ? $ParticipantInstanceNum[$this_part] : 1;
			// Add to table
			$sql = "insert into redcap_surveys_scheduler_queue (email_recip_id, record, scheduled_time_to_send, reminder_num, instance)
					values ($email_recip_id, ".checkNull($this_record).", '".db_escape($thisSendTime)."', '".db_escape($reminder_num)."', $instance)";
			if (!db_query($sql)) $insertErrors++;
		}
	} else {
		$insertErrors++;
	}
}

// Confirmation text for IMMEDIATE sending
if ($_POST['emailSendTime'] == 'IMMEDIATELY')
{
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_694']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'),
						($recipCount > 1 ? "$recipCount {$lang['survey_696']}" : "$recipCount {$lang['survey_697']}")
					) .
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_695'])
				);
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_700']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'),
						($recipCount > 1 ? "$recipCount {$lang['survey_702']}" : "$recipCount {$lang['survey_703']}")
					) .
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_701'])
				);
	} else {
		// EMAIL
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_328']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'),
						($recipCount > 1 ? "$recipCount {$lang['survey_696']}" : "$recipCount {$lang['survey_697']}")
					) .
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_329'])
				);
	}
}
// Confirmation text for SCHEDULING the emails to be sent
else
{
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_WEB' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_704']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_705']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " .
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_706']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_707']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " .
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	} else {
		// EMAIL
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_331']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_332']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " .
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	}
}