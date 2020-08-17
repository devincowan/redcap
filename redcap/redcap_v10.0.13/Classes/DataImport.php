<?php

/**
 * DataImport
 * This class is used for processes related to reports and the Data Import Tool.
 */
class DataImport
{
	// Process uploaded Excel file, return references to (1) an array of fieldnames and (2) an array of items to be updated
	public static function csvToArray($csv_filepath, $format='rows')
	{
		global $lang, $table_pk, $longitudinal, $Proj, $user_rights, $project_encoding;

		// Extract data from CSV file and rearrange it in a temp array
		$newdata_temp = array();
		$found_pk = false;
		$i = 0;
		// Set commas as default delimiter (if can't find comma, it will revert to tab delimited)
		$delimiter 	  = ",";
		$removeQuotes = false;
		$resetKeys = false; // Set flag to reset array keys if any headers are blank

		// CHECKBOXES: Create new arrays with all checkbox fields and the translated checkbox field names
		$fullCheckboxFields = array();
		foreach (MetaData::getCheckboxFields(PROJECT_ID) as $field=>$value) {
			foreach ($value as $code=>$label) {
				$code = (Project::getExtendedCheckboxCodeFormatted($code));
				$fullCheckboxFields[$field . "___" . $code] = array('field'=>$field, 'code'=>$code);
			}
		}
		if (($handle = fopen($csv_filepath, "rb")) !== false)
		{
			// Loop through each row
			while (($row = fgetcsv($handle, 0, $delimiter)) !== false)
			{
				// Detect if all values are blank in row (so we can ignore it)
				$numRowValuesBlank = 0;

				if ($i == 0)
				{
					## CHECK DELIMITER
					// Determine if comma- or tab-delimited (if can't find comma, it will revert to tab delimited)
					$firstLine = implode(",", $row);
					// If we find X number of tab characters, then we can safely assume the file is tab delimited
					$numTabs = 0;
					if (substr_count($firstLine, "\t") > $numTabs)
					{
						// Set new delimiter
						$delimiter = "\t";
						// Fix the $row array with new delimiter
						$row = explode($delimiter, $firstLine);
						// Check if quotes need to be replaced (added via CSV convention) by checking for quotes in the first line
						// If quotes exist in the first line, then remove surrounding quotes and convert double double quotes with just a double quote
						$removeQuotes = (substr_count($firstLine, '"') > 0);
					}
				}

				// Find record identifier field
				if (!$found_pk)
				{
					if ($i == 0 && preg_replace("/[^a-z_0-9]/", "", $row[0]) == $table_pk) {
						$found_pk = true;
					} elseif ($i == 1 && preg_replace("/[^a-z_0-9]/", "", $row[0]) == $table_pk && $format == 'cols') {
						$found_pk = true;
						$newdata_temp = array(); // Wipe out the headers that already got added to array
						$i = 0; // Reset
					}
				}
				// Loop through each column in this row
				for ($j = 0; $j < count($row); $j++)
				{
					// If tab delimited, compensate sightly
					if ($delimiter == "\t")
					{
						// Replace characters
						$row[$j] = str_replace("\0", "", $row[$j]);
						// If first column, remove new line character from beginning
						if ($j == 0) {
							$row[$j] = str_replace("\n", "", ($row[$j]));
						}
						// If the string is UTF-8, force convert it to UTF-8 anyway, which will fix some of the characters
						if (function_exists('mb_detect_encoding') && mb_detect_encoding($row[$j]) == "UTF-8")
						{
							$row[$j] = utf8_encode($row[$j]);
						}
						// Check if any double quotes need to be removed due to CSV convention
						if ($removeQuotes)
						{
							// Remove surrounding quotes, if exist
							if (substr($row[$j], 0, 1) == '"' && substr($row[$j], -1) == '"') {
								$row[$j] = substr($row[$j], 1, -1);
							}
							// Remove any double double quotes
							$row[$j] = str_replace("\"\"", "\"", $row[$j]);
						}
					}
					// Reads as records in rows (default)
					if ($format == 'rows')
					{
						// Santize the variable name
						if ($i == 0) {
							$row[$j] = preg_replace("/[^a-zA-Z_0-9]/", "", $row[$j]);
							if ($row[$j] == '') {
								$resetKeys = true;
								continue;
							}
						} elseif (!isset($newdata_temp[0][$j]) || $newdata_temp[0][$j] == '') {
							continue;
						}
						// If value is blank, then increment counter
						if ($row[$j] == '') $numRowValuesBlank++;
						// Add to array
						$newdata_temp[$i][$j] = $row[$j];
						if ($project_encoding == 'japanese_sjis')
						{ // Use only for Japanese SJIS encoding
							$newdata_temp[$i][$j] = mb_convert_encoding($newdata_temp[$i][$j], 'UTF-8',  'sjis');
						}
					}
					// Reads as records in columns
					else
					{
						// Santize the variable name
						if ($j == 0) {
							$row[$j] = preg_replace("/[^a-zA-Z_0-9]/", "", $row[$j]);
							if ($row[$j] == '') {
								$resetKeys = true;
								continue;
							}
						} elseif ($newdata_temp[0][$i] == '') {
							continue;
						}
						$newdata_temp[$j][$i] = $row[$j];
						if ($project_encoding == 'japanese_sjis')
						{ // Use only for Japanese SJIS encoding
							$newdata_temp[$j][$i] = mb_convert_encoding($newdata_temp[$j][$i], 'UTF-8',  'sjis');
						}
					}
				}
				// If whole row is blank, then skip it
				if ($numRowValuesBlank == count($row)) {
					$resetKeys = true;
					unset($newdata_temp[$i]);
				}
				// Increment col counter
				$i++;
			}
			unset($row);
			fclose($handle);
		} else {
			// ERROR: File is missing
			$fileMissingText = (!SUPER_USER) ? $lang['period'] : " (".APP_PATH_TEMP."){$lang['period']}<br><br>{$lang['file_download_13']}";
			print 	RCView::div(array('class'=>'red'),
						RCView::b($lang['global_01'].$lang['colon'])." {$lang['file_download_08']} <b>\"".htmlspecialchars(basename($csv_filepath), ENT_QUOTES)."\"</b>
						{$lang['file_download_12']}{$fileMissingText}"
					);
			exit;
		}
		
		// If importing records as columns, remove any columns that are completely empty
		if ($format == 'cols') {
			$recCount = count($newdata_temp);
			for ($i=1; $i<$recCount; $i++) {
				// Set default for each record
				$recordEmpty = true;
				if (!isset($newdata_temp[$i])) continue;
				foreach ($newdata_temp[$i] as $val) {
					// If found a value, then skip to next record
					if ($val != '') {
						$recordEmpty = false;
						break;
					}
				}
				// Remove record
				if ($recordEmpty) {
					unset($newdata_temp[$i]);
				}
			}
			// If record count is now different, then re-index the array
			if ($recCount > count($newdata_temp)) {
				$newdata_temp = array_values($newdata_temp);
			}
		}

		// Give error message if record identifier variable name could not be found in expected places
		if (!$found_pk)
		{
			if ($format == 'rows') {
				$found_pk_msg = "{$lang['data_import_tool_134']} (\"$table_pk\") {$lang['data_import_tool_135']}";
			} else {
				$found_pk_msg = "{$lang['data_import_tool_134']} (\"$table_pk\") {$lang['data_import_tool_136']}";
			}
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}:</b><br>
						$found_pk_msg<br><br>
						{$lang['data_import_tool_76']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		// Shift the fieldnames  into a separate array called $fieldnames_new
		$fieldnames_new = array_shift($newdata_temp);

		//	Ensure that all record names are in proper UTF-8 format, if UTF-8 (no black diamond characters)
		if (function_exists('mb_detect_encoding')) {
			foreach ($newdata_temp as $key=>$row) {
				$this_record = $row[0];
				if (mb_detect_encoding($this_record) == 'UTF-8' && $this_record."" !== mb_convert_encoding($this_record, 'UTF-8', 'UTF-8')."") {
					// Convert to true UTF-8 to remove black diamond characters
					$newdata_temp[$key][0] = utf8_encode($this_record);
				}
			}
			unset($row);
		}

		// If any columns were removed, reindex the arrays so that none are missing
		if ($resetKeys) {
			// Reindex the header array
			$fieldnames_new = array_values($fieldnames_new);
			// Loop through ALL records and reindex each
			foreach ($newdata_temp as $key=>&$vals) {
				$vals = array_values($vals);
			}
		}

		// If longitudinal, get array key of redcap_event_name field
		if ($longitudinal) {
			$eventNameKey = array_search('redcap_event_name', $fieldnames_new);
		}

		// Check if DAGs exist
		$groups = $Proj->getGroups();

		// If has DAGs, try to find DAG field
		if (!empty($groups)) {
			$groupNameKey = array_search('redcap_data_access_group', $fieldnames_new);
		}
		
		// Determine if using repeating instances
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		$repeat_instance_index = $repeat_instrument_index = $importHasRepeatingFormsEvents = false;
		if ($hasRepeatingFormsEvents) {
			$repeat_instrument_index = array_search('redcap_repeat_instrument', $fieldnames_new);
			$repeat_instance_index = array_search('redcap_repeat_instance', $fieldnames_new);
			$importHasRepeatingFormsEvents = ($repeat_instance_index !== false);
		}

		## PUT ALL UPLOADED DATA INTO $updateitems
		$updateitems = $invalid_eventids = array();
		foreach ($newdata_temp as $i => $element)
		{
			// Trim the record name, just in case
			$newdata_temp[$i][0] = $element[0] = trim($element[0]);
			// Get event_id to add as subkey for record
			$event_id = ($longitudinal) ? $Proj->getEventIdUsingUniqueEventName($element[$eventNameKey]) : $Proj->firstEventId;
			if ($longitudinal && $event_id === false) {
				// Invalid unique event name was used.
				$invalid_eventids[] = $element[$eventNameKey];
				continue;
			}
			// Loop through data array and add each record values to $updateitems
			for ($j = 0; $j < count($fieldnames_new); $j++) {
				// Get this field and value
				$this_field = trim($fieldnames_new[$j]);
				$this_value = trim($element[$j]);
				// Skip if field is blank
				if ($this_field == "") continue;
				elseif ($this_field == "redcap_repeat_instance" || $this_field == "redcap_repeat_instrument") {
					if ($hasRepeatingFormsEvents) continue;
					else {
						// Stop if uploading repeating fields when project is not set to repeat forms/events
						print  "<div class='red' style='margin-bottom:15px;'>
									<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_252']}</b><br>
									{$lang['data_import_tool_253']}
								</div>";
						renderPrevPageLink("index.php?route=DataImportController:index");
						exit;
					}
				}
				// Is this row a repeating instance?
				$rowIsRepeatingInstance = false;
				if ($importHasRepeatingFormsEvents) {					
					$repeat_instance = $element[$repeat_instance_index];
					$repeat_instrument = $repeat_instrument_index ? $element[$repeat_instrument_index] : "";
					$rowIsRepeatingInstance = ($repeat_instance.$repeat_instrument."" != "");
				}
				if ($rowIsRepeatingInstance) {
					// Repeating instance
					if (isset($fullCheckboxFields[$this_field])) {
						// Checkbox
						$updateitems[$element[0]]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$fullCheckboxFields[$this_field]['field']][$fullCheckboxFields[$this_field]['code']] = $this_value;
					} else {
						// Non-checkbox
						$updateitems[$element[0]]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$this_field] = $this_value;
					}
				} else {
					// Regular non-repeating instance
					if (isset($fullCheckboxFields[$this_field])) {
						// Checkbox
						$updateitems[$element[0]][$event_id][$fullCheckboxFields[$this_field]['field']][$fullCheckboxFields[$this_field]['code']] = $this_value;
					} else {
						// Non-checkbox
						$updateitems[$element[0]][$event_id][$this_field] = $this_value;
					}
				}
			}
		}
		
		// Invalid unique event name was used.
		if (!empty($invalid_eventids)) 
		{
		    foreach ($invalid_eventids as &$val) $val = RCView::escape($val);
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['data_import_tool_254']}
						\"<b>".implode("</b>\", \"<b>", $invalid_eventids)."</b>\"
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		// If project has DAGs and redcap_data_access_group column is included and user is IN a DAG, then tell them they must remove the column
		if ($user_rights['group_id'] != '' && !empty($groups) && in_array('redcap_data_access_group', $fieldnames_new))
		{
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_171']}</b><br>
						{$lang['data_import_tool_172']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}
		// DAG check to make sure that a single record doesn't have multiple values for 'redcap_data_access_group'
		elseif ($user_rights['group_id'] == '' && !empty($groups) && $groupNameKey !== false)
		{
			// Creat array to collect all DAG designations for each record (each should only have one DAG listed)
			$dagPerRecord = array();
			foreach ($newdata_temp as $thisrow) {
				// Get record name
				$record = $thisrow[0];
				// Get DAG name for this row/record
				$dag = $thisrow[$groupNameKey];
				// Add to array
				$dagPerRecord[$record][$dag] = true;
			}
			unset($thisrow);
			// Now loop through all records and remove all BUT those with duplicates
			foreach ($dagPerRecord as $record=>$dags) {
				if (count($dags) <= 1) {
					unset($dagPerRecord[$record]);
				}
			}
			// If there records with multiple DAG designations, then stop here and throw error.
			if (!empty($dagPerRecord))
			{
				print  "<div class='red' style='margin-bottom:15px;'>
							<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_173']}</b><br>
							{$lang['data_import_tool_174']} <b>".implode("</b>, <b>", array_keys($dagPerRecord))."</b>{$lang['period']}
						</div>";
				renderPrevPageLink("index.php?route=DataImportController:index");
				exit;
			}
		}

		return $updateitems;
	}


	// Display errors/warnings in table format. Return HTML string.
	public static function displayErrorTable($errors, $warnings)
	{
		global $lang;
		$altrow = 1;
		$errortable =  "<br><table id='errortable'><tr><th scope=\"row\" class=\"comp_fieldname\" bgcolor=\"black\" colspan=4>
						<font color=\"white\">{$lang['data_import_tool_97']}</th></tr>
						<tr><th scope='col'>{$lang['global_49']}</th><th scope='col'>{$lang['data_import_tool_98']}</th>
						<th scope='col'>{$lang['data_import_tool_99']}</th><th scope='col'>{$lang['data_import_tool_100']}</th></tr>";
		foreach ($errors as $item) {
			$altrow = $altrow ? 0 : 1;
			$errortable .= $altrow ? "<tr class='alt'>" : "<tr>";
			$errortable .= "<th>".RCView::escape($item[0])."</th>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[1])."</td>";
			$errortable .= "<td class='comp_new_error'>".RCView::escape($item[2])."</td>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[3])."</td>";
		}
		foreach ($warnings as $item) {
			$altrow = $altrow ? 0 : 1;
			$errortable .= $altrow ? "<tr class='alt'>" : "<tr>";
			$errortable .= "<th>".RCView::escape($item[0])."</th>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[1])."</td>";
			$errortable .= "<td class='comp_new_warning'>".RCView::escape($item[2])."</td>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[3])."</td>";
		}
		$errortable .= "</table>";
		return $errortable;
	}


	// Display data comparison table
	public static function displayComparisonTable($updateitems, $format='rows')
	{
		global $lang, $table_pk, $user_rights, $longitudinal, $Proj;
		
		// Get record names being imported (longitudinal will not have true record name as array key
		$record_names = array();
		foreach (array_keys($updateitems) as $key) {
			list ($this_record, $nothing, $nothing, $nothing) = explode_right("-", $key, 4);
			$record_names[] = $this_record;
		}
		$record_names = array_values(array_unique($record_names));

		// Determine if imported values are a new or existing record by gathering all existing records into an array for reference
		$existing_records = array();
		foreach (Records::getRecordList($Proj->project_id, $user_rights['group_id'],false, false,null,null, 0, $record_names) as $this_record) {
			$existing_records[$this_record.""] = true;
		}

		$comparisontable = array();
		$rowcounter = 0;
		$columncounter = 0;

		//make "header" column (leftmost column) with fieldnames
		foreach ($updateitems as $studyevent) {
			foreach (array_keys($studyevent) as $fieldname) {
				if (isset($Proj->metadata[$fieldname]) && ($Proj->metadata[$fieldname]['element_type'] == 'calc' || $Proj->metadata[$fieldname]['element_type'] == 'file')) {
					continue;
				}
				$comparisontable[$rowcounter++][$columncounter] = "<th scope='row' class='comp_fieldname'>$fieldname</th>";
			}
			$columncounter++;
			break;
		}
		
		// If "Require Reason for Change" is enabled, then check which forms have data
		if ($GLOBALS['require_change_reason']) {
			$formStatusValues = Records::getFormStatus(PROJECT_ID, $record_names);
		}

		// Create array of all new records
		$newRecords = array();
		// Loop through all values
		foreach ($updateitems as $key=>$studyevent)
		{
			if (!isset($studyevent[$table_pk]['new'])) continue;
			list ($this_record, $this_event, $this_repeat_instrument, $this_instance) = explode_right("-", $key, 4);
			$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
			
			$rowcounter = 0;
			// Get record and evenet_id
			$studyid = $studyevent[$table_pk]['new'];
			$event_id = ($longitudinal) ? $Proj->getEventIdUsingUniqueEventName($studyevent['redcap_event_name']['new']) : $Proj->firstEventId;
			// Check if a new record or not
			$newrecord = !isset($existing_records[$studyid.""]);
			// Increment new record count
			if ($newrecord) $newRecords[] = $studyid;
			// Loop through fields/values
			foreach ($studyevent as $fieldname=>$studyrecord)
			{
				if (isset($Proj->metadata[$fieldname]) && ($Proj->metadata[$fieldname]['element_type'] == 'calc' || $Proj->metadata[$fieldname]['element_type'] == 'file')) {
					continue;
				}
				$this_form = $Proj->metadata[$fieldname]['form_name'];
				$this_form_instance = is_numeric($this_instance) ? $this_instance : 1;
				//print "<br>$studyid, $event_id, $fieldname: ".$updateitems[$key][$fieldname]['new'];
				if ($rowcounter == 0){ //case of column header (cells contain the record id)
					// Check if a new record or not
					$existing_status_class = '';
					if (isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') {
						$existing_status = "<div class='new_impt_rec'>({$lang['data_import_tool_268']})</div>";
					} elseif (!$newrecord) {
						$existing_status_class = 'exist_impt_rec';
						$existing_status = "<div class='$existing_status_class'>({$lang['data_import_tool_144']})</div>";
					} else {
						$existing_status = "<div class='new_impt_rec'>({$lang['data_import_tool_145']})</div>";
					}
					// Render record number as table header
					$comparisontable[$rowcounter][$columncounter] = "<th scope='col' class='comp_recid'><span id='record-{$columncounter}' class='$existing_status_class'>".htmlspecialchars($studyid, ENT_QUOTES)."</span>
																	 <span style='display:none;' id='event-{$columncounter}'>$event_id</span>$existing_status</th>";
				} else {
				//3 cases: new (+ errors or warnings), old, and update (+ errors or warnings)
					// Display redcap event name normally
					if (!(isset($updateitems[$key][$fieldname]))){
						$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>&nbsp;</td>";
					} else {
						if ($updateitems[$key][$fieldname]['status'] == 'add'){
							if (isset($updateitems[$key][$fieldname]['validation'])){
								//if error
								if ($updateitems[$key][$fieldname]['validation'] == 'error'){
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new_error'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								}
								elseif ($updateitems[$key][$fieldname]['validation'] == 'warning'){ //if warning
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new_warning'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								}
								else {
									//shouldn't be a case of this
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new'>problem!</td>";
								}
							}
							else{
								// If requiring reason for change, check if form has any data
								$formHasDataClass = "";
								if ($GLOBALS['require_change_reason']) {
									$formHasData = (isset($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]) && is_numeric($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]));
									if ($formHasData) $formHasDataClass = "form_has_data";
								}
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new $formHasDataClass'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
							}
						}
						elseif ($updateitems[$key][$fieldname]['status'] == 'keep'){
							if ($updateitems[$key][$fieldname]['old'] != ""){
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>" . RCView::escape($updateitems[$key][$fieldname]['old']) . "</td>";
							} else {
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>&nbsp;</td>";
							}
						}
						elseif ($updateitems[$key][$fieldname]['status'] == 'update' || $updateitems[$key][$fieldname]['status'] == 'delete'){
							if (isset($updateitems[$key][$fieldname]['validation'])){
								//if error
								if ($updateitems[$key][$fieldname]['validation'] == 'error'){
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update_error'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								} elseif ($updateitems[$key][$fieldname]['validation'] == 'warning'){ //if warning
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update_warning'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								} else {
									//shouldn't be a case of this
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new'>problem!</td>";
								}
							} else {
								// If requiring reason for change, check if form has any data
								$formHasDataClass = "";
								if ($GLOBALS['require_change_reason']) {
									$formHasData = (isset($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]) && is_numeric($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]));
									if ($formHasData) $formHasDataClass = "form_has_data";
								}
								// Show new and old value
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update $formHasDataClass'>"
																			  . RCView::escape($updateitems[$key][$fieldname]['new']);
								if (!$newrecord) {
									$comparisontable[$rowcounter][$columncounter] .= "<br><span class='comp_oldval'>("
																				  . RCView::escape($updateitems[$key][$fieldname]['old'])
																				  . ")</span>";
								}
								$comparisontable[$rowcounter][$columncounter] .= "</td>";
							}
						}
					}
				}
				$rowcounter++;
			}
			$columncounter++;
		}

		// Build table (format as ROWS)
		if ($format == 'rows')
		{
			$comparisonstring = "<table id='comptable'><tr><th scope='row' class='comp_fieldname' colspan='$rowcounter' bgcolor='black'><font color='white'><b>{$lang['data_import_tool_28']}</b></font></th></tr>";
			for ($rowi = 0; $rowi <= $columncounter; $rowi++)
			{
				$comparisonstring .= "<tr>";
				for ($colj = 0; $colj < $rowcounter; $colj++)
				{
					$comparisonstring .= isset($comparisontable[$colj][$rowi]) ? $comparisontable[$colj][$rowi] : '';
				}
				$comparisonstring .= "</tr>";
			}
			$comparisonstring .= "</table>";
		}
		// Build table (format as COLUMNS)
		else
		{
			$comparisonstring = "<table id='comptable'><tr><th scope='row' class='comp_fieldname' colspan='" . ($columncounter+1) . "' bgcolor='black'><font color='white'><b>{$lang['data_import_tool_28']}</b></font></th></tr>";
			foreach ($comparisontable as $rowi => $rowrecord)
			{
				$comparisonstring .= "<tr>";
				foreach ($rowrecord as $colj =>$cellpoint)
				{
					$comparisonstring .= $comparisontable[$rowi][$colj];
				}
				$comparisonstring .= "</tr>";
			}
			$comparisonstring .= "</table>";
		}

		// If user is not allowed to create new records, then stop here if new records exist in uploaded file
		if (!$user_rights['record_create'] && !empty($newRecords))
		{
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_import_tool_159']} <b>
						".implode("</b>, <b>", $newRecords)."</b>{$lang['period']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		return $comparisonstring;

	}
	
	// Download import template CSV file
	public function downloadCSVImportTemplate()
	{
		extract($GLOBALS);
		
		//Make column headers (COLUMN format only)
		$data = '';
		if ($_GET['format'] == 'cols') {
			$data = "Variable / Field Name";
			for ($k=1; $k<=20; $k++) {
				$data .= ",Record";
			}
			// Line break
			$data .= "\r\n";
		}

		// Check if DAGs exist. Add redcap_data_access_group field if DAGs exist AND user is not in a DAG
		$dags = $Proj->getGroups();
		$addDagField = (!empty($dags) && $user_rights['group_id'] == '');

		//Get the field names from metadata table
		$select =  "SELECT field_name, element_type, element_enum FROM redcap_metadata WHERE element_type != 'calc'
					and element_type != 'file' and element_type != 'descriptive' and project_id = $project_id ORDER BY field_order";
		$export = db_query($select);
		while ($row = db_fetch_array($export))
		{
			// If a checkbox field, then loop through choices to render pseudo field names for each choice
			if ($row['element_type'] == "checkbox")
			{
				foreach (array_keys(parseEnum($row['element_enum'])) as $this_value) {
					//Write data for each cell
					$data .= Project::getExtendedCheckboxFieldname($row['field_name'], $this_value);
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
				}
			}
			// Normal non-checkbox fields
			else
			{
				//Write data for each cell
				$data .= $row['field_name'];
				// Line break OR comma
				$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
			}
			// If we're on the first field and project is longitudinal, add redcap_event_name
			if ($row['field_name'] == $table_pk)
			{
				if ($longitudinal) {
					//Write data for each cell
					$data .= "redcap_event_name";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
				}
				if ($Proj->hasRepeatingFormsEvents()) {
					//Write data for each cell
					$data .= "redcap_repeat_instrument";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
					//Write data for each cell
					$data .= "redcap_repeat_instance";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
				}
				if ($addDagField) {
					//Write data for each cell
					$data .= "redcap_data_access_group";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? "," : "\r\n";
				}
			}
		}

		// Logging
		Logging::logEvent("","redcap_metadata","MANAGE",$project_id,"project_id = $project_id","Download data import template");

		// Begin output to file
		$file_name = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)."_ImportTemplate_".date("Y-m-d").".csv";
		header('Pragma: anytextexeptno-cache', true);
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=$file_name");

		// Output the data
		print $data;
	}
	
	// Render Data Import Tool page
	public function renderDataImportToolPage()
	{
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
			System::killConcurrentRequests(30);
		}

		extract($GLOBALS);
		
		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);

		renderPageTitle("<i class=\"fas fa-file-import\"></i> ".$lang['app_01']);

		// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
		$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
							   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");

		$doODM = (isset($_GET['type']) && $_GET['type'] == 'odm');
		$this_file = $_SERVER['REQUEST_URI'];
		$doMobileAppDataDump = (isset($_GET['doc_id']) && isset($_GET['doc_id_hash']));

		#Set official upload directory
		$upload_dir = APP_PATH_TEMP;
		if (!is_writeable($upload_dir)) {
			print "<br><br><div class='red'>
				<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}:</b><br>
				{$lang['data_import_tool_104']} <b>$upload_dir</b> {$lang['data_import_tool_105']}</div>";
			include APP_PATH_VIEWS . 'FooterProject.php';
			exit();
		}

		// Display instructions when initially viewing the page but not after uploading a file
		if (!isset($_REQUEST['submit']) && !isset($_POST['submit_events']) && !isset($_POST['updaterecs']))
		{
			//Print instructions
			print  "<div style='padding-right:10px;line-height:1.4em;max-width:700px;'>";

			//If user is in DAG, only show info from that DAG and give note of that
			$dagWarning = "";
			if ($user_rights['group_id'] != "") {
				$dagWarning = "<p style='color:#800000;'>{$lang['global_02']}{$lang['colon']} {$lang['data_import_tool_106']}</p>";
			}

			$devWarning = "";
			if ($status < 1) {
				$devWarning =  "<div class='yellow' style='margin:15px 0 15px;'>
									<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
									<b style='font-size:14px;'>{$lang['global_03']}{$lang['colon']}</b> {$lang['data_entry_28']}
								</div>";
			}

			print RCView::p(array(), $lang['data_import_tool_241']) . $dagWarning . $devWarning;
			// Tabs
			$tabs = array("index.php?route=DataImportController:index" =>	RCView::img(array('src'=>'csv.gif', 'style'=>'vertical-align:middle;')).
													RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_import_tool_242']),
						  "index.php?route=DataImportController:index&type=odm"=>RCView::img(array('src'=>'xml.png', 'style'=>'vertical-align:middle;')).
													RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_import_tool_243']));
			RCView::renderTabs($tabs);


			// CDISC import
			if ($doODM)
			{
				print  "	<p style='font-size:14px;font-weight:bold;margin-top:0;'>
								{$lang['global_24']}{$lang['colon']}
							</p>
							<p>
								{$lang['data_import_tool_248']} {$lang['data_import_tool_249']}
							</p>
							<p>
								<font color=#800000>{$lang['data_import_tool_17']}</font>
								{$lang['data_import_tool_19']}
								{$lang['data_import_tool_22']}
							</p>";
			}

			// CSV import
			else
			{
				print  "	<p style='font-size:14px;font-weight:bold;margin-top:0;'>
								{$lang['global_24']}{$lang['colon']}
							</p>
							<div style='text-indent:-1.5em;margin-left:2em;'>
								1.) <font color=#800000>{$lang['data_import_tool_02']}</font> {$lang['data_import_tool_03']}<br><br>
								<img src='".APP_PATH_IMAGES."xls.gif'>
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=rows' style='text-decoration:underline;'>{$lang['data_import_tool_04']}</a> {$lang['data_import_tool_05']}<br>
								&nbsp; &nbsp;OR<br>
								<img src='".APP_PATH_IMAGES."xls.gif'>
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=cols' style='text-decoration:underline;'>{$lang['data_import_tool_04']}</a> {$lang['data_import_tool_06']}<br>
								<br>
							</div>
							<div style='text-indent:-1.5em;margin-left:2em;'>
								2.) {$lang['data_import_tool_09']}<br>
									<div style='padding-top:5px;text-indent:-0.8em;margin-left:3em;'>&bull;&nbsp; {$lang['data_import_tool_10']}</div>
									<div style='padding-top:5px;text-indent:-0.8em;margin-left:3em;'>
									    &bull;&nbsp; {$lang['data_import_tool_11']} 
									    {$lang['data_import_tool_280']}
									    <a href='" . APP_PATH_WEBROOT . "Design/data_dictionary_codebook.php?pid=$project_id' style='text-decoration:underline;'>{$lang['design_482']}</a>{$lang['period']}
								    </div>
									<div style='padding-top:5px;text-indent:-0.8em;margin-left:3em;'>&bull;&nbsp; {$lang['data_import_tool_16']}</div>
									<br>
							</div>
							<div style='text-indent:-1.5em;margin-left:2em;'>
								3.) <font color=#800000>{$lang['data_import_tool_17']}</font>
									{$lang['data_import_tool_19']}<br><br>
							</div>
							<div style='text-indent:-1.5em;margin-left:2em;'>
								4.) {$lang['data_import_tool_22']}
							</div>
						</div>";

				// HELP SECTION for using redcap_data_access_group and redcap_event_name
				// If DAGs exist and user is NOT in a DAG, then give instructions on how to use redcap_data_access_group field
				$dags = $Proj->getGroups();
				$canAssignDags = (!empty($dags) && $user_rights['group_id'] == "");
				if ($longitudinal || $canAssignDags)
				{
					$html = "";
					if ($canAssignDags) {
						$html .= RCView::div(array('style'=>'font-weight:bold;font-size:12px;color:#3E72A8;'),
									RCView::img(array('src'=>'help.png')) .
									$lang['data_import_tool_176']
								) .
								$lang['data_import_tool_177'] . RCView::SP .
								RCView::a(array('style'=>'text-decoration:underline;','href'=>APP_PATH_WEBROOT."index.php?route=DataAccessGroupsController:index&pid=$project_id"), $lang['global_22']) . " " . $lang['global_14'] . $lang['period'];
					}
					if ($longitudinal && $canAssignDags) {
						$html .= RCView::div(array('class'=>'space'), '');
					}
					if ($longitudinal) {
						$html .= RCView::div(array('style'=>'font-weight:bold;font-size:12px;color:#3E72A8;'),
									RCView::img(array('src'=>'help.png')) .
									$lang['data_import_tool_178']
								) .
								$lang['data_import_tool_179'] . RCView::SP .
								RCView::a(array('style'=>'text-decoration:underline;','href'=>APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id"), $lang['global_16']) . " " . $lang['global_14'] . $lang['period'] . RCView::SP .
								$lang['data_import_tool_180'];
					}
					print RCView::div(array('style'=>'color:#333;background-color:#f5f5f5;border:1px solid #ccc;margin-top:15px;max-width:700px;padding:5px 8px 8px;'), $html);
				}
			}
		}


		## FILE UPLOAD FORM
		// CDISC import
		if ($doODM)
		{
			print  "<br><form action='$this_file' method='POST' name='form' enctype='multipart/form-data'>
					<div class='darkgreen' style='max-width:850px;padding:20px;'>
						<div id='uploadmain'>

							<div style='padding-bottom:3px;'>
								<b>{$lang['data_import_tool_231']}</b>&nbsp;
								<select name='overwriteBehavior' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;' onchange=\"
									if (this.value == 'normal') return;
									simpleDialog('".js_escape($lang['data_import_tool_236'])."','".js_escape($lang['survey_369'])."',null,null,function(){
										$('select[name=overwriteBehavior]').val('normal');
									},'".js_escape($lang['global_53'])."','','".js_escape($lang['rights_305'])."');
								\">
									<option value='normal' ".((!isset($_REQUEST['overwriteBehavior']) || $_REQUEST['overwriteBehavior'] == 'normal') ? "selected" : "").">{$lang['data_import_tool_245']}</option>
									<option value='overwrite' ".((isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? "selected" : "").">{$lang['data_import_tool_246']}</option>
								</select>
							</div>
							
							".(!$auto_inc_set ? "" : 
								"<div style='padding-bottom:3px;'>
									<b>{$lang['data_import_tool_265']}</b><a href='javascript:;' class='help' onclick=\"simpleDialog('".js_escape($lang['data_import_tool_273'])."','".js_escape($lang['data_import_tool_265'])."',null,550);\">?</a>&nbsp;
									<select name='forceAutoNumber' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;'>
										<option value='0' ".((!isset($_REQUEST['forceAutoNumber']) || $_REQUEST['forceAutoNumber'] == '0') ? "selected" : "").">{$lang['data_import_tool_266']}</option>
										<option value='1' ".((isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') ? "selected" : "").">{$lang['data_import_tool_267']}</option>
									</select>
								</div>"
							)."

							<div style='padding-top:18px;font-weight:bold;padding-bottom:5px;'><img src='".APP_PATH_IMAGES."xml.png'>
								{$lang['data_import_tool_244']}
							</div>
							<input type='file' name='uploadedfile' size='50'>
							<div style='padding-top:5px;'>
								<input type='submit' id='submit' name='submit' value='{$lang['data_import_tool_20']}' onclick=\"
									if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
										simpleDialog('".js_escape($lang['data_import_tool_114'])."');
										return false;
									}
									var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
									if (file_ext != 'xml') {
										simpleDialog('".js_escape($lang['data_import_tool_269'])."');
										return false;
									}
									document.getElementById('uploadmain').style.display='none';
									document.getElementById('progress').style.display='block';\">
							</div>
						</div>
						<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
							<table cellpadding=10><tr>
							<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
							<td valign=top style='padding-top:20px;'>
								<b>{$lang['data_import_tool_44']}</b><br>{$lang['data_import_tool_45']}<br>{$lang['data_import_tool_46']}</td>
							</tr></table>
						</div>
					</div>
					</form>";
		}

		// CSV import
		elseif (!$doMobileAppDataDump)
		{
			print  "<br><form action='$this_file' method='POST' name='form' enctype='multipart/form-data'>
					<div class='darkgreen' style='max-width:850px;padding:20px;'>
						<div id='uploadmain'>

							<div style='padding-bottom:3px;'>
								<b>{$lang['data_import_tool_110']}</b> {$lang['data_import_tool_111']}&nbsp;
								<select name='format' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;'>
									<option value='rows' ".((!isset($_REQUEST['format']) || $_REQUEST['format'] == 'rows') ? "selected" : "").">{$lang['data_import_tool_112']}</option>
									<option value='cols' ".(( isset($_REQUEST['format']) && $_REQUEST['format'] == 'cols') ? "selected" : "").">{$lang['data_import_tool_113']}</option>
								</select>
							</div>

							<div style='padding-bottom:3px;'>
								<b>{$lang['data_import_tool_186']}</b>&nbsp;
								<select name='date_format' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;'>
									<option value='MDY' ".((!isset($_REQUEST['date_format']) && DateTimeRC::get_user_format_base() != 'DMY') || (isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'MDY') ? "selected" : "").">MM/DD/YYYY {$lang['global_47']} YYYY-MM-DD</option>
									<option value='DMY' ".((!isset($_REQUEST['date_format']) && DateTimeRC::get_user_format_base() == 'DMY') || (isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'DMY') ? "selected" : "").">DD/MM/YYYY {$lang['global_47']} YYYY-MM-DD</option>
								</select>
							</div>

							<div style='padding-bottom:3px;'>
								<b>{$lang['data_import_tool_231']}</b>&nbsp;
								<select name='overwriteBehavior' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;' onchange=\"
									if (this.value == 'normal') return;
									simpleDialog('".js_escape($lang['data_import_tool_236'])."','".js_escape($lang['survey_369'])."',null,null,function(){
										$('select[name=overwriteBehavior]').val('normal');
									},'".js_escape($lang['global_53'])."','','".js_escape($lang['rights_305'])."');
								\">
									<option value='normal' ".((!isset($_REQUEST['overwriteBehavior']) || $_REQUEST['overwriteBehavior'] == 'normal') ? "selected" : "").">{$lang['data_import_tool_245']}</option>
									<option value='overwrite' ".((isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? "selected" : "").">{$lang['data_import_tool_246']}</option>
								</select>
							</div>
							
							".(!$auto_inc_set ? "" : 
								"<div style='padding-bottom:3px;'>
									<b>{$lang['data_import_tool_265']}</b><a href='javascript:;' class='help' onclick=\"simpleDialog('".js_escape($lang['data_import_tool_273'])."','".js_escape($lang['data_import_tool_265'])."',null,550);\">?</a>&nbsp;
									<select name='forceAutoNumber' class='x-form-text x-form-field' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;'>
										<option value='0' ".((!isset($_REQUEST['forceAutoNumber']) || $_REQUEST['forceAutoNumber'] == '0') ? "selected" : "").">{$lang['data_import_tool_266']}</option>
										<option value='1' ".((isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') ? "selected" : "").">{$lang['data_import_tool_267']}</option>
									</select>
								</div>"
							)."
							
							<div style='padding-top:18px;font-weight:bold;padding-bottom:5px;'><img src='".APP_PATH_IMAGES."xls.gif'>
								{$lang['data_import_tool_23']}
							</div>
							<input type='file' name='uploadedfile' size='50'>
							<div style='padding-top:5px;'>
								<input type='submit' id='submit' name='submit' value='{$lang['data_import_tool_20']}' onclick=\"
									if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
										simpleDialog('".js_escape($lang['data_import_tool_114'])."');
										return false;
									}
									var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
									if (file_ext != 'csv') {
										$('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, zIndex: 3999, buttons: {
											Close: function() { $(this).dialog('close'); }
										}});
										return false;
									}
									document.getElementById('uploadmain').style.display='none';
									document.getElementById('progress').style.display='block';
									$('#comptable, #center div.red:last, #center div.blue:last, #center div.green:last').remove();\">
							</div>
						</div>
						<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
							<table cellpadding=10><tr>
							<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
							<td valign=top style='padding-top:20px;'>
								<b>{$lang['data_import_tool_44']}</b><br>{$lang['data_import_tool_45']}<br>{$lang['data_import_tool_46']}</td>
							</tr></table>
						</div>
					</div>
					</form>";
		}

		// Div for displaying popup dialog for file extension mismatch (i.e. if XLS or other)
		?>
		<br><br>
		<div id="filetype_mismatch_div" title="<?php echo js_escape2($lang['random_12']) ?>" style="display:none;">
			<p>
				<?php echo $lang['data_import_tool_160'] ?>
				<a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
					style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
				<?php echo $lang['data_import_tool_117'] ?>
			</p>
			<p>
				<b style="color:#800000;"><?php echo $lang['data_import_tool_110'] ?></b><br>
				<?php echo $lang['data_import_tool_118'] ?>
			</p>
		</div>
		<?php





		###############################################################################
		# This page has 3 states:
		# (1) plain page shows "browse..." textbox and upload button.
		# (2) 'submit' -- user has just uploaded an Excel file. page parses the file, validates the data, and displays an error table or a "Data is okay, do you want to commit?" button
		# (3) 'updaterecs' -- user has chosen to update records. page re-parses previously uploaded Excel file (to avoid passing SQL from page to page) and executes  SQL to update the project.
		###############################################################################

		# Check if a file has been submitted
		if (!isset($_REQUEST['updaterecs']) && (isset($_REQUEST['submit']) || isset($_POST['submit_events'])))
		{
			// Mobile App data dump
			$doc_id_file_name = "";
			if (isset($_REQUEST['doc_id']) && isset($_REQUEST['doc_id_hash']) && ($_REQUEST['doc_id_hash'] == Files::docIdHash($_REQUEST['doc_id'])))
			{
				// Copy the file into temp, which is where it is expected
				$doc_id_file_name = Files::copyEdocToTemp($_REQUEST['doc_id'], true, true);
			}
			
			// Uploading first time
			if (isset($_REQUEST['submit'])) {

				foreach ($_POST as $key=>$value) {
					$_POST[$key] = db_escape($value);
				}

				# Save the file details that are passed to the page in the _FILES array
				foreach ($_FILES as $fn=>$f) {
					$$fn = $f;
					foreach ($f as $k=>$v) {
						$name = $fn . "_" . $k;
						$$name = $v;
					}
				}

				if ($doc_id_file_name != "") {
					$uploadedfile_name = $doc_id_file_name;
				}

				# If filename is blank, reload the page
				if ($uploadedfile_name == "") {
					redirect(PAGE_FULL."?".$_SERVER['QUERY_STRING']);
					exit;
				}

				// Check the field extension
				$filetype = strtolower(substr($uploadedfile_name,strrpos($uploadedfile_name,".")+1,strlen($uploadedfile_name)));
				$msg = "";
				if ($doODM) {
					// If uploaded anything other than XML for ODM
					if ($filetype != "xml") $msg = $lang['data_import_tool_247'];
				} elseif (!$doODM && $filetype != "csv") {
					// If uploaded as XLSX or CSV, tell user to save as XLS and re-uploade
					if ($filetype == "xls" || $filetype == "xlsx") {
						$msg = $lang['design_135'];
					} else {
						$msg = $lang['data_import_tool_47'];
					}
				}
				if ($msg != "") {
					// Display error message
					print  '<div class="red" style="margin:30px 0;">
								<img src="'.APP_PATH_IMAGES.'exclamation.png"> <b>'.$lang['global_01'].$lang['colon'].'</b><br>'.$msg.'
							</div>';
					include APP_PATH_VIEWS . 'FooterProject.php';
					exit;
				}

				if (!$doMobileAppDataDump) 
				{
					# If Excel file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
					$uploadedfile_name = date('YmdHis') . "_" . $app_name . "_import_data." . $filetype;
					$uploadedfile_name = str_replace("\\", "\\\\", $upload_dir . $uploadedfile_name);
					# If moving or copying the uploaded file fails, print error message and exit
					if (!move_uploaded_file($uploadedfile_tmp_name, $uploadedfile_name))
					{
						if (!copy($uploadedfile_tmp_name, $uploadedfile_name))
						{
							print '<p><br><table width=100%><tr><td class="comp_new_error"><font color=#800000>' .
								 "<b>{$lang['data_import_tool_48']}</b><br>{$lang['data_import_tool_49']} $project_contact_name " .
								 "{$lang['global_15']} <a href=\"mailto:$project_contact_email\">$project_contact_email</a> {$lang['data_import_tool_50']}</b></font></td></tr></table>";
							include APP_PATH_VIEWS . 'FooterProject.php';
							exit;
						}
					}
				}

			## If longitudinal and submitting second time with Events selected
			} elseif (isset($_POST['submit_events'])) {

				$uploadedfile_name = $_REQUEST['fname'];

			}

			# Process uploaded Excel file
			// Set parameters for saveData()
			$forceAutoNumber = ($auto_inc_set && isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1');
			$overwriteBehavior = (isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? 'overwrite' : 'normal';
			$saveDataFormat = $doODM ? 'odm' : 'array';
			$dateFormat = $doODM ? 'YMD' : $_REQUEST['date_format'];
			if ($doODM) $_REQUEST['format'] = 'rows';
			$importData = $doODM ? file_get_contents($uploadedfile_name) : DataImport::csvToArray($uploadedfile_name, $_REQUEST['format']);
		
			// Do test import to check for any errors/warnings
			$result = Records::saveData($saveDataFormat, $importData, $overwriteBehavior, $dateFormat, 'flat',
										$user_rights['group_id'], true, true, false, false, true, array(), true, !$doODM, false, $forceAutoNumber);
			// Check if error occurred
			$warningcount = count($result['warnings']);
			$errorcount = count($result['errors']);
			$warnings = $errors = array();
			if ($errorcount > 0) {
				// Parse errors: Save as CSV file in order to parse it
				$filename = APP_PATH_TEMP . date('YmdHis') . "_csv_" . substr(sha1(rand()), 0, 6) . ".csv";
				file_put_contents($filename, implode("\n", $result['errors']));
				if (($handle = fopen($filename, "rb")) !== false) {
					while (($row = fgetcsv($handle, 0, ",")) !== false) {
						$errors[] = $row;
					}
					fclose($handle);
				}
				unlink($filename);
			} elseif ($warningcount > 0) {
				// Parse warnings: Save as CSV file in order to parse it
				$filename = APP_PATH_TEMP . date('YmdHis') . "_csv_" . substr(sha1(rand()), 0, 6) . ".csv";
				file_put_contents($filename, implode("\n", $result['warnings']));
				if (($handle = fopen($filename, "rb")) !== false) {
					while (($row = fgetcsv($handle, 0, ",")) !== false) {
						$warnings[] = $row;
					}
					fclose($handle);
				}
				unlink($filename);
			}

			// If there are any errors or warnings, display the table and message
			if (($errorcount + $warningcount) > 0)
			{
				// If any errors, automatically delete the uploaded file on the server.
				if ($errorcount > 0 && !$doMobileAppDataDump) {
					unlink($uploadedfile_name);
				}

				$usermsg = "<br>
							<div class='".($errorcount > 0 ? 'red' : 'yellow')."'>
								<img src='".APP_PATH_IMAGES."".($errorcount > 0 ? 'exclamation.png' : 'exclamation_orange.png')."'>
								<b>".($errorcount > 0 ? $lang['data_import_tool_51'] : $lang['data_import_tool_237'])."</b>";

				if ($errorcount + $warningcount > 1){
					$usermsg .= "<br><br>{$lang['data_import_tool_52']} ";
				} else {
					$usermsg .= "<br><br>{$lang['data_import_tool_53']} ";
				}

				if ($errorcount > 1){
					$usermsg .= $errorcount . " {$lang['data_import_tool_54']} {$lang['data_import_tool_56']} ";
				}else if ($errorcount == 1){
					$usermsg .= $errorcount . " {$lang['data_import_tool_41']} {$lang['data_import_tool_56']} ";
				}

				if (($errorcount > 0)&&($warningcount > 0)){
						$usermsg .= " {$lang['global_43']} ";
				}

				if ($warningcount > 1){
					$usermsg .= $warningcount . " {$lang['data_import_tool_58']} {$lang['data_import_tool_60']} ";
				}else if ($warningcount == 1){
					$usermsg .= $warningcount . " {$lang['data_import_tool_43']} {$lang['data_import_tool_60']} ";
				}

					$usermsg .= " {$lang['data_import_tool_61']} ";

					if ($errorcount > 0){
						$usermsg .= " {$lang['data_import_tool_62']}";
					} else {
						$usermsg .= " {$lang['data_import_tool_63']}";
					}

				$usermsg .= "</div><br>";
				print $usermsg;
				// Create the error/warning table to display (if any errors/warnings exist)
				print self::displayErrorTable($errors, $warnings);

			} else {
				//Display confirmation that file was uploaded successfully
				print  "<br>
						<div class='green' style='padding:10px 10px 13px;'>
							<img src='".APP_PATH_IMAGES."accept.png'>
							<b>{$lang['data_import_tool_24']}</b><br>
							{$lang['data_import_tool_24b']}<br>
						</div>";
			}


			### Instructions and Key for Data Display Table
			if ($errorcount == 0)
			{
				print  "<div class='blue' style='font-size:12px;margin:25px 0;'>
							<b style='font-size:15px;'>{$lang['data_import_tool_102']}</b><br><br>
							{$lang['data_import_tool_25']}<br><br>
							<table style='background-color:#FFF;color:#000;font-size:11px;border:1px;'>
								<tr><th scope='row' class='comp_fieldname' style='background-color:#000;color:#FFF;font-size:11px;'>
									{$lang['data_import_tool_33']}
								</th></tr>
								<tr><td class='comp_update' style='background-color:#FFF;font-size:11px;'>
									{$lang['data_import_tool_35']} = {$lang['data_import_tool_36']}
								</td></tr>
								<tr><td class='comp_old' style='background-color:#FFF;font-size:11px;'>
									{$lang['data_import_tool_37']} = {$lang['data_import_tool_38']}
								</td></tr>
								<tr><td class='comp_old' style='font-size:11px;'>
									<span class='comp_oldval'>{$lang['data_import_tool_27']} = {$lang['data_import_tool_39']}</span>
								</td></tr>
								<tr><td class='comp_new_error' style='font-size:11px;'>
									{$lang['data_import_tool_40']} = {$lang['data_import_tool_41']}
								</td></tr>
								<tr><td class='comp_new_warning' style='font-size:11px;'>
									{$lang['data_import_tool_42']} = {$lang['data_import_tool_43']}
								</td></tr>
							</table>
						</div>";

				// Render Data Disply table
				print DataImport::displayComparisonTable($result['values'], $_REQUEST['format']);

				// Using jQuery, manually add "data change reason" text boxes for each record, if option is enabled
				if ($require_change_reason)
				{
					?>
					<script type="text/javascript">
					$(function(){

						// Set up functions and variables
						function renderReasonBox(record_count) {
							return "<td class='yellow' style='border:1px solid gray;width:210px;'>"
								 + "<textarea id='reason-"+record_count+"' onblur=\"charLimit('reason-"+record_count+"',200)\" class='change_reason x-form-textarea x-form-field' style='width:200px;height:60px;'></textarea></td>"
						}
						var reason_hdr = "<th class='yellow' style='color:#800000;border:1px solid gray;font-weight:bold;'><?php echo $lang['data_import_tool_132'] ?></th>";
						var new_rec_td = "<td class='comp_new'> </td>";
						var record_count = 1;

					<?php if (isset($_REQUEST['format']) && $_REQUEST['format'] == 'rows') {?>

						// Row data format
						$(".comp_recid").each(function() {
							var hasDataChanges = ($(this).find('div.exist_impt_rec').length && $(this).parent().find('.comp_new.form_has_data, .comp_update.form_has_data').length > 0);
							if (hasDataChanges) { // only for existing records with at least 1 value changed
								$(this).after(renderReasonBox(record_count));
							} else {
								$(this).after(new_rec_td);
							}
							record_count++;
						});
						$("#comptable").find('th').filter(':nth-child(2)').before(reason_hdr);

					<?php } else { ?>

						// Column data format
						var reasonRow = "";
						$(".comp_recid").each(function() {
							var hasDataChanges = ($(this).find('div.exist_impt_rec').length && $(this).parent().find('.comp_new.form_has_data, .comp_update.form_has_data').length > 0);
							reasonRow += hasDataChanges ? renderReasonBox(record_count) : new_rec_td; // only for existing records with at least 1 value changed
							record_count++;
						});
						var rows = document.getElementById('comptable').tBodies[0].rows;
						$(rows[1]).after("<tr>"+reason_hdr+reasonRow+"</tr>");

					<?php } ?>

					});
					</script>
					<?php
				}

				print  "<br><br>";

				// If ALL fields are old, then there's no need to update anything
				$field_counter = 0;
				$old_counter = 0;
				foreach ($result['values'] as $studyid => $studyrecord) {
					foreach ($studyrecord as $fieldname => $datapoint){
						if (isset($Proj->metadata[$fieldname]) && ($Proj->metadata[$fieldname]['element_type'] == 'calc' || $Proj->metadata[$fieldname]['element_type'] == 'file')) {
							continue;
						}
						if ($datapoint['status'] == 'keep') {
							$old_counter++;
						}
						$field_counter++;
					}
				}
				if ($field_counter != $old_counter) 
				{
					$doc_id_text = "";
					if (isset($_REQUEST['doc_id']))
					{
						$doc_id_text = (($doc_id_name != "") ? "<input type='text' id='doc_id' name='doc_id' value='".$_REQUEST['doc_id']."'><input type='hidden' id='doc_id_hash' name='doc_id_hash' value='".Files::docIdHash($_REQUEST['doc_id'])."'>" : "");
					}
					// Button for committing to import
					print  "<div id='commit_import_div' class='darkgreen' style='max-width:850px;padding:20px;'>
								<form action='$this_file' method='post' id='form' name='form' enctype='multipart/form-data'>
								<div id='uploadmain2'>
									<b>{$lang['data_import_tool_66']}</b><br>{$lang['data_import_tool_67']}
									<input type='hidden' name='fname' value='".htmlspecialchars($uploadedfile_name, ENT_QUOTES)."'> " . $doc_id_text . "
									<input type='hidden' id='event_string' name='event_string' value='" . (isset($_POST['event_string']) ? $_POST['event_string'] : '') . "'>
									<input type='hidden' name='format' value='".((isset($_REQUEST['format']) && $_REQUEST['format'] == 'cols') ? "cols" : "rows")."'>
									<input type='hidden' name='date_format' value='".((isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'DMY') ? "DMY" : "MDY")."'>
									<input type='hidden' name='overwriteBehavior' value='$overwriteBehavior'>
									<input type='hidden' name='forceAutoNumber' value='$forceAutoNumber'>
									<div style='padding-top:10px;'>
										<input type='submit' name='updaterecs' value='{$lang['data_import_tool_29']}' onclick='return importDataSubmit($require_change_reason);'>
										<a href='{$_SERVER['REQUEST_URI']}' style='margin-left:15px;'>{$lang['global_53']}</a>
									</div>
									<div id='change-reasons-div' style='display:none;'></div>
								</div>
								<div id='progress2' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
									<table cellpadding=10><tr>
										<td valign=top>
											<img src='" . APP_PATH_IMAGES . "progress.gif'>
										</td>
										<td valign=top style='padding-top:20px;'>
											<b>{$lang['data_import_tool_64']}<br>{$lang['data_import_tool_65']}</b><br>
											{$lang['data_import_tool_46']}
										</td>
									</tr></table>
								</div>
								</form>
							</div>";

				} else {

					//Message saying that there are no new records (i.e. all the uploaded records already exist in project)
					//Button for committing to record import
					print  "<div id='commit_import_div' class='red' style='padding:20px;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png'>
								<b>{$lang['data_import_tool_68']}</b><br>";
								if (isset($_REQUEST['doc_id'])) {
									print $lang['mobile_app_117']."<br><br>".RCView::a(array("href"=>APP_PATH_WEBROOT."MobileApp/index.php?files=1&pid=".PROJECT_ID), $lang['mobile_app_115']);
								} else {
									print $lang['data_import_tool_69'];
								}
					print "</div>";

					//Delete the uploaded file from the server since its data cannot be imported
					unlink($uploadedfile_name);
				}

			}

			print "<br><br><br>";

		}









		/**
		 * USER CLICKED "IMPORT DATA" BUTTON
		 */
		elseif (isset($_REQUEST['updaterecs']))
		{
			// If submitted "change reason" then reconfigure as array with record as key to add to logging.
			$change_reasons = array();
			if ($require_change_reason && isset($_POST['records']) && isset($_POST['events']) && isset($_POST['reasons']))
			{
				foreach ($_POST['records'] as $this_key=>$this_record)
				{
					$event_id = $_POST['events'][$this_key];
					$change_reasons[$this_record][$event_id] = $_POST['reasons'][$this_key];
				}
				unset($_POST['records'],$_POST['reasons'],$_POST['events']);
			}

			// Process uploaded Excel file
			$uploadedfile_name = $_POST['fname'];

			// Set parameters for saveData()
			$forceAutoNumber = ($auto_inc_set && isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1');
			$overwriteBehavior = (isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? 'overwrite' : 'normal';
			$saveDataFormat = $doODM ? 'odm' : 'array';
			$dateFormat = $doODM ? 'YMD' : $_REQUEST['date_format'];
			if ($doODM) $_REQUEST['format'] = 'rows';
			$importData = $doODM ? file_get_contents($uploadedfile_name) : DataImport::csvToArray($uploadedfile_name, $_REQUEST['format']);
			// Do test import to check for any errors/warnings
			$result = Records::saveData($saveDataFormat, $importData, $overwriteBehavior, $dateFormat, 'flat',
										$user_rights['group_id'], true, true, true, false, true, $change_reasons, false, !$doODM, false, $forceAutoNumber);

			// Count records added/updated
			$numRecordsImported = count($result['ids']);

			// Delete the uploaded file from the server now that its data has been imported
			unlink($uploadedfile_name);

			// Give user message of successful import
			print  "<br><br>
					<div class='green' style='padding-top:10px;'>
						<img src='".APP_PATH_IMAGES."accept.png'> <b>{$lang['data_import_tool_133']}</b>
						<span style='font-size:16px;color:#800000;margin-left:8px;margin-right:1px;font-weight:bold;'>".User::number_format_user($numRecordsImported)."</span>
						<span style='color:#800000;'>".($numRecordsImported == '1' ? $lang['data_import_tool_183'] : $lang['data_import_tool_184'])."</span>
						<br><br>";
			if (isset($_REQUEST['doc_id'])) {
				print $lang['mobile_app_116']."<br><br>".RCView::a(array("href"=>APP_PATH_WEBROOT."MobileApp/index.php?files=1&pid=".PROJECT_ID), $lang['mobile_app_115']);
			} else {
				print $lang['data_import_tool_70'];
			}
			if ($forceAutoNumber && !empty($result['ids'])) {
				print "<br><br>".$lang['data_import_tool_270']."<br>";
				print "<table id='comptable' style='background-color:#fff;margin-top:10px;'>";
				print "<tr><td class='comp_new'><b>".$lang['data_import_tool_271']."</b></th><td class='comp_new'><b>".$lang['data_import_tool_272']."</b></th></tr>";
				foreach ($result['ids'] as $origId=>$savedId) {
					print "<tr><td class='comp_new'>$origId</td><td class='comp_new'>$savedId</td></tr>";
				}
				print "</table>";
			}
			print "</div>";

		}
	}
}