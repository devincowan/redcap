<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";


// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();

// Validate form, event_id, and survey_id
if (!$Proj->validateEventId($_GET['event_id']) || !$Proj->validateSurveyId($_GET['survey_id']) || !isset($Proj->forms[$_POST['form']]))
{
	exit("0");
}

// Append --# to record name for DDE users
$_POST['record'] = addDDEending($_POST['record']);


## DISPLAY POP-UP CONTENT
if ($_POST['action'] == 'popup')
{
	## Set up email-to options
	$emailToDropdown = $phoneToDropdown = '';
	$emailToDropdownOptions = $phoneToDropdownOptions = array();

	// Get participant email from Participant List's original invitation, if any
	if (isset($Proj->forms[$Proj->firstForm]['survey_id']))
	{
		$sql = "select p.participant_email, p.participant_id from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
				where s.project_id = ".PROJECT_ID." and p.survey_id = s.survey_id and p.participant_id = r.participant_id
				and r.record = '".db_escape($_POST['record'])."' and s.form_name = '".$Proj->firstForm."' and p.event_id = ".$Proj->firstEventId."
				and p.participant_email is not null and p.participant_email != '' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$val = db_result($q, 0, "participant_email");
			$partId = db_result($q, 0, "participant_id");
			// If Participant Identifiers have been enabled, then it's okay to display the email address
			if ($enable_participant_identifiers) {
				$emailToDropdownOptions[$val] = $val . " " . $lang['survey_275'];
			}
			// If not enabled, then do NOT show email address but put participant_id as value with "undisclosed email address" displaying
			else {
				$emailToDropdownOptions[$partId] = $lang['survey_499'] . " " . $lang['survey_275'];
			}
		}
	}
	// Get email address if a field has been specified in project to capture participant's email
	$this_survey_email_participant_field = "";
	if (isset($Proj->surveys[$_GET['survey_id']]) && $Proj->surveys[$_GET['survey_id']]['email_participant_field'] != '' && isset($Proj->metadata[$Proj->surveys[$_GET['survey_id']]['email_participant_field']])) {
		$this_survey_email_participant_field = $Proj->surveys[$_GET['survey_id']]['email_participant_field'];
	}
	if ($survey_email_participant_field != '' || $this_survey_email_participant_field != '')
	{
		// Query record data to get field's value, if exists. (look over ALL events for flexibility)
		$sql = "select distinct value from redcap_data where project_id = $project_id and record = '".db_escape($_POST['record'])."'
				and field_name in ('".db_escape($survey_email_participant_field)."', '".db_escape($this_survey_email_participant_field)."') 
				and value != '' order by value";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				$val = $row['value'];
				if (!isset($emailToDropdownOptions[$val])) {
					$emailToDropdownOptions[$val] = $val . " " . $lang['survey_273'];
				}
			}
		}
	}
	// Get any emails used previously (static email address not connected to a participant_id or metadata field)
	$sql = "select e.static_email from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s,
			redcap_surveys_emails_recipients e where s.project_id = ".PROJECT_ID." and p.survey_id = s.survey_id
			and p.participant_id = r.participant_id and r.record = '".db_escape($_POST['record'])."'
			and p.participant_email is not null and p.participant_id = e.participant_id and e.static_email is not null";
	$q = db_query($sql);
	if (db_num_rows($q) > 0) {
		while ($row = db_fetch_assoc($q)) {
			if (!isset($emailToDropdownOptions[$row['static_email']])) {
				$emailToDropdownOptions[$row['static_email']] = $row['static_email'] . " " . $lang['survey_378'];
			}
		}
	}
	// Get HTML for email drop-down
	if (!empty($emailToDropdownOptions)) {
		$emailToDropdown =  RCView::select(array('class'=>'x-form-text x-form-field','style'=>'margin-bottom:5px;width:70%;max-width:510px;','id'=>'followupSurvEmailToDD','onchange'=>'inviteFollowupSurveyPopupSelectEmail(this);'),
								(array(''=>"-- ".$lang['survey_274']." --")+$emailToDropdownOptions), '', 500
							);
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			$emailToDropdown .= RCView::span(array('style'=>'margin-left:8px;color:#777;'), $lang['survey_783']);
		}
		$emailToDropdown .= RCView::br() . $lang['survey_276'] . RCView::SP . RCView::SP;
	}


	## TWILIO
	if ($twilio_enabled && $Proj->twilio_enabled_surveys)
	{
		// Get phone from Participant List's original invitation, if any
		if (isset($Proj->forms[$Proj->firstForm]['survey_id']))
		{
			$sql = "select p.participant_phone, p.participant_id from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
					where s.project_id = ".PROJECT_ID." and p.survey_id = s.survey_id and p.participant_id = r.participant_id
					and r.record = '".db_escape($_POST['record'])."' and s.form_name = '".$Proj->firstForm."' and p.event_id = ".$Proj->firstEventId."
					and p.participant_email is not null and p.participant_phone != '' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0)
			{
				$val = db_result($q, 0, "participant_phone");
				$partId = db_result($q, 0, "participant_id");
				// If Participant Identifiers have been enabled, then it's okay to display the email address
				if ($enable_participant_identifiers) {
					$phoneToDropdownOptions[$val] = formatPhone($val) . " " . $lang['survey_275'];
				}
				// If not enabled, then do NOT show phone number but put participant_id as value with "undisclosed email address" displaying
				else {
					$phoneToDropdownOptions[$partId] = $lang['survey_789'] . " " . $lang['survey_275'];
				}
			}
		}
		// Get phone if a field has been specified in project to capture participant's email
		if ($survey_phone_participant_field != '')
		{
			// Query record data to get field's value, if exists. (look over ALL events for flexibility)
			$sql = "select value from redcap_data where project_id = $project_id and record = '".db_escape($_POST['record'])."'
					and field_name = '$survey_phone_participant_field' and value != '' order by value limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				$val = db_result($q, 0);
				// Remove all non-numerals from phone numbers
				$val = preg_replace("/[^0-9]/", "", trim($val));
				if (!isset($phoneToDropdownOptions[$val])) {
					$phoneToDropdownOptions[$val] = formatPhone($val) . " " . $lang['survey_785'];
				}
			}
		}
		// Get any emails used previously (static email address not connected to a participant_id or metadata field)
		$sql = "select e.static_phone from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s,
				redcap_surveys_emails_recipients e where s.project_id = ".PROJECT_ID." and p.survey_id = s.survey_id
				and p.participant_id = r.participant_id and r.record = '".db_escape($_POST['record'])."'
				and p.participant_email is not null and p.participant_id = e.participant_id and e.static_phone is not null";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				if (!isset($phoneToDropdownOptions[$row['static_phone']])) {
					$phoneToDropdownOptions[$row['static_phone']] = formatPhone($row['static_phone']) . " " . $lang['survey_378'];
				}
			}
		}
		// Get HTML for email drop-down
		if (!empty($phoneToDropdownOptions)) {
			$phoneToDropdown =  RCView::select(array('class'=>'x-form-text x-form-field','style'=>' margin-bottom:5px;','id'=>'followupSurvPhoneToDD','onchange'=>'inviteFollowupSurveyPopupSelectPhone(this);'),
									(array(''=>"-- ".$lang['survey_787']." --")+$phoneToDropdownOptions), '', 500
								);
			if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
				$phoneToDropdown .= RCView::span(array('style'=>'margin-left:8px;color:#777;'), $lang['survey_783']);
			}
			$phoneToDropdown .= RCView::br() . $lang['survey_786'] . RCView::SP . RCView::SP;
		}
	}

	// Get delivery method
	$participantAttributes = Survey::getResponsesEmailsIdentifiers(array($_POST['record']), $_GET['survey_id']);
	$delivery_type = isset($participantAttributes[$_POST['record']]) ? $participantAttributes[$_POST['record']]['delivery_preference'] : 'EMAIL';
	
	// For new schedules, auto-add the survey-link text to the compose box
	if ($GLOBALS['isIE'] && vIE() < 11) {
		$emailContent = "{$lang['survey_1224']}\n\n{$lang['survey_134']}\n[survey-link]\n\n{$lang['survey_135']}\n[survey-url]\n\n{$lang['survey_137']}";
    } else {
		$emailContent = "<p>{$lang['survey_1224']}</p><p>{$lang['survey_134']}<br/>[survey-link]</p><p>{$lang['survey_135']}<br/>[survey-url]</p><p>{$lang['survey_137']}</p>";
	}

	// Create HTML content
	$html = RCView::fieldset(array('style'=>'padding-left:8px;background-color:#f3f5f5;border:1px solid #ccc;margin-bottom:10px;'),
				RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					RCView::img(array('src'=>'txt.gif')) .
					$lang['survey_340']
				) .
				RCView::div(array('style'=>'padding:3px 8px 8px 2px;'),
					// Survey title
					RCView::div(array('style'=>'color:#800000;'),
						RCView::b($lang['survey_310']) .
						RCView::span(array('style'=>'font-size:13px;margin-left:8px;'),
							// If survey title is blank (because using a logo instead), then insert the instrument name
							RCView::escape($Proj->surveys[$_GET['survey_id']]['title'] == ""
								? $Proj->forms[$Proj->surveys[$_GET['survey_id']]['form_name']]['menu']
								: $Proj->surveys[$_GET['survey_id']]['title']
							)
						)
					) .
					// Event name (if longitudinal)
					RCView::div(array('style'=>'color:#000066;padding-top:3px;' . ($longitudinal ? '' : 'display:none;')),
						RCView::b($lang['bottom_23']) .
						RCView::span(array('style'=>'font-size:13px;margin-left:8px;'),
							RCView::escape($Proj->eventInfo[$_GET['event_id']]['name_ext'])
						)
					)
				)
			) .
			// If TWILIO is enabled, give option to send as SMS or VOICE
			(!($twilio_enabled && $Proj->twilio_enabled_surveys) ? '' :
				RCView::fieldset(array('style'=>'padding:0 0 2px 8px;background-color:#f3f5f5;border:1px solid #ccc;margin-bottom:10px;'),
					RCView::legend(array('style'=>'color:#333;'),
						RCView::img(array('src'=>'arrow_right_curve.png', 'style'=>'margin-right:2px;')) .
						RCView::b($lang['survey_687']). " " . $lang['survey_691']
					) .
					RCView::div(array('style'=>'padding:6px 2px 6px 2px;'),
						RCView::select(array('name'=>'delivery_type', 'class'=>'x-form-text x-form-field', 'style'=>'', 'onchange'=>"setInviteDeliveryMethod(this);$('#followupSurvEmailMsg').trigger('blur');"),
							Survey::getDeliveryMethods(false, true, $delivery_type), $delivery_type, 200) .
							RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;',
								'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
					)
				)
			) .
			## SET TIME FOR SENDING EMAIL
			RCView::fieldset(array('style'=>'padding-left:8px;background-color:#f3f5f5;border:1px solid #ccc;margin-bottom:10px;'),
				RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					RCView::img(array('src'=>'clock_fill.png','style'=>'margin-right:3px;')) .
					$lang['survey_347']
				) .
				RCView::div(array('style'=>'padding:5px 8px 7px 2px;'),
					RCView::radio(array('name'=>'emailSendTime','value'=>'IMMEDIATELY','style'=>'','checked'=>'checked')) .
					$lang['survey_323'] . RCView::br() .
					RCView::radio(array('name'=>'emailSendTime','value'=>'EXACT_TIME','style'=>'','onclick'=>"if ($('#emailSendTimeTS').val().length<1) $('#emailSendTimeTS').focus();")) .
					$lang['survey_324'] .
					RCView::input(array('name'=>'emailSendTimeTS', 'id'=>'emailSendTimeTS', 'type'=>'text', 'class'=>'x-form-text x-form-field',
						'style'=>'width:102px;font-size:11px;margin-left:7px;padding-bottom:1px;','onkeydown'=>"if(event.keyCode==13){return false;}",
						'onfocus'=>"$('#inviteFollowupSurvey input[name=\"emailSendTime\"][value=\"EXACT_TIME\"]').prop('checked',true); this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",
						'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
					RCView::span(array('class'=>'df','style'=>'padding-left:5px;'), DateTimeRC::get_user_format_label().' H:M') .
					// Get current time zone, if possible
					RCView::div(array('style'=>'margin:4px 0 0 22px;font-size:10px;line-height:10px;color:#777;'),
						"{$lang['survey_296']} <b>".getTimeZone()."</b>{$lang['survey_297']} <b>" .
						DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true) . "</b>{$lang['period']}"
					)
				)
			) .
			## REMINDERS
			RCView::fieldset(array('style'=>'padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom: 10px;'),
				RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					RCView::img(array('src'=>'bell.png')) .
					$lang['survey_733']
				) .
				RCView::div(array('style'=>'padding:5px 0 10px 2px;'),
					// Instructions
					RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;padding:3px 10px 3px 0;color:#444;'),
						RCView::checkbox(array('id'=>"enable_reminders_chk", 'style'=>'margin-right:3px;')) .
						$lang['survey_734'] .
						RCView::span(array('id'=>'reminders_text1'), $lang['survey_749'])
					) .
					## When to send once condition is met
					RCView::div(array('id'=>"reminders_choices_div", 'style'=>'margin-left:20px;display:none;'),
						// Next occurrence of (e.g., Work day at 11:00am)
						RCView::div(array('style'=>'padding:4px 0 1px;'),
							RCView::radio(array('name'=>"reminder_type",'value'=>'NEXT_OCCURRENCE')) .
							$lang['survey_735'] . RCView::SP . RCView::SP .
							RCView::select(array('name'=>"reminder_nextday_type",'style'=>'font-size:11px;', 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }"), SurveyScheduler::daysofWeekOptions(), '') . RCView::SP .
							$lang['survey_424'] . RCView::SP . RCView::SP .
							RCView::input(array('name'=>"reminder_nexttime",'type'=>'text', 'class'=>'x-form-text x-form-field time2',
								'style'=>'font-size:11px;width:40px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
								'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",
								'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }")) .
							RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), 'H:M')

						).
						// Time lag of X amount of days/hours/minutes
						RCView::div(array('style'=>'padding:1px 0;'),
							RCView::radio(array('name'=>"reminder_type",'value'=>'TIME_LAG')) .
							$lang['survey_735'] . RCView::SP . RCView::SP .
							RCView::span(array('style'=>'font-size:11px;'),
								RCView::input(array('name'=>"reminder_timelag_days",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:31px;', 'value'=>'', 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
								$lang['survey_426'] . RCView::SP . RCView::SP .
								RCView::input(array('name'=>"reminder_timelag_hours",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
								$lang['survey_427'] . RCView::SP . RCView::SP .
								RCView::input(array('name'=>"reminder_timelag_minutes",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
								$lang['survey_428']
							)
						) .
						// Exact time
						RCView::div(array('style'=>'padding:1px 0;'),
							RCView::radio(array('name'=>"reminder_type",'value'=>'EXACT_TIME')) .
							$lang['survey_429'] . RCView::SP . RCView::SP .
							RCView::input(array('name'=>"reminder_exact_time", 'type'=>'text', 'class'=>'reminderdt x-form-text x-form-field',
								'value'=>'', 'style'=>'width:102px;font-size:11px;padding-bottom:1px;',
								'onkeydown'=>"if(event.keyCode==13){return false;}",
								'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
								'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);",
								'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=EXACT_TIME]').prop('checked',true).trigger('change'); }")) .
							RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), DateTimeRC::get_user_format_label().' H:M')
						) .
						// Recurrence
						RCView::div(array('style'=>'margin:4px 0 5px -15px;color:#999;'),
							"&ndash; " . $lang['global_87'] . " &ndash;"
						) .
						RCView::div(array('style'=>''),
							$lang['survey_739'] . RCView::SP . RCView::SP .
							RCView::select(array('name'=>"reminder_num",'style'=>'font-size:11px;'), array('1'=>$lang['survey_736'], '2'=>"{$lang['survey_737']} 2 {$lang['survey_738']}",
								'3'=>"{$lang['survey_737']} 3 {$lang['survey_738']}", '4'=>"{$lang['survey_737']} 4 {$lang['survey_738']}",
								'5'=>"{$lang['survey_737']} 5 {$lang['survey_738']}", ), '1')
						)
					)
				)
			) .
			## COMPOSE EMAIL SUBJECT AND MESSAGE
			RCView::fieldset(array('style'=>'padding-left:8px;background-color:#f3f5f5;border:1px solid #ccc;'),
				RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					RCView::img(array('src'=>'email.png')) .
					$lang['survey_692']
				) .
				RCView::div(array('style'=>'padding:10px 0 10px 2px;'),
					RCView::table(array('cellspacing'=>'0','border'=>'0','width'=>'100%'),
						// From
						RCView::tr(array('id'=>'compose_email_from_tr'),
							RCView::td(array('style'=>'vertical-align:top;width:50px;'),
								$lang['global_37']
							) .
							RCView::td(array('style'=>'vertical-align:middle;color:#555;'),
                                '<div class="clearfix nowrap">
                                    <div class="float-left" style="width:180px;margin-right:3px;">
                                        '.RCView::select(array('class'=>'x-form-text x-form-field'.($GLOBALS['use_email_display_name']?'':' hidden'),'style'=>'width:100%;','id'=>'email_sender_display','name'=>'email_sender_display', 'placeholder'=>$lang['survey_1270']),
									    array($lang['survey_1271']=>(SurveyScheduler::getDisplayNamesParticipantList()+array(''=>'-- clear --'))), '', 500
                                        ).'
                                    </div>
                                    <div class="float-left" style="width:75%;max-width:340px;">' .
								User::emailDropDownListAllUsers($user_email, true, 'followupSurvEmailFrom','followupSurvEmailFrom') .
                                '</div>
                                    </div>' .
                                RCView::div(array('style'=>'padding:2px 0 0 2px;font-size:11px;color:#777;'),
                                    $lang['survey_417']
                                )
							)
						) .
						// To (email)
						RCView::tr(array('id'=>'compose_email_to_tr'),
							RCView::td(array('style'=>'vertical-align:top;width:50px;padding-top:10px;'),
								$lang['global_38']
							) .
							RCView::td(array('style'=>'vertical-align:top;padding-top:10px;color:#666;'),
								$emailToDropdown .
								"<input onblur=\"this.value=trim(this.value);if(this.value != ''){inviteFollowupSurveyPopupSelectEmail(this);redcap_validate(this,'','','soft_typed','email');}\" size='30' class='x-form-text x-form-field' style='' type='text' id='followupSurvEmailTo'>" .
								(!($twilio_enabled && $Proj->twilio_enabled_surveys && $emailToDropdown == '') ? '' : RCView::span(array('style'=>'margin-left:8px;color:#777;'), $lang['survey_783'])) .
								RCView::div(array('style'=>'margin:5px 5px 2px 0;font-size: 11px;line-height: 12px;color: #999;'), $lang['survey_1128'])
							)
						) .
						// To (phone)
						RCView::tr(array('id'=>'compose_phone_to_tr', 'style'=>'display:none;'),
							RCView::td(array('style'=>'vertical-align:top;width:50px;padding-top:10px;'),
								$lang['global_38']
							) .
							RCView::td(array('style'=>'vertical-align:top;padding-top:10px;color:#666;'),
								$phoneToDropdown .
								"<input onblur=\"this.value=trim(this.value);if(this.value != ''){inviteFollowupSurveyPopupSelectPhone(this); this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','int');}\" size='30' class='x-form-text x-form-field' style='' type='text' id='followupSurvPhoneTo'>" .
								(!($twilio_enabled && $Proj->twilio_enabled_surveys && $phoneToDropdown == '') ? '' : RCView::span(array('style'=>'margin-left:8px;color:#777;'), $lang['survey_784']))

							)
						) .
						// Subject
						RCView::tr(array('id'=>'compose_email_subject_tr'),
							RCView::td(array('style'=>'vertical-align:middle;padding:10px 0 0;width:50px;'),
								$lang['survey_103']
							) .
							RCView::td(array('style'=>'vertical-align:middle;padding:10px 0 0;'),
							'<input class="x-form-text x-form-field" style="width:380px;" type="text" id="followupSurvEmailSubject" onkeydown="if(event.keyCode == 13){return false;}" value="'.js_escape2(str_replace('"', '&quot;', label_decode(isset($emailSubject) ? $emailSubject : ''))).'"/>'
							)
						) .
						// Message
						RCView::tr(array('id'=>'compose_email_form_fieldset'),
							RCView::td(array('colspan'=>'2','style'=>'padding:5px 0 10px;'),
								'<div class="text-right mb-1 mr-5">
                                            <a href="javascript:;" class="fs11" onclick="textareaTestPreviewEmail(\'#followupSurvEmailMsg\',1,\'#followupSurvEmailSubject\',\'#followupSurvEmailFrom option:selected\');">'.$lang['design_700'].'</a>
                                        </div>
                                        <textarea class="x-form-field notesbox mceEditor" id="followupSurvEmailMsg" onblur="checkComposeForSurveyLink(this);" style="height:270px;width:95%;">'.label_decode(isset($emailContent) ? $emailContent : '').'</textarea>' .
								// Extra instructions
								RCView::div(array('style'=>'margin-top:15px;padding:0 10px 0 2px;'),
									RCView::div(array('style'=>'font-size:11px;color:#D00000;padding-bottom:8px;line-height:13px;'),
										'<i class="far fa-lightbulb"></i> ' . RCView::b($lang['survey_105']) . RCView::SP . ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_1226'] : $lang['survey_1225'])
									)
								)
							)
						)
					)
				)
			) .
			## HIDDEN INPUTS AND DIVS FOR JAVASCRIPT VALIDATION USE
			RCView::hidden(array('id'=>'now_mdyhm','value'=>date('m-d-Y H:i'))) .
			RCView::div(array('style'=>'display:none;','id'=>'langFollowupProvideTime'), $lang['survey_325']) .
			RCView::div(array('style'=>'display:none;','id'=>'langFollowupTimeInvalid'), $lang['survey_326'] . " <b>" .DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true) . "</b>".$lang['period']) .
			RCView::div(array('style'=>'display:none;','id'=>'langFollowupTimeExistsInPast'), $lang['survey_327']);

	// Return the HTML
	print $html;
	Survey::renderCheckComposeForSurveyLink();
	?>
	<script type="text/javascript">
	$(function(){
		// Enable sendtime datetime picker
		$('#inviteFollowupSurvey #emailSendTimeTS').datetimepicker({
			onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
			buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
			showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
		});
	});
	</script>
	<?php
}




## SEND EMAIL
elseif ($_POST['action'] == 'email' && isset($_POST['email']))
{
	// Get user info
	$user_info = User::getUserInfo($userid);
	
	// Find the participant_id
	$sql = "select p.participant_id from redcap_surveys_participants p, redcap_surveys_response r
			where p.participant_id = r.participant_id and p.participant_email is not null
			and p.event_id = '".db_escape($_GET['event_id'])."' and p.survey_id = '".db_escape($_GET['survey_id'])."'
			and r.record = '".db_escape($_POST['record'])."' and r.instance = '".db_escape($_GET['instance'])."' limit 1";
	$q = db_query($sql);
	$participant_id = db_result($q, 0);

	// Set vars
	$subject = decode_filter_tags($_POST['subject']);
	$content = decode_filter_tags($_POST['msg']);
    // Remove line breaks because rich text editor doesn't have them
	$content = str_replace(array("\r", "\n"), array('', ''), $content);

	// PIPING: If field_names exist in the email subject or content, the try to replace with piped data
	$subject = strip_tags(Piping::replaceVariablesInLabel($subject, $_POST['record'], $_GET['event_id'], 1, array(), true, null, false, "", 1, false, false, $Proj->surveys[$_GET['survey_id']]['form_name'], $participant_id));
	$content = Piping::replaceVariablesInLabel($content, $_POST['record'], $_GET['event_id'], 1, array(), true, null, false, "", 1, false, false, $Proj->surveys[$_GET['survey_id']]['form_name'], $participant_id);
	
	// Set the From address for the emails sent
	$fromEmail = $_POST['email_account'];
	// Validate email
	$allProjectEmails = User::getEmailAllProjectUsers($project_id);
	if (!isEmail($fromEmail) || !in_array($fromEmail, $allProjectEmails)) {
		$fromEmail = $user_email;
	}

	// Set the send time for the emails. If specified exact date/time, convert timestamp from mdy to ymd for saving in backend
	if ($_POST['sendTimeTS'] != '') {
		list ($this_date, $this_time) = explode(" ", $_POST['sendTimeTS']);
		$_POST['sendTimeTS'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
	}
	if ($_POST['reminder_exact_time'] != '') {
		list ($this_date, $this_time) = explode(" ", $_POST['reminder_exact_time']);
		$_POST['reminder_exact_time'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
	}
	$sendTime = ($_POST['sendTime'] != 'IMMEDIATELY') ? $_POST['sendTimeTS'] : NOW;

	// Get the delivery type - default to EMAIL
	$delivery_methods = Survey::getDeliveryMethods(true);
	if (!($twilio_enabled && $Proj->twilio_enabled_surveys) || !isset($_POST['delivery_type']) || !isset($delivery_methods[$_POST['delivery_type']])) {
		$_POST['delivery_type'] = 'EMAIL';
	}

	// If respondent's email address is numeric, that means it was undisclosed and it thus the participant_id (rather than the email address).
	// Convert it from participant_id to email address
	$obscureParticipantEmail = false;
	if (is_numeric($_POST['email']))
	{
		$sql = "select participant_email from redcap_surveys_participants where participant_id = '".db_escape($_POST['email'])."'
				and participant_email is not null and participant_email != '' limit 1";
		$q = db_query($sql);
		// Convert to proper email address
		if (db_num_rows($q) > 0) {
			$_POST['email'] = db_result($q, 0);
			// Set flag to obscure the email address when re-displaying (if applicable)
			if (!$enable_participant_identifiers) $obscureParticipantEmail = true;
		}
	}

	// If respondent's email address is numeric, that means it was undisclosed and it thus the participant_id (rather than the email address).
	// Convert it from participant_id to email address
	$obscureParticipantPhone = false;
	if ($twilio_enabled && $Proj->twilio_enabled_surveys && is_numeric($_POST['phone']) && strlen($_POST['phone']) < 10) // It MUST be participant_id if less than 10 digits
	{
		$sql = "select participant_phone from redcap_surveys_participants where participant_id = '".db_escape($_POST['phone'])."'
				and participant_email is not null and participant_phone != '' limit 1";
		$q = db_query($sql);
		// Convert to proper email address
		if (db_num_rows($q) > 0) {
			$_POST['phone'] = db_result($q, 0);
			// Set flag to obscure the email address when re-displaying (if applicable)
			if (!$enable_participant_identifiers) $obscureParticipantPhone = true;
		}
	}

	// If using a static email that is not associated with participant, then store it in emails_recipients table
	$recipStaticEmail = ($_POST['delivery_type'] == 'EMAIL') ? $_POST['email'] : "";

	// If using a static email that is not associated with participant, then store it in emails_recipients table
	$recipStaticPhone = ($_POST['delivery_type'] != 'EMAIL') ? $_POST['phone'] : "";

	// Get participant_id and hash for this event-record-survey
	list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($_GET['survey_id'], $_POST['record'], $_GET['event_id'], false, $_GET['instance']);

	// Add email info to tables
	$sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_sender,
			email_account, email_static, email_sent, append_survey_link, email_sender_display) values
			({$_GET['survey_id']}, '" . db_escape($subject) . "', '" . db_escape($content) . "', {$user_info['ui_id']},
			'" . db_escape($_POST['email_account']) . "', ".checkNull($fromEmail).", null, '".Survey::getAppendSurveyLink($_POST['delivery_type'])."', ".checkNull($_POST['email_sender_display']).")";
	if (!db_query($sql)) exit("0");
	$email_id = db_insert_id();

	// Insert into emails_recipients table
	$sql = "insert into redcap_surveys_emails_recipients (email_id, participant_id, static_email, static_phone, delivery_type)
			values ($email_id, $participant_id, ".checkNull($recipStaticEmail).", ".checkNull($recipStaticPhone).", '".db_escape($_POST['delivery_type'])."')";
	if (db_query($sql)) {
		// Get email_recip_id
		$email_recip_id = db_insert_id();
		// First, remove invitation if already queued
		Survey::removeQueuedSurveyInvitations($_GET['survey_id'], $_GET['event_id'], array($participant_id));
	} else {
		// If query failed, then undo previous query and return error
		db_query("delete from redcap_surveys_emails where email_id = $email_id");
		exit("0");
	}


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


	## SCHEDULE THE INVITATION
	$insertErrors = 0;
	// Now add to scheduler_queue table (loop through orig invite + any reminder invites)
	foreach ($participantSendTimes as $reminder_num=>$thisSendTime) {
		$sql = "insert into redcap_surveys_scheduler_queue (email_recip_id, record, scheduled_time_to_send, reminder_num, instance)
				values ($email_recip_id, ".checkNull($_POST['record']).", '".db_escape($thisSendTime)."', '".db_escape($reminder_num)."', '".db_escape($_GET['instance'])."')";
		if (!db_query($sql)) $insertErrors++;
	}
	if ($insertErrors == 0) {
		// Logging
		$log_descrip = ($_POST['delivery_type'] == 'EMAIL') ? "(via email)" : ($_POST['delivery_type'] == 'VOICE_INITIATE' ? "(via voice call)" : "(via SMS)");
		Logging::logEvent($sql,"redcap_surveys_emails","MANAGE",$email_id,"email_id = $email_id,\nparticipant_id = $participant_id","Send survey invitation to participant $log_descrip");
		// Return confirmation message in pop-up
		print 	RCView::div(array('class'=>'darkgreen','style'=>'margin:20px 0;'),
					RCView::table(array('cellspacing'=>'10','style'=>'width:100%;'),
						RCView::tr(array(),
							RCView::td(array('style'=>'padding:0 20px;'),
								RCView::img(array('src'=>'check_big.png'))
							) .
							RCView::td(array('style'=>'font-size:14px;font-weight:bold;font-family:verdana;line-height:22px;'),
								$lang['survey_788'] .
								RCView::div(array('style'=>'color:green;'),
									($recipStaticEmail != ''
										? ($obscureParticipantEmail ? $lang['survey_499'] : $_POST['email'])
										: ($obscureParticipantPhone ? $lang['survey_789'] : formatPhone($_POST['phone']))
									)
								) .
								RCView::div(array('style'=>'color:#555;'), "(" . DateTimeRC::format_ts_from_ymd($sendTime) . ")")
							)
						)
					)
				);
	}
}

## ERROR
else
{
	exit("0");
}