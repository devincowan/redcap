<?php

/**
 * LogicTester
 * This class is used for execution/testing of logic used in Data Quality, branching logic, Automated Invitations, etc.
 */
class LogicTester
{
	/**
	 * Tests the logic with existing data and returns boolean as TRUE if all variables have values
	 * and the logic evaluates as true. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function apply($logic, $record_data=array(), $Proj=null, $returnValue=false, $useAnonFunction=false)
	{
		if ($Proj == null) {
			global $Proj;
		}
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		// If there is an issue in the logic, then return an error message and stop processing
		$funcName = null;
		try {
			// Instantiate logic parser
			$parser = new LogicParser();
			list ($funcName, $argMap) = $parser->parse($logic, array_flip($events), true, $isCalcField=false, $useAnonFunction);
		}
		catch (LogicException $e) {
			return false;
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		$logicApplied = self::applyLogic($funcName, $argMap, $record_data, $Proj->firstEventId, $returnValue, $Proj->project_id);
		if ($returnValue === false) {
			return ($logicApplied === 1);
		} else {
			return $logicApplied;
		}
	}


	/**
	 * Check if the logic is syntactically valid
	 */
	public static function isValid($logic)
	{
		$parser = new LogicParser();
		try {
			$parser->parse($logic, null, false);
			return true;
		} catch (LogicException $e) {
			return false;
		}
	}


	/**
	 * Evaluate a logic string for a given record
	 */
	public static function evaluateLogicSingleRecord($raw_logic, $record, $record_data=null, $project_id_override=null,
													 $repeat_instance=1, $repeat_instrument=null, $returnValue=false, $bypassIsValid=false, $useAnonFunction=false)
	{
		// Check the logic to see if it's syntactically valid
		if (!$bypassIsValid && !self::isValid($raw_logic)) {
			return false;
		}
		// Get $Proj object
		if (is_numeric($project_id_override)) {
			$Proj = new Project($project_id_override);
		} else {
			global $Proj;
		}
		// Array to collect list of all fields used in the logic
		$fields = array();
		$events = ($Proj->longitudinal) ? array() : array($Proj->firstEventId);
		// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
		foreach (array_keys(getBracketedFields($raw_logic, true, true, false)) as $this_field)
		{
			// Check if has dot (i.e. has event name included)
			if (strpos($this_field, ".") !== false) {
				list ($this_event_name, $this_field) = explode(".", $this_field, 2);
				$events[] = $this_event_name;
			}
			// Verify that the field really exists (may have been deleted). If not, stop here with an error.
			if (!isset($Proj->metadata[$this_field])) return false;
			// Add field to array
			$fields[] = $this_field;
		}
		$events = array_unique($events);
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
		if ($record_data == null) {
			// Retrieve data from data table since $record_data array was not passed as parameter
			$record_data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
		}
		// If some events don't exist in $record_data because there are no values in data table for that event,
		// then add empty event with default values to $record_data (or else the parse will throw an exception).
        $recordDataEventIds = array_keys($record_data[$record]);
        $recordDataEventIdsRepeatKey = array_search('repeat_instances', $recordDataEventIds);
        if ($recordDataEventIdsRepeatKey !== false) {
            unset($recordDataEventIds[$recordDataEventIdsRepeatKey]);
        }
        if (count($events) > count($recordDataEventIds)) {
            // Get unique event names (with event_id as key)
            $unique_events = $Proj->getUniqueEventNames();
            // Loop through each event
            foreach ($events as $event) {
                // If classical, the 'events' array will have just then firstEventId
                // If longitudinal, 'events' will be an array of unique_event_names
                $this_event_id = is_numeric($event) ? $event : array_search($event, $unique_events);
                if (!isset($record_data[$record][$this_event_id])) {
					// Add all fields from $fields with defaults for this event
					foreach ($fields as $this_field) {
						// If a checkbox, set all options as "0" defaults
						if ($Proj->isCheckbox($this_field)) {
							foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
								$record_data[$record][$this_event_id][$this_field][$this_code] = "0";
							}
						}
						// If a Form Status field, give "0" default
						elseif ($this_field == $Proj->metadata[$this_field]['form_name']."_complete") {
							$record_data[$record][$this_event_id][$this_field] = "0";
						} else {
							$record_data[$record][$this_event_id][$this_field] = "";
						}
					}
				}
			}
		}
        /*
		// If this is not the first instance of a repeating instrument, then modify $record_data to remove the extra instances of
		// this instrument and set the $repeat_instance as instance 1 (so that LogicTester::apply can interpret it correctly).
		if ($repeat_instance > 0 || $repeat_instrument != "") 
		{
			$record_data_repeat_instance = array();
			$removeRepeatInstancesKey = false;
			foreach ($record_data[$record]['repeat_instances'] as $this_event_id=>&$attr) {
				if (!isset($attr[$repeat_instrument][$repeat_instance])) continue;
				// Loop through instance's fields, and if they exist on this instrument, then overwrite on main event for instance 1
				foreach ($attr[$repeat_instrument][$repeat_instance] as $this_field=>$this_val) {
					// If this field is somehow not on the repeating form, then skip
					if ($repeat_instrument != "" && !isset($Proj->forms[$repeat_instrument]['fields'][$this_field])) continue;
					// Overwrite instance 1's data with this instance
					$record_data[$record][$this_event_id][$this_field] = $this_val;
					$removeRepeatInstancesKey = true;
				}
			}
			// Remove all the instances since they are not needed
			if ($removeRepeatInstancesKey) unset($record_data[$record]['repeat_instances']);
		}
		*/
		// Apply the logic and return the result (TRUE = all conditions are true)
		return self::apply($raw_logic, $record_data[$record], $Proj, $returnValue, $useAnonFunction);
	}

	// Format logic to PHP format
	public static function formatLogicToPHP($string)
	{
		// Replace tabs and line breaks and reset any operators back to user-facing specs
		$string = str_replace(array("\r\n", "\n", "\r", "\t", "!=", " && ",  " || ", " AND ", " OR "),
							  array(" ",    " ",  " ",  " ",  "<>", " and ", " or ", " and ", " or "),
							  $string);
		// Pre-format the string with regard to exponent format - (x)^(y)
		$string = self::replaceExponents($string, true);
		// Replace any instances of round() with roundRC()
		$string = Calculate::replaceRoundRC($string);
		// Replace ="" with ="NaN" for better parsing
		$string = Calculate::replaceEqualNull($string);
		// Replace commas with dots for "numbers w/ comma decimals"
		$string = Calculate::replaceCommasForDecimals($string);
		// Replace all instances of "NaN" and 'NaN' in string with ""
		$string = Calculate::replaceNaN($string);
		// Replace all instances of "log" in string with "logRC" to handle non-numbers
		$string = Calculate::replaceLog($string);
		// Replace all instances of "min" in string with "minRC" to handle non-numbers
		$string = Calculate::replaceMin($string);
		// Replace all instances of "max" in string with "maxRC" to handle non-numbers
		$string = Calculate::replaceMax($string);
		// Replace all literal date values inside datediff()
		$string = Calculate::replaceDatediffLiterals($string);
		// Return formatted string
		return $string;
	}


	/**
	 * Runs the logic function and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	public static function applyLogic($funcName, $argMap=array(), $recordData=array(), $firstEventId=null, $returnValue=false, $project_id=null)
	{
		$args = array();
		try {
			if (!self::buildLogicArgs($argMap, $recordData, $args, $firstEventId, $project_id)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
			$logicCheckResult = call_user_func_array($funcName, $args);
			if ($returnValue === false) {
				return ($logicCheckResult === false ? 0 : 1);
			} else {
				return $logicCheckResult;
			}
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Builds the arguments to an anonymous function given record data.
	 * @param string $funcName the name of the function to build args for.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @return boolean true if $recordData contained all data necessary to
	 * populate the function parameters, false if not.
	 */
	static public function buildLogicArgs($argMap=array(), $recordData=array(), &$args, $firstEventId=null, $project_id=null)
	{
		$isValid = true;
		// Get first event ID for the relevant project
		if ($firstEventId == null && defined("PROJECT_ID")) {
			global $Proj;
			$firstEventId = $Proj->firstEventId;
			$project_id = $Proj->project_id;
		} elseif (is_numeric($firstEventId) && !is_numeric($project_id)) {
			// Obtain project_id from event_id
			$sql = "select a.project_id from redcap_events_metadata e, redcap_events_arms a 
					where a.arm_id = e.arm_id and e.event_id = $firstEventId limit 1";
			$q = db_query($sql);
			if (!db_num_rows($q)) throw new Exception("Could not determine project_id");
			$project_id = db_result($q, 0);
			$Proj = new Project($project_id);
			$firstEventId = $Proj->firstEventId;
		} elseif ((!is_numeric($firstEventId) || !isset($Proj)) && is_numeric($project_id)) {
			$Proj = new Project($project_id);
			$firstEventId = $Proj->firstEventId;
		}
		if (!is_numeric($firstEventId) || !is_numeric($project_id)) {
			throw new Exception("Could not determine project_id or first event_id");
		}
		try {
			foreach ($argMap as $argData)
			{
				// Get event_id, variable, and (if a checkbox) checkbox choice
				list ($eventVar, $projectVar, $cboxChoice, $instanceVar) = $argData;
				// If missing the event_id, assume the first event_id in the project
				if ($eventVar == "") $eventVar = $firstEventId;
				$eventId = is_numeric($eventVar) ? $eventVar : $Proj->getEventIdUsingUniqueEventName($eventVar);
				// Determine repeating instrument based on event_id and field's form
				$isRepeatInstance = false;
				if (is_numeric($instanceVar) && $Proj->isRepeatingEvent($eventId)) {
					$repeat_instance = $instanceVar;
					$repeat_instrument = "";
					$isRepeatInstance = true;
				} elseif (is_numeric($instanceVar) && $Proj->isRepeatingForm($eventId, $Proj->metadata[$projectVar]['form_name'])) {
					$repeat_instrument = $Proj->metadata[$projectVar]['form_name'];
					$repeat_instance = $instanceVar;
					$isRepeatInstance = true;
				} else {
					$repeat_instrument = "";
					$repeat_instance = 0;
				}
				// If instance=0, then we may be using relative instance references (previous-instance), so return ""
                if ($isRepeatInstance && $repeat_instance < 1) {
                    $args[] = "";
                    continue;
                }
				// Check event key
				if (!isset($recordData[$eventId]) && !isset($recordData['repeat_instances'][$eventId])) {
					throw new Exception("Missing event: $eventId");
				}
				if ($isRepeatInstance && isset($recordData['repeat_instances'][$eventId][$repeat_instrument][$repeat_instance])) {
					$projFields = $recordData['repeat_instances'][$eventId][$repeat_instrument][$repeat_instance];
				} else {
					$projFields = $recordData[$eventId];
				}
//				print "\n\$isRepeatInstance: ";var_dump($isRepeatInstance);
//				print "\n\$projectVar: ";var_dump($projectVar);
//				print "\n\$eventId: ";var_dump($eventId);
//				print "\n\$instanceVar: ";var_dump($instanceVar);
//				print "\n\$projFields:  ";print_r($projFields);
				// Check field key
				if (!isset($projFields[$projectVar])) {
					throw new Exception("Missing project field: $projectVar");
				}
				// Set value, then validate it based on field type
				$value = $projFields[$projectVar];
				if ($cboxChoice === null && is_array($value) || $cboxChoice !== null && !is_array($value))
					throw new Exception("checkbox/value mismatch! $value " . print_r($value, true));
				if ($cboxChoice !== null && !isset($value[$cboxChoice]))
					throw new Exception("Missing checkbox choice: $cboxChoice");
				if ($cboxChoice !== null) {
					$value = $value[$cboxChoice];
				}
				// Add value to args array
				$args[] = $value;
			}
		}
		catch (Exception $e) {
			$isValid = false;
		}
		// Return if all arguments are valid and accounted for
		return $isValid;
	}

	// For fastest processing, preformat the logic by prepending [event-name] and/or appending [current-instance] where appropriate
	public static function preformatLogicEventInstanceSmartVariables($logic, $Proj)
	{
		// First, remove all instances of [current-instance] and [event-name] to start with a clean slate (will be added back later, if appropriate)
		$logic = str_replace("][current-instance]", "]", $logic);
		if ($Proj->longitudinal) {
			$logic = str_replace("[event-name][", "[", $logic);
		}
		// Only prepend/append smart variables if we have repeating instances in the project
		if ($Proj->hasRepeatingFormsEvents())
		{
			// Now set our starting point in case we need to revert to it
			$logic_original = $logic;
			// Prepend [event-name]
			if ($Proj->longitudinal) {
				$logic = LogicTester::logicPrependEventName($logic, 'event-name', $Proj);
			}
			// Append [current-instance] to all fields on repeating forms/events (this aids with parsing)
			$logic = LogicTester::logicAppendCurrentInstance($logic, $Proj);
			// If we have no x-instance smart variables, which must be evaluated for each array item (i.e., slow),
			// then revert back to having no [event-name] prepended, which will make the logic processing way faster.
			if ($Proj->longitudinal && !Piping::containsInstanceSpecialTags($logic)) {
				$logic = $logic_original;
			}
		}
		return $logic;
	}


	/**
	 * For a general logic string, prepend all variables with a unique event name provided if the
	 * variable is not already prepended with a unique event name.
	 * (Used to define an event explicitly before being evaluated for a record.)
	 */
	public static function logicPrependEventName($logic, $unique_event_name, $Proj)
	{
		// Set $Proj if not set
        if ((!isset($Proj) || !is_object($Proj)) && defined("PROJECT_ID")) {
			$Proj = new Project(PROJECT_ID);
		}
        if (!$Proj->longitudinal) return $logic;
		// First, prepend fields with unique event name
		$logic = preg_replace("/([^\]])(\[)/", "$1[$unique_event_name]$2", " " . $logic);
		// Get all unique event names for this project
		$events = array();
        if (isset($Proj) && is_object($Proj)) {
			foreach ($Proj->getUniqueEventNames() as $this_event) {
				$events[] = preg_quote("[$this_event]");
			}
		}
        // Remove instances of double event names in logic as cleanup
		if (!empty($events)) {
			$logic = preg_replace("/(\[{$unique_event_name}\])(" . implode("|", $events) . ")/", "$2", $logic);
		}
		// Remove instances of double event names with special piping tags in logic as cleanup
		$specialPipingTags = array();
		foreach (Piping::getSpecialTagsFormatted(true) as $tag) {
			$specialPipingTags[$tag] = "[$unique_event_name]$tag";
		}
		$logic = str_replace($specialPipingTags, array_keys($specialPipingTags), $logic);
		// Return the formatted logic
		return $logic;
	}


	/**
	 * For a general logic string, append all variables with a repeating instance number provided if the
	 * variable is from a repeating event/form and does not already the instance number appended.
	 */
	public static function logicAppendInstance($logic, $Proj, $event_id, $instrument, $instance)
	{
		$fieldsReplace = array();
		if (!is_numeric($instance) || $instance == '0') return $logic;
		// If this is a repeating event, then gather all fields designated to this event
		if ($Proj->isRepeatingEvent($event_id)) {
			foreach ($Proj->eventsForms[$event_id] as $thisForm) {
				foreach (array_keys($Proj->forms[$thisForm]['fields']) as $thisField) {
					if ($thisField == $Proj->table_pk) continue;
					if ($Proj->isCheckbox($thisField)) {
						foreach (array_keys(parseEnum($Proj->metadata[$thisField]['element_enum'])) as $thisChoice) {						
							$fieldsReplace["[$thisField($thisChoice)]"] = "[$thisField($thisChoice)][$instance]";
							$fieldsReplace["[$thisField($thisChoice)][$instance]["] = "[$thisField($thisChoice)][";
						}
					} else {
						$fieldsReplace["[$thisField]"] = "[$thisField][$instance]";
						$fieldsReplace["[$thisField][$instance]["] = "[$thisField][";
					}
				}
			}
		}
		// If this is a repeating instrument, then gather all fields on this instrument
		if (in_array($instrument, $Proj->eventsForms[$event_id]) && $Proj->isRepeatingForm($event_id, $instrument)) {
			foreach (array_keys($Proj->forms[$instrument]['fields']) as $thisField) {
				if ($thisField == $Proj->table_pk) continue;
				if ($Proj->isCheckbox($thisField)) {
					foreach (array_keys(parseEnum($Proj->metadata[$thisField]['element_enum'])) as $thisChoice) {						
						$fieldsReplace["[$thisField($thisChoice)]"] = "[$thisField($thisChoice)][$instance]";
						$fieldsReplace["[$thisField($thisChoice)][$instance]["] = "[$thisField($thisChoice)][";
					}
				} else {
					$fieldsReplace["[$thisField]"] = "[$thisField][$instance]";
					$fieldsReplace["[$thisField][$instance]["] = "[$thisField][";
				}
			}
		}
		$fieldsReplace = array_unique($fieldsReplace);
		// Perform the replacements
		if (!empty($fieldsReplace)) {
			$logic = str_replace(array_keys($fieldsReplace), $fieldsReplace, $logic);
		}
		// Return the formated logic
		return $logic;
	}


    /**
     * For a general logic string, append all variables with [current-instance] if the
     * variable is from a repeating event/form and does not already the instance number appended.
     */
    public static function logicAppendCurrentInstance($logic, $Proj)
    {
        // Do nothing if there are no repeating events/forms
        if (!$Proj->hasRepeatingFormsEvents()) return $logic;
        $fieldsReplace = $events = $fields = $eventsInstruments = array();
        // Get smart variables
		$smartVars = Piping::getSpecialTags();
		// Get unique event names
		$eventsUnique = $Proj->getUniqueEventNames();
        // Parse the label to pull out the events/fields used therein
        $fieldsEventsLogic = array_keys(getBracketedFields($logic, true, true, false));
		$fieldsLogic = array();
        foreach ($fieldsEventsLogic as $this_key=>$this_field)
        {
            // If longitudinal with a dot, parse it out and put unique event name in $events array
            if (strpos($this_field, '.') !== false) {
                // Separate event from field
                list ($this_event, $this_field) = explode(".", $this_field, 2);
                // Put event in $events array
                $this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
                if (isset($Proj->eventInfo[$this_event_id])) {
                    if (!isset($events[$this_event_id])) $events[$this_event_id] = $this_event;
                    $eventsInstruments[] = $this_event_id."-".$Proj->metadata[$this_field]['form_name'];
                } elseif ($Proj->longitudinal && in_array($this_event, $smartVars)) {
                	// If field has a X-event smart variable, then just add all events as a possibility
					$events = $eventsUnique;
					foreach ($events as $this_event_id2=>$nothing) {
						if (!in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id2])) continue;
						$eventsInstruments[] = $this_event_id2 . "-" . $Proj->metadata[$this_field]['form_name'];
					}
				}
            } else {
                $eventsInstruments[] = $Proj->firstEventId."-".$Proj->metadata[$this_field]['form_name'];
            }
			$fieldsLogic[$this_field] = true;
        }
        $eventsInstruments = array_unique($eventsInstruments);

        // If this is a repeating event, then gather all fields designated to this event
        if ($Proj->longitudinal && $Proj->hasRepeatingEvents()) {
        	if (empty($events)) $events = $Proj->eventInfo;
        	$fieldsOnRepeatingEvent = array();
            foreach (array_keys($events) as $event_id) {
                if ($Proj->isRepeatingEvent($event_id)) {
                	// Replace relevant fields from this repeating event
                    foreach ($Proj->eventsForms[$event_id] as $thisForm) {
                        foreach (array_keys($Proj->forms[$thisForm]['fields']) as $this_field) {
                            if ($this_field == $Proj->table_pk) continue;
                            if (!isset($fieldsLogic[$this_field])) continue;
							$fieldsOnRepeatingEvent[$this_field] = true;
                            if ($Proj->isCheckbox($this_field)) {
                                foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $thisChoice) {
                                    $fieldsReplace["[$this_field($thisChoice)]"] = "[$this_field($thisChoice)][current-instance]";
                                    $fieldsReplace["[$this_field($thisChoice)][current-instance]["] = "[$this_field($thisChoice)][";
                                }
                            } else {
                                $fieldsReplace["[$this_field]"] = "[$this_field][current-instance]";
                                $fieldsReplace["[$this_field][current-instance]["] = "[$this_field][";
                            }
                        }
                    }
                }
            }
            // Does logic have any prepended event-name smart variables?
			$hasEventNameSmartVars = (strpos($logic, "event-name][") !== false);
			// In case a field exists on both a repeating and non-repeating event, we need to undo the replacements made above
			foreach (array_keys($fieldsLogic) as $this_field) {
				// If this field doesn't exist on a repeating event, then we're not worried about it
				if (!isset($fieldsOnRepeatingEvent[$this_field])) continue;
				// Find all non-repeating events that have this field
				$this_field_form = $Proj->metadata[$this_field]['form_name'];
				foreach ($Proj->eventsForms as $event_id=>$forms) {
					if ($Proj->isRepeatingEvent($event_id)) continue;
					if (!in_array($this_field_form, $forms)) continue;
					$unique_event_name = $Proj->getUniqueEventNames($event_id);
					if ($Proj->isCheckbox($this_field)) {
						foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $thisChoice) {
							$fieldsReplace["[$unique_event_name][$this_field($thisChoice)][current-instance]"] = "[$unique_event_name][$this_field($thisChoice)]";
						}
					} else {
						$fieldsReplace["[$unique_event_name][$this_field][current-instance]"] = "[$unique_event_name][$this_field]";
					}
				}
				// If logic contains an event-name smart variable prepended to fields, then remove [current-instance] from the end of these for better processing
				if ($hasEventNameSmartVars) {
					if ($Proj->isCheckbox($this_field)) {
						foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $thisChoice) {
							$fieldsReplace["event-name][$this_field($thisChoice)][current-instance]"] = "event-name][$this_field($thisChoice)]";
						}
					} else {
						$fieldsReplace["event-name][$this_field][current-instance]"] = "event-name][$this_field]";
					}
				}
			}
        }

        // If this is a repeating instrument, then gather all fields in logic from this instrument
        foreach ($eventsInstruments as $eventsInstrument) {
            list ($event_id, $instrument) = explode("-", $eventsInstrument, 2);
            if (!in_array($instrument, $Proj->eventsForms[$event_id])) continue;
            if ($Proj->isRepeatingForm($event_id, $instrument)) {
                foreach (array_keys($Proj->forms[$instrument]['fields']) as $this_field) {
                    if ($this_field == $Proj->table_pk) continue;
					if (!isset($fieldsLogic[$this_field])) continue;
                    if ($Proj->isCheckbox($this_field)) {
                        foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $thisChoice) {
                            $fieldsReplace["[$this_field($thisChoice)]"] = "[$this_field($thisChoice)][current-instance]";
                            $fieldsReplace["[$this_field($thisChoice)][current-instance]["] = "[$this_field($thisChoice)][";
                        }
                    } else {
                        $fieldsReplace["[$this_field]"] = "[$this_field][current-instance]";
                        $fieldsReplace["[$this_field][current-instance]["] = "[$this_field][";
                    }
                }
            }
        }
        // Perform the replacements
        if (!empty($fieldsReplace)) {
            $logic = str_replace(array_keys($fieldsReplace), $fieldsReplace, $logic);
        }
        // Return the formated logic
        return $logic;
    }

	/**
	 * Evaluates a condition using existing data and returns value if all variables have values. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function evaluateCondition($logic=null, $record_data=array(), $funcName=null, $argMap=null, $Proj2=null)
	{
		// If we have neither logic nor function name, then return false
		if (empty($logic) && empty($funcName)) return false;
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj;
		} else {
			$Proj = $Proj2;
		}
		// If there is an issue in the logic, then return an error message and stop processing
		if ($funcName == null) {
			try {
				// Get unique event names (with event_id as key)
				$events = $Proj->getUniqueEventNames();
				// Instantiate logic parser
				$parser = new LogicParser();
				list ($funcName, $argMap) = $parser->parse($logic, array_flip($events));
				// print $funcName."(){ ".$parser->generatedCode." }";
			}
			catch (LogicException $e) {
				// print "Error: ".$e->getMessage();
				return false;
			}
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		return self::applyLogicForEvaluateCondition($funcName, $argMap, $record_data, $Proj);
	}

	/**
	 * Runs a logic condition's result and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	static private function applyLogicForEvaluateCondition($funcName, $argMap=array(), $recordData=array(), $Proj=null)
	{
		$args = array();
		try {
			if (!LogicTester::buildLogicArgs($argMap, $recordData, $args, $Proj->firstEventId, $Proj->project_id)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
			$logicCheckResult = call_user_func_array($funcName, $args);
			return $logicCheckResult;
		}
		catch (Exception $e) {
			return false;
		}
	}


	// Replace datediff()'s comma with -DDC- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceDatediff($string)
	{
		if (strpos($string, "datediff") !== false)
		{
			## Determine which format of datediff() they're using (can include or exclude certain parameters)
			// Include the 'returnSignedValue' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8-DDC-$10-DDC-$12)", $string);
			}
			// Include the 'dateformat' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8-DDC-$10)", $string);
			}
			// Now try pattern without the 'dateformat' parameter (legacy)
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8)", $string);
			}
		}
		return $string;
	}


	// Replace round()'s comma with -ROC- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceRound($string,$i=0)
	{
		// Deal with round(, if any are present
		if (strpos($string, "round") !== false)
		{
			$regex = "/(round)(\s*)(\()([^,((round)(\s*)(\())]+)(,)([^,((round)(\s*)(\())]+)(\))/";
			// Replace all instances of round() that contain a comma inside so it does not interfere with ternary formatting later
			while (preg_match($regex, $string) && $i++ < 20)
			{
				$string = preg_replace_callback($regex, "LogicTester::replaceRoundCallback", $string);
			}
		}
		// Replace back commas that are not used in round()
		$string = str_replace("-REMOVE-", ",", $string);
		return $string;
	}


	// Callback function for replacing round()'s comma
	public static function replaceRoundCallback($matches)
	{
		// If non-equal number of '(' vs. ')', then send back -REMOVE- to replace as comma to prevent function from
		// going into infinite loops. Otherwise, assume the comma belongs to this round().
		return ((substr_count($matches[4], "(") != substr_count($matches[4], ")")) ? "round(".$matches[4]."-REMOVE-".$matches[6].")" : "round(".$matches[4]."-ROC-".$matches[6].")");
	}


	//Replace ^ exponential form with javascript or php equivalent
	public static function replaceExponents($string, $replaceForPHP=false)
	{
		//First, convert any "sqrt" functions to javascript equivalent
		if (!$replaceForPHP) { // PHP already has a sqrt function
			$first_loop = true;
			while (preg_match("/(sqrt)(\s*)(\()/", $string)) {
				//Ready the string to location "sqrt(" substring easily
				if ($first_loop) {
					$string = preg_replace("/(sqrt)(\s*)(\()/", "sqrt(", $string);
					$first_loop = false;
				}
				//Loop through each character and find outer parenthesis location
				$last_char = strlen($string);
				$sqrt_pos  = strpos($string, "sqrt(");
				$found_end = false;
				$rpar_count = 0;
				$lpar_count = 0;
				$i = $sqrt_pos;
				//Since there are parentheses inside "sqrt", loop through each letter to localize and replace
				if (!preg_match("/(sqrt)(\()([^\(\)]{1,})(\))/", $string)) {
					while ($i <= $last_char && !$found_end) {
						//Keep count of left/right parentheses
						if (substr($string, $i, 1) == "(") {
							$lpar_count++;
						} elseif (substr($string, $i, 1) == ")") {
							$rpar_count++;
						}
						//If found the parentheses boundary, then end loop
						if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
							$found_end = true;
						} else {
							$i++;
						}
					}
					$inside = substr($string, $sqrt_pos + 5, $i - $sqrt_pos - 5);
					//Replace this instance of "sqrt"
					$string = substr($string, 0, $sqrt_pos) . "Math.pow($inside-EXPC-0.5)" . substr($string, $i + 1);
				//There are no parentheses inside "sqrt", so do simple preg_replace
				} else {
					$string = preg_replace("/(sqrt)(\()([^\(\)]{1,})(\))/", "Math.pow($3-EXPC-0.5)", $string);
				}
			}
		}

		//Find all ^ and locate outer parenthesis for its number and exponent
		$powReplacement = ($replaceForPHP) ? "pow(" : "Math.pow(";
		$powReplacementLen = strlen($powReplacement);
		$caret_pos = strpos($string, "^");
		$num_carets_total = substr_count($string, "^");
		while ($caret_pos !== false)
		{
			//For first half of string
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i >= 0 && !$found_end) {
				$i--;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed first half of string
			$string = substr($string, 0, $i). $powReplacement . substr($string, $i);
			$caret_pos += $powReplacementLen; // length of "Math.pow(" or "pow("

			//For last half of string
			$last_char = strlen($string);
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i <= $last_char && !$found_end) {
				$i++;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed last half of string
			$string = substr($string, 0, $caret_pos) . "-EXPC-" . substr($string, $caret_pos + 1, $i - $caret_pos) . ")" . substr($string, $i + 1);

			if ($num_carets_total == substr_count($string, "^")) {
				// If the replacement did NOT work, then stop looping or else it'll go on forever
				$caret_pos = false;
			} else {
				// Set again for checking in next loop
				$caret_pos = strpos($string, "^");
			}
		}

		// Re-replace the comma (PHP only)
		if ($replaceForPHP) {
			$string = str_replace("-EXPC-", ",", $string);
		}

		// Return string
		return $string;
	}

	// Convert a string with an IF statement from Excel format - e.g. if(cond, true, false) -
	// to PHP ternary operator format - e.g. if(cond ? true : false).
	public static function convertIfStatement($string,$recursions=0)
	{
		// If we have any nested IFs, space them out some for more accurate parsing
		$string = str_replace("if(", "if( ", $string);
		// Check if has any IF statements
		if (preg_match("/(if)(\s*)(\()/i", $string) && substr_count($string, ",") >= 2 && $recursions < 1000)
		{
			// Remove spaces between "if" and parenthesis so we can more easily parse it downstream
			$string_temp = preg_replace("/(if)(\s*)(\()/i", "if(", $string);
			// Defaults
			$curstr = "";
			$nested_paren_count = 0; // how many nested parentheses we're inside of
			$found_first_comma = false;
			$found_second_comma = false;
			$location_first_comma = null;
			$location_second_comma = null;
			// Only begin parsing at first IF (i.e. only use string_temp)
			list ($cumulative_string, $string_temp) = explode("if(", $string_temp, 2);
			// First, find the first innermost IF in the string and get its location. We'll begin parsing there.
			$string_array = explode("if(", $string_temp);
			foreach ($string_array as $key => $this_string)
			{
				// Check if we should parse this loop
				if ($this_string != "")
				{
					// If current string is empty, then set it as this_string, otherwise prepend curstr from last loop to this_string
					$curstr .= $this_string;
					// Check if this string has ALL we need (2 commas, 1 right parenthesis, and num right parens = num left parens+1)
					$num_commas 	 = substr_count($curstr, ",");
					$num_left_paren  = substr_count($curstr, "(");
					$num_right_paren = substr_count($curstr, ")");
					$hasCompleteIfStatement = ($num_commas >= 2 && $num_right_paren > 0 && $num_right_paren > $num_left_paren);
					if ($hasCompleteIfStatement)
					{
						// The entire IF statement MIGHT be in this_string. Check if it is (commas and parens in correct order).
						$curstr_len = strlen($curstr);
						// Loop through the string letter by letter
						for ($i = 0; $i < $curstr_len; $i++)
						{
							// Get current letter
							$letter = substr($curstr, $i, 1);
							// Perform logic based on current letter and flags already set
							if ($letter == "(") {
								// Increment the count of how many nested parentheses we're inside of
								$nested_paren_count++;
							} elseif ($letter != ")" && $nested_paren_count > 0) {
								if ($i+1 == $curstr_len) {
									// This is the last letter of the string, and we still haven't completed the entire IF statement.
									// So reset curstr and go to next loop, which should have a nested IF (we'll work our way outwards)
									$cumulative_string .= "if($curstr";
									$curstr = "";
								} else {
									// We're inside a nested parenthesis, so there's nothing to do -> keep looping till we get out
								}
							} elseif ($letter == ")" && $nested_paren_count > 0) {
								// We just left a nested parenthesis, so reduce count by 1 and keep looping
								$nested_paren_count--;
							} elseif ($letter == "," && $nested_paren_count == 0 && !$found_first_comma) {
								// Found first valid comma AND not in a nested parenthesis
								$found_first_comma = true;
								$found_second_comma = false;
								$location_first_comma = $i;
								$location_second_comma = null;
							} elseif ($letter == "," && $nested_paren_count == 0 && $found_first_comma && !$found_second_comma) {
								// Found second valid comma AND not in a nested parenthesis
								$found_second_comma = true;
								$location_second_comma = $i;
							} elseif ($letter == ")" && $nested_paren_count == 0 && $found_first_comma && $found_second_comma) {
								// Found closing valid parenthesis of IF statement, so replace the commas with ternary operator format
								$cumulative_string .= "(" . substr($curstr, 0, $location_first_comma)
													. " ? " . substr($curstr, $location_first_comma+1, $location_second_comma-($location_first_comma+1))
													. " : " . substr($curstr, $location_second_comma+1);
								// Reset values for further processing
								$curstr = "";
								$found_first_comma = false;
								$found_second_comma = false;
								$location_first_comma = null;
								$location_second_comma = null;
							}
						}
					} else {
						// The entire IF statement is NOT in this_string, therefore there must be a nested IF after this one.
						// Reset curstr and begin anew with next nested IF (we'll work our way outwards from the innermost IFs)
						$cumulative_string .= "if($curstr";
						$curstr = "";
					}
				}
			}
			// If the string still has IFs because of nesting, then do recursively.
			return self::convertIfStatement($cumulative_string,++$recursions);
		}
		// Now that we're officially done parsing, return the string
		return $string;
	}
	
	// Callback function for replacing [instance]
	public static function replaceInstanceCallback($matches)
	{
		global $Proj, $repeatingFieldsEventInfo;		
		// Get parts
		$instance = $matches[4];
		$field = $matches[2];
		$event_name = $matches[1];
		if ($event_name == "") {
			$event_id = $_GET['event_id'];
		} else {
			$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
			if ($event_id == $_GET['event_id']) {
				// If prepended event name is same as the event we're on, then just remove it (not necessary)
				$event_name = "";
			}
		}
		// Find checkbox code, if any
		list ($field, $checkboxCode) = explode("(", $field, 2);
		if (substr($checkboxCode, -1) == ")") $checkboxCode = substr($checkboxCode, 0, -1);
		// Is this field on the current event-form-instance?
		$fieldOnCurrentPage = (is_numeric($instance) && $instance == $_GET['instance'] && $event_id == $_GET['event_id'] && $Proj->metadata[$field]['form_name'] == $_GET['page']);
		// Get name of HTML form
		$form = ($event_name == "") ? "form" : "form__".$event_name;
		if (is_numeric($instance) 
			// If field is currently on this page, then don't render using ____I format but normal format.
			&& !$fieldOnCurrentPage
		){
			// Has instance
			$repeat_instrument = $Proj->isRepeatingEvent($event_id) ? "" : $Proj->metadata[$field]['form_name'];
			if ($checkboxCode == "") {
				$returnString = "document.{$form}.{$field}____I{$instance}.value";
			} else {
				$returnString = "(if(document.forms['{$form}'].elements['__chk__{$field}_RC_".DataEntry::replaceDotInCheckboxCoding($checkboxCode)."____I{$instance}'].value=='',0,1))";
			}
		} else {
			// Has no instance
			$repeat_instrument = "";
			$form = ($event_name == "") ? "form" : "form__".$event_name;			
			if ($checkboxCode == "") {
				$returnString = "document.{$form}.{$field}.value";
			} else {
				$returnString = "(if(document.forms['{$form}'].elements['__chk__{$field}_RC_".DataEntry::replaceDotInCheckboxCoding($checkboxCode)."'].value=='',0,1))";
			}
		}
		// For repeating instances, add to $repeatingFieldsEventInfo array so we can manually add these to the HTML form downstream
		if ($instance != "") {
			$repeatingFieldsEventInfo[$event_id][$repeat_instrument][$instance][$field] = true;
		}
		// Return the JS string
		return $returnString;
	}
	
	// Format a logic string into JS notation (including converting fields into JS notation)
	public static function formatLogicToJS($string, $doExtraCalcFormatting=false, $current_event_id=null, $returnFieldsUtilized=false, $project_id=null)
	{
		global $Proj;
		
		// REPLACE ANY STRINGS IN QUOTES WITH A PLACEHOLDER BEFORE DOING THE OTHER REPLACEMENTS:
		$lqp = new LogicQuoteProtector();
		$string = $lqp->sub($string);

		//Replace operators in equation with javascript equivalents (Strangely, the < character causes issues with str_replace later when it has no spaces around it, so add spaces around it)
		$orig = array("\t", "\r\n", "\n", "\r", "<"  , "=" , "===", "====", "> ==", "< ==", ">==", "<==", "< >", "<>", " and ", " AND ", " or ", " OR ", "!==");
		$repl = array(" ",  " ",    " ",  " ",  " < ", "==", "==" , "=="  , ">="  , "<="  , ">=" , "<="  , "!=" , "!=", " && " , " && ", " || ", " || ", "!=");
		$string = str_replace($orig, $repl, $string);
		
		// UNDO THE REPLACEMENT BEFORE EVALUATING THE EXPRESSION
		$string = $lqp->unsub($string);
			
		// For better parsing downstream, remove spaces around operators
		$string = preg_replace("/(\s*)(=|!=|\<\>|\>|\>=|\<|\<=)(\s*)/", "$2", $string);

		// Get list of field names used in string (Calc fields only)
		if ($doExtraCalcFormatting || $returnFieldsUtilized) {
			$fields_utilized = getBracketedFields($string, true, true, true);
		}

		// Replace field_name in brackets with javascript equivalent
		$string = preg_replace_callback('/(?:\[([a-z0-9][_a-z0-9]*)\])?\[([a-z][_.a-zA-Z0-9:\(\)-]*)\](\[(\d+)\])?/', "LogicTester::replaceInstanceCallback", $string);
		
		$valTypes = getValTypes();
		$eventNames = $Proj->getUniqueEventNames();

		$checkRepeatingInstances = ($Proj->hasRepeatingFormsEvents() && strpos($string, "____I"));

		// CALC FIELDS ONLY
		if ($doExtraCalcFormatting)
		{
			// Replace field names with javascript equivalent
			foreach (array_keys($fields_utilized) as $this_field)
			{
				// If field is NOT a Text field OR is a number/integer-validated Text field, then wrap the field with chkNull function to
				// ensure that we get either a numerical value or NaN.
				$fieldType = $Proj->metadata[$this_field]['element_type'];
				// Is this a MC field with all-numeric codings?
				$mcFieldWithNumCodings = ($Proj->isMultipleChoice($this_field) && isinteger(str_replace("-", "", implode("", array_keys(parseEnum($Proj->metadata[$this_field]['element_enum']))))));
				$fieldValidation = $Proj->metadata[$this_field]['element_validation_type'];
				if ($fieldValidation == 'float') $fieldValidation = 'number';
				if ($fieldValidation == 'int') $fieldValidation = 'integer';
				$fieldDataType = isset($valTypes[$fieldValidation]['data_type']) ? $valTypes[$fieldValidation]['data_type'] : '';
				if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
					$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file')
				{
					// Replace
					$string = str_replace("document.form.$this_field.value", "chkNull(document.form.$this_field.value)", $string);
					// Replace fields on repeating forms/events
					if ($checkRepeatingInstances) {
						$string = preg_replace("/(document\.form\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
					}
					// Also, if longitudinal, loop through all events where this field's form is utilized and replace in that format
					$fieldForm = $Proj->metadata[$this_field]['form_name'];
					if ($Proj->longitudinal) {
						foreach ($eventNames as $this_event_id=>$this_event_name) {
							// Skip if field not used on this event
							if (!in_array($fieldForm, $Proj->eventsForms[$this_event_id])) continue;
							// Replace event format
							$string = str_replace("document.form__$this_event_name.$this_field.value", "chkNull(document.form__$this_event_name.$this_field.value)", $string);
							// Replace fields on repeating forms/events
							if ($checkRepeatingInstances) {
								$string = preg_replace("/(document\.form__{$this_event_name}\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
							}
						}
					}
				}
			}
			
			// While chkNull returns "NaN", if we have [date]="" or [date]<>"", it ends up as ""=="NaN" and ""!="NaN" when the field 
			// is blank, which is incorrect. So we need to make sure that all date fields being checked as blank/non-blank are
			// referencing "" and not "NaN", which is replaced by REDCap automatically upstream.
			foreach (array('"', "'") as $quote) {
				$string = str_replace(array(".value=={$quote}NaN{$quote}", ".value!={$quote}NaN{$quote}", "{$quote}NaN{$quote}==document.", "{$quote}NaN{$quote}!=document."),
									  array(".value=={$quote}{$quote}", ".value!={$quote}{$quote}", "{$quote}{$quote}==document.", "{$quote}{$quote}!=document."),
									  $string);
			}

			// Now swap all "+" with "*1+1*" in the equation to work around possibility of JavaScript concatenation in some cases
			if (strpos($string, "+") !== false) $string = str_replace("+", "*1+1*", $string);
		}
		// BRANCHING LOGIC ONLY
		else
		{
			// Wrap integer/number data type fields with chkNull() to allow proper processing of them as numbers
			// Replace field names with javascript equivalent
			foreach (array_keys($fields_utilized) as $this_field)
			{
				$fieldType = $Proj->metadata[$this_field]['element_type'];
				$fieldValidation = $Proj->metadata[$this_field]['element_validation_type'];
				if ($fieldValidation == 'float') $fieldValidation = 'number';
				if ($fieldValidation == 'int') $fieldValidation = 'integer';
				$fieldDataType = isset($valTypes[$fieldValidation]['data_type']) ? $valTypes[$fieldValidation]['data_type'] : '';
				if ($fieldType == 'calc' || $fieldType == 'slider' || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer')
				{
					// Make sure that any fields being referenced as ="" or <>"" to replace "" with "NaN" since chkNull() will return "NaN" when the field is blank
					foreach (array('"', "'") as $quote) {
						$string = str_replace(array("document.form.$this_field.value=={$quote}{$quote}",    "document.form.$this_field.value!={$quote}{$quote}",    "{$quote}{$quote}==document.form.$this_field.value",    "{$quote}{$quote}!=document.form.$this_field.value"),
											  array("document.form.$this_field.value=={$quote}NaN{$quote}", "document.form.$this_field.value!={$quote}NaN{$quote}", "{$quote}NaN{$quote}==document.form.$this_field.value", "{$quote}NaN{$quote}!=document.form.$this_field.value"),
											  $string);
					}
					// Replace
					$string = str_replace("document.form.$this_field.value", "chkNull(document.form.$this_field.value,1)", $string);
					// Replace fields on repeating forms/events
					if ($checkRepeatingInstances) {
						$string = preg_replace("/(document\.form\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
					}
					// Also, if longitudinal, loop through all events where this field's form is utilized and replace in that format
					if ($Proj->longitudinal) {
						$fieldForm = $Proj->metadata[$this_field]['form_name'];
						foreach ($eventNames as $this_event_id=>$this_event_name) {
							// Skip if field not used on this event
							if (!in_array($fieldForm, $Proj->eventsForms[$this_event_id])) continue;
							// Make sure that any fields being referenced as ="" or <>"" to replace "" with "NaN" since chkNull() will return "NaN" when the field is blank
							foreach (array('"', "'") as $quote) {
								$string = str_replace(array("document.form__$this_event_name.$this_field.value=={$quote}{$quote}",    "document.form__$this_event_name.$this_field.value!={$quote}{$quote}",    "{$quote}{$quote}==document.form__$this_event_name.$this_field.value",    "{$quote}{$quote}!=document.form__$this_event_name.$this_field.value"),
													  array("document.form__$this_event_name.$this_field.value=={$quote}NaN{$quote}", "document.form__$this_event_name.$this_field.value!={$quote}NaN{$quote}", "{$quote}NaN{$quote}==document.form__$this_event_name.$this_field.value", "{$quote}NaN{$quote}!=document.form__$this_event_name.$this_field.value"),
													  $string);
							}
							// Replace event format
							$string = str_replace("document.form__$this_event_name.$this_field.value", "chkNull(document.form__$this_event_name.$this_field.value,1)", $string);
							// Replace fields on repeating forms/events
							if ($checkRepeatingInstances) {
								$string = preg_replace("/(document\.form__{$this_event_name}\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
							}
						}
					}
				}
			}
		}

		// Replace ^ exponential form with javascript equivalent
		$string = self::replaceExponents($string);

		// Temporarily swap out commas in any datediff() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceDatediff($string);

		// Temporarily swap out commas in any round() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceRound($string);

		// If using conditional logic, format any conditional logic to Javascript ternary operator standards
		$string = self::convertIfStatement($string);

		// Auto-insert the datediff() date format parameter (if not present)
		if ($project_id != null && strpos($string, "-DDC-") !== false)
		{
			$Proj = new Project($project_id);
			$datediff_array = explode("datediff(", $string);
			foreach ($datediff_array as $key=>$item) {
				if (strpos($item, "-DDC-") !== false) {
					$datediff_array[$key] = $item = array_map('ltrim', explode("-DDC-", $item));
					// If the fourth parameter is missing, then determine the field's date format and add it as the fourth parameter IF it is dmy or mdy
					if (!isset($item[3]) && isset($item[2]))
					{
						$closingParen = strpos($item[2], ")");
						if ($closingParen !== false && (strpos($item[0], "document.") !== false || strpos($item[1], "document.") !== false)) {
							$datediff_field_param = (strpos($item[0], "document.") !== false) ? $item[0] : $item[1];
							$datediff_field_array = explode(".", $datediff_field_param, 4);
							$current_field = $datediff_field_array[2];
							if (isset($Proj->metadata[$current_field])) {
								$datediff_field_format = substr($Proj->metadata[$current_field]['element_validation_type'], -3);
								if ($datediff_field_format == 'mdy' || $datediff_field_format == 'dmy') {
									$item[2] = substr($item[2], 0, $closingParen) . "-DDC-'$datediff_field_format'" . substr($item[2], $closingParen);
								}
							}
						}
					}
					// If the fourth parameter is false or true
					elseif (stripos($item[3], 'false') === 0 || stripos($item[3], '"false"') === 0 || stripos($item[3], "'false'") === 0
						 || stripos($item[3], 'true') === 0 || stripos($item[3], '"true"') === 0 || stripos($item[3], "'true'") === 0)
					{
						$datediff_field_param = (strpos($item[0], "document.") !== false) ? $item[0] : $item[1];
						$datediff_field_array = explode(".", $datediff_field_param, 4);
						$current_field = $datediff_field_array[2];
						if (isset($Proj->metadata[$current_field])) {
							$datediff_field_format = substr($Proj->metadata[$current_field]['element_validation_type'], -3);
							if ($datediff_field_format == 'mdy' || $datediff_field_format == 'dmy') {
								$item[3] = "'$datediff_field_format'-DDC-" . $item[3];
							}
						}
					}
					$datediff_array[$key] = $item = implode("-DDC-", $item);
				}
			}
			$string = implode("datediff(", $datediff_array);
		}

		// Now swap datediff() commas back into the equation (was replaced with -DDC-)
		if (strpos($string, "-DDC-") !== false) $string = str_replace("-DDC-", ",", $string);

		// Now swap round() commas back into the equation (was replaced with -ROC-)
		if (strpos($string, "-ROC-") !== false) $string = str_replace(array("-ROC-)","-ROC-"), array(")",","), $string);

		// Now swap sqrt() or exponential commas back into the equation (was replaced with -DDC-)
		if (strpos($string, "-EXPC-") !== false) $string = str_replace("-EXPC-", ",", $string);

		// Replace isblankormissingcode(chkNull([field])) with isblankormissingcode(([field]))
		$string = preg_replace("/(isblankormissingcode)(\()(\s*)(chkNull)(\s*)(\()/", "$1$2$6", $string);

		// Return array of formatted string and fields utilized in the branching logic
		if ($returnFieldsUtilized) {
			// Return array
			return array($string, array_keys($fields_utilized));
		} 
		// Return formatted string
		else {
			return $string;
		}
	}

}