<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Check params
$theme_name = trim(strip_tags(label_decode($_POST['theme_name'])));
if ($theme_name == '') exit('0');
$regex_color = "/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/";
$theme_bg_page = (isset($_POST['theme_bg_page']) && preg_match($regex_color, $_POST['theme_bg_page'])) ? substr($_POST['theme_bg_page'], 1) : '';
$theme_text_buttons = (isset($_POST['theme_text_buttons']) && preg_match($regex_color, $_POST['theme_text_buttons'])) ? substr($_POST['theme_text_buttons'], 1) : '';
$theme_text_title = (isset($_POST['theme_text_title']) && preg_match($regex_color, $_POST['theme_text_title'])) ? substr($_POST['theme_text_title'], 1) : '';
$theme_bg_title = (isset($_POST['theme_bg_title']) && preg_match($regex_color, $_POST['theme_bg_title'])) ? substr($_POST['theme_bg_title'], 1) : '';
$theme_text_question = (isset($_POST['theme_text_question']) && preg_match($regex_color, $_POST['theme_text_question'])) ? substr($_POST['theme_text_question'], 1) : '';
$theme_bg_question = (isset($_POST['theme_bg_question']) && preg_match($regex_color, $_POST['theme_bg_question'])) ? substr($_POST['theme_bg_question'], 1) : '';
$theme_text_sectionheader = (isset($_POST['theme_text_sectionheader']) && preg_match($regex_color, $_POST['theme_text_sectionheader'])) ? substr($_POST['theme_text_sectionheader'], 1) : '';
$theme_bg_sectionheader = (isset($_POST['theme_bg_sectionheader']) && preg_match($regex_color, $_POST['theme_bg_sectionheader'])) ? substr($_POST['theme_bg_sectionheader'], 1) : '';

// Save theme
$sql = "insert into redcap_surveys_themes (theme_name, ui_id,
		theme_bg_page, theme_text_buttons, theme_text_title, theme_bg_title,
		theme_text_question, theme_bg_question, theme_text_sectionheader, theme_bg_sectionheader)
		values ('".db_escape($theme_name)."', (select ui_id from redcap_user_information where username = '".db_escape(USERID)."' limit 1),
		" . checkNull($theme_bg_page) . ", " . checkNull($theme_text_buttons) . ",
		" . checkNull($theme_text_title) . ", " . checkNull($theme_bg_title) . ", " . checkNull($theme_text_question) . ",
		" . checkNull($theme_bg_question) . ", " . checkNull($theme_text_sectionheader) . ", " . checkNull($theme_bg_sectionheader) .
		")";
if (!db_query($sql)) exit('0');

// Return HTML of survey theme drop-down list with the newly added theme
print Survey::renderSurveyThemeDropdown(db_insert_id(), true);