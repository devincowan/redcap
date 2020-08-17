<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

## DISPLAY TABLE IN DIALOG
if (!isset($_POST['action']))
{
	// Get survey_id (if submitted)
	if (isset($_GET['survey_id']) && $_GET['survey_id'] != '' && !$Proj->validateSurveyId($_GET['survey_id'])) {
		$_GET['survey_id'] = $Proj->firstFormSurveyId;
	}

	// Survey Notifications: Return array of surveys/users with attributes regarding email notifications for survey responses
	$endSurveyNotify = Survey::getSurveyNotificationsList();

	// Create array of all surveys (exclude orphaned ones)
	$surveys = array();
	// Display table with all project users for each survey
	foreach ($Proj->forms as $form_name=>$form_attr)
	{
		// If the instrument is not a survey, then skip it
		if (!(isset($form_attr['survey_id']) && is_numeric($form_attr['survey_id']))) continue;
		// Add to array
		$surveys[$form_attr['survey_id']] = $Proj->surveys[$form_attr['survey_id']]['title'];
	}

	// Instructions
	$h = $lang['setup_129'] .
		 // If only display one survey, then given option to display all surveys
		 ((isset($_GET['survey_id']) && $_GET['survey_id'] != '' && count($surveys) > 1)
			? RCView::div(array('style'=>'padding-top:8px;'),
				RCView::b($lang['survey_373']) . RCView::SP . RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;','onclick'=>'displayTrigNotifyPopup()'), $lang['survey_372'])
			  )
			: ""
		 );

	// print_array($Proj->surveys);
	//print_r($endSurveyNotify);
	// Display table with all project users for each survey
	foreach ($surveys as $this_survey_id=>$survey_title)
	{
		// If survey_id was sent in request, then only show that specific survey
		if (isset($_GET['survey_id']) && $_GET['survey_id'] != '' && $_GET['survey_id'] != $this_survey_id) continue;

		// First, build rows for each user in the project
		$r = '';
		foreach ($endSurveyNotify as $this_user=>$attr)
		{
			// Is user already checked for this survey?
			$checked1 = (isset($attr['surveys'][$this_survey_id]) && $attr['surveys'][$this_survey_id] == '1') ? "checked" : "";
			$checked2 = (isset($attr['surveys'][$this_survey_id]) && $attr['surveys'][$this_survey_id] == '2') ? "checked" : "";
			$checked3 = (isset($attr['surveys'][$this_survey_id]) && $attr['surveys'][$this_survey_id] == '3') ? "checked" : "";
			// Email drop-down list
			// Radio for email1
			$user_emails = array();
			if ($attr['email1'] != '') $user_emails[0] = '-- '.$lang['setup_128'].' --';
			if ($attr['email1'] != '') $user_emails[1] = $attr['email1'] . " " . $lang['setup_130'];
			if ($attr['email2'] != '') $user_emails[2] = $attr['email2'] . " " . $lang['setup_131'];
			if ($attr['email3'] != '') $user_emails[3] = $attr['email3'] . " " . $lang['setup_132'];
			if (empty($user_emails)) {
				$user_email_dropdown = "";
			} else {
				$user_email_dropdown = RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'max-width:100%;width:100%;',
				'onchange'=>"endSurvTrigSave('".js_escape($this_user)."',this.value,$this_survey_id);"), $user_emails, isset($attr['surveys'][$this_survey_id]) ? $attr['surveys'][$this_survey_id] : '');
			}
			// Build row for user
			$r .=	RCView::tr('',
						RCView::td(array('class'=>'data','style'=>'padding:1px 5px;'),
							RCView::span(array('style'=>'vertical-align:middle;'),
								// Display username
								RCView::b($this_user) .
								// Display email
								RCView::span(array('style'=>'margin-left:8px;font-size:11px;color:#222;'),
									($attr['name'] != ''
										? "(" . $attr['name'] . ")"
										: RCView::span(array('style'=>'color:#888;'), "[" . $lang['setup_125'] . "]")
									)
								)
							)
						) .
						RCView::td(array('class'=>'data','style'=>'padding:4px 8px;color:#222;overflow: visible; white-space: normal;word-break:break-all;'),
							// Email drop-down of user's email addresses
							$user_email_dropdown
						) .
						RCView::td(array('class'=>'data','style'=>'padding:4px 2px;text-align:center;color:red;font-size:11px;font-weight:bold;'),
							// Save status span
							RCView::span(array('id'=>'triggerEndSurv-svd-'.$this_survey_id.'-'.$this_user, 'style'=>'display:none;'),
								$lang['design_243']
							) .
							// Enabled/disabled images
							RCView::img(array('id'=>"triggerEnabled_$this_survey_id-$this_user", 'style'=>'vertical-align:middle;'.(isset($attr['surveys'][$this_survey_id]) ? '' : 'display:none;'), 'src'=>'tick_circle_frame2.png')) .
							RCView::img(array('id'=>"triggerDisabled_$this_survey_id-$this_user", 'style'=>'vertical-align:middle;'.(isset($attr['surveys'][$this_survey_id]) ? 'display:none;' : ''), 'src'=>'circle_gray.png'))
						)
					);
		}

		// If no users to display, then display row saying this
		if ($r == '') {
			$r .=	RCView::tr('',
						RCView::td(array('class'=>'data', 'style'=>'padding:10px;color:#666;', 'colspan'=>'3'), $lang['control_center_191'])
					);
		}

		// Build table for this survey
		$h .= 	RCView::table(array('cellspacing'=>'0','class'=>'form_border','style'=>'table-layout:fixed;margin:20px 0;width:100%;'),
					// Table header
					RCView::tr('',
						RCView::td(array('class'=>'header','style'=>'color:#800000;'),
							strip_tags($survey_title)
						) .
						RCView::td(array('class'=>'header','style'=>'padding-left:12px;width:320px;font-weight:normal;'),
							RCView::img(array('src'=>'email.png')) .
							RCView::span(array('style'=>'margin-left:3px;color:#000066;'), $lang['setup_127'])
						) .
						RCView::td(array('class'=>'header','style'=>'font-size:11px;text-align:center;font-weight:normal;width:80px;'),
							$lang['setup_126']
						)
					) .
					// All rows of users
					$r
				);
	}

	// Set dialog title
	$t = RCView::img(array('src'=>'email.png','style'=>'vertical-align:middle;')) .
		 RCView::span(array('style'=>'vertical-align:middle;'), $lang['setup_55']);

	// Send back JSON with info
	exit(json_encode_rc(array('title'=>$t, 'content'=>$h)));
}




## SAVE TRIGGER FOR END-SURVEY EMAIL RESPONSE
if ($_POST['action'] == "endsurvey_email" && is_numeric($_POST['value']))
{
	// Get survey_id
	if (!isset($_GET['survey_id']) || (isset($_GET['survey_id']) && !$Proj->validateSurveyId($_GET['survey_id']))) {
		$_GET['survey_id'] = $Proj->firstFormSurveyId;
	}

	// Save value
	if ($_POST['value'] > 0) {
		$email_acct = ($_POST['value'] == '3' ? 'EMAIL_TERTIARY' : ($_POST['value'] == '2' ? 'EMAIL_SECONDARY' : 'EMAIL_PRIMARY'));
		$sql = "insert into redcap_actions (project_id, survey_id, action_trigger, action_response, recipient_id) values
				($project_id, {$_GET['survey_id']}, 'ENDOFSURVEY', '$email_acct', (select ui_id from redcap_user_information
				where username = '".db_escape($_POST['username'])."' limit 1))
				on duplicate key update action_response = '$email_acct'";
		$log_descrip = "Enabled survey notification for user";
	} else {
		$sql = "delete from redcap_actions where project_id = $project_id and survey_id = {$_GET['survey_id']}
				and action_trigger = 'ENDOFSURVEY' and action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY')
				and recipient_id = (select ui_id from redcap_user_information where username = '".db_escape($_POST['username'])."')";
		$log_descrip = "Disabled survey notification for user";
	}
	if (!db_query($sql)) exit('0');

	// Log the event
	Logging::logEvent($sql, "redcap_actions", "MANAGE", $_POST['username'], "username = '{$_POST['username']}'\nsurvey_id = {$_GET['survey_id']}", $log_descrip);

	// Send back JSON with info
	exit(json_encode_rc(array('survey_notifications_enabled'=>(Survey::surveyNotificationsEnabled() ? '1' : '0'))));
}

// Error response
exit('0');