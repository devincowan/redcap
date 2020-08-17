<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

function fieldHandler($arr) {
	global $mainArray;
	$mainArray[] = $arr;
}
function fieldCompare($a, $b) {
  return $a['FieldOrder'] > $b['FieldOrder'];
}
function getFieldStrValue($arr, $field) {
    if(isset($arr[$field])) {
    	$temp = str_replace("\\","\\\\",$arr[$field]);
    	$temp = str_replace("'","''",$temp);
        return "'$temp'";
    }
    return 'null';
}


// Redirect back to Design page if cancelled action
if (isset($_GET['cancel'])) {
	redirect(APP_PATH_WEBROOT.'SharedLibrary/index.php?pid='.$project_id);
}



//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// User defined new form name, so pull form from Library and load into REDCap
if (isset($_POST['import_id']) && $_SESSION['import_id'] != $_POST['import_id'])
{	
	// Is a battery?
	if (!isset($_POST['promis_battery_key'])) $_POST['promis_battery_key'] = '';
    $batterytitle = $_POST['batterytitle'];
	$batteryForms = PROMIS::getBatteryInstrumentList($_POST['promis_battery_key']);
	$libraryFormsToFetch = array();
	if (!empty($batteryForms)) {
		// Get attributes for each instrument in the battery
		$i = 0;
		foreach ($batteryForms as $thisForm) {
			// Obtain instrument attributes
			$url = SHARED_LIB_PATH . "getInstrumentIdByPromisKey.php?promis_key=".urldecode($thisForm['FormOID']);
			$InstrumentAttr = http_get($url);
			$InstrumentAttr = json_decode($InstrumentAttr, true);
			if (is_array($InstrumentAttr) && !empty($InstrumentAttr)) {
				$libraryFormsToFetch[$i]['import_id'] = $InstrumentAttr['instrument_id'];
				$libraryFormsToFetch[$i]['isBattery'] = 1;
				$libraryFormsToFetch[$i]['promis_battery_key'] = $_POST['promis_battery_key'];
				$libraryFormsToFetch[$i]['promis_key'] = $thisForm['FormOID'];
				$libraryFormsToFetch[$i]['scoring_type'] = $InstrumentAttr['scoring_type'];
				$libraryFormsToFetch[$i]['newFormDescription'] = ($i+1) . ") " . $InstrumentAttr['title'];
                $libraryFormsToFetch[$i]['surveyTitle'] = $batterytitle;
				$i++;
			}
		}
	} else {
		// Single instrument	
		$import_id = $_SESSION['import_id'] = $_POST['import_id'];
		if (!isset($_POST['promis_key'])) $_POST['promis_key'] = '';
		$promis_key = $_POST['promis_key'];
		if (!isset($_POST['scoring_type'])) $_POST['scoring_type'] = '';
		$scoring_type = $_POST['scoring_type'];	
		$newFormDescription = isset($_POST['new_form']) ? strip_tags(label_decode(urldecode($_POST['new_form']))) : '';
		$libraryFormsToFetch[] = array('import_id'=>$import_id, 'isBattery'=>0, 'promis_key'=>$promis_key, 'scoring_type'=>$scoring_type, 'newFormDescription'=>$newFormDescription);
	}
	
	$libraryFormsToFetchLastKey = max(array_keys($libraryFormsToFetch));
	
	foreach ($libraryFormsToFetch as $lkey=>$attr)
	{
		// Set array attributes as variables
		extract($attr);
		
		// Reload the metadata for $Proj in case we're beginning a new loop
		$Proj->loadMetadata();
	
		// Fetch instrument XMl via curl from Shared Library
		$error = false;
		$error_str = '';
		$curlXml = curl_init();
		curl_setopt($curlXml, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curlXml, CURLOPT_VERBOSE, 0);
		curl_setopt($curlXml, CURLOPT_URL, SHARED_LIB_DOWNLOAD_URL.'?attr=xml&id='.$import_id);
		curl_setopt($curlXml, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlXml, CURLOPT_POST, false);
		curl_setopt($curlXml, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
		curl_setopt($curlXml, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		$xml = curl_exec($curlXml);

		// Make sure the XML is truly UTF-8 encoded
		if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding') && mb_detect_encoding($xml) != "UTF-8") {
			$xml = mb_convert_encoding($xml, "UTF-8");
		}
		 // Replace some characters that cause the XML to be invalid
		$xml = str_replace("&#13;\n", "&lt;br&gt;", $xml);

		$field_order_max = SharedLibrary::getMaxFieldOrderValue($project_id);

		$newFormName = SharedLibrary::getUniqueFormName($newFormDescription);
		$newSurveyTitle = ($surveyTitle != '') ? $surveyTitle : $newFormDescription;

		try {
			$mainArray = array();
			$parser = new SharedLibraryXml();
			$parser->setXml($xml);
			$parser->setFieldHandler('fieldHandler');
			$successFlag = $parser->parse();
			if($successFlag == 0) {
				$error_code = xml_get_error_code($parser);
				throw new Exception("error parsing xml document please contact server administrator (ERROR CODE: " . $error_code  . ")");
			}

			//get mapped names for database columns:
			$map_field_name = SharedLibrary::getMappedElement('field_name');
			$map_field_phi = SharedLibrary::getMappedElement('field_phi');
			$map_form_name = SharedLibrary::getMappedElement('form_name');
			$map_form_menu_description = SharedLibrary::getMappedElement('form_menu_description');
			$map_field_order = SharedLibrary::getMappedElement('field_order');
			$map_field_units = SharedLibrary::getMappedElement('field_units');
			$map_element_preceding_header = SharedLibrary::getMappedElement('element_preceding_header');
			$map_element_type = SharedLibrary::getMappedElement('element_type');
			$map_element_label = SharedLibrary::getMappedElement('element_label');
			$map_element_enum = SharedLibrary::getMappedElement('element_enum');
			$map_element_note = SharedLibrary::getMappedElement('element_note');
			$map_element_validation_type = SharedLibrary::getMappedElement('element_validation_type');
			$map_element_validation_min = SharedLibrary::getMappedElement('element_validation_min');
			$map_element_validation_max = SharedLibrary::getMappedElement('element_validation_max');
			$map_element_validation_checktype = SharedLibrary::getMappedElement('element_validation_checktype');
			$map_branching_logic = SharedLibrary::getMappedElement('branching_logic');
			$map_field_req = SharedLibrary::getMappedElement('field_req');

			$map_image_attachment = SharedLibrary::getMappedElement('image_attachment');
			$map_stored_name = SharedLibrary::getMappedElement('stored_name');
			$map_mime_type = SharedLibrary::getMappedElement('mime_type');
			$map_doc_name = SharedLibrary::getMappedElement('doc_name');
			$map_doc_size = SharedLibrary::getMappedElement('doc_size');
			$map_file_extension = SharedLibrary::getMappedElement('file_extension');
			$map_edoc_display_img = SharedLibrary::getMappedElement('edoc_display_img');

			$map_custom_alignment = SharedLibrary::getMappedElement('custom_alignment');
			$map_stop_actions = SharedLibrary::getMappedElement('stop_actions');
			$map_question_num = SharedLibrary::getMappedElement('mime_type');
			$map_matrix_group_name = SharedLibrary::getMappedElement('grid_name');
			$map_matrix_ranking = SharedLibrary::getMappedElement('grid_rank');
			$map_field_annotation = SharedLibrary::getMappedElement('misc');

			//sort the array by the field_order element
			usort($mainArray, 'fieldCompare');
			$adjustedFieldOrderValue = $field_order_max + 1;

			// Adjust field names to be unique ONLY IF they duplicate an existing field (ignore if this is a Single Survey project)
			$modifiedFieldNames = array();
			$existingFieldNames = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
			foreach ($mainArray as $key=>$field)
			{
				// If this is the T-Score or Error field of a promis instrument, then put $newFormName inside variable name
				if ($promis_key != '' && ($field[$map_field_name] == 'promis_tscore' || $field[$map_field_name] == 'promis_std_error' 
					|| $field[$map_field_name] == 'neuroqol_tscore' || $field[$map_field_name] == 'neuroqol_std_error')) 
				{
					$mainArray[$key][$map_field_name] = $field[$map_field_name] = 
						$modifiedFieldNames[$field[$map_field_name]] = $newFormName.substr($field[$map_field_name], 6);
				}
				// First, check if exists
				if (isset($existingFieldNames[$field[$map_field_name]]))
				{
					// Field already exists, so create new unique name
					$uniqueFieldName = SharedLibrary::getUniqueFieldName($mainArray, $map_field_name, $project_id, $field[$map_field_name]);
					if ($uniqueFieldName != $field[$map_field_name])
					{
						$modifiedFieldNames[$field[$map_field_name]] = $uniqueFieldName;
						$mainArray[$key][$map_field_name] = $uniqueFieldName;
					}
				}
			}

			//modify branching logic and calculated field references for adjusted field names
			//need to do this in a separate loop, because we need to wait for all of the field
			//names to be converted to unique field names first.
			foreach($modifiedFieldNames as $oldName=>$newName)
			{
				foreach($mainArray as $key=>$field)
				{
					if(isset($field[$map_element_enum])) {
						$mainArray[$key][$map_element_enum] = str_replace('['.$oldName.']','['.$newName.']',$field[$map_element_enum]);
						// Replace checkbox syntax
						$mainArray[$key][$map_element_enum] = str_replace('['.$oldName.'(','['.$newName.'(',$field[$map_element_enum]);
					}
					if(isset($field[$map_branching_logic])) {
						$mainArray[$key][$map_branching_logic] = str_replace('['.$oldName.']','['.$newName.']',$field[$map_branching_logic]);
						// Replace checkbox syntax
						$mainArray[$key][$map_branching_logic] = str_replace('['.$oldName.'(','['.$newName.'(',$field[$map_branching_logic]);
					}
					//data conversion formulas can only reference the current field, but it is convenient to make the check here, and allows
					//the rule to be modified in the future allowing conversion formulas to act like calculated fields (referencing other fields)
					if(isset($field['MappedCodes'])) {
						foreach($field['MappedCodes'] as $mappedKey => $mappedValue) {
							if(isset($mappedValue['DataConversion'])) {
								$mainArray[$key]['MappedCodes'][$mappedKey]['DataConversion'] = str_replace('['.$oldName.']','['.$newName.']',$mappedValue['DataConversion']);
								// Replace checkbox syntax
								$mainArray[$key]['MappedCodes'][$mappedKey]['DataConversion'] = str_replace('['.$oldName.'(','['.$newName.'(',$mappedValue['DataConversion']);
							}
						}
					}
				}
			}

			// Set form_menu_description for first field only
			$formMenuDescription = "'".db_escape($newFormDescription)."'";

			// Default value for if form has any attachments
			$has_images = false;

			// Rename any matric group names that already exist in project to prevent duplication
			$matrix_group_name_fields = ($status > 0) ? $Proj->matrixGroupNamesTemp : $Proj->matrixGroupNames;
			$matrix_group_names = array_keys($matrix_group_name_fields);
			$matrix_group_names_transform = array();
			// Loop through fields being imported to find matrix group names
			foreach ($mainArray as $attr) {
				// Get matrix group name, if exists for this field
				$this_mgn = $attr['MatrixGroupName'];
				if ($this_mgn == '') continue;
				// Does matrix group name already exist?
				$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
				$mgn_renamed = false;
				while ($mgn_exists) {
					// Rename it
					// Make sure no longer than 50 characters and append alphanums to end
					$this_mgn = substr($this_mgn, 0, 43) . "_" . substr(sha1(rand()), 0, 6);
					// Does new field exist in existing fields or new fields being added?
					$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
					$mgn_renamed = true;
				}
				// Add to transform array
				if ($mgn_renamed) {
					$matrix_group_names_transform[$attr['MatrixGroupName']] = $this_mgn;
				}
			}
			// Loop through fields being imported to rename matrix group names
			foreach ($mainArray as $key=>$attr) {
				if (isset($matrix_group_names_transform[$attr['MatrixGroupName']])) {
					$mainArray[$key]['MatrixGroupName'] = $matrix_group_names_transform[$attr['MatrixGroupName']];
				}
			}

			// Loop through the incoming form fields and add as new form
			foreach ($mainArray as $field)
			{
				// Set "required field" flag, if applicable
				$fieldReqValue = 0;
				if (isset($field[$map_field_req])) {
					$fieldReqValue = $field[$map_field_req];
				}

				// Format the stop actions to be comma delimited
				$this_stop_actions = array();
				foreach($field['ActionExists'] as $action) {
					$this_stop_actions[] = $action['Trigger'];
				}

				$this_grid_rank = getFieldStrValue($field,$map_matrix_ranking);
				if ($this_grid_rank == 'null') $this_grid_rank = '0';

				// Build query to insert this field
				$sqlInsert = 'insert into ' . $metadata_table .
					' (project_id, field_name, field_phi, form_name, form_menu_description, field_order, field_units, ' .
					'element_preceding_header, element_type, element_label, element_enum, element_note, ' .
					'element_validation_type, element_validation_min, element_validation_max, element_validation_checktype, ' .
					'branching_logic, field_req, ' .
					'custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc ' .
					') values (' .
					$project_id.','.
					'\''.$field[$map_field_name].'\','.								getFieldStrValue($field, $map_field_phi).','.
					'\''.$newFormName.'\','.										$formMenuDescription.','.
					$adjustedFieldOrderValue.','.									getFieldStrValue($field,$map_field_units).','.
					getFieldStrValue($field,$map_element_preceding_header).','.		getFieldStrValue($field,$map_element_type).','.
					getFieldStrValue($field,$map_element_label).','.				getFieldStrValue($field,$map_element_enum).','.
					getFieldStrValue($field,$map_element_note).','.					getFieldStrValue($field,$map_element_validation_type).','.
					getFieldStrValue($field,$map_element_validation_min).','.		getFieldStrValue($field,$map_element_validation_max).','.
					getFieldStrValue($field,$map_element_validation_checktype).','.	getFieldStrValue($field,$map_branching_logic).','.
					$fieldReqValue.','.
					getFieldStrValue($field,$map_custom_alignment).','.
					checkNull(implode(",", $this_stop_actions)).','.
					getFieldStrValue($field,$map_question_num).','.
					getFieldStrValue($field,$map_matrix_group_name).','.
					$this_grid_rank.','.
					getFieldStrValue($field,$map_field_annotation).
					')';

				// Increment field order
				$adjustedFieldOrderValue += 1;
				// Set form menu description to null since only used for first field entry
				$formMenuDescription = 'null';

				// Insert this field
				if (!db_query($sqlInsert)) {
					$error = true;
					$error_str .= db_error();
				} else {
					if(isset($field['MappedCodes'])) {
						foreach($field['MappedCodes'] as $mappedCode) {
							$standard_id = addStandard($mappedCode['StandardName'],$mappedCode['StandardVersion'],$mappedCode['StandardDescription']);
							if($standard_id >= 0) {
								$standard_code_id = addStandardCode($mappedCode['Code'],$mappedCode['CodeDescription'],$standard_id);
								if($standard_code_id >= 0) {
									$dataConversion = "";
									if(isset($mappedCode['DataConversion']) && trim($mappedCode['DataConversion']) != "") {
										$dataConversion = str_replace("\\","\\\\",$mappedCode['DataConversion']);
									}
									$dataConversion2 = "";
									if(isset($mappedCode['DataConversion2']) && trim($mappedCode['DataConversion2']) != "") {
										$dataConversion2 = str_replace("\\","\\\\",$mappedCode['DataConversion2']);
									}
									$success = addMappedStandard($project_id, $field[$map_field_name], $standard_code_id, $dataConversion, $dataConversion2);
									if(!$success) {
										$error = true;
										$error_str .= "error mapping field to code {$mappedCode['Code']}<br>";
										$error_str .= db_error();
									}
								}else {
									$error = true;
									$error_str .= "error adding standard code {$mappedCode['Code']}<br>";
									$error_str .= db_error();
								}
							}else {
								$error = true;
								$error_str .= "error adding standard {$mappedCode['StandardName']} {$mappedCode['StandardVersion']}<br>";
								$error_str .= db_error();
							}
						}
					}
				}

				// ATTACHMENT: If field has an attachments, add it to edocs table and update metadata table with doc_id
				if (is_array($field[$map_image_attachment]))
				{
					$has_images = true;
					$image = $field[$map_image_attachment];
					// Add the attachment to the edocs_metadata table
					$insert_edocs = "insert into redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension,
									 project_id, stored_date) values ('" . db_escape($image[$map_stored_name]) . "', " .
									"'" . db_escape($image[$map_mime_type]) . "', '" . db_escape($image[$map_doc_name]) . "', " .
									"'" . db_escape($image[$map_doc_size]) . "', '" . db_escape($image[$map_file_extension]) . "', " .
									"$project_id, '" . NOW . "')";
					$query_edocs = db_query($insert_edocs);
					$doc_id = db_insert_id();
					// Update the field with edoc_id
					$update_question = "update $metadata_table set edoc_id = $doc_id, edoc_display_img = {$image[$map_edoc_display_img]}
										where field_name = '{$field[$map_field_name]}' and project_id = $project_id";
					$query_question = db_query($update_question);
				}
			}

			// Add the Form Complete field at the end of the form
			$sqlInsert = 'insert into ' . $metadata_table .
				' (project_id, field_name, form_name, field_order, element_preceding_header, ' .
				'element_type, element_label, element_enum, field_req) ' .
				' values (' .
				$project_id.','.
				'\''.$newFormName.'_complete\',\''.$newFormName.'\','.$adjustedFieldOrderValue.','.
				'\'Form Status\',\'select\',\'Complete?\',\'0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete\',0)';
			if (!db_query($sqlInsert))
			{
				$error = true;
				$error_str .= db_error();
			}

			// If form has any attachments, then start downloading them now since we've already added them to the edocs_metadata table
			if ($has_images)
			{
				$params = array('library_id'=>$import_id, 'newFormName'=>$newFormName);
				$imgCurl = curl_init();
				curl_setopt($imgCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($imgCurl, CURLOPT_VERBOSE, 0);
				curl_setopt($imgCurl, CURLOPT_URL, APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/SharedLibrary/image_downloader.php?pid=$project_id");
				curl_setopt($imgCurl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($imgCurl, CURLOPT_POST, true);
				curl_setopt($imgCurl, CURLOPT_TIMEOUT, 1000);
				curl_setopt($imgCurl, CURLOPT_POSTFIELDS, $params);
				curl_setopt($imgCurl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
				curl_setopt($imgCurl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
				$response = curl_exec($imgCurl);
				curl_close($imgCurl);
			}

			// Add new form to rights table for ALL users (development only)
			if ($status < 1) {
				$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[$newFormName,1]') where project_id = " . PROJECT_ID;
				db_query($sql);
			}


			if (!$error)
			{
				// Log the event
				Logging::logEvent("", $metadata_table, "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Download instrument from Shared Library");
			}

			if (!$longitudinal && $status < 1) {
				//enter event info
				$eventSql = "select e.event_id " .
						"from redcap_events_metadata e, redcap_events_arms a " .
						"where a.arm_id = e.arm_id and a.project_id = $project_id limit 1";
				$eventQuery = db_query($eventSql);
				$eventId = -1;
				if($eventRow = db_fetch_array($eventQuery)) {
					$eventId = $eventRow['event_id'];
					$eventSql = "insert into redcap_events_forms (event_id, form_name) values($eventId,'$newFormName')";
					db_query($eventSql);
				}
			}

			//mark the form as downloaded from library
			$shareSql = "insert into redcap_library_map (project_id, form_name, type, library_id, promis_key, scoring_type, battery, promis_battery_key) " .
						"values ($project_id,'".db_escape($newFormName)."',1,'".db_escape($import_id)."',
						".checkNull($promis_key).", ".checkNull($scoring_type).", $isBattery,".checkNull($promis_battery_key).")";
			db_query($shareSql);
			//put a cached copy of the acknowledgement into the database
			$ack = SharedLibrary::getAcknowledgement($project_id,$newFormName);

			// If this instrument is a PROMIS CAT, then enable the instrument as a survey and also enable survey use in the project
			if ($promis_key != '') {
				// Enable surveys for the project, if not yet enabled
				if (!$surveys_enabled) {
					$sql = "update redcap_projects set surveys_enabled = 1 where project_id = $project_id";
					$q = db_query($sql);
				}
				// Set auto-continue if instrument is part of a battery (but is not the last one in a battery)
				$survey_auto_continue = ($isBattery && $lkey < $libraryFormsToFetchLastKey) ? '1' : '0';
				// Set instructions and acknowledgement
                $survey_acknowledgement = "<p><strong>Thank you for taking the survey.</strong></p>\n<p>Have a nice day!</p>";
				$survey_instructions = "<p><strong>Please complete the survey below.</strong></p>\n<p>Thank you!</p>";
				if ($isBattery && $newSurveyTitle != "" && $lkey > 0) $survey_instructions = ""; // Follow-up surveys in a battery should not have any instructions displayed
                // Add new row in surveys table
				$sql = "insert into redcap_surveys (project_id, form_name, title, instructions, acknowledgement, question_auto_numbering, end_survey_redirect_next_survey) values
						($project_id, '".db_escape($newFormName)."', '".db_escape($newSurveyTitle)."', '".db_escape($survey_instructions)."',
						'".db_escape($survey_acknowledgement)."', '0', $survey_auto_continue)";
				$q = db_query($sql);
			}

		}catch(Exception $e) {
			$error = true;
			$error_str = $e->getMessage();
		}
	}

	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

    print "<div class='round' style='border:1px solid #99B5B7;background-color:#E8ECF0;max-width:800px;margin:20px 0;'>";
	print "<h4 style='padding:5px 10px;margin:0;border-bottom:1px solid #ccc;color:#222;'>
				<img src='".APP_PATH_IMAGES."blog_pencil.png'>
				{$lang['shared_library_44']}
			</h4>";
	print "<div style='padding:5px 10px 5px 30px;width:700px;'>";

	if ($error) {
		print  '<p>'.$error_str.'</p>';
	    print  "<p style='font-weight:bold;color:red;'>
					<img src='" . APP_PATH_IMAGES . "cross.png'>
					{$lang['shared_library_45']}
				</p>";
	} else {
		$successText = $isBattery ? $lang['shared_library_93'] : $lang['shared_library_46'];
	    print  "<p style='max-width:800px;'>
					<img src='" . APP_PATH_IMAGES . "tick.png'>
					<span style='color:green;font-weight:bold;'>$successText</span><br><br>
					" . (($status < 1 && !$longitudinal) ? $lang['shared_library_47'] : "") . "
					" . (($status > 0) ? $lang['shared_library_48'] : "") . "
				</p>";
	}
	// Give user link back to Design page
	renderPrevPageLink("Design/online_designer.php");
	print "</div>";

}















/**
 * 	USER MUST GIVE NAME TO FORM, THEN HIT SUBMIT
 */
elseif ($_SESSION['import_id'] != $_GET['importId'])
{
	//determine if the form was downloaded or uploaded previously
	$shareSql = "select type from redcap_library_map where project_id = $project_id and library_id = " . (int)$_GET['importId'];
	$shareResult = db_query($shareSql);
	$currentShare = 0;
	if(db_num_rows($shareResult) > 0) {
		$shareArray = db_fetch_array($shareResult);
		$currentShare = $shareArray[0];
	}


	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

	## Online Form Editor
	print  "<div class='round' style='border:1px solid #99B5B7;background-color:#E8ECF0;max-width:700px;margin:20px 0;'>";

	print  "<h4 style='padding:5px 10px;margin:0;border-bottom:1px solid #ccc;color:#222;'>
				<img src='".APP_PATH_IMAGES."blog_pencil.png'>
				{$lang['shared_library_44']}
			</h4>";
	print  "<div style='padding:5px 20px 5px 30px;'>
			<p>
				{$lang['shared_library_49']}
			</p>";

	// Note if instrument has been uploaded/downloaded before
	if ($currentShare > 0)
	{
		$updownText = ($currentShare == 1) ? $lang['shared_library_50'] : $lang['shared_library_51'];
		print  "<p>
					<b>{$lang['global_02']}: {$lang['shared_library_52']} $updownText {$lang['shared_library_07']}.
					{$lang['shared_library_53']}</b>
				</p>";
	}


	//Query to get form names to display in drop-down list for choosing
	$form_menu_choices = "";
	$sql = "SELECT form_name, form_menu_description FROM $metadata_table WHERE project_id = $project_id
			AND form_menu_description IS NOT NULL ORDER BY field_order";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$form_menu_choices .= "<option value='{$row['form_name']}'>{$row['form_menu_description']}</option>";
	}

	//Obtain form name from URL (originates from library) and plug into text box
	if (isset($_GET['formtitle']) && $_GET['formtitle'] != "") {
		$formtitle = decode_filter_tags(label_decode(urldecode($_GET['formtitle'])));
	} else {
		$formtitle = "";
	}

	print  "<form method='post' onsubmit='showProgress(1);'>";
	print  "<div style='margin:15px 0;'>";
	print  "<input type='hidden' name='import_id' value='".js_escape($_GET['importId'])."'/>";
	print  "<input type='hidden' name='promis_key' value='".js_escape($_GET['promis_key'])."'/>";
	print  "<input type='hidden' name='scoring_type' value='".js_escape($_GET['scoring_type'])."'/>";
	print  "<input type='hidden' name='promis_battery_key' value='".js_escape($_GET['promis_battery_key'])."'/>";
	
	// BATTERY
	if (isset($_GET['promis_battery_key']) && !empty($_GET['promis_battery_key'])) {
		$batteryForms = PROMIS::getBatteryInstrumentList($_GET['promis_battery_key']);
		if (empty($batteryForms)) {
			// Error: Battery not found
			print   RCView::div(array('class'=>'red', 'style'=>'margin:15px 0;font-size:11px;'),
						RCView::img(array('src'=>'exclamation.png')) .
						RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['shared_library_89']
					);
		} else {
			// "Add" button and form title text field
            print RCView::hidden(array('name'=>'batterytitle', 'value'=>$formtitle));
			print  "<input type='submit' name='submit' value=' ".js_escape($lang['design_171'])." ' class='btn btn-success' style='font-weight:bold;font-size:14px;vertical-align:middle;'
						onclick=\"if( $('#new_form').val()==''){alert('".js_escape($lang['shared_library_54'])."');return false;} showProgress(1);\">
					{$lang['shared_library_90']}
					<div id='ImportBatteryList' class='darkgreen' style='margin-top:10px;'>".
						"<b>".$lang['shared_library_91'] . " \"<span style='color:#A00000;'>" . htmlspecialchars($formtitle, ENT_QUOTES)."</span>\"</b>" . RCView::br() . RCView::br();
			foreach ($batteryForms as $thisForm) {
				print   RCView::div(array('style'=>'margin:3px 0;', 'id'=>$thisForm['FormOID']),
							$thisForm['Order'].") ".$thisForm['Name']
						);
			}
			print  "</div>";
			print   RCView::div(array('class'=>'yellow', 'style'=>'margin:15px 0;font-size:11px;'),
						RCView::img(array('src'=>'exclamation_orange.png')) .
						RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['shared_library_92']
					);
		}
	} 
	// Non-Battery instrument (traditional, CAT, auto-scoring)
	else {
		// "Add" button and form title text field
		print  "<input type='submit' name='submit' value=' ".js_escape($lang['design_171'])." ' class='btn btn-success' style='font-weight:bold;font-size:14px;vertical-align:middle;'
					onclick=\"if( $('#new_form').val()==''){alert('".js_escape($lang['shared_library_54'])."');return false;} showProgress(1);\">
				{$lang['shared_library_55']}
				<input type='text' value='".htmlspecialchars($formtitle, ENT_QUOTES)."' name='new_form' id='new_form' class='x-form-text x-form-field'
					style='width:45%;max-width:300px;margin:0 3px;' maxlength='200'
					placeholder='".js_escape($lang['shared_library_88'])."
					onkeydown=\"if(event.keyCode==13){if( $('#new_form').val()==''){ return false; }else{ $('#new_form').val( $('#new_form').val().trim() ); showProgress(1); }};\"
					onblur=\"$('#new_form').val( $('#new_form').val().trim() ); }\">";
		
		if (isset($_GET['promis_key']) && !empty($_GET['promis_key'])) {
			// Auto-scoring instruments
			if (isset($_GET['scoring_type']) && $_GET['scoring_type'] == 'END_ONLY') {
				print   RCView::div(array('class'=>'yellow', 'style'=>'margin:15px 0;font-size:11px;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['shared_library_85']
						);
			}
			// CATs
			else {
				print   RCView::div(array('class'=>'yellow', 'style'=>'margin:15px 0;font-size:11px;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['shared_library_86']
						);
			}
		}
	}
	print  "</div>";
	print  "<p style='margin:30px 0 10px;'>
				{$lang['shared_library_56']}
				<a style='text-decoration:underline;' href='".SHARED_LIB_PATH."'>{$lang['shared_library_57']}</a>.
			</p>";
	print "</form>";

	// Give user link back to Design page
	print "<br><button class='btn btn-defaultrc btn-xs' onclick=\"window.location.href = app_path_webroot+'Design/online_designer.php?pid='+pid;\">{$lang['global_53']}</button>";
	print "<br>";
	print "<br>";

	print  "</div>";
	print  "</div>";

} else {

	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

    print "<div class='round' style='border:1px solid #99B5B7;background-color:#E8ECF0;max-width:700px;margin:20px 0;'>";
	print "<h4 style='padding:5px 10px;margin:0;border-bottom:1px solid #ccc;color:#222;'>
				<img src='".APP_PATH_IMAGES."blog_pencil.png'>
				{$lang['shared_library_44']}
			</h4>";
	print "<div style='padding:5px 10px 5px 30px;width:600px;'>";
	print "<p>{$lang['shared_library_58']}</p>";
	// Give user link back to Design page
	renderPrevPageLink("Design/online_designer.php");
	print "</div>";
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
