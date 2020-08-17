<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

print logicSearchResults($_POST['location'], $_POST['word'], $_POST['draft_mode']);
