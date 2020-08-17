<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Determine the instrument
$form = (isset($_GET['page']) && isset($Proj->forms[$_GET['page']])) ? $_GET['page'] : null;

// If survey has already been created (it shouldn't have been), then redirect to edit_info page to edit survey
if (isset($Proj->forms[$form]['survey_id'])) {
	redirect(str_replace(PAGE, 'Surveys/edit_info.php', $_SERVER['REQUEST_URI']));
}


/**
 * PROCESS SUBMITTED CHANGES
 */
if ($_SERVER['REQUEST_METHOD'] == "POST")
{
	// Assign Post array as globals
	foreach ($_POST as $key => $value) $$key = $value;
	// Set values
	if (!isset($pdf_auto_archive)) $pdf_auto_archive = 0;
	$check_diversity_view_results = (isset($check_diversity_view_results) && $check_diversity_view_results == 'on') ? 1 : 0;
	if (!isset($view_results)) $view_results = 0;
	if (!isset($min_responses_view_results)) $min_responses_view_results = 10;
	if ($survey_termination_options == 'url') {
		$acknowledgement = '';
	} else {
		$end_survey_redirect_url = '';
	}
	// Survey Auto-Continue - Set checkbox to 0 if not in post
	$end_survey_redirect_next_survey = (isset($_POST['end_survey_redirect_next_survey']) && $_POST['end_survey_redirect_next_survey'] == 'on') ? '1' : '0';
	// Reformat $survey_expiration from MDYHS to YMDHS for saving purposes
	if ($survey_expiration != '') {
		list ($this_date, $this_time) = explode(" ", $survey_expiration);
		$survey_expiration = trim(DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time);
	}
	if (!isset($survey_auth_enabled_single)) $survey_auth_enabled_single = '0';
	$edit_completed_response = (isset($edit_completed_response) && $edit_completed_response == 'on') ? '1' : '0';
	$repeat_survey_enabled = (isset($repeat_survey_enabled) && $repeat_survey_enabled == 'on') ? '1' : '0';
	if (!$repeat_survey_enabled) $repeat_survey_btn_text = '';
	
	$display_page_number = (isset($display_page_number) && $display_page_number == 'on') ? '1' : '0';
	$hide_back_button = (isset($hide_back_button) && $hide_back_button == 'on') ? '1' : '0';
	if ($confirmation_email_content == '') $confirmation_email_from = '';
	$confirmation_email_attach_pdf = ($confirmation_email_attach_pdf == 'on') ? '1' : '0';
	$text_to_speech = (isset($text_to_speech) && is_numeric($text_to_speech)) ? $text_to_speech : '0';
	if (!isset($text_to_speech_language) || $text_to_speech_language == '') $text_to_speech_language = 'en';
	if (!isset($text_size) || !is_numeric($text_size)) $text_size = '';
	if (!isset($font_family) || !is_numeric($font_family)) $font_family = '';
	$enhanced_choices = (isset($enhanced_choices) && is_numeric($enhanced_choices)) ? $enhanced_choices : '0';
	$repeat_survey_btn_location = ($repeat_survey_btn_location == 'BEFORE_SUBMIT') ? 'BEFORE_SUBMIT' : 'AFTER_SUBMIT';
	$response_limit = (isset($response_limit) && is_numeric($response_limit) && !empty($response_limit)) ? $response_limit : '';
	$response_limit_include_partials = (isset($response_limit_include_partials) && $response_limit_include_partials == '0') ? '0' : '1';
	$survey_time_limit_days = (isset($survey_time_limit_days) && is_numeric($survey_time_limit_days) && !empty($survey_time_limit_days)) ? $survey_time_limit_days : '';
	$survey_time_limit_hours = (isset($survey_time_limit_hours) && is_numeric($survey_time_limit_hours) && !empty($survey_time_limit_hours)) ? $survey_time_limit_hours : '';
	$survey_time_limit_minutes = (isset($survey_time_limit_minutes) && is_numeric($survey_time_limit_minutes) && !empty($survey_time_limit_minutes)) ? $survey_time_limit_minutes : '';
	$end_of_survey_pdf_download = (isset($end_of_survey_pdf_download) && $end_of_survey_pdf_download == '1') ? '1' : '0';
	$save_and_return_code_bypass = (isset($save_and_return_code_bypass) && $save_and_return_code_bypass == 'on') ? '1' : '0';		
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
	$sql = "replace into redcap_surveys (project_id, form_name, acknowledgement, instructions, question_by_section,
			question_auto_numbering, save_and_return, survey_enabled, title,
			view_results, min_responses_view_results, check_diversity_view_results, end_survey_redirect_url, survey_expiration,
			survey_auth_enabled_single, edit_completed_response, display_page_number, hide_back_button, show_required_field_text,
			confirmation_email_subject, confirmation_email_content, confirmation_email_from, confirmation_email_from_display, text_to_speech, text_to_speech_language,
			end_survey_redirect_next_survey, enhanced_choices, theme, text_size, font_family,
			theme_bg_page, theme_text_buttons, theme_text_title, theme_bg_title,
			theme_text_question, theme_bg_question, theme_text_sectionheader, theme_bg_sectionheader, 
			repeat_survey_enabled, repeat_survey_btn_text, repeat_survey_btn_location, response_limit, survey_time_limit_days,
			survey_time_limit_hours, survey_time_limit_minutes, response_limit_include_partials, response_limit_custom_text, 
			end_of_survey_pdf_download, confirmation_email_attach_pdf, save_and_return_code_bypass, pdf_auto_archive,
			pdf_econsent_firstname_field, pdf_econsent_lastname_field, pdf_econsent_dob_field,
			pdf_econsent_firstname_event_id, pdf_econsent_lastname_event_id, pdf_econsent_dob_event_id,
			pdf_econsent_version, pdf_econsent_type, email_participant_field, pdf_econsent_allow_edit,
			pdf_econsent_signature_field1, pdf_econsent_signature_field2, pdf_econsent_signature_field3, pdf_econsent_signature_field4, pdf_econsent_signature_field5)
			values ($project_id, '" . db_escape($form) . "',
			'" . db_escape($acknowledgement) . "', '" . db_escape($instructions) . "',
			'" . db_escape($question_by_section) . "', '" . db_escape($question_auto_numbering) . "',
			'" . db_escape($save_and_return) . "', 1, '" . db_escape($title) . "',
			'" . db_escape($view_results) . "', '" . db_escape($min_responses_view_results) . "', '" . db_escape($check_diversity_view_results) . "',
			" . checkNull($end_survey_redirect_url) . ", " . checkNull($survey_expiration) . ",
			'" . db_escape($survey_auth_enabled_single) . "', '" . db_escape($edit_completed_response) . "', '" . db_escape($display_page_number) . "',
			'" . db_escape($hide_back_button) . "', '" . db_escape($show_required_field_text) . "',
			" . checkNull($confirmation_email_subject) . ", " . checkNull($confirmation_email_content) . ",
			" . checkNull($confirmation_email_from) . ", " . checkNull($confirmation_email_from_display) . ", '" . db_escape($text_to_speech) . "', '" . db_escape($text_to_speech_language) . "',
			'" . db_escape($end_survey_redirect_next_survey) . "', '" . db_escape($enhanced_choices) . "', 
			" . checkNull($theme) . ", " . checkNull($text_size) . ", " . checkNull($font_family) . ",
			" . checkNull($theme_bg_page) . ", " . checkNull($theme_text_buttons) . ", " . checkNull($theme_text_title) . ", " . checkNull($theme_bg_title) . ",
			" . checkNull($theme_text_question) . ", " . checkNull($theme_bg_question) . ", " . checkNull($theme_text_sectionheader) . ", 
			" . checkNull($theme_bg_sectionheader) . ", '" . db_escape($repeat_survey_enabled) . "', " . checkNull($repeat_survey_btn_text) . ",
			'" . db_escape($repeat_survey_btn_location) . "', " . checkNull($response_limit) . ", " . checkNull($survey_time_limit_days) . ", 
			" . checkNull($survey_time_limit_hours) . ", " . checkNull($survey_time_limit_minutes) . ", '" . db_escape($response_limit_include_partials) . "',
			" . checkNull($response_limit_custom_text) . ", '" . db_escape($end_of_survey_pdf_download) . "', 
			'" . db_escape($confirmation_email_attach_pdf) . "', '" . db_escape($save_and_return_code_bypass) . "', '" . db_escape($pdf_auto_archive) . "',
			" . checkNull($pdf_econsent_firstname_field) . ", " . checkNull($pdf_econsent_lastname_field) . ",	" . checkNull($pdf_econsent_dob_field) . ",
			" . checkNull($pdf_econsent_firstname_event_id) . ", " . checkNull($pdf_econsent_lastname_event_id) . ",	" . checkNull($pdf_econsent_dob_event_id) . ",
			" . checkNull($pdf_econsent_version) . ", " . checkNull($pdf_econsent_type) . ", " . checkNull($email_participant_field) . " 
			, " . checkNull($pdf_econsent_allow_edit) . "
			, " . checkNull($pdf_econsent_signature_field1) . "
			, " . checkNull($pdf_econsent_signature_field2) . "
			, " . checkNull($pdf_econsent_signature_field3) . "
			, " . checkNull($pdf_econsent_signature_field4) . "
			, " . checkNull($pdf_econsent_signature_field5) . "
        )";
    if (!db_query($sql)) {
        exit("An error occurred. Please try again.");
    }
    $survey_id = db_insert_id();

	// Upload logo
	$hide_title = (isset($hide_title) && $hide_title == "on" ? "1" : "0");
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
	Logging::logEvent($sql, "redcap_surveys", "MANAGE", $survey_id, "survey_id = $survey_id", "Set up survey");

	// Once the survey is created, redirect to Online Designer and display "saved changes" message
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&survey_save=create");
}








// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

// Instructions
?>
<p style="margin-bottom:20px;">
	<?php
	print $lang['survey_271'] . " " . $lang['survey_272'];
	?>
</p>
<?php


// If form name does not exist (except only in Draft Mode), then give error message
if (($form == null || !isset($Proj->forms[$form]['survey_id'])) && $status > 0 && $draft_mode >= 1)
{
	print 	RCView::div(array('class'=>'yellow','style'=>''),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['survey_496']
			);

	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

// Force user to click button to begin survey-enabling process
if (!isset($_GET['view']))
{
	?>
	<div class="yellow" style="text-align:center;font-weight:bold;padding:10px;">
		<?php echo $lang['survey_151'] ?>
		<br><br>
		<button class="jqbutton" onclick="window.location.href='<?php echo $_SERVER['REQUEST_URI'] ?>&view=showform';"
			><?php echo $lang['survey_152'] ?> "<?php echo $Proj->forms[$form]['menu'] ?>" <?php echo $lang['survey_153'] ?></button>
	</div>
	<?php
}


// Display form to enable survey
elseif (isset($_GET['view']) && $_GET['view'] == "showform")
{
	?>
	<div class="darkgreen" style="max-width:900px;">
		<div style="float:left;">
            <i class="fas fa-plus"></i>
			<?php
			print $lang['setup_24'];
			print " {$lang['setup_89']} \"<b>".RCView::escape($Proj->forms[$form]['menu'])."</b>\"";
			?>
		</div>
        <button class="btn btn-defaultrc btn-xs float-right" onclick="history.go(-1);return false;"><?php echo js_escape2($lang['global_53']) ?></button>
        <button class="btn btn-rcgreen btn-xs float-right mr-2" onclick="$('#surveySettingsSubmit').trigger('click');"><?php echo js_escape2($lang['report_builder_28']) ?></button>
		<div class="clear"></div>
	</div>
	<div style="background-color:#FAFAFA;border:1px solid #DDDDDD;padding:0 6px;max-width:900px;">
		<?php
		// Set defaults to pre-fill table
		$title = empty($Proj->forms[$form]['menu']) ? "My Survey" : $Proj->forms[$form]['menu'];
		$question_auto_numbering = 1;
		$question_by_section = $pdf_auto_archive = 0;
		$save_and_return = 0;
		$logo = $confirmation_email_subject = $confirmation_email_content = $confirmation_email_attachment = "";
		$hide_title = 0;
		$instructions = '<p><strong>'.$lang['survey_154'].'</strong></p><p>'.$lang['global_83'].'</p>';
		$acknowledgement = '<p><strong>'.$lang['survey_155'].'</strong></p><p>'.$lang['survey_156'].'</p>';
		$view_results = 0;
		$min_responses_view_results = 10;
		$check_diversity_view_results = 1;
		$end_survey_redirect_url = '';
		$survey_expiration = '';
		$survey_auth_enabled_single = '0';
		$edit_completed_response = $display_page_number = $hide_back_button = $confirmation_email_attach_pdf = $save_and_return_code_bypass = 0;
		$show_required_field_text = 1;
		$text_to_speech = 0;
		$text_to_speech_language = 'en';
		$theme = '';
		$text_size = '1';
		$font_family = '16';
		$theme_text_buttons = $theme_bg_page = $theme_text_title = $theme_bg_title = $repeat_survey_btn_text = '';
		$theme_text_sectionheader = $theme_bg_sectionheader = $theme_text_question = $theme_bg_question = '';
		$enhanced_choices = $repeat_survey_enabled = 0;
		$repeat_survey_btn_location = 'BEFORE_SUBMIT';
		$response_limit = '';
		$response_limit_include_partials = '1';
		$response_limit_custom_text = $survey_time_limit_days = $survey_time_limit_hours = $survey_time_limit_minutes = '';
		$pdf_econsent_firstname_field = $pdf_econsent_lastname_field = $pdf_econsent_dob_field = '';
		$pdf_econsent_type = $pdf_econsent_version = '';
		$pdf_econsent_firstname_event_id = $pdf_econsent_lastname_event_id = $pdf_econsent_dob_event_id = $Proj->firstEventId;
        $pdf_econsent_allow_edit = '0';
		$confirmation_email_from_display = '';
		// Render the create/edit survey table
		$surveyEnabled = false;
		include APP_PATH_DOCROOT . "Surveys/survey_info_table.php";
		?>
	</div>
	<?php
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
