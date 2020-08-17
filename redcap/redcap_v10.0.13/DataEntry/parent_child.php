<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Redirect to the project's earliest designate form on the earliest event
foreach ($Proj->eventsForms as $event_id=>$forms) {
	if (!empty($forms)) {
		foreach ($forms as $form) {
			redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&event_id=$event_id&page=$form&id=" . $_GET['record']);
		}
	}
}
