<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// The app won't work without the API
if (!$api_enabled) System::redirectHome();

## SAVE DEVICE NICKNAME
if (isset($_GET['dashboard']) && isset($_POST['device_id']) && isset($_POST['nickname']))
{
	$_POST['nickname'] = strip_tags(label_decode($_POST['nickname']));
	$sql = "UPDATE redcap_mobile_app_devices SET nickname='".db_escape($_POST['nickname'])."' 
	        WHERE project_id = ".PROJECT_ID." AND device_id = '".db_escape($_POST['device_id'])."'";
	db_query($sql);
	exit( $_POST['nickname'] );
}

// display the page
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<script type='text/javascript'>
var lang_getAppCode1 = '<?php print js_escape($lang['mobile_app_53'] . (SUPER_USER ? " " . RCView::span(array('style'=>'color:#C00000;'), $lang['mobile_app_54']) : '')) ?>';
var lang_getAppCode2 = '<?php print js_escape($lang['global_01']) ?>';
</script>
<?php

// If user is going to initialization tab but has init'd the project in the app, then take them to Dashboard instead
if (!isset($_GET['files']) && !isset($_GET['activity']) && !isset($_GET['dashboard']) && !isset($_GET['logs']) && !isset($_GET['init'])) {
	// If has app activity, then send to dashboard tab, else send to init tab
	if (MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID)) {
		redirect(PAGE_FULL."?pid=$project_id&dashboard=1");
	} else {
		redirect(PAGE_FULL."?pid=$project_id&init=1");
	}
}

// REPEATING INSTANCES: Add note that currently the app does not support repeating forms/events
$repeatWarning = '';
if ($Proj->hasRepeatingFormsEvents()) {
    $repeatWarning = RCView::div(array('class'=>'float-right blue mb-2', 'style'=>'font-size:11px;max-width:350px;'), '<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' . $lang['mobile_app_129']);
}

// Title
renderPageTitle("<img src='" . APP_PATH_IMAGES . "phone_tablet.png'>&nbsp;&nbsp;" . $lang['global_118'] . $repeatWarning);
loadJS('MobileApp.js');

// FIELD EMBEDDING: Add note that it is not supported in the REDCap Mobile App
if (Piping::projectHasEmbeddedVariables($project_id)) {
	print RCView::div(array('class'=>'yellow mb-2', 'style'=>'font-size:11px;'), '<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' . $lang['design_798']);
}

// TABS
$tabs = array();
$tabs['MobileApp/index.php?init=1'] = 	RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_37']);
$tabs['MobileApp/index.php?dashboard=1'] = 	RCView::img(array('src'=>'table.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_60']);
$tabs['MobileApp/index.php?activity=1'] = 	RCView::img(array('src'=>'report.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_02']);
$tabs['MobileApp/index.php?files=1'] = 	RCView::img(array('src'=>'page_white_stack.png', 'style'=>'vertical-align:middle;')) .
										        RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_01']);
$tabs['MobileApp/index.php?logs=1'] = 	RCView::img(array('src'=>'page_white_stack.png', 'style'=>'vertical-align:middle;')) .
										        RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_61']);
print "<div class='d-none d-sm-block'>";
RCView::renderTabs($tabs);
print "</div>";

// Mobile tabs
?>
<div class="btn-group d-block d-md-none" role="group" style="margin-bottom:10px;margin-top:10px;">
	<button type="button" class="btn btn-defaultrc dropdown-toggle active" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<?php
		if (isset($_GET['files'])) {
			print 	RCView::img(array('src'=>'page_white_stack.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_01']);
		} elseif (isset($_GET['activity'])) {
			print 	RCView::img(array('src'=>'table.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_02']);
		} else {
			print 	RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_37']);
		}
		?>
	</button>
	<div class="dropdown-menu">
		<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT_PARENT ?> . "index.php?action=myprojects" style="font-size:15px;color:#393733;padding:7px 9px;">
			<img src="<?php echo APP_PATH_IMAGES ?>redcap_icon.gif" style="height:16px;width:16px;"> <?php print $lang['bottom_03'] ?></a>
		<?php foreach ($tabs as $this_url=>$this_set) { ?>
			<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT . $this_url . "&pid=" . PROJECT_ID ?>" style="font-size:15px;color:#393733;padding:7px 9px;">
				<?php echo $this_set ?>
			</a>
		<?php } ?>
	</div>
</div>
<?php

## ACTIVITY
if (isset($_GET['activity']))
{
	// Render instructions and table of all log entries for app for this project
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
				$lang['mobile_app_24']
			) .
			MobileApp::displayAppActivityTable(PROJECT_ID);
}

## DEVICE ACTIVITY
elseif (isset($_GET['dashboard']))
{
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
			   $lang['mobile_app_91']
			) .
			MobileApp::displayAppDashboardTables(PROJECT_ID);
}

## LOG ARCHIVE
elseif (isset($_GET['logs']))
{
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
				$lang['mobile_app_90']
			) .
			MobileApp::displayAppLogTables(PROJECT_ID);
}

## CSV ARCHIVE
elseif (isset($_GET['files']))
{
	// Render instructions and table
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
				$lang['mobile_app_20']
			) .
			MobileApp::displayAppDataDumpTables(PROJECT_ID);
}

## INITIALIZE PROJECT
else
{
	// Display init project page
	print MobileApp::displayInitPage();
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
