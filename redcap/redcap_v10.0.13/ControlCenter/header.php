<?php


use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER && !System::isCI()) redirect(APP_PATH_WEBROOT);


// Check for any extra whitespace from config files that would mess up lots of things
$prehtml = ob_get_contents();


// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/YuiCharts.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/underscore-min.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/backbone-min.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "RedCapUtil.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "ControlCenter.js");
$objHtmlPage->addExternalJS(APP_PATH_WEBPACK . "css/tinymce/tinymce.min.js");
$objHtmlPage->PrintHeader();

// STATS: Check if need to report institutional stats to REDCap consortium
Stats::checkReportStats();

include APP_PATH_VIEWS . 'HomeTabs.php';

?>
<script type="text/javascript">
// Toggle displaying a secret key (for security)
function showSecret(selector) {
	$(selector).clone().attr('type','text').attr('size','60').insertAfter(selector).prev().remove();
}
</script>

<style type='text/css'>
.cc_label {
	padding: 10px; font-weight: bold; vertical-align: top; line-height: 16px; width: 40%;
}
.cc_data {
	padding: 10px; width: 60%; vertical-align: top; line-height: 16px;
}
.labelrc, .data {
	background:#F0F0F0 url('<?php echo APP_PATH_IMAGES ?>label-bg.gif') repeat-x scroll 0 0;
	border:1px solid #CCCCCC;
	font-size:12px;
	font-weight:bold;
	
	padding:5px 10px;
}
.labelrc a:link, .labelrc a:visited, .labelrc a:active, .labelrc a:hover { font-size:12px; font-family: "Open Sans",Helvetica,Arial,sans-serif; }
.notesbox {
	width: 100%;
}
.form_border { width: 100%;	}
#sub-nav { font-size:60%;margin-top:8px !important; }
#pagecontainer { max-width: 1100px;  }
h3, h4 { font-weight:bold; }
h4 {font-size: 1.4em;}
.cc_menu_header { font-weight:bold;padding:0px; }
.cc_menu_item, .cc_menu_section { font-weight:normal;padding:0px; }
.cc_menu_item { text-indent: -1.8em; margin-left: 1.8em; }
.cc_menu_divider { font-weight:normal;clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd; }
.cc_menu_item .glyphicon, .cc_menu_item span { text-indent:0;margin:0; }
hr { border-top: 1px solid #ccc; }
</style>

<div class="row">

	<?php	
	// Get count of pending To-Do List items
	$todoListItemsPending = ToDoList::getTotalNumberRequestsByStatus('pending') + ToDoList::getTotalNumberRequestsByStatus('low-priority');
	$todoListItemsPendingBadge = ($todoListItemsPending > 0) ? " <span class='badgerc'>$todoListItemsPending</span>" : "";
	?>

	<div id="control_center_menu" class="d-none d-md-block col-md-4 col-lg-3" role="navigation">
		
		<!-- ACCOUNT MANAGER TOOLS -->
		<?php if (ACCOUNT_MANAGER) { ?>
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_4581'] ?></div>
			<div class="cc_menu_item"><img src="<?php echo APP_PATH_IMAGES ?>users3.png">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/view_users.php"><?php echo $lang['control_center_109'] ?></a></div>
			<?php if (in_array($auth_meth_global, array('none', 'table', 'ldap_table','aaf_table','shibboleth_table'))) { ?>
				<div class="cc_menu_item"><img src="<?php echo APP_PATH_IMAGES ?>user_add3.png">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/create_user.php"><?php echo $lang['control_center_4570'] ?></a></div>
			<?php } ?>
			<div class="cc_menu_item"><img src="<?php echo APP_PATH_IMAGES ?>user_list.png">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/user_allowlist.php"><?php echo $lang['control_center_162'] ?></a></div>
			<div class="cc_menu_item"><img src="<?php echo APP_PATH_IMAGES ?>email_go.png">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/email_users.php"><?php echo $lang['email_users_02'] ?></a></div>
		</div>
		<?php } else { ?>
		
		<!-- REDCap Home Page and My Projects links (mobile view only) -->
		<div class="cc_menu_section d-block d-sm-none col-12">
			<div class="cc_menu_item"><i class='fas fa-home'></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT_PARENT ?>"><?php echo $lang['control_center_4531'] ?></a></div>
			<div class="cc_menu_item"><i class="far fa-list-alt"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT_PARENT ?>index.php?action=myprojects"><?php echo $lang['home_22'] ?></a></div>
            <div class="cc_menu_divider"></div>
        </div>
		
		<!-- Control Center Home -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_129'] ?></div>
			<div class="cc_menu_item"><i class="fas fa-info-circle"></i> <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/index.php"><?php echo $lang['control_center_117'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-tasks"></i> <a href="<?php echo APP_PATH_WEBROOT ?>ToDoList/index.php"><?php echo $lang['control_center_446'] . $todoListItemsPendingBadge ?></a></div>
            <div class="cc_menu_item mt-1">
                <i class="fas fa-sign-in-alt" style="margin-right:2px;"></i>
                <input type="text" id="pid-go-project" class="x-form-text x-form-field fs11" style="width:145px;" autocomplete="off" placeholder="<?php echo js_escape2($lang['control_center_4707']) ?>" onkeydown="if(event.keyCode==13){$(this).trigger('blur');return false;}" onblur="$(this).val($(this).val().trim()); if($(this).val()==''){return false;} if(redcap_validate(this,'1','','soft_typed','integer',1)){ window.location.href=app_path_webroot+'index.php?pid='+$(this).val(); }">
            </div>
        </div>
		<div class="cc_menu_divider"></div>
		
		<!-- Admin Resources -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_4689'] ?></div>
			<div class="cc_menu_item"><img src="<?php echo APP_PATH_IMAGES ?>redcap_community.png">&nbsp; <a target="_blank" href="https://community.projectredcap.org"><?php echo $lang['control_center_4690'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-globe" style="display:inline;margin-left:3px;top:1px;position: relative;"></i>&nbsp; <a target="_blank" href="https://projectredcap.org"><?php echo $lang['control_center_4691'] ?></a></div>
            <div class="cc_menu_item"><span class="fab fa-youtube" style="margin-left:3px;margin-right:2px;top:1px;" aria-hidden="true"></span> <a target="_blank" href="https://redcap.vanderbilt.edu/plugins/redcap_consortium/admin_videos.php"><?php echo $lang['control_center_4733'] ?></a></div>
            <div class="cc_menu_item"><span class="fas fa-user-graduate" style="margin-left:4px;top:1px;" aria-hidden="true"></span>&nbsp; <a target="_blank" href="https://redcap.vanderbilt.edu/plugins/redcap_consortium/training_materials.php"><?php echo $lang['control_center_4692'] ?></a></div>
            <?php if ($GLOBALS['enable_url_shortener_redcap']) { ?>
                <div class="cc_menu_item"><i class="fas fa-link" style="margin-left:3px;margin-right:1px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/url_shortener.php"><?php echo $lang['control_center_4708'] ?></a></div>
            <?php } ?>
        </div>
		<div class="cc_menu_divider"></div>
		
		<!-- Dashboard -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_03'] ?></div>
			<div class="cc_menu_item"><i class="fas fa-table" style="margin-left:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/system_stats.php"><?php echo $lang['dashboard_48'] ?></a></div>
			<?php if(FhirEhr::isCdisEnabledInSystem()) : ?>
			<div class="cc_menu_item"><i class="fas fa-fire" style="margin-left:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>index.php?route=FhirStatsController:index"><?php echo $lang['dashboard_126'] ?></a></div>
			<?php endif; ?>
			<div class="cc_menu_item"><i class="fas fa-receipt" style="margin-left:4px;margin-right:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/todays_activity.php"><?php echo $lang['control_center_206'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-chart-bar" style="margin-left:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/graphs.php"><?php echo $lang['control_center_4395'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-map-marker-alt" style='margin-left:3px;margin-right:2px;'></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/google_map_users.php"><?php echo $lang['control_center_386'] ?></a></div>
		</div>
		<div class="cc_menu_divider"></div>
		
		<!-- Projects -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_134'] ?></div>
			<div class="cc_menu_item"><i class="fas fa-layer-group"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/view_projects.php"><?php echo $lang['control_center_110'] ?></a></div>
            <div class="cc_menu_item"><i class="fas fa-edit" style="margin-left:1px;margin-right:1px;"></i> <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/edit_project.php"><?php echo $lang['control_center_4396'] ?></a></div>
            <div class="cc_menu_item"><i class="fas fa-link"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/survey_link_lookup.php"><?php echo $lang['control_center_4702'] ?></a></div>
        </div>
        <div class="cc_menu_divider"></div>
		
		<!-- Users -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_132'] ?></div>
			<div class="cc_menu_item"><i class="fas fa-user-friends"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/view_users.php"><?php echo $lang['control_center_109'] ?></a></div>
			<?php if (in_array($auth_meth_global, array('none', 'table', 'ldap_table','aaf_table','shibboleth_table'))) { ?>
				<div class="cc_menu_item"><i class="fas fa-user-plus"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/create_user.php"><?php echo $lang['control_center_4570'] ?></a></div>
			<?php } ?>
			<div class="cc_menu_item"><i class="fas fa-user-check"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/user_allowlist.php"><?php echo $lang['control_center_162'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-envelope"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/email_users.php"><?php echo $lang['email_users_02'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-coins"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/user_api_tokens.php"><?php echo $lang['control_center_245'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-user-shield"></i> <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/superusers.php"><?php echo $lang['control_center_4572'] ?></a></div>
		</div>
		<div class="cc_menu_divider"></div>
		
		<!-- Technical / Developer Tools -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_442'] ?></div>
			<?php if (defined("APP_URL_EXTMOD")) { ?>
				<div class="cc_menu_item"><i class="fas fa-cube fs14" style="margin-left:2px;margin-right:2px;"></i>&nbsp;<a href="<?php echo APP_URL_EXTMOD ?>manager/control_center.php"><?php echo $lang['global_142'] ?></a></div>
			<?php } ?>
			<div class="cc_menu_item"><i class="fas fa-laptop-code"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT_PARENT ?>api/help/index.php"><?php echo $lang['control_center_445'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-plug fs14" style="margin-left:2px;margin-right:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>Plugins/index.php"><?php echo $lang['control_center_4605'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-database" style="margin-left:2px;margin-right:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/mysql_dashboard.php"><?php echo $lang['control_center_4457'] ?></a></div>
		</div>
		<div class="cc_menu_divider"></div>
		
		<!-- Misc modules -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_4399'] ?></div>
            <div class="cc_menu_item"><i class="fas fa-fire" style="margin-left:3px;margin-right:1px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/ddp_fhir_settings.php"><?php echo $lang['ws_262'] ?></a></div>
            <div class="cc_menu_item nowrap"><i class="fas fa-database" style="margin-left:2px;margin-right:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/ddp_settings.php"><?php echo $lang['ws_63']." - ".$lang['ws_240'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-bookmark" style="margin-left:3px;margin-right:1px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/external_links_global.php"><?php echo $lang['extres_55'] ?></a></div>
			<div class="cc_menu_item"><i class="far fa-newspaper"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/pub_matching_settings.php"><?php echo $lang['control_center_4370'] ?></a></div>
        </div>
		<div class="cc_menu_divider"></div>
		
		<!-- System Configuration -->
		<div class="cc_menu_section">
			<div class="cc_menu_header"><?php echo $lang['control_center_131'] ?></div>
			<div class="cc_menu_item"><i class="fas fa-clipboard-check" style="font-size:15px;margin:0 0px 0 2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/check.php"><?php echo $lang['control_center_443'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-sliders-h"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/general_settings.php"><?php echo $lang['control_center_125'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-shield-alt"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/security_settings.php"><?php echo $lang['control_center_113'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-user-cog"></i> <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/user_settings.php"><?php echo $lang['control_center_315'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-file-upload" style="margin-left:3px;margin-right:1px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/file_upload_settings.php"><?php echo $lang['system_config_214'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-cubes fs14"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/modules_settings.php"><?php echo $lang['control_center_4604'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-check-square" style="margin-left:2px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/validation_type_setup.php"><?php echo $lang['control_center_150'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-home"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/homepage_settings.php"><?php echo $lang['control_center_4397'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-star"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/project_templates.php"><?php echo $lang['create_project_79'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-pen-square fs15"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/project_settings.php"><?php echo $lang['control_center_136'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-level-down-alt" style="margin-left:3px;margin-right:1px;"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/footer_settings.php"><?php echo $lang['control_center_4398'] ?></a></div>
			<div class="cc_menu_item"><i class="fas fa-clock"></i>&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/cron_jobs.php"><?php echo $lang['control_center_287'] ?></a></div>
		</div>
		
		<?php } ?>
	</div>

	<div id="control_center_window" style="padding-left:20px;" class="col-12 col-md-8">
