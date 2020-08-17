<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Check collapse value
if (!isset($_POST['collapse']) || !isset($_POST['targetid']) || !in_array($_POST['collapse'], array('0', '1'))) exit('0');

// Add value to UI State (project_id is key and menu ID is subkey)
if (strpos($_POST['targetid'], ',') !== false && $_POST['collapse'] == 0) {
	// Uncollapse all tables/columns on page
	foreach (explode(",", $_POST['targetid']) as $this_targetid) {
		if ($this_targetid == '') return;
		// If a repeating event column, prepend with string
		if (is_numeric($this_targetid)) $this_targetid = "repeat_event-$this_targetid";
		// Prevent tampering and injectino
		if (!preg_match("/^([a-zA-Z0-9_-]+)$/", $this_targetid)) return;
		// Remove it from UI state
		UIState::removeUIStateValue($project_id, $_POST['object'], $this_targetid);
	}
} elseif ($_POST['collapse'] == 1) {
	// Add it as collapsed
	UIState::saveUIStateValue($project_id, $_POST['object'], $_POST['targetid'], 1);
} else {
	// Remove it from UI state
	UIState::removeUIStateValue($project_id, $_POST['object'], $_POST['targetid']);
}