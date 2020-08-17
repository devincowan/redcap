<?php
// Prevent view from being called directly
require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
System::init();
// Set tab active status
$activeClass = ' active';
$tabHomeActive = (!isset($_GET['action']) && !isset($_GET['route']) && strpos(PAGE_FULL, '/index.php') !== false && strpos(PAGE_FULL, 'ControlCenter/') === false && strpos(PAGE_FULL, 'ToDoList/') === false) ? $activeClass : "";
$tabMyProjectsActive = (isset($_GET['action']) && $_GET['action'] == 'myprojects') ? $activeClass : "";
$tabNewProjectActive = (isset($_GET['action']) && $_GET['action'] == 'create') ? $activeClass : "";
$tabTrainingActive = (isset($_GET['action']) && $_GET['action'] == 'training') ? $activeClass : "";
if (SUPER_USER || ACCOUNT_MANAGER) {
	$tabTrainingActive = ($tabTrainingActive == $activeClass) ? ' class="active d-none d-md-block"' : ' class="d-none d-md-block"';
}
$tabHelpActive = (isset($_GET['action']) && $_GET['action'] == 'help') ? $activeClass : "";
$tabSendItActive = (PAGE == 'SendItController:upload') ? $activeClass : "";
$tabControlCenterActive = (strpos(PAGE, 'ControlCenter/') !== false || strpos(PAGE, 'ToDoList/') !== false) ? $activeClass : "";
$navCollapse = (strpos(PAGE, 'ControlCenter/') !== false || strpos(PAGE, 'ToDoList/') !== false) ? 'onclick="toggleProjectMenuMobile($(\'#control_center_menu\'))"' : 'data-target="#redcap-home-navbar-collapse"';
$ccBrand = (strpos(PAGE, 'ControlCenter/') !== false || strpos(PAGE, 'ToDoList/') !== false) ? '<a class="d-inline d-md-none navbar-brand" style="font-size:13px;padding-left:0;" href="'.APP_PATH_WEBROOT.'ControlCenter/index.php"><kbd>'.$lang['global_07'].'</kbd></a>' : '';
$controlCenterDefaultPage = ACCOUNT_MANAGER ? "ControlCenter/view_users.php" : "ControlCenter/index.php";
$tabUserProfileActive = (PAGE == 'Profile/user_profile.php') ? " active" : "";

loadJS('Libraries/velocity-min.js');
loadJS('Libraries/velocity-ui-min.js');
?>

<nav class="navbar navbar-expand-md navbar-light fixed-top" style="background-color:#f8f8f8;border-bottom:1px solid #e7e7e7;padding:0 10px 0 0;" role="navigation">
	<a class="navbar-brand" style="padding:8px 0px 5px 10px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>"><img alt="REDCap" style="width:120px;height:36px;" src="<?php echo APP_PATH_IMAGES ?>redcap-logo-medium.png"></a>
	<?php print $ccBrand ?>
	<button type="button" class="navbar-toggler collapsed" data-toggle="collapse" <?php print $navCollapse ?> aria-expanded="false">
		<span class="navbar-toggler-icon"></span>
	</button>

    <div class="collapse navbar-collapse" id="redcap-home-navbar-collapse">
      <ul class="nav navbar-nav">
        <li id="nav-tab-home" class="nav-item<?php print $tabHomeActive ?>"><a class="nav-link" style="color:#333;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php"><?php print $lang['home_21'] ?></a></li>
        <li class="nav-item<?php print $tabMyProjectsActive ?>"><a class="nav-link" style="color:#000;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=myprojects"><i class="far fa-list-alt"></i> <?php print $lang['home_22'] ?></a></li>
        <?php if (isset($GLOBALS['allow_create_db']) && $GLOBALS['allow_create_db']) { ?><li class="nav-item<?php print $tabNewProjectActive ?>"><a class="nav-link" style="color:#008000;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=create"><i class="fas fa-plus"></i> <?php print $lang['home_61'] ?></a></li><?php } ?>
        <li id="nav-tab-help" class="nav-item<?php print $tabHelpActive ?>"><a class="nav-link" style="color:#3E72A8;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=help"><i class="fas fa-question-circle"></i> <?php print $lang['bottom_27'] ?></a></li>
		<li id="nav-tab-training" class="nav-item<?php print $tabTrainingActive ?>"><a class="nav-link" style="color:#725627;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=training"><i class="fas fa-film"></i> <?php print $lang['home_62'] ?></a></li>
        <?php if ($GLOBALS['sendit_enabled'] == '1' || $GLOBALS['sendit_enabled'] == '2') { ?> <li id="nav-tab-sendit" class="nav-item<?php print $tabSendItActive ?> d-none d-lg-block"><a class="nav-link" style="color:#660303;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT ?>index.php?route=SendItController:upload"><i class="fas fa-envelope"></i> <?php print $lang['home_26'] ?></a></li><?php } ?>
        <?php if (isset($GLOBALS['user_messaging_enabled']) && $GLOBALS['user_messaging_enabled'] == 1) { ?>
			<li class="nav-item"><?php print Messenger::renderHeaderIcon('navbar') ?></li>
		<?php } ?>
		<?php if (SUPER_USER || ACCOUNT_MANAGER) { ?><li class="nav-item<?php print $tabControlCenterActive ?>"><a class="nav-link" style="color:#000;padding:15px 8px;" href="<?php print APP_PATH_WEBROOT . $controlCenterDefaultPage ?>"><i class="fas fa-cog"></i> <?php print $lang['global_07'] ?></a></li><?php } ?>
	  </ul>

	  <ul class="nav navbar-nav ml-auto">
        <li class="nav-item d-none d-sm-block nohighlighthover<?php if (SUPER_USER || ACCOUNT_MANAGER) print " loggedInUsername"; ?>"><a class="nav-link" href="#" style='cursor:default;color:#000;padding:10px 10px;font-size:11px;line-height:14px;'><?php print (defined('USERID') ? $lang['bottom_01'].RCView::br().RCView::p(array('id'=>'username-reference','class'=>'my-proj-username'),USERID).RCView::b(USERID) : ''); ?></a></li>
        <li id="nav-tab-profile" class="nav-item d-block d-sm-none d-lg-block<?php print $tabUserProfileActive ?>"><a class="nav-link" style='color:#3c5ca3;padding:15px 8px;' href="<?php echo APP_PATH_WEBROOT ?>Profile/user_profile.php"><i class="fas fa-user-circle"></i> <?php echo $lang['config_functions_50'] ?></a></li>
        <li id="nav-tab-logout" class="nav-item d-block d-sm-none d-lg-block"><a class="nav-link" style="color:#555;padding:15px 8px;" href="<?php echo PAGE_FULL . (($_SERVER['QUERY_STRING'] == "") ? "?" : "?" . $_SERVER['QUERY_STRING'] . "&") ?>logout=1"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['bottom_02'] ?><span class="d-block d-sm-none" style="margin-left:20px;"><?php print (defined('USERID') ? "(".$lang['bottom_01'].RCView::SP.RCView::b(USERID).")" : ''); ?></span></a></li>

        <li class="nav-item dropdown d-none d-sm-block d-md-block d-lg-none">
          <a class="nav-link" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><?php print $lang['global_134'] ?> <i class="fas fa-caret-down"></i></a>
          <ul class="dropdown-menu dropdown-menu-right">
            <li id="nav-tab-help2" class="nav-item"><a class="nav-link" style="color:#3E72A8;padding:5px 20px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=help"><i class="fas fa-question-circle"></i> <?php print $lang['bottom_27'] ?></a></li>
            <li id="nav-tab-training2" class="nav-item"><a class="nav-link" style="color:#725627;padding:5px 20px;" href="<?php print APP_PATH_WEBROOT_PARENT ?>index.php?action=training"><i class="fas fa-film"></i> <?php print $lang['home_62'] ?></a></li>
			<?php if ($GLOBALS['sendit_enabled'] == '1' || $GLOBALS['sendit_enabled'] == '2') { ?> <li id="nav-tab-sendit2"<?php print $tabSendItActive ?>><a class="nav-link" style="color:#660303;padding:5px 20px;" href="<?php print APP_PATH_WEBROOT ?>index.php?route=SendItController:upload"><i class="fas fa-envelope"></i> <?php print $lang['home_26'] ?></a></li><?php } ?>
			<li id="nav-tab-profile2" class="nav-item"><a class="nav-link" style='color:#3c5ca3;padding:5px 20px;' href="<?php echo APP_PATH_WEBROOT ?>Profile/user_profile.php"><i class="fas fa-user-circle"></i> <?php echo $lang['config_functions_50'] ?></a></li>
			<li role="separator" class="nav-item divider"></li>
            <li id="nav-tab-logout2" class="nav-item"><a class="nav-link" style="color:#555;padding:0px 20px 5px;" href="<?php echo PAGE_FULL . (($_SERVER['QUERY_STRING'] == "") ? "?" : "?" . $_SERVER['QUERY_STRING'] . "&") ?>logout=1"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['bottom_02'] ?><span class="d-block d-sm-none" style="margin-left:20px;"><?php print (defined('USERID') ? "(".$lang['bottom_01'].RCView::SP.RCView::b(USERID).")" : ''); ?></span></a></li>
          </ul>
        </li>
	  </ul>
    </div>
</nav>