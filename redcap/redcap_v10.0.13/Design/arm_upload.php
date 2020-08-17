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

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	// Check for basic attributes needed
	if (empty($data) || !isset($data[0]['name']) || !isset($data[0]['arm_num'])) {
		$errors[] = $lang['design_641'] . " arm_num, name";
	} else {
		foreach($data as $arm)
		{
			$arm['name'] = trim($arm['name']);

			// Ensure that arm number is numeric
			if (!isinteger($arm['arm_num']) || $arm['arm_num'] <= 0) {
				$errors[] = "{$lang['design_636']} \"{$arm['arm_num']}\" {$lang['design_639']}";
				continue;
			}
			if ($arm['name'] == '') {
				$errors[] = "{$lang['design_637']} \"{$arm['arm_num']}\" {$lang['design_638']}";
				continue;
			}
			if (strlen($arm['name']) > 50) {
				$errors[] = "{$lang['design_651']} \"{$arm['name']}\" {$lang['design_652']}";
				continue;
			}

			if (empty($errors))
			{
				if (Arm::getArm(PROJECT_ID, $arm['arm_num']))
				{
					Arm::updateArmName(PROJECT_ID, $arm['arm_num'], $arm['name']);
					++$count;
					continue;
				}

				Arm::addArm(PROJECT_ID, $arm['arm_num'], $arm['name']);
				++$count;
			}
		}
	}

	// Build preview of changes being made
	if (!$commit && empty($errors))
	{
		$cells = "";
		foreach (array_keys($data[0]) as $this_hdr) {
			$cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
		}
		$rows = RCView::tr(array(), $cells);
		foreach($data as $arm)
		{
			$arm['name'] = trim($arm['name']);
			// Check for changes
			$col1class = (!isset($Proj->events[$arm['arm_num']]) ? 'green' : 'gray');
			$col2class = (!isset($Proj->events[$arm['arm_num']]) ? 'green' : (($Proj->events[$arm['arm_num']]['name']."" === $arm['name']."") ? 'gray' : 'yellow'));
			$oldname = (!isset($Proj->events[$arm['arm_num']]) || $Proj->events[$arm['arm_num']]['name']."" === $arm['name']."") ? "" :
						RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$Proj->events[$arm['arm_num']]['name']})");;
			// Add row
			$rows .= 	RCView::tr(array(),
							RCView::td(array('class'=>$col1class),
								$arm['arm_num']
							) .
							RCView::td(array('class'=>$col2class),
								$arm['name'] . $oldname
							)
						);
		}
		$preview = RCView::table(array('cellspacing'=>1), $rows);
	}

	if ($commit && empty($errors)) {
		// Commit
		$csv_content = "";
		db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");
		Logging::logEvent("", "redcap_arms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload arms");
	} else {
		// ERROR: Roll back all changes made and return the error message
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
	}

	$_SESSION['imported'] = 'arms';
	$_SESSION['count'] = $count;
	$_SESSION['errors'] = $errors;
	$_SESSION['csv_content'] = $csv_content;
	$_SESSION['preview'] = $preview;
}

redirect(APP_PATH_WEBROOT . 'Design/define_events.php?pid=' . PROJECT_ID);