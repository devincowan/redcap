<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Check collapse value
if (!isset($_POST['collapse']) || !isset($_POST['menu_id']) || !in_array($_POST['collapse'], array('0', '1'))) exit('0');

// Add value to cookie (project_id is key and menu ID is subkey)
if (!$_POST['collapse']) {
	// Remove it
	UIState::removeUIStateValue($project_id, 'sidebar', $_POST['menu_id']);
} else {
	// Add it
	UIState::saveUIStateValue($project_id, 'sidebar', $_POST['menu_id'], $_POST['collapse']);
}