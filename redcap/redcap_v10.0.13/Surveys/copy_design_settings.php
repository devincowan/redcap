<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Check params
if (!isset($_POST['copy_design_survey_ids']) || empty($_POST['copy_design_survey_ids'])) exit;
$survey_ids = array();
foreach (explode(",", $_POST['copy_design_survey_ids']) as $this_survey_id) {
	if (isset($Proj->surveys[$this_survey_id])) $survey_ids[] = $this_survey_id;
}
if (empty($survey_ids)) exit;

// Build SQL to copy survey values
$sql_arr = $sql_all = array();
if ($_POST['copy_design_text_size']) {
	$sql_arr[] = "text_size = " . checkNull($_POST['text_size']);
}
if ($_POST['copy_design_enhanced_choices']) {
	$sql_arr[] = "enhanced_choices = " . checkNull($_POST['enhanced_choices']);
}
if ($_POST['copy_design_font_family']) {
	$sql_arr[] = "font_family = " . checkNull($_POST['font_family']);
}
if ($_POST['copy_design_theme']) {
	// Set theme name and also any customization settings
	$regex_color = "/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/";
	$theme_bg_page = (isset($_POST['theme_bg_page']) && preg_match($regex_color, $_POST['theme_bg_page'])) ? substr($_POST['theme_bg_page'], 1) : '';
	$theme_text_buttons = (isset($_POST['theme_text_buttons']) && preg_match($regex_color, $_POST['theme_text_buttons'])) ? substr($_POST['theme_text_buttons'], 1) : '';
	$theme_text_title = (isset($_POST['theme_text_title']) && preg_match($regex_color, $_POST['theme_text_title'])) ? substr($_POST['theme_text_title'], 1) : '';
	$theme_bg_title = (isset($_POST['theme_bg_title']) && preg_match($regex_color, $_POST['theme_bg_title'])) ? substr($_POST['theme_bg_title'], 1) : '';
	$theme_text_question = (isset($_POST['theme_text_question']) && preg_match($regex_color, $_POST['theme_text_question'])) ? substr($_POST['theme_text_question'], 1) : '';
	$theme_bg_question = (isset($_POST['theme_bg_question']) && preg_match($regex_color, $_POST['theme_bg_question'])) ? substr($_POST['theme_bg_question'], 1) : '';
	$theme_text_sectionheader = (isset($_POST['theme_text_sectionheader']) && preg_match($regex_color, $_POST['theme_text_sectionheader'])) ? substr($_POST['theme_text_sectionheader'], 1) : '';
	$theme_bg_sectionheader = (isset($_POST['theme_bg_sectionheader']) && preg_match($regex_color, $_POST['theme_bg_sectionheader'])) ? substr($_POST['theme_bg_sectionheader'], 1) : '';
	$sql_arr[] = "theme = " . checkNull($_POST['theme']);
	$sql_arr[] = "theme_bg_page = " . checkNull($theme_bg_page);
	$sql_arr[] = "theme_text_buttons = " . checkNull($theme_text_buttons);
	$sql_arr[] = "theme_text_title = " . checkNull($theme_text_title);
	$sql_arr[] = "theme_bg_title = " . checkNull($theme_bg_title);
	$sql_arr[] = "theme_text_question = " . checkNull($theme_text_question);
	$sql_arr[] = "theme_bg_question = " . checkNull($theme_bg_question);
	$sql_arr[] = "theme_text_sectionheader = " . checkNull($theme_text_sectionheader);
	$sql_arr[] = "theme_bg_sectionheader = " . checkNull($theme_bg_sectionheader);
}
if ($_POST['copy_design_logo'] && is_numeric($_POST['old_logo'])) {
	$sql_arr[] = "hide_title = " . ((isset($_POST['hide_title']) && $_POST['hide_title'] == 'on') ? '1' : '0');
}
if (empty($sql_arr)) exit;
$sql_all[] = $sql = "update redcap_surveys set " . implode(", ", $sql_arr) . " where survey_id in (".prep_implode($survey_ids).")";
if (!db_query($sql)) exit;

// Copy logo (if applicable)
if ($_POST['copy_design_logo'] && is_numeric($_POST['old_logo'])) {
	// Loop through each survey and create new logo copy for each
	foreach ($survey_ids as $this_survey_id) {
		// If survey already has a logo, set it to be deleted in edoc_metadata table
		$old_edoc_id = $Proj->surveys[$this_survey_id]['logo'];
		if (is_numeric($old_edoc_id)) {
			$sql_all[] = $sql = "update redcap_edocs_metadata set delete_date = '".NOW."' where doc_id = $old_edoc_id";
			db_query($sql);
		}
		// Copy file on server
		$new_edoc_id = copyFile($_POST['old_logo'], PROJECT_ID);
		if (!is_numeric($new_edoc_id)) continue;
		// Now update this survey's logo value
		$sql_all[] = $sql = "update redcap_surveys set logo = $new_edoc_id where survey_id = $this_survey_id";
		db_query($sql);
	}
}

// Logging
Logging::logEvent(implode(";\n", $sql_all),"redcap_surveys","MANAGE","","survey_id in (".prep_implode($survey_ids).")","Copy survey design settings");

// Output success msg
print 	RCView::div(array('style'=>'color:green;font-weight:bold;font-size:13px;'),
			RCView::img(array('src'=>'tick.png')) .
			$lang['survey_1051']
		) .
		RCView::div(array('class'=>'yellow', 'style'=>'margin-top:20px;font-size:12px;'),
			$lang['survey_1052']
		);