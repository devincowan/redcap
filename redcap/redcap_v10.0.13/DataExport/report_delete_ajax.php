<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate id
if (!isset($_POST['report_id'])) exit('0');
$report_id = $_POST['report_id'];
$report = DataExport::getReports($report_id);
if (empty($report)) exit('0');

// If user does not have EDIT ACCESS to this report, then go back to My Reports page
$reports_edit_access = DataExport::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id'], $_POST['report_id']);
if (empty($reports_edit_access)) exit('0');

// Copy the report and return the new report_id
$success = DataExport::deleteReport($report_id);
print ($success === false) ? '0' : '1';