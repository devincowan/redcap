<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Create drop-down list of all text fields
$possible_login_fields = Survey::getTextFieldsForDropDown();

// Display content for survey login setup dialog
if (isset($_POST['action']) && $_POST['action'] == 'view')
{
	// If longitudinal, build an array of drop-down options listing all REDCap event names
	if ($longitudinal) {
		$rc_events = array();
		foreach ($Proj->eventInfo as $this_event_id=>$attr) {
			// Add to array
			$rc_events[$this_event_id] = $attr['name_ext'];
		}
	}
	// Set HTML content for dialog
	$content = 	RCView::div(array(),
					// Instructions
					RCView::div(array('style'=>'margin:0 0 10px;'),
						$lang['survey_592'] . " " .
						RCView::a(array('onclick'=>"$(this).hide();$('#survey-login-hidden-instructions').removeClass('hidden');fitDialog($('#survey_login_setup_dialog'));$('#survey_login_setup_dialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });",
							'style'=>'text-decoration:underline;', 'href'=>'javascript:;'), $lang['global_58']) .
						RCView::span(array('id'=>'survey-login-hidden-instructions', 'class'=>'hidden'), $lang['survey_615'])
					) .
					RCView::div(array('style'=>'margin:0 0 15px;'),
						$lang['survey_593'] . " ".
						Survey::getSurveyLoginAutoLogoutTimer() ." " . $lang['survey_428'].$lang['period']
					) .
					RCView::form(array('id'=>'survey_login_setup_form'),
						// Hidden input for save action
						RCView::hidden(array('name'=>'action', 'value'=>'save')) .
						// Table
						RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
							// Enabled?
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc '.($survey_auth_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
									RCView::img(array('src'=>'key.png')) .
									RCView::b($lang['survey_616'])
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data '.($survey_auth_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_enabled', 'onchange'=>"enableSurveyLoginRowColor();", 'class'=>'x-form-text x-form-field', 'style'=>''),
										array(0=>$lang['global_23'], 1=>$lang['index_30']), $survey_auth_enabled, 200)
								)
							) .
							// Header
							RCView::tr(array(),
								RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
									$lang['survey_603'] .
									(!$Proj->hasRepeatingFormsEvents() ? '' :
										RCView::span(array('class'=>'font-weight-normal ml-4 fs12'),
											$lang['survey_1294']
										)
									)
								)
							) .
							// Field 1
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_591'] . " #1") .
									// Button to add more fields
									RCView::div(array('style'=>'margin-top:8px;'),
										RCView::a(array('class'=>'survey-login-field-add', 'href'=>'javascript:;', 'style'=>($survey_auth_field2 == '' ? '' : 'display:none;') .'font-weight:normal;color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"addSurveyLoginFieldInDialog();"),
											RCView::img(array('src'=>'plus_small2.png')) .
											$lang['survey_590']
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_field1', 'class'=>'survey-login-field x-form-text x-form-field', 'style'=>'width:300px;'),
										$possible_login_fields, $survey_auth_field1, 200) .
									(!$longitudinal ? '' :
										RCView::div(array('style'=>'margin-top:4px;'),
											RCView::span(array('style'=>'color:#666;margin-right:6px;'), $lang['data_entry_67']) .
											RCView::select(array('name'=>'survey_auth_event_id1', 'class'=>'x-form-text x-form-field', 'style'=>'width:280px;'),
												$rc_events, $survey_auth_event_id1, 200)
										)
									)
								)
							) .
							// Field 2
							RCView::tr(array('style'=>($survey_auth_field2 == '' ? 'display:none;' : '')),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_591'] . " #2") .
									// Button to add more fields
									RCView::div(array('style'=>'margin-top:8px;'),
										RCView::a(array('class'=>'survey-login-field-add', 'href'=>'javascript:;', 'style'=>($survey_auth_field3 == '' ? '' : 'display:none;') .'font-weight:normal;color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"addSurveyLoginFieldInDialog();"),
											RCView::img(array('src'=>'plus_small2.png')) .
											$lang['survey_590']
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_field2', 'class'=>'survey-login-field x-form-text x-form-field', 'style'=>'width:300px;'),
										$possible_login_fields, $survey_auth_field2, 200) .
									// Remove?
									RCView::a(array('class'=>'survey_auth_field_delete', 'href'=>'javascript:;', 'style'=>'margin-left:5px;'.($survey_auth_field3 == '' ? '' : 'display:none;'), 'onclick'=>"removeSurveyLoginFieldInDialog(this);"),
										RCView::img(array('src'=>'cross.png', 'title'=>$lang['scheduling_57']))
									) .
									(!$longitudinal ? '' :
										RCView::div(array('style'=>'margin-top:4px;'),
											RCView::span(array('style'=>'color:#666;margin-right:6px;'), $lang['data_entry_67']) .
											RCView::select(array('name'=>'survey_auth_event_id2', 'class'=>'x-form-text x-form-field', 'style'=>'width:280px;'),
												$rc_events, $survey_auth_event_id2, 200)
										)
									)
								)
							) .
							// Field 3
							RCView::tr(array('style'=>($survey_auth_field3 == '' ? 'display:none;' : '')),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_591'] . " #3")
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_field3', 'class'=>'survey-login-field x-form-text x-form-field', 'style'=>'width:300px;'),
										$possible_login_fields, $survey_auth_field3, 200) .
									// Remove?
									RCView::a(array('class'=>'survey_auth_field_delete', 'href'=>'javascript:;', 'style'=>'margin-left:5px;', 'onclick'=>"removeSurveyLoginFieldInDialog(this);"),
										RCView::img(array('src'=>'cross.png', 'title'=>$lang['scheduling_57']))
									) .
									(!$longitudinal ? '' :
										RCView::div(array('style'=>'margin-top:4px;'),
											RCView::span(array('style'=>'color:#666;margin-right:6px;'), $lang['data_entry_67']) .
											RCView::select(array('name'=>'survey_auth_event_id3', 'class'=>'x-form-text x-form-field', 'style'=>'width:280px;'),
												$rc_events, $survey_auth_event_id3, 200)
										)
									)
								)
							) .
							// Header (options)
							RCView::tr(array(),
								RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
									$lang['survey_604']
								)
							) .
							// Select minimum # fields needed
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_595'])
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_min_fields', 'class'=>'x-form-text x-form-field', 'style'=>''),
										array(1=>1, 2=>2, 3=>3), $survey_auth_min_fields)
								)
							) .
							// Apply survey login to all surveys?
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_596'])
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::select(array('name'=>'survey_auth_apply_all_surveys', 'class'=>'x-form-text x-form-field', 'style'=>'width:300px;'),
										array(1=>$lang['survey_597'], 0=>$lang['survey_598']), $survey_auth_apply_all_surveys)
								)
							) .
							// Custom error message
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_601']) .
									RCView::div(array('class'=>'cc_info', 'style'=>'margin-top:15px;color:#666;'),
										$lang['design_472'].$lang['colon']." \"".$lang['survey_602']." ".
										RCView::escape("<a href=\"mailto:survey_admin@myinstitution.edu\">survey_admin@myinstitution.edu</a>",false).
										" ".$lang['survey_614']."\""
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
									RCView::textarea(array('name'=>'survey_auth_custom_message', 'class'=>'x-form-textarea x-form-field', 'style'=>'width:300px;height:100px;'),
										htmlspecialchars($survey_auth_custom_message, ENT_QUOTES)
									) .
									RCView::div(array('class'=>'cc_info', 'style'=>'margin-top:0px;color:#666;'),
										$lang['survey_613']
									)
								)
							) .
							// Header (options)
							RCView::tr(array(),
								RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
									$lang['survey_609']
								)
							) .
							// Login fail limit
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_599'])
								) .
								RCView::td(array('class'=>'data', 'style'=>'padding:6px 10px;color:#777;'),
									RCView::text(array('name'=>'survey_auth_fail_limit', 'class'=>'x-form-text x-form-field', 'style'=>'margin-right:5px;', 'onblur'=>"redcap_validate(this,'0','99','hard','int')", 'value'=>$survey_auth_fail_limit)) .
									$lang['system_config_121']
								)
							) .
							// Login fail window of time
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
									RCView::b($lang['survey_600'])
								) .
								RCView::td(array('class'=>'data', 'style'=>'padding:6px 10px;color:#777;'),
									RCView::text(array('name'=>'survey_auth_fail_window', 'class'=>'x-form-text x-form-field', 'style'=>'margin-right:5px;', 'onblur'=>"redcap_validate(this,'0','999','hard','int')", 'value'=>$survey_auth_fail_window)) .
									$lang['system_config_123']
								)
							)
						)
					)
				);
	// Output JSON
	print json_encode_rc(array('content'=>$content, 'save_btn'=>$lang['designate_forms_13'], 'cancel_btn'=>$lang['global_53'],
							'title'=>RCView::img(array('src'=>'key.png', 'style'=>'vertical-align:middle;')) .
									 RCView::span(array('style'=>'color:#865200;vertical-align:middle;'), $lang['survey_573'])));
}

// Save the survey login setup options
elseif (isset($_POST['action']) && $_POST['action'] == 'save')
{
	// Validate/fix values
	$_POST['survey_auth_enabled'] = ($_POST['survey_auth_enabled'] == '1') ? 1 : 0;
	if (!is_numeric($_POST['survey_auth_fail_limit'])) $_POST['survey_auth_fail_limit'] = 0;
	if (!is_numeric($_POST['survey_auth_fail_window'])) $_POST['survey_auth_fail_window'] = 0;
	if (!isset($Proj->metadata[$_POST['survey_auth_field1']])) $_POST['survey_auth_field1'] = '';
	if (!isset($Proj->metadata[$_POST['survey_auth_field2']])) $_POST['survey_auth_field2'] = '';
	if (!isset($Proj->metadata[$_POST['survey_auth_field3']])) $_POST['survey_auth_field3'] = '';
	if ($_POST['survey_auth_field1'] != '' && $_POST['survey_auth_field1'] == $_POST['survey_auth_field2'] && $_POST['survey_auth_event_id1'] == $_POST['survey_auth_event_id2']) {
		$_POST['survey_auth_field1'] = $_POST['survey_auth_field2'];
		$_POST['survey_auth_field2'] = '';
	}
	if ($_POST['survey_auth_field1'] != '' && $_POST['survey_auth_field1'] == $_POST['survey_auth_field3'] && $_POST['survey_auth_event_id1'] == $_POST['survey_auth_event_id3']) {
		$_POST['survey_auth_field1'] = $_POST['survey_auth_field3'];
		$_POST['survey_auth_field3'] = '';
	}
	if ($_POST['survey_auth_field2'] != '' && $_POST['survey_auth_field2'] == $_POST['survey_auth_field3'] && $_POST['survey_auth_event_id2'] == $_POST['survey_auth_event_id3']) {
		$_POST['survey_auth_field2'] = $_POST['survey_auth_field3'];
		$_POST['survey_auth_field3'] = '';
	}
	if ($_POST['survey_auth_field1'] == '') {
		$_POST['survey_auth_field1'] = $_POST['survey_auth_field2'];
		$_POST['survey_auth_field2'] = $_POST['survey_auth_field3'];
		$_POST['survey_auth_field3'] = '';
	}
	if ($_POST['survey_auth_field2'] == '') {
		$_POST['survey_auth_field2'] = $_POST['survey_auth_field3'];
		$_POST['survey_auth_field3'] = '';
	}
	$_POST['survey_auth_min_fields'] = ($_POST['survey_auth_min_fields'] == '3' ? 3 : ($_POST['survey_auth_min_fields'] == '2' ? 2 : 1));
	$_POST['survey_auth_apply_all_surveys'] = ($_POST['survey_auth_apply_all_surveys'] == '1') ? 1 : 0;
	$_POST['survey_auth_custom_message'] = trim($_POST['survey_auth_custom_message']);
	// Count fields submitted to make sure min_fields count doesn't exceed actual field count
	$totalFields = ($_POST['survey_auth_field1'] == '' ? 0 : 1) + ($_POST['survey_auth_field2'] == '' ? 0 : 1) + ($_POST['survey_auth_field3'] == '' ? 0 : 1);
	if ($_POST['survey_auth_min_fields'] > $totalFields) {
		$_POST['survey_auth_min_fields'] = $totalFields;
	}
	// Update projects table
	$sql = "update redcap_projects set survey_auth_enabled = ".checkNull($_POST['survey_auth_enabled']).",
			survey_auth_field1 = ".checkNull($_POST['survey_auth_field1']).", survey_auth_event_id1 = ".checkNull($_POST['survey_auth_event_id1']).",
			survey_auth_field2 = ".checkNull($_POST['survey_auth_field2']).", survey_auth_event_id2 = ".checkNull($_POST['survey_auth_event_id2']).",
			survey_auth_field3 = ".checkNull($_POST['survey_auth_field3']).", survey_auth_event_id3 = ".checkNull($_POST['survey_auth_event_id3']).",
			survey_auth_min_fields = ".checkNull($_POST['survey_auth_min_fields']).", survey_auth_apply_all_surveys = ".checkNull($_POST['survey_auth_apply_all_surveys']).",
			survey_auth_custom_message = ".checkNull($_POST['survey_auth_custom_message']).",
			survey_auth_fail_limit = ".checkNull($_POST['survey_auth_fail_limit']).", survey_auth_fail_window = ".checkNull($_POST['survey_auth_fail_window'])."
			where project_id = $project_id";
	if (!db_query($sql)) exit('0');
	// Log the action
	Logging::logEvent($sql, "redcap_projects", "MANAGE", $project_id, "project_id = $project_id", "Add/edit survey login settings");
	// Output JSON
	$content = 	RCView::img(array('src'=>'tick.png')) .
				RCView::span(array('style'=>'color:green;'), $lang['survey_606']);
	print json_encode_rc(array('content'=>$content, 'title'=>$lang['survey_605'], 'login_enabled'=>$_POST['survey_auth_enabled']));
}

// ERROR
else exit('0');