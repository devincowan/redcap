<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Log all the source data points (md_id's) viewed by the user
print $DDP->logDataView($_POST['source_id_value'], explode(",", $_POST['md_ids']));