<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$errorMsg = "";

// Add DAG to uistate if passed in query string for users NOT in a DAG
$dags = $Proj->getGroups();
$uiStateDag = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'dag');
if (isset($_GET['dag']) && is_numeric($_GET['dag']) && $user_rights['group_id'] == "" && ($uiStateDag == '' || isset($dags[$uiStateDag]))) {
	if ($uiStateDag != $_GET['dag']) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'dag', (int)$_GET['dag']);
	}
} elseif (!isset($_GET['dag']) && $uiStateDag != '' && $user_rights['group_id'] == "" && isset($dags[$uiStateDag])) {
	$_GET['dag'] = $uiStateDag;
} else {
	$_GET['dag'] = null;
	if ($uiStateDag != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'dag');
	}
}

// Add arm to uistate if passed in query string
if ($multiple_arms) {
	$uiStateArm = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'arm');
	if (isset($_GET['arm']) && is_numeric($_GET['arm']) && isset($Proj->events[$_GET['arm']])) {
		if ($uiStateArm != $_GET['arm']) {
			UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'arm', (int)$_GET['arm']);
		}
	} elseif (!isset($_GET['arm']) && $uiStateArm != '') {
		$_GET['arm'] = $uiStateArm;
	} else {
		$_GET['arm'] = 1;
		if ($uiStateArm != '') {
			UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'arm');
		}
	}
} else {
	$_GET['arm'] = false;
}

// Add "records per page" to uistate if passed in query string
$uiStateNumRecs = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
if ($uiStateNumRecs == 'ALL') {
    $uiStateNumRecs == '';
    UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
}
if (isset($_GET['num_per_page'])) {
	if ($uiStateNumRecs != $_GET['num_per_page'] && $_GET['num_per_page'] != 'ALL') { // Save UIState but not if set to show ALL records (page could be too large and never load in the future)
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'num_per_page', $_GET['num_per_page']);
	}
} elseif (!isset($_GET['num_per_page']) && $uiStateNumRecs != '') {
	$_GET['num_per_page'] = $uiStateNumRecs;
} else {
	unset($_GET['num_per_page']);
	if ($uiStateNumRecs != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
	}
}

// Add "page number" to uistate if passed in query string
$uiStatePageNum = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'pagenum');
if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
	if ($uiStatePageNum != $_GET['pagenum']) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'pagenum', (int)$_GET['pagenum']);
	}
} elseif (!isset($_GET['pagenum']) && $uiStatePageNum != '') {
	$_GET['pagenum'] = $uiStatePageNum;
} else {
	unset($_GET['pagenum']);
	if ($uiStatePageNum != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'pagenum');
	}
}

// Get/save UI value to remember last dashboard viewed in this project
$uiStateRdId = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
$rd_id = (isset($_GET['rd_id']) && is_numeric($_GET['rd_id'])) ? (int)$_GET['rd_id'] : null;
if ($uiStateRdId != $rd_id) {
	if (!empty($rd_id)) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'rd_id', $rd_id);
	} elseif (!empty($uiStateRdId) && empty($rd_id) && isset($_GET['rd_id'])) {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
	} elseif (!empty($uiStateRdId) && empty($rd_id)) {
		$rd_id = $uiStateRdId;
	} else {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
	}
}

// Get dashboard settings of current dashboard
$dashboard = RecordDashboard::getRecordDashboardSettings($rd_id);
// In case a dashboard was deleted but still has it's uistate saved
if (is_numeric($rd_id) && $dashboard['rd_id'] == '') {
	$rd_id = null;
	UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
}
// Set arm manually if saved in custom dashboard
$showArmTabs = $multiple_arms;
if ($multiple_arms && is_numeric($dashboard['arm'])) {
	$_GET['arm'] = $dashboard['arm'];
	$showArmTabs = false;
}
// Get selected events/forms array to limit columns
$selected_forms_events_array = RecordDashboard::convertSelectedFormsEventsFromBackendAsArray($dashboard['selected_forms_events']);

// Set dashboard title and instructions
$instructions = ($dashboard['rd_id'] == '') ? $lang['data_entry_176'] : trim(filter_tags($dashboard['description']));
$title = ($dashboard['rd_id'] == '') ? "{$lang['global_91']} {$lang['bottom_61']}" : strip_tags($dashboard['title']);

// Set vertical/horizontal orientation values for table headers
$th_span1 = $th_span2 = '';
$th_width = 'width:35px;';
if ($dashboard['orientation'] == 'V') {
	$th_span1 = '<span class="vertical-text"><span class="vertical-text-inner">';
	$th_span2 = '</span></span>';
	$th_width = '';
}

// Has repeating instances?
$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();

// Get list of all records
$recordNames = $recordNamesReal = array_values(Records::getRecordList(PROJECT_ID, ($user_rights['group_id'] != '' ? $user_rights['group_id'] : $_GET['dag']), true, false, $_GET['arm']));
// For DDE user, append DDE#
$isDDE = ($Proj->project['double_data_entry'] && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
if ($isDDE) {
	foreach ($recordNamesReal as &$this_record) {
		$this_record .= "--" . $user_rights['double_data'];
	}
}

// Apply filter logic (if defined for a custom dashboard)
if (trim($dashboard['filter_logic']) != '' && !empty($recordNames)) 
{
	// Set events
	$events = (is_numeric($_GET['arm']) && isset($Proj->events[$_GET['arm']])) ? array_keys($Proj->events[$_GET['arm']]['events']) : array_keys($Proj->eventInfo);
	// Get record names
	try {
        $getDataParams = array('project_id'=>PROJECT_ID, 'return_format'=>'array', 'records'=>$recordNamesReal, 'fields'=>$Proj->table_pk,
                                'events'=>$events, 'filterLogic'=>$dashboard['filter_logic'], 'returnEmptyEvents'=>true);
		$recordNames = array_keys(Records::getData($getDataParams));
	} catch (Exception $e) {
		$errorMsg = $lang['data_entry_368'];
		$recordNames = array();
	}
}

// If using Order Records By feature, then order records by that field's value instead of by record name
$preserve_record_order = false;
if ($errorMsg == '' && !empty($recordNames) && !($dashboard['sort_order'] == 'ASC' && ($dashboard['sort_field_name'] == '' || $dashboard['sort_field_name'] == $Proj->table_pk)))
{
	$preserve_record_order = true;
	// Get all values for the Order Records By field
	$order_id_by_records = Records::getData('array', $recordNamesReal, $dashboard['sort_field_name'], $dashboard['sort_event_id']);
	// Isolate values only into separate array
	$order_id_by_values = array();
	foreach ($recordNames as $this_record) {
		$val = "";
		if (isset($order_id_by_records[$this_record][$dashboard['sort_event_id']][$dashboard['sort_field_name']])) {
			$val = $order_id_by_records[$this_record][$dashboard['sort_event_id']][$dashboard['sort_field_name']];
		}
		$order_id_by_values[$this_record] = strtolower($val); // Make lowercase since we want to do case-insensitive ordering
		unset($order_id_by_records[$this_record]);
	}
	// Now sort $formStatusValues by values in $order_id_by_values
	$field_type = $Proj->metadata[$dashboard['sort_field_name']]['element_type'];
	$val_type = $Proj->metadata[$dashboard['sort_field_name']]['element_validation_type'];
	$sortFieldIsNumber = (($dashboard['sort_field_name'] == $Proj->table_pk && $Proj->project['auto_inc_set']) 
						 || $val_type == 'float' || $val_type == 'int' || $field_type == 'calc' || $field_type == 'slider');
	array_multisort($order_id_by_values, ($dashboard['sort_order'] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber ? SORT_NUMERIC : SORT_STRING), $recordNames);
	unset($order_id_by_values, $order_id_by_records);
}
// No longer need this
unset($recordNamesReal);



$numRecords = count($recordNames);

// Remove records from $formStatusValues array based upon page number
if (isset($_GET['num_per_page'])) {
	$num_per_page = is_numeric($_GET['num_per_page']) ? (int)$_GET['num_per_page'] : $numRecords;
} else {
	$num_per_page = ($numRecords <= 100) ? $numRecords : 100;
}
if ($num_per_page < 1) $num_per_page = 100;
$limit_begin  = 0;
if (!isset($_GET['pagenum']) || $num_per_page*($_GET['pagenum']-1) > $numRecords) {
	$_GET['pagenum'] = 1;
} elseif (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
	$limit_begin = ((int)$_GET['pagenum'] - 1) * $num_per_page;
}

// Do not slice array if showall flag is in query string
if ($_GET['pagenum'] != 'ALL' && $numRecords > $num_per_page) {
	$recordNamesThisPage = array_slice($recordNames, $limit_begin, $num_per_page, true);
} else {
	$recordNamesThisPage = $recordNames;
}

// If this is a longitudinal project with multiple arms, then fill array denoting which arm that a record belongs to
//$recordsPerArm = ($multiple_arms) ? Records::getRecordListPerArm($project_id, $recordNamesThisPage, $_GET['arm']) : array();

// Get form status of just this page's records
$formStatusValues = array();
if ($errorMsg == '') {
	$formStatusValues = Records::getFormStatus(PROJECT_ID, $recordNamesThisPage, (is_numeric($dashboard['arm']) ? $dashboard['arm'] : $_GET['arm']), 
								$_GET['dag'], $selected_forms_events_array, $preserve_record_order);
}
$numRecordsThisPage = count($formStatusValues);


// Obtain custom record label & secondary unique field labels for ALL records.
$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordNamesThisPage);
/* 
if ($multiple_arms) {
	$extra_record_labels = array();
	foreach ($recordsPerArm as $this_arm=>$these_records) {
		$extra_record_labels_temp = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($these_records), false, $this_arm);
		// Loop through the results and add each record's label (we loop because we may have one label per arm, and so this will concatenate them all together)
		foreach ($extra_record_labels_temp as $this_record=>$this_label) {
			if (!isset($extra_record_labels[$this_record])) {
				$extra_record_labels[$this_record] = $this_label;
			} else {
				$extra_record_labels[$this_record] .= " " . $this_label;
			}
		}
	}
	unset($extra_record_labels_temp);
} else {
	$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords();// Obtain custom record label & secondary unique field labels for ALL records.
}
 */
 
## LOCKING & E-SIGNATURES
$displayLocking = $displayEsignature = false;
// Check if need to display this info at all
$sql = "select display, display_esignature from redcap_locking_labels
		where project_id = $project_id and form_name in (".prep_implode(array_keys($Proj->forms)).")";
$q = db_query($sql);
if (db_num_rows($q) == 0) {
	$displayLocking = true;
} else {
	$lockFormCount = count($Proj->forms);
	$esignFormCount = 0;
	while ($row = db_fetch_assoc($q)) {
		if ($row['display'] == '0') $lockFormCount--;
		if ($row['display_esignature'] == '1') $esignFormCount++;
	}
	if ($esignFormCount > 0) {
		$displayLocking = $displayEsignature = true;
	} elseif ($lockFormCount > 0) {
		$displayLocking = true;
	}
}
// Get all locked records and put into an array
$locked_records = array();
if ($displayLocking) {
	$sql = "select record, event_id, form_name, instance from redcap_locking_data
			where project_id = $project_id and record in (".prep_implode(array_keys($formStatusValues)).")";
	$q = db_query($sql);
	if($q !== false)
	{
		while ($row = db_fetch_assoc($q)) {
			$locked_records[$row['record']][$row['event_id']][$row['form_name']][$row['instance']] = true;
		}
	}
}
// Get all e-signed records and put into an array
$esigned_records = array();
if ($displayEsignature) {
	$sql = "select record, event_id, form_name, instance from redcap_esignatures
			where project_id = $project_id and record in (".prep_implode(array_keys($formStatusValues)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$esigned_records[$row['record']][$row['event_id']][$row['form_name']][$row['instance']] = true;
	}
}

// Build drop-down list of page numbers
$num_pages = ceil($numRecords/$num_per_page);
//$pageNumDropdownOptions = array('ALL'=>'-- '.$lang['docs_44'].' --');
$pageNumDropdownOptions = array();
for ($i = 1; $i <= $num_pages; $i++) {
	$end_num   = $i * $num_per_page;
	$begin_num = $end_num - $num_per_page + 1;
	$value_num = $end_num - $num_per_page;
	if ($end_num > $numRecords) $end_num = $numRecords;
	$pageNumDropdownOptions[$i] = "{$lang['survey_132']} $i {$lang['survey_133']} $num_pages{$lang['colon']} \"".removeDDEending($recordNames[$begin_num-1])."\" {$lang['data_entry_216']} \"".removeDDEending($recordNames[$end_num-1])."\"";
}
if ($num_pages == 0) {
	$pageNumDropdownOptions[0] = "0";
}

$dagsDropdown = '';
if ($user_rights['group_id'] == '' && !empty($dags))
{
	$dagOptions = array('ALL'=>'-- ' . $lang['docs_44'] . ' --');
	$dag = is_numeric($_GET['dag']) ? $_GET['dag'] : 'ALL';

	foreach( $dags as $k => $v )
	{
		$dagOptions[$k] = $v;
	}

	$dagsDropdown = RCView::div(array('style'=>"margin-bottom:7px;"), $lang['data_entry_261'] . '&nbsp;&nbsp;' .
						RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'color:#008000;',
							'onchange'=>"showProgress(1);window.location.href=window.location.href+'&dag='+this.value;"),
							$dagOptions, $dag));
}


		
// Build drop-down list of records per page options, including any legacy values
$recordsPerPageOptions = array('ALL' => $lang['docs_44'] . " (".User::number_format_user($numRecords).")");
$defaultRecordsPerPage = array(10,25,50,100,250,500,1000);
if (is_numeric($num_per_page) && !in_array($num_per_page)) {
	array_push($defaultRecordsPerPage,$num_per_page);
	sort($defaultRecordsPerPage);
}
foreach ($defaultRecordsPerPage as $opt) {
	$recordsPerPageOptions[$opt] = $opt;
}

// Custom dashboards list
$dashboards = RecordDashboard::getRecordDashboardsList(true);
$dashboardNames = array();
foreach ($dashboards as $rd_id2=>$attr) {
	$dashboardNames[$rd_id2] = strip_tags($attr['title']);
}
$dashboardsDropdown = RCView::div(array('class'=>'clearfix', 'style'=>"margin-bottom:7px;"), 
						RCView::div(array('style'=>'float:left;'),
							$lang['data_entry_334'] . '&nbsp;&nbsp;' .
							RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'color:#A00000;',
							'onchange'=>"showProgress(1);window.location.href=window.location.href+'&rd_id='+this.value;"),
							$dashboardNames, $rd_id) .
							(!(is_numeric($rd_id) && $user_rights['design']) ? '' :
								RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'style'=>'font-size:13px;', 
									'onclick'=>"openDashboardSetup('$rd_id');"), 
									$lang['design_169']
								)
							)
						) .
						(!$user_rights['design'] ? '' :
							RCView::button(array('class'=>'btn btn-primaryrc btn-xs', 'style'=>'float:right;font-size:13px;', 
									'onclick'=>"openDashboardSetup('');"), 
								RCView::i(array('class'=>'fas fa-pencil-alt', 'style'=>'font-size:11px;'), '') . ' ' .
								$lang['data_entry_335']
							)
						)
					  );

// Settings section
$dashboardOptionsBox = RCView::div(array('class'=>'chklist clearfix','style'=>'padding:8px 15px 7px;margin:5px 0 20px;max-width:750px;'),
						$dashboardsDropdown .
						$dagsDropdown .
						RCView::div(array('style'=>'float:left;'),
							$lang['data_entry_177'] .
							RCView::select(array('class'=>'x-form-text x-form-field','style'=>'margin-left:8px;margin-right:4px;',
								'onchange'=>"showProgress(1);window.location.href=window.location.href+'&pagenum='+this.value;"),
								$pageNumDropdownOptions, $_GET['pagenum'], 500) .
							$lang['survey_133'].
							RCView::span(array('style'=>'font-weight:bold;margin:0 4px;font-size:13px;'),
								User::number_format_user($numRecords)
							) .
							$lang['data_entry_173']
						) .
						// Num records per page
						RCView::div(array('style'=>'float:right;'),
							RCView::select(
								array('class'=>'x-form-text x-form-field',
									'style'=>'margin-right:4px;',
									'onchange'=>"showProgress(1);window.location.href=window.location.href+'&num_per_page='+this.value;"
								), $recordsPerPageOptions, ($num_per_page == $numRecords ? 'ALL' : $num_per_page)
							) . " " . $lang['data_entry_332']
						)
					);
if ($user_rights['design']) $dashboardOptionsBox .= RecordDashboard::renderSetup($dashboard);

// Determine if records also exist as a survey response for some instruments
$surveyResponses = array();
if ($surveys_enabled) {
	$surveyResponses = Survey::getResponseStatus($project_id, array_keys($formStatusValues));
}

// Determine if Real-Time Web Service is enabled, mapping is set up, and that this user has rights to adjudicate
$showRTWS = ((($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())) && $DDP->userHasAdjudicationRights());

// If RTWS is enabled, obtain the cached item counts for the records being displayed on the page
if ($showRTWS)
{
	// Collect records with cached data into array with record as key and last fetch timestamp as value
	$records_with_cached_data = array();
	$sql = "select r.record, r.item_count from redcap_ddp_records r
			where r.project_id = $project_id and r.record in (" . prep_implode(array_keys($formStatusValues)) . ")";
	$q = db_query($sql);
	if($q !== false)
	{
		while ($row = db_fetch_assoc($q)) {
			if ($row['item_count'] === null) $row['item_count'] = ''; // Avoid null values because isset() won't work with it as an array value
			$records_with_cached_data[$row['record']] = $row['item_count'];
		}
	}
}


// Obtain a list of all instruments used for all events (used to iterate over header rows and status rows)
$formsEvents = $formsEventsColspan = array();
// Loop through each event and output each where this form is designated
if ($dashboard['group_by'] == 'event') {
	foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
		// If we are viewing a specific arm, then only show events for the current arm
		if ($multiple_arms && is_numeric($_GET['arm']) && !isset($Proj->events[$_GET['arm']]['events'][$this_event_id])) {
			continue;
		}
		// Loop through forms
		foreach ($these_forms as $form_name) {
			// If user does not have form-level access to this form, then do not display it
			if (!isset($user_rights['forms'][$form_name]) || $user_rights['forms'][$form_name] < 1) continue;
			if (!in_array($form_name, $selected_forms_events_array[$this_event_id])) continue;
			// Add to array
			$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id);
			// Set colspan for event/form, depending on group_by
			if (isset($formsEventsColspan[$this_event_id])) {
				$formsEventsColspan[$this_event_id]++;
			} else {
				$formsEventsColspan[$this_event_id] = 1;
			}
		}
	}
} else {
	foreach (array_keys($Proj->forms) as $form_name) {
		// If user does not have form-level access to this form, then do not display it
		if (!isset($user_rights['forms'][$form_name]) || $user_rights['forms'][$form_name] < 1) continue;
		// Loop through events
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
			// Skip if form not designated for this event
			if (!in_array($form_name, $these_forms)) continue;
			if (!in_array($form_name, $selected_forms_events_array[$this_event_id])) continue;
			// If we are viewing a specific arm, then only show events for the current arm
			if ($multiple_arms && is_numeric($_GET['arm']) && !isset($Proj->events[$_GET['arm']]['events'][$this_event_id])) {
				continue;
			}
			// Add to array
			$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id);
			// Set colspan for event/form, depending on group_by
			if (isset($formsEventsColspan[$form_name])) {
				$formsEventsColspan[$form_name]++;
			} else {
				$formsEventsColspan[$form_name] = 1;
			}
		}
		
	}
}


// HEADERS: Add all row HTML into $rows. Add header to table first.
$hdrs = RCView::th(array('rowspan'=>($longitudinal ? '2' : '1'), 'style'=>'text-align:center;color:#800000;padding:5px 10px;vertical-align:bottom;'), 
			$th_span1 . $table_pk_label . $th_span2
		);
// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$hdrs .= RCView::th(array('id'=>'rtws_rsd_hdr', 'rowspan'=>($longitudinal ? '2' : '1'), 'class'=>'wrap darkgreen', 'style'=>'line-height:10px;width:100px;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
				RCView::div(array('style'=>'font-weight:bold;font-size:12px;margin-bottom:7px;'),
					'<i class="fas fa-database"></i> ' .
                    ($DDP->isEnabledInProjectFhir() ? $lang['ws_292'] : $lang['ws_30'])
				) .
				$lang['ws_06'] . RCView::SP . $DDP->getSourceSystemName()
			);
}
if ($longitudinal) {
	$prev_event_id = $prev_form_name = null;
	foreach ($formsEvents as $attr) {
		if ($dashboard['group_by'] == 'event') {
			// Skip if already did this event
			if ($prev_event_id == $attr['event_id']) continue;
			// Group by event
			$hdrs .= RCView::th(array('class'=>'rsd-left', 'colspan'=>$formsEventsColspan[$attr['event_id']], 'style'=>'border-bottom:1px dashed #aaa;color:#800000;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
						RCView::escape($Proj->eventInfo[$attr['event_id']]['name'])
					);
			$prev_event_id = $attr['event_id'];
		} else {
			// Skip if already did this event
			if ($prev_form_name == $attr['form_name']) continue;
			// Group by form
			$hdrs .= RCView::th(array('class'=>'rsd-left', 'colspan'=>$formsEventsColspan[$attr['form_name']], 'style'=>'border-bottom:1px dashed #aaa;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
						RCView::escape($Proj->forms[$attr['form_name']]['menu'])
					);
			$prev_form_name = $attr['form_name'];
		}
	}
	$rows = RCView::tr('', $hdrs);
	$hdrs = "";
}
$prev_form = $prev_event = null;
foreach ($formsEvents as $attr) {
	if ($dashboard['group_by'] == 'event') {
		// Group by event
		$hdrs .= RCView::th(array('class'=>($longitudinal && $prev_event != $attr['event_id'] ? ' rsd-left' : ''), 'style'=>$th_width.($longitudinal ? 'border-top:0;' : '').'font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
					RCView::div(array('style'=>($longitudinal ? 'font-weight:normal;' : '')), 
						$th_span1 . RCView::escape($Proj->forms[$attr['form_name']]['menu']) . $th_span2
					)
				);
	} else {
		// Group by form
		$hdrs .= RCView::th(array('class'=>($longitudinal && $prev_form != $attr['form_name'] ? ' rsd-left' : ''), 'style'=>$th_width.($longitudinal ? 'border-top:0;' : '').'color:#800000;font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
					RCView::div(array('style'=>($longitudinal ? 'font-weight:normal;' : '')), 
						$th_span1 . RCView::escape($Proj->eventInfo[$attr['event_id']]['name']) . $th_span2
					)
				);		
	}
	$prev_form = $attr['form_name'];
	$prev_event = $attr['event_id'];
}
$rows .= RCView::tr('', $hdrs);
$rows = RCView::thead('', $rows);


// IF NO RECORDS EXIST, then display a single row noting that
if (empty($formStatusValues))
{
	$rows .= RCView::tr('',
				RCView::td(array('class'=>'data','colspan'=>count($formsEvents)+($showRTWS ? 1 : 0)+1,'style'=>'font-size:12px;padding:10px;color:#555;'),
					($errorMsg == '' ? ($dashboard['filter_logic'] == '' ? $lang['data_entry_179'] : $lang['data_entry_372']) : RCView::div(array('class'=>'red'), $errorMsg))
				)
			);
}

$arm = is_numeric($_GET['arm']) ? $_GET['arm'] : getArm();

// Determine which records are locked via record-level locking
$lockingWhole = new Locking();
$lockingWhole->findLockedWholeRecord($project_id, array_keys($formStatusValues), $arm);
$arm_id = $Proj->getArmIdFromArmNum($arm);

// ADD ROWS: Get form status values for all records/events/forms and loop through them
$prev_form = $prev_event = null;
$rowclass = "even";
foreach ($formStatusValues as $this_record=>$rec_attr)
{
	// For each record (i.e. row), loop through all forms/events
	$this_row = RCView::td(array('style'=>'font-size:12px;'),
					RCView::a(array('href'=>APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=$project_id&arm=$arm&id=".htmlspecialchars(removeDDEending($this_record), ENT_QUOTES),
							'style'=>'text-decoration:underline;font-size:13px;'), htmlspecialchars(removeDDEending($this_record), ENT_QUOTES)) .
					// Display custom record label or secondary unique field (if applicable)
					(isset($extra_record_labels[$this_record]) ? '&nbsp;&nbsp;' . $extra_record_labels[$this_record] : '') .
                    // Is record locked via record-level locking?
                    (isset($lockingWhole->lockedWhole[$this_record]) ? RCView::img(array('src'=>'lock_big.png', 'class'=>'lock align-middle', 'style'=>'width:18px;height:18px;', 'title'=>$lang['form_renderer_37']." ".DateTimeRC::format_ts_from_ymd($lockingWhole->lockedWhole[$this_record][$arm_id]))) : "")
				);
	// If RTWS is enabled, then display column for it
	if ($showRTWS) {
		// If record already has cached data, then obtain count of unadjudicated items for this record
		if (isset($records_with_cached_data[$this_record])) {
			// Get number of items to adjudicate and the html to display inside the dialog
			if ($records_with_cached_data[$this_record] != "") {
				$itemsToAdjudicate = $records_with_cached_data[$this_record];
			} else {
				list ($itemsToAdjudicate, $newItemsTableHtml)
					= $DDP->fetchAndOutputData($this_record, null, array(), $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus,
												false, true, false, false);
			}
		} else {
			// No cached data for this record
			$itemsToAdjudicate = 0;
		}
		// Set display values
		if ($itemsToAdjudicate == 0) {
			$rtws_row_class = "darkgreen";
			$rtws_item_count_style = "color:#999;font-size:10px;";
			$num_items_text = $lang['dataqueries_259'];
		} else {
			$rtws_row_class = "statusdashred";
			$rtws_item_count_style = "color:red;font-size:15px;font-weight:bold;";
			$num_items_text = $itemsToAdjudicate;
		}
		// Display row
		$this_row .= RCView::td(array('class'=>$rtws_row_class, 'id'=>'rtws_new_items-'.$this_record, 'style'=>'font-size:12px;padding:0 5px;text-align:center;'),
						'<div style="float:left;width:50px;text-align:center;'.$rtws_item_count_style.'">'.$num_items_text.'</div>
						<div style="float:right;"><a href="javascript:;" onclick="triggerRTWSmappedField(\''.js_escape2($this_record).'\',true);" style="font-size:10px;text-decoration:underline;">'.$lang['dataqueries_92'].'</a></div>
						<div style="clear:both:height:0;"></div>'
					);
	}
	// Loop through each column
	$lockimgStatic  = trim(RCView::img(array('class'=>'lock', 'src'=>'lock_small.png')));
	$esignimgStatic = trim(RCView::img(array('class'=>'esign', 'src'=>'tick_shield_small.png')));
	$lockimgMultipleStatic  = trim(RCView::img(array('class'=>'lock', 'src'=>'locks_small.png')));
	$esignimgMultipleStatic = trim(RCView::img(array('class'=>'esign', 'src'=>'tick_shields_small.png')));
	foreach ($formsEvents as $attr)
	{
		// If a longitudinal project with multiple arms, do NOT display the icon if record does NOT belong to this arm
		// if ($multiple_arms && !isset($recordsPerArm[$Proj->eventInfo[$attr['event_id']]['arm_num']][$this_record])) {
			// $td = '';
		// } else {
			// Determine status
			$this_status_array = $rec_attr[$attr['event_id']][$attr['form_name']];
			$status_concat = trim(implode('', $this_status_array));
			$status_count = count(array_keys($this_status_array));
			$status_value_count = strlen($status_concat);
			$form_has_mixed_statuses = false;
			$form_has_multiple_instances = ($status_count > 1);
			if ($form_has_multiple_instances) {
				// Determine if all statuses are same or mixed status values
				$all0s = (str_replace('0', '', $status_concat) == '');
				$all1s = (str_replace('1', '', $status_concat) == '');
				$all2s = (str_replace('2', '', $status_concat) == '');
				$form_has_mixed_statuses = (count($this_status_array) > 1 && !($all0s || $all1s || $all2s));
				// Set array of values to single value
				if ($all0s) {
					$this_status_array = '0';
				} elseif ($all1s) {
					$this_status_array = '1';
				} elseif ($all2s) {
					$this_status_array = '2';
				}
			} else {
				$this_status_array = array_pop($this_status_array);
			}
			// Mixed status icon
			if ($form_has_mixed_statuses) {
				$img = 'circle_blue_stack.png';
			} else {
				// If it's a survey response, display different icons
				if (isset($surveyResponses[$this_record][$attr['event_id']][$attr['form_name']][1])) {
					//Determine color of button based on response status
					switch ($surveyResponses[$this_record][$attr['event_id']][$attr['form_name']][1]) {
						case '2':
							$img = ($form_has_multiple_instances) ? 'circle_green_tick_stack.png' : 'circle_green_tick.png';
							break;
						default:
							$img = ($form_has_multiple_instances) ? 'circle_orange_tick_stack.png' : 'circle_orange_tick.png';
					}
				} else {
					// Set image HTML
					if ($this_status_array == '2') {
						$img = ($form_has_multiple_instances) ? 'circle_green_stack.png' : 'circle_green.png';
					} elseif ($this_status_array == '1') {
						$img = ($form_has_multiple_instances) ? 'circle_yellow_stack.png' : 'circle_yellow.png';
					} elseif ($this_status_array == '0') {
						$img = ($form_has_multiple_instances) ? 'circle_red_stack.png' : 'circle_red.png';
					} else {
						$img = 'circle_gray.png';
					}
				}
			}
			// If locked and/or e-signed, add icon
			$lockimg = $esignimg = "";
			if ($hasRepeatingFormsEvents) {
				$locked_instances = $locked_records[$this_record][$attr['event_id']][$attr['form_name']];
				$esigned_instances = $esigned_records[$this_record][$attr['event_id']][$attr['form_name']];
				if (isset($locked_instances)) {
					$lockimg = (count($locked_instances) > 1) ? $lockimgMultipleStatic : $lockimgStatic;
				}
				if (isset($esigned_instances)) {
					$esignimg = (count($esigned_instances) > 1) ? $esignimgMultipleStatic : $esignimgStatic;
				}
			} else {
				if (isset($locked_records[$this_record][$attr['event_id']][$attr['form_name']]))  $lockimg = $lockimgStatic;
				if (isset($esigned_records[$this_record][$attr['event_id']][$attr['form_name']])) $esignimg = $esignimgStatic;
			}
			$lockingEsignImg = $lockimg . $esignimg; 
			// Set icon style
			$statusIconStyle = ($form_has_multiple_instances) ? 'width:22px;' : 'width:16px;margin-right:6px;';
			// Get highest instance number
			$highest_instance = max(array_keys($rec_attr[$attr['event_id']][$attr['form_name']]));
			if (empty($highest_instance)) $highest_instance = '1';
			// If this is a repeating form, then add a + button to add new instance
			$addRptBtn = '';
			if ($Proj->isRepeatingForm($attr['event_id'], $attr['form_name'])) {
				// Get next instance number
				$next_instance = $highest_instance + 1;
				// Display button
				$this_url = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".urlencode(removeDDEending($this_record))."&event_id={$attr['event_id']}&page={$attr['form_name']}";
				$addRptBtn = "<button title='".js_escape($lang['grid_43'])."' onclick=\"window.location.href='$this_url&instance=$next_instance';\" class='btn btn-defaultrc btnAddRptEv ".($this_status_array == '' ? "invis" : "opacity50")."'>+</button>";
				// Locking/esign icon
				if ($lockingEsignImg != '') $lockingEsignImg = trim(RCView::span(array('class'=>'lockEsignIcons nowrap'), $lockingEsignImg));
			}
			// If has multiple statuses (blue), add click event to open table to see all instances
			if ($form_has_multiple_instances) {
				$href = "javascript:;";
				$onclick = "onclick=\"loadInstancesTable(this,'".js_escape($this_record)."', {$attr['event_id']}, '{$attr['form_name']}');\"";
			} else {
				$href = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".removeDDEending($this_record)."&page={$attr['form_name']}&event_id={$attr['event_id']}&instance=$highest_instance";
				$onclick = "";
			}
			// Add cell
			$td = "$lockingEsignImg<a href='$href' $onclick style='text-decoration:none;'><img src='".APP_PATH_IMAGES."$img' class='fstatus' style='$statusIconStyle' $iconOnlick></a>$addRptBtn";
		//}
		// Determine grouping class (longitudinal only)
		if ($longitudinal) {
			if ($dashboard['group_by'] == 'event') {
				$grouping_class = ($prev_event != $attr['event_id'] ? 'rsd-left' : '');
			} else {
				$grouping_class = ($prev_form != $attr['form_name'] ? 'rsd-left' : '');
			}
		} else {
			$grouping_class = '';
		}
		// Add column to row
		$this_row .= RCView::td(array('class'=>$grouping_class, 'style'=>'text-align:center;'), $td);
		$prev_form = $attr['form_name'];
		$prev_event = $attr['event_id'];
	}
	$rowclass = ($rowclass == "even") ? "odd" : "even";
	$rows .= RCView::tr(array('class'=>$rowclass), $this_row);
}


// Get all repeating events
$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
$hasRepeatingForms = $Proj->hasRepeatingForms();
$hasRepeatingEvents = $Proj->hasRepeatingEvents();
$hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);



// Page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// JavaScript
loadJS('RecordDashboard.js');
?>
<style type="text/css">
a.statuslink_selected { color:#777; }
a.statuslink_unselected { text-decoration:underline; }
.lock, .esign { display:none;margin:1px 1px 2px 1px; }
.lockEsignIcons { margin-right:4px;display:none; }
table.dataTable tbody tr td, table#record_status_table tbody tr td { padding: 3px !important; white-space: nowrap !important; }
table.dataTable thead tr th {
	background-color: #FFFFE0;
	border-top: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
table.dataTable.cell-border thead tr th {
	border-right: 1px solid #ddd;
}
table.dataTable.cell-border thead tr th:first-child {
    border-left: 1px solid #ddd;
}
#record_status_table { border-bottom:1px solid #ccc; }
#dashboard-config textarea {
	max-height: 300px;
}
#dashboard-config .td1 {
	font-weight:bold;
	padding:3px 15px 3px 10px;vertical-align:top;
	width:30%;
}
#dashboard-config .td2 {
	padding:3px 0;vertical-align:top;
	width:70%;
}
#choose_select_forms_events_div {
	min-width:280px;
	background: transparent url(<?php print APP_PATH_IMAGES ?>upArrow.png) no-repeat center top;
	position:absolute;
	z-index:10;
	padding:9px 0 0;
	display: none;
	font-size:11px;
}
#choose_select_forms_events_div_sub {
	background-color: #fafafa;
	padding:3px 6px 10px;
	border:1px solid #000;
	overflow-y: scroll;
	max-height: 400px;
}
#choose_select_forms_events_table tr td { vertical-align:top; padding:2px 5px 4px 1px; }
.hangevl { text-indent: -1.5em; margin-left: 3.2em; }
.hangevc { text-indent: -1.5em; margin-left: 1.8em; }
#selected_forms_events { cursor: pointer; cursor: hand; border:none; background-color: transparent; width:95%; max-width:385px;overflow:hidden;white-space: nowrap;text-overflow: ellipsis; }
</style>
<?php

// Page title
renderPageTitle("<i class=\"fas fa-th\"></i> $title");
// Instructions and Legend for colored status icons
print	RCView::table(array('class'=>'d-none d-sm-block', 'style'=>'max-width:950px;table-layout:fixed;'.($instructions == '' ? 'margin-top:-25px;' : '')),
			RCView::tr('',
				RCView::td(array('class'=>'col-8', 'style'=>'vertical-align:bottom;padding:10px 10px 10px 0;'),
					// Instructions
					$instructions					
				) .
				RCView::td(array('id'=>'rsd_legend_td', 'style'=>(is_numeric($rd_id) ? 'vertical-align:top;' : 'vertical-align:bottom;').''.($hasRepeatingFormsOrEvents && $surveys_enabled ? 'width:400px;' : 'width:320px;')),
					// "Show legend" link (if hidden in custom dashboard)
					(!is_numeric($rd_id) ? '' :
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;display:block;margin:10px 0 0 50px;', 
							'onclick'=>"$(this).remove();$('#rsd_legend_td').css('vertical-align','bottom');$('#rsd_legend').show();"), $lang['data_entry_353'])
					) .
					// Legend
					RCView::div(array('id'=>'rsd_legend', 'class'=>'chklist', 'style'=>(is_numeric($rd_id) ? 'display:none;' : '').'background-color:#eee;border:1px solid #ccc;'),
						RCView::table(array('id'=>'status-icon-legend'),
							RCView::tr('',
								RCView::td(array('colspan'=>'2', 'style'=>'font-weight:bold;'),
									$lang['data_entry_178']
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_red.png')) . $lang['global_92']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									RCView::img(array('src'=>'circle_gray.png')) . $lang['global_92'] . " " . $lang['data_entry_205'] .
									RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['global_58'], 'onclick'=>"simpleDialog('".js_escape($lang['data_entry_232'])."','".js_escape($lang['global_92'] . " " . $lang['data_entry_205'])."');"), '?')
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_yellow.png')) . $lang['global_93']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									($surveys_enabled 
										? RCView::img(array('src'=>'circle_orange_tick.png')) . $lang['global_95']
										: (!$hasRepeatingFormsOrEvents ? "" :
											(RCView::img(array('src'=>'circle_green_stack.png')) . RCView::img(array('src'=>'circle_yellow_stack.png', 'style'=>'position:relative;left:-6px;')) . 
											RCView::img(array('src'=>'circle_red_stack.png', 'style'=>'position:relative;left:-12px;')) . 
											RCView::span(array('style'=>'position:relative;left:-12px;'), $lang['data_entry_282'])))
									)
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_green.png')) . $lang['survey_28']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									($surveys_enabled 
										? RCView::img(array('src'=>'circle_green_tick.png')) . $lang['global_94']
										: (!$hasRepeatingFormsOrEvents ? "" : RCView::img(array('src'=>'circle_blue_stack.png')) . $lang['data_entry_281'])
									)
								)
							) .
							( !($hasRepeatingFormsOrEvents && $surveys_enabled) ? "" :
								RCView::tr('',
									RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
										RCView::img(array('src'=>'circle_blue_stack.png')) . $lang['data_entry_281']
									) .
									RCView::td(array('class'=>'nowrap', 'style'=>''),
										RCView::img(array('src'=>'circle_green_stack.png')) . RCView::img(array('src'=>'circle_yellow_stack.png', 'style'=>'position:relative;left:-6px;')) . 
										RCView::img(array('src'=>'circle_red_stack.png', 'style'=>'position:relative;left:-12px;')) . 
										RCView::span(array('style'=>'position:relative;left:-12px;'), $lang['data_entry_282'])
									)
								)
							)
						)
					)
				)
			)
		);
print	$dashboardOptionsBox;

// User defines the Record ID
$autoIdBtnText = $auto_inc_set ? $lang['data_entry_46'] : $lang['data_entry_443'];
if ($multiple_arms) {
    $autoIdBtnText .= " " . $lang['data_entry_442'];
}
if (!$auto_inc_set && $user_rights['record_create'] > 0)
{
    // Check if record ID field should have validation
    $text_val_string = "";
    if ($Proj->metadata[$table_pk]['element_type'] == 'text' && $Proj->metadata[$table_pk]['element_validation_type'] != '')
    {
        // Apply validation function to field
        $text_val_string = "if(redcap_validate(this,'{$Proj->metadata[$table_pk]['element_validation_min']}','{$Proj->metadata[$table_pk]['element_validation_max']}','hard','".convertLegacyValidationType($Proj->metadata[$table_pk]['element_validation_type'])."',1)) ";
    }
    //Text box for next records
    ?>
    <div class="input-group mb-4">
        <input id="inputString" type="text" placeholder="<?php echo js_escape2($autoIdBtnText) ?>" class="fs13 x-form-text x-form-field" style="width:<?=($multiple_arms ? '250' : '180')?>px;">
        <div class="input-group-append">
            <button class="btn btn-xs btn-rcgreen fs13" onclick="var ob=$('#inputString');ob.trigger('blur');if(ob.val().trim()=='')ob.focus();"><i class="fas fa-plus"></i> <?=$lang['design_248']?></button>
        </div>
    </div>
    <script type="text/javascript">
        // Enable validation and redirecting if hit Tab or Enter
        $(function(){
            $('#inputString').keypress(function(e) {
                if (e.which == 13) {
                    $('#inputString').trigger('blur');
                    return false;
                }
            });
            $('#inputString').blur(function() {
                var refocus = false;
                var idval = trim($('#inputString').val());
                if (idval.length < 1) {
                    return;
                }
                if (idval.length > 100) {
                    refocus = true;
                    alert('<?php echo remBr($lang['data_entry_186']) ?>');
                }
                if (refocus) {
                    setTimeout(function(){document.getElementById('inputString').focus();},10);
                } else {
                    $('#inputString').val(idval);
                    <?php echo isset($text_val_string) ? $text_val_string : ''; ?>
                    setTimeout(function(){
                        idval = $('#inputString').val();
                        idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
                        var validRecordName = recordNameValid(idval);
                        if (validRecordName !== true) {
                            $('#inputString').val('');
                            alert(validRecordName);
                            $('#inputString').focus();
                            return false;
                        }
                        // Redirect, but NOT if the validation pop-up is being displayed (for range check errors)
                        if (!$('.simpleDialog.ui-dialog-content:visible').length)
                            window.location.href = app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&arm=<?php echo getArm() ?>&id=' + idval;
                    },200);
                }
            });
        });
    </script>
    <?php
}
// Auto-number button(s) - if option is enabled
elseif ($auto_inc_set && $user_rights['record_create'] > 0)
{
    ?>
    <div class="mb-3">
        <!-- New record button -->
        <button class="btn btn-xs btn-rcgreen fs13" onclick="window.location.href=app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&id=<?php echo DataEntry::getAutoId() ?>&auto=1&arm=<?php echo getArm() ?>';"><i class="fas fa-plus"></i> <?php echo $autoIdBtnText ?></button>
    </div>
    <?php
}

// Options to view locking and/or esignature status
print 	(!($displayLocking || $displayEsignature) ? '' :
			RCView::div(array('style'=>'margin-bottom:10px;color:#888;'),
				RCView::span(array('style'=>'font-weight:bold;margin-right:10px;color:#000;'), $lang['data_entry_225']) .
				// Instrument status only
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_selected', 'onclick'=>"changeLinkStatus(this);$('.esign, .lock, .lockEsignIcons').hide();$('.fstatus, .btnAddRptEv').show();"),
					 $lang['data_entry_226']) .
				// Lock only
				(!$displayLocking ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus, .btnAddRptEv, .esign').hide();$('.lock, .lockEsignIcons').show();"),
						 $lang['data_entry_227'])
					) .
				// Esign only
				(!$displayEsignature ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus, .btnAddRptEv, .lock').hide();$('.esign, .lockEsignIcons').show();"),
						 $lang['data_entry_228'])
				) .
				// Esign + Locking
				(!($displayLocking && $displayEsignature) ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus, .btnAddRptEv').hide();$('.lock, .esign, .lockEsignIcons').show();"),
						 $lang['data_entry_230'])
				) .
				// All types
				RCView::SP . " | " . RCView::SP .
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus, .lock, .esign, .lockEsignIcons, .btnAddRptEv').show();"),
					 $lang['data_entry_229'])
			)
		);

// Display Arm number tab
if ($showArmTabs) 
{
	print '<div id="sub-nav" class="clearfix" style="margin-top:5px;margin-bottom:10px;max-width:750px;"><ul>';
	//Loop through each ARM and display as a tab
	foreach ($Proj->events as $this_arm=>$attr) {
		//Render tab
		print '<li'.($this_arm == $_GET['arm'] ? ' class="active"' : '')
			. '><a style="font-size:12px;color:#393733;padding:5px 5px 5px 11px;" href="javascript:;" onclick="window.location.href=window.location.href+\'&arm='.$this_arm.'\';"'
			. '>'.$lang['global_08'].' '.$this_arm.$lang['colon']
			. RCView::span(array('style'=>'margin-left:6px;font-weight:normal;color:#800000;'), RCView::escape(strip_tags($attr['name']))).'</a></li>';
	}
	print  '</ul></div>';
}

print "<table id='record_status_table' class='dataTable cell-border' style='clear:both;'>$rows</table>";

// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$DDP->renderJsAdjudicationPopup('');
}

// Page footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';