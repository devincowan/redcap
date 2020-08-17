<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// This file not used anymore as of 6.0.0. Redirect to new stats page.
redirect(APP_PATH_WEBROOT . "DataExport/index.php?pid=$project_id&report_id=ALL&stats_charts=1&page={$_GET['page']}");