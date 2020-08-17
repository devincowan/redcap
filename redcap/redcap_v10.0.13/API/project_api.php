<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) System::redirectHome();

$db = new RedCapDB();
$token = $db->getAPIToken($userid, $project_id);

// API help
$instr = RCView::p(array('style' => 'margin-top:20px;'),
				$lang['system_config_114'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' ');

// If using SSL, give reminder about checking SSL certificate in API request
if (SSL) {
	$instr .= RCView::p(array('class'=>'yellow', 'style'=>'margin:20px 0;'),
				RCView::img(array('src' => 'exclamation_orange.png')) .
				RCView::b($lang['api_09']) . RCView::br() .
				$lang['api_10'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/?view=security', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' '
			  );
}

$h = ''; // will hold the HTML to display in API div (all JS is included inline at the bottom)
$h .= RCView::span(array('id' => 'apiDialogContainerId', 'style' => 'display: none;'), '');

// dummy container used as a target for a loading overlay
$dummy = '';
// API token for selected project
$tok = '';
$tok .= RCView::div(array('class' => 'chklisthdr'), $lang['api_05'] . ' "' . RCView::escape($app_title) . '"');
$tok .= RCView::div(array('style' => 'margin:10px 0;'), $lang['edit_project_87']);
$tok .= RCView::div(array('style' => 'margin:10px 0 25px;'),
			RCView::div(array('style' => 'font-weight:bold;'),
				RCView::img(array('src'=>'coin.png')) .
				$lang['control_center_333'] . $lang['colon']
			) .
			RCView::span(array('id' => 'apiTokenId', 'style' => 'font-size: 18px; font-weight: bold; color: #347235;'), $token) . ' ');
$tok .= RCView::div(array('style' => 'margin:5px 0 0;'),
			RCView::button(array('class' => 'jqbuttonmed', 'onclick'=>"simpleDialog(null,null,'deleteTokenDialog',500,null,'".js_escape($lang['global_53'])."','deleteToken()','".js_escape($lang['edit_project_96'])."');"), $lang['edit_project_116']). '&nbsp; ' . $lang['edit_project_117']);
// Hidden delete dialog
$tok .= RCView::div(array('id'=>'deleteTokenDialog', 'title'=>$lang['edit_project_111'], 'class'=>'simpleDialog'),
			$lang['edit_project_112'].
			(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
				RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
		);
$tok .= RCView::div(array('style' => 'margin:5px 0 0;'),
			RCView::button(array('class' => 'jqbuttonmed', 'onclick'=>"simpleDialog(null,null,'deleteRegenDialog',500,null,'".js_escape($lang['global_53'])."','regenerateToken()','".js_escape($lang['edit_project_97'])."');"), $lang['edit_project_118']) . '&nbsp; ' . $lang['edit_project_119']);
// Hidden regen dialog
$tok .= RCView::div(array('id'=>'deleteRegenDialog', 'title'=>$lang['edit_project_113'], 'class'=>'simpleDialog'),
			$lang['edit_project_114'].
			(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
				RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
		);
$tok .= RCView::div(array('style' => 'margin:20px 0 0;', 'class'=>(SUPER_USER ? '' : 'hidden')),
					$lang['edit_project_115'] . '&nbsp; ' .
					RCView::span(array('id' => 'apiTokenUsersId', 'class' => 'code'), ''));
$dummy .= RCView::div(array('id' => 'apiTokenBoxId', 'class' => 'redcapAppCtrl', 'style' => 'display: none;'), $tok);
// API token request
$userInfo = $db->getUserInfoByUsername($userid);
$requestAuto = ($api_token_request_type == 'auto_approve_all' || ($api_token_request_type == 'auto_approve_selected' && $userInfo->api_token_auto_request == '1')) ? 1 : 0;
$req = '';
$req .= RCView::div(array('class' => 'chklisthdr'), $lang['api_139'] . ' "' . RCView::escape($app_title) . '"');
$req .= RCView::div(array('style' => 'margin:5px 0 0;'), ($requestAuto ? $lang['edit_project_183'] : $lang['edit_project_88']));
$ui_id = $userInfo->ui_id;
$todo_type = 'token access';
if(ToDoList::checkIfRequestExist($project_id, $ui_id, $todo_type) > 0){
	$reqAPIBtn = RCView::button(array('class' => 'api-req-pending'), $lang['api_03']);
	$reqP = RCView::p(array('class' => 'api-req-pending-text'), $lang['edit_project_179']);
}else{
	$reqAPIBtn = RCView::button(array('class' => 'jqbuttonmed', 'onclick' => "requestToken($requestAuto);"), ($requestAuto ? $lang['api_138'] : $lang['api_03']));
	$reqP = '';
}
$req .= RCView::div(array('class' => 'chklistbtn'), $reqAPIBtn.$reqP);
if ($super_user && !defined("AUTOMATE_ALL")) {
	$req .= RCView::br();
	$approveLink = APP_PATH_WEBROOT . 'ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid .
		'&api_pid=' . $project_id . '&goto_proj=1';
	$req .= RCView::button(array('onclick' =>"window.location.href='$approveLink';", 'class' => 'jqbuttonmed'), RCView::escape($lang['api_08'])) .
	RCView::SP . RCView::span(array('style' => 'color: red;'), $lang['edit_project_77']);
}
$dummy .= RCView::div(array('id' => 'apiReqBoxId', 'class' => 'redcapAppCtrl', 'style' => 'display: none;'), $req);

$h .= RCView::div(array('id' => 'apiDummyContainer'), $dummy);

// API Event names
$event_names = '';
$eventKeys = Event::getUniqueKeys($project_id);
$events = $db->getEvents($project_id);
// key the events by event ID
$tmp = array();
foreach ($events as $e) $tmp[$e->event_id] = $e;
$events = $tmp;
if ($longitudinal && count($events) > 0) {
	$eventRows = array($lang['edit_project_94'] . ' ' . RCView::span(array('style'=>'color:#800000;'), RCView::escape($app_title)));
	$eventRows[] = array($lang['define_events_65'], $lang['global_10'], $lang['global_08']);
	foreach ($eventKeys as $eventId => $eventKey) {
		$row = array();
		$row[] = RCView::font(array('class' => 'code'), RCView::escape($eventKey));
		$row[] = RCView::escape($events[$eventId]->descrip);
		$row[] = RCView::escape($events[$eventId]->arm_name);
		$eventRows[] = $row;
	}
	$event_names = RCView::div(array('style'=>'margin:20px 0;'), RCView::simpleGrid($eventRows, array(200, 200, 100)));
}

// If Data Access Groups exist, display them and their unique names here
$d = '';
$dags = $Proj->getUniqueGroupNames();
if (!empty($dags))
{
	$dagRows = array($lang['data_access_groups_ajax_20'] . ' ' . RCView::span(array('style'=>'color:#800000;'), RCView::escape($app_title)));
	$dagRows[] = array($lang['data_access_groups_ajax_18'], $lang['data_access_groups_ajax_21']);
	foreach (array_combine($dags, $Proj->getGroups()) as $unique=>$label) {
		$dagRows[] = array(RCView::font(array('class' => 'code'), $unique), $label);
	}
	$d = RCView::div(array('style'=>'margin:20px 0;'), RCView::simpleGrid($dagRows, array(200, 300)));
}

// display the page
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<script type='text/javascript'>
$(function() {
	<?php if (empty($token)) { ?>
	$("#apiReqBoxId").show();
	<?php } else { ?>
	$("#apiTokenBoxId").show();
	$.get(app_path_webroot + "API/project_api_ajax.php",
		{ action: 'getTokens', pid: pid },
		function(data) { $("#apiTokenUsersId").html(data); }
	);
	<?php } ?>
	$("#reqAPIRegenId").click(function() {
		$("#apiDialogRegenId").dialog({ bgiframe: true, modal: true, width: 500, buttons: {
			Cancel: function() { $(this).dialog('close'); },
			'<?php echo js_escape($lang['edit_project_97']) ?>': function() { $(this).dialog('close'); regenerateToken(); }}}
		);
		return false;
	});
});
</script>
<?php
// Title
renderPageTitle('<i class="fas fa-laptop-code"></i> ' . $lang['setup_77']);
// Page instructions
echo $instr;
// Tabs to view "my token" or all users' tokens (super users only)
if (SUPER_USER) {
	$tabs = array('API/project_api.php'=>RCView::img(array('src'=>'coin.png')) . $lang['control_center_340'],
				  'API/project_api.php?allUserTokens=1'=>RCView::img(array('src'=>'coins.png')) . $lang['control_center_341']);
	RCView::renderTabs($tabs);
}
if (SUPER_USER && isset($_GET['allUserTokens'])) {
	// Get JS dependencies
	loadJS('Libraries/underscore-min.js');
	loadJS('Libraries/backbone-min.js');
	loadJS('RedCapUtil.js');
	print RCView::div(array('style'=>'max-width:700px;'), $lang['control_center_342']);
	// List table of all users with token (super users only)
	include APP_PATH_DOCROOT . 'ControlCenter/user_api_tokens.php';
} else {
	// Box with user's API token and options
	echo $h;
	// Event and DAG tables
	echo $event_names;
	echo $d;
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
