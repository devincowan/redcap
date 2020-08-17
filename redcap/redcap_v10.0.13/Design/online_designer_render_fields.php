<?php


//Get any variables passed by Post
if (isset($_POST['pnid']))  $_GET['pnid'] = $_POST['pnid'];
if (isset($_POST['pid']))   $_GET['pid']  = $_POST['pid'];
if (isset($_POST['page'])) 	$_GET['page'] = $_POST['page'];

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Validate PAGE
if (isset($_GET['page']) && (($status == 0 && !isset($Proj->forms[$_GET['page']])) || ($status > 0 && !isset($Proj->forms_temp[$_GET['page']])))) {
	exit("ERROR!");
}

//Get only the LAST field on the form (excluding the Form Status field)
$metadata = array();
if (isset($_GET['field_name'])) {
	$metadata[] = ($status > 0) ? $Proj->metadata_temp[$_GET['field_name']] : $Proj->metadata[$_GET['field_name']];
//Get ALL fields on this form (if any) and render them here as editable table
} else {
	if ($status > 0) {
		foreach ($Proj->forms_temp[$_GET['page']]['fields'] as $this_field=>$this_label) {
			$metadata[] = $Proj->metadata_temp[$this_field];
		}
	} else {
		foreach ($Proj->forms[$_GET['page']]['fields'] as $this_field=>$this_label) {
			$metadata[] = $Proj->metadata[$this_field];
		}
	}
}

//Render form as editable table
if (count($metadata) > 0) {

	$string_data1 = "";

	//Replace any single or double quotes since they cause rendering problems
	$orig_quote = array("'", "\"");
	$repl_quote = array("&#039;", "&quot;");

	//Collect any "sql" field types
	$sql_fields = array();

	// Set default
	$prev_grid_name = "";
    $grid_rank  = '0';
	
	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	$action_tags_regex = Form::getActionTagMatchRegexOnlineDesigner();

	// Find all variables that are embedded on THIS instrument
	$embeddedVarsThisInstrument = Piping::getEmbeddedVariables(PROJECT_ID, $_GET['page'], null, true);

	//Render each table row
	foreach ($metadata as $row)
	{
		$field_name = $row['field_name'];
		$element_preceding_header = $row['element_preceding_header'];
		$element_type = $row['element_type'];
		$element_label = str_replace($orig_quote, $repl_quote, $row['element_label']);
		$element_enum = str_replace($orig_quote, $repl_quote, $row['element_enum']);
		$element_note = str_replace($orig_quote, $repl_quote, $row['element_note']);
		$element_validation_type = $row['element_validation_type'];
		$element_validation_min = $row['element_validation_min'];
		$element_validation_max = $row['element_validation_max'];
		$element_validation_checktype = $row['element_validation_checktype'];
		$branching_logic = trim($row['branching_logic']);
		$field_req = $row['field_req'];
		$field_phi = $row['field_phi'];
		$edoc_id = $row['edoc_id'];
		$edoc_display_img = $row['edoc_display_img'];
		$stop_actions = (isset($Proj->forms[$_GET['page']]['survey_id'])) ? DataEntry::parseStopActions($row['stop_actions']) : "";
		$custom_alignment = $row['custom_alignment'];
		$grid_name = trim($row['grid_name']);
		$grid_rank = ($row['grid_rank'] === '1') ? '1' : '0';
		$video_url = trim($row['video_url']);
		$video_display_inline = trim($row['video_display_inline']);

		// Do not process the Form Status field
		if ($_GET['page'] . "_complete" == $field_name) {
			continue;
		}

		$fieldIsEmbeddedOnInstrument = in_array($field_name, $embeddedVarsThisInstrument);

		## MATRIX QUESTION GROUPS
		$isMatrixField = false; //default
		// Beginning a new grid
		if ($grid_name != "" && $prev_grid_name != $grid_name)
		{
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


		//if this data field specifies a 'header' separator - process this first
		$hasSHtag = false;
		if ($element_preceding_header && ($field_name != $table_pk) && ((!isset($_GET['edit_question']) || !$_GET['edit_question']) || (isset($_GET['section_header']) && $_GET['section_header'])))
		{
			// Tag if this field has a section header attached to it
			$hasSHtag = true;
			// IF a matrix field, then set flag in this element
			if ($isMatrixField) {
				$matrix_string_data1 = "'matrix_field'=>'$matrixGroupPosition', 'grid_name'=>'$grid_name',";
				$shIcons = "";
			} else {
				$matrix_string_data1 = "";
				$shIcons = "<div class=\'frmedit\' style=\'padding-bottom:4px;\'>"
						 . "<a href=\'javascript:;\' onclick=\'openAddQuesForm(\"$field_name\",\"$element_type\",1,0);\'><img src=\'".APP_PATH_IMAGES."pencil.png\' ignore=\'Yes\'></a> "
						 . "<a href=\'javascript:;\' onclick=\'deleteField(\"$field_name\",1);\'><img src=\'".APP_PATH_IMAGES."cross.png\' ignore=\'Yes\'></a> "
						 . "</div>";
			}
			$element_preceding_header = nl2br(DataEntry::cleanLabel(decode_filter_tags($element_preceding_header)));
			$string_data1 .= "\$elements1[] = array('rr_type'=>'header',
													'css_element_class'=>'header',
													'field'=>'{$field_name}-sh', $matrix_string_data1
													'value'=>'$shIcons<div>$element_preceding_header</div>');\n";
			// If only editing/adding a single section header, stop this loop here
			if (isset($_GET['section_header']) && $_GET['section_header']) continue;
		}

		//process the true data element
		if ($element_type == 'sql') {
			$string_data1 .= "\$elements1[]=array('rr_type'=>'select', 'field'=>'$field_name', 'name'=>'$field_name',";
			//Add to array of sql field type fields
			$sql_fields[] = $field_name;
		} else {
			$string_data1 .= "\$elements1[]=array('rr_type'=>'$element_type', 'field'=>'$field_name', 'name'=>'$field_name',";
		}

		// IF a matrix field, then set flag in this element
		if ($isMatrixField) {
			$string_data1 .= "'matrix_field'=>'$matrixGroupPosition', 'grid_name'=>'$grid_name', 'grid_rank'=>'$grid_rank',";
		}

		// Tag if this field has a section header attached to it
		if ($hasSHtag) {
			$string_data1 .= "'hasSH'=>'1',";
		}

		//Process required field status (add note underneath field label)
		if ($field_req == '1' && $element_type != 'descriptive') {
			$fieldReqClass = ($isMatrixField) ? 'requiredlabelmatrix' : 'requiredlabel'; // make matrix fields more compact
			$element_label .= "<div class='$fieldReqClass'>* {$lang['data_entry_39']}</div>";
		}

		//FIELD LABEL
		$string_data1 .= " 'label'=> '" . nl2br(DataEntry::cleanLabel($element_label)) . "',";

		// Custom alignment
		$string_data1 .= " 'custom_alignment'=>'$custom_alignment',";
		
		// If field_annotation has @, then assume it might be an action tag
		if (strpos($row['misc'], '@') !== false) {
			// Match triggers via regex
			preg_match_all($action_tags_regex, $row['misc'], $this_misc_match);
			if (isset($this_misc_match[1]) && !empty($this_misc_match[1])) {
				$string_data1 .= " 'action_tag_class'=>'".implode(" ", $this_misc_match[1])."',";
			}
		}

		//For elements of type 'text', we'll handle data validation if details are provided in metadata
		if ($element_type == 'text' || $element_type == 'calc') {
			if($element_validation_type){
				$hold_validation_string = "'validation'=>'$element_validation_type', 'onblur'=>\"redcap_validate(this,'$element_validation_min','$element_validation_max',";
				if($element_validation_checktype){
					$hold_validation_string .= "'$element_validation_checktype','$element_validation_type')\"";
				}else{
					$hold_validation_string .= "'soft_typed','$element_validation_type')\"";
				}
				$string_data1 .= " $hold_validation_string,";
			}
			// ONTOLOGY AUTO-SUGGEST
			elseif ($element_type == 'text' && $element_enum != '' && strpos($element_enum, ":") !== false) {
				$string_data1 .= " 'element_enum'=>'$element_enum',";
			}
		}

		// Add $element_validation_type for FILE fields (for signatures only) and SELECT fields (for auto-complete)
		if (($element_type == 'file' || $element_type == 'select' || $element_type == 'sql') && $element_validation_type != '') {
			$string_data1 .= "'validation'=>'" . js_escape($element_validation_type) . "', ";
		}

		// Add edoc_id, if a Descriptive field has an attachement or video url
		if ($element_type == 'descriptive') {
			if (is_numeric($edoc_id)) {
				$string_data1 .= "'edoc_id'=>$edoc_id, 'edoc_display_img'=>$edoc_display_img, ";
			} elseif ($video_url != '') {
				$string_data1 .= "'video_url'=>'".js_escape(strip_tags(label_decode($video_url)))."', 'video_display_inline'=>'$video_display_inline', ";
			}
		}

		// Add slider labels & and display value option
		if ($element_type == 'slider') {
			$slider_labels = Form::parseSliderLabels($element_enum);
			$string_data1 .= "  'slider_labels'=>array('" . js_escape((decode_filter_tags($slider_labels['left']))) . "',
								'" . js_escape((decode_filter_tags($slider_labels['middle']))) . "',
								'" . js_escape((decode_filter_tags($slider_labels['right']))) . "',
								'" . remBr(DataEntry::cleanLabel($element_validation_type)) . "'), ";
		}

		//For elements of type 'select', we need to include the $element_enum information
		if ($element_type == 'truefalse' || $element_type == 'yesno' || $element_type == 'select' || $element_type == 'radio' || $element_type == 'checkbox' || $element_type == 'sql')
		{
			//Add any checkbox fields to array to use during data pull later to fill form with existing data
			if ($element_type == 'checkbox') $chkbox_flds[$field_name] = "";

			//Do normal select/radio/checkbox options
			if ($element_type != 'sql')
			{
				// Clean the label
				$element_enum = DataEntry::cleanLabel($element_enum);
				// If stop actions exist, add labels to element_enum and set back to original formatting (exclude matrix fields due to matrix header complexity)
				if (!empty($stop_actions) && !$isMatrixField)
				{
					$element_enum_temp = array();
					foreach (parseEnum($element_enum) as $this_key=>$this_choice)
					{
						// Append "end survey" string to choice if a stop action exists
						if (in_array($this_key, $stop_actions)) {
							$this_choice .= " <span class=\"stopnote\">{$lang['design_211']}</span>";
						}
						$element_enum_temp[] = "$this_key, $this_choice";
					}
					// Now set element_enum back again
					$element_enum = implode("\\n", $element_enum_temp);
				}
				// Add to string data
				$string_data1 .= " 'enum'=>'$element_enum',";
			}
			//Do SQL field for dynamic select box (Must be "select" statement)
			else
			{
				$string_data1 .= ' \'enum\'=>"' . str_replace(array('"',"'"), array('&quot;',"&#039;"), getSqlFieldEnum($element_enum)) . '",';
			}

		}

		//If an element_note is specified, we'll utilize here:
		if ($element_note) {
			if (strpos($element_note, "'") !== false) $element_note = str_replace("'", "&#39;", $element_note); //Apostrophes cause issues when rendered, so replace with equivalent html character
			$string_data1 .= " 'note'=>'" . DataEntry::cleanLabel($element_note) . "',";
		}

		//For elements of type 'textarea', we need to specify the number of rows to include
		//Note that we used to use $element_other for this, but probably not necessary
		if ($element_type == 'textarea'){
			$string_data1 .= " 'rows'=>'2', 'style'=>'width:97%;',";
		}

		// If branching logic exists, add to element in order to display that it exists on Online Form Editor
		$branching_logic_trunc = str_replace(array("\t", "\r", "\n"), array(" ", " ", " "), br2nl(label_decode($branching_logic, false)));
		if (strlen($branching_logic_trunc) > 65) $branching_logic_trunc = substr($branching_logic_trunc, 0, 63)."...";
		$branching_logic_trunc = htmlspecialchars($branching_logic_trunc, ENT_QUOTES);
		$string_data1 .= " 'branching_logic'=>'<span id=\"bl-label_{$field_name}\" class=\"bledit\" style=\"visibility:"
					   . ($branching_logic == "" ? "hidden" : "visible")
					   . ";\"><i>{$lang['design_731']}</i> <span id=\"bl-log_{$field_name}\">$branching_logic_trunc</span></span>'";

		// If this field is embedded somewhere on this instrument, then give a note about it
		$fieldIsEmbeddedOnInstrument1 = $fieldIsEmbeddedOnInstrument ? '' : 'hide';
		$fieldIsEmbeddedOnInstrument2 = $fieldIsEmbeddedOnInstrument ? 'hide' : '';
		if ($field_name != $Proj->table_pk) {
			$string_data1 .= ", 'field_embed'=>'<div var=\"$field_name\" class=\"rc-field-embed-designer $fieldIsEmbeddedOnInstrument1 float-right mr-1 bg-success text-white\" style=\"padding:1px 3px;\">".htmlspecialchars($lang['design_793'], ENT_QUOTES)." <i class=\"fas fa-level-up-alt fs13\"></i></div>
												<div class=\"rc-field-embed-designer-btn float-right mr-1 $fieldIsEmbeddedOnInstrument2\" style=\"padding:1px 3px;\"><a ignore=\"Yes\" href=\"javascript:;\" onclick=\"fieldEmbeddingExplanation();return false;\" class=\"fs11\"><i class=\"fas fa-info-circle\"></i> ".htmlspecialchars($lang['design_794'], ENT_QUOTES)."</a></div>'";
		}
		// END
		$string_data1 .= " );";

	}

	//Evaluate the string to produce the $elements1 array
	eval($string_data1);

}

// Render table or row
print (PAGE == "Design/online_designer.php" || (isset($_GET['ordering']) && $_GET['ordering'])) ? "<div id='draggablecontainer'>" : "<div>";
DataEntry::renderForm($elements1, array());
print "</div>";
