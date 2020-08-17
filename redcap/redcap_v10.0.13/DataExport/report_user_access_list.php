<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Display list of usernames who would have access
$content = DataExport::displayReportAccessUsernames($_POST, $_GET['access_type']);
// Output JSON
print json_encode_rc(array('content'=>$content, 'title'=>$lang['report_builder_108']));