<?php


//Pick up any variables passed by Post
if (isset($_POST['pnid'])) $_GET['pnid'] = $_POST['pnid'];
if (isset($_POST['pid']))  $_GET['pid']  = $_POST['pid'];
if (isset($_POST['arm']))  $_GET['arm']  = $_POST['arm'];

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

//If action is provided in AJAX request, perform action.
if (isset($_REQUEST['action'])) {

	switch ($_REQUEST['action']) {
		//Save grid values
		case "save_grid":
			$sql_all = array();
			//Get arm number
			$arm = getArm();
			//Delete all previous form-event combos first in order to replace with new (only delete form-event info for this Arm)
			$sql_all[] = $sql = "delete from redcap_events_forms where event_id in
								(select m.event_id from redcap_events_metadata m, redcap_events_arms a where a.project_id = $project_id
								and a.arm_num = $arm and a.arm_id = m.arm_id)";
			db_query($sql);
			//Loop through posted elements and insert new event-form info
			$grid_array = explode(",", $_POST['grid_values']);
			foreach ($grid_array as $value)
			{
				if(strstr($value, '--'))
				{
					list($this_form, $this_event_id, $this_tf) = explode("--", $value);
					if ($this_tf == "true") {
						$sql_all[] = $sql = "insert into redcap_events_forms (event_id, form_name)
										 	values (".checkNull($this_event_id).", '".db_escape($this_form)."')";
						db_query($sql);
					}
				}
			}
			// If surveys exist and have Automated Invitations set up, then set any AI to inactive status if
			// the user undesignates a form for an event in which the form/survey+event have AI set up as active.
			// This prevents AI from sending survey invites if the form is now undesignated.
			if ($surveys_enabled && !empty($Proj->surveys))
			{
				$sub = pre_query("select ss.ss_id from redcap_surveys_scheduler ss, redcap_surveys s, redcap_events_forms f
						where s.survey_id = ss.survey_id and ss.active = 1 and s.project_id = $project_id
						and f.form_name = s.form_name and f.event_id = ss.event_id");
				$sql_all[] = $sql = "update redcap_surveys_scheduler ss2, redcap_surveys s2 set ss2.active = 0
						where s2.survey_id = ss2.survey_id and ss2.active = 1 and s2.project_id = $project_id
						and ss2.ss_id not in ($sub)";
				db_query($sql);
			}
			// Logging
			Logging::logEvent(implode(";\n", $sql_all),"redcap_events_forms","MANAGE","","arm_num = $arm","Perform instrument-event mappings");
			break;
	}

}


## DTS: Check for any events-forms are being used by DTS
$dtsEventsForms = ($dts_enabled_global && $dts_enabled) ? Event::getDtsEventsForms() : array();
if (!empty($dtsEventsForms)) {
	?>
	<div class="red" style="margin:10px 0;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['define_events_64'] ?></b><br>
		<?php echo $lang['designate_forms_22'] ?>
	</div>
	<?php
}

$csrf_token = System::getCsrfToken();



// Import/export buttons
print 	RCView::div(array('style'=>'margin-top:15px;max-width:700px;text-align:right;'),
			RCView::button(array('onclick'=>"showBtnDropdownList(this,event,'downloadUploadEventsInstrDropdownDiv');", 'class'=>'jqbuttonmed'),
				RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
				RCView::span(array('style'=>'vertical-align:middle;'), $lang['define_events_78']) .
				RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
			) .
			// PDF button/drop-down options (initially hidden)
			RCView::div(array('id'=>'downloadUploadEventsInstrDropdownDiv', 'style'=>'text-align:left;display:none;position:absolute;z-index:1000;'),
				RCView::ul(array('id'=>'downloadUploadEventsInstrDropdown'),
					// Only show upload if development or if prod+users can edit events/arms
					(!($super_user || $status < 1 || ($status > 0 && $enable_edit_prod_events)) ? '' :
						RCView::li(array(),
							RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"simpleDialog(null,null,'importEventsInstrDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importEventsInstrForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importEventsInstrDialog').parent()).css('font-weight','bold');"),
								RCView::img(array('src'=>'arrow_up_sm.gif')) .
								RCView::SP . $lang['define_events_79']
							)
						)
					) .
					RCView::li(array(),
						RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"window.location.href = app_path_webroot+'Design/instrument_event_mapping_download.php?pid='+pid;"),
							RCView::img(array('src'=>'arrow_down_sm.png')) .
							RCView::SP . $lang['define_events_80']
						)
					)
				)
			)
		);

// Hidden import dialog div
print 	RCView::div(array('id'=>'importEventsInstrDialog', 'class'=>'simpleDialog', 'title'=>$lang['define_events_79']),
			RCView::div(array(), $lang['api_106']) .
			RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['api_88']) .
			RCView::form(array('id'=>'importEventsInstrForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/instrument_event_mapping_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::input(array('type'=>'file', 'name'=>'file'))
			)
		);
print 	RCView::div(array('id'=>'importEventsInstrDialog2', 'class'=>'simpleDialog', 'title'=>$lang['define_events_79']),
			RCView::div(array(), $lang['api_125'] . " " . $lang['api_126']) .
			RCView::div(array('id'=>'mapping_preview', 'style'=>'margin:15px 0'), '') .
			RCView::form(array('id'=>'importEventsInstrForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'Design/instrument_event_mapping_upload.php?pid=' . PROJECT_ID),
				RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
				RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), $_SESSION['csv_content'])
			)
		);


/***************************************************************
** ARM TABS
***************************************************************/
//Display Arm number tab
//Loop through each ARM and display as a tab
$q = db_query("select arm_id, arm_num, arm_name from redcap_events_arms where project_id = $project_id order by arm_num");
if (db_num_rows($q) > 1) {
	print '<div id="sub-nav" style="margin-bottom:0;"><ul>';
	while ($row = db_fetch_assoc($q)) {
		//Render tab
		print  '<li';
		//If this tab is the current arm, make it selected
		if ($row['arm_num'] == $arm) {
			print  ' class="active"';
			//Get current Arm ID
			$arm_id = $row['arm_id'];
			//Get current Arm Name
			$arm_name = $row['arm_name'];
		}
		print '><a style="font-size:12px;color:#393733;padding:5px 5px 5px 11px;" href="'.APP_PATH_WEBROOT.'Design/designate_forms.php?pid='.$project_id.'&arm='.$row['arm_num'].'"'
			. '>'.$lang['global_08'].' '.$row['arm_num'].$lang['colon']
			. RCView::span(array('style'=>'margin-left:6px;font-weight:normal;color:#800000;'), RCView::escape(strip_tags($row['arm_name']))).'</a></li>';
	}
	print  '</ul></div>&nbsp;<br>';
	//If more than one arm exists, the display arm name for clarity
	print  "<p>{$lang['designate_forms_18']} <b style='color:#800000;'>".RCView::escape(strip_tags($arm_name))."</b></p>";
} else {
	$arm_id = db_result($q,0);
}




/***************************************************************
** EVENT-FORM GRID
***************************************************************/

//Determine if any forms have been assigned to events and display grid
$q = db_query("select m.event_id, m.descrip, f.form_name from redcap_events_metadata m, redcap_events_forms f
				  where m.event_id = f.event_id and m.arm_id = $arm_id order by m.day_offset, m.descrip");

while ($row = db_fetch_assoc($q))
{
	//Add form-event info to array
	$form_events[$row['event_id']][$row['form_name']] = "";
}
//print "<Pre>";print_r($event_descrip);print_r($form_events);print "</pre>";

//Determine if any visits have been defined yet
$q = db_query("select * from redcap_events_metadata where arm_id = $arm_id order by day_offset, descrip");
$num_events = db_num_rows($q);
while ($row = db_fetch_assoc($q)) {
	//Collect event description to render as labels in grid at bottom
	$event_descrip[$row['event_id']] = $row['descrip'];
}


//Render Grid
$grid_disp_change = "";
$grid_string  =  "<table id='event_grid_table' class='dataTable cell-border'>";
$grid_string .=  "<thead><tr>
					<th style='text-align:center;padding:5px;'>{$lang['global_35']}</th>";
//Render table headers
$i = 1;
foreach ($event_descrip as $this_event) {
	$grid_string .= "<th style='text-align:center;color:#800000;padding:5px;white-space:normal;vertical-align:bottom;'>
						 <div style=''>".RCView::escape(strip_tags($this_event))."</div>
						 <div style='font-weight:normal;font-size:10px;'>(".$i++.")</div>
					 </th>";
}
$grid_string .= "</tr></thead>";
//Render table rows
$sql = "select e.event_id, e.descrip, m.form_name, m.form_menu_description from redcap_events_metadata e, redcap_metadata m
		where m.project_id = $project_id and m.form_menu_description is not null and e.arm_id = $arm_id
		order by m.field_order, e.day_offset, e.descrip";
$q = db_query($sql);
$this_form = "";
$grid_values = array();	
$rowclass = "even";
while ($row = db_fetch_assoc($q))
{
	//Deterine if we are starting new row
	if ($this_form != $row['form_name']) {
		if ($this_form != "") $grid_string .= "</tr>";
		$rowclass = ($rowclass == "even") ? "odd" : "even";
		$grid_string .= "<tr class='$rowclass'><td>".RCView::escape($row['form_menu_description']);
		// Show the label "survey" if first instrument is a survey
		if ($surveys_enabled && isset($Proj->forms[$row['form_name']]['survey_id'])) {
			$grid_string .= "<span style='margin:0 4px;color:#888;font-size:10px;font-family:tahoma;'>{$lang['grid_39']}</span>";
		}
		$grid_string .= "</td>";
	}
	//Render cell
	$grid_string .= "<td style='text-align:center;'>";
	$grid_string .= "<input type='checkbox' id='{$row['form_name']}--{$row['event_id']}' ";
	// If event-form has been stored, then display check mark.
	// Also, for a survey+forms-type project, do not allow the first form to be repeated
	if (isset($form_events[$row['event_id']][$row['form_name']]))
	{
		// Add different image if auto-continue is enabled
		$do_autocontinue = ($surveys_enabled && isset($Proj->forms[$row['form_name']]['survey_id']) &&
						   ($Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['end_survey_redirect_next_survey'] == '1'));
		$image = ($do_autocontinue) ? 'arrow_down.png' : 'tick.png';
		$title = ($do_autocontinue) ? " title='".js_escape($lang['survey_1001'])."'" : "";
		// Add as checked
		$grid_string .= "checked ";
		// If in prod, then prevent user from unchecking it
		if (!$super_user && $status > 0) {
			$grid_string .= "disabled ";
		}
		// Add image
		$grid_string .= "><img src='".APP_PATH_IMAGES."$image' id='img--{$row['form_name']}--{$row['event_id']}'{$title}";
		//Gather javascript to hide check images to begin editing
		$grid_disp_change .= "document.getElementById('img--{$row['form_name']}--{$row['event_id']}').style.display='none';";
	}
	// Give warning label if used by DTS
	if (isset($dtsEventsForms[$row['event_id']][$row['form_name']])) {
		$grid_string .= "><div class='dtswarn' style='font-size:9px;'>{$lang['define_events_62']}</div";
	}
	$grid_string .= "></td>";
	//Collect checkbox values for submitting
	$grid_values[] = "'{$row['form_name']}--{$row['event_id']}--'+document.getElementById('{$row['form_name']}--{$row['event_id']}').checked";
	//Gather javascript to display checkboxes to begin editing
	$grid_disp_change .= "document.getElementById('{$row['form_name']}--{$row['event_id']}').style.display='';";
	//Set for next loop
	$this_form = $row['form_name'];
}
$grid_string .=  "</tr>
		</table>
		<br>";



// Render Edit and Save buttons at top of section
if ($super_user || $status < 1 || ($status > 0 && $enable_edit_prod_events))
{
	print  "<div class='p'>";
	print  "<button class='jqbuttonmed' style='font-size:11px;' onclick=\"
				$(this).button('disable');
				$('#save_btn').button('enable');
				document.getElementById('select_all_links').style.visibility = 'visible';
				$('#event_grid_table img').hide();
				$('#event_grid_table input, #prod_disable_checkbox_notice').show();
			\">{$lang['designate_forms_11']}</button> &nbsp;
			<button class='jqbuttonmed' id='save_btn' style='font-size:11px;' disabled onclick=\"";
	if (count($grid_values) > 0 && isset($grid_values)) {
		print  "$(this).button('disable');
				document.getElementById('progress_save').style.visibility = 'visible';
				document.getElementById('select_all_links').style.visibility = 'hidden';
				var g='';";
		foreach ($grid_values as $grid_value) {
			print "g+=$grid_value+',';";
		}
		print  "$.post('".APP_PATH_WEBROOT."Design/designate_forms_ajax.php', { pid: pid, arm: $arm, action: 'save_grid', grid_values: g }, function(data){
					$('#table').html(data);
					initDesigInstruments();
				});";
	}
	print "	\">{$lang['designate_forms_13']}</button> &nbsp;&nbsp;
			<span id='progress_save' style='color:#555;visibility:hidden;'>
				<img src='".APP_PATH_IMAGES."progress_circle.gif'>
				{$lang['designate_forms_21']}
			</span>
			<span id='select_all_links' style='visibility:hidden;color:#777;font-size:13px;'>
				<a href='javascript;' style='font-size:11px;margin-right:2px;' onclick=\"$('#event_grid_table input[type=checkbox]:not(:disabled)').prop('checked',true);return false;\">{$lang['data_export_tool_52']}</a>
				| <a href='javascript;' style='font-size:11px;margin-left:1px;' onclick=\"$('#event_grid_table input[type=checkbox]:not(:disabled)').prop('checked',false);return false;\">{$lang['data_export_tool_53']}</a>
			</span>";
	// When 'begin editing' button is clicked, display message that only admins can uncheck existing designated forms
	if (!$super_user && $status > 0) {
		print "<div id='prod_disable_checkbox_notice' class='yellow' style='margin-top:10px;font-size:12px;display:none;'>
				{$lang['designate_forms_24']}
				</div>";
	}
	print  "</div>";
}

//Render table
print $grid_string;

