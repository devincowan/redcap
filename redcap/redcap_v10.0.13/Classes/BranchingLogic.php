<?php

class BranchingLogic
{
	private $_results = array();
	private $_equations = array();
	private $_fields_utilized = array();

    public function feedBranchingEquation($name, $string)
	{
		$string = html_entity_decode($string, ENT_QUOTES);

		// Format logic to JS format
		list ($string, $fields_utilized) = LogicTester::formatLogicToJS($string, false, $_GET['event_id'], true, PROJECT_ID);

		// Add to arrays
		$this->_results[] = $name;
		$this->_equations[] = $string;
		$this->_fields_utilized[$name] = $fields_utilized;
    }

	public function exportBranchingJS()
	{
		global $Proj;
		$specialPipingTags = Piping::getSpecialTagsFormatted(false);
		
		// Compile all trigger fields as keys in array with values as fields they trigger
		$triggerFields = array();
		foreach ($this->_fields_utilized as $receiver=>$triggers) {
			// Also find all fields that the receiver field is dependent upon (to deal with calc/branching chaining)
			$dependentFields = getDependentFields(array($receiver));
			foreach ($triggers as $tkey=>$trigger) {
				if (!in_array($trigger, $specialPipingTags)) {
					$triggerFields[$trigger][] = $receiver;
				}
				if (!empty($dependentFields)) {
					foreach ($dependentFields as $dkey=>$trigger2) {
						if (!in_array($trigger2, $specialPipingTags)) {
							$triggerFields[$trigger2][] = $receiver;
						}
					}
				}
				unset($triggers[$tkey]);
			}
			unset($this->_fields_utilized[$receiver], $triggers);
		}
		$result2  = "\n<!-- Branching Logic Trigger Fields -->";
		$result2 .= "\n<script type=\"text/javascript\">\n";
		$result2 .= "function dbtf(t,c){\n";
		$result2 .= "  if(t==''){return true;}var o=new Object();\n";
		foreach ($triggerFields as $trigger=>$receivers) {
			$result2 .= "  o.$trigger='|".implode("|", array_unique($receivers))."|';\n";
		}
		$result2 .= "  if(typeof o[t]=='undefined') return true;\n";
		// If this trigger field has a branching receiver that is itself a trigger for another branching, then return true to make sure all chained branching get called.
		$result2 .= "  var oa=o[t].split('|');for(var i=0;i<oa.length;i++){if(typeof o[oa[i]]!='undefined'){return true;}}\n";
		// Determine if the current field is a branching receiver for this trigger field.
		$result2 .= "  return (o[t].indexOf('|'+c+'|')>-1);\n";
		$result2 .= "}\n";
		$result2 .= "</script>";

		$result  = "\n<!-- Branching Logic -->";
		$result .= "\n<script type=\"text/javascript\">\n";
		$result .= "function doBranching(t,b,isOnPageLoad){\n";
        $result .= "  if(typeof isOnPageLoad=='undefined') isOnPageLoad=false;\n";
        $result .= "  var c;if(typeof b=='undefined') b=false;if(typeof t=='undefined') t='';\n";
		// Loop through all branching logic fields
		for ($i = 0; $i < sizeof($this->_results); $i++)
		{
			// Show the field only if the condition is true; Hide it if false. Prompt if about to hide a field with data already entered.
			$this_field = $this->_results[$i];
			// Set string for try/catch
			$try = $catch = "";
			if (!isset($_GET['__display_errors'])) {
				$try = "try{";
				$catch = "}catch(e){brErr(c)}";
			}
			// Add line of JS
			$result .= "  c='$this_field';{$try}if(dbtf(t,c))evalLogic(c,b,(" . $this->_equations[$i] . ")){$catch}\n";
		}
		// Hide any section headers in which all fields in the section have been hidden
		$result .= "  hideSectionHeaders();\n";
		// If any enhanced choice fields are hidden due to branching logic, then make sure their UI shows them as unselected
		$result .= "  $(function(){ updateHiddenEnhancedChoices(); });\n";
        // Re-run floating matrix headers
        $result .= "  if (!isOnPageLoad) enabledFloatingMatrixHeaders();\n";
		// Return false
		$result .= "  return false;\n";
		$result .= "}\n";

		// Add javascript for form/survey page to show form table right before we execute the branching (but delay this if we are doing any field embedding)
		if (!Piping::instrumentHasEmbeddedVariables(PROJECT_ID, $_GET['page'])) {
			$result .= "displayQuestionTable();\n";
		}

		// Execute the branching logic
		$result .= "brErrExist = doBranching('',false,true);\n";
		$result .= "</script>\n";
		$result .= "<script type=\"text/javascript\">\n";
		$result .= "if(brErrExist){brErr2()}\n";
		$result .= "</script>\n";

		return $result2 . $result;
	}


	// Determines if ALL fields provided in $fields would be hidden by branching logic
	// based on existing saved data values (also considers @HIDDEN and @HIDDEN-SURVEY). Returns boolean.
	public static function allFieldsHidden($record, $event_id=null, $form_name=null, $instance=1, $fields=array())
	{
		global $Proj, $longitudinal, $table_pk;
		// Return false if $fields is empty
		if ($record == null || empty($fields)) return false;
		// Loop through all fields and check to make sure they ALL have branching logic.
		// If at least one does NOT have branching logic, then return false.
		foreach ($fields as $field) {
			if ($Proj->metadata[$field]['branching_logic'] == '' && !Form::hasHiddenOrHiddenSurveyActionTag($Proj->metadata[$field]['misc'])) {
				return false;
			}
		}
		// Has repeating events/forms?
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// If longitudinal, then get unique event name from event_id
		if ($event_id == null) $event_id = $Proj->getFirstEventIdArm(getArm());
		$unique_event_name = $Proj->getUniqueEventNames($event_id);
		// Obtain all dependent fields for the fields displayed
		$fieldsDependent = getDependentFields($fields, false, true);
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
		$record_data = Records::getData('array', $record, array_merge($fieldsDependent, $fields, array($table_pk)));
		$record_data = $record_data[$record];
		// For longitudinal only, there might be cross-event logic that references events that dont' have any
		// data yet, which will cause it to return FALSE mistakenly in some cases. So for all events with no data,
		// add each event with empty values and add to $record_data array so that they are present (and blank) to be used in apply().
		if ($longitudinal) {
			// Get any missing events from $record_data
			$missing_event_ids = array_diff(array_keys($Proj->eventInfo), array_keys($record_data));
			// If there exist some events with no data, then loop through $record_data and add empty events
			if (!empty($missing_event_ids)) {
				$empty_data = array();
				foreach ($record_data as $this_event_id=>$these_fields) {
					// Loop through fields
					foreach ($these_fields as $this_field=>$this_value) {
						if (is_array($this_value)) {
							// Checkboxes
							foreach ($this_value as $this_code=>$this_checkbox_value) {
								// Add to array a 0 as default checkbox value
								$empty_data[$this_field][$this_code] = '0';
							}
						} else {
							// Non-checkbox fields
							// Set value as blank (but not for record ID field and not for Form Status fields)
							if ($this_field == $table_pk) {
								// Do nothing, leave record ID value as-is
							} elseif ($Proj->isFormStatus($this_field)) {
								// Set default value as 0
								$this_value = '0';
							} else {
								$this_value = '';
							}
							// Add to array
							$empty_data[$this_field] = $this_value;
						}
					}
					// Stop here since we only need just one event's field structure
					break;
				}
			}
			// Add empty event arrays to $record_data
			if (!empty($empty_data)) {
				// Loop through missing event_ids and add each event with blank event data
				foreach ($missing_event_ids as $this_event_id) {
					$record_data[$this_event_id] = $empty_data;
				}
			}
		}
		// Loop through all fields visible on survey and evaluate their branching logic one by one
        $countDescriptive = 0;
		foreach ($fields as $field) {
		    if ($Proj->metadata[$field]['element_type'] == 'descriptive') $countDescriptive++;
			// First, check if has HIDDEN or HIDDEN-SURVEY action tag
			if (Form::hasHiddenOrHiddenSurveyActionTag($Proj->metadata[$field]['misc'])) {
				// Field is hidden by action tag, so no need to check branching logic. Skip to next field.
				continue;
			}
			// Get branching logic for this field
			$logic = $Proj->metadata[$field]['branching_logic'];
			if ($logic == '') return false;
			// If this is a repeating event/form, then append [current-instance] to all repeating fields so that they get replaced properly via pipeSpecialTags()
            if ($hasRepeatingFormsEvents && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $form_name))) {
                $logic = LogicTester::logicAppendCurrentInstance($logic, $Proj);
            }
			// Pipe any special tags?
			$logic = Piping::pipeSpecialTags($logic, $Proj->project_id, $record, $event_id, $instance, USERID, true, null, $form_name);
			// If longitudinal, then inject the unique event names into logic (if missing)
			// in order to specific the current event.
			if ($longitudinal) {
				$logic = LogicTester::logicPrependEventName($logic, $unique_event_name, $Proj);
			}
			// Make sure that the field's branching logic has proper syntax before we evaluate it with data
			if (!LogicTester::isValid($logic)) return false;
			// Now evaluate the logic with data
			$displayField = LogicTester::apply($logic, $record_data);
			// If at least one field is to be displayed, then return false
			if ($displayField) return false;
		}
		// If all the fields are descriptive and at least one is displayed, then return false
        if (empty($record_data) && count($fields) == $countDescriptive) return false;
		// If we made it this far, then all fields must be hidden
		return true;
	}

}
