<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Place all HTML here
$html = "";

// Ensure that report_id is numeric
if (isset($_GET['report_id']) && !(is_numeric($_GET['report_id']) || $_GET['report_id'] == 'ALL' || $_GET['report_id'] == 'SELECTED')) unset($_GET['report_id']);

## CREATE NEW REPORT
if (isset($_GET['addedit']))
{
	// If user does not have EDIT ACCESS to this report, then go back to My Reports page
	if (isset($_GET['report_id']) && !SUPER_USER) {
		$reports_edit_access = DataExport::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id'], $_GET['report_id']);
		if (empty($reports_edit_access)) redirect(APP_PATH_WEBROOT . "DataExport/index.php?pid=" . PROJECT_ID);
	}
	// Hidden dialog for help with filters and AND/OR logic
	$html .= DataExport::renderFilterHelpDialog();
	// Hidden dialog for error popup when field name entered is not valid
	$html .= RCView::div(array('id'=>'VarEnteredNoExist_dialog', 'class'=>'simpleDialog'), $lang['report_builder_72']);
	// Hidden dialog for longitudinal event-level filter checkbox
	$html .= RCView::div(array('id'=>'eventLevelFilter_dialog', 'class'=>'simpleDialog', 'title'=>$lang['data_export_tool_191']),
				RCView::b($lang['data_export_tool_193']) . RCView::br() . $lang['data_export_tool_196'] . RCView::br() . RCView::br() .
				$lang['data_export_tool_194'] . RCView::br() . RCView::br() .
				RCView::span(array('style'=>'color:#C00000;'), $lang['data_export_tool_195'])
			 );
	// Hidden dialog for "Quick Add" field dialog
	$html .= RCView::div(array('id'=>'quickAddField_dialog', 'class'=>'simpleDialog'), '&nbsp;');
	// Add the actual "create report" table's HTML at the very bottom since we're doing a direct print. So output the buffer and disable buffering.
	ob_end_flush();
}
## OTHER EXPORT OPTIONS
elseif (isset($_GET['other_export_options']) && $user_rights['data_export_tool'] > 0)
{
	$html .= // Instructions
			RCView::p(array('style'=>'max-width:700px;margin:5px 0 10px;'),
				$lang['report_builder_116']
			) .
			// Get html for displaying additional export options
			DataExport::outputOtherExportOptions();
}
## VIEW LIST OF ALL REPORTS
elseif (!isset($_GET['report_id']))
{
	$html .= 	// Instructions
				RCView::p(array('style'=>'max-width:810px;margin:5px 0 15px;'),
					$lang['report_builder_117']
				) .
				// Report list table
				RCView::div(array('id'=>'report_list_parent_div'),
					DataExport::renderReportList()
				 );
	// Just in case, make sure that all report orders are correct
	DataExport::checkReportOrder();
}
## VIEW STATS & CHARTS
elseif (isset($_GET['stats_charts']) && isset($_GET['report_id'])
	&& (is_numeric($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
	// Get html for all the fields to display for report
	$html .= DataExport::outputStatsCharts(	$_GET['report_id'],
											(isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
											(isset($_GET['events']) ? explode(',', $_GET['events']) : array()));
	// Add note about Missing Data Codes for "Missing" values
    $html .= RCView::div(array('class'=>'spacer mt-5'),' ') .
             RCView::h6(array('class'=>'mt-3', 'style'=>'color:#A00000;'),
                "<span class='em-ast' style='font-size:16px;'>*</span> " . $lang['missing_data_13']
             );
}
## VIEW REPORT
elseif (isset($_GET['report_id']) && (is_numeric($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
	// Get report name
	$report_name = DataExport::getReportNames($_GET['report_id'], !$user_rights['reports']);
	// If report name is NULL, then user doesn't have Report Builder rights AND doesn't have access to this report
	if ($report_name === null) {
		$html .= RCView::div(array('class'=>'red'),
					$lang['global_01'] . $lang['colon'] . " " . $lang['data_export_tool_180']
				);
	} else {
		// Display progress while report loads via ajax
		$html .= RCView::div(array('id'=>'report_load_progress', 'style'=>'display:none;margin:5px 0 25px 20px;color:#777;font-size:18px;'),
					RCView::img(array('src'=>'progress_circle.gif')) .
					$lang['report_builder_60'] . " \"" .
					RCView::span(array('style'=>'color:#800000;font-size:18px;'),
						$report_name
					) .
					"\"" .
					RCView::span(array('id'=>'report_load_progress_pagenum_text', 'style'=>'display:none;margin-left:10px;color:#777;font-size:14px;'),
						"({$lang['global_14']} " .
						RCView::span(array('id'=>'report_load_progress_pagenum'), '1')  .
						")"
					)
				 ) .
				 RCView::div(array('id'=>'report_load_progress2', 'style'=>'display:none;margin:5px 0 0 20px;color:#999;font-size:18px;'),
					RCView::img(array('src'=>'hourglass.png')) .
					$lang['report_builder_115']
				 );
		// Div where report will go
		$html .= RCView::div(array('id'=>'report_parent_div', 'style'=>''), '');
	}
}


// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
print	RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
			RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'),
				$lang['app_23']
			) .
			RCView::div(array('class'=>'d-print-none', 'style'=>'float:right;padding:0 5px 5px 0;'),
				// VIDEO link
                '<i class="fas fa-film"></i> ' .
				RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:12px;font-weight:normal;text-decoration:underline;', 'onclick'=>"popupvid('exports_reports01.mp4','".js_escape($lang['report_builder_131'])."');"),
					"{$lang['global_80']} {$lang['report_builder_131']}"
				)
			) .
			RCView::div(array('class'=>'clear'), '')
		);
// JavaScript files
loadJS('Libraries/jquery_tablednd.js');
loadJS('DataExport.js');
// Hidden dialog to choose export format
$html .= DataExport::renderExportOptionDialog();
// Hidden dialog for Shared Library manuscript citation
if ($Proj->formsFromLibrary()) {
	$html .= "<!-- Hidden citation for Shared Library manuscript -->
			<div class='simpleDialog' style='font-size:13px;' id='rsl_cite' title='".js_escape($lang['data_export_tool_146'])."'>
				Jihad S. Obeid, Catherine A. McGraw, Brenda L. Minor, Jos&eacute; G. Conde, Robert Pawluk, Michael Lin, Janey Wang, Sean R. Banks, Sheree A. Hemphill, Rob Taylor, Paul A. Harris,
				<b>Procurement of shared data instruments for Research Electronic Data Capture (REDCap)</b>, Journal of Biomedical Informatics, Available online 10 November 2012, ISSN 1532-0464, 10.1016/j.jbi.2012.10.006.
				(<a target='_blank' style='text-decoration:underline;' href='http://www.sciencedirect.com/science/article/pii/S1532046412001608'>http://www.sciencedirect.com/science/article/pii/S1532046412001608</a>)
			</div>";
}
?>
<style type="text/css">
.ui-autocomplete {
	max-height: 200px;
	max-width: 500px;
	overflow-y: auto;
	/* prevent horizontal scrollbar */
	overflow-x: hidden;
}
.report_pagenum_div { margin:0;padding:8px 15px 7px;max-width:100%;width:800px; }
.report_sort_msg { font-size:11px;color:#C00000;padding:1px 5px;display:none; }
table#export_choices_table tr td {
	border: 1px solid #eee;
}
.dataTables_filter-parent { float:right; }
.dataTables_filter-parent label { 
	display: inline;
    margin-bottom: 5px;
    font-weight: 700;
}
table.dataTable thead tr th {
	background-color: #FFFFE0;
	border-top: 1px solid #aaaaaa;
	border-bottom: 1px solid #aaaaaa;
}
table.dataTable.cell-border thead tr th {
	border-right: 1px solid #ddd;
}
table.dataTable.cell-border thead tr th:first-child {
    border-left: 1px solid #ddd;
}
table.dataTable tr td a.rl { font-size:8pt;font-family:Verdana;text-decoration:underline; }
table.dataTable tr th { line-height: 11px; }
table.dataTable tr th.rpthdrc { border-top:0; }
table.dataTable tr th.rptchclbl { border-bottom:1px dashed #ccc; }
table.dataTable tbody td, table.dataTable thead th { padding:5px; }
#report_table { border-bottom:1px solid #ccc; }
th.form_noaccess { background:#eee;color:#777; }
td.form_noaccess { background:#C1C1C1;color:#777;text-align:center; }
td.nodesig { background:#d9d9d9; }
tr.even td.nodesig { background:#d3d3d3; }
.shadow {
	-moz-box-shadow: 3px 3px 3px #ddd;
	-webkit-box-shadow: 3px 3px 3px #ddd;
	box-shadow: 3px 3px 3px #ddd;
}
.export_box {
	border-bottom-left-radius:10px 10px;
	border-bottom-right-radius:10px 10px;
	border-top-left-radius:10px 10px;
	border-top-right-radius:10px 10px;
}
.export_hdr {
	font-weight: bold;
	font-size: 16px;
	border-bottom: 1px solid #eee;
	margin: 2px 0 8px;
}
.rprt_selected_hidden { display: none; }
.field-dropdown-div .fn { color:#555;font-size:11px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:7px 0 0 30px;width:270px; }
.field-dropdown-div .fna { font-weight:normal;margin-right:3px; }
.field-dropdown-div .field-dropdown { width:260px;color:#800000; }
.crl { white-space: normal; }
#quickAddField_dialog td.data { vertical-align:middle; overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
#quickAddField_dialog td.data span { margin-left:5px; color:#777; }
#quickAddField_dialog td.data img { display:none; }
table.no-footer.fixedHeader-floating { display: none; }
</style>
<script type="text/javascript">
// Set variable if user has "reports" user rights
var user_rights_reports = <?php print $user_rights['reports'] ?>;
<?php if (isset($_GET['addedit'])) { ?>
	// List of field variables/labels for auto suggest
	var autoSuggestFieldList = <?php print DataExport::getAutoSuggestJsString() ?>;
	// List of all possible filter operators
	var allLimiterOper = new Object();
	<?php
	foreach (DataExport::getLimiterOperators() as $key=>$val) {
		// Change "not =" to "<>"
		if ($val == "not =") $val = "<>";
		print "allLimiterOper['$key'] = '".js_escape($val)."';\n";
	}
	?>
	// List of unique events
	var uniqueEvents = new Object();
	uniqueEvents[''] = '';
	<?php
	foreach ($Proj->getUniqueEventNames() as $key=>$val) {
		print "uniqueEvents['$key'] = '$val';\n";
	}
	?>
	// List of forms with comma-delimited list of fields in each form
	var formFields = new Object();
	<?php
	foreach ($Proj->forms as $key=>$val) {
		$formFields = $val['fields'];
		foreach (array_keys($formFields) as $this_field) {
			// Remove descriptive fields since they have no data
			if ($Proj->metadata[$this_field]['element_type'] == 'descriptive') {
				unset($formFields[$this_field]);
			}
		}
		print "formFields['$key']='".implode(',', array_keys($formFields))."';\n";
	}
	?>
	// List of fields with their respective form name
	var fieldForms = new Object();
	<?php
	foreach ($Proj->metadata as $this_field=>$attr) {
		print "fieldForms['$this_field']='{$attr['form_name']}';";
	}
	print "\nvar formLabels = new Object();\n";
	foreach ($Proj->forms as $key=>$attr) {
		print "formLabels['$key']='".js_escape($attr['menu'])."';";
	}
}
?>

// Language variables
var langQuestionMark = '<?php print js_escape($lang['questionmark']) ?>';
var closeBtnTxt = '<?php print js_escape($lang['global_53']) ?>';
var exportBtnTxt = '<?php print js_escape($lang['report_builder_48']) ?>';
var exportBtnTxt2 = '<?php print js_escape($lang['data_export_tool_199']." ".$lang['data_export_tool_209']) ?>';
var langSaveValidate = '<?php print js_escape($lang['report_builder_52']) ?>';
var langIconSaveProgress = '<?php print js_escape($lang['report_builder_55']) ?>';
var langIconSaveProgress2 = '<?php print js_escape($lang['report_builder_56']) ?>';
var langIconSaveProgress3 = '<?php print js_escape($lang['report_builder_147']) ?>';
var langCancel = '<?php print js_escape($lang['global_53']) ?>';
var langNoTitle = '<?php print js_escape($lang['report_builder_68']) ?>';
var langNoUserAccessSelected = '<?php print js_escape($lang['report_builder_69']) ?>';
var langNoFieldsSelected = '<?php print js_escape($lang['report_builder_70']) ?>';
var langLimitersIncomplete = '<?php print js_escape($lang['report_builder_71']) ?>';
var langTypeVarName = '<?php print js_escape($lang['report_builder_30']) ?>';
var langDragReport = '<?php print js_escape($lang['report_builder_75']) ?>';
var langDelete = '<?php print js_escape($lang['global_19']) ?>';
var langDeleteReport = '<?php print js_escape($lang['report_builder_11']) ?>';
var langDeleteReportConfirm = '<?php print js_escape($lang['report_builder_76']) ?>';
var langCopy = '<?php print js_escape($lang['report_builder_46']) ?>';
var langCopyReport = '<?php print js_escape($lang['report_builder_08']) ?>';
var langCopyReportConfirm = '<?php print js_escape($lang['report_builder_77']) ?>';
var langExporting = '<?php print js_escape($lang['report_builder_51']) ?>';
var langConvertToAdvLogic = '<?php print js_escape($lang['report_builder_94']) ?>';
var langConvertToAdvLogic2 = '<?php print js_escape($lang['report_builder_95']) ?>';
var langConvertToAdvLogic3 = '<?php print js_escape($lang['report_builder_97']) ?>';
var langConvertToAdvLogic4 = '<?php print js_escape($lang['report_builder_98']) ?>';
var langConvertToAdvLogic5 = '<?php print js_escape($lang['report_builder_99']) ?>';
var langConvert = '<?php print js_escape($lang['report_builder_96']) ?>';
var langPreviewLogic = '<?php print js_escape($lang['report_builder_100']) ?>';
var langChooseOtherfield = '<?php print js_escape($lang['report_builder_103']) ?>';
var langError = '<?php print js_escape($lang['global_01']) ?>';
var langReportFailed = '<?php print js_escape($lang['report_builder_128']) ?>';
var langExportFailed = '<?php print js_escape($lang['report_builder_129']) ?>';
var langTotFldsSelected = '<?php print js_escape($lang['report_builder_138']) ?>';
var langExportWholeProject = '<?php print js_escape($lang['data_export_tool_208']) ?>';
var max_live_filters = <?php print DataExport::MAX_LIVE_FILTERS ?>;
</script>
<?php
// Tabs
DataExport::renderTabs();
// Output content
print $html;
// If displaying the "add/edit report" table, do direct Print to page because $html might get very big
if (isset($_GET['addedit'])) {
	DataExport::outputCreateReportTable(isset($_GET['report_id']) ? $_GET['report_id'] : '');
}
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';