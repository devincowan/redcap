<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = "0";

## DISPLAY DIALOG PROMPT
if (isset($_POST['setting']) && isset($_POST['action']) && $_POST['action'] == 'view')
{
	## Display different messages for different settings

	// Survey participant email address field
	if ($_POST['setting'] == 'survey_email_participant_field')
	{
		// Collect all email-validated fields and their labels into an array
		$emailFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, 'email');
		// Set dialog content
		$response = RCView::div(array(),
						RCView::div(array('style'=>'margin-bottom:15px;background-color:#f5f5f5;border:1px solid #ddd;padding:10px;'),
							RCView::b($lang['setup_116']) . RCView::br() .
							RCView::select(array('style'=>'margin-top:5px;max-width:400px;min-width:220px;','id'=>'surveyPartEmailFieldName'), $emailFieldsLabels, '', 300)
						) .
						$lang['setup_114'] . "<br><br>" . $lang['setup_122'] . RCView::br() . RCView::br() .
						RCView::span(array('style'=>'color:#C00000;'), $lang['setup_133']) . RCView::br() . RCView::br() .
						RCView::b($lang['global_02'].$lang['colon']) . " " . $lang['setup_115'] . " " . $lang['setup_168'] . RCView::br() . RCView::br() .
						RCView::b($lang['setup_165'].$lang['colon']). " " . $lang['setup_171'] . " " . $lang['setup_169'] . " " . $lang['setup_172']
					);
	}

	// Twilio voice/SMS services
	if ($_POST['setting'] == 'twilio_enabled')
	{
		// Set $twilio_request_inspector_checked checkbox as pre-checked or not
		// $twilio_request_inspector_checked_chkbox_checked = ($twilio_request_inspector_checked == "") ? "checked" : "";
		// Set dialog content
		$response = RCView::div(array(),
						// Instructions
						RCView::div(array('style'=>'margin:0 0 10px;line-height:14px;'),
							RCView::div(array('style'=>''),
								$lang['survey_1276'] . " " .
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;text-decoration:underline;', 'onclick'=>"
									$(this).hide();
									$('#twilio_setup_instr1').show('fade',function(){
										fitDialog($('#TwilioEnableDialog'));
										$('#TwilioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
									});
								"),
									$lang['global_58']
								)
							) .
							RCView::div(array('id'=>'twilio_setup_instr1', 'style'=>'display:none;'),
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_969']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_1282']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_970']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_973'] . " " . $lang['survey_974']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_972']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_975']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_967'])
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;display:block;text-decoration:underline;', 'onclick'=>"
								$(this).hide();
								$('#twilio_setup_instr2').show('fade',function(){
									fitDialog($('#TwilioEnableDialog'));
									$('#TwilioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
								});
							"),
								$lang['survey_846']
							)
						) .
						RCView::div(array('id'=>'twilio_setup_instr2', 'style'=>'display:none;margin:15px 0 15px;line-height:14px;'),
							RCView::div(array('style'=>'color:#C00000;font-weight:bold;margin-bottom:2px;'), $lang['survey_976']) .
							$lang['survey_1281'] . " " .
							RCView::a(array('href'=>'https://www.twilio.com', 'target'=>'_blank', 'style'=>'font-size:13px;text-decoration:underline;'),
								"www.twilio.com"
							) . $lang['period'] . " " .
							$lang['survey_835']
						) .
						RCView::form(array('id'=>'twilio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Enabled?
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc nowrap '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'twilio.png')) .
										RCView::b($lang['survey_711']) . RCView::SP . RCView::SP
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_enabled',
											'onchange'=>"enableTwilioRowColor();", 'class'=>'x-form-text x-form-field', 'style'=>''),
											array(0=>$lang['global_23'], 1=>$lang['index_30']), $twilio_enabled, 200)
									)
								) .
								// Header
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										$lang['survey_714']
									)
								) .
								// Account SID
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_715'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::input(array('name'=>'twilio_account_sid', 'type'=>'text',
											'class'=>'x-form-text x-form-field', 'style'=>'width:260px;', 'value'=>$twilio_account_sid))
									)
								) .
								// Account Token
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_716'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data nowrap', 'style'=>'padding:6px 10px;'),
										RCView::input(array('type'=>'password', 'name'=>'twilio_auth_token',
											'class'=>'x-form-text x-form-field', 'style'=>'width:170px;', 'value'=>$twilio_auth_token)) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'cclink', 'style'=>'text-decoration:underline;font-size:7pt;margin-left:5px;', 'onclick'=>"$(this).remove();showPasswordField('twilio_auth_token');"),
											$lang['survey_720']
										)
									)
								) .
								// "From" Number
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_718'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::text(array('name'=>'twilio_from_number',
											'class'=>'x-form-text x-form-field', 'style'=>'width:120px;',
											'value'=>$twilio_from_number, 'onblur'=>"this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','integer',1)"))

									)
								) .
								// Display note about manually disabling Twilio's Request Inspector
								RCView::tr(array(),
									RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data', 'style'=>'padding:20px 15px;color:#A00000;'),
										'<i class="fas fa-exclamation-triangle"></i> '.$lang['survey_1269']
									)
								) .
								// FIELD EMBEDDING: Add note that it is not supported in the REDCap Mobile App
								(!Piping::projectHasEmbeddedVariables($project_id) ? '' :
									RCView::tr(array(),
										RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data yellow p-3'),
											'<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' .
											$lang['design_820']
										)
									)
								)
								/*
								// Display status of Twilio's Request Inspector (is it enabled?)
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_927']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'margin-top:20px;'),
											$lang['survey_1067']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										($twilio_request_inspector_checked == ""
											? RCView::div(array('style'=>'color:#C00000;'),
												'<i class="fas fa-minus-circle"></i> ' . $lang['survey_929'])
											: ($twilio_request_inspector_enabled
												? RCView::div(array('style'=>'color:#C00000;'),
                                                    '<i class="fas fa-minus-circle"></i> ' . $lang['survey_1069'])
												: RCView::div(array('style'=>'color:green;'),
													'<i class="fas fa-check"></i> ' . $lang['survey_934']) .
													$lang['survey_935'] . " " . ($twilio_request_inspector_checked == "" ? RCView::b($lang['dashboard_54'])
														: DateTimeRC::format_ts_from_ymd($twilio_request_inspector_checked))
											  )
										) .
										RCView::div(array('style'=>'font-weight:bold;margin:8px 0 6px;'),
											($twilio_request_inspector_checked == "" ? $lang['survey_930'] : $lang['survey_936']) . RCView::SP . RCView::SP .
											RCView::checkbox(array('name'=>'twilio_request_inspector_check',
												$twilio_request_inspector_checked_chkbox_checked=>$twilio_request_inspector_checked_chkbox_checked,
												'onclick'=>"if ($(this).prop('checked')) { $('#twilio_note_ri_charge').css('color','#C00000').effect('highlight',{ },4000); }"))
										) .
										RCView::div(array('id'=>'twilio_note_ri_charge', 'style'=>'font-size:11px;line-height:11px;color:#666;padding:2px;'),
											$lang['survey_931']
										)
									)
								)
								*/
							)
						)
					);
	}

	// Twilio voice/SMS services: Set up project-level settings for Twilio (at this point it is already enabled)
	elseif ($_POST['setting'] == 'twilio_setup' && $user_rights['design'])
	{
		// Set checkbox settings
		$twilio_option_voice_initiate_chk = ($twilio_option_voice_initiate) ? "checked" : "";
		$twilio_option_sms_initiate_chk = ($twilio_option_sms_initiate) ? "checked" : "";
		$twilio_option_sms_invite_make_call_chk = ($twilio_option_sms_invite_make_call) ? "checked" : "";
		$twilio_option_sms_invite_receive_call_chk = ($twilio_option_sms_invite_receive_call) ? "checked" : "";
		$twilio_option_sms_invite_web_chk = ($twilio_option_sms_invite_web) ? "checked" : "";
		$twilio_multiple_sms_behavior_disabled_chk = (count($Proj->surveys) > 1) ? "" : "disabled";
		// Collect all email-validated fields and their labels into an array
		$phoneFieldsLabels = array(''=>'--- '.$lang['random_02'].' ---');
		foreach ($Proj->metadata as $field=>$attr) {
			// Allow integers and U.S. phone numbers
			if ($attr['element_validation_type'] == 'phone' || $attr['element_validation_type'] == 'int') {
				$phoneFieldsLabels[$field] = "$field (\"{$attr['element_label']}\")";
			}
		}
		// Set dialog content
		$response = RCView::div(array(),
						// Instructions
						RCView::div(array('style'=>'margin:0 0 10px;line-height:14px;'),
							$lang['survey_939']
						) .
						RCView::form(array('id'=>'twilio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Header (options)
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'twilio.png')) . $lang['survey_717']
									)
								) .
								// Gender of voice
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_722']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_1275']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_voice_language', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;'),
											TwilioRC::getDropdownAllLanguages(), $twilio_voice_language)
									)
								) .
								// Modules utilizing the service (i.e. surveys and/or alerts)
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_1280'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_modules_enabled', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;'),
											array('SURVEYS'=>$lang['survey_1277'], 'ALERTS'=>$lang['survey_1278'], 'SURVEYS_ALERTS'=>$lang['survey_1279']), $twilio_modules_enabled, 100)
									)
								) .
								// Survey settings
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'colspan'=>'2', 'class'=>'labelrc fs14', 'style'=>'padding:20px 10px 5px;color:#C00000;background-color:#ddd;'),
										$lang['survey_1283']
									)
								) .
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_836']) .
										RCView::div(array('class'=>'cc_info'),
											$lang['survey_837']
										) .
										RCView::div(array('style'=>'font-weight:normal;margin-top:15px;font-size:11px;color:#800000;text-indent:-0.7em;margin-left:0.7em;line-height:11px;'),
											"* ".$lang['survey_974']
										)
									) .
									RCView::td(array('id'=>'twilio_options_checkboxes', 'valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::div(array('style'=>'font-weight:bold;'),
											$lang['survey_804']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_web', $twilio_option_sms_invite_web_chk=>$twilio_option_sms_invite_web_chk)) . $lang['survey_957']
										) .
										RCView::div(array('style'=>'margin-top:8px;font-weight:bold;'),
											$lang['survey_802']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_voice_initiate', $twilio_option_voice_initiate_chk=>$twilio_option_voice_initiate_chk)) . $lang['survey_728']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_make_call', $twilio_option_sms_invite_make_call_chk=>$twilio_option_sms_invite_make_call_chk)) . $lang['survey_799']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_receive_call', $twilio_option_sms_invite_receive_call_chk=>$twilio_option_sms_invite_receive_call_chk)) . $lang['survey_800']
										) .
										RCView::div(array('style'=>'margin-top:8px;font-weight:bold;'),
											$lang['survey_803']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_initiate', $twilio_option_sms_initiate_chk=>$twilio_option_sms_initiate_chk, 'onclick'=>"
												if (!super_user && $('input[type=\"checkbox\"][name=\"twilio_option_sms_initiate\"]').prop('checked') && '$twilio_option_sms_initiate' == '0') {
													simpleDialog('".js_escape($lang['survey_974'])."','".js_escape($lang['survey_943'])."',null,500);
													$('input[type=\"checkbox\"][name=\"twilio_option_sms_initiate\"]').prop('checked', false);
												}")) .
											$lang['survey_729'] .
											RCView::span(array('style'=>'margin-left:2px;color:#C00000;font-size:16px;font-weight:bold;'), "*")
										)
									)
								) .
								// Option to append response instructions (auto-add "press 1 for...")
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_945']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_946']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_append_response_instructions', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:280px;'),
											array('0' => $lang['survey_947'], '1' => $lang['survey_948']), $twilio_append_response_instructions) .
										RCView::div(array('class'=>'cc_info', 'style'=>'margin-top:10px;line-height:12px;'),
											$lang['survey_949']
										)
									)
								) .
								// Default delivery preference
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_925']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_1006']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data nowrap', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_default_delivery_preference', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:280px;'),
											array('EMAIL' => $lang['survey_688'], 'SMS_INVITE_WEB' => $lang['survey_955'], 'SMS_INITIATE' => $lang['survey_767'], 'VOICE_INITIATE' => $lang['survey_884'],
												  'SMS_INVITE_MAKE_CALL' => $lang['survey_690'], 'SMS_INVITE_RECEIVE_CALL' => $lang['survey_801']), $twilio_default_delivery_preference) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 13px;',
											'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
									)
								) .
								// Designated phone field
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_793']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_794']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'survey_phone_participant_field',
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:370px;'),
											$phoneFieldsLabels, $survey_phone_participant_field) .
										RCView::div(array('class'=>'note', 'style'=>'line-height:11px;margin-top:10px;'), $lang['survey_838'])
									)
								) .
								// SMS multiple invite behavior
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_964']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_980']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_multiple_sms_behavior', $twilio_multiple_sms_behavior_disabled_chk=>$twilio_multiple_sms_behavior_disabled_chk,
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:95%;'),
											array('OVERWRITE'=>$lang['survey_979'], 'FIRST'=>$lang['survey_978'], 'CHOICE'=>$lang['survey_963']), $twilio_multiple_sms_behavior, 200) .
										RCView::div(array('class'=>'note', 'style'=>'line-height:11px;margin-top:10px;'), $lang['survey_981'])
									)
								)
							)
						)
					);
	}
}


## ENABLE TWILIO & SAVE TWILIO CREDENTIALS
elseif (isset($_POST['twilio_enabled']))
{
	if (!(SUPER_USER || (!$twilio_enabled_by_super_users_only && $user_rights['design']))) exit("ERROR: You must be a super user to perform this action!");
	// Set values to be saved
	$twilio_enabled = (isset($_POST['twilio_enabled']) && $_POST['twilio_enabled'] == '1') ? '1' : '0';
	$twilio_from_number = (isset($_POST['twilio_from_number']) && is_numeric($_POST['twilio_from_number'])) ? $_POST['twilio_from_number'] : '';
	$twilio_account_sid = $_POST['twilio_account_sid'];
	$twilio_auth_token = $_POST['twilio_auth_token'];
	$twilio_request_inspector_check = (isset($_POST['twilio_request_inspector_check']) && $_POST['twilio_request_inspector_check'] == 'on');

	// Error msg
	$error_msg = "";

	// Make sure that Twilio number is not used by another project
	if ($twilio_from_number != '') {
		$sql = "select 1 from redcap_projects where project_id != $project_id and twilio_from_number = ".checkNull($twilio_from_number);
		$q = db_query($sql);
		if (db_num_rows($q)) {
			// ERROR: Another project has this number
			$error_msg .= RCView::span(array('style'=>''),
							$lang['survey_958'] . " " . RCView::b(formatPhone($twilio_from_number)) . " " . $lang['survey_959']
						  );
			$twilio_from_number = '';
		}
	}

	// Modify settings in table
	$sql = "update redcap_projects set twilio_enabled = $twilio_enabled,
			twilio_from_number = ".checkNull($twilio_from_number).", twilio_account_sid = ".checkNull($twilio_account_sid).",
			twilio_auth_token = ".checkNull($twilio_auth_token)." where project_id = $project_id";
	if (db_query($sql)) {
		// Set response
		$response = "1";
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project settings");
		## TWILIO CHECK: Check connection to Twilio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
		// Initialize Twilio
		TwilioRC::init();
		// Instantiate a new Twilio Rest Client
		$twilioClient = TwilioRC::client();
		// SET URLS: Loop over the list of numbers and get the sid of the phone number (don't do this if we don't have a value for $twilio_from_number)
		$numberBelongsToAcct = false;
		$allNumbers = array();
		if ($twilio_from_number != '') {
			try {
				foreach ($twilioClient->account->incoming_phone_numbers as $number) {
					// Collect number in array
					$allNumbers[] = $number->phone_number;
					// If number does not match, then skip
					if (substr($number->phone_number, -1 * strlen($twilio_from_number)) != $twilio_from_number) {
						continue;
					}
					// We verified that the number belongs to this Twilio account
					$numberBelongsToAcct = true;
					// Set VoiceUrl and SmsUrl for this number, if not set yet
					if ($number->voice_url != APP_PATH_SURVEY_FULL || $number->sms_url != APP_PATH_SURVEY_FULL) {
						$number->update(array("VoiceUrl" => APP_PATH_SURVEY_FULL, "SmsUrl" => APP_PATH_SURVEY_FULL));
					}
				}
				// If number doesn't belong to account
				if (!$numberBelongsToAcct) {
					// Set error message
					$error_msg .= $lang['survey_920'];
					if (empty($allNumbers)) {
						$error_msg .= RCView::div(array('style' => 'margin-top:10px;font-weight:bold;'), $lang['survey_843']);
					} else {
						$error_msg .= RCView::div(array('style' => 'margin-top:5px;font-weight:bold;'), " &nbsp; " . implode("<br> &nbsp; ", $allNumbers));
					}
				}
			} catch (Exception $e) {
				// Set error message
				$error_msg .= $lang['survey_919'];
				// Make sure Localhost isn't being used as REDCap base URL (not valid for Twilio)
				if (strpos(APP_PATH_SURVEY_FULL, "http://localhost") !== false || strpos(APP_PATH_SURVEY_FULL, "https://localhost") !== false) {
					$error_msg .= "<br><br>" . $lang['survey_841'];
				}
			}
		}
		// If we are missing the phone number or if an error occurred with Twilio, then disable this module
		if ($twilio_enabled && ($twilio_from_number == '' || $error_msg != '')) {
			$sql = "update redcap_projects set twilio_enabled = 0 where project_id = $project_id";
			db_query($sql);
			// If Twilio credentials worked but no phone number was entered, then let them know that the module was NOT enabled
			if ($twilio_from_number == '' && $error_msg == '') {
				$error_msg = $lang['survey_842'];
			}
		}
		// If there's an error message, then display it
		if ($error_msg != '') {
			// Display error message
			print 	RCView::div(array('class'=>'red'),
						RCView::img(array('src'=>'exclamation.png')) .
						$error_msg
					);
			exit;
		}
	}
}


## SAVE TWILIO SETTINGS
elseif ($twilio_enabled && isset($_POST['survey_phone_participant_field']))
{
	// Get all available Twilio languages
	$allLang = TwilioRC::getAllLanguages();
	// Set values to be saved
	$twilio_append_response_instructions = (isset($_POST['twilio_append_response_instructions']) && $_POST['twilio_append_response_instructions'] == '1') ? '1' : '0';
	$twilio_option_voice_initiate = (isset($_POST['twilio_option_voice_initiate']) && $_POST['twilio_option_voice_initiate'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_make_call = (isset($_POST['twilio_option_sms_invite_make_call']) && $_POST['twilio_option_sms_invite_make_call'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_receive_call = (isset($_POST['twilio_option_sms_invite_receive_call']) && $_POST['twilio_option_sms_invite_receive_call'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_web = (isset($_POST['twilio_option_sms_invite_web']) && $_POST['twilio_option_sms_invite_web'] == 'on') ? '1' : '0';
	$twilio_voice_language = (isset($_POST['twilio_voice_language']) && isset($allLang[$_POST['twilio_voice_language']])) ? $_POST['twilio_voice_language'] : 'en';
	$survey_phone_participant_field = (isset($Proj->metadata[$_POST['survey_phone_participant_field']])) ? $_POST['survey_phone_participant_field'] : "";
	$twilio_default_delivery_preference = (isset($_POST['twilio_default_delivery_preference'])) ? $_POST['twilio_default_delivery_preference'] : 'EMAIL';
	$twilio_multiple_sms_behavior = ($_POST['twilio_multiple_sms_behavior'] == 'OVERWRITE') ? 'OVERWRITE' : (($_POST['twilio_multiple_sms_behavior'] == 'FIRST') ? 'FIRST' : 'CHOICE');
	$twilio_modules_enabled = $_POST['twilio_modules_enabled'];
	// Only super users can enable SMS conversation
	if ($twilio_option_sms_initiate) {
		// Option is already enabled
		$twilio_option_sms_initiate = (isset($_POST['twilio_option_sms_initiate']) && $_POST['twilio_option_sms_initiate'] == 'on') ? '1' : '0';
	} else {
		// Option not enabled yet (only let super users enable)
		$twilio_option_sms_initiate = (SUPER_USER && isset($_POST['twilio_option_sms_initiate']) && $_POST['twilio_option_sms_initiate'] == 'on') ? '1' : '0';
	}
	// Modify settings in table
	$sql = "update redcap_projects set twilio_option_voice_initiate = $twilio_option_voice_initiate,
			twilio_option_sms_initiate = $twilio_option_sms_initiate, twilio_option_sms_invite_make_call = $twilio_option_sms_invite_make_call,
			twilio_option_sms_invite_receive_call = $twilio_option_sms_invite_receive_call, twilio_option_sms_invite_web = $twilio_option_sms_invite_web,
			twilio_voice_language = '".db_escape($twilio_voice_language)."', survey_phone_participant_field = '".db_escape($survey_phone_participant_field)."',
			twilio_default_delivery_preference = '".db_escape($twilio_default_delivery_preference)."',
			twilio_append_response_instructions = '".db_escape($twilio_append_response_instructions)."',
			twilio_multiple_sms_behavior = '".db_escape($twilio_multiple_sms_behavior)."',
			twilio_modules_enabled = '".db_escape($twilio_modules_enabled)."' 
			where project_id = $project_id";
	if (db_query($sql)) {
		// Set response
		$response = "1";
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project Twilio settings");
	}
}


## SAVE PROJECT SETTING VALUE
else
{
	// Make sure the "name" setting is a real one that we can change
	$viableSettingsToChange = array('auto_inc_set', 'scheduling', 'randomization', 'repeatforms', 'surveys_enabled',
									'survey_email_participant_field', 'realtime_webservice_enabled', 'fhir_ddp_enabled',
									'datamart_allow_repeat_revision', 'datamart_allow_create_revision', 'datamart_cron_enabled', 'break_the_glass_enabled');
	if (!empty($_POST['name']) && in_array($_POST['name'], $viableSettingsToChange))
	{
		// If this is a super-user-only attribute, then make sure the user is a super user before doing anything
		if (($_POST['name'] == 'realtime_webservice_enabled' || $_POST['name'] == 'fhir_ddp_enabled') && !SUPER_USER) exit;
		// Specifically for DDP
		$sqlsub = "";
		$logDescription = "Modify project settings";
		if ($_POST['name'] == 'realtime_webservice_enabled') {
			$sqlsub = ", realtime_webservice_type = 'CUSTOM'";
			$logDescription = ($_POST['value'] == '1') ? "Enable DDP Custom module" : "Disable DDP Custom module";
		} elseif ($_POST['name'] == 'fhir_ddp_enabled') {
			$_POST['name'] = 'realtime_webservice_enabled';
			$sqlsub = ", realtime_webservice_type = 'FHIR'";
			$logDescription = ($_POST['value'] == '1') ? "Enable Clinical Data Pull (CDP) module" : "Disable Clinical Data Pull (CDP) module";
		}
		// Modify setting in table
		$sql = "update redcap_projects set {$_POST['name']} = '" . db_escape(label_decode($_POST['value'])). "' $sqlsub
				where project_id = $project_id";
		if (db_query($sql)) {
			$response = "1";
			// Logging
			Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id",$logDescription);
			// If project is being enabled as longitudinal AND also has randomization already enabled, make sure target_event is not null
			if ($_POST['name'] == 'repeatforms' && !$longitudinal && $randomization && Randomization::setupStatus()) {
				// Make sure the target event is set as the first event_id if it is currently null
				$sql = "update redcap_randomization set target_event = {$Proj->firstEventId}
						where project_id = $project_id and target_event is null";
				db_query($sql);
			}
			// Enabling or disabling longitudinal, make sure we reset the record list cache, just in case there are multiple arms, which might affect the record count.
			if ($_POST['name'] == 'repeatforms') {
				Records::resetRecordCountAndListCache($project_id);
			}
		}
	}
}

// Send response
print $response;
