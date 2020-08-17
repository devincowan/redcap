<?php

class Calculate
{
	private $_results = array();
	private $_equations = array();
	private $_fields_utilized = array();

    public function feedEquation($name, $string)
	{
		$string = html_entity_decode($string, ENT_QUOTES);
		
		// Replace ="" with ="NaN" for better parsing
		$string = self::replaceEqualNull($string);

		// Format logic to JS format
		list ($string, $fields_utilized) = LogicTester::formatLogicToJS($string, true, $_GET['event_id'], true, PROJECT_ID);

		// Add to arrays
		$this->_results[] = $name;
		$this->_equations[] = $string;
		$this->_fields_utilized[$name] = $fields_utilized;
    }


	public function exportJS()
	{
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
					foreach ($dependentFields as $trigger2) {
						if (!in_array($trigger2, $specialPipingTags)) {
							$triggerFields[$trigger2][] = $receiver;
						}
					}
				}
                unset($triggers[$tkey]);
            }
            unset($this->_fields_utilized[$receiver], $triggers);
		}
		
		$result2  = "\n<!-- Calculation Trigger Fields -->";
		$result2 .= "\n<script type=\"text/javascript\">\n";
		$result2 .= "function ctf(t,c){\n";
		$result2 .= "  if(t==''){return true;}var o=new Object();\n";
		foreach ($triggerFields as $trigger=>$receivers) {
			$result2 .= "  o.$trigger='|".implode("|", array_unique($receivers))."|';\n";
		}
		$result2 .= "  if(typeof o[t]=='undefined') return true;\n";
		// If this trigger field has a calc receiver that is itself a trigger for another calc, then return true to make sure all chained calcs get called.
		$result2 .= "  var oa=o[t].split('|');for(var i=0;i<oa.length;i++){if(typeof o[oa[i]]!='undefined'){return true;}}\n";
		// Determine if the current field is a calc receiver for this trigger field.
		$result2 .= "  return (o[t].indexOf('|'+c+'|')>-1);\n";
		$result2 .= "}\n";
		$result2 .= "</script>";		
		
		$result  = "\n<!-- Calculations -->";
		$result .= "\n<script type=\"text/javascript\">\n";
		$result .= "function calculate(t,isOnPageLoad){\n";
		$result .= "  var a,b,c;if(typeof t=='undefined') t='';if(typeof isOnPageLoad=='undefined') isOnPageLoad=false;\n";

		for ($i = 0; $i < sizeof($this->_results); $i++)
		{
			$this_field = $this->_results[$i];
			// Set string for try/catch
			$try = $catch = "";
			if (!isset($_GET['__display_errors'])) {
				$try = "try{";
				$catch = "}catch(e){calcErr('$this_field')}";
			}
			$result .= "  {$try}if(ctf(t,'$this_field')){c=" . $this->_equations[$i] . ";";
			$result .= "b=document.form.$this_field.value;";
			$result .= "a=document.form.$this_field.value=isNumeric(c)?c:'';";
			$result .= "calcChangeCheck(a,b,'$this_field');";
			$result .= "}$catch\n";
		}

		$result .= "  try{updateCalcPipingReceivers()}catch(e){}\n";
		$result .= "  return false;\n";
		$result .= "}\n";
		$result .= "calcErrExist = calculate('',true);\n";
		$result .= "</script>\n";

		$result .= "<script type=\"text/javascript\">\n";
		$result .= "if(calcErrExist){calcErr2()}\n";
		$result .= "</script>\n";

		return $result2 . $result;
	}

	/**
	 * Calculates values of multiple calc fields and returns array with field name as key
	 * with both existing value and calculated value
	 * @param array $calcFields Array of calc fields to calculate (if contains non-calc fields, they will be removed automatically) - if an empty array, then assumes ALL fields in project.
	 * @param array $records Array of records to perform the calculations for (if an empty array, then assumes ALL records in project).
	 */
	public static function calculateMultipleFields($records=array(), $calcFields=array(), $returnIncorrectValuesOnly=false,
												   $current_event_id=null, $group_id=null, $Proj2=null)
	{
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj;
		} else {
			$Proj = $Proj2;
		}
		// Project has repeating forms/events?
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// Validate $current_event_id
		if (!is_numeric($current_event_id)) $current_event_id = 'all';
		// Validate as a calc field. If not a calc field, remove it.
		$calcFieldsNew = $calcFieldsOrder = array();
		if (!is_array($calcFields) || empty($calcFields)) $calcFields = array_keys($Proj->metadata);
		foreach ($calcFields as $this_field) {
			if (isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] == 'calc') {
				// Add to array of calc fields
				$calcFieldsNew[$this_field] = $Proj->metadata[$this_field]['element_enum'];
				$calcFieldsOrder[$this_field] = $Proj->metadata[$this_field]['field_order'];
			}
		}
		$calcFields = $calcFieldsNew;
		// Make sure calc fields are in the correct order
		array_multisort($calcFieldsOrder, SORT_NUMERIC, $calcFields);
		unset($calcFieldsNew, $calcFieldsOrder);
		// To be the most efficient with longitudinal projects, determine all the events being used by all records
		// in $records (this wittles down the possible events utilized in case there are lots of calcs to process).
		if ($Proj->longitudinal) {
			$viableRecordEvents = Records::getData($Proj->project_id, 'array', $records, $Proj->table_pk);
			$viableEvents = array();
			foreach ($viableRecordEvents as $this_record=>$event_data) {
				foreach (array_keys($event_data) as $this_event_id) {
					if ($this_event_id == 'repeat_instances') {
						foreach (array_keys($event_data['repeat_instances']) as $this_event_id) {
							$viableEvents[$this_event_id] = true;
						}
					} else {
						$viableEvents[$this_event_id] = true;
					}
				}
			}
		}
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		$eventNameToId = array_flip($events);
		$eventsUtilizedAllFields = $logicContainsSmartVariablesFields = array();
		// Create anonymous PHP functions from calc eqns
		$fieldToLogicFunc = $logicFuncToArgs = $logicFuncToCode = array();
		// Loop through all calc fields
		foreach ($calcFields as $this_field=>$this_logic)
		{
			$this_logic_orig = $this_logic;
			// If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
			$logicContainsSmartVariables = Piping::containsSpecialTags($this_logic);
			if ($logicContainsSmartVariables) {
				$logicContainsSmartVariablesFields[] = $this_field;
			}
			// Format calculation to PHP format
			$this_logic = self::formatCalcToPHP($this_logic, $Proj2);
			// Array to collect list of which events are utilized the logic
			$eventsUtilized = array();
			if ($Proj->longitudinal) {
				// Longitudinal
				foreach (array_keys(getBracketedFields($this_logic_orig, true, true, false)) as $this_field2)
				{
					// Check if has dot (i.e. has event name included)
					if (strpos($this_field2, ".") !== false) {
						list ($this_event_name, $this_field2) = explode(".", $this_field2, 2);
						// Get the event_id
						$this_event_id = array_search($this_event_name, $events);
						// Add event_id to $eventsUtilized array
						if (is_numeric($this_event_id))	{
							// Add this event_id
							$eventsUtilized[$this_event_id] = true;
							// If the current event is used, then make ALL events as utilized where this field's form is designated
							if ($current_event_id == $this_event_id) {
								foreach ($Proj->getEventsFormDesignated($Proj->metadata[$this_field]['form_name'], array($current_event_id)) as $this_event_id2) {
									$eventsUtilized[$this_event_id2] = true;
								}
							}
						}
					} else {
						// Add event/field to $eventsUtilized array
						$eventsUtilized[$current_event_id] = true;
					}
				}
			} else {
				// Classic
				$eventsUtilized[$Proj->firstEventId] = true;
			}
			// Add to $eventsUtilizedAllFields
			$eventsUtilizedAllFields = $eventsUtilizedAllFields + $eventsUtilized;
			// If classic or if using ALL events in longitudinal, then loop through all events to get this logic for ALL events
			$eventsUtilizedLogic = array();
			if (!$Proj->longitudinal) {
				// Classic
				$eventsUtilizedLogic[$Proj->firstEventId] = $this_logic;
			} else {
				// Longitudinal: Loop through each event and add
				foreach (array_keys($Proj->eventInfo) as $this_event_id) {
					// Make sure this calc field is utilized on this event for this record(s)
					if (isset($viableEvents[$this_event_id]) && in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id])) {
						$eventsUtilizedLogic[$this_event_id] = LogicTester::logicPrependEventName($this_logic, $Proj->getUniqueEventNames($this_event_id), $Proj);
					}
				}
			}
			// If there is an issue in the logic, then return an error message and stop processing
			foreach ($eventsUtilizedLogic as $this_event_id=>$this_loop_logic) {
				// If longitudinal AND saving a form/survey
				if ($Proj->longitudinal && is_numeric($current_event_id)) {
					// Set event name string to search for in the logic
					$event_name_keyword = "[".$Proj->getUniqueEventNames($current_event_id)."][";
					// If the logic does not contain the current event name at all, then it is not relevant, so skip it
					if (strpos($this_loop_logic, $event_name_keyword) === false) {
						continue;
					}
				}
				$funcName = null;
				$args = array();
				if ($logicContainsSmartVariables) {
					// Set placeholder for Smart Vars since they will have to be evaluated for each item
					$fieldToLogicFunc[$this_event_id][$this_field] = '';
				} else {
					try {
						// Instantiate logic parse
						$parser = new LogicParser();
						list($funcName, $argMap) = $parser->parse($this_loop_logic, $eventNameToId, true, true, false, false, true);
						$logicFuncToArgs[$funcName] = $argMap;
						// if (isDev()) $logicFuncToCode[$funcName] = $parser->generatedCode;
						$fieldToLogicFunc[$this_event_id][$this_field] = $funcName;
					}
					catch (Exception $e) {
						if (isDev()) print "<br>$this_field) ".$e->getMessage();
						unset($calcFields[$this_field]);
					}
				}
			}
		}
		// Return fields/values in $calcs array
		$calcs = array();
		if (!empty($calcFields)) {
			// GET ALL FIELDS USED IN EQUATIONS
			$dependentFields = getDependentFields(array_keys($calcFields), true, false, $Proj2);
			// Get data for all calc fields and all their dependent fields
			$recordData = Records::getData($Proj->project_id, 'array', $records, array_merge(array($Proj->table_pk), array_keys($calcFields), $dependentFields),
							(isset($eventsUtilizedAllFields['all']) ? array_keys($Proj->eventInfo) : array_keys($eventsUtilizedAllFields)),
							$group_id, false, false, false, false, false, false, false, false, false, array(),
							false, false, false, false, false, false, 'EVENT', false, false, true);
			// Loop through all calc values in $recordData
			foreach ($recordData as $record=>&$this_record_data1) {
				foreach ($this_record_data1 as $event_id=>$this_event_data) {
					// Is repeating instruments/event? If not, set up like repeating instrument so that all is consistent for looping.
					if ($event_id != 'repeat_instances') {
						// Create array to simulate the repeat instance data structure for looping
						$this_event_data = array($event_id=>array(""=>array(""=>$this_event_data)));
					}
					// Loop through event/repeat_instrument/repeat_instance
					foreach ($this_event_data as $event_id=>$attr1) {
						// New check to skip null events for a record
						if ($Proj->longitudinal && !isset($viableRecordEvents[$record][$event_id])
							&& !isset($viableRecordEvents[$record]['repeat_instances'][$event_id])) continue;
						// Look through smaller structures
						foreach ($attr1 as $repeat_instrument=>$attr2) {
							foreach ($attr2 as $repeat_instance=>$attr3) {
								// Loop through ONLY calc fields in each event
								foreach (array_keys($calcFields) as $field) {
									// If this field on this event does not have a corresponding function set for the calc, then skip (nothing to do)									
									if (!isset($fieldToLogicFunc[$event_id][$field])) continue;
									// If has repeating forms/events, then see if this field is relevant for this event/form
									if ($hasRepeatingFormsEvents) {
										// Get field's form
										$fieldForm = $Proj->metadata[$field]['form_name'];
										// If field is not relevant for this event/form, then skip
										if ($repeat_instrument == "" && $repeat_instance == "" && $Proj->isRepeatingForm($event_id, $fieldForm)) {
											continue;
										} elseif ($repeat_instrument != "" && $repeat_instance != "" 
											&& (!$Proj->isRepeatingForm($event_id, $fieldForm) || $repeat_instrument != $fieldForm)) {
											continue;
										}
									}
									// Get saved calc field value
									if ($repeat_instance == "") {
										$savedCalcVal = $this_record_data1[$event_id][$field];
									} else {
										$savedCalcVal = $this_record_data1['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$field];
									}
									// If project is longitudinal, make sure field is on a designated event
									if ($Proj->longitudinal && !in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$event_id])) continue;
									// Get the anonymous PHP function to use for this item
                                   $funcName = null;
                                   if (in_array($field, $logicContainsSmartVariablesFields)) {
                                       // Calc contains Smart Variables, so generate new function on the fly for this item
										try {
											// Instantiate logic parse
											$parser = new LogicParser();
                                            $logicThisItem = $calcFields[$field];
                                            $logicThisItem = Piping::pipeSpecialTags($logicThisItem, $Proj->project_id, $record, $event_id, $repeat_instance, null, true, null, $Proj->metadata[$field]['form_name']);
                                            if ($Proj->longitudinal) {
                                                $logicThisItem = LogicTester::logicPrependEventName($logicThisItem, $Proj->getUniqueEventNames($event_id), $Proj);
                                            }
											list($funcName, $argMap) = $parser->parse($logicThisItem, $eventNameToId, true, true, false, false, true);
											$logicFuncToArgs[$funcName] = $argMap;
											$fieldToLogicFunc[$event_id][$field] = $funcName;
										}
										catch (Exception $e) {
											unset($calcFields[$field]);
											continue;
										}
									} else {
										// Run regular function
										$funcName = $fieldToLogicFunc[$event_id][$field];
									}
									if ($funcName === null) continue;
									// Calculate what SHOULD be the calculated value
									$thisInstanceArgMap = $logicFuncToArgs[$funcName];
									// If we're in a repeating instance, then add the instance number to the arg map for all repeating fields 
									// that don't already have a specified instance number in the arg map.
									if ($repeat_instance != "") {
										foreach ($thisInstanceArgMap as &$theseArgs) {
											// If there is no instance number for this arm map field, then proceed
											if ($theseArgs[3] == "") {
												$thisInstanceArgEventId = ($theseArgs[0] == "") ? $event_id : $theseArgs[0];
												$thisInstanceArgEventId = is_numeric($thisInstanceArgEventId) ? $thisInstanceArgEventId : $Proj->getEventIdUsingUniqueEventName($thisInstanceArgEventId);
												$thisInstanceArgField = $theseArgs[1];
												$thisInstanceArgFieldForm = $Proj->metadata[$thisInstanceArgField]['form_name'];
												// If this event or form/event is repeating event/instrument, the add the current instance number to arg map
												if ( // Is a valid repeating instrument?
													($repeat_instrument != '' && $thisInstanceArgFieldForm == $repeat_instrument && $Proj->isRepeatingForm($thisInstanceArgEventId, $thisInstanceArgFieldForm))
													// Is a valid repeating event?
													|| ($repeat_instrument == '' && $Proj->isRepeatingEvent($thisInstanceArgEventId)))
													// NOTE: The commented line below was causing calcs not to be calculated if referencing a field on a repeating event whose form was not designated for the event
													// || ($repeat_instrument == '' && $Proj->isRepeatingEvent($thisInstanceArgEventId) && in_array($thisInstanceArgFieldForm, $Proj->eventsForms[$thisInstanceArgEventId])))
												{
													$theseArgs[3] = $repeat_instance;
												}
											}
										}
										unset($theseArgs);
									}
									$calculatedCalcVal = LogicTester::evaluateCondition(null, $this_record_data1, $funcName, $thisInstanceArgMap, $Proj2);
									// Change the value in $this_record_data for this record-event-field to the calculated value in case other calcs utilize it
									if ($repeat_instance == "") {
										$recordData[$record][$event_id][$field] = $this_record_data1[$event_id][$field] = $calculatedCalcVal;
									} else {
										$recordData[$record]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$field] = 
											$this_record_data1['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$field] = $calculatedCalcVal;
									}
									// Now compare the saved value with the calculated value
									$is_correct = !($calculatedCalcVal !== false && $calculatedCalcVal."" != $savedCalcVal."");
									// Precision Check: If both are floating point numbers and within specific range of each other, then leave as-is
									if (!$is_correct) {
										// Convert temporarily to strings
										$calculatedCalcVal2 = $calculatedCalcVal."";
										$savedCalcVal2 = $savedCalcVal."";
										// Neither must be blank AND one must have decimal
										if ($calculatedCalcVal2 != "" && $savedCalcVal2 != "") {
											// Get position of decimal
											$calculatedCalcVal2Pos = strpos($calculatedCalcVal2, ".");
											if ($calculatedCalcVal2Pos === false) {
												$calculatedCalcVal2 .= ".0";												
												$calculatedCalcVal2Pos = strpos($calculatedCalcVal2, ".");
											}
											$savedCalcVal2Pos = strpos($savedCalcVal2, ".");
											if ($savedCalcVal2Pos === false) {
												$savedCalcVal2 .= ".0";
												$savedCalcVal2Pos = strpos($savedCalcVal2, ".");
											}
											// If numbers have differing precision, then round both to lowest precision of the two and compare
											$precision1 = strlen(substr($calculatedCalcVal2, $calculatedCalcVal2Pos+1));
											$precision2 = strlen(substr($savedCalcVal2, $savedCalcVal2Pos+1));
											$precision3 = ($precision1 < $precision2) ? $precision1 : $precision2;
											// Check if they are the same number after rounding
											$is_correct = (round($calculatedCalcVal, $precision3)."" == round($savedCalcVal, $precision3)."");
										}
									}
									// If flag is set to only return incorrect values, then go to next value if current value is correct
									if ($returnIncorrectValuesOnly && $is_correct) continue;
									// Add to array
									$calcs[$record][$event_id][$repeat_instrument][$repeat_instance][$field] 
										= array('saved'=>$savedCalcVal."", 'calc'=>$calculatedCalcVal."", 'c'=>$is_correct);
								}
							}
						}
					}
				}
				// Remove data as we go
				unset($recordData[$record]);
			}
			unset($this_record_data1);
		}
		// Return array of values
		return $calcs;
	}


	/**
	 * For specific records and calc fields given, perform calculations to update those fields' values via server-side scripting.
	 * @param array $calcFields Array of calc fields to calculate (if contains non-calc fields, they will be removed automatically) - if an empty array, then assumes ALL fields in project.
	 * @param array $records Array of records to perform the calculations for (if an empty array, then assumes ALL records in project).
	 * @param array $excludedRecordEventFields Array of record-event-fieldname (as keys) to exclude when saving values.
	 */
	public static function saveCalcFields($records=array(), $calcFields=array(), $current_event_id='all',
										  $excludedRecordEventFields=array(), $Proj2=null, $dataLogging=true, $group_id = null)
	{
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj, $user_rights;
			$group_id = (isset($user_rights['group_id'])) ? $user_rights['group_id'] : null;
		} else {
			$Proj = $Proj2;
		}
		// Validate $current_event_id
		if (!is_numeric($current_event_id)) $current_event_id = 'all';
		// Return number of calculations that were updated/saved
		$calcValuesUpdated = 0;
		// Perform calculations on ALL calc fields over ALL records, and return those that are incorrect
		$calcFieldData = self::calculateMultipleFields($records, $calcFields, true, $current_event_id, $group_id, $Proj2);
		if (!empty($calcFieldData)) {
			// Loop through any excluded record-event-fields and remove them from array
			foreach ($excludedRecordEventFields as $record=>$this_record_data) {
				foreach ($this_record_data as $event_id=>$this_event_data) {
					foreach ($this_event_data as $repeat_instrument=>$attr1) {
						foreach ($attr1 as $repeat_instance=>$attr2) {
							foreach (array_keys($attr2) as $field) {
								if ($repeat_instance < 1) $repeat_instance = "";
								if (isset($calcFieldData[$record][$event_id][$repeat_instrument][$repeat_instance][$field])) {
									// Remove it
									unset($calcFieldData[$record][$event_id][$repeat_instrument][$repeat_instance][$field]);
									$removed++;
								}
							}
							if (empty($calcFieldData[$record][$event_id][$repeat_instrument][$repeat_instance])) unset($calcFieldData[$record][$event_id][$repeat_instrument][$repeat_instance]);
						}
						if (empty($calcFieldData[$record][$event_id][$repeat_instrument])) unset($calcFieldData[$record][$event_id][$repeat_instrument]);
					}
					if (empty($calcFieldData[$record][$event_id])) unset($calcFieldData[$record][$event_id]);
				}
				if (empty($calcFieldData[$record])) unset($calcFieldData[$record]);
			}
			// Loop through all calc values in $calcFieldData and format to data array format
			$calcDataArray = array();
			foreach ($calcFieldData as $record=>&$this_record_data) {
				foreach ($this_record_data as $event_id=>&$this_event_data) {
					foreach ($this_event_data as $repeat_instrument=>&$attr1) {
						foreach ($attr1 as $repeat_instance=>&$attr2) {
							foreach ($attr2 as $field=>$attr) {
								if ($repeat_instance == "") {
									// Normal data structure
									$calcDataArray[$record][$event_id][$field] = $attr['calc'];
								} else {
									// Repeating data structure
									$calcDataArray[$record]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$field] = $attr['calc'];
								}
							}
						}
					}
				}
				unset($calcFieldData[$record]);
			}
			// Save the new calculated values
			$saveResponse = Records::saveData($Proj->project_id, 'array', $calcDataArray, 'overwrite', 'YMD', 'flat', $group_id, $dataLogging,
											  false, true, true, false, array(), false, true, true);
			// Set number of calc values updated
			if (empty($saveResponse['errors'])) {
				$calcValuesUpdated = $saveResponse['item_count'];
			}
		}
		// Return number of calculations that were updated/saved
		return $calcValuesUpdated;
	}


	/**
	 * Determine all calc fields based upon a trigger field used in their calc equation. Return as array of fields.
	 * Also return any calc fields that are found in $triggerFields as well.
	 */
	public static function getCalcFieldsByTriggerField($triggerFields=array(), $do_recursive=true, $Proj2=null)
	{
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj;
		} else {
			$Proj = $Proj2;
		}
		// Array to capture the calc fields
		$calcFields = array();
		// Validate $triggerFields and add field to SQL where clause
		$triggerFieldsRegex = array();
		foreach ($triggerFields as $key=>$field) {
			if (isset($Proj->metadata[$field])) {
				if ($Proj->metadata[$field]['element_type'] == 'calc') {
					// If this field is a calc field, then add it to $calcFields automatically
					$calcFields[] = $field;
				} elseif ($Proj->isCheckbox($field)) {
					// Loop through all checkbox choices and add each
					foreach (parseEnum($Proj->metadata[$field]['element_enum']) as $code=>$label) {
						// Add to trigger fields regex array
						$triggerFieldsRegex[] = preg_quote("[$field($code)]");
					}
				} else {
					// Add to trigger fields regex array
					$triggerFieldsRegex[] = preg_quote("[$field]");
				}
			}
		}
		// Create regex string
		$regex = "/(" . implode("|", $triggerFieldsRegex) .")/";
		// Now loop through all calc fields to see if any trigger field is used in its equation
		foreach ($Proj->metadata as $field=>$attr) {
			if ($attr['element_type'] == 'calc' && $attr['element_enum'] != '' &&
				// Add if one field is used in the equation OR if no fields are used (means that it's purely numerical - unlikely but possible)
				(strpos($attr['element_enum'], "[") === false || preg_match($regex, $attr['element_enum'])))
			{
				$calcFields[] = $field;
			}
		}
		// Do array unique
		$calcFields = array_values(array_unique($calcFields));
		// In case some calc fields are used by other calc fields, do a little recursive check to get ALL calc fields used
		if ($do_recursive) {
			$loop = 1;
			do {
				// Get original field count
				$countCalcFields = count($calcFields);
				// Get more dependent calc fields, if any
                if ($countCalcFields > 0) {
				    $calcFields = self::getCalcFieldsByTriggerField($calcFields, false, $Proj2);
                }
				// Prevent over-looping, just in case
				$loop++;
			} while ($loop < 100 && $countCalcFields < count($calcFields));
		}
		// Return array
		return $calcFields;
	}

	// Replace all instances of ="" and ='' with ="NaN" and ='NaN'
	public static function replaceEqualNull($string)
	{
		// Return if not applicable
		if ($string == '') return '';
		if (strpos($string, "''") === false && strpos($string, '""') === false) return $string;
		// Do regex replacement to format string for parsing purposes
		$string = preg_replace(array("/(['|\"]{2})(\s*)(=|!=|\<\>|\>|\>=|\<|\<=)/", "/(=|!=|\<\>|\>|\>=|\<|\<=)(\s*)(['|\"]{2})/"), array("'NaN'$3", "$1'NaN'"), $string);
		$string = str_replace(array("'NaN'<>", "<>'NaN'"), array("'NaN'!=", "!='NaN'"), $string);
		// Return string
		return $string;
	}

	// Replace commas with dots for "numbers w/ comma decimals"
	public static function replaceCommasForDecimals($string)
	{
		return $string;
		###### TO DO ####################
		// Return if not applicable
		if ($string == '') return '';
		if (strpos($string, ",") === false) return $string;
		// Do regex replacement to format string for parsing purposes
		$string = preg_replace(array("/(['|\"]?)(\d+)(,)(\d+)(['|\"]?)(\s*)(=|!=|\<\>|\>|\>=|\<|\<=)/", "/(=|!=|\<\>|\>|\>=|\<|\<=)(\s*)(['|\"]?)(\d+)(,)(\d+)(['|\"]?)/"), array("$2.$4$6$7", "$1$2$4.$6"), $string);
		// Return string
		return $string;
	}

	// Replace all instances of "NaN" and 'NaN' in string is_nan_null()
	public static function replaceNaN($string)
	{
		// Return if not applicable
		if ($string == '') return '';
		if (strpos($string, "'NaN'") === false && strpos($string, '"NaN"') === false) return $string;
		// Pad with spaces to avoid certain parsing issues
		$string = " $string ";
		// Add extra space around AND and OR for parsing purposes
		$string = str_ireplace(array(" and ", " or "), array("  and  ", "  or  "), $string);
		// Do regex replacement to format string for parsing purposes
		$string = preg_replace(	array("/('|\")(NaN)('|\")(\s*)(=|!=|<>)/", "/(=|!=|<>)(\s*)('|\")(NaN)('|\")/"),
								array("'NaN'$5", "$1'NaN'"), $string);
		$string = str_replace(array("'NaN'<>", "<>'NaN'"), array("'NaN'!=", "!='NaN'"), $string);

		// Set max loops to prevent infinite looping mistakenly
		$max_loops = 10000;

		// Replace "'NaN'=" and "'NaN'!="
		$nanStrings = array("'NaN'=", "'NaN'!=");
		foreach ($nanStrings as $nanString) {
			$nanStringLen = strlen($nanString);
			$nanPos = strpos($string, $nanString);
			$loop_num = 1;
			while ($nanPos !== false && $loop_num <= $max_loops) {
				// How many nested parentheses we're inside of
				$nested_paren_count = 0;
				$string_len = strlen($string);
				// Capture the position to put the closing parenthesis for is_nan_null() - default to the length of the string
				$isnanCloseInsertParenPos = $string_len;
				// Loop through each letter in string to find where the logical close will be for the expression
				for ($i = $nanPos; $i <= $string_len; $i++) {
					// Get current character
					$letter = substr($string, $i, 1);
					if ($i == $string_len) {
						// BINGO! This is the last letter of the string, so this must be it
						$isnanCloseInsertParenPos = $i;
					} elseif ($letter == "(") {
						// Increment the count of how many nested parentheses we're inside of
						$nested_paren_count++;
					} elseif ($nested_paren_count == 0 && ($letter == "(" || $letter == "," 
							|| ($letter == " " && strtolower(substr($string, $i, 4)) == ' or ')
							|| ($letter == " " && strtolower(substr($string, $i, 5)) == ' and ')
					)) {
						// BINGO!
						$isnanCloseInsertParenPos = ($letter == "(" || $letter == ",") ? $i : $i-1;
						break;
					} elseif ($letter == ")") {
						// We just left a nested parenthesis, so reduce count by 1 and keep looping
						$nested_paren_count--;
					}
				}
				## Rebuild the string and insert the is_nan_null() function
				// Build the part inside is_nan_null()
				$before_is_nan_null = substr($string, 0, $nanPos);
				$nan_negation = (strpos($nanString, "!") === false ? "" : "!");
				$inside_is_nan_null = trim(substr($string, $nanPos+$nanStringLen, $isnanCloseInsertParenPos-$nanPos-$nanStringLen));
				$after_is_nan_null = substr($string, $isnanCloseInsertParenPos);
				$string = $before_is_nan_null . $nan_negation . "is_nan_null($inside_is_nan_null)" . $after_is_nan_null;
				// Set value for next loop, if needed
				$nanPos = strpos($string, $nanString);
				// Increment loop num
				$loop_num++;
			}
		}

		// Replace "='NaN'" and "!='NaN'"
		$nanStrings = array("!='NaN'", "='NaN'");
		foreach ($nanStrings as $nanString) {
			$nanStringLen = strlen($nanString);
			$nanPos = strpos($string, $nanString);
			$loop_num = 1;
			while ($nanPos !== false && $loop_num <= $max_loops) {
				// How many nested parentheses we're inside of
				$nested_paren_count = 0;
				$string_len = strlen($string);
				// Capture the position to put the closing parenthesis for is_nan_null() - default to the length of the string
				$isnanCloseInsertParenPos = 0;
				// Loop through each letter in string to find where the logical close will be for the expression
				for ($i = $nanPos; $i >= 0; $i--) {
					// Get current character
					$letter = substr($string, $i, 1);
					if ($i == 0) {
						// BINGO! This is the first letter of the string, so this must be it
						$isnanCloseInsertParenPos = $i;
					} elseif ($letter == ")") {
						// Increment the count of how many nested parentheses we're inside of
						$nested_paren_count++;
					} elseif ($nested_paren_count == 0 && ($letter == "(" || $letter == "," 
							|| ($letter == "c" && substr($string, $i, 8) == 'chkNull(')
							|| ($letter == " " && strtolower(substr($string, $i, 4)) == ' or ')
							|| ($letter == " " && strtolower(substr($string, $i, 5)) == ' and ')
					)) {
						// BINGO!
						if ($letter == "(" || $letter == ",") {
							$isnanCloseInsertParenPos = $i;
						} elseif ($letter == " " && strtolower(substr($string, $i, 4)) == ' or ') {
							$isnanCloseInsertParenPos = $i+3;
						} elseif ($letter == " " && strtolower(substr($string, $i, 5)) == ' and ') {
							$isnanCloseInsertParenPos = $i+4;
						} else {
							$isnanCloseInsertParenPos = $i-1;	
						}
						break;
					} elseif ($letter == "(") {
						// We just left a nested parenthesis, so reduce count by 1 and keep looping
						$nested_paren_count--;
					}
				}
				//print "<br>\$nanPos: $nanPos, \$isnanCloseInsertParenPos: $isnanCloseInsertParenPos, \$nanStringLen: $nanStringLen";
				$string = substr($string, 0, $isnanCloseInsertParenPos+1) . (strpos($nanString, "!") === false ? "" : "!")
						. "is_nan_null(" . substr($string, $isnanCloseInsertParenPos+1, $nanPos-$isnanCloseInsertParenPos-1)
						. ")" . substr($string, $nanPos+$nanStringLen);
				// Set value for next loop, if needed
				$nanPos = strpos($string, $nanString);
				// Increment loop num
				//print "<br>\$loop_num: $loop_num";
				$loop_num++;
			}
		}
		// Trim the string and return it
		return trim($string);
	}

	// Replace round() in calc field with roundRC(), which returns FALSE with non-numbers
	public static function replaceRoundRC($string)
	{
		// Deal with round(, if any are present
		$regex = "/(round)(\s*)(\()/";
		if (strpos($string, "round") !== false && preg_match($regex, $string)) {
			// Replace all instances of round( with roundRC(
			$string = preg_replace($regex, "roundRC(", $string);
		}
		return $string;
	}

	// Replace all instances of "log" in string with "logRC" to handle non-numbers
	public static function replaceLog($string)
	{
		// Deal with log(, if any are present
		$regex = "/([^a-z0-9_])(log)(\s*)(\()/";
		if (strpos($string, "log") !== false && preg_match($regex, $string)) {
			// Replace all instances of log( with logRC(
			$string = preg_replace($regex, "$1logRC(", $string);
		}
		return $string;
	}

	// Replace all instances of "min" in string with "minRC" to handle non-numbers
	public static function replaceMin($string)
	{
		// Deal with min(, if any are present
		$regex = "/([^a-z0-9_])(min)(\s*)(\()/";
		if (strpos($string, "min") !== false && preg_match($regex, $string)) {
			// Replace all instances of min( with minRC(
			$string = preg_replace($regex, "$1minRC(", $string);
		}
		return $string;
	}

	// Replace all instances of "max" in string with "maxRC" to handle non-numbers
	public static function replaceMax($string)
	{
		// Deal with max(, if any are present
		$regex = "/([^a-z0-9_])(max)(\s*)(\()/";
		if (strpos($string, "max") !== false && preg_match($regex, $string)) {
			// Replace all instances of max( with maxRC(
			$string = preg_replace($regex, "$1maxRC(", $string);
		}
		return $string;
	}

	// Wrap all field names with chkNull (except date/time fields)
	public static function addChkNull($string, $Proj2=null)
	{
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj;
		} else {
			$Proj = $Proj2;
		}		
		$valTypes = getValTypes();
		$eventNames = $Proj->getUniqueEventNames();		
		// Loop through all fields used in logic
		$all_logic_fields = $all_logic_fields_events = array();
		foreach (array_keys(getBracketedFields($string, true, true)) as $field) {
			if (strpos($field, ".") !== false) {
				// Event is prepended
				list ($event, $field) = explode(".", $field);
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields_events["[$event][$field($code)]"] = "chkNull([$event--RCEVT--$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					$fieldType = $Proj->metadata[$field]['element_type'];
					// Is this a MC field with all-numeric codings?
					$mcFieldWithNumCodings = ($Proj->isMultipleChoice($field) && isinteger(str_replace("-", "", implode("", array_keys(parseEnum($Proj->metadata[$field]['element_enum']))))));
					$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
					if ($fieldValidation == 'float') $fieldValidation = 'number';
					if ($fieldValidation == 'int') $fieldValidation = 'integer';
					$fieldDataType = isset($valTypes[$fieldValidation]['data_type']) ? $valTypes[$fieldValidation]['data_type'] : '';
					if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
						$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file')
					{
						$all_logic_fields_events["[$event][$field]"] = "chkNull([$event--RCEVT--$field])"; // Add --RCEVT-- to replace later on so that non-event field replacement won't interfere
					}
				}
			} else {
				// Normal field syntax (no prepended event)
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields["[$field($code)]"] = "chkNull([$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					$fieldType = $Proj->metadata[$field]['element_type'];
					// Is this a MC field with all-numeric codings?
					$mcFieldWithNumCodings = ($Proj->isMultipleChoice($field) && isinteger(str_replace("-", "", implode("", array_keys(parseEnum($Proj->metadata[$field]['element_enum']))))));
					$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
					if ($fieldValidation == 'float') $fieldValidation = 'number';
					if ($fieldValidation == 'int') $fieldValidation = 'integer';
					$fieldDataType = isset($valTypes[$fieldValidation]['data_type']) ? $valTypes[$fieldValidation]['data_type'] : '';
					if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
						$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file')
					{
						$all_logic_fields["[$field]"] = "chkNull([$field])";
					}
				}
			}
		}
		// Now through through all replacement strings and replace
		foreach ($all_logic_fields_events as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		foreach ($all_logic_fields as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		$string = str_replace("--RCEVT--", "][", $string);
		// Fix any repeating instances that did not get replaced correctly
		if ($Proj->hasRepeatingFormsEvents()) {
			$regex = '/(\)?)(\]\)\[)(\d+|first-instance|last-instance|previous-instance|next-instance|current-instance)(\])/';
			$string = preg_replace($regex, "$1][$3$4)", $string);
		}
		// Return the filtered string with chkNull
		return $string;
	}

	// Find first closing parenthesis at level 0 (i.e., not nested)
	public static function getLocationLastUnnestedClosedParen($string="", $offset=0)
	{
		$level = 0;
		$strlen = strlen($string);
		if ($offset >= $strlen) $offset = 0;
		for($i = $offset; $i < strlen($string); $i++) {
			$paren = $string[$i];
			if ($paren == '(') {
				$level++;
			} elseif ($paren == ')' && $level > 0) {			
				$level--;
				// At end, so return string from beginning to here
				if ($level === 0) return $i;
			}
			if ($level < 0) {
				// not nested correctly
				return false;
			}
		}
		// If got there, then couldn't find it
		return false;
	}

	// Replace all literal date values inside datediff()
	public static function replaceDatediffLiterals($string)
	{
		// Deal with datediff(), if any are present
		$regex = "/(datediff)(\s*)(\()(\s*)/";
		$dd_func_paren = "datediff(";

		// If string contains datediff(), then reformat so that no spaces exist between it and parenthesis (makes it easier to process)
		if (strpos($string, "datediff") !== false && preg_match($regex, $string)) {
			// Replace strings
			$string = preg_replace($regex, $dd_func_paren, $string);
		} else {
			// No datediffs, so return
			return $string;
		}

		// Set other variables to be used
		$dd_func_paren_replace = "rcr-diff(";
		$dd_func_paren_len = strlen($dd_func_paren);

		// Loop through each datediff instance in string
		$num_loops = 0;
		$max_loops = 1000;
		$dd_pos = strpos($string, $dd_func_paren);
		while ($dd_pos !== false && preg_match($regex, $string) && $num_loops < $max_loops) {
			// Replace this current datediff with another string (so we know we're working on it)
			$string = substr($string, 0, $dd_pos) . $dd_func_paren_replace . substr($string, $dd_pos+$dd_func_paren_len);
			// Explode the string to get the first parameters
			$first_of_string = substr($string, 0, $dd_pos+$dd_func_paren_len);
			// Break up into individual params
			$last_paren_pos = self::getLocationLastUnnestedClosedParen($string, $dd_pos);
			$string_after_last_paren = substr($string, $last_paren_pos);
			$string_dd_only = substr($string, $dd_pos, $last_paren_pos-$dd_pos);
			$string_dd_only = substr($string_dd_only, $dd_func_paren_len);
			list ($first_param, $second_param, $third_param, $fourth_param, $last_of_string) = explode(",", $string_dd_only, 5);
			if ($forth_param === null) $forth_param = '';
			if ($last_of_string === null) $last_of_string = '';
			// Trim params
			$first_param = trim($first_param);
			$second_param = trim($second_param);
			$third_param = trim($third_param);
			$fourth_param = trim(isset($fourth_param) ? $fourth_param : '');
			$fourth_param_beginning = strtolower(substr($fourth_param, 0, 5));
			// Get the date format (if not specific, then assumes YMD, in which case it's okay and we can leave here and return string as-is.
			if (in_array($fourth_param_beginning, array("'mdy'", "'dmy'", '"mdy"', '"dmy"'))) {
				// Get date format
				$date_format = substr($fourth_param, 1, 3);
				// Check each param and convert to YMD format if a MDY or DMY literal date
				$first_param_charcheck = substr($first_param, 0, 1).substr($first_param, 3, 1).substr($first_param, 6, 1).substr($first_param, -1);
				if (($first_param_charcheck == '"--"' || $first_param_charcheck == "'--'")) {
					// This is a literal date, so convert it to YMD.
					$first_param_no_quotes = substr($first_param, 1, -1);
					// Convert date to YMD and wrap with quotes
					$first_param = '"' . DateTimeRC::datetimeConvert($first_param_no_quotes, $date_format, 'ymd') . '"';
				}
				$second_param_charcheck = substr($second_param, 0, 1).substr($second_param, 3, 1).substr($second_param, 6, 1).substr($second_param, -1);
				if (($second_param_charcheck == '"--"' || $second_param_charcheck == "'--'")) {
					// This is a literal date, so convert it to YMD.
					$second_param_no_quotes = substr($second_param, 1, -1);
					// Convert date to YMD and wrap with quotes
					$second_param = '"' . DateTimeRC::datetimeConvert($second_param_no_quotes, $date_format, 'ymd') . '"';
				}
				// Splice the string back together again
				$string = $first_of_string . "$first_param, $second_param, $third_param, $fourth_param" . ($last_of_string == null ? '' : ", $last_of_string");
				// Re-add end of string (if there was more after the datediff function)
				$string .= $string_after_last_paren;
			}
			// Check string again for an instance of "datediff" to see if we should keep looping
			$dd_pos = strpos($string, $dd_func_paren);
			// Increment loop
			$num_loops++;
		}
		// Unreplace "datediff"
		$string = str_replace($dd_func_paren_replace, $dd_func_paren, $string);
		// Return string
		return $string;
	}


	// Format calculation to PHP format
	public static function formatCalcToPHP($string, $Proj2=null)
	{
		// Replace tabs and line breaks
		$string = str_replace(array("\r\n", "\n", "\r", "\t"), array(" ", " ", " ", " "), $string);
		// Replace any instances of round() with roundRC()
		$string = self::replaceRoundRC($string);
		// Wrap all field names with chkNull (except date/time fields)
		$string = self::addChkNull($string, $Proj2);
		// Replace ="" with ="NaN" for better parsing
		$string = self::replaceEqualNull($string);
		// Replace all instances of "NaN" and 'NaN' in string with ""
		$string = self::replaceNaN($string);
		// Replace all instances of "log" in string with "logRC" to handle non-numbers
		$string = self::replaceLog($string);
		// Replace all instances of "min" in string with "minRC" to handle non-numbers
		$string = self::replaceMin($string);
		// Replace all instances of "max" in string with "maxRC" to handle non-numbers
		$string = self::replaceMax($string);
		// Replace all literal date values inside datediff()
		$string = self::replaceDatediffLiterals($string);
		// Return formatted string
		return $string;
	}

}
