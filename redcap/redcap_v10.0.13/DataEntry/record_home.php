<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
DataEntry::renderRecordHomePage();
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';