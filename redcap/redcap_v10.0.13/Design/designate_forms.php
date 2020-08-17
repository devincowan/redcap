<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';


print  "<p style='text-align:right;'>
			<i class=\"fas fa-film\"></i>
			<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=designate_instruments02.mp4&referer=".SERVER_NAME."&title=".js_escape($lang['global_28'])."','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">{$lang['designate_forms_23']}</a><br>
		</p>";

// Link back to Project Setup
$tabs = array(	"ProjectSetup/index.php"=>"<i class=\"fas fa-chevron-circle-left\"></i> {$lang['app_17']}",
				"Design/define_events.php".(isset($_GET['arm']) ? "?arm=".getArm() : "")=>"<i class=\"fas fa-business-time\"></i> {$lang['global_16']}",
				"Design/designate_forms.php".(isset($_GET['arm']) ? "?arm=".getArm() : "")=>"<span id='popupTrigger'><i class=\"far fa-hand-pointer\"></i> {$lang['global_28']}</span>" );
RCView::renderTabs($tabs);


//This page can only be used if multiple events have been defined (doesn't make sense otherwise)
if (!$longitudinal) {
	print  "<br><div class='red'>
			<b>{$lang['global_02']}:</b><br>
			{$lang['designate_forms_04']}
			<a href='".APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id' style='font-family:Verdana;text-decoration:underline;'>{$lang['designate_forms_05']}</a>
			{$lang['designate_forms_06']}
			</div></p>";
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

print  "<p>{$lang['designate_forms_07']}
		<a href='" . APP_PATH_WEBROOT . "Design/define_events.php?pid=$project_id' style='text-decoration:underline;'>{$lang['global_16']} </a>
		{$lang['designate_forms_09']}</p>
		<p>{$lang['designate_forms_10']} <i style='color:#800000;'>{$lang['designate_forms_11']}</i>
		{$lang['designate_forms_12']}
		<i style='color:#800000;'>{$lang['designate_forms_13']}</i> {$lang['designate_forms_14']}</p>";

?>
<style type="text/css">
table.dataTable thead tr th {
	background-color: #FFFFE0;
	border-top: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
table.dataTable.cell-border thead tr th {
	border-right: 1px solid #ddd;
}
table.dataTable.cell-border thead tr th:first-child {
    border-left: 1px solid #ddd;
}
table.dataTable tbody th, table.dataTable tbody td {
    padding: 3px 5px;
}
#event_grid_table { border-bottom:1px solid #ccc; }
#event_grid_table input {
	display:none;vertical-align: middle;margin:0;	
}
</style>
<script type="text/javascript">
$(function(){
	initDesigInstruments();
});
</script>
<?php

// NOTE: If normal users cannot add/edit events in production, then give notice
if (!$super_user && $status > 0 && !$enable_edit_prod_events)
{
	print  "<div class='yellow' style='margin-bottom:10px;'>
				<b>{$lang['global_02']}:</b><br>
				{$lang['define_events_10']}
				{$lang['define_events_11']} $project_contact_name {$lang['global_15']}
				<a href='mailto:$project_contact_email' style='font-family:Verdana;text-decoration:underline;'>$project_contact_email</a>.
			</div>";
}

//Div where table where be rendered
print  "<div id='table'>";
$arm = getArm();
include APP_PATH_DOCROOT . "Design/designate_forms_ajax.php";
print  "</div>";

Design::alertRecentImportStatus();

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
