<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set title for left-hand menu panel for Reports
$reportsListTitle = $lang['app_06'];
if ($user_rights['reports']) {
	$reportsListTitle = "<div class='opacity65' id='menuLnkEditReports' style='float:right;margin-right:5px;'>"
						. RCView::i(array('class'=>'fas fa-pencil-alt', 'style'=>'color:#000066;font-size:10px;top:2px;margin-right:3px;'), '')
						. RCView::a(array('href'=>APP_PATH_WEBROOT."DataExport/index.php?pid=$project_id",'style'=>'font-family:"Open Sans",arial;font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), $lang['global_27'])
					. "</div>";
}

// If user is collapsing a Report Folder, then set in database
if (isset($_POST['collapse']) && isset($_POST['folder_id']) && is_numeric($_POST['folder_id'])) {
	DataExport::collapseReportFolder($_POST['folder_id'], $_POST['collapse']);
}

// Output html for left-hand menu panel for Reports
list ($reportsListTitle, $reportsListCollapsed) = DataExport::outputReportPanelTitle();
// Reports built in Reports & Exports module
$reportsList = DataExport::outputReportPanel();
if ($reportsList != "") {
	print renderPanel($reportsListTitle, $reportsList, 'report_panel', $reportsListCollapsed);
}