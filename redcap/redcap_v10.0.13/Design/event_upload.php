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

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	// Check for basic attributes needed
	if (empty($data) || !isset($data[0]['event_name']) || !isset($data[0]['arm_num'])) {
		$errors[] = $lang['design_641'] . " event_name,arm_num" . ($override ? "" : ",unique_event_name");
	} else {
		foreach($data as $e)
		{
			$day_offset++;

			// Set defaults
			if (!isset($e['day_offset'])) $e['day_offset'] = $day_offset;
			if (!isset($e['offset_min'])) $e['offset_min'] = 0;
			if (!isset($e['offset_max'])) $e['offset_max'] = 0;

			// Error checking
			if (!is_numeric($e['arm_num']) || $e['arm_num'] < 0) {
				$errors[] = "{$lang['design_636']} \"{$e['arm_num']}\" {$lang['design_639']}";
				continue;
			}
			if (!Arm::getArm(PROJECT_ID, $e['arm_num'])) {
				$errors[] = "{$lang['design_644']} \"{$e['arm_num']}\" {$lang['design_643']}";
				continue;
			}
			if (!is_numeric($e['day_offset'])) {
				$errors[] = "{$lang['design_645']} \"{$e['day_offset']}\" {$lang['design_646']}";
				continue;
			}
			if (!is_numeric($e['offset_min']) || $e['offset_min'] < 0) {
				$errors[] = "{$lang['design_647']} \"{$e['offset_min']}\" {$lang['design_639']}";
				continue;
			}
			if (!is_numeric($e['offset_max']) || $e['offset_max'] < 0) {
				$errors[] = "{$lang['design_648']} \"{$e['offset_max']}\" {$lang['design_639']}";
				continue;
			}
			if ((function_exists('mb_strlen') && mb_strlen($e['event_name']) > 30) 
				|| (!function_exists('mb_strlen') && strlen($e['event_name']) > 30)) {
				$errors[] = "{$lang['design_649']} \"{$e['event_name']}\" {$lang['design_650']}";
				continue;
			}
			$id = $Proj->getEventIdUsingUniqueEventName($e['unique_event_name']);

			if($id)
			{
				Event::update($id, $e);
				++$count;
				continue;
			}

			Event::create(PROJECT_ID, $e);
			++$count;
		}
	}

	// Build preview of changes being made
	if (!$commit && empty($errors))
	{
		$uniqueNames = $Proj->getUniqueEventNames();
		$cells = "";
		foreach (array_keys($data[0]) as $this_hdr) {
			$cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
		}
		$rows = RCView::tr(array(), $cells);
		foreach($data as $item)
		{
			$this_event_id = $Proj->getEventIdUsingUniqueEventName($item['unique_event_name']);
			$event_name = trim($item['event_name']);
			unset($item['event_name']);
			$item = array_merge(array('name'=>$event_name), $item);
			$cells = "";
			foreach ($item as $key=>$val) {
				$oldval = '';
				if (!is_numeric($this_event_id)) {
					$colclass = 'green';
				} elseif ($key == 'unique_event_name' || $Proj->eventInfo[$this_event_id][$key]."" === $val."") {
					$colclass = 'gray';
				} else {
					$colclass = 'yellow';
					$oldval = RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$Proj->eventInfo[$this_event_id][$key]})");
				}
				$cells .= RCView::td(array('class'=>$colclass),
							$val . $oldval
						  );
			}
			$rows .= RCView::tr(array(), $cells);
		}
		$preview = RCView::table(array('cellspacing'=>1), $rows);
	}

	if ($commit && empty($errors)) {
		// Commit
		$csv_content = "";
		db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");
		Logging::logEvent("", "redcap_events", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload events");
	} else {
		// ERROR: Roll back all changes made and return the error message
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
	}

	$_SESSION['imported'] = 'events';
	$_SESSION['count'] = $count;
	$_SESSION['errors'] = $errors;
	$_SESSION['csv_content'] = $csv_content;
	$_SESSION['preview'] = $preview;
}

redirect(APP_PATH_WEBROOT . 'Design/define_events.php?pid=' . PROJECT_ID);