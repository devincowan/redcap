<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set the form menu description for the form
if (isset($_POST['action']) && $_POST['action'] == "set_menu_name") {
	list($form_name, $menu_description, $survey_title) = REDCap::setFormName($project_id, $_POST['page'], $_POST['menu_description']);

	// Response (form_name \ label \ survey title)
	print $form_name . "\n" . $menu_description . "\n" . $survey_title;
}
