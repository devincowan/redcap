<?php

/**
 * DataEntry
 * This class is used for processes related to general data entry.
 */
class DataEntry
{
	// Set maximum number of records before the record selection drop-downs disappear on Data Entry Forms
	public static $maxNumRecordsHideDropdowns = 25000;

	// Set default codes for missing data codes
	public static $defaultMissingDataCodes =   'NI,No information
                                                INV,Invalid
                                                UNK,Unknown
                                                NASK,Not asked
                                                ASKU,Asked but unknown
                                                NAV,Temporarily unavailable
                                                MSK,Masked
                                                NA,Not applicable
                                                NAVU,Not available
                                                NP,Not present
                                                QS,Sufficient quantity
                                                TRC,Trace
                                                UNC,Unencoded
                                                DER,Derived
                                                PINF,Positive infinity
                                                NINF,Negative infinity
                                                OTH,Other';
	
	// Return HTML for rendering the button-icons for collapsing tables on Record Home page (maybe elsewhere)
	public static function renderCollapseTableIcon($project_id, $tableid)
	{
		global $lang;
		// Get current collapsed state
		$collapsed = UIState::isTableCollapsed($project_id, $tableid);
		$collapsed_attr = $collapsed ? '1' : '0';
		$collapsed_class = $collapsed ? 'btn-primaryrc' : 'opacity50';
		// Return button-icon html
		return "<button targetid='$tableid' collapsed='$collapsed_attr' class='btn btn-defaultrc $collapsed_class btn-xs btn-table-collapse' title='".js_escape($lang['grid_48'])."'>
					<img src='".APP_PATH_IMAGES."arrow_state_grey_expanded_sm.png'>
				</button>";
	}
	
	// Return HTML for rendering the button-icons for collapsing event columns on Record Home page
	public static function renderCollapseEventColumnIcon($project_id, $eventid)
	{
		global $lang;
		// Get current collapsed state
		$collapsed = UIState::isEventColumnCollapsed($project_id, $eventid);
		$collapsed_attr = $collapsed ? '1' : '0';
		$collapsed_class = $collapsed ? 'btn-warning' : 'opacity50';
		$glyphicon_class = $collapsed ? 'fas fa-forward' : 'fas fa-backward';
		// Return button-icon html
		return "<button targetid='$eventid' collapsed='$collapsed_attr' class='btn btn-defaultrc $collapsed_class btn-xs btn-event-collapse' title='".js_escape($lang['grid_49'])."'>
					<span class='$glyphicon_class'></span>
				</button>";
	}
	
	// Return HTML for rendering the Record Home Page
	public static function renderRecordHomePage()
	{
		extract($GLOBALS);
		## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
		System::killConcurrentRequests(5, 3);
		// Auto-number logic (pre-submission of new record)
		if ($auto_inc_set) {
			// If the auto-number record selected has already been created by another user, fetch the next one to prevent overlapping data
			if (isset($_GET['id']) && isset($_GET['auto']) && $_GET['auto'] != '2') {
				$q = db_query("select 1 from redcap_data where project_id = $project_id and record = '".db_escape($_GET['id'])."' and field_name = '{$Proj->table_pk}' limit 1");
				if (db_num_rows($q) > 0) {
					// Record already exists, so redirect to new page with this new record value
					redirect(PAGE_FULL . "?pid=$project_id&page={$_GET['page']}&auto=2&id=" . DataEntry::getAutoId(PROJECT_ID, false) . (isset($_GET['arm']) ? "&arm=".$_GET['arm'] : ""));
				}
			}
		}
		//Get arm number from URL var 'arm'
		$arm = getArm();
		// Reload page if id is a blank value
		if (isset($_GET['id']) && trim($_GET['id']) == "")
		{
			redirect(PAGE_FULL . "?pid=" . PROJECT_ID . "&page=" . $_GET['page'] . "&arm=" . $arm);
		}
		// Clean id
		if (isset($_GET['id'])) {
			$_GET['id'] = strip_tags(label_decode($_GET['id']));
		}
		// Header
		if (isset($_GET['id'])) {
			renderPageTitle("<i class=\"fas fa-columns\"></i> ".$lang['grid_42']);
		} else {
			// Hook: redcap_add_edit_records_page
			Hooks::call('redcap_add_edit_records_page', array(PROJECT_ID, null, null));
			renderPageTitle("<i class=\"fas fa-file-alt\"></i> " . ($user_rights['record_create'] ? $lang['bottom_62'] : $lang['bottom_72']));
		}
		//Custom page header note
		if (trim($custom_data_entry_note) != '') {
			print "<br><div class='green' style='font-size:11px;'>" . nl2br(decode_filter_tags($custom_data_entry_note)) . "</div>";
		}
		// Get all repeating events
		$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
		$hasRepeatingForms = $Proj->hasRepeatingForms();
		$hasRepeatingEvents = $Proj->hasRepeatingEvents();
		$hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);
		//Alter how records are saved if project is Double Data Entry (i.e. add --# to end of Study ID)
		if ($double_data_entry && $user_rights['double_data'] != 0) {
			$entry_num = "--" . $user_rights['double_data'];
		} else {
			$entry_num = "";
		}
		## GRID
		if (isset($_GET['id']))
		{
			## If study id has been entered or selected, display grid.
			//Adapt for Double Data Entry module
			if ($entry_num == "") {
				//Not Double Data Entry or this is Reviewer of Double Data Entry project
				$id = $_GET['id'];
			} else {
				//This is #1 or #2 Double Data Entry person
				$id = $_GET['id'] . $entry_num;
			}
			$sql = "select d.record from redcap_events_metadata m, redcap_events_arms a, redcap_data d where a.project_id = $project_id
					and a.project_id = d.project_id and m.event_id = d.event_id and a.arm_num = $arm and a.arm_id = m.arm_id
					and d.record = '".db_escape($id)."' limit 1";
			$q = db_query($sql);
			$row_num = db_num_rows($q);
			$existing_record = ($row_num > 0);
			
			// If NOT an existing record AND project has only ONE FORM, then redirect to the first viable form
			if (!$existing_record && !$longitudinal && count($Proj->forms) == 1 && $user_rights['forms'][$Proj->firstForm] > 0) 
			{
				redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=" . $Proj->firstForm . "&id=" . $_GET['id']
						. ($auto_inc_set ? "&auto=1" : ""));
			} 
			elseif (!$existing_record && $longitudinal) 
			{
				$viableEventsThisArm = array_intersect_key($Proj->eventsForms, $Proj->events[$arm]['events']);
				if (count($viableEventsThisArm) == 1) {
					$thisArmFirstEventId = array_shift(array_keys($viableEventsThisArm));
					$formsThisArmFirstEventId = array_shift($viableEventsThisArm);
					if (count($formsThisArmFirstEventId) == 1) {
						$firstFormThisArm = array_shift($formsThisArmFirstEventId);
						redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=$firstFormThisArm&id=" . $_GET['id']
								. "&event_id=$thisArmFirstEventId" . ($auto_inc_set ? "&auto=1" : ""));
					}
				}
			}
			
			## LOCK RECORDS & E-SIGNATURES
			// For lock/unlock records feature, show locks by any forms that are locked (if a record is pulled up on data entry page)
			$locked_forms = $locked_forms_grid = $esigned_forms_grid = array();
			$qsql = "select event_id, form_name, instance, timestamp from redcap_locking_data 
					where project_id = $project_id and record = '" . db_escape($id). "'";
			if ($longitudinal && isset($Proj->events[$arm])) {
				$qsql .= " and event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")";
			} else {
				$qsql .= " and event_id = " . $Proj->firstEventId;
			}
			$q = db_query($qsql);
			while ($row = db_fetch_array($q)) {
				$this_lock_ts = " <img class='gridLockIcon' src='".APP_PATH_IMAGES."lock_small.png' title='".js_escape($lang['bottom_59'])." ".DateTimeRC::format_ts_from_ymd($row['timestamp'])."'>";
				$locked_forms[$row['event_id'].",".$row['form_name'].",".$row['instance']] = $this_lock_ts;
				if ($hasRepeatingForms && $Proj->isRepeatingForm($row['event_id'], $row['form_name']) && isset($locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']])) {
					$locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']] = " <img class='gridLockIcon' src='".APP_PATH_IMAGES."locks_small.png' title='".js_escape($lang['data_entry_283'])."'>";
				}
				if (!isset($locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']])) {
					$locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']] = $this_lock_ts;
				}
			}
			// E-signatures
			$qsql = "select event_id, form_name, instance, timestamp from redcap_esignatures 
					where project_id = $project_id and record = '" . db_escape($id). "'";
			if ($longitudinal && isset($Proj->events[$arm])) {
				$qsql .= " and event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")";
			} else {
				$qsql .= " and event_id = " . $Proj->firstEventId;
			}
			$q = db_query($qsql);
			while ($row = db_fetch_array($q)) {
				$this_esign_ts = " <img class='gridEsignIcon' src='".APP_PATH_IMAGES."tick_shield_small.png' title='".js_escape($lang['data_entry_224'])." ".DateTimeRC::format_ts_from_ymd($row['timestamp'])."'>";
				if (isset($locked_forms[$row['event_id'].",".$row['form_name'].",".$row['instance']])) {
					$locked_forms[$row['event_id'].",".$row['form_name'].",".$row['instance']] .= $this_esign_ts;
				} else {
					$locked_forms[$row['event_id'].",".$row['form_name'].",".$row['instance']] = $this_esign_ts;
				}
				if ($hasRepeatingForms && $Proj->isRepeatingForm($row['event_id'], $row['form_name']) && isset($esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']])) {
					$esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']] = " <img class='gridEsignIcon' src='".APP_PATH_IMAGES."tick_shields_small.png' title='".js_escape($lang['data_entry_284'])."'>";
				}
				if (!isset($esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']])) {
					$esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$row['instance']] = $this_esign_ts;
				}
			}
			//Check if record exists in another group, if user is in a DAG
			if ($user_rights['group_id'] != "" && $existing_record)
			{
				$q = db_query("select 1 from redcap_data where project_id = $project_id and record = '".db_escape($id)."' and
								  field_name = '__GROUPID__' and value = '{$user_rights['group_id']}' limit 1");
				if (db_num_rows($q) < 1) {
					//Record is not in user's DAG
					print  "<div class='red'>
								<img src='".APP_PATH_IMAGES."exclamation.png'>
								<b>{$lang['global_49']} ".$_GET['id']." {$lang['grid_13']}</b><br><br>
								{$lang['grid_14']}<br><br>
								<a href='".APP_PATH_WEBROOT."DataEntry/record_home.php?pid=$project_id' style='text-decoration:underline'><< {$lang['grid_15']}</a>
								<br><br>
							</div>";
					include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
					exit;
				}
			}
			## If new study id, give some brief instructions above normal instructions.
			if (!$existing_record) {
				print  "<p style='max-width:800px;margin-top:15px;color:#008000;'>
							<img src='".APP_PATH_IMAGES."add.png'>
							<span style='vertical-align:middle;'>
								<b>{$lang['global_49']} \"{$_GET['id']}\" {$lang['grid_16']} ".RCView::escape($table_pk_label)."{$lang['period']}</b> {$lang['grid_47']}
							</span>
						</p>";
			}
			// PIPING for Custom Event Label
			$event_label_piping_data = array();
			$custom_event_labels_all = "";
			if ($existing_record && $Proj->longitudinal) {
				// Gather all custom event label fields
				$custom_event_labels_all = "";
				foreach ($Proj->eventInfo as $attr) {
					$custom_event_labels_all .= " " . $attr['custom_event_label'];
				}
				$custom_event_labels_all = trim($custom_event_labels_all);
				$custom_event_label_fields = array_keys(getBracketedFields($custom_event_labels_all, true, false, true));
				$event_label_piping_data = Records::getData('array', $id, $custom_event_label_fields);
			}
			
			// Get array of DAGs
			$dags = $Proj->getGroups();
			
			$true_record = addDDEending($_GET['id']);
			
			## RECORD ACTIONS (locking, delete record, etc.)
			if ($existing_record)
			{
				// Get the record's DAG assignment, if applicable
				$recordDag = empty($dags) ? false : Records::getRecordGroupId($project_id, $true_record);
				// Customize prompt message for deleting record button
				$delAlertMsg = $lang['data_entry_188'];
				if ($longitudinal) {
					$delAlertMsg .= " <b>".$lang['data_entry_51'];
					if ($multiple_arms) {
						$delAlertMsg .= " ".$lang['data_entry_52'];
					}
					$delAlertMsg .= $lang['period']."</b>";
				} else {
					$delAlertMsg .= " <b>".$lang['data_entry_189']."</b>";
				}
				$delAlertMsg .= RCView::div(array('style'=>'margin-top:15px;color:#C00000;font-weight:bold;'), $lang['data_entry_190']);
				if ($allow_delete_record_from_log) {
					$delAlertMsg .= RCView::div(array('style'=>'padding:5px;padding-left: 25px;border:1px solid #eee;background-color:#fafafa;text-indent: -1.4em;margin-top:20px;color:#555;'), 
										RCView::checkbox(array('id'=>'allow_delete_record_from_log', 'signed'=>'0', 'onclick'=>"openDeleteRecordLogDialog();")) . 
										RCView::b($lang['data_entry_436']) . RCView::br() . $lang['data_entry_437']
									);
					print 	RCView::div(array('class'=>'simpleDialog', 'title'=>$lang['data_entry_439'], 'id'=>'allow_delete_record_from_log_confirm'), 
								$lang['data_entry_438'] . RCView::br() . RCView::br() .
								RCView::text(array('id'=>'allow_delete_record_from_log_delete'))
							);
				}
				// Is whole record locked?
				$locking = new Locking();
	            $wholeRecordIsLocked = $locking->isWholeRecordLocked($project_id, $true_record, getArm());
				// Action drop-down
				$actions =  RCView::div(array('style'=>'margin:12px 0 8px;'),
								RCView::button(array('id'=>'recordActionDropdownTrigger', 'onclick'=>"showBtnDropdownList(this,event,'recordActionDropdownDiv');", 'class'=>'jqbuttonmed'),
									RCView::span(array('class'=>'fas fa-edit', 'style'=>'color:#000066;top:3px;'), '') .
									RCView::span(array('style'=>'vertical-align:middle;color:#000066;margin-right:6px;margin-left:3px;'), $lang['grid_51']) .
									RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
								) .
								// PDF button/drop-down options (initially hidden)
								RCView::div(array('id'=>'recordActionDropdownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
									RCView::ul(array('id'=>'recordActionDropdown'),
										// ZIP file containing all file upload fields
										(($user_rights['data_export_tool'] == '0' || !Files::hasUploadedFiles()) ? '' :
											RCView::li(array(),
												RCView::a(array('target'=>'_blank', 'style'=>'display:block;', 'href'=>APP_PATH_WEBROOT."DataExport/file_export_zip.php?pid=$project_id&id=".RCView::escape($_GET['id'])),
													RCView::span(array('style'=>'vertical-align:middle;color:#c6a614;'),
                                                        '<i class="fas fa-file-archive"></i> ' .$lang['data_entry_315']
													)
												)
											)
										) .
										// Download PDF of all instruments
										($user_rights['data_export_tool'] == '0' ? '' :
											RCView::li(array(),
												RCView::a(array('target'=>'_blank', 'style'=>'display:block;', 'href'=>APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=$project_id&id=".RCView::escape($_GET['id'])),
													RCView::span(array('style'=>'vertical-align:middle;color:#A00000;'),
                                                        '<i class="fas fa-file-pdf"></i> ' .($longitudinal ? $lang['data_entry_314'] : $lang['data_entry_313'])
													)
												)
											)
										) .
										// Download PDF of all instruments (compact)
										($user_rights['data_export_tool'] == '0' ? '' :
											RCView::li(array(),
												RCView::a(array('target'=>'_blank', 'style'=>'display:block;', 'href'=>APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=$project_id&id=".RCView::escape($_GET['id'])."&compact=1"),
													RCView::span(array('style'=>'vertical-align:middle;color:#A00000;'),
                                                        '<i class="fas fa-file-pdf"></i> ' .($longitudinal ? $lang['data_entry_314'] : $lang['data_entry_313']) . " " . $lang['data_entry_425']
													)
												)
											)
										) .
										// Lock record
										(!($user_rights['lock_record_multiform'] && !$wholeRecordIsLocked) ? '' :
											RCView::li(array(),
												RCView::a(array('href'=>'javascript:;', 'style'=>'display:block;', 'onclick'=>"lockUnlockForms('$id','".RCView::escape($_GET['id'])."','','".$arm."','1','lock');"),
													RCView::span(array('style'=>'vertical-align:middle;color:#A86700;'),
                                                        '<i class="fas fa-lock"></i> '.$lang['bottom_110'])
												)
											)
										) .
										// Unock record
										(!($user_rights['lock_record_multiform'] && $wholeRecordIsLocked) ? '' :
											RCView::li(array(),
												RCView::a(array('href'=>'javascript:;', 'style'=>'display:block;', 'onclick'=>"lockUnlockForms('$id','".RCView::escape($_GET['id'])."','','".$arm."','1','unlock');"),
													RCView::span(array('style'=>'vertical-align:middle;color:#555;'), '<i class="fas fa-unlock"></i> '.$lang['bottom_111'])
												)
											)
										) .
										// Assign to a DAG (only show if DAGs exist and user is NOT in a DAG)
										($user_rights['group_id'] != '' || empty($dags) ? '' :
											RCView::li(array(),
												RCView::a(array('href'=>'javascript:;', 'style'=>'display:block;', 'onclick'=>"assignDag('$recordDag');"),
													RCView::span(array('style'=>'vertical-align:middle;color:#008000;'),
                                                        '<i class="fas fa-users"></i> ' .$lang['data_entry_323'] .
														(!$recordDag ? '' : " ".$lang['data_entry_324'])
													)
												)
											)
										) .
										// Rename record
										(!$user_rights['record_rename'] ? '' :
											RCView::li(array(),
												RCView::a(array('href'=>'javascript:;', 'style'=>'display:block;', 'onclick'=>"renameRecord();"),
													RCView::span(array('style'=>'vertical-align:middle;'),
														'<i class="fas fa-exchange-alt"></i> '.$lang['data_entry_316']
													)
												)
											)
										) .
										// Delete record
										(!$user_rights['record_delete'] ? '' :
											RCView::li(array(),
												RCView::a(array('href'=>'javascript:;', 'style'=>'display:block;', 'onclick'=>"simpleDialog('".str_replace('"', '&quot;', js_escape("<div style='margin:10px 0;font-size:13px;'>$delAlertMsg</div>"))."','".str_replace('"', '&quot;', js_escape("{$lang['data_entry_49']} \"".RCView::escape($_GET['id'])."\"{$lang['questionmark']}"))."',null,550,null,'".js_escape($lang['global_53'])."',function(){ deleteRecord(getParameterByName('id'),getParameterByName('arm')); },'".js_escape($lang['data_entry_49'])."');"),
													RCView::span(array('style'=>'vertical-align:middle;color:#C00000;'),
														'<i class="fas fa-times"></i> '.$lang['data_entry_208']." ".($longitudinal ? $lang['data_entry_245'] : $lang['data_entry_244'])
													)
												)
											)
										)
									)
								)
							);
			}
			
			// Record name dialog
			if ($user_rights['record_rename'] && $existing_record) 
			{
				print 	RCView::div(array('id'=>'rename-record-dialog', 'title'=>$lang['data_entry_316']." \"".RCView::escape($_GET['id'])."\"", 'class'=>'simpleDialog', 'style'=>'font-size:14px;'),
							$lang['data_entry_316'] . " \"<b>".RCView::escape($_GET['id'])."</b>\" " . $lang['data_entry_317'] . 
							RCView::div(array('style'=>'margin:10px 0 2px;'),
								RCView::text(array('id'=>'new-record-name', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:100px;font-size:14px;', 'value'=>$_GET['id']))
							) .
							(!$multiple_arms ? '' :
								RCView::div(array('style'=>'font-size:12px;color:#666;margin:15px 0 0px;'), $lang['data_entry_321'])
							)
						);
			}
			
			// Assign record to DAG dialog
			if ($user_rights['group_id'] == '' && $existing_record && !empty($dags)) 
			{
				print 	RCView::div(array('id'=>'assign-dag-record-dialog', 'title'=>$lang['form_renderer_10'], 'class'=>'simpleDialog', 'style'=>'font-size:14px;'),
							$lang['data_entry_325'] . " \"<b>".RCView::escape($_GET['id'])."</b>\" " . $lang['data_entry_326'] . 
							RCView::div(array('style'=>'margin:10px 0 2px;'),
								RCView::select(array('id'=>'new-dag-record', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:90%;font-size:14px;'), array(''=>$lang['data_access_groups_ajax_23'])+$dags, $recordDag)
							)
						);
			}
			
			?>
			<style type="text/css">
			table.dataTable tbody tr td { padding:3px !important; vertical-align:top; }
			table.dataTable tr td a { text-decoration:none; }
			table.dataTable .surveyLabel { margin:0 4px;color:#888;font-size:10px;font-family:tahoma; }
			table.dataTable .gridLockEsign { text-align:center; }
			table.dataTable .labelform { font-weight:normal;line-height:18px; }
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
			#event_grid_table { border-bottom:1px solid #ccc; }
			</style>
			<?php
			
			## General instructions for grid.
			print	RCView::table(array('style'=>'width:800px;table-layout:fixed;'),
					RCView::tr('',
						RCView::td(array('style'=>'padding:0 30px 0 0;','valign'=>'top'),
							// Instructions
							RCView::div(array('class'=>'d-none d-sm-block', 'style'=>'padding-top:10px'),
								$lang['grid_41'] .
								// (!($longitudinal && $user_rights['design'] && !isset($_SESSION[FhirEhr::$session_data_name])) ? "" : "
								(!($longitudinal && $user_rights['design']) ? "" : "
									{$lang['grid_21']}
									<a href='".APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id'
										style='text-decoration:underline;'>{$lang['global_16']}</a>
									{$lang['global_14']}{$lang['period']}"
								)
							) .
							// Actions for locking
							$actions
						) .
						RCView::td(array('class'=>'d-none d-sm-block', 'valign'=>'top','style'=>($hasRepeatingFormsOrEvents && $surveys_enabled ? 'width:400px;' : 'width:320px;')),
							// Legend
							RCView::div(array('class'=>'chklist','style'=>'background-color:#eee;border:1px solid #ccc;'),
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
			// Check if record exists for other arms, and if so, notify the user (only for informational purposes)
			if (Records::recordExistOtherArms($id, $arm))
			{
				// Record exists in other arms, so give message
				print  "<p class='red' style=''>
							<b>{$lang['global_03']}</b>{$lang['colon']} {$lang['grid_36']} ".RCView::escape($table_pk_label)."
							\"<b>".removeDDEending($id)."</b>\" {$lang['grid_37']}
						</p>";
			}
			// Set up context messages to users for actions performed in longitudinal projects (Save button redirects back here for longitudinals)
			if (isset($_GET['msg']))
			{
				if ($_GET['msg'] == 'edit') {
					if (isset($_GET['edit_id'])) {
						print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'>
								<img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['edit_id'])."</b> {$lang['data_entry_08']}{$lang['period']}
								<b>{$lang['data_entry_415']} <u>".RCView::escape($table_pk_label)." ".RCView::escape($_GET['id'])."</u></b>
							   </div>";
					} else {
						print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_08']}</div>";
					}
				} elseif ($_GET['msg'] == 'add') {
					print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_09']}</div>";
				} elseif ($_GET['msg'] == 'cancel') {
					print "<div class='red' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."exclamation.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_11']}</div>";
				} elseif ($_GET['msg'] == '__rename_failed__') {
					print "<div class='red' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."exclamation.png'> " . RCView::escape($table_pk_label) . " <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_08']}<br/><b>{$lang['data_entry_13']} " . RCView::escape($table_pk_label) . " {$lang['data_entry_15']}</b></div>";
				} elseif ($_GET['msg'] == 'deleteevent') {
					print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_301']}</div>";
				} elseif ($_GET['msg'] == 'deleterecord') {
					print "<div class='red' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."exclamation.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_10']}</div>";
				} elseif ($_GET['msg'] == 'rename') {
					print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_320']}</div>";
				} elseif ($_GET['msg'] == 'assigndag') {
					print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_327']}</div>";
				} elseif ($_GET['msg'] == 'unassigndag') {
					print "<div class='darkgreen' style='margin:10px 0;max-width:580px;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['id'])."</b> {$lang['data_entry_328']}</div>";
				}
			}
			/***************************************************************
			** EVENT-FORM GRID
			***************************************************************/
			$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
			$recordHasRepeatedEvents = false;
			## Query to get all Form Status values for all forms across all time-points. Put all into array for later retrieval.
			// Prefill $grid_form_status array with blank defaults
			$grid_form_status = array();
			foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
				foreach ($these_forms as $this_form) {
					$grid_form_status[$this_event_id][$this_form][1] = '';
				}
			}
			// Query to get resources from table
			$sql = "select d.event_id, d.field_name, d.value, d.instance from redcap_data d 
					where d.project_id = $project_id and d.record = '".db_escape($id)."' 
					and d.field_name in ('".implode("_complete', '", array_keys($Proj->forms))."_complete')";
			if ($longitudinal && isset($Proj->events[$arm])) {
				$sql .= " and d.event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")";
			} else {
				$sql .= " and d.event_id = " . $Proj->firstEventId;
			}
			$sql .= " order by d.record";
			$q = db_query($sql);
			if (!$q) return false;
			while ($row = db_fetch_assoc($q))
			{
				// If record is not in the array yet, prefill forms with blanks
				if (!isset($grid_form_status[$row['event_id']])) {
					foreach ($Proj->eventsForms[$row['event_id']] as $this_form) {
						$grid_form_status[$row['event_id']][$this_form][1] = '';
					}
				}
				// Set form name
				$form = substr($row['field_name'], 0, -9);
				// Add the form values to array (ignore table_pk value since it was only used as a record placeholder anyway)
				if ($hasRepeatingFormsEvents) {
					if ($row['instance'] == '') {
						$row['instance'] = '1';
					} elseif (isset($repeatingFormsEvents[$row['event_id']]) && !is_array($repeatingFormsEvents[$row['event_id']])) {
						$recordHasRepeatedEvents = true;
					}
					$grid_form_status[$row['event_id']][$form][$row['instance']] = $row['value'];
				} else {
					$grid_form_status[$row['event_id']][$form][1] = $row['value'];
				}
			}
			// For last record, check if we have any blank values (gray status icons), and if so, place in arrays
			$grayStatusForms = $grayStatusEvents = array();
			foreach ($grid_form_status as $this_event_id=>$these_forms) {
				foreach ($these_forms as $this_form=>$these_instances) {
					foreach ($these_instances as $this_instance=>$this_value) {
						// If status value is blank, place form/event/record in arrays to query after this
						if ($this_value == '') {
							$grayStatusForms[$this_form] = true;
							$grayStatusEvents[$this_event_id] = true;
						}
					}
				}
			}
			unset($these_forms, $these_instances);
		
			// Now deal with forms with NO STATUS VALUE saved but might have other values for fields in the form (occurs due to data imports)
			if (!empty($grayStatusEvents)) 
			{
				$qsql = "select distinct d.event_id, m.form_name, d.instance
						from redcap_data d, redcap_metadata m where d.project_id = $project_id and d.project_id = m.project_id 
						and m.element_type != 'calc' and m.field_name != '{$Proj->table_pk}' and d.field_name = m.field_name 
						and d.record = '".db_escape($id)."' and m.form_name in (".prep_implode(array_keys($grayStatusForms)).")
						and d.field_name not in ('".implode("_complete', '", array_keys($grayStatusForms))."_complete')";
				if ($longitudinal && isset($Proj->events[$arm])) {
					$qsql .= " and d.event_id in (".prep_implode(array_keys($grayStatusEvents)).")";
				} else {
					$qsql .= " and d.event_id = " . $Proj->firstEventId;
				}
				$q = db_query($qsql);
				while ($row = db_fetch_array($q)) {
					if ($row['instance'] == '') {
						$row['instance'] = '1';
					} elseif (isset($repeatingFormsEvents[$row['event_id']]) && !is_array($repeatingFormsEvents[$row['event_id']])) {
						$recordHasRepeatedEvents = true;
					}
					//Put time-point and form name as array keys with form status as value
					if ($grid_form_status[$row['event_id']][$row['form_name']][$row['instance']] == '') {
						$grid_form_status[$row['event_id']][$row['form_name']][$row['instance']] = '0';
					}
				}
			}

			// Create an array to count the max instances per event
			$instance_count = array();
			// If has repeated events, then loop through all events/forms and sort them by instance
			if ($recordHasRepeatedEvents) {
				// Loop through events
				foreach ($grid_form_status as $this_event_id=>$these_forms) {
				    if (!$Proj->isRepeatingEvent($this_event_id)) continue;
					foreach ($these_forms as $this_form=>$these_instances) {
						$count_instances = count($these_instances);
						if ($count_instances > 1) {
							ksort($these_instances);
							$grid_form_status[$this_event_id][$this_form] = $these_instances;
						}
						// Add form instance
						foreach ($these_instances as $this_instance=>$this_form_status) {
							if (!isset($instance_count[$this_event_id][$this_instance])) {
								$instance_count[$this_event_id][$this_instance] = '';
							}
						}
					}
					// Loop through other remaining forms and seed with blank value
					foreach (array_diff(array_keys($Proj->forms), array_keys($these_forms)) as $this_form) {
						$grid_form_status[$this_event_id][$this_form] = array();
					}
				}
				// Now loop back through and seed all forms so that each event_id has same number of form instances per event
				foreach ($grid_form_status as $this_event_id=>$these_forms) {
                    if (!$Proj->isRepeatingEvent($this_event_id)) continue;
					ksort($instance_count[$this_event_id]);
					foreach ($these_forms as $this_form=>$these_instances) {
						// Seed all defaults for this form
						$grid_form_status[$this_event_id][$this_form] = $instance_count[$this_event_id];
						// Add form instance
						foreach ($these_instances as $this_instance=>$this_form_status) {
							$grid_form_status[$this_event_id][$this_form][$this_instance] = $this_form_status;
						}
					}
				}
			}
			// Determine if any events have no data
			if (!empty($repeatingFormsEvents)) {	
				$eventsNoData = array();
				foreach ($grid_form_status as $this_event_id=>$these_forms) {
					$allInstanceString = "";
					foreach ($these_forms as $this_form=>$these_instances) {
						foreach ($these_instances as $this_instance=>$this_form_status) {
							$allInstanceString .= $this_form_status;
						}
					}
					// If string is blank, then all form statuses are null/empty
					if ($allInstanceString == "") {
						$eventsNoData[$this_event_id] = true;
					}
				}
			}
			// Determine if this record also exists as a survey response for some instruments
			$surveyResponses = array();
			if ($surveys_enabled) {
				$surveyResponses = Survey::getResponseStatus($project_id, $id);
			}
			// Get Custom Record Label and Secondary Unique Field values (if applicable)
			if ($existing_record) {
				$this_custom_record_label_secondary_pk = Records::getCustomRecordLabelsSecondaryFieldAllRecords($true_record, false, $arm, true, '');
				if ($this_custom_record_label_secondary_pk != '') {
					$this_custom_record_label_secondary_pk = "<span style='color:#800000;margin-left:6px;'>$this_custom_record_label_secondary_pk</span>";
				}
			} else {
				$this_custom_record_label_secondary_pk = "";
			}
			// JavaScript
			loadJS('Calendar.js');
			loadJS('RecordHomePage.js');
			// If has multiple arms, then display this arm's name AND display DAG name
			$dagArmDisplay = "";
			$dagDisplay = ($user_right['group_id'] == '' && !empty($dags) && isset($dags[$recordDag])) ? RCView::escape($dags[$recordDag]) : '';
			$armDisplay = (!$multiple_arms ? "" : "{$lang['global_08']} {$arm}{$lang['colon']} ".RCView::escape(strip_tags($Proj->events[$arm]['name'])));
			if ($dagDisplay != "" || $armDisplay != "") {
				$dagArmDisplay = "<div style='font-size:13px;color:#999;'>";
				if ($armDisplay != "") $dagArmDisplay .= "<span class='nowrap' style='color:#916314;margin:0 2px;'>$armDisplay</span>";
				if ($dagDisplay != "" && $armDisplay != "") $dagArmDisplay .= " &mdash; ";
				if ($dagDisplay != "") $dagArmDisplay .= "<span class='nowrap' style='color:#008000;margin:0 2px;'>$dagDisplay</span>";
				$dagArmDisplay .= "</div>";
			}
			// Upcoming calendar events?
			$upcomingCalEventsText = "";
			if ($user_rights['calendar']) {
				$upcomingCalEvents = Calendar::renderUpcomingAgenda(7, $true_record, true);
				if ($upcomingCalEvents > 0) {
					// At least 1 event, so display button
					$upcomingCalEventsText = RCView::button(array('class'=>'btn btn-defaultrc btn-xs rhp_calevents'), 
						RCView::img(array('src'=>'date.png')) . " " . $upcomingCalEvents . " ". ($upcomingCalEvents == '1' ? $lang['calendar_17'] : $lang['calendar_13'])
					);
				// No events in next X days, so don't show anything unless this record has 1 event at any time
				} elseif (Calendar::recordHasCalendarEvents($true_record)) {
					// Tell user that there are no upcoming events				
					$upcomingCalEventsText = RCView::span(array('class'=>'btn-xs rhp_calevents'), $lang['calendar_14']);
				}
			}

			// If DDP is enabled, then display number of items to adjudicate for this record
			if ($existing_record && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir()))) {
				?>
				<script type="text/javascript">
				var record_exists = 1;
				</script>
				<?php $DDP->renderJsAdjudicationPopup($true_record); 
				if (isset($_GET['openDDP'])) { ?>
				<script type="text/javascript">
				$(function() { openAdjudicationDialog(getParameterByName('id')); });
				</script>
				<?php }
			}
			
			// SCHEDULED INVITES: If using designated email field OR participant identifier, then show upcoming scheduled invitations
			$upcomingInvitesText = "";
			if ($existing_record && $surveys_enabled) 
			{
				$SurveyScheduler = new SurveyScheduler();
				$numUpcomingInvites = $SurveyScheduler->getSurveyInvitationLog($true_record, 7, true);
				// At least 1 event, so display button
				if ($numUpcomingInvites) {
					$upcomingInvitesText = RCView::button(array('class'=>'btn btn-defaultrc btn-xs rhp_schedinvites'), 
						RCView::img(array('src'=>'clock_fill.png')) . " $numUpcomingInvites ". ($numUpcomingInvites == '1' ? $lang['survey_1135'] : $lang['survey_1132'])
					);
				}
			}

			// LOCK WHOLE RECORD
			$wholeRecordIsLockedDisplay = '';
			if ($existing_record)
			{
				$locking = new Locking();
	            $wholeRecordIsLocked = $locking->isWholeRecordLocked(PROJECT_ID, $_GET['id'], getArm());
			    if ($wholeRecordIsLocked) {
                    list ($whole_lock_user, $whole_lock_time) = $locking->getWholeRecordLockTimeUser(PROJECT_ID, $_GET['id'], getArm());
                    $wholelock_user_info = User::getUserInfo($whole_lock_user);
                    $wholeRecordIsLockedDisplay = RCView::img(array('src'=>'lock_big.png', 'style'=>'width:18px;height:18px;position:relative;top:-2px;', 'title'=>"{$lang['form_renderer_34']} $whole_lock_user ({$wholelock_user_info['user_firstname']} {$wholelock_user_info['user_lastname']}) {$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($whole_lock_time)));
			    }
			}
			
			// DISPLAY RECORD ID above grid
			print  "<div id='record_display_name'>
						<div>
							$wholeRecordIsLockedDisplay " . (!$existing_record ? RCView::b($lang['grid_30']) : "") . "
							".RCView::escape($table_pk_label).RCView::SP.RCView::b(RCView::escape($_GET['id']))."
							$this_custom_record_label_secondary_pk
							$dagArmDisplay
						</div>
						<div style='margin-left:50px;text-align:left;'>
							<div>$upcomingCalEventsText</div>
							<div style='padding-top:2px;'>$upcomingInvitesText</div>
						</div>
					</div>";
			// GRID
			$grid_disp_change = "";
			print  "<table id='event_grid_table' class='dataTable cell-border'>";
			// Display "events" and/or arm name
			print  "<thead><tr>
					<th class='text-center' style='padding:5px 0;'>
						".DataEntry::renderCollapseTableIcon($project_id, 'event_grid_table')."
						<div style='margin:0 25px;'>{$lang['global_35']}</div>
					</th>";
			// Get collapsed state of the event grid and set TR class for each row
			$eventGridCollapseClass = UIState::isTableCollapsed($project_id, 'event_grid_table') ? 'hidden' : '';
			// Collect hidden event columns into an array
			$eventGridEventsCollapsed = $max_event_instances = array();
			//Render table headers
			foreach ($Proj->events[$arm]['events'] as $this_event_id=>$this_event) 
			{
				// Find collapsed state of column
				$eventGridColumnCollapseClass = '';
				if (UIState::isEventColumnCollapsed($project_id, $this_event_id)) {
					$eventGridColumnCollapseClass = 'hidden';
					$eventGridEventsCollapsed[] = $this_event_id;
				}
				// Determine instance info
				$i = 0;
				if (!isset($instance_count[$this_event_id])) {
					$instance_count[$this_event_id][1] = '';
				}
				$is_repeating_event = (isset($repeatingFormsEvents[$this_event_id]) && !is_array($repeatingFormsEvents[$this_event_id]));
				$this_event_instances = array_keys($instance_count[$this_event_id]);
				$max_event_instances[$this_event_id] = $max_event_instance = max($this_event_instances);
				$has_multiple_instances = !empty($this_event_instances);
				foreach ($this_event_instances as $this_instance) 
				{
					// If classic project, then set "event name" to be "status"
					if (!$longitudinal) $this_event['descrip'] = $lang['calendar_popup_08'];
					// Don't display title for repeated events
					$evTitle = ($i > 0) ? "" : "<div class='evTitle'>".RCView::escape(strip_tags($this_event['descrip']))."</div>";
					// Show instance number, if has repeated events
					$instanceNumDisplay = (($is_repeating_event && $max_event_instance > 1) ? "" : "display:none;");
					// Add button to add new instance, if applicable
					$addInstanceBtn = "";
					if ($existing_record && $is_repeating_event && $this_instance == $max_event_instance) {
						$repeatEventBtnDisabled = isset($eventsNoData[$this_event_id]) ? "disabled" : "";
						if (count($this_event_instances) > 2) $addInstanceBtn .= DataEntry::renderCollapseEventColumnIcon(PROJECT_ID, $this_event_id);
						$addInstanceBtn .= "<div class='divBtnAddRptEv'><button $repeatEventBtnDisabled onclick=\"gridAddRepeatingEvent(this);\" event_id='$this_event_id' instance='$this_instance' class='btn btn-xs btn-defaultrc btnAddRptEv nowrap'>+&nbsp;{$lang['data_entry_247']}</button></div>";
					}
					// Event label piping
					$custom_event_label = "";
					if ($custom_event_labels_all != "") {
						$custom_event_label = Piping::replaceVariablesInLabel($Proj->eventInfo[$this_event_id]['custom_event_label'], $id, $this_event_id, $this_instance, $event_label_piping_data, false, null, false);
						$custom_event_label = str_replace("-", "&#8209;", $custom_event_label); // Replace hyphens with non-breaking hyphens for better display
						$custom_event_label = RCView::div(array('class'=>'custom_event_label'), filter_tags($custom_event_label));
					}
					// Set class for repeating events (add class for all instances EXCEPT first and last)
					$repeatEventColClass = ($is_repeating_event && $this_instance > 1 && $this_instance < $max_event_instance) ? "eventCol-$this_event_id $eventGridColumnCollapseClass" : "";
					// Output header
					print  "<th class='evGridHdr $repeatEventColClass $fade'>
								{$addInstanceBtn}{$evTitle}{$custom_event_label}
								<div class='evGridHdrInstance evGridHdrInstance-$this_event_id nowrap' style='$instanceNumDisplay'>
									(#<span class='instanceNum'>$this_instance</span>)
								</div>
							</th>";
					$i++;
					// If this is not a repeating event (=repeat entire event), then only do one loop
					if (!$is_repeating_event) break;
				}
			}
			print "</tr></thead>";
			// Create array of all events and forms for this arm
			$form_events = array();
			foreach (array_keys($Proj->events[$arm]['events']) as $this_event_id) {
				$form_events[$this_event_id] = (isset($Proj->eventsForms[$this_event_id])) ? $Proj->eventsForms[$this_event_id] : array();
			}
			// Create array of all forms used in this arm (because some may not be used, so we should not display them)
			$forms_this_arm = array();
			foreach ($form_events as $these_forms) {
				$forms_this_arm = array_merge($forms_this_arm, $these_forms);
			}
			$forms_this_arm = array_unique($forms_this_arm);
			
			//Render table rows
			$prev_form = "";
			$row_num = 0;
			$deleteRow = array();
			$grayIconsByEvent = array();	
			$rowclass = "even";
			foreach ($Proj->forms as $form_name=>$attr)
			{
				// If form is not used in this arm, then skip it
				if (!in_array($form_name, $forms_this_arm)) continue;
				// Set vars
				$row['form_name'] = $form_name;
				$row['form_menu_description'] = $attr['menu'];
				// Make sure user has access to this form. If not, then do not display this form's row.
				if ($user_rights['forms'][$row['form_name']] == '0') continue;
				//Deterine if we are starting new row
				if ($prev_form != $row['form_name'])
				{
					$row_num++;
					if ($prev_form != "") print "</tr>";			
					$rowclass = ($rowclass == "even") ? "odd" : "even";
					print "<tr class='$rowclass $eventGridCollapseClass'><td class='labelform'>".RCView::escape($row['form_menu_description']);
					// If instrument is enabled as a survey, then display "(survey)" next to it
					if (isset($Proj->forms[$row['form_name']]['survey_id'])) {
						print RCView::span(array('class'=>'surveyLabel'), $lang['grid_39']);
					}
					print "</td>";
				}
				// Render cells
				foreach ($form_events as $this_event_id=>$eattr)
				{
					$row['event_id'] = $this_event_id;
					// Find collapsed state of column
					$eventGridColumnCollapseClass = in_array($this_event_id, $eventGridEventsCollapsed) ? 'hidden' : '';
					// Add first event instance, if missing
					if (!isset($grid_form_status[$row['event_id']][$row['form_name']])) {
						$grid_form_status[$row['event_id']][$row['form_name']][1] = '';
					}
					// Determine if the entire event is set to repeat
					$is_repeating_event = (isset($repeatingFormsEvents[$this_event_id]) && !is_array($repeatingFormsEvents[$this_event_id]));
					$event_has_repeating_forms = (isset($repeatingFormsEvents[$this_event_id]) && is_array($repeatingFormsEvents[$this_event_id]));
					// Determine if this form for this record has multiple instances saved
					$is_repeating_form = (isset($repeatingFormsEvents[$this_event_id]) && is_array($repeatingFormsEvents[$this_event_id]) && isset($repeatingFormsEvents[$this_event_id][$row['form_name']]));
					$status_concat = trim(implode('', $grid_form_status[$row['event_id']][$row['form_name']]));
					$status_count = strlen($status_concat);
					$form_has_mixed_statuses = $form_has_multiple_instances = ($is_repeating_form && count($grid_form_status[$row['event_id']][$row['form_name']]) > 1 && $status_count > 1);
					if ($form_has_multiple_instances) {
						// Determine if all statuses are same or mixed status values
						$form_has_mixed_statuses = !(str_replace('0', '', $status_concat) == '' || str_replace('1', '', $status_concat) == '' || str_replace('2', '', $status_concat) == '');
					}
					if (!isset($grayIconsByEvent[$row['event_id']])) {
						$grayIconsByEvent[$row['event_id']] = array('form_count'=>0, 'gray_count'=>0);
					}
					// Loop through all instances
					$countEventFormInstance = count($grid_form_status[$row['event_id']][$row['form_name']]);
					foreach ($grid_form_status[$row['event_id']][$row['form_name']] as $this_instance=>$this_form_status) 
					{
						// If the first instance is a placeholder, and other instances exist, then skip this one
						if (!$is_repeating_event && $countEventFormInstance > 1 && $this_instance == '1' && $this_form_status == '') continue;
						// Gray status icon for repeating instruments need to have instance set manually to '1' for some reason (somehow gets mangled upstream - not sure where)
						if (!$is_repeating_event && !$form_has_mixed_statuses && !$form_has_multiple_instances && !is_numeric($this_form_status) && in_array($row['form_name'], $eattr)) {
							$this_instance = '1';
						}
						// Deal with possible missing instances of repeating instruments
						if ($form_has_multiple_instances) {
							$this_form_status = substr($status_concat, 0, 1);
						}
						// Add to $deleteRow for the last row
						if ($row_num == 1) {
							$deleteRow[] = array('event_id'=>$row['event_id'], 'form_name'=>$row['form_name'], 
												 'instance'=>$this_instance, 'max_event_instance'=>$max_event_instances[$row['event_id']]);
						}
						// Change bg color slightly for repeated events
						$repeatEv = ($is_repeating_event && $this_instance > 1) ? "dataEvRpt" : "";
						// Set class for repeating events (add class for all instances EXCEPT first and last)
						$repeatEventColClass = ($is_repeating_event && $this_instance > 1 && $this_instance < max(array_keys($instance_count[$this_event_id])))
							? "eventCol-$this_event_id $eventGridColumnCollapseClass" : "";
						// Render table cell
						print "<td class='nowrap $repeatEventColClass $repeatEv' style='".($longitudinal ? "" : "padding:3px 10px 4px;")."text-align:center;'>";
						if (in_array($row['form_name'], $eattr))
						{
							// Increment total form count per event and also count of gray icons
							$grayIconsByEvent[$row['event_id']]['form_count']++;
							// Many different statuses (for repeating only)
							if ($form_has_mixed_statuses) {
								$this_color = 'circle_blue_stack.png';
							} else {
								// If it's a survey response, display different icons
								if (isset($surveyResponses[$id][$row['event_id']][$row['form_name']][$this_instance])) {
									//Determine color of button based on response status
									switch ($surveyResponses[$id][$row['event_id']][$row['form_name']][$this_instance]) {
										case '2':
											$this_color = ($form_has_multiple_instances) ? 'circle_green_tick_stack.png' : 'circle_green_tick.png';
											break;
										default:
											$this_color = ($form_has_multiple_instances) ? 'circle_orange_tick_stack.png' : 'circle_orange_tick.png';
									}
								} else {
									// Form status
									if ($form_has_multiple_instances) {
										switch ($this_form_status) {
											case '2': 	$this_color = 'circle_green_stack.png';  break;
											case '1': 	$this_color = 'circle_yellow_stack.png'; break;
											default:	$this_color = 'circle_red_stack.png';
										}
									} else {
										switch ($this_form_status) {
											case '2': 	$this_color = 'circle_green.png';  break;
											case '1': 	$this_color = 'circle_yellow.png'; break;
											case '0': 	$this_color = 'circle_red.png';    break;
											default: 	
												$this_color = 'circle_gray.png';
												$grayIconsByEvent[$row['event_id']]['gray_count']++;
										}
									}
								}
							}
							
							//Determine record id (will be different for each time-point). Configure if Double Data Entry
							if ($entry_num == "") {
								$displayid = $id;
							} else {
								//User is Double Data Entry person
								$displayid = $_GET['id'];
							}
							//Set button HTML, but don't make clickable if color is gray
							$statusIconStyle = ($form_has_multiple_instances) ? 'width:22px;' : 'width:16px;';
							if ($event_has_repeating_forms && !$form_has_multiple_instances) $statusIconStyle .= 'margin-right:6px;';
							$this_url = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".urlencode($displayid)."&event_id={$row['event_id']}&page={$row['form_name']}"
									  . ($this_instance > 1 ? "&instance=$this_instance" : "").((isset($_GET['auto']) && $auto_inc_set) ? "&auto=1" : "");
							$this_button = "<img src='".APP_PATH_IMAGES."$this_color' style='height:16px;$statusIconStyle'>";
							// Set link for icon
							$thisPlusBtnUrl = $this_url;
							if ($form_has_multiple_instances) {
								$this_url = 'javascript:;';
								$onclick = "onclick=\"loadInstancesTable(this,'".js_escape($true_record)."', {$row['event_id']}, '{$row['form_name']}');\"";
							} else {						
								$onclick = "";
							}
							print "<a href='$this_url' $onclick>$this_button</a>";
							// If this is a repeating form, then add a + button to add new instance
							$lockingSpacerRepeatForms = '';
							if ($existing_record && $event_has_repeating_forms) {
								// Spacer for locking icons
								$lockingSpacerRepeatForms = "<img src='".APP_PATH_IMAGES."spacer.gif' style='height:16px;width:".(isset($esigned_forms_grid[$row['event_id'].",".$row['form_name']]) ? "12px" : "28px").";'>";
								// Get next instance number
								$next_instance = max(array_keys($grid_form_status[$row['event_id']][$row['form_name']])) + 1;
								// Display button
								print "<button title='".js_escape($lang['grid_43'])."' onclick=\"window.location.href='$thisPlusBtnUrl&instance=$next_instance';\" opacity='50' class='btn btn-defaultrc btnAddRptEv ".($is_repeating_form && $this_color != "circle_gray.png" ? "opacity50" : "invis")."'>+</button>";
							}
							//Display lock icon for any forms that are locked for this record
							if ((isset($locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]) || isset($esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]))) 
							{
								print 	RCView::div(array('class'=>'gridLockEsign nowrap'), 
											(!isset($locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]) ? '' :
												$locked_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]
											) .
											(!isset($esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]) ? '' :
												$esigned_forms_grid[$row['event_id'].",".$row['form_name'].",".$this_instance]
											) .
											$lockingSpacerRepeatForms
										);
							}
						}
						print "</td>";
						// If entire event does not repeat, then only do one loop
						if (!$is_repeating_event) break;
					}
				}
				//Set for next loop
				$prev_form = $row['form_name'];
			}
			print  "</tr>";

			// DELETE EVENT: If user has record delete rights, then add new row to delete events
			if ($longitudinal && $existing_record && $user_rights['record_delete'] && !$wholeRecordIsLocked)
			{
				$rowclass = ($rowclass == "even") ? "odd" : "even";
				print "<tr class='$rowclass $eventGridCollapseClass'><td class='labelform' style='color:#aaa;font-size:10px;'>{$lang['grid_52']}</td>";
				foreach ($deleteRow as $attr)
				{
					$tdLink = "";
					$isRepeatingEvent = $Proj->isRepeatingEvent($attr['event_id']) ? 1 : 0;
					if ($grayIconsByEvent[$attr['event_id']]['form_count'] > $grayIconsByEvent[$attr['event_id']]['gray_count']) {
						// Display cross icon if event has some data saved
						$deleteEventText = $isRepeatingEvent ? $lang['data_entry_298'] : $lang['data_entry_297'];
						// Set cell content
						$tdLink = "<a href='javascript:;' onclick=\"deleteEventInstance({$attr['event_id']},{$attr['instance']},$isRepeatingEvent);\" style='color:#A00000;'><span class='fas fa-times opacity35' style='padding:2px 5px;' title='".js_escape($deleteEventText)."'></span></a>";
					}
					// Find collapsed state of column
					$eventGridColumnCollapseClass = in_array($attr['event_id'], $eventGridEventsCollapsed) ? 'hidden' : '';
					// Set class for repeating events (add class for all instances EXCEPT first and last)
					$repeatEventColClass = ($isRepeatingEvent && $attr['instance'] > 1 && $attr['instance'] < $attr['max_event_instance']) ? "eventCol-{$attr['event_id']} $eventGridColumnCollapseClass" : "";
					// Render the cell
					print  "<td class='$repeatEventColClass' style='text-align:center;'>$tdLink</td>";
				}
				print  "</tr>";
			}
			
			print  "</table>";
			
			// If project has repeating forms, then display tables of their data
			print RepeatInstance::renderRepeatingFormsDataTables($true_record, $grid_form_status, $locked_forms);
		}
		################################################################################
		## PAGE WITH RECORD ID DROP-DOWN
		else
		{
			// Set message to display at top (e.g., if record was just edited)
			if (isset($_GET['msg']))
			{
				if ($_GET['msg'] == 'edit') {
					print "<div class='darkgreen' style='margin:10px 0;'><img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['edit_id'])."</b> {$lang['data_entry_08']}</div>";
				}
				elseif ($_GET['msg'] == 'edit_no_next') {
					print "<div class='darkgreen' style='margin:10px 0;'>
							<img src='".APP_PATH_IMAGES."tick.png'> ".RCView::escape($table_pk_label)." <b>".RCView::escape($_GET['edit_id'])."</b> {$lang['data_entry_08']}{$lang['period']}<br>
							<div style='font-weight:bold;margin-top:7px;'>{$lang['data_entry_411']}</div>
						   </div>";
				}
			}
			
			// Get total record count
			$num_records = Records::getRecordCount(PROJECT_ID);
			// Get extra record count in user's data access group, if they are in one
			if ($user_rights['group_id'] != "")
			{
				$num_records_group = count(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id']));
			}
			// If more records than a set number exist, do not render the drop-downs due to slow rendering.
			$search_text_label = $lang['grid_35'] . " " .RCView::escape($table_pk_label);
			if ($num_records > DataEntry::$maxNumRecordsHideDropdowns)
			{
				// If using auto-numbering, then bring back text box so users can auto-suggest to find existing records	.
				// The negative effect of this is that it also allows users to [accidentally] bypass the auto-numbering feature.
				if ($auto_inc_set) {
					$search_text_label = $lang['data_entry_121'] . " ".RCView::escape($table_pk_label);
				}
				// Give extra note about why drop-down is not being displayed
				$search_text_label .= RCView::div(array('style'=>'padding:10px 0 0;font-size:10px;font-weight:normal;color:#555;'),
										$lang['global_03'] . $lang['colon'] . " " . $lang['data_entry_172'] . " " .
										User::number_format_user(DataEntry::$maxNumRecordsHideDropdowns, 0) . " " .
										$lang['data_entry_173'] . $lang['period']
									);
			}
			/**
			 * ARM SELECTION DROP-DOWN (if more than one arm exists)
			 */
			//Loop through each ARM and display as a drop-down choice
			$arm_dropdown_choices = "";
			if ($multiple_arms) {
				foreach ($Proj->events as $this_arm_num=>$arm_attr) {
					//Render option
					$arm_dropdown_choices .= "<option";
					//If this tab is the current arm, make it selected
					if ($this_arm_num == $arm) {
						$arm_dropdown_choices .= " selected ";
					}
					$arm_dropdown_choices .= " value='$this_arm_num'>{$lang['global_08']} {$this_arm_num}{$lang['colon']} {$arm_attr['name']}</option>";
				}
			}
			// Page instructions and record selection table with drop-downs
			?>
			<p>
				<?php echo $lang['grid_38'] ?>
				<?php echo ($auto_inc_set) ? $lang['data_entry_96'] : $lang['data_entry_97']; ?>
			</p>
			<?php
			//If project is a prototype, display notice for users telling them that no real data should be entered yet.
			if ($status < 1) {
				print  "<div class='yellow' style='width:90%;max-width:700px;margin:15px 0 25px;'>
							<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
							<b style='font-size:14px;'>{$lang['global_03']}{$lang['colon']}</b> {$lang['data_entry_28']}
						</div>";
			}
			?>			
			<style type="text/css">
			.data { padding: 7px; max-width: 400px; }
			</style>
			<table class="form_border" style="width:100%;max-width:700px;margin-top:20px;">
				<!-- Header displaying record count -->
				<tr>
					<td class="header" colspan="2" style="font-weight:normal;padding:10px 5px;color:#800000;font-size:12px;">
						<?php echo $lang['graphical_view_22'] ?> <b><?php echo User::number_format_user($num_records) ?></b>
							<?php if (isset($num_records_group)) { ?>
								&nbsp;/&nbsp; <?php echo $lang['data_entry_104'] ?> <b><?php echo User::number_format_user($num_records_group) ?></b>
							<?php } ?>
					</td>
				</tr>
			<?php
			/***************************************************************
			** DROP-DOWNS
			***************************************************************/
			$dropdownid_disptext = array();
			if ($num_records <= DataEntry::$maxNumRecordsHideDropdowns)
			{
				print  "<tr>
							<td class='labelrc'>{$lang['grid_31']} ".RCView::escape($table_pk_label)."</td>
							<td class='data'>";
				// Obtain custom record label & secondary unique field labels for ALL records.
				$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array(), true, $arm);
				if($extra_record_labels)
				{
					foreach ($extra_record_labels as $this_record=>$this_label) {
						$dropdownid_disptext[removeDDEending($this_record)] .= " $this_label";
					}
				}
				unset($extra_record_labels);
				/**
				 * ARM SELECTION DROP-DOWN (if more than one arm exists)
				 */
				//Loop through each ARM and display as a drop-down choice
				if ($multiple_arms && $arm_dropdown_choices != "")
				{
					print  "<select id='arm_name' class='x-form-text x-form-field' style='margin-right:20px;' onchange=\"
								if ($('#record').val().length > 0) {
									window.location.href = app_path_webroot+'DataEntry/record_home.php?pid=$project_id&id='+$('#record').val()+'&arm='+$('#arm_name').val();
								} else {
									showProgress(1);
									setTimeout(function(){
										window.location.href = app_path_webroot+'DataEntry/record_home.php?pid=$project_id&arm='+$('#arm_name').val();
									},500);
								}
							\">
							$arm_dropdown_choices
							</select>";
				}
				/**
				 * RECORD SELECTION DROP-DOWN
				 */
				$study_id_array = Records::getRecordList($project_id, $user_rights['group_id'], true, false, $arm);
				
				// Custom record ordering is set
				if ($order_id_by != "" && $order_id_by != $table_pk)
				{
					$orderer_arr_getData = array();
					foreach (Records::getData('array', $study_id_array, array($table_pk, $order_id_by), $Proj->firstEventId) as $this_record=>$event_data) {
						$orderer_arr_getData[$this_record] = $event_data[$Proj->firstEventId][$order_id_by];
					}
					natcasesort($orderer_arr_getData);
					$study_id_array = array_keys($orderer_arr_getData);
					unset($orderer_arr_getData);
				}
				// Build record drop-down
				print  "<select id='record' class='x-form-text x-form-field' style='max-width:350px;' onchange=\"
							window.location.href = app_path_webroot+page+'?pid='+pid+'&arm=$arm&id=' + this.value;
						\">";
				print  "	<option value=''>{$lang['data_entry_91']}</option>";
				foreach ($study_id_array as $this_record)
				{
					// Check for custom labels
					$custom_record_text = isset($dropdownid_disptext[$this_record]) ? $dropdownid_disptext[$this_record] : "";
					//Render drop-down options
					print "<option value='$this_record'>".RCView::escape(strip_tags("{$this_record}{$dropdownid_disptext[$this_record]}"))."</option>";
				}
				print  "</select>";
				print  "</td></tr>";
			}
			//User defines the Record ID
			if ((!$auto_inc_set && $user_rights['record_create']) || ($auto_inc_set && $num_records > DataEntry::$maxNumRecordsHideDropdowns))
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
				<tr>
					<td class="labelrc">
						<?php echo $search_text_label ?>
					</td>
					<td class="data" style="width:400px;">
						<input id="inputString" type="text" class="x-form-text x-form-field" style="position:relative;">
					</td>
				</tr>
				<?php
			}
			// Auto-number button(s) - if option is enabled
			if ($auto_inc_set && $user_rights['record_create'] > 0)// && $num_records <= DataEntry::$maxNumRecordsHideDropdowns)
			{
				$autoIdBtnText = $lang['data_entry_46'];
				if ($multiple_arms) {
					$autoIdBtnText .= $lang['data_entry_99'];
				}
				?>
				<tr>
					<td class="labelrc">&nbsp;</td>
					<td class="data">
						<!-- New record button -->
						<button class="btn btn-xs btn-rcgreen fs13" onclick="window.location.href=app_path_webroot+page+'?pid='+pid+'&id=<?php echo DataEntry::getAutoId() ?>&auto=1&arm='+($('#arm_name_newid').length ? $('#arm_name_newid').val() : '<?php echo $arm ?>');return false;"><i class="fas fa-plus"></i> <?php echo $autoIdBtnText ?></button>
					</td>
				</tr>
				<?php
			}
			if ($Proj->metadata[$table_pk]['element_type'] != 'text') {
				// Error if first field is NOT a text field
				?>
				<tr>
					<td colspan="2" class="red"><?php echo RCView::b($lang['global_48'] .$lang['colon']) ." " .$lang['data_entry_180'] . " <b>$table_pk</b> (\"".RCView::escape($table_pk_label)."\")".$lang['period'] ?></td>
				</tr>
				<?php
			}
			print "</table>";
			// Display search utility
			DataEntry::renderSearchUtility();
			?>
			<br><br>
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
							if (!$('.simpleDialog.ui-dialog-content:visible').length) {

                                // If record-autonumbering is enabled, then we must be here because there are so many records that the auto-suggest input field
                                // is being displayed, so only let users choose a record from the auto-suggest list and not enter a new record name via freeform.
                                var recordExists = true;
                                if (auto_inc_set) {
                                    $.ajax({
                                        url: app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:recordExists',
                                        data: { record: idval, redcap_csrf_token: redcap_csrf_token },
                                        async: false,
                                        type: 'POST',
                                        success: function(data) {
                                            if (data != '1') recordExists = false;
                                        },
                                        error: function(e) {
                                            recordExists = false;
                                        }
                                    });
                                }
                                // Redirect to record home page
                                if (recordExists) {
								    window.location.href = app_path_webroot+page+'?pid='+pid+'&arm=<?php echo (($arm_dropdown_choices != "") ? "'+ $('#arm_name_newid').val() +'" : $arm) ?>&id=' + idval;
								} else {
								    $('#inputString').val('');
								    $('#inputString').focus();
								}
                            }
						},200);
					}
				});
			});
			</script>
			<?php
			//Using double data entry and auto-numbering for records at the same time can mess up how REDCap saves each record.
			//Give warning to turn one of these features off if they are both turned on.
			if ($double_data_entry && $auto_inc_set) {
				print "<div class='red' style='margin-top:20px;'><b>{$lang['global_48']}</b><br>{$lang['data_entry_56']}</div>";
			}
			// If multiple Arms exist, use javascript to pop in the drop-down listing the Arm names to choose from for new records
			if ($arm_dropdown_choices != "" && ((!$auto_inc_set && $user_rights['record_create'])
				|| ($auto_inc_set && $num_records > DataEntry::$maxNumRecordsHideDropdowns)))
			{
				print  "<script type='text/javascript'>
						$(function(){
							$('#inputString').before('".js_escape("<select id='arm_name_newid' onchange=\"if (!$('select#arm_name').length){ window.location.href=window.location.href+'&arm='+this.value; return; } editAutoComp(autoCompObj,this.value);\" class='x-form-text x-form-field' style='margin-right:20px;'>$arm_dropdown_choices</select>")."');
						});
						</script>";
			}
		}
		// Render JavaScript for record selecting auto-complete/auto-suggest
		?>
		<script type="text/javascript">
		var langRH01 = '<?php print js_escape($lang['index_53']) ?>';
		var langRH02 = '<?php print js_escape($lang['survey_1133']) ?>';
		var autoCompObj;
		$(function(){
			// Autocomplete for entering recrod names
			if ($('#inputString').length) {
				autoCompObj = 	$('#inputString').autocomplete({
									source: app_path_webroot+'DataEntry/auto_complete.php?pid='+pid+'&arm='+($('#arm_name_newid').length ? $('#arm_name_newid').val() : '<?php echo $arm ?>'),
									minLength: 1,
									delay: 0,
									select: function( event, ui ) {
										$(this).val(ui.item.value).trigger('blur');
										return false;
									}
								})
								.data('ui-autocomplete')._renderItem = function( ul, item ) {
									return $("<li></li>")
										.data("item", item)
										.append("<a>"+item.label+"</a>")
										.appendTo(ul);
								};
			}
			// Initialize button drop-down(s) for top of form
			if ($('#recordActionDropdown').length) {
				$('#recordActionDropdown').menu();
				$('#recordActionDropdownDiv ul li a').click(function(){
					$('#recordActionDropdownDiv').hide();
				});
				if ($('#recordActionDropdown li').length < 1) $('#recordActionDropdownTrigger').hide();
			}
			// Delete event button opacity
			$('#event_grid_table .glyphicon.opacity35').mouseenter(function() {
				$(this).removeClass('opacity35');
			}).mouseleave(function() {
				$(this).addClass('opacity35');
			});
		});
		function editAutoComp(autoCompObj,val) {
			var autoCompObj = 	$('#inputString').autocomplete({
									source: app_path_webroot+'DataEntry/auto_complete.php?pid='+pid+'&arm='+val,
									minLength: 1,
									delay: 0,
									select: function( event, ui ) {
										$(this).val(ui.item.value).trigger('blur');
										return false;
									}
								})
								.data('ui-autocomplete')._renderItem = function( ul, item ) {
									return $("<li></li>")
										.data("item", item)
										.append("<a>"+item.label+"</a>")
										.appendTo(ul);
								};
		}

		// Delete record
		function deleteRecord(record, arm) {
			showProgress(1);
			$.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:deleteRecord',{ record: record, arm: arm, allow_delete_record_from_log: ($('#allow_delete_record_from_log').prop('checked') ? '1' : '0') },function(data){
				if (data != '1') { alert(woops); return; }
				showProgress(0,0);
				simpleDialog('<div style="color:#C00000;font-size:14px;font-weight:bold;">'+table_pk_label+' "'+decodeURIComponent(getParameterByName('id'))+'" <?php print js_escape($lang['rights_07'].$lang['period']) ?></div>','<?php print js_escape($lang['data_entry_312']) ?>',null,null,function(){
					window.location.href = app_path_webroot+'DataEntry/record_home.php?pid='+pid;
				},'<?php print js_escape($lang['calendar_popup_01']) ?>');
			});
		}

		// Confirm prompt for Delete Event Instanct
		function deleteEventInstance(event_id, instance, isRepeatingEvent) {
			simpleDialog((isRepeatingEvent ? '<?php print js_escape($lang['data_entry_299']) ?>' : '<?php print js_escape($lang['data_entry_240']) ?>'), 
				(isRepeatingEvent ? '<?php print js_escape("{$lang['data_entry_300']} \"".htmlspecialchars($_GET['id'], ENT_QUOTES)."\"{$lang['questionmark']}") ?>' : '<?php print js_escape("{$lang['data_entry_238']} \"".htmlspecialchars($_GET['id'], ENT_QUOTES)."\"{$lang['questionmark']}") ?>'),
				null, 650, null, '<?php print js_escape($lang['global_53']) ?>', function(){
					doDeleteEventInstance(event_id, instance);
				}, (isRepeatingEvent ? '<?php print js_escape($lang['data_entry_298']) ?>' : '<?php print js_escape($lang['data_entry_297']) ?>'));
		}

		// Delete event instance
		function doDeleteEventInstance(event_id, instance) {
			$.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:deleteEventInstance&event_id='+event_id+'&instance='+instance,{ record: getParameterByName('id') },function(data){
				if (data != '1') { simpleDialog(data); return; }
				// Reload page
				showProgress(1);
				window.location.href = window.location.href+'&msg=deleteevent';
			});
		}

		// Rename record
		function renameRecord() {
			simpleDialog(null,null,'rename-record-dialog',450,null,'<?php print js_escape($lang['global_53']) ?>',function(){		
				showProgress(1);
				var arm = getParameterByName('arm');
				if (arm != '') arm = '&arm='+arm;
				var recordInput = $('#rename-record-dialog #new-record-name');
				var new_record = trim(recordInput.val());
				var validRecordName = recordNameValid(new_record);
				if (validRecordName !== true) {
					alert(validRecordName);
					showProgress(0,0);
					setTimeout(function(){ renameRecord(); },500);
					return false;
				}
				// Ajax call
				$.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:renameRecord'+arm,{ record: getParameterByName('id'), new_record: new_record },function(data){
					if (data == '') { alert(woops); return; }
					if (data == '1') {
						window.location.href = app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&id='+new_record+arm+'&msg=rename';
					} else {
						showProgress(0,0);
						simpleDialog(data,null,null,500,function(){renameRecord()});
					}
				});
			},'<?php print js_escape($lang['data_entry_316']) ?>');
		}

		// Assign record to DAG
		function assignDag(currentDag) {
			simpleDialog(null,null,'assign-dag-record-dialog',500,null,'<?php print js_escape($lang['global_53']) ?>',function(){
				var group_id = $('#assign-dag-record-dialog #new-dag-record').val();
				if (group_id == currentDag) {
					simpleDialog('<div style="color:#A00000;"><?php print js_escape($lang['data_entry_329']) ?></div>',null,null,500,"assignDag('"+currentDag+"')");
					return;
				}		
				showProgress(1);
				$.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:assignRecordToDag',{ record: getParameterByName('id'), group_id: group_id },function(data){
					if (data == '') { alert(woops); return; }
					if (data == '1') {
						window.location.href = window.location.href+'&msg='+(group_id=='' ? 'unassigndag' : 'assigndag');
					} else {
					showProgress(0,0);
						simpleDialog(data,null,null,500,function(){renameRecord()});
					}
				});
			},'<?php print js_escape($lang['data_entry_323']) ?>');
		}
		</script>
		<?php
	}

    // Verify username+password
    public static function passwordVerify()
    {
        global $auth_meth, $shibboleth_esign_salt;
        return ($auth_meth == "none"
                || (USERID == $_POST['username'] && checkUserPassword($_POST['username'], $_POST['password']))
                // Special Shibboleth logic that uses Andy Martin's hook to allow e-signing to work with Shibboleth authentication
                || ($auth_meth == "shibboleth" && isset($_POST['shib_auth_token'])
                    && $_POST['shib_auth_token'] == Authentication::hashPassword(USERID,$shibboleth_esign_salt,USERID))
                 );
    }

    // Render a form
    public static function renderForm($elements, $element_data=array(), $hideFields=array())
    {
        // Global variables needed
        global $project_id, $app_name, $user_rights, $reset_radio, $edoc_field_option_enabled, $hidden_edit, $multiple_arms,
               $table_pk, $table_pk_label, $this_form_menu_name, $sql_fields, $sendit_enabled, $Proj, $double_data_entry, $isIpad,
               $lang, $history_widget_enabled, $surveys_enabled, $isMobileDevice, $isIOS, $isIE, $isTablet, $secondary_pk, $longitudinal, $display_today_now_button,
               $enable_edit_survey_response, $randomization, $custom_record_label, $data_resolution_enabled,
               $survey_auth_enabled, $survey_auth_apply_all_surveys, $enable_field_attachment_video_url,
               $autologout_timer,
               $pageFields, $question_by_section, $totalPages, $missingDataCodes;

        //create enum string for missing data codes, for appending to Drop downs and Radio buttons
        $missingDataEnum = "";
        foreach ($missingDataCodes as $key=>$label) {
            $missingDataEnum .= "$key, ".strip_tags($label) . " ($key)\n";
        }
        $missingDataEnum = trim($missingDataEnum);

        $valTypes = getValTypes();
        // Is this form being displayed as a survey?
        $isSurveyPage = ((isset($_GET['s']) && PAGE == "surveys/index.php" && defined("NOAUTH")) || PAGE == "Surveys/theme_view.php");
        // Is this a data entry form being displayed for a record?
        $isDataEntryForm = (PAGE == "DataEntry/index.php" && isset($_GET['id']));
        // Is randomize record popup?
        $isRandomizeRecordPopup = (PAGE == "Randomization/randomize_record.php" && isset($_POST['record']) && isset($_POST['action']) && $_POST['action'] == 'view');
        // Is either a form or a survey page?
        $isFormOrSurveyPage = ($isSurveyPage || $isDataEntryForm | $isRandomizeRecordPopup);
        // Defaults
        $table_width = "";
        $bookend1 = '';
        $bookend2 = '';
        $bookend3 = '';
        $colClassLeft  	  = $isSurveyPage ? 'col-6' : 'col-7';
        $colClassRight 	  = 'col-5';
        $colClassCombined = $isSurveyPage ? 'col-11' : 'col-12';
        // Alter how records are saved if project is Double Data Entry (i.e. add --# to end of Study ID)
        $entry_num = ($double_data_entry && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "";
        // For surveys, is question auto numbering enabled?
        if ($isSurveyPage)
        {
            $question_auto_numbering = $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['question_auto_numbering'];
        }
        // Set image onload for IE due to random image loading issues related to image width

        $surveyImgOnload = ($isSurveyPage && vIE() > 7) ? " onload='imgSizeIE(this);fitImg(this);'" : " onload='fitImg(this);'";
        // Is this a repeating form or event?
        $isRepeatingFormOrEvent = ($isFormOrSurveyPage && $Proj->isRepeatingFormOrEvent($_GET['event_id'], $_GET['page']));
        ## SECONDARY UNIQUE IDENTIFIER
        // For longitudinal projects or repeating events/instruments, if 2ndary id is changed, then change for ALL events that use that form.
        if ($secondary_pk != '' && ($isRepeatingFormOrEvent ||  $longitudinal) && PAGE == 'DataEntry/index.php' && isset($_GET['id'])
            && $_GET['page'] == $Proj->metadata[$secondary_pk]['form_name'])
        {
            // Form name of secondary id
            $secondary_pk_form = $Proj->metadata[$secondary_pk]['form_name'];
            // Store events where secondary id's form is used
            $secondary_pk_form_events = array();
            // Check if other events use this form
            foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
                if (in_array($secondary_pk_form, $these_forms)) {
                    // Get first event that uses the secondary id's form
                    if (!isset($secondary_pk_form_first_event)) {
                        $secondary_pk_form_first_event = $this_event_id;
                    }
                    // Collect all events where the form is used
                    $secondary_pk_form_events[] = $this_event_id;
                }
            }
            // Add special note to display under $secondary_pk field ONLY IF this form is used on multiple events or is on a repeating instrument
            if ($isRepeatingFormOrEvent || count($secondary_pk_form_events) > 1) {
                $secondary_pk_note = "<span class='note' style='color:#666;'>({$lang['data_entry_125']})</span><div class='note' style='color:#800000;line-height:10px;'>{$lang['data_entry_427']}</div>";
            }
            // Fetch value if we don't have one for this repeating event/instrument
            if ($isRepeatingFormOrEvent && (!isset($element_data[$secondary_pk]) || $element_data[$secondary_pk] == '')) {
                $element_data[$secondary_pk] = $Proj->getSecondaryIdVal($_GET['id']);
            }
        }
        // For longitudinal projects or repeating events/instruments, if a survey email invitation field is being displayed, then display the value from ANY other event/instance
        if (($isRepeatingFormOrEvent || $longitudinal) && PAGE == 'DataEntry/index.php' && isset($_GET['id']))
        {
            // Get email invitation fields
            $surveyEmailInvitationFields = $Proj->getSurveyEmailInvitationFields(true);
            $surveyEmailInvitationFieldsForms = array();
            foreach ($surveyEmailInvitationFields as $surveyEmailInvitationField) {
                $surveyEmailInvitationFieldsForm = $Proj->metadata[$surveyEmailInvitationField]['form_name'];
                if ($surveyEmailInvitationFieldsForm != $_GET['page']) continue;
                $surveyEmailInvitationFieldsForms[$surveyEmailInvitationField] = $surveyEmailInvitationFieldsForm;
            }
            if (!empty($surveyEmailInvitationFieldsForms))
            {
                $surveyEmailInvitationFieldsValues = $Proj->getEmailInvitationFieldValues($_GET['id'], array_keys($surveyEmailInvitationFieldsForms), getArm());
                // Add value to this form
                foreach ($surveyEmailInvitationFieldsValues as $this_field=>$this_val) {
                    $element_data[$this_field] = $this_val;
                }
            }
        }
        // Use different form name/id for Randomization widget pop-up
        $formJsName = (PAGE == 'Randomization/randomize_record.php') ? 'random_form' : 'form';

        // Add language to JavaScript
	    addLangToJS(array('design_799', 'design_800', 'design_801', 'design_802', 'design_803', 'design_821', 'design_823'));

        /**
         * Begin form
         */
        if (!(PAGE == 'Design/online_designer_render_fields.php' && isset($_GET['edit_question'])))
        {
            print "<form action='" . PAGE_FULL;
            if (isset($_GET['pid']) && !$isSurveyPage) {
                // Add strings to form action, if a form and not a survey
                print "?pid=$project_id";
                // Display event_id and page from URL, if exist
                if (isset($_GET['page'])) {
                    print "&event_id=".(isset($_GET['event_id']) && is_numeric($_GET['event_id']) ? $_GET['event_id'] : $Proj->firstEventId)
                        . "&page=".$_GET['page'];
                }
                // If performing Save-and-Continue when editing a survey response, keep as still editing when page reloads
                if (isset($_GET['editresp']) && $_GET['editresp']) {
                    print "&editresp=1";
                }
                // Repeating event instance
                print "&instance=" . $_GET['instance'];
            } elseif ($isSurveyPage) {
                // If this is a survey, then pass 's' URL variable in form action
                print "?s=".$_GET['s'];
                // Set table attributes
                $table_width = "style='display:none;' id='questiontable'";
            } elseif (PAGE == 'install.php' && isset($_GET['version'])) {
                // If on install page
                print "?version=".preg_replace("/[^0-9.]/", '', $_GET['version']);
            }
            // If we are on Control Center page, use "project" and "view" URL variables
            if (isset($_GET['view']) && !$isSurveyPage) {
                print "?view=".preg_replace("/[^0-9a-z_]/", '', $_GET['view']);
                print isset($_GET['project']) ? "&project=".preg_replace("/[^0-9a-z_]/", '', $_GET['project']) : "";
            }
            // Finish form tag
            print "' enctype='multipart/form-data' target='_self' method='post' name='$formJsName' id='$formJsName'>";
        }
        // Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
        // (This is done in case the page is very long and user submits form before the DOM has finished loading.)
        print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";


        // READ-ONLY MODE (disable all fields on the page)
        // Set default if all fields should be disabled
        $disable_all = false;
        // Disable if user has read-only rights
        if ((PAGE == "DataEntry/index.php") && isset($_GET['id'])
            && isset($user_rights) && $user_rights['forms'][$_GET['page']] == '2')
        {
            $disable_all = true;
        }

        // Determine if another user is on this form for this record for this project (do not allow the page to load, if so)
        $otherUserOnPage = DataEntry::checkSimultaneousUsers();
        if (!$disable_all && $otherUserOnPage !== false)
        {
            // Set disable all flag
            $disable_all = true;
            // If the edit survey flag exists, then remove it
            if (isset($_GET['editresp'])) unset($_GET['editresp']);
            // Obtain other user's email/name for display
            $q = db_query("select * from redcap_user_information where username = '" . db_escape($otherUserOnPage) . "'");
            $otherUserEmail = db_result($q, 0, "user_email");
            $otherUserName = db_result($q, 0, "user_firstname") . " " . db_result($q, 0, "user_lastname");
            // Display msg to user
            print  "<div class='yellow' style='margin:15px 0;padding:10px;'>
                        <div>
                            <img src='".APP_PATH_IMAGES."exclamation_orange.png'>
                            <b>{$lang['data_entry_279']}</b><br><br>
                            {$lang['data_entry_78']} (<b>$otherUserOnPage</b> - <a href='mailto:$otherUserEmail'>$otherUserName</a>)
                            {$lang['data_entry_79']} (\"<b>{$_GET['id']}</b>\"){$lang['period']} {$lang['data_entry_280']}
                        </div>
                        <div id='errconflict' class='brown' style='display:none;margin:10px 0;'>
                            {$lang['data_entry_81']} ($autologout_timer {$lang['data_entry_82']}){$lang['data_entry_83']}
                        </div>
                        <div style='margin-top:10px;'>
                            <table role='presentation' style='width:100%;'><tr>
                            <td>
                                <button onclick='window.location.reload();return false;'>{$lang['data_entry_84']}</button>
                            </td>
                            <td style='text-align:right;'>
                                <a href='javascript:;' onclick=\"$(this).remove();$('#errconflict').show('fast');\" style='font-size:11px;'>{$lang['data_entry_85']}</a>
                            </td>
                            </tr></table>
                        </div>
                    </div>";
        }

        // Begin div and set width
        if ($isMobileDevice || $isSurveyPage) {
            print "<div>";
        } else {
            print "<div style='max-width:800px;'>";
        }
        if ($isSurveyPage || ((PAGE == "DataEntry/index.php") && isset($_GET['id'])))
        {
            ?>
            <script type="text/javascript">
            var missing_data_replacement_js = '<?php print Piping::missing_data_replacement ?>';
            var piping_receiver_class_field_js = '.<?php print Piping::piping_receiver_class.".".Piping::piping_receiver_class_field ?>';
            var lang_checked = '<?php print js_escape($lang['global_143']) ?>';
            var lang_unchecked = '<?php print js_escape($lang['global_144']) ?>';
            </script>
            <?php
            ## @MAXCHECKED action tag alert when user clicks choices after max has been reached
            print RCView::span(array('id'=>'maxchecked_tag_label', 'class'=>'', 'style'=>'display:none;z-index:1000;'), $lang['data_entry_421']);
            ## MATRIX RANKING
            // Set invisible div that says "value removed" if user clicks same column for another field in the matrix
            print RCView::span(array('id'=>'matrix_rank_remove_label', 'class'=>'opacity75', 'style'=>'display:none;'), $lang['data_entry_203']);
            ## PIPING
            $specialPipingTags = Piping::getSpecialTagsFormatted();
            // Create array as field list of piping transmitters (i.e. fields to be replaced in field labels)
            $piping_transmitter_fields = array();
            // Create array as event list of piping transmitters (solely for the data pull)
            $piping_transmitter_events = array(); // may contain be event_ids and unique event names
            // Set array to denote which fields/attributes in $elements need to be replaced (so we don't have to scan the whole array a second time)
            $elements_replace = array();
            // Put all drop-down fields that are piping receivers into special array so we can do ajax requests if their transmitter is changed.
            // Keys will be piping transmitters, and values will be receiver field drop-downs.
            $piping_receiver_dropdown_fields = array();
            // Set flag if any [..-event-name] smart variables are used, which means we should pull data for ALL events, just in case
            $usingEventNameSmartVariables = false;
            // Set array of field attributes to look at in $elements
            $elements_attr_to_replace = array('label', 'enum', 'value', 'note', 'slider_labels');
            // List of embedded fields
            $embeddedFields = array();
            // Loop through all fields to be displayed on this form/survey
            foreach ($elements as $key=>$attr)
            {
                // Ignore some field types that aren't applicable
                if ($attr['rr_type'] == 'hidden' || $attr['rr_type'] == 'static') continue;
                // Loop through all relevant field attributes for this field
                foreach ($elements_attr_to_replace as $this_attr_type)
                {
                    // Skip if field doesn't have current attribute
                    if (!isset($attr[$this_attr_type])) continue;
                    // Set array of elements to loop through
                    $these_strings = ($this_attr_type == 'slider_labels') ? $attr[$this_attr_type] : array($attr[$this_attr_type]);
                    // Loop through string(s)
                    foreach ($these_strings as $this_key=>$this_string)
                    {
                        // Embed field if has {field} in label
                        $embeddedFieldReplacement = Piping::replaceEmbedVariablesInLabel($this_string, PROJECT_ID, $_GET['page']);
                        if (is_array($embeddedFieldReplacement)) {
                            list ($this_string, $theseEmbeddedFields) =  $embeddedFieldReplacement;
                            $embeddedFields = array_merge($embeddedFields, $theseEmbeddedFields);
                            // Slider only: loop through slider label options
                            if ($this_attr_type == 'slider_labels') {
                                foreach ($elements[$key][$this_attr_type] as $this_skey=>$this_slabel) {
                                    $elements[$key][$this_attr_type][$this_skey] = $this_string;
                                }
                            }
                            // All other fields besides sliders
                            else {
                                $elements[$key][$this_attr_type] = $this_string;
                            }
                        }

                        // If a field, check field label
                        if ($this_string != '' && strpos($this_string, '[') !== false && strpos($this_string, ']') !== false)
                        {
                            // Set flag to add this field/attribute to the elements_replace array
                            $replace_this_attr = false;
                            // Replace field label
                            $possibleVars = array_keys(getBracketedFields($this_string, true, true, false));
                            foreach (array_merge($possibleVars, $specialPipingTags) as $this_field) {
                                // If longitudinal with a dot, parse it out
                                if (strpos($this_field, '.') !== false) {
                                    // Separate event from field
                                    list ($this_event, $this_field) = explode(".", $this_field, 2);
                                } else {
                                    // Set this_event as current event_id
                                    $this_event = $_GET['event_id'];
                                }
                                // Split off the field from any parameters, such as :value or :checked
                                list ($this_field, $nothing) = explode(":", $this_field);
                                // Validate field name
                                $isSmartVar = in_array($this_field, $specialPipingTags);
                                if (!isset($Proj->metadata[$this_field]) && !$isSmartVar) continue;
                                // Add field and event to transmitter arrays
                                if (!$isSmartVar) $piping_transmitter_fields[] = $this_field;
                                $piping_transmitter_events[] = $this_event;
                                // Set flag
                                $replace_this_attr = true;
                                // If field is a drop-down and has choice option as a piping receiver, put in array for later.
                                // Keys will be piping transmitters, and values will be receiver field drop-downs.
                                if ($attr['rr_type'] == 'select' && $this_attr_type == 'enum') {
                                    $piping_receiver_dropdown_fields[$this_field][] = $attr['field'];
                                }
                                // Has event-name smart variable?
                                if (strpos($this_string, 'event-name][') !== false) {
                                    $usingEventNameSmartVariables = true;
                                }
                            }
                            // Add key and attribute type to elements_replace so we can replace in $elements w/o having to loop
                            // through the entire $elements array a second time below.
                            if ($replace_this_attr)	$elements_replace[$key][] = $this_attr_type;
                        }
                    }
                }
            }
            $embeddedFields = array_unique($embeddedFields);
            // If there are strings to replace, then get data for fields/events and do replacing
            if (!empty($elements_replace))
            {
                ## REPLACE FIELDS IN STRING WITH DATA
                // Set piping transmitter fields/events as unique
                $piping_transmitter_fields = array_unique($piping_transmitter_fields);
                $piping_transmitter_events = $usingEventNameSmartVariables ? array_keys($Proj->eventInfo) : array_unique($piping_transmitter_events);
                // Obtain saved data for all piping receivers used in field labels and MC option labels
                $piping_record_data = Records::getData('array', $_GET['id'].$entry_num, $piping_transmitter_fields, $piping_transmitter_events);
                // Now loop through all fields again and replace those that need replacing
                foreach ($elements_replace as $key=>$attr) {
                    // Loop through each attribute and REPLACE
                    foreach ($attr as $this_attr_type) {
                        // Slider only: loop through slider label options
                        if ($this_attr_type == 'slider_labels') {
                            foreach ($elements[$key][$this_attr_type] as $this_skey=>$this_slabel) {
                                $elements[$key][$this_attr_type][$this_skey] = Piping::replaceVariablesInLabel($this_slabel, $_GET['id'].$entry_num, $_GET['event_id'], $_GET['instance'], $piping_record_data, true, null, true, "", 1, false, false, $_GET['page']);
                            }
                        }
                        // All other fields besides sliders
                        else {
                            $elements[$key][$this_attr_type] = Piping::replaceVariablesInLabel($elements[$key][$this_attr_type], $_GET['id'].$entry_num, $_GET['event_id'], $_GET['instance'], $piping_record_data, true, null, true, "", 1, false, false, $_GET['page']);
                        }
                    }
                }
                ## CREATE JQUERY TRIGGERS FOR REAL-TIME PIPING ON PAGE
                // Parse out trasmitter fields by field type
                $piping_transmitter_fields_types = array('text'=>array(), 'radio'=>array(), 'select'=>array(), 'calc'=>array());
                $piping_transmitter_fields_survey_prefill = "";
                foreach ($piping_transmitter_fields as $this_field) {
                    // Get thid field's field type
                    $this_type = $Proj->metadata[$this_field]['element_type'];
                    // If survey questions are being pre-filled via query string or Post pre-fill method, then trigger them after page load
                    // so that the pre-filled value gets piped.
                    $isPipingTransmitterPrefilledOnSurvey = ($isSurveyPage && (($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET[$this_field]))
                                                            || (isset($_POST['__prefill']) && isset($_POST[$this_field]))));
                    // Add to sub-array based on field type
                    // NOTE: Slider fields will be handled independently in enableSldr() in base.js
                    if ($this_type == 'text') {
                        $piping_transmitter_fields_types['text'][] = "form#form :input[name='$this_field'], form#form input#$this_field-autosuggest";
                    } elseif ($this_type == 'textarea') {
                        $piping_transmitter_fields_types['text'][] = "form#form textarea[name='$this_field']";
                    } elseif (in_array($this_type, array('radio', 'yesno', 'truefalse'))) {
                        $piping_transmitter_fields_types['radio'][] = "form#form :input[name='{$this_field}___radio']";
                    } elseif ($this_type == 'select' || $this_type == 'sql') {
                        $piping_transmitter_fields_types['dropdown'][] = "form#form select[name='$this_field']";
                    } elseif ($this_type == 'calc') {
                        $piping_transmitter_fields_types['calc'][] = "form#form :input[name='$this_field']";;
                    } elseif ($this_type == 'checkbox') {
                        $piping_transmitter_fields_types['checkbox'][] = "form#form :input[name='__chkn__{$this_field}']";;
                    }
                    // If the field is pre-filled on a survey, set jQuery trigger
                    if ($isPipingTransmitterPrefilledOnSurvey) {
                        if (in_array($this_type, array('text', 'textarea', 'calc'))) {
                            $piping_transmitter_fields_survey_prefill .= "$('form#form :input[name=\"{$this_field}\"]').trigger('blur');";
                        } elseif (in_array($this_type, array('radio', 'yesno', 'truefalse'))) {
                            $piping_transmitter_fields_survey_prefill .= "$('form#form :input[name=\"{$this_field}___radio\"]:checked').trigger('click');";
                        } elseif (in_array($this_type, array('select', 'sql'))) {
                            $piping_transmitter_fields_survey_prefill .= "$('form#form select[name=\"{$this_field}\"]').trigger('change');";
                        }
                    }
                }
                // Set name of page for ajax request (for surveys, use passthru mechanism)
                if ($isSurveyPage) {
                    $piping_dropdown_replace_page = APP_PATH_SURVEY . "index.php?s={$_GET['s']}&__passthru=".urlencode("DataEntry/piping_dropdown_replace.php");
                } else {
                    $piping_dropdown_replace_page = APP_PATH_WEBROOT . "DataEntry/piping_dropdown_replace.php?pid=$project_id";
                }
                // JavaScript to perform real-time dynamic piping on forms/survey pages
                ?>
                <script type="text/javascript">
                // PIPING
                <?php if (!empty($piping_transmitter_fields_types['calc'])) { ?>
                // Update any calc field piping receivers on the page
                function updateCalcPipingReceivers() {
                    $("<?php print implode(", ", $piping_transmitter_fields_types['calc']) ?>").each(function(){
                        var val = ($(this).val() != '') ? $(this).val() : missing_data_replacement_js;
                        var name = $(this).attr('name');
                        // Set value for all piping receivers on page
                        $(piping_receiver_class_field_js+event_id+'-'+name).html(val);
                        $(piping_receiver_class_field_js+event_id+'-'+name+'-label').html(val);
                        // Update drop-down options separately via ajax
                        updatePipingDropdowns(name,val);
                    });
                }
                <?php } ?>
                // Update drop-down options that are piping receivers via ajax. Returns json of drop-down options to replace on page.
                function updatePipingDropdowns(transmitter_field,transmitter_field_value) {
                    <?php if (!empty($piping_receiver_dropdown_fields)) { ?>
                    // Set array of transmitter fields that are triggers. If field not in this array, then stop here.
                    var piping_transmitter_fields = new Array(<?php print prep_implode(array_keys($piping_receiver_dropdown_fields)) ?>);
                    if (!in_array(transmitter_field, piping_transmitter_fields)) return false;
                    // Set json string to send
                    var json_piping_receiver_dropdown_fields = '<?php print json_encode_rc($piping_receiver_dropdown_fields) ?>';
                    // Ajax request
                    $.post('<?php print $piping_dropdown_replace_page ?>',{ formdata: JSON.stringify($('#form').serializeObject()), page: getParameterByName('page'), record: '<?php echo js_escape($_GET['id']) ?>', event_id: event_id, instance: '<?php echo js_escape($_GET['instance']) ?>', json_piping_receiver_dropdown_fields: json_piping_receiver_dropdown_fields, transmitter_field: transmitter_field, transmitter_field_value: transmitter_field_value },function(data){
                        if (data != '') {
                            // Parse JSON and update all affected drop-down fields' options with new text
                            var json_data = jQuery.parseJSON(data);
                            // Loop through fields
                            for (var this_field in json_data) {
                                for (var this_code in json_data[this_field]) {
                                    var this_label = json_data[this_field][this_code];
                                    // Update this option for this field
                                    $("form#form select[name='"+this_field+"'] option[value='"+this_code+"']").html( this_label );
                                }
                                // Update autocomplete options, if dropdown using autocomplete
                                if ($("form#form select[name='"+this_field+"'].rc-autocomplete").length) {
                                    $("form#form select[name='"+this_field+"']").removeClass('rc-autocomplete-enabled');
                                    $("form#form table tr#"+this_field+"-tr input.rc-autocomplete, form#form table tr#"+this_field+"-tr button.rc-autocomplete").unbind();
                                    $("form#form table tr#"+this_field+"-tr input.rc-autocomplete").autocomplete("destroy");
                                }
                            }
                            // Update autocomplete options
                            enableDropdownAutocomplete();
                        }
                    });
                    <?php } ?>
                }
                // Piping transmitter field triggers
                $(function(){
                    <?php if (!empty($piping_transmitter_fields_types['checkbox'])) { ?>
                        // Checkbox fields
                        $("<?php print implode(", ", $piping_transmitter_fields_types['checkbox']) ?>").click(function(){
                            try{ updatePipingCheckboxes(this); }catch(e){ }
                        });
                    <?php } ?>
                    <?php if (!empty($piping_transmitter_fields_types['text'])) { ?>
                        // Text fields
                        updatePipingTextFields("<?php print implode(", ", $piping_transmitter_fields_types['text']) ?>");
                    <?php } ?>
                    <?php if (!empty($piping_transmitter_fields_types['radio'])) { ?>
                        // Radio fields
                        updatePipingRadios("<?php print implode(", ", $piping_transmitter_fields_types['radio']) ?>");
                    <?php } ?>
                    <?php if (!empty($piping_transmitter_fields_types['dropdown'])) { ?>
                        // Drop-down fields
                        updatePipingDropdownsPre("<?php print implode(", ", $piping_transmitter_fields_types['dropdown']) ?>");
                    <?php }
                    // Survey only: If any piping transmitters are pre-filled on a survey, then add JS to trigger them after page load to perform piping.
                    print $piping_transmitter_fields_survey_prefill;
                    ?>
                });
                </script>
                <?php
                // Remove variables no longer needed
                unset($elements_replace, $piping_record_data, $piping_transmitter_fields_types);
            }
            // Note to survey participants or form users about incompatibility with IE 6-8
            if ($isIE && vIE() <= 8) {
                print "<div class='red fs15'>We're sorry, but your current web browser (<b>Internet Explorer ".vIE()
                    . "</b>) is not compatible with this web page. In order to continue on this page, we recommend you upgrade your web browser "
                    . "to a newer version or else use Firefox or Google Chrome instead. Thanks for understanding!</div>";
                exit;
            }
            ## Render temporary "loading.." div while page is loading (only show after delay of 0.75 seconds)
            print  "<div id='questiontable_loading'>
                        <img alt='".js_escape($lang['scheduling_20'])."' src='".APP_PATH_IMAGES."progress_circle.gif'> {$lang['data_entry_64']}
                    </div>
                    <script type='text/javascript'>
                        setTimeout(function(){
                            document.getElementById('questiontable_loading').style.visibility='visible';
                        },750);
                    </script>";
        }
        // Set flag that form is not locked (default)
        $form_locked = array('status'=>false);
        ## RANDOMIZATION
        if ($isSurveyPage || ((PAGE == "DataEntry/index.php") && isset($_GET['id'])))
        {
            // Check if randomization has been enabled
            $randomizationEnabled = ($randomization && Randomization::setupStatus());
            // If enabled, get randomization field and criteria fields
            if ($randomizationEnabled)
            {
                // Get randomization attributes
                $randAttr = Randomization::getRandomizationAttributes();
                // Set fields for reference later
                $randomizationField = $randAttr['targetField'];
                $randomizationEvent = $randAttr['targetEvent'];
                $randomizationCriteriaFields = $randAttr['strata'];
                $randomizationGroupBy = $randAttr['group_by'];
                // Determine if this record has already been randomized
                $wasRecordRandomized = Randomization::wasRecordRandomized($_GET['id']);
                // If record was randomized and grouping by DAG, then disable DAG drop-down for all events
                if ($wasRecordRandomized && $randomizationGroupBy == 'DAG' && $user_rights['group_id'] == '')
                {
                    ?>
                    <script type="text/javascript">
                    $(function(){
                        $('form#form select[name="__GROUPID__"]').prop('disabled',true);
                    });
                    </script>
                    <?php
                }
                // If record was randomized, then set javascript variable to prevent randomization field and strata
                // from being hidden by branching logic.
                if ($wasRecordRandomized)
                {
                    // Build JS array of criteria fields IF they exist on the current event
                    $randomizationCriteriaFieldList = array();
                    if ($randomizationEvent == $_GET['event_id']) $randomizationCriteriaFieldList[] = $randomizationField;
                    foreach ($randomizationCriteriaFields as $this_strata_field=>$this_strata_event_id) {
                        if ($this_strata_event_id == $_GET['event_id']) $randomizationCriteriaFieldList[] = $this_strata_field;
                    }
                    if (!empty($randomizationCriteriaFieldList))
                    {
                        ?>
                        <script type="text/javascript">
                        var randomizationCriteriaFieldList = new Array(<?php echo prep_implode($randomizationCriteriaFieldList) ?>);
                        </script>
                        <?php
                    }
                }
            }
        }
        // PROMIS: Determine if instrument is an adaptive PROMIS instrument
        if (!$isSurveyPage) {
            list ($isPromisInstrument, $isAutoScoringInstrument) = PROMIS::isPromisInstrument(isset($_GET['page']) ? $_GET['page'] : '');
            // If record exists, then display the save buttons in case need to lock/e-sign it
            if ($isPromisInstrument && $hidden_edit == 1) {
                if ($user_rights['forms'][$_GET['page']] == '3' && $user_rights['lock_record'] > 0) {
                    $_GET['editresp'] = '1';
                    ?>
                    <script type='text/javascript'>
                    $(function(){
                        // Keep save buttons disabled until survey has been started by participant
                        if ($('#form_response_header').length) {
                            $('form#form :input[name="submit-btn-cancel"]').removeClass('disabled').prop('disabled',false);
                            $('form#form :input[name="submit-btn-delete"]').removeClass('disabled').prop('disabled',false);
                            $('form#form :input[name="submit-btn-deleteform"]').removeClass('disabled').prop('disabled',false);
                            $('form#form :input[name="submit-btn-deleteevent"]').removeClass('disabled').prop('disabled',false);
                        } else {
                            $('#formSaveTip, #submit-btn-cancel-tr, #__DELETEBUTTONS__-tr, #__SUBMITBUTTONS__-tr, #__LOCKRECORD__-tr').hide();
                        }
                    });
                    </script>
                    <?php
                } else {
                    ?>
                    <script type='text/javascript'>
                    $(function(){
                        $('#formSaveTip').hide();
                    });
                    </script>
                    <?php
                }
            }
            // Hide SAVE buttons and record locking options, etc. if record doesn't exist yet
            if ($isPromisInstrument && $hidden_edit == 0) {
                // If record doesn't exist yet, give information on how to create it via
                if ($isAutoScoringInstrument) {
                    print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0;padding:5px 8px 8px;'),
                                RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                    RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                    RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_249'])
                                ) .
                                $lang['data_entry_250'] . " " .
                                $lang['data_entry_251'] . " " .
                                RCView::a(array('href'=>APP_PATH_WEBROOT."Surveys/invite_participants.php?pid=".PROJECT_ID, 'style'=>'text-decoration:underline;'), $lang['app_24']) . " " .
                                $lang['data_entry_252']
                            );
                } else {
                    print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0;padding:5px 8px 8px;'),
                                RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                    RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                    RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_217'])
                                ) .
                                $lang['data_entry_218'] . " " .
                                $lang['data_entry_222'] . " " .
                                RCView::a(array('href'=>APP_PATH_WEBROOT."Surveys/invite_participants.php?pid=".PROJECT_ID, 'style'=>'text-decoration:underline;'), $lang['app_24']) . " " .
                                $lang['data_entry_223']
                            );
                }
                ?>
                <script type='text/javascript'>
                $(function(){
                    $('#formSaveTip, #submit-btn-cancel-tr, #__DELETEBUTTONS__-tr, #__SUBMITBUTTONS__-tr, #__LOCKRECORD__-tr').hide();
                });
                </script>
                <?php
            }
        }
        /**
         * DATA ENTRY PAGE (WITH RECORD SELECTED)
         */
        if ((PAGE == "DataEntry/index.php") && isset($_GET['id']))
        {
            //Set table width (don't do for mobile viewing)
            $table_width = "id='questiontable'";
            if (PAGE == "DataEntry/index.php") {
                $table_width .= " style='display:none;'";
            }

            //If there is no form status data value for this data entry form, add it as 0 (default)
            if (!isset($element_data[$_GET['page']."_complete"])) $element_data[$_GET['page']."_complete"] = '0';

            ## IS ENTIRE RECORD LOCKED? If so, display message at top of page.
            $locking = new Locking();
            $wholeRecordIsLocked = $locking->isWholeRecordLocked($project_id, $_GET['id'], getArm());
            if ($wholeRecordIsLocked) {
                list ($whole_lock_user, $whole_lock_time) = $locking->getWholeRecordLockTimeUser($project_id, $_GET['id'], getArm());
                $wholelock_user_info = User::getUserInfo($whole_lock_user);
                $wholelock =  "<b>{$lang['form_renderer_34']} $whole_lock_user</b> ({$wholelock_user_info['user_firstname']} {$wholelock_user_info['user_lastname']})
                                {$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($whole_lock_time)."
                                <div class='mt-2'>{$lang['form_renderer_36']}</div>";
                print "<div class='red p-2' id='whole_record_lock_msg'>".RCView::img(array('src'=>'lock_big.png'))." $wholelock</div>";
                $disable_all = true;
            }

            ## E-SIGNATURE: Determine if this form for this record has esignature set
            $sql = "select display_esignature from redcap_locking_labels where project_id = $project_id and form_name = '" . db_escape($_GET['page']) . "' limit 1";
            $q = db_query($sql);
            // If it is NOT in the table OR if it IS in table with display=1, then show e-signature, if user has rights
            $displayEsignature = (db_num_rows($q) && db_result($q, 0) == "1");
            if ($displayEsignature)
            {
                // Determine how to display the e-signature and if user has rights to view it
                $sql = "select e.username, e.timestamp, u.user_firstname, u.user_lastname from redcap_esignatures e, redcap_user_information u
                        where e.project_id = " . PROJECT_ID . " and e.username = u.username
                        and e.record = '" . db_escape($_GET['id'].$entry_num) . "'
                        and e.event_id = {$_GET['event_id']} and e.form_name = '" . db_escape($_GET['page']) . "'
                        and e.instance = '".db_escape($_GET['instance'])."' limit 1";
                $q = db_query($sql);
                $is_esigned = db_num_rows($q);

                // If form is disabled (for whatever reason), give option to e-sign it via ajax
                $onclick = ($disable_all) ? "onclick=\"$('#__LOCKRECORD__').prop('checked',true);saveLocking(($('#__LOCKRECORD__').prop('disabled') ? 2 : 1),'save');\"" : "";
                // Set html for esign checkbox
                $esignature_text = "<div id='esignchk'>
                                        <input type='checkbox' id='__ESIGNATURE__' $onclick " . ($is_esigned ? "checked disabled" : "") . ">
                                        <img src='" . APP_PATH_IMAGES . "tick_shield.png'>
                                        {$lang['global_34']} &nbsp;<span style='color:#444;font-weight:normal;'>(<a style='text-decoration:underline;font-size:10px;font-family:tahoma;'
                                            href='javascript:;' onclick='esignExplainLink(); return false;'>{$lang['form_renderer_02']}</a>)</span>
                                    </div>";
                // If e-sign exists, display who and when
                if ($is_esigned)
                {
                    $esign = db_fetch_assoc($q);
                    // Set basic e-sign info to be inserted in 2 places on page
                    $esign_info =  "<b>{$lang['form_renderer_03']} {$esign['username']}</b> ({$esign['user_firstname']} {$esign['user_lastname']})
                                    {$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($esign['timestamp']);
                    // Text for bottom of page
                    $esignature_text .= "<div id='esignts'>$esign_info</div>";
                    // Text for top of page
                    print "<div class='darkgreen' id='esign_msg'><img src='" . APP_PATH_IMAGES . "tick_shield.png'> $esign_info</div>";
                }
                // If not already e-signed and user has NO e-sign rights, then do not show e-signature option
                elseif (!$is_esigned && $user_rights['lock_record'] < 2)
                {
                    $esignature_text = "";
                }
            }
            // User has set the option to NOT show the e-signature for this form
            else
            {
                $esignature_text = "";
            }
            ## LOCKING: Disable all fields if form has been locked for this record when user does not have lock/unlock privileges
            $sql = "select l.username, l.timestamp, u.user_firstname, u.user_lastname from redcap_locking_data l
                    left outer join redcap_user_information u on l.username = u.username
                    where l.project_id = " . PROJECT_ID . " and l.record = '" . db_escape($_GET['id'].$entry_num) . "'
                    and l.event_id = {$_GET['event_id']} and l.form_name = '" . db_escape($_GET['page']) . "'
                    and l.instance = '".db_escape($_GET['instance'])."' limit 1";
            $q = db_query($sql);
            if (db_num_rows($q))
            {
                // Set flag that form is locked
                $form_locked['status'] = true;
                // Set username and timestamp of locked record
                $form_locked['timestamp'] = db_result($q, 0, 'timestamp');
                $form_locked['user_firstname']   = db_result($q, 0, 'user_firstname');
                $form_locked['user_lastname']    = db_result($q, 0, 'user_lastname');
                $form_locked['username']  		 = db_result($q, 0, 'username');
                // Set flag to disable all fields on this form
                $disable_all = true;
                // Give message to user about all fields being disabled
                print  "<div class='red' id='lock_record_msg' style='text-align:left;margin:10px 0;'>
                            <div style='margin-left:2em;text-indent:-1.8em;'>
                                <img src='".APP_PATH_IMAGES."lock.png'>
                                <b>{$lang['form_renderer_35']} {$form_locked['username']}</b> ({$form_locked['user_firstname']} {$form_locked['user_lastname']})
                                {$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($form_locked['timestamp']) . "
                            </div>
                            <div class='mt-2'>                            
                                {$lang['form_renderer_38']} \"".RCView::escape($Proj->forms[$_GET["page"]]["menu"])."\" {$lang['form_renderer_39']} \"" . $_GET["id"] . "\".
                                {$lang['form_renderer_40']}
                            </div>
                        </div>
                        <br>";
            }

            print "<div id='formtop-div'>";

            // If project has repeating forms
            $repeatInstanceDropdown = "";
            $instanceList = array();
            if ($Proj->isRepeatingFormOrEvent($_GET['event_id'], $_GET['page']))
            {
                $instanceList = RepeatInstance::getRepeatFormInstanceList(addDDEending($_GET['id']), $_GET['event_id'], $_GET['page'], $Proj);
            }
            if ($Proj->isRepeatingForm($_GET['event_id'], $_GET['page']))
            {
                // Get instance count
                $instanceCount = count($instanceList);
                $instanceMax = max(array_keys($instanceList));
                if ($instanceCount > 0)
                {
                    // CUSTOM FORM LABEL PIPING: Gather field names of all custom form labels (if any)
                    $pipedFormLabels = RepeatInstance::getPipedCustomRepeatingFormLabels(addDDEending($_GET['id']), $_GET['event_id'], $_GET['page']);
                    // Get pre-piped custom repeating form label
                    $pre_piped_label = $Proj->RepeatingFormsEvents[$_GET['event_id']][$_GET['page']];
                    // Set html for drop-down list
                    $repeatInstanceDropdownChoices = "";
                    $instanceChoiceBaseUrl = APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&page={$_GET['page']}&id={$_GET['id']}&event_id={$_GET['event_id']}&instance=";
                    foreach ($instanceList as $this_instance=>$this_form_status)
                    {
                        // Pipe any custom form labels
                        $pipedLabel = "";
                        if ($pre_piped_label != "" && isset($pipedFormLabels[addDDEending($_GET['id'])][$this_instance])) {
                            $pipedLabel = "<span style='color:#999;margin:0 4px 0 2px;'>&ndash;</span>"
                                        . $pipedFormLabels[addDDEending($_GET['id'])][$this_instance];
                        }
                        // Set bg color if on this instance
                        $instanceBgColor = ($this_instance == $_GET['instance']) ? "background:#dadada;" : "";
                        // Add choice
                        $repeatInstanceDropdownChoices .= RCView::li(array('style'=>$instanceBgColor),
                                                            RCView::a(array('href'=>$instanceChoiceBaseUrl.$this_instance, 'style'=>'display:block;text-decoration:none;'),
                                                                "<span style='vertical-align:middle;'>".RCView::img(array('src'=>RepeatInstance::getStatusIcon($this_form_status))) .
                                                                "<span style='color:#800000;'><span style='margin:0 2px;'>$this_instance</span>".
                                                                "</span>$pipedLabel</span>"
                                                            )
                                                        );
                    }
                    // Add "add new instance" choice to drop-down (if we're not already on it)
                    if ($_GET['instance'] <= $instanceMax) {
                        $repeatInstanceDropdownChoices .= RCView::li(array('style'=>'border-top:1px solid #eee;padding:5px 10px;'),
                                                            RCView::button(array('class'=>'btn btn-defaultrc btnAddRptEv', 'onclick'=>"window.location.href='".$instanceChoiceBaseUrl.($instanceMax+1)."';return false;",
                                                                'style'=>'padding:0px 4px !important;margin:0 2px 2px 0;font-size:12px !important;'), ' + ' . $lang['data_entry_247'])
                                                        );
                    }
                    // Is this a partially/completed response?
                    $formStatus = $element_data[$_GET['page']."_complete"];
                    if (!isset($instanceList[$_GET['instance']])) {
                        $formStatus = '';
                    } elseif ($_GET['instance'] > $instanceMax) {
                        $formStatus = '';
                        $instanceMax++;
                    } elseif (isset($Proj->forms[$_GET['page']]['survey_id'])) {
                        $responseCompleted = Survey::isResponseCompleted($Proj->forms[$_GET['page']]['survey_id'], addDDEending($_GET['id']), $_GET['event_id'], $_GET['instance']);
                        if ($responseCompleted !== false) {
                            // Set to 4=completed or 3=partial
                            $formStatus = $responseCompleted == 1 ? 4 : 3;
                        }
                    }
                    // Current instance piping: Pipe any custom form labels
                    $pipedLabel = "";
                    if ($pre_piped_label != "") {
                        $pipedLabel = trim(Piping::replaceVariablesInLabel($pre_piped_label, addDDEending($_GET['id']), $_GET['event_id'], $_GET['instance'], null,
                                            true, null, false, $_GET['page'], 1, false, false, $_GET['page']));
                        if ($pipedLabel != "") $pipedLabel = "<span style='color:#999;margin:0 4px 0 2px;'>&ndash;</span>$pipedLabel";
                    }
                    // Output the drop-down
                    $repeatInstanceDropdown = RCView::div(array('style'=>'float:left;'),
                                                RCView::span(array('style'=>'font-size:13px;margin-right:5px;'), $lang['grid_46']) .
                                                RCView::button(array('id'=>'repeatInstanceDropdown', 'onclick'=>"showBtnDropdownList(this,event,'repeatInstanceDropdownDiv');return false;", 'class'=>'jqbuttonmed'),
                                                    RCView::img(array('src'=>RepeatInstance::getStatusIcon($formStatus), 'style'=>'vertical-align:middle;margin-left:2px;')) .
                                                    "<span style='vertical-align:middle;'><span style='color:#800000;'><span style='margin:0 2px;'>{$_GET['instance']}</span>".
                                                    "</span>$pipedLabel</span>" .
                                                    RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:6px;vertical-align:middle;position:relative;top:-1px;'))
                                                ) .
                                                RCView::div(array('id'=>'repeatInstanceDropdownDiv', 'style'=>'z-index:2;display:none;position:absolute;'),
                                                    RCView::ul(array('id'=>'repeatInstanceDropdownUl'),
                                                        $repeatInstanceDropdownChoices
                                                    )
                                                )
                                              );
                }
            }

            // Get DAGs
            $dags = $Proj->getGroups();

            // Display repeating instance drop-down here and then reset the string
            if ($repeatInstanceDropdown != "" && (!empty($dags) || !isset($Proj->forms[$_GET['page']]['survey_id']))) {
                print $repeatInstanceDropdown;
                $repeatInstanceDropdown = "";
            }

            //If a user group exists for this project, show all groups in drop-down
            if (!empty($dags))
            {
                if ($user_rights['group_id'] == "") {
                    //User not in a group but groups exist, so give choice to associate this record with a group if not already associated with a group
                    // If record already in a DAG, then display DAG name. ONLY allow editing of DAG when creating record.
                    if ($hidden_edit) {
                        // Existing record: Display DAG name
                        print "<div class='wrap' style='float:right;margin-right:5px;'>".$lang['global_78'].$lang['colon']."<span style='margin-left:6px;'>";
                        if ($element_data['__GROUPID__'] != '') {
                            print RCView::b(RCView::escape($dags[$element_data['__GROUPID__']]));
                        } else {
                            print RCView::b($lang['data_access_groups_ajax_23']);
                        }
                        print  "</span>
                                <a href='javascript:;' title='".js_escape($lang['data_entry_330'].($element_data['__GROUPID__'] != '' ? " ".$lang['data_entry_324'] : ''))."' class='help' onclick=\"simpleDialog('".js_escape($lang['data_entry_331'])."','".js_escape($lang['data_entry_330'])."',null,550);\">?</a>
                                </div>";
                    }
                    // Display DAG dropdown (if creating record) or outut as hidden (if existing record)
                    $dagDropdownDisplay = $hidden_edit ? 'display:none;' : '';
                    print "<div class='wrap' style='float:right;$dagDropdownDisplay'>";
                    print (!isset($element_data['__GROUPID__']) || $element_data['__GROUPID__'] == '') ? $lang['form_renderer_10'] : $lang['form_renderer_11'];
                    $group_id_disabled = ($user_rights['forms'][$_GET['page']] == '2') ? 'disabled' : '';
                    print "<select name='__GROUPID__' class='x-form-text x-form-field' style='margin-left:8px;font-size:12px;' $group_id_disabled>
                           <option value=''> -- {$lang['data_access_groups_ajax_22']} -- </option>
                           <option value=''>{$lang['data_access_groups_ajax_23']}</option>";
                    foreach ($dags as $group_id=>$group_name) {
                        print "<option value='$group_id' ";
                        if (isset($element_data['__GROUPID__']) && $element_data['__GROUPID__'] == $group_id) print "selected";
                        print ">".RCView::escape($group_name)."</option>";
                    }
                    print "</select></div>";
                } else {
                    //Check to make sure user is in the same group as record. If not, don't allow access and redirect page.
                    if ((isset($element_data['__GROUPID__']) && $user_rights['group_id'] != $element_data['__GROUPID__']) || (!isset($element_data['__GROUPID__']) && $hidden_edit)) {
                        print "<script type='text/javascript'>$(function(){ $('#questiontable_loading').hide(); });</script>";
                        exit("<div class='red'>
                              <img src='".APP_PATH_IMAGES."exclamation.png'>
                              <b>{$lang['global_49']} ".$_GET['id']." {$lang['form_renderer_13']}</b><br><br>
                              {$lang['form_renderer_14']}<br><br>
                              <a href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&page=".$_GET['page']."'
                                style='text-decoration:underline'><< {$lang['form_renderer_15']}</a>
                              <br><br></div>");
                    }
                    //Since user is in a group, they can only have access to this page if the record itself is in the same group.
                    //Give a hidden field for the group id value to be saved in data table.
                    print "<input type='hidden' name='__GROUPID__' value='".$user_rights['group_id']."'>";
                }
            }

            print "<div class='clear'></div></div>";
        }
        /**
         * ONLINE DESIGNER
         */
        elseif (PAGE == "Design/online_designer.php" || PAGE == "Design/online_designer_render_fields.php")
        {
            // Default
            $table_width = " id='draggable'";
            // Add surrounding HTML to table rows when in Edit Mode on Design page (to display "add field" buttons)
            if (isset($_GET['page']))
            {
                // Set "add matrix fields" button
                $addMatrixBtn = '<input id="btn-{name}-m" type="button" class="btn2" value="'.js_escape2($lang['design_307']).'"
                                onmouseover="this.className=\'btn2 btn2hov\'" onmouseout="this.className=\'btn2\'"
                                onclick="openAddMatrix(\'\',\'{name}\')">';
                // Set array of strings to be replaced out of $bookend1 for each field
                $orig1 = array("{name}", "{rr_type}", "{branching_logic}", "{display_stopactions}", "{signature}", "{field_embed}");
                $bookend1 = '<td class="frmedit_row" style="padding:0 10px;background-color:#ddd;">
                        <div class="frmedit" style="max-width:700px;text-align:center;padding:8px;background-color:#ddd;{addFieldBtnStyle}">
                            <input id="btn-{name}-f" type="button" class="btn2" value="'.js_escape2($lang['design_309']).'"
                                onmouseover="this.className=\'btn2 btn2hov\'" onmouseout="this.className=\'btn2\'"
                                onclick="openAddQuesForm(\'{name}\',\'\',0,0)">
                            '.$addMatrixBtn.'
                        </div>
                        {matrixGroupIcons}
                        <table role="presentation" class="frmedit_tbl" id="design-{name}" cellspacing=0 width=100%>
                        <tr {style-display}>
                            <td class="frmedit" colspan="2" valign="top" style="padding:4px 0 4px 5px;border-bottom:1px solid #e5e5e5;background-color:#f3f3f3;">
                                <div class="frmedit_icons">{field_icons}</div>
                                {matrixHdrs}
                            </td>
                        </tr>
                        <tr>';
                $bookend2 = '</tr></table></td>';
                //Last "Add Field Here" button at bottom
                if (PAGE == "Design/online_designer.php" || (PAGE == "Design/online_designer_render_fields.php" && (isset($_GET['ordering']) && $_GET['ordering'] == "1")))
                {
                    $bookend3 = '<tr NoDrag="1"><td class="frmedit" style="padding:0 10px;background-color:#DDDDDD;">
                                 <div style="max-width:700px;text-align:center;padding:8px;background-color:#ddd;">
                                    <input id="btn-last" type="button" class="btn2" value="'.js_escape2($lang['design_309']).'"
                                        onmouseover="this.className=\'btn2 btn2hov\'" onmouseout="this.className=\'btn2\'"
                                        onclick="openAddQuesForm(\'\',\'\',0,0)">
                                    '.$addMatrixBtn.'
                                 </div>
                                 </td></tr>';
                    $table_width = "style='width:100%;border:1px solid #bbb;' id='draggable'";
                }
            }
        }
        // CHECK LOCKING STATUS FOR SURVEYS
        if ($isSurveyPage && isset($_GET['event_id']))
        {
            ## RECORD-LEVEL LOCKING: Check if record has been locked at record level
            $lockingWhole = new Locking();
            $lockingWhole->findLockedWholeRecord(PROJECT_ID, $_GET['id'], getArm());
            ## LOCKING: Disable all fields if form has been locked for this record when user does not have lock/unlock privileges
            $sql = "select l.username, l.timestamp, u.user_firstname, u.user_lastname from redcap_locking_data l
                    left outer join redcap_user_information u on l.username = u.username
                    where l.project_id = " . PROJECT_ID . " and l.record = '" . db_escape($_GET['id']) . "'
                    and l.event_id = {$_GET['event_id']} and l.form_name = '" . db_escape($_GET['page']) . "'
                    and l.instance = '".db_escape($_GET['instance'])."' limit 1";
            if (isset($lockingWhole->lockedWhole[$_GET['id']]) || db_num_rows(db_query($sql))) {
                // Set flag to disable all fields on this form
                $disable_all = true;
                // Hide SAVE buttons and display notification of why page is disabled
                ?>
                <script type='text/javascript'>
                $(function(){
                    $('form#form tr.surveysubmit').remove();
                    $('form#form').before('<?php print js_escape(RCView::div(array('class'=>'yellow', 'style'=>'max-width:97%;'), RCView::img(array( 'src'=>'exclamation_orange.png')) . $lang['survey_674'])) ?>');
                });
                </script>
                <style type="text/css">
                </style>
                <?php
            }
        }
        // If VIEWING A SURVEY RESPONSE on the first form, set flag to disable all fields on this form
        if (!$isSurveyPage && PAGE == 'DataEntry/index.php' && isset($_GET['id']) && $surveys_enabled
            && isset($Proj->forms[$_GET['page']]['survey_id']) && $hidden_edit == 1 && isset($Proj->forms[$_GET['page']]['survey_id']))
        {
            // Now ensure that this was an actual survey response and not entered as manual response on the form
            $survey_id = $Proj->forms[$_GET['page']]['survey_id'];
            // Determine if we should go and pre-fetch the participant hash/survey link, which creates a placeholder in the surveys response table
            $fetchHash = !($Proj->isRepeatingFormOrEvent($_GET['event_id'], $_GET['page']) && !isset($instanceList[$_GET['instance']]));
            // Get participant_id and hash for this event-record-survey
            if ($fetchHash) {
                list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($survey_id, addDDEending($_GET['id']), $_GET['event_id'], false, $_GET['instance']);
            } else {
                $hash = '';
            }
            // Create survey action buttons as a drop-down
            $surveyActionDropdown = "";
            if ($user_rights['participants'])
            {
                // Set disabled flag for drop-down if on a repeating instance that has no data yet
                $surveyActionDropdownOnclick = $fetchHash ? "showBtnDropdownList(this,event,'SurveyActionDropDownDiv');return false;" : "simpleDialog('".js_escape($lang['survey_1085'])."','".js_escape($lang['survey_1084'])."',null,500,null,'".js_escape($lang['global_53'])."',function(){dataEntrySubmit('submit-btn-savecontinue');},'".js_escape($lang['data_entry_292'])."');return false;";
                $surveyActionDropdownOpacity = $fetchHash ? "" : "opacity65";
                // Set html for drop-down list
                $surveyActionDropdown =
                        RCView::span(array(),
                            RCView::button(array('id'=>'SurveyActionDropDown', 'onclick'=>$surveyActionDropdownOnclick, 'class'=>"jqbuttonmed $surveyActionDropdownOpacity"),
                                RCView::img(array('src'=>'blog_arrow.png', 'style'=>'vertical-align:middle;position:relative;top:-1px;margin-left:2px;')) .
                                RCView::span(array('style'=>'vertical-align:middle;margin-right:40px;'), $lang['survey_649']) .
                                RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
                            ) .
                            // Survey action drop-down (initially hidden)
                            RCView::div(array('id'=>'SurveyActionDropDownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
                                RCView::ul(array('id'=>'SurveyActionDropDownUl'),
                                    // Open survey
                                    RCView::li(array(),
                                        RCView::a(array('id'=>'surveyoption-openSurvey', 'href'=>'javascript:;', 'style'=>'display:block;text-decoration:none;color:green;', 'onclick'=>"surveyOpen('".APP_PATH_SURVEY_FULL."?s=$hash',0);".
                                            "simpleDialog(lang_data_entry_371,lang_data_entry_370,null,570,null,langStayOnPage,function(){
                                                dataEntryFormValuesChanged = false;
                                                window.location.href = $('#record-home-link').attr('href');
                                            },langLeavePage);return false;"),
                                            RCView::img(array('src'=>'arrow_right_curve.png')) .
                                            $lang['survey_220']
                                        )
                                    ) .
                                    // Log out + Open survey
                                    RCView::li(array(),
                                        RCView::a(array('id'=>'surveyoption-openSurvey', 'href'=>'javascript:;', 'style'=>'display:block;text-decoration:none;', 'onclick'=>"surveyOpen('".APP_PATH_SURVEY_FULL."?s=$hash',0);window.location.href=window.location.href+'&logout=1';return false;"),
                                            RCView::span(array('class'=>'fas fa-sign-out-alt', 'style'=>'margin-left:2px;'), '') .
                                            RCView::span(array('style'=>'margin-left:4px;'),
                                                $lang['bottom_02']
                                            ) .
                                            RCView::span(array('style'=>'margin:0 2px 0 5px;'),
                                                "+"
                                            ) .
                                            RCView::span(array('style'=>'color:green;'),
                                                RCView::img(array('src'=>'arrow_right_curve.png')) .
                                                $lang['survey_220']
                                            )
                                        )
                                    ) .
                                    // Invite respondent via email
                                    RCView::li(array(),
                                        RCView::a(array('id'=>'surveyoption-composeInvite', 'href'=>'javascript:;', 'style'=>'display:block;text-decoration:none;', 'onclick'=>"inviteFollowupSurveyPopup($survey_id,'" . db_escape($_GET['page']) . "','{$_GET['id']}','{$_GET['event_id']}','{$_GET['instance']}');return false;"),
                                            RCView::img(array('src'=>'email.png')) .
                                            $lang['survey_278']
                                        )
                                    ) .
                                    // Access Code and QR code
                                    RCView::li(array(),
                                        RCView::a(array('id'=>'surveyoption-accessCode', 'href'=>'javascript:;', 'style'=>'display:block;text-decoration:none;color:#000;', 'onclick'=>"getAccessCode('$hash');return false;"),
                                            RCView::img(array('src'=>'ticket_arrow.png')) .
                                            $lang['survey_628'] .
                                            (!gd2_enabled() ? '' :
                                                RCView::SP . $lang['global_43'] . RCView::br() .
                                                RCView::img(array('src'=>'qrcode.png')) .
                                                $lang['survey_664']
                                            )
                                        )
                                    ) .
                                    // Survey Queue (only display if queue is enabled and this record has at least one queue item
                                    (!(Survey::surveyQueueEnabled() && Survey::getSurveyQueueForRecord($_GET['id'], true)) ? '' :
                                        RCView::li(array(),
                                            RCView::a(array('id'=>'surveyoption-surveyQueue', 'href'=>'javascript:;', 'style'=>'display:block;text-decoration:none;color:#800000;', 'onclick'=>"surveyOpen('".APP_PATH_SURVEY_FULL . '?sq=' . Survey::getRecordSurveyQueueHash($_GET['id'])."',0);return false;"),
                                                RCView::img(array('src'=>'list_red.gif')) .
                                                $lang['survey_505']
                                            )
                                        )
                                    )
                                )
                            )
                        );
            }
            // Query to check if in response table
            $sql = "select p.participant_id, r.response_id, r.first_submit_time, r.completion_time, 
                    r.return_code, p.participant_identifier, p.participant_email
                    from redcap_surveys_participants p, redcap_surveys_response r where p.survey_id = $survey_id
                    and p.event_id = " . getEventId() . " and p.participant_id = r.participant_id
                    and r.record = '" . db_escape(addDDEending($_GET['id'])) . "' and r.first_submit_time is not null
                    and r.instance = '".db_escape($_GET['instance'])."' order by p.participant_email desc limit 1";
            $q = db_query($sql);
            ## RESPONSE EXISTS: A survey response exists for this record for this instrument
            if (db_num_rows($q) > 0)
            {
                // Set vars
                $response_id		      = db_result($q, 0, "response_id");
                $survey_completion_time   = db_result($q, 0, "completion_time");
                $survey_first_submit_time = db_result($q, 0, "first_submit_time");
                $return_code 			  = strtoupper(db_result($q, 0, "return_code"));
                $participant_identifier   = db_result($q, 0, "participant_identifier");
                $participant_id   		  = db_result($q, 0, "participant_id");
                $participant_email   	  = db_result($q, 0, "participant_email");
                if ($participant_identifier != "") {
                    $participant_identifier = " {$lang['form_renderer_06']} <b>$participant_identifier</b>";
                }
                // Set flag if survey is still active/editable
                $responseCompleted = ($survey_completion_time != '');
                $responsePartiallyCompleted = (!$responseCompleted && $survey_first_submit_time != '');
                // If the response is NOT active or returnable, then disable some choices in the drop-down
                if (
                    // If survey is not active anymore
                    !$Proj->surveys[$survey_id]['survey_enabled']
                    // Or if returning to a partially completed response
                    // Or if returning to a fully completed response (with Edit Completed Response option enabled)
                    || ($responseCompleted && (!$Proj->surveys[$survey_id]['save_and_return'] || !$Proj->surveys[$survey_id]['edit_completed_response']))
                ) {
                    ?>
                    <script type="text/javascript">
                    $(function(){
                        // Disable survey option drop-down choices that are no longer usable
                        $('#SurveyActionDropDownUl #surveyoption-openSurvey, #SurveyActionDropDownUl #surveyoption-composeInvite, '
                            + '#SurveyActionDropDownUl #surveyoption-accessCode').addClass('opacity35').attr('onclick','').css('cursor','default');
                        // If all choices are disabled, then disable whole drop-down
                        if ($('#SurveyActionDropDownDiv a').length == $('#SurveyActionDropDownDiv a.opacity35').length) {
                            $('#SurveyActionDropDown').button('disable');
                        }
                    });
                    </script>
                    <?php
                }
                // For Completed Responses, if there is no form status value for this form (and/or it's value was set to 0 above), then set to 2
                if ($survey_completion_time != "" && $element_data[$_GET['page']."_complete"] == '0') {
                    // Set value to 2 for page element
                    $element_data[$_GET['page']."_complete"] = '2';
                    // Now manually fix this on the back-end so that the data accurately reflects it (try update first, then insert)
                    $sql = "update redcap_data set value = '2' where project_id = $project_id and
                            event_id = " . getEventId() . " and record = '" . db_escape(addDDEending($_GET['id'])) . "' and
                            field_name = '{$_GET['page']}_complete' and instance ".($_GET['instance'] == '1' ? "is null" : "= '".db_escape($_GET['instance'])."'");
                    $q = db_query($sql);
                    if (!$q || db_affected_rows() < 1) {
                        $sql = "insert into redcap_data (project_id, event_id, record, field_name, value, instance) values
                                ($project_id, " . getEventId() . ", '" . db_escape(addDDEending($_GET['id'])) . "', '{$_GET['page']}_complete', '2', "
                              . ($_GET['instance'] == '1' ? "NULL" : "'".db_escape($_GET['instance'])."'") . ")";
                        db_query($sql);
                    }
                }
                // If survey has Save & Return Later enabled BUT participant has NO return code, then generate one on the fly and save it.
                if ($Proj->surveys[$survey_id]['save_and_return'] && $return_code == "")
                {
                    $return_code = Survey::getUniqueReturnCode($survey_id, $response_id);
                }
                // Hide SAVE AND MARK RESPONSE AS COMPLETE button since this response has been completed
                if ($enable_edit_survey_response && $survey_completion_time != "" && $user_rights['forms'][$_GET['page']] == '3' && isset($_GET['editresp']))
                {
                    ?>
                    <script type='text/javascript'>
                    $(function(){
                        $('#formSaveTip input[name="submit-btn-savecompresp"], #form :input[name="submit-btn-savecompresp"]').remove();
                    });
                    </script>
                    <?php
                }
                // Disable regular fields if user does NOT have "edit survey response" rights (value=3)
                if (!($enable_edit_survey_response && $user_rights['forms'][$_GET['page']] == '3' && isset($_GET['editresp'])))
                {
                    // Disable all fields
                    $disable_all = true;
                    // Disable record renaming (even though already disabled)
                    $user_rights['record_rename'] = 0;
                    // Hide SAVE buttons and display notification of why page is disabled
                    ?>
                    <script type='text/javascript'>
                    $(function(){
                        $('#<?php echo $table_pk ?>-tr').next('#<?php echo $table_pk ?>-tr').hide();
                        var formStatusRow = $('#'+getParameterByName('page')+'_complete-tr');
                        formStatusRow.next('tr').hide();
                        formStatusRow.next('tr').next('tr').hide();
                        formStatusRow.next('tr').next('tr').next('tr').hide();
                        formStatusRow.next('tr').next('tr').next('tr').next('tr').hide();
                    });
                    </script>
                    <style type="text/css">
                    #__SUBMITBUTTONS__-tr, #formSaveTip, #__DELETEBUTTONS__-tr, #__LOCKRECORD__-tr, td.context_msg { display: none; }
                    #<?php echo $table_pk ?>-tr {border: 1px solid #DDD; }
                    </style>
                    <?php
                }
                // PROMIS: Determine if instrument is an adaptive PROMIS instrument
                if ($isPromisInstrument)
                {
                    if ($isAutoScoringInstrument) {
                        print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0 5px;padding:5px 8px 8px;'),
                                    RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                        RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_249'])
                                    ) .
                                    $lang['data_entry_250']
                                );
                    } else {
                        print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0 5px;padding:5px 8px 8px;'),
                                    RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                        RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_217'])
                                    ) .
                                    $lang['data_entry_218']
                                );
                    }
                }
                // Display repeating instance drop-down
                if ($repeatInstanceDropdown != "") {
                    print "<div id='formtop-div' style='display:block;'>$repeatInstanceDropdown<div class='clear'></div></div>";
                }
                // Does this survey have the e-Consent Framework enabled? If so, and if the survey is complete, then prevent users from editing
                $econsentComplete = false;
                if ($survey_completion_time != "" && $user_rights['forms'][$_GET['page']] == '3' && $enable_edit_survey_response
                    && $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['pdf_auto_archive'] == '2'
                    && $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['pdf_econsent_allow_edit'] == '0')
                {
                    $disabled = "readonly";
                    $econsentComplete = true;
                    $enable_edit_survey_response = '0';
                    unset($_GET['editresp']);
                    // If user has locking rights, then display the locking checkbox and submit buttons
                    if ($user_rights['lock_record'] > 0) {
                        ?>
                        <script type="text/javascript">
                            $(function () {
                                setTimeout(function () {
                                    $('#__SUBMITBUTTONS__-tr, #__SUBMITBUTTONS__-div, #__LOCKRECORD__-tr').show();
                                }, 100);
                            });
                        </script>
                        <?php
                    }
                }
                ?>
                <div class="<?php print ($survey_completion_time == "" ? "orange" : "darkgreen") ?>" id="form_response_header">
                    <?php if ($econsentComplete) { ?>
                        <!-- Survey response is not editable -->
                        <img src="<?php echo APP_PATH_IMAGES ?>lock.png">
                        <b style="color:#A00000;"><?php echo $lang['data_entry_440'] ?></b>
                        <?php
                        if ($user_rights['lock_record'] > 0) {
                            print "<span style=\"color:#A00000;\">".$lang['data_entry_441']."</span>";
                        }
                        ?>
                    <?php } elseif ($user_rights['forms'][$_GET['page']] == '3' && $enable_edit_survey_response && !$isPromisInstrument) { ?>
                        <!-- Survey response is editable -->
                        <img src="<?php echo APP_PATH_IMAGES ?>pencil.png">
                        <b><?php echo $lang['data_entry_148'] ?></b>
                        <?php if (isset($_GET['editresp'])) { ?>
                            <b style='color:red;margin-left:5px;'><?php echo $lang['data_entry_150'] ?></b>
                        <?php } elseif ($otherUserOnPage === false) { ?>
                            &nbsp; <button id="edit-response-btn" class="jqbuttonmed" style="font-size:12px;" onclick="window.location.href = app_path_webroot+'<?php echo PAGE ?>?pid=<?php echo PROJECT_ID ?>&page='+getParameterByName('page')+'&id='+getParameterByName('id')+'&event_id='+event_id+(getParameterByName('instance')==''?'':'&instance='+getParameterByName('instance'))+'&editresp=1';return false;"><?php echo $lang['data_entry_174'] ?></button>
                        <?php } ?>
                    <?php } else { ?>
                        <!-- Survey response is read-only -->
                        <img src="<?php echo APP_PATH_IMAGES ?>lock.png">
                        <b><?php echo $lang['data_entry_146'] ?></b>
                    <?php }
                    print RCView::SP . RCView::SP . $surveyActionDropdown;
                    ?>
                    <br><br>
                    <?php
                    if ($survey_completion_time == "") {
                        // Partial survey response
                        print RCView::img(array('src'=>'circle_orange_tick.png')) . " <b>{$lang['data_entry_101']} {$lang['data_entry_100']} " . DateTimeRC::format_ts_from_ymd($survey_first_submit_time) . "</b>$participant_identifier" . $lang['period'] . " ";
                    } else {
                        // Complete survey response
                        print RCView::img(array('src'=>'circle_green_tick.png')) . " <b>" . $lang['data_entry_152'] . " " . DateTimeRC::format_ts_from_ymd($survey_completion_time) . "</b>$participant_identifier" . $lang['period'] . " ";
                    }
                    if ($isPromisInstrument)
                    {
                        if ($isAutoScoringInstrument) {
                            print " ".$lang['data_entry_254'];
                        } else {
                            print " ".$lang['data_entry_219'];
                        }
                    }
                    elseif (!$enable_edit_survey_response)
                    {
                        print " ".$lang['data_entry_167'];
                    }
                    else
                    {
                        if ($user_rights['forms'][$_GET['page']] == '3') {
                            // Survey response is editable
                            print $lang['data_entry_149'];
                            if (!isset($_GET['editresp'])) {
                                print " ".$lang['data_entry_151'];
                            }
                        } else {
                            // Survey response is read-only
                            print $lang['data_entry_147'];
                        }
                        // Display who has edited this response thus far
                        $isResponseCompleted = ($survey_completion_time == '') ? '0' : '1';
                        print " <a href='javascript:;' onclick=\"getResponseContributors('$response_id',$isResponseCompleted);return false;\">{$lang['survey_1231']}</a> {$lang['survey_1232']}";

                    }
                    // If survey has save&return enabled and this is a partial response (or is a completed response with Edit Completed Response enabled), then display Return Code
                    // Do not show Return Code if Survey Login is enabled (which means that we don't need a Return code).
                    if (!($survey_auth_enabled && ($survey_auth_apply_all_surveys || $Proj->surveys[$survey_id]['survey_auth_enabled_single']))
                        && ($survey_completion_time == "" || $Proj->surveys[$survey_id]['edit_completed_response'])
                        && $Proj->surveys[$survey_id]['save_and_return'] && $Proj->surveys[$survey_id]['save_and_return_code_bypass'] != '1' && $return_code != "")
                    {
                        print  "<div style='font-size:12px;color:#000060;padding:10px 0 2px;'>".
                                    ($Proj->surveys[$survey_id]['edit_completed_response'] ? $lang['data_entry_231'] : $lang['data_entry_117']) .
                                    "<input value='$return_code' class='staticInput' readonly style='letter-spacing:1px;margin-left:10px;color:#111;font-size:12px;width:100px;padding:2px 6px;' onclick='this.select();'>
                                </div>";
                    }
                    // Display record name
                    print  "<div style='padding:10px 0 0;color:#000066;'>
                                $table_pk_label <b>{$_GET['id']}</b>";
                    // Display event name if longitudinal
                    if ($longitudinal) {
                        print RCView::span(array('style'=>'margin-left:5px;'),
                                "&ndash; ".$Proj->eventInfo[$_GET['event_id']]['name_ext']
                              );
                    }
                    // Add instance number
                    if ($_GET['instance'] > 1) {
                        print "<span style='margin-left:5px;font-weight:normal;'>({$lang['data_entry_278']}{$_GET['instance']})</span>";
                    }
                    // Append secondary ID field value, if set for a "survey+forms" type project
                    if ($secondary_pk != '')
                    {
                        $secondary_pk_val = $Proj->getSecondaryIdVal($_GET['id']);
                        if ($secondary_pk_val != '') {
                            // Add field value and its label to context message
                            print "<span style='font-size:11px;padding-left:8px;'>({$Proj->metadata[$secondary_pk]['element_label']} <b>$secondary_pk_val</b>)</span>";
                        }
                    }
                    // If Custom Record Label is specified (such as "[last_name], [first_name]", then parse and display)
                    if ($custom_record_label != '')
                    {
                        // Replace any Smart Variable and prepend/append events and instances in Custom Record Label
                        $custom_record_label_orig = $custom_record_label;
                        if ($Proj->longitudinal) {
                            $custom_record_label = LogicTester::logicPrependEventName($custom_record_label, $Proj->getUniqueEventNames($_GET['event_id']), $Proj);
                        }
                        $custom_record_label = Piping::pipeSpecialTags($custom_record_label, $Proj->project_id, $_GET['id'], $_GET['event_id'], $_GET['instance'], USERID, false, null, $_GET['page'], false);
                        if ($Proj->hasRepeatingFormsEvents()) {
                            $custom_record_label = LogicTester::logicAppendInstance($custom_record_label, $Proj, $_GET['event_id'], $_GET['page'], $_GET['instance']);
                        }
                        print "<span style='font-size:11px;padding-left:8px;'>"
                            . filter_tags(getCustomRecordLabels($custom_record_label, $Proj->getFirstEventIdArm(getArm()),
                                $_GET['id'] . ($double_data_entry && $user_rights['double_data'] != 0 ? '--'.$user_rights['double_data'] : '')))
                            . "</span>";
                        $custom_record_label = $custom_record_label_orig; // Reset back to original
                    }
                    print  "</div>";
                    ?>
                </div>
                <br>
                <?php
            }
            ## RESPONSE DOES NOT EXIST YET: RENDER EMAIL BUTTON
            // This form is a survey, but there is no response for it yet.
            // Give the option to add a response to the survey (ONLY if have 'participants' rights)
            elseif ($user_rights['participants'])
            {
                // Check if participant has already been sent an invitation. If so, display to user.
                $sql = "select e.email_sent, q.scheduled_time_to_send, q.reminder_num, q.reason_not_sent
                        from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys_emails e,
                        redcap_surveys_emails_recipients er left join redcap_surveys_scheduler_queue q
                        on q.email_recip_id = er.email_recip_id	where p.participant_id = r.participant_id and
                        p.participant_id = er.participant_id and e.email_id = er.email_id and p.survey_id = $survey_id
                        and p.event_id = {$_GET['event_id']} and r.record = '".db_escape(addDDEending($_GET['id']))."'
                        and q.ssq_id is not null and q.status != 'DELETED' and q.instance = {$_GET['instance']}
                        order by e.email_id desc, q.reminder_num limit 1";
                $q = db_query($sql);
                $prevInviteStatus = "";
                if (db_num_rows($q)) {
                    // Invitation has been scheduled or sent
                    $inviteInfo = db_fetch_assoc($q);
                    if ($inviteInfo['reason_not_sent'] != '') {
                        // Invitation failed to sent for some reason
                        $prevInviteStatus = RCView::img(array('src'=>'exclamation.png',
                                            'title'=>$lang['survey_1080']." ".DateTimeRC::format_ts_from_ymd($inviteInfo['scheduled_time_to_send'])));
                    } elseif ($inviteInfo['email_sent'] == '' && $inviteInfo['scheduled_time_to_send'] != '') {
                        // Invitation is scheduled but not sent
                        $prevInviteStatus = RCView::img(array('src'=>'clock_fill.png',
                                            'title'=>$lang['survey_410']." ".DateTimeRC::format_ts_from_ymd($inviteInfo['scheduled_time_to_send'])));
                    } elseif ($inviteInfo['email_sent'] != '') {
                        // Invitation was sent
                        $prevInviteStatus = RCView::img(array('src'=>'email_check.png',
                                            'title'=>$lang['survey_411']." ".DateTimeRC::format_ts_from_ymd($inviteInfo['email_sent'])));
                    }
                }
                if ($prevInviteStatus == '') {
                    // Invitation NOT sent yet
                    $prevInviteStatus = RCView::img(array('src'=>'email_gray.gif',
                                        'title'=>$lang['survey_412']));
                }
                // PROMIS: Determine if instrument is an adaptive PROMIS instrument
                if ($isPromisInstrument)
                {
                    if ($isAutoScoringInstrument) {
                        print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0;padding:5px 8px 8px;'),
                                    RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                        RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_249'])
                                    ) .
                                    $lang['data_entry_250'] . " " . $lang['data_entry_253']
                                );
                    } else {
                        print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin:15px 0;padding:5px 8px 8px;'),
                                    RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
                                        RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
                                        RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_217'])
                                    ) .
                                    $lang['data_entry_218'] . " " . $lang['data_entry_221']
                                );
                    }
                }
                // Display survey action buttons as a drop-down
                print RCView::div(array('id'=>'inviteFollowupSurveyBtn'),
                        $repeatInstanceDropdown .
                        RCView::div(array('style'=>'float:right;'),
                            $surveyActionDropdown
                        ) .
                        RCView::div(array('style'=>'float:right;color:#777;padding:3px 20px 0 0;'),
                            "{$lang['survey_413']} &nbsp;$prevInviteStatus"
                        ) .
                        RCView::div(array('class'=>'clear'), '')
                    );
            }
        }

        // If Enhanced Choices are enabled for radios and checkboxes on surveys
        $enhanced_choices = (($isSurveyPage && $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['enhanced_choices'])
                            // or for Survey Theme View page
                            || (PAGE == 'Surveys/theme_view.php' && isset($_GET['enhanced_choices']) && $_GET['enhanced_choices'] == '1'));

        // If ALL fields on the form are disabled, then set variable to prevent branching logic prompt
        // of "Erase Value" (overwrites original value from base.js)
        if (!$isSurveyPage && $disable_all && !isset($_GET['editresp']))
        {
            ?>
            <script type='text/javascript'>
            var showEraseValuePrompt = 0;
            </script>
            <?php
        }

        // SURVEYS: Use the surveys/index.php page as a pass through for certain files (file uploads/downloads, etc.)
        if ($isSurveyPage)
        {
            $file_download_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_download.php");
            $file_delete_page   = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_delete.php");
            $image_view_page    = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/image_view.php");
        }
        else
        {
            $file_download_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id&page=" . $_GET['page'];
            $file_delete_page   = APP_PATH_WEBROOT . "DataEntry/file_delete.php?pid=$project_id&page=" . $_GET['page'];
            $image_view_page    = APP_PATH_WEBROOT . "DataEntry/image_view.php?pid=$project_id";
        }
        // DATA RESOLUTION/COMMENT LOG: Get array of fields that have a history
        if ($data_resolution_enabled > 0)
        {
            $id = isset($_GET['id']) ? $_GET['id'] : '';
            $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : '';
            $dcFieldsWithHistory = DataQuality::fieldsWithDataResHistory($id.($double_data_entry && $user_rights['double_data'] != 0 ? "--".$user_rights['double_data'] : ""), $event_id, (isset($_GET['page']) ? $_GET['page'] : ''), $_GET['instance']);
        }
        // Set default value of NULL regarding whether this form/survey has any data saved yet (to be used with @DEFAULT action tag)
        $pageHasData = null;
        // If "Require Reason for Change" is enabled, then check if this form has any data yet (if not, then disable the check) - for forms only (not surveys)
        if ($GLOBALS['require_change_reason'] && PAGE == 'DataEntry/index.php' && isset($_GET['id'])) {
            $pageHasData = Records::formHasData($_GET['id'].$entry_num, $_GET['page'], $_GET['event_id'], $_GET['instance']);
            if (!$pageHasData) print  "<script type='text/javascript'>require_change_reason=0;</script>";
        }
        // Begin rendering form table
        print "<table role='presentation' class='form_border container-fluid' $table_width><tbody class='formtbody'>";
        // Loop through each element to render each row of the form's table
        foreach ($elements as $rr_key=>$rr_array)
        {
            // Re-format labels and notes to account for any HTML
            if (isset($rr_array['label']) && $rr_array['rr_type'] != 'surveysubmit') {
                $rr_array['label'] = decode_filter_tags($rr_array['label']);
            }
            if (isset($rr_array['note'])) {
                $rr_array['note']  = decode_filter_tags($rr_array['note']);
            }
            // Set up variables for this loop
            $rr_type = $rr_array['rr_type'];
            if (isset($rr_array['name'])) 			  {	$name 		= $rr_array['name']; if (!isset($rr_array['field'])) $rr_array['field'] = $name; } else { $name = ""; }
            if (isset($rr_array['value']))
            {
                $value = $rr_array['value'];
            }
            else
            {
                $value = isset($rr_array['field']) && isset($element_data[$rr_array['field']]) ? $element_data[$rr_array['field']] : '';
            }
            if (array_key_exists('label', $rr_array))				$label 		= $rr_array['label']; else $label = "";
            if (array_key_exists('style', $rr_array)) 				$style	 	= "style=\"".$rr_array['style']."\""; else $style = "";
            if (array_key_exists('id', $rr_array))   				$id		 	= "id=\"".$rr_array['id']."\""; else $id = "";
            if (array_key_exists('disabled', $rr_array)) 		  { $disabled 	= "disabled"; $reset_radio = "none"; } else { $disabled = ""; $reset_radio = ""; }
            if (array_key_exists('onclick', $rr_array)) 			$onclick 	= "onclick=\"".$rr_array['onclick']."\""; else $onclick = "";
            if (array_key_exists('onchange', $rr_array)) 			$onchange 	= "onchange=\"".$rr_array['onchange']."\""; else $onchange = "";
            if (array_key_exists('onblur', $rr_array)) 			    $onblur 	= "onblur=\"".$rr_array['onblur']."\""; else $onblur = "";
            if (array_key_exists('onfocus', $rr_array))			    $onfocus 	= "onfocus=\"".$rr_array['onfocus']."\""; else $onfocus = "";
            if (array_key_exists('onkeyup', $rr_array))			    $onkeyup 	= "onkeyup=\"".$rr_array['onkeyup']."\""; else $onkeyup = "";
            if (array_key_exists('onkeydown', $rr_array)) 			$onkeydown 	= "onkeydown=\"".$rr_array['onkeydown']."\""; else $onkeydown = "";
            if (array_key_exists('css_element_class', $rr_array))	$class 		= "class='".$rr_array['css_element_class']."'"; else $class = "";
            if (array_key_exists('action_tag_class', $rr_array))    $action_tag_class = "class='".$rr_array['action_tag_class']."'"; else $action_tag_class = "";
            if (array_key_exists('tabindex', $rr_array))			$tabindex	= "tabindex='0'"; else $tabindex = "";
            if (array_key_exists('note', $rr_array))				$note		= "<div id='note-$name' class='note' aria-hidden='true'>".$rr_array['note']."</div>"; else $note = "";
            if (array_key_exists('src', $rr_array))				    $src 		= "src='".$rr_array['src']."'"; else $src = "";
            if (array_key_exists('field_req', $rr_array))			$field_req	= "req='1'"; else $field_req = "";
            if (array_key_exists('custom_alignment', $rr_array))	$custom_alignment = $rr_array['custom_alignment'];
            // For mobile devices, show all fields as left-aligned
            if ($isMobileDevice) {
                $custom_alignment = str_replace('R', 'L', $custom_alignment);
                if ($custom_alignment == '') $custom_alignment = 'LV';
            }
            // ACTION TAGS: Set tags as array
            $rr_array['action_tags'] = array();
            $hideChoices = $maxChoices = array();
            if (isset($rr_array['action_tag_class']) && $rr_array['action_tag_class'] != '') {
                $rr_array['action_tags'] = explode(" ", $rr_array['action_tag_class']);
            }
            if ($isFormOrSurveyPage && $name != $Proj->table_pk)
            {
                $calcChangesClass = "";
                // @USERNAME ACTION TAG
                if (in_array('@USERNAME', $rr_array['action_tags']) && $value == '') {
                    $value = UserRights::isImpersonatingUser() ? UserRights::getUsernameImpersonating() : USERID;
                    $calcChangesClass = "calcChanged";
                }
                // @WORDLIMT AND @CHARLIMIT ACTION TAGS (note: the two cannot be used together)
                if (in_array('@CHARLIMIT', $rr_array['action_tags'])) {
                    $actionTagLimitGoal = Form::getValueInActionTag($Proj->metadata[$name]['misc'], "@CHARLIMIT");
                    if (is_numeric($actionTagLimitGoal) && $actionTagLimitGoal > 0) {
                        print  "<script type='text/javascript'>$(function(){ 
                                wordcharlimit('$name', 'char', ".(int)$actionTagLimitGoal.", '".js_escape($lang['data_entry_404'])."');
                                });</script>";
                    }
                } elseif (in_array('@WORDLIMIT', $rr_array['action_tags'])) {
                    $actionTagLimitGoal = Form::getValueInActionTag($Proj->metadata[$name]['misc'], "@WORDLIMIT");
                    if (is_numeric($actionTagLimitGoal) && $actionTagLimitGoal > 0) {
                        print  "<script type='text/javascript'>$(function(){ 
                                wordcharlimit('$name', 'word', ".(int)$actionTagLimitGoal.", '".js_escape($lang['data_entry_403'])."');
                                });</script>";
                    }
                }
                // @HIDECHOICE ACTION TAG
                if (in_array('@HIDECHOICE', $rr_array['action_tags']))
                {
                    // Obtain the HIDECHOICE text for this field
                    $hideChoiceText = Form::getValueInQuotesActionTag($Proj->metadata[$name]['misc'], "@HIDECHOICE");
                    if ($hideChoiceText != "") {
                        // If a checkbox, then parse the comma-delimited values into an array
                        foreach (explode(",", $hideChoiceText) as $thisVal) {
                            $hideChoices[] = trim($thisVal)."";
                        }
                    }
                }
                // @MAXCHOICE ACTION TAG
                if (in_array('@MAXCHOICE', $rr_array['action_tags']))
                {
                    $maxChoices = Form::getMaxChoiceReached($name, $_GET['event_id']);
                }
                // @NONEOFTHEABOVE ACTION TAG (checkboxes only)
                if ($Proj->metadata[$name]['element_type'] == 'checkbox' && in_array('@NONEOFTHEABOVE', $rr_array['action_tags']))
                {
                    // Obtain the NONEOFTHEABOVE text for this field (try with quotes and no quotes)
                    $noneOfTheAboveChoiceTextNoQuotes = Form::getValueInActionTag($Proj->metadata[$name]['misc'], "@NONEOFTHEABOVE");
                    $noneOfTheAboveChoiceText = ($noneOfTheAboveChoiceTextNoQuotes == "") ? Form::getValueInQuotesActionTag($Proj->metadata[$name]['misc'], "@NONEOFTHEABOVE") : $noneOfTheAboveChoiceTextNoQuotes;
                    if ($noneOfTheAboveChoiceText != "") {
                        // If a checkbox, then parse the comma-delimited values into an array
                        $currentChoices = $normalChoices = parseEnum($Proj->metadata[$name]['element_enum']);
                        $noneOfTheAboveChoices = array();
                        foreach (explode(",", $noneOfTheAboveChoiceText) as $thisVal) {
                            $thisVal = trim($thisVal)."";
                            if (isset($currentChoices[$thisVal])) {
                                $noneOfTheAboveChoices[] = $thisVal;
                                unset($normalChoices[$thisVal]);
                            }
                        }
                        if (!empty($noneOfTheAboveChoices)) {
                            print  "<script type='text/javascript'>$(function(){
                                        noneOfTheAboveAlert('$name', '".implode(",", $noneOfTheAboveChoices)."', '".implode(",", array_keys($normalChoices))."', '".js_escape($lang['data_entry_417'])."', '".js_escape($lang['global_53'])."');
                                    });</script>";
                            // Hidden dialog
                            print RCView::simpleDialog($lang['data_entry_416']." \"<b id='noneOfTheAboveLabelDialog'></b>\" ".$lang['data_entry_413'],$lang['data_entry_412'],"noneOfTheAboveDialog");
                        }
                    }
                }
            }
            // @RANDOMORDER action tag for MC field choices
            $hasRandomOrderActionTag = (($isSurveyPage || PAGE == 'DataEntry/index.php') && isset($_GET['id']) && in_array('@RANDOMORDER', $rr_array['action_tags']));
            // @DEFAULT ACTION TAG
            $defaultValue = '';
            if (($isSurveyPage || PAGE == 'DataEntry/index.php') && isset($_GET['id']) && in_array('@DEFAULT', $rr_array['action_tags'])
                // Make sure that @DEFAULT can't be used for File Upload/Signature fields (would not make sense)
                && $Proj->metadata[$name]['element_type'] != 'file')
            {
                // Obtain the default value for this field
                $defaultValuePre = Form::getValueInQuotesActionTag($Proj->metadata[$name]['misc'], "@DEFAULT");
                // Perform piping on the default value (if needed)
                $defaultValue = Piping::replaceVariablesInLabel($defaultValuePre, $_GET['id'].$entry_num, $_GET['event_id'],
                                    $_GET['instance'], array(), false, null, false, "", 1, false, false, $_GET['page'], null, true);
                // If a survey page of a multi-page survey, check if this survey page has data yet
                if ($isSurveyPage && $hidden_edit == 1 && $question_by_section && $pageHasData === null) {

                    $pageHasData = Records::fieldsHaveData($_GET['id'].$entry_num, $pageFields[$_GET['__page__']], $_GET['event_id'], $_GET['instance']);
                }
                // If record exists, then determine if this form as a whole has data
                elseif ($hidden_edit == 1 && $pageHasData === null) { // Don't call the formHasData method unless we have to.
                    $pageHasData = Records::formHasData($_GET['id'].$entry_num, $_GET['page'], $_GET['event_id'], $_GET['instance']);
                }
                // Set value IF the record doesn't exist OR if the form has not been saved
                if ($value == '' && $defaultValue != '' && ($hidden_edit == 0 || $pageHasData === false)) {
                    $setValuesChangedFlag = false;
                    // If a checkbox, then parse the comma-delimited values into an array
                    if ($Proj->isCheckbox($name)) {
                        if (!is_array($value)) $value = array();
                        foreach (explode(",", $defaultValue) as $thisChkVal) {
                            // Do not employ @DEFAULT if @MAXCHOICE has been reached
                            if (!in_array($thisChkVal, $maxChoices)) {
                                $value[] = trim($thisChkVal);
                                $setValuesChangedFlag = true;
                            }
                        }
                    } else {
                        // Do not employ @DEFAULT if @MAXCHOICE has been reached
                        if (!in_array($defaultValue, $maxChoices)) {
                            $value = br2nl($defaultValue); // Replace any <br> tags with real line breaks in case this is getting placed into a text field
                            $setValuesChangedFlag = true;
                            $calcChangesClass = "calcChanged";
                        }
                    }
                    // Make sure the Save Changes prompt gets triggered if they try to leave the page
                    if ($setValuesChangedFlag) { ?><script type="text/javascript">dataEntryFormValuesChanged = true;</script><?php }
                    // If the Secondary Unique Field has @DEFAULT, then we should check it on pageload
                    if ($name == $secondary_pk) {
                        ?><script type="text/javascript">$(function(){ checkSecondaryUniqueField($(':input[name="<?=$name?>"]')); });</script><?php
                    }
                }
            }
            // @PLACEHOLDER ACTION TAG
            $placeholderText = "";
            if (in_array('@PLACEHOLDER', $rr_array['action_tags']))
            {
                // Obtain the PLACEHOLDER text for this field
                $placeholderText = Form::getValueInQuotesActionTag($Proj->metadata[$name]['misc'], "@PLACEHOLDER");
                if ($placeholderText != "") {
                    $placeholderText = " placeholder='".htmlspecialchars($placeholderText, ENT_QUOTES)."'";
                }
            }
            // @DISABLED ACTION TAGS: Disable field if has disabled action tag
            $hasReadonlyActionTag = Form::disableFieldViaActionTag($rr_array['action_tag_class'], $isSurveyPage);
            if (isset($rr_array['action_tag_class']) && $hasReadonlyActionTag) {
                // Set "disabled" html for this form element
                $disabled = "disabled";
                // Set CSS to hide radio "reset value" links
                $reset_radio = "none";
            }
            // Initialize value to mark fields involved with randomization with special class (for unlocking purposes)
            $randFldClass = '';
            if ((PAGE == "DataEntry/index.php" || ($isSurveyPage && $disable_all)) && isset($_GET['id']))
            {
                // Disable all fields if form has been locked for this record (do not disable __LOCKRECORDS__ field)
                if ($disable_all || $isPromisInstrument)
                {
                    // Set "disabled" html for each form element, UNLESS it's the study_id because we will lose
                    // their values (gets posted as "" value) when disabled. Make study_id field "readonly" instead.
                    $disabled = ($name == $table_pk) ? "readonly" : "disabled";
                    // Set CSS to hide radio "reset value" links
                    $reset_radio = "none";
                    // Prevent from showing Submit buttons to users w/o lock rights (because we cannot easily disable them here like other fields - more complex)
                    // But show them when user has e-sign rights and form is not e-signed
                    if ($name == "__SUBMITBUTTONS__" || $name == "__DELETEBUTTONS__")
                    {
                        if ($user_rights['lock_record'] < 1 || $wholeRecordIsLocked) {
                            // Do not display them if whole page is disabled
                            continue;
                        } elseif (
                            // Still display delete button for PROMIS instruments
                            !($name == "__DELETEBUTTONS__" && $isPromisInstrument)
                            // Still display save and delete buttons if user has e-sign rights and form is not e-signed
                            && (!($user_rights['lock_record'] > 1 && (!isset($is_esigned) || !$is_esigned)) || !$displayEsignature)
                        ) {
                            print "<script type='text/javascript'> $(function(){ $('#$name-div').css('display','none'); });</script>";
                        }
                    }
                }
                if (!$isSurveyPage && ($disable_all || ($user_rights['forms'][$_GET['page']] == '3' && $hidden_edit == 1 && (isset($survey_first_submit_time) && $survey_first_submit_time != null))))
                {
                    // Form Status fields should not be disabled because gets posted as "", which turns into "0" as default.
                    // Instead, remove all unselected options, and if user unlocks page, it will add back the other options.
                    if ($name == $_GET['page']."_complete")
                    {
                        // If editing a survey response, then make Form Status field disabled, otherwise just leave as field with no other options.
                        $disabled = ($user_rights['forms'][$_GET['page']] == '3') ? "readonly" : "";
                        print  "<script type='text/javascript'> $(function(){ removeUnselectedFormStatusOptions(); }); </script>";
                    }
                }
                ## RANDOMIZATION
                // Lock the randomization field and strata fields
                if (!$isSurveyPage && isset($randomizationEnabled) && $randomizationEnabled && (
                    // The randomization field should ALWAYS be locked...
                    ($randomizationField == $name) ||
                    // OR if this is a criteria field (and event) for randomization AND the recod has been randomized, then lock the field
                    (isset($wasRecordRandomized) && $wasRecordRandomized && isset($randomizationCriteriaFields[$name]) && $randomizationCriteriaFields[$name] == $_GET['event_id'])
                    ))
                {
                    // If page is already locked, then lock the Randomize button too
                    $disableRandBtn = $disabled;
                    // Lock randomization/criteria fields
                    $disabled = "disabled";
                    $reset_radio = "none";
                    $randFldClass = "randomizationField";
                    // Add "Randomize" button for the randomization field (via javascript)
                    if ($randomizationField == $name && $randomizationEvent == $_GET['event_id'])
                    {
                        // Check if randomized and set text accordingly
                        $randomizeFieldDisplay = '';
                        if ($wasRecordRandomized) {
                            // If record was randomized
                            $randomize_button = $lang['random_56'];
                        } elseif (!$user_rights['random_perform']) {
                            // If record is NOT randomized, but user does NOT have permission to randomize, then give text
                            $randomize_button = RCView::span(array('style'=>'color:#888;'), $lang['random_69']);
                        } elseif ($value != '') {
                            // If randomization field somehow already has a value prior to randomization, then prevent it from getting randomized
                            $randomize_button = RCView::span(array('style'=>'color:#C00000;'), $lang['random_132']);
                        } else {
                            // Give alert that the record needs to be saved first if doesn't exist yet
                            $randomizeFieldDisplay = 'display:none;';
                            $randomize_button_onclick = "randomizeDialog('".js_escape(strip_tags(label_decode($_GET['id'])))."'); return false;";

                            $randomize_button = "<button id='redcapRandomizeBtn' class='jqbuttonmed' onclick=\"$randomize_button_onclick\" $disableRandBtn><span style='vertical-align:middle;color:green;'><i class=\"fas fa-random\"></i> {$lang['random_51']}</span></button>";
                        }
                        $randomize_button = "<div id='alreadyRandomizedText'>$randomize_button</div>";
                        ?>
                        <script type="text/javascript">
                        $(function(){
                            var randomizationFieldTdObj = $('#<?php echo $randomizationField ?>-tr td.data');
                            if (randomizationFieldTdObj.length) {
                                // Right-aligned
                                var randomizationFieldHtml = randomizationFieldTdObj.html();
                                randomizationFieldTdObj.html('<?php echo js_escape($randomize_button) ?><div id="randomizationFieldHtml" style="<?php echo $randomizeFieldDisplay ?>">'+randomizationFieldHtml+'</div>');
                            } else {
                                // Left-aligned
                                randomizationFieldTdObj = $('#<?php echo $randomizationField ?>-tr .labelrc');
                                var labelText = trim($('#<?php echo $randomizationField ?>-tr .labelrc table td:first').html());
                                var randomizationFieldHtml = randomizationFieldTdObj.html();
                                randomizationFieldTdObj.html('<div class="randomizationDuplLabel">'+labelText+'<div class="space"></div></div><?php echo js_escape($randomize_button) ?><div id="randomizationFieldHtml" style="<?php echo $randomizeFieldDisplay ?>">'+randomizationFieldHtml+'</div>');
                                <?php echo (!$wasRecordRandomized && $user_rights['random_perform']) ? "$('.randomizationDuplLabel').show();" : '' ?>
                            }
                            $('#alreadyRandomizedText button').button();
                        });
                        </script>
                        <?php
                    }
                }
            }
            // RANDOMIZATION ON SURVEY: Lock the randomization field IF it is displayed on a survey
            elseif ($isSurveyPage && isset($randomizationEnabled) && $randomizationEnabled && $randomizationField == $name)
            {
                // Lock randomization field
                $disabled = "disabled";
                $reset_radio = "none";
            }
            //If enum exists, make sure that the \n's are also treated as line breaks
            if (isset($rr_array['enum']) && strpos($rr_array['enum'],"\\n")) {
                $rr_array['enum'] = str_replace("\\n","\n",$rr_array['enum']);
            }
            // For survey pages ONLY, set $trclass as 'hidden' to hide other-page fields for multi-page surveys
            $trclass = "";
            if ($isSurveyPage) {
                if ($rr_type != 'surveysubmit' && isset($rr_array['field'])) {
                    $trclass = (in_array($rr_array['field'], $hideFields) ? " class='hidden' " : "");
                } elseif ($rr_type != 'surveysubmit' && isset($rr_array['shfield'])) {
                    $trclass = (in_array($rr_array['shfield'], $hideFields) ? " class='hidden' " : "");
                } elseif ($rr_type == 'surveysubmit') {
                    $trclass = " class='surveysubmit' ";
                }
            }
            if ($trclass == "" && $action_tag_class != "") {
                $trclass = $action_tag_class;
            }
            if (isset($rr_array['matrix_field'])) {
                if ($trclass == "") {
                    $trclass = " class='mtxfld' ";
                } else {
                    $trclass = str_replace("class='", "class='mtxfld ", $trclass);
                }
            }

            // If field is embedded elsewhere on the page, add class=hide so that branching logic will never display this field
            if (isset($embeddedFields) && $rr_type != 'matrix_header' && in_array($rr_array['field'], $embeddedFields)) {
                if ($trclass == "") {
                    $trclass = " class='hide row-field-embedded' ";
                } else {
                    $trclass = str_replace("class='", "class='hide row-field-embedded ", $trclass);
                }
            }

            ## Begin rendering row
            // Set default number of columns in table
            $sh_colspan = 2;
            // Normal Fields or Matrix header row
            if ($rr_type == 'matrix_header' || (isset($rr_array['field']) && $rr_type != 'hidden'))
            {
                // Normal field tr
                print "<tr ";
                // Add attributes to rows for matrix fields
                if (isset($rr_array['matrix_field'])) {
                    if (PAGE == "Design/online_designer.php" || PAGE == "Design/online_designer_render_fields.php") {
                        // Online Designer: Do not allow any matrix fields to be dragged
                        // Do not allow other fields to be dragged into a matrix group
                        print "NoDrag='1' class='mtxRow' NoDrop='1' ";
                    } else {
                        // Form/Survey: Add attribute to row, if a matrix field
                        print "mtxgrp='{$rr_array['grid_name']}' ";
                    }
                }
                // Set special id for matrix headers
                if ($rr_type == 'matrix_header') {
                    print "$trclass id='{$rr_array['grid_name']}-mtxhdr-tr' mtxgrp='{$rr_array['grid_name']}' ";
                } else {
                    print "$trclass id='{$rr_array['field']}-tr' sq_id='{$rr_array['field']}' $field_req";
                    // Do not allow users to move the Primary Key field in the Online Designer
                    if ($rr_array['field'] == $table_pk && (PAGE == "Design/online_designer.php" || PAGE == "Design/online_designer_render_fields.php")) {
                        print "NoDrag='1' NoDrop='1' ";
                    }
                }
                // If a saved value already exists for the field, note it as an attribute flag to use when processing required fields/branching during form submission
                if (($isSurveyPage || PAGE == "DataEntry/index.php") && isset($value) && $value != "") {
                    print " hasval='1'";
                }
                // Do not put red bar on @DEFAULT fields
                if ($defaultValue != '' && $pageHasData && isset($value) && $value != "") {
                    print " ignoreDefault='1'";
                }
                print ">";
                $end_row = "</tr>";
                // For surveys, add extra table cell in each row for placing question numbers (for both custom and auto numbering)
                if ($isSurveyPage)
                {
                    $quesnum_class = (isset($rr_array['matrix_field'])) ? "labelmatrix questionnummatrix col-1" : "labelrc questionnum col-1";
                    print "<td class='$quesnum_class' valign='top'>";
                    // Add custom number, if option is enabled for survey
                    if (!$question_auto_numbering && $rr_type != 'matrix_header') {
                        print $Proj->metadata[$rr_array['field']]['question_num'];
                    }
                    print "</td>";
                }
            }
            // Section Headers
            elseif (isset($rr_array['shfield']) && $rr_type != 'hidden')
            {
                print "<tr $trclass id='{$rr_array['shfield']}-sh-tr' sq_id='{" . (isset($rr_array['field']) ? $rr_array['field'] : '') . "}'>";
                $end_row = "</tr>";
                // For surveys, change colspan to 3 to deal with table modification due to addition of new cell for question numbers
                if ($isSurveyPage) {
                    $sh_colspan = 3;
                }
            }
            // For survey submit buttons
            elseif ($rr_type == 'surveysubmit')
            {
                print "<tr $trclass>";
                $end_row = "</tr>";
                $sh_colspan = 3;
            }
            // For other matters
            elseif ($rr_type != 'hidden')
            {
                print "<tr $trclass>";
                $end_row = "</tr>";
            }
            // Hidden Fields
            else
            {
                $end_row = "";
            }
            // If on Design page, add "add field" button and show icons
            if (PAGE == "Design/online_designer.php" || PAGE == "Design/online_designer_render_fields.php")
            {
                // HTML to display matrix group icons for actions
                $matrixGroupIcons = "";
                if ((isset($rr_array['matrix_field']) && $rr_array['matrix_field'] == '1' && $rr_type == "header") || (isset($rr_array['matrix_field']) && $rr_array['matrix_field'] == '1' && !isset($rr_array['hasSH']))) {
                    $matrixGroupIcons = "<div class='designMtxGrpIcons'>
                                            <a href='javascript:;' onclick=\"openAddMatrix('{name}','')\"
                                            ><img src='".APP_PATH_IMAGES."pencil.png' ignore='Yes' title='Edit Matrix'></a>&nbsp;
                                            <a href='javascript:;' onclick=\"moveField('{name}','{$rr_array['grid_name']}')\"
                                            ><img src='".APP_PATH_IMAGES."file_move.png' ignore='Yes' title='Move Matrix'></a>&nbsp;
                                            <a href='javascript:;' onclick=\"deleteMatrix('{name}','{$rr_array['grid_name']}');\"
                                            ><img src='".APP_PATH_IMAGES."cross.png' ignore='Yes' title='Delete Matrix'></a>
                                            &nbsp;&nbsp;&nbsp;
                                            <span class='mtxgrpname'>
                                                <i>{$lang['design_302']}</i>&nbsp; {$rr_array['grid_name']}
                                            </span>
                                         </div>";
                }
                // Hide icons for Section Headers, as they are not applicable
                // Flag to show/hide the Add Field buttons
                $addFieldBtnStyle = "";
                if ($rr_type == "header") {
                    ## SECTION HEADER
                    $this_bookend1 = str_replace(array("{style-display}","{matrixHdrs}","{matrixGroupIcons}","{field_icons}","{addFieldBtnStyle}"),
                                                 array('style="display:none;"','',$matrixGroupIcons,"",$addFieldBtnStyle),
                                                 $bookend1);
                    $name = $rr_array['field'];
                } else {
                    ## REGULAR FIELDS
                    // Replace string for Matrix question headers to display
                    $matrixHdrsRepl = '';
                    $fieldIcons =  '<a href="javascript:;" onclick="openAddQuesForm(\'{name}\',\'{rr_type}\',0,\'{signature}\');"
                                    ><img src="'.APP_PATH_IMAGES.'pencil.png" ignore="Yes" title="Edit"></a>&nbsp;
                                    <a href="javascript:;" onClick="copyField(\'{name}\')"
                                    ><img src="'.APP_PATH_IMAGES.'page_copy.png" ignore="Yes" title="Copy"/></a>&nbsp;
                                    <a href="javascript:;" onClick="openLogicBuilder(\'{name}\')"
                                    ><img src="'.APP_PATH_IMAGES.'arrow_branch_side.png" ignore="Yes" title="Branching Logic"/></a>&nbsp;
                                    <a href="javascript:;" onClick="moveField(\'{name}\',\'\')"
                                    ><img src="'.APP_PATH_IMAGES.'file_move.png" ignore="Yes" title="Move"/></a>&nbsp;
                                    <a href="javascript:;" onClick="setStopActions(\'{name}\')"
                                    ><img style="display:{display_stopactions};margin-right:4px;" src="'.APP_PATH_IMAGES.'stopsign.gif" ignore="Yes" title="Stop Actions for Surveys"/></a
                                    ><a href="javascript:;" onClick="deleteField(\'{name}\',0);"
                                    ><img src="'.APP_PATH_IMAGES.'cross.png" ignore="Yes" title="Delete Field"/></a>
                                    &nbsp;&nbsp;&nbsp;
                                    <span class="designVarName">
                                        <i>'.$lang['form_renderer_16'].'</i> {name}
                                        {branching_logic}
                                        {field_embed}
                                        '.(isset($Proj->forms[$_GET['page']]['survey_id']) ? '<span class="pkNoDispMsg"></span>' : '').'
                                    </span>';
                    // If this is first field (PK field), then hide Add Question button and remove delete/move icons
                    if ($rr_array['field'] == $table_pk)
                    {
                        $addFieldBtnStyle = "visibility:hidden;padding:1px;height:10px;";
                        $fieldIcons =  '<a href="javascript:;" onclick="openAddQuesForm(\'{name}\',\'{rr_type}\',0,\'{signature}\');"
                                        ><img src="'.APP_PATH_IMAGES.'pencil.png" ignore="Yes" title="Edit"></a>
                                        &nbsp;&nbsp;&nbsp;
                                        <span class="designVarName">
                                            <i>'.$lang['form_renderer_16'].'</i> {name}
                                            {branching_logic}
                                            {field_embed}
                                            '.(isset($Proj->forms[$_GET['page']]['survey_id']) ? '<span class="pkNoDispMsg"></span>' : '').'
                                        </span>';
                    }
                    // Format matrix fields differently
                    elseif (isset($rr_array['enum']) && $rr_array['enum'] != "" && isset($rr_array['matrix_field']))
                    {
                        // Only show matrix column headers for first field in matrix group
                        if ($rr_array['matrix_field'] == '1') {
                            $matrixHdrsRepl =  DataEntry::matrixHeaderTable($rr_array, DataEntry::getMatrixHdrWidths($rr_array), $rr_array['matrix_field'], $rr_array['grid_rank']);
                        }
                        // Hide Add Field buttons between matrix questions (swap out CSS to hide it)
                        if ($rr_array['matrix_field'] != '1' || ($rr_array['matrix_field'] == '1' && isset($rr_array['hasSH']))) {
                            $addFieldBtnStyle = "visibility:hidden;padding:1px;height:0;";
                        }
                        // Use different field icons for matrix fields
                        $fieldIcons =  '<a href="javascript:;" onClick="openLogicBuilder(\'{name}\')"
                                        ><img src="'.APP_PATH_IMAGES.'arrow_branch_side.png" ignore="Yes" title="Branching Logic"/></a
                                        >&nbsp;
                                        <a href="javascript:;" onClick="setStopActions(\'{name}\')"
                                        ><img style="display:{display_stopactions};margin-right:4px;" src="'.APP_PATH_IMAGES.'stopsign.gif" ignore="Yes" title="Stop Actions for Surveys"/></a>
                                        &nbsp;&nbsp;&nbsp;
                                        <span style="font-size:10px;position:relative;top:-4px;">
                                            <i>'.$lang['form_renderer_16'].'</i> {name}
                                            {branching_logic}
                                            {field_embed}
                                        </span>';
                    }
                    // Replace the strings
                    $this_bookend1 = str_replace(array("{field_icons}","{matrixHdrs}","{addFieldBtnStyle}","{matrixGroupIcons}","{style-display}"),
                                                 array($fieldIcons, $matrixHdrsRepl,$addFieldBtnStyle,$matrixGroupIcons,""),
                                                 $bookend1);
                }
                // If form is set up as a survey AND this field is multiple choice, show the Stop Action icon
                $displayStopAction = "none";
                if (isset($Proj->forms[$_GET['page']]['survey_id']) && in_array($rr_type, array('select','radio','yesno','truefalse','checkbox'))) {
                    $displayStopAction = "";
                }
                // Set up replacement values ("sql" field type is special case since it is rendered as "select")
                $repl1 = array($name, $rr_type, (isset($rr_array['branching_logic']) ? $rr_array['branching_logic'] : ''), $displayStopAction,
                                (isset($rr_array['validation']) && $rr_array['validation'] == 'signature' ? '1' : '0'),
                                (isset($rr_array['field_embed']) ? $rr_array['field_embed'] : ''));
                if ($rr_type == "select") {
                    if (in_array($name, $sql_fields)) {
                        $repl1 = array($name, "sql", $rr_array['branching_logic'], $displayStopAction, ($rr_array['validation'] == 'signature' ? '1' : '0'));
                    }
                }
                // Replace strings to customize each field in Design mode
                print str_replace($orig1, $repl1, $this_bookend1);
            }
            // For data entry forms, render ICON FOR DATA HISTORY (i.e. replace label with extra surrounding html)
            elseif (PAGE == "DataEntry/index.php" && isset($_GET['id'])
                // exclude certain field types
                && !in_array($rr_type, array("static", "hidden", "button", "lock_record", "esignature", "descriptive", "matrix_header")))
            {
                $tdColWidth1 = !empty($missingDataCodes) ? 1 : 0;
                $tdColWidth2 = $history_widget_enabled ? 1 : 0;
                $tdColWidth3 = $data_resolution_enabled ? 1 : 0;
                $label =   "<table role='presentation' style='width:100%;height:100%;max-height:100%;' cellspacing='0' cellpadding='0'>
                                <tr>
                                    <td>
                                        $label
                                    </td>
    
                                    <td style='".(($tdColWidth1+$tdColWidth2+$tdColWidth3 >= 2) ? "width:40px;" : "width:21px;")."padding-left:5px;text-align:right;' class='rc-field-icons'>";
                // If data history widget is enabled, display the icon
                if ($history_widget_enabled) {
                    $dataHistWidth = ($rr_type == 'file' && $Proj->metadata[$name]['element_validation_type'] != 'signature'
                                        && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID)) ? 900 : 750;
                    if ($GLOBALS['require_change_reason']) $dataHistWidth += 100;
                    $label .=  "		<a href='javascript:;' tabindex='-1' onclick=\"dataHist('$name',{$_GET['event_id']},$dataHistWidth);return false;\"><img src='".APP_PATH_IMAGES."history.png'
                                        title=\"".js_escape2($lang['data_entry_181'])."\" onmouseover='dh1(this)' onmouseout='dh2(this)' style='margin-bottom:1px;'></a><br>";
                }
                // Data Resolution icon
                if ($data_resolution_enabled > 0) {
                    // Set icon to display (gray if field has no data cleaner history)
                    $fieldHasDRHistory = array_key_exists($name, $dcFieldsWithHistory);
                    if ($fieldHasDRHistory) {
                        $dc_field_mouse_over = "";
                        if ($dcFieldsWithHistory[$name]['status'] == 'OPEN' && !$dcFieldsWithHistory[$name]['responded']) {
                            $dc_field_icon = "balloon_exclamation.gif";
                        } elseif ($dcFieldsWithHistory[$name]['status'] == 'OPEN' && $dcFieldsWithHistory[$name]['responded']) {
                            $dc_field_icon = "balloon_exclamation_blue.gif";
                        } elseif ($dcFieldsWithHistory[$name]['status'] == 'CLOSED') {
                            $dc_field_icon = "balloon_tick.gif";
                        } elseif ($dcFieldsWithHistory[$name]['status'] == 'VERIFIED') {
                            $dc_field_icon = "tick_circle.png";
                        } elseif ($dcFieldsWithHistory[$name]['status'] == 'DEVERIFIED') {
                            $dc_field_icon = "exclamation_red.png";
                        } else {
                            $dc_field_icon = "balloon_left.png";
                        }
                    } else {
                        $dc_field_icon = "balloon_left_bw2.gif";
                        $dc_field_mouse_over = "onmouseover='dc1(this)' onmouseout='dc2(this)'";
                    }
                    $fieldForm = '';
                    $hasFormEditRights = false;
                    if (isset($name))
                    {
                        $fieldForm = $Proj->metadata[$name]['form_name'];
                        $hasFormEditRights = ($user_rights['forms'][$Proj->metadata[$name]['form_name']] != '2');
                    }
                    // Display DR balloon icon if...
                    if (
                        // Has field comment log enabled AND
                        ($data_resolution_enabled == '1' && ($fieldHasDRHistory || (!$fieldHasDRHistory && $hasFormEditRights)))
                        // OR has DQ resolution workflow enabled AND (user has edit rights OR user has view rights and field has history)
                        || ($data_resolution_enabled == '2'
                            && ($user_rights['data_quality_resolution'] > 1 || ($fieldHasDRHistory && $user_rights['data_quality_resolution'] > 0))))
                    {
                        $dc_field_icon_title = ($data_resolution_enabled == '1') ? $lang['dataqueries_145'] : $lang['dataqueries_140'];
                        $label .=  "	<a href='javascript:;' tabindex='-1' onclick=\"dataResPopup('$name',{$_GET['event_id']},null,null,null,{$_GET['instance']});return false;\"><img id='dc-icon-$name' src='".APP_PATH_IMAGES."$dc_field_icon'
                                        title='".js_escape($dc_field_icon_title)."' $dc_field_mouse_over></a>";
                    }
                }

                // Add missing Data Button to field, if form unlocked
                // (Except for:
                //		Form Status dropdowns and checkboxes- these should not need to be marked as missing
                //		Calculated fields, File uploads and signatures- not supported yet
                //)
                $hideMDButton="";
                if($form_locked['status']) {
                    $hideMDButton="style='display: none;'";
                }
                if (!empty($missingDataCodes) && !$disable_all && !in_array('@NOMISSING', $rr_array['action_tags']) && !$hasReadonlyActionTag
                    // Don't display icon for calc fields for form status fields
                    && $rr_array['rr_type'] != 'calc' && $name != $_GET['page']."_complete"
                    // Don't display icon for randomization field/event
                    && !($randomizationEnabled && $randomizationField == $name && $randomizationEvent == $_GET['event_id'])
                ) {
                    //set MissingDataButton to active image is the field contains missing data code
                    $imgURL=APP_PATH_IMAGES."missing.png";
                    $mouseOver="onmouseover='md1(this)' onmouseout='md2(this)'";
                    //code for setting the image to active when field contains missing data. Couldn't quite get this working right!
                    $qType=$rr_array['rr_type'];
                    $thisValue=$value;
                    if ($qType=='checkbox'){
                        $thisValue=$value[0];
                    }
                    if (isset($missingDataCodes[$thisValue])) {
                        $imgURL=APP_PATH_IMAGES."missing_active.png";
                        $mouseOver="";
                     }
                    $label .=  "<img class='missingDataButton' name='missingDataButton' $hideMDButton fieldName=$name src='$imgURL' qtype='$qType' title=\"".js_escape2($lang['missing_data_03'])."\" $mouseOver >";
                }

                $label .=  "		</td>
                                </tr>
                            </table>";
            }
            // Wrap the field label with <label> tags to work better with screen readers
            $ariaLabelledBy = "";
            if ($name != '' && $rr_type != 'descriptive') {
                $label = "<label class='fl' id='label-$name' aria-hidden='true'>$label</label>";
                // Set id of field label as aria-labelledby
                $ariaLabelledBy = "label-$name";
                // Add field note if defined
                if ($note != "") $ariaLabelledBy .= " note-$name";
            }
            // Render html table row for each field type
            switch ($rr_type)
            {
                // Section headers AND context messages
                case 'header':
                    if (PAGE == "DataEntry/index.php" || $isSurveyPage) {
                        $value = decode_filter_tags($value);
                    }
                    print "<td $class $style colspan='$sh_colspan'>$value</td>";
                    break;
                // Survey "submit" buttons
                case 'surveysubmit':
                    print  "<td class='labelrc col-12' style='padding:5px;' colspan='$sh_colspan'>$label</td>";
                    break;
                // Descriptive text with option image/file attachment OR embedded video
                case 'descriptive':
                    print "<td class='labelrc $colClassCombined' colspan='2'>$label";
                    // Check if has a file attachment or video url
                    $edoc_id = isset($rr_array['edoc_id']) ? $rr_array['edoc_id'] : '';
                    $edoc_display_img = isset($rr_array['edoc_display_img']) ? $rr_array['edoc_display_img'] : '';
                    $video_url = isset($rr_array['video_url']) ? $rr_array['video_url'] : '';
                    $video_display_inline = isset($rr_array['video_display_inline']) ? $rr_array['video_display_inline'] : '';
                    // ATTACHMENT OR INLINE IMAGE
                    if (is_numeric($edoc_id))
                    {
                        // Query edocs table to get file attachment info
                        $sql = "select * from redcap_edocs_metadata where project_id = " . PROJECT_ID . " and delete_date is null
                                and doc_id = $edoc_id";
                        $q = db_query($sql);
                        // Show text for downloading file or viewing image
                        if (db_num_rows($q) < 1)
                        {
                            print "<br><br><i style='font-weight:normal;color:#666;'>{$lang['design_204']}</i>";
                        }
                        else
                        {
                            $edoc_info = db_fetch_assoc($q);
                            //Set max-width for logo (include for mobile devices)
                            $img_attach_width = (isset($isMobileDevice) && $isMobileDevice) ? '250' : '670';
                            // If an image file and set to view as image, then do so and resize (if needed)
                            $img_types = array("jpeg", "jpg", "gif", "png", "bmp");
                            if ($edoc_display_img == '2')
                            {
                                // Embedded audio
                                if ($isIE && $edoc_info['mime_type'] != 'audio/mp3' && $edoc_info['mime_type'] != 'audio/mpeg'){ //if IE use the embed tag
                                    print "<br><br><embed src='$file_download_page&doc_id_hash=".Files::docIdHash($edoc_id)."&instance={$_GET['instance']}&id=$edoc_id&stream=1"
                                    . ($isSurveyPage ? "&s=".$_GET['s'] : "") . "' autostart=false class='audio-player'>";
                                } else{ //use the HTML audio tag on chrome and other browsers
                                    print "<br><br><audio controls='controls' type='{$edoc_info['mime_type']}'><source src='$file_download_page&doc_id_hash=".Files::docIdHash($edoc_id)."&instance={$_GET['instance']}&id=$edoc_id&stream=1"
                                                . ($isSurveyPage ? "&s=".$_GET['s'] : "") . "'/>{$lang['global_121']}</audio>";
                                }
                            }
                            elseif ($edoc_display_img == '1' && in_array(strtolower($edoc_info['file_extension']), $img_types))
                            {
                                // Get img dimensions (local file storage only)
                                $thisImgMaxWidth = $img_attach_width;
                                $styleDim = "max-width:{$thisImgMaxWidth}px;";
                                list ($thisImgWidth, $thisImgHeight) = Files::getImgWidthHeightByDocId($edoc_id);
                                $nativeDim = '0';
                                if (is_numeric($thisImgHeight)) {
                                    $thisImgMaxHeight = round($thisImgMaxWidth/$thisImgWidth*$thisImgHeight);
                                    if ($thisImgWidth < $thisImgMaxWidth) {
                                        // Use native dimensions
                                        $styleDim = "width:{$thisImgWidth}px;max-width:{$thisImgWidth}px;height:{$thisImgHeight}px;max-height:{$thisImgHeight}px;";
                                        $nativeDim = '1';
                                    } else {
                                        // Shrink size
                                        $styleDim = "width:{$thisImgMaxWidth}px;max-width:{$thisImgMaxWidth}px;height:{$thisImgMaxHeight}px;max-height:{$thisImgMaxHeight}px;";
                                    }
                                }
                                // Inline image
                                print "<br><br><img src='$image_view_page&doc_id_hash=".Files::docIdHash($edoc_id)."&id=$edoc_id&instance={$_GET['instance']}"
                                            . ($isSurveyPage ? "&s=".$_GET['s'] : "") .

                                            "' {$surveyImgOnload}alt='".js_escape($lang['survey_1140'])."' style='$styleDim' nativedim='$nativeDim'>";
                            }
                            // Else display as a link for download
                            else
                            {
                                // Set file size in MB
                                $edoc_info['doc_size'] = round_up($edoc_info['doc_size'] / 1024 / 1024);
                                // Display icon for appriopriate files
                                switch (strtolower($edoc_info['file_extension'])) {
                                    case "csv":	$icon = "csv.gif"; break;
                                    case "xls":
                                    case "xlsx":$icon = "xls.gif"; break;
                                    case "doc":
                                    case "docx":$icon = "doc.gif"; break;
                                    case "pdf":	$icon = "pdf.gif"; break;
                                    case "ppt":
                                    case "pptx":$icon = "ppt.gif"; break;
                                    case "jpg":
                                    case "jpeg":
                                    case "gif":
                                    case "png":
                                    case "bmp": $icon = "picture.png"; break;
                                    case "zip": $icon = "zip.png"; break;
                                    default:	$icon = "attach.png";
                                }
                                // Display link for downloading file
                                print  "<div class='div_attach'>
                                            {$lang['design_205']} &nbsp;
                                            <img src='" . APP_PATH_IMAGES . "$icon'>
                                            <a class='rc_attach' target='_blank' href='$file_download_page&type=attachment&doc_id_hash=".Files::docIdHash($edoc_id)."&instance={$_GET['instance']}&id=$edoc_id"
                                            . ($isSurveyPage ? "&s=".$_GET['s'] : "") . "'>{$edoc_info['doc_name']}</a>&nbsp;
                                            <span aria-hidden='true'>({$edoc_info['doc_size']} MB)</span>
                                        </div>";
                            }
                        }
                    }
                    // EMBEDDED VIDEO
                    elseif ($enable_field_attachment_video_url && $video_url != '')
                    {
                        // Default values
                        $unknown_video_service = '1';
                        $video_url_formatted = $video_url;
                        // Vimeo URL
                        if (stripos($video_url, 'vimeo.com') !== false
                            && preg_match("/https?:\/\/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/", $video_url, $matches)) {
                            $unknown_video_service = '0';
                            $video_url_formatted = 'https://player.vimeo.com/video/' . $matches[3];
                        }
                        // Youtube URL
                        elseif ((stripos($video_url, 'youtube.com') !== false || stripos($video_url, 'youtu.be') !== false)
                                && preg_match("/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", $video_url, $matches)) {
                            $unknown_video_service = '0';
                            $video_url_formatted = 'https://www.youtube.com/embed/' . $matches[2] . '?wmode=transparent&rel=0';
                        }
                        // Inline display
                        if ($video_display_inline == '1') {
                            $video_height = (isset($isMobileDevice) && $isMobileDevice) ? '180' : '450';
                            print "<div class='div_embed_video'>";
                            if ($unknown_video_service) {
                                print "<embed src='".js_escape($video_url_formatted)."' width='100%' height='$video_height' scale='aspect' controller='true' autostart='false' autostart='0'></embed>";
                            } else {
                                print "<iframe src='".js_escape($video_url_formatted)."' type='text/html' frameborder='0' allowfullscreen width='100%' height='$video_height'></iframe>";
                            }
                            print "</div>";
                        }
                        // Display inside popup
                        else {
                            print  "<div class='div_embed_video'>
                                        <button ignore='Yes' class='jqbuttonmed' onclick=\"openEmbedVideoDlg('".js_escape($video_url_formatted)."',$unknown_video_service,'$name');return false;\">
                                            <img src='" . APP_PATH_IMAGES . "video_icon.png'>
                                            <span>{$lang['global_120']}</span>
                                        </button>
                                    </div>";
                        }
                    }
                    print "</td>";
                    break;
                //Static element (put lots of things here)
                case 'static':
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'>$value";
                    //Let $table_pk be hidden if static (for posting purposes)
                    if ((PAGE == "DataEntry/index.php") && $rr_array['field'] == $table_pk)
                    {
                        // Use '__old_id__' field to determine if record id gets changed (if option is enabled)	when on first form
                        if (isset($user_rights) && $user_rights['record_rename'] && isset($_GET['page']) && $_GET['page'] == $Proj->firstForm)
                        {
                            // Add hidden old id field (to catch record renaming)
                            print "<input type='hidden' name='__old_id__' value='" . htmlspecialchars($value, ENT_QUOTES) . "'>";
                            print "<div style='color:#777;font-size:7pt;line-height:8pt;padding:5px 0 2px;'>".$lang['data_entry_322'];
                            print ' <a class="opacity75" style="font-size:11px;text-decoration:underline;font-size:7pt;line-height:8pt;" href="'.APP_PATH_WEBROOT.'DataEntry/record_home.php?pid='.PROJECT_ID.'&id='.$_GET['id'].($Proj->eventInfo[$_GET['event_id']]['arm_num'] > 1 ? '&arm='.$Proj->eventInfo[$_GET['event_id']]['arm_num'] : '').'">'.$lang['grid_42'].'</a>'.$lang['period'];
                            print "</div>";
                        }
                    }
                    print "</td>";
                    break;
                //Images
                case 'image':
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'><img $id $src $onclick></td>";
                    break;
                //Advcheckbox
                case 'advcheckbox':
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'>";
                    print  '<input '.$id.' type="checkbox" '." $disabled $tabindex $onchange $onblur $onfocus".' onclick="
                            document.form.'.$name.'.value=(this.checked)?1:0;doBranching(\''.$name.'\');" name="_checkbox_'.$name.'" ';
                    if ($value == '1') {
                        print 'checked> ';
                        $default_value = '1';
                    } else {
                        print '> ';
                        $default_value = '0'; //Default value is 0 if no present value exists
                    }
                    print  '<input type="hidden" aria-labelledby="'.$ariaLabelledBy.'" value="'.$default_value.'" name="'.$name.'">';
                    print  "$note</td>";
                    break;
                //Lock/Unlock records
                case 'lock_record':
                    // If form is disabled (for whatever reason), give option to lock it via ajax
                    $onclick = ($disabled != 'disabled') ? '' : "onclick='lockDisabledForm(this)'";
                    if ($disabled == 'disabled' && !$form_locked['status']) $disabled = '';
                    if ($wholeRecordIsLocked) $disabled = 'disabled';
                    // Output row
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight' style='padding:5px;'><input type='checkbox' id='__LOCKRECORD__' $onclick $disabled";
                    if ($form_locked['status']) print ' checked ';
                    print  "><label for='__LOCKRECORD__' style='color:#A86700;cursor:pointer;' class='font-weight-bold ml-2'><img src='".APP_PATH_IMAGES."lock.png' style='position:relative;top:-2px;'> {$lang['form_renderer_18']}</label>";
                    // Display username and timestamp to ALL users if locked
                    if ($form_locked['status'])
                    {
                        // Render link to unlock
                        if ($user_rights['lock_record'] > 0 && !$wholeRecordIsLocked) {
                            print  "<span id='unlockbtn' style='padding-left:20px;'><input type='button' onclick='unlockForm();' style='font-size:11px;' value='".js_escape($lang['data_entry_182'])."'></span>";
                        }
                        print  "<div id='lockingts'><b>{$lang['form_renderer_05']}</b> ";
                        if ($form_locked['username'] != "") {
                            print  "<b>{$lang['form_renderer_06']} {$form_locked['username']}</b> ({$form_locked['user_firstname']}
                                {$form_locked['user_lastname']}) ";
                        }
                        print  "{$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($form_locked['timestamp']);
                        print  "</div>";
                    }
                    // Display e-signature info, if any and/or if user has e-signature rights
                    print $esignature_text;
                    print  "</td>";
                    break;
                //Single Checkbox
                case 'checkbox_single':
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'><input $id aria-labelledby='$ariaLabelledBy' type='checkbox' name='$name' $disabled $tabindex $onchange $onblur $onfocus>$note</td>";
                    break;
                //Multiple Answer Checkbox
                case 'checkbox':
                    // @MAXCHOICE ACTION TAG
                    $maxChecked = (int)(($isFormOrSurveyPage && in_array('@MAXCHECKED', $rr_array['action_tags'])) ? Form::getValueInActionTag($Proj->metadata[$name]['misc'], "@MAXCHECKED") : 0);
                    if (!is_numeric($maxChecked) || $maxChecked < 0) $maxChecked = 0;
                    // Is Matrix field?
                    $matrix_col_width = null;
                    if (isset($rr_array['matrix_field'])) {
                        // Determine width of each column based upon number of choices
                        $matrix_col_width = DataEntry::getMatrixHdrWidths($rr_array, $value);
                        print  "<td class='labelmatrix $colClassCombined' colspan='2'>
                                    <table role='presentation' cellspacing='0' style='width:100%;'><tr>
                                        <td style='padding:2px 0;'>$label</td>";
                    // Right-aligned
                    } elseif ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    DataEntry::render_checkboxes($rr_array, (isset($value) ? $value : ''), $name, "$id $onchange $onclick $onblur $onfocus $disabled", $custom_alignment, $matrix_col_width, $tabindex, $disabled=='disabled', $enhanced_choices, $hasReadonlyActionTag, $hasRandomOrderActionTag, $hideChoices, $ariaLabelledBy, $maxChoices, $maxChecked);
                    if (isset($rr_array['matrix_field'])) {
                        print "</tr></table>";
                    } else {
                        print "<div class='space'></div>";
                    }

                    //check if checkbox value is a missing data code, and display label if so:
                    $MDValue=$value[0];
                    DataEntry::displayMissingDataLabel($name, $MDValue);

                    print "$note</td>";
                    break;
                //Hidden fields
                case 'hidden':
                    // If this is really a date[time][_seconds] field that is hidden, then make sure we reformat the date for display on the page
                    if (isset($Proj->metadata[$name]) && $Proj->metadata[$name]['element_type'] == 'text' && $name != $Proj->table_pk)
                    {
                        if (substr($Proj->metadata[$name]['element_validation_type'], -4) == '_mdy') {
                            list ($this_date, $this_time) = explode(" ", $value);
                            $value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
                        } elseif (substr($Proj->metadata[$name]['element_validation_type'], -4) == '_dmy') {
                            list ($this_date, $this_time) = explode(" ", $value);
                            $value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
                        }
                    }
                    print "\n<input type='hidden' name='$name' $id value='".str_replace("'","&#039;",$value)."'>";
                    break;
                //HTML "file" input fields, not REDCap "file" field types
                case 'file2':
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'><input type='file' name='$name' id='$name'></td>";
                    break;
                //Textarea
                case 'textarea':
                    // Clean the value for </textarea> tags used in XSS
                    $value = RCView::escape(isset($value) ? $value : '', false);

                    if(isset($missingDataCodes[$value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])){
                        $disabled='disabled';
                    }
                    // Output row
                    if (!isset($custom_alignment) || $custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    print  "	<textarea autocomplete='new-password' class='x-form-field notesbox' aria-labelledby='$ariaLabelledBy' id='$name' name='$name' $tabindex $disabled $onchange $onclick $onblur $onkeydown $onfocus $placeholderText>$value</textarea>
                                <div id='{$name}-expand' class='expandLinkParent'>
                                    <a href='javascript:;' tabindex='-1' class='expandLink' onclick=\"growTextarea('$name')\">{$lang['form_renderer_19']}</a>&nbsp;
    
                                </div>";
                    DataEntry::displayMissingDataLabel($name, $value);

                    print  "$note </td>";
                    break;
                //True-False
                case 'truefalse':
                    // Validate that the value is either 1 or 0 or blank. If none, then set to blank.

                    if (isset($value) && $value != '' && $value != '0' && $value != '1' && !isset($missingDataCodes[$value])) $value = '';
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    print  "<input name='$name' value='" . (isset($value) ? $value : '') . "' tabindex='-1' class='hiddenradio' aria-labelledby='label-$name'>";
                    DataEntry::render_radio($rr_array, (isset($value) ? $value : ''), $name, "$id $onchange $onclick $onblur $onfocus $disabled" . ($randFldClass==''?'':" class='$randFldClass'"), $custom_alignment, null, $disabled=='disabled', $enhanced_choices, $hasReadonlyActionTag, $hasRandomOrderActionTag, $hideChoices, $ariaLabelledBy, $maxChoices);
                    print "<div class='resetLinkParent'><a href='javascript:;' class='smalllink $randFldClass' tabindex='0' style='display:$reset_radio;'
                        onclick=\"return radioResetVal('$name','$formJsName');\">{$lang['form_renderer_20']}</a></div>";
                    DataEntry::displayMissingDataLabel($name, $value);
                    print "$note</td>";
                    break;
                //Yes-No
                case 'yesno':
                    // Validate that the value is either 1 or 0 or blank. If none, then set to blank.
                    if (isset($value) && $value != '' && $value != '0' && $value != '1' && !isset($missingDataCodes[$value])) $value = '';
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    print  "<input name='$name' value='" . (isset($value) ? $value : '') . "' tabindex='-1' class='hiddenradio' aria-labelledby='label-$name'>";
                    DataEntry::render_radio($rr_array, (isset($value) ? $value : ''), $name, "$id $onchange $onclick $onblur $onfocus $disabled" . ($randFldClass==''?'':" class='$randFldClass'"), $custom_alignment, null, $disabled=='disabled', $enhanced_choices, $hasReadonlyActionTag, $hasRandomOrderActionTag, $hideChoices, $ariaLabelledBy, $maxChoices);
                    print "<div class='resetLinkParent'><a href='javascript:;' class='smalllink $randFldClass' tabindex='0' style='display:$reset_radio;'
                        onclick=\"return radioResetVal('$name','$formJsName');\">{$lang['form_renderer_20']}</a></div>";
                    DataEntry::displayMissingDataLabel($name, $value);
                    print "$note</td>";
                    break;
                // Matrix group header
                case 'matrix_header':
                    // Determine width of each column based upon number of choices
                    $matrix_col_width = DataEntry::getMatrixHdrWidths($rr_array);
                    // First column (which is blank)
                    print  "<td class='labelmatrix $colClassCombined' colspan='2' style='padding:10px 0 0;vertical-align:bottom;'>";
                    print  DataEntry::matrixHeaderTable($rr_array, $matrix_col_width, null, $rr_array['grid_rank']);
                    print  "</td>";
                    break;
                //Radio
                case 'radio':
                    // Validate the format of the value. If has illegal characters, then set to blank. (Some Assessment Center API assessments might contain a dash.)
                    if (isset($value) && !is_numeric($value) && !preg_match("/^([a-zA-Z0-9._\-]+)$/", $value) && !(isset($isPromisInstrument) && $isPromisInstrument)) $value = '';
                    // Is Matrix field?
                    $matrix_col_width = null;
                    if (isset($rr_array['matrix_field'])) {
                        // Determine width of each column based upon number of choices
                        $matrix_col_width = DataEntry::getMatrixHdrWidths($rr_array, $value);
                        print  "<td class='labelmatrix $colClassCombined' colspan='2'>
                                    <table role='presentation' cellspacing=0 width=100%><tr>
                                        <td style='padding:2px 0;'>$label</td>";
                    // Right-aligned
                    } elseif ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    print  "<input name='$name' value='" . (isset($value) ? $value : '') . "' tabindex='-1' class='hiddenradio' aria-labelledby='label-$name'>";
                    DataEntry::render_radio($rr_array, (isset($value) ? $value : ''), $name, "$id $onchange $onclick $onblur $onfocus $disabled" . ($randFldClass==''?'':" class='$randFldClass'"), $custom_alignment, $matrix_col_width, $disabled=='disabled', $enhanced_choices, $hasReadonlyActionTag, $hasRandomOrderActionTag, $hideChoices, $ariaLabelledBy, $maxChoices);
                    if (isset($rr_array['matrix_field'])) {
                        print "</tr></table>";
                    }
                    print  "<div class='resetLinkParent'><a href='javascript:;' class='smalllink $randFldClass' tabindex='0' style='display:$reset_radio;'
                            onclick=\"return radioResetVal('$name','$formJsName');\">{$lang['form_renderer_20']}</a></div>";

                    DataEntry::displayMissingDataLabel($name, $value);
                    print "$note</td>";
                    break;
                //Drop-down
                case 'select':
                    // Validate the format of the value. If has illegal characters, then set to blank.
                    if (PAGE != 'install.php' && $Proj->metadata[$name]['element_type'] != 'sql' && isset($value) && !is_numeric($value)
                        && !preg_match("/^([a-zA-Z0-9._\-]+)$/", $value)) {
                        $value = '';
                    }
                    // If this field is REALLY an SQL field type, then do a string replace in the value to deal with commas and parsing
                    if ($Proj->metadata[$name]['element_type'] == 'sql') {
                        $value = str_replace(array('"',"'","&#39;",","), array('&quot;',"&#039;","&#039;","&#44;"), $value);
                        $rr_array['enum'] = str_replace(array('"',"'","&#39;"), array('&quot;',"&#039;","&#039;"), $rr_array['enum']);
                        // Fix any issues with commas in the enum labels
                        // $tempEnum = array();
                        // foreach (parseEnum($rr_array['enum']) as $key=>$val) {
                            // $tempEnum[] = "$key, ".str_replace(",", "&#44;", $val);
                        // }
                        // $rr_array['enum'] = implode(" \n ", $tempEnum);
                    }
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    // Enable auto-complete?
                    $enable_dd_auto_complete = (isset($rr_array['validation']) && $rr_array['validation'] == 'autocomplete');
                    $dd_auto_complete_class = "";
                    $dd_auto_complete_input = "";
                    if ($enable_dd_auto_complete) {
                        // Build HTML for input/button to replace select box
                        $dd_auto_complete_btn_onclick = (PAGE == "Design/online_designer.php" || PAGE == "Design/online_designer_render_fields.php")
                                                        ? "enableDropdownAutocomplete();return false;" : "return false;";
                        $dd_auto_complete_input = "<div class='nowrap' style='max-width:95%;'>"
                                                . "<input role='combobox' $tabindex $placeholderText type='text' $disabled class='x-form-text x-form-field rc-autocomplete' id='rc-ac-input_$name' aria-labelledby='$ariaLabelledBy'>"
                                                . "<button $disabled listopen='0' tabindex='-1' onclick='$dd_auto_complete_btn_onclick' class='ui-button ui-widget ui-state-default ui-corner-right rc-autocomplete' aria-label='".js_escape($lang['data_entry_444'])."'>"
                                                . "<img class='rc-autocomplete' src='".APP_PATH_IMAGES."arrow_state_grey_expanded.png' alt='".js_escape($lang['data_entry_444'])."'></button></div>";
                        // Set new values for the select box
                        $tabindex = "";
                        $dd_auto_complete_class = "rc-autocomplete";
                    }
                    // Required?
                    $aria_required = isset($rr_array['field_req']) ? " aria-required='true'" : "";

                    // add missing data options, unless this is a survey page, or this field is the page status dropdown:
                    if ($name==$_GET['page']."_complete" || $isSurveyPage || in_array('@NOMISSING', $rr_array['action_tags'])){
                        $ddlOptions=$rr_array['enum'];
                    }else{
                        $ddlOptions=$rr_array['enum']."\n".$missingDataEnum;
                    }
                    // Render the drop-down
                    list ($ddOptionHtml, $ddDisabled) = DataEntry::render_dropdown($ddlOptions,$value, "", $hasRandomOrderActionTag, $hideChoices, $maxChoices, ($_GET['page']."_complete" == $name), $enable_dd_auto_complete);

                    $opacityClass = "";
                    if ($ddDisabled) {
                        $disabled = "disabled";
                        $opacityClass = "opacity65";
                    }
                    print  "<span $class><select role='listbox'{$aria_required} aria-labelledby='$ariaLabelledBy' class='x-form-text x-form-field $dd_auto_complete_class $randFldClass $opacityClass' name='$name' $id $disabled $onchange $onclick $onblur $onfocus $tabindex>";

                    print  "$ddOptionHtml</select></span>$dd_auto_complete_input";
                    if ($Proj && !$Proj->isFormStatus($name)) DataEntry::displayMissingDataLabel($name, $value);
                    print "$note </td>";
                    break;
                //Text
                case 'text':
                    // Get field's data type
                    $fieldDataType = isset($valTypes[$rr_array['validation']]['data_type']) ? $valTypes[$rr_array['validation']]['data_type'] : '';
                    // Alignment class
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        $newclass = "";
                    } else {
                        $newclass = "rci-left";
                    }
                    if (isset($calcChangesClass) && $calcChangesClass != '') {
                        $newclass = trim("$newclass $calcChangesClass");
                    }

                    if(isset($missingDataCodes[$value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])){
                        $disabled = "disabled";
                        // JavaScript to hide datepicker and now/today button
                        ?><script type="text/javascript">$(function(){ $('#<?=$name?>-tr button:not(.rc-autocomplete), #<?=$name?>-tr .ui-datepicker-trigger').hide(); });</script><?php
                    }

                    // If needed, deal with Date and Time validated fields
                    $style = "";
                    $nowBtn = "";
                    $inputPattern = "";
                    $fv_attr = "";
                    $value = isset($value) ? $value : '';
                    if (isset($rr_array['validation']))
                    {
                        // Set field attribute
                        $fv_attr = "fv='{$rr_array['validation']}'";
                        // Dates
                        if ($rr_array['validation'] == 'date' || $rr_array['validation'] == 'date_ymd' || $rr_array['validation'] == 'date_mdy' || $rr_array['validation'] == 'date_dmy')
                        {
                            if (!$disable_all) {
                                $newclass = $rr_array['validation'];
                                if ($rr_array['validation'] == 'date') {
                                    $newclass = 'date_ymd';
                                }
                                $dformat = MetaData::getDateFormatDisplay($rr_array['validation']);
                                if ($display_today_now_button) $nowBtn = "<button ignore='Yes' class='jqbuttonsm ml-2 today-now-btn' onclick=\"setToday('$name','{$rr_array['validation']}');return false;\">{$lang['dashboard_32']}</button>";
                                $nowBtn .= "<span class='df'>$dformat</span>";
                            }

                            // Reformat MDY/DMY values (unless value is a missing data code)
                            if ($rr_array['validation'] == 'date_mdy' && !isset($missingDataCodes[$value])) {
                                $value = DateTimeRC::date_ymd2mdy($value);

                            } elseif ($rr_array['validation'] == 'date_dmy' && !isset($missingDataCodes[$value])) {
                                $value = DateTimeRC::date_ymd2dmy($value);
                            }
                            $onkeydown = "onkeydown=\"dateKeyDown(event,'$name')\"";
                        }
                        // Time
                        elseif ($rr_array['validation'] == 'time')
                        {
                            if (!$disable_all) {
                                $newclass = "time2";
                                if ($display_today_now_button) $nowBtn = "<button ignore='Yes' class='jqbuttonsm ml-2 today-now-btn' onclick=\"setNowTime('$name');return false;\">{$lang['form_renderer_29']}</button>";
                                $dformat = MetaData::getDateFormatDisplay($rr_array['validation']);
                                $nowBtn .= "<span class='df'>$dformat</span>";
                            }
                        }
                        // Datetimes
                        elseif ($rr_array['validation'] == 'datetime' || $rr_array['validation'] == 'datetime_ymd' || $rr_array['validation'] == 'datetime_mdy' || $rr_array['validation'] == 'datetime_dmy')
                        {
                            if (!$disable_all) {
                                $newclass = $rr_array['validation'];
                                if ($rr_array['validation'] == 'datetime') {
                                    $newclass = 'datetime_ymd';
                                }
                                $dformat = MetaData::getDateFormatDisplay($rr_array['validation']);
                                if ($display_today_now_button) $nowBtn = "<button ignore='Yes' class='jqbuttonsm ml-2 today-now-btn' onclick=\"setNowDateTime('$name',0,'{$rr_array['validation']}');return false;\">{$lang['form_renderer_29']}</button>";
                                $nowBtn .= "<span class='df'>$dformat</span>";
                            }
                            // Reformat MDY/DMY values

                            if ($rr_array['validation'] == 'datetime_mdy' && !isset($missingDataCodes[$value])) {
                                list ($this_date, $this_time) = explode(" ", $value);
                                $value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);

                            } elseif ($rr_array['validation'] == 'datetime_dmy' && !isset($missingDataCodes[$value])) {
                                list ($this_date, $this_time) = explode(" ", $value);
                                $value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
                            }
                            $onkeydown = "onkeydown=\"dateKeyDown(event,'$name')\"";
                        }
                        // Datetime_seconds
                        elseif ($rr_array['validation'] == 'datetime_seconds' || $rr_array['validation'] == 'datetime_seconds_ymd' || $rr_array['validation'] == 'datetime_seconds_mdy' || $rr_array['validation'] == 'datetime_seconds_dmy')
                        {
                            if (!$disable_all) {
                                $newclass = $rr_array['validation'];
                                if ($rr_array['validation'] == 'datetime_seconds') {
                                    $newclass = 'datetime_seconds_ymd';
                                }
                                $dformat = MetaData::getDateFormatDisplay($rr_array['validation']);
                                if ($display_today_now_button) $nowBtn = "<button ignore='Yes' class='jqbuttonsm ml-2 today-now-btn' onclick=\"setNowDateTime('$name',1,'{$rr_array['validation']}');return false;\">{$lang['form_renderer_29']}</button>";
                                $nowBtn .= "<span class='df'>$dformat</span>";
                            }
                            // Reformat MDY/DMY values

                            if ($rr_array['validation'] == 'datetime_seconds_mdy' && !isset($missingDataCodes[$value])) {
                                list ($this_date, $this_time) = explode(" ", $value);
                                $value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);

                            } elseif ($rr_array['validation'] == 'datetime_seconds_dmy' && !isset($missingDataCodes[$value])) {
                                list ($this_date, $this_time) = explode(" ", $value);
                                $value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
                            }
                            $onkeydown = "onkeydown=\"dateKeyDown(event,'$name')\"";
                        }
                        // INTEGER or PHONE field on MOBILE devices and TABLETS only (switch to number pad instead of regular keyboard)
                        elseif (($isTablet || $isMobileDevice)

                            && ($fieldDataType == 'integer'
                            // Phone won't work on Android because it strangely erases the field value after being entered
                            || ($isIOS && $rr_array['validation'] == 'phone')))
                        {
                            if ($isIOS) {
                                // iOS
                                $inputPattern = "pattern='\d*'";
                            } else {

                                // Android
                                $rr_type = "number";
                            }
                        }
                        // NUMBER field on MOBILE devices and TABLETS only (switch to number pad instead of regular keyboard)
                        elseif (($isTablet || $isMobileDevice)
                            && $fieldDataType == 'number')
                        {
                            if ($isIOS) {
                                // iOS
                                if ($fieldDataType == 'number_comma_decimal') {
                                    // Number with comma decimal: The only option is to display the default querty keyboard
                                } else {
                                    // Number with dot decimal
                                    $rr_type = "number";
                                    $inputPattern = "inputmode='decimal'";
                                }
                            } else {
                                // Android
                                $rr_type = "number";
                    }
                        }
                    }
                    // ACTION TAG Customizations
                    if (in_array("@PASSWORDMASK", $rr_array['action_tags'])) {
                        $rr_type = "password";
                        $style = "style='max-width:70%;'";
                        $nowBtn .= "&nbsp;&nbsp;<a ignore='Yes' class='smalllink' style='color:#800000;font-family:Tahoma;' href='javascript:;' onclick=\"simpleDialog('".js_escape($isSurveyPage ? $lang['data_entry_262'] : $lang['data_entry_263'])."','".js_escape($lang['form_renderer_22'])."');\">{$lang['form_renderer_22']}</a>";
                    } elseif (in_array("@LATITUDE", $rr_array['action_tags'])) {
                        $style = "style='max-width:130px;'";
                        $nowBtn = "&nbsp;&nbsp;<button ignore='Yes' class='jqbuttonsm' onclick=\"getGeolocation('latitude','$name','form',true);dataEntryFormValuesChanged=true;return false;\">{$lang['global_125']}</button>";
                        $nowBtn .= "&nbsp;&nbsp;<a ignore='Yes' class='smalllink' href='javascript:;' onclick=\"$('[name=$name]').val('');dataEntryFormValuesChanged=true;try{calculate('$name');doBranching('$name');}catch(e){} return false;\">{$lang['form_renderer_20']}</a>";
                    } elseif (in_array("@LONGITUDE", $rr_array['action_tags'])) {
                        $style = "style='max-width:130px;'";
                        $nowBtn = "&nbsp;&nbsp;<button ignore='Yes' class='jqbuttonsm' onclick=\"getGeolocation('longitude','$name','form',true);dataEntryFormValuesChanged=true;return false;\">{$lang['global_125']}</button>";
                        $nowBtn .= "&nbsp;&nbsp;<a ignore='Yes' class='smalllink' href='javascript:;' onclick=\"$('[name=$name]').val('');dataEntryFormValuesChanged=true;try{calculate('$name');doBranching('$name');}catch(e){} return false;\">{$lang['form_renderer_20']}</a>";
                    }
                    if (in_array('@HIDEBUTTON', $rr_array['action_tags'])) {
                        $nowBtn = "<span class='df'>$dformat</span>";
                    }
                    // Add extra note for longitudinal projects employing the secondary identifier field
                    $extra_note = (isset($secondary_pk_note) && $name == $secondary_pk) ? $secondary_pk_note : "";
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    // Ontology search
                    $autosuggest = "";
                    if (isset($rr_array['element_enum']) && $rr_array['element_enum'] != '' && strpos($rr_array['element_enum'], ":") !== false) {
                        // Get the name of the name of the web service API and the category (ontology) name
                        list ($autosuggest_service, $autosuggest_cat) = explode(":", $rr_array['element_enum'], 2);
                        // Set field as read-only
                        $disabled = "readonly";
                        //disable field if contains missing data code
                        $inputDisabled="";
                        if(isset($missingDataCodes[$value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])) {
                            $inputDisabled="disabled";
                        }
                        // Set class
                        $newclass = "autosug-ont-field";
                        $style = "";
                        // Set elements for ontology search
                        print  "<input id='$name-autosuggest-span' $inputDisabled class='x-form-text x-form-field autosug-span' type='text' readonly value='".str_replace("'","&#039;",Form::getWebServiceCacheValues(PROJECT_ID, $autosuggest_service, $autosuggest_cat, $value))."'>
                                <input id='$name-autosuggest' role='combobox' class='x-form-text x-form-field autosug-search' type='text'>
                                <span class='nowrap'>
                                    <span id='$name-autosuggest-instr' class='autosug-instr'>{$lang['data_entry_260']}</span>
                                    <img id='$name-autosuggest-progress' class='autosug-progress' alt='".js_escape($lang['scheduling_20'])."' src='".APP_PATH_IMAGES."progress_circle.gif'>
                                </span>";
                    }
                    // Required?
                    $aria_required = isset($rr_array['field_req']) ? " aria-required='true'" : "";
                    // Text input
                    print  "	<input autocomplete='new-password'{$aria_required} aria-labelledby='$ariaLabelledBy' class='x-form-text x-form-field $newclass' $id type='$rr_type' name='$name' $inputDisabled value='".str_replace("'","&#039;",$value)."'
                                $disabled $id $style $onchange $onclick $onblur $onfocus $tabindex $onkeydown $onkeyup $fv_attr $inputPattern $placeholderText>
                                $nowBtn $extra_note $note";
                                DataEntry::displayMissingDataLabel($name, $value);
                    print  "</td>";
                    break;
                //Calculated Field
                case 'calc':
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    print  "	<input type='text' aria-labelledby='$ariaLabelledBy' name='$name' value='" . (isset($value) ? $value : '') . "' sv='" . (isset($value) ? $value : '') . "' $id $onfocus tabindex='-1' readonly='readonly'
                                class='x-form-text x-form-field rci-calc'>&nbsp;&nbsp;<a href='javascript:;' ignore='Yes' class='viewEq' tabindex='-1' onclick=\"viewEq('$name');\">{$lang['form_renderer_21']}</a>
                                $note
                            </td>";
                    break;
                //Slider / Visual Analog Scale
                case 'slider':
                    $missing = false;
                    $display = "";
                    $sldrparent_display = "";
                    if (isset($missingDataCodes[$value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])) {
                        $missing = true;
                        $display = "style='display:none;'";
                        $sldrparent_display = "hidden";
                    }
                    // Show or hide slider display value? (if 'number', then show it)
                    $sliderValDispVis = ($rr_array['slider_labels'][3] == "number") ? "visible" : "hidden";
                    // Alter slider text for mobile devices and iPads
                    $sliderDispText = ($isMobileDevice || $isIpad) ? $lang['design_721'] : $lang['design_722'];
                    // For mobile devices, only show sliders as left-aligned
                    if ($isMobileDevice) $custom_alignment = str_replace('R', 'L', $custom_alignment);
                    // Validate that the slider's value (if existing) is numeric. If not, set to blank.
                    if (isset($value) && !is_numeric($value) && $missing === false) $value = '';
                    // Render slider row
                    switch ($custom_alignment) {
                        case '':
                        case 'RV':
                            $alignment_class = 'right-vertical';
                            $sliderNoPadStyle = "sldrnopad";
                            break;
                        case 'RH':
                            $alignment_class = 'right-horizontal';
                            $sliderNoPadStyle = "";
                            break;
                        case 'LH':
                            $alignment_class = 'left-horizontal';
                            $sliderNoPadStyle = "";
                            break;
                        case 'LV':
                            $alignment_class = 'left-vertical';
                            $sliderNoPadStyle = "sldrnopad";
                            break;
                    }
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label<div class='space'></div>";
                    }
                    $sliderStyle = ($custom_alignment == 'LV') ? "sldrmsgl" : "sldrmsg";
                    print  "	<table role='presentation' class='sldrparent $sldrparent_display' style='position:relative;'>
                                    <tr>
                                        <td colspan='3' style='padding:3px 3px 0 5px;'>
                                            <table role='presentation' class='sliderlabels $alignment_class'>	
                                                <tr style='font-size:11px;font-weight:normal;'> 
                                                    <td id='sldrlaba-$name' aria-hidden='true' class='sldrlaba ".($rr_array['slider_labels'][0] == "" ? "sliderlabels-e" : "sliderlabels-ne")."'>{$rr_array['slider_labels'][0]}</td>
                                                    <td id='sldrlabb-$name' aria-hidden='true' class='sldrlabb $sliderNoPadStyle ".($rr_array['slider_labels'][1] == "" ? "sliderlabels-e" : "sliderlabels-ne")."'>{$rr_array['slider_labels'][1]}</td>
                                                    <td id='sldrlabc-$name' aria-hidden='true' class='sldrlabc ".($rr_array['slider_labels'][2] == "" ? "sliderlabels-e" : "sliderlabels-ne")."'>{$rr_array['slider_labels'][2]}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='sldrtd' ".($form_locked['status'] ? "" : "onmousedown=\"enableSldr('$name',event.type);$('#slider-$name').attr('modified','1');\"").">
                                            <div id='slider-$name' class='slider' $display data-align='$alignment_class' " . ((isset($value) && $value != '') ? "modified='1'" : '') . " ".($form_locked['status'] ? "locked='1'" : "")."></div>
                                        </td>								
                                        <td valign='bottom' class='sldrnumtd $alignment_class'>
                                            <input type='text' name='$name' value='" . (isset($value) ? $value : '') . "' $id $onfocus tabindex='-1' readonly='readonly'
                                                style='visibility: $sliderValDispVis;' class='sldrnum' aria-labelledby='label-$name'>
                                        </td>
                                    </tr>
                                    <tr>					
                                        <td id='sldrmsg-$name' class='$sliderStyle' colspan='3' $display>$sliderDispText</td>
                                    </tr>
                                </table>";
                    //Set to already posted values
                    if (isset($value) && $value != "" && is_numeric($value)) {
                        print "<script type='text/javascript'>\$(function(){setSlider('$name','$value');});</script>";
                    }
                    print  "	<div style='text-align:right;'><a href='javascript:;' class='smalllink' tabindex='0' style='display:$reset_radio;'
                                onclick=\"resetSlider('$name');return false;\">{$lang['form_renderer_20']}</a></div>
                                " . DataEntry::displayMissingDataLabel($name, $value) . "
                                $note
                            </td>";
                    break;
                //Buttons
                case 'button':
                case 'submit':
                    $btnclass = (isset($rr_array['btnclass'])) ? "class='{$rr_array['btnclass']}'" : "";
                    print  "<td class='labelrc $colClassLeft'>$label</td>
                            <td class='data $colClassRight'><span $class><input type='$rr_type' name='$name'
                                value='$value' $id $disabled $onchange $onclick $onblur $onfocus $tabindex $btnclass></span></td>";
                    break;
                //E-doc file uploading
                case 'file':
                    // Render row
                    if ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'RH') {
                        print  "<td class='labelrc $colClassLeft'>$label</td><td class='data $colClassRight'>";
                        $file_align = "right";
                    } else {
                        print  "<td class='labelrc $colClassCombined' colspan='2'>$label";
                        $file_align = "left";
                    }
                    if ($edoc_field_option_enabled) {
                        // Missing data codes
                        $missing = false;
                        $missingClass = "";
                        if (isset($missingDataCodes[$value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])) {
                            $missing = true;
                            $missingClass = "hidden";
                        }
                        // Boolean if field is a 'signature' file upload type
                        $signature_field = (isset($rr_array['validation']) && $rr_array['validation'] == 'signature') ? '1' : '0';
                        // Hidden input containing real value
                        print "<input type='hidden' name='$name' value='" . (isset($value) && $value != '' ? ($missing ? $value : (int)$value) : '') . "'>";
                        //If edoc upload capability is turned on
                        if (!isset($value) || $value == '' || $missing) {
                            //If no document has been uploaded, give link to upload new document
                            $this_file_link = '';
                            $this_file_link_display = 'none';
                            if ((!isset($user_rights) || (isset($user_rights) && ($user_rights['forms'][$_GET['page']] == '1' || $user_rights['forms'][$_GET['page']] == '3'))) && !$disable_all) {
                                $this_file_link_value = ($signature_field) ? $lang['form_renderer_31'] : $lang['form_renderer_23'];
                                $this_file_link_img = ($signature_field) ? '<i class="fas fa-signature mr-1"></i>' : '<i class="fas fa-upload mr-1 fs12"></i>';
                            } else {
                                $this_file_link_value = '';
                                $this_file_link_img = '';
                            }
                            $this_file_link_new = '<a href="javascript:;" '.$tabindex.'
                                onclick="filePopUp(\''.$name.'\','.$signature_field.');return false;" aria-label="'.js_escape2($lang['survey_1146']).'" class="fileuploadlink">'.$this_file_link_img.$this_file_link_value.'</a>';
                            $q_fileup = array();
                        }
                        else{
                            if (!$missing) $value = (int)$value;
                            //If document has been uploaded, give link to download and link to delete
                            $this_file_link = $file_download_page.'&doc_id_hash='.Files::docIdHash($value).'&id='.$value.'&s='.(isset($_GET['s']) ? $_GET['s'] : '')."&page={$_GET['page']}&record={$_GET['id']}".(($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "")."&event_id={$_GET['event_id']}&field_name=$name&instance={$_GET['instance']}";
                            $this_file_link_display = 'block';
                            $senditText = '';
                            $this_file_link_img_replace = '';
                            if ((!isset($user_rights) || (isset($user_rights) && ($user_rights['forms'][$_GET['page']] == '1' || $user_rights['forms'][$_GET['page']] == '3'))) && !$disable_all
                                && !$hasReadonlyActionTag)
                            {
                                $this_file_link_value = $lang['form_renderer_24'];
                                $this_file_link_img = '<i class="far fa-trash-alt mr-1"></i>';
                                // Send-It
                                if ($sendit_enabled == 1 || $sendit_enabled == 3) {
                                    $senditText = "<span class=\"sendit-lnk\"><span style=\"font-size:10px;padding:0 10px;\">{$lang['global_47']}</span><a onclick=\"popupSendIt($value,3);return false;\" href=\"javascript:;\"
                                        style=\"font-size:10px;\"><i class=\"far fa-envelope mr-1\"></i>{$lang['form_renderer_25']}</a></span>&nbsp;</span>";
                                }
                                // File Version History
                                if (!$signature_field && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID)) {
                                    $this_file_link_img_replace = '<a href="javascript:;" style="font-size:10px !important;color:green;" class="fileuploadlink"
                                                                    onclick="filePopUp(\''.$name.'\','.$signature_field.',1);return false;" aria-label="'.js_escape2($lang['survey_1146']).'"><i class="fas fa-upload mr-1"></i>'.$lang['data_entry_459'].'</a>'
                                                                . "<span style=\"font-size:10px;padding:0 10px;\">{$lang['global_47']}</span>";
                                }
                            } else {
                                $this_file_link_value = '';
                                $this_file_link_img = '';
                            }
                            $this_file_link_new = $this_file_link_img_replace.'<span class="edoc-link"><a href="javascript:;" class="deletedoc-lnk" style="font-size:10px;color:#C00000;"
                                onclick=\'deleteDocumentConfirm('.$value.',"'.$name.'","'.$_GET['id'].'",'.$_GET['event_id'].','.$_GET['instance'].',"'.$file_delete_page.'&__response_hash__="+$("#form :input[name=__response_hash__]").val());return false;\'>'.$this_file_link_img.$this_file_link_value.'</a>'
                                .$senditText.'</span>';
                            // Query edocs table for file attributes
                            $q_fileup_query = db_query("select doc_name, doc_size from redcap_edocs_metadata where doc_id = $value limit 1");
                            $q_fileup = db_fetch_array($q_fileup_query);
                            $q_fileup['doc_size'] = round_up($q_fileup['doc_size'] / 1024 / 1024);
                            $q_fileup['doc_name'] = Files::truncateFileName($q_fileup['doc_name'], 34);
                        }

                        // If a signature field that has a signature saved, display as inline image
                        $link_margin = "margin:0 10px;";
                        if ($signature_field) {
                            if (isset($value) && $value != '' && !$missing) {
                                $signature_img_src = $image_view_page.'&doc_id_hash='.Files::docIdHash($value).'&id='.$value.'&s='.(isset($_GET['s']) ? $_GET['s'] : '')."&page={$_GET['page']}&record={$_GET['id']}".(($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "")."&event_id={$_GET['event_id']}&field_name=$name&instance={$_GET['instance']}&signature=1";
                                $signature_img = "<img src='$signature_img_src' alt='".js_escape($lang['survey_1147'])."'>";
                                $signature_img_display = "block";
                            } else {
                                $signature_img = "";
                                $signature_img_display = "none";
                            }
                            $link_margin = "margin:0 20px;";
                            print "<div id='$name-sigimg' class='sig-img' style='text-align:$file_align;display:$signature_img_display;'>$signature_img</div>";
                        }
                        // Display "upload document" link OR download file link
                        print '<div id="fileupload-container-'.$name.'" class="fileupload-container '.$missingClass.'"><a target="_blank" class="filedownloadlink" name="'.$name.'" '.$tabindex.' href=\''.$this_file_link.'\' onclick="return appendRespHash(\''.$name.'\');" id="'.$name.'-link"
                               style="text-align:'.$file_align.';font-weight:normal;display:'.$this_file_link_display.';text-decoration:underline;'.$link_margin.'position:relative;">' . (isset($q_fileup['doc_name']) ? $q_fileup['doc_name'] : '') . ' (' . (isset($q_fileup['doc_size']) ? $q_fileup['doc_size'] : '') . ' MB)</a>
                               <div style="font-weight:normal;margin:10px 5px 0 0;position:relative;text-align:'.$file_align.';" id="'.$name.'-linknew">'.$this_file_link_new.'</div></div>';
                    } else {
                        //File upload capabilities are turned off
                        print '<span style="color:#808080;">'.$lang['form_renderer_26'].'</span>';
                    }
                    DataEntry::displayMissingDataLabel($name, $value);
                    print "<div class='space'></div>$note</td>";
                    break;
            }
            print $bookend2;
            print $end_row;
        }
        print $bookend3;
        // Print copyright info for instrument, if available
        if (((PAGE == "DataEntry/index.php") && isset($_GET['id'])) || PAGE == "Design/online_designer.php"
            || ($isSurveyPage && isset($_GET['__page__']) && $_GET['__page__'] == '1'))
        {
            $ack = SharedLibrary::getAcknowledgement($project_id, $_GET['page']);
            if ($ack != "") {
                print "<tr $trclass NoDrag='1' NoDrop='1'><td class='header' style='font-size:12px;font-weight:normal;border:1px solid #CCCCCC;' colspan='".($isSurveyPage ? '3' : '2')."'>".nl2br($ack)."</td></tr>";
            }
        }
        print "</tbody></table>";
        print "</div>";
        if (!(PAGE == 'Design/online_designer_render_fields.php' && isset($_GET['edit_question'])))
        {
            // Append any JS to append repeating instance fields to the form
            print DataEntry::addHiddenFieldsRepeatingInstances();
            // End of form
            print "</form>";
        }

        //If missing data codes are set for this project, create Missing Data context menu
        if (count($missingDataCodes) > 0)
        {
            echo "<div id='MDMenu' controlField=''>" . $lang['missing_data_01'];
            echo "<div class='set_btn' name='MDSetButton' code=''>" . $lang['missing_data_02'] . "</div>";
            foreach($missingDataCodes as $code=>$label){
                $label = strip_tags($label);
                echo "<div class='set_btn' name='MDSetButton' code='$code' label='".js_escape($label)."'>$label ($code)</div>";
            }
            echo "</div>";
        }

        // If data entry form is disabled for a record (based on user rights or other reasons)
        if ($disable_all && (PAGE == "DataEntry/index.php") && isset($_GET['id']))
        {
            ?>
            <script type='text/javascript'>
            $(function(){
                // Disable all Sliders on the page
                $('.slider').each(function(){
                    $(this).prop('onmousedown','');
                    try { $(this).slider('disable'); }catch(e){ }
                });
                // Hide button box at top right of page
                $('#formSaveTip').hide();
            });
            </script>
            <?php
        }
    }

    //Function to render drop-down fields
    public static function render_dropdown($select_choices, $element_value="", $blankDDlabel="", $hasRandomOrderActionTag=false, $hideChoices=array(),
                             $maxChoices=array(), $isFormStatus=false, $enable_dd_auto_complete=false)
    {
        global $lang, $missingDataCodes;
        // Drop-down option html
        $hasMissingDataCodes = (!empty($missingDataCodes) && !$isFormStatus);
        $addedMissingDataCodeOptLabel = false;
        $optionHtml = "";
        $ddDisabled = false;
        $select_choices = trim($select_choices);
        $element_value = $element_value."";
        $element_value2 = html_entity_decode(html_entity_decode($element_value, ENT_QUOTES), ENT_QUOTES)."";
        if ($select_choices != "")
        {
            // Randomize order?
            $select_choices_array = parseEnum($select_choices);
            if ($hasRandomOrderActionTag) shuffle_assoc($select_choices_array);
            // Loop through choices
            foreach ($select_choices_array as $this_value=>$this_text)
            {
                if ($this_text != '<') $this_text = strip_tags2(label_decode($this_text, false));
                $this_value = $this_value."";
                $this_value2 = html_entity_decode(html_entity_decode($this_value, ENT_QUOTES), ENT_QUOTES)."";
                // Is choice selected?
                $currentChoiceSelected = ($this_value === $element_value || $this_value2 === $element_value2);
                // Is choice hidden? Hide unless its value is selected.
                $hideChoice = (in_array($this_value, $hideChoices, true) && !$currentChoiceSelected);
                if ($hideChoice) continue;
                // Has max choice been reached?
                $maxChoiceReached = (in_array($this_value, $maxChoices) && !$currentChoiceSelected);
                if ($maxChoiceReached) continue;
                // Missing data code
                if (!$addedMissingDataCodeOptLabel && $hasMissingDataCodes && isset($missingDataCodes[$this_value])) {
                    $addedMissingDataCodeOptLabel = true;
                    if ($enable_dd_auto_complete) {
                        $optionHtml .= "<option value='---'>---</option>";
                    } else {
                        $optionHtml .= "<optgroup label='" . js_escape($lang['missing_data_04'] . $lang['colon']) . "'>";
                    }
                }
                // Render option
                $optionHtml .= "<option value='$this_value' ";
                if ($currentChoiceSelected) $optionHtml .= "selected";
                $optionHtml .= ">$this_text</option>";
            }
        }
        if ($addedMissingDataCodeOptLabel && !$enable_dd_auto_complete) {
            $optionHtml .= "</optgroup>";
        }
        // If DROPDOWN_DISABLE_BLANK constant is not defined, then given drop-downs a blank value as first option
        if (!defined('DROPDOWN_DISABLE_BLANK') && !$isFormStatus) {
            // If all choices are hidden by @MAXCHOICE, display a label for the blank option for explanatory purposes
            if ($maxChoiceReached && $optionHtml == "") {
                $blankDDlabel = $lang['data_entry_418'];
                $ddDisabled = true;
            }
            // Output the blank default option
            $optionHtml = "<option value=''>$blankDDlabel</option></span>$optionHtml";
        }

        // disable dropdown if missing value selected
        if (isset($missingDataCodes[$element_value]) && !$isFormStatus) {
            $ddDisabled = true;
        }
        // Output the options
        return array($optionHtml, $ddDisabled);
    }

    //Function to render radio fields
    public static function render_radio($rr_array,$element_value,$name,$attr,$custom_alignment='',$matrix_col_width=null,
                          $disabled=false,$enhanced_choices=false,$readonly=false,$hasRandomOrderActionTag=false,
                          $hideChoices=array(), $ariaLabelledBy="", $maxChoices=array())
    {
        // Set parameters
        global $missingDataCodes;
        //missing data stuff
        $missing=isset($missingDataCodes[$element_value]);

        $isMatrixField = is_numeric($matrix_col_width);
        $vertical_align = ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'LV');
        $col_size_class = $vertical_align ? 'col-md-12' : 'col-md-6';
        $select_choices = trim($rr_array['enum']);

        //disable onclick method if field disabled or marked as missing
        $onclick = $disabled || $missing ? '' : 'sr(this,event)';
        $enhanced_choices_html = '';
        $standard_choices_display = ($enhanced_choices) ? ' hidden' : '';
        $inputTabIndex = ($enhanced_choices) ? '-1' : '0';
        if ($select_choices != "")
        {
            // Add HTML for enhanced choices
            if ($enhanced_choices) $enhanced_choices_html .= "<div class='enhancedchoice_wrapper'>";
            // Randomize order?
            $select_choices_array = parseEnum($select_choices);
            if ($hasRandomOrderActionTag && !$isMatrixField) shuffle_assoc($select_choices_array);
            // Loop through each choice
            foreach ($select_choices_array as $this_value=>$this_text)
            {
                $this_value = $this_value."";
                if ($this_text != '<') $this_text = strip_tags2(label_decode($this_text, false));
                // Is choice selected?
                $currentChoiceSelected = ($this_value === $element_value."");
                // Is choice hidden? Hide unless its value is selected.
                $currentChoiceHidden = (in_array($this_value, $hideChoices, true) && !$currentChoiceSelected);
                $hideChoiceClass = ($currentChoiceHidden & !$isMatrixField) ? ' hidden' : '';
                // Has max choice been reached?
                $maxChoiceReached = (in_array($this_value, $maxChoices) && !$currentChoiceSelected);
                $thisChoiceOnclick = $maxChoiceReached || $missing ? "" : $onclick;
                $thisChoiceDisabled = $maxChoiceReached || $missing ? "disabled" : "";
                $thisChoiceDisabledLabel = $maxChoiceReached || $missing ? " opacity35" : "";
                $thisChoiceName = $maxChoiceReached ? "" : "name='".$name."___radio'";
                // Begin output for this choice
                if ($isMatrixField) {
                    print "<td class='data choicematrix{$hideChoiceClass}' style='width:{$matrix_col_width}%;'>";
                } elseif ($vertical_align) {
                    print "<div class='choicevert{$standard_choices_display}{$hideChoiceClass}{$thisChoiceDisabledLabel}' onclick='$thisChoiceOnclick'>";
                } else {
                    print "<span class='choicehoriz{$standard_choices_display}{$hideChoiceClass}{$thisChoiceDisabledLabel}' onclick='$thisChoiceOnclick'>";
                }
                if ($isMatrixField) {
                    $mtx_id = "mtxopt-".$name."_".$this_value;
                    if (!$currentChoiceHidden) {
                        print "<input type='radio' id='$mtx_id' tabindex='0' $thisChoiceName  $thisChoiceDisabled aria-labelledby='$ariaLabelledBy matrixheader-{$rr_array['grid_name']}-$this_value' $attr value='$this_value' label='".js_escape(strip_tags(label_decode($this_text)))."' ";
                        if ($currentChoiceSelected) print "checked";
                        print ">";
                    }
                } else {
                    if (!$currentChoiceHidden) {
                        print "<input type='radio' tabindex='$inputTabIndex' $thisChoiceName  $thisChoiceDisabled aria-labelledby='$ariaLabelledBy label-$name-$this_value' $attr value='$this_value' ";
                        if ($currentChoiceSelected) print "checked";
                        print ">";
                    }
                    // Enhanced choices
                    if ($enhanced_choices) {
                        if ($this_text == "") $this_text = "&nbsp;";
                        $enhanced_choices_class = $currentChoiceSelected ? 'selectedradio' : '';
                        $enhanced_choices_class2 = ($readonly || $maxChoiceReached) ? '' : 'hover';
                        $enhanced_choices_onclick = ($readonly || $maxChoiceReached) ? "" : "onclick='enhanceChoiceSelect(this,null)'";
                        $enhanced_choices_html .= "<div class='enhancedchoice{$hideChoiceClass} col-12 $col_size_class $thisChoiceDisabledLabel'>"
                            . "<label tabindex='0' onkeydown='if(event.keyCode==32){enhanceChoiceSelect(this,null);}' $enhanced_choices_onclick comps='$name,value,$this_value' class='$enhanced_choices_class2 $enhanced_choices_class'><span class='ec'>$this_text</span></label></div>";
                    }
                }
                if (!$isMatrixField) print " <label id='label-$name-$this_value' class='mc'>$this_text</label>";

                // Finalize output for this choice
                if ($isMatrixField) {
                    print "</td>";
                } elseif ($vertical_align) {
                    print "</div>";
                } else {
                    print "</span>";
                }
            }
            // Output the enhanced choices HTML
            if ($enhanced_choices) print $enhanced_choices_html . "</div>";
        }
    }

    //Function to render checkbox fields
    public static function render_checkboxes($rr_array,$element_value,$name,$attr,$custom_alignment='',$matrix_col_width=null,$tabindex,
                               $disabled=false,$enhanced_choices=false,$readonly=false,$hasRandomOrderActionTag=false,
                               $hideChoices=array(), $ariaLabelledBy="", $maxChoices=array(), $maxChecked=0)
    {
        global $missingDataCodes, $Proj;
        //check if checkbox marked as missing
        $dataMissing=false;
        $disabled="";
        $MDValue=$element_value[0];
        if (isset($missingDataCodes[$MDValue]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])) {
            $dataMissing=true;
            $disabled="disabled";
        }
        // Set parameters
        $isMatrixField = is_numeric($matrix_col_width);
        $vertical_align = ($custom_alignment == '' || $custom_alignment == 'RV' || $custom_alignment == 'LV');
        $col_size_class = $vertical_align ? 'col-md-12' : 'col-md-6';
        $select_choices = trim($rr_array['enum']);
        $onclick = $disabled ? '' : 'sr(this,event)';
        $enhanced_choices_html = '';
        $standard_choices_display = ($enhanced_choices) ? ' hidden' : '';
        $inputTabIndex = ($enhanced_choices) ? '-1' : '0';
        if ($select_choices != "")
        {
            // Add HTML for enhanced choices
            if ($enhanced_choices) $enhanced_choices_html .= "<div class='enhancedchoice_wrapper'>";
            // Randomize order?
            $select_choices_array = parseEnum($select_choices);
            if ($hasRandomOrderActionTag && !$isMatrixField) shuffle_assoc($select_choices_array);
            // Loop through each choice
            foreach ($select_choices_array as $this_value=>$this_text)
            {
                $this_value = $this_value."";
                if ($this_text != '<') $this_text = strip_tags2(label_decode($this_text, false));
                // Is choice selected?
                $currentChoiceSelected = (is_array($element_value) && in_array($this_value, $element_value, true));
                // Is choice hidden? Hide unless its value is selected.
                $currentChoiceHidden = (in_array($this_value, $hideChoices, true) && !$currentChoiceSelected);
                if ($isMatrixField) {
                    $hideChoiceClass = $currentChoiceHidden ? ' invis' : '';
                } else {
                    $hideChoiceClass = $currentChoiceHidden ? ' hidden' : '';
                }
                // Has max choice been reached?
                $maxChoiceReached = (in_array($this_value, $maxChoices) && !$currentChoiceSelected);
                $thisChoiceOnclick = $maxChoiceReached ? "" : $onclick;

                $thisChoiceDisabled = $maxChoiceReached || $dataMissing? "disabled" : "";
                $thisChoiceDisabledLabel = $maxChoiceReached || $dataMissing ? " opacity35" : "";
                $thisChoiceName = $maxChoiceReached ? "" : "name='__chkn__{$name}'";
                $thisCheckboxOnclick = "checkboxClick('$name','$this_value',this,event,$maxChecked);";
                // Begin output for this choice
                if ($isMatrixField) {
                    print "<td class='data choicematrix{$hideChoiceClass}' style='width:{$matrix_col_width}%;'>";
                } elseif ($vertical_align) {
                    print "<div class='choicevert{$standard_choices_display}{$hideChoiceClass}{$thisChoiceDisabledLabel}' onclick='$thisChoiceOnclick'>";
                } else {
                    print "<span class='choicehoriz{$standard_choices_display}{$hideChoiceClass}{$thisChoiceDisabledLabel}' onclick='$thisChoiceOnclick'>";
                }
                // Note: IE 6-9 does not trigger onchange when clicking checkboxes, so adding calculate();doBranching(); here for onclick for IE only.
                // Set aria label for choice
                if ($isMatrixField) {
                    $ariaLabelledbyChoice = "matrixheader-{$rr_array['grid_name']}-$this_value";
                } else {
                    $ariaLabelledbyChoice = "label-$name-$this_value";
                }
                print "<input type='checkbox' $thisChoiceDisabled aria-labelledby='$ariaLabelledBy $ariaLabelledbyChoice' tabindex='$inputTabIndex' $attr "
                    . "id='id-__chk__{$name}_RC_".DataEntry::replaceDotInCheckboxCoding($this_value)."' $thisChoiceName code='{$this_value}' onclick=\"checkboxClick('$name','$this_value',this,event,$maxChecked);\" $disabled";
                if ($currentChoiceSelected) {
                    print 'checked>';
                    $default_value = $this_value;
                } else {
                    print '>';
                    $default_value = ''; //Default value is 'null' if no present value exists
                }
                print '<input type="hidden" value="'.$default_value.'" name="__chk__'.$name.'_RC_'.DataEntry::replaceDotInCheckboxCoding($this_value).'">';
                if (!$isMatrixField) {

                    print " <label id='label-$name-$this_value' class='mc' onclick=\"$thisCheckboxOnclick\">$this_text</label>";
                    // Enhanced choices
                    if ($enhanced_choices) {
                        if ($this_text == "") $this_text = "&nbsp;";
                        $enhanced_choices_class = $currentChoiceSelected ? 'selectedchkbox' : 'unselectedchkbox';
                        $enhanced_choices_class2 = ($readonly || $maxChoiceReached) ? '' : 'hover';
                        $enhanced_choices_onclick = ($readonly || $maxChoiceReached) ? "" : "onclick='enhanceChoiceSelect(this,$maxChecked)'";
                        $enhanced_choices_html .= "<div class='enhancedchoice{$hideChoiceClass} col-12 $col_size_class $thisChoiceDisabledLabel'>"
                            . "<label tabindex='0' onkeydown='if(event.keyCode==32){enhanceChoiceSelect(this,$maxChecked);}' $enhanced_choices_onclick comps='$name,code,$this_value' class='$enhanced_choices_class2 $enhanced_choices_class'><span class='ec'>$this_text</span></label></div>";
                    }
                }
                // Finalize output for this choice

                if ($isMatrixField) {
                    print "</td>";
                } elseif ($vertical_align) {
                    print "</div>";
                } else {
                    print "</span>";
                }
            }

            foreach($missingDataCodes as $code=>$label){
                $value="";
                if (in_array($code, $element_value)){
                    $value=$code;
                }
                print '<input type="hidden" name="__chk__'.$name.'_RC_'.DataEntry::replaceDotInCheckboxCoding($code).'" value=' .$value.'>';
            }

            // Output the enhanced choices HTML
            if ($enhanced_choices) print $enhanced_choices_html . "</div>";
        }
    }


    // Capture any hidden required fields that have no value but were hidden via branching logic.
    // Return the hidden required fields in an array, and also auto-add the fields to POST.
    public static function addEmptyRequiredFieldsToPost()
    {
        global $Proj;
        // Capture any hidden required fields that have no value but were hidden via branching logic
        $emptyReqFields = array();
        if (isset($_POST['empty-required-field'])) {
            $emptyReqFields = $_POST['empty-required-field'];
            // Validate fields and add them with blank values to POST
            foreach ($emptyReqFields as $key=>$emptyReqField) {
                // If not a valid field or if already in POST, then skip
                if (!isset($Proj->metadata[$emptyReqField])) {
                    unset($emptyReqFields[$key]);
                    continue;
                }
                // Add to POST
                if (!isset($_POST[$emptyReqField])) {
                    if ($Proj->isCheckbox($emptyReqField)) {
                        foreach (array_keys(parseEnum($Proj->metadata[$emptyReqField]['element_enum'])) as $emptyReqFieldChoice) {
                            $_POST["__chk__".$emptyReqField."_RC_".DataEntry::replaceDotInCheckboxCoding($emptyReqFieldChoice)] = "";
                        }
                    } else {
                        $_POST[$emptyReqField] = "";
                    }
                }
            }
        }
        // Return array (reindexed, just in case we need it to be)
        return array_values($emptyReqFields);
    }

    // In a checkbox coding that gets used as the input name, replace a dot for a pipe for PHP compatibility
    public static function replaceDotInCheckboxCoding($code='')
    {
        return str_replace(".", "|", $code);
    }

    // Reverse of replaceDotInCheckboxCoding()
    public static function replaceDotInCheckboxCodingReverse($code='')
    {
        return str_replace("|", ".", $code);
    }

    // Function for saving submitted data to the data table
    public static function saveRecord($fetched, $saveCalculations=true, $preventRepeatSurveyRedirect=false, $preventDateReformatInPost=false, $response_id=null, $callSaveRecordHook=false)
    {
        global $double_data_entry, $user_rights, $table_pk, $require_change_reason, $context_msg_update, $multiple_arms,
               $context_msg_error_existing, $context_msg_insert, $secondary_pk, $longitudinal, $Proj, $data_resolution_enabled,
               $realtime_webservice_global_enabled, $randomization, $missingDataCodes;
        // Set project values if not set (depends on context)
        $isSurveyPage = (PAGE == "surveys/index.php" || (defined("NOAUTH") && isset($_GET['s'])));
        if ($table_pk == null) $table_pk = $Proj->table_pk;
        if ($longitudinal == null) $longitudinal = $Proj->longitudinal;
        if ($multiple_arms == null) $multiple_arms = $Proj->multiple_arms;
        if ($double_data_entry == null) $double_data_entry = $Proj->project['double_data_entry'];
        if ($require_change_reason == null) $require_change_reason = $Proj->project['require_change_reason'];
        if ($secondary_pk == null) $secondary_pk = $Proj->project['secondary_pk'];
        if ($data_resolution_enabled == null) $data_resolution_enabled = $Proj->project['data_resolution_enabled'];
        $realtime_webservice_enabled = ($realtime_webservice_global_enabled && $Proj->project['realtime_webservice_enabled']);
        // Get array of repeating forms/events
        $RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();
        $hasRepeatingFormsEvents = !empty($RepeatingFormsEvents);
        // Ignore special fields that only occur for surveys
        $postIgnore = array('__page__', '__response_hash__', '__response_id__', 'empty-required-field');
        // Just in case this wasn't removed earlier, remove CSRF token from Post to prevent it from being added to logging
        unset($_POST['redcap_csrf_token']);
        // Capture any hidden required fields that have no value but were hidden via branching logic.
        // Return the hidden required fields in an array, and also auto-add the fields to POST.
        $emptyReqFields = DataEntry::addEmptyRequiredFieldsToPost();
        // If deleting the form or event, then create a special flag
        $deletingFormOrEvent = false;
        if (isset($_POST['submit-action']) && ($_POST['submit-action'] == "submit-btn-deleteform" || $_POST['submit-action'] == "submit-btn-deleteevent")) {
            // Reset this Post value
            $_POST['submit-action'] = "submit-btn-saverecord";
            // Set flag
            $deletingFormOrEvent = true;
        }
        // If repeating the survey, then create a special flag
        $repeatThisSurvey = false;
        if (isset($_POST['submit-action']) && $_POST['submit-action'] == "submit-btn-saverepeat") {
            // Reset this Post value
            $_POST['submit-action'] = "submit-btn-saverecord";
            // Set flag
            $repeatThisSurvey = true;
        }
        // Just in case the Primary Key field is missing (how?), make sure it's in Post anyway.
        $_POST[$table_pk] = $fetched = trim($fetched);
        // Decode and trim new record name (in case has quotes or spaces), if renaming record.
        if (isset($_POST['__old_id__'])) {
            $_POST['__old_id__'] = trim(html_entity_decode($_POST['__old_id__'], ENT_QUOTES));
        }
        // If user is a double data entry person, append --# to record id when saving
        if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
            $fetched .= "--" . $user_rights['double_data'];
            $_POST[$table_pk] .= "--" . $user_rights['double_data'];
            if (isset($_POST['__old_id__'])) $_POST['__old_id__'] .= "--" . $user_rights['double_data'];
        }
        // Does record exist?
        $recordExists = Records::recordExists(PROJECT_ID, $fetched, (($longitudinal && $multiple_arms && isset($Proj->eventInfo[$_GET['event_id']]['arm_num'])) ? $Proj->eventInfo[$_GET['event_id']]['arm_num'] : null));
        // First, determine what notification message to show AND if record id was changed (if option is enabled)
        if (isset($_POST['hidden_edit_flag']) && $_POST['hidden_edit_flag'] == 1) {
            //Updating existing record
            $context_msg = $context_msg_update;
            //Check if record id changed. If yes, alter listing in data table to reflect the change.
            if (isset($_POST['__old_id__'])) {
                // If record name was changed...
                if ($_POST['__old_id__'] !== $fetched) {
                    // Check if new record name exists already (can't change to record that already exists)
                    if ($recordExists)
                    {
                        // New record already exists, so can't change record id
                        $context_msg = $context_msg_error_existing;
                        // Reset id number back to original value so data can be saved
                        $fetched = $_POST[$table_pk] = $_POST['__old_id__'];
                        // Set extra __rename_failed__ flag to denote that the record rename failed because the record already exists
                        $_POST['__rename_failed__'] = true;
                    } else {
                        // New record does not exist, so change record id
                        DataEntry::changeRecordId($_POST['__old_id__'], $fetched);
                    }
                }
            }
        } else {
            // Creating new record (or changed record id)
            $context_msg = $context_msg_insert;
        }
        // Prevent altering of randomization field value. Determine if this record has already been randomized.
        // If on data entry form, then remove randomization field's value from POST array.
        if ($randomization && PAGE == 'DataEntry/index.php' && Randomization::setupStatus() && Randomization::wasRecordRandomized($fetched)) {
            // Get randomization attributes
            $randAttr = Randomization::getRandomizationAttributes();
            $randomizationField = $randAttr['targetField'];
            // Remove randomization field from POST array
            if (isset($_POST[$randomizationField])) {
                unset($_POST[$randomizationField]);
            }
        }
        // Check if event_id exists in URL. If not, then this is not "longitudinal" and has one event, so retrieve event_id.
        if (!isset($_GET['event_id']) || $_GET['event_id'] == "") {
            $_GET['event_id'] = $Proj->firstEventId;
        }
        if (!$deletingFormOrEvent)
        {
            // Check if MAXCHOICE action tag is used and if exceeded the value just submitted
            if (isset($_GET['page'])) {
                Form::hasReachedMaxChoiceInPostFields($_POST, $fetched, $_GET['event_id']);
            }
            // If Form Status is blank, set to Incomplete
            if (!$deletingFormOrEvent && $saveCalculations && isset($_GET['page']) && (!isset($_POST[$_GET['page']."_complete"]) || (isset($_POST[$_GET['page']."_complete"]) && empty($_POST[$_GET['page']."_complete"]))))
            {
                $_POST[$_GET['page']."_complete"] = '0';
            }
            // Build sql for data retrieval for checking if new data or if overwriting old data
            $datasql = "select field_name, value from redcap_data where record = '" . db_escape($fetched) . "'
                        and event_id = {$_GET['event_id']} and project_id = " . PROJECT_ID .
                        ($hasRepeatingFormsEvents ? " and instance ".($_GET['instance'] == '1' ? "is NULL" : "= ".$_GET['instance']) : "") .
                        " and field_name in (";
            foreach ($_POST as $key=>$value)
            {
                // Ignore special Post fields
                if (in_array($key, $postIgnore)) continue;


                // Ignore the "name" from the "checkbox" field's checkboxes (although do NOT ignore the "checkbox" hidden fields beginning with "__chk__")
                if (substr($key, 0, 8) == '__chkn__')
                {
                    // Remove the field from Post
                    unset($_POST[$key]);
                }
                // Reformat any checkboxes
                elseif (substr($key, 0, 7) == '__chk__')
                {
                    // Parse out the field name and the checkbox coded value
                    list ($key, $chkval) = explode('_RC_', substr($key, 7), 2);
				    $chkval = DataEntry::replaceDotInCheckboxCodingReverse($chkval);
                    $datasql .= "'$key', ";
                }
                // Non-checkbox fields
                else
                {
                    $datasql .= "'$key', ";
                }
                // Also, check if field is a Text field with MDY or DMY date validation.
                // If so, convert to YMD format before saving.
                if (!$preventDateReformatInPost && $_POST[$key] != '' && $key != $Proj->table_pk && isset($Proj->metadata[$key]) && $Proj->metadata[$key]['element_type'] == 'text'
                    && !isset($missingDataCodes[$_POST[$key]])
                    && (substr($Proj->metadata[$key]['element_validation_type'], -4) == "_dmy" || substr($Proj->metadata[$key]['element_validation_type'], -4) == "_mdy"))
                {
                    $thisValType = $Proj->metadata[$key]['element_validation_type'];
                    if ($thisValType == 'date_mdy') {
                        $_POST[$key] = DateTimeRC::date_mdy2ymd($_POST[$key]);
                    } elseif ($thisValType == 'date_dmy') {
                        $_POST[$key] = DateTimeRC::date_dmy2ymd($_POST[$key]);
                    } elseif ($thisValType == 'datetime_mdy' || $thisValType == 'datetime_seconds_mdy') {
                        list ($this_date, $this_time) = explode(" ", $_POST[$key]);
                        $_POST[$key] = DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time;
                    } elseif ($thisValType == 'datetime_dmy' || $thisValType == 'datetime_seconds_dmy') {
                        list ($this_date, $this_time) = explode(" ", $_POST[$key]);
                        $_POST[$key] = DateTimeRC::date_dmy2ymd($this_date) . " " . $this_time;
                    }
                }

            }
            $datasql = substr($datasql,0,-2).")";
            //Execute query and put any existing data into an array to display on form
            $q = db_query($datasql);
            while ($row_data = db_fetch_array($q))
            {
                //Checkbox: Add data as array
                if ($Proj->isCheckbox($row_data['field_name'])) {
                    $current_data[$row_data['field_name']][$row_data['value']] = $row_data['value'];
                //Non-checkbox fields: Add data as string
                } else {
                    $current_data[$row_data['field_name']] = $row_data['value'];
                }
            }
            // print "<br><br>SQL: $datasql<br>Current data: ";print_array($current_data);print_array($_POST);
            // Data Resolution Workflow: If enabled, create array to capture record/event/fields that
            // had their data value changed just now so they can be De-verified, if already Verified.
            $autoDeverify = array();
            // Keep track of all fields where a value changed, added, or deleted
            $field_values_changed = array();
            // Add logging info for Part 11 compliance, if enabled
            $change_reason = ($require_change_reason && isset($_POST['change-reason'])) ? $_POST['change-reason'] : "";
            // Loop through all posted values. Update if exists. Insert if not exist.
            foreach ($_POST as $field_name=>$value)
            {
                $field_name_orig = $field_name;
                // Ignore special Post fields
                if (in_array($field_name, $postIgnore)) continue;
                // Do not save fields that failed server-side validation
                if (isset($serverSideValErrors[$field_name])) continue;
                // Flag for if field is a checkbox
                $is_checkbox = false;
                // Handle the Lock Record field by simply ignoring it
                if ($field_name == '__LOCKRECORD__') continue;
                // Reformat the fieldnames of any checkboxes
                if (substr($field_name, 0, 7) == '__chk__') {
                    // Parse out the field name and the checkbox coded value
                    list ($field_name, $chkval) = explode('_RC_', substr($field_name, 7), 2);
				    $chkval = DataEntry::replaceDotInCheckboxCodingReverse($chkval);
                    // Set flag
                    $is_checkbox = true;
                    // Deal with other repeating instances that might have been hidden on page
                    if (strpos($field_name_orig, '____I') !== false) {
                        continue;
                    }
                }
                // Because all GET/POST elements get HTML-escaped, we need to HTML-unescape them here
                $value = html_entity_decode($value, ENT_QUOTES);
                // Ignore certain fields that are not real metadata fields
                if (strpos($field_name, "-") === false && $field_name != 'hidden_edit_flag' && $field_name != '__old_id__' && !(substr($field_name,0,10) == '_checkbox_' && $value == 'on'))
                {
                    // If on a form or survey, only save fields on this form (don't save hidden fields due to cross-form branching/calcs)
                    if ($saveCalculations && isset($_GET['page']) && $field_name != $Proj->table_pk && $field_name != "__GROUPID__"
                        && $field_name != '__LOCKRECORD__' && !isset($Proj->forms[$_GET['page']]['fields'][$field_name])) {
                        continue;
                    }
                    ## OPTION 1: If data exists for this field (and it's not a checkbox), update the value
                    if (isset($current_data[$field_name]) && !$is_checkbox) {
                        if ($value !== $current_data[$field_name]) {
                            // MAXCHOICE action tag: If a field has value to be saved but has already reached max, then ignore
                            if (isset($_GET['maxChoiceFieldsReached']) && in_array($field_name, $_GET['maxChoiceFieldsReached'])) {
                                continue;
                            }
                            //If current data is different from submitted data, then update
                            if ($value != '') {
                                $sql_all[] = $sql = "UPDATE redcap_data SET value = '" . db_escape($value) . "' WHERE project_id = " . PROJECT_ID
                                                  . " AND record = '" . db_escape($fetched) . "' AND event_id = {$_GET['event_id']} AND field_name = '$field_name'"
                                                  . ($hasRepeatingFormsEvents ? " AND instance ".($_GET['instance'] == '1' ? "is NULL" : "= '".db_escape($_GET['instance'])."'") : "");
                            } else {
                                $sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'"
                                                  . " AND event_id = {$_GET['event_id']} AND field_name = '$field_name'"
                                                  . ($hasRepeatingFormsEvents ? " AND instance ".($_GET['instance'] == '1' ? "is NULL" : "= '".db_escape($_GET['instance'])."'") : "");
                            }
                            db_query($sql);
                            // Add field to values changed array
                            $field_values_changed[] = $field_name;
                            // Add to De-verify array
                            $autoDeverify[$fetched][$_GET['event_id']][$field_name][$_GET['instance']] = true;
                            //Gather new values for logging display
                            if ($field_name != "__GROUPID__" && $field_name != '__LOCKRECORD__') {
                                $display[] = "$field_name = '$value'";
                            }
                            // If we're changing the DAG association of the record, make sure we update any calendar events for this record with the new DAG
                            elseif ($field_name == "__GROUPID__") {
                                // Set flag to log DAG designation
                                $dag_sql_all = array($sql);
                                // Update calendar table (just in case)
                                $sql_all[] = $dag_sql_all[] = $sql = "UPDATE redcap_events_calendar SET group_id = " . checkNull($value) . " WHERE project_id = " . PROJECT_ID
                                                  . " AND record = '" . db_escape($fetched) . "'";
                                db_query($sql);
                                // Also, make sure that ALL EVENTS get assigned the new group_id value
                                if ($value == '') {
                                    $dag_log_descrip = "Remove record from Data Access Group";
                                    $sql_all[] = $dag_sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'"
                                                      . " AND field_name = '$field_name'";
                                } else {
                                    $dag_log_descrip = "Assign record to Data Access Group";
                                    $sql_all[] = $dag_sql_all[] = $sql = "UPDATE redcap_data SET value = '" . db_escape($value) . "' WHERE project_id = " . PROJECT_ID
                                                      . " AND record = '" . db_escape($fetched) . "' AND field_name = '$field_name'";
                                }
                                db_query($sql);
                            }
                        }
                    ## OPTION 2: If field is a checkbox and it was just unchecked, remove the data point completely
            } elseif (isset($chkval) && isset($current_data[$field_name][$chkval]) && $is_checkbox && $value == "") {
                        // If a checkbox field and was just unchecked, then remove from table completely
                        $sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "'"
                                          . " AND event_id = {$_GET['event_id']} AND field_name = '$field_name'"
                                          . " AND value = '" . db_escape($chkval) . "'"
                                          . ($hasRepeatingFormsEvents ? " AND instance ".($_GET['instance'] == '1' ? "is NULL" : "= '".db_escape($_GET['instance'])."'") : "")
                                          . " LIMIT 1";
                        db_query($sql);
                        // Add field to values changed array
                        $field_values_changed[] = $field_name;
                        // Add to De-verify array
                        $autoDeverify[$fetched][$_GET['event_id']][$field_name][$_GET['instance']] = true;
                        //Gather new values for logging display
                        if ($field_name != "__GROUPID__" && $field_name != '__LOCKRECORD__') {
                            $display[] = $is_checkbox ? ("$field_name($chkval) = " . (($value == "") ? "unchecked" : "checked")) : "$field_name = '$value'";
                        }
                    ## OPTION 3: If there is no data for this field (checkbox or non-checkbox)
                    } elseif ((isset($chkval) && !isset($current_data[$field_name][$chkval]) && $is_checkbox) || (!isset($current_data[$field_name]) && !$is_checkbox)) {
                        if ($value != '' && strpos($field_name, '___') === false) { //Do not insert if blank or if the excess Radio field element (which has ___)
                            // MAXCHOICE action tag: If a field has value to be saved but has already reached max, then ignore
                            if (isset($_GET['maxChoiceFieldsReached']) && in_array($field_name, $_GET['maxChoiceFieldsReached'])) {
                                continue;
                            }
                            //Insert values
                            $sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) "
                                              . "VALUES (" . PROJECT_ID . ", {$_GET['event_id']}, '" . db_escape($fetched) . "', "
                                              . "'$field_name', '" . db_escape($value) . "', "
                                              . ($_GET['instance'] == '1' ? "NULL" : "'".db_escape($_GET['instance'])."'") . ")";
                            db_query($sql);
                            // Add field to values changed array
                            $field_values_changed[] = $field_name;
                            // Add to De-verify array
                            $autoDeverify[$fetched][$_GET['event_id']][$field_name][$_GET['instance']] = true;
                            //Gather new values for logging display
                            if ($field_name != "__GROUPID__" && $field_name != '__LOCKRECORD__') {
                                $display[] = $is_checkbox ? ("$field_name($chkval) = " . (($value == "") ? "unchecked" : "checked")) : "$field_name = '$value'";
                            }
                            // If we're setting the DAG association of the record, make sure we update any calendar events for this record with the new DAG
                            elseif ($field_name == "__GROUPID__") {
                                // Set flag to log DAG designation
                                $dag_sql_all = array($sql);
                                $dag_log_descrip = "Assign record to Data Access Group";
                                // Update calendar table (just in case)
                                $sql_all[] = $dag_sql_all[] = $sql = "UPDATE redcap_events_calendar SET group_id = " . checkNull($value) . " WHERE project_id = " . PROJECT_ID
                                                  . " AND record = '" . db_escape($fetched) . "'";
                                db_query($sql);
                            }
                        }
                    }
                }
            }
            ## SECONDARY UNIQUE IDENTIFIER IS CHANGED (LONGITUDINAL)
            // If changing 2ndary id in a longitudinal project, then set that value for ALL instances of the field
            // in other Events (keep them synced for consistency).
            if ($longitudinal && $secondary_pk != '' && isset($_POST[$secondary_pk]) && $_POST[$secondary_pk] !== $current_data[$secondary_pk])
            {
                // Form name of secondary id
                $secondary_pk_form = $Proj->metadata[$secondary_pk]['form_name'];
                // Determine if this is the data entry page of a project
                if ((PAGE == "DataEntry/index.php") && isset($_GET['page']) && isset($_GET['event_id'])
                    // Only do this if the secondary_pk field belongs on this form (rather than is as a hidden field on another form),
                    // in which it may be blank. This could happen when using secondary_pk in cross-event calc fields.
                    && $_GET['page'] == $secondary_pk_form)
                {
                    // Store events where secondary id's form is used
                    $secondary_pk_form_events = $instanceEvents = array();
                    // Get current arm
                    $current_arm = getArm();
                    // If longitudinal with multiple arms, determine on which arms the record exists.
                    if ($longitudinal && $multiple_arms) {
                        // Get all arms on which the record exists
                        $this_record_arms = array();
                        $sql = "select distinct a.arm_num from redcap_data d, redcap_events_metadata e, redcap_events_arms a
                                where a.project_id = " . PROJECT_ID . " and a.project_id = d.project_id and a.arm_id = e.arm_id
                                and e.event_id = d.event_id and d.record = '" . db_escape($fetched) . "'";
                        $q = db_query($sql);
                        while ($row = db_fetch_assoc($q)) {
                            $this_record_arms[] = $row['arm_num'];
                        }
                    }
                    // Get all events that use the form
                    foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
                        if (in_array($secondary_pk_form, $these_forms)) {
                            // If longitudinal with multiple arms, determine on which arms the record exists.
                            if ($longitudinal && $multiple_arms) {
                                // If record does not exist on current arm, then skip
                                $this_event_arm = $Proj->eventInfo[$this_event_id]['arm_num'];
                                if (!in_array($this_event_arm, $this_record_arms)) {
                                    continue;
                                }
                            }
                            // Collect all events where the form is used
                            $secondary_pk_form_events[] = $this_event_id;
                            $instanceEvents[$this_event_id][] = 1;
                        }
                    }

                    // Get list of all repeating instance numbers for all events for this record for this secondary unique field
                    if ($hasRepeatingFormsEvents) {
                        $instanceEvents = RepeatInstance::getRepeatInstanceEventsForField($fetched, $secondary_pk);
                    }

                    // Get events as array for this arm
                    if ($longitudinal && $multiple_arms) {
                        $eventsThisArm = array_keys($Proj->events[$current_arm]['events']);
                    } else {
                        $eventsThisArm = array_keys($Proj->eventInfo);
                    }

                    // If we're setting SUF value to blank, then obtain a list of all events/instances where the SUF exists that have data
                    $secondary_pk_events_instances_with_data = array();
                    if ($_POST[$secondary_pk] == '') {
                        $sql = "select event_id, instance from redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' "
                             . "AND event_id in (" . prep_implode($eventsThisArm) . ") AND field_name = '$secondary_pk'";
                        $q = db_query($sql);
                        while ($row = db_fetch_assoc($q))
                        {
                            if ($row['instance'] == null) $row['instance'] = "";
                            $secondary_pk_events_instances_with_data[$row['event_id']][$row['instance']] = true;
                        }
                    }

                    // First delete all instances of the value on ALL events
                    $sql_all[] = $sql = "DELETE FROM redcap_data WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($fetched) . "' "
                                      . "AND event_id in (" . prep_implode($eventsThisArm) . ") AND field_name = '$secondary_pk'";
                    db_query($sql);
                    // Now loop through all events where 2ndary id is used and insert
                    foreach ($secondary_pk_form_events as $this_event_id)
                    {
                        if (!isset($instanceEvents[$this_event_id])) $instanceEvents[$this_event_id][] = 1;
                        foreach ($instanceEvents[$this_event_id] as $this_instance)
                        {
                            if (!in_array($this_event_id, $eventsThisArm)) continue;
                            if ($this_instance == '1') $this_instance = "";
                            // If the SUF value is blank and this event/instance has no data yet, then there's nothing to do here
                            if ($_POST[$secondary_pk] == '' && !isset($secondary_pk_events_instances_with_data[$this_event_id][$this_instance])) {
                                continue;
                            }
                            // Add value to redcap_data
                            $sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) "
                                              . "VALUES (" . PROJECT_ID . ", $this_event_id, '" . db_escape($fetched) . "', "
                                              . "'$secondary_pk', '" . db_escape($_POST[$secondary_pk]) . "', " . checkNull($this_instance) . ")";
                            db_query($sql);
                            // Log this event so that it shows up in Data History widget for all places where this field is displayed
                           Logging::logEvent($sql, "redcap_data", "update", $fetched, "$secondary_pk = '{$_POST[$secondary_pk]}'", "Update record",
                               $change_reason, "", "", true, $this_event_id, $this_instance);
                        }
                    }
                }
            }
            // If one or more email invitation fields are set, SAVE values FOR SINGLE RECORD ONLY
            if ($Proj->isRepeatingFormOrEvent($_GET['event_id'], $_GET['page']) || $Proj->longitudinal)
            {
                $surveyEmailInvitationFields = $Proj->getSurveyEmailInvitationFields(true);
                $surveyEmailInvitationFieldsSubmitted = array_intersect(array_keys($_POST), $surveyEmailInvitationFields);
                $surveyEmailInvitationFieldsValues = array();
                foreach ($surveyEmailInvitationFieldsSubmitted as $this_field) {
                    // update values only if email field is a real field on this form - nut just used in branching logic, say (and hence included in $_POST)
                    if ($Proj->metadata[$this_field]['form_name'] == $_GET['page']) {
                        $surveyEmailInvitationFieldsValues[$this_field] = $_POST[$this_field];
                    }
                }
                $Proj->saveEmailInvitationFieldValues($fetched, $surveyEmailInvitationFieldsValues, getArm());
            }
            ## Logging
            // Determine if updating or creating a record
            if ($recordExists) {
                $event  = "update";
                $log_descrip = (PAGE == "surveys/index.php") ? "Update survey response" : "Update record";
            } else {
                $event  = "insert";
                $log_descrip = (PAGE == "surveys/index.php") ? "Create survey response" : "Create record";
            }
            // Append note if we're doing automatic calculations
            $log_descrip .= (!$saveCalculations) ? " (Auto calculation)" : "";
            // Log the data change
            $log_event_id = Logging::logEvent(implode(";\n",isset($sql_all) ? $sql_all : array()), "redcap_data", $event, $fetched, implode(",\n", isset($display) ? $display : array()), $log_descrip, $change_reason, "", "", true, null, $_GET['instance']);
            // Log DAG designation (if occurred)
            if (isset($dag_sql_all) && !empty($dag_sql_all))
            {
                $group_name = ($_POST['__GROUPID__'] == '') ? '' : $Proj->getUniqueGroupNames($_POST['__GROUPID__']);
                Logging::logEvent(implode(";\n",$dag_sql_all), "redcap_data", "update", $fetched, "redcap_data_access_group = '$group_name'", $dag_log_descrip, "", "", "", true, null, $_GET['instance']);
            }
        }
        ## DATA RESOLUTION WORKFLOW: If enabled, deverify any record/event/fields that
        // are Verified but had their data value changed just now.
        if ($data_resolution_enabled == '2' && !empty($autoDeverify))
        {
            $num_deverified = DataQuality::dataResolutionAutoDeverify($autoDeverify);
        }
        ## DO CALCULATIONS (unless disabled for project)
        if ($saveCalculations && !$Proj->project['disable_autocalcs']) {
            // Was this a form/survey submission?
            // If so, remove any calc fields that were just submitted on a form (don't need to calculate since JavaScript already calculated them)
            if (isset($_GET['page']) && isset($Proj->forms[$_GET['page']])) {
                // Get trigger fields based ONLY on fields that were just modified
                $calcFields = Calculate::getCalcFieldsByTriggerField(array_unique($field_values_changed));
                // Remove any calc fields just submitted (unless this form is utilized on another event - longitudinal only)
                if (!$Proj->longitudinal || ($Proj->longitudinal && $Proj->numEventsFormDesignated($_GET['page'], array($_GET['event_id'])) < 1)) {
                    foreach (array_keys($Proj->forms[$_GET['page']]['fields']) as $this_field) {
                        if (isset($_POST[$this_field]) && $Proj->metadata[$this_field]['element_type'] == 'calc') {
                            // This is a calc field on the current form AND its value was just submitted, so skip it
                            $this_key = array_search($this_field, $calcFields);
                            if ($this_key !== false) {
                                unset($calcFields[$this_key]);
                            }
                        }
                    }
                }
            } else {
                // Not a form submission (e.g. Data Quality rule, data import)
                $calcFields = Calculate::getCalcFieldsByTriggerField(array_keys($_POST));
            }
            // If there are some calc fields to calculate, then do so
            if (!empty($calcFields)) {
                $calcValuesUpdated = Calculate::saveCalcFields(array($fetched), $calcFields, $_GET['event_id']);
            }
        }

        ## TWILIO: Seed the default delivery preference for new records only
        if (!$recordExists && $Proj->project['twilio_enabled'] && $Proj->twilio_enabled_surveys && $Proj->project['twilio_default_delivery_preference'] != 'EMAIL' && !empty($Proj->surveys))
        {
            $surveyIds = array_keys($Proj->surveys);
            $firstAvailableSurveyId = $surveyIds[0];
            Survey::getFollowupSurveyParticipantIdHash($firstAvailableSurveyId, $fetched, $_GET['event_id'], false, $_GET['instance']);
        }

        // REDCap Hook injection point: Pass project_id, record, and other values
        if ($callSaveRecordHook)
        {
            $dags = $Proj->getGroups();
            $group_id = empty($dags) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
            if (!is_numeric($group_id)) $group_id = null;
            Hooks::call('redcap_save_record', array(PROJECT_ID, $fetched, $_GET['page'], $_GET['event_id'], $group_id, ($isSurveyPage ? $_GET['s'] : null), $response_id, $_GET['instance']));
        }

        ## SURVEY INVITATION SCHEDULE LISTENER
        // If the form is designated as a survey, check if a survey schedule has been defined for this event.
        // If so, perform check to see if this record/participant is NOT a completed response and needs to be scheduled for the mailer.
        if (!empty($Proj->surveys))
        {
            // Check if we're ready to schedule the participant's survey invitation to be sent
            $surveyScheduler = new SurveyScheduler();
            // Return count of invitation scheduled, if any
            $numInvitationsScheduled = $surveyScheduler->checkToScheduleParticipantInvitation($fetched);
            // If this was a survey response that was just completed AND it already has an invitation queued,
            // then flag it in scheduler_queue table (if already in there).
            if (PAGE == 'surveys/index.php' || $_POST['submit-action'] == "submit-btn-savecompresp") {
                // Return boolean for if invitation status was changed to SURVEY ALREADY COMPLETED
                $invitationUnscheduled = SurveyScheduler::deleteInviteIfCompletedSurvey($Proj->forms[$_GET['page']]['survey_id'], $_GET['event_id'], $fetched, $_GET['instance']);
            }
        }

        ## DDP: If using DDP and the source identifier field's value just changed, then purge that record's cached data
        ## so it can be reobtained from the source system.
        if ($realtime_webservice_enabled) {
            // Make sure DDP has been mapped in this project
            $DDP = new DynamicDataPull(PROJECT_ID, $Proj->project['realtime_webservice_type']);
            if ($DDP->isMappingSetUp()) {
                // Get the DDP identifier field
                list ($ddp_id_field, $ddp_id_event) = $DDP->getMappedIdRedcapFieldEvent();
                if ($ddp_id_event == $_GET['event_id'] && in_array($ddp_id_field, $field_values_changed)) {
                    $DDP->purgeDataCache($fetched);
                }
            }
        }

        ## DATA ENTRY TRIGGER
        // If the Data Entry Trigger is enabled, then send HTTP Post request to specified URL
        DataEntry::launchDataEntryTrigger();

        // Return boolean if any fields have data values being added/modified
        $dataValuesModified = $dataValuesModifiedIncludingCalcs = false;
        foreach ($field_values_changed as $this_field) {
            if ($dataValuesModifiedIncludingCalcs && $dataValuesModified) break;
            // Ignore record ID field
            if ($this_field == $Proj->table_pk) continue;
            // Count calc fields and non-calc fields separately
            $dataValuesModifiedIncludingCalcs = true;
            if ($Proj->metadata[$this_field]['element_type'] != "calc") {
                $dataValuesModified = true;
            }
        }

        // Alerts (only do this when calling the save_record hook)
        if ($callSaveRecordHook)
        {
            $eta = new Alerts();
            $eta->saveRecordAction(PROJECT_ID, $fetched, $_GET['page'], $_GET['event_id'], $_GET['instance'], ($isSurveyPage ? $_GET['s'] : null), $response_id, $dataValuesModified, $dataValuesModifiedIncludingCalcs);
        }

        ## REPEAT THE SURVEY
        if (!$preventRepeatSurveyRedirect && $repeatThisSurvey)
        {
            // Get count of existing instances and find next instance number
            list ($instanceTotal, $instanceMax) = RepeatInstance::getRepeatFormInstanceMaxCount($fetched, $_GET['event_id'], $_GET['page'], $Proj);
            $instanceNext = max(array($instanceMax, $_GET['instance'])) + 1;
            // Since we just completed a repeating survey, make sure we also send emails to participant and users, if applicable
            Survey::sendSurveyConfirmationEmail($Proj->forms[$_GET['page']]['survey_id'], $_GET['event_id'], $fetched);
            Survey::sendEndSurveyEmails($Proj->forms[$_GET['page']]['survey_id'], $_GET['event_id'], $GLOBALS['participant_id'], $fetched, $_GET['instance']);
            // Get the next instance's survey url
            $repeatSurveyLink = REDCap::getSurveyLink($fetched, $_GET['page'], $_GET['event_id'], $instanceNext);
            redirect($repeatSurveyLink);
        }

        // Return the current record name (in case was renamed) and context message for user display
        return array($fetched, $context_msg, $log_event_id, $dataValuesModified, $dataValuesModifiedIncludingCalcs);
    }

    //Function for changing a record id (if option is enabled)
    public static function changeRecordId($old_id, $new_id)
    {
        global $Proj, $table_pk, $multiple_arms, $status;
        // If multiple arms exist, get list of all event_ids from current arm, so we can tack this on to each query (so don't rename records from other arms)
        $eventList = "";
        $arm_id = getArmId();
        $arm = getArm();
        if ($multiple_arms && isset($_GET['event_id'])) {
            // Only rename this record for THIS ARM
            $eventPreQuery = pre_query("select event_id from redcap_events_metadata where arm_id = $arm_id");
            $eventList = " AND event_id IN ($eventPreQuery)";
            $eventList2 = " AND rss.event_id IN ($eventPreQuery)";
        }
        //Change record id value first for the id field
        $sql_all[] = $sql = "UPDATE redcap_data SET value = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' AND field_name = '$table_pk' $eventList";
        db_query($sql);
        //Change record id for all fields
        $sql_all[] = $sql = "UPDATE redcap_data SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        //Change logging history to reflect new id number
        $sql_all[] = $sql = "UPDATE ".Logging::getLogEventTable(PROJECT_ID)." SET pk = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND pk = '" . db_escape($old_id) . "' AND legacy = '0' $eventList";
        db_query($sql);
        //Change record id in calendar
        $sql_all[] = $sql = "UPDATE redcap_events_calendar SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        //Change record id in locking_data table
        $sql_all[] = $sql = "UPDATE redcap_locking_data SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        $sql_all[] = $sql = "UPDATE redcap_locking_records SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' AND arm_id = $arm_id";
        db_query($sql);
        //Change record id in e-signatures table
        $sql_all[] = $sql = "UPDATE redcap_esignatures SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        //Change record id in data quality table
        $sql_all[] = $sql = "UPDATE redcap_data_quality_status SET record = '" . db_escape($new_id) . "' WHERE project_id = " . PROJECT_ID
                          . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        //Change record id in survey response table
        $participant_ids = pre_query("select p.participant_id from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
                                     where s.project_id = " . PROJECT_ID . " and s.survey_id = p.survey_id and p.participant_id = r.participant_id
                                     and p.event_id in (".pre_query("select event_id from redcap_events_metadata where arm_id = $arm_id").")
                                     and r.record = '" . db_escape($old_id) . "'");
        $sql_all[] = $sql = "UPDATE redcap_surveys_response SET record = '" . db_escape($new_id) . "' WHERE record = '" . db_escape($old_id) . "'"
                          . " AND participant_id in ($participant_ids)";
        db_query($sql);
        // Change record id in randomization allocation table (if applicable)
        $sql_all[] = $sql = "UPDATE redcap_randomization_allocation a, redcap_randomization r
                             SET a.is_used_by = '" . db_escape($new_id) . "'
                             WHERE r.project_id = " . PROJECT_ID . " and a.project_status = $status
                             and r.rid = a.rid and a.is_used_by = '".db_escape($old_id)."'";
        db_query($sql);
        // Change record id in redcap_ddp_records
        $sql_all[] = $sql = "UPDATE redcap_ddp_records SET record = '" . db_escape($new_id) . "'
                             WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($old_id) . "'";
        db_query($sql);
        // Change record id in redcap_ehr_import_counts
        $sql_all[] = $sql = "UPDATE redcap_ehr_import_counts SET record = '" . db_escape($new_id) . "'
                             WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($old_id) . "'";
        db_query($sql);
        // Change record id in redcap_surveys_queue_hashes
        $sql_all[] = $sql = "UPDATE redcap_surveys_queue_hashes SET record = '" . db_escape($new_id) . "'
                             WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($old_id) . "'";
        db_query($sql);
        // Change record id in redcap_surveys_scheduler_queue
        $sql_all[] = $sql = "UPDATE redcap_surveys_scheduler rss, redcap_events_metadata rem, redcap_events_arms rea, redcap_surveys_scheduler_queue ssq
                            SET ssq.record = '" . db_escape($new_id) . "' WHERE rss.event_id = rem.event_id and rem.arm_id = rea.arm_id $eventList2
                            and rea.project_id = " . PROJECT_ID . " and ssq.ss_id = rss.ss_id and ssq.record = '" . db_escape($old_id) . "'";
        db_query($sql);
        // Change record id in redcap_new_record_cache
        $sql_all[] = $sql = "UPDATE redcap_new_record_cache SET record = '" . db_escape($new_id) . "'
                             WHERE project_id = " . PROJECT_ID . " AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        // Change record id in redcap_surveys_pdf_archive
        $sql_all[] = $sql = "UPDATE redcap_surveys_pdf_archive SET record = '" . db_escape($new_id) . "' 
                             WHERE event_id in (".implode(', ', array_keys($Proj->eventInfo)).") 
                             AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        // Change record id in redcap_surveys_scheduler_queue
        $sql_all[] = $sql = "update redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r, redcap_surveys_emails e, redcap_surveys s
                             set record = '" . db_escape($new_id) . "'
                             where r.email_recip_id = q.email_recip_id and r.email_id = e.email_id and e.survey_id = s.survey_id
                             and q.record = '" . db_escape($old_id) . "' and s.project_id = " . PROJECT_ID;
        db_query($sql);
        // Change record id in alerts tables
        $sql_all[] = $sql = "UPDATE redcap_alerts_recurrence SET record = '" . db_escape($new_id) . "' 
                             WHERE event_id in (".implode(', ', array_keys($Proj->eventInfo)).") 
                             AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        $sql_all[] = $sql = "UPDATE redcap_alerts_sent SET record = '" . db_escape($new_id) . "' 
                             WHERE event_id in (".implode(', ', array_keys($Proj->eventInfo)).") 
                             AND record = '" . db_escape($old_id) . "' $eventList";
        db_query($sql);
        // Change record id in redcap_record_list
        Records::renameRecordInRecordListCache(PROJECT_ID, $new_id, $old_id, $arm);
        //Logging
        Logging::logEvent(implode(";\n",$sql_all),"redcap_data","update",$new_id,"$table_pk = '$new_id'","Update record");
    }

    //Function for deleting a record (if option is enabled) - if multiple arms exist, will only delete record for current arm
    public static function deleteRecord($fetched)
    {
        global $table_pk, $multiple_arms, $randomization, $status, $require_change_reason;
        $arm_id = getArmId();
        Records::deleteRecord($fetched, $table_pk, $multiple_arms, $randomization, $status, $require_change_reason, $arm_id);
    }

    // Retrieve data values for Context Detail, if has been set
    public static function parse_context_msg($custom_record_label,$context_msg,$removeIdentifiers=false)
    {
        global $secondary_pk, $Proj, $user_rights, $double_data_entry, $secondary_pk_display_value, $secondary_pk_display_label;
        // Append secondary ID field value, if set for a "survey+forms" type project
        if ($secondary_pk != '' && $secondary_pk_display_value)
        {
            // Is 2ndary PK an identifier?
            $secondary_pk_val = ($removeIdentifiers && $Proj->metadata[$secondary_pk]['field_phi'] && $user_rights['data_export_tool'] == '2') ? "[IDENTIFIER]" : $Proj->getSecondaryIdVal($_GET['id']);
            // Add field value and its label to context message
            if ($secondary_pk_val != '') {
                $context_msg = substr($context_msg, 0, -6)
                             . "<span style='font-size:11px;color:#800000;padding-left:8px;'>("
                             . ($secondary_pk_display_label ? strip_tags(br2nl(label_decode($Proj->metadata[$secondary_pk]['element_label'], false)))." " : "")
                             . "<b>$secondary_pk_val</b>)</span></div>";
            }
        }
        // If Custom Record Label is specified (such as "[last_name], [first_name]", then parse and display)
        if (!empty($custom_record_label))
        {
            // Replace any Smart Variable and prepend/append events and instances in Custom Record Label
            $custom_record_label_orig = $custom_record_label;
            if ($Proj->longitudinal) {
                $custom_record_label = LogicTester::logicPrependEventName($custom_record_label, $Proj->getUniqueEventNames($_GET['event_id']), $Proj);
            }
            $custom_record_label = Piping::pipeSpecialTags($custom_record_label, $Proj->project_id, $_GET['id'], $_GET['event_id'], $_GET['instance'], USERID, false, null, $_GET['page'], false);
            if ($Proj->hasRepeatingFormsEvents()) {
                $custom_record_label = LogicTester::logicAppendInstance($custom_record_label, $Proj, $_GET['event_id'], $_GET['page'], $_GET['instance']);
            }
            // Add to context message
            $context_msg = substr($context_msg, 0, -6) . " <span style='font-size:11px;padding-left:8px;'>"
                         . getCustomRecordLabels($custom_record_label, $Proj->getFirstEventIdArm(getArm()),
                                $_GET['id'] . ($double_data_entry && $user_rights['double_data'] != 0 ? '--'.$user_rights['double_data'] : ''),
                                $removeIdentifiers)
                         . "</span></div>";
            $custom_record_label = $custom_record_label_orig; // Reset back to original
        }
        // Return value
        return $context_msg;
    }

    //Function for rendering Context Detail at top of data entry page, if specified in Control Center
    public static function render_context_msg($custom_record_label,$context_msg)
    {
        global $Proj, $longitudinal, $multiple_arms, $lang, $hidden_edit, $user_firstname, $user_lastname, $user_email;
        // Retrieve data values for Context Detail, if has been set
        $context_msg = DataEntry::parse_context_msg($custom_record_label, $context_msg);
        //If multiple events exist, display this Event name
        if ($longitudinal)
        {
            // Get all repeating events
            $repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
            // Add instance number if a repeating instance
            $is_repeating_event = (isset($repeatingFormsEvents[$_GET['event_id']]) && !is_array($repeatingFormsEvents[$_GET['event_id']]));
            $instanceNum = ($is_repeating_event && $_GET['instance'] > 1) ? "<span style='margin-left:4px;'>({$lang['data_entry_278']}{$_GET['instance']})</span>" : "";
            //Render the event name, if longitudinal
            $event_name = $Proj->eventInfo[$_GET['event_id']]['name_ext'];
            $context_msg .= "<div class='yellow'>
                             <img src='".APP_PATH_IMAGES."spacer.gif' style='width:16px;height:1px;'>
                             {$lang['global_10']}{$lang['colon']} 
                             <span style='font-weight:bold;color:#800000;'>".RCView::escape(strip_tags($event_name))."</span>
                             $instanceNum
                             </div>";
        }
        return "<div id='contextMsg'>$context_msg</div>";
    }

    // Input a multi-line value for Select Choices values and return a formated enum string (auto-code when any values do not have manual coding)
    public static function autoCodeEnum($enum) {
        // Set default max coded value (to use for any non-manual codings)
        $maxcode = 0;
        // Create array to use to auto-coding when no manual coding is supplied by user
        $auto_coded_labels = array();
        // Create temp array for cleaning $enum_array array
        $enum_array2 = array();
        // Check if manually coded. If not, do auto coding.
        $enum_array = explode("\n", $enum);
        // Loop through coded variables, remove any non-numerical codings, and add codings for those not coded by user
        foreach ($enum_array as $choice)
        {
            $choice = trim($choice);
            if ($choice != "") {
                // If coded manually, clean and do checking of format
                $pos = strpos($choice, ",");
                if ($pos !== false) {
                    $coded_value = trim(substr($choice, 0, $pos));
                    $label = trim(substr($choice, $pos + 1));
                    if ($coded_value != "") {
                        // If coded value is not numeric AND doesn't pass RegEx for acceptable raw value format, then don't process here but add to array for later auto-coding
                        if (!preg_match("/[0-9A-Za-z_]/", $coded_value)) {
                            $auto_coded_labels[] = $choice;
                        // Add to array after parsing
                        } else {
                            $enum_array2[$coded_value] = $label;
                            // Set this as max coded value, if it is the highest number value thus far
                            if (is_numeric($coded_value) && $coded_value > $maxcode) {
                                $maxcode = $coded_value;
                            }
                        }
                    }
                // If not coded manually, add to array for later auto-coding
                } else {
                    $auto_coded_labels[] = $choice;
                }
            }
        }
        // Loop through non-manually coded values and add to temp array
        foreach ($auto_coded_labels as $label) {
            $maxcode++;
            $enum_array2[$maxcode] = $label;
        }
        // Set variable back again with new values
        $enum_array = array();
        foreach ($enum_array2 as $coded_value=>$label) {
            $enum_array[] = "$coded_value, $label";
        }
        // Return the new value
        return implode(" \\n ", $enum_array);
    }

    // On Data Entry Form, get next form that the user has access to
    public static function getNextForm($current_form, $event_id)
    {
        global $Proj, $user_rights;
        if (!is_numeric($event_id)) return '';
        $current_form_key = array_search($current_form, $Proj->eventsForms[$event_id]);
        if ($current_form_key === false) return '';
        foreach ($Proj->eventsForms[$event_id] as $key=>$this_form) {
            // Get the next form that the user has access to
            if ($key > $current_form_key && isset($user_rights['forms'][$this_form]) && $user_rights['forms'][$this_form] > 0) {
                return $this_form;
            }
        }
        return '';
    }

    /**
     * GENERATE NEW AUTO ID FOR A DATA ENTRY PAGE
     * NOTE: For longitudinal projects, it does NOT get next ID for the selected arm BUT returns next ID
     * considering all arms together (prevents duplication of records across arms).
     */
    public static function getAutoId($project_id=null, $useRecordListCache=true)
    {
        global $user_rights;
        if (!is_numeric($project_id) && defined("PROJECT_ID")) $project_id = PROJECT_ID;
        if (!is_numeric($project_id)) return '';
        $Proj = new Project($project_id);
        // See if the record list has alrady been cached. If so, use it.

        $recordListCacheStatus = Records::getRecordListCacheStatus($project_id);
        ## USE RECORD LIST CACHE (if completed)
        if ($useRecordListCache && $recordListCacheStatus == 'COMPLETE') {
            // User is in a DAG, so only pull records from this DAG
            if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
            {
                $sql = "select distinct(substring(a.record,".(strlen($user_rights['group_id'])+2).")) as thisrecord
                        from redcap_record_list a 
                        where a.record like '{$user_rights['group_id']}-%' and a.project_id = $project_id
                        and substring(a.record,".(strlen($user_rights['group_id'])+2).") regexp '^[0-9]+$'
                        order by cast(thisrecord as signed integer) desc limit 1";
            }
            // User is not in a DAG
            else {
                $sql = "select distinct record as thisrecord from redcap_record_list 
                        where project_id = $project_id
                        and record regexp '^[0-9]+$'
                        order by cast(record as signed integer) desc limit 1";
            }
        }
        ## USE DATA TABLE
        else {
            // User is in a DAG, so only pull records from this DAG
            if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
            {
                $sql = "select distinct(substring(a.record,".(strlen($user_rights['group_id'])+2).")) as thisrecord
                        from redcap_data a left join redcap_data b
                        on a.project_id = b.project_id and a.record = b.record and b.field_name = '__GROUPID__'
                        where a.record like '{$user_rights['group_id']}-%' and a.field_name = '{$Proj->table_pk}'
                        and a.project_id = $project_id
                        and substring(a.record,".(strlen($user_rights['group_id'])+2).") regexp '^[0-9]+$'
                        order by cast(thisrecord as signed integer) desc limit 1";
            }
            // User is not in a DAG
            else {
                $sql = "select distinct record as thisrecord from redcap_data 
                        where project_id = $project_id and field_name = '{$Proj->table_pk}'
                        and record regexp '^[0-9]+$'
                        order by cast(record as signed integer) desc limit 1";
            }
        }
        $recs = db_query($sql);
        //Use query from above and find the largest record id and add 1
        $holder = 0;
        while ($row = db_fetch_assoc($recs))
        {
            $holder = $row['thisrecord'];
        }
        db_free_result($recs);
        // Increment the highest value by 1 to get the new value
        $holder++;
        //If user is in a DAG append DAGid+dash to beginning of record
        if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
        {
            $holder = $user_rights['group_id'] . "-" . $holder;
        }
        // Return new auto id value
        return $holder;
    }

    // Return arrays of calc fields on a form and fields involved in calc equation
    public static function getCalcFields($form)
    {
        global $Proj, $longitudinal;
        $calc_fields_this_form = array();
        $calc_triggers = array();
        $fieldsThisForm = array_keys($Proj->forms[$form]['fields']);
        // Pull any calc fields from other forms that are dependent upon fields on this form (need to add as hidden fields here)
        $subquery_array = array();
        foreach ($fieldsThisForm as $this_field)
        {
            $subquery_array[] = "element_enum like '%[$this_field]%'";
        }
        if (!empty($subquery_array)) {
            $pre_sql = "select field_name from redcap_metadata where element_type = 'calc' and form_name != '$form'
                        and project_id = " . PROJECT_ID . " and (" . implode(" or ", $subquery_array) . ")";
            $subquery = "or field_name in ("
                      . pre_query($pre_sql)
                      . ")";
        } else {
            $subquery = "";
        }
        // If field is not on this form, then add it as a hidden field at bottom near Save buttons
        $sql = "select field_name, element_enum, form_name from redcap_metadata where element_type = 'calc' and element_enum != ''
                and (form_name = '$form' $subquery) and project_id = " . PROJECT_ID . " order by field_order";
        $q = db_query($sql);
        while ($rowcalc = db_fetch_assoc($q))
        {
            // Pipe any special tags?
            $rowcalc['element_enum'] = Piping::pipeSpecialTags($rowcalc['element_enum'], PROJECT_ID, $_GET['id'], $_GET['event_id'], $_GET['instance'], USERID, true, null, $_GET['page']);
            //Add this Calc field to Calculate Object for rendering the JavaScript
            if ($rowcalc['form_name'] == $form) {
                $calc_triggers[$rowcalc['field_name']] = $rowcalc['element_enum'];
            }
            //Add all fields in the equation to array
            foreach (array_keys(getBracketedFields($rowcalc['element_enum'], true, true)) as $this_field)
            {
                $calc_fields_this_form[] = $this_field;
            }
            // If field is on other form, then add to $calc_fields_this_form so that it gets added as hidden field
            if ($rowcalc['form_name'] != $form)
            {
                $calc_fields_this_form[] = $rowcalc['field_name'];
            }
        }
        array_unique($calc_fields_this_form);
        // If using unique event name in equation and we're currently on that event, replace the event name in the JS
        if ($longitudinal)
        {
            foreach ($calc_fields_this_form as $this_key=>$this_field)
            {
                if (strpos($this_field, ".") !== false)
                {
                    list ($this_event, $this_field) = explode(".", $this_field, 2);
                    $this_event_id = array_search($this_event, $Proj->getUniqueEventNames());
                    if ($this_event_id == $_GET['event_id'])
                    {
                        $calc_fields_this_form[$this_key] = $this_field;
                    }
                }
            }
        }
        array_unique($calc_fields_this_form);
        // Return the two arrays
        return array($calc_triggers, $calc_fields_this_form);
    }

    // Return arrays of fields with branching logic on a form and fields involved in the logic
    public static function getBranchingFields($form)
    {
        global $longitudinal, $Proj;
        $bl_fields_this_form = array();
        $bl_triggers = array();
        // If field is not on this form, then add it as a hidden field at bottom near Save buttons
        foreach (array_keys($Proj->forms[$form]['fields']) as $this_field)
        {
            $row = $Proj->metadata[$this_field];
            if ($row['branching_logic'] == '') continue;
            // Pipe any special tags?
            $row['branching_logic'] = Piping::pipeSpecialTags($row['branching_logic'], PROJECT_ID, $_GET['id'], $_GET['event_id'], $_GET['instance'], USERID, true, null, $_GET['page']);
            //Add this Calc field to Calculate Object for rendering the JavaScript
            $bl_triggers[$this_field] = $row['branching_logic'];
            //Add all fields in the equation to array
            foreach (array_keys(getBracketedFields($row['branching_logic'], true, true)) as $this_field)
            {
                $bl_fields_this_form[] = $this_field;
            }
        }
        array_unique($bl_fields_this_form);
        // If using unique event name in equation and we're currently on that event, replace the event name in the JS
        if ($longitudinal)
        {
            foreach ($bl_fields_this_form as $this_key=>$this_field)
            {
                if (strpos($this_field, ".") !== false)
                {
                    list ($this_event, $this_field) = explode(".", $this_field, 2);
                    $this_event_id = array_search($this_event, $Proj->getUniqueEventNames());
                    if ($this_event_id == $_GET['event_id'])
                    {
                        $bl_fields_this_form[$this_key] = $this_field;
                    }
                }
            }
        }
        array_unique($bl_fields_this_form);
        // Return the two arrays
        return array($bl_triggers, $bl_fields_this_form);
    }

    // Gather and structure metadata for a given form, and return output as array to place in DataEntry::renderForm() function
    public static function buildFormData($form_name, $skipFields=array())
    {
        global $Proj, $lang, $user_rights, $table_pk, $cp, $bl, $longitudinal, $auto_inc_set, $repeatingFieldsEventInfo;
        ## Calculated Fields: Get all field names involved in calculations
        // Get list of calc trigger fields and fields involved in calcultions
        list ($calc_triggers, $calc_fields_this_form) = DataEntry::getCalcFields($form_name);
        // Add each Calc field to Calculate Object for rendering the JavaScript
        foreach ($calc_triggers as $this_field=>$this_enum)
        {
            // If field is in $skipFields, then skip it
            if (in_array($this_field, $skipFields)) continue;
            // Add field
            $cp->feedEquation($this_field, $this_enum);
        }
        ## Branching Logic: Get all field names involved in branching equation
        // If field is not on this form, then add it as a hidden field at bottom near Save buttons
        list ($bl_triggers, $branch_fields_this_form) = DataEntry::getBranchingFields($form_name);
        // Add each Branching field to BranchingLogic Object for rendering the JavaScript
        foreach ($bl_triggers as $this_field=>$this_enum)
        {
            // If field is in $skipFields, then skip it
            if (in_array($this_field, $skipFields)) continue;
            // Add field
            $bl->feedBranchingEquation($this_field, $this_enum);
        }

        // Obtain the unique event name for this event (longitudinal only)
        $this_unique_event = null;
        if ($longitudinal) {
            $unique_event_names = $Proj->getUniqueEventNames();
            $this_unique_event  =  $unique_event_names[$_GET['event_id']];
        }
        // Obtain the list of DAGs and set flags if DAGs exist
        $dags = $Proj->getGroups();
        $removeRecordIdValidation = (!empty($dags) && $auto_inc_set && $user_rights['record_rename']);
        // ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
        $action_tags_regex = Form::getActionTagMatchRegex();
        // Set array to catch checkbox fieldnames
        $chkbox_flds = array();
        // Initialize the counter
        $j = 0;
        // Set initial grid name for Matrix question formatting groups
        $prev_grid_name = "";
        //print_array($skipFields);
        $form_elements = array();
        // Loop through all fields for this form
        foreach (array_keys($Proj->forms[$form_name]['fields']) as $field_name)
        {
            /** @var $form_element Holds data for this form element, to be added to the $form_elements collection */
            $form_element = array();
            // If field is in $skipFields, then skip it
            if (in_array($field_name, $skipFields)) continue;
            // Increment counter
            $j++;
            //Replace any single or double quotes since they cause rendering problems
            $orig_quote = array("'", "\"");
            $repl_quote = array("&#039;", "&quot;");
            $element_label = str_replace($orig_quote, $repl_quote, $Proj->metadata[$field_name]['element_label']);
            $element_preceding_header = $Proj->metadata[$field_name]['element_preceding_header'];
            $element_type = $Proj->metadata[$field_name]['element_type'];
            $element_enum = str_replace($orig_quote, $repl_quote, $Proj->metadata[$field_name]['element_enum']);
            $element_note = $Proj->metadata[$field_name]['element_note'];
            $element_validation_type = $Proj->metadata[$field_name]['element_validation_type'];
            $element_validation_min = $Proj->metadata[$field_name]['element_validation_min'];
            $element_validation_max = $Proj->metadata[$field_name]['element_validation_max'];
            $element_validation_checktype = $Proj->metadata[$field_name]['element_validation_checktype'];
            $field_req = $Proj->metadata[$field_name]['field_req'];
            $edoc_id = $Proj->metadata[$field_name]['edoc_id'];
            $edoc_display_img = $Proj->metadata[$field_name]['edoc_display_img'];
            $custom_alignment = $Proj->metadata[$field_name]['custom_alignment'];
            $grid_name = trim($Proj->metadata[$field_name]['grid_name']);
            $grid_rank = $Proj->metadata[$field_name]['grid_rank'];
            $video_url = trim($Proj->metadata[$field_name]['video_url']);
            $video_display_inline = trim($Proj->metadata[$field_name]['video_display_inline']);
            // First check to see if this is the record id.
            // If so, use use rights to determine if it should be rendered as an editable entity
            if ($field_name == $table_pk && isset($user_rights) && !$user_rights['record_rename']) {
                continue;
            }

            // Make sure the record ID field is not editable (because users should rename it on the Record Home page, not here on the form)
            if ($field_name == $table_pk) $element_type = 'hidden';

            ## SECTION HEADER: If this data field specifies a 'header' separator - process this first
            if ($element_preceding_header)
            {
                if (strpos($element_preceding_header,"'") !== false) $element_preceding_header = str_replace("'","&#39;",$element_preceding_header); //Apostrophes cause issues when rendered, so replace with equivalent html character
                $element_preceding_header = nl2br($element_preceding_header);
                $form_elements[] = array(
                    "rr_type" => "header",
                    "shfield" => $field_name,
                    "css_element_class" => "header",
                    "value" => $element_preceding_header,
                );
            }
            ## MATRIX QUESTION GROUPS
            $isMatrixField = false; //default
            // Beginning a new grid
            if ($grid_name != "" && $prev_grid_name != $grid_name)
            {
                // Insert matrix header row
                $form_elements[] = array(
                    "rr_type" => "matrix_header",
                    "grid_name" => $grid_name,
                    "grid_rank" => $grid_rank,
                    "field" => $field_name,
                    "enum" => $element_enum,
                );
                // Set flag that this is a matrix field
                $isMatrixField = true;
                // Set that field is the first field in matrix group
                $matrixGroupPosition = '1';
            }
            // Continuing an existing grid
            elseif ($grid_name != "" && $prev_grid_name == $grid_name)
            {
                // Set flag that this is a matrix field
                $isMatrixField = true;
                // Set that field is *not* the first field in matrix group
                $matrixGroupPosition = 'X';
            }
            // Set value for next loop
            $prev_grid_name = $grid_name;
            // Process the data element itself
            $form_element["field"] = $field_name;
            $form_element["name"] = $field_name;
            $form_element["rr_type"] = $element_type == "sql" ? "select" : $element_type;
            // IF a matrix field, then set flag in this element
            if ($isMatrixField) {
                $form_element["matrix_field"] = $matrixGroupPosition;
                $form_element["grid_name"] = $grid_name;
            }
            //Process required field status (add note underneath field label)

            if ($field_req == '1' && $element_type != 'descriptive')
            {
                $fieldReqClass = ($isMatrixField) ? 'requiredlabelmatrix' : 'requiredlabel'; // make matrix fields more compact
                $element_label .= "<div class='$fieldReqClass' aria-label='".js_escape($lang['survey_1145'])."'>* {$lang['data_entry_39']}</div>";
                // $element_label .= "<div class='$fieldReqClass'>* {$lang['data_entry_39']}</div>";
                // Add 'required field' flag
                $form_element["field_req"] = 1;
            }
            // Process field label
            $form_element["label"] = " " . nl2br($element_label);
            // Custom alignment
            $form_element["custom_alignment"] = $custom_alignment;
            // If field_annotation has @, then assume it might be an action tag
            if (strpos($Proj->metadata[$field_name]['misc'], '@') !== false) {
                // Match triggers via regex
                preg_match_all($action_tags_regex, $Proj->metadata[$field_name]['misc'], $this_misc_match);
                if (isset($this_misc_match[1]) && !empty($this_misc_match[1])) {
                    $form_element["action_tag_class"] = implode(" ", $this_misc_match[1]);
                }
            }
            // Tabbing order for fields
            $form_element["tabindex"] = $j;
            // If a checkbox, then increment $j for all checkbox options so that each gets a different tabindex
            if ($element_type == 'checkbox') {
                $this_chk_enum = $Proj->metadata[$field_name]['element_enum'];
                if ($this_chk_enum != '') {
                    $j = $j - 1 + count(parseEnum($this_chk_enum));
                }
            }
            // Add slider labels & and display value option
            if ($element_type == 'slider')
            {
                $slider_labels = Form::parseSliderLabels($element_enum);
                $form_element["slider_labels"] = array(
                    decode_filter_tags($slider_labels['left']),
                    decode_filter_tags($slider_labels['middle']),
                    decode_filter_tags($slider_labels['right']),
                    $element_validation_type
                );
            }
            //For elements of type 'text', we'll handle data validation if details are provided in metadata
            if ($element_type == 'text' || $element_type == 'calc')
            {
                // Check if using validation
                if (!empty($element_validation_type)
                    // If auto-numbering is enabled AND user is in a DAG AND has record rename rights,
                    // then remove the record ID field's validation to prevent issues when the first form loads for an existing record.
                    // (It would get the user stuck on the page forever unless they closed the tab/window.)
                    && !($field_name == $table_pk && $removeRecordIdValidation))
                {
                    // Catch specific regex validation types
                    if ($element_validation_type == "date" || $element_validation_type == "datetime" || $element_validation_type == "datetime_seconds") {
                        // Add "_ymd" to end of legacy date validation names so that they correspond with values from validation table
                        $element_validation_type .= "_ymd";
                    // Catch legacy values
                    } elseif ($element_validation_type == "int") {
                        $element_validation_type = "integer";
                    } elseif ($element_validation_type == "float") {
                        $element_validation_type = "number";
                    }
                    // Set javascript validation function
                    $hold_validation_string  = "redcap_validate(this,'$element_validation_min','$element_validation_max',";
                    $hold_validation_string .= (!empty($element_validation_checktype) ? "'$element_validation_checktype'" : "'soft_typed'");
                    $hold_validation_string .= ",'$element_validation_type',1)";
                    $form_element["validation"] = $element_validation_type;
                    $form_element["onblur"] = "$hold_validation_string";
                }
                // ONTOLOGY AUTO-SUGGEST
                elseif ($element_type == 'text' && $element_enum != '' && strpos($element_enum, ":") !== false) {
                    $form_element["element_enum"] = $element_enum;
                }
            }
            // Add $element_validation_type for FILE fields (for signatures only) and SELECT fields (for auto-complete)
            if (($element_type == 'file' || $element_type == 'select' || $element_type == 'sql') && $element_validation_type != '') {
                $form_element["validation"] = $element_validation_type;
            }
            // Add edoc_id, if a Descriptive field has an attachement or video url
            if ($element_type == 'descriptive') {
                if (is_numeric($edoc_id)) {
                    $form_element["edoc_id"] = $edoc_id;
                    $form_element["edoc_display_img"] = $edoc_display_img;
                } elseif ($video_url != '') {
                    $form_element["video_url"] = $video_url;
                    $form_element["video_display_inline"] = $video_display_inline;
                }
            }
            // Using either Calculated Fields OR Branching Logic OR both
            $useBranch = (in_array($field_name, $branch_fields_this_form) || ($longitudinal && in_array("$this_unique_event.$field_name", $branch_fields_this_form)));
            $useCalc   = (in_array($field_name, $calc_fields_this_form)   || ($longitudinal && in_array("$this_unique_event.$field_name", $calc_fields_this_form)));
            if ($useCalc || $useBranch)
            {
                // Set string to run calculate() function: ALWAYS perform branching after calculation to catch any changes from calculation
                $calcFuncString = ($useCalc ? "calculate('$field_name');" : "");
                // Calc & Branching: Radios and checkboxes need to use onclick to work in some browsers
                if ($element_type == 'radio' || $element_type == 'yesno' || $element_type == 'truefalse') {
                    ## MC fields (excluding checkboxes)
                    // if radio button is part of a ranking matrix field, add js to rank, while still allowing to branch and calculate
                    $js = (!$grid_rank) ? "" :	"matrix_rank(this.value,'$field_name','" . implode(",", $Proj->matrixGroupNames[$grid_name]) . "');";
                    // Use different javascript for Randomization widget popup
                    $js .= (PAGE == 'Randomization/randomize_record.php') ? "document.forms['random_form'].$field_name.value=this.value;" : "document.forms['form'].$field_name.value=this.value;{$calcFuncString}doBranching('$field_name');";
                    $form_element["onclick"] = $js;
                } else {
                    ## All non-MC fields (including checkboxes)
                    // Use different javascript for Randomization widget popup
                    $js = (PAGE == 'Randomization/randomize_record.php') ? "" : "clean_datetime(this,'" . js_escape($element_validation_type) . "');{$calcFuncString}doBranching('$field_name');";
                    $form_element["onchange"] = $js;
                    // For Text and Notes fields, add onkeyup triggering for branching logic so that it gets triggered prior to leaving the field.
                    // The downside of having this is that if the value changes AND they tab out, branching gets called twice (small price to pay?).
                    if (($element_type == 'text' || $element_type == 'textarea') && PAGE != 'Randomization/randomize_record.php') {
                        $form_element["onkeydown"] = "if(event.keyCode==9){doBranching('$field_name');}";
                    }
                }
            }
            // Add onclick to all radios to change hidden input's value
            elseif ($element_type == 'radio' || $element_type == 'yesno' || $element_type == 'truefalse') {
                // if radio button is part of a ranking matrix field, add js to rank, while still allowing to branch and calculate
                $js = (!$grid_rank) ? "" :	"matrix_rank(this.value,'$field_name','" . implode(",", $Proj->matrixGroupNames[$grid_name]) . "');";
                // Use different javascript for Randomization widget popup
                $js .= (PAGE == 'Randomization/randomize_record.php') ? "document.forms['random_form'].$field_name.value=this.value;" : "document.forms['form'].$field_name.value=this.value;";
                $form_element["onclick"] = $js;
            }
            //For elements of type 'select', we need to include the $element_enum information
            if ($element_type == 'truefalse' || $element_type == 'yesno' || $element_type == 'select' || $element_type == 'radio' || $element_type == 'checkbox' || $element_type == 'sql')
            {
                //Add any checkbox fields to array to use during data pull later to fill form with existing data
                if ($element_type == 'checkbox') {
                    $chkbox_flds[$field_name] = "";
                }
                //Do normal select/radio options
                if ($element_type != 'sql') {
                    $form_element["enum"] =  $element_enum;
                //Do SQL field for dynamic select box (Must be "select" statement)
                } else {
                    $form_element["enum"] = str_replace(array("\"","'"), array("&quot;","&#39;"), getSqlFieldEnum($element_enum, PROJECT_ID, $_GET["id"], $_GET["event_id"], $_GET["instance"], null, null, $_GET["page"]));
                }
            }
            //If an element_note is specified, we'll utilize here:
            if ($element_note)
            {
                if (strpos($element_note, "'") !== false) $element_note = str_replace("'", "&#39;", $element_note); //Apostrophes cause issues when rendered, so replace with equivalent html character
                $form_element["note"] = $element_note;
            }
            $form_elements[] = $form_element;
        }
        return array($form_elements, array_unique($calc_fields_this_form), array_unique($branch_fields_this_form), $chkbox_flds);
    }

    // Check for REQUIRED FIELDS: First, check for any required fields that weren't entered (checkboxes are ignored - cannot be Required)
    // Return TRUE if clean, and return FALSE if a required field was left blank for surveys OR redirect back to form if not survey.
    public static function checkReqFields($fetched, $isSurveyPage=false, $reqmsg_maxlength = 1500)
    {
        global $Proj, $double_data_entry, $user_rights, $missingDataCodes;
        // Set array of submit-actions to ignore
        $ignoreSubmitActions = array('submit-btn-cancel', 'submit-btn-delete', 'submit-btn-deleteform', 'submit-btn-deleteevent');
        // Check required fields
        if (isset($_POST['submit-action']) && !in_array($_POST['submit-action'], $ignoreSubmitActions))
        {
            // Defaults
            $__reqmsg = '';
            // Capture any hidden required fields that have no value but were hidden via branching logic.
            // Return the hidden required fields in an array, and also auto-add the fields to POST.
            $emptyReqFields = DataEntry::addEmptyRequiredFieldsToPost();
            // Loop through each to check if required
            foreach ($Proj->forms[$_GET['page']]['fields'] as $this_field=>$this_label)
            {
                // Only check field's value if the field is required
                if ($Proj->metadata[$this_field]['field_req'] && !in_array($this_field, $emptyReqFields))
                {
                    // If this field has an @HIDDEN[-??] action tag, then skip
                    if (($isSurveyPage && Form::hasHiddenOrHiddenSurveyActionTag($Proj->metadata[$this_field]['misc']))
                        || (!$isSurveyPage && Form::hasHiddenOrHiddenFormActionTag($Proj->metadata[$this_field]['misc']))) {
                        continue;
                    }
                    // Set flag
                    $missingFieldValue = false;
                    // Do check for non-checkbox fields
                    if (isset($_POST[$this_field]) && !$Proj->isCheckbox($this_field) && $_POST[$this_field] == '')
                    {
                        $missingFieldValue = true;
                    }
                    // Do check for checkboxes, making sure at least one checkbox is checked
                    elseif ($Proj->isCheckbox($this_field) && !isset($_POST["__chkn__".$this_field]))
                    {
                        // Check if checkboxes are visible and if none are checked
                        $doReqChk = false;
                        $thisCheckboxEnum = parseEnum($Proj->metadata[$this_field]['element_enum']);
                        if (!empty($missingDataCodes)) {
                            $thisCheckboxEnum = $thisCheckboxEnum+$missingDataCodes;
                        }
                        foreach (array_keys($thisCheckboxEnum) as $key) {
                            if (isset($_POST["__chk__".$this_field."_RC_".DataEntry::replaceDotInCheckboxCodingReverse($key)])) {
                                $doReqChk = true;
                                break;
                            }
                        }
                        if ($doReqChk)
                        {
                            // Build temp array of checkbox-formatted variable names that is used on html form for this field (e.g., __chk__matrix_2_RC_6)
                            $numCheckBoxesChecked = 0;
                            foreach (array_keys($thisCheckboxEnum) as $key) {
                                $this_field_chkbox = "__chk__".$this_field."_RC_".DataEntry::replaceDotInCheckboxCodingReverse($key);
                                if (isset($_POST[$this_field_chkbox]) && $_POST[$this_field_chkbox] != '') {
                                    $numCheckBoxesChecked++;
                                }
                            }
                        }
                        // If zero boxes are checked for this checkbox
                        if ($doReqChk && $numCheckBoxesChecked == 0)
                        {
                            $missingFieldValue = true;
                        }
                    }
                    // If field's value is missing, add label to reqmsg to prompt
                    if ($missingFieldValue)
                    {
                        $__reqmsg .= $this_field . ",";
                    }
                }
            }
            // If some required fields weren't entered, save and return to page with user prompt
            if ($__reqmsg != '')
            {
                // Remove last comma
                $__reqmsg = $_GET['__reqmsgpre'] = substr($__reqmsg, 0, -1);
                // Get true new record name (puts record name in cache table to ensure it hasn't already been used)
                $newRecordOnForm = (!$isSurveyPage && $_POST['hidden_edit_flag'] == 0);
                $newRecordOnPublicSurvey = ($isSurveyPage && $GLOBALS['public_survey'] && !isset($_POST['__response_id__']));
                if (($newRecordOnForm || $newRecordOnPublicSurvey) && !isset($GLOBALS['__addNewRecordToCache'])) {
               		$_GET['id'] = $fetched = $_POST[$Proj->table_pk] = Records::addNewAutoIdRecordToCache(PROJECT_ID, $fetched);
                }
                // Perform server-side validation
                Form::serverSideValidation($_POST);
                // Save data (but NOT if previewing a survey)
                list ($fetched, $context_msg, $log_event_id, $dataValuesModified, $dataValuesModifiedIncludingCalcs) = DataEntry::saveRecord($fetched, true, true, false, (isset($_POST['__response_id__']) && $isSurveyPage ? $_POST['__response_id__'] : null), true);
                // Set first submit time if null
                if ($isSurveyPage && isset($_POST['__response_id__'])) {
                    // Set first_submit_time in response table, if null
                    $sql = "update redcap_surveys_response set first_submit_time = '".NOW."'
                            where response_id = {$_POST['__response_id__']} and first_submit_time is null";
                    $q = db_query($sql);
                }
                // To prevent having a URL length overflow issue, truncate string after set limit
                if (strlen($__reqmsg) > $reqmsg_maxlength) {
                    $__reqmsg = substr($__reqmsg, 0, strpos($__reqmsg, ",", $reqmsg_maxlength)) . ",[more]";
                }
                // For surveys, don't redirect (because we'll lose our session) but merely set $_GET variable (to be utilized at bottom of page)
                // Don't enforce for surveys if going backward to previous page.
                if ($isSurveyPage && !isset($_GET['__prevpage'])) {
                    // Set required field query string param
                    $_GET['__reqmsg'] = urlencode(strip_tags($__reqmsg));
                    // If server-side validation was violated, then add to redirect URL
                    if (isset($_SESSION['serverSideValErrors'])) {
                        // Build query string parameter
                        $_GET['serverside_error_fields'] = implode(",", array_keys($_SESSION['serverSideValErrors']));
                        // Remove from session
                        unset($_SESSION['serverSideValErrors']);
                    }
                    // MAXCHOICE ACTION TAG CATCHING
                    // If server-side validation was violated, then add to redirect URL
                    if (isset($_GET['maxChoiceFieldsReached'])) {
                        $_GET['maxchoice_error_fields'] = implode(",", $_GET['maxChoiceFieldsReached']);
                        unset($_GET['maxChoiceFieldsReached']);
                    }
                    return $fetched;
                }
                // Redirect with '__reqmsg' URL variable (and accomodate DDE persons, if applicable)
                elseif (!$isSurveyPage) {
                    // Set URL to be redirected to

                    $fetchedEncoded = rawurlencode(label_decode($fetched));
                    $url = PAGE_FULL . "?pid=" . PROJECT_ID . "&page=" . $_GET['page']

                         . "&id=" . (($double_data_entry && $user_rights['double_data'] != 0) ? substr($fetchedEncoded, 0, -3) : $fetchedEncoded)
                         . "&event_id={$_GET['event_id']}&instance={$_GET['instance']}&__reqmsg=" . urlencode(strip_tags($__reqmsg));
                    // SET UP DATA QUALITY RUNS TO RUN IN REAL TIME WITH ANY DATA CHANGES ON FORM
                    $dq = new DataQuality();
                    // Check for any errors and return array of DQ rule_id's for those rules that were violated
                    $dq_repeat_instrument = $Proj->isRepeatingForm($_GET['event_id'], $_GET['page']) ? $_GET['page'] : "";
                    $dq_repeat_instance = ($Proj->isRepeatingEvent($_GET['event_id']) || $Proj->isRepeatingForm($_GET['event_id'], $_GET['page'])) ? $_GET['instance'] : 0;
                    list ($dq_errors, $dq_errors_excluded) = $dq->checkViolationsSingleRecord($fetched, $_GET['event_id'], $_GET['page'], array(), $dq_repeat_instance, $dq_repeat_instrument);
                    // If rules were violated, reload page and then display pop-up message about discrepancies
                    if (!empty($dq_errors)) {
                        // Build query string parameter
                        $url .= '&dq_error_ruleids=' . implode(",", array_merge($dq_errors, $dq_errors_excluded));
                    }
                    // SET SERVER-SIDE VALIDATION CATCHING
                    // If server-side validation was violated, then add to redirect URL
                    if (isset($_SESSION['serverSideValErrors'])) {
                        // Build query string parameter
                        $url .= '&serverside_error_fields=' . implode(",", array_keys($_SESSION['serverSideValErrors']));
                        // Remove from session
                        unset($_SESSION['serverSideValErrors']);
                    }
                    // MAXCHOICE ACTION TAG CATCHING
                    // If server-side validation was violated, then add to redirect URL
                    if (isset($_GET['maxChoiceFieldsReached'])) {
                        // Build query string parameter
                        $url .= '&maxchoice_error_fields=' . implode(",", $_GET['maxChoiceFieldsReached']);
                        // Remove from get
                        unset($_GET['maxChoiceFieldsReached']);
                    }
                    // Finally redirect
                    redirect($url);
                }
            }
        }
        return $fetched;
    }

    // REQUIRED FIELDS pop-up message (URL variable 'msg' has been passed)
    public static function msgReqFields($fetched, $last_form='', $isSurveyPage=false)
    {
        global $Proj, $lang, $double_data_entry, $user_rights, $multiple_arms, $longitudinal;
        if (isset($_GET['__reqmsg']) && trim($_GET['__reqmsg']) != '')
        {
            $_GET['__reqmsg'] = explode(",", strip_tags(urldecode($_GET['__reqmsg'])));
            //Render javascript for pop-up
            print  "<div id='reqPopup' title='".js_escape("{$lang['global_02']}{$lang['colon']} {$lang['data_entry_71']}")."' style='display:none;text-align:left;'>
                        {$lang['data_entry_72']}<br/><br/>
                        {$lang['data_entry_73']}<br/>
                        <div style='font-size:11px;font-family:tahoma,arial;font-weight:bold;padding:3px 0;'>";
            foreach ($_GET['__reqmsg'] as $this_req)
            {
                $this_req = trim($this_req);
                if ($this_req == '') continue;
                if ($this_req == '[more]') {
                    $this_label = $this_req;
                } else {
                    $this_label = filter_tags(label_decode($Proj->metadata[$this_req]['element_label']));
                    // Perform piping
                    $this_label = Piping::replaceVariablesInLabel($this_label, addDDEending($_GET['id']), $_GET['event_id'], $_GET['instance'], array(), true, null, false, "", 1, false, false, $_GET['page']);
                }
                print "<div style='margin-left: 1.5em;text-indent: -1em;'> &bull; ".$this_label."</div>";
            }
            print  "</div>";
            print  "</div>";
            ?>
            <script type='text/javascript'>
            $(function(){
                setTimeout(function(){
                    // REQUIRED FIELDS POP-UP DIALOG
                    $('#reqPopup').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 570), open: function(){fitDialog(this)},
                    <?php echo (count($_GET['__reqmsg']) > 10 ? "height: 600,": "") ?>
                    buttons: {
                        <?php
                        // Don't show all buttons on survey page
                        if (!$isSurveyPage) {
                            // If user is on last form, don't show the button "Ignore and go to Next Form"
                            if ($_GET['page'] != $last_form && !empty($last_form))
                            {
                                // Show button "ignore and go to next form"
                                $nextForm = DataEntry::getNextForm($_GET['page'], $_GET['event_id']);
                                // If this is a repeating instrument, then make sure the Go To Next Form button goes to instance 1 of the next form
                                $nextFormInstance = $Proj->isRepeatingEvent($_GET['event_id']) ? $_GET['instance'] : '1';
                                print "'" . js_escape($lang['data_entry_74']) . "': function(){ window.location.href='".PAGE_FULL."?pid=".PROJECT_ID."&instance=$nextFormInstance&page=$nextForm&id=".htmlspecialchars((($double_data_entry && $user_rights['double_data'] != 0) ? substr($fetched, 0, -3) : $fetched), ENT_QUOTES)."&event_id={$_GET['event_id']}'; },";
                            }
                            // Show button "ignore and leave record"
                            print "'" . js_escape($lang['data_entry_76']) . "': function(){ window.location.href=app_path_webroot+'"
                                . ($longitudinal ? 'DataEntry/record_home.php' : 'DataEntry/index.php')
                                . "?pid=" . PROJECT_ID
                                . (!$longitudinal ? "&page={$_GET['page']}" : "&id=".htmlspecialchars((($double_data_entry && $user_rights['double_data'] != 0) ? substr($fetched, 0, -3) : $fetched), ENT_QUOTES))
                                . ($multiple_arms ? "&arm=".getArm() : "")
                                . "'; },";
                        }
                        ?>
                        '<?=js_escape($lang['design_401'])?>': function() { $(this).dialog('close'); }
                    } });
                },(isMobileDevice ? 1500 : 0));
            });
            </script>
            <?php
        }
    }

    // Determine if another user is on this form for this record for this project (do not allow the page to load, if so).
    // Returns the username of the user already on the form.
    public static function checkSimultaneousUsers()
    {
        global $autologout_timer, $hidden_edit, $auto_inc_set, $double_data_entry, $user_rights;
        // Need to use autologout timer value to determine span of time to evaluate
        if (empty($autologout_timer) || $autologout_timer == 0 || !is_numeric($autologout_timer)) return false;
        // Ignore if project uses auto-numbering and the user is on an uncreated record (i.e. $hidden_edit=0 on first form)
        if ($hidden_edit === 0 && $auto_inc_set) return false;
        // If for some reason there is no session, then assume the other user won't have a session, which negates checking here.
        if (!session_id()) return false;
        // If user has form "read-only" privileges, then allow them access to this form if someone else is already on it.
        if ($user_rights['forms'][$_GET['page']] == '2') return false;
        // Check sessions table using log_view table session_id values
        if ((PAGE == "DataEntry/index.php" || PAGE == "ProjectGeneral/keep_alive.php") && isset($_GET['page']) && isset($_GET['id']) && isset($_GET['event_id']))
        {
            // Set window of time after which the user should have been logged out (based on system-wide parameter)
            $bufferTime = 3; // X minutes of buffer time (2 minute auto-logout warning + 1 minute buffer for lag, slow page load, etc.)
            $logoutWindow = date("Y-m-d H:i:s", mktime(date("H"),date("i")-(Authentication::AUTO_LOGOUT_RESET_TIME+$bufferTime),date("s"),date("m"),date("d"),date("Y")));
            // Ignore users sitting on page for uncreated records when auto-numbering is enabled
            $ignoreUncreatedAutoId = ($auto_inc_set ? "and a.full_url not like '%&auto%'" : "");
            // Set record (account for DDE)
            $record = $_GET['id'] . (($double_data_entry && $user_rights['double_data'] != '0') ? '--'.$user_rights['double_data'] : '');
            // For better performance of the big query below, first get minimum log_view_id
            $sql = "select min(log_view_id) from redcap_log_view where ts >= '$logoutWindow'";
            $q = db_query($sql);
            $logoutWindowLogViewId = db_result($q, 0);
            if ($logoutWindowLogViewId == '') $logoutWindowLogViewId = '0';
            // Check latest log_view listing in past [MaxLogoutTime] minutes for this form/record (for users other than current user, ignore super users)
            $sql = "select a.session_id, a.user
                    from redcap_log_view a
                    inner join redcap_user_rights u ON a.user = u.username AND u.project_id = a.project_id
                    inner join redcap_user_information i ON i.username = u.username
                    left join redcap_user_roles ur ON u.role_id = ur.role_id
                    where a.project_id = " . PROJECT_ID . "
                    and a.log_view_id >= $logoutWindowLogViewId
                    and a.user != '" . db_escape(USERID) . "'
                    and a.event_id = {$_GET['event_id']}
                    and a.record = '".db_escape($record)."'
                    and a.form_name = '" . db_escape($_GET['page']) . "'
                    and a.page in ('DataEntry/index.php', 'ProjectGeneral/keep_alive.php', 'DataEntry/check_unique_ajax.php', 
                                   'DataEntry/file_download.php', 'DataEntry/file_upload.php', 'DataEntry/file_delete.php')
                    and (((u.data_entry like '%[{$_GET['page']},1]%' OR u.data_entry like '%[{$_GET['page']},3]%') AND ur.role_id IS NULL) 
                        OR ((ur.data_entry LIKE '%[{$_GET['page']},1]%' OR ur.data_entry LIKE '%[{$_GET['page']},3]%') AND ur.role_id IS NOT NULL)
                        OR i.super_user = 1)
                    and a.log_view_id = (select b.log_view_id from redcap_log_view b where b.user = a.user order by b.log_view_id desc limit 1)
                    $ignoreUncreatedAutoId
                    order by a.log_view_id desc limit 1";
            $q = db_query($sql);
            if (db_num_rows($q) > 0)
            {
                // Now use the session_id from log_view table to see if they're still logged in (check sessions table)
                $session_id = db_result($q, 0, "session_id");
                $other_user = db_result($q, 0, "user");
                $sql = "select 1 from redcap_sessions where session_id = '$session_id' and session_expiration >= '$logoutWindow' limit 1";
                $q = db_query($sql);
                if (db_num_rows($q) > 0)
                {
                    ## We have 2 users on same form/record. Prevent loading of page.
                    // First remove the new row just made in log_view table (otherwise, can simply refresh page to gain access)
                    $sql = "update redcap_log_view 
                            set record = null, miscellaneous = 'record = \'{$record}\'\\n// Simultaneous user detected on form' where project_id = " . PROJECT_ID . "
                            and event_id = {$_GET['event_id']} and form_name = '" . db_escape($_GET['page']) . "' and user = '" . USERID . "' 
                            and page in ('DataEntry/index.php', 'ProjectGeneral/keep_alive.php', 'DataEntry/check_unique_ajax.php', 
                                         'DataEntry/file_download.php', 'DataEntry/file_upload.php', 'DataEntry/file_delete.php')
                            order by log_view_id desc limit 1";
                    $q = db_query($sql);
                    // Return the username of the user already on the form
                    return $other_user;
                }
            }
        }
        return false;
    }

    // Initialize and render the "file" field type pop-up box (initially hidden)
    public static function initFileUploadPopup()
    {
        global $lang, $isMobileDevice;
        // Is this form being displayed as a survey?
        $isSurveyPage = ((isset($_GET['s']) && !empty($_GET['s']) && PAGE == "surveys/index.php" && defined("NOAUTH")) || PAGE == "Surveys/preview.php");
        // SURVEYS: Use the surveys/index.php page as a pass through for certain files (file uploads/downloads, etc.)
        if ($isSurveyPage)
        {
            $file_upload_page = APP_PATH_SURVEY . "index.php?pid=" . PROJECT_ID . "&__passthru=".urlencode("DataEntry/file_upload.php");
            $file_empty_page  = APP_PATH_SURVEY . "index.php?pid=" . PROJECT_ID . "&__passthru=".urlencode("DataEntry/empty.php") . '&s=' . $_GET['s'];
        }
        else
        {
            $file_upload_page = APP_PATH_WEBROOT . "DataEntry/file_upload.php?pid=" . PROJECT_ID . "&page=" . $_GET['page'];
            $file_empty_page  = APP_PATH_WEBROOT . "DataEntry/empty.php?pid=" . PROJECT_ID;
        }
        // Set the form action URL (must be customized in case we're on survey page, which has no authentication)
        $formAction = $file_upload_page.'&id='.rawurlencode($_GET['id']).'&event_id='.$_GET['event_id'].'&instance='.$_GET['instance'];
        if ($isSurveyPage) $formAction .= '&s='.$_GET['s'];
        // Set up file_upload pop-up html
        $f1_upload_form =  '<div style="color:#800000;margin:20px 0;font-size:14px;">'.$lang['data_entry_62'].'</div>
                            <div style="margin:15px 0;">
                                <input name="myfile" type="file" size="40">
                                <input name="myfile_base64" type="hidden">
                                <input name="myfile_base64_edited" type="hidden" value="0">
                                <input name="myfile_replace" type="hidden" value="0">
                            </div>
                            <button class="btn btn-primaryrc btn-fileupload" style="font-size:14px;" onclick="uploadFilePreProcess();return false;"><i class="fas fa-upload"></i> '.$lang['form_renderer_23'].'</button>
                            <span style="margin-left:10px;color:#888;">('.$lang['data_entry_63'].' '.maxUploadSizeEdoc().' MB)</span>';
        $file_upload_win = '<form autocomplete="new-password" id="form_file_upload" action="'.$formAction.'" method="post" enctype="multipart/form-data" target="upload_target" onsubmit="return startUpload();" >
                                <div id="this_upload_field">
                                    <div style="font-size:14px;font-weight:bold;" id="field_name_popup">'.$lang['data_entry_64'].'</div>
                                </div>
                                <div id="signature-div" onchange=\'if($(this).jSignature("getData","base30")[1].length){ $("#f1_upload_form input[name=myfile_base64_edited]").val("1"); }\'></div>
                                <div id="signature-div-actions" style="padding-top:25px;">
                                    <button class="btn btn-primaryrc btn-fileupload" style="font-size:14px;" onclick="saveSignature();return false;">'.$lang['form_renderer_30'].'</button>
                                    <a href="javascript:;" style="margin-left:15px;text-decoration:underline;" onclick=\'$("#signature-div").jSignature("reset");$("#f1_upload_form input[name=myfile_base64_edited]").val("0");return false;\'>'.$lang['form_renderer_20'].'</a>
                                </div>
                                <div id="f1_upload_process" style="display:none;font-weight:bold;font-size:14px;text-align:center;">
    
                                    <br>'.$lang['data_entry_65'].'<br><img src="'.APP_PATH_IMAGES.'loader.gif" alt="'.js_escape2($lang['data_entry_65']).'">
                                </div>
                                <div id="f1_upload_form">'.$f1_upload_form.'</div>
                                <input type="hidden" id="field_name" name="field_name" value="">
                                '.($isSurveyPage ? '' : '<input type="hidden" name="redcap_csrf_token" value="'.System::getCsrfToken().'">').'
                                <iframe id="upload_target" name="upload_target" src="'.$file_empty_page.'" style="width:0;height:0;border:0px solid #fff;"></iframe>
                            </form>';
        ?>
        <!-- Password+File Upload Vault dialog -->
        <?php if (Files::fileUploadPasswordVerifyExternalStorageEnabledProject(PROJECT_ID)) { ?>
            <script type='text/javascript'>
            var lang_cancel = '<?php echo js_escape($lang['global_53']) ?>';
            var lang_confirm = '<?php echo js_escape($lang['design_654']) ?>';
            </script>
            <div id="file_upload_vault_popup" title="<?=js_escape2(($isSurveyPage ? $lang['data_entry_455'] : $lang['data_entry_453']))?>" class="simpleDialog fs14" style="line-height:1.5;background-color:#e1eee1;">
                <div class="mt-1 mb-3">
                    <?php print ($isSurveyPage ? $lang['data_entry_454'] : $lang['data_entry_460']) ?>
                    "<b id="file_upload_vault_popup_text1" style="color:#006000;"></b>"<?php print $lang['period'] ?>
                </div>
                <?php if (!$isSurveyPage) { ?>
                    <div class="clearfix my-1">
                        <div style="float:left;display:block;margin-left:50px;width:100px;font-weight:bold;"><?php print $lang['global_11'] . $lang['colon'] ?></div>
                        <div style="float:left;display:block;">
                            <input type="text" id="file_upload_vault_username" autocomplete="new-password" class="x-form-text x-form-field" value="<?=USERID?>" readonly style="background:#ddd;">
                        </div>
                    </div>
                    <div class="clearfix my-1">
                        <div style="float:left;display:block;margin-left:50px;width:100px;font-weight:bold;"><?php print $lang['global_32'] . $lang['colon'] ?></div>
                        <div style="float:left;display:block;">
                            <input type="password" id="file_upload_vault_password" autocomplete="new-password" class="x-form-text x-form-field">
                        </div>
                    </div>
                <?php } ?>
                <div class="mt-3"><?php print $lang['data_entry_461'] ?></div>
            </div>
        <?php } ?>
        <!-- Edoc file upload dialog pop-up divs and javascript -->
        <div id="file_upload" class="simpleDialog" title="<?php echo js_escape2($lang['form_renderer_23']) ?>"></div>
        <div id="fade" class="black_overlay"></div>
        <script type='text/javascript'>
        // Set html for file upload pop-up (for resetting purposes)
        var file_upload_win = '<?php echo js_escape($file_upload_win) ?>';
        var f1_upload_form = '<?php echo js_escape($f1_upload_form) ?>';
        // Language for file upload dialog title
        var lang_file_upload_title1 = '<?php echo js_escape($lang['form_renderer_23']) ?>';
        var lang_file_upload_title2 = '<?php echo js_escape($lang['form_renderer_31']) ?>';
        var lang_file_upload_title3 = '<?php echo js_escape($lang['data_entry_459'].$lang['color'].' '.$lang['data_entry_468']) ?>';
        </script>
        <script type="text/javascript" src="<?php print APP_PATH_JS?>Libraries/jSignature.js"></script>
        <script type="text/javascript" src="<?php print APP_PATH_JS?>Libraries/jSignature.SignHere.js"></script>
        <?php
    }

    // For metadata labels, clean the string of anything that would cause the page to break
    public static function cleanLabel($string)
    {
        // Apostrophes cause issues when rendered, so replace with equivalent html character
        if (strpos($string, "'") !== false) $string = str_replace("'", "&#39;", $string);
        // Backslashes at the beginning or end of the string will crash in the eval, so pad with a space if that occurs
        if (substr($string, 0, 1) == '\\') $string  = ' ' . $string;
        if (substr($string, -1)   == '\\') $string .= ' ';
        // Return cleaned string
        return $string;
    }

    // CALC FIELDS AND BRANCHING LOGIC: Add fields from REPEATING INSTANCES as hidden fields if involved in calc/branching on this form
    // Returns JavaScript to output repeating values onto the HTML form
    public static function addHiddenFieldsRepeatingInstances()
    {
        global $Proj, $repeatingFieldsEventInfo;
        if (empty($repeatingFieldsEventInfo)) return "";
        // Gather fields for getData
        $fields = array($Proj->table_pk);
        foreach ($repeatingFieldsEventInfo as $attr1) {
            foreach ($attr1 as $attr2) {
                foreach ($attr2 as $attr3) {
                    foreach (array_keys($attr3) as $field) {
                        $fields[] = $field;
                        // Also add the form status field for checkboxes that are not checked
                        $fields[] = $Proj->metadata[$field]['form_name']."_complete";
                    }
                }
            }
        }
        $fields = array_unique($fields);
        // Get data
        $data = Records::getData($Proj->project_id, 'array', $_GET['id'], $fields, array_keys($repeatingFieldsEventInfo));
        // Build input elements as HTML
        $inputs = "";
        foreach ($repeatingFieldsEventInfo as $event_id=>$attr1) {
            // Don't create extra input if it's on another event_id (this will be done via Form::addHiddenFieldsOtherEvents())
            if ($event_id != $_GET['event_id']) continue;
            foreach ($attr1 as $repeat_instrument=>$attr2) {
                foreach ($attr2 as $instance=>$attr3) {
                    foreach (array_keys($attr3) as $field) {
                        $value = isset($data[$_GET['id']]['repeat_instances'][$event_id][$repeat_instrument][$instance][$field]) ? $data[$_GET['id']]['repeat_instances'][$event_id][$repeat_instrument][$instance][$field] : "";
                        $valueIsArray = is_array($value);
                        if ($valueIsArray || $Proj->isCheckbox($field)) {
                            // If checkbox is somehow missing values (referencing an instance that doesn't exist yet), then add defaults
                            if (!$valueIsArray) {
                                $value = array_fill_keys(array_keys(parseEnum($Proj->metadata[$field]['element_enum'])), '0');
                            }
                            // Checkbox values
                            foreach ($value as $this_code=>$this_checked) {
                                // Create HTML input
                                $inputs .= "<input type='hidden' name='__chk__{$field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."____I{$instance}' value='".($this_checked ? $this_code : "")."'>\n";
                            }
                        } else {
                            // If this is really a date[time][_seconds] field that is hidden, then make sure we reformat the date for display on the page
                            if ($Proj->metadata[$field]['element_type'] == 'text')
                            {
                                if (substr($Proj->metadata[$field]['element_validation_type'], -4) == '_mdy') {
                                    list ($this_date, $this_time) = explode(" ", $value);
                                    $value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
                                } elseif (substr($Proj->metadata[$field]['element_validation_type'], -4) == '_dmy') {
                                    list ($this_date, $this_time) = explode(" ", $value);
                                    $value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
                                }
                            }
                            // Create HTML input
                            $inputs .= "<input type='hidden' name='{$field}____I{$instance}' value='".str_replace("'","&#039;",$value)."'>\n";
                        }
                    }
                }
            }
        }
        if ($inputs != "") $inputs = "\n\n<!-- Append any repeating instance fields to form -->\n" . $inputs . "\n";
        return $inputs;
    }

    // CALC FIELDS AND BRANCHING LOGIC: Add fields from other forms as hidden fields if involved in calc/branching on this form
    public static function addHiddenFieldsOtherForms($current_form, $calc_branch_fields_all_forms)
    {
        global $table_pk, $Proj;
        // Add fields to elements array
        $elements = array();
        $chkbox_flds = array();
        $js = "<script type='text/javascript'>";
        // Remove event prefix (if any are using cross-event logic)
        foreach ($calc_branch_fields_all_forms as $key=>$value) {
            $dot_pos  = strpos($value, ".");
            if ($dot_pos !== false) {
                $calc_branch_fields_all_forms[$key] = substr($value, $dot_pos+1);
            }
        }
        $calc_branch_fields_all_forms = array_unique($calc_branch_fields_all_forms);
        foreach ($calc_branch_fields_all_forms as $this_field)
        {
            $rowq = $Proj->metadata[$this_field];
            if ($rowq['form_name'] == $current_form || $this_field == $table_pk) continue;
            // If a checkbox AND we've not already added it
            if ($rowq['element_type'] == "checkbox" && !isset($chkbox_flds[$rowq['field_name']]))
            {
                // Add as official checkbox field on this form (will be displayed as table row, but will hide later using javascript)
                $elements[] = array('rr_type'=>'checkbox', 'field'=>$rowq['field_name'], 'label'=>'Label', 'enum'=>$rowq['element_enum'],
                                    'name'=>$rowq['field_name']);
                $chkbox_flds[$rowq['field_name']] = "";
                // Run javascript when page finishes loading to hide the row (since we cannot easily use hidden fields for invisible checkboxes
                $js .= "document.getElementById('{$rowq['field_name']}-tr').style.display='none';";
            }
            else
            {
                // Add field and its value as hidden field
                $elements[] = array('rr_type'=>'hidden', 'field'=>$rowq['field_name'], 'name'=>$rowq['field_name']);
            }
        }
        $js .= "</script>";
        // Return elements array
        return array($elements, $chkbox_flds, $js);
    }

    // Parse the stop_actions column into an array
    public static function parseStopActions($string)
    {
        // Explode into array, where strings should be delimited with pipe |
        $codes = array();
        if (strpos($string, ",") !== false)
        {
            foreach (explode(",", $string) as $code)
            {
                $codes[] = trim($code);
            }
        }
        elseif ($string != "")
        {
            $codes[] = trim($string);
        }
        return $codes;
    }

    // Render javascript to enable Stop Actions on a survey
    public static function enableStopActions()
    {
        global $Proj;
        // Begin rendering javascript
        print "<script type='text/javascript'>\n\$(function(){";
        // Loop through all fields
        foreach ($Proj->metadata as $this_field=>$attr)
        {
            // Ignore fields without stop actions
            if ($attr['stop_actions'] == "") continue;
            // Parse this field's stop actions
            $stop_actions = DataEntry::parseStopActions($attr['stop_actions']);
            // Enable for Radio buttons, YesNo, and TrueFalse
            if (in_array($attr['element_type'], array('radio','yesno','truefalse')))
            {
                print  "\n\$('#form :input[name=\"{$this_field}___radio\"]').each(function(){"
                    .		"if(in_array(\$(this).val(),['".implode("','", $stop_actions)."'])){\$(this).click(function(){triggerStopAction(\$(this));});}"
                    .  "});";
            }
            // Enable for Checkboxes
            elseif ($attr['element_type'] == 'checkbox')
            {
                print  "\n\$('#form :input[name=\"__chkn__{$this_field}\"]').each(function(){"
                    .  		"if(in_array(\$(this).attr('code'),['".implode("','", $stop_actions)."'])){\$(this).click(function(){triggerStopAction(\$(this));});}"
                    .  "});";
            }
            // Enable for Drop-downs
            elseif ($attr['element_type'] == 'select')
            {
                print  "\n\$('#form select[name=\"{$this_field}\"]').change(function(){"
                    .		"if(in_array(\$(this).val(),['".implode("','", $stop_actions)."'])){triggerStopAction(\$(this));}"
                    .  "});";
            }
        }
        print "\n});\n</script>";
    }

    // Make sure that there is a case sensitivity issue with the record name. Check value of id in URL with back-end value.
    // If doesn't match back-end case, then reload page using back-end case in URL.
    public static function checkRecordNameCaseSensitive()
    {
        global $double_data_entry, $user_rights;
        // Set record (account for DDE)
        $record = "" . $_GET['id'] . (($double_data_entry && $user_rights['double_data'] != '0') ? '--'.$user_rights['double_data'] : '');
        // Compare with back-end value, if exists
        $backEndRecordName = Records::checkRecordNameCaseSensitive($record);
        if ($record !== $backEndRecordName)
        {
            // They don't match, so reload page using back-end value
            redirect(PAGE_FULL . "?pid=" . PROJECT_ID . "&page={$_GET['page']}&event_id={$_GET['event_id']}&id=$backEndRecordName" . (isset($_GET['auto']) ? "&auto=1" : ""));
        }
    }

    // Display search utility on data entry page
    public static function renderSearchUtility()
    {
        global $lang, $Proj, $longitudinal, $user_rights;
        // Build the options for the field drop-down list
        $field_dropdown = "";
        $exclude_fieldtypes = array("file", "descriptive", "checkbox", "dropdown", "select", "radio", "yesno", "truefalse");
        foreach ($Proj->metadata as $row)
        {
            // Do not include certain field types
            if (in_array($row['element_type'], $exclude_fieldtypes)) continue;
            // Do not include fields from forms the user does not have access to
            if ($user_rights['forms'][$row['form_name']] == '0' && $row['field_name'] != $Proj->table_pk) continue;
            // Build list option
            $this_select_dispval = $row['field_name']." (".strip_tags(label_decode($row['element_label'])).")";
            $maxlength = 70;
            if (strlen($this_select_dispval) > $maxlength) {
                $this_select_dispval = substr($this_select_dispval, 0, $maxlength-2) . "...)";
            }
            $field_dropdown .= "<option value='{$row['field_name']}'>$this_select_dispval</option>";
        }
        // Get record count
        $recordCount = Records::getRecordCount($Proj->project_id);
        $maxRecordLimitForSearchAll = 20000;
        // Disply html table of search utility
        ?>
        <div style="max-width:700px;margin:40px 0 0;">
            <table role="presentation" class="form_border" width=100%>
                <tr>
                    <td class="header" colspan="2" style="font-weight:normal;padding:10px 5px;color:#800000;font-size:13px;">
                        <?php echo $lang['data_entry_138'] ?>
                    </td>
                </tr>
                <tr>
                    <td class="labelrc" style="width:275px;padding:10px 8px;">
                        <?php echo $lang['data_entry_139'] ?>
                        <div style="font-size:10px;font-weight:normal;color:#555;"><?php echo $lang['data_entry_141'] ?></div>
                    </td>
                    <td class="data" style="padding:10px 8px;">
                        <select role="listbox" id="field_select" class="x-form-text x-form-field" style="max-width:300px;">
                            <?php
                            if ($recordCount <= $maxRecordLimitForSearchAll) {
                                ?><option value=""><?php echo $lang['dataqueries_183'] ?></option><?php
                            } else {
                                ?><optgroup label='<?php echo RCView::escape($lang['data_entry_471']." ".number_format($maxRecordLimitForSearchAll)." ".$lang['data_entry_173']) ?>'></optgroup><?php
                            }
                            echo $field_dropdown;
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="labelrc" style="width:275px;padding:10px 8px;">
                        <?php echo $lang['data_entry_142'] ?>
                        <div style="padding-top:4px;font-size:10px;font-weight:normal;color:#555;"><?php echo $lang['data_entry_143'] ?></div>
                    </td>
                    <td class="data" style="padding:10px 8px;">
                        <input type="text" id="search_query" size="30" class="x-form-text x-form-field" autocomplete="new-password">
                        <span id="search_progress" style="padding-left:10px;display:none;">
                            <img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif" style="vertical-align:middle;" alt="<?php echo js_escape2($lang['scheduling_20']) ?>">
                            <?php echo $lang['data_entry_145'] ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <script type="text/javascript">
        // Show the searching progress icon
        function showSearchProgress(show) {
            var repeat = false;
            if (show == 1) {
                $('#search_progress').show();
                repeat = true;
            } else {
                var searchIconVisible = $('#search_progress').is(':visible');
                if (searchIconVisible && ($('ul.ui-autocomplete:last').is(':visible') || $('#search_query').val().length < 1) ){
                    $('#search_progress').fadeOut('fast');
                } else if (searchIconVisible) {
                    repeat = true;
                }
            }
            if (repeat) {
                // Check every 0.5 seconds until the div appears
                setTimeout("showSearchProgress(0)", 500);
            }
        }
        // Initialize Data Search object variable
        var search;
        // Set Data Search autocomplete trigger
        function enableDataSearchAutocomplete(field,arm) {
            search = null;
            search = 	$('#search_query').autocomplete({
                            source: app_path_webroot+'DataEntry/search.php?field='+field+'&pid='+pid+'&arm='+arm,
                            minLength: 1,
                            delay: 50,
                            select: function( event, ui ) {
                                // Reset value in textbox
                                $('#search_query').val('');
                                // Get record and event_id values and redirect to form
                                var data_arr = ui.item.value.split('|',4);
                                window.location.href = app_path_webroot+'DataEntry/index.php?pid='+pid+'&page='+data_arr[1]+'&event_id='+data_arr[2]+'&id='+data_arr[3]+'&instance='+data_arr[0];
                                return false;
                            },
                            focus: function (event, ui) {
                                // Reset value in textbox
                                $('#search_query').val('');
                                return false;
                            },
                            response: function (event, ui) {
                                // When it opens, hide the progress icon/text
                                $('#search_progress').fadeOut('fast');
                            }
                        })
                        .data('ui-autocomplete')._renderItem = function( ul, item ) {
                            return $("<li></li>")
                                .data("item", item)
                                .append("<a>"+item.label+"</a>")
                                .appendTo(ul);
                        };
        }
        $(function(){
            // Enable searching via auto complete
            enableDataSearchAutocomplete($('#field_select option:first').val(),'<?php echo getArm() ?>');
            // If user selects new field for Data Search, set search query input to blank
            $('#field_select').change(function(){
                // Reset query text
                $('#search_query').val('');
                // Enable searching via auto complete
                enableDataSearchAutocomplete($(this).val(),'<?php echo getArm() ?>');
            });
            // Make progress gif appear when loading new results
            $('#search_query').keydown(function(e){
                if (e.which == 40) return; // If down arrow is clicked, then stop
                $('ul.ui-autocomplete:last').hide();
                $('ul.ui-autocomplete:last li').each(function(){
                    $(this).remove();
                });
                showSearchProgress(1);
            });
        });
        </script>
        <?php
    }

    // If downloading/deleting file from a survey, double check to make sure that only the respondent who uploaded the file has rights to it
    public static function checkSurveyFileRights()
    {
        global $lang;
        // We can only do the check if we have certain parameters
        if (isset($_GET['s']) && !empty($_GET['s']) && isset($_GET['field_name']) && isset($_GET['record']) && isset($_GET['event_id']) && is_numeric($_GET['event_id']))
        {
            // If record is empty or don't have a response_id in session yet, then give note that cannot yield
            // the file until the record has been saved (security reasons).
            if ($_GET['record'] == "" || !isset($_GET['__response_hash__']) || (isset($_GET['__response_hash__']) && empty($_GET['__response_hash__'])))
            {
                // Make sure record exists. If it does, give notice that record must be saved/created first in order to download/delete the file
                if ($_GET['record'] != "" && !Records::recordExists(PROJECT_ID, $_GET['record'])) return;
                // Make sure we have a non-blank response hash
                if (isset($_GET['__response_hash__']) && !empty($_GET['__response_hash__'])) return;
                // Record exists, but we don't have a response_id (i.e. we're on first page of survey), so page must be saved first
                $HtmlPage = new HtmlPage();
                $HtmlPage->PrintHeaderExt();
                print "<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['survey_217']}";
                $HtmlPage->PrintFooterExt();
                exit;
            }
            //Cross reference the doc_id, project_id, field_name, and record to ensure they all match up for this
            $isRecordValue = DataEntry::isRecordValue();
            // If we're deleting a file for an existing record, but the edoc_id itself has not been saved
            // into redcap_data yet, then all is fine, so return.
            if (!$isRecordValue && strpos($_GET['__passthru'], 'file_delete.php') !== false) return;
            // Also cross reference the survey hash, response_id, and record number in the surveys tables
            $sql = "select participant_id from redcap_surveys_participants where hash = '".db_escape($_GET['s'])."' limit 1";
            $q = db_query($sql);
            $participant_id = db_result($q, 0);
            $response_id = Survey::decryptResponseHash($_GET['__response_hash__'], $participant_id);
            $rsql = ($response_id === false) ? "" : "and r.response_id = '$response_id'";
            $sql = "select 1 from redcap_surveys_participants p, redcap_surveys_response r where p.hash = '{$_GET['s']}'
                    and p.participant_id = r.participant_id $rsql and r.record = '" . db_escape($_GET['record']) . "' limit 1";
            $q = db_query($sql);
            $matchesResponseId = db_num_rows($q);
            // If the record exists and the file was uploaded, but the survey page has not been saved
            if (!$isRecordValue && $matchesResponseId)
            {
                $HtmlPage = new HtmlPage();
                $HtmlPage->PrintHeaderExt();
                print "<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['survey_217']}";
                $HtmlPage->PrintFooterExt();
                exit;
            }
            // If does not match all existing data for this response, then do not allow downloading of file (i.e. they don't have rights to do so)
            elseif (!$isRecordValue || !$matchesResponseId)
            {
                exit("{$lang['global_01']}!");
            }
        }
    }

    public static function isRecordValue()
    {
        //Cross reference the doc_id, project_id, field_name, and record to ensure they all match up for this
        $sql = "select 1 from redcap_metadata m, redcap_edocs_metadata e, redcap_data d where m.project_id = " . PROJECT_ID . " and
                m.project_id = d.project_id and m.project_id = e.project_id and m.field_name = '" . db_escape($_GET['field_name']) . "'
                and m.field_name = d.field_name and m.element_type = 'file' and d.event_id = {$_GET['event_id']}
                and d.record = '" . db_escape($_GET['record']) . "' and d.value = e.doc_id and e.doc_id = '" . db_escape($_GET['id']) . "'
                and d.instance ".($_GET['instance'] == '1' ? "is null" : "= '" . db_escape($_GET['instance']) . "'")." limit 1";
        $q = db_query($sql);
        return (db_num_rows($q) > 0);
    }

    // If downloading/deleting file from a form, double check to make sure that the user has rights to it
    public static function checkFormFileRights()
    {
        global $lang, $Proj, $user_rights;
        // Since this is a project file, we can safely assume it's not a survey logo,
        // so it MUST be either an image/file attachment for a field, a survey email attachment, OR an uploaded file.
        // First, check if the file is an image/file attachment
        $sql = "select 1 from redcap_metadata m, redcap_edocs_metadata e where m.project_id = " . PROJECT_ID . " and
                m.project_id = e.project_id and m.element_type = 'descriptive' and m.edoc_id = '".db_escape($_GET['id'])."'
                and e.doc_id = m.edoc_id limit 1";
        $q = db_query($sql);
        $isNotRecordFile = db_num_rows($q);
        // If not an image/file attachement, check if it is a survey email attachment
        if (!$isNotRecordFile) {
            $sql = "select 1 from redcap_surveys where project_id = " . PROJECT_ID . " and
                    confirmation_email_attachment = '".db_escape($_GET['id'])."' limit 1";
            $q = db_query($sql);
            $isNotRecordFile = db_num_rows($q);
        }
        // Check if a file sent from the REDCap mobile app
        if (!$isNotRecordFile) {
            $appFileInfo = MobileApp::getAppArchiveFiles(PROJECT_ID, $_GET['id']);
            $isNotRecordFile = (!empty($appFileInfo));
        }
        // Check if this is a Data Dictionary snapshot AND user has Design privileges
        if (!$isNotRecordFile && $user_rights['design']) {
            $sql = "select 1 from redcap_data_dictionaries where project_id = " . PROJECT_ID . " and
                    doc_id = '".db_escape($_GET['id'])."' limit 1";
            $q = db_query($sql);
            $isNotRecordFile = db_num_rows($q);
        }
        // If the file is a user-uploaded file (i.e. NOT a field attachment), then it MUST have a field_name in the query string that we can now validate
        if (!$isNotRecordFile)
        {
            // Validate the field name, and also check that the record/event_id are included in the query string
            if (!isset($_GET['field_name']) || (isset($_GET['field_name']) && !isset($Proj->metadata[$_GET['field_name']]))
                || !isset($_GET['record']) || !isset($_GET['event_id']) || !is_numeric($_GET['event_id']))
            {
                exit("{$lang['global_01']}!");
            }
            // Add logic if user is in a DAG
            $group_sql = ($user_rights['group_id'] == "") ? "" : "and d.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])) . ")";
            // Cross reference the doc_id, project_id, field_name, and record to ensure they all match up for this (include DAG permissions)
            $sql = "select 1 from redcap_metadata m, redcap_edocs_metadata e, redcap_data d where m.project_id = " . PROJECT_ID . " and
                    m.project_id = d.project_id and m.project_id = e.project_id and m.field_name = '" . db_escape($_GET['field_name']) . "'
                    and m.field_name = d.field_name and m.element_type = 'file' and d.event_id = {$_GET['event_id']} $group_sql
                    and d.record = '" . db_escape($_GET['record']) . "' and d.value = e.doc_id 
                    and d.instance ".($_GET['instance'] == '1' ? "is null" : "= '" . db_escape($_GET['instance']) . "'")." 
                    and e.doc_id = '" . db_escape($_GET['id']) . "' limit 1";
            $q = db_query($sql);
            $isRecordValue = db_num_rows($q);
            // If record permissions don't add up, give error message
            if (!$isRecordValue)
            {
                // If deleting the file on the form when the record doesn't exist yet, don't render an error here
                if (PAGE == 'DataEntry/file_delete.php') return;
                // Check if record exists
                if (isset($_GET['signature']) && PAGE == 'DataEntry/image_view.php' && !Records::recordExists(PROJECT_ID, $_GET['record']))
                {
                    // Record doesn't exist. Make an exception if this is the image_view.php page when viewing a signature
                    return;
                }
                // Record doesn't exist yet OR the value hasn't been saved in the data table for an existing
                // record yet, so give error message
                $HtmlPage = new HtmlPage();
                $HtmlPage->PrintHeaderExt();
                print "<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['survey_217']}";
                $HtmlPage->PrintFooterExt();
                exit;
            }
            // Lastly, check form-level rights to make sure the user can access any data on the form that the file exists on
            $form = $Proj->metadata[$_GET['field_name']]['form_name'];
            if ($user_rights['forms'][$form] == '0') exit("{$lang['global_01']}!");
        }
    }

    // SECONDARY UNIQUE FIELD: Render the secondary unique field language text
    public static function renderSecondaryIdLang()
    {
        addLangToJS(array('data_entry_169', 'data_entry_170', 'data_entry_108', 'data_entry_105', 'calendar_popup_01', 'data_entry_106', 'data_entry_107',
        				  'data_entry_109', 'data_entry_110', 'data_entry_111', 'period'));
    }

	// Determine if ALL fields in the matrix have @HIDECHOICE action tag and have the same value for it.
	// Return array (boolean if all have @HIDECHOICE with same value, CSV string of choices hidden).
	public static function matrixAllFieldsHideChoice($matrix_group_name)
	{
		global $Proj;
		if (!isset($Proj->matrixGroupNames[$matrix_group_name])) return false;
		$action_tags_regex = $last_hide_choice_val = null;
		foreach ($Proj->matrixGroupNames[$matrix_group_name] as $field)
		{
			$misc = $Proj->metadata[$field]['misc'];
			// If field_annotation has @, then assume it might be an action tag
			if (strpos($misc, '@') === false) return array(false, '');
			// Get regex
			if ($action_tags_regex === null) {
				$action_tags_regex = Form::getActionTagMatchRegex();
			}
			// Match for @HIDECHOICE
			preg_match_all($action_tags_regex, $misc, $this_misc_match);
			if (isset($this_misc_match[1]) && !empty($this_misc_match[1]) && in_array('@HIDECHOICE', $this_misc_match[1]))
			{
				// Obtain the HIDECHOICE text for this field
				$this_hide_choice_val = Form::getValueInQuotesActionTag($misc, "@HIDECHOICE");
				if ($this_hide_choice_val == "") return array(false, '');
				if ($last_hide_choice_val === null) {
					$last_hide_choice_val = $this_hide_choice_val;
				} elseif ($last_hide_choice_val != $this_hide_choice_val) {
					return array(false, '');
				}
			}
		}
		return array(true, $this_hide_choice_val);
	}

	// Determine width of each column based upon number of choices
	public static function getMatrixHdrWidths($rr_array, $value=null)
	{
		$num_matrix_headers = count(parseEnum($rr_array['enum']));
		// Total percentage width of all choices combined (i.e., w/o the label)
		$totalPercentWidthChoices = 66;
		// Return column width percentage of each
		return round($totalPercentWidthChoices/$num_matrix_headers, 1);
	}

	// Produce HTML table to display a matrix question's headers
	public static function matrixHeaderTable($rr_array, $matrix_col_width, $isFirstInGroup=null, $hasRanking=0)
	{
		global $lang;
		// Get choice list
		$enum = $rr_array['enum'];
		// For Online Designer, add a table attribute so we can know if this field is the first field of a matrix group (for previewing form)
		$firstMatrix = "";
		if ($isFirstInGroup != null) {
			$firstMatrix = ($isFirstInGroup == '1') ? "fmtx='1'" : "";
		}
		// See if some columns need to be hidden due to @HIDECHOICE action tag
		$hideChoices = array();
		// First column (which is blank)
		$html = "<table role='presentation' cellspacing=0 class='headermatrix' hdrmtxgrp='{$rr_array['grid_name']}' $firstMatrix>
					<tr>
					  <td class='matrix_first_col_hdr'>".($hasRanking ? $lang['data_entry_204'] : "")."</td>";
		// Loop through all choices and display their label
		foreach (parseEnum($enum) as $this_key=>$this_hdr) {
			if (in_array($this_key."", $hideChoices, true)) continue;
			$html .= "<td id='matrixheader-{$rr_array['grid_name']}-$this_key' style='width:{$matrix_col_width}%;'>".decode_filter_tags($this_hdr)."</td>";
		}
		$html .= "	</tr>
				</table>";
		// Return table html
		return $html;
	}

	// If the Data Entry Trigger is enabled, then send HTTP Post request to specified URL
	public static function launchDataEntryTrigger()
	{
		global $data_entry_trigger_url, $table_pk, $longitudinal, $Proj, $data_entry_trigger_enabled, $redcap_version;
		// First, check if enabled
		if (!$data_entry_trigger_enabled || $data_entry_trigger_url == '') {
			return false;
		}
		// Set record name
		$record = $_POST[$table_pk];
		// Build HTTP Post request parameters to send
		$params = array('redcap_url'=>APP_PATH_WEBROOT_FULL,
						'project_url'=>APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/index.php?pid=".PROJECT_ID,
						'project_id'=>PROJECT_ID, 'username'=>USERID);
		// Add record name (using its literal variable name as key)
		$params['record'] = $record;
		// If longitudinal, include unique event name
		if ($longitudinal) {
			$params['redcap_event_name'] = $Proj->getUniqueEventNames($_GET['event_id']);
		}
		// Add unique data access group, if record is in a DAG
		if (PAGE != 'surveys/index.php' && isset($_POST['__GROUPID__']) && !empty($_POST['__GROUPID__'])) {
			// Data Entry Form: Get group_id from Post value
			$unique_group_name = $Proj->getUniqueGroupNames($_POST['__GROUPID__']);
		} elseif (PAGE == 'surveys/index.php') {
			// Survey: Check if DAGs exist and get DAG manually from back-end
			$uniqueDags = $Proj->getUniqueGroupNames();
			if (!empty($uniqueDags)) {
				// Query back-end to get DAG for this record (if in a DAG)
				$group_id = Records::getRecordGroupId(PROJECT_ID, $record);
				if ($group_id !== false) {
					$unique_group_name = $Proj->getUniqueGroupNames($group_id);
				}
			}
		}
		if (isset($unique_group_name) && !empty($unique_group_name)) {
			$params['redcap_data_access_group'] = $unique_group_name;
		}
		// Add name of data collection instrument and its status value (0,1,2) unless we're merging a DDE record
		if (PAGE != 'DataComparisonController:index') {
			$params['instrument'] = $_GET['page'];
			// Add status of data collection instrument for this record (0=Incomplete, 1=Unverified, 2=Complete)
			$formStatusField = $_GET['page'].'_complete';
			$params[$formStatusField] = $_POST[$formStatusField];
		}
		// Repeating events/instruments
		if ($Proj->hasRepeatingFormsEvents()) {
			if ($Proj->isRepeatingForm($_GET['event_id'], $_GET['page'])) {
				$params['redcap_repeat_instrument'] = $_GET['page'];
				$params['redcap_repeat_instance'] = $_GET['instance'];
			}
			elseif ($Proj->isRepeatingEvent($_GET['event_id'])) {
				$params['redcap_repeat_instance'] = $_GET['instance'];
			}
		}
		// Set timeout value for http request
		$timeout = 10; // seconds
		// If $data_entry_trigger_url is a relative URL, then prepend with server domain
		$pre_url = "";
		if (substr($data_entry_trigger_url, 0, 1) == "/") {
			$pre_url = (SSL ? "https://" : "http://") . SERVER_NAME;
		}
		// Send Post request
		$response = http_post($pre_url . $data_entry_trigger_url, $params, $timeout);
		// Return boolean for success
		return !!$response;
	}

    // check if value is a missing data code, and display label if so
    public static function displayMissingDataLabel($name, $code)
    {
        global $missingDataCodes, $Proj;
        if (isset($missingDataCodes[$code]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$name]['misc'])) {
            $MDLabel = RCView::escape($missingDataCodes[$code])." ($code)";
            $label = js_escape(strip_tags($missingDataCodes[$code]));
            $visible='';
        } else {
            $MDLabel = $label = "";
            $visible = 'display:none';
        }
        print "<div id='{$name}_MDLabel' class='MDLabel' style='$visible' code='".js_escape(strip_tags($code))."' label='$label'>$MDLabel</div>";
    }
}