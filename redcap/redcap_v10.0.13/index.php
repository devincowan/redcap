<?php


if (isset($_GET['pid'])) {
	require_once 'Config/init_project.php';
} else {
	require_once 'Config/init_global.php';
}
// Routing to controller: Check "route" param in query string (if exists)
$Route = new Route();
// If no pid is provided, then redirect to the REDCap Home page
if (!isset($_GET['pid'])) System::redirectHome();

// Header and tabs
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
loadJS('Calendar.js');

// REDCap Hook injection point: Pass project_id to method
Hooks::call('redcap_project_home_page', array(PROJECT_ID));

// Determine if project is being used as a template project
$templateInfo = ProjectTemplates::getTemplateList($project_id);
$isTemplate = (!empty($templateInfo));
if ($isTemplate) {
	// Edit/remove template
	$templateTxt =  RCView::img(array('src'=>($templateInfo[$project_id]['enabled'] ? 'star.png' : 'star_empty.png'))) .
					RCView::span(array('style'=>'margin-right:10px;vertical-align: middle;'), $lang['create_project_91']) .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"),
						RCView::img(array('src'=>'pencil.png','title'=>$lang['create_project_90']))
					) .
					RCView::SP .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_delete',$project_id)"),
						RCView::img(array('src'=>'cross.png','title'=>$lang['create_project_93']))
					);
	$templateClass = 'yellow';
} else {
	// Add as template
	$templateTxt =  RCView::img(array('src'=>'star_empty.png')) .
					RCView::span(array('style'=>'margin-right:10px;vertical-align: middle;'), $lang['create_project_92']) .
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs','style'=>'font-size:11px;margin-top:2px;','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"),
						$lang['design_171'] . RCView::SP
					);
	$templateClass = 'chklist';
}
$templateTxt = RCView::div(array('class'=>$templateClass,'style'=>'margin:0 0 4px;padding:2px 10px 4px 8px;float:right;'), $templateTxt);

// Warning about using Randomization module with DDE
if ($double_data_entry && $randomization && Randomization::setupStatus()) {
    print RCView::div(array('class'=>'yellow'),
            '<i class="fas fa-exclamation-triangle"></i> '. RCView::b($lang['global_48']) . RCView::br() . $lang['data_entry_470']
          );
}
?>

<!-- PROJECT DASHBOARD -->
<div class="col-12 mb-4" style="padding:10px 20px;max-width:800px;">
	<div class="clearfix mb-3">
	    <div class="float-left"><?php echo $lang['index_70'] ?></div>
	    <?php if (UserRights::isSuperUserNotImpersonator()) { ?><div class="float-right"><?php echo $templateTxt ?></div><?php } ?>
	</div>
	<div class="row">
<?php

/**
 * USER TABLE
 */
// Loop through user rights
$user_list = $proj_users = array();
$user_rights_all = UserRights::getPrivileges($project_id);
$user_rights_all = $user_rights_all[$project_id];
foreach ($user_rights_all as $this_user=>$attr) {
	$proj_users[] = $this_user = strtolower($this_user);
	$user_list[$this_user]['expiration']  = $attr['expiration'];
	$user_list[$this_user]['double_data'] = $attr['double_data'];
}


// Get users' email, name, and suspension status
$user_info = array();
$q = db_query("select username, user_email, user_firstname, user_lastname, if(user_suspended_time is null, '0', '1') as suspended
			   from redcap_user_information where username in (".prep_implode($proj_users).")");
while ($row = db_fetch_array($q)) {
	$row['username'] = strtolower($row['username']);
	$user_info[$row['username']]['user_email'] 		= $row['user_email'];
	$user_info[$row['username']]['user_firstlast'] 	= $row['user_firstname'] . " " . $row['user_lastname'];
	$user_info[$row['username']]['suspended'] 		= $row['suspended'];
}
//Loop through user list to render each row of users table
$i = 0;
foreach ($user_list as $this_user=>$row) {
	//Render expiration date, if exists (expired users will display in red)
	if ($row['expiration'] == '') {
		$row['expiration'] = "<span style=\"color:gray\">{$lang['index_37']}</span>";
	} else {
		if (str_replace("-","",$row['expiration']) < date('Ymd')) {
			$row['expiration'] = "<span style=\"color:red\">".DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24')."</span>";
		} else {
			$row['expiration'] = DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24');
		}
	}
	// Add text if user is suspended
	$suspendedText = ((!isset($user_info[$this_user]) || !$user_info[$this_user]['suspended'])) ? '' :
						RCView::div(array('style'=>'color:red;'),
							$lang['rights_281']
						);
	//If user's name and email are recorded, display their name and email
	if (isset($user_info[$this_user])) {
		$name_email = "<div>(<a style=\"font-size:11px\"
			href=\"mailto:".$user_info[$this_user]['user_email']."\">".js_escape($user_info[$this_user]['user_firstlast'])."</a>)</div>";
	} else {
		$name_email = "";
	}
	$row_data[$i][0] = RCView::escape($this_user) . $name_email . $suspendedText;
	$row_data[$i][1] = $row['expiration'];
	if ($double_data_entry) {
		if ($row['double_data'] == 0) $double_data_label = $lang['rights_51']; else $double_data_label = "#" . $row['double_data'];
		$row_data[$i][2] = $double_data_label;
	}
	$i++;
}

$title = "<div style=\"padding:0;\"><i class=\"fas fa-user\"></i>
	<span style=\"color:#000066;\">{$lang['index_19']}</span><span style=\"margin-left:7px;font-weight:normal;\">($i)</span></div>";
$width = 195;
$col_widths_headers = array(
						array(102, $lang['global_17'], "left"),
						array(65,  $lang['index_35'], "center")
					  );
if ($double_data_entry)
{
	$dde_col_width = 50;
	$col_widths_headers[] = array($dde_col_width, RCView::div(array('class'=>'wrap'), $lang['index_36']), "left");
	$width += $dde_col_width+12;
}
print "<div class='col-12 col-md-".($double_data_entry ? '6' : '4')." float-left'>";
renderGrid("user_list", $title, $width, 'auto', $col_widths_headers, $row_data);
print "<div style='margin-top:10px;'></div></div>";



/**
 * PROJECT STATISTICS TABLE
 */
$title = '<div style="padding:0;"><i class="fas fa-clipboard-list"></i> ' . $lang['index_27'] . '</div>';

$file_space_usage_text = "<span style='cursor:pointer;cursor:hand;' onclick=\"\$('#fileuse_explain').toggle('blind','fast');\">{$lang['index_56']}</span>
						  <div id='fileuse_explain'>
								{$lang['index_51']}
						  </div>";

// Set column widths
$col1_width = 137;
$col2_width = 138;

$col_widths_headers = array(
						array($col1_width, '', "left"),
						array($col2_width,  '', "center")
					  );
$row_data = array(
				array($lang['index_22'], "<span id='projstats1'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>"),
				//array($lang['index_23'], $num_data_exports),
				//array($lang['index_24'], $num_logged_events),
				array($lang['index_25'], "<span id='projstats2'>".($last_logged_event != "" ? DateTimeRC::format_user_datetime($last_logged_event, 'Y-M-D_24') : "<span style='color:#888;'>{$lang['data_entry_64']}</span>")."</span>"),
				array($file_space_usage_text, "<span id='projstats3'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>")
			);
if ($double_data_entry)
{
	$row_data[] = array($lang['global_04'], $lang['index_30']);
}

print "<div class='col-12 col-md-".($double_data_entry ? '6' : '8')." float-right text-left'>";

// Render the table
print "<div>";
renderGrid("stats_table", $title, 300, 'auto', $col_widths_headers, $row_data, false);
print "<div style='margin-top:20px;'></div></div>";


/**
 * UPCOMING EVENTS TABLE
 * List any events scheduled on the calendar in the next 7 days (if any)
 */
// Do not show the calendar events if don't have access to calendar page
if ($user_rights['calendar']) print Calendar::renderUpcomingAgenda(7);

print "</div></div></div>";

?>
<script type="text/javascript">
// AJAX call to fetch the stats table values
$(function(){
	$.get(app_path_webroot+'ProjectGeneral/project_stats_ajax.php', { pid: pid }, function(data){
		if (data!='0') {
			var json = jQuery.parseJSON(data);
			$('#projstats1').html(json[0]);
			$('#projstats2').html(json[1]);
			$('#projstats3').html(json[2]);
		}
	});
});
</script>
<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
