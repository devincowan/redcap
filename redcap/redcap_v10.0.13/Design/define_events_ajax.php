<?php


//Pick up any variables passed by Post
if (isset($_POST['pnid'])) $_GET['pnid'] = $_POST['pnid'];
if (isset($_POST['pid']))  $_GET['pid']  = $_POST['pid'];
if (isset($_POST['arm']))  $_GET['arm']  = $_POST['arm'];


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$arm = getArm();

// If action is provided in AJAX request, perform action.
if (isset($_REQUEST['action']))
{
	switch ($_REQUEST['action'])
	{
		// Remove this visit into table (mark as "deleted" but don't really delete because of dependency issues elsewhere)
		case "delete":
			// Logging
			Logging::logEvent("","redcap_events_metadata","MANAGE",$_GET['event_id'],Event::eventLogChange($_GET['event_id']),"Delete event");
			// Delete after logging so log values can be captured
			db_query("delete from redcap_events_forms where event_id = ".checkNull($_GET['event_id']));
			db_query("delete from redcap_events_metadata where event_id = ".checkNull($_GET['event_id']));
			$Proj->loadMetadata();
			$Proj->loadEvents();
			$Proj->loadEventsForms();
			break;
		//Add new Arm OR edit existing Arm
		case "add_arm":
			//If we are renaming Arm, then do update
			if (isset($_GET['old_arm']) && is_numeric($_GET['old_arm'])) {
				$sql = "update redcap_events_arms set arm_num = $arm, arm_name = '".db_escape($_GET['arm_name'])."' where project_id = $project_id and arm_num = {$_GET['old_arm']}";
				$log_descrip = "Edit arm name/number";
			//Add arm
			} else {
				$sql = "insert into redcap_events_arms (project_id, arm_num, arm_name) values ($project_id, $arm, '".db_escape($_GET['arm_name'])."')";
				$log_descrip = "Create arm";
			}
			if (db_query($sql)) {
				// Logging
				Logging::logEvent($sql,"redcap_events_arms","MANAGE",$arm,"Arm $arm: {$_GET['arm_name']}",$log_descrip);
			} elseif (db_errno() == 1062) {
				//Give warning message if arm number already exists
				print "<div class='red'><b>{$lang['global_01']}:</b> {$lang['define_events_68']}</div>";
			}
			break;
		//Delete Arm and mark any Events associated with that Arm as "removed"
		case "delete_arm":
			// Logging
			$armText = db_result(db_query("select concat(arm_num,': ',arm_name) from redcap_events_arms where project_id = $project_id and arm_num = {$_GET['arm']}"), 0);
			Logging::logEvent("","redcap_events_arms","MANAGE",$_GET['arm'],"Arm $armText","Delete arm");
			// Do deletion after logging so log values may be captured before deletion
			db_query("delete from redcap_events_forms where event_id in (select m.event_id from redcap_events_arms a, redcap_events_metadata m where a.project_id = $project_id and a.arm_num = {$_GET['arm']} and a.arm_id = m.arm_id)");
			db_query("delete from redcap_events_metadata where arm_id = (select arm_id from redcap_events_arms where project_id = $project_id and arm_num = {$_GET['arm']})");
			db_query("delete from redcap_events_arms where project_id = $project_id and arm_num = {$_GET['arm']}");
			//Get smallest arm number to reset page
			$arm = db_result(db_query("select min(arm_num) from redcap_events_arms where project_id = $project_id"), 0);
			if ($arm == "") $arm = 1;
			// If user has somehow deleted ALL events, then add one default event automatically (otherwise things may get screwy)
			$sql = "select 1 from redcap_events_arms a, redcap_events_metadata e where a.project_id = $project_id
					and a.arm_id = e.arm_id";
			$q = db_query($sql);
			if (db_num_rows($q) < 1) {
				$sql = "select arm_num from redcap_events_arms where project_id = $project_id order by arm_num limit 1";
				$arm = db_result(db_query($sql), 0);
				$sql = "insert into redcap_events_metadata (arm_id) select arm_id from redcap_events_arms where project_id = $project_id
						and arm_num = $arm limit 1";
				db_query($sql);
			}
			Records::resetRecordCountAndListCache($project_id);
			$Proj->loadMetadata();
			$Proj->loadEvents();
			$Proj->loadEventsForms();
			break;
		// Add new event to table
		case "add":
			if (is_numeric($_GET['day_offset']) && is_numeric($_GET['offset_min']) && is_numeric($_GET['offset_max'])) {
				// Get arm_id from this arm num
				$sql = "select arm_id from redcap_events_arms where project_id = $project_id and arm_num = $arm";
				$arm_id = db_result(db_query($sql), 0);
				//Add this event to table
				$sql = "insert into redcap_events_metadata (arm_id, day_offset, descrip, offset_min, offset_max, custom_event_label)
						values ($arm_id, '".db_escape($_GET['day_offset'])."', '".db_escape(label_decode($_GET['descrip']))."',
						'".db_escape($_GET['offset_min'])."', '".db_escape($_GET['offset_max'])."', ".checkNull(label_decode($_GET['custom_event_label'])).")";
				db_query($sql);
				//Add new event_id as hidden field on page (in order to highlight its table row for emphasis)
				$new_event_id = db_insert_id();
				// If not using Scheduling, then reset day offset values for all events
				if (!$scheduling) {
					db_query("set @count=0");
					$sql = "update redcap_events_metadata set day_offset = (@count:=@count+1)
							where arm_id = $arm_id order by day_offset, descrip";
					db_query($sql);

				}
				// CHECK IF FIRST EVENT CHANGED. IF SO, GIVING WARNING ABOUT THE PUBLIC SURVEY LINK CHANGING
				Design::checkFirstEventChange($arm);
				// Logging
				Logging::logEvent($sql,"redcap_events_metadata","MANAGE",$new_event_id,Event::eventLogChange($new_event_id),"Create event");
				// Hidden value
				print "<input type='hidden' id='new_event_id' value='$new_event_id'>";
			} else {
				//Give warning message if error exists
				print "<div class='red'><b>{$lang['global_01']}:</b> {$lang['define_events_35']}</div>";
			}
			// Reload project events so that new unique event names are reflected
			$Proj->loadEvents();
			$Proj->loadEventsForms();
			break;
		// Edit single event
		case "edit":
			if (!$scheduling || ($scheduling && is_numeric($_POST['day_offset']) && is_numeric($_POST['offset_min']) && is_numeric($_POST['offset_max']))) {
				// Update the event
				if ($scheduling) {
					$sql = "update redcap_events_metadata set descrip = '".db_escape(label_decode($_POST['descrip']))."',
							day_offset = '".db_escape($_POST['day_offset'])."',
							offset_min = '".db_escape($_POST['offset_min'])."', offset_max = '".db_escape($_POST['offset_max'])."', 
							custom_event_label = ".checkNull(label_decode($_POST['custom_event_label']))."
							where event_id = '".db_escape($_POST['event_id'])."'";
				} else {
					$sql = "update redcap_events_metadata set descrip = '".db_escape(label_decode($_POST['descrip']))."', 
							custom_event_label = ".checkNull(label_decode($_POST['custom_event_label']))."
							where event_id = '".db_escape($_POST['event_id'])."'";
				}
				db_query($sql);
				// CHECK IF FIRST EVENT CHANGED. IF SO, GIVING WARNING ABOUT THE PUBLIC SURVEY LINK CHANGING
				Design::checkFirstEventChange($arm);
				// Logging
				Logging::logEvent($sql,"redcap_events_metadata","MANAGE",$_POST['event_id'],Event::eventLogChange($_POST['event_id']),"Edit event");
			} else {
				//Give warning message if error exists
				print "<div class='red'><b>{$lang['global_01']}:</b> {$lang['define_events_35']}</div>";
			}
			// Reload project events so that new unique event names are reflected
			$Proj->loadEvents();
			$Proj->loadEventsForms();
			break;
		// Reorder all events in an arm
		case "reorder_events":
			if (!$scheduling && isset($_POST['event_ids'])) {
				// Update the event
				$day_offset = 1;
				foreach (explode(",", $_POST['event_ids']) as $this_event_id) {
					if (!isset($Proj->eventInfo[$this_event_id])) continue;
					$sql = "update redcap_events_metadata set day_offset = ".$day_offset++."
							where event_id = '".db_escape($this_event_id)."'";
					db_query($sql);
				}
				// CHECK IF FIRST EVENT CHANGED. IF SO, GIVING WARNING ABOUT THE PUBLIC SURVEY LINK CHANGING
				Design::checkFirstEventChange($arm);
				// Logging
				Logging::logEvent($sql,"redcap_events_metadata","MANAGE","",Event::eventLogChange($_POST['event_ids']),"Reorder events");
			}
			// Reload project events so that new unique event names are reflected
			$Proj->loadEvents();
			$Proj->loadEventsForms();
			break;
	}
}





// If in production, give big warning that deleting an Event will delete data
if (UserRights::isSuperUserNotImpersonator() && $status > 0) {
	?>
	<div class="red" style="margin:10px 0;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['global_48'] . $lang['colon'] ?></b><br>
		<?php echo $lang['define_events_37'] ?>
	</div>
	<?php
}


// Check if any events are used by DTS. If so, give warning message.
$eventIdsDts = ($dts_enabled_global && $dts_enabled) ? Event::getDtsEvents() : array();
if (!empty($eventIdsDts)) {
	?>
	<div class="red" style="margin:10px 0;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['define_events_64'] ?></b><br>
		<?php echo $lang['define_events_63'] ?>
	</div>
	<?php
}



/***************************************************************
** ARM TABS
***************************************************************/
//Display Arm number tab
$max_arm = 0;
//Set default
$arm_exists = false;

$csrf_token = System::getCsrfToken();



// Import/export buttons
print 	RCView::div(array('style'=>'text-align:right;'),
			RCView::button(array('onclick'=>"showBtnDropdownList(this,event,'downloadUploadEventsArmsDropdownDiv');", 'class'=>'jqbuttonmed'),
				RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
				RCView::span(array('style'=>'vertical-align:middle;'), $lang['define_events_73']) .
				RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
			) .
			// Button/drop-down options (initially hidden)
			RCView::div(array('id'=>'downloadUploadEventsArmsDropdownDiv', 'style'=>'text-align:left;display:none;position:absolute;z-index:1000;'),
				RCView::ul(array('id'=>'downloadUploadEventsArmsDropdown'),
					// Only show upload if development or if prod+users can edit events/arms
					(!(UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events)) ? '' :
						RCView::li(array(),
							RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importArmsDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importArmsForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importArmsDialog').parent()).css('font-weight','bold');"),
								RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
								RCView::SP . $lang['define_events_74']
							)
						)
					) .
					RCView::li(array(),
						RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"window.location.href = app_path_webroot+'Design/arm_download.php?pid='+pid;"),
							RCView::img(array('src'=>'arrow_down_sm_orange.gif')) .
							RCView::SP . $lang['define_events_75']
						)
					) .
					// Only show upload if development or if prod+users can edit events/arms
					(!(UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events)) ? '' :
						RCView::li(array(),
							RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"simpleDialog(null,null,'importEventsDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importEventsForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importEventsDialog').parent()).css('font-weight','bold');"),
								RCView::img(array('src'=>'arrow_up_sm.gif')) .
								RCView::SP . $lang['define_events_76']
							)
						)
					) .
					RCView::li(array(),
						RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"window.location.href = app_path_webroot+'Design/event_download.php?pid='+pid;"),
							RCView::img(array('src'=>'arrow_down_sm.png')) .
							RCView::SP . $lang['define_events_77']
						)
					)
				)
			)
		);

// Hidden import dialog divs
print 	RCView::div(array('id'=>'importArmsDialog', 'class'=>'simpleDialog', 'title'=>$lang['define_events_74']),
			RCView::div(array(), $lang['api_91']) .
			RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['api_86']) .
			RCView::form(array('id'=>'importArmsForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/arm_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::input(array('type'=>'file', 'name'=>'file'))
			)
		);
print 	RCView::div(array('id'=>'importArmsDialog2', 'class'=>'simpleDialog', 'title'=>$lang['define_events_74']." - ".$lang['design_654']),
			RCView::div(array(), $lang['api_125']) .
			RCView::div(array('id'=>'arm_preview', 'style'=>'margin:15px 0'), '') .
			RCView::form(array('id'=>'importArmsForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/arm_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES))
			)
		);
print 	RCView::div(array('id'=>'importEventsDialog', 'class'=>'simpleDialog', 'title'=>$lang['define_events_76']),
			RCView::div(array(), $lang['api_92']) .
			RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['api_87']) .
			RCView::form(array('id'=>'importEventsForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/event_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::input(array('type'=>'file', 'name'=>'file'))
			)
		);
print 	RCView::div(array('id'=>'importEventsDialog2', 'class'=>'simpleDialog', 'title'=>$lang['define_events_76']." - ".$lang['design_654']),
			RCView::div(array(), $lang['api_125']) .
			RCView::div(array('id'=>'event_preview', 'style'=>'margin:15px 0'), '') .
			RCView::form(array('id'=>'importEventsForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/event_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES))
			)
		);

print '<div id="sub-nav" style="margin-bottom:16px;max-width:750px;"><ul>';
//Loop through each ARM and display as a tab
$q = db_query("select arm_id, arm_num, arm_name from redcap_events_arms where project_id = $project_id order by arm_num");
$arm_count = db_num_rows($q);
while ($row = db_fetch_assoc($q)) {
	//Get max arm value
	if ($row['arm_num'] > $max_arm) $max_arm = $row['arm_num'];
	//Render tab
	print  '<li';
	//If this tab is the current arm, make it selected
	if ($row['arm_num'] == $arm) {
		print  ' class="active"';
		$arm_exists = true;
		//Get current Arm ID
		$arm_id = $row['arm_id'];
	}
	print '><a style="font-size:12px;color:#393733;padding:5px 5px 5px 11px;" href="'.APP_PATH_WEBROOT.'Design/define_events.php?pid='.$project_id.'&arm='.$row['arm_num'].'"'
		. '>'.$lang['global_08'].' '.$row['arm_num'].$lang['colon']
		. RCView::span(array('style'=>'margin-left:6px;font-weight:normal;color:#800000;'), RCView::escape(strip_tags($row['arm_name']))).'</a></li>';
}
## ADD NEW ARM Tab
$max_arm++;
// Tab
if (UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events))
{
	print  '<li' . (!$arm_exists ? ' class="active"' : '') . '>
				<a style="font-size:12px;color:#393733;padding:5px 5px 5px 11px;font-weight:normal;"
					href="'.APP_PATH_WEBROOT.'Design/define_events.php?pid='.$project_id.'&arm='.$max_arm.'">+'.$lang['define_events_38'].'</a>
			</li>';
}
print  '</ul></div><br><br><br>';



/***************************************************************
** ARM NAME
***************************************************************/

print  "<div style='clear:both;margin-left:20px;width:800px;max-width:100%;'>
			<div style='float:left;padding-top:5px;margin-bottom:16px;'>
			{$lang['define_events_39']} &nbsp;";

//If Arm name has not been set, make user set it
if (!isset($arm_id) || $arm_id == "" || isset($_GET['action']) && $_GET['action'] == "rename_arm")
{
	//Replace escaped strings
	print  "<input type='text' size='25' maxlength='50' class='x-form-text x-form-field' id='arm_name' value='" . htmlspecialchars(label_decode($_GET['arm_name']), ENT_QUOTES) . "'> &nbsp;
			&nbsp; &nbsp; {$lang['define_events_40']} &nbsp;<input type='text' class='x-form-text x-form-field' style='width:30px;' maxlength='2' id='arm_num' value='$arm' onblur=\"redcap_validate(this,'1','99','soft_typed','int')\"> &nbsp;
			<br><br>
			<input type='button' value='".js_escape($lang['designate_forms_13'])."' style='font-size:11px;' id='savebtn' onclick=\"
				if (document.getElementById('arm_name').value.length > 0 && document.getElementById('arm_num').value.length > 0) {
					this.disabled = true;
					document.getElementById('progress').style.visibility = 'visible';
					document.getElementById('arm_name').disabled = true;
					document.getElementById('arm_num').disabled = true;
					document.getElementById('cancelbtn').disabled = true;
					$.get(app_path_webroot+'Design/define_events_ajax.php', { pid: pid, arm: document.getElementById('arm_num').value,
						action: 'add_arm', arm_name: document.getElementById('arm_name').value".
						($_GET['action'] == "rename_arm" ? ", old_arm: $arm" : "")."
						},function(data){ $('#table').html(data); initDefineEvents(); });
				} else {
					simpleDialog('".js_escape($lang['define_events_41'])."');
				}
			\"> &nbsp;
			<input type='button' value='".js_escape($lang['global_53'])."' style='font-size:11px;' id='cancelbtn' onclick=\"
				this.disabled = true;
				document.getElementById('progress').style.visibility = 'visible';
				document.getElementById('arm_name').disabled = true;
				document.getElementById('arm_num').disabled = true;
				document.getElementById('savebtn').disabled = true;
				$.get(app_path_webroot+'Design/define_events_ajax.php', { pid: pid },function(data){ $('#table').html(data);initDefineEvents(); });
			\">";
	//Progess icon that only appears when running an AJAX request
	print  "<span id='progress' style='padding-left:10px;visibility:hidden;'>
			<img src='".APP_PATH_IMAGES."progress_circle.gif'>
			</span>";
	print  "</div>
		</div>";
	print  "<div class='space' style='margin:50px 0;padding:50px 0;'>&nbsp;</div>";



//Arm name has been set already
} else {

	$sql = "select arm_name from redcap_events_arms where arm_id = $arm_id";
	$arm_name = db_result(db_query($sql), 0);
	print  "<b style='color:#800000;font-size:13px;'>".RCView::escape(strip_tags($arm_name))."</b></div>";

	if (UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events))
	{
		// Rename arm
		print  "<div style='float:right;padding-right:6px;color:#888;'>
				<a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"
					$.get(app_path_webroot+'Design/define_events_ajax.php?arm_name=".rawurlencode(label_decode($arm_name))."', { pid: pid, arm: $arm, action: 'rename_arm' },function(data){ $('#table').html(data);initDefineEvents(); });
				\">{$lang['define_events_42']} $arm</a>";
		// Delete arm (if more than one arm exists)
		if ($arm_count > 1 && (UserRights::isSuperUserNotImpersonator() || $status < 1)) {
			print  "&nbsp;|&nbsp;
					<a href='javascript:;' style='text-decoration:underline;font-size:11px;color:#800000;' onclick=\"
						if (confirm('".js_escape("{$lang['define_events_43']} $arm?\\n\\n{$lang['define_events_44']} $arm {$lang['define_events_45']} $arm{$lang['period']} {$lang['define_events_46']}")."')) {
							$.get(app_path_webroot+'Design/define_events_ajax.php', { pid: pid, arm: $arm, action: 'delete_arm' },function(data){ $('#table').html(data);initDefineEvents(); });
						}
					\">{$lang['define_events_47']} $arm</a>";
		}
		print  "</div>";
	}
	print  "<br><br><br>";


	/***************************************************************
	** EVENT TABLE
	***************************************************************/

	// Get list of all unique event names
	$uniqueEventNames = $Proj->getUniqueEventNames();

	//Get number of ALL events for ALL arms
	$num_events_total = count($Proj->eventInfo);

	//Determine if any visits have been defined yet
	$q = db_query("select * from redcap_events_metadata where arm_id = $arm_id");
	$num_events = db_num_rows($q);

	//Render table headers
	print  "<table class='form_border' id='event_table' style='clear:both;width:100%;'>
			<tr class='nodrop'>" .
				($scheduling ? "" : "<td id='reorderTrigger' class='labelrc' style='background-color:#eee;width:20px;'></td>") . "
				<td class='labelrc' style='background-color:#eee;width:50px;'></td>
				<td class='labelrc' style='text-align:center;background-color:#eee;width:60px;'>{$lang['define_events_48']}</td>" .
				(!$scheduling ? "" :
					"<td class='labelrc' style='text-align:center;background-color:#eee;'>{$lang['define_events_49']}</td>
					<td class='labelrc' style='text-align:center;background-color:#eee;font-size:10px;'>
						{$lang['define_events_50']}
						<div style='font-weight:normal;'>{$lang['define_events_51']}</div>
					</td>"
				) . "
				<td class='labelrc' style='text-align:center;background-color:#eee;'>{$lang['global_10']}</td>
				<td class='labelrc' style='text-align:center;background-color:#eee;font-size:11px;'>
					{$lang['design_671']}
					<a href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['design_672'])."', '".js_escape($lang['design_671'])."');\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a>
					<div style='font-weight:normal;'>{$lang['survey_251']}</div>
				</td>
				<td class='labelrc' style='font-weight:normal;text-align:center;background-color:#eee;font-size:10px;'>
					{$lang['define_events_65']}
					<a href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['define_events_67'])."', '".js_escape($lang['define_events_65'])."');\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a><br>
					{$lang['define_events_66']}
				</td>
			</tr>";


	//No visits are defined yet
	if ($num_events < 1) {

		print   "<tr>
					<td class='data' colspan='5' style='padding:10px;color:#800000;font-weight:bold;'>{$lang['define_events_53']}</td>
				</tr>";

	//Visits have been defined, so display them
	} else {

		//Loop through all visits and render
		$i = 1;
		while ($row = db_fetch_assoc($q))
		{
			//Collect event description to render as labels in grid at bottom
			$event_descrip[$row['event_id']] = $row['descrip'];
			//Render editable row if user clicked pencil for this visit
			if (isset($_GET['edit']) && $_GET['event_id'] == $row['event_id']) {
				print  "<tr id='design_{$row['event_id']}'>";
				if (!$scheduling) {
					print  "<td class='data ".((UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events)) ? 'dragHandle' : '')."' style='text-align:center;width:20px;'></td>";
				}
				print "
					<td class='data' style='text-align:center;'>
						<input type='button' id='editbutton' value='".js_escape($lang['designate_forms_13'])."' style='font-size:11px;' onclick='editVisit($arm,{$row['event_id']})'>
					</td>
					<td class='data' style='text-align:center;color:#777;'>$i</td>" .
					(!$scheduling ? "" :
						"<td class='data' style='text-align:center;'>
							<input type='text' value='{$row['day_offset']}' id='day_offset_edit'
								onkeydown='if(event.keyCode==13){editVisit($arm,{$row['event_id']});}' style='width:35px;' maxlength='5'
								onblur='redcap_validate(this,\"-9999\",\"9999\",\"soft_typed\",\"int\")'>
						</td>
						<td class='data' style='text-align:center;'>
							-<input type='text' value='{$row['offset_min']}' id='offset_min_edit'
								onkeydown='if(event.keyCode==13){editVisit($arm,{$row['event_id']});}' style='width:20px;' maxlength='5'
								onblur='redcap_validate(this,\"0\",\"9999\",\"soft_typed\",\"int\")'>
							+<input type='text' value='{$row['offset_max']}' id='offset_max_edit'
								onkeydown='if(event.keyCode==13){editVisit($arm,{$row['event_id']});}' style='width:20px;' maxlength='5'
								onblur='redcap_validate(this,\"0\",\"9999\",\"soft_typed\",\"int\")'>
						</td>"
					) . "
					<td class='evt_name data' style='padding:1px 5px;'>
						<input type='text' value='".str_replace("'","&#039;",$row['descrip'])."' class='x-form-text x-form-field'
							onkeydown='if(event.keyCode==13){editVisit($arm,{$row['event_id']});}' id='descrip_edit' size='30' maxlength='30'>
					</td>
					<td class='evt_name data' style='padding:1px 5px;'>
						<input type='text' value='".str_replace("'","&#039;",$row['custom_event_label'])."' class='x-form-text x-form-field'
							onkeydown='if(event.keyCode==13){editVisit($arm,{$row['event_id']});}' id='custom_event_label_edit' style='width:98%;'>
					</td>
					<td class='data' style='padding:0 5px;'>
					</td>
				</tr>";
			}
			//Render normal row
			else
			{
				print  "<tr id='design_{$row['event_id']}'>";
				if (!$scheduling) {
					print  "<td class='data ".((UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events)) ? 'dragHandle' : '')."' style='text-align:center;width:20px;'></td>";
				}
				print  "<td id='row_a{$row['event_id']}' class='data' style='text-align:center;'>";
				if (UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events))
				{
					print  "	<a href='javascript:;' onclick='beginEdit(\"$arm\",\"{$row['event_id']}\")'><img src='".APP_PATH_IMAGES."pencil.png' title='{$lang['global_27']}' alt='{$lang['global_27']}'></a> ";
					//Don't allow user to delete ALL events (one event MUST exist)
					if ($num_events_total != 1 && (UserRights::isSuperUserNotImpersonator() || $status < 1)) {
						print  "&nbsp;<a href='javascript:;' onclick=\"delVisit('$arm','{$row['event_id']}',$num_events_total);\"><img src='".APP_PATH_IMAGES."cross.png' title='{$lang['global_19']}' alt='{$lang['global_19']}'></a>";
					}
				} else {
					print "		<img src='".APP_PATH_IMAGES."spacer.gif' style='height:19px;'>";
				}
				print " </td>
						<td class='data' style='text-align:center;color:#777;'>$i</td>" .
						(!$scheduling ? "" :
							"<td class='data' style='text-align:center;'>{$row['day_offset']}</td>
							<td class='data' style='text-align:center;'>-{$row['offset_min']}/+{$row['offset_max']}</td>"
						) . "
						<td class='evt_name data notranslate' style='padding:0px 10px 0px 10px;'>".RCView::escape(strip_tags($row['descrip']));
				if (isset($eventIdsDts[$row['event_id']])) {
					// Give warning label if used by DTS
					print "&nbsp; <span class='dtswarn'>{$lang['define_events_62']}</span>";
				}
				print  "</td>
						<td class='data' style='padding:2px 10px;font-size:11px;color:#333;'>
							".filter_tags($row['custom_event_label'])."
						</td>
						<td class='data' style='font-size:10px;color:#777;padding:0px 10px 0px 10px;'>{$uniqueEventNames[$row['event_id']]}</td>
					</tr>";
			}
			$i++;
		}

	}

	//Last row for adding a new time-point/visit
	if (UserRights::isSuperUserNotImpersonator() || $status < 1 || ($status > 0 && $enable_edit_prod_events))
	{
		print  "<tr class='nodrop'>" .
					($scheduling ? "" : "<td class='data' style='background-color:#eee;width:20px;'></td>") . "
					<td class='data' valign='top' colspan='2' style='text-align:center;background:#eee;padding:15px 10px 0px 0px;'>
						<input id='addbutton' type='button' value='".js_escape($lang['define_events_16'])."' onclick=\"addEvents($arm,$num_events_total);\">
					</td>" .
					(!$scheduling ? "" :
						"<td class='data' valign='top' style='text-align:center;background:#eee;width:80px;padding-top:15px;'>
							<div class='nowrap'>
								<input type='text' tabindex=1 class='x-form-text x-form-field' id='day_offset' maxlength='5' style='width:35px;'
									onkeydown='if(event.keyCode==13){addEvents($arm,$num_events_total);}'
									onblur='redcap_validate(this,\"-9999\",\"9999\",\"hard\",\"int\")'>
								<span style='color:#444;'>{$lang['define_events_56']}</span>
							</div>
							<div style='padding:7px 0 3px 0;line-height:10px;'>
								<a href='javascript:;' id='convert_link' style='position:relative;font-family:tahoma;font-size:10px;text-decoration:underline;'
									onclick='openConvertPopup()'>{$lang['define_events_57']}</a>
							</div>
						</td>
						<td class='data' valign='top' style='text-align:center;background:#eee;padding-top:15px;'>
							<div class='nowrap' style='vertical-align:middle;'>
								-<input type='text' tabindex=2 class='x-form-text x-form-field' id='offset_min' maxlength='5' style='width:20px;'
									onkeydown='if(event.keyCode==13){addEvents($arm,$num_events_total);}'
									onblur='redcap_validate(this,\"-9999\",\"9999\",\"hard\",\"int\")' value='0'>
								+<input type='text' tabindex=3 class='x-form-text x-form-field' id='offset_max' maxlength='5' style='width:20px;'
									onkeydown='if(event.keyCode==13){addEvents($arm,$num_events_total);}'
									onblur='redcap_validate(this,\"-9999\",\"9999\",\"hard\",\"int\")' value='0'>
							</div>
						</td>"
					) . "
					<td class='data' valign='top' style='background:#eee;padding:15px 5px 0 5px;'>
						<input type='text' tabindex=4 class='x-form-text x-form-field' id='descrip' size='30' maxlength='30' onkeydown='if(event.keyCode==13){addEvents($arm,$num_events_total);}'>
						<div style='padding:7px 0 7px 3px;font-family:tahoma;font-size:10px;color:#666;'>
							{$lang['define_events_58']}
						</div>
					</td>
					<td class='data' valign='top' style='background:#eee;padding:15px 5px 0 5px;'>
						<input type='text' tabindex=4 class='x-form-text x-form-field' id='custom_event_label' style='width:98%;' onkeydown='if(event.keyCode==13){addEvents($arm,$num_events_total);}'>
						<div style='padding:7px 0 7px 3px;font-family:tahoma;font-size:10px;color:#666;'>
							{$lang['design_671']} {$lang['survey_251']}
							<div style='margin-top:5px;font-size:10px;'>
								{$lang['edit_project_125']} 
								<span style='color:#888;'>[visit_date], [weight] kg</span>
							</div>
						</div>
					</td>
					<td class='data' valign='top' style='background:#eee;padding:15px 5px 0 5px;'>
					</td>
				</tr>";
	}

	print  "</table>";

	// Arm number (hidden)
	print  "<input type='hidden' id='arm' value='$arm'>";

	//Progess icon that only appears when running an AJAX request
	print  "<div id='progress' style='visibility:hidden;'>
			<img src='".APP_PATH_IMAGES."progress_circle.gif'>
			<span style='color:#555'>{$lang['define_events_59']}</span>
			</div>";


}
