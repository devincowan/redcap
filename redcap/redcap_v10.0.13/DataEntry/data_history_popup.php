<?php


include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Make sure we have all the correct elements needed
if (!($history_widget_enabled && is_numeric($_POST['event_id']) && isset($_POST['record']) && isset($Proj->metadata[$_POST['field_name']])))
{
	exit('ERROR!');
}

// Render data history log
print Form::renderDataHistoryLog($_POST['record'], $_POST['event_id'], $_POST['field_name'], $_POST['instance']);

