<?php

class MetaData
{
	
	public static function getFields2($projectId, $fields)
	{
		$sql = "SELECT field_name, element_enum, element_type, element_validation_type, element_validation_min,
					element_validation_max, element_validation_checktype
				FROM redcap_metadata
				WHERE project_id = $projectId";
		$sql_fields = " AND field_name IN ('". implode("','", $fields) ."')";
		// If query is very long, then do not include "field_name IN" part
		if (strlen($sql.$sql_fields) > 1000000) {
            $checkFieldNameEachLoop = true;
        } else {
		    $sql .= $sql_fields;
            $checkFieldNameEachLoop = false;
        }
		$rsData = db_query($sql);

		$metaData = array();
		while ($row = db_fetch_assoc($rsData))
		{
            // If we need to validate the field name in each loop, then check.
            if ($checkFieldNameEachLoop && !in_array($row['field_name'], $fields)) continue;

			if ($row['element_enum'] != "")
			{
				// Parse MC fields for dropdowns and radios, but retrieve valid enum from "sql" field queries
				if ($row['element_type'] == "sql")
				{
					$row['element_enum'] = getSqlFieldEnum($row['element_enum'], $projectId);
				}
				$row['enums'] = parseEnum($row['element_enum']);
			}
			elseif ($row['element_type'] == "yesno")
			{
				$row['element_enum'] = "1, Yes \\n 0, No";
				$row['enums'] = parseEnum($row['element_enum']);
				$row['element_type'] = "radio";
			}
			elseif ($row['element_type'] == "truefalse")
			{
				$row['element_enum'] = "1, True \\n 0, False";
				$row['enums'] = parseEnum($row['element_enum']);
				$row['element_type'] = "radio";
			}
			elseif ($row['element_type'] == "slider")
			{
				$row['element_type'] = "text";
				$row['element_validation_type'] = "int";
				$row['element_validation_min'] = 0;
				$row['element_validation_max'] = 100;
				$row['element_validation_checktype'] = "hard";
			}

			$metaData[$row['field_name']] = $row;
		}

		return $metaData;
	}

	public static function getFields($projectId, $longitudinal, $primaryKey, $isChild, $hasSurveys, $fields = array(), $rawOrLabel='raw', $displayDags=false, $displaySurveyFields=false)
	{
		global $Proj;

		$fieldData = array();
		$fieldNames = array();
		$fieldDefaults = array();
		$fieldTypes = array();
		$fieldValidationTypes = array();
		$fieldPhis = array();
		$fieldEnums = array();

		# create list of fields for sql statement
		$fieldList = "'" . implode("','", $fields) . "'";

		# if the primary key field was not passed in, add it
		if (count($fields) > 0)
		{
			$keys = array_flip($fields);
			if ( !array_key_exists($primaryKey, $keys) ) {
				$fieldList = "'".$primaryKey."',".$fieldList;
			}
		}

		#create sql statement for fields
		$fieldSql = (count($fields) > 0) ? "AND field_name IN ($fieldList)" : '';

		//Get all Checkbox field choices to use for later looping (so we know how many choices each checkbox question has)
		$checkboxFields = MetaData::getCheckboxFields($projectId, true);

		if (!$isChild) // normal
		{
			$sql = "SELECT field_name, element_type, element_enum, form_name, element_validation_type, field_phi
					FROM redcap_metadata
					WHERE project_id = $projectId $fieldSql AND element_type != 'descriptive'
					ORDER BY field_order";
		}
		else // parent/child linking exists
		{
			$sql = "SELECT field_name, element_type, form_name, element_validation_type, field_phi, field_order, tbl
					FROM ((SELECT field_name, element_type, form_name, element_validation_type, field_phi, field_order, 1 as tbl
						FROM redcap_metadata
						WHERE project_id = $project_id_parent $fieldSql AND element_type != 'descriptive')
					UNION (SELECT field_name, element_type, form_name, element_validation_type, field_phi, field_order, 2 as tbl
						FROM redcap_metadata
						WHERE project_id = $projectId $fieldSql AND element_type != 'descriptive')) as x
					ORDER BY tbl, field_order";
		}

		$prev_form = "";
		$prev_field = "";

		$q = db_query($sql);
		while($row = db_fetch_assoc($q))
		{
			// If starting a new form and form is a survey, then add survey timestamp field here
			if ($displaySurveyFields && $hasSurveys && isset($Proj->forms[$row['form_name']]['survey_id'])
				&& (($prev_form != $row['form_name'] && $row['field_name'] != $primaryKey)
				|| ($prev_form == $row['form_name'] && $prev_field == $primaryKey)))
			{
				// Add timestamp field
				$fieldNames[] = $row['form_name'].'_timestamp';
				$fieldDefaults[$row['form_name'].'_timestamp'] = '';
				$fieldTypes[$row['form_name'].'_timestamp'] = 'text';
				$fieldValidationTypes[$row['form_name'].'_timestamp'] = '';
			}

			if ($row['element_type'] != "checkbox")
			{
				$fieldNames[] = $row['field_name'];

				# Set Default Values
				if ($row['field_name'] == $row['form_name'] . "_complete") {
					if ($rawOrLabel == 'label') {
						$fieldDefaults[$row['field_name']] = 'Incomplete';
					} else {
						$fieldDefaults[$row['field_name']] = '0';
					}
				} else {
					$fieldDefaults[$row['field_name']] = '';
				}
			}
			else
			{
				// Loop through checkbox elements and append string to variable name
				foreach ($checkboxFields[$row['field_name']] as $value => $label)
				{
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$value = (Project::getExtendedCheckboxCodeFormatted($value));

					// Append triple underscore + coded value
					$newName = $row['field_name'] . '___' . $value;
					$fieldNames[] = $newName;

					# Set Default Values
					$fieldDefaults[$row['field_name']][$value] = ($rawOrLabel == 'raw') ? '0' : '';	# checkbox gets default of 0
				}
			}

			# Store enums for fields that have them defined
			if ($row['element_type'] != 'calc' & $row['element_enum'] != "") {
				// Parse MC fields for dropdowns and radios, but retrieve valid enum from "sql" field queries
				if ($row['element_type'] == "sql")
				{
					$row['element_enum'] = getSqlFieldEnum($row['element_enum']);
				}
				$fieldEnums[$row['field_name']] = parseEnum($row['element_enum']);
			}

			# Store Field Types
			$fieldTypes[$row['field_name']] = $row['element_type'];

			# Store Validation Type
			if ($row['element_type'] == "text" || $row['element_type'] == "textarea") {
				$fieldValidationTypes[$row['field_name']] = $row['element_validation_type'];
			}

			# Store Fields that are Identifiers
			if ($row['field_phi']) $fieldPhis[] = $row['field_name'];

			# Add extra columns (if needed) if we're on the first field
			if ($row['field_name'] == $primaryKey)
			{
				# Add event name if project is longitudinal
				if ($longitudinal)
				{
					$fieldNames[] = 'redcap_event_name';
					$fieldDefaults['redcap_event_name'] = '';
					$fieldTypes['redcap_event_name'] = 'text';
					$fieldValidationTypes['redcap_event_name'] = '';
				}

				# Add DAG field if specified
				if ($displayDags)
				{
					$fieldNames[] = 'redcap_data_access_group';
					$fieldDefaults['redcap_data_access_group'] = '';
					$fieldTypes['redcap_data_access_group'] = 'text';
					$fieldValidationTypes['redcap_data_access_group'] = '';
				}

				# Add timestamp and identifier, if any surveys exist
				if ($hasSurveys && $displaySurveyFields)
				{
					$fieldNames[] = 'redcap_survey_identifier';
					$fieldDefaults['redcap_survey_identifier'] = '';
					$fieldTypes['redcap_survey_identifier'] = 'text';
					$fieldValidationTypes['redcap_survey_identifier'] = '';
				}
			}

			// Set values for next loop
			$prev_form = $row['form_name'];
			$prev_field = $row['field_name'];
		}

		$fieldData = array("names" => $fieldNames, "defaults" => $fieldDefaults, "types" => $fieldTypes,
			"enums" => $fieldEnums, "valTypes" => $fieldValidationTypes, "identifiers" => $fieldPhis);

		return $fieldData;
	}

	public static function getCheckboxFields($projectId, $addDefaults = false)
	{
		$sql = "SELECT field_name, element_enum
				FROM redcap_metadata
				WHERE project_id = $projectId AND element_type = 'checkbox'";
		$result = db_query($sql);

		$checkboxFields = array();

		while ($row = db_fetch_assoc($result))
		{
			foreach (parseEnum($row['element_enum']) as $value => $label) {
				$checkboxFields[$row['field_name']][$value] = ($addDefaults ? "0" : html_entity_decode($label, ENT_QUOTES));
			}
		}

		return $checkboxFields;
	}

	public static function getFieldNames($projectId)
	{
		$sql = "SELECT field_name, element_type, element_enum
				FROM redcap_metadata
				WHERE project_id = $projectId
				ORDER BY field_order";
		$result = db_query($sql);

		$fields = array();
		while($row = db_fetch_assoc($result))
		{
			if ( $row['element_type'] != "checkbox")
			{
				$fields[] = $row['field_name'];
			}
			else
			{
				foreach (parseEnum($row['element_enum']) as $value => $label)
				{
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$value = (Project::getExtendedCheckboxCodeFormatted($value));

					// Append triple underscore + coded value
					$fields[] = $row['field_name'] . '___' . $value;
				}
			}
		}

		return $fields;
	}

	// Get the date/time format display for date/time fields (to be displayed next to them) - e.g. M-D-Y H:M
	public static function getDateFormatDisplay($valtype)
	{
		switch ($valtype)
		{
			case 'time':
				$dformat = "H:M";
				break;
			case 'date':
			case 'date_ymd':
				$dformat = "Y-M-D";
				break;
			case 'date_mdy':
				$dformat = "M-D-Y";
				break;
			case 'date_dmy':
				$dformat = "D-M-Y";
				break;
			case 'datetime':
			case 'datetime_ymd':
				$dformat = "Y-M-D H:M";
				break;
			case 'datetime_mdy':
				$dformat = "M-D-Y H:M";
				break;
			case 'datetime_dmy':
				$dformat = "D-M-Y H:M";
				break;
			case 'datetime_seconds':
			case 'datetime_seconds_ymd':
				$dformat = "Y-M-D H:M:S";
				break;
			case 'datetime_seconds_mdy':
				$dformat = "M-D-Y H:M:S";
				break;
			case 'datetime_seconds_dmy':
				$dformat = "D-M-Y H:M:S";
				break;
			default:
				$dformat = '';
		}
		return $dformat;
	}

	// Get the pixel width of date/time fields based upon their type
	public static function getDateFieldWidth($valtype)
	{
		switch ($valtype)
		{
			case 'time':
				$width = '70px';
				break;
			case 'date':
			case 'date_ymd':
			case 'date_mdy':
			case 'date_dmy':
				$width = '80px';
				break;
			case 'datetime':
			case 'datetime_ymd':
			case 'datetime_mdy':
			case 'datetime_dmy':
				$width = '118px';
				break;
			case 'datetime_seconds':
			case 'datetime_seconds_ymd':
			case 'datetime_seconds_mdy':
			case 'datetime_seconds_dmy':
				$width = '135px';
				break;
			default:
				$width = '';
		}
		return $width;
	}

	// Return array with the headers used for the data dictionary
	public static function getDataDictionaryHeaders($returnCsvLabelHeaders=false)
	{
		if ($returnCsvLabelHeaders) {
			$ddheaders = array(
				'field_name'=>"Variable / Field Name", 'form_name'=>"Form Name", 'element_preceding_header'=>"Section Header",
				'element_type'=>"Field Type", 'element_label'=>"Field Label", 'element_enum'=>"Choices, Calculations, OR Slider Labels",
				'element_note'=>"Field Note", 'element_validation_type'=>"Text Validation Type OR Show Slider Number",
				'element_validation_min'=>"Text Validation Min", 'element_validation_max'=>"Text Validation Max", 'field_phi'=>"Identifier?",
				'branching_logic'=>"Branching Logic (Show field only if...)", 'field_req'=>"Required Field?",
				'custom_alignment'=>"Custom Alignment", 'question_num'=>"Question Number (surveys only)", 'grid_name'=>"Matrix Group Name",
				'grid_rank'=>"Matrix Ranking?", 'misc'=>"Field Annotation");
		} else {
			$ddheaders = array(
				'field_name'=>"field_name", 'form_name'=>"form_name", 'element_preceding_header'=>"section_header",
				'element_type'=>"field_type", 'element_label'=>"field_label", 'element_enum'=>"select_choices_or_calculations",
				'element_note'=>"field_note", 'element_validation_type'=>"text_validation_type_or_show_slider_number",
				'element_validation_min'=>"text_validation_min", 'element_validation_max'=>"text_validation_max", 'field_phi'=>"identifier",
				'branching_logic'=>"branching_logic", 'field_req'=>"required_field",
				'custom_alignment'=>"custom_alignment", 'question_num'=>"question_number", 'grid_name'=>"matrix_group_name",
				'grid_rank'=>"matrix_ranking", 'misc'=>"field_annotation");
		}
		return $ddheaders;
	}
	
	// Return HTML of DD snapshot button for Online Designer and DD Upload pages
	public static function renderDataDictionarySnapshotButton()
	{
		global $lang;
		// Get last snapshot time
		$last_snapshot_time = self::getLastDataDictionarySnapshot();
		if ($last_snapshot_time === false) {
			$last_snapshot_text = $lang['rights_171'];
		} else {
			$last_snapshot_text = RCView::a(array('href'=>APP_PATH_WEBROOT."ProjectSetup/project_revision_history.php?pid=".PROJECT_ID),
									DateTimeRC::format_user_datetime($last_snapshot_time, 'Y-M-D_24')
								  );
		}
		// Set info for dialog
		$snapshotInfo = "simpleDialog('".js_escape($lang['design_689'])."','".js_escape($lang['design_688'])."',null,550);";
		// Output html
		return 	RCView::span(array('id'=>'dd_snapshot_parent'),
					RCView::button(array('id'=>'dd_snapshot_btn', 'class'=>'btn btn-defaultrc btn-xs', 'style'=>'background-color:#eee;font-size:11px;', 'onclick'=>"createDataDictionarySnapshot();"), 
						RCView::span(array('class'=>'fas fa-camera', 'style'=>'top:2px;'), '') .
						RCView::span(array('style'=>'vertical-align:middle;margin-left:4px;'), $lang['design_688'])
					) .
					RCView::br() . 
					// "Last snapshot on..."
					RCView::span(array('id'=>'last_dd_snapshot'), 
						$lang['design_687'] . " " . 
						RCView::span(array('id'=>'last_dd_snapshot_ts'), $last_snapshot_text) .
						RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'font-weight:normal;margin-right:7px;', 'onclick'=>$snapshotInfo), '?')
					)
				);
	}
	
	// Get timestamp of last DD snapshot for this project
	public static function getLastDataDictionarySnapshot()
	{
		$sql = "select e.stored_date from redcap_data_dictionaries d, redcap_edocs_metadata e 
				where e.project_id = ".PROJECT_ID." and e.project_id = d.project_id and d.doc_id = e.doc_id
				order by d.dd_id desc limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) < 1) ? false : db_result($q, 0);
	}
	
	// Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table. Return boolean is successful.
	// If project is in production, then it will perform a snapshot of the Drafted Changes if in draft mode.
	public static function createDataDictionarySnapshot()
	{
		global $status, $draft_mode;
		// Get current metadata and save as temp file
		$dd_snapshot_filename = APP_PATH_TEMP . date('YmdHis') . "_DataDictionary_" . substr(sha1(rand()), 0, 6) . ".csv";
		$dd_snapshot = MetaData::getDataDictionary('csv', true, array(), array(), false, ($status > 0 && $draft_mode > 0));
		// Add BOM to file if using UTF-8 encoding
		$dd_snapshot = addBOMtoUTF8($dd_snapshot);
		// Temporarily store file in temp
		file_put_contents($dd_snapshot_filename, $dd_snapshot);
		// Simulate a file upload for storing in edocs table
		$ddfile = array('name'=>basename($dd_snapshot_filename), 'type'=>'application/csv', 
						'size'=>filesize($dd_snapshot_filename), 'tmp_name'=>$dd_snapshot_filename);
		$dd_edoc_id = Files::uploadFile($ddfile);
		$user_info = User::getUserInfo(USERID);
		// Remove temp file
		unlink($dd_snapshot_filename);
		// Add to DD table
		$sql = "insert into redcap_data_dictionaries (doc_id, project_id, ui_id) 
				values ($dd_edoc_id, ".PROJECT_ID.", {$user_info['ui_id']})";
		// Return true if successful
		return db_query($sql);
	}

	// Get the data dictionary in either CVS, JSON, or XML format.
	// Parameter $returnCsvLabelHeaders can only be used where $returnFormat='csv'.
	// Parameter $fields will return ONLY those fields. By default, will return all fields.
	// Parameter $project_id_override will cause the function to pull the metadata for another project if this method is called in another project's context.
	public static function getDataDictionary($returnFormat='csv', $returnCsvLabelHeaders=true, $fields=array(), $forms=array(),
											 $isMobileApp=false, $draft_mode=false, $revision_id=null, $project_id_override=null)
	{
		// Generate WHERE clause for field/form subset
		$fieldSql = "";
		if (is_array($fields) && count($fields) > 0) {
			$fieldSql .= "field_name in (".prep_implode($fields).")";
		} elseif ($fields !== null && $fields != "" && is_string($fields)) {
			$fieldSql .= "field_name = '".db_escape($fields)."'";
		}
		if (is_array($forms) && count($forms) > 0) {
			if ($fieldSql != "") $fieldSql .= " or ";
			$fieldSql .= "form_name in (".prep_implode($forms).")";
		} elseif ($forms !== null && $forms != "" && is_string($forms)) {
			if ($fieldSql != "") $fieldSql .= " or ";
			$fieldSql .= "form_name = '".db_escape($forms)."'";
		}
		if ($fieldSql != "") $fieldSql = "and ($fieldSql)";

		//If coming from project revision history page and referencing rev_id, then use metadata archive table
		if (is_numeric($revision_id))
		{
			$metadata_where = "AND m.pr_id = $revision_id";
			$metadata_table = "redcap_metadata_archive";
		}
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		else
		{
			$metadata_where = "";
			$metadata_table = $draft_mode ? "redcap_metadata_temp" : "redcap_metadata";
		}

		// Add headers
		$ddheaders = self::getDataDictionaryHeaders($returnFormat == 'csv' && $returnCsvLabelHeaders);

		// Mobile App: Returns slightly different format than regular API metadata export
		$cat_where = "";
		if ($isMobileApp) {
			// Don't output any CATs because they cannot be used in the Mobile App
			$cat_list = PROMIS::getPromisInstruments(is_numeric($project_id_override) ? $project_id_override : PROJECT_ID);
			if (!empty($cat_list)) {
				$cat_where = "and m.form_name not in (".prep_implode($cat_list).")";
			}
			// Return slightly different format than regular API metadata export
			$ddheaders['edoc_display_img'] = ($returnFormat == 'csv' && $returnCsvLabelHeaders) ? "Has image attachment?" : "has_image_attachment";
		}

		//Pull the metadata from table to export into CSV file
		$sql = "SELECT p.project_encoding, m.field_name, m.form_name, m.element_preceding_header, m.element_type,
				m.element_label, m.element_enum, m.element_note, m.element_validation_type, m.element_validation_min,
				m.element_validation_max, m.field_phi, m.branching_logic, m.field_req,
				m.custom_alignment, m.question_num, m.grid_name, m.grid_rank, m.edoc_id, m.misc, m.edoc_display_img
				FROM $metadata_table m, redcap_projects p WHERE m.project_id = p.project_id
				AND m.project_id = ".(is_numeric($project_id_override) ? $project_id_override : PROJECT_ID)."
				AND field_name != concat(m.form_name,'_complete') $metadata_where $fieldSql $cat_where
				ORDER BY m.field_order";
		$q = db_query($sql);
		$lines = array();
		while ($row = db_fetch_assoc($q))
		{
			// Set project_encoding
			$project_encoding = $row['project_encoding'];
			// Remove any hex'ed double-CR characters in field label, etc.
			$row['element_label'] = str_replace("\x0d\x0d", "\n\n", $row['element_label']);	
			$row['element_preceding_header'] = str_replace("\x0d\x0d", "\n\n", $row['element_preceding_header']);	
			$row['element_enum'] = str_replace("\x0d\x0d", "\n\n", $row['element_enum']);
			// Mobile App: Returns slightly different format than regular API metadata export
			if ($isMobileApp) {
				// Does the field have an inline image attachment?
				if ($row['edoc_id'] == '' || $row['edoc_display_img'] != '1') $row['edoc_display_img'] = 0;
				// Convert all calculations and branching logic to JavaScript notation
				if ($row['branching_logic'] != '') {
					$row['branching_logic'] = LogicTester::formatLogicToJS(html_entity_decode($row['branching_logic'], ENT_QUOTES));
				}
				if ($row['element_type'] == 'calc') {
					$row['element_enum'] = LogicTester::formatLogicToJS(html_entity_decode($row['element_enum'], ENT_QUOTES), true);
				}
			} else {
				unset($row['edoc_display_img']);
			}
			unset($row['edoc_id'], $row['project_encoding']);
			// Output right-vertical aligned sliders as RV, not as ""
			if ($row['element_type'] == 'slider' && $row['custom_alignment'] == '') {
				$row['custom_alignment'] = "RV";
			}	
			// Loop through all columns for last-minute formatting
			foreach ($row as $this_field=>&$value)
			{
				// Unescape the values
				$value = html_entity_decode($value, ENT_QUOTES);
				// For Excel compatibility, add space to pad anything that begins with @, which denotes a function in Excel
				if ($this_field == "misc" && !defined("API") && substr($value, 0, 1) == '@') {
					$value = " $value";
				}
				//Remove \n in Select Choices and replace with |
				if ($this_field == "element_enum") {
					$value = str_replace("\\n", "|", trim($value));
				//Change Subject Identifier, Required Field and Matrix Ranking values of '1' to 'y'
				} elseif ($this_field == "field_phi" || $this_field == "field_req" || $this_field == "grid_rank") {
					$value = trim($value) == "1" ? "y" : "";
				//Change to user-friendly/non-legacy values for Validation
				} elseif ($this_field == "element_validation_type") {
					if (in_array($value, array("date","datetime","datetime_seconds"))) {
						$value .= "_ymd";
					} elseif (in_array($value, array("int","float"))) {
						$value = str_replace(array("int","float"), array("integer","number"), $value);
					}
				//Change to user-friendly values for Validation
				} elseif ($this_field == "element_type") {
					$value = str_replace(array("select","textarea"), array("dropdown","notes"), $value);
				} elseif ($this_field == "element_preceding_header") {
					// If Section Header is only whitespace (to server as a placeholder), then wrap single space in quotes to preserve it.
					if (substr($value, 0, 1) == " " && trim($value) == "") $value = ' ';
				}
				if ($value != "") {
					// Fix any formatting
					$value = str_replace(array("&#39;","&#039;"), array("'","'"), $value);
					// For Japanese encoding
					if ($project_encoding == 'japanese_sjis' && mb_detect_encoding($value) == "UTF-8" 
						// Don't do this encoding conversion for the mobile app because it will break it
						&& !(API && $_POST['mobile_app'] == '1')) {
						$value = mb_convert_encoding($value, "SJIS", "UTF-8");
					}
				}
			}
			// If not CSV format, then rename column names for display purposes
			if ($returnFormat != 'csv') {
				$row2 = array();
				foreach ($row as $this_field2=>$value2) {
					$row2[$ddheaders[$this_field2]] = $value2;
				}
				$row = $row2;
			}
			// Add line to array with field_name as key
			$lines[$row['field_name']] = $row;
		}

		// Change structure according to desired reteurn format
		switch ($returnFormat)
		{
			// Array (PHP array type)
			case 'array':
				return $lines;
				break;
			// JSON
			case 'json':
				// Convert all data into JSON string (do line by line to preserve memory better)
				$content = '';
				foreach ($lines as $key=>&$item) {
					// Loop through each record and encode
					$item_json = json_encode_rc($item);
					if ($item_json !== false) $content .= ",\n".$item_json;
					// Remove line from array to free up memory as we go
					unset($lines[$key]);
				}
				return '[' . substr($content, 2) . ']';
				break;
			// XML
			case 'xml':
				$content = '<?xml version="1.0" encoding="UTF-8" ?>';
				$content .= "\n<records>\n";
				foreach ($lines as $row) {
					$line = '';
					foreach ($row as $item => $value) {
						if ($value != "")
							$line .= "<$item><![CDATA[" . $value . "]]></$item>";
						else
							$line .= "<$item></$item>";
					}
					$content .= "<item>$line</item>\n";
				}
				$content .= "</records>\n";
				break;
			// CSV
			default:
				// Open connection to create file in memory and write to it
				$fp = fopen('php://memory', "x+");
				// Add headers
				fputcsv($fp, $ddheaders);
				// Loop and write each line to CSV
				foreach ($lines as $line) {
					fputcsv($fp, $line);
				}
				// Open file for reading and output to user
				fseek($fp, 0);
				$content = stream_get_contents($fp);
				// Replace CR+LF with just LF for better compatiblity with Excel on Macs
				$content = str_replace("\r\n", "\n", $content);
		}

		// Return content
		return $content;
	}


	// Get array of viable column names from the CSV file (using Excel letter naming for columns)
	public static function getCsvColNames()
	{
		return array(1 => "A", 2 => "B", 3 => "C", 4 => "D", 5 => "E", 6 => "F", 7 => "G", 8 => "H",
					 9 => "I", 10 => "J", 11 => "K", 12 => "L", 13 => "M", 14 => "N", 15 => "O",
					 16 => "P", 17 => "Q", 18 => "R");
	}


	// Convert a flat item-based metadata array into Data Dictionary array with specific Excel-cell-named keys-subkeys (e.g. A1)
	public static function convertFlatMetadataToDDarray($data)
	{
		// make $data look like spreadsheet data
		$csv_cols = MetaData::getCsvColNames();
		$dd_array = array();
		$r = 1; // Start with 1 so that the record ID field gets row 2 position (assumes headers in row 1)

		foreach($data as $row)
		{
			++$r;
			$row_keys = array_keys($row);

			foreach($csv_cols as $n => $l)
			{
				if(!isset($dd_array[$l]))
				{
					$dd_array[$l] = array();
				}

				$dd_array[$l][$r] = $row[$row_keys[$n-1]];
			}
		}
		return $dd_array;
	}


	// Save a flat item-based metadata array
	public static function saveMetadataFlat($data, $ignoreIllegalVariables=false)
	{
		global $lang;
		// Convert a flat item-based metadata array into Data Dictionary array
		$dd_array = self::convertFlatMetadataToDDarray($data);
		// Clean and check for errors
		list ($errors, $warnings, $dd_array) = self::error_checking($dd_array, false, $ignoreIllegalVariables);
		// Return any errors found (unless we're creating a project via ODM/XML file, in which we'll allow metadata errors)
		if (!empty($errors) && !defined("CREATE_PROJECT_ODM")) {
			return array(count($dd_array), $errors);
		}
		// Commit the changes
		$errors = self::save_metadata($dd_array, false, true);
		// Set user-facing error message in case something bad happened
		if (!empty($errors)) $errors = array($lang['random_13']);
		// Return field count and any errors
		return array(count($dd_array['A']), $errors);
	}


	// Save extra metadata attributes not in Data Dictionary
	// Parameter $attr contains the field name as key with column names and value pairs as sub-array.
	public static function saveMetadataExtraAttr($metadata_extra)
	{
		// Loop through fields and add attributes to each one
		foreach ($metadata_extra as $field=>$attr)
		{
			## METADATA
			// Reset array for this field
			$attr_to_add = array();
			// Metadata attributes
			$metaAttr = array('video_url', 'video_display_inline', 'edoc_display_img');
			// Get attributes
			foreach ($metaAttr as $attr_name) {
				if (isset($attr[$attr_name])) {
					$attr_to_add[] = $attr_name . " = '" . db_escape($attr[$attr_name]) . "'";
				}
			}
			// Add to metadata table
			if (!empty($attr_to_add)) {
				$sql = "update redcap_metadata set " . implode(", ", $attr_to_add) . "
						where field_name = '" . db_escape($field) . "' and project_id = " . PROJECT_ID;
				$q = db_query($sql);
			}

			## ATTACHMENT
			if (!isset($attr['doc_contents'])) continue;
			// Set full file path in temp directory. Replace any spaces with underscores for compatibility.
			$filename_tmp = APP_PATH_TEMP . substr(sha1(rand()), 0, 8) . str_replace(" ", "_", $attr['doc_name']);
			file_put_contents($filename_tmp, $attr['doc_contents']);
			// Set file attributes as if just uploaded
			$file = array('name'=>$attr['doc_name'], 'type'=>$attr['mime_type'], 'size'=>filesize($filename_tmp), 'tmp_name'=>$filename_tmp);
			$edoc_id = Files::uploadFile($file);
			if (is_numeric($edoc_id)) {
				$sql = "update redcap_metadata set edoc_id = $edoc_id
						where field_name = '" . db_escape($field) . "' and project_id = " . PROJECT_ID;
				$q = db_query($sql);
			}
		}
	}


	// Check for errors in data dictionary array
	public static function error_checking($dictionary_array, $appendFields=false, $ignoreIllegalVariables=false, $bypassPromisCheck=false)
	{
		global $status, $project_language, $lang, $surveys_enabled, $table_pk, $randomization, $Proj, $project_encoding;
		// Error messages will go in this array
		$errors_array = array();
		// Warning messages will go in this array (they are allowable or correctable errors)
		$warnings_array = array();
		// Get correct table we're using, depending on if in production
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		// Obtain the table_pk from metadata and compare to one in uploaded file to make sure it's not changing
		$sql = "select field_name from $metadata_table where project_id = " . PROJECT_ID . " order by field_order limit 1";
		$current_table_pk = db_result(db_query($sql), 0);
		// Set default value of table_pk from uploaded file (obtain during looping)
		$file_table_pk = "";
		// Check if any data exists yet. Needed for checking if changing PK.
		$q = db_query("select 1 from redcap_data where project_id = " . PROJECT_ID . " limit 1");
		$noData = (db_num_rows($q) == 0);
		// If don't have $Proj, then get form_names from DD
		if (!isset($Proj)) {
			$forms = array();
			foreach ($dictionary_array['B'] as $this_form) {
				$forms[$this_form] = true;
			}
			// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
			$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($forms)) . "_timestamp"
								   . "," . implode("_return_code,", array_keys($forms)) . "_return_code");
		} else {
			// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
			$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
								   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");
		}
		// Get array of PROMIS instrument names (if any forms were downloaded from the Shared Library)
		$promis_forms = PROMIS::getPromisInstruments();
		// Set array for collecting promis fields from uploaded DD
		$promis_fields_DD = array();
		// Set array holding key numbers of all fields that need to be removed from the DD array
		$fields_to_remove = array();
		// Get array of Smart Variables
		$smartVars = Piping::getSpecialTags();

		## FIELD NAMES
		foreach ($dictionary_array['A'] as $row => $this_field)
		{
			// Get the record ID field (table_pk for this DD file)
			if (!empty($this_field) && empty($file_table_pk)) {
				// Set file pk value
				$file_table_pk = $this_field;
				// Make sure that record ID field does NOT have a section header (unneccessary)
				if (!$appendFields) $dictionary_array['C'][$row] = "";
			}
			// If variable name is missing
			elseif (empty($this_field)) {
				$errors_array[2][] = "<b>A{$row}</b>";
			}
			//CHECK FOR BLANK FIELD NAMES AND IF TABLE_PK IS DIFFERENT (IF DATA EXISTS)
			if (!$noData && $file_table_pk == $this_field && $current_table_pk != $file_table_pk) {
				$warnings_array[13] = "<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
									<b>{$lang['database_mods_92']}</b> {$lang['database_mods_93']}
									{$lang['update_pk_02']} {$lang['update_pk_05']} {$lang['database_mods_96']}<br>
									&nbsp;&bull; {$lang['database_mods_94']} <b>$current_table_pk</b><br>
									&nbsp;&bull; {$lang['database_mods_95']} <b>$file_table_pk</b>";
			}
			// Check field names if it has two-byte characters (for Japanese)
			if ($project_encoding == 'japanese_sjis') {
				if (mb_detect_encoding($this_field) != "ASCII") {
					$errors_array[7][] = "<b>A{$row}</b>";
				}
			}
			//FIELD NAMES CANNOT BEGIN WITH NUMERAL
			if (is_numeric(substr($this_field, 0, 1))) {
				$errors_array[3][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
			}
			//FIELD NAMES CANNOT BEGIN WITH NUMERAL
			if (substr($this_field, 0, 1) == "_") {
				$errors_array[10][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
			}
			//FIELD NAMES CANNOT HAVE A TRIPE UNDERSCORE (reserved for checkbox variable names when exported)
			if (strpos($this_field, "___") !== false) {
				$errors_array[26][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
			}
			//FIELD NAMES SHOULD NOT BE LONGER THAN 26 CHARS AND CANNOT BE LONGER THAN 100
			if (strlen($this_field) > 100) {
				$errors_array[11][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
			} elseif (strlen($this_field) > 26) {
				$warnings_array[3][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
			}
			//ONLY LOWERCASE LETTERS, NUMBERS, AND UNDERSCORES
			if (($this_field != preg_replace("/[^a-z_0-9]/", "", $this_field) || ($status == 0 && strpos($this_field, "__") !== false)) && strpos($this_field, "___") === false) {
				// Only replace double underscores when in developement (could cause loss of data to rename a field in production that already has double underscores)
				$doubleUnderscore = ($status == 0) ? "__" : " ";
				// Triple underscores would already be caught earlier and listed as an error, so ignore them here
				$dictionary_array['A'][$row] = preg_replace("/[^a-z_0-9]/", "", str_replace(array(" ",$doubleUnderscore), array("_","_"), strtolower($this_field)));
				$warnings_array[1][] = "<b>".RCView::escape($this_field)." (A{$row})</b> {$lang['database_mods_15']} <b>" . $dictionary_array['A'][$row] . "</b>";
				$this_field = $dictionary_array['A'][$row];
			}
			//VARIABLE NAME	CANNOT BE A RESERVED FIELD NAME
			if (isset(Project::$reserved_field_names[$this_field]) || in_array($this_field, $extra_reserved_field_names)) {
				if ($ignoreIllegalVariables) {
					// Remove this field from all columns
					$fields_to_remove[] = $row;
				} else {
					$errors_array[22][] = "<b>A{$row}</b>";
				}
			}
			//VARIABLE NAME	CANNOT BE A FORM NAME + "_complete". Flag any possible errors and keep to check later after we've collected the form names.
			if (substr($this_field, -9) == "_complete") {
				$errors_array[23][$row] = substr($this_field, 0, -9);
			}
		}
		// FIELD NAME DUPLICATION (do this last in column 1 since it will break the query)
		$field_diff = array_diff_assoc($dictionary_array['A'], array_unique($dictionary_array['A']));
		if (count($field_diff) > 0) {
			$errors_array[1] = $lang['database_mods_16'];
			foreach ($field_diff as $row => $this_field) {
				$errors_array[1] .= "<br><b>".RCView::escape($this_field)." (A{$row})</b>";
			}
		}
		if (isset($errors_array[2])) {
			$errors_array[2] = $lang['database_mods_17'] . implode(", ", $errors_array[2]) . $lang['period'];
		}
		if (isset($errors_array[3])) {
			$errors_array[3] = "{$lang['database_mods_18']}<br>" . implode("<br>", $errors_array[3]);
		}
		if (isset($errors_array[26])) {
			$errors_array[26] = "{$lang['database_mods_19']}<br>" . implode("<br>", $errors_array[26]);
		}
		if (isset($errors_array[10])) {
			$errors_array[10] = "{$lang['database_mods_20']}<br>" . implode("<br>", $errors_array[10]);
		}
		if (isset($warnings_array[1])) {
			$warnings_array[1] = "{$lang['database_mods_21']}<br>" . implode("<br>", $warnings_array[1]);
		}
		if (isset($warnings_array[3])) {
			$warnings_array[3] = "{$lang['database_mods_22']}<br>" . implode("<br>", $warnings_array[3]);
		}
		if (isset($errors_array[11])) {
			$errors_array[11] = "{$lang['database_mods_23']}<br>" . implode("<br>", $errors_array[11]);
		}
		if (isset($errors_array[22])) {
			$errors_array[22] = $lang['database_mods_24'] . implode(", ", $errors_array[22]);
		}
		if (isset($errors_array[7])) {
			$errors_array[7] = $lang['database_mods_25'] . implode(", ", $errors_array[7]) . $lang['period'];
		}
		// RANDOMIZATION: Make sure all fields used in randomization exist in the uploaded DD
		if (!$appendFields && $randomization && Randomization::setupStatus())
		{
			// Get randomization fields
			$randFields = Randomization::getRandomizationFields();
			foreach ($randFields as $this_field) {
				if (!in_array($this_field, $dictionary_array['A'])) {
					$errors_array[30][] = "<b>".RCView::escape($this_field)." (A{$row})</b>";
				}
			}
			if (isset($errors_array[30])) {
				$errors_array[30] = $lang['database_mods_118'] . " " . implode(", ", $errors_array[30]) . $lang['period'];
			}
		}


		## FORM NAMES
		$prev_form = "";
		$form_key = array();
		foreach ($dictionary_array['B'] as $row => $this_form) {
			//CHECK FOR BLANK FORM NAMES
			if ($this_form == "") {
				$errors_array[5][] = "<b>B{$row}</b>";
			}
			// Check form names if it has two-byte characters (for Japanese)
			if ($project_encoding == 'japanese_sjis') {
				if (mb_detect_encoding($this_form) != "ASCII") {
					$errors_array[6][] = "<b>B{$row}</b>";
				}
			}
			// FORM NAMES SHOULD NOT BE LONGER THAN 50 CHARS AND CANNOT BE LONGER THAN 64
			// If the form existed previously and had more than 64 characters, then allow it.
			if (strlen($this_form) > 64 && !isset($Proj->forms[$this_form])) {
				$errors_array[12][] = "<b>".RCView::escape($this_form)." (A{$row})</b>";
			} elseif (strlen($this_form) > 50) {
				$warnings_array[4][] = "<b>".RCView::escape($this_form)." (A{$row})</b>";
			}
			//LOWERCASE LETTERS, NUMBERS, AND UNDERSCORES
			if ($this_form != preg_replace("/[^a-z_0-9]/", "", $this_form) || is_numeric(substr($this_form, 0, 1)) || is_numeric(trim(str_replace(array(" ", "_"), array("", ""), $this_form)))) {
				// Remove illegal characters first
				$dictionary_array['B'][$row] = preg_replace("/[^a-z_0-9]/", "", str_replace(" ", "_", strtolower($this_form)));
				// Remove any double underscores, beginning numerals, and beginning/ending underscores
				while (strpos($dictionary_array['B'][$row], "__") !== false) 	$dictionary_array['B'][$row] = str_replace("__", "_", $dictionary_array['B'][$row]);
				while (substr($dictionary_array['B'][$row], 0, 1) == "_") 		$dictionary_array['B'][$row] = substr($dictionary_array['B'][$row], 1);
				while (substr($dictionary_array['B'][$row], -1) == "_") 		$dictionary_array['B'][$row] = substr($dictionary_array['B'][$row], 0, -1);
				while (is_numeric(substr($dictionary_array['B'][$row], 0, 1))) 	$dictionary_array['B'][$row] = substr($dictionary_array['B'][$row], 1);
				while (substr($dictionary_array['B'][$row], 0, 1) == "_") 		$dictionary_array['B'][$row] = substr($dictionary_array['B'][$row], 1);
				// Cannot begin with numeral
				if (is_numeric(substr($dictionary_array['B'][$row], 0, 1)) || $dictionary_array['B'][$row] == "") {
					$dictionary_array['B'][$row] = substr(preg_replace("/[0-9]/", "", md5($dictionary_array['B'][$row])), 0, 4) . $dictionary_array['B'][$row];
				}
				// Set warning flag
				$warnings_array[2][] = "<b>".RCView::escape($this_form)." (B{$row})</b> {$lang['database_mods_15']} <b>" . $dictionary_array['B'][$row] . "</b>";
				$this_form = $dictionary_array['B'][$row];
			}
			//FORMS MUST BE SEQUENTIAL
			if ($prev_form != "" && $prev_form != $this_form && isset($form_key[$this_form])) {
				$errors_array[8][] = "<b>".RCView::escape($this_form)." (B{$row})</b>";
			}
			// If a PROMIS adaptive form, then add attributes to array for checking later
			if (in_array($this_form, $promis_forms)) {
				// Set up array to switch out Excel column letters
				foreach (MetaData::getCsvColNames() as $colletter) {
					if ($dictionary_array['A'][$row] == $table_pk) continue;
					$promis_fields_DD[$dictionary_array['A'][$row]][$colletter] = $dictionary_array[$colletter][$row];
				}
			}
			//Collect form names as unique
			$form_key[$this_form] = "";
			//Set for next loop
			$prev_form = $this_form;
		}

		if (isset($errors_array[5])) {
			$errors_array[5] = $lang['database_mods_26'] . implode(", ", $errors_array[5]) . ".";
		}
		if (isset($errors_array[6])) {
			$errors_array[6] = $lang['database_mods_27'] . implode(", ", $errors_array[6]) . ".";
		}
		if (isset($warnings_array[2])) {
			$warnings_array[2] = "{$lang['database_mods_28']}<br>" . implode("<br>", $warnings_array[2]);
		}
		if (isset($errors_array[8])) {
			$errors_array[8] = "{$lang['database_mods_29']}<br>" . implode("<br>", $errors_array[8]);
		}
		if (isset($warnings_array[4])) {
			$warnings_array[4] = "{$lang['database_mods_30']}<br>" . implode("<br>", $warnings_array[4]);
		}
		if (isset($errors_array[12])) {
			$errors_array[12] = "{$lang['database_mods_31']}<br>" . implode("<br>", $errors_array[12]);
		}
		if (isset($errors_array[23])) {
			// Loop through possible matches for form_name+"_complete"
			foreach ($errors_array[23] as $this_row=>$form_maybe) {
				if (isset($form_key[$form_maybe])) {
					$errors_array[24][] = "<b>{$form_maybe}_complete (A{$this_row})</b>";
				}
			}
			unset($errors_array[23]);
		}
		if (isset($errors_array[24])) {
			$errors_array[24] = "{$lang['database_mods_32']}<br>" . implode("<br>", $errors_array[24]);
		}

		// Create array of Form Status field names (to use in allowing in calc fields and branching logic)
		$formStatusFields = array();
		foreach (array_unique($dictionary_array['B']) as $this_form) {
			$formStatusFields[] = $this_form . "_complete";
		}

		// MAKE SURE FIELD #1 IS A TEXT FIELD
		if (!$appendFields && $dictionary_array['D'][2] != "text") {
			$warnings_array[16][] = "<b>D2</b>";
			$dictionary_array['D'][2] = $this_field_type = "text";
			$warnings_array[16] = $lang['database_mods_109'] . " " . implode(", ", $warnings_array[16]) . ".";
		}

		## FIELD TYPES AND CHOICES/CALCULATIONS
		$types = array("text", "notes", "radio", "dropdown", "calc", "file", "sql", "advcheckbox", "checkbox", "yesno", "truefalse", "descriptive", "slider");
		$types_no_easter_eggs = array("text", "notes", "radio", "dropdown", "calc", "file", "checkbox", "yesno", "truefalse", "descriptive", "slider");
		$legacy_types = array("textarea", "select");
		foreach ($dictionary_array['D'] as $row => $this_field_type)
		{
			// CHECK FOR BLANK FIELD TYPES
			if ($this_field_type == "") {
				$errors_array[13][] = "<b>D{$row}</b>";
			}
			// ENSURE CERTAIN FIELDS DO NOT HAVE A "SELECT CHOICE" VALUE
			if (in_array($this_field_type, array("textarea", "notes", "file", "yesno", "truefalse", "descriptive"))) {
				$dictionary_array['F'][$row] = "";
			}
			// CHECK IF VALID FIELD TYPE
			if (in_array($this_field_type, $legacy_types)) {
				//Allow legacy values for field types (and reformat to new equivalents)
				if ($this_field_type == "textarea") {
					$dictionary_array['D'][$row] = $this_field_type = "notes";
				} elseif ($this_field_type == "select") {
					$dictionary_array['D'][$row] = $this_field_type = "dropdown";
				}
			} elseif (!in_array($this_field_type, $types)) {
				// Not a valid field type
				$errors_array[9][] = "<b>".RCView::escape($this_field_type)." (D{$row})</b>";
			} elseif ($this_field_type == "calc") {
				// Make sure calc fields have an equation
				if ($dictionary_array['F'][$row] == "" && !$appendFields) {
					$errors_array[14][] = "<b>F{$row}</b>";
				// Do simple check to see if there are basic errors in calc field equation
				} elseif (substr_count($dictionary_array['F'][$row], "(") != substr_count($dictionary_array['F'][$row], ")") || substr_count($dictionary_array['F'][$row], "[") != substr_count($dictionary_array['F'][$row], "]")) {
					$errors_array[15][] = "<b>F{$row}</b>";
				// Check to make sure there are no spaces or illegal characters within square brackets in calc field equation
				} else {
					$calc_preg = cleanBranchingOrCalc($dictionary_array['F'][$row]);
					if ($dictionary_array['F'][$row] != $calc_preg) {
						$warnings_array[10][] = "<b>{$dictionary_array['F'][$row]} (F{$row})</b> was replaced with <b>$calc_preg</b>";
					}
					$dictionary_array['F'][$row] = $calc_preg;
				}
				// Check to make sure all variables within square brackets are real variables in column A (also allow Form Status fields, which won't be in column A)
				if (!$appendFields) {
					$calcFields = getBracketedFields($dictionary_array['F'][$row], true, true, true);
					foreach (array_keys($calcFields) as $this_field)
					{
						if (!in_array($this_field, $dictionary_array['A']) && !in_array($this_field, $formStatusFields) && !in_array($this_field, $smartVars)) {
							$errors_array[21][] = "<b>$this_field (F{$row})</b>";
						}
					}
				}
				// Check the equation for illegal functions and syntax errors
				$parser = new LogicParser();
				$dictionary_array['F'][$row] = trim(str_replace(array("\r\n","\r"),array("\n","\n"),$dictionary_array['F'][$row])); // clean the equation
				try {
					$parser->parse($dictionary_array['F'][$row]);
				} catch (LogicException $e) {
					if (count($parser->illegalFunctionsAttempted) !== 0) {
						// Contains illegal functions
						if (SUPER_USER) {
							// For super users, only warn them but allow it
							$warnings_array[23][] = "<b>\"".implode("()\", \"", $parser->illegalFunctionsAttempted)."()\" (F{$row})</b>";
						} else {
							// For normal users, do not allow it (unless the equation is the same as before)
							$old_calc_eqn = ($status == 0 ? $Proj->metadata[$dictionary_array['A'][$row]]['element_enum'] : $Proj->metadata_temp[$dictionary_array['A'][$row]]['element_enum']);
							if ($dictionary_array['F'][$row] == trim(label_decode($old_calc_eqn))) {
								$warnings_array[25][] = "<b>{$dictionary_array['A'][$row]} (F{$row})</b>";
							} else {
								$errors_array[37][] = "<b>\"".implode("()\", \"", $parser->illegalFunctionsAttempted)."()\" (F{$row})</b>";
							}
						}
					} else {
						// Contains invalid syntax
						if (SUPER_USER) {
							// For super users, only warn them but allow it
							$warnings_array[24][] = "<b>{$dictionary_array['A'][$row]} (F{$row})</b>";
						} else {
							// For normal users, do not allow it (unless the equation is the same as before)
							$old_calc_eqn = trim(str_replace(array("\r\n","\r"),array("\n","\n"),html_entity_decode(($status == 0 ? $Proj->metadata[$dictionary_array['A'][$row]]['element_enum'] : $Proj->metadata_temp[$dictionary_array['A'][$row]]['element_enum']),ENT_QUOTES)));
							if ($dictionary_array['F'][$row] == $old_calc_eqn) {
								$warnings_array[25][] = "<b>{$dictionary_array['A'][$row]} (F{$row})</b>";
							} else {
								$errors_array[38][] = "<b>{$dictionary_array['A'][$row]} (F{$row})</b>";
							}
						}
					}
				}
			// Automatically add choices for advcheckbox
			} elseif ($this_field_type == "advcheckbox") {
				$dictionary_array['F'][$row] = "0, Unchecked \\n 1, Checked";
			// "sql" field types can ONLY be added or edited by Super Users
			} elseif ($this_field_type == "sql") {
				// If user is not super user, then check to make sure that sql field is not new, or if exists, that it is not being changed
				if (!SUPER_USER) {
					// First check if field name already exists and is currently an "sql" field
					$sql = "select element_enum from $metadata_table where project_id = " . PROJECT_ID . " and element_type = 'sql'
							and field_name = '" . $dictionary_array['A'][$row] . "' limit 1";
					$q = db_query($sql);
					if (db_num_rows($q) < 1) {
						// SQL field does not exist and thus cannot be added
						$errors_array[28][] = "<b>" . $dictionary_array['A'][$row] . " ({$lang['database_mods_160']} {$row})</b>";
					} else {
						// Field exists and is being edited
						$this_existing_sql = html_entity_decode(trim(db_result($q, 0)), ENT_QUOTES);
						$this_new_sql = html_entity_decode(trim($dictionary_array['F'][$row]), ENT_QUOTES);
						if ($this_existing_sql != $this_new_sql) {
							// SQL field exists, and user is attempting to modify it
							$errors_array[29][] = "<b>" . $dictionary_array['A'][$row] . " ({$lang['database_mods_160']} {$row})</b>";
						}
					}
				}
			}
			if (!$appendFields) {
				// Make sure multiple choice fields have some choices
				if (in_array($this_field_type, array("select", "radio", "dropdown", "checkbox", "advcheckbox", "sql")) && $dictionary_array['F'][$row] == "") {
					$errors_array[18][] = "<b>F{$row}</b>";
				// Make sure multiple choice fields do not have any options coded with same value
				} elseif (in_array($this_field_type, array("select", "radio", "dropdown", "checkbox")) && $dictionary_array['F'][$row] != "") {
					// Count original choices, then compare to count after parsing each and allocating each choice as a unique key
					$select_array = explode("|", $dictionary_array['F'][$row]);
					$choice_count_start = count($select_array);
					$choice_count_array = array();
					$select_array2 = array();
					foreach ($select_array as $value) {
						// Get coded value
						if (strpos($value,",") !== false) {
							$pos = strpos($value, ",");
							$choice_label = trim(substr($value,$pos+1));
							$value = trim(substr($value,0,$pos));
						} else {
							$value = $choice_label = trim($value);
						}
						// Add to array for checking of duplications
						$choice_count_array[$value] = "";
						// Check to make sure that MC fields don't have illegal characters in their raw coded value
						if (!is_numeric($value) && !preg_match("/^([a-zA-Z0-9._\-]+)$/", $value)) {
							// If not numeric and also not a valid non-numeric alpha-num, then give error (provide suggestions for replacing)
							$coded_preg = preg_replace("/[^a-zA-Z0-9._\-]/", "", str_replace(" ", "_", $value));
							if ($coded_preg != $value) {
								if ($coded_preg == "") $coded_preg = "{number value}";
								if (!$ignoreIllegalVariables) {
									$errors_array[27][] = "<b>\"$value\"</b> (F{$row})</b> - {$lang['database_mods_33']} <b>\"$coded_preg\"</b>";
								} else {
									$value = $coded_preg;
								}
							}
						}
						// Set flag if this is a checkbox with a numerical value containing a dot
						elseif ($this_field_type == "checkbox" && strpos($value, ".") !== false) {
							// If not numeric and also not a valid non-numeric alpha-num, then give error (provide suggestions for replacing)
							$coded_preg = preg_replace("/[^a-zA-Z0-9_]/", "", str_replace(" ", "_", $value));
							if ($coded_preg != $value) {
								if ($coded_preg == "") $coded_preg = "{number value}";
								if (!$ignoreIllegalVariables) {
									$errors_array[44][] = "<b>\"$value\"</b> (F{$row})</b> - {$lang['database_mods_33']} <b>\"$coded_preg\"</b>";
								} else {
									$value = $coded_preg;
								}
							}
						}
						$select_array2[] = "$value, $choice_label";
					}
					$choice_count_end = count($choice_count_array);
					if (!$ignoreIllegalVariables && $choice_count_end < $choice_count_start 
						// Ignore PROMIS CATs for this check
						&& !in_array($dictionary_array['B'][$row], $promis_forms)) 
					{
						// Add error flag if a coded value is duplicated
						$errors_array[25][] = "<b>F{$row}</b>";
					}
					// Fix select array if was changed
					if ($ignoreIllegalVariables) {
						$dictionary_array['F'][$row] = implode("|", $select_array2);
					}
				}
			}
		}
		if (isset($errors_array[9])) {
			$errors_array[9] = "{$lang['database_mods_34']} <u>" . implode("</u>, <u>", $types_no_easter_eggs) .
								"</u> {$lang['database_mods_35']}<br>" . implode("<br>", $errors_array[9]);
		}
		if (isset($errors_array[13])) {
			$errors_array[13] = $lang['database_mods_36'] . implode(", ", $errors_array[13]) . ".";
		}
		if (isset($errors_array[14])) {
			$errors_array[14] = $lang['database_mods_37'] . implode(", ", $errors_array[14]);
		}
		if (isset($errors_array[15])) {
			$errors_array[15] = $lang['database_mods_38'] . implode(", ", $errors_array[15]);
		}
		if (isset($errors_array[18])) {
			$errors_array[18] = $lang['database_mods_39'] . implode(", ", $errors_array[18]);
		}
		if (isset($errors_array[25])) {
			$errors_array[25] = $lang['database_mods_40'] . implode(", ", $errors_array[25]);
		}
		if (isset($errors_array[28])) {
			$errors_array[28] = "{$lang['database_mods_41']}<br>" . implode("<br>", $errors_array[28]);
		}
		if (isset($errors_array[29])) {
			$errors_array[29] = "{$lang['database_mods_42']}<br>" . implode("<br>", $errors_array[29]);
		}
		if (isset($errors_array[27])) {
			$errors_array[27] = "{$lang['database_mods_154']} <br>" . implode("<br>", $errors_array[27]);
		}
		if (isset($errors_array[44])) {
			$errors_array[44] = "{$lang['design_511']} <br>" . implode("<br>", $errors_array[44]);
		}
		if (isset($warnings_array[10])) {
			$warnings_array[10] = "{$lang['database_mods_44']}<br>" . implode("<br>", $warnings_array[10]);
		}
		if (isset($errors_array[21])) {
			$errors_array[21] = "{$lang['database_mods_45']}<br>" . implode("<br>", $errors_array[21]);
		}
		if (isset($errors_array[37])) {
			$errors_array[37] = "{$lang['design_447']}<br>" . implode("<br>", $errors_array[37]);
		}
		if (isset($warnings_array[23])) {
			$warnings_array[23] = "{$lang['design_448']}<br>" . implode("<br>", $warnings_array[23]);
		}
		if (isset($errors_array[38])) {
			$errors_array[38] = "{$lang['design_449']}<br>" . implode("<br>", $errors_array[38]);
		}
		if (isset($warnings_array[24])) {
			$warnings_array[24] = "{$lang['design_450']}<br>" . implode("<br>", $warnings_array[24]);
		}
		if (isset($warnings_array[25])) {
			$warnings_array[25] = "{$lang['design_451']}<br>" . implode("<br>", $warnings_array[25]);
		}

		## FIELD LABELS
		foreach ($dictionary_array['E'] as $row => $this_field_label) {
			//CHECK FOR BLANK FIELD LABELS
			if ($this_field_label == "") {
				$warnings_array[5][] = "<b>E{$row}</b>";
			}
		}
		if (isset($warnings_array[5])) {
			$warnings_array[5] = $lang['database_mods_46'] . implode(", ", $warnings_array[5]) . ".";
		}

		## CHOICES OR CALCULATIONS
		foreach ($dictionary_array['F'] as $row => $this_field_choices)
		{
			if ($this_field_choices != "")
			{
				//CHECK FOR | OR \n (don't warn for checkboxes because it may be useful to only have a single checkbox option)
				if ($dictionary_array['D'][$row] != "text" && $dictionary_array['D'][$row] != "checkbox" && $dictionary_array['D'][$row] != "slider" && $dictionary_array['D'][$row] != "sql" && $dictionary_array['D'][$row] != "calc" && strpos($this_field_choices, "|") === false &&
					strpos($this_field_choices, "\\n") === false && strpos($this_field_choices, "\n") === false)
				{
					$warnings_array[6][] = "<b>F{$row}</b>";
				}
			}
		}
		if (isset($warnings_array[6])) {
			$warnings_array[6] = $lang['database_mods_47'] . implode(", ", $warnings_array[6]) . ".";
		}
		if (isset($warnings_array[17])) {
			$warnings_array[17] = $lang['database_mods_115'] . "<br><br>" . implode("<br><br>", $warnings_array[17]);
		}

		## VALIDATION TYPES
		$val_types_all = getValTypes();
		$val_types = array("date", "datetime", "datetime_seconds", "int", "float"); // seed array with legacy values
		$visible_val_types = array();
		foreach ($val_types_all as $valType=>$valAttr) {
			$val_types[] = $valType;
			// Differentiate between exposed validation types and hidden ones (i.e Easter Eggs)
			if ($valAttr['visible']) {
				$visible_val_types[] = $valType;
			}
		}
		foreach ($dictionary_array['H'] as $row => $this_val_type) {
			if ($this_val_type == "") {
				// MAKE SURE THERE IS A VALIDATION TYPE IF THERE ARE MIN/MAX VALUES
				if ($dictionary_array['D'][$row] == "text" && ($dictionary_array['I'][$row] != "" || $dictionary_array['J'][$row] != "")) {
					$errors_array[47][] = "I{$row}";
				}				
			} else {
				// CHECK IF A TEXT OR SLIDER FIELD OR SIGNATURE FIELD
				if ($dictionary_array['D'][$row] != "text" && $dictionary_array['D'][$row] != "slider"
					&& !(($dictionary_array['D'][$row] == "dropdown" || $dictionary_array['D'][$row] == "sql") && $dictionary_array['H'][$row] == "autocomplete")
					&& !($dictionary_array['D'][$row] == "file" && $dictionary_array['H'][$row] == "signature"))
				{
					$errors_array[17][] = "<b>H{$row}</b>";
				}
				elseif ($dictionary_array['D'][$row] == "text")
				{
					$origValueI = $dictionary_array['I'][$row];
					$origValueJ = $dictionary_array['J'][$row];
					// IF USING DATE VALIDATION, REFORMAT MIN/MAX RANGE FROM DD/MM/YYYY TO MM/DD/YYYY
					// Datetime and Datetime w/ seconds formats
					if (substr($this_val_type, 0, 8) == "datetime")
					{
						// DATETIME MIN VALIDATION
						if ($dictionary_array['I'][$row] != "" && strpos($dictionary_array['I'][$row], "/"))
						{
							list ($thisdate, $thistime) = explode(" ", $dictionary_array['I'][$row], 2);
							// Determine if D/M/Y or M/D/Y format
							if ($_POST['date_format'] == 'DMY') {
								list ($dd, $mm, $yyyy) = explode('/', $thisdate);
							} else {
								list ($mm, $dd, $yyyy) = explode('/', $thisdate);
							}
							if (strlen($yyyy) == 2) $yyyy = "20".$yyyy;
							$mm = sprintf("%02d", $mm);
							$dd = sprintf("%02d", $dd);
							if (substr($this_val_type, 0, 16) == "datetime_seconds") {
								if (strlen($thistime) <= 5 && strpos($thistime, ":") !== false) {
									// If Excel cut off the seconds from the end of the time component, append ":00"
									$thistime .= ":00";
								}
								if (strlen($thistime) < 8 && strpos($thistime, ":") !== false) {
									// Add leading zeroes where needed for time
									$thistime = "0".$thistime;
								}
							} else {
								if (strlen($thistime) < 5) $thistime = "0".$thistime;
							}
							$dictionary_array['I'][$row] = "$yyyy-$mm-$dd $thistime";
							## Use RegEx to evaluate the value based upon validation type
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all[(substr($this_val_type, 0, 16) == "datetime_seconds" ? 'datetime_seconds_ymd' : 'datetime_ymd')]['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['I'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex) {
								$errors_array[41][] = "<b>\"".RCView::escape($origValueI)."\"</b> (I{$row})</b>";
							}
						}
						// DATETIME MAX VALIDATION
						if ($dictionary_array['J'][$row] != "" && strpos($dictionary_array['J'][$row], "/"))
						{
							list ($thisdate, $thistime) = explode(" ", $dictionary_array['J'][$row], 2);
							// Determine if D/M/Y or M/D/Y format
							if ($_POST['date_format'] == 'DMY') {
								list ($dd, $mm, $yyyy) = explode('/', $thisdate);
							} else {
								list ($mm, $dd, $yyyy) = explode('/', $thisdate);
							}
							if (strlen($yyyy) == 2) $yyyy = "20".$yyyy;
							$mm = sprintf("%02d", $mm);
							$dd = sprintf("%02d", $dd);
							if (substr($this_val_type, 0, 16) == "datetime_seconds") {
								if (strlen($thistime) <= 5 && strpos($thistime, ":") !== false) {
									// If Excel cut off the seconds from the end of the time component, append ":00"
									$thistime .= ":00";
								}
								if (strlen($thistime) < 8 && strpos($thistime, ":") !== false) {
									// Add leading zeroes where needed for time
									$thistime = "0".$thistime;
								}
							} else {
								if (strlen($thistime) < 5) $thistime = "0".$thistime;
							}
							$dictionary_array['J'][$row] = "$yyyy-$mm-$dd $thistime";
							## Use RegEx to evaluate the value based upon validation type
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all[(substr($this_val_type, 0, 16) == "datetime_seconds" ? 'datetime_seconds_ymd' : 'datetime_ymd')]['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['J'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex) {
								$errors_array[41][] = "<b>\"".RCView::escape($origValueJ)."\"</b> (J{$row})</b>";
							}
						}
					}
					// Date formats
					elseif (substr($this_val_type, 0, 4) == "date") {
						// DATE MIN VALIDATION
						if ($dictionary_array['I'][$row] != "" && strpos($dictionary_array['I'][$row], "/"))
						{
							// Determine if D/M/Y or M/D/Y format
							if ($_POST['date_format'] == 'DMY') {
								list ($dd, $mm, $yyyy) = explode('/', $dictionary_array['I'][$row]);
							} else {
								list ($mm, $dd, $yyyy) = explode('/', $dictionary_array['I'][$row]);
							}
							if (strlen($yyyy) == 2) $yyyy = "20".$yyyy;
							$mm = sprintf("%02d", $mm);
							$dd = sprintf("%02d", $dd);
							$dictionary_array['I'][$row] = "$yyyy-$mm-$dd";
							## Use RegEx to evaluate the value based upon validation type
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all['date_ymd']['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['I'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex) {
								$errors_array[40][] = "<b>\"".RCView::escape($origValueI)."\"</b> (I{$row})</b>";
							}
						}
						// DATE MAX VALIDATION
						if ($dictionary_array['J'][$row] != "" && strpos($dictionary_array['J'][$row], "/")) {
							// Determine if D/M/Y or M/D/Y format
							if ($_POST['date_format'] == 'DMY') {
								list ($dd, $mm, $yyyy) = explode('/', $dictionary_array['J'][$row]);
							} else {
								list ($mm, $dd, $yyyy) = explode('/', $dictionary_array['J'][$row]);
							}
							if (strlen($yyyy) == 2) $yyyy = "20".$yyyy;
							$mm = sprintf("%02d", $mm);
							$dd = sprintf("%02d", $dd);
							$dictionary_array['J'][$row] = "$yyyy-$mm-$dd";
							## Use RegEx to evaluate the value based upon validation type
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all['date_ymd']['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['J'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex) {
								$errors_array[40][] = "<b>\"".RCView::escape($origValueJ)."\"</b> (J{$row})</b>";
							}
						}
					}
					// Time
					elseif ($this_val_type == "time") {
						if ($dictionary_array['I'][$row] != "" && strpos($dictionary_array['I'][$row], ":")) {
							if (strlen($dictionary_array['I'][$row]) < 5) $dictionary_array['I'][$row] = "0".$dictionary_array['I'][$row];
						}
						if ($dictionary_array['J'][$row] != "" && strpos($dictionary_array['J'][$row], ":")) {
							if (strlen($dictionary_array['J'][$row]) < 5) $dictionary_array['J'][$row] = "0".$dictionary_array['J'][$row];
						}
					}
					// LOWERCASE LETTERS
					if ($this_val_type != strtolower($this_val_type)) {
						$warnings_array[7][] = "<b>".RCView::escape($this_val_type)." (H{$row})</b> {$lang['database_mods_15']} <b>" . strtolower($this_val_type) . "</b>";
						$dictionary_array['H'][$row] = $this_val_type = strtolower($this_val_type);
					}
					// CHECK IF VALID VALIDATION TYPE
					if (in_array($this_val_type, $val_types)) {
						// Allow non-legacy values for validation types (and reformat to new equivalents)
						if ($this_val_type == "int") {
							$dictionary_array['H'][$row] = $this_val_type = "integer";
						} elseif ($this_val_type == "float") {
							$dictionary_array['H'][$row] = $this_val_type = "number";
						} elseif ($this_val_type == "date") {
							$dictionary_array['H'][$row] = $this_val_type = "date_ymd";
						} elseif ($this_val_type == "datetime") {
							$dictionary_array['H'][$row] = $this_val_type = "datetime_ymd";
						} elseif ($this_val_type == "datetime_seconds") {
							$dictionary_array['H'][$row] = $this_val_type = "datetime_seconds_ymd";
						}
					} else {
						// Not a valid validation type
						$errors_array[16][] = "<b>".RCView::escape($this_val_type)." (H{$row})</b>";
					}
					// VALIDATE THE MIN/MAX VALUES (exclude date or datetime fields because they have already been pre-formatted to YMD format)
					$this_data_type = $val_types_all[$this_val_type]['data_type'];
					if (!in_array($this_data_type, array('date', 'datetime', 'datetime_seconds'))) {
						if ($dictionary_array['I'][$row] != "") {
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all[$this_val_type]['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['I'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex && !($this_data_type == 'number' && is_numeric($dictionary_array['I'][$row]))) {
								// If min/max value is a number when it's a "number" data type, then allow it
								// even though it failed validation (this helps where Excel removes decimals when users
								// are using the number_Xdp validations).
								$errors_array[45][] = "<b>\"".RCView::escape($dictionary_array['I'][$row])."\"</b> (I{$row})</b>";
							}
						}
						if ($dictionary_array['J'][$row] != "") {
							// Set regex pattern to use for this field
							$regex_pattern = $val_types_all[$this_val_type]['regex_php'];
							// Run the value through the regex pattern
							preg_match($regex_pattern, $dictionary_array['J'][$row], $regex_matches);
							// Was it validated? (If so, will have a value in 0 key in array returned.)
							$failed_regex = (!isset($regex_matches[0]));
							// Set error message if failed regex
							if ($failed_regex && !($this_data_type == 'number' && is_numeric($dictionary_array['J'][$row]))) {
								// If min/max value is a number when it's a "number" data type, then allow it
								// even though it failed validation (this helps where Excel removes decimals when users
								// are using the number_Xdp validations).
								$errors_array[46][] = "<b>\"".RCView::escape($dictionary_array['J'][$row])."\"</b> (J{$row})</b>";
							}
						}
					}
				}
			}
		}
		if (isset($errors_array[16])) {
			$errors_array[16] = "{$lang['database_mods_48']} <u>" . implode("</u>, <u>", $visible_val_types) .
								"</u> {$lang['database_mods_49']}<br>" . implode("<br>", $errors_array[16]);
		}
		if (isset($errors_array[17])) {
			$errors_array[17] = $lang['database_mods_50'] . implode(", ", $errors_array[17]);
		}
		if (isset($warnings_array[7])) {
			$warnings_array[7] = "{$lang['database_mods_51']}<br>" . implode("<br>", $warnings_array[7]);
		}
		if (isset($errors_array[40])) {
			$errors_array[40] = ($_POST['date_format'] == 'DMY' ? $lang['data_import_tool_188'] : $lang['data_import_tool_90'])."<br>" . implode("<br>", $errors_array[40]);
		}
		if (isset($errors_array[41])) {
			$errors_array[41] = ($_POST['date_format'] == 'DMY' ? $lang['data_import_tool_189'] : $lang['data_import_tool_150'])."<br>" . implode("<br>", $errors_array[41]);
		}
		if (isset($errors_array[45])) {
			$errors_array[45] = $lang['data_import_tool_198']."<br>" . implode("<br>", $errors_array[45]);
		}
		if (isset($errors_array[46])) {
			$errors_array[46] = $lang['data_import_tool_199']."<br>" . implode("<br>", $errors_array[46]);
		}
		if (isset($errors_array[47])) {
			$errors_array[47] = $lang['data_import_tool_264']." <b>" . implode("</b>, <b>", $errors_array[47]) . "</b>";
		}

		## IDENTIFIERS
		foreach ($dictionary_array['K'] as $row => $this_identifier) {
			if ($this_identifier != "") {
				if (trim(strtolower($this_identifier)) != "y") {
					$warnings_array[8][] = "<b>$this_identifier (K{$row})</b>";
					$dictionary_array['K'][$row] = "";
				}
			}
		}
		if (isset($warnings_array[8])) {
			$warnings_array[8] = "{$lang['database_mods_52']}<br>" . implode("<br>", $warnings_array[8]);
		}
		## BRANCHING LOGIC
		foreach ($dictionary_array['L'] as $row => $this_branching) {
			if ($this_branching != "") {
				// Check for any stray spaces
				if (trim($this_branching) == "") {
					$this_branching = $dictionary_array['L'][$row] = trim($this_branching);
					continue;
				}
				// Do simple check to see if there are basic errors in branching
				if (substr_count($this_branching, "(") != substr_count($this_branching, ")")
					|| substr_count($this_branching, "[") != substr_count($this_branching, "]")
					|| substr_count($this_branching, "'")%2 != 0
					|| substr_count($this_branching, "\"")%2 != 0
					) {
					$errors_array[19][] = "<b>L{$row}</b>";
				// Check to make sure there are no spaces or illegal characters within square brackets in logic
				} else {
					$branch_preg = cleanBranchingOrCalc($this_branching);
					if ($this_branching != $branch_preg) {
						$warnings_array[11][] = "<b>{$dictionary_array['L'][$row]} (L{$row})</b> {$lang['database_mods_15']} <b>$branch_preg</b>";
					}
					$dictionary_array['L'][$row] = $this_branching = $branch_preg;
				}
				// Check to make sure all variables within square brackets are real variables in column A (or if they are a Form Status field)
				// If any fieldnames have parenthesis inside brackets (used for checkbox logic), then strip out parenthesis
				if (!$appendFields) {
					$branchFields = array_keys(getBracketedFields(cleanBranchingOrCalc($this_branching), true, true, true));
					foreach ($branchFields as $this_field) {
						if (!in_array($this_field, $dictionary_array['A']) && !in_array($this_field, $formStatusFields) && !in_array($this_field, $smartVars)) {
							$errors_array[20][] = "<b>$this_field (L{$row})</b>";
						}
					}
				}
				// Check the logic for illegal functions
				$parser = new LogicParser();
				try {
					$parser->parse($this_branching);
				} catch (LogicException $e) {
					if (count($parser->illegalFunctionsAttempted) !== 0) {
						// Contains illegal functions
						if (SUPER_USER) {
							// For super users, only warn them but allow it
							$warnings_array[20][] = "<b>\"".implode("()\", \"", $parser->illegalFunctionsAttempted)."()\" (L{$row})</b>";
						} else {
							// For normal users, do not allow it (unless the branching is the same as before)
							$old_branching = ($status == 0 ? $Proj->metadata[$dictionary_array['A'][$row]]['branching_logic'] : $Proj->metadata_temp[$dictionary_array['A'][$row]]['branching_logic']);
							if ($this_branching == trim(label_decode($old_branching))) {
								$warnings_array[22][] = "<b>{$dictionary_array['A'][$row]} (L{$row})</b>";
							} else {
								$errors_array[35][] = "<b>\"".implode("()\", \"", $parser->illegalFunctionsAttempted)."()\" (L{$row})</b>";
							}
						}
					} else {
						// Contains invalid syntax
						if (SUPER_USER) {
							// For super users, only warn them but allow it
							$warnings_array[21][] = "<b>{$dictionary_array['A'][$row]} (L{$row})</b>";
						} else {
							// For normal users, do not allow it (unless the branching is the same as before)
							$old_branching = ($status == 0 ? $Proj->metadata[$dictionary_array['A'][$row]]['branching_logic'] : $Proj->metadata_temp[$dictionary_array['A'][$row]]['branching_logic']);
							if ($this_branching == trim(label_decode($old_branching))) {
								$warnings_array[22][] = "<b>{$dictionary_array['A'][$row]} (L{$row})</b>";
							} else {
								$errors_array[36][] = "<b>{$dictionary_array['A'][$row]} (L{$row})</b>";
							}
						}
					}
				}
			}
		}
		if (isset($errors_array[19])) {
			$errors_array[19] = $lang['database_mods_53'] . implode(", ", $errors_array[19]);
		}
		if (isset($warnings_array[11])) {
			$warnings_array[11] = "{$lang['database_mods_54']}<br>" . implode("<br>", $warnings_array[11]);
		}
		if (isset($errors_array[20])) {
			$errors_array[20] = "{$lang['database_mods_55']}<br>" . implode("<br>", $errors_array[20]);
		}
		if (isset($errors_array[35])) {
			$errors_array[35] = "{$lang['design_442']}<br>" . implode("<br>", $errors_array[35]);
		}
		if (isset($warnings_array[20])) {
			$warnings_array[20] = "{$lang['design_443']}<br>" . implode("<br>", $warnings_array[20]);
		}
		if (isset($errors_array[36])) {
			$errors_array[36] = "{$lang['design_444']}<br>" . implode("<br>", $errors_array[36]);
		}
		if (isset($warnings_array[21])) {
			$warnings_array[21] = "{$lang['design_445']}<br>" . implode("<br>", $warnings_array[21]);
		}
		if (isset($warnings_array[22])) {
			$warnings_array[22] = "{$lang['design_446']}<br>" . implode("<br>", $warnings_array[22]);
		}

		## REQUIRED FIELDS
		foreach ($dictionary_array['M'] as $row => $this_req_field) {
			if ($this_req_field != "") {
				// If illegal formatting for "y"
				if (trim(strtolower($this_req_field)) != "y") {
					$warnings_array[9][] = "<b>$this_req_field (M{$row})</b>";
					$dictionary_array['M'][$row] = $this_req_field = "";
				// Make sure advcheckbox and descriptive fields are not "required" (since "unchecked" is technically a real value)
				} elseif ($dictionary_array['D'][$row] == "descriptive" || $dictionary_array['D'][$row] == "advcheckbox") {
					$dictionary_array['M'][$row] = $this_req_field = "";
					$warnings_array[12][] = "<b>F{$row} {$lang['database_mods_56']} \"{$dictionary_array['A'][$row]}\"</b>";
				}
			}
		}
		if (isset($warnings_array[9])) {
			$warnings_array[9] = "{$lang['database_mods_57']}<br>" . implode("<br>", $warnings_array[9]);
		}
		if (isset($warnings_array[12])) {
			$warnings_array[12] = "{$lang['database_mods_128']}<br>" . implode("<br>", $warnings_array[12]);
		}

		## CUSTOM ALIGNMENT
		foreach ($dictionary_array['N'] as $row => $this_align) {
			if ($this_align != "") {
				// Allowable alignments
				$align_options = array('LV', 'LH', 'RV', 'RH');
				// If illegal formatting, then warn and set to blank (default)
				if (!in_array($this_align, $align_options)) {
					$warnings_array[15][] = "<b>$this_align (N{$row})</b>";
					$dictionary_array['N'][$row] = "";
				}
			}
		}
		if (isset($warnings_array[15])) {
			$warnings_array[15] = $lang['database_mods_106'] . " '" . implode("', '", $align_options) . "'" . $lang['period']
								. " " . $lang['database_mods_107'] . " " . implode(", ", $warnings_array[15]);
		}


		## MATRIX GROUP NAMES
		$prev_group = "";
		$group_key = array();
		$group_enum_check = array();
		$group_fieldtype_check = array();
		foreach ($dictionary_array['P'] as $row => $this_group)
		{
			// Trim it
			$dictionary_array['P'][$row] = $this_group = trim($this_group);
			//GROUP NAMES SHOULD NOT BE LONGER THAN 60 CHARS
			if (strlen($this_group) > 60) {
				$errors_array[31][] = "<b>$this_group (A{$row})</b>";
			}
			//LOWERCASE LETTERS, NUMBERS, AND UNDERSCORES
			if ($this_group != preg_replace("/[^a-z_0-9]/", "", $this_group)) {
				// Remove illegal characters first
				$dictionary_array['P'][$row] = preg_replace("/[^a-z_0-9]/", "", str_replace(" ", "_", strtolower($this_group)));
				// Remove any double underscores, beginning numerals, and beginning/ending underscores
				while (strpos($dictionary_array['P'][$row], "__") !== false) 	$dictionary_array['P'][$row] = str_replace("__", "_", $dictionary_array['P'][$row]);
				while (substr($dictionary_array['P'][$row], 0, 1) == "_") 		$dictionary_array['P'][$row] = substr($dictionary_array['P'][$row], 1);
				while (substr($dictionary_array['P'][$row], -1) == "_") 		$dictionary_array['P'][$row] = substr($dictionary_array['P'][$row], 0, -1);
				// Set warning flag
				$warnings_array[18][] = "<b>$this_group (P{$row})</b> {$lang['database_mods_15']} <b>" . $dictionary_array['P'][$row] . "</b>";
				$this_group = $dictionary_array['P'][$row];
			}
			//GROUPS MUST BE SEQUENTIAL
			if ($prev_group != $this_group && isset($group_key[$this_group])) {
				$errors_array[32][] = "<b>$this_group (P{$row})</b>";
			}
			if ($this_group != '') {
				// Collect form names as unique, and add grid_rank as value
				$group_key[$this_group][] = (trim(strtolower($dictionary_array['Q'][$row])) == 'y' ? '1' : '0');
				// Make sure only checkboxes/radios are in a matrix group
				if ($dictionary_array['D'][$row] != 'radio' && $dictionary_array['D'][$row] != 'checkbox') {
					$errors_array[34][] = "<b>{$dictionary_array['D'][$row]} (D{$row})</b>";
				} else {
					// Make sure all fields in a single matrix group are either all radios or all checkboxes
					if (isset($group_fieldtype_check[$this_group])) {
						if ($group_fieldtype_check[$this_group] != $dictionary_array['D'][$row]) {
							$warnings_array[19][] = "<b>{$dictionary_array['D'][$row]} (D{$row})</b> {$lang['database_mods_127']} <b>{$group_fieldtype_check[$this_group]}</b>";
							// Change the field type
							$dictionary_array['D'][$row] = $group_fieldtype_check[$this_group];
						}
					} else {
						// Add field type to array to track later
						$group_fieldtype_check[$this_group] = $dictionary_array['D'][$row];
					}
					// Collect matrix group's choices as unique to make sure all field in a matrix have same choices
					if (isset($group_enum_check[$this_group])) {
						// Check to see if has same choices for this group
						if ($group_enum_check[$this_group] !== parseEnum(str_replace("|", "\\n", $dictionary_array['F'][$row]))) {
							// Convert back to DD format choice string
							$choices_string = array();
							foreach ($group_enum_check[$this_group] as $code=>$label) {
								$choices_string[] = "$code, $label";
							}
							$errors_array[33][] = "<b>F{$row}</b> - {$lang['database_mods_124']} <b>".implode(" | ", $choices_string)."</b>";
						}
					} else {
						// First field in group, so add to array
						$group_enum_check[$this_group] = parseEnum(str_replace("|", "\\n", $dictionary_array['F'][$row]));
					}
				}
			}
			//Set for next loop
			$prev_group = $this_group;
		}
		if (isset($warnings_array[18])) {
			$warnings_array[18] = "{$lang['database_mods_120']}<br>" . implode("<br>", $warnings_array[18]);
		}
		if (isset($warnings_array[19])) {
			$warnings_array[19] = "{$lang['database_mods_126']}<br>" . implode("<br>", $warnings_array[19]);
		}
		if (isset($errors_array[31])) {
			$errors_array[31] = "{$lang['database_mods_121']}<br>" . implode("<br>", $errors_array[31]);
		}
		if (isset($errors_array[32])) {
			$errors_array[32] = "{$lang['database_mods_122']}<br>" . implode("<br>", $errors_array[32]);
		}
		if (isset($errors_array[33])) {
			$errors_array[33] = "{$lang['database_mods_123']}<br>" . implode("<br>", $errors_array[33]);
		}
		if (isset($errors_array[34])) {
			$errors_array[34] = "{$lang['database_mods_125']}<br>" . implode("<br>", $errors_array[34]);
		}

		## FIELD ANNOTATION
		foreach ($dictionary_array['R'] as $row => $this_field)
		{
			// For Excel compatibility, add space to pad anything that begins with @, which denotes a function in Excel
			if (!defined("API") && substr($this_field, 0, 2) == ' @') {
				$dictionary_array['R'][$row] = substr($this_field, 1);
			}
		}

		## MATRIX RANKING
		foreach ($dictionary_array['Q'] as $row => $this_rank) {
			if ($this_rank != "") {
				if (trim(strtolower($this_rank)) != "y") {
					$warnings_array[26][] = "<b>$this_rank (Q{$row})</b>";
					$dictionary_array['Q'][$row] = "";
				}
			}
		}
		// Loop through each matrix group and make sure all have the same ranking value (y or blank)
		foreach ($group_key as $this_group_name=>$these_rank_values) {
			if (count(array_unique($these_rank_values)) > 1) {
				$errors_array[39][] = "<b>$this_group_name</b>";
			}
		}
		if (isset($warnings_array[26])) {
			$warnings_array[26] = "{$lang['database_mods_155']}<br>" . implode("<br>", $warnings_array[26]);
		}
		if (isset($errors_array[39])) {
			$errors_array[39] = "{$lang['database_mods_156']}<br>" . implode(", ", $errors_array[39]);
		}

		## PROMIS instrument check: Make sure no fields were modified for PROMIS adaptive instruments
		if (!$bypassPromisCheck && !empty($promis_forms)) {
			$promis_fields_Proj = array();
			// Get arrays of forms/fields
			$all_current_forms  = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
			$all_current_fields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
			// Existing PROMIS field count
			$existing_promis_field_count = 0;
			$existing_promis_field_count_per_form = array();
			$deleted_promis_field_count_per_form = array();
			// Check each PROMIS instrument one at a time
			foreach ($promis_forms as $promis_form) {
				foreach (array_keys($all_current_forms[$promis_form]['fields']) as $promis_field) {
					// Ignore form status field and Record ID field
					if ($promis_field == $table_pk || $promis_field == $promis_form.'_complete') continue;
					// Increment existing PROMIS field count
					$existing_promis_field_count++;
					$existing_promis_field_count_per_form[$promis_form] = (isset($existing_promis_field_count_per_form[$promis_form])) ? $existing_promis_field_count_per_form[$promis_form]+1 : 1;
					if (array_search($promis_field, $dictionary_array['A']) === false) {
						$deleted_promis_field_count_per_form[$promis_form] = (isset($deleted_promis_field_count_per_form[$promis_form])) ? $deleted_promis_field_count_per_form[$promis_form]+1 : 1;
					}
				}
			}
			foreach ($promis_forms as $promis_form) {
				foreach (array_keys($all_current_forms[$promis_form]['fields']) as $promis_field) {
					// Ignore form status field and Record ID field
					if ($promis_field == $table_pk || $promis_field == $promis_form.'_complete') continue;
					// If all fields in this form were deleted (which is fine), then skip this form
					if ($existing_promis_field_count_per_form[$promis_form] == $deleted_promis_field_count_per_form[$promis_form]) continue;
					// Get row number and determine if field was deleted
					$rownum = array_search($promis_field, $dictionary_array['A']);
					$fieldDeleted = ($rownum === false);
					$rowtext = ($fieldDeleted) ? $lang['database_mods_159'] : "({$lang['database_mods_160']} $rownum)";
					// Check first 5 columsn to compare to existing field values
					if ($promis_fields_DD[$promis_field]['A'] != $all_current_fields[$promis_field]['field_name']) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
					if ($promis_fields_DD[$promis_field]['B'] != $all_current_fields[$promis_field]['form_name']) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
					if ($promis_fields_DD[$promis_field]['C'] != $all_current_fields[$promis_field]['element_preceding_header']) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
					if ($promis_fields_DD[$promis_field]['F'] != str_replace("\\n","|",$all_current_fields[$promis_field]['element_enum'])) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
					if ($all_current_fields[$promis_field]['element_type'] == 'textarea') {
						// Convert from legacy/back-end field type
						$all_current_fields[$promis_field]['element_type'] = 'notes';
					}
					if ($promis_fields_DD[$promis_field]['D'] != $all_current_fields[$promis_field]['element_type']) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
					if ($promis_fields_DD[$promis_field]['E'] != $all_current_fields[$promis_field]['element_label']) {
						$errors_array[42][] = "<b>$promis_field</b> $rowtext";
						continue;
					}
				}
			}
			// If user changed any PROMIS field, throw error
			if (isset($errors_array[42])) {
				$errors_array[42] = "{$lang['database_mods_157']}<br>" . implode("<br>", $errors_array[42]);
			}
			// Make sure there are no extra fields trying to be adding to the PROMIS form
			if (!empty($existing_promis_field_count_per_form) && count($promis_fields_DD) > $existing_promis_field_count) {
				$errors_array[43] = "{$lang['database_mods_158']}<br><b>" . implode("<br>", array_keys($existing_promis_field_count_per_form)) . "</b>";
			}
		}

		// If we need to remove some fields, do it here last
		if (!empty($fields_to_remove))
		{
			// Loop and remove each
			foreach ($fields_to_remove as $key) {
				foreach (array_keys($dictionary_array) as $colname) {
					unset($dictionary_array[$colname][$key]);
				}
			}
			// Now reset indexes
			foreach (array_keys($dictionary_array) as $colname) {
				$dictionary_array[$colname] = array_merge(array(0=>''), array_values($dictionary_array[$colname]));
				unset($dictionary_array[$colname][0]);
			}
		}

		// Return the cleaned data dictionary and any errors or warnings
		return array($errors_array, $warnings_array, $dictionary_array);
	}


	// Save metadata when in DD array format
	public static function save_metadata($dictionary_array, $appendFields=false, $preventLogging=false)
	{
		global $status, $longitudinal, $Proj;

		// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

		// DEV ONLY: Only run the following actions (change rights level, events designation) if in Development
		if ($status < 1)
		{
			// If new forms are being added, give all users "read-write" access to this new form
			$existing_form_names = array();
			if (!$appendFields) {
				$sql = "select distinct form_name from $metadata_table where project_id = " . PROJECT_ID;
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					$existing_form_names[] = $row['form_name'];
				}
			}
			$newforms = array();
			foreach (array_unique($dictionary_array['B']) as $new_form) {
				if (!in_array($new_form, $existing_form_names)) {
					//Add rights for EVERY user for this new form
					$newforms[] = $new_form;
					//Add all new forms to redcap_events_forms table
					if (!$longitudinal) {
						$sql = "insert into redcap_events_forms (event_id, form_name) select m.event_id, '$new_form'
								from redcap_events_arms a, redcap_events_metadata m
								where a.project_id = " . PROJECT_ID . " and a.arm_id = m.arm_id";
						db_query($sql);
					}
				}
			}

			//Add new forms to rights table
			$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[".implode(",1][", $newforms).",1]') where project_id = " . PROJECT_ID;
			db_query($sql);
			//Also delete form-level user rights for any forms deleted (as clean-up)
			if (!$appendFields) {
				foreach (array_diff($existing_form_names, array_unique($dictionary_array['B'])) as $deleted_form) {
					//Loop through all 3 data_entry rights level states to catch all instances
					for ($i = 0; $i <= 2; $i++) {
						$sql = "update redcap_user_rights set data_entry = replace(data_entry,'[$deleted_form,$i]','') where project_id = " . PROJECT_ID;
						db_query($sql);
					}
					//Delete all instances in redcap_events_forms
					$sql = "delete from redcap_events_forms where event_id in
							(select m.event_id from redcap_events_arms a, redcap_events_metadata m, redcap_projects p where a.arm_id = m.arm_id
							and p.project_id = a.project_id and p.project_id = " . PROJECT_ID . ") and form_name = '$deleted_form;";
					db_query($sql);
				}
			}

			## CHANGE FOR MULTIPLE SURVEYS????? (Should we ALWAYS assume that if first form is a survey that we should preserve first form as survey?)
			// If using first form as survey and form is renamed in DD, then change form_name in redcap_surveys table to the new form name
			if (!$appendFields && isset($Proj->forms[$Proj->firstForm]['survey_id']))
			{
				$columnB = $dictionary_array['B'];
				$newFirstForm = array_shift(array_unique($columnB));
				unset($columnB);
				// Do not rename in table if the new first form is ALSO a survey (assuming it even exists)
				if ($newFirstForm != '' && $Proj->firstForm != $newFirstForm && !isset($Proj->forms[$newFirstForm]['survey_id']))
				{
					// Change form_name of survey to the new first form name
					$sql = "update redcap_surveys set form_name = '$newFirstForm' where survey_id = ".$Proj->forms[$Proj->firstForm]['survey_id'];
					db_query($sql);
				}
			}
		}

		// Build array of existing form names and their menu names to try and preserve any existing menu names
		$q = db_query("select form_name, form_menu_description from $metadata_table where project_id = " . PROJECT_ID . " and form_menu_description is not null");
		$existing_form_menus = array();
		while ($row = db_fetch_assoc($q)) {
			$existing_form_menus[$row['form_name']] = $row['form_menu_description'];
		}

		// Before wiping out current metadata, obtain values in table not contained in data dictionary to preserve during carryover (e.g., edoc_id)
		$sql = "select field_name, edoc_id, edoc_display_img, stop_actions, field_units, video_url, video_display_inline
				from $metadata_table where project_id = " . PROJECT_ID . "
				and (edoc_id is not null or stop_actions is not null or field_units is not null or video_url is not null)";
		$q = db_query($sql);
		$extra_values = array();
		while ($row = db_fetch_assoc($q))
		{
			if (!empty($row['edoc_id'])) {
				// Preserve edoc values
				$extra_values[$row['field_name']]['edoc_id'] = $row['edoc_id'];
				$extra_values[$row['field_name']]['edoc_display_img'] = $row['edoc_display_img'];
			}
			if ($row['stop_actions'] != "") {
				// Preserve stop_actions value
				$extra_values[$row['field_name']]['stop_actions'] = $row['stop_actions'];
			}
			if ($row['field_units'] != "") {
				// Preserve field_units value (no longer included in data dictionary but will be preserved if defined before 4.0)
				$extra_values[$row['field_name']]['field_units'] = $row['field_units'];
			}
			if ($row['video_url'] != "") {
				// Preserve video_url value
				$extra_values[$row['field_name']]['video_url'] = $row['video_url'];
				$extra_values[$row['field_name']]['video_display_inline'] = $row['video_display_inline'];
			}
		}

		// Determine if we need to replace ALL fields or append to existing fields
		if ($appendFields) {
			// Only append new fields to existing metadata (as opposed to replacing them all)
			$sql = "select max(field_order)+1 from $metadata_table where project_id = " . PROJECT_ID;
			$q = db_query($sql);
			$field_order = db_result($q, 0);
		} else {
			// Default field order value
			$field_order = 1;
			// Delete all instances of metadata for this project to clean out before adding new
			db_query("delete from $metadata_table where project_id = " . PROJECT_ID);
		}

		// Capture any SQL errors
		$sql_errors = array();
		// Create array to keep track of form names for building form_menu_description logic
		$form_names = array();
		// Set up exchange values for replacing legacy back-end values
		$convertValType = array("integer"=>"int", "number"=>"float");
		$convertFldType = array("notes"=>"textarea", "dropdown"=>"select", "drop-down"=>"select");

		// Loop through data dictionary array and save into metadata table
		foreach (array_keys($dictionary_array['A']) as $i)
		{
			// If this is the first field of a form, generate form menu description for upcoming form
			// If form menu description already exists, it may have been customized, so keep old value
			$form_menu = "";
			if (!in_array($dictionary_array['B'][$i], $form_names)) {
				if (isset($existing_form_menus[$dictionary_array['B'][$i]])) {
					// Use existing value if form existed previously
					$form_menu = $existing_form_menus[$dictionary_array['B'][$i]];
				} else {
					// Create menu name on the fly
					$form_menu = ucwords(str_replace("_", " ", $dictionary_array['B'][$i]));
				}
			}
			// Deal with hard/soft validation checktype for text fields
			$valchecktype = ($dictionary_array['D'][$i] == "text") ? "'soft_typed'" : "NULL";
			// Swap out Identifier "y" with "1"
			$dictionary_array['K'][$i] = (strtolower(trim($dictionary_array['K'][$i])) == "y") ? "'1'" : "NULL";
			// Swap out Required Field "y" with "1"	(else "0")
			$dictionary_array['M'][$i] = (strtolower(trim($dictionary_array['M'][$i])) == "y") ? "'1'" : "'0'";
			// Format multiple choices
			if ($dictionary_array['F'][$i] != "" && $dictionary_array['D'][$i] != "calc" && $dictionary_array['D'][$i] != "slider" && $dictionary_array['D'][$i] != "sql") {
				$dictionary_array['F'][$i] = str_replace(array("|","\n"), array("\\n"," \\n "), $dictionary_array['F'][$i]);
			}
			// Do replacement of front-end values with back-end equivalents
			if (isset($convertFldType[$dictionary_array['D'][$i]])) {
				$dictionary_array['D'][$i] = $convertFldType[$dictionary_array['D'][$i]];
			}
			if ($dictionary_array['H'][$i] != "" && $dictionary_array['D'][$i] != "slider") {
				// Replace with legacy/back-end values
				if (isset($convertValType[$dictionary_array['H'][$i]])) {
					$dictionary_array['H'][$i] = $convertValType[$dictionary_array['H'][$i]];
				}
			} elseif ($dictionary_array['D'][$i] == "slider" && $dictionary_array['H'][$i] != "" && $dictionary_array['H'][$i] != "number") {
				// Ensure sliders only have validation type of "" or "number" (to display number value or not)
				$dictionary_array['H'][$i] = "";
			}
			// Make sure question_num is 10 characters or less
			if (strlen($dictionary_array['O'][$i]) > 10) $dictionary_array['O'][$i] = substr($dictionary_array['O'][$i], 0, 10);
			// Swap out Matrix Rank "y" with "1" (else "0")
			$dictionary_array['Q'][$i] = (strtolower(trim($dictionary_array['Q'][$i])) == "y") ? "'1'" : "'0'";
			// Remove any hex'ed double-CR characters in field labels, etc.
			$dictionary_array['E'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['E'][$i]);
			$dictionary_array['C'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['C'][$i]);
			$dictionary_array['F'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['F'][$i]);
			// Insert edoc_id and slider display values that should be preserved
			$edoc_id 		  = isset($extra_values[$dictionary_array['A'][$i]]['edoc_id']) ? $extra_values[$dictionary_array['A'][$i]]['edoc_id'] : "NULL";
			$edoc_display_img = isset($extra_values[$dictionary_array['A'][$i]]['edoc_display_img']) ? $extra_values[$dictionary_array['A'][$i]]['edoc_display_img'] : "0";
			$stop_actions 	  = isset($extra_values[$dictionary_array['A'][$i]]['stop_actions']) ? $extra_values[$dictionary_array['A'][$i]]['stop_actions'] : "";
			$field_units	  = isset($extra_values[$dictionary_array['A'][$i]]['field_units']) ? $extra_values[$dictionary_array['A'][$i]]['field_units'] : "";
			$video_url	  	  = isset($extra_values[$dictionary_array['A'][$i]]['video_url']) ? $extra_values[$dictionary_array['A'][$i]]['video_url'] : "";
			$video_display_inline = isset($extra_values[$dictionary_array['A'][$i]]['video_display_inline']) ? $extra_values[$dictionary_array['A'][$i]]['video_display_inline'] : "0";

			// Build query for inserting field
			$sql = "insert into $metadata_table (project_id, field_name, form_name, field_units, element_preceding_header, "
				 . "element_type, element_label, element_enum, element_note, element_validation_type, element_validation_min, "
				 . "element_validation_max, field_phi, branching_logic, element_validation_checktype, form_menu_description, "
				 . "field_order, field_req, edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, "
				 . "grid_name, grid_rank, misc, video_url, video_display_inline) values ("
				 . PROJECT_ID . ", "
				 . checkNull($dictionary_array['A'][$i]) . ", "
				 . checkNull($dictionary_array['B'][$i]) . ", "
				 . checkNull($field_units) . ", "
				 . checkNull($dictionary_array['C'][$i]) . ", "
				 . checkNull($dictionary_array['D'][$i]) . ", "
				 . checkNull($dictionary_array['E'][$i]) . ", "
				 . checkNull($dictionary_array['F'][$i]) . ", "
				 . checkNull($dictionary_array['G'][$i]) . ", "
				 . checkNull($dictionary_array['H'][$i]) . ", "
				 . checkNull($dictionary_array['I'][$i]) . ", "
				 . checkNull($dictionary_array['J'][$i]) . ", "
				 . $dictionary_array['K'][$i] . ", "
				 . checkNull($dictionary_array['L'][$i]) . ", "
				 . "$valchecktype, "
				 . checkNull($form_menu). ", "
				 . "$field_order, "
				 . $dictionary_array['M'][$i] . ", "
				 . "$edoc_id, "
				 . "$edoc_display_img, "
				 . checkNull($dictionary_array['N'][$i]) . ", "
				 . checkNull($stop_actions) . ", "
				 . checkNull($dictionary_array['O'][$i]) . ", "
				 . checkNull($dictionary_array['P'][$i]) . ", "
				 . $dictionary_array['Q'][$i] . ", "
				 . checkNull(isset($dictionary_array['R']) ? $dictionary_array['R'][$i] : null) . ", "
				 . checkNull($video_url) . ", "
				 . "'$video_display_inline'"
				 . ")";

			//Insert into table
			if (db_query($sql)) {
				// Increment field order
				$field_order++;
			} else {
				//Log this error
				$sql_errors[] = $sql;
			}

			//Add Form Status field if we're on the last field of a form
			if (isset($dictionary_array['B'][$i]) && $dictionary_array['B'][$i] != $dictionary_array['B'][$i+1]) {
				//Insert new Form Status field
				$sql = "insert into $metadata_table (project_id, field_name, form_name, field_order, element_type, "
					 . "element_label, element_enum, element_preceding_header) values (" . PROJECT_ID . ", "
					 . "'" . $dictionary_array['B'][$i] . "_complete', '" . $dictionary_array['B'][$i] . "', "
					 . "'$field_order', 'select', 'Complete?', '0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
				//Insert into table
				if (db_query($sql)) {
					// Increment field order
					$field_order++;
				} else {
					//Log this error
					// $sql_errors[] = $sql;
				}
			}

			//Add form name to array for later checking for form_menu_description
			$form_names[] = $dictionary_array['B'][$i];

		}

		// Logging
		if (!$appendFields && !$preventLogging) {
			Logging::logEvent("",$metadata_table,"MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,"Upload data dictionary");
		}

		// Return any SQL errors
		return $sql_errors;
	}

	// Set the label of a given form_name in the metadata table
	public static function setFormLabel($form_name, $label, $draft_mode=false)
	{
		$metadata_table = $draft_mode ? "redcap_metadata_temp" : "redcap_metadata";
		$sql = "update $metadata_table set form_menu_description = '".db_escape($label)."' where form_name = '".db_escape($form_name)."'
				and form_menu_description is not null and project_id = " . PROJECT_ID;
		return db_query($sql);
	}

	// Delete a field in the metadata table
	public static function deleteField($project_id, $field, $draft_mode=false)
	{
		$metadata_table = $draft_mode ? "redcap_metadata_temp" : "redcap_metadata";
		$sql = "delete from $metadata_table where field_name = '".db_escape($field)."' and project_id = $project_id";
		return db_query($sql);
	}

}
