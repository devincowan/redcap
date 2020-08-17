<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Ajax only
if (!$isAjax) exit("0");

// Create snapshot
MetaData::createDataDictionarySnapshot();

// Return the current time (which is the snapshot timestamp)
print RCView::a(array('href'=>APP_PATH_WEBROOT."ProjectSetup/project_revision_history.php?pid=".PROJECT_ID),
		DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24')
	  );
