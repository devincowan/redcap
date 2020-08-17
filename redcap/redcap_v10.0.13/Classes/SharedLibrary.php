<?php

class SharedLibrary
{

	public static function getCrfFormData($form)
	{
		global $table_pk, $status;

		$libxml = new SharedLibraryXml();
		$retVal = array();
		$index = 0;

		$sql = "select " .
			"field_name, field_phi, form_name, form_menu_description, field_order, field_units, " .
			"element_preceding_header, element_type, element_label, element_enum, element_note, " .
			"element_validation_type, element_validation_min, element_validation_max, " .
			"element_validation_checktype, branching_logic, field_req, edoc_id, edoc_display_img, " .
			"custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc " .
			"from redcap_metadata " .
			"where " .
			"project_id = " . PROJECT_ID . " " .
			"and form_name = '".db_escape($form)."' " .
			"and field_name != concat(form_name,'_complete') " .
			"and field_name != '$table_pk' " .
			"order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// For this field, add each attribute
			$retVal[$index] = array();
			foreach ($row as $key => $val)
			{
				// Ignore certain attributes because we'll add it later
				if (in_array($key, array('edoc_display_img', 'stop_actions'))) continue;
				// Add attribute
				SharedLibrary::addFormElement($retVal[$index], SharedLibrary::getMappedElement($key), html_entity_decode($val, ENT_QUOTES));
			}
			// If has a stop action,
			if ($row['stop_actions'] != '')
			{
				foreach (explode(",", $row['stop_actions']) as $this_code)
				{
					$retVal[$index]['ActionExists'][] = array(SharedLibrary::getMappedElement('action_trigger')=>$this_code, SharedLibrary::getMappedElement('action')=>1);
				}
			}
			// If has an attachment, then add the file attachment's attributes
			if (is_numeric($row['edoc_id']) && $row['element_type'] == 'descriptive')
			{
				$retVal[$index]['ImageAttachment'] = SharedLibrary::getAttachmentInfo($row['edoc_id'], $row['edoc_display_img']);
			}
			// If is a slider
			elseif ($row['element_type'] == 'slider')
			{
				// Add slider labels
				$this_slider_labels = Form::parseSliderLabels($row['element_enum']);
				SharedLibrary::addFormElement($retVal[$index], SharedLibrary::getMappedElement('slider_min'), $this_slider_labels['left']);
				SharedLibrary::addFormElement($retVal[$index], SharedLibrary::getMappedElement('slider_mid'), $this_slider_labels['middle']);
				SharedLibrary::addFormElement($retVal[$index], SharedLibrary::getMappedElement('slider_max'), $this_slider_labels['right']);
				// If its supposed to display its number value
				SharedLibrary::addFormElement($retVal[$index], SharedLibrary::getMappedElement('slider_val_disp'), ($row['element_validation_type'] == 'number' ? '1' : '0'));
			}

			// Increment counter
			$index++;
		}

		return $retVal;
	}

	public static function addFormElement(&$arr,$key,$val){
		if($key != null && trim($val) != '') {
			$arr[$key] = $val;
		}
	}

	public static function initMapTable() {
		global $xmlMapTable;
		global $reverseXmlMapTable;
		$xmlMapTable = array(
			// CRF constants
			'field_name'					=> 'FieldName',
			'field_phi'						=> 'FieldPhi',
			'form_name'						=> 'FormName',
			'form_menu_description'			=> 'FormMenuDescription',
			'field_order'					=> 'FieldOrder',
			'field_units'					=> 'FieldUnits',
			'element_preceding_header'		=> 'ElementPreceedingHeader',
			'element_type'					=> 'ElementType',
			'element_label'					=> 'ElementLabel',
			'element_enum'					=> 'ElementEnum',
			'element_note'					=> 'ElementNote',
			'element_validation_type'		=> 'ElementValidationType',
			'element_validation_min'		=> 'ElementValidationMin',
			'element_validation_max'		=> 'ElementValidationMax',
			'element_validation_checktype'	=> 'ElementValidationChecktype',
			'branching_logic'				=> 'BranchingLogic',
			'field_req'						=> 'FieldReq',
			'mapped_codes'					=> 'MappedCodes',
			'standard_code'					=> 'Code',
			'standard_code_desc'			=> 'CodeDescription',
			'standard_name'					=> 'StandardName',
			'standard_version'				=> 'StandardVersion',
			'standard_desc'					=> 'StandardDescription',
			'data_conversion'				=> 'DataConversion',
			'data_conversion2'				=> 'DataConversion2',
			// New stuff in 4.X
			'custom_alignment'				=> 'QuestionLayout',
			'stop_actions'					=> 'Action',
			'question_num'					=> 'QuestionNum',
			//
			'slider_min'					=> 'ElementSliderMin',
			'slider_mid'					=> 'ElementSliderMid',
			'slider_max'					=> 'ElementSliderMax',
			'slider_val_disp'				=> 'ElementSliderValDisp',
			'action'						=> 'Action',
			'action_exists'					=> 'ActionExists',
			'action_trigger'				=> 'Trigger',
			// Added matrix group names in 4.13
			'grid_name'						=> 'MatrixGroupName',
			'grid_rank'						=> 'MatrixRanking',
			// Field annotation added in 6.5.0
			'misc'							=> 'FieldAnnotation',
			// File-related attributes
			'image_attachment'				=> 'ImageAttachment',
			'stored_name'					=> 'StoredName',
			'mime_type'						=> 'MimeType',
			'doc_name'						=> 'DocName',
			'doc_size'						=> 'DocSize',
			'file_extension'				=> 'FileExtension',
			'edoc_display_img'				=> 'FileDownloadImgDisplay'
		);
		$reverseXmlMapTable = array();
		foreach($xmlMapTable as $key=>$val) {
			$reverseXmlMapTable[$val] = $key;
		}
	}

	public static function getAttachmentInfo($doc_id, $edoc_display_img)
	{
		$sql = "select stored_name, mime_type, doc_name, doc_size, file_extension from redcap_edocs_metadata
				where doc_id = $doc_id limit 1";
		$q = db_query($sql);
		$attachmentArray = null;
		if ($row = db_fetch_assoc($q))
		{
			$attachmentArray = array();
			$attachmentArray[SharedLibrary::getMappedElement('stored_name')] = $row['stored_name'];
			$attachmentArray[SharedLibrary::getMappedElement('mime_type')] = $row['mime_type'];
			$attachmentArray[SharedLibrary::getMappedElement('doc_name')] = $row['doc_name'];
			$attachmentArray[SharedLibrary::getMappedElement('doc_size')] = $row['doc_size'];
			$attachmentArray[SharedLibrary::getMappedElement('file_extension')] = $row['file_extension'];
			$attachmentArray[SharedLibrary::getMappedElement('edoc_display_img')] = $edoc_display_img;
		}
		return $attachmentArray;
	}

	public static function getMappedElement($str) {
		global $xmlMapTable;
		global $reverseXmlMapTable;
		if(!isset($xmlMapTable)) {
			SharedLibrary::initMapTable();
		}
		if(isset($xmlMapTable[$str])) {
			return $xmlMapTable[$str];
		}else if(isset($reverseXmlMapTable[$str])) {
			return $reverseXmlMapTable[$str];
		}
		return null;
	}

	public static function getMaxFieldOrderValue($project_id) {
		global $status;
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		$max = -1;
		$sql = "select max(field_order) as field_max from $metadata_table where project_id=$project_id";
		$q = db_query($sql);
		if($row = db_fetch_array($q,MYSQLI_ASSOC)) {
			$max = $row['field_max'];
		}
		return $max;
	}

	public static function getUniqueFormName($formName) {

		global $status, $Proj;
		// Clean it
		$new_form_name = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower(html_entity_decode($formName, ENT_QUOTES))));
		// Remove any double underscores, beginning numerals, and beginning/ending underscores
		while (strpos($new_form_name, "__") !== false) 		$new_form_name = str_replace("__", "_", $new_form_name);
		while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
		while (substr($new_form_name, -1) == "_") 			$new_form_name = substr($new_form_name, 0, -1);
		while (is_numeric(substr($new_form_name, 0, 1))) 	$new_form_name = substr($new_form_name, 1);
		while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
		// Cannot begin with numeral and cannot be blank
		if (is_numeric(substr($new_form_name, 0, 1)) || $new_form_name == "") {
			$new_form_name = substr(preg_replace("/[0-9]/", "", md5($new_form_name)), 0, 4) . $new_form_name;
		}
		// Make sure it's less than 50 characters long
		$new_form_name = substr($new_form_name, 0, 50);
		while (substr($new_form_name, -1) == "_") $new_form_name = substr($new_form_name, 0, -1);
		// Make sure this form value doesn't already exist
		$formExists = ($status > 0) ? isset($Proj->forms_temp[$new_form_name]) : isset($Proj->forms[$new_form_name]);
		while ($formExists) {
			// Make sure it's less than 64 characters long
			$new_form_name = substr($new_form_name, 0, 45);
			// Append random value to form_name to prevent duplication
			$new_form_name .= "_" . substr(sha1(rand()), 0, 4);
			// Try again
			$formExists = ($status > 0) ? isset($Proj->forms_temp[$new_form_name]) : isset($Proj->forms[$new_form_name]);
		}
		// Return form name
		return $new_form_name;
	}

	public static function isInFieldArray($fieldArray, $nameIndex, $fieldName) {
		foreach($fieldArray as $field) {
			if($field[$nameIndex] == $fieldName) {
				return true;
			}
		}
		return false;
	}

	public static function getUniqueFieldName($fieldArray, $nameIndex, $project_id, $fieldName, $count=1) {
		global $status;
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		$fieldName = html_entity_decode($fieldName, ENT_QUOTES);
		$fieldName = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower($fieldName)));
		$sql = "select field_name as fn from $metadata_table where project_id = $project_id and field_name = '".db_escape($fieldName)."' limit 1";
		$q = db_query($sql);
		if ($row = db_fetch_array($q) || SharedLibrary::isInFieldArray($fieldArray, $nameIndex, $fieldName)) {
			return str_replace("__", "_", $fieldName . "_" . substr(sha1(rand()), 0, 6));
		} else {
			return $fieldName;
		}
	}

	/**
	 * RETRIEVE ACKNOWLEDGEMENT/COPYRIGHT FOR A FORM FROM THE LIBRARY_MAP TABLE (OR CALL IT FROM LIBRARY SERVER IF EXPIRED)
	 */
	public static function getAcknowledgement($project_id,$formName) {
		//if necessary, convert the project name to project id
		if (!is_numeric($project_id)) {
			$sqlCheck = "select project_id from redcap_projects where project_name = '$project_id'";
			$resCheck = db_query($sqlCheck);
			if($row = db_fetch_array($resCheck)) {
				$project_id = $row['project_id'];
			}
		}
		//get the acknowledgement form the local project
		$getLibInfo =  "select library_id, acknowledgement, acknowledgement_cache " .
			"from redcap_library_map " .
			"where project_id = $project_id and form_name = '$formName' and type = 1";
		$result = db_query($getLibInfo);
		if ($row = db_fetch_array($result)) {
			$libId = $row['library_id'];
			$ack = decode_filter_tags(label_decode($row['acknowledgement']));
			$difference = floor((time() - strtotime($row['acknowledgement_cache']))/(60*60*24));
			//check if local copy is expired (30 days) and update if necessary
			if ($difference > 30) {
				$curlAck = curl_init();
				curl_setopt($curlAck, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($curlAck, CURLOPT_VERBOSE, 0);
				curl_setopt($curlAck, CURLOPT_URL, SHARED_LIB_DOWNLOAD_URL.'?attr=acknowledgement&id='.$libId);
				curl_setopt($curlAck, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlAck, CURLOPT_POST, false);
				curl_setopt($curlAck, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
				curl_setopt($curlAck, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
				$ack = curl_exec($curlAck);
				$ack = decode_filter_tags(label_decode($ack));
				$updateSql = "update redcap_library_map " .
					"set acknowledgement = '".db_escape($ack)."', acknowledgement_cache = '".NOW."' " .
					"where project_id = $project_id and form_name = '$formName' and type = 1";
				db_query($updateSql);
			}
			// Return the acknowledgement
			return $ack;
		}
		return "";
	}

	// Browse Shared Library form: Build the hidden form that sets Post values to be submitted to "log in"
	// to the REDCap Shared Library.
	public static function renderBrowseLibraryForm()
	{
		global $institution, $user_firstname, $user_lastname, $user_email, $redcap_version, $promis_enabled;
		// Check if cURL is loaded
		$onSubmitValidate = "";
		if (!function_exists('curl_init')) {
			// Set unique id
			$errorId = "curl_error_".substr(sha1(rand()), 0, 8);
			//cURL is not loaded
			print "<div style='display:none' id='$errorId'>";
			curlNotLoadedMsg();
			print "</div>";
			$onSubmitValidate = "onSubmit=\"$('#$errorId').show();return false;\"";
		}
		return "<form id='browse_rsl' method='post' $onSubmitValidate action='".SHARED_LIB_BROWSE_URL."'>
                    <input type='hidden' name='action' value='browse'>
                    <input type='hidden' name='user' value='" . md5($institution . USERID) . "'>
                    <input type='hidden' name='first_name' value='".js_escape($user_firstname)."'>
                    <input type='hidden' name='last_name' value='".js_escape($user_lastname)."'>
                    <input type='hidden' name='email' value='".js_escape($user_email)."'>
                    <input type='hidden' name='promis_enabled' value='".js_escape($promis_enabled)."'>
                    <input type='hidden' name='enable_batteries' value='1'>
                    <input type='hidden' name='server_name' value='" . SERVER_NAME . "'>
                    <input type='hidden' name='institution' value=\"".js_escape2(str_replace('"', '', $institution))."\">
                    <input type='hidden' name='redcap_version' value='".js_escape($redcap_version)."'>
                    <input type='hidden' name='callback' value='" . SHARED_LIB_CALLBACK_URL . "?pid=".PROJECT_ID."'>
                </form>";
	}
}
