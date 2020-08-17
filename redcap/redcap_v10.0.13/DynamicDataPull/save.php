<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Save adjudicated data from RTWS and output success message
print $DDP->saveAdjudicatedData($_GET['record'], $_GET['event_id'], $_POST);
