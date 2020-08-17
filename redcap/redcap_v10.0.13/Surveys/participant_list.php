<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Increase memory limit in case needed for intensive processing (i.e. lots of participants)
System::increaseMemory(2048);

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();

// Ensure the survey_id belongs to this project
if (!$Proj->validateSurveyId($_GET['survey_id']))
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
}

// Retrieve survey info
$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value, ENT_QUOTES));
}

// Obtain current arm_id
$_GET['event_id'] = getEventId();
$_GET['arm_id'] = getArmId();

// Check if this is a follow-up survey
$isFollowUpSurvey = !($_GET['survey_id'] == $Proj->firstFormSurveyId && $Proj->isFirstEventIdInArm($_GET['event_id']));
// Check if a repeating survey
$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']);

// If using Survey Queue, go get survey queue hashes for all records on this page
$surveyQueueEnabled = Survey::surveyQueueEnabled();

// Section the Participant List into multiple pages
$num_per_page = 50;
$limit_begin  = 0;
$displayAllParticipants = false;
if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']))
{
	if ($_GET['pagenum'] > 1) {
		// Set to output specific page of participants
		$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
	} elseif ($_GET['pagenum'] == 0) {
		$displayAllParticipants = true;
	}
} else {
	$_GET['pagenum'] = 1;
}

if (isset($_GET['emailformat']) && $_GET['emailformat'] == '1') {
	$displayAllParticipants = true;
}

// Get record names and participant_id's for this page ONLY
$recordsThisPage = $participantIdsThisPage = $emailAddressCount = array();
if (!$displayAllParticipants) {
	list ($recordsThisPage, $participantIdsThisPage, $participant_count, $emailAddressFirstNumThisPage) = Survey::getParticipantListSinglePage($survey_id, $_GET['event_id'], $limit_begin, $num_per_page);
}

// Gather participant list (with identfiers and if Sent/Responded)
list ($part_list, $part_list_duplicates) = Survey::getParticipantList($survey_id, $_GET['event_id'], $recordsThisPage, $participantIdsThisPage);

foreach ($emailAddressFirstNumThisPage as $thisEmail=>$thisFirstNumThisPage) {
	if (!isset($part_list_duplicates[$thisEmail])) continue;
	if ($thisFirstNumThisPage <= 1) continue;
	$part_list_duplicates[$thisEmail]['total'] += $thisFirstNumThisPage+2;
	$part_list_duplicates[$thisEmail]['current'] = $thisFirstNumThisPage+2;
}

// Set array to fill with table display info
$part_list_full = array();

// Determine if designated email address and phone number is being used
$designatedEmailFieldRecord = $designatedPhoneFieldRecord = array();

// Create array of records for these participants
$surveyEmailInvitationFields = $Proj->getSurveyEmailInvitationFields(true);
if ($isRepeatingFormOrEvent || $surveyQueueEnabled || !empty($surveyEmailInvitationFields) || $survey_phone_participant_field != '')
{
	// Loop through participants to get record names
	$records = array();
	foreach ($part_list as $this_part=>$attr) {
		if ($attr['record'] != '') $records[] = $attr['record'];
	}
	// Determine if designated email address or designated phone number is being used
	if (!empty($surveyEmailInvitationFields) || $survey_phone_participant_field != '')
	{
		// Get data for email field for these records
		if (!empty($records)) {
			$survey_email_part_field_data = Records::getData('array', $records, array_merge($surveyEmailInvitationFields, array($survey_phone_participant_field)));
			// Loop through data and get non-blank email values and store for each record
			foreach ($survey_email_part_field_data as $this_record=>$event_data) {
				// Loop through all event data for this record
				foreach (array_keys($event_data) as $this_event_id) {
					if ($this_event_id == 'repeat_instances') {
						$eventNormalized = $event_data['repeat_instances'];
					} else {
						$eventNormalized = array();
						$eventNormalized[$this_event_id][""][0] = $event_data[$this_event_id];
					}
					foreach ($eventNormalized as $this_event_id=>$data1)
					{
						foreach ($data1 as $repeat_instrument=>$data2)
						{
							foreach ($data2 as $instance=>$field_data)
							{
								foreach ($surveyEmailInvitationFields as $thisSurveyId=>$surveyEmailInvitationField) {
									if ($field_data[$surveyEmailInvitationField] != ''
										&& ($surveyEmailInvitationField == $survey_email_participant_field || $thisSurveyId == $survey_id)) {
										$designatedEmailFieldRecord[$this_record][$thisSurveyId] = $field_data[$surveyEmailInvitationField];
									}
								}
								if ($field_data[$survey_phone_participant_field] != '') {
									$designatedPhoneFieldRecord[$this_record] = $field_data[$survey_phone_participant_field];
								}
							}
						}
					}
				}
			}
			unset($survey_email_part_field_data);
		}
	}
}

// CUSTOM FORM LABEL PIPING: Gather field names of all custom form labels (if any)
$pipedFormLabels = RepeatInstance::getPipedCustomRepeatingFormLabels($records, $_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']);


// DISPLAY FOR EMAIL POP-UP FORMAT (contains checkboxes and does not list those already responded)
if (isset($_GET['emailformat']) && $_GET['emailformat'] == '1')
{
	// Note if all participants are already checked off (if so, precheck the "check all" checkbox)
	$numPartUnchecked = $numPartChecked = 0;
	// Expand array with full details to render table
	$i = 0; // counter
	foreach ($part_list as $this_part=>&$attr)
	{
		// If this is the initial survey AND response was not created via Participant List, then do NOT display it here
		if ($attr['email'] == '' && isset($designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])) {
			$attr['email'] = $designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']];
		}
		// If we have no email, then we can't email this one, so skip it
		if (($attr['email'] == '' && !($twilio_enabled && $Proj->twilio_enabled_surveys)) || ($attr['email'] == '' && $attr['phone'] == '' && $twilio_enabled && $Proj->twilio_enabled_surveys)) continue;
		// Set "checked" status of checkbox if sent/unsent
		$sentclass = ($attr['sent']) ? "part_sent" : "part_unsent";
		// Set "checked" status of checkbox
		$schedclass = ($attr['scheduled'] == '') ? "unsched" : "sched";
		// Don't pre-check checkbox if they have been sent an email OR have partially completed survey
		$checked = ($attr['sent'] || $attr['scheduled'] != '' || $attr['response'] != '0') ? "" : "checked";
		// Check for duplicated emails in order to pre-pend with number, if needed
		$email_num = "";
		if ($part_list_duplicates[strtolower($attr['email'])]['total'] > 1) {
			$email_num = "<span style='color:#777;'>" . $part_list_duplicates[strtolower($attr['email'])]['current'] . ")</span>&nbsp;&nbsp;";
			$part_list_duplicates[strtolower($attr['email'])]['current']++; // Increment current email number for next time
		}
		// Skip those that have already responded completely UNLESS the Edit Completed Response option is enabled
		if ($attr['response'] == '2' && !$edit_completed_response) continue;
		if ($attr['response'] == "2") {
			// Responded
			$response_icon = '<img src="'.APP_PATH_IMAGES.'circle_green_tick.png">';
			$respond_class = "part_resp_full part_resp";
		} elseif ($attr['response'] == "1") {
			// Partial response
			$response_icon = '<img src="'.APP_PATH_IMAGES.'circle_orange_tick.png">';
			$respond_class = "part_resp_partial part_resp";
		} else {
			// No response
			$response_icon = '<img src="'.APP_PATH_IMAGES.'stop_gray.png">';
			$respond_class = "part_not_resp";
		}
		// For followup surveys, append record name after email
		$emailDisplay = $attr['email'];
		if ($attr['record'] != '') {
			if (
				// Display record name if participant has an Identifier
				($attr['identifier'] != '' && isset($participantParams[$this_part]))
				// OR if the email address originates from the designated email field
				|| ($survey_email_participant_field != ''
						&& isset($designatedEmailFieldRecord[$attr['record']][''])
						&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][''])
					)
				// OR if the email address originates from the designated SURVEY-LEVELemail field
				|| (isset($designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
						&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
					)
				)
			{				
				$instance = "";
				if ($isRepeatingFormOrEvent) {
					$instance .= ", <span class='nowrap'>#{$attr['repeat_instance']}";
					if (isset($pipedFormLabels[$attr['record']][$attr['repeat_instance']])) {
						$instance .= "&nbsp;-&nbsp;{$pipedFormLabels[$attr['record']][$attr['repeat_instance']]}";
					}
					$instance .= "</span>";
				}
				$emailDisplay .= "<span class='partListId'>(<span class='nowrap'>ID {$attr['record']}</span>{$instance})</span>";
			}
		}

		// For VOICE/SMS, add preference/phone/email as hidden input attributes
		$partpref = ($twilio_enabled && $Proj->twilio_enabled_surveys) ? " partpref='{$attr['delivery_preference']}'" : "";
		$hasPhone = ($twilio_enabled && $Proj->twilio_enabled_surveys) ? " hasphone='".($attr['phone'] == '' ? '0' : '1')."'" : "";
		$hasEmail = ($twilio_enabled && $Proj->twilio_enabled_surveys) ? " hasemail='".($attr['email'] == '' ? '0' : '1')."'" : "";

		// Add to array
		$part_list_full[$i] = array();
		$part_list_full[$i][] = "<input type='checkbox'{$partpref}{$hasPhone}{$hasEmail} class='chk_part $sentclass $schedclass $respond_class' id='chk_part{$this_part}' onclick='plsetcount();' $checked>";
		$part_list_full[$i][] = RCView::div(array('class'=>'wrapemail'), $email_num . $emailDisplay);
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			// Phone number
			$part_list_full[$i][] = formatPhone($attr['phone']);
		}
		$part_list_full[$i][] = RCView::div(array('class'=>'wrapemail'), $attr['identifier']);
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			// Delivery preference
			if ($attr['delivery_preference'] == 'VOICE_INITIATE') {
				$deliv_pref_icon = RCView::img(array('src'=>'phone.gif', 'title'=>$lang['survey_884']));
			} else if ($attr['delivery_preference'] == 'SMS_INITIATE') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloons_box.png', 'title'=>$lang['survey_767']));
			} else if ($attr['delivery_preference'] == 'SMS_INVITE_MAKE_CALL') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone.gif', 'title'=>$lang['survey_690']));
			} else if ($attr['delivery_preference'] == 'SMS_INVITE_RECEIVE_CALL') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone_receive.gif', 'title'=>$lang['survey_801']));
			} else if ($attr['delivery_preference'] == 'SMS_INVITE_WEB') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_link.gif', 'title'=>$lang['survey_955']));
			} else {
				$deliv_pref_icon = RCView::img(array('src'=>'email.png', 'title'=>$lang['global_33']));
			}
			$part_list_full[$i][] = $deliv_pref_icon;
		}
		$part_list_full[$i][] = ($attr['scheduled'] == '' ? '-' : ($attr['next_invite_is_reminder']
									? RCView::img(array('src'=>'clock_fill_bell.gif', 'title'=>$lang['survey_732']." ".DateTimeRC::format_ts_from_ymd($attr['scheduled'])))
									: RCView::img(array('src'=>'clock_fill.png', 'title'=>DateTimeRC::format_ts_from_ymd($attr['scheduled'])))));
		$part_list_full[$i][] = ($attr['sent'] ? RCView::img(array('src'=>'email_check.png','title'=>$lang['survey_316'])) : RCView::img(array('src'=>'email_gray.gif','title'=>$lang['survey_317'])));
		$part_list_full[$i][] = $response_icon;
		// Note if this one was checked
		if ($checked == "") $numPartUnchecked++; else $numPartChecked++;
		// Increment counter
		$i++;
		// Remove this row to save memory
		unset($part_list[$this_part]);
	}

	// Note if all are checked off
	$checkAllCheckboxChecked = ($numPartUnchecked == 0) ? "checked" : "";

	// If no participants exist yet, render one row to let user know that
	if (empty($part_list_full)) $part_list_full[] = array("",$lang['survey_34'],"","");

	// Build participant list table
	$partTableHeight = (count($part_list_full) <= 20) ? "auto" : 550;
	$partTableWidth = (count($part_list_full) <= 20) ? 561 : 578;
	$partTableHeaders = array();
	$emailHdr = $lang['global_33'].RCView::span(array('style'=>'margin-left:25px;color:#800000;'),
					$lang['leftparen'].RCView::span(array('id'=>'plist_selected', 'style'=>''), $numPartChecked) . " " . $lang['survey_1010'].$lang['rightparen']
				);
	$partTableHeaders[] = array(16, RCView::checkbox(array($checkAllCheckboxChecked=>$checkAllCheckboxChecked, 'onclick'=>"var ischkd = $(this).prop('checked'); var pt = $('#table-participant_table_email input[type=checkbox]'); pt.prop('checked', ischkd); plsetcount();")), "center");
	if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
		$partTableHeaders[] = array(111, $emailHdr);
		$partTableHeaders[] = array(95, $lang['design_89']);
		$partTableHeaders[] = array(80, RCView::span(array('class'=>'wrap'), $lang['survey_250']));
		$partTableHeaders[] = array(55, RCView::span(array('class'=>'wrap', 'style'=>'font-size:11px;'), $lang['survey_779']), "center");
		$partTableWidth += 62;
	} else {
		$partTableHeaders[] = array(198, $emailHdr);
		$partTableHeaders[] = array(105, RCView::span(array('style'=>'font-size:11px;'), $lang['survey_250']));
	}
	$partTableHeaders[] = array(56, RCView::span(array('style'=>'font-size:11px;'), $lang['survey_669']), "center");
	$partTableHeaders[] = array(28, RCView::span(array('style'=>'font-size:11px;'), $lang['survey_36']), 'center');
	$partTableHeaders[] = array(40, RCView::span(array('class'=>'wrap', 'style'=>'font-size:11px;word-break:break-all;word-wrap:break-word;'), $lang['survey_47']), 'center');


	// Create drop-down of action choices for checking/unchecking participants in list
	$checkOptions = array(''=>$lang['survey_280']);
	$checkOptions['check_all'] = $lang['survey_41'];
	$checkOptions['uncheck_all'] = $lang['survey_42'];
	$checkOptions['check_sent'] = $lang['survey_39'];
	$checkOptions['check_unsent'] = $lang['survey_40'];
	$checkOptions['check_sched'] = $lang['survey_319'];
	$checkOptions['check_unsched'] = $lang['survey_320'];
	$checkOptions['check_unsent_unsched'] = $lang['survey_321'];
	$checkOptions['check_resp'] = $lang['survey_673'];
	$checkOptions['check_resp_partial'] = $lang['survey_671'];
	$checkOptions['check_resp_full'] = $lang['survey_672'];
	$checkOptions['check_not_resp'] = $lang['survey_670'];
	$partTableTitle =	RCView::div(array('style'=>'padding:0;'),
							RCView::div(array('style'=>'float:left;font-size:13px;padding:0 0 2px;'),
								$lang['survey_37'] . RCView::br() .
								RCView::span(array('style'=>'font-weight:normal;font-size:11px;color:#666;'),
									($edit_completed_response ? $lang['survey_769'] : $lang['survey_1008'])
								)
							) .
							RCView::div(array('style'=>'float:right;font-size:11px;'),
								$lang['survey_281'] .
								RCView::select(array('onchange'=>'emailPartPreselect(this.value);plsetcount();','style'=>'max-width: 200px;font-weight:normal;margin-left:5px;font-size:11px;'), $checkOptions)
							)
						);
	// Build Participant List
	renderGrid("participant_table_email", $partTableTitle, $partTableWidth, $partTableHeight, $partTableHeaders, $part_list_full);
}





// DISPLAY FOR MAIN PAGE
elseif (!isset($_GET['emailformat']))
{
	## Build drop-down list of surveys/events
	// Create drop-down of ALL surveys and, if longitudinal, the events for which they're designated
	$surveyEventOptions = array();
	// Loop through each event and output each where this form is designated
	foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
		// Loop through forms
		foreach ($these_forms as $form_name) {
			// Ignore if not a survey
			if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
			// Get survey_id
			$this_survey_id = $Proj->forms[$form_name]['survey_id'];
			// If this is the first form and first event, note it as "public survey"
			$public_survey_text = ($Proj->isFirstEventIdInArm($this_event_id) && $form_name == $Proj->firstForm) ? $lang['survey_351']." " : "";
			// If longitudinal, add event name
			$event_name = ($longitudinal) ? " - ".$Proj->eventInfo[$this_event_id]['name_ext'] : "";
			// If survey title is blank (because using a logo instead), then insert the instrument name
			$survey_title = ($Proj->surveys[$this_survey_id]['title'] == "") ? $Proj->forms[$form_name]['menu'] : $Proj->surveys[$this_survey_id]['title'];
			// Truncate survey title if too long
			if (strlen($public_survey_text.$survey_title.$event_name) > 70) {
				$survey_title = substr($survey_title, 0, 67-strlen($public_survey_text)-strlen($event_name)) . "...";
			}
			// Add this survey/event as drop-down option
			$surveyEventOptions["$this_survey_id-$this_event_id"] = "$public_survey_text\"$survey_title\"$event_name";
		}
	}

	// Collect HTML
	$surveyEventDropdown = RCView::select(array('class'=>"x-form-text x-form-field",
		'style'=>'max-width:400px;font-weight:bold;font-size:11px;',
		'onchange'=>"if(this.value!=''){showProgress(1);var seid = this.value.split('-'); window.location.href = app_path_webroot+'Surveys/invite_participants.php?pid=$project_id&participant_list=1&survey_id='+seid[0]+'&event_id='+seid[1];}"),
			$surveyEventOptions, $_GET['survey_id']."-".$_GET['event_id'], 500
		);


	## Option to enable/disable PARTICIPANT IDENTIFIERS
	$partIdentBtnDisabled = ($status < 1 || $super_user) ? "" : "disabled";
	$partIdentDisabled = "";
	$partIdentHdrStyle = "margin-right:2px;";
	if (!$enable_participant_identifiers) {
		// Disabled
		$enablePartIdent = "&nbsp; <button onclick='enablePartIdent({$_GET['survey_id']},{$_GET['event_id']});' class='jqbuttonsm' style='color:#007000;' $partIdentBtnDisabled>{$lang['survey_152']}</button>";
		$partIdentHdrStyle = "margin-right:20px;color:#888;";
	} else {
		// Enabled
		$partIdentDisabled = $lang['survey_251'];
		$enablePartIdent = "<div style='margin-top:5px;'><button onclick='enablePartIdent({$_GET['survey_id']},{$_GET['event_id']});' class='jqbuttonsm' style='color:#800000;' $partIdentBtnDisabled>{$lang['control_center_153']}</button></div>";
	}
	// Remove enable/disable button for followup surveys
	if ($isFollowUpSurvey) $enablePartIdent = "";
	
	// Survey time limit enabled
	$timeLimit = Survey::calculateSurveyTimeLimit($Proj->surveys[$_GET['survey_id']]['survey_time_limit_days'], $Proj->surveys[$_GET['survey_id']]['survey_time_limit_hours'], $Proj->surveys[$_GET['survey_id']]['survey_time_limit_minutes']);
	$timeLimitEnabled = ($timeLimit > 0);

	// First, get form, record, and event_id for all complete/partial responses to display as links
	$participantParams = array();
	if (!empty($part_list))
	{
		$sql = "select s.form_name, r.record, p.event_id, p.participant_id, r.instance
				from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r
				where s.survey_id = {$_GET['survey_id']} and s.survey_id = p.survey_id
				and p.participant_id = r.participant_id and p.event_id = {$_GET['event_id']}
				and p.participant_id in (".implode(", ", array_keys($part_list)).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add params to array with participant_id as key
			$participantParams[$row['participant_id']] = "&page={$row['form_name']}&id={$row['record']}&event_id={$row['event_id']}&instance={$row['instance']}";
		}
	}

	// If Survey Queue is enabled, get all record names so we can get the survey queue link for each
	if ($surveyQueueEnabled) {
		// Get survey queue hashes for all records
		$surveyQueueHashes = Survey::getRecordSurveyQueueHashBulk($records);
	}
	unset($records);

	// Expand array with full details to render table
	$i = 0; // counter
	foreach ($part_list as $this_part=>&$attr)
	{
		// Trim identifier
		$attr['identifier'] = trim($attr['identifier']);
		// Check for duplicated emails in order to pre-pend with number, if needed
		$email_num = "";
		$email_num_raw = "";
		if (isset($attr['email']) && isset($part_list_duplicates[$attr['email']])
			&& $part_list_duplicates[strtolower($attr['email'])]['total'] > 1) {
			$email_num_raw = $part_list_duplicates[strtolower($attr['email'])]['current'];
			$email_num = "<span style='color:#777;'>$email_num_raw)</span>&nbsp;&nbsp;";
			$part_list_duplicates[strtolower($attr['email'])]['current']++; // Increment current email number for next time
		}
		// If there is no email address, then don't allow users to "edit" it (it will display "[No email listed]" text
		$editemail = ($attr['email'] == '') ? "" : "editemail";
		$editphone = "editphone";
		// Set flag to edit identifier ONLY if identifier already has a value OR response
		$editidentifier = ($attr['record'] == '' || ($attr['record'] != '' && $attr['identifier'] != '')) ? "editidentifier" : "noeditidentifier";
		if (($attr['identifier'] != '' && isset($participantParams[$this_part]))
			// OR if the email address originates from the designated email field
			|| ($survey_email_participant_field != ''
					&& isset($designatedEmailFieldRecord[$attr['record']][''])
					&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][''])
				)
			// OR if the email address originates from the designated SURVEY-LEVELemail field
			|| (isset($designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
					&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
				)
			// OR if the phone number originates from the designated phone field
			|| ($survey_phone_participant_field != ''
					&& isset($designatedPhoneFieldRecord[$attr['record']])
					&& (formatPhone($attr['phone']) == formatPhone($designatedPhoneFieldRecord[$attr['record']]))
				)
		) {
			$viewresponse = "viewresponse";
			$imgtitle = 'title="'.js_escape2($lang['survey_245']).'"';
		} else {
			$viewresponse = "noviewresponse";
			$imgtitle = '';
		}
		// Set response and link icons
		$link_icon = "<a target='_blank' href='" . APP_PATH_SURVEY_FULL . "?s={$attr['hash']}'><img class='partLink' src='".APP_PATH_IMAGES."link.png' title=\"".js_escape2($lang['survey_246'])."\"></a>";
		$access_code = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>"getAccessCode('{$attr['hash']}');"),
							(!gd2_enabled()
								? RCView::img(array('src'=>'ticket_arrow.png', 'style'=>'vertical-align:middle;'))
								: RCView::img(array('src'=>'access_qr_code.gif', 'style'=>'vertical-align:middle;'))
							)
						);
		if ($attr['response'] == "2") {
			// Responded
			$response_icon = '<img class="'.$viewresponse.'" src="'.APP_PATH_IMAGES.'circle_green_tick.png" '.$imgtitle.'>';
			// Do not show link/access code icons UNLESS the Edit Completed Response option is enabled
			if (!$edit_completed_response) $link_icon = $access_code = '-';
		} elseif ($attr['response'] == "1") {
			// Partial response
			$response_icon = '<img class="'.$viewresponse.'" src="'.APP_PATH_IMAGES.'circle_orange_tick.png" '.$imgtitle.'>';
		} else {
			// No response
			$response_icon = '<img src="'.APP_PATH_IMAGES.'stop_gray.png">';
		}
		// Add email if from designated field
        if ($attr['email'] == '' && isset($designatedEmailFieldRecord[$attr['record']])) {
            $attr['email'] = isset($designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
                ? $designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']]
                : $designatedEmailFieldRecord[$attr['record']][''];
        }
		// Add link to response (ONLY if has identifier and ONLY for partial and complete responses)
		$displayRecordId = false;
		if (($attr['identifier'] != '' && isset($participantParams[$this_part]))
			// OR if the email address originates from the designated email field
			|| ($survey_email_participant_field != ''
					&& isset($designatedEmailFieldRecord[$attr['record']][''])
					&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][''])
				)
			// OR if the email address originates from the designated SURVEY-LEVELemail field
			|| (isset($designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
					&& ($attr['email'] == $designatedEmailFieldRecord[$attr['record']][$_GET['survey_id']])
				)
			// OR if the phone number originates from the designated phone field
			|| ($survey_phone_participant_field != ''
					&& isset($designatedPhoneFieldRecord[$attr['record']])
					&& (formatPhone($attr['phone']) == formatPhone($designatedPhoneFieldRecord[$attr['record']]))
				)
		) {
			$displayRecordId = true;
			$response_icon = "<a href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id{$participantParams[$this_part]}'>$response_icon</a>";
		}
		// If this is the initial survey AND response was not created via Participant List, then do NOT display it here
		if (isset($designatedEmailFieldRecord[$attr['record']])) $editemail = "noeditemailpublic";
		if (isset($designatedPhoneFieldRecord[$attr['record']])) $editphone = "noeditphonepublic";
		// Append record name after email address IF has an identifier
		$emailDisplay = $attr['email'];
		$display_id = "";
		if ($attr['record'] != '') {
			if ($attr['email'] == '') $emailDisplay .= "<i>{$lang['survey_284']}</i>";
			if ($displayRecordId)
			{
				$instance = "";
				if ($isRepeatingFormOrEvent) {
					$instance .= "<span class='nowrap'>#{$attr['repeat_instance']}";
					if (isset($pipedFormLabels[$attr['record']][$attr['repeat_instance']])) {
						$instance .= "&nbsp;-&nbsp;{$pipedFormLabels[$attr['record']][$attr['repeat_instance']]}";
					}
					$instance .= "</span>";
					$instance = "<span class='partListId'>({$instance})</span>";
				}
				$display_id = "<a href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&page={$Proj->surveys[$_GET['survey_id']]['form_name']}&event_id={$_GET['event_id']}&id={$attr['record']}&instance={$attr['repeat_instance']}' style='font-size:12px;text-decoration:underline;'>{$attr['record']}</a>$instance";
			} else {
				$display_id = '<i class="far fa-eye-slash" style="color:#ddd;"></i>';
			}
		}
		// If identifiers are disabled
		if (!$enable_participant_identifiers && ($attr['response'] == "0" || $attr['identifier'] == '')) {
			// Set identifier text as "disabled"
			$attr['identifier'] = $lang['global_23'];
			// Set "disabled" class for identifier cells
			$editidentifier = "partIdentColDisabled";
		}
		// If identifier is blank, add space to make it clearly editable
		else {
			if ($attr['identifier'] == '') $attr['identifier'] = '&nbsp;';
		}
		// Add to array
		$part_list_full[$i] = array();
		$part_list_full[$i][] = "<span style='display:none;'>{$attr['email']}{$email_num_raw}</span>" .
								"<div class='$editemail wrapemail' id='editemail_{$this_part}' part='$this_part'>{$email_num}{$emailDisplay}";
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			// Phone number
			$attr['phone'] = ($attr['phone'] == '') ? '&nbsp;' : formatPhone($attr['phone']);
			$part_list_full[$i][] = "<div class='$editphone' id='editphone_{$this_part}' part='$this_part'>{$attr['phone']}</div>";
		}
		$part_list_full[$i][] = "<div class='wrap' style='word-wrap:break-word;'>$display_id</div>";
		$part_list_full[$i][] = "<div class='$editidentifier wrapemail' id='editidentifier_{$this_part}' part='$this_part'>{$attr['identifier']}</div>";
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			// Deliever preference icon
			$part_list_full[$i][] = RCView::div(array('class'=>'editinvpref', 'pref'=>$attr['delivery_preference'], 'id'=>'editinvpref_'.$this_part, 'rec'=>$attr['record'], 'part'=>$this_part),
										Survey::getDeliveryPrefIcon($attr['delivery_preference'])
									);
		}
		$part_list_full[$i][] = $response_icon;
		if ($attr['scheduled'] != '' && $attr['scheduled'] <= NOW ) {
			$part_list_full[$i][] = '-';
			// If email was scheduled (or was sent Immediately but cron has not sent it yet) and is sending right now, give special email icon
			$part_list_full[$i][] = RCView::img(array('src'=>'email_go.png','title'=>$lang['survey_346']));
		} else {
			$part_list_full[$i][] = ($attr['scheduled'] == '' ? '-' : ($attr['next_invite_is_reminder']
									? RCView::img(array('src'=>'clock_fill_bell.gif', 'title'=>$lang['survey_732']." ".DateTimeRC::format_ts_from_ymd($attr['scheduled'])))
									: RCView::img(array('src'=>'clock_fill.png', 'title'=>DateTimeRC::format_ts_from_ymd($attr['scheduled'])))));
			// If email was sent or not yet, display icon for each
			$part_list_full[$i][] = ($attr['sent'] ? RCView::img(array('src'=>'email_check.png','title'=>$lang['survey_316'])) : RCView::img(array('src'=>'email_gray.gif','title'=>$lang['survey_317'])));
		}
		// If time limit enabled, then display icons if still open or expired
		if ($timeLimitEnabled) {
			if ($attr['link_expiration'] == '') {
				$attr['link_expiration'] = '-';
			} else {
				$thisExpireTimestamp = DateTimeRC::format_ts_from_ymd($attr['link_expiration']);
				if (strtotime(NOW) > strtotime($attr['link_expiration'])) {
					// If initial survey invite time + time limit is > now, then the link has expired
					$attr['link_expiration'] = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>"changeLinkExpiration($this_part);"),
													RCView::img(array('src'=>'cross-octagon.png', 'class'=>'opacity65', 'title'=>$lang['survey_1119']." ".$thisExpireTimestamp))
												);
					// Since link has expired, do not show the clickable link icon
					$link_icon = '-';
				} else {
					// Not yet expired
					$attr['link_expiration'] = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>"changeLinkExpiration($this_part);"),
													RCView::img(array('src'=>'clock_fill.png', 'title'=>$lang['survey_1118']." ".$thisExpireTimestamp))
												);
				}
			}
			$part_list_full[$i][] = RCView::div(array('class'=>'wrap', 'style'=>'font-size:11px;'), $attr['link_expiration']);
		}
		$part_list_full[$i][] = $link_icon;
		// Quick code and QR code
		$part_list_full[$i][] = $access_code;
		// If Survey Queue is enabled, display as new column
		if ($surveyQueueEnabled) {
			if ($attr['record'] != '' && isset($surveyQueueHashes[$attr['record']])) {
				$part_list_full[$i][] = RCView::a(array('href'=>APP_PATH_SURVEY_FULL.'?sq='.$surveyQueueHashes[$attr['record']], 'target'=>'_blank'),
											RCView::img(array('src'=>'list_red_sm.gif', 'title'=>$lang['survey_553']))
										);
			} else {
				$part_list_full[$i][] = '-';
			}
		}
		// Do not allow user to delete this participant if this is a follow-up survey OR if a public survey response OR if record exists
		// OR if this is an initial survey that was been started.
		$part_list_full[$i][] = ($attr['response'] > 0 || $isFollowUpSurvey || $attr['record'] != '') ? "" : '<a onclick=\'deleteParticipant('.$_GET['survey_id'].','.$_GET['event_id'].','.$this_part.');\'" href="javascript:;" style="color:#888;font-size:10px;text-decoration:underline;">'.$lang['survey_43'].'</a>';
		// Increment counter
		$i++;
		// Remove this row to save memory
		unset($part_list[$this_part]);
	}

	// If no participants exist yet, render one row to let user know that
	//$part_list_full = array();
	if (empty($part_list_full))
	{
		// No participants exist yet
		$part_list_full[0] = array($lang['survey_44'],"","","","","","","","","");
	}

	if ($displayAllParticipants) {
		// Get participant count
		$participant_count = count($part_list_full);
		// Take full participant list and cut down to one page length of participants
		// $part_list_full = array_slice($part_list_full, $limit_begin, $num_per_page);
	}
	## Build the paging drop-down for participant list
	$pageDropdown = "<select id='pageNumSelect' onchange='loadPartList({$_GET['survey_id']},{$_GET['event_id']},this.value);' style='vertical-align:middle;font-size:11px;'>";
	// Set "all participants" option as value '0'
	$pageDropdown .= "<option value='0' " . ($displayAllParticipants ? "selected" : "") . ">-- {$lang['docs_44']} --</option>";
	//Calculate number of pages of for dropdown
	$num_pages = ceil($participant_count/$num_per_page);
	//Loop to create options for dropdown
	for ($i = 1; $i <= $num_pages; $i++) {
		$end_num   = $i * $num_per_page;
		$begin_num = $end_num - $num_per_page + 1;
		$value_num = $end_num - $num_per_page;
		if ($end_num > $participant_count) $end_num = $participant_count;
		$pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">".User::number_format_user($begin_num)." - ".User::number_format_user($end_num)."</option>";
	}
	if ($num_pages == 0) {
		$pageDropdown .= "<option value=''>0</option>";
	}
	$pageDropdown .= "</select>";
	$pageDropdown  = "<span style='margin-right:25px;'>{$lang['survey_45']} $pageDropdown {$lang['survey_133']} ".User::number_format_user($participant_count)."</span>";

	// Build participant list table
	$partTableWidth = 915;
	$partTableHeaders = array();
	if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
		$partTableHeaders[] = array(170, $lang['global_33']);
		$partTableHeaders[] = array(100, $lang['design_89']);
		$partTableHeaders[] = array(60, $lang['global_49'], "center");
		$partTableHeaders[] = array(154, "<div style='$partIdentHdrStyle'>{$lang['survey_250']} $partIdentDisabled</div><div style='text-align:right;padding:3px 0 1px;'>$enablePartIdent</div>");
		$partTableHeaders[] = array(50, "<div class='wrap' style='line-height:13px;'>{$lang['survey_779']}</div>", "center");
		$partTableWidth += 62;
	} else {
		$partTableHeaders[] = array(236, $lang['global_33']);
		$partTableHeaders[] = array(60, $lang['global_49'], "center");
		$partTableHeaders[] = array(200, "<div style='$partIdentHdrStyle'>{$lang['survey_250']} $partIdentDisabled</div> $enablePartIdent");
	}
	$partTableHeaders[] = array(68,  $lang['survey_47'], "center");
	$partTableHeaders[] = array(62, "<div class='wrap' style='line-height:13px;'>{$lang['survey_318']}</div>", "center");
	$partTableHeaders[] = array(52,  "<div class='wrap' style='line-height:13px;'>{$lang['survey_46']}</div>", "center");
	// Survey time limit enabled
	if ($timeLimitEnabled) {
		$partTableHeaders[] = array(55, "<div class='wrap' style='line-height:13px;'>{$lang['survey_1117']}</div><div class='wrap' style='font-size:10px;color:#666;line-height:11px;margin-top:5px;'>{$lang['survey_1120']}</div>", "center");
		$partTableWidth += 67;
	}
	$partTableHeaders[] = array(24,  $lang['design_196'], "center");
	// Quick code and QR code
	$partTableHeaders[] = array(45, "<div class='wrap' style='font-size:10px;line-height:11px;'>".(gd2_enabled() ? $lang['survey_627'] : $lang['survey_628'])."</div>", "center");
	// If Survey Queue is enabled, display as new column
	if ($surveyQueueEnabled) {
		$partTableHeaders[] = array(35, "<div class='wrap' style='line-height:13px;'>{$lang['survey_505']}</div>", "center");
		$partTableWidth += 47;
	}
	$partTableHeaders[] = array(40,  " ", "center");
	// Table title
	$partTableTitle =  "<input type='hidden' id='enable_participant_identifiers' value='$enable_participant_identifiers'>
						<!-- Participant List table -->
						<table id='partListTitle' cellspacing='0' style='width:100%;table-layout:fixed;'>
							<tr>
								<td valign='bottom'>
									<div style='vertical-align:middle;color:#000;font-size:14px;padding:0;'>
										{$lang['survey_37']}
										<span class='wrap'>
											".RCView::span(array('style'=>'padding:0 3px;font-weight:normal;color:#666;font-size:12px;'), $lang['survey_33'])."
											$surveyEventDropdown
										</span>
									</div>
									<div class='wrap' style='vertical-align:middle;font-weight:normal;padding:8px 0 0;color:#555;'>
										$pageDropdown
										<span id='addPartsBtnSpan'><button id='addPartsBtn' class='jqbuttonmed' ".($isFollowUpSurvey ? "disabled" : "")." onclick=\"
											addPart({$_GET['survey_id']},{$_GET['event_id']});
										\"><i class=\"fas fa-user-plus\"></i> <span style='vertical-align:middle;'>{$lang['survey_230']}</span></button></span>
										<button id='sendEmailsBtn' class='jqbuttonmed' onclick=\"sendEmails($survey_id,{$_GET['event_id']});\"><i class=\"fas fa-envelope\"></i>
											<span style='vertical-align:middle;'>{$lang['survey_266']}</span></button>
									</div>
								</td>
								<td valign='bottom' class='d-none d-md-table-cell' style='text-align:right;width:150px;'>
									".((!$isFollowUpSurvey && $_GET['event_id'] == $Proj->firstEventId) ?
										"<div style='padding:0 0 5px 0;'>
											<button class='jqbuttonsm' style='color:#666;' onclick=\"deleteParticipants({$_GET['survey_id']},{$_GET['event_id']})\">{$lang['survey_166']}</button>
										</div>"
									  : ""
									)."
									<div style='padding:0'>
										<button class='jqbuttonmed' onclick=\"
											window.location.href='".APP_PATH_WEBROOT."Surveys/participant_export.php?pid=$project_id&survey_id={$_GET['survey_id']}&event_id={$_GET['event_id']}';
										\"><img src='".APP_PATH_IMAGES."xls.gif' style='vertical-align:middle;'> <span style='vertical-align:middle;'>{$lang['survey_229']}</span></button>
									</div>
								</td>
							</tr>
							<tr class='d-block d-md-none'>
								<td valign='bottom' style='text-align:right;padding-top:5px;'>
									".((!$isFollowUpSurvey && $_GET['event_id'] == $Proj->firstEventId) ?
										"<button class='jqbuttonsm' style='color:#666;' onclick=\"deleteParticipants({$_GET['survey_id']},{$_GET['event_id']})\">{$lang['survey_166']}</button>"
									  : ""
									)."
									<button class='jqbuttonmed' onclick=\"
										window.location.href='".APP_PATH_WEBROOT."Surveys/participant_export.php?pid=$project_id&survey_id={$_GET['survey_id']}&event_id={$_GET['event_id']}';
									\"><img src='".APP_PATH_IMAGES."xls.gif' style='vertical-align:middle;'> <span style='vertical-align:middle;'>{$lang['survey_229']}</span></button>
								</td>
							</tr>
						</table>";
	// Build Participant List
	renderGrid("participant_table", $partTableTitle, $partTableWidth-count($partTableHeaders), "auto", $partTableHeaders, $part_list_full);
}