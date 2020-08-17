<?php



include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();

// Get all forms that be locked and put into an array
$sql = "select m.form_name, m.form_menu_description, if(f.display_esignature is null, 0, f.display_esignature) as display_esignature
		from redcap_metadata m left join redcap_locking_labels f on f.form_name = m.form_name and m.project_id = f.project_id
		where m.project_id = $project_id and m.form_menu_description is not null and (f.display = 1 or f.display is null) order by m.field_order";
$q = db_query($sql);
$forms = array();
while ($row = db_fetch_assoc($q))
{
	$forms[$row['form_name']]['menu'] = $row['form_menu_description'];
	$forms[$row['form_name']]['display_esign'] = $row['display_esignature'];
}

// If user is in DAG, then use sub-query to restrict resulting record list to records in their DAG
$group_sql = ($user_rights['group_id'] == "") ? "" : "and d.record in (" . prep_implode(Records::getRecordListSingleDag($project_id, $user_rights['group_id'])) . ")";

													  
// Get all locked records and put into an array
$sql = "select d.record, d.event_id, d.form_name, d.instance, d.username, d.timestamp, u.user_firstname, u.user_lastname
		from redcap_locking_data d left join redcap_user_information u on d.username = u.username 
		where d.project_id = $project_id $group_sql and d.form_name in (".prep_implode(array_keys($forms)).")";
$q = db_query($sql);
$locked_records = array();
while ($row = db_fetch_assoc($q))
{
	// Repeating forms/events
	$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
	$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $row['form_name']));
	$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
	$repeat_instrument = $isRepeatForm ? $row['form_name'] : "";
	$instance = (!$isRepeatEventOrForm) ? 0 : $row['instance'];
	// Add to array
	$locked_records[$row['record']][$row['event_id']][$row['form_name']][$repeat_instrument][$instance] 
		= DateTimeRC::format_ts_from_ymd($row['timestamp']) . (($row['username'] == '') 
		  ? '' :  ("<br>" . $row['username'] . " (" . $row['user_firstname'] . " " . $row['user_lastname'] . ")"));
}


// Get all e-signed records and put into an array
$sql = "select d.record, d.event_id, d.form_name, d.instance, d.username, d.timestamp, u.user_firstname, u.user_lastname
		from redcap_esignatures d left join redcap_user_information u on d.username = u.username 
		where d.project_id = $project_id $group_sql and d.form_name in (".prep_implode(array_keys($forms)).")";
$q = db_query($sql);
$esigned_records = array();
while ($row = db_fetch_assoc($q))
{
	// Repeating forms/events
	$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
	$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $row['form_name']));
	$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
	$repeat_instrument = $isRepeatForm ? $row['form_name'] : "";
	$instance = (!$isRepeatEventOrForm) ? 0 : $row['instance'];
	// Add to array
	$esigned_records[$row['record']][$row['event_id']][$row['form_name']][$repeat_instrument][$instance] 
		= DateTimeRC::format_ts_from_ymd($row['timestamp']) . (($row['username'] == '') 
		  ? '' :  ("<br>" . $row['username'] . " (" . $row['user_firstname'] . " " . $row['user_lastname'] . ")"));
}

// Create array of all form status fields
$form_status_fields = array();
foreach (array_keys($Proj->forms) as $form) {
	if (!isset($forms[$form])) continue;
	$form_status_fields[] = $form . "_complete";
}
$data = Records::getData('array', array(), $form_status_fields, array(), $user_rights['group_id']);
// Remove all non-applicable fields for events and repeating forms
Records::removeNonApplicableFieldsFromDataArray($data, $Proj);
//print_array($data);
// Loop through all data and add to other array used for rendering
$all_lock_esign_info = array();
foreach ($data as $record=>&$event_data)
{
	foreach (array_keys($event_data) as $event_id)
	{
		if ($event_id == 'repeat_instances') {
			$eventNormalized = $event_data['repeat_instances'];
		} else {
			$eventNormalized = array();
			$eventNormalized[$event_id][""][0] = $event_data[$event_id];
		}
		foreach ($eventNormalized as $event_id=>&$data1)
		{
			foreach ($data1 as $repeat_instrument=>&$data2)
			{
				foreach ($data2 as $instance=>&$data3)
				{
					foreach ($data3 as $field=>$value)
					{
						$form_name = substr($field, 0, -9);
						if (!isset($forms[$form_name])) continue;
						// Add to array
						$all_lock_esign_info[] = array(	
							'record' 	=> $record,
							'event_id'	=> $event_id,
							'instance'	=> $instance,
							'form_name'	=> $form_name,
							'locked'	=> ((isset($locked_records[$record][$event_id][$form_name][$repeat_instrument][$instance])) ? 1 : 0),
							'locktime'	=> ((isset($locked_records[$record][$event_id][$form_name][$repeat_instrument][$instance])) ? $locked_records[$record][$event_id][$form_name][$repeat_instrument][$instance] : ''),
							'esigned'	=> ((isset($esigned_records[$record][$event_id][$form_name][$repeat_instrument][$instance])) ? 1 : ($forms[$form_name]['display_esign'] ? 0 : '')),
							'esigntime'	=> ((isset($esigned_records[$record][$event_id][$form_name][$repeat_instrument][$instance])) ? $esigned_records[$record][$event_id][$form_name][$repeat_instrument][$instance] : '')
						);
					}
				}
			}
		}
	}
	unset($data[$record], $event_data, $data1, $data2, $data3);
}
unset($data);



## RENDER PAGE WITH TABLE
if (!isset($_GET['csv']))
{

	// Set page header
	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

    renderPageTitle("<i class=\"fas fa-lock\"></i> {$lang['locking_35']}");
    $tabs = array();
    if ($user_rights['lock_record_customize'] > 0) $tabs['Locking/locking_customization.php'] = '<i class="fas fa-user-lock"></i> ' . $lang['app_11'];
    if ($user_rights['lock_record'] > 0) $tabs['Locking/esign_locking_management.php'] = '<i class="fas fa-unlock-alt"></i> ' . $lang['esignature_01'];
    RCView::renderTabs($tabs);

	?>
	<style type="text/css">
	.data { padding:3px 8px; }
	.data a:link, .data a:visited, .data a:hover, .data a:active { font-size:12px; font-family:tahoma; text-decoration:underline; }
	.label_header { padding:5px 10px; }
	.lock   { text-align: center; color: #666; font-size:12px; font-family:tahoma; }
	.esign  { text-align: center; color: #666; font-size:12px; font-family:tahoma; }
	</style>

	<!-- Instructions -->
	<p><?php print $lang['esignature_02'] ?></p>

	<!-- CSV download -->
	<div style="max-width:770px;" class="text-right mt-1 mb-3">
        <button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+page+'?csv=1&pid='+pid;"><img src="<?php echo APP_PATH_IMAGES ?>xls.gif"> <?php print $lang['locking_37'] ?></button>
	</div>

	<!-- Actions -->
	<p style="margin-left:5em;text-indent:-4.6em;margin-bottom:20px;color:#777;">
		<b style="color:#000;">Actions:</b> &nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();"><?php print $lang['esignature_04'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.lock div').show();$('.esign div').show();$('.lock img').hide();$('.esign img').hide();"><?php print $lang['esignature_05'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.lock div').hide();$('.esign div').hide();$('.lock img').show();$('.esign img').show();"><?php print $lang['esignature_06'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.unlocked').hide();"><?php print $lang['esignature_07'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.locked').hide();"><?php print $lang['esignature_08'] ?></a> &nbsp;|&nbsp; <br>
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.unesigned').hide();$('.aesigned').hide();"><?php print $lang['esignature_09'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.esigned').hide();$('.aesigned').hide();"><?php print $lang['esignature_10'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').hide();$('.locked').show();$('.unesigned').hide();$('.aesigned').hide();"><?php print $lang['esignature_11'] ?></a> &nbsp;|&nbsp; <br>
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.locked').hide();$('.esigned').hide();$('.aesigned').hide();"><?php print $lang['esignature_12'] ?></a> &nbsp;|&nbsp;
		<a href="javascript:;" style="text-decoration:underline;" onclick="$('.rowl').show();$('.unlocked').hide();$('.esigned').hide();$('.aesigned').hide();"><?php print $lang['esignature_13'] ?></a>
	</p>

	<!-- Table -->
	<table id="esignLockList" class="form_border">
		<tr>
			<td style="padding: 7px; border: 1px solid #aaa; background-color: #ddd; font-size: 12px;" colspan="<?php echo ($longitudinal ? ($hasRepeatingFormsEvents ? '7' : '6') : ($hasRepeatingFormsEvents ? '6' : '5')) ?>" class="label_header">
				<?php print $lang['esignature_14'] ?>
			</td>
		</tr>
		<tr>
			<td class="label_header"><?php print $lang['global_49'] ?></td>
			<?php if ($longitudinal) { ?><td class="label_header"><?php print $lang['global_10'] ?></td><?php } ?>
			<td class="label_header"><?php print $lang['global_12'] ?></td>
			<?php if ($hasRepeatingFormsEvents) { ?>
				<td class="label_header"><?php print $lang['global_133'] ?></td>
			<?php } ?>
			<td class="label_header"><?php print $lang['esignature_18'] ?></td>
			<td class="label_header"><?php print $lang['esignature_19'] ?></td>
			<td class="label_header">&nbsp;</td>
		</tr>
		<?php foreach ($all_lock_esign_info as $attr) { ?>
		<tr class="rowl <?php echo ($attr['locked'] ? 'locked' : 'unlocked') ?> <?php echo (($attr['esigned'] == "1") ? 'esigned' : ($attr['esigned'] == "0" ? 'unesigned' : 'aesigned')) ?>">
			<td class="data"><?php echo $attr['record'] ?></td>
			<?php if ($longitudinal) { ?><td class="data"><?php echo $Proj->eventInfo[$attr['event_id']]['name_ext'] ?></td><?php } ?>
			<td class="data"><?php echo $Proj->forms[$attr['form_name']]['menu'] ?></td>
			<?php if ($hasRepeatingFormsEvents) { ?>
				<td class="data"><?php print ($attr['instance'] == '0' ? '' : '#'.$attr['instance']) ?></td>
			<?php } ?>
			<td class="data lock"><?php echo ($attr['locked'] ? '<img src="'.APP_PATH_IMAGES.'lock_small.png"><div style="display:none;">'.$attr['locktime'].'</div>' : '') ?></td>
			<td class="data esign"><?php echo (($attr['esigned'] == "1") ? '<img src="'.APP_PATH_IMAGES.'tick_shield_small.png"><div style="display:none;">'.$attr['esigntime'].'</div>' : ($attr['esigned'] == "0" ? '' : '<span style="color:#999;">N/A</span>')) ?></td>
			<td class="data" style="padding:3px 12px;"><a target="_blank" href="<?php echo APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id={$attr['record']}&page={$attr['form_name']}&event_id={$attr['event_id']}".($attr['instance'] > 0 ? "&instance=".$attr['instance'] : "") ?>"><?php print $lang['esignature_20'] ?></a></td>
		</tr>
		<?php } ?>
	</table>
	<?php

	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

}



## OUTPUT TABLE AS CSV FILE
else
{
	// Set headers
	$headers = array();
	$headers[] = $lang['global_49'];
	if ($longitudinal) $headers[] = $lang['global_10'];
	$headers[] = $lang['global_12'];
	if ($hasRepeatingFormsEvents) $headers[] = $lang['global_133'];
	$headers[] = $lang['esignature_18'];
	$headers[] = $lang['esignature_19'];

	// Set file name and path
	$filename = APP_PATH_TEMP . date("YmdHis") . '_' . PROJECT_ID . '_EsignLockMgmt.csv';

	// Begin writing file from query result
	$fp = fopen($filename, 'w');
	if ($fp)
	{
		// Write headers to file
		fputcsv($fp, $headers);

		// Set values for this row and write to file
		foreach ($all_lock_esign_info as $attr)
		{
			// Set row values
			$this_row = array();
			$this_row[] = $attr['record'];
			if ($longitudinal) $this_row[] = label_decode($Proj->eventInfo[$attr['event_id']]['name_ext']);
			$this_row[] = label_decode($Proj->forms[$attr['form_name']]['menu']);
			if ($hasRepeatingFormsEvents) {
				$this_row[] = ($attr['instance'] == '0' ? '' : '#'.$attr['instance']);
			}
			$this_row[] = ($attr['locked']) ? str_replace("<br>", ", ", $attr['locktime']) : '';
			$this_row[] = ($attr['esigned'] == '1') ? str_replace("<br>", ", ", $attr['esigntime']) : (($attr['esigned'] == '0') ? '' : 'N/A');
			// Write this row to file
			fputcsv($fp, $this_row);
		}

		// Close file for writing
		fclose($fp);

		// Open file for downloading
		$download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_EsignLockMgmt_" . date("Y-m-d_Hi") . ".csv";
		header('Pragma: anytextexeptno-cache', true);
		header("Content-type: application/csv");

		header("Content-Disposition: attachment; filename=$download_filename");

		// Open file for reading and output to user
		$fp = fopen($filename, 'rb');
		print addBOMtoUTF8(fread($fp, filesize($filename)));

		// Close file and delete it from temp directory
		fclose($fp);
		unlink($filename);

	}

}

