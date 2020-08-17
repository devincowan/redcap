<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Tabs
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
// JavaScript
loadJS('UserRights.js');
// DAG Switcher popovers, etc.
$dagSwitcher = new DAGSwitcher();
$dagSwitcher->includeUserRightsPageJs();

## DISPLAY USER LIST OR ENTER NEW USER NAME

// Get all roles
$roles = UserRights::getRoles();

// Page instructions
print "<p>{$lang['rights_216']}</p>";

// Display main user rights table
print 	RCView::div(array('id'=>'user_rights_roles_table_parent', 'style'=>'margin:0 20px 20px 0;'),
			UserRights::renderUserRightsRolesTable()
		);
// Hidden pop-up to add or edit user/role
print 	RCView::div(array('id'=>'editUserPopup', 'class'=>'simpleDialog'), '');
// API explanation pop-up
print 	RCView::div(array('id' => 'apiHelpDialogId', 'title' => $lang['rights_141'], 'style' => 'display: none;'),
			RCView::p(array('style' => ''),
							$lang['system_config_114'] . ' ' . $lang['edit_project_142'] . $lang['period'] . '<br/><br/>' .
							RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
							$lang['setup_45'] . ' ' . $lang['edit_project_142'])
			)
		);
// Mobile App explanation pop-up
print 	RCView::div(array('id' => 'appHelpDialogId', 'title' => $lang['global_118'], 'class' => 'simpleDialog'),
			RCView::b($lang['rights_308']) . RCView::br() . $lang['rights_310'] .
			RCView::br() . RCView::br() .
                        $lang['rights_321'] .
			RCView::br() . RCView::br() .
			RCView::b($lang['rights_311']) . RCView::br() . $lang['rights_312']
		);
// Mobile App enable confirmation pop-up
print 	RCView::div(array('id' => 'mobileAppEnableConfirm', 'title' => $lang['rights_303'], 'class' => 'simpleDialog'),
			$lang['rights_304']
		);
// TOOLTIP div when click USER'S EXPIRATION DATE in table
print 	RCView::div(array('id'=>'userClickExpiration', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'),
			RCView::div(array('style'=>'margin:5px 0 6px;font-weight:bold;font-size:13px;'), $lang['rights_203']) .
			// Set new expiration
			RCView::text(array('id'=>'tooltipExpiration', 'class'=>'x-form-text x-form-field', 'style'=>'color:#000;width:85px;', 'maxlength'=>'10',
				'onblur'=>"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
			// Date format
			RCView::span(array('class'=>'df', 'style'=>'font-size:11px;padding-left:5px;'), '('.DateTimeRC::get_user_format_label().')') .
			// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
			RCView::hidden(array('id'=>'tooltipExpirationHiddenUsername')) .
			RCView::div(array('style'=>'margin:3px 0 0;'),
				RCView::button(array('id'=>'tooltipExpirationBtn', 'class'=>'jqbuttonmed','onclick'=>"setExpiration();"), $lang['designate_forms_13']) .
				RCView::a(array('id'=>'tooltipExpirationCancel', 'href'=>'javascript:;', 'style'=>'margin-left:2px;color:#bbb;font-size:11px;text-decoration:underline;', 'onclick'=>"$('#userClickExpiration').hide();"), $lang['global_53']) .
				// Hidden progress save message
				RCView::span(array('id'=>'tooltipExpirationProgress', 'style'=>'margin:3px 0 0 10px;font-size:13px;color:#fff;font-weight:bold;', 'class'=>'hidden'),
					$lang['design_243']
				)
			)
		);

// TOOLTIP div when click USER'S DATA ACCESS GROUP in table
$groups = $Proj->getGroups();
print 	RCView::div(array('id'=>'userClickDagName', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'),
			RCView::div(array('style'=>'margin:5px 0 6px;font-weight:bold;font-size:13px;'), $lang['data_access_groups_ajax_32']) .
			// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
			RCView::hidden(array('id'=>'tooltipDagHiddenUsername')) .
			// DAG drop-down
			RCView::select(array('id'=>'userClickDagSelect', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:160px;color:#000;'),
				(array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '') .
			RCView::div(array('style'=>'margin:3px 0 0;'),
				// Select DAG
				RCView::button(array('id'=>'tooltipDagBtn', 'class'=>'jqbuttonmed','onclick'=>"assignUserDag();"), $lang['rights_181']) .
				RCView::a(array('id'=>'tooltipDagCancel', 'href'=>'javascript:;', 'style'=>'margin-left:2px;color:#bbb;font-size:11px;text-decoration:underline;', 'onclick'=>"$('#userClickDagName').hide();"), $lang['global_53']) .
				// Hidden progress save message
				RCView::span(array('id'=>'tooltipDagProgress', 'style'=>'margin:3px 0 0 10px;font-size:13px;color:#fff;font-weight:bold;', 'class'=>'hidden'),
					$lang['design_243']
				)
			)
		);

?>
<!-- Data Quality explanation pop-up -->
<div style="display:none;margin:15px 0;line-height:16px;" id="explainDataQuality"><?php echo $lang['dataqueries_101'] ?></div>
<!-- Data Resolution Workflow explanation pop-up -->
<div class="simpleDialog" id="explainDRW"><?php echo $lang['dataqueries_156']." ".$lang['dataqueries_144']."<br><br>".$lang['dataqueries_157'] ?></div>
<!-- Randomization explanation pop-up -->
<div style="display:none;" id="randHelpDialogId" title="<?php echo js_escape2($lang['rights_145']) ?>">
	<p><?php echo $lang['random_01'] ?></p>
	<p><?php echo $lang['create_project_63'] ?></p>
</div>
<!-- DDP explanation pop-up -->
<div class="simpleDialog" id="explainDDP" title="<?php echo js_escape2(($realtime_webservice_type == 'FHIR' ? $lang['ws_210'] . " " . $DDP->getSourceSystemName(true) : $lang['ws_28'])) ?>"><?php echo ($realtime_webservice_type == 'FHIR' ? $lang['ws_291'] : $lang['ws_31']) ?></div>
<!-- Custom javascript -->
<?php addLangToJS(array('rights_215', 'global_03', 'colon', 'rights_214', 'rights_317', 'rights_316', 'rights_161', 'rights_354', 'rights_360', 'rights_186', 'rights_163')) ?>
<script type="text/javascript">
var auth_meth = '<?=$auth_meth?>';
// Copy the role
function copyRoleName(role_name) {
	var copyRoleAction = function(){
		$('form#user_rights_form input[name=\'submit-action\']').val('copy_role');
		$('form#user_rights_form input[name=\'role_name_edit\']').val( trim($('#role_name_copy').val()) );
		saveUserFormAjax();
	};
	simpleDialog('<?php echo js_escape($lang['rights_212'].RCView::div(array('style'=>'margin:20px 0 0;font-weight:bold;'), $lang['rights_213'] . RCView::text(array('id'=>'role_name_copy', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:10px;width:150px;')))) ?>','<?php echo js_escape($lang['rights_211'].$lang['questionmark']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',copyRoleAction,'<?php echo js_escape($lang['rights_211']) ?>');
	$('#role_name_copy').val(role_name);
}
</script>
<style type="text/css">
    #user-rights-left-col td { padding-bottom: 6px; }
</style>
<?php

// REDCap Hook injection point: Pass project_id to method
Hooks::call('redcap_user_rights', array(PROJECT_ID));

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';