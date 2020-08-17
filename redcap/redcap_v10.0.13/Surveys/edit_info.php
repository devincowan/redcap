<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Determine the instrument
$form = (isset($_GET['page']) && isset($Proj->forms[$_GET['page']])) ? $_GET['page'] : $Proj->firstForm;

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = Survey::getSurveyId($form);
}


if (Survey::checkSurveyProject($_GET['survey_id']))
{
	// Default message
	$msg = "";

	// Retrieve survey info
	$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
	foreach (db_fetch_assoc($q) as $key => $value)
	{
		if ($value === null) {
			$$key = $value;
		} else {
			// Replace non-break spaces because they cause issues with html_entity_decode()
			$value = str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $value);
			// Don't decode if cannnot detect encoding
			if (function_exists('mb_detect_encoding') && (
				(mb_detect_encoding($value) == 'UTF-8' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === false)
				|| (mb_detect_encoding($value) == 'ASCII' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === 'UTF-8')
			)) {
				$$key = trim($value);
			} else {
				$$key = trim(html_entity_decode($value, ENT_QUOTES));
			}
		}
	}
	if ($survey_expiration != '')
	{
		$expiration = substr($survey_expiration, 0, -3);
		if (strstr($expiration, ' '))
		{
			list ($survey_expiration_date, $survey_expiration_time) = explode(" ", $expiration, 2);
			$survey_expiration = DateTimeRC::format_ts_from_ymd($survey_expiration_date)." $survey_expiration_time";
		}
		else
		{
			$survey_expiration_time = '';
			list ($survey_expiration_date,) = explode(" ", $expiration, 2);
			$survey_expiration = DateTimeRC::format_ts_from_ymd($survey_expiration_date);
		}
	}


	/**
	 * PROCESS SUBMITTED CHANGES
	 */
	if ($_SERVER['REQUEST_METHOD'] == "POST")
	{
		// Build "go back" button to specific page
		if (isset($_GET['redirectDesigner'])) {
			// Go back to Online Designer
			$goBackBtn = renderPrevPageBtn("Design/online_designer.php",$lang['global_77'],false);
		} else {
			// Go back to Project Setup page
			$goBackBtn = renderPrevPageBtn("ProjectSetup/index.php?&msg=surveymodified",$lang['global_77'],false);
		}
		$msg = RCView::div(array('style'=>'padding:0 0 20px;'), $goBackBtn);
		
		// Get current value of Time Limit option
		$currentTimeLimitSeconds = Survey::calculateSurveyTimeLimit($survey_time_limit_days, $survey_time_limit_hours, $survey_time_limit_minutes);
		// Assign Post array as globals
		foreach ($_POST as $key => $value) $$key = $value;
		// If some fields are missing from Post because disabled drop-downs don't post, then manually set their default value.
		if (!isset($_POST['pdf_auto_archive'])) 		$pdf_auto_archive = '0';
		if (!isset($_POST['question_auto_numbering'])) 	$question_auto_numbering = '0';
		if (!isset($_POST['show_required_field_text'])) $show_required_field_text = '0';
		if (!isset($_POST['save_and_return'])) 			$save_and_return = '0';
		if (!isset($_POST['question_by_section'])) 		$question_by_section = '1';
		if (!isset($_POST['view_results'])) 			$view_results = '0';
		if (!isset($_POST['promis_skip_question'])) 	$promis_skip_question = '0';
		if (!isset($_POST['survey_auth_enabled_single'])) 	$survey_auth_enabled_single = '0';
		$enhanced_choices = (isset($_POST['enhanced_choices']) && is_numeric($_POST['enhanced_choices'])) ? $_POST['enhanced_choices'] : '0';
		$edit_completed_response = (isset($_POST['edit_completed_response']) && $_POST['edit_completed_response'] == 'on') ? '1' : '0';
		$display_page_number = (isset($_POST['display_page_number']) && $_POST['display_page_number'] == 'on') ? '1' : '0';
		$hide_back_button = (isset($_POST['hide_back_button']) && $_POST['hide_back_button'] == 'on') ? '1' : '0';
		// Set checkbox value
		$check_diversity_view_results = (isset($check_diversity_view_results) && $check_diversity_view_results == 'on') ? 1 : 0;
		if (!isset($view_results)) $view_results = 0;
		if (!isset($min_responses_view_results)) $min_responses_view_results = 10;
		if ($survey_termination_options == 'url') {
			$acknowledgement = '';
		} else {
			$end_survey_redirect_url = '';
		}
		// AutoContinue - Set checkbox to 0 if not in post
		$end_survey_redirect_next_survey = (isset($_POST['end_survey_redirect_next_survey']) && $_POST['end_survey_redirect_next_survey'] == 'on') ? '1' : '0';
		// Reformat $survey_expiration from MDYHS to YMDHS for saving purposes
		if ($survey_expiration != '') {
			$survey_expiration_save = DateTimeRC::format_ts_to_ymd(trim($survey_expiration)).":00";
		} else {
			$survey_expiration_save = '';
		}
		// Set if the survey is active or offline
		if (isset($_POST['survey_enabled'])) {
			$survey_enabled = $_POST['survey_enabled'];
		}
		$survey_enabled = ($survey_enabled == '1') ? '1' : '0';
		$repeat_survey_enabled = (isset($repeat_survey_enabled) && $repeat_survey_enabled == 'on') ? '1' : '0';
		if (!$repeat_survey_enabled) $repeat_survey_btn_text = '';
		$text_to_speech = (is_numeric($text_to_speech)) ? $text_to_speech : '0';
		if ($confirmation_email_content == '') $confirmation_email_from = '';
		$confirmation_email_attach_pdf = ($confirmation_email_attach_pdf == 'on') ? '1' : '0';
		if ($text_to_speech_language == '') $text_to_speech_language = 'en';
		if (!isset($text_size) || !is_numeric($text_size)) $text_size = '';
		if (!isset($font_family) || !is_numeric($font_family)) $font_family = '';
		$repeat_survey_btn_location = ($repeat_survey_btn_location == 'BEFORE_SUBMIT') ? 'BEFORE_SUBMIT' : 'AFTER_SUBMIT';
		$response_limit = (isset($response_limit) && is_numeric($response_limit) && !empty($response_limit)) ? $response_limit : '';
		$response_limit_include_partials = (isset($response_limit_include_partials) && $response_limit_include_partials == '0') ? '0' : '1';
		$survey_time_limit_days = (isset($survey_time_limit_days) && is_numeric($survey_time_limit_days) && !empty($survey_time_limit_days)) ? $survey_time_limit_days : '';
		$survey_time_limit_hours = (isset($survey_time_limit_hours) && is_numeric($survey_time_limit_hours) && !empty($survey_time_limit_hours)) ? $survey_time_limit_hours : '';
		$survey_time_limit_minutes = (isset($survey_time_limit_minutes) && is_numeric($survey_time_limit_minutes) && !empty($survey_time_limit_minutes)) ? $survey_time_limit_minutes : '';
		$end_of_survey_pdf_download = (isset($end_of_survey_pdf_download) && $end_of_survey_pdf_download == '1') ? '1' : '0';
		$save_and_return_code_bypass = (isset($save_and_return_code_bypass) && $save_and_return_code_bypass == 'on') ? '1' : '0';
		// Get new value of Time Limit option
		$newTimeLimitSeconds = Survey::calculateSurveyTimeLimit($survey_time_limit_days, $survey_time_limit_hours, $survey_time_limit_minutes);
		// If Time Limit changed, then we need to reset all the cached values in the participants table
		$resetCachedLinkExpiration = ($currentTimeLimitSeconds != $newTimeLimitSeconds);		
		// Custom theme elements
		if (!isset($_POST['theme'])) {
			$theme = '';
			$regex_color = "/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/";
			$theme_bg_page = (isset($_POST['theme_bg_page']) && preg_match($regex_color, $_POST['theme_bg_page'])) ? substr($_POST['theme_bg_page'], 1) : '';
			$theme_text_buttons = (isset($_POST['theme_text_buttons']) && preg_match($regex_color, $_POST['theme_text_buttons'])) ? substr($_POST['theme_text_buttons'], 1) : '';
			$theme_text_title = (isset($_POST['theme_text_title']) && preg_match($regex_color, $_POST['theme_text_title'])) ? substr($_POST['theme_text_title'], 1) : '';
			$theme_bg_title = (isset($_POST['theme_bg_title']) && preg_match($regex_color, $_POST['theme_bg_title'])) ? substr($_POST['theme_bg_title'], 1) : '';
			$theme_text_question = (isset($_POST['theme_text_question']) && preg_match($regex_color, $_POST['theme_text_question'])) ? substr($_POST['theme_text_question'], 1) : '';
			$theme_bg_question = (isset($_POST['theme_bg_question']) && preg_match($regex_color, $_POST['theme_bg_question'])) ? substr($_POST['theme_bg_question'], 1) : '';
			$theme_text_sectionheader = (isset($_POST['theme_text_sectionheader']) && preg_match($regex_color, $_POST['theme_text_sectionheader'])) ? substr($_POST['theme_text_sectionheader'], 1) : '';
			$theme_bg_sectionheader = (isset($_POST['theme_bg_sectionheader']) && preg_match($regex_color, $_POST['theme_bg_sectionheader'])) ? substr($_POST['theme_bg_sectionheader'], 1) : '';
		} else {
			$theme = $_POST['theme'];
		}
		if (!isset($pdf_econsent_firstname_field)) $pdf_econsent_firstname_field = '';
		if (!isset($pdf_econsent_lastname_field)) $pdf_econsent_lastname_field = '';
		if (!isset($pdf_econsent_dob_field)) $pdf_econsent_dob_field = '';
		if (!isset($pdf_econsent_firstname_event_id) || $pdf_econsent_firstname_field == '') $pdf_econsent_firstname_event_id = '';
		if (!isset($pdf_econsent_lastname_event_id) || $pdf_econsent_lastname_field == '') $pdf_econsent_lastname_event_id = '';
		if (!isset($pdf_econsent_dob_event_id) || $pdf_econsent_dob_field == '') $pdf_econsent_dob_event_id = '';
		if (!isset($pdf_econsent_version)) $pdf_econsent_version = '';
		if (!isset($pdf_econsent_type)) $pdf_econsent_type = '';
		if (!isset($email_participant_field)) $email_participant_field = '';
        $pdf_econsent_allow_edit = (isset($pdf_econsent_allow_edit) && $pdf_econsent_allow_edit == 'on') ? '1' : '0';

        $pdf_econsent_signature_array = array();
        $pdf_econsent_signature_field = 'pdf_econsent_signature_field';
        for ($i=1; $i<=5; $i++) {
            $this_pdf_econsent_signature_field = $pdf_econsent_signature_field.$i;
            $this_val = $$this_pdf_econsent_signature_field;
            if ($this_val != '') {
                $pdf_econsent_signature_array[] = $this_val;
            }
        }
        $pdf_econsent_signature_array = array_values(array_unique($pdf_econsent_signature_array));
        for ($i=1; $i<=5; $i++) {
            $this_pdf_econsent_signature_field = $pdf_econsent_signature_field.$i;
            $this_val = isset($pdf_econsent_signature_array[$i-1]) ? $pdf_econsent_signature_array[$i-1] : "";
            $$this_pdf_econsent_signature_field = $this_val;
        }

		// Save survey info
		$sql = "update redcap_surveys set title = '" . db_escape($title) . "', acknowledgement = '" . db_escape($acknowledgement) . "',
				instructions = '" . db_escape($instructions) . "', question_by_section = '" . db_escape($question_by_section) . "',
				question_auto_numbering = '" . db_escape($question_auto_numbering) . "', save_and_return = '" . db_escape($save_and_return) . "',
				view_results = '" . db_escape($view_results) . "', min_responses_view_results = '" . db_escape($min_responses_view_results) . "',
				check_diversity_view_results = '" . db_escape($check_diversity_view_results) . "',
				end_survey_redirect_url = " . checkNull($end_survey_redirect_url) . ", survey_expiration = " . checkNull($survey_expiration_save) . ",
				survey_enabled = " . db_escape($survey_enabled) . ", promis_skip_question = '".db_escape($promis_skip_question)."',
				survey_auth_enabled_single = '".db_escape($survey_auth_enabled_single)."',
				edit_completed_response = '".db_escape($edit_completed_response)."', display_page_number = '".db_escape($display_page_number)."',
				hide_back_button = '".db_escape($hide_back_button)."', show_required_field_text = '".db_escape($show_required_field_text)."',
				confirmation_email_subject = ".checkNull($confirmation_email_subject).", confirmation_email_content = ".checkNull($confirmation_email_content).",
				confirmation_email_from = ".checkNull($confirmation_email_from).", 
				confirmation_email_from_display = ".checkNull($confirmation_email_from_display).", 
				text_to_speech = '" . db_escape($text_to_speech) . "',
				confirmation_email_attach_pdf = '".db_escape($confirmation_email_attach_pdf)."', text_to_speech_language = '" . db_escape($text_to_speech_language) . "',
				end_survey_redirect_next_survey = '" . db_escape($end_survey_redirect_next_survey) . "', enhanced_choices = '".db_escape($enhanced_choices)."',
				theme = ".checkNull($theme).", text_size = ".checkNull($text_size).", font_family = ".checkNull($font_family).",
				theme_bg_page = ".checkNull($theme_bg_page).", theme_text_buttons = ".checkNull($theme_text_buttons).", theme_text_title = ".checkNull($theme_text_title).",
				theme_bg_title = ".checkNull($theme_bg_title).", theme_text_question = ".checkNull($theme_text_question).", theme_bg_question = ".checkNull($theme_bg_question).",
				theme_text_sectionheader = ".checkNull($theme_text_sectionheader).", theme_bg_sectionheader = ".checkNull($theme_bg_sectionheader).",
				repeat_survey_enabled = '" . db_escape($repeat_survey_enabled) . "', repeat_survey_btn_text = ".checkNull($repeat_survey_btn_text).",
				repeat_survey_btn_location = '" . db_escape($repeat_survey_btn_location) . "',
				response_limit = ".checkNull($response_limit).", survey_time_limit_days = ".checkNull($survey_time_limit_days).",
				survey_time_limit_hours = ".checkNull($survey_time_limit_hours).", survey_time_limit_minutes = ".checkNull($survey_time_limit_minutes).",
				response_limit_include_partials = '" . db_escape($response_limit_include_partials) . "',
				response_limit_custom_text = ".checkNull($response_limit_custom_text).", end_of_survey_pdf_download = '" . db_escape($end_of_survey_pdf_download) . "',
				save_and_return_code_bypass = '" . db_escape($save_and_return_code_bypass) . "', pdf_auto_archive = '" . db_escape($pdf_auto_archive) . "',
				pdf_econsent_firstname_field = ".checkNull($pdf_econsent_firstname_field).", pdf_econsent_lastname_field = ".checkNull($pdf_econsent_lastname_field).",
				pdf_econsent_dob_field = ".checkNull($pdf_econsent_dob_field).", pdf_econsent_firstname_event_id = ".checkNull($pdf_econsent_firstname_event_id).",
				pdf_econsent_lastname_event_id = ".checkNull($pdf_econsent_lastname_event_id).", pdf_econsent_dob_event_id = ".checkNull($pdf_econsent_dob_event_id).",
				pdf_econsent_version = ".checkNull($pdf_econsent_version).", pdf_econsent_type = ".checkNull($pdf_econsent_type).", 
				email_participant_field = ".checkNull($email_participant_field)."
				, pdf_econsent_allow_edit = ".checkNull($pdf_econsent_allow_edit)."
				, pdf_econsent_signature_field1 = ".checkNull($pdf_econsent_signature_field1)."
				, pdf_econsent_signature_field2 = ".checkNull($pdf_econsent_signature_field2)."
				, pdf_econsent_signature_field3 = ".checkNull($pdf_econsent_signature_field3)."
				, pdf_econsent_signature_field4 = ".checkNull($pdf_econsent_signature_field4)."
				, pdf_econsent_signature_field5 = ".checkNull($pdf_econsent_signature_field5)."
				where survey_id = $survey_id";
		if (db_query($sql))
		{
			$msg .= RCView::div(array('id'=>'saveSurveyMsg','class'=>'darkgreen','style'=>'display:none;vertical-align:middle;text-align:center;margin:0 0 25px;'),
						RCView::img(array('src'=>'tick.png')) . $lang['control_center_48']
					);
			// If Time Limit changed, then we need to reset all the cached values in the participants table
			// Do NOT reset any that have been overridden.
			if ($resetCachedLinkExpiration) {
				$sql = "update redcap_surveys_participants set link_expiration = null
						where survey_id = $survey_id and link_expiration is not null and link_expiration_override = 0";
				db_query($sql);
			}
		}
		else
		{
			$msg = 	RCView::div(array('id'=>'saveSurveyMsg','class'=>'red','style'=>'display:none;vertical-align:middle;text-align:center;margin:0 0 25px;'),
						RCView::img(array('src'=>'exclamation.png')) . $lang['survey_159']
					);
		}

		// Upload logo
		$hide_title = ($hide_title == "on") ? "1" : "0";
		if (!empty($_FILES['logo']['name'])) {
			// Check if it is an image file
			$file_ext = getFileExt($_FILES['logo']['name']);
			if (in_array(strtolower($file_ext), array("jpeg", "jpg", "gif", "bmp", "png"))) {
				// Upload the image
				$logo = Files::uploadFile($_FILES['logo']);
				// Add doc_id to redcap_surveys table
				if ($logo != 0) {
					db_query("update redcap_surveys set logo = $logo, hide_title = $hide_title where survey_id = $survey_id");
				}
			}
		} elseif (empty($old_logo)) {
			// Mark existing field for deletion in edocs table, then in redcap_surveys table
			$logo = db_result(db_query("select logo from redcap_surveys where survey_id = $survey_id"), 0);
			if (!empty($logo)) {
				db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where doc_id = $logo");
				db_query("update redcap_surveys set logo = null, hide_title = 0 where survey_id = $survey_id");
			}
			// Set back to default values
			$logo = "";
			$hide_title = "0";
		} elseif (!empty($old_logo)) {
			db_query("update redcap_surveys set hide_title = $hide_title where survey_id = $survey_id");
		}

		// Upload survey confirmation email attachment
		if (!empty($_FILES['confirmation_email_attachment']['name'])) {
			// Upload image
			$confirmation_email_attachment = Files::uploadFile($_FILES['confirmation_email_attachment']);
			// Add doc_id to redcap_surveys table
			if ($confirmation_email_attachment != 0) {
				db_query("update redcap_surveys set confirmation_email_attachment = $confirmation_email_attachment where survey_id = $survey_id");
			}
		} elseif (empty($old_confirmation_email_attachment)) {
			// Mark existing field for deletion in edocs table, then in redcap_surveys table
			$confirmation_email_attachment = db_result(db_query("select confirmation_email_attachment from redcap_surveys where survey_id = $survey_id"), 0);
			if (!empty($confirmation_email_attachment)) {
				db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where doc_id = $confirmation_email_attachment");
				db_query("update redcap_surveys set confirmation_email_attachment = null where survey_id = $survey_id");
			}
			// Set back to default values
			$confirmation_email_attachment = "";
		}

		// Log the event
		Logging::logEvent($sql, "redcap_surveys", "MANAGE", $survey_id, "survey_id = $survey_id", "Modify survey info");

		// Once the survey is created, redirect to Online Designer and display "saved changes" message
		redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&survey_save=edit");
	}













	// Header
	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

	// TABS
	include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

	?>
	<script type="text/javascript">
	// Display "saved changes" message, if just saved survey settings
	$(function(){
		if ($('#saveSurveyMsg').length) {
			setTimeout(function(){
				$('#saveSurveyMsg').slideToggle('normal');
			},200);
			setTimeout(function(){
				$('#saveSurveyMsg').slideToggle(1200);
			},2500);
		}
	});
	</script>

	<p style="margin-bottom:20px;"><?php echo $lang['survey_160'] ?></p>

	<?php
	// Display error message, if exists
	if (!empty($msg)) print $msg;
	?>

	<div class="blue" style="max-width:900px;">
		<div style="float:left;">
            <i class="fas fa-pencil-alt"></i>
			<?php
			print $lang['setup_05'];
			print " {$lang['setup_89']} \"<b>".RCView::escape($Proj->forms[$form]['menu'])."</b>\"";
			?>
		</div>
        <button class="btn btn-defaultrc btn-xs float-right" onclick="history.go(-1);return false;"><?php echo js_escape2($lang['global_53']) ?></button>
        <button class="btn btn-primaryrc btn-xs float-right mr-2" onclick="$('#surveySettingsSubmit').trigger('click');"><?php echo js_escape2($lang['report_builder_28']) ?></button>
		<div class="clear"></div>
	</div>
	<div style="background-color:#FAFAFA;border:1px solid #DDDDDD;padding:0 6px;max-width:900px;">
	<?php

	// Render the create/edit survey table
	$surveyEnabled = true;
	include APP_PATH_DOCROOT . "Surveys/survey_info_table.php";

	print "</div>";

	// Footer
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}