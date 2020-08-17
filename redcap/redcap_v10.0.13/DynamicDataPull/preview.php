<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Obtain data for the preview fields and display it
print $DDP->displayPreviewData($_POST['source_id_value']);
