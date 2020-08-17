<?php

class DataComparisonTool
{
	// Render page
	public function renderPage()
	{
		extract($GLOBALS);

		renderPageTitle("<i class=\"fas fa-not-equal\"></i> " . $lang['app_02'] . " " . ($double_data_entry ? $lang['data_comp_tool_01'] : ""));

		?>
		<script type="text/javascript">
		// For selecting values for merging in Data Comparison Tool when using Double Data Entry module
		function dataCmpChk(col,field,val) {
			if (col < 3) {
				eval('document.create_new.'+field+'.value = val; document.create_new.'+field+'___RAD3.disabled = true;');
				changeSty(field+'___RAD3','data');
				if (col == 1) {
					changeSty(field+'___RAD1','header');
					changeSty(field+'___RAD2','data');
				} else if (col == 2) {
					changeSty(field+'___RAD1','data');
					changeSty(field+'___RAD2','header');
				}
			} else if (col == 3) {
				eval('document.create_new.'+field+'.value = ""; document.create_new.'+field+'___RAD3.disabled = false;');
				changeSty(field+'___RAD1','data');
				changeSty(field+'___RAD2','data');
				changeSty(field+'___RAD3','header');
			}
		}
        //Allow for dynamic style changes
        function changeSty(thisfield,classpassed){
            document.getElementById(thisfield).className=classpassed;
        }
		</script>
		<?php
		
		// REPEATING INSTANCES: Add note that currently the app does not support repeating forms/events
		if ($Proj->hasRepeatingFormsEvents()) {
			print RCView::div(array('class'=>'red', 'style'=>''), $lang['data_comp_tool_48']);
		}

		// Instructions
		if ($double_data_entry) {
			print "<p>" . $lang['data_comp_tool_03'];
		} else {
			print "<p>" . $lang['data_comp_tool_04'];
		}

		//If user is in DAG, only show info from that DAG and give note of that
		if ($user_rights['group_id'] != "") {
			print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['data_comp_tool_05']}</p>";
		}

		// Create array of checkbox fields with field_name as key and default value options of "0" as sub-array values
		$chkbox_fields = array();
		foreach ($Proj->metadata as $field=>$attr) {
			if (!$Proj->isCheckbox($field)) continue;
			foreach (parseEnum($attr['element_enum']) as $this_value=>$this_label) {
				$chkbox_fields[$field][$this_value] = "0";
			}
		}
		
		###############################################################################################
		## If Double Data Entry reviewer is creating a new merged third record...
		if (isset($_GET['create_new']) && $_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['compare-all']))
		{
			print "<hr size=1><p>";

			// Get record name and its event_id for new record
			$event_id3 = (int)$_GET['event_id'];
			$record3 = $_POST[$table_pk] = substr($_POST[$table_pk], 0, -3);
			
			// Loop through posted values to clean and remove extra radio logic used (kludgey!)
			foreach ($_POST as $key=>$value) {
				if (substr($key, -11) == "___RADradio") {
					$real_key = substr($key, 0, -11);
					if (isset($_POST[$real_key])) {
						unset($_POST[$real_key."___RAD1"]);
						unset($_POST[$real_key."___RAD2"]);
						unset($_POST[$real_key."___RAD3"]);
						unset($_POST[$real_key."___RADradio"]);
					}
				}
			}

			// Get all data shared by both records and add it to $_POST, which currently has the data selected by the user because of discrepancies
			$sql = "select d.field_name, d.value, m.element_type, m.element_enum from redcap_data d, redcap_metadata m where d.project_id = $project_id
					and d.project_id = m.project_id and d.field_name = m.field_name and d.event_id = $event_id3 and d.record = '".db_escape($record3)."--1'
					and d.instance is null and (m.element_type = 'checkbox' or d.field_name not in (";
			foreach (array_keys($_POST) as $this_field)
			{
				if (strpos($this_field, "___") !== false) {
					$this_field_real = substr($this_field, 0, strpos($this_field, "___"));
					if (isset($Proj->metadata[$this_field_real])) {
						$this_field = $this_field_real;
					}
				}
				$sql .= "'$this_field',";
			}
			$sql = substr($sql, 0, -1) . "))";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Parse checkboxes correctly
				if ($row['element_type'] == "checkbox") {
					// Add default "0" values if they've not been added yet for each checkbox field
					foreach (array_keys(parseEnum($row['element_enum'])) as $key) {
						if (!isset($_POST[$row['field_name']][$key])) $_POST[$row['field_name']][$key] = "0";
					}
					// Now add this single checkbox value from the data table
					$_POST[$row['field_name']][$row['value']] = "1";
				// Normal non-checkbox fields
				} else {
					// If a date[time][_seconds] field, then reformat data before saving (entering in other formats)
					if ($Proj->metadata[$row['field_name']]['element_type'] == 'text' && substr($Proj->metadata[$row['field_name']]['element_validation_type'], 0, 4) == 'date')
					{
						// Check type
						if (substr($Proj->metadata[$row['field_name']]['element_validation_type'], 0, 8) == 'datetime') {
							list ($thisdate, $thistime) = explode(" ", $row['value']);
						} else {
							$thisdate = $row['value'];
							$thistime = "";
						}
						if (substr($Proj->metadata[$row['field_name']]['element_validation_type'], -4) == '_dmy') {
							$row['value'] = trim(DateTimeRC::date_ymd2dmy($thisdate) . " " . $thistime);
						} elseif (substr($Proj->metadata[$row['field_name']]['element_validation_type'], -4) == '_mdy') {
							$row['value'] = trim(DateTimeRC::date_ymd2mdy($thisdate) . " " . $thistime);
						}
					}
					// Add to array
					$_POST[$row['field_name']] = $row['value'];
				}
			}

			// Loop through POST again to now overlay any checkbox field values by converting existing POST fields with triple underscore to sub-arrays
			foreach ($_POST as $key=>$value)
			{
				$pos = strpos($key, "___");
				if ($pos !== false) {
					$this_field = substr($key, 0, $pos);
					if (isset($chkbox_fields[$this_field])) {
						// This is a checkbox field, so convert it
						$code = substr($key, $pos+3);
						$_POST[$this_field][$code] = $value;
						// Now remove the pre-converted keys
						unset($_POST[$key]);
					}
				}
			}

			// Loop through posted values to insert values to create new record
			$sql_all = array();
			$display = array();
			$query_failed = false;
			foreach ($_POST as $this_field=>$this_value)
			{
				// Checkbox fields only
				if (isset($chkbox_fields[$this_field])) {
					foreach ($this_value as $this_key=>$this_value_sub) {
						// Only insert if value is "1" (checked)
						if ($this_value_sub) {
							$sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES ($project_id, $event_id3, '" . db_escape($record3). "', '$this_field', '" . db_escape($this_key). "')";
							if (db_query($sql)) {
								$display[] = "$this_field($this_key) = checked";
							} else {
								$query_failed = true;
							}
						}
					}
				// Regular non-checkbox fields
				} else {

					// If a date[time][_seconds] field, then check if we need to reformat data before saving (entering in other formats)
					if ($Proj->metadata[$this_field]['element_type'] == 'text' && substr($Proj->metadata[$this_field]['element_validation_type'], 0, 4) == 'date')
					{
						// Check type
						if (substr($Proj->metadata[$this_field]['element_validation_type'], 0, 8) == 'datetime') {
							list ($thisdate, $thistime) = explode(" ", $this_value);
						} else {
							$thisdate = $this_value;
							$thistime = "";
						}
						if (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == '_dmy') {
							$this_value = trim(DateTimeRC::date_dmy2ymd($thisdate) . " " . $thistime);
						} elseif (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == '_mdy') {
							$this_value = trim(DateTimeRC::date_mdy2ymd($thisdate) . " " . $thistime);
						}

					// For "file upload" fields, copy the file first and get new doc_id here (so 2 records don't point to 1 doc)
					} elseif ($Proj->metadata[$this_field]['element_type'] == "file") {
						// Take the doc_id value to copy the file and get new doc_id for copied file
						$new_edoc_id = copyFile($this_value);
						if ($new_edoc_id !== false) {
							$this_value = $new_edoc_id;
						}
					}

					// Set sql
					$sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES ($project_id, $event_id3, '" . db_escape($record3). "', '$this_field', '" . db_escape($this_value). "')";
					if (db_query($sql)) {
						$display[] = "$this_field = '$this_value'";
					} else {
						$query_failed = true;
					}
				}
			}

			if ($query_failed) {
				print "<p><font color=#800000><b>{$lang['global_09']}. {$lang['data_comp_tool_07']}</font></b>";
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
				exit;
			} else {
				// DAGs: If records belong to a DAG, then add this 3rd record to that DAG
				$dagRecord1 = Records::getRecordGroupId($project_id, "$record3--1");
				$dagRecord2 = Records::getRecordGroupId($project_id, "$record3--2");
				if (is_numeric($dagRecord1) && $dagRecord1 == $dagRecord2) {					
					$sql_all[] = $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES ($project_id, $event_id3, '" . db_escape($record3). "', '__GROUPID__', '" . db_escape($dagRecord1). "')";
					db_query($sql);
				}
			}

			// Set event_id for logging and Data Entry Trigger
			$_GET['event_id'] = $event_id3;

			//Logging
			Logging::logEvent(implode(";\n",$sql_all),"redcap_data","insert",$record3,implode(",\n",$display),"Merge records");

			// Perform auto-calculation on new record, which might have calc fields
			if (!$Proj->project['disable_autocalcs']) {
				$calcFields = Calculate::getCalcFieldsByTriggerField(array_keys($_POST));
				if (!empty($calcFields)) {
					$calcValuesUpdated = Calculate::saveCalcFields(array($record3), $calcFields, $_GET['event_id']);
				}
			}

			// DATA ENTRY TRIGGER: If the Data Entry Trigger is enabled, then send HTTP Post request to specified URL
			DataEntry::launchDataEntryTrigger();
		}



		// Set flag to compare ALL records/events instead of single pair of records/events
		$compareAll = ($double_data_entry && isset($_POST['compare-all']) && $_POST['compare-all']);






		//Decide which pulldowns to display for user to choose Study ID
		if ($user_rights['group_id'] == "") {
			$group_sql  = "";
		} else {
			$group_sql  = "and d.record in (" . prep_implode(Records::getRecordListSingleDag($project_id, $user_rights['group_id'])) . ")";
		}
		$rs_ids_sql = "select d.record, d.event_id from redcap_data d, redcap_events_metadata m, redcap_events_arms a
					   where d.project_id = $project_id and a.project_id = d.project_id and a.arm_id = m.arm_id and d.field_name = '$table_pk' $group_sql
					   and d.instance is null and d.event_id = m.event_id order by d.record, a.arm_num, m.day_offset, m.descrip";
		$q = db_query($rs_ids_sql);
		// Collect record names into array
		$records  = array();
		$alreadyMerged = $records_orig_case = array(); // Used for DDE only
		while ($row = db_fetch_assoc($q))
		{
			// Maintain case for each pair of DDE records
			if ($double_data_entry && (substr($row['record'], -3) == '--1' || substr($row['record'], -3) == '--2')) {
				// Array to deal with case sensitivity in names
				$record_base = substr($row['record'], 0, -3);
				$record_base_lower = strtolower($record_base);
				$record_ending = substr($row['record'], -3);
				if (isset($records_orig_case[$record_base_lower])) {
					// Already have first of pair for record, so use the first one's case
					$row['record'] = $records_orig_case[$record_base_lower] . $record_ending;
				} else {
					// This is the first of the pair, so add its case to the array
					$records_orig_case[$record_base_lower] = $record_base;
				}
			}
			// Add to arrays
			$records[$row['record']][$row['event_id']] = $Proj->eventInfo[$row['event_id']]['name_ext'];
		}
		unset($records_orig_case);
		natcaseksort($records);
		// DDE ONLY: Now loop through array and parse out
		if ($double_data_entry)
		{
			// Temp array
			$records2 = array();
			// Loop through all records
			foreach ($records as $this_record=>$this_event)
			{
				// Get real record name (i.e. w/o the --1 or --2 on the end)
				if (substr($this_record, -3) == '--1' || substr($this_record, -3) == '--2') {
					$this_record_real = substr($this_record, 0, -3);
				} else {
					$this_record_real = $this_record;
				}
				// Loop through the events for this record
				foreach ($this_event as $this_event_id=>$this_event_name)
				{
					// If both --1 and --2 records exist, then replace both with a single real record, else remove both
					if (isset($records[$this_record_real."--1"][$this_event_id]) && isset($records[$this_record_real."--2"][$this_event_id]))
					{
						// Add to $records2 array
						$records2[$this_record_real][$this_event_id] = $Proj->eventInfo[$this_event_id]['name_ext'];
						// If record was merged, add to $alreadyMerged array
						if (isset($records[$this_record_real][$this_event_id]))
						{
							$alreadyMerged[$this_record_real][$this_event_id] = true;
						}
					}
				}
			}
			// Swap arrays now
			$records = $records2;
			unset($records2);
		}
		// Loop through the record list and store as string for drop-down options
		$id_dropdown = "";
		foreach ($records as $this_record=>$this_event)
		{
			foreach ($this_event as $this_event_id=>$this_event_name)
			{
				$id_dropdown .= "<option value='{$this_record}[__EVTID__]{$this_event_id}'>"
							  . $this_record . ($longitudinal ? " - $this_event_name" : "")
							  . (isset($alreadyMerged[$this_record][$this_event_id]) ? " - ".$lang['data_comp_tool_47'] : "")
							  . "</option>";
			}
		}

		// Give option to compare all DDE pairs of records on single page
		$compareAllBtn = '';
		if ($double_data_entry)
		{
			$disableCompAllBtn = (empty($records)) ? "disabled" : "";
			$compareAllBtn = RCView::div(array('style'=>'padding:5px 0;font-weight:normal;color:#777;'),
								"&mdash; {$lang['global_46']} &mdash;"
							 ) .
							 RCView::div('',
								RCView::input(array('style'=>'font-weight:normal;', 'type'=>'submit','name'=>'submit','value'=>$lang['data_comp_tool_45'],$disableCompAllBtn=>$disableCompAllBtn,'onclick'=>"$('#record1').val($('#record1 option:eq(1)').val()); $('input[name=\"compare-all\"]').val('1');"))
							 );
		}


		// Table to choose record (show ONLY 1 pulldown for true Double Data Entry comparison)
		print "<form action=\"".APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID."&route=DataComparisonController:index\" method=\"post\" enctype=\"multipart/form-data\" name=\"datacomp\" target=\"_self\">";
		print "<table class='form_border'>
				<tr>
					<td class='label_header' style='padding:10px;'>
						$table_pk_label
					</td>
					<td class='label_header' style='padding:10px;' rowspan='2'>
						<input style='font-weight:normal;' name='submit' type='submit' value=\"".js_escape2($double_data_entry ? $lang['data_comp_tool_44'] : $lang['data_comp_tool_02'])."\" onclick=\"
							if ($('#record1').val().length < 1" . (!$double_data_entry ? " || $('#record2').val().length < 1" : "") . ") {
								simpleDialog('".js_escape($lang['data_comp_tool_06'])."');
								return false;
							}
						\">
						$compareAllBtn
						<input type='hidden' name='compare-all' value='0'>
					</td>
				</tr>
				<tr>
					<td class='data' align='center' style='padding:15px;'>
						<select name='record1' id='record1' class='x-form-text x-form-field' style=''>
							<option value=''>--- {$lang['data_comp_tool_43']} ---</option>
							$id_dropdown";
		if (!$double_data_entry)
		{
			print  " 	</select> &nbsp;&nbsp;
						<select name='record2' id='record2' class='x-form-text x-form-field' style=''>
							<option value=''>--- {$lang['data_comp_tool_43']} ---</option>
							$id_dropdown";
		}
		print  "		</select></td>";
		print  "</tr>
				</table>";
		print  "</form><br><br>";

		// If sumbitted values, use javascript to select the dropdown values
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			if (!$compareAll) {
				// pre-select the drop-down(s), but not if user clicked "compare all" button
				print  "<script type='text/javascript'>
						$(function(){
							$('#record1').val('{$_POST['record1']}');
							$('#record2').val('{$_POST['record2']}');
						});
						</script>";
			}
			if (isset($_GET['create_new']) && !isset($_POST['compare-all']))
			{
				//Conclude with congratulatory text
				print 	"<div class='darkgreen'><h4><font color=#800000>{$lang['data_comp_tool_09']}</font></h4>" .
						"<p><b>Record <font color=#800000>$record3</font>"
						.($longitudinal ? " {$lang['data_entry_67']} <font color=#800000>".$Proj->eventInfo[$event_id3]['name_ext']."</font>" : "")."
						{$lang['data_comp_tool_08']} $record3" . "--1 and $record3" . "--2"
						.($longitudinal ? " {$lang['data_entry_67']} ".$Proj->eventInfo[$event_id3]['name_ext'] : "")."{$lang['period']}</b>
						</div>";
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
				exit;
			}
		}










		$display_string = '';

		###############################################################################################
		# When records are selected for comparison, display side-by-side comparison in table
		if (isset($_POST['submit']) || isset($_GET['merge']))
		{
			// Reset some CSS in table for consistent viewing when selecting which record value to merge
			$display_string .= "<style type='text/css'>
								.data { padding: 5px; }
								.header { font-size: 7.5pt; }
								</style>";

			// PRINT PAGE button
			print  "<div style='text-align:right;max-width:700px;'>
						<button class='jqbuttonmed invisible_in_print' onclick='window.print();'><img src='".APP_PATH_IMAGES."printer.png'> Print page</button>
					</div>";

			// If only comparing a single pair of records/events
			if (!$compareAll) {
				list ($record1, $event_id1) = explode("[__EVTID__]", $_POST['record1']);
                $event_id1 = (int)$event_id1;
				$records = array($record1=>array($event_id1=>1));
			}

			// Retrieve all validation types
			$valTypes = getValTypes();

			// Loop counter
			$loopNum = 0;

			//print_array($records);

			// Loop through records
			foreach ($records as $record1=>$evts)
			{
				// Retrieve the submitted record names and their corresponding event_ids
				if ($double_data_entry) {
					$record2 = $record1 . "--2";
					$record1 .= "--1";
				} else {
					list ($record2, $event_id2) = explode("[__EVTID__]", $_POST['record2']);
                    $event_id2 = (int)$event_id2;
				}

				// Loop through events for this record
				foreach (array_keys($evts) as $event_id1)
				{
					// Retrieve the submitted record names and their corresponding event_ids
					if ($double_data_entry) {
						$event_id2 = $event_id1;
					}

					// Check to make sure the user didn't select the same record twice
					if ($record1."" === $record2."" && $event_id2 == $event_id1 && $record1 != "" && $record2 != "") {
						print "<hr size=1><p><font color=#800000><b>{$lang['data_comp_tool_10']} ($record1) {$lang['data_comp_tool_11']}</b></font><p><br>";
						if (!$compareAll) {
							include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
							exit;
						}
					}

					// Retrieve data values for record 1
					$sql = "select record, field_name, value from redcap_data where record = '".db_escape($record1)."'
							and project_id = $project_id and event_id = $event_id1 and instance is null";
					$q = db_query($sql);
					$record1_data = self::eavDataArray($q, $chkbox_fields);
					// Make sure the array key's case matches the $record1 var
					if (!empty($record1_data) && !isset($record1_data[$record1])) {
						foreach ($record1_data as &$this_data) {
							$record1_data = array($record1=>$this_data);
							break;
						}
					}

					// Retrieve data values for record 2
					$sql = "select record, field_name, value from redcap_data where record = '".db_escape($record2)."'
							and project_id = $project_id and event_id = $event_id2 and instance is null";
					$q = db_query($sql);
					$record2_data = self::eavDataArray($q, $chkbox_fields);
					// Make sure the array key's case matches the $record1 var
					if (!empty($record2_data) && !isset($record2_data[$record2])) {
						foreach ($record2_data as &$this_data) {
							$record2_data = array($record2=>$this_data);
							break;
						}
					}

					// Retrieve metadata fields that are only relevent here for data comparison (only get fields that we have data for)
					$metadata_fields_rec1rec2 = array_unique(array_merge(array_keys($record1_data[$record1]), array_keys($record2_data[$record2])));
					$metadata_fields = array();
					foreach ($Proj->metadata as $this_field=>$row) {
						if (!in_array($this_field, $metadata_fields_rec1rec2)) continue;
						$metadata_fields[$this_field] = $row;
					}

					// Initialize string to gather HTML for entire table display
					$display_string = "<hr size=1>";

					// Display comparison table instructions
					if (!isset($_GET['merge'])) {
						$display_string .= "<p style='color:#000066;'>
												<b>{$lang['data_comp_tool_46']} <span style='color:#800000;font-size:14px;'>$record1</span> {$lang['global_43']}
												<span style='color:#800000;font-size:14px;'>$record2</span>" .
												($longitudinal ? " {$lang['data_entry_67']} <span style='color:#800000;'>".$Proj->eventInfo[$event_id1]['name_ext']."</span>" : "") .
												"{$lang['period']}</b><br><br>
												{$lang['data_comp_tool_16']} <b>$record1</b>
												{$lang['global_43']} <b>$record2</b>".$lang['period']."
												{$lang['data_comp_tool_17']}
											</p>";
					}

					//Default flag to show the merged record, if it exists.
					$show_merged_record = false;

					if ($double_data_entry && !isset($_GET['merge'])) {

						//Check to see if the record has been created before. If so, then stop here.
						$record3 = substr($record1, 0, -3);
						$event_id3 = $event_id1;
						$sql = "select distinct record from redcap_data where project_id = $project_id and record = '".db_escape($record3)."' and field_name = '$table_pk'";
						if ($longitudinal) $sql .= " and event_id = $event_id3";
						$q = db_query($sql);
						if (db_num_rows($q) > 0) {
							//The merged record already exists. Do not allow a merge here.
							$display_string .= "<p style='color:#000066;'>
													<b>{$lang['global_02']}:</b> {$lang['data_comp_tool_18']} <b>$record3</b>{$lang['data_comp_tool_19']}
												</p>";
							//Set value to display the merged record, since it exists.
							$show_merged_record = true;
							// Retrieve data values for record 2
							$sql = "select record, field_name, value from redcap_data where record = '".db_escape($record3)."' and project_id = $project_id
									and event_id = $event_id3 and instance is null";
							$q = db_query($sql);
							$record3_data = self::eavDataArray($q, $chkbox_fields);

						} else {

							//Give link for merging the two records
							$display_string .= "<form action='".APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID."&route=DataComparisonController:index&merge=1' method='post' name='merge{$loopNum}'>
												<p style='color:#000066;'>
													<b>{$lang['data_comp_tool_20']}</b><br>
													{$lang['data_comp_tool_21']}
													<a href='javascript:;' style='text-decoration:underline;color:#800000;' onclick=\"
														document.merge{$loopNum}.submit();
														return false;
													\">{$lang['data_comp_tool_22']}</a>".$lang['period']."
													<input type='hidden' name='record1' value='".substr($record1, 0, -3)."[__EVTID__]{$event_id1}'>
												</p>
												</form>";
						}

					} elseif ($double_data_entry && isset($_GET['merge'])) {

						//Give instructions for merging the two records now that the third record column is displayed.
						$display_string .= "<p style='color:#000066;'>
												<b>{$lang['data_comp_tool_23']}</b><br><br>
												{$lang['data_comp_tool_24']}<br><br>
												{$lang['data_comp_tool_25']}
											</p>";

					}
					
					// Init any sliders on the page
					$display_string .= "<script type='text/javascript'>\$(function(){ initSliders(); });</script>";

					//Begin building table and build headers
					$display_string .= "<div style='max-width:700px;'>
										<form action='".APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID."&route=DataComparisonController:index&event_id=$event_id1&create_new=1' method='post' enctype='multipart/form-data' name='create_new' target='_self'>
										<table class='form_border'>
										<tr>
											<td class='header' style='font-size:8pt;text-align:center;' rowspan=2>{$lang['data_comp_tool_26']} <i>{$lang['data_comp_tool_27']}</i></td>
											<td class='header' style='font-size:8pt;text-align:center;' rowspan=2>{$lang['global_12']}</td>";

					//If merging the two records, show different text for the last table header
					if (isset($_GET['merge'])) {
						$display_string .= "<td class='header' style='font-size:8pt;text-align:center;' colspan=3>
												<font color=#800000>&nbsp;<br/>
												{$lang['data_comp_tool_29']}<br/>
												&nbsp;</font>
											</td>
										</tr>";
					} else {
						//Determine if need to show the merged record
						$display_string .= "<td class='header' style='font-size:8pt;text-align:center;' colspan='" . ($show_merged_record ? 3 : 2) . "'>
												<font color=#800000>$table_pk_label</font>
											</td>
										</tr>";
					}


					$display_string .= "<tr>
											<td class='data' valign='bottom' style='text-align:center;color:#000066;'>
												<b>$record1</b>" . ($longitudinal ? "<div style='font-size:11px;'>".$Proj->eventInfo[$event_id1]['name_ext']."</div>" : "") . "
											</td>
											<td class='data' style='text-align:center;color:#000066;'>
												<b>$record2</b>" . ($longitudinal ? "<div style='font-size:11px;'>".$Proj->eventInfo[$event_id2]['name_ext']."</div>" : "") . "
											</td>";

					//Determine if need to show the merged record
					if ($show_merged_record) {
						$display_string .= "<td class='data' valign='bottom' style='text-align:center;color:#000066;'>
												<b>$record3<br>{$lang['data_comp_tool_31']}</b>" . ($longitudinal ? "<div style='font-size:11px;'>".$Proj->eventInfo[$event_id3]['name_ext']."</div>" : "") . "
											</td>";
					}

					//Add extra column to table if preparing to merge the two records
					if (isset($_GET['merge'])) {
						$display_string .= "<td class='data' valign='bottom' style='text-align:center;'>
												<font color=#000066><b>{$lang['data_comp_tool_32']}</b></font>
											</td>";
					}

					$display_string .= "</tr>";

					// Initialize string for capturing table row of HTML
					$diff = "";

					// print_array($record1_data);
					// print_array($record2_data);
					// print_array($metadata_fields);

					//Render rows: Loop through all fields being compared
					foreach ($metadata_fields as $field_name=>$attr) {

						// Skip record id field (not applicable)
						if ($field_name == $table_pk) continue;

						// Get field attributes
						$element_label = $attr['element_label'];
						$form_name = $attr['form_name'];
						$element_type = $attr['element_type'];
						$select_choices = $attr['element_enum'];

						// Skip calc fields (not applicable)
						if ($element_type == "calc") continue;

						// Create array for possible sub-looping through multiple values for single field
						$subloop = array();


						// If field has multiple values associated with a single field (e.g., checkbox), then causing multiple looping for that field
						if ($element_type == "checkbox") {
							// Create array to hold labels for this checkbox field
							$checkbox_labels = parseEnum($Proj->metadata[$field_name]['element_enum']);
							// Loop using record1's values (but doesn't matter because ALL checkboxes are added to record1 and record2 arrays by default (to cover default "0" values)
							foreach (array_keys($record1_data[$record1][$field_name]) as $this_code) {
								// Create new field name with triple underscore + coded value
								$this_field = $field_name . "___" . $this_code;
								// Set with multiple values
								$subloop[1][$this_field] = $record1_data[$record1][$field_name][$this_code];
								$subloop[2][$this_field] = $record2_data[$record2][$field_name][$this_code];
								if ($show_merged_record) {
									$subloop[3][$this_field] = $record3_data[$record3][$field_name][$this_code];
								}
							}
						// If field only has one data point (normal)
						} else {
							// Set with single values
							$subloop[1][$field_name] = $record1_data[$record1][$field_name];
							$subloop[2][$field_name] = isset($record2_data[$record2][$field_name])
								? $record2_data[$record2][$field_name] : '';
							if ($show_merged_record) {
								$subloop[3][$field_name] = $record3_data[$record3][$field_name];
							}
						}
						
						//create array of missing data codes for comparison later:
						$missingDataKeys=array_keys($missingDataCodes);
						
						// Loop through all sub-fields, if a checkbox, else it'll just loop once
						foreach (array_keys($subloop[1]) as $sub_field_name)
						{
							// Set values for this subloop
							$this_val1 = $subloop[1][$sub_field_name];
							$this_val2 = $subloop[2][$sub_field_name];
							if ($show_merged_record) {
								$this_val3 = $subloop[3][$sub_field_name];
							}

							// If field is Text or Notes field type, then remove line breaks and minimize spaces for proper comparison of characters
							if ($element_type == 'text' || $element_type == 'textarea') {
								$this_val1 = remBr($this_val1);
								$this_val2 = remBr($this_val2);
								if ($show_merged_record) {
									$this_val3 = remBr($this_val3);
								}
								// If a date[time][_seconds] field, then check if we need to reformat data before displaying (entered in other formats) - Unless the field contains a missing data code
								if ($Proj->metadata[$sub_field_name]['element_type'] == 'text' && substr($Proj->metadata[$sub_field_name]['element_validation_type'], 0, 4) == 'date')
								{
									// Check type
									if (substr($Proj->metadata[$sub_field_name]['element_validation_type'], 0, 8) == 'datetime') {
										list ($thisdate1, $thistime1) = explode(" ", $this_val1);
										list ($thisdate2, $thistime2) = explode(" ", $this_val2);
										if ($show_merged_record) list ($thisdate3, $thistime3) = explode(" ", $this_val3);
									} else {
										$thisdate1 = $this_val1;
										$thistime1 = "";
										$thisdate2 = $this_val2;
										$thistime2 = "";
										if ($show_merged_record) {
											$thisdate3 = $this_val3;
											$thistime3 = "";
										}
									}
									if (substr($Proj->metadata[$sub_field_name]['element_validation_type'], -4) == '_dmy') {
										if (!in_array($thisdate1, $missingDataKeys)){
											$this_val1 = trim(DateTimeRC::date_ymd2dmy($thisdate1) . " " . $thistime1);
										}
										if (!in_array($thisdate2, $missingDataKeys)){
											$this_val2 = trim(DateTimeRC::date_ymd2dmy($thisdate2) . " " . $thistime2);
										}
										
										if ($show_merged_record) {
											if (!in_array($thisdate3, $missingDataKeys)){
												$this_val3 = trim(DateTimeRC::date_ymd2dmy($thisdate3) . " " . $thistime3);
											}
										}
									} elseif (substr($Proj->metadata[$sub_field_name]['element_validation_type'], -4) == '_mdy') {
										if (!in_array($thisdate1, $missingDataKeys)){
										$this_val1 = trim(DateTimeRC::date_ymd2mdy($thisdate1) . " " . $thistime1);
										}
										
										if (!in_array($thisdate2, $missingDataKeys)){
										$this_val2 = trim(DateTimeRC::date_ymd2mdy($thisdate2) . " " . $thistime2);
										}
										if ($show_merged_record) {
											if (!in_array($thisdate3, $missingDataKeys)){
												$this_val3 = trim(DateTimeRC::date_ymd2mdy($thisdate3) . " " . $thistime3);
											}
										}
									}
								}
							}

							//print out values if there is a difference bewteen data for each entered id
							if ($this_val1 != $this_val2) {

								// Remove any illegal characters that can cause javascript to crash
								$this_val1 = $this_val1_orig = htmlspecialchars(html_entity_decode(html_entity_decode($this_val1, ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);
								$this_val2 = $this_val2_orig = htmlspecialchars(html_entity_decode(html_entity_decode($this_val2, ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);

								// For checkboxes, convert each choice to advcheckbox enum
								if ($element_type == 'checkbox') {
									$select_choices = $attr['element_enum'] = "0, Unchecked \n 1, Checked";
								}

								// Process values for SELECT boxes (and ADVCHECKBOXes) to display the text AND the numerical value
								if ($element_type == 'yesno' || $element_type == 'truefalse' || $element_type == 'select' || $element_type == 'checkbox' || $element_type == 'advcheckbox' || $element_type == 'radio') {

									// Parse the enum to store as array to pull the labels later, adding in missing data codes
									$select_text = parseEnum($attr['element_enum']) + $missingDataCodes;
									
									//print_array($select_text);
									//print_array($missingDataCodes);
									
									// Set newly formatted values for display
									if ($this_val1 != "") $this_val1 = $select_text[$this_val1] . " <i>($this_val1)</i>";
									if ($this_val2 != "") $this_val2 = $select_text[$this_val2] . " <i>($this_val2)</i>";
									if ($show_merged_record) {
										if ($this_val3 != "") $this_val3 = $select_text[$this_val3] . " <i>($this_val3)</i>";
									}
								}

								// For checkboxes, provide extra label of field_name + triple underscore + coding
								if ($element_type == 'checkbox')
								{
									$disp_field_name = strtolower($field_name . " &raquo; $sub_field_name");
									$disp_choice_code = substr($sub_field_name, strrpos($sub_field_name, "___")+3);
									$disp_element_label = $element_label . " (Choice =  <b>" . $checkbox_labels[$disp_choice_code] . "</b>)";
								}
								else
								{
									$disp_field_name = $field_name;
									$disp_element_label = $element_label;
								}

								// Render static row of two values
								if (!isset($_GET['merge'])) {

									$diff .=  	"<tr>
													<td class='data' style='padding:2px 5px;'>
														".RCView::escape($disp_element_label)." <i>($disp_field_name)</i>
													</td>
													<td class='data' style='padding:2px 5px;'>
														".RCView::escape($Proj->forms[$form_name]['menu'])."
													</td>
													<td valign='top' class='data' style='padding:2px 5px;cursor:pointer;' onclick=\"window.open('" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&page=$form_name&id=$record1&event_id=$event_id1&fldfocus=$field_name#$field_name-tr','_blank');\">
														<span class=\"compare\" style='color:#800000;'>$this_val1</span>
													</td>
													<td valign='top' class='data' style='padding:2px 5px;cursor:pointer' onclick=\"window.open('" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&page=$form_name&id=$record2&event_id=$event_id2&fldfocus=$field_name#$field_name-tr','_blank');\">
														<span class=\"compare\" style='color:#800000;'>$this_val2</span>
													</td>";
									if ($show_merged_record) {
										$diff .=   "<td valign='top' class='data' style='padding:2px 5px;cursor:pointer' onclick=\"window.open('" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&page=$form_name&id=$record3&event_id=$event_id3&fldfocus=$field_name#$field_name-tr','_blank');\">
														<span class=\"compare\" style='color:#800000;'>$this_val3</span>
													</td>";
									}
									$diff .= "</tr>";

								// Render row with ability to select values for merge
								} elseif (isset($_GET['merge'])) {

									// Print out table row (no links)
									$diff .=   "<tr>
													<td class='data' valign='center'>
														".strip_tags(label_decode($disp_element_label))." <i>($disp_field_name)</i>
													</td>
													<td class='data'>
														".RCView::escape($Proj->forms[$form_name]['menu'])."
													</td>
													<td valign='top' class='header' valign='bottom' id='{$sub_field_name}___RAD1'>
														<p style='text-align:center;'>
															<input name='{$sub_field_name}___RADradio' checked value='' type='radio' onclick=\"
																dataCmpChk(1,'$sub_field_name','".htmlspecialchars($this_val1_orig, ENT_QUOTES)."');
															\">
															<input name='{$sub_field_name}___RAD1' value='".htmlspecialchars($this_val1_orig, ENT_QUOTES)."' type='hidden'>
														</p>
														<span class='compare' style='color:#800000;'>$this_val1</span>
													</td>
													<td valign='top' class='data' valign='bottom' id='{$sub_field_name}___RAD2'>
														<p style='text-align:center;'>
															<input name='{$sub_field_name}___RADradio' value='' type='radio' onclick=\"
																dataCmpChk(2,'$sub_field_name','".htmlspecialchars($this_val2_orig, ENT_QUOTES)."');
															\">
															<input name='{$sub_field_name}___RAD2' value='".htmlspecialchars($this_val2_orig, ENT_QUOTES)."' type='hidden'>
														</p>
														<span class='compare' style='color:#800000;'>$this_val2</span>
													</td>
													<td valign='top' class='data' valign='bottom' id='{$sub_field_name}___RAD3'>";

									//Build the field like it would appear normally on the data entry page
									switch($element_type) {
										case 'yesno':
											$select_choices = YN_ENUM;
										case 'truefalse':
											if ($element_type != 'yesno') $select_choices = TF_ENUM;
										case 'select':
										case 'radio':
										case 'checkbox':
										case 'advcheckbox':
											$diff .= "<select name=\"$sub_field_name" . "___RAD3\" disabled=\"disabled\" onchange=\"document.create_new.$sub_field_name.value=document.create_new.$sub_field_name" . "___RAD3.value\"><option value=\"\"></option>";
											foreach (parseEnum($select_choices) as $key=>$value) {
												$diff .= "<option value='$key'>$value</option>";
											}
											$diff .= "</select>";
											break;
										case 'textarea':
											$diff .= "<textarea name=\"$sub_field_name" . "___RAD3\" style='width:180px;height:100px' disabled=\"disabled\" onchange=\"document.create_new.$sub_field_name.value=document.create_new.$sub_field_name" . "___RAD3.value\"></textarea>";
											break;
										case 'file':
											break;
										case 'slider':
											// For sliders, a bit difficult to actually display an active slider here, so just setting it as an integer field with range limits
											$attr['element_validation_type'] = 'int';
											$attr['element_validation_min'] = '0';
											$attr['element_validation_max'] = '100';
											$attr['element_validation_checktype'] = 'hard';
										default:									
											//Get validation info, if any.
											$element_validation_type = $attr['element_validation_type'];
											$element_validation_min = $attr['element_validation_min'];
											$element_validation_max = $attr['element_validation_max'];
											$element_validation_checktype = $attr['element_validation_checktype'];
											$validation_string = "";
											if ($element_validation_type != "")
											{
												$validation_string = "onblur=\"redcap_validate(this,'$element_validation_min','$element_validation_max','$element_validation_checktype','".convertLegacyValidationType($element_validation_type)."',1);document.create_new.$sub_field_name.value=document.create_new.$sub_field_name" . "___RAD3.value;\"";
											}
											$diff .= "<input name=\"$sub_field_name" . "___RAD3\" size=\"30\" value=\"\" type=\"text\" $validation_string disabled=\"disabled\" onchange=\"document.create_new.$sub_field_name.value=document.create_new.$sub_field_name" . "___RAD3.value\">";
									}
									if ($element_type != 'file') {
										//Set the real field (which is hidden) with the value of the first record by default
										$diff .= "<p><input name='$sub_field_name" . "___RADradio' value='' type='radio' onclick=\"dataCmpChk(3,'$sub_field_name','".htmlspecialchars($this_val3, ENT_QUOTES)."');\"></p>";
									}
									$diff .= "<input name='$sub_field_name' value='".htmlspecialchars($this_val1_orig, ENT_QUOTES)."' type='hidden'>";
									$diff .= "</td></tr>";
								}
							}

						}

					}


					// Display table if there were any differences.
					if ($diff != "") {

						$display_string .= $diff;

						if (isset($_GET['merge'])) {
							$display_string .= "<tr>
													<td class='data' colspan=4>
													</td>
													<td class='data' align='center'>
														<input name='$table_pk' type='hidden' value='$record1'><br>
														<input type='button' value='{$lang['data_comp_tool_33']}' onclick=\"document.create_new.submit();\">
														<a href='".$_SERVER['REQUEST_URI']."' style='margin-left:20px;text-decoration:underline;font-size:11px;'>{$lang['global_53']}</a>
														<br>&nbsp;
													</td>
												</tr>";
						}

						$display_string .= "</table></form></div><br><br>";

						print $display_string;


					// If no differences, then give message.
					} else {

						print  "<hr size=1><font color=#800000><b>{$lang['data_comp_tool_34']} $record1 {$lang['global_43']} $record2
								{$lang['data_comp_tool_35']}</b></font> ";
						//Determine if merged file exists
						if (!$show_merged_record) {
							if ($double_data_entry) {
								print "<p>{$lang['data_comp_tool_37']} " . substr($record1, 0, -3) . $lang['data_comp_tool_39'] . "</p>
									  <form action=\"".APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID."&route=DataComparisonController:index&event_id=$event_id1&create_new=1\" method=\"post\" enctype=\"multipart/form-data\" target=\"_self\">
									  <input name=\"$table_pk\" type=\"hidden\" value=\"$record1\">
									  <input type=\"submit\" value=\"{$lang['data_comp_tool_38']} " . substr($record1, 0, -3) . "\"></form><p><br>";
							}
						} else {
							print "<font color=#800000><b>{$lang['data_comp_tool_41']} " . substr($record1, 0, -3) . " {$lang['data_comp_tool_42']}</b></font>";
						}

					}
					// Increment counter
					$loopNum++;
				}
			}
		}
	}
	
	//Function uses resource link from query to EAV formatted table and outputs an array
	//with keys as 'record' and sub-arrays with keys as 'field_name' and value as 'value'
	private static function eavDataArray($resource_link, $chkbox_fields = null) 
	{
		// If array with of checkbox fields (with field_name as key and default value options of "0" as sub-array values) is not provided, then build one
		if (!isset($chkbox_fields) || $chkbox_fields == null) {
			$sql = "select field_name from redcap_metadata where project_id = " . PROJECT_ID . " and element_type = 'checkbox'";
			$chkboxq = db_query($sql);
			$chkbox_fields = array();
			while ($row = db_fetch_assoc($chkboxq)) {
				// Add field to list of checkboxes and to each field add checkbox choices
				foreach (parseEnum($row['element_enum']) as $this_value=>$this_label) {
					$chkbox_fields[$row['field_name']][$this_value] = "0";
				}
			}
		}
		// Add data from data table to array
		$result = array();
		$chkbox_values = array();
		while ($row = db_fetch_array($resource_link)) {
			if (!isset($chkbox_fields[$row['field_name']])) {
				// Non-checkbox field
				$result[$row['record']][$row['field_name']] = $row['value'];
			} else {
				// If a checkbox
				$chkbox_values[$row['record']][$row['field_name']][$row['value']] = "1";
			}
		}
		// Now loop through each record. First add default "0" values for checkboxes, then overlay with any "1"s (actual checks from earlier)
		foreach (array_keys($result) as $this_record) {
			// First add default "0" values to each record
			foreach ($chkbox_fields as $this_fieldname=>$this_choice_array) {
				$result[$this_record][$this_fieldname] = $this_choice_array;
			}
			// Now loop through $chkbox_values to overlay any checked values (i.e. 1's)
			if(isset($chkbox_values[$this_record]))
			{
				foreach ($chkbox_values[$this_record] as $this_fieldname=>$this_choice_array) {
					foreach ($this_choice_array as $this_value=>$this_data_value) {
						// Make sure it's a real checkbox option and not some random data point that leaked in
						if (isset($chkbox_fields[$this_fieldname][$this_value])) {
							// Add checkbox data to data array
							$result[$this_record][$this_fieldname][$this_value] = $this_data_value;
						}
					}
				}
			}
		}
		return $result;
	}
}