<?php


// Make sure the page can't be called directly
if (!defined("REDCAP_VERSION")) exit("ERROR!");

// Get list of projects where CURRENT user has User Rights privileges BUT is not expired
$projects_user_rights_access = array();
$sql = "select p.project_id from redcap_projects p, redcap_user_rights u left join redcap_user_roles r on r.role_id = u.role_id
		where p.project_id = u.project_id and u.username = '".db_escape(USERID)."'
		and if(u.user_rights = 1 and r.user_rights is null, 1, if(r.user_rights = 1, 1, 0)) = 1 and p.date_deleted is null
		and (u.expiration is null or (u.expiration is not null and u.expiration >= '".TODAY."'))";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$projects_user_rights_access[] = $row['project_id'];
}


// Set page title
$uad_title = RCView::div(array(),
				RCView::div(array('style'=>'float:left;font-size:18px;font-weight:bold;'),
					RCView::img(array('src'=>'key.png')) .
					RCView::img(array('src'=>'group.png', 'style'=>'margin-left:-7px;')) .
					$lang['rights_226']
				) .
				RCView::div(array('style'=>'float:right;margin-right:100px;'),
					RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.print();", 'style'=>'font-size:12px;'),
						RCView::img(array('src'=>'printer.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'),
							$lang['custom_reports_13']
						)
					)
				) .
				RCView::div(array('class'=>'clear'), '')
			 );




// IF USER POSTED CHANGES, PROCESS THEM
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Get yesterday's date (for expiring)
	$yesterdayDate = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	// Add expired/deleted users and their project_ids to arrays for logging purposes
	$expiredUsers = $deletedUsers = array();
	// Loop through Post values
	foreach ($_POST as $key=>$val)
	{
		// $val must be "expire" or "remove"
		if ($val != 'expire' && $val != 'remove') continue;
		// Unreplace any pipes | with dots (since dots would get removed when Posted)
		$key = str_replace("|", ".", $key);
		// Must begin with pas- and have valid project_id as second number
		list ($nothing, $this_pid, $this_user) = explode("-", $key, 3);
		if (!in_array($this_pid, $projects_user_rights_access)) continue;
		// Expire or Remove?
		if ($val == 'expire') {
			// EXPIRE
			$sql = "update redcap_user_rights set expiration = '".db_escape($yesterdayDate)."'
					where username = '".db_escape($this_user)."' and project_id = '".db_escape($this_pid)."'";
			if (db_query($sql) && db_affected_rows() > 0) {
				// Logging
				$log_event_id = Logging::logEvent($sql,"redcap_user_rights","update",$this_user,"user = '".db_escape($this_user)."'","Edit user");
				// Since Logging::logEvent() is not being called in a project context, it's project_id will be set to 0, so fix manually
				$sql = "update ".Logging::getLogEventTable($this_pid)." set project_id = '".db_escape($this_pid)."' where log_event_id = $log_event_id";
				db_query($sql);
				// Set user/project_id in logging array
				$expiredUsers[] = array('username'=>$this_user, 'project_id'=>$this_pid);
			}
		} else {
			// REMOVE FROM PROJECT
			$sql = "delete from redcap_user_rights where project_id = '".db_escape($this_pid)."' and username = '".db_escape($this_user)."'";
			if (db_query($sql)) {
				// Also delete from project bookmarks users table as well
				$sql2 = "delete from redcap_external_links_users where username = '".db_escape($this_user)."' and ext_id in
						(" . pre_query("select ext_id from redcap_external_links where project_id = '".db_escape($this_pid)."'") . ")";
				db_query($sql2);
				// Logging
				$log_event_id = Logging::logEvent($sql.";".$sql2,"redcap_user_rights","delete",$this_user,"user = '".db_escape($this_user)."'","Delete user");
				// Since Logging::logEvent() is not being called in a project context, it's project_id will be set to 0, so fix manually
				$sql = "update ".Logging::getLogEventTable($this_pid)." set project_id = '".db_escape($this_pid)."' where log_event_id = $log_event_id";
				db_query($sql);
				// Set user/project_id in logging array
				$deletedUsers[] = array('username'=>$this_user, 'project_id'=>$this_pid);
				// Remove from any linked conversations in Messenger.
				Messenger::removeUserFromLinkedProjectConversation($this_pid, $this_user);
			}
		}
	}

	// Log all individual actions as a single event with the details JSON encoded
	$expiredDeletedUsers = array('deleted'=>$deletedUsers, 'expired'=>$expiredUsers);
	Logging::logEvent('',"redcap_user_rights","MANAGE",'',json_encode($expiredDeletedUsers),"Delete/expire users via User Access Dashboard");

	## Display confirmation message that changes were saved
	// Title
	$h = $uad_title;
	// Recommendation text to expire any suspended users
	$countDeletedUsers = count($deletedUsers);
	$countExpiredUsers = count($expiredUsers);
	if (($countDeletedUsers+$countExpiredUsers) > 0) {
		$h .= 	RCView::div(array('class'=>'darkgreen', 'style'=>'width:600px;margin:30px 0;'),
					RCView::img(array('src'=>'tick.png')) . RCView::SP .
					RCView::b($lang['setup_57']) . RCView::br() . RCView::br() .
					($countDeletedUsers == 0 ? '' : "$countDeletedUsers " . $lang['rights_276'] . RCView::br()) .
					($countExpiredUsers == 0 ? '' : "$countExpiredUsers " . $lang['rights_277'])
				);
	} else {
		$h .= RCView::div(array('class'=>'red', 'style'=>'width:600px;margin:30px 0;'), "ERROR!");
	}
	// Button to return to UAD
	$h .= RCView::button(array('class'=>'jqbutton', 'style'=>'', 'onclick'=>"window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=user_access_dashboard';"),
			$lang['rights_278']
		  );
	// Add space
	$h .= RCView::div(array('class'=>'space', 'style'=>'height:50px;'), " ");
	// End page here
	exit($h);
}











// Set text to display the user's last time accessing this page
if ($user_access_dashboard_view == '') {
	$user_access_dashboard_action_text = $lang['rights_279'];
} else {
	$user_access_dashboard_days_ago = floor((strtotime(TODAY)-strtotime(substr($user_access_dashboard_view, 0, 10)))/86400);
	$user_access_dashboard_action_text = "{$lang['rights_241']} ".
			($user_access_dashboard_days_ago <= 1 ? ($user_access_dashboard_days_ago == 1 ? $lang['rights_257'] : $lang['rights_258']) : "$user_access_dashboard_days_ago ".$lang['rights_256']).$lang['period'];
}

// Set the user's user_access_dashboard_view timestamp to NOW when they view THIS page
User::setUserAccessDashboardViewTimestamp(USERID);



// Check query string for filter flags
$choose_status = (isset($_GET['status']) && in_array($_GET['status'], array('0','1','2','3'))) ? $_GET['status'] : '';
$choose_status_sql = ($choose_status == '') ? '' : "and p.status = $choose_status";
$choose_include_practice = (isset($_GET['include_practice']) && $_GET['include_practice'] == '0') ? '0' : '1';
$choose_include_practice_sql = ($choose_include_practice == '1') ? '' : "and p.purpose != '0'";


// Create array of all projects that the user has User Rights access to
$project_access = $project_api_tokens = array();
$sql = "select p.project_id, p.app_title, p.status, p.purpose, trim(u.username) as username, u.expiration,
		if(u.user_rights = 1 and r.user_rights is null, 1, if(r.user_rights = 1, 1, 0)) as user_rights2,
		u.api_token, trim(concat(i.user_firstname, ' ', i.user_lastname)) as name, i.user_email,
		if(i.user_suspended_time is null, 0, 1) as suspended
		from redcap_projects p, redcap_user_rights u left join redcap_user_information i
		on i.username = u.username left join redcap_user_roles r on r.role_id = u.role_id
		where p.project_id = u.project_id and u.username != '' and p.date_deleted is null
		and p.project_id in (".prep_implode($projects_user_rights_access).")
		$choose_status_sql $choose_include_practice_sql
		and (select count(*) from redcap_user_rights u2 where u2.project_id = u.project_id) > 1
		order by p.project_id, user_rights2 desc, u.username";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	// Add project title
	$project_access[$row['project_id']]['!TITLE!'] = strip_tags(br2nl($row['app_title']));
	$project_access[$row['project_id']]['!STATUS!'] = strip_tags(br2nl($row['status']));
	// Add user info
	$project_access[$row['project_id']][$row['username']] = array('status'=>$row['status'], 'expiration'=>$row['expiration'],
			'name'=>$row['name'], 'user_rights'=>$row['user_rights2'], 'user_email'=>$row['user_email'], 'suspended'=>$row['suspended']);
	// Add API token count to other array
	if (!isset($project_api_tokens[$row['project_id']])) {
		$project_api_tokens[$row['project_id']] = array();
	}
	if ($row['api_token'] != '') {
		$project_api_tokens[$row['project_id']][$row['username']] = true;
	}
}

// Set headers for table
$hdrs = array();
$hdrs[] = array('300', RCView::b($lang['global_17']));
$hdrs[] = array('30', RCView::div(array('class'=>'wrap'), $lang['control_center_333']), 'center');
$hdrs[] = array('90', RCView::div(array('class'=>'wrap'), $lang['rights_266'] .
			RCView::div(array('style'=>'color:#777;'), "(".DateTimeRC::get_user_format_label().")")), 'center');
$hdrs[] = array('110', RCView::div(array('class'=>'wrap'), $lang['rights_230']), 'center');
$hdrs[] = array('55', RCView::img(array('src'=>'cross.png')) . RCView::b($lang['global_19']), 'center');
$hdrs[] = array('55', RCView::img(array('src'=>'clock_frame.png')) . RCView::b($lang['rights_227']), 'center');
$hdrs[] = array('35', '', 'center');

// Set today's date as numeric in YMD format
$today_ymd_number = date('Ymd');

// Build table html (loop through each project)
$table = '';
$projects_involved = array_keys($project_access);
$countSuspendRecommendations = 0;
foreach ($project_access as $this_pid=>$users)
{
	// Reset $row_data for this project
	$row_data = array();
	// Get project title and status remove from array
	$title = $users['!TITLE!'];
	$this_status = $users['!STATUS!'];
	unset($users['!TITLE!'], $users['!STATUS!']);
	// Set icon/text for project status
	switch($this_status)
	{
		case 0: // Development
			$iconstatus = '<span class="fas fa-wrench" style="color:#444;" aria-hidden="true" title="' . js_escape2($lang['global_29']) . '"></span> '.$lang['global_29'];
			break;
		case 1: // Production
			$iconstatus = '<span class="far fa-check-square" style="color:#00A000;" aria-hidden="true" title="' . js_escape2($lang['global_30']) . '"></span> '.$lang['global_30'];
			break;
		case 2: // Inactive
			$iconstatus = '<span class="fas fa-minus-circle" style="color:#A00000;" aria-hidden="true" title="' . js_escape2($lang['global_159']) . '"></span> '.$lang['global_159'];
			break;
	}
	// Set table title
	$title = RCView::div(array('style'=>'font-size:13px;padding-top:3px;'),
				// RCView::img(array('src'=>'folder.png')) . RCView::SP .
				RCView::span(array('style'=>'font-size:12px;font-weight:normal;margin-right:5px;'), $lang['extres_24']) .
				RCView::a(array('href'=>APP_PATH_WEBROOT."UserRights/index.php?pid=$this_pid", 'target'=>'_blank', 'style'=>'font-size:14px;text-decoration:underline;'),
					$title
				)
			 ) .
			 RCView::div(array('style'=>'font-size:13px;font-weight:normal;'),
				$iconstatus .
				RCView::img(array('src'=>'group.png', 'style'=>'margin-left:20px;')) .
				count($users) . RCView::SP . $lang['control_center_192'] . RCView::span(array('style'=>'font-size:11px;color:#888;margin-left:5px;'), $lang['rights_239']) .
				RCView::img(array('src'=>'coins.png', 'style'=>'margin-left:20px;')) .
				count($project_api_tokens[$this_pid]) . RCView::SP . $lang['rights_229']
			 );
	// Set flag if the current user is the ONLY user on the project
	$onlyOneUser = (count($users) == 1 && isset($users[USERID]));
	// Loop through each user
	foreach ($users as $this_user=>$attr)
	{
		// Get user's name/email
		$name_email = ($attr['name'] == '') ? '' :
			"&nbsp; (" . RCView::a(array('href'=>"mailto:{$attr['user_email']}", 'style'=>'text-decoration:underline;font-size:11px;'),
				$attr['name']) .
			")";
		// If user has User Rights privileges in this project, then put asterisk after their username
		$userRightsAsterisk = ($attr['user_rights']) ? RCView::span(array('style'=>'font-size:14px;color:red;'), '*') : '';
		// Convert date format for expiration
		$expiration = DateTimeRC::format_ts_from_ymd($attr['expiration']);
		// If user's access has expired, then set as
		$expired = ($attr['expiration'] != '' && $today_ymd_number > 1*str_replace('-', '', $attr['expiration']));
		if ($expired) $expiration = RCView::span(array('style'=>'color:red;'), $expiration);
		if ($attr['expiration'] == '') $expiration = RCView::span(array('style'=>'color:#aaa;'), "&mdash;");
		// Hide "expire now" radio if user is already expired
		$visibilityExpireRadio = ($expired) ? "visibility:hidden;" : "visibility:visible;";
		// Add invisible expiration YMD time for sorting purposes
		$expiration = RCView::span(array('class'=>'hidden'), $attr['expiration']) . $expiration;
		// Set text if user's account is suspended
		$suspendedText = (!$attr['suspended']) ? '' : RCView::a(array('href'=>'javascript:;', 'onclick'=>"suspendExplainDialog();", 'style'=>'text-decoration:underline;font-size:11px;color:red;margin-left:10px;'), $lang['rights_281']);
		// If a user is suspend and NOT expired for a project, pre-select its expire radio
		$removeRadioChecked = '';
		if (!$expired && $attr['suspended']) {
			$removeRadioChecked = 'checked';
			$countSuspendRecommendations++;
		}
		// Set name of radio buttons (replace any dots in username with pipe, then unreplace on Post processing)
		$radio_name = str_replace(".", "|", "pas-$this_pid-$this_user");
		// API token text (if has token)
		$apiText = (!isset($project_api_tokens[$this_pid][$this_user])) ? '' : RCView::img(array('src'=>'coin.png'));
		// Store row for this user
		$row_data[] = array(
			RCView::span(array('style'=>'color:#C00000;'), RCView::escape(strtolower($this_user)) . $userRightsAsterisk) . $name_email . $suspendedText .
				(!$onlyOneUser ? '' : RCView::div(array('style'=>'color:#777;'), $lang['rights_268'])),
			$apiText,
			$expiration,
			RCView::div(array('id'=>"pas-ts-$this_pid-".strtolower($this_user), 'style'=>'height:16px;'),
				RCView::span(array('class'=>"pid-cnt pid-cntp-$this_pid"),
					RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-size:11px;', 'onclick'=>"getProjectUserLastTimeAccesssed($this_pid,'".RCView::escape(strtolower($this_user))."');"), $lang['rights_280'])
				)
			),
			RCView::radio(array('name'=>$radio_name, 'value'=>'remove', 'style'=>'vertical-align:middle;', 'onclick'=>'setRadioBgColor(this);')),
			RCView::radio(array('name'=>$radio_name, 'value'=>'expire', 'style'=>'vertical-align:middle;'.$visibilityExpireRadio,
					$removeRadioChecked=>$removeRadioChecked, 'onclick'=>'setRadioBgColor(this);')),
			RCView::a(array('href'=>'javascript:;', 'class'=>'reset', 'style'=>'font-size:11px;', 'onclick'=>
				"$('form[name=\"pas_form\"] input[name=\"$radio_name\"]').prop('checked',false);setRadioBgColor(this);"), $lang['form_renderer_20'])
		);
	}
	// Add html to $table
	$table .= renderGrid("proj_access_table-$this_pid", $title, 760, 'auto', $hdrs, $row_data, true, true, false) . RCView::br();
	// Remove project from the array to free up memory as we go
	unset($project_access[$this_pid]);
}





// Title
$h = $uad_title;
// Instructions
$h .= RCView::p(array('style'=>'margin:20px 0;'),
			$lang['rights_228'] . " " .
			RCView::span(array('style'=>'color:#C00000;'), $lang['rights_320'])
		);
// Text when user last accessed this page
$h .= RCView::div(array('style'=>'width:675px;padding:3px 10px 6px 10px;color:#666;border:1px solid #e5e5e5;background-color:#f7f7f7;'),
			RCView::img(array('src'=>'clock_frame.png')) . RCView::SP .
			$user_access_dashboard_action_text
		);
// Recommendation text to expire any suspended users
if ($countSuspendRecommendations > 0) {
	$h .= RCView::div(array('class'=>'red', 'style'=>'width:675px;padding:3px 10px 6px 10px;margin:10px 0 0;'),
				RCView::img(array('src'=>'exclamation.png')) . RCView::SP .
				RCView::b("{$lang['rights_263']} $countSuspendRecommendations {$lang['rights_264']} ") . $lang['rights_265']
			);
}
// Filter drop-downs
$h .= RCView::div(array('style'=>'margin:20px 0;'),
			RCView::b("Displaying:") .
			RCView::select(array('id'=>'choose_status', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 5px 0 5px;'),
				array(''=>$lang['rights_231'], '0'=>$lang['rights_233'], '1'=>$lang['rights_232'], '2'=>$lang['rights_359'], '3'=>$lang['rights_235']),
				$choose_status, 500) .
			RCView::select(array('id'=>'choose_include_practice', 'class'=>'x-form-text x-form-field', 'style'=>''),
				array('1'=>$lang['rights_236'], '0'=>$lang['rights_237']), $choose_include_practice, 500) .
			RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin-left:5px;', 'onclick'=>
				"showProgress(1);window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=user_access_dashboard&status='+$('#choose_status').val()+'&include_practice='+$('#choose_include_practice').val();"),
				$lang['rights_238']
			)
		);
// Display table
$h .= RCView::form(array('method'=>'post', 'name'=>'pas_form', 'action'=>$_SERVER['REQUEST_URI'], 'enctype'=>'multipart/form-data'),
			($table != ''
				? 	$table .
					RCView::div(array('style'=>'text-align:center;margin:20px 0;'),
						// Save button
						RCView::button(array('class'=>'jqbutton', 'style'=>'font-weight:bold;', 'onclick'=>'saveUADconfirm(); return false;'), $lang['pub_085']) .
						// Clear button
						RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;margin-left:10px;', 'onclick'=>
								"$('form[name=\"pas_form\"] input[type=\"radio\"]').prop('checked',false);
								 $('form[name=\"pas_form\"] .uad_bgred').removeClass('uad_bgred').addClass('uad_bggreen');"), $lang['setup_53'])
					)
				:
				// No table to display
				RCView::div(array('class'=>'darkgreen', 'style'=>'margin:20px 0;'),
					RCView::b($lang['global_03'].$lang['colon'])." ".$lang['rights_240']
				)
			)
		);
// Put all html in padded div
$h = RCView::div(array('style'=>'margin-left:20px;'), $h);
// Output all HTML
print $h;
// Javascript
?>
<script type="text/javascript">
var lang_never = '<?php echo js_escape($lang['index_37']) ?>';
// Display a dialog of selected users to delete/expire, and then prompt to save selections
function saveUADconfirm() {
	// Get total number of users to delete
	var usersDelete = $('form[name="pas_form"] input[type="radio"][value="remove"]:checked').length;
	// Get total number of users to expire
	var usersExpire = $('form[name="pas_form"] input[type="radio"][value="expire"]:checked').length;
	// Error msg if none selected
	if ((usersDelete+usersExpire) == 0) {
		simpleDialog('<?php echo js_escape($lang['rights_271']) ?>');
		return;
	}
	// Dialog confirming save
	simpleDialog('<?php echo js_escape($lang['rights_273']."<br><br>") ?>'+
				(usersDelete==0 ? '' : ' &bull; <b style="color:#C00000;">'+usersDelete+' <?php echo js_escape($lang['rights_274']."<br>") ?></b>')+
				(usersExpire==0 ? '' : ' &bull; <b style="color:#C00000;">'+usersExpire+' <?php echo js_escape($lang['rights_275']) ?></b>')
				, '<?php echo js_escape($lang['rights_272']) ?>',null,500,null,'<?php echo js_escape($lang['global_53']) ?>'
				, function(){ $('form[name="pas_form"]').submit(); }, '<?php echo js_escape($lang['report_builder_28']) ?>');

}
// Set bg color for remove/expire radio buttons
function setRadioBgColor(ob) {
	// First remove any existing highlighting in row
	$(ob).parents('tr:first').find('.uad_bgred').removeClass('uad_bgred').addClass('uad_bggreen');
	// Get this cell
	var td = $(ob).parents('div:first');
	// Check if radio in this cell is selected
	if (td.find('input[type="radio"]:checked').length) {
		td.removeClass('uad_bggreen').addClass('uad_bgred');
	}
}
// Ajax call to load user timestamps for each project
function getProjectUserLastTimeAccesssed(pid,username) {
	// Replace link with spinning progress icon
	$('#pas-ts-'+pid+'-'+(username.replace(/\./g,'\\.').replace(/@/g,'\\@'))+' .pid-cnt').html('<img src="'+app_path_images+'progress_circle.gif">');
	// Ajax call
	$.post(app_path_webroot+'Home/user_access_dashboard_ajax.php', { pid: pid, username: username }, function(data) {
		var json_data = jQuery.parseJSON(data);
		// Loop through each user
		var i=0, tsselector, this_user;
		for (var this_ts in json_data.timestamps) {
			this_user = json_data.timestamps[i]['user'];
			// Escape periods and ampersands for user ts selector
			tsselector = '#proj_access_table-'+pid+' #pas-ts-'+pid+'-'+this_user.replace(/\./g,'\\.').replace(/@/g,'\\@');
			// Replace text
			$(tsselector).html( "<span class='hidden'>"+json_data.timestamps[i]['time_ymd']+"</span>"+json_data.timestamps[i]['time'] );
			i++;
		}
	});
}
// Display dialog explaining "user suspension"
function suspendExplainDialog(ob) {
	simpleDialog('<?php echo js_escape($lang['rights_269']) ?>', '<?php echo js_escape($lang['rights_270']) ?>');
}
$(function(){
	$('form[name="pas_form"] input[type="radio"]').parent().addClass('uad_bggreen');
	// Trigger click on any pre-selected radios
	$('form[name="pas_form"] input[type="radio"]:checked').trigger('click');
	// Hide certain elements when printing age
	$('div#sub-nav, #footer, .report_btn').addClass('d-print-none');
});
</script>
<?php
