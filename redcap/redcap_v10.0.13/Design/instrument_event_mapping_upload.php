<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


$count = 0;
$errors = array();
$csv_content = $preview = "";
$commit = false;
if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
	$csv_content = file_get_contents($_FILES['file']['tmp_name']);
} elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
	$csv_content = $_POST['csv_content'];
	$commit = true;
}

if ($csv_content != "")
{
	$data = csvToArray(removeBOMfromUTF8($csv_content));

	$count = 0;
	$day_offset = 0;
	$errors = array();

	// Check structure
	if (empty($data) || !isset($data[0]['form']) || !isset($data[0]['unique_event_name']) || !isset($data[0]['arm_num'])) {
		$errors[] = $lang['design_641'] . " arm_num,unique_event_name,form";
	} else {
		// Error checking
		foreach ($data as $e)
		{
			if (!is_numeric($e['arm_num']) || $e['arm_num'] < 0) {
				$errors[] = "{$lang['design_636']} \"{$e['arm_num']}\" {$lang['design_639']}";
			}
			elseif (!Arm::getArm(PROJECT_ID, $e['arm_num'])) {
				$errors[] = "{$lang['design_644']} \"{$e['arm_num']}\" {$lang['design_643']}";
			}
			if (!$Proj->getEventIdUsingUniqueEventName($e['unique_event_name'])) {
				$errors[] = "{$lang['design_642']} \"{$e['unique_event_name']}\" {$lang['design_643']}";
			}
			if (!isset($Proj->forms[$e['form']])) {
				$errors[] = "{$lang['design_653']} \"{$e['form']}\" {$lang['design_643']}";
			}
		}

		// Build preview of changes being made
		if (!$commit && empty($errors))
		{
			// Build new arrays of existing mappings and new mappings to compare
			$existingMappings = $newMappings = array();
			foreach ($data as $items) {
				$newMappings[$items['unique_event_name'].'-'.$items['form']] = $items;
			}
			foreach ($Proj->eventsForms as $this_event_id=>$forms) {
				$event_name = $Proj->getUniqueEventNames($this_event_id);
				foreach ($forms as $this_form) {
					$existingMappings[$event_name.'-'.$this_form] = array('arm_num'=>$Proj->eventInfo[$this_event_id]['arm_num'], 'unique_event_name'=>$event_name, 'form'=>$this_form);
				}
			}
			$mappingsToDelete = array_diff_key($existingMappings, $newMappings);
			$mappingsToAdd = array_diff_key($newMappings, $existingMappings);

			$cells = "";
			foreach ($newMappings as $hdrs) { $hdrs = array_keys($hdrs); break; }
			foreach ($hdrs as $this_hdr) {
				$cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
			}
			$rows = RCView::tr(array(), $cells);
			foreach($newMappings as $key=>$item)
			{
				// Is the item new or existing?
				$colclass = isset($mappingsToAdd[$key]) ? 'green' : 'gray';
				$cells = "";
				foreach ($item as $key=>$val) {
					$cells .= RCView::td(array('class'=>$colclass),
								$val
							  );
				}
				$rows .= RCView::tr(array(), $cells);
			}
			// Now loop through any that were deleted
			$mappingsToDeleteInfo = array();
			foreach($mappingsToDelete as $item)
			{
				$cells = "";
				$mappingsToDeleteInfo[] = implode(", ", $item);
				foreach ($item as $key=>$val) {
					$cells .= RCView::td(array('class'=>'red'),
								$val
							  );
				}
				$rows .= RCView::tr(array(), $cells);
				// While in production, prevent users from removing any designations
				if ($status > 0 && !SUPER_USER) { 
					$errors[] = "{$lang['design_770']}<br><i>" . implode("<br>", $mappingsToDeleteInfo)."</i>";
				}
			}

			$preview = RCView::table(array('cellspacing'=>1), $rows);
		}

		if ($commit && empty($errors)) {
			// Commit
			$csv_content = "";
			// Set new mappings
			$Proj->clearEventForms();
			$count = $Proj->addEventForms($data);
			// Logging
			Logging::logEvent("", "redcap_event_forms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload instrument-event mappings");
		}
	}

	$_SESSION['imported'] = 'instr_event_map';
	$_SESSION['count'] = $count;
	$_SESSION['errors'] = $errors;
	$_SESSION['csv_content'] = $csv_content;
	$_SESSION['preview'] = $preview;
}

redirect(APP_PATH_WEBROOT . 'Design/designate_forms.php?pid=' . PROJECT_ID);