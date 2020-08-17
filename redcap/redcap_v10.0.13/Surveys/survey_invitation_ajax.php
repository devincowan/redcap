<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Validate values
if (!(isset($_POST['ssq_ids']) || (is_numeric($_POST['email_recip_id']) && is_numeric($_POST['reminder_num']))) || !isset($_POST['action'])) exit("0");

// Defaults
$content = '';
$title   = '';

// View confirmation to delete invitation
if ($_POST['action'] == 'view_delete')
{
	// Obtain timestamp of invitation and sender email
	$sql = "select q.scheduled_time_to_send,
			if (p.participant_email is null, r.static_email, p.participant_email) as email
			from redcap_surveys_scheduler_queue q, redcap_surveys_participants p,
			redcap_surveys_emails_recipients r where q.email_recip_id = r.email_recip_id
			and r.participant_id = p.participant_id and q.email_recip_id = ".$_POST['email_recip_id']."
			and q.reminder_num = ".$_POST['reminder_num'];
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	// Set values
	$email = db_result($q, 0, 'email');
	$sendtime = db_result($q, 0, 'scheduled_time_to_send');
	$title = $lang['survey_486'];
	$content = 	$lang['survey_487'] . " " . RCView::b($email) . " " . $lang['global_51'] . " " .
				RCView::b(DateTimeRC::format_ts_from_ymd($sendtime)) . $lang['questionmark'] . " " . $lang['survey_489'];
}

// Delete the invitation
elseif ($_POST['action'] == 'delete')
{
	// Obtain some context variables for logging purposes
	$sql = "select q.ssq_id, q.record, if (s.survey_id is null, p2.survey_id, s.survey_id) as survey_id, 
			if (s.event_id is null, p2.event_id, s.event_id) as event_id, 
			if (p.project_id is null, p3.project_id, p.project_id) as project_id, q.reminder_num
			from (redcap_surveys_emails_recipients e, redcap_surveys_scheduler_queue q)
			left join redcap_surveys_scheduler s on q.ss_id = s.ss_id
			left join redcap_surveys p on p.survey_id = s.survey_id
			left join redcap_surveys_participants p2 on p2.participant_id = e.participant_id
			left join redcap_surveys p3 on p2.survey_id = p3.survey_id
			where q.email_recip_id = ".$_POST['email_recip_id']." and q.reminder_num = ".$_POST['reminder_num']."
			and e.email_recip_id = q.email_recip_id limit 1";
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	$row = db_fetch_assoc($q);
	// Mark it as deleted in the scheduler_queue table
	$sql = "update redcap_surveys_scheduler_queue set status = 'DELETED'
			where ssq_id = ".$row['ssq_id'];
	if (!db_query($sql)) exit("0");	
	if (db_affected_rows() > 0) {
		// Log the deletion
		Logging::logEvent($sql,"redcap_surveys_scheduler_queue","MANAGE",$row['record'],
			"survey_id = {$row['survey_id']},\nevent_id = {$row['event_id']},\nrecord = '{$row['record']}',\nssq_id = {$row['ssq_id']}",
			"Delete scheduled survey invitation", "", USERID, PROJECT_ID, true, $row['event_id']);
	}
	// Output dialog content
	$title = $lang['survey_486'];
	$content = 	RCView::div(array('style'=>'color:green;'),
					RCView::img(array('src'=>'tick.png')) .
					$lang['survey_488']
				);
}

// Delete MULTIPLE invitations
elseif ($_POST['action'] == 'delete_multiple')
{
	$ssq_ids = array();
	foreach (explode(",", $_POST['ssq_ids']) as $ssq_id) {
		$ssq_id = trim($ssq_id);
		if (!is_numeric($ssq_id)) continue;
		$ssq_ids[] = $ssq_id;
	}
	// Obtain some context variables for logging purposes
	$sql = "select q.ssq_id, p.event_id, p.survey_id, q.record
			from redcap_surveys_emails_recipients e, redcap_surveys_scheduler_queue q, redcap_surveys_participants p
			where q.ssq_id in (".prep_implode($ssq_ids).") and e.email_recip_id = q.email_recip_id 
			and e.participant_id = p.participant_id and p.survey_id in (".prep_implode(array_keys($Proj->surveys)).")";
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	$deleted = 0;
	while ($row = db_fetch_assoc($q)) {
		// Mark it as deleted in the scheduler_queue table
		$sql = "update redcap_surveys_scheduler_queue set status = 'DELETED'
				where ssq_id = ".$row['ssq_id'];
		if (!db_query($sql)) exit("0");	
		if (db_affected_rows() > 0) {
			$deleted++;
			// Log the deletion
			Logging::logEvent($sql,"redcap_surveys_scheduler_queue","MANAGE",$row['record'],
				"survey_id = {$row['survey_id']},\nevent_id = {$row['event_id']}".($row['record'] == '' ? '' : ",\nrecord = '{$row['record']}'").",\nssq_id = {$row['ssq_id']}",
				"Delete scheduled survey invitation", "", USERID, PROJECT_ID, true, $row['event_id']);
		}
	}
	// Output dialog content
	$title = $lang['survey_1216'];
	$content = 	RCView::div(array('style'=>'color:green;'),
					RCView::img(array('src'=>'tick.png')) .
					$deleted . " " . $lang['survey_1217']
				);
}

// View confirmation to edit invitation time
if ($_POST['action'] == 'view_edit_time')
{
	// Obtain timestamp of invitation and sender email
	$sql = "select q.scheduled_time_to_send,
			if (p.participant_email is null, r.static_email, p.participant_email) as email
			from redcap_surveys_scheduler_queue q, redcap_surveys_participants p,
			redcap_surveys_emails_recipients r where q.email_recip_id = r.email_recip_id
			and r.participant_id = p.participant_id and r.email_recip_id = ".$_POST['email_recip_id']."
			and q.reminder_num = ".$_POST['reminder_num']." limit 1";
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	// Set values
	$email = db_result($q, 0, 'email');
	$sendtime = substr(db_result($q, 0, 'scheduled_time_to_send'), 0, -3);
	list ($sendtime_date, $sendtime_time) = explode(" ", $sendtime, 2);
	$title = $lang['survey_490'];
	$content = 	$lang['survey_491'] . " " . RCView::b($email) . " " . $lang['global_51'] . " " .
				RCView::b(DateTimeRC::format_ts_from_ymd($sendtime)) . $lang['questionmark'] . " " . $lang['survey_492'] .
				RCView::div(array('style'=>'padding-top:20px;'),
					RCView::b($lang['survey_493']) . RCView::br() .
					RCView::text(array('id'=>'newInviteTime','value'=>DateTimeRC::format_ts_from_ymd($sendtime_date) . " $sendtime_time",
						'onblur'=>"if(redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) window.newInviteTime=this.value;",
						'class'=>'x-form-text x-form-field','style'=>'width:120px;')) .
					"<span class='df'>".DateTimeRC::get_user_format_label()." H:M</span>"
				);
}

// Edit invitation time
elseif ($_POST['action'] == 'edit_time')
{
	// Obtain some context variables for logging purposes
	$sql = "select q.ssq_id, q.record, if (s.survey_id is null, p2.survey_id, s.survey_id) as survey_id, 
			if (s.event_id is null, p2.event_id, s.event_id) as event_id, 
			if (p.project_id is null, p3.project_id, p.project_id) as project_id, q.reminder_num
			from (redcap_surveys_emails_recipients e, redcap_surveys_scheduler_queue q)
			left join redcap_surveys_scheduler s on q.ss_id = s.ss_id
			left join redcap_surveys p on p.survey_id = s.survey_id
			left join redcap_surveys_participants p2 on p2.participant_id = e.participant_id
			left join redcap_surveys p3 on p2.survey_id = p3.survey_id
			where q.email_recip_id = ".$_POST['email_recip_id']." and q.reminder_num = ".$_POST['reminder_num']."
			and e.email_recip_id = q.email_recip_id limit 1";
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	$row = db_fetch_assoc($q);
	// Edit the time in scheduler_queue table
	$sendtime = DateTimeRC::format_ts_to_ymd(trim($_POST['newInviteTime']));
	$sql = "update redcap_surveys_scheduler_queue set scheduled_time_to_send = '".db_escape($sendtime).":00'
			where ssq_id = ".$row['ssq_id'];
	if (!db_query($sql)) exit("0");	
	if (db_affected_rows() > 0) {
		// Log the deletion
		Logging::logEvent($sql,"redcap_surveys_scheduler_queue","MANAGE",$row['record'],
			"survey_id = {$row['survey_id']},\nevent_id = {$row['event_id']},\nrecord = '{$row['record']}',\nssq_id = {$row['ssq_id']}",
			"Modify send time for scheduled survey invitation", "", USERID, PROJECT_ID, true, $row['event_id']);
	}
	// Output dialog content
	$title = $lang['survey_490'];
	$content = 	RCView::div(array('style'=>'color:green;'),
					RCView::img(array('src'=>'tick.png')) .
					$lang['survey_494']
				);
}


// Return JSON
print '{"content":"'.js_escape2($content).'","title":"'.js_escape2($title).'"}';