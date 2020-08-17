<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Exclude a source value for a given record during the Adjudication process
print $DDP->excludeValue($_POST['md_id'], $_POST['exclude']);