<?php


/**
 * DATA QUALITY
 */
class DataQuality
{
	// Set max amount of results that can be returned when executing a rule (for both server memory and browser memory issues)
	public $resultLimit = 10000;
	// Array with the Data Quality rules defined by the user
	private $rules = null;
	// Results from running the logic from the rules
	public $logicCheckResults = array();
	// Array of discrepancy count for individual DAGs
	public $dag_discrepancies = array();
	// Array of status labels
	private $status_labels = array();
	// Array of default status value for pre-defined rules and user-defined rules
	private $default_status = array();
	// Array of pre-defined rules
	private	$predefined_rules = array();
	// Array to store association of records with DAGs
	private $dag_records = array();
	// Keys are function names, values are argument mappings. @see LogicParser->parse()\
	private $logicFuncToArgs = array();
	// Keys are function names, values are strings of PHP code that compose the function body.\
	private $logicFuncToCode = array();
	// Array with the Data Quality data issues
	private $dataIssues = null;
	// Default data resolution status
	private $defaultDataResStatus = '';
	// Array of valid data resolution statuses
	private $validDataResStatuses = array('', 'OPEN', 'CLOSED', 'OPEN_RESPONDED', 'OPEN_UNRESPONDED', 'VERIFIED', 'DEVERIFIED');
	// Set max length of field label to display in table
	private $maxFieldLabelLen = 60;
	// Set array of keywords to ignore for the Field Comment Log keyword search
	public $FCL_keywords_ignore = array('or', 'and', 'the');
	// Set max amount of results to dipslay for the Field Comment Log
	private $FCL_max_results = 100;
	// Array of DRW metrics charts (chart_name & chart display label)
	public $drw_metrics_charts = array();
	// Count number of values fixed (if we are fixing values and not just displaying discrepancies)
	public $valuesFixed = 0;
	// Keep track of record-event-instances that are processed by an exectued rule
	private $alreadyProcessed = array();
	private $logicCheckResultsKey = array();
	// If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
	private $logicContainsSmartVariables = false;

    // Construct
	public function __construct()
	{
		
		global $lang;
		// Define DRW metrics charts
		$this->drw_metrics_charts = array(
			'num_open_queries_by_dag' => array('dag_related'=>1, 'label'=>$lang['dataqueries_248']),
			'num_closed_queries_by_dag' => array('dag_related'=>1, 'label'=>$lang['dataqueries_252']),
			'resolution_time_by_dag' => array('dag_related'=>1, 'label'=>$lang['dataqueries_249']),
			'response_time_by_dag' => array('dag_related'=>1, 'label'=>$lang['dataqueries_254']),
			'response_time_by_user' => array('dag_related'=>0, 'label'=>$lang['dataqueries_253']),
			'top_fields' => array('dag_related'=>0, 'label'=>$lang['dataqueries_239']),
			'top_records' => array('dag_related'=>0, 'label'=>$lang['dataqueries_241']),
			'top_rules' => array('dag_related'=>0, 'label'=>$lang['dataqueries_240'])
		);
		// Define status labels
		$this->status_labels = array(
			0 => $lang['dataqueries_51'],
			1 => $lang['dataqueries_52'],
			2 => $lang['dataqueries_53'],
			3 => $lang['dataqueries_54'],
			4 => $lang['dataqueries_55'],
			5 => $lang['dataqueries_56'],
			6 => $lang['dataqueries_57'],
			7 => $lang['dataqueries_58'],
			8 => $lang['dataqueries_59'],
			9 => $lang['dataqueries_60'],
			10=> $lang['dataqueries_61'],
			11=> $lang['dataqueries_150']
		);
		// Define default status for rules
		$this->default_status = array(
			'num'  => 0, // This is an umbrella for all user-defined rules
			'pd-1' => 2,
			'pd-2' => 2,
			'pd-3' => 2,
			'pd-4' => 3,
			'pd-5' => 4,
			'pd-6' => 2,
			'pd-7' => 6,
			'pd-8' => 6,
			'pd-9' => 7,
			'pd-10'=> 11,
			'pd-11'=> 0
		);
		// Define pre-defined rules (will be named pd-#)
		$this->predefined_rules = array(
			// 1 => 'Any missing values - branching logic ignored',
			// 2 => 'Any missing values (required fields only) - branching logic ignored',
			3 => $lang['dataqueries_62'].'*',
			6 => $lang['dataqueries_62'].'* '.$lang['dataqueries_63'],
			4 => $lang['dataqueries_64'].' '.$lang['dataqueries_65'],
			9 => $lang['dataqueries_64'].' '.$lang['dataqueries_66'],
			5 => $lang['dataqueries_67'].'<br>'.$lang['dataqueries_68'].'**',
			7 => $lang['dataqueries_69'].'***',
			8 => $lang['dataqueries_70'],
			10 =>$lang['dataqueries_149'],
			11 =>$lang['dataqueries_305']
		);
    }

	// Return array of response choices (to populate drop-down) when responding to a data query.
	private static function getDataResolutionResponseChoices($type=null)
	{
		global $lang;
		// Establish array of choices
		$choices = array(
			'DATA_MISSING' => $lang['dataqueries_161'],
			'TYPOGRAPHICAL_ERROR' => $lang['dataqueries_162'],
			'WRONG_SOURCE' => $lang['dataqueries_163'],
			'CONFIRMED_CORRECT' => $lang['dataqueries_164'],
			'OTHER' => $lang['create_project_19']
		);
		// If a $type was provided and is valid, return only its label (return string)
		if ($type != null) {
			return (isset($choices[$type]) ? $choices[$type] : '');
		}
		// Return whole array
		else {
			return $choices;
		}
	}

	// Convert a number to a character (1=A, 2=B, etc.)
	private function numtochars($num,$start=65,$end=90)
	{
		$sig = ($num < 0);
		$num = abs($num);
		$str = "";
		$cache = ($end-$start);
		while($num != 0)
		{
			$str = chr(($num%$cache)+$start-1).$str;
			$num = ($num-($num%$cache))/$cache;
		}
		if($sig)
		{
			$str = "-".$str;
		}
		return $str;
	}

	// Load rules defined. If provide rule_id, then only return that rule.
	public function loadRules($rule_id=null)
	{
		$counterPdRule = 1;
		## First, load the pre-defined rules
		foreach ($this->predefined_rules as $pd_rule_id=>$name)
		{
			$this->rules['pd-'.$pd_rule_id] = array(
				'name' => "<span class='pd-rule'>$name</span>",
				'logic' => "<span class='pd-rule'>&nbsp;-</span>",
				'order' => $this->numtochars($counterPdRule++)
			);
		}

		## Now, load the user-defined rules
		// If rule_id is defined, then add it to sql
		$sql_rule_id = (is_numeric($rule_id) ? "and rule_id = $rule_id" : "");
		// Query to get rules from table
		$sql = "select * from redcap_data_quality_rules where project_id = " . PROJECT_ID
			 . " $sql_rule_id order by rule_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add rule to array
			$this->rules[$row['rule_id']] = array(
				'name'  => filter_tags($row['rule_name']),
				'logic' => label_decode($row['rule_logic'], false),
				'real_time_execute' => $row['real_time_execute'],
				'order' => $row['rule_order']
			);
			// Get array of fields used in this rule
			$fieldsInLogic = array_keys(getBracketedFields($row['rule_logic'], true, true, true));
			// Determine field if logic contains only one field
			if (count($fieldsInLogic) == 1) {
				// Get field and add to array
				$this->rules[$row['rule_id']]['contains_one_field'] = array_pop($fieldsInLogic);
			} else {
				$this->rules[$row['rule_id']]['contains_one_field'] = null;
			}
		}
		// Do a quick check to make sure the rule are in the right order (if not, will fix it)
		if ($rule_id == null) $this->checkOrder();
	}

	// Retrieve all rules defined. Return as array.
	public function getRules()
	{
		// Load the rules
		if ($this->rules == null) $this->loadRules();
		// Return the rules
		return $this->rules;
	}

	// Retrieve a single rule defined. Return as array.
	public function getRule($rule_id)
	{
		// Load the rule
		if ($this->rules == null) $this->loadRules($rule_id);
		// Return the rule
		return (isset($this->rules[$rule_id]) ? $this->rules[$rule_id] : false);
	}

	// Execute a single PRE-DEFINED rule
	private function executePredefinedRule($rule_id, $rule_record=null, $dag_discrep)
	{
		global $Proj, $table_pk, $user_rights, $lang, $longitudinal, $data_resolution_enabled, $missingDataCodes;

		// Get the rule and its attributes
		$rule_attr = $this->getRule($rule_id);
		
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();

		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		$eventNameToId = array_flip($events);

		// EXCLUDED: Get a list of any record-event-field's for this rule that have been excluded (so we know what to exclude)
		$excluded = array();
		$sql = "select record, event_id, field_name, instance from redcap_data_quality_status
				where pd_rule_id = " . substr($rule_id, 3) . " and project_id = " . PROJECT_ID;
		if ($rule_record != '') $sql .= " and record = '".db_escape($rule_record)."'";
		// Append to query based on if data query feature is enabled (used old Exclude or new Data Query resolution)
		$sql .= ($data_resolution_enabled == '2') ? " and query_status in ('CLOSED', 'VERIFIED')" : " and exclude = 1";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Repeating forms/events
			$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
			$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $Proj->metadata[$row['field_name']]['form_name']));
			$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
			$repeat_instrument = $isRepeatForm ? $Proj->metadata[$row['field_name']]['form_name'] : "";
			$instance = $isRepeatEventOrForm ? $row['instance'] : 0;
			// Add to excluded array
			$excluded[$row['record']][$row['event_id']][$repeat_instrument][$instance][$row['field_name']] = true;
		}

		// Get deliminted list of all available events for use in queries (this is done to ignore orphaned data)
		$eventIdsSql = implode(", ", array_keys($Proj->eventInfo));

		// Which pre-defined rule are we running?
		switch ($rule_id)
		{
			// Rule: All missing values
			case 'pd-1':
			// Rule: Missing values (required fields only)
			case 'pd-2':
			// Rule: Missing values (excluding fields hidden by branching logic)
			case 'pd-3':
			// Rule: Missing values (required fields only - excluding fields hidden by branching logic)
			case 'pd-6':
				// First create a fieldname array with blanks as values (default) - exclude PK, Form Status fields, desciptive text, and checkboxes
				$fields = array();
			    $containsSpecialTags = array();
				foreach ($Proj->metadata as $field=>$attr)
				{
					if ($field != $table_pk // && $field != $attr['form_name'] . "_complete"
						// && $attr['element_type'] != 'checkbox' 
						&& $attr['element_type'] != 'descriptive')
					{
						// For pre-defined rule pd-2/pd-6, only add Required Fields
						if ((($rule_id == 'pd-2' || $rule_id == 'pd-6') && $attr['field_req']) || $rule_id == 'pd-1' || $rule_id == 'pd-3') {
							$fields[$field] = '';
						}
					}
				}
				
				// Get data for records
				$field_data_missing = Records::getData('array', ($rule_record == '' ? array() : $rule_record), 
										array_merge(array($table_pk), array_keys($fields)), array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				// Remove all non-applicable fields for events and repeating forms, then remove non-blank values
				Records::removeNonApplicableFieldsFromDataArray($field_data_missing, $Proj, true);
				
				// For pd-3/pd-6 only, retrieve ALL data for fields using in branching logic fields
				if ($rule_id == 'pd-3' || $rule_id == 'pd-6')
				{
					// Store field names and data related to branching in arrays
					$branching_fields = array();
					$branching_fields_utilized = array($Proj->table_pk=>true);
					$branching_fields_ignore = array();
					$branching_data = array();
					// Loop through metadata and get all fields that have branching logic
					foreach ($Proj->metadata as $field=>$attr)
					{
						$this_branching = trim($attr['branching_logic']);
						if ($this_branching != "")
						{
							if (Piping::containsSpecialTags($this_branching)) {
								$containsSpecialTags[$field] = true;
							}
							$parser = new LogicParser();
							// If there is an issue in the logic, then we cannot use it, so skip it
							try {
								// obtain the branching logic in a usable format
								$this_branching = html_entity_decode($this_branching, ENT_QUOTES);
								list($funcName, $argMap) = $parser->parse($this_branching, $eventNameToId, true, false, false, false, true);
								$this->logicFuncToArgs[$funcName] = $argMap;
								//$this->logicFuncToCode[$funcName] = $parser->generatedCode;
								$branching_fields[$field] = $funcName;
								// Obtain the fields utilized in each field's branching logic
								foreach ($argMap as $argData) {
									$branching_fields_utilized[$argData[1]] = true;
								}
							}
							catch (LogicException $e) {
								// Add field to ignore array because it's logic is not usable
								$branching_fields_ignore[$field] = true;
							}
						}
					}
					// Now query all records have missing values for fields used in all branching logic fields
					$getDataParams = array('project_id'=>PROJECT_ID, 'records'=>($rule_record != '' ? $rule_record : array_keys($field_data_missing)), 'fields'=>array_keys($branching_fields_utilized));
					$branching_data = Records::getData($getDataParams);
				}
				// Set last_record value to be set at end of each loop
				$last_record = "";
				// Now we have an array with all missing values for all records-events, so loop through it and add to results
				foreach ($field_data_missing as $record=>$event_data)
				{
					// If we're beginning a new record, then remove the last record from arrays to conserve memory
					if ($last_record !== "" && $last_record !== $record) {
						unset($field_data_missing[$last_record], $branching_data[$last_record]);
					}
					// print round(memory_get_usage()/1024/1024,2) . " MB (record $record)\n";
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// Set default flag
										$addDiscrep = true;
										// If rule pd-3/pd-6, then check branching logic to see if we should ignore this
										if (($rule_id == 'pd-3' || $rule_id == 'pd-6')
											&& isset($branching_fields[$field]) && !isset($branching_fields_ignore[$field]))
										{
											// Parse differently if logic contains smart variables
											if ($containsSpecialTags[$field]) {
												$this_branching = $Proj->metadata[$field]['branching_logic'];
												if ($Proj->longitudinal) {
													$this_branching = LogicTester::logicPrependEventName($this_branching, $Proj->getUniqueEventNames($event_id), $Proj);
												}
												$this_branching = Piping::pipeSpecialTags($this_branching, $Proj->project_id, $record, $event_id, $instance, null, true, null, $Proj->metadata[$field]['form_name'], false);
												if ($Proj->hasRepeatingFormsEvents()) {
													$this_branching = LogicTester::logicAppendInstance($this_branching, $Proj, $event_id, $Proj->metadata[$field]['form_name'], $instance);
												}
												$isHidden = !LogicTester::apply($this_branching, $branching_data[$record], $Proj, false, true);
											} else {
												$useArgs = array();
												//$branching_record_data = Records::moveRepeatingDataToBaseInstance($branching_data[$record], $event_id, $repeat_instrument, $instance, $Proj);
												$argsValid = $this->buildRuleLogicArgs($branching_fields[$field], $branching_data[$record], $event_id, $useArgs, true, $Proj);
												$logicResult = $argsValid ?
													$this->applyRuleLogic($branching_fields[$field],
														$branching_data[$record], $event_id, $rule_attr, $args,
														$useArgs, $Proj) :
													null;
												$isHidden = !$argsValid || $logicResult === 1;
											}
											// don't execute the branching logic if we don't have values
											// for all the parameters; don't count as a discrepancy
											if (!$argsValid || $isHidden) {
												$addDiscrep = false;
											}
											/*
											$branching_record_data = Records::moveRepeatingDataToBaseInstance($branching_data[$record], $event_id, $repeat_instrument, $instance, $Proj);
											$useArgs = array();
											$argsValid = $this->buildRuleLogicArgs($branching_fields[$field], $branching_record_data, $event_id, $useArgs, true, $Proj);
											// don't execute the branching logic if we don't have values
											// for all the parameters; don't count as a discrepancy
											if (!$argsValid) {
												$addDiscrep = false;
											}
											// if executing the branching logic resulted in a false return
											// (indicated by a 1), then don't add a discrepancy
											else
											{
												if ($this->applyRuleLogic($branching_fields[$field], $branching_record_data, $event_id, $rule_attr, $args, $useArgs, $Proj) === 1)
												{
													$addDiscrep = false;
												}
											}
											unset($branching_record_data);
											*/
										}
										// If we're set to ignore this field (because we can't use it's logic), then ignore it
										elseif (isset($branching_fields_ignore[$field]))
										{
											$addDiscrep = false;
										}
										// For longitudinal projects, make sure that this field's form has been designated for an event and is not orphaned.
										if ($longitudinal && !in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$event_id]))
										{
											$addDiscrep = false;
										}
										// Add discrepancy
										if ($addDiscrep)
										{
											// Set the $value variable as HTML link to data entry page
											$value_html = "<a style='color:#888;' target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
															. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
															. "&fldfocus=$field#$field-tr'>{$lang['dataqueries_71']}</a>";
											// Is this record-event excluded for this rule?
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, $rule_attr['logic'], isset($literalLogic) ? $literalLogic : '', $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html", $excludeRecEvt, 0, $field, $instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
					// Set for next loop
					$last_record = $record;
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Field validation errors
			case 'pd-4':
				// Get array of all available validation types
				$valTypes = getValTypes();
				// Add legacy values and back-end values to valTypes (since back-end values are different for date, int, float, etc)
				$valTypes['date'] = $valTypes['date_ymd'];
				$valTypes['datetime'] = $valTypes['datetime_ymd'];
				$valTypes['datetime_seconds'] = $valTypes['datetime_seconds_ymd'];
				$valTypes['int'] = $valTypes['integer'];
				$valTypes['float'] = $valTypes['number'];
				unset($valTypes['integer']);
				unset($valTypes['number']);
				// For MDY and DMY formats, give them the YMD regex since we're parsing the raw data, which will ALWAYS be in YMD format
				$valTypes['date_mdy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
				$valTypes['date_dmy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
				$valTypes['datetime_mdy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
				$valTypes['datetime_dmy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
				$valTypes['datetime_seconds_mdy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
				$valTypes['datetime_seconds_dmy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
				// Set array holding just validation types
				$valTypesList = array_keys($valTypes);
				// Build array of fields that have validation
				$valFields = array();
				foreach ($Proj->metadata as $field=>$attr)
				{
					// Only looking for text fields (also include calc fields and sliders and treat them as number/integer-validated text fields)
					if ($attr['element_type'] == 'slider' || $attr['element_type'] == 'calc' || ($attr['element_type'] == 'text' && in_array($attr['element_validation_type'], $valTypesList)))
					{
						$valFields[] = $field;
					}
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, $valFields);								
				// Get data for records
				if (!empty($valFields)) {
					$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), $valFields, array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				} else {
					$data = array();
				}
				// Loop through all values
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// Ignore non-relevant fields/values
										if ($value == '' || isset($missingDataCodes[$value])) continue;
										// Get the validation type of the field for this data point (also include calc fields and sliders and treat them as number-validated text fields)
										if ($Proj->metadata[$field]['element_type'] == 'text') {
											$valType = $Proj->metadata[$field]['element_validation_type'];
										} elseif ($Proj->metadata[$field]['element_type'] == 'calc') {
											$valType = 'float';
										} elseif ($Proj->metadata[$field]['element_type'] == 'slider') {
											$valType = 'int';
										}
										## Use RegEx to evaluate the value based upon validation type
										// Set regex pattern to use for this field
										$regex_pattern = $valTypes[$valType]['regex_php'];
										// Run the value through the regex pattern
										preg_match($regex_pattern, $value, $regex_matches);
										// Was it validated? (If so, will have a value in 0 key in array returned.)
										$failed_regex = (!isset($regex_matches[0]));
										// Set error message if failed regex
										if ($failed_regex)
										{
											// If a DMY or MDY date, then convert value to that format for display
											$value = $this->convertDateFormat($field, $value);
											// Set the $value variable as HTML link to data entry page
											if (in_array($field, $fieldsNoAccess)) {
												$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
											} else {
												// Set the $value variable as HTML link to data entry page
												$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
															. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
															. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
											}
											// Is this record-event excluded for this rule?
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, $rule_attr['logic'], $literalLogic, $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html", $excludeRecEvt, 0, $field, $instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
					unset($data[$record]);
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Outliers for numerical fields
			case 'pd-5':
				// First create a fieldname array for just numerical fields (int, float, calc, slider)
				$numericalFields = array();
				foreach ($Proj->metadata as $field=>$attr)
				{
					if ($attr['element_type'] == 'calc' || $attr['element_type'] == 'slider' ||
						($attr['element_type'] == 'text' && ($attr['element_validation_type'] == 'int' || $attr['element_validation_type'] == 'float')))
					{
						$numericalFields[] = $field;
					}
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, $numericalFields);								
				// Get data for records
				$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), $numericalFields, array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				// Loop through all values
				$fieldData = $recordData = $stdevs = $medians = array();
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// If one of our number fields does not have a numerical value, then skip it. (HOW DOES THIS AFFECT MISSING THOUGH???)
										if (!is_numeric($value) || isset($missingDataCodes[$value])) {
										    if (isset($data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$field])) {
												unset($data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$field]);
                                            } elseif (isset($data[$record][$event_id][$field])) {
												unset($data[$record][$event_id][$field]);
											}
										} else {
											// Add value to  field data array
											$fieldData[$field][] = $value;
										}
									}
								}
							}
						}
					}
				}

				// Now that we have all data, loop through it and determine missing value count and stats				
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// If we only have 1 record with data for this field, then skip it (cannnot properly perform stdev)
										if ($value."" === '' || count($fieldData[$field]) <= 1) continue;
										// Setup up math constraints for this field
										if (!isset($stdevs[$field])) {
											$stdev = $stdevs[$field] = stdev($fieldData[$field]);
										} else {
											$stdev = $stdevs[$field];
										}
										// Make sure the stdev is not 0 (not useful if so)
										if ($stdev == 0) continue;
										if (!isset($medians[$field])) {
											$median = $medians[$field] = median($fieldData[$field]);
										} else {
											$median = $medians[$field];
										}
										$two_stdev_upper = $median + ($stdev * 2);
										$two_stdev_lower = $median - ($stdev * 2);
										// Is it an outlier?
										if ($value <= $two_stdev_lower || $value >= $two_stdev_upper)
										{
											$stdev_display = User::number_format_user($stdev, 2);
											// Set the $value variable as HTML link to data entry page
											if (in_array($field, $fieldsNoAccess)) {
												$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
											} else {
												// Set the $value variable as HTML link to data entry page
												$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
															. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
															. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
											}
											$data_display = $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html<br><span style='color:gray;'>({$lang['dataqueries_74']} {$median}{$lang['dataqueries_75']} {$stdev_display})</span>";
											// Is this record-event excluded for this rule?
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, '', '', $data_display, $excludeRecEvt, 0, $field, $instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
					unset($data[$record]);
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Hidden fields that contain values
			case 'pd-7':
				// Store field names and data related to branching in arrays
				$branching_fields = array();
				$branching_fields_utilized = array();
				$branching_fields_ignore = array();
				$branching_data = array();
				$containsSpecialTags = array();
				// Loop through metadata and get all fields that have branching logic
				foreach ($Proj->metadata as $field=>$attr)
				{
					$this_branching = trim($attr['branching_logic']);
					if ($this_branching != "")
					{
						// If there is an issue in the logic, then we cannot use it, so skip it
						try {
							// obtain the branching logic in a usable format
							$this_branching = html_entity_decode($this_branching, ENT_QUOTES);
							// For fastest processing, preformat the logic by prepending [evnet-name] and/or appending [current-instance] where appropriate
							$this_branching = LogicTester::preformatLogicEventInstanceSmartVariables($this_branching, $Proj);
							if (Piping::containsSpecialTags($this_branching)) {
                                $containsSpecialTags[$field] = true;
								$branching_fields[$field] = "";
								$this_fields_utilized = array_keys(getBracketedFields($this_branching, true, true, true));
								foreach ($this_fields_utilized as $this_field) {
									$branching_fields_utilized[$this_field] = true;
                                }
                            } else {
								$parser = new LogicParser();
								list($funcName, $argMap) = $parser->parse($this_branching, $eventNameToId, true, false, false, false, true);
								$this->logicFuncToArgs[$funcName] = $argMap;
								//$this->logicFuncToCode[$funcName] = $parser->generatedCode;
								$branching_fields[$field] = $funcName;
								// Obtain the fields utilized in each field's branching logic
								foreach ($argMap as $argData) {
									$branching_fields_utilized[$argData[1]] = true;
								}
							}
						}
						catch (LogicException $e) {
							// Add field to ignore array because it's logic is not usable
							$branching_fields_ignore[$field] = true;
						}
					}
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, array_keys($branching_fields));
				// Get data for records
				$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), array_keys($branching_fields), array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true, true, null, 0, false, ",", '', false, 0, array(), true);
				// Now query all records for fields used in all branching logic fields (trying to minimize the data returned here).
				$branching_data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), array_merge(array_keys($branching_fields_utilized), array($Proj->table_pk)), array(), $user_rights['group_id']);
				// Now we have an array with all values for all records-events for all branching fields, so loop through it and add to results
                foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										if ($value == '' && !is_array($value)) continue;
										// Check branching logic to see if we should ignore this
										if (isset($branching_fields[$field]) && !isset($branching_fields_ignore[$field]))
										{
										    // Parse differently if logic contains smart variables
                                            if ($containsSpecialTags[$field]) {
                                                $this_branching = $Proj->metadata[$field]['branching_logic'];
                                                if ($Proj->longitudinal) {
                                                    $this_branching = LogicTester::logicPrependEventName($this_branching, $Proj->getUniqueEventNames($event_id), $Proj);
                                                }
                                                $this_branching = Piping::pipeSpecialTags($this_branching, $Proj->project_id, $record, $event_id, $instance, null, true, null, $Proj->metadata[$field]['form_name'], false);
                                                if ($Proj->hasRepeatingFormsEvents()) {
                                                    $this_branching = LogicTester::logicAppendInstance($this_branching, $Proj, $event_id, $Proj->metadata[$field]['form_name'], $instance);
                                                }
                                                $isHidden = !LogicTester::apply($this_branching, $branching_data[$record], $Proj, false, true);
                                            } else {
                                                $useArgs = array();
                                                $argsValid = $this->buildRuleLogicArgs($branching_fields[$field], $branching_data[$record], $event_id, $useArgs, true, $Proj);
                                                $logicResult = $argsValid ?
                                                    $this->applyRuleLogic($branching_fields[$field],
														$branching_data[$record], $event_id, $rule_attr, $args,
                                                        $useArgs, $Proj) :
                                                    null;
                                                $isHidden = !$argsValid || $logicResult === 1;
                                            }
											// If field is hidden BUT contains value, then place in results as a discrepancy
											if ($isHidden)
											{
												// If a DMY or MDY date, then convert value to that format for display
												$value = $this->convertDateFormat($field, $value);
												// Set the $value variable as HTML link to data entry page
												if (in_array($field, $fieldsNoAccess)) {
													$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																	<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
												} else {
													// Convert checkbox values into comma-delimited string of only those that are checked
													if (is_array($value)) {
														// If all checkbox values are blank (not designated), then skip
														if (implode("", $value) == "") {
															continue;
														}
														$value_chk = array();
														foreach ($value as $key2c=>$value2c) {
															if ($value2c == '0') continue;
															$value_chk[] = $key2c;
														}
														$value = implode(",", $value_chk);
														if ($value == '') continue;
													}
													// Set the $value variable as HTML link to data entry page
													$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
																. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
																. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
												}
												// Is this record-event excluded for this rule?
												$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
												// Save result
												$this->saveLogicCheckResults($rule_id, $record, $event_id, '', '', $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html", $excludeRecEvt, 0, $field, $instance);
												// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
												if (isset($this->dag_records[$record]))
												{
													$group_id = $this->dag_records[$record];
													if (!$excludeRecEvt) $dag_discrep[$group_id]++;
												}
											}
										}
									}
								}
							}
						}
					}
					// Remove each record to free up memory
                    unset($data[$record], $branching_data[$record]);
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Multiple choice fields with invalid values
			case 'pd-8':
				// First create a fieldname array containing vars of all multiple choice fields with fieldname as key and their options as element
				$mc_fields = $checkbox_fields = $smartVarSqlFields = array();
				$mc_fieldtypes = array('radio', 'select', 'advcheckbox', 'checkbox', 'yesno', 'truefalse', 'sql');
				foreach ($Proj->metadata as $field=>$attr)
				{
					// Only get MC fields
					if (in_array($attr['element_type'], $mc_fieldtypes))
					{
						// Convert sql field types' query result to an enum format
						if ($attr['element_type'] == "sql")
						{
							// If this is a SQL field containing Smart Variables, then we need to re-evaluate for EVERY field. Put in an array for later reference.
						    if (Piping::containsSpecialTags($attr['element_enum'])) {
								$smartVarSqlFields[$field] = $attr['element_enum'];
                            }
						    // Get the traditional enum format from the SQL query
							$attr['element_enum'] = getSqlFieldEnum($attr['element_enum']);
						} elseif ($attr['element_type'] == "checkbox") {
							$checkbox_fields[] = $field;
						}
						// Add field and it's MC options to array
						$mc_fields[$field] = parseEnum($attr['element_enum']);
					}
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, array_keys($mc_fields));
				// ALL DATA: Get data for records
				$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), array_keys($mc_fields), array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				// CHECKBOX DATA: Get raw data just for checkboxes (because getData will not pull invalid values)
				$checkbox_data = array();
				if (!empty($checkbox_fields)) 
				{
					$group_sql = "";
					if ($user_rights['group_id'] != "") {
						$group_sql  = "and record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])).")";
					}
					// Create array of all records-events in the data table with their value
					$sql = "select record, event_id, field_name, value, instance from redcap_data where project_id = " . PROJECT_ID . "
							and record != '' $group_sql and field_name in ('" . implode("', '", $checkbox_fields) . "')
							and event_id in ($eventIdsSql)";
					if ($rule_record != '') $sql .= " and record = '".db_escape($rule_record)."'";
					$q = db_query($sql);
					while ($row = db_fetch_assoc($q))
					{
						if ($row['value'] == '') continue;
						// If project is longitudinal, make sure field is on a designated event
						if ($longitudinal && !in_array($Proj->metadata[$row['field_name']]['form_name'], $Proj->eventsForms[$row['event_id']])) continue;
						// Repeating forms/events
						$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
						$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $Proj->metadata[$row['field_name']]['form_name']));
						$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
						$repeat_instrument = $isRepeatForm ? $Proj->metadata[$row['field_name']]['form_name'] : "";
						if ($row['instance'] === null) {
							$instance = $isRepeatEventOrForm ? 1 : 0;
						} else {
							$instance = $row['instance'];
						}
						// Add checkbox data
						$checkbox_data[$row['record']][$row['event_id']][$repeat_instrument][$instance][$row['field_name']][$row['value']] = '1';
					}
				}
				// Loop through all data
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										$isCheckbox = is_array($value);
										if (($value == '' || isset($missingDataCodes[$value])) && !$isCheckbox) continue;
										if ($isCheckbox) {
											if (isset($checkbox_data[$record][$event_id][$repeat_instrument][$instance][$field])) {
												$values = array_keys($checkbox_data[$record][$event_id][$repeat_instrument][$instance][$field]);
											} elseif (implode("", $value) == "") {
												continue;
                                            } else {
												$values = $value;
											}
										} else {
										    // If this is a SQL field containing Smart Variables, then we need to re-evaluate for EVERY field
										    if (isset($smartVarSqlFields[$field])) {
												$mc_fields[$field] = parseEnum(getSqlFieldEnum($smartVarSqlFields[$field], $Proj->project_id, $record, $event_id, $instance, null, null, $Proj->metadata[$field]['form_name']));
											}
											$values = array($value);
										}
										foreach ($values as $value) {
											if ($value == '0' && $isCheckbox) continue;
											// If value isn't a valid value, then put in array
											if (!isset($mc_fields[$field][$value]) && !isset($missingDataCodes[$value]))
											{
												// Set the $value variable as HTML link to data entry page
												if (in_array($field, $fieldsNoAccess)) {
													$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																	<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
												} else {
													// Set the $value variable as HTML link to data entry page
													$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
																. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
																. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
												}
												// Is this record-event excluded for this rule?
												$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
												// Save result
												$this->saveLogicCheckResults($rule_id, $record, $event_id, '', '', $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html", $excludeRecEvt, 0, $field, $instance);
												// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
												if (isset($this->dag_records[$record]))
												{
													$group_id = $this->dag_records[$record];
													if (!$excludeRecEvt) $dag_discrep[$group_id]++;
												}
											}
										}
									}
								}
							}
						}
					}
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Field validation errors - out of range
			case 'pd-9':
				$valTypes = getValTypes();
				// First create a fieldname array for just fields that have min/max validation
				$fields = array();
				foreach ($Proj->metadata as $field=>$attr)
				{
					if ($attr['element_validation_min'] != '')
					{
						$fields[$field]['min'] = $attr['element_validation_min'];
					}
					if ($attr['element_validation_max'] != '')
					{
						$fields[$field]['max'] = $attr['element_validation_max'];
					}
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, array_keys($fields));								
				// Get data for records
				$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), array_keys($fields), array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				// Loop through all values
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// Ignore non-relevant fields/values
										if ($value == '' || isset($missingDataCodes[$value])) continue;
										if ($value == '' || isset($missingDataCodes[$value])) continue;
										// Set default flag for out-of-range error
										$outOfRange = false;
										// Deal with commas in number_comma_decimal fields
										if ($valTypes[$Proj->metadata[$field]['element_validation_type']]['data_type'] == 'number_comma_decimal') {
										    $value = str_replace(",", ".", $value);
										    if (isset($fields[$field]['min'])) $fields[$field]['min'] = str_replace(",", ".", $fields[$field]['min']);
										    if (isset($fields[$field]['max'])) $fields[$field]['max'] = str_replace(",", ".", $fields[$field]['max']);
										}
										// Check min, if exists
										if (isset($fields[$field]['min']) && $value < $fields[$field]['min'])
										{
											$outOfRange = true;
										}
										// Check max, if exists
										if (!$outOfRange && isset($fields[$field]['max']) && $value > $fields[$field]['max'])
										{
											$outOfRange = true;
										}
										// If out of range, then output to results
										if ($outOfRange)
										{
											// If a DMY or MDY date, then convert value to that format for display
											$value = $this->convertDateFormat($field, $value);
											// Set the $value variable as HTML link to data entry page
											if (in_array($field, $fieldsNoAccess)) {
												$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
											} else {
												// Set the $value variable as HTML link to data entry page
												$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
															. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
															. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
											}
											// Set label for min/max display next to value
											$data_display = $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html<br><span style='color:gray;'>(";
											if (isset($fields[$field]['min'])) {
												$data_display .= "min: " . $this->convertDateFormat($field, $fields[$field]['min']);
											}
											if (isset($fields[$field]['min']) && isset($fields[$field]['max'])) {
												$data_display .= ", ";
											}
											if (isset($fields[$field]['max'])) {
												$data_display .= "max: " . $this->convertDateFormat($field, $fields[$field]['max']);
											}
											$data_display .= ")</span>";
											// Is this record-event excluded for this rule?
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, '', '', $data_display, $excludeRecEvt, 0, $field, $instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

			// Rule: Incorrect values for calculated fields
			case 'pd-10':
				// Set record array
				$record_array = ($rule_record != '') ? array($rule_record) : array();
				// If we are fixing the calc fields (rather than just displaying the discrepancies), then update all that are incorrect
				if (isset($_POST['action']) && $_POST['action'] == 'fixCalcs') {
					// Also count number of values fixed
					$this->valuesFixed = Calculate::saveCalcFields($record_array, array(), 'all', $excluded);
				} else {
					// Perform calculations on ALL calc fields over ALL records, and return those that are incorrect
					$calcFieldData = Calculate::calculateMultipleFields($record_array, array(), true, null, $user_rights['group_id'], $Proj);
					if (!empty($calcFieldData)) {
						// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
						// If does NOT have rights, then place fields in array so we can hide their data in the results.
						$fieldsNoAccess = $this->checkFormLevelRights($rule_id, array_keys($Proj->metadata));
						// LOCKING CHECK: Get all forms that are locked for the uploaded records
						$Locking = new Locking();
						$Locking->findLocked($Proj, $record_array);
						$Locking->findLockedWholeRecord(PROJECT_ID, $record_array);
						// Loop through all calc values in $calcFieldData
						foreach ($calcFieldData as $record=>&$this_record_data) {
							foreach ($this_record_data as $event_id=>&$this_event_data) {
								$this_arm_id = $Proj->eventInfo[$event_id]['arm_id'];
								foreach ($this_event_data as $repeat_instrument=>&$attr1) {
									foreach ($attr1 as $repeat_instance=>&$attr2) {
										// Loop through events to display values in popup
										foreach ($attr2 as $field=>$attr) {
											// Set the $value variable as HTML link to data entry page
											if (in_array($field, $fieldsNoAccess)) {
												$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
											} else {
												// Set the $value variable as HTML link to data entry page
												if ($attr['saved'] != '') {
													$value_html = "\"<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
																. "&id=$record&instance=$repeat_instance&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
																. "&fldfocus=$field#$field-tr'>".htmlspecialchars($attr['saved'], ENT_QUOTES)."</a>\"";
												} else {
													$value_html = "<a target='_blank' style='color:gray;' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
																. "&id=$record&instance=$repeat_instance&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
																. "&fldfocus=$field#$field-tr'>".$lang['dataqueries_71']."</a>";
												}
											}
											$data_display = $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html<div class='wrap' style='color:#800000;'>({$lang['dataqueries_291']} \"{$attr['calc']}\")</div>";
											// If record-event-field is locking, then note this (because it will not be able to be changed)
											$lock_instance = ($repeat_instance == '') ? 1 : $repeat_instance;
											if (isset($Locking->lockedWhole[$record][$this_arm_id])) {
												$data_display .= "<div class='wrap' style='line-height:13px;color:#A86700;margin-top:5px;'>
																	<img src='".APP_PATH_IMAGES."lock_small.png' style='position:relative;top:-2px;'>
																	{$lang['data_import_tool_288']}
																  </div>";
											} elseif (isset($Locking->locked[$record][$event_id][$lock_instance][$field])) {
												$data_display .= "<div class='wrap' style='line-height:13px;color:#A86700;margin-top:5px;'>
																	<img src='".APP_PATH_IMAGES."lock_small.png' style='position:relative;top:-2px;'>
																	{$lang['data_import_tool_290']}
																  </div>";
											}
											// Is this record-event excluded for this rule?
											$instance = ($repeat_instance == "") ? 0 : $repeat_instance;
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, '', '', $data_display, $excludeRecEvt, 0, $field, $repeat_instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

            // Rule: Fields containing missing data codes
			case 'pd-11':
				
				// Build array of fields for the project
				$valFields = array();
				foreach ($Proj->metadata as $field=>$attr)
				{
						$valFields[] = $field;
				}
				// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
				// If does NOT have rights, then place fields in array so we can hide their data in the results.
				$fieldsNoAccess = $this->checkFormLevelRights($rule_id, $valFields);								
				// Get data for records
				if (!empty($valFields)) {
					$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), $valFields, array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);
				} else {
					$data = array();
				}
				// Loop through all values
				foreach ($data as $record=>$event_data)
				{
					foreach (array_keys($event_data) as $event_id)
					{
						if ($event_id == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$event_id][""][0] = $event_data[$event_id];
						}
						foreach ($eventNormalized as $event_id=>$data1)
						{
							foreach ($data1 as $repeat_instrument=>$data2)
							{
								foreach ($data2 as $instance=>$data3)
								{
									foreach ($data3 as $field=>$value)
									{
										// Ignore non-relevant fields/values
										if ($value == '') continue;
										if (is_array($value)) {
										    // Deal with checkboxes
										    foreach ($value as $value_chk_code=>$value_chk_val) {
												if (isset($missingDataCodes[$value_chk_code]) && $value_chk_val == '1') {
												    $value = $value_chk_code;
												    break;
                                                }
                                            }
                                        }
										// If value is a missing data code, add to list
										if (isset($missingDataCodes[$value]))
										{
											// If a DMY or MDY date, then convert value to that format for display
											$value = $this->convertDateFormat($field, $value);
											// Set the $value variable as HTML link to data entry page
											if (in_array($field, $fieldsNoAccess)) {
												$value_html =  "<span style='color:#888;'>{$lang['dataqueries_72']}</span><br>
																<span style='color:#800000;'>{$lang['dataqueries_73']}</span>";
											} else {
												// Set the $value variable as HTML link to data entry page
												$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
															. "&id=$record".($instance > 0 ? "&instance=$instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
															. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
											}
											// Is this record-event excluded for this rule?
											$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance][$field]) ? 1 : 0);
											// Save result
											$this->saveLogicCheckResults($rule_id, $record, $event_id, $rule_attr['logic'], $literalLogic, $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field = $value_html", $excludeRecEvt, 0, $field, $instance);
											// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
											if (isset($this->dag_records[$record]))
											{
												$group_id = $this->dag_records[$record];
												if (!$excludeRecEvt) $dag_discrep[$group_id]++;
											}
										}
									}
								}
							}
						}
					}
					unset($data[$record]);
				}
				// Set the DAG discrepancy count array for this rule
				$this->dag_discrepancies[$rule_id] = $dag_discrep;
				break;

				
		}

		// If no discrepancies exist for this rule, then add it as empty results
		if (empty($this->logicCheckResults[$rule_id]))
		{
			$this->logicCheckResults[$rule_id] = array();
		}

	}

	// Load array of records as key with their corresponding DAG as value
	private function loadDagRecords()
	{
		global $Proj;
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Get a list of records in all DAGs
		$sql = "select record, value from redcap_data where project_id = " . PROJECT_ID . " and field_name = '__GROUPID__'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$this_group_id = $row['value'];
			// Make sure the DAG actually exists (in case was deleted but value remained in redcap_data)
			if (isset($dags[$this_group_id])) {
				$this->dag_records[$row['record']] = $this_group_id;
			}
		}
	}

	// Execute a single USER-DEFINED rule. Check all records' values for this rule.
	public function executeRule($rule_id, $rule_record=null)
	{
		global $lang, $Proj, $longitudinal, $user_rights, $data_resolution_enabled;

		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);
		
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();

		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// If DAGs exist, then set up arrays to collect which records are in which DAGs and a count of discrepancies for each DAG
		$dag_discrep = array();
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			// Set initial discrepancy count as 0 for each DAG
			foreach (array_keys($dags) as $group_id)
			{
				$dag_discrep[$group_id] = 0;
			}
			// Load array of records as key with their corresponding DAG as value
			$this->loadDagRecords();
		}

		// Check if this is a PRE-DEFINED RULE (will not be a number)
		if (!is_numeric($rule_id))
		{
			$this->executePredefinedRule($rule_id, $rule_record, $dag_discrep);
			return;
		}

		// Get the rule and its attributes
		$rule_attr = $this->getRule($rule_id);

		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		$eventsFlipped = array_flip($events);
		
		// Get special piping tags
		$specialPipingTags = Piping::getSpecialTagsFormatted(false);

		// Set the logic variable
		$logic = $rule_attr['logic'];
		// For fastest processing, preformat the logic by prepending [evnet-name] and/or appending [current-instance] where appropriate
		$logic = LogicTester::preformatLogicEventInstanceSmartVariables($logic, $Proj);
		
		// If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
		$this->logicContainsSmartVariables = Piping::containsSpecialTags($logic);
		// Pre-format the logic so that we don't run it every loop
		$logic = LogicTester::formatLogicToPHP($logic);

		// If there is an issue in the logic, then return an error message and stop processing
		$funcName = null;
		if (!$this->logicContainsSmartVariables) {
			try {
				// Instantiate logic parse
				$parser = new LogicParser();
				list($funcName, $argMap) = $parser->parse($logic, $eventsFlipped, true, false, false, true, true);
				$this->logicFuncToArgs[$funcName] = $argMap;
				//$this->logicFuncToCode[$funcName] = $parser->generatedCode;
			}
			catch (LogicException $e) {
				// Send back error message
				$this->logicHasErrors();
			}
		}

		// Determine if rule contains just one single field in its logic. If so, set variable as field name.
		$ruleContainsOneField = '';
		if (is_numeric($rule_id)) {
			$ruleContainsOneField = $this->ruleContainsOneField($rule_id);
			if ($ruleContainsOneField === false) $ruleContainsOneField = '';
		}

		// Array to collect list of all fields used in the logic
		$fields = array();
		$eventsUtilized = array();
		$fields_repeating_status = array();
		$fieldsReal = 0;
		// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
		foreach (array_keys(getBracketedFields($logic, true, true, false)) as $this_field)
		{
			// Check if has dot (i.e. has event name included)
			if (strpos($this_field, ".") !== false) {
				list ($this_event_name, $this_field) = explode(".", $this_field, 2);
				$this_form = $Proj->metadata[$this_field]['form_name'];
				// Get the event_id
				$this_event_id = array_search($this_event_name, $events);
				if (!is_numeric($this_event_id)) $this_event_id = 'all';
				// Add event/field to $eventsUtilized array
				$eventsUtilized[$this_event_id][$this_field] = true;
				// If fields being used exist on a repeating form, then add to other array
				if ($Proj->isRepeatingForm($this_event_id, $this_form) || ($this_event_id == 'all' && $Proj->isRepeatingFormAnyEvent($this_form))) {
					$fields_repeating_status[] = $this_form."_complete";
				}
			} else {
				$this_form = $Proj->metadata[$this_field]['form_name'];
				// Add event/field to $eventsUtilized array
				$eventsUtilized['all'][$this_field] = true;
				// If fields being used exist on a repeating form, then add to other array
				if ($Proj->isRepeatingFormAnyEvent($this_form)) {
					$fields_repeating_status[] = $this_form."_complete";
				}
			}
			// Add field to array
			$fields[] = $this_field;
			if ($Proj->isRepeatingFormAnyEvent($this_form)) {
				$fields[] = $this_form."_complete";
			}
			if (isset($Proj->metadata[$this_field])) $fieldsReal++;
			// Verify that the field really exists (may have been deleted). If not, stop here with an error.
			if (!isset($Proj->metadata[$this_field]) && !in_array($this_field, $specialPipingTags)) $this->logicHasErrors();
		}
		
		// If no fields were found BUT we're using Smart Variables, then include
		if ($this->logicContainsSmartVariables && $fieldsReal == 0) {
			$fields[] = $Proj->table_pk;
			// If using instance Smart Variables, then also include ALL form status fields (since repeating instances are often tied to those)
			if (Piping::containsInstanceSpecialTags($input)) {
				$formStatusFields = array();
				foreach (array_keys($Proj->forms) as $thisForm) {
					$formStatusFields[] = $thisForm."_complete";
				}
				$fields = array_merge($fields, $formStatusFields);
			}
		}
		
		// FORM-LEVEL RIGHTS: Make sure user has form-level data access to the form for ALL fields.
		// If does NOT have rights, then show nothing and give error message.
		$this->checkFormLevelRights($rule_id, $fields);

		// Get default values for all records (all fields get value '', except Form Status and checkbox fields get value 0)
		$default_values = array();
		$checkboxEvents = array(); // both keys and values are event IDs
		foreach ($fields as $this_field)
		{
			// Loop through all designated events so that each event
			foreach (array_keys($Proj->eventInfo) as $this_event_id)
			{
				// If is a real field or not
				if (isset($Proj->metadata[$this_field]) || in_array($this_field, $specialPipingTags))
				{
					// For longitudinal projects, ensure that this instrument has been designated for an event
					if ($longitudinal && !in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id])) continue;
					// Check a checkbox or Form Status field
					if ($Proj->metadata[$this_field]['element_type'] == 'checkbox') {
						// Loop through all choices and set each as 0
						foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $choice) {
							$default_values[$this_event_id][$this_field][$choice] = '0';
						}
						// remember events that have referenced checkboxes
						$checkboxEvents[$this_event_id] = $this_event_id;
					} elseif ($this_field == $Proj->metadata[$this_field]['form_name'] . "_complete") {
						// Set as 0
						$default_values[$this_event_id][$this_field] = '0';
					} else {
						// Set as ''
						$default_values[$this_event_id][$this_field] = '';
					}
				}
			}
		}

		// STATUS & EXCLUDED: Get a list of any record-event's for this rule that have been excluded (so we know what to exclude)
		// and the status for ALL.
		$excluded = array();
		$statuses = array();
		$sql = "select record, event_id, field_name, repeat_instrument, instance, exclude, status, query_status
				from redcap_data_quality_status where rule_id = $rule_id and project_id = " . PROJECT_ID;
		if ($rule_record != '') $sql .= " and record = '".db_escape($rule_record)."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Repeating forms/events
			$this_form = $row['repeat_instrument'] != '' ? $row['repeat_instrument'] : ($row['field_name'] != '' ? $Proj->metadata[$row['field_name']]['form_name'] : '');
			$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
			$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $this_form));
			$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
			$repeat_instrument = $isRepeatForm ? $this_form : "";
			$instance = $isRepeatEventOrForm ? $row['instance'] : 0;
			// Add status
			$statuses[$row['record']][$row['event_id']][$repeat_instrument][$instance] = $row['status'];
			// If excluded or is a closed data query
			if (   ($data_resolution_enabled != '2' && $row['exclude']) 
				|| ($data_resolution_enabled == '2' && ($row['query_status'] == 'VERIFIED' || $row['query_status'] == 'CLOSED'))
			) {
				$excluded[$row['record']][$row['event_id']][$repeat_instrument][$instance] = true;
			}
		}
		// Get data for records
		$data = Records::getData('array', ($rule_record == '' ? array() : $rule_record), array_merge(array($Proj->table_pk), $fields, $fields_repeating_status), array(), $user_rights['group_id'],
										false, false, false, '',
										false, false, false, false, false, array(), false, false, false, false, false, false,
										'EVENT', false, false, false, true);

		// Loop through all values
		foreach ($data as $record=>$event_data)
		{
			// Add any missing events not found in $data for this record so that we have placeholders for all events needed
			if ($Proj->longitudinal) {
				$missingEvents = array_diff(array_keys($default_values), array_keys($event_data));
				if (!empty($missingEvents)) {
					foreach ($missingEvents as $missingEventId) {
						// Only add this default data to event if we're utilizing the event in the logic
						if (!isset($event_data[$missingEventId]) && (isset($eventsUtilized['all']) || isset($eventsUtilized[$missingEventId]))) {
							$event_data[$missingEventId] = $default_values[$missingEventId];
						}
					}
				}
			}
			foreach (array_keys($event_data) as $event_id)
			{
				if ($event_id == 'repeat_instances') {
					$eventNormalized = $event_data['repeat_instances'];
				} else {
					$eventNormalized = array();
					$eventNormalized[$event_id][""][0] = $event_data[$event_id];
				}
				foreach ($eventNormalized as $event_id=>$data1)
				{
				    // If this event is not utilized at all in the logic, then skip it.
					if (!isset($eventsUtilized['all']) && !isset($eventsUtilized[$event_id])) {
					    continue;
                    }
					foreach ($data1 as $repeat_instrument=>$data2)
					{
						foreach ($data2 as $instance=>$instance_data)
						{
							if (empty($instance_data)) continue;
							// If we've already processed this, then skip
                            if ($funcName != null) {
								if (isset($this->alreadyProcessed[$record][$event_id][$repeat_instrument][$instance][$funcName])) continue;
								else $this->alreadyProcessed[$record][$event_id][$repeat_instrument][$instance][$funcName] = 1;
							}
							// If rule has one field, and we're looking at an event where that field's form is not designated, then skip
							if ($ruleContainsOneField != '' && !in_array($Proj->metadata[$ruleContainsOneField]['form_name'], $Proj->eventsForms[$event_id])) continue;
							// If rule has one field, and this is an instance number or repeat_instrument, even through this is a non-repeating form for this event, then skip
							if ($ruleContainsOneField != '' && ($repeat_instrument != '' || $instance != '0')
								&& !$Proj->isRepeatingForm($event_id, $Proj->metadata[$ruleContainsOneField]['form_name'])) continue;
							// If rule has one field, and that field exists on a repeating form, and this is not a repeating instance but the base instance, then skip
							if ($ruleContainsOneField != '' && ($repeat_instrument == '' || $instance == '0') 
								&& $Proj->isRepeatingForm($event_id, $Proj->metadata[$ruleContainsOneField]['form_name'])) continue;
							// If this event is a repeating event, and this is not a repeating instance but the base instance, then skip
							if ($instance == '0' && $Proj->isRepeatingEvent($event_id)) continue;
							// Transform data for processing


                            /*
                            $hasDiscrepancy = REDCap::evaluateLogic($logic, $Proj->project_id, $record, $event_id, ($instance > 0 ? $instance : null), $repeat_instrument,
                                                    ($ruleContainsOneField != '' ? $Proj->metadata[$ruleContainsOneField]['form_name'] : null), $data);
                            print "[$record][$event_id][$repeat_instrument][$instance]\n";
                            if ($hasDiscrepancy) {
								// Set the display for the fields/values used in the logic to display in the results table
								$data_display = $this->setResultTableDataDisplay($fields, $record, $consumedRecordData, $instance);
								// Is this record-event excluded for this rule?
								$excludeRecEvt = (isset($excluded[$record][$event_id][$repeat_instrument][$instance]) ? 1 : 0);
								// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
								if (isset($this->dag_records[$record])) {
									$group_id = $this->dag_records[$record];
									if (!$excludeRecEvt) $dag_discrep[$group_id]++;
								}
								// Get the status of this record-event (default is 0)
								$status = (isset($statuses[$record][$event_id][$repeat_instrument][$instance]) ? $statuses[$record][$event_id][$repeat_instrument][$instance] : 0);
								// Store results in array
								$this->saveLogicCheckResults($rule_id, $record, $event_id, $logic, $this->logicFuncToCode[$funcName],
									$data_display, $excludeRecEvt, $status, $ruleContainsOneField, $instance, $repeat_instrument);
							}
							continue;
                            */


                            // $instance_data = Records::moveRepeatingDataToBaseInstance($event_data, $event_id, $repeat_instrument, $instance, $Proj);
							// If we have Smart Variables, parse logic with Smart Variables right here
							if ($this->logicContainsSmartVariables) {
								$funcName = null;
								try {
									// Instantiate logic parse
									$parser = new LogicParser();
									$logicThisItem = Piping::pipeSpecialTags($logic, $Proj->project_id, $record, $event_id, $instance, null, true, null, 
														($ruleContainsOneField != '' ? $Proj->metadata[$ruleContainsOneField]['form_name'] : null));
									list($funcName, $argMap) = $parser->parse($logicThisItem, $eventsFlipped, true, false, false, true, true);
									$this->logicFuncToArgs[$funcName] = $argMap;
									// $this->logicFuncToCode[$funcName] = $parser->generatedCode;
								}
								catch (LogicException $e) {
									continue;
								}
							} else {
								// Get argmap, if we don't have it
								$argMap = $this->logicFuncToArgs[$funcName];
							}
							// If we're in a repeating event/instrument with logic having more than 1 field, make sure that each field exists in the specified data structure
                            // so that it doesn't look in the repeat_instances data for non-repeating fields
                            if ($ruleContainsOneField == '' && $instance != '0')
                            {
                                // Loop through $argMap and check if any fields specified in the logic do not exist in our $event_data structure, and if not, then skip
								$repeatingFieldsCount = 0;
                                foreach ($argMap as $argMapAttr) {
									if (!is_numeric($argMapAttr[3])) continue;
									$repeatingFieldsCount++;
                                }
                                // If we have no repeating fields but we're on a repeating instance, then there's nothing to do here, so skip
                                if ($repeatingFieldsCount === 0) continue;
                            }
							// Apply the logic
							$this->applyRuleLogicToAllEvents($funcName, $event_data, $rule_attr, $record, $excluded, $statuses, $rule_id, $dag_discrep, $ruleContainsOneField,
															 $repeat_instrument, $instance, $event_id, $Proj);
						}
					}
				}
			}
			unset($data[$record], $instance_data);
		}
		// If no discrepancies exist for this rule, then add it as empty results
		if (empty($this->logicCheckResults[$rule_id]))
		{
			$this->logicCheckResults[$rule_id] = array();
		}
		// Set the DAG discrepancy count array for this rule
		$this->dag_discrepancies[$rule_id] = $dag_discrep;
	}

	/**
	 * Builds the arguments to an anonymous function given record data.
	 * @param string $funcName the name of the function to build args for.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param boolean $forceBlankArgValue is used only for checking branching logic values
	 * so that it will replace any missing arguments/fields with a blank value (rather than stop executing).
	 * @param array $consumedRecordData a subset of $recordData containing only
	 * the data that was used in the function - used for displaying to the user.
	 * @return boolean true if $recordData contained all data necessary to
	 * populate the function parameters, false if not.
	 */
	private function buildRuleLogicArgs($funcName, &$recordData=array(), $currEventId=null, &$args=array(), $forceBlankArgValue=false, $Proj, &$consumedRecordData=array())
	{
		$isValid = true;
		try {
			$argMap = $this->logicFuncToArgs[$funcName];
			$args = array(); $consumedRecordData = array();
			foreach ($argMap as $argData)
			{
                // Get event_id, variable, and (if a checkbox) checkbox choice
                list ($eventVar, $projectVar, $cboxChoice, $instanceVar) = $argData;
                // If missing the event_id, assume the first event_id in the project
                if ($eventVar == '') $eventVar = $currEventId;
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
                    $projFields = $recordData[$eventVar];
                }
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
                    $consumedRecordData[$eventVar][$projectVar][$cboxChoice] = $value;
                }
                else {
                    $consumedRecordData[$eventVar][$projectVar] = $value;
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

	/**
	 * Runs the logic function and returns the *COMPLEMENT* of the result;
	 * sets a $_GET variable as a side effect to create an error message.
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildRuleLogicArgs().
	 * @return 1 if the function returned false, 0 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	private function applyRuleLogic($funcName, &$recordData, $currEventId,
		$rule_attr, &$args=null, &$useArgs=null, $Proj=null)
	{
		global $lang;
		$args = array();
		try {
			// Set values in $_GET array so we can retrieve them easily if an error occurs for the error message
			$_GET['error_rule_name'] = $lang['dataqueries_14'] . (is_numeric($rule_attr['order']) ? " #{$rule_attr['order']}" : "") . "{$lang['colon']} {$rule_attr['name']}";
			if (is_array($useArgs)) {
				$args = $useArgs;
			} elseif (!$this->buildRuleLogicArgs($funcName, $recordData, $currEventId, $args, false, $Proj)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
			$logicCheckResult = call_user_func_array($funcName, $args);
			return ($logicCheckResult === false ? 1 : 0);
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Runs the logic function for all a record's events and saves each result
	 * if the result implies that a discrepancy was found in an event-record.
	 * @param string $funcName see $this->applyRuleLogic().
	 * @param array $recordData see $this->applyRuleLogic().
	 * @param array $rule_attr see $this->applyRuleLogic().
	 * @param string $record the identifier of the record.
	 * @param array $excluded reference to exclusions for all records.
	 * @param array $statuses reference to statuses for all records.
	 * @param string $rule_id the identifier of the rule being executed.
	 * @param array $dag_discrep reference to an array used to keep track of
	 * discrepancies for each DAG.
	 */
	private function applyRuleLogicToAllEvents($funcName, &$recordData, $rule_attr, $record, &$excluded, &$statuses, 
											   $rule_id, &$dag_discrep, $ruleContainsOneField='', $repeat_instrument="", $instance=0, $eventId=null, $Proj)
	{
		// determine which events are used by the logic so that we can exclude
		// events that do not apply
//		$eventsUsed = array(); $allEvents = false;
//		$argMap = $this->logicFuncToArgs[$funcName];
//		foreach ($argMap as $argData) {
//			$eventId = $argData[0];
//			if ($eventId === null) {
//				$allEvents = true;
//			}
//			else {
//				$eventsUsed[$eventId] = true;
//			}
//		}
		// execute the rule for each event
		// foreach (array_keys($recordData) as $eventId)
		// {
			// skip this event if it plays no part in the rule
			// if (!$this->logicContainsSmartVariables && !$allEvents && empty($eventsUsed[$eventId])) continue;
			$useArgs = array(); $consumedRecordData = array();
			// skip this event if we don't have all the data to populate the function args
			if (!$this->buildRuleLogicArgs($funcName, $recordData, $eventId, $useArgs, false, $Proj, $consumedRecordData))
			{
				return;
			}
			$logicCheckResult = $this->applyRuleLogic($funcName, $recordData, $eventId, $rule_attr, $args, $useArgs, $Proj);
		    // print "----\n[$record][$eventId][$repeat_instrument][$instance][$funcName] == $logicCheckResult\n";
			// implies a TRUE value from the user's logic which means that their test
			// for a discrepancy found a discrepancy (could also imply an exception
			// when executing the logic)
			if ($logicCheckResult !== 1)
			{
				// Set the display for the fields/values used in the logic to display in the results table
				$data_display = $this->setResultTableDataDisplay($fields, $record, $consumedRecordData, $instance); 
				// Is this record-event excluded for this rule?
				$excludeRecEvt = (isset($excluded[$record][$eventId][$repeat_instrument][$instance]) ? 1 : 0);
				// If record is in a DAG, then get the group_id and increment the DAG discrepancy count
				if (isset($this->dag_records[$record]))
				{
					$group_id = $this->dag_records[$record];
					if (!$excludeRecEvt) $dag_discrep[$group_id]++;
				}
				// Get the status of this record-event (default is 0)
				$status = (isset($statuses[$record][$eventId][$repeat_instrument][$instance]) ? $statuses[$record][$eventId][$repeat_instrument][$instance] : 0);
				// Store results in array
				$this->saveLogicCheckResults($rule_id, $record, $eventId, $rule_attr['logic'], $this->logicFuncToCode[$funcName], 
											 $data_display, $excludeRecEvt, $status, $ruleContainsOneField, $instance, $repeat_instrument);
			}
		// }
	}

	// For date[time][_seconds] fields, return the format set for the field. (If not a date field or is YMD formatted date, then will ignore.)
	private function convertDateFormat($field, $value)
	{
		global $Proj;
		// Get field validation type, if exists
		$valType = $Proj->metadata[$field]['element_validation_type'];
		// If field is a date[time][_seonds] field with MDY or DMY formatted, then reformat the displayed date for consistency
		if ($value != '' && !is_array($value) && substr($valType, 0, 4) == 'date' 
			&& (substr($valType, -4) == '_mdy' || substr($valType, -4) == '_dmy'))
		{
			// Get array of all available validation types
			$valTypes = getValTypes();
			$valTypes['date_mdy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
			$valTypes['date_dmy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
			$valTypes['datetime_mdy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
			$valTypes['datetime_dmy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
			$valTypes['datetime_seconds_mdy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
			$valTypes['datetime_seconds_dmy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
			// Set regex pattern to use for this field
			$regex_pattern = $valTypes[$valType]['regex_php'];
			// Run the value through the regex pattern
			preg_match($regex_pattern, $value, $regex_matches);
			// Was it validated? (If so, will have a value in 0 key in array returned.)
			$failed_regex = (!isset($regex_matches[0]));
			if ($failed_regex) return $value;
			// Dates
			if ($valType == 'date_mdy') {
				$value	= DateTimeRC::date_ymd2mdy($value);
			} elseif ($valType == 'date_dmy') {
				$value = DateTimeRC::date_ymd2dmy($value);
			} else {
				// Datetime and Datetime seconds
				list ($this_date, $this_time) = explode(" ", $value);
				if ($valType == 'datetime_mdy' || $valType == 'datetime_seconds_mdy') {
					$value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
				} elseif ($valType == 'datetime_dmy' || $valType == 'datetime_seconds_dmy') {
					$value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
				}
			}
		}
		// Return the value
		return $value;
	}

	// Set the display for the fields/values used in the logic to display in the results table as HTML
	private function setResultTableDataDisplay($fields, $record, $record_data, $instance=0)
	{
		global $Proj, $lang;
		// Capture the fields and values as an HTML array, then output as a string
		$html_array = array();
		// Loop through the fields and data
		foreach ($record_data as $event_id=>$event_data)
		{
			foreach ($event_data as $field=>$thisvalue)
			{
			    // Does this field exist on a repeating form or event? If not, then set instance to 0
				$this_field_instance = ($instance > 0 && !$Proj->isRepeatingFormOrEvent($event_id, $Proj->metadata[$field]['form_name'])) ? 0 : $instance;

				if (is_array($thisvalue)) {
					foreach ($thisvalue as $choice=>$value) {
						// Set the $value variable as HTML link to data entry page
						$value_html = "<a target='_blank' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
									. "&id=$record".($this_field_instance > 0 ? "&instance=$this_field_instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
									. "&fldfocus=$field#$field-tr'>".htmlspecialchars($value, ENT_QUOTES)."</a>";
						// Add html to array
						$html_array[] = $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field($choice): $value_html";
					}
				} else {
					$value = $thisvalue;
					// If a DMY or MDY date, then convert value to that format for display
					$value = $this->convertDateFormat($field, $value);
					// Set the $value variable as HTML link to data entry page
					$value_html = "<a target='_blank' ".($value == '' ? "style='color:#888;'" : "")." href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID
								. "&id=$record".($this_field_instance > 0 ? "&instance=$this_field_instance" : "")."&event_id=$event_id&page=" . $Proj->metadata[$field]['form_name']
								. "&fldfocus=$field#$field-tr'>".($value == '' ? $lang['dataqueries_71'] : htmlspecialchars($value, ENT_QUOTES))."</a>";
					// Add html to array
					$html_array[] = $this->getFieldLabelForDiscrepantResults($field, $Proj)."$field: $value_html";
				}
				// Remove the field from the $fields array (so we'll know to display blank values - it's not in data because of EAV model)
				$fields_key = array_search($field, $fields);
				unset($fields[$fields_key]);
			}
		}
		// Now loop through any fields left over that have no values
		foreach ($fields as $field)
		{
			$html_array[] = "$field:";
		}
		// Return as HTML string
		return implode("<br>", $html_array);
	}

	// Get the saved results of a logic check
	public function getLogicCheckResults()
	{
		return $this->logicCheckResults;
	}

	// Save the results of logic check
	private function saveLogicCheckResults($rule_id, $record, $event_id, $logic, $literalLogic, $data_display, 
										   $exclude, $status, $field_name='', $instance=1, $repeat_instrument="")
	{
		if ($instance < 0 || !is_numeric($instance)) $instance = null;
		// Add info to the results array
		$this->logicCheckResults[$rule_id][] = array(
			'record' => $record,
			'event_id' => $event_id,
			// 'logic_original' => $logic,
			// 'logic_executed' => $literalLogic,
			'data_display' => $data_display,
			'exclude' => $exclude,
			'status' => $status,
			'field_name' => $field_name, // Only used for pre-defined rules, which are specific to single fields,
			'instance' => $instance,
			'repeat_instrument' => $repeat_instrument
		);
	}

	// Load the table data for displaying the rules
	private function loadRulesTable()
	{
		global $Proj, $lang, $user_rights, $isIE;
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Create the table for displaying the rules
		$rulesTableData = array();
		$counter = 1;
		foreach ($this->getRules() as $rule_id=>$rule_attr)
		{
			// Do not show order number for pre-defined rules but instead show letters
			if (!is_numeric($rule_attr['order'])) {
				$rule_attr['order'] = "<span style='color:#888;'>" . $rule_attr['order'] . "</span>";
			}
			// Add rule as row
			$rulesTableData[$counter] = array();
			$rulesTableData[$counter][] = "<div id='ruleid_{$rule_id}'><span style='display:none;'>{$rule_id}</span></div>";
			$rulesTableData[$counter][] = "<div id='ruleorder_{$rule_id}' class='rulenum'>{$rule_attr['order']}</div>";
			$rulesTableData[$counter][] = "<div id='rulename_{$rule_id}' rid='{$rule_id}' class='editname'>{$rule_attr['name']}</div>";
			$rulesTableData[$counter][] = "<div id='rulelogic_{$rule_id}' rid='{$rule_id}' class='editlogic'>".(is_numeric($rule_attr['order']) ? htmlspecialchars($rule_attr['logic'],ENT_QUOTES) : $rule_attr['logic'])."</div>";
			$rteChecked = (isset($rule_attr['real_time_execute']) && $rule_attr['real_time_execute']) ? "checked" : "";
			$rteImage   = (isset($rule_attr['real_time_execute']) && $rule_attr['real_time_execute']) ? "accept.png" : "stop_gray.png";
			$rulesTableData[$counter][] = !is_numeric($rule_id) ? "" :
				"<div class='editrte' style='text-align:center;padding:2px 0;' id='rulerte_{$rule_id}'>
					<img src='".APP_PATH_IMAGES."$rteImage'>
					<input type='checkbox' id='rulerte_newvalue_{$rule_id}' style='display:none;' disabled $rteChecked>
					<button style='display:none;font-size:11px;' onclick=\"enableDQRTE({$rule_id});\">{$lang['designate_forms_13']}</button>
				</div>";
			$rulesTableData[$counter][] = ($user_rights['data_quality_execute'] ? "<div id='ruleexe_{$rule_id}' class='exebtn' style='height:28px;vertical-align:middle;'><button style='".($isIE ? "font-size:11px;" : "")."vertical-align:middle;' onclick=\"preExecuteRulesAjax('$rule_id',0);\">{$lang['dataqueries_80']}</button></div>" : "");
			// If DAGs exist, add each as new column
			if (!empty($dags) && $user_rights['group_id'] == "")
			{
				foreach (array_keys($dags) as $group_id)
				{
					$rulesTableData[$counter][] = "<div id='ruleexe_{$rule_id}-{$group_id}' class='exegroup dagr_{$rule_id}'>&nbsp;</div>";
				}
			}
			// Add delete button (if have design rights)
			$rulesTableData[$counter][] = (is_numeric($rule_id) && $user_rights['data_quality_design']) ? "<div id='ruledel_{$rule_id}'><a href='javascript:;' onclick=\"deleteRule($rule_id);\"><img src='".APP_PATH_IMAGES."cross.png'></a></div>" : "";
			// Increment counter
			$counter++;
		}
		// Add extra row to add new rule (if have design rights)
		if ($user_rights['data_quality_design']) {
			$rulesTableData[$counter] = array();
			$rulesTableData[$counter][] = "";
			$rulesTableData[$counter][] = "<button style='vertical-align:middle;' onclick='addNewRule();'>{$lang['design_171']}</button>";
			$rulesTableData[$counter][] = "<div class='newname'>
											<textarea class='x-form-field notesbox' id='input_rulename_id_0' style='height:40px;margin:4px 0;width:95%;'></textarea>
                                                                                        <div style='border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;'>&nbsp;</div>
											<div style='padding:0;'><b>{$lang['dataqueries_76']}</b></div>
											<div style='padding:5px 0 20px;color:#666;'>{$lang['dataqueries_77']}</div>
										 </div>";
			$rulesTableData[$counter][] = "<div class='newlogic'>
        <textarea class='x-form-field notesbox' id='input_rulelogic_id_0' style='height:40px;margin:4px 0;width:95%;' onkeydown='logicSuggestSearchTip(this, event);' onblur='var val = this; setTimeout(function() { logicHideSearchTip(val); if(!checkLogicErrors(val.value,1)){validate_logic(val.value,\"\",0,\"\");}; }, 0);'></textarea>" .
                logicAdd("input_rulelogic_id_0") ."
				<div style='border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;' id='input_rulelogic_id_0_Ok'>&nbsp;</div>
				<div style='padding:0;'><b>{$lang['dataqueries_78']}</b></div>
				<div style='padding:5px 0 0;color:#666;'>(e.g., [age] < 18)</div>
				<div style='padding:5px 0 0;'><a href='javascript:;' style='font-size:10px;' onclick=\"helpPopup('ss69')\">{$lang['dataqueries_79']}</a></div>
			 </div>";
			$rulesTableData[$counter][] = "<div class='newrte' style='padding:30px 0 0;'>
											<div style='text-align:center;'><input type='checkbox' id='rulerte_id_0'></div>
											<div class='wrap' style='padding:22px 0 10px;'>{$lang['dataqueries_124']}<a href='javascript:;' class='help' style='text-decoration:none;font-weight:bold;' onclick='explainDQRTE()'>?</a></div>
										</div>";
			$rulesTableData[$counter][] = "";
			$rulesTableData[$counter][] = "";
		} else {
			$rulesTableData[$counter] = array("", "", "", "", "", "");
		}
		// Set up the table headers
		$rulesTableHeaders = array();
		$rulesTableHeaders[] = array(30, "", "center");
		$rulesTableHeaders[] = array(50, "<b>{$lang['dataqueries_14']} #</b>", "center");
		$rulesTableHeaders[] = array(251, "<b>{$lang['dataqueries_15']}</b>");
		$rulesTableHeaders[] = array(225, "<b>{$lang['dataqueries_16']}</b>&nbsp; {$lang['dataqueries_17']}");
		$rulesTableHeaders[] = array(78, RCView::span(array('style'=>'','class'=>'wrap'),
				$lang['dataqueries_123'] . "<a href='javascript:;' class='help' onclick='explainDQRTE()'>?</a>",
			"center"));
		$rulesTableHeaders[] = array(100, ($user_rights['data_quality_execute'] ? "<div style='white-space:normal;word-wrap:normal;padding:0;'><b>{$lang['dataqueries_18']}</b></div>" : ""), "center");
		// If DAGs exist, add each as new header column and also add new columns to "new rule" row at bottom
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			foreach ($dags as $group_id=>$group_name)
			{
				$rulesTableHeaders[] = array(50, "<div class='grouphdr'>$group_name</div>", "center");
				$rulesTableData[$counter][] = "";
			}
		}
		// Add column for delete button
		$rulesTableHeaders[] = array(30, ($user_rights['data_quality_design'] ? "<div style='font-size:10px;white-space:normal;word-wrap:normal;padding:0;'>{$lang['dataqueries_28']}</div>" : ""), "center");
		// Return the table headers and data
		return array($rulesTableHeaders, $rulesTableData);
	}

	// Display the table data for displaying the rules
	public function displayRulesTable()
	{
		global $Proj, $user_rights, $lang;
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Set the table width
		$width = 857 + ((!empty($dags) && $user_rights['group_id'] == "") ? count($dags)*63 : 0);
		// Load the rules table data
		list ($rulesTableHeaders, $rulesTableData) = $this->loadRulesTable();
		// Set table "title" with the execute button
		$title =   "<div style='width:690px'>
						<div style='float:left;font-size:13px;padding:15px 0 0 10px;'>
							{$lang['dataqueries_81']}
						</div>";
		if ($user_rights['data_quality_execute'])
		{
			// "Execute All Rules" button
			$title .=  "<div style='float:right;padding:2px 0 0;text-align:right;font-weight:bold;'>
							<span id='execRuleProgress' style='display:none;color:#444;padding-right:10px;'>
								<img src='".APP_PATH_IMAGES."progress_circle.gif'>
								{$lang['dataqueries_82']} <span id='rule_num_progress'>0</span> {$lang['dataqueries_83']} <span id='rule_num_total'>0</span>
							</span>
							<span id='execRuleComplete' style='display:none;color:green;padding-left:5px;padding-right:10px;'>
								<img src='".APP_PATH_IMAGES."tick.png'>
								{$lang['dataqueries_84']}
							</span>
							<span style='color:#800000;'>{$lang['dataqueries_115']}</span>
							<button class='execRuleBtn' style='font-weight:normal;' onclick=\"$('#dqRuleRecord').prop('disabled',true);$(this).css('color','#999999').prop('disabled',true);$('#dq_results').html('');preExecuteRulesAjax(rule_ids,0);\">{$lang['dashboard_12']}</button>
							<button class='execRuleBtn' style='font-weight:normal;' onclick=\"$('#dqRuleRecord').prop('disabled',true);$(this).css('color','#999999').prop('disabled',true);$('#dq_results').html('');preExecuteRulesAjax(rule_ids_excludeAB,0);\">{$lang['dataqueries_116']}</button>
							".(count($this->rules) == count($this->predefined_rules) ? "" : "<button class='execRuleBtn' style='font-weight:normal;' onclick=\"$('#dqRuleRecord').prop('disabled',true);$(this).css('color','#999999').prop('disabled',true);$('#dq_results').html('');preExecuteRulesAjax(rule_ids_user_defined,0);\">{$lang['dataqueries_117']}</button>")."
							&nbsp;<button id='clearBtn' style='font-weight:normal;color:#999;' disabled onclick=\"window.location.href=app_path_webroot+page+'?pid='+pid;\">{$lang['dataqueries_86']}</button>
						</div>
						<br><br>
						<div style='float:right;font-weight:normal;color:#800000;'>
							{$lang['dataqueries_297']}
							<span id='dqRuleRecordParent'>
								<select id='dqRuleRecord' class='x-form-text x-form-field' disabled style='max-width:300px;font-size:11px;margin-left:2px;padding-right:0;padding-top: 1px;height:19px;'>
									<option value=''>{$lang['pub_017']}</option>
								</select>
							</span>
						</div>";
		}
		$title .=  "	<div style='clear:both;height:0;padding:0;'></div>
					</div>";
		// Get html for the rules table
		$table_html = renderGrid("rules", $title, $width, "auto", $rulesTableHeaders, $rulesTableData, true, false, false);
		// Load JS for obtaining record list
		$table_html .= "<script type='text/javascript'>
						$(function(){
							$.post(app_path_webroot+'DataQuality/record_list.php?pid='+pid,{ },function(data){
								$('#dqRuleRecordParent').html(data);
							});
						});
						</script>";
		// Return the html
		return $table_html;
	}

	// Load the table data for the results of the rules check
	private function loadResultsTable()
	{
		global $longitudinal, $Proj, $lang, $user_rights, $data_resolution_enabled;
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Count exclusions
		$exclusion_count = 0;
		// Create the table for displaying the results of the rules check
		$resultsTableData = array();
		// Get logic results
		$logicResults = $this->getLogicCheckResults();
		// If DAGs exist, then reorder results grouped by DAG
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			// Add group_id, record, and event_id to arrays so we can do a multisort to sort them by DAG
			$group_ids = array();
			$records   = array();
			$events    = array();
			// Loop though all results
			foreach ($logicResults as $rule_id=>$results_list)
			{
				foreach ($results_list as $results)
				{
					$records[] = $results['record'];
					$events[]  = $results['event_id'];
					$group_ids[] = (isset($this->dag_records[$results['record']])) ? $this->dag_records[$results['record']] : "";
				}
			}
			// Now sort the results by DAG, thus grouping them by DAG in the list
			array_multisort($group_ids, SORT_NUMERIC, $logicResults[$rule_id]);
			unset($records, $events, $group_ids, $results_list);
		}
		// Obtain custom record label & secondary unique field labels for ALL records.
		$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords();
		// Loop through results
		foreach ($logicResults as $rule_id=>$results_list)
		{
			// First, get all data issues for this rule (comment counts for each record-event will be included)
			// This will load $this->dataIssues with each record/event
			$dataIssues = $this->getDataIssuesByRule($rule_id);
			// Check how many results we have and limit it if too many (memory issues + just cannnot display that many rows in a browser)
			$resultCount = count($results_list);
			// Loop through all results
			foreach ($results_list as $result_key=>$results)
			{
				// EXCLUDED OR DATA QUERY? Determine if need to show it if it's excluded or open a data resolution log
				if ($data_resolution_enabled == '2') {
					## OPEN DATA RESOLUTION / DATA QUERY
					// Set defaults
					$this_comment_count = 0;
					$dataQueryStatus = $dataQueryResponse = '';
					// Determine the comments count for this record and event
					if (isset($dataIssues[$results['record']][$results['event_id']])) {
						// Get current data query status
						$dataQueryStatus = ($results['field_name'] == '') ? $dataIssues[$results['record']][$results['event_id']]['query_status']
																		  : $dataIssues[$results['record']][$results['event_id']][$results['field_name']]['query_status'];
						$dataQueryResponse = ($results['field_name'] == '') ? $dataIssues[$results['record']][$results['event_id']]['response']
																		  : $dataIssues[$results['record']][$results['event_id']][$results['field_name']]['response'];
						// Don't show closed queries (exclusions), but only count them
						if (($dataQueryStatus == 'CLOSED' || $dataQueryStatus == 'VERIFIED') && isset($_POST['show_exclusions']) && !$_POST['show_exclusions']) {
							$exclusion_count++;
							continue;
						}
						// Count number of comments to display
						$this_comment_count = ($results['field_name'] == '') ? $dataIssues[$results['record']][$results['event_id']]['num_comments']
																			 : $dataIssues[$results['record']][$results['event_id']][$results['field_name']]['num_comments'];
						if (empty($this_comment_count)) $this_comment_count = 0;
					}
					// Set "comment" or "comments" text
					$this_comment_text = ($this_comment_count != 1) ? $lang['dataqueries_02'] : $lang['dataqueries_01'];
					// Determine balloon icon to display
					if ($dataQueryStatus == 'OPEN' && $dataQueryResponse == '') {
						$balloonIcon = 'balloon_exclamation.gif';
					} elseif ($dataQueryStatus == 'OPEN' && $dataQueryResponse != '') {
						$balloonIcon = 'balloon_exclamation_blue.gif';
					} elseif ($dataQueryStatus == 'CLOSED') {
						$balloonIcon = 'balloon_tick.gif';
					} elseif ($dataQueryStatus == 'VERIFIED') {
						$balloonIcon = 'tick_circle.png';
					} elseif ($dataQueryStatus == 'DEVERIFIED') {
						$balloonIcon = 'exclamation_red.png';
					} elseif ($this_comment_count > 0) {
						$balloonIcon = 'balloon_left.png';
					} else {
						$balloonIcon = 'balloon_left_bw2.gif';
					}
					if ($user_rights['data_quality_resolution'] == '0') {
						// User has no DQ resolution rights, so don't display button
						$excludeAction = "";
					} else {
						// Display button
						$excludeAction = RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;',
											'onclick'=>"dataResPopup('{$results['field_name']}',{$results['event_id']},'".js_escape($results['record'])."',1,'$rule_id','{$results['instance']}');"),
											RCView::img(array('id'=>"dc-icon-{$rule_id}_{$results['field_name']}__{$results['record']}", 'src'=>$balloonIcon,'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;line-height:1.3;'),
												RCView::span(array('id'=>"dc-numcom-{$rule_id}_{$results['field_name']}__{$results['record']}"), $this_comment_count) . " $this_comment_text"
											)
										);
					}
				} else {
					## EXCLUDE / REMOVE EXCLUSION
					if ($results['exclude'] && isset($_POST['show_exclusions']) && !$_POST['show_exclusions']) {
						// Don't show exclusions, but only count them
						$exclusion_count++;
						continue;
					} elseif (!$results['exclude']) {
						// Show link to exclude this result
						$excludeAction = "<a href='javascript:;' style='font-size:10px;' onclick=\"excludeDQResult(this,'$rule_id',1,'{$results['record']}',{$results['event_id']},'{$results['field_name']}','{$results['instance']}','{$results['repeat_instrument']}');\">{$lang['dataqueries_87']}</a>";
					} else {
						// Show link to remove the exclusion for this result
						$excludeAction = "<a href='javascript:;' style='font-size:10px;color:#800000;' onclick=\"excludeDQResult(this,'$rule_id',0,'{$results['record']}',{$results['event_id']},'{$results['field_name']}','{$results['instance']}','{$results['repeat_instrument']}');\">{$lang['dataqueries_88']}</a>";
					}
				}
				// For longitudinal projects, add arm/event name to record display
				$record_eventname = $results['record']
								  . (isset($extra_record_labels[$results['record']]) ? " ".$extra_record_labels[$results['record']] : '')
								  . ($results['instance'] < 1 ? "" : "<span class='dq_instlabel'>(#{$results['instance']})</span>")
								  . (($longitudinal && isset($Proj->eventInfo[$results['event_id']])) ? "<div class='dq_evtlabel'>" . $Proj->eventInfo[$results['event_id']]['name_ext'] . "</div>" : "");
				// Show label if this row is excluded
				if ($results['exclude']) {
					$record_eventname .= "<div class='dq_excludelabel'>{$lang['dataqueries_89']}</div>";
				}
				// Show DAG label if record is in a DAG
				if (isset($this->dag_records[$results['record']]) && $user_rights['group_id'] == "")
				{
					$group_id = $this->dag_records[$results['record']];
					$group_name = $dags[$group_id];
					$record_eventname .= "<div class='dq_daglabel'>($group_name)</div>";
				}
				// Set status label
				$status_label = (!is_numeric($this->rules[$rule_id]['order']) ? $this->status_labels[$this->default_status[$rule_id]] : $this->status_labels[$results['status']]);
				// Add rule as row
				$resultsTableData[] = array
				(
					$record_eventname,
					$results['data_display'],// . "<br><br>".$results['logic_executed'],
					$status_label,
					$excludeAction
					// , $commentary
				);
				// Free up memory as we go by deleting the result set as it is converted into the HTML table array form
				unset($logicResults[$rule_id][$result_key], $results_list[$result_key]);
				// If we have exceeded the max limit of results, then stop looping
				if ($result_key >= $this->resultLimit-1) break;
			}
		}
		// Free up memory
		unset($logicResults, $results_list, $this->logicCheckResults[$rule_id]);
		// Set up the table headers
		$resultsTableHeaders = array();
		$resultsTableHeaders[] = array(140, "<b>{$lang['global_49']}</b>" . ((!empty($dags) && $user_rights['group_id'] == "") ? "&nbsp;&nbsp;" . $lang['dataqueries_26'] : ""));
		$resultsTableHeaders[] = array(260, "<b>{$lang['dataqueries_25']}</b>");
		$resultsTableHeaders[] = array(110, "<b>{$lang['calendar_popup_08']}</b>", "center");
		if ($data_resolution_enabled == '2') {
			## OPEN DATA RESOLUTION / DATA QUERY
			$resultsTableHeaders[] = array(124, "<b>{$lang['dataqueries_130']}</b> <a href='javascript:;' onclick='explainDQResolve();'><img src='".APP_PATH_IMAGES."help.png' style='vertical-align:middle;'></a>", "center");
		} else {
			## EXCLUDE
			$resultsTableHeaders[] = array(124, "<b>{$lang['dataqueries_29']}</b> <a href='javascript:;' onclick='explainDQExclude();'><img src='".APP_PATH_IMAGES."help.png' style='vertical-align:middle;'></a>", "center");
		}

		// Return the table headers and data
		return array($resultsTableHeaders, $resultsTableData, $rule_id, $exclusion_count);
	}

	// Display the table data for displaying the results of the rules check
	public function displayResultsTable($rule_info)
	{
		global $lang;
		// Load the results table data
		list ($resultsTableHeaders, $resultsTableData, $rule_id, $exclusion_count) = $this->loadResultsTable();
		// Get count of discrepanies
		$num_discrepancies = count($resultsTableData);
		// If exclusions exist, then display message for the count
		$exclusionText = "";
		if ($exclusion_count > 0)
		{
			$exclusionWord = ($exclusion_count == 1) ? $lang['dataqueries_12'] : $lang['dataqueries_13'];
			$exclusionText = "<div id='excl_reload_{$rule_id}' style='padding:5px 0 0;font-size:11px;'>
							 (<b style='color:#800000;'>$exclusion_count $exclusionWord</b> -
							  <a href='javascript:;' style='font-size:11px;text-decoration:underline;' onclick=reloadRuleAjax('$rule_id',1,'$rule_id');>{$lang['dataqueries_92']}</a>)
							  <span style='padding-left:6px;display:none;' id='reload_dq_{$rule_id}'><img src='".APP_PATH_IMAGES."progress_circle.gif' style='vertical-align:middle;'> {$lang['dataqueries_90']}</span>
							  </div>";
		}
		// Set formatting of discrepancy count
		$num_discrepancies_formatted = User::number_format_user($num_discrepancies, 0);
		if ($num_discrepancies >= $this->resultLimit) {
			$num_discrepancies_formatted = "$num_discrepancies_formatted+<br>"
										 . "<span style='font-weight:normal;font-size:11px;'>{$lang['dataqueries_97']} $num_discrepancies_formatted {$lang['dataqueries_98']}</span>";
		}
		// Set the table title
		$resultsTableTitle = "<div style='padding:2px;font-weight:normal;'>
								{$lang['dataqueries_14']}" . (is_numeric($rule_info['order']) ? " #{$rule_info['order']}" : "") . ": 
								<b style='color:#800000;'>" . filter_tags(label_decode($rule_info['name']), false) . "</b>
							  </div>
							  <div style='padding:2px;font-weight:normal;'>
								{$lang['dataqueries_91']} <b style='color:#800000;'>$num_discrepancies_formatted</b>
								$exclusionText
							  </div>";
		// For PD-10 (fix calc values), add extra button to fix all calc values
		if ($rule_id == 'pd-10' && $num_discrepancies > 0) {
			$resultsTableTitle .= 	"<div style='margin-top:8px;'>
										<img src='".APP_PATH_IMAGES."exclamation.png'>
										<span style='margin:0 10px 0 4px;color:#800000;font-weight:normal;'>{$lang['dataqueries_292']}</span>
										<button class='jqbuttonmed' style='border-color:#999;' onclick=executeRulesAjax('$rule_id',1,0,'fixCalcs');>{$lang['dataqueries_293']}"
										.(($_POST['record'] == '') ? '' : " ".$lang['dataqueries_298'].' "'.RCView::escape($_POST['record']).'"')
										."</button>
									</div>";
		}
		// Obtain the html for the results table
		$table_html = renderGrid("results_table_" . $rule_id, "", "auto", "auto", $resultsTableHeaders, $resultsTableData, (!empty($resultsTableData)), false, false);
		// Return the html and count of discrepancies
		return array($num_discrepancies, str_replace(array("\r","\n","\t"), array("","",""), $table_html), str_replace(array("\r","\n","\t"), array("","",""), $resultsTableTitle));
	}


	// Check the order of the rules for rule_order to make sure they're not out of order
	public function checkOrder()
	{
		// Store the sum of the rule_order's and count of how many there are
		$sum   = 0;
		$count = 0;
		// Loop through existing resources
		foreach ($this->getRules() as $rule_id=>$attr)
		{
			// Ignore pre-defined rules
			if (!is_numeric($rule_id)) continue;
			// Add to sum
			$sum += $attr['order']*1;
			// Increment count
			$count++;
		}
		// Now perform check (use simple math method)
		if ($count*($count+1)/2 != $sum)
		{
			// Out of order, so reorder
			$this->reorder();
		}
	}

	// Reset the order of the rules for rule_order in the table
	public function reorder()
	{
		// Initial value
		$order = 1;
		// Loop through existing resources
		foreach (array_keys($this->getRules()) as $rule_id)
		{
			// Ignore pre-defined rules
			if (!is_numeric($rule_id)) continue;
			// Save to table
			$sql = "update redcap_data_quality_rules set rule_order = $order where project_id = " . PROJECT_ID . " and rule_id = $rule_id";
			$q = db_query($sql);
			// Increment the order
			$order++;
		}
	}

	// FORM-LEVEL RIGHTS: Make sure user has form-level data accses to the form for ALL fields.
	// If does NOT have rights, then show nothing and give error message.
	private function checkFormLevelRights($rule_id, $fields=array())
	{
		global $Proj, $user_rights, $lang;
		// Put all forbidden fields in an array
		$fieldsNoAccess = array();		
		// Get special piping tags
		$specialPipingTags = Piping::getSpecialTagsFormatted(false);
		// Loop through all fields used in this logic string
		foreach ($fields as $this_field)
		{
			if (in_array($this_field, $specialPipingTags)) continue;
			// Get form of field
			$this_field_form = $Proj->metadata[$this_field]['form_name'];
			if (!(isset($user_rights['forms'][$this_field_form]) && $user_rights['forms'][$this_field_form] > 0))
			{
				// Place field in array
				$fieldsNoAccess[] = $this_field;
				// If this is a user-defined rule, then stop here and throw error
				if (is_numeric($rule_id))
				{
					// Get list of upcoming rules to be processed after this one
					list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
					// Get current full name of rule
					$rule_attr = $this->getRule($rule_id);
					$error_rule_name = $lang['dataqueries_14'] . (is_numeric($rule_attr['order']) ? " #{$rule_attr['order']}" : "") . ": {$rule_attr['name']}";
					// Set error message
					$msg = "<div id='results_table_{$rule_id}'>
								<p class='red'>
									<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['dataqueries_32']}
									<b>$error_rule_name</b>{$lang['period']} {$lang['dataqueries_44']}
								</p>
							</div>";
					// Send back JSON
					print '{"rule_id":"' . $rule_id . '",'
						. '"next_rule_ids":"' . $rule_ids . '",'
						. '"discrepancies":"1",'
						. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.$lang['global_01'].'</span>",'
						. '"dag_discrepancies":[],'
						. '"title":"' . cleanJson($error_rule_name) . '",'
						. '"payload":"' . cleanJson($msg)  .'"}';
					exit;
				}
			}
		}
		// Return an array of fields that user cannot access
		return $fieldsNoAccess;
	}

	// LOGIC WITH ERRORS: If user-defined logic has syntax errors when the logic is executed, then send back an error message to user.
	private function logicHasErrors()
	{
		global $lang;
		// Get list of upcoming rules to be processed after this one
		list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
		// Get current full name of rule
		$rule_attr = $this->getRule($rule_id);
		$error_rule_name = $lang['dataqueries_14'] . (is_numeric($rule_attr['order']) ? " #{$rule_attr['order']}" : "") . "{$lang['colon']} {$rule_attr['name']}";
		// Set error message
		$msg = "<div id='results_table_{$rule_id}'>
					<p class='red'>
						<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['dataqueries_32']}
						<b>$error_rule_name</b>{$lang['period']} {$lang['dataqueries_50']}
					</p>
				</div>";
		// Send back JSON
		print '{"rule_id":"' . $rule_id . '",'
			. '"next_rule_ids":"' . $rule_ids . '",'
			. '"discrepancies":"1",'
			. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.cleanJson($lang['global_01']).'</span>",'
			. '"dag_discrepancies":[],'
			. '"title":"' . cleanJson($error_rule_name) . '",'
			. '"payload":"' . cleanJson($msg)  .'"}';
		exit;
	}

	## REAL-TIME EXECUTION CHECK FOR SINGLE RECORD ON FORM
	// Check DQ rules for single record after being saved on data entry form.
	// Return array of DQ rule_id's for those rules that were violated.
	// Either provide a form_name or an array of fields involved.
	public function checkViolationsSingleRecord($record, $event_id, $form_name=null, $fields_involved=array(), 
												$repeat_instance=1, $repeat_instrument="")
	{
		global $Proj, $longitudinal;
		// Get all DQ rules
		$dq_rules = $this->getRules();
		// Remove the pre-defined rules
		foreach (array_keys($dq_rules) as $key) {
			if (!is_numeric($key)) unset($dq_rules[$key]);
		}
		// Create array of all fields involved (if not supplied explicitly) from $form_name
		if ($form_name != null && isset($Proj->forms[$form_name])) {
			$fields_involved = array_keys($Proj->forms[$form_name]['fields']);
		}
		// EXCLUSIONS: Get exclusions for this record-event. Return as array with rule_id as key.
		$exclusions = self::getExclusionsSingleRecord($record, $event_id, $repeat_instance, $repeat_instrument);
		// Place all DQ rule_id's with discrepancies into an array after executing them
		$dq_errors = $dq_errors_excluded = array();
		// Has repeating instances?
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// If user-defined rules exist, then run them
		if (!empty($dq_rules))
		{
			// If longitudinal, get unique event name for this event
			if ($longitudinal) {
				$unique_event_name = $Proj->getUniqueEventNames($event_id);
			}
			// Loop through all user-defined rules to get fields involved in each. Add to dq_rules array.
			foreach ($dq_rules as $key=>$attr)
			{
				// If real-time execution is not enabled, then skip this rule
				if (isset($attr['real_time_execute']) && !$attr['real_time_execute']) {
					unset($dq_rules[$key]);
					continue;
				}
				// If longitudinal, then inject the unique event names into logic (if missing)
				// in order to specific the current event.
				if ($longitudinal) {
					$dq_rules[$key]['logic'] = $attr['logic'] = LogicTester::logicPrependEventName($attr['logic'], $unique_event_name, $Proj);
				}
				// If a repeating instance, then append instance number to fields in logic, if needed
                if ($hasRepeatingFormsEvents && is_numeric($repeat_instance) && $repeat_instance > 0) {
                    $dq_rules[$key]['logic'] = $attr['logic'] = LogicTester::logicAppendInstance($attr['logic'], $Proj, $event_id, $repeat_instrument, $repeat_instance);
                }
                // Replace any smart variables
				$dq_rules[$key]['logic'] = $attr['logic'] = Piping::pipeSpecialTags($attr['logic'], $Proj->project_id, $record, $event_id, $repeat_instance, null, true, null, $form_name);
                // Get fields involved
				$dq_rule_fields = array_keys(getBracketedFields($attr['logic'], true, true, true));
				// Now loop through all fields involved to see if any are on this form
				$executeRule = false;
				foreach ($dq_rule_fields as $this_field) {
					// If any fields are in $fields_involved and are validated as a real fields, then execute rule
					if (in_array($this_field, $fields_involved) && isset($Proj->metadata[$this_field])) {
						$executeRule = true;
					}
				}
				// If longitudinal and the current event is *not* found in the logic at all (explicity refers to other events),
				// then do not execute this rule.
				if ($executeRule && $longitudinal && strpos($attr['logic'], "[$unique_event_name]") === false) {
					$executeRule = false;
				}
				// If flag is set to not execute the rule, then remove it from the array of rules to execute
				if (!$executeRule) unset($dq_rules[$key]);
			}
			// If more than 5 DQ rules are going to be run, then go ahead and obtain ALL the record's data to 
			// reuse in LogicTester::evaluateLogicSingleRecord. This will be faster in the long run.
			if (count($dq_rules) > 5) {
				$record_data = Records::getData($Proj->project_id, 'array', $record);
			} else {
				$record_data = null;
			}
			// Loop through all pertinent user-defined rules and evaluate them.
			foreach ($dq_rules as $key=>$attr)
			{
				// Evaluate the logic for this record. If has discrepancy, then add rule_id to array
				$hasDiscrepancy = LogicTester::evaluateLogicSingleRecord($attr['logic'], $record, $record_data, null, $repeat_instance, $repeat_instrument);
				if ($hasDiscrepancy) {
					// Has this record-event been excluded for this rule?
					$isExcluded = in_array($key, $exclusions);
					if ($isExcluded) {
						$dq_errors_excluded[] = $key;
					} else {
						$dq_errors[] = $key;
					}
				}
			}
		}
		// print "excluded: ";print_array($dq_errors_excluded);
		// print "dq_errors: ";print_array($dq_errors);
		// exit;
		// Return array of errors and error that have been excluded
		return array($dq_errors, $dq_errors_excluded);
	}

	// Get exclusions for a single record-event. Return as array.
	public static function getExclusionsSingleRecord($record, $event_id, $repeat_instance=0, $repeat_instrument="")
	{
		global $data_resolution_enabled;
		// Query for exclusions and put in array
		$excluded = array();
		$sql = "select distinct rule_id, field_name
				from redcap_data_quality_status where project_id = " . PROJECT_ID . "
				and event_id = $event_id and record = '".db_escape($record)."'";
		// Append to query based on if data query feature is enabled (used old Exclude or new Data Query resolution)
		$sql .= ($data_resolution_enabled == '2') ? " and query_status in ('CLOSED','VERIFIED')" : " and exclude = 1";
		// If a repeating form/event, then limit to these instances
		if ($repeat_instance > 0 && is_numeric($repeat_instance)) {
			$sql .= " and instance = $repeat_instance";
		}
		if ($repeat_instrument != "") {
			$sql .= " and ((field_name is not null and repeat_instrument is null) 
							or (field_name is null and repeat_instrument = '".db_escape($repeat_instrument)."'))";
		}
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			if (is_numeric($row['rule_id'])) {
				// Custom rules created by user
				$excluded[] = $row['rule_id'];
			} else {
				// Field-level (rule-less)
				$excluded[] = $row['field_name'];
			}
		}
		return $excluded;
	}

	// Single Record Error Pop-up on data entry form: Output the HTML/JavaScript to display the DQ rules that were violed on the form
	public function displayViolationsSingleRecord($dq_error_ruleids=array(), $record, $event_id, $current_form=null, 
												  $show_excluded=0, $instance=1, $repeat_instrument="")
	{
		global $lang, $Proj, $user_rights, $isAjax, $user_rights, $data_resolution_enabled, $longitudinal;
		// Validate vars
		if (!is_numeric($event_id)) return false;
		$show_excluded = ($show_excluded != '0') ? '1' : '0';
		// Obtain array of all user-defined DQ rules
		$this->loadRules();
		// Put all HTML/JS into $r table rows
		$r = $dq_rules_violated_fields = $dq_rules_violated_events = $dq_rules_violated_fields_events = $dq_rules_violated_fields_all = array();
		// Sort the rule_id's by rule_id number
		sort($dq_error_ruleids);
		// Loop through the rule_id's of those violated and validate them
		foreach ($dq_error_ruleids as $rule_id) {
			// Is a valid rule_id?
			if (!isset($this->rules[$rule_id])) continue;
			// Get fields involved in this rule
			$dq_rules_violated_fields[$rule_id] = array_keys(getBracketedFields($this->rules[$rule_id]['logic'], true, true, true));
			// Add to array of all fields
			$dq_rules_violated_fields_all = array_merge($dq_rules_violated_fields_all, $dq_rules_violated_fields[$rule_id]);
			// Add events to array (longitudinal only)
			if ($longitudinal) {
				foreach (array_keys(getBracketedFields($this->rules[$rule_id]['logic'], true, true, false)) as $event_field) {
					list ($this_event_name, $this_field) = explode('.', $event_field, 2);
					if ($Proj->getEventIdUsingUniqueEventName($this_event_name) == "") {
						$this_event_name = Piping::pipeSpecialTags("[$this_event_name]", $Proj->project_id, $record, $event_id, $instance, null, false, null, $current_form);
					}
					$dq_rules_violated_events[] = $this_event_name;
					$dq_rules_violated_fields_events[$rule_id][$this_field] = $Proj->getEventIdUsingUniqueEventName($this_event_name);
				}
			}
		}
		// Convert unique event names to event_ids
		$dq_rules_violated_event_ids = array($event_id);
		if ($longitudinal) {
			foreach (array_unique($dq_rules_violated_events) as $this_event_name) {
				$dq_rules_violated_event_ids[] = $Proj->getEventIdUsingUniqueEventName($this_event_name);
			}
		}
		unset($dq_rules_violated_events);
		$dq_rules_violated_fields_all = array_unique($dq_rules_violated_fields_all);
		// Get all data for these fields for the given record-event so we can display the values
		if (!empty($dq_rules_violated_fields_all))
		{
			// Build query for pulling existing data
			$sql = "select field_name, value from redcap_data where	project_id = ".PROJECT_ID."
					and event_id in (".prep_implode($dq_rules_violated_event_ids).")
					and record = '".db_escape($record)."' and field_name in (".prep_implode($dq_rules_violated_fields_all).")
					and " . ($instance > 1 ? "instance = '".db_escape($instance)."'" : "instance is null");
			//Execute query and put any existing data into an array to display on form
			$q = db_query($sql);
			$element_data = array();
			while ($row_data = db_fetch_array($q)) {
				if ($row_data['value'] == '') continue;
				//Checkbox: Add data as array
				if ($Proj->isCheckbox($row_data['field_name'])) {
					$element_data[$row_data['field_name']][] = $row_data['value'];
				//Non-checkbox fields: Add data as string
				} else {
					$element_data[$row_data['field_name']] = $row_data['value'];
				}
			}
		}
		// EXCLUSIONS: Get exclusions for this record-event. Return as array with rule_id as key.
		$exclusions = self::getExclusionsSingleRecord($record, $event_id, $instance, $repeat_instrument);
		// Count the total number of exclusions that exist for all rules that are violated
		$total_exclusions = 0;
		// Loop through the rule_id's of those violated and  display error for each
		foreach ($dq_rules_violated_fields as $rule_id=>$dq_rule_fields)
		{
			// Has this record-event been excluded for this rule?
			if ($data_resolution_enabled == '2') {
				// If this rule only involves one field, get it
				$dataQueryField = (count($dq_rule_fields) == 1) ? $dq_rule_fields[0] : '';
				$isExcluded = ($dataQueryField != '' && in_array($dataQueryField, $exclusions));
			} else {
				$isExcluded = in_array($rule_id, $exclusions);
			}
			// Increment counter for total exclusions
			if ($isExcluded) $total_exclusions++;
			// If this record-event has been excluded for this rule, then skip it (do not display)
			if (!$show_excluded && $isExcluded) continue;

			// EXCLUDED OR DATA QUERY? Determine if need to show it if it's excluded or open a data resolution log
			if ($data_resolution_enabled == '2') {
				## OPEN DATA RESOLUTION / DATA QUERY
				// Obtain data resolution history as array
				$drw_history = $this->getFieldDataResHistory($record, $event_id, '', $rule_id, $instance);
				$dataQueryStatus = $drw_history[0]['query_status'];
				$dataQueryResponse = $drw_history[count($drw_history)-1]['response'];
				$this_comment_count = count($drw_history);
				// Set "comment" or "comments" text
				$this_comment_text = ($this_comment_count != 1) ? $lang['dataqueries_02'] : $lang['dataqueries_01'];
				// Determine balloon icon to display
				if ($dataQueryStatus == 'OPEN' && $dataQueryResponse == '') {
					$balloonIcon = 'balloon_exclamation.gif';
				} elseif ($dataQueryStatus == 'OPEN' && $dataQueryResponse != '') {
					$balloonIcon = 'balloon_exclamation_blue.gif';
				} elseif ($dataQueryStatus == 'CLOSED') {
					$balloonIcon = 'balloon_tick.gif';
				} elseif ($dataQueryStatus == 'VERIFIED') {
					$balloonIcon = 'tick_circle.png';
				} elseif ($dataQueryStatus == 'DEVERIFIED') {
					$balloonIcon = 'exclamation_red.png';
				} elseif ($this_comment_count > 0) {
					$balloonIcon = 'balloon_left.png';
				} else {
					$balloonIcon = 'balloon_left_bw2.gif';
				}
				if ($user_rights['data_quality_resolution'] == '0') {
					// User has no DQ resolution rights, so don't display button
					$excludeAction = "";
				} else {
					// Display button
					$excludeAction = RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;',
										'onclick'=>"dataResPopup('$dataQueryField',$event_id,'".js_escape($record)."',1,'$rule_id','$instance');"),
										RCView::img(array('id'=>"dc-icon-{$rule_id}_{$dataQueryField}__{$record}", 'src'=>$balloonIcon,'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;line-height:1.3;'),
											RCView::span(array('id'=>"dc-numcom-{$rule_id}_{$dataQueryField}__{$record}"), $this_comment_count) . " $this_comment_text"
										)
									);
				}
			} else {
				## EXCLUDE / REMOVE EXCLUSION
				if ($isExcluded) {
					$excludeAction = RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;color:#800000;text-decoration:underline;', 'onclick'=>"excludeDQResult(this,'$rule_id',0,'".js_escape($record)."',$event_id,'','$instance','$repeat_instrument');"),
										$lang['dataqueries_88']
									);
				} else {
					$excludeAction = RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;text-decoration:underline;', 'onclick'=>"excludeDQResult(this,'$rule_id',1,'".js_escape($record)."',$event_id,'','$instance','$repeat_instrument');"),
										$lang['dataqueries_87']
									);
				}
			}

			// Construct form for displaying fields and their values
			$dq_rule_fields_display = array();
			foreach ($dq_rule_fields as $this_field)
			{
				## FORM-LEVEL RIGHTS: Make sure user has form-level data accses to all fields utilized in this rule.
				// Get form of field
				$this_field_form = $Proj->metadata[$this_field]['form_name'];
				if ($this_field_form != null && !(isset($user_rights['forms'][$this_field_form]) && $user_rights['forms'][$this_field_form] > 0))
				{
					$dq_rule_fields_display[] = "$this_field = <span style='color:#888;'>{$lang['dataqueries_122']}</span>";
					continue;
				}
				// Set flag if this field exists on the currently opened form
				$fieldOnCurrentForm = ($this_field_form == $current_form);
				// Get this event_id that this field belongs to
				$this_event_id = (isset($dq_rules_violated_fields_events[$rule_id][$this_field])) ? $dq_rules_violated_fields_events[$rule_id][$this_field] : $Proj->firstEventId;
				## ADD VALUE TO ARRAY FOR DISPLAY
				if (is_array($element_data[$this_field])) {
					// Checkbox
					foreach ($element_data[$this_field] as $this_code) {
						// If field exists on current form/event, add data value as a link
						if ($fieldOnCurrentForm && $this_event_id == $event_id) {
							$dq_rule_fields_display[] = "$this_field($this_code): " .
														RCView::a(array('href'=>'javascript:;','style'=>'font-size:11px;text-decoration:underline;','onclick'=>"dqRteGoToField('$this_field');"), "1");
						} else {
							$dq_rule_fields_display[] = "$this_field($this_code): checked";
						}
					}
				} else {
					// Escape the value
					$value = nl2br(htmlspecialchars(br2nl(label_decode($element_data[$this_field]))), ENT_QUOTES);
					// If a DMY or MDY date, then convert value to that format for display
					$value = $this->convertDateFormat($this_field, $value);
					// If field exists on current form/event, add data value as a link
					if ($fieldOnCurrentForm && $this_event_id == $event_id) {
						$dq_rule_fields_display[] = "$this_field: " .
													RCView::a(array('href'=>'javascript:;','style'=>'font-size:11px;text-decoration:underline;','onclick'=>"dqRteGoToField('$this_field');"), $value);
					} else {
						$dq_rule_fields_display[] = "$this_field: $value";
					}
				}
			}
			// Add as row in table
			$r[] = array
			(
				RCView::img(array('src'=>'exclamation.png')),
				RCView::div(array('class'=>'wrap'),
					RCView::div(array('style'=>'font-weight:bold;line-height:12px;'),
						$lang['dataqueries_14'] . " #" . $this->rules[$rule_id]['order'] . $lang['colon'] .
						" " . RCView::span(array('style'=>'color:#800000;'), $this->rules[$rule_id]['name'])
					) .
					RCView::div(array('style'=>'padding:2px 10px;color:#555;line-height:12px;'),
						$this->rules[$rule_id]['logic']
					)
				),
				RCView::div(array('class'=>'wrap','style'=>'line-height:12px;'),
					implode("<br>", $dq_rule_fields_display)
				),
				$excludeAction
			);
		}
		// If any DQ rules were violated, then display pop-up delineating them
		if (!empty($r))
		{
			## HTML output
			// LOCKING: If form is locked for this record-event, then give notification with
			// ability to unlock it if user has lock/unlock privileges. (Assumes we're on a form.)
			$lockingMsg = '';
			if ($current_form != null) {
				$sql = "select 1 from redcap_locking_data where project_id = " . PROJECT_ID . " and record = '" . db_escape($record) . "'
						and event_id = $event_id and form_name = '" . db_escape($current_form) . "'
						instance = '".db_escape($instance < 1 ? "1" : $instance)."' limit 1";
				$q = db_query($sql);
				$formIsLocked = (db_num_rows($q) > 0);
				if ($formIsLocked) {
					$lockingMsg .= 	RCView::img(array('src'=>'lock.png')) .
									RCView::b($lang['data_entry_185']) . "<br>" .
									$lang['data_entry_183'];
					// If user has locking rights, then display text and button letting them unlock the form in this popup
					if ($user_rights['lock_record'] > 0) {
						$lockingMsg .= 	" " . $lang['data_entry_184'] .
										"<div style='margin:5px 0 0;'><button class='btn btn-defaultrc btn-xs' style='font-size:11px;' onclick=\"unlockForm('$(\'#unlockDQdiv\').hide()')\">{$lang['data_entry_182']}</button></div>";
					}
					// Wrap msg in div
					$lockingMsg = 	RCView::div(array('id'=>'unlockDQdiv','class'=>'yellow','style'=>'margin-bottom:20px;'),
										$lockingMsg
									);
				}
			}
			// Set up the table headers
			$hdrs = array();
			$hdrs[] = array(19, "");
			$hdrs[] = array(306,  RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['dataqueries_119']) .
									(($show_excluded || (!$show_excluded && $total_exclusions == 0)) ? '' :
										RCView::span(array('style'=>'margin-left:15px;'),
											"($total_exclusions " . ($total_exclusions > 1 ? $lang['dataqueries_13'] : $lang['dataqueries_12']) . " - " .
											RCView::a(array('href'=>'javascript:;','style'=>'font-size:11px;text-decoration:underline;',
												'onclick'=>"reloadDQResultSingleRecord(1);"), $lang['dataqueries_92']) .
											")"
										)
									)
							 );
			$hdrs[] = array(212, "<b>{$lang['dataqueries_120']}</b>");
			if ($data_resolution_enabled == '2') {
				## OPEN DATA RESOLUTION / DATA QUERY
				$hdrs[] = array(124, "<b>{$lang['dataqueries_130']}</b> <a href='javascript:;' onclick='explainDQResolve();'><img src='".APP_PATH_IMAGES."help.png' style='vertical-align:middle;'></a>", "center");
			} else {
				## EXCLUDE
				$hdrs[] = array(124, "<b>{$lang['dataqueries_29']}</b> <a href='javascript:;' onclick='explainDQExclude();'><img src='".APP_PATH_IMAGES."help.png' style='vertical-align:middle;'></a>", "center");
			}
			// Place table html into variable
			$rules_table_html = renderGrid("dq_rules_table_single_record", '', "710", "auto", $hdrs, $r, true, false, false);
			// If this is an AJAX request, then only output the "rules violated" table
			if ($isAjax) {
				// Render instructions and table of rules violated
				print 	RCView::div(array('style'=>'padding-bottom:20px;'), ($data_resolution_enabled == '2' ? $lang['dataqueries_309'] : $lang['dataqueries_118'])) .
						// Message if form is locked
						$lockingMsg .
						// Render table of rules violated
						$rules_table_html;
			} else {
				// Output hidden dialog div with table inside it
				print 	RCView::div(array('id'=>'dq_rules_violated', 'class'=>'simpleDialog'),
							// Instructions
							RCView::div(array('style'=>'padding-bottom:20px;'), ($data_resolution_enabled == '2' ? $lang['dataqueries_309'] : $lang['dataqueries_118'])) .
							// Message if form is locked
							$lockingMsg .
							// Render table of rules violated
							$rules_table_html
						);
				if ($data_resolution_enabled == '2') {
					// Div container for "explain Resolve" dialog
					print RCView::div(array('id'=>'explain_resolve', 'class'=>'simpleDialog', 'title'=>$lang['dataqueries_131']), $lang['dataqueries_132']);
				} else {
					// Div container for "explain Exclude" dialog
					print RCView::div(array('id'=>'explain_exclude', 'class'=>'simpleDialog', 'title'=>$lang['dataqueries_30']), $lang['dataqueries_121']);
				}
				// Javascript
				?>
				<script type='text/javascript'>
				$(function(){
					setTimeout(function(){
						// DQ RULES POP-UP DIALOG
						$('#dq_rules_violated').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 770), height: 550, open: function(){fitDialog(this)},
							title: '<?php echo js_escape(RCView::img(array('src'=>'exclamation_frame.png','style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;'), "{$lang['global_48']}{$lang['colon']} {$lang['dataqueries_113']}")) ?>',
							buttons: {
								Close: function() { $(this).dialog('close'); }
							}
						});
					},(isMobileDevice ? 1500 : 0));
				});
				</script>
				<?php
			}
		}
	}


	// Exclude result for a rule for a given rule-record-event
	public function saveExcludeForRule($rule_id=null, $record=null, $event_id=null, $status=null, $field_name='', $exclude=null, $instance=1, $repeat_instrument="")
	{
		global $lang, $Proj;
		// Verify rule_id, record, and event_id
		if (!((is_numeric($rule_id) || preg_match("/pd-\d{1,2}/", $rule_id)) && isset($event_id) 
				&& is_numeric($event_id) && is_numeric($instance) && $record != null)) {
			return false;
		}
		if ($repeat_instrument != "" && !isset($Proj->forms[$repeat_instrument])) $repeat_instrument = "";
		// Determine if a pre-defined rule or not
		if (is_numeric($rule_id)) {
			$ruleid_val = $rule_id;
			$pdruleid_val = "";
			$ruleid_sql = "rule_id = $rule_id";
			// Determine default status value for this rule
			$default_status = ($status == null) ? $this->default_status['num'] : $status;
		} else {
			$ruleid_val = "";
			$pdruleid_val = substr($rule_id, 3);
			$ruleid_sql = "pd_rule_id = '$pdruleid_val'";
			// Determine default status value for this rule
			$default_status = ($status == null) ? $this->default_status[$rule_id] : $status;
		}
		// If field_name is included in POST (i.e. for pre-defined rules), then add to query
		$field_sql = (!is_numeric($rule_id) && $field_name != '' && isset($Proj->metadata[$field_name])) ? "and field_name = '$field_name'" : "";
		// Insert new or update existing
		$sql = "insert into redcap_data_quality_status (rule_id, pd_rule_id, project_id, record, event_id, field_name, status, exclude, instance, repeat_instrument)
				values (" . checkNull($ruleid_val) . ", " . checkNull($pdruleid_val) . ", " . PROJECT_ID . ", '" . db_escape($record) . "', $event_id,
				" . checkNull($field_name) . ", $default_status, $exclude, '" . db_escape($instance) . "', " . checkNull($repeat_instrument) . ")
				on duplicate key update exclude = $exclude, status_id = LAST_INSERT_ID(status_id)";
		return (db_query($sql) ? db_insert_id() : false);
	}


	// Obtain array of fields that have a data resolution history for a given record/event
	public static function fieldsWithDataResHistory($record, $event_id, $form, $instance=1)
	{
		// Query table for fields that have a history (at least one row in cleaner_log table)
		$fieldsWithHistory = array();
		$sql = "select x.*, if (y.response is null or x.status='CLOSED', 0, 1) as responded from
				(select s.status_id, max(r.res_id) as max_res_id, s.field_name, s.query_status as status
				from redcap_data_quality_status s, redcap_data_quality_resolutions r, redcap_metadata m
				where s.project_id = " . PROJECT_ID . " and s.event_id = $event_id and s.record = '".db_escape($record)."'
				and s.status_id = r.status_id and m.project_id = s.project_id and s.instance = '".db_escape($instance)."'
				and m.field_name = s.field_name and m.form_name = '".db_escape($form)."'
				group by s.status_id) x, redcap_data_quality_resolutions y where x.max_res_id = y.res_id";
		$q = db_query($sql);
		if($q !== false)
		{
			while ($row = db_fetch_assoc($q))
			{
				// Add field to array as key with status as value
				$fieldsWithHistory[$row['field_name']] = array('status'=>$row['status'], 'responded'=>$row['responded']);
			}
		}
		// Return the array
		return $fieldsWithHistory;
	}


	// Obtain data resolution history for a given field/event/record and return as array
	public function getFieldDataResHistory($record, $event_id, $field, $rule_id='', $instance=1)
	{
		// Set subquery for rule_id/field
		$sub_sql = "";
		if (is_numeric($rule_id)) {
			// Determine if custom rule contains one field in logic
			$ruleContainsOneField = $this->ruleContainsOneField($rule_id);
			if ($ruleContainsOneField !== false) {
				// Custom rule with one field in logic (so consider it rule-less as field-level)
				$sub_sql = "and s.field_name = '".db_escape($ruleContainsOneField)."'";
			} else {
				// Custom rule-level (multiple fields)
				$sub_sql = "and s.rule_id = $rule_id";
			}
		} elseif ($field != '') {
			// Field-level (can include PD rules too)
			$sub_sql = "and s.field_name = '".db_escape($field)."'";
		}
		// Query table for history
		$drw_history = array();
		$sql = "select r.*, s.status, s.exclude, s.query_status, s.assigned_user_id
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.event_id = $event_id
				and s.record = '".db_escape($record)."' and s.status_id = r.status_id
				and s.instance = '".db_escape($instance)."'
				$sub_sql order by r.res_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add row to array
			$drw_history[] = $row;
		}
		// Return the array
		return $drw_history;
	}


	// Display data resolution history in table format
	public function displayFieldDataResHistory($record, $event_id, $field, $rule_id='', $instance=1)
	{
		global $longitudinal, $lang, $table_pk_label, $Proj, $user_rights, $data_resolution_enabled, $double_data_entry, $field_comment_edit_delete;

		// append --# if DDE user
		$record .= ($double_data_entry && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "";

		// Load all rules so we can use the rule number and label
		$this->loadRules();
		// Obtain data cleaner history  as array
		$drw_history = $this->getFieldDataResHistory($record, $event_id, $field, $rule_id, $instance);
		$drw_history_count = count($drw_history);

		// If using full DRW, then INTERWEAVE DATA HISTORY LOG into the comments
		if ($data_resolution_enabled == '2')
		{
			// Get data history log
			if ($field == '') {
				// If a rule with multiple fields, loop through all fields to get all their Data History
				$fieldsInLogic = array_keys(getBracketedFields($this->rules[$rule_id]['logic'], true, true, true));
				$dh_history = array();
				foreach ($fieldsInLogic as $thisDhField) {
					$dh_history_temp = Form::getDataHistoryLog($record, $event_id, $thisDhField, $instance);
					// Reformat data values so that it is formatted as "field_name = "data values""
					foreach ($dh_history_temp as &$attr) {
						if ($Proj->isCheckbox($thisDhField)) $attr['value'] = nl2br(str_replace("\n\n", "\n", trim(br2nl($attr['value']))));
						$attr['value'] = "$thisDhField = '{$attr['value']}'";
					}
					unset($attr);
					// Merge into existing values
					$dh_history = array_merge($dh_history, $dh_history_temp);
				}
				// Now put DH back in chronological order now that we've merged them all
				$dh_datetimes = array();
				foreach ($dh_history as $attr) $dh_datetimes[] = $attr['ts'];
				array_multisort($dh_datetimes, SORT_REGULAR, $dh_history);
			} else {
				$dh_history = Form::getDataHistoryLog($record, $event_id, $field, $instance);
				// Reformat data values so that it is formatted as "field_name = "data values""
				foreach ($dh_history as &$attr) {
					if ($Proj->isCheckbox($field)) $attr['value'] = nl2br(str_replace("\n\n", "\n", trim(br2nl($attr['value']))));
					$attr['value'] = "$field = '{$attr['value']}'";
				}
				unset($attr);
			}
			$dh_history_count = count($dh_history);
			$dh_history = array_values($dh_history);
			// Walk trough $drw_history and $dh_history chronologically and add to $drw_history_temp
			if ($dh_history_count > 0) {
				// Put merged info into $drw_history_temp, which we'll delete later
				$drw_history_temp = array();
				$drw_key = $dh_key = 0;
				// Loop
				for ($key = 0; $key < ($dh_history_count+$drw_history_count); $key++)
				{
					if (isset($dh_history[$dh_key]) && (!isset($drw_history[$drw_key]) || $dh_history[$dh_key]['ts'] <= $drw_history[$drw_key]['ts'])) {
						// Add data history event to array
						$drw_history_temp[] = array('ts'=>$dh_history[$dh_key]['ts'], 'user_id'=>$dh_history[$dh_key]['user'],
													'data_values'=>$dh_history[$dh_key]['value']);
						// Increment its key
						$dh_key++;
					} elseif (isset($drw_history[$drw_key]) && (!isset($dh_history[$dh_key]) || $dh_history[$dh_key]['ts'] > $drw_history[$drw_key]['ts'])) {
						// Add DRW comment to array and increment its key
						$drw_history_temp[] = $drw_history[$drw_key++];
					}
				}
				$drw_history = $drw_history_temp;
				unset($drw_history_temp);
			}
		}

		// Initialize variables
		$h = $r = $currentStatus = $currentResponded = $statusThisItem = '';
		$prevUserAttr = array();
		$num_row = 0;
		// Build rows of existing items in this thread
		if (!empty($drw_history))
		{
			// Loop through items in thread
			foreach ($drw_history as $attr) {
				// Increment number of DRW rows (exclude Data History rows)
				if (isset($attr['res_id'])) $num_row++;
				// Render row/section
				$r .= self::renderFieldDataResHistoryExistingSection($attr, $prevUserAttr, $num_row);
				// Get value of current status and last action's attributes
				if (isset($attr['query_status'])) {
					$prevUserAttr = $attr;
					$currentStatus = $prevUserAttr['query_status'];
					$statusThisItem = $prevUserAttr['current_query_status'];
					$currentResponded = $prevUserAttr['response'];
				}
			}
		}
		## Instructions
		// Set string for field name/label
		$fieldNameLabel = '';
		if ($field != '') {
			$fieldNameLabel = RCView::div('',
								"{$lang['graphical_view_23']}{$lang['colon']} <b>$field</b>
								(\"" . strip_tags($Proj->metadata[$field]['element_label']) . "\") "
							  );
		}
		// Set string for field name/label
		$ruleLabel = '';
		if ($rule_id != '') {
			$ruleLabel = 	RCView::div('',
								"{$lang['dataqueries_14']}{$lang['colon']} " .
								RCView::span(array('style'=>'color:#800000;'),
									"<b>" . $lang['dataqueries_14'] . " " . (is_numeric($rule_id) ? '#' : '') .
									$this->rules[$rule_id]['order'] . $lang['colon'] . "</b> " . $this->rules[$rule_id]['name']
								)
							);
		}
		// Query status label
		$queryStatusLabel = '';
		if ($data_resolution_enabled == '2')
		{
			if ($currentStatus == '') {
				$currentStatusText = $lang['dataqueries_217'];
				$currentStatusColor = 'gray';
				$currentStatusIcon = 'balloon_left_bw2.gif';
			} else {
				$currentStatusText = $lang['dataqueries_216'];
				if ($currentStatus == 'OPEN' && $currentResponded == '') {
					$currentStatusColor = '#C00000';
					$currentStatusIcon = 'balloon_exclamation.gif';
					$currentStatusText .= RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['dataqueries_219']);
				} elseif ($currentStatus == 'OPEN' && $currentResponded != '') {
					$currentStatusColor = '#000066';
					$currentStatusIcon = 'balloon_exclamation_blue.gif';
					$currentStatusText .= RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['dataqueries_218']);
				} elseif ($currentStatus == 'VERIFIED') {
					$currentStatusColor = 'green';
					$currentStatusIcon = 'tick_circle.png';
					$currentStatusText = $lang['dataqueries_220'];
				} elseif ($currentStatus == 'DEVERIFIED') {
					$currentStatusColor = '#800000';
					$currentStatusIcon = 'exclamation_red.png';
					$currentStatusText = $lang['dataqueries_222'];
				} else {
					$currentStatusColor = 'green';
					$currentStatusIcon = 'balloon_tick.gif';
					$currentStatusText = $lang['dataqueries_215'];
				}
			}
			$queryStatusLabel = RCView::div('',
									$lang['dataqueries_214']." " .
									RCView::img(array('src'=>$currentStatusIcon)) .
									RCView::span(array('style'=>"font-weight:bold;color:$currentStatusColor;"),
										$currentStatusText
									)
								);
		}
		// Output instructions string
		$h .= 	RCView::div(array('style'=>'margin:0 0 15px;'),
					// Instructions
					($data_resolution_enabled == '2'
						? 	// DRW instructxions
							RCView::div(array('style'=>'text-align:right;margin:0 10px 5px;'),
								'<i class="fas fa-film"></i> ' .
								RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('data_resolution_workflow01.swf','".js_escape($lang['dataqueries_137'])."');"),
									$lang['global_80'] . " " . $lang['dataqueries_137']
								)
							) .
							$lang['dataqueries_129']
						: 	// Field Comment Log instructions
							$lang['dataqueries_154'] . " " .
							RCView::a(array('href'=>APP_PATH_WEBROOT."DataQuality/field_comment_log.php?pid=".PROJECT_ID,
								'style'=>"text-decoration:underline;"), $lang['dataqueries_141']) . " " .
							$lang['dataqueries_258'] .
							// Add note about disabling editing/deleting field comments
							(($data_resolution_enabled == '1' && $field_comment_edit_delete) ? " " .
								RCView::span(array('style'=>'color:#800000;'), $lang['dataqueries_287']) : '')
					) .
					// Record
					RCView::div(array('style'=>'margin-top:10px;'),
						"{$table_pk_label}{$lang['colon']} &nbsp;" .
						($field == ''
							? RCView::span(array('style'=>'font-size:13px;font-weight:bold;'),
								RCView::escape($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
							  )
							: RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&instance=$instance&event_id=$event_id&id=".
								($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
								."&page=".$Proj->metadata[$field]['form_name']."&fldfocus=$field#$field-tr", 'style'=>'font-size:13px;font-weight:bold;text-decoration:underline;'),
								RCView::escape($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
							)
						)
					) .
					// Event name (if longitudinal)
					(($longitudinal && isset($Proj->eventInfo[$event_id])) ? "<div class='dq_evtlabel'>{$lang['bottom_23']} <b>" . $Proj->eventInfo[$event_id]['name_ext'] . "</b></div>" : "") .
					// Rule
					$ruleLabel .
					// Field
					$fieldNameLabel .
					// Opened/Closed, etc.
					$queryStatusLabel
				);
		## Render SECTION HEADER as separate table
		$h .=
			// If query has not been opened and user has Respond-only rights, then don't show table header
			(($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '2' && empty($prevUserAttr)) ? '' :
				RCView::table(array('id'=>'existingDCHistorySH','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
					// SECTION HEADER (only display if some rows exist already)
					RCView::tr('',
						(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
							RCView::td(array('class'=>'label_header','style'=>'padding:0;width:35px;'),
								''
							)
						) .
						RCView::td(array('class'=>'label_header','style'=>'padding:5px 8px;width:140px;'),
							$lang['dataqueries_06']
						) .
						RCView::td(array('class'=>'label_header','style'=>'padding:5px 8px;width:145px;'),
							$lang['global_17']
						) .
						RCView::td(array('class'=>'label_header','style'=>'text-align:left;padding:5px 8px 5px 12px;'),
							($data_resolution_enabled == '1'
								// "Comments" header text
								? $lang['dataqueries_146']
								// "Comments and Details" header text
								: $lang['dataqueries_147']
							)
						)
					)
				)
			);
		// If field is provided, then get its form name
		if ($field != '') {
			$fieldForm = $Proj->metadata[$field]['form_name'];
			//$hasFormEditRights = ($user_rights['forms'][$fieldForm] == '1' || $user_rights['forms'][$fieldForm] == '3');
		}
		// Render whole thread as a table insider a scrollable div
		$h .=
			// Display existing thread
			($r == '' ? '' :
				RCView::div(array('id'=>'existingDCHistoryDiv','style'=>'overflow-y:auto;'),
					RCView::table(array('id'=>'existingDCHistory','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
						// Rows for EXISTING COMMENTS/ATTRIBUTES
						$r
					)
				)
			) .
			## Rows for adding NEW COMMENT/ATTRIBUTES
			// If using Field Comment Log
			((	$data_resolution_enabled == '1'
				// Or if using DR and responding to an open query
				|| ($data_resolution_enabled == '2' && $prevUserAttr['response_requested']
					&& ($user_rights['data_quality_resolution'] == '2' || $user_rights['data_quality_resolution'] == '3'
						|| $user_rights['data_quality_resolution'] == '5'))
				// Or if using DR and opening a query OR re-opening a closed query (with Open Query Only rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '4'
					&& (empty($prevUserAttr)
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
				// Or if using DR and opening a query OR re-opening a closed query OR responding to an open query (as Open and Response rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '5'
					&& (empty($prevUserAttr) || $prevUserAttr['response_requested']
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
				// Or if using DR and opening a query OR closing an open query OR re-opening a closed query (as Open, Close, Response rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '3'
					&& (empty($prevUserAttr) || $prevUserAttr['response']
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
			)
				// Render new form
				? self::renderFieldDataResHistoryNewForm($record, $event_id, $field, $rule_id, $instance, $prevUserAttr)
				// User does not have rights to take an action in DRW mode
				:	(($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] != 'CLOSED')
						? 	RCView::div(array('class'=>'yellow', 'style'=>'margin:20px 0;'),
								RCView::img(array('src'=>'exclamation_frame.png')) .
								$lang['dataqueries_213']
							)
						: RCView::div(array('class'=>'space', 'style'=>'margin:20px 0;'), ' ')
					)
			);
		// Output html
		return $h;
	}


	// Render single section of data resolution history table
	static private function renderFieldDataResHistoryExistingSection($attr=array(), $prev_attr=array(), $num_row)
	{
		global $lang, $data_resolution_enabled, $field_comment_edit_delete, $Proj;
		// Determine if a real DRW entry or a Data History entry
		if (isset($attr['res_id'])) {
			// DRW
			// Get username of initiator
			$userInitiator = User::getUserInfoByUiid($attr['user_id']);
			$userInitiator = ($userInitiator === false) ? RCView::div(array('style'=>'font-size:12px;line-height:13px;color:#C00000;'), $lang['dataqueries_302']) : $userInitiator['username'];
			$cellstyle = '';
			// Get username of assigned user
			if ($num_row == '1' && isset($attr['assigned_user_id'])) {
				$userAssigned = User::getUserInfoByUiid($attr['assigned_user_id']);
				$userAssigned = "{$userAssigned['username']} ({$userAssigned['user_firstname']} {$userAssigned['user_lastname']})";
			}
			// Get form name of this field
			$form_name = (!(isset($_POST['field_name']) && isset($Proj->metadata[$_POST['field_name']]))) ? '' : $Proj->metadata[$_POST['field_name']]['form_name'];
		} else {
			// Data History data values
			// Get username and info
			$userInitiator = $attr['user_id'];
			$cellstyle = 'background:#E2EAFA';
		}
		// Set thread status type
		$userResponded = (isset($attr['response']) && $attr['response'] != '' && !$attr['response_requested']);
		$userClosedQuery = (isset($attr['current_query_status']) && $attr['current_query_status'] == 'CLOSED');
		$userUploadedFile = (isset($attr['upload_doc_id']) && $attr['upload_doc_id'] != '');
		// Get uploaded file name and size (if applicable)
		if ($userUploadedFile) {
			$q_fileup_query = db_query("select doc_name, doc_size from redcap_edocs_metadata where doc_id = {$attr['upload_doc_id']} limit 1");
			$q_fileup = db_fetch_array($q_fileup_query);
			$q_fileup['doc_size'] = round_up($q_fileup['doc_size'] / 1024 / 1024);
			if (strlen($q_fileup['doc_name']) > 24) $q_fileup['doc_name'] = substr($q_fileup['doc_name'],0,22)."...";
			$fileup_label = "{$q_fileup['doc_name']} ({$q_fileup['doc_size']} MB)";
		}
		// Render this row or section of rows
		$h = RCView::tr(array('id'=>'res_id-'.$attr['res_id']),
				// Edit/delete comments, if enabled
				(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
					RCView::td(array('class'=>'data nowrap', 'style'=>'border:1px solid #ddd;padding:3px 0;width:35px;text-align:center;'.$cellstyle),
						RCView::div(array('style'=>'margin-bottom:3px;'),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"editFieldComment({$attr['res_id']},'$form_name',1,0);"),
								RCView::img(array('src'=>'pencil.png', 'title'=>$lang['global_27']))
							)
						) .
						RCView::div(array('style'=>''),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteFieldComment({$attr['res_id']},'$form_name',1);"),
								RCView::img(array('src'=>'cross.png', 'title'=>$lang['design_170']))
							)
						)
					)
				) .
				// Date/time
				RCView::td(array('class'=>'data nowrap', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:140px;'.$cellstyle),
					DateTimeRC::format_ts_from_ymd($attr['ts'])
				) .
				// Current user
				RCView::td(array('class'=>'data', 'style'=>'word-wrap:break-word;border:1px solid #ddd;padding:3px 8px;text-align:center;width:145px;'.$cellstyle),
					$userInitiator
				) .
				// Comment and other attributes
				RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'.$cellstyle),
					// If a responder responded to an opened query
					(!$userResponded ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_212']) .
							RCView::span(array('style'=>'color:#000066;'), self::getDataResolutionResponseChoices($attr['response']))
						)
					) .
					// If user uploaded a file
					(!$userUploadedFile ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_211']) .
							RCView::a(array('target'=>'_blank', 'style'=>'text-decoration:underline;', 'href'=>APP_PATH_WEBROOT."DataQuality/data_resolution_file_download.php?pid=".PROJECT_ID."&res_id={$attr['res_id']}&id={$attr['upload_doc_id']}"),
								$fileup_label
							)
						)
					) .
					// Note if user opened the query
					((!(in_array($prev_attr['current_query_status'], array('','VERIFIED','DEVERIFIED')) && $attr['current_query_status'] == 'OPEN')) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_210'])
						)
					) .
					// Note if user sent query back for further attention
					((!($prev_attr['response'] != '' && $attr['response_requested'])) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_209'])
						)
					) .
					// Note if user closed the query
					(!$userClosedQuery ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:green;'), $lang['dataqueries_208'])
						)
					) .
					// Note if user re-opened the query
					((!($prev_attr['current_query_status'] == 'CLOSED' && $attr['current_query_status'] == 'OPEN')) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_206'])
						)
					) .
					// Note if user verified the data
					((!($attr['current_query_status'] == 'VERIFIED')) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:green;'), $lang['dataqueries_221'])
						)
					) .
					// Note if the data was de-verified
					((!($attr['current_query_status'] == 'DEVERIFIED')) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
							RCView::span(array('style'=>'color:#800000;'),
								$lang['dataqueries_223'] .
								// If was de-verified automatically via data change, then note this
								($attr['comment'] != '' ? '' : " ".$lang['dataqueries_225'])
							)
						)
					) .
					// If was assigned to a user
					(!isset($userAssigned) ? '' :
						RCView::div(array('style'=>''),
							RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_205']) .
							RCView::span(array('style'=>'color:#800000;'), $userAssigned)
						)
					) .
					// Display comments
					(!isset($attr['comment']) ? '' :
						RCView::div(array('style'=>'line-height:13px;'),
							($data_resolution_enabled == '2'
								// Full DRW display
								?	RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_195'].$lang['colon']) .
									"&#8220;" . nl2br(RCView::escape($attr['comment'],false)) . "&#8221;"
								// Field Comment Log (only display the comment itself)
								: 	nl2br(RCView::escape(filter_tags(br2nl($attr['comment'])),false))
							)
						)
					) .
					// Display data values (from Data History Widget)
					(!isset($attr['data_values']) ? '' :
						RCView::div(array('style'=>'line-height:13px;padding-bottom:2px;'),
							RCView::div(array('style'=>'color:#777;font-size:11px;'), $lang['data_history_03'] . $lang['colon']) .
							$attr['data_values']
						)
					) .
					// "EDITED" div that denotes if comment was edited before
					($data_resolution_enabled == '1' ?
						RCView::div(array('class'=>'fc-comment-edit', 'style'=>($attr['field_comment_edited'] ? 'display:block;' : '')),
							$lang['dataqueries_286']
						)
						: ''
					)
				)
			);
		// Output html
		return $h;
	}


	// Render form to add/modify data resolution history
	public static function renderFieldDataResHistoryNewForm($record, $event_id, $field, $rule_id, $instance=1, $prevUserAttr=array())
	{
		global $lang, $data_resolution_enabled, $user_rights, $data_resolution_enabled, $field_comment_edit_delete;
		// Set background color
		$bgColor = 'background:#ddd;';
		// Put all content for last column in $td
		$td = '';
		// Determine if the rule_id contains only one field. If so, set field_name as variable.
		$ruleContainsOneField = ($field == '') ? '' : $field;
		if (is_numeric($rule_id)) {
			$dqOneField = new DataQuality();
			$ruleContainsOneField = $dqOneField->ruleContainsOneField($rule_id);
		}
		// Set "new comment" label
		$commentLabel = ($prevUserAttr['response_requested']) ? $lang['dataqueries_203'] : $lang['dataqueries_204'];
		## IF USER IS RE-OPENING THREAD
		if ($data_resolution_enabled == '2' && isset($prevUserAttr['current_query_status']) && $prevUserAttr['current_query_status'] == 'CLOSED')
		{
			$td .=
				// Require response from other user?
				RCView::div(array('style'=>''),
					RCView::checkbox(array('id'=>'dc-response_requested', 'onclick'=>"
						if ($(this).prop('checked')) {
							$('#dc-comment-div').removeClass('opacity35');$('#dc-comment').prop('disabled',false).focus();
						} else {
							$('#dc-comment-div').addClass('opacity35');$('#dc-comment').prop('disabled',true).val('');
						}")) .
					$lang['dataqueries_202']
				);
		}
		## IF USER IS CLOSING THREAD OR RETURNING BACK TO ASSIGNED USER
		elseif ($data_resolution_enabled == '2' && isset($prevUserAttr['response']) && $prevUserAttr['response'] != '')
		{
			$td .=
				// Choose thread status: close or return to user
				RCView::div(array('style'=>''),
					RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-closed','value'=>'CLOSED','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_151'])."');")) .
					RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_151']) .
					RCView::br() .
					RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_153'])."');")) .
					RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_153'])
				);
		}
		## IF USER IS OPENING A QUERY
		elseif ($data_resolution_enabled == '2' && (empty($prevUserAttr) || (!empty($prevUserAttr)
			&& ($prevUserAttr['current_query_status'] == 'DEVERIFIED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
				|| !$prevUserAttr['response_requested']))))
		{
			// Get array of user_id's of users with Respond privileges
			$usersCanRespond = User::getUsersDataResRespond();

			## Add extra radio to verify data or de-verify data
			$assignedUserSelectStyle = 'margin-bottom:5px;';
			if (empty($prevUserAttr) || (!empty($prevUserAttr) && $prevUserAttr['current_query_status'] == 'DEVERIFIED')) {
				// Option to Verify data
				$assignedUserSelectStyle = 'margin-left:24px;margin-bottom:10px;';
				$td .= 	RCView::div(array('style'=>''),
							RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-verified','value'=>'VERIFIED','checked'=>'checked','onclick'=>"$('#drw_comment_optional').show();$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_221'])."');")) .
							RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_221']) .
							RCView::div(array('style'=>'color:gray;margin:4px 0 2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
							RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#drw_comment_optional').hide();$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_197'])."');")) .
							RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_197'])
						);
			} elseif ($prevUserAttr['current_query_status'] == 'VERIFIED') {
				// Option to De-Verify data
				$assignedUserSelectStyle = 'margin-left:24px;margin-bottom:10px;';
				$td .= 	RCView::div(array('style'=>''),
							RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-verified','value'=>'DEVERIFIED','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_224'])."');")) .
							RCView::span(array('style'=>'color:#800000;font-weight:bold;'), $lang['dataqueries_224']) .
							RCView::div(array('style'=>'color:gray;margin:4px 0 2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
							RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_197'])."');")) .
							RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_197'])
						);
			}
			// Require response from other user?
			$td .=	RCView::div(array('style'=>$assignedUserSelectStyle),
						$lang['dataqueries_201'] . " " .
						RCView::select(array('id'=>'dc-assigned_user_id'), $usersCanRespond, '')
					);
			if ((empty($prevUserAttr) && is_numeric($rule_id) && $ruleContainsOneField == '') || $prevUserAttr['current_query_status'] == 'VERIFIED'
				|| $prevUserAttr['current_query_status'] == 'DEVERIFIED') {
				$td .=	RCView::div(array('class'=>'hidden'),
							RCView::checkbox(array('id'=>'dc-response_requested', 'checked'=>'checked'))
						);
			}
;
		}
		## IF USER IS RESPONDING TO THREAD (and has respond rights)
		elseif ($data_resolution_enabled == '2' && $prevUserAttr['response_requested'])
		{
			// Close query (optional): If user has open/close/respond rights (rather than just respond rights), then also show option to close the query
			$radioCloseOption = $radioRespondOption = $fileUploadStyle = '';
			if ($user_rights['data_quality_resolution'] == '3') {
				$radioCloseOption = RCView::div(array('style'=>'margin:2px 0 10px;'),
										RCView::div(array('style'=>'color:gray;margin-bottom:2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
										RCView::radio(array('name'=>'dc-status','value'=>'CLOSED','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_151'])."');")) .
										RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_151'])
									);
				$radioRespondOption = RCView::radio(array('name'=>'dc-status','value'=>'OPEN','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_152'])."');")) . " ";
				$fileUploadStyle = 'margin-left:24px;';
			}
			// Response drop-down
			$td .=
				RCView::div(array('style'=>''),
					$radioRespondOption . RCView::span(array('style'=>'color:#000066;font-weight:bold;'), $lang['dataqueries_200']) . " &nbsp;" .
					RCView::select(array('id'=>'dc-response'), array_merge(array(''=>$lang['dataqueries_199']),
						self::getDataResolutionResponseChoices()), '')
				);
			// Upload a file (optional)
			$td .=
				RCView::div(array('style'=>$fileUploadStyle),
					$lang['dataqueries_198'] . " &nbsp;" .
					// Span container for "Upload New Document" link
					RCView::span(array('id'=>'drw_upload_new_container'),
						RCView::img(array('src'=>'add.png')) .
						RCView::a(array('href'=>'javascript:;', 'id'=>'dc-upload_doc_id', 'style'=>'color:green;text-decoration:underline',
							'onclick'=>"openDataResolutionFileUpload('".js_escape($record)."', $event_id, '$field', '$rule_id');"), $lang['form_renderer_23'])
					) .
					RCView::div(array(),
						// Hidden link for dispaying file name of uploaded file (once uploaded)
						RCView::a(array('href'=>'javascript:;', 'id'=>'dc-upload_doc_id-label', 'style'=>'display:none;text-decoration:underline'), '') .
						// Hidden link for removing uploaded file (once uploaded)
						RCView::a(array('href'=>'javascript:;', 'id'=>'drw_upload_remove_doc',
							'style'=>'display:none;margin-left:10px;color:#800000;font-size:10px;', 'onclick'=>"dataResolutionDeleteUpload();"),
							'[X] '.$lang['scheduling_57']
						)
					) .
					// Hidden div to store doc_id of uploaded file
					RCView::div(array('id'=>'drw_upload_file_container', 'class'=>'hidden'), '')
				);
			$td .= $radioCloseOption;
		}
		// Disable the comment textarea if query is closed
		$disableComments = ($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] == 'CLOSED') ? 'disabled' : '';
		$commentsDivClass = ($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] == 'CLOSED') ? 'opacity35' : '';

		// Query status label and dialog Save button text (depending on state)
		$saveBtn = $lang['dataqueries_195'];
		$commentOptionalClass = 'hidden';
		if ($data_resolution_enabled == '2')
		{
			if (empty($prevUserAttr)) {
				$saveBtn = $lang['dataqueries_221'];
				$commentOptionalClass = '';
			} else {
				if ($prevUserAttr['current_query_status'] == 'OPEN' && $prevUserAttr['response'] == '') {
					$saveBtn = $lang['dataqueries_152'];
				} elseif ($prevUserAttr['current_query_status'] == 'OPEN' && $prevUserAttr['response'] != '') {
					$saveBtn = $lang['dataqueries_151'];
				} elseif ($prevUserAttr['current_query_status'] == 'VERIFIED') {
					$saveBtn = $lang['dataqueries_224'];
				} elseif ($prevUserAttr['current_query_status'] == 'DEVERIFIED') {
					$saveBtn = $lang['dataqueries_221'];
					$commentOptionalClass = '';
				} else {
					$saveBtn = $lang['dataqueries_196'];
				}
			}
		}
		// Output Table and Save/Cancel buttons
		return 	RCView::table(array('id'=>'newDCHistory','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
					RCView::tr('',
						(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
							RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 0;text-align:center;width:35px;'.$bgColor),
								''
							)
						) .
						// Invisible progress icon
						RCView::td(array('id'=>'newDCnow','class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 8px;text-align:center;width:140px;'.$bgColor),
							DateTimeRC::format_ts_from_ymd(NOW)
						) .
						// Username
						RCView::td(array('class'=>'data', 'style'=>'word-wrap:break-word;border:1px solid #ccc;padding:3px 8px;text-align:center;width:145px;'.$bgColor),
							USERID
						) .
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 8px;'.$bgColor),
							// Contents
							$td .
							// Comment box
							RCView::div(array('id'=>'dc-comment-div','class'=>$commentsDivClass),
								($data_resolution_enabled == '2'
									? RCView::div(array('style'=>'padding-top:5px;'),
										$lang['dataqueries_195'] .
										// Only display "optional" for comment if verifying data value
										RCView::span(array('id'=>'drw_comment_optional','class'=>$commentOptionalClass),
											' '.$lang['survey_251']
										) .
										$lang['colon']
									  )
									: ''
								) .
								RCView::textarea(array('id'=>'dc-comment','class'=>'x-form-field notesbox',$disableComments=>$disableComments,'style'=>'height:45px;width:97%;'))
							)
						)
					)
				) .
				// SAVE & CANCEL BUTTONS
				RCView::div(array('style'=>'padding:15px 0 7px;text-align:right;font-size:13px;font-weight:bold;vertical-align:middle;'),
					// Cancel button
					RCView::div(array('style'=>'float:right;'),
						RCView::button(array('class'=>'jqbutton', 'style'=>'padding: 0.4em 0.8em !important;', 'onclick'=>"$('#data_resolution').dialog('close');"),
							$lang['global_53']
						)
					) .
					// Save button
					RCView::div(array('style'=>'float:right;'),
						RCView::button(array('id'=>'dataResSavBtn', 'class'=>'jqbutton', 'style'=>'padding: 0.4em 0.8em !important;margin-right:3px;',
							'onclick'=>"dataResolutionSave('".js_escape($field)."','".js_escape($event_id)."','".js_escape($record)."','".js_escape($rule_id)."','".js_escape($instance)."');"), $saveBtn
						)
					) .
					// "Saved!" msg
					RCView::div(array('class'=>'hidden','id'=>'drw_saved','style'=>'padding-top:5px;color:green;margin-right:20px;float:right;'),
						RCView::img(array('src'=>'tick.png')) .
						$lang['design_243']
					) .
					// "Saving..." msg
					RCView::div(array('class'=>'hidden','id'=>'drw_saving','style'=>'padding-top:5px;margin-right:20px;float:right;'),
						RCView::img(array('src'=>'progress_circle.gif')) .
						$lang['designate_forms_21']
					)
				);
	}

	// Data Resolution Workflow: Render the file upload dialog
	public static function renderDataResFileUploadDialog()
	{
		global $lang, $data_resolution_enabled;
		// Validate that DRW is enabled
		if ($data_resolution_enabled != '2') return '';
		// Invisible div for dialog
		return	RCView::div(array('class'=>'simpleDialog','style'=>'font-size:12px;display:none;', 'id'=>'drw_file_upload_popup','title'=>$lang['form_renderer_23']),
					// "Upload Success" msg
					RCView::div(array('id'=>'drw_upload_success', 'style'=>'display:none;margin-top:20px;font-weight: bold; font-size: 14px; text-align: center; color: green;'),
						RCView::img(array('src'=>'tick.png')) .
						$lang['design_200']
					) .
					// "Upload Failed" msg
					RCView::div(array('id'=>'drw_upload_failed', 'style'=>'display:none;margin-top:20px;font-weight: bold; font-size: 14px; text-align: center; '),
						$lang['dataqueries_160']
					) .
					// "Upload progress" msg
					RCView::div(array('id'=>'drw_upload_progress', 'style'=>'display:none;margin-top:20px;font-weight: bold; font-size: 14px; text-align: center; '),
						$lang['data_entry_65'] . RCView::br() .
						RCView::img(array('src'=>'loader.gif'))
					) .
					// Form for uploading file
					RCView::form(array('id'=>'drw_upload_form', 'method'=>'post', 'target'=>'drw_upload_target', 'enctype'=>'multipart/form-data',
						'action'=>APP_PATH_WEBROOT."DataQuality/data_resolution_file_upload.php?pid=".PROJECT_ID, 'onsubmit'=>'dataResolutionStartUpload();'),
						// Instructions
						RCView::div(array('style'=>'margin:5px 0 10px;'), $lang['data_entry_62']) .
						// File input field
						RCView::div(array('id'=>'dc-upload_doc_id-container'),
							RCView::file(array('id'=>'dc-upload_doc_id', 'name'=>'myfile'))
						) .
						// Hidden record, event_id, field, and rule_id values (to be given values via jQuery)
						RCView::input(array('type'=>'hidden','name'=>'record')) .
						RCView::input(array('type'=>'hidden','name'=>'event_id')) .
						RCView::input(array('type'=>'hidden','name'=>'field')) .
						RCView::input(array('type'=>'hidden','name'=>'rule_id')) .
						// Max file size
						RCView::div(array('style'=>'color:#808080;'),
							"({$lang['data_entry_63']} ".maxUploadSizeAttachment()." MB)"
						) .
						// Hidden CSRF token field
						RCView::input(array('type'=>'hidden','name'=>'redcap_csrf_token', 'value'=>System::getCsrfToken()))
					) .
					// Invisible iframe for uploading file
					RCView::iframe(array('id'=>'drw_upload_target', 'name'=>'drw_upload_target',
						'src'=>APP_PATH_WEBROOT . "DataEntry/empty.php?pid=" . PROJECT_ID, 'style'=>'width:0;height:0;border:0px solid #fff;'), ' ')
				);
	}

	// Get a count of all query statuses by status type. Return array with status type as key.
	public function countDataResIssues()
	{
		global $user_rights, $Proj;
		// Limit records pulled only to those in user's Data Access Group
		if ($user_rights['group_id'] != "") {
			$dag_sql = "and s.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])).")";
		}

		// Limit results to existing fields, ignoring any issues orphaned by deleted or renamed fields
		$field_where_clause = "and (s.field_name is null or s.field_name in (".prep_implode(array_keys($Proj->metadata))."))";

		// Set up query
		$sql = "select s.query_status, count(1) as count_status from redcap_data_quality_status s
				where s.project_id = " . PROJECT_ID . " and s.query_status is not null
				$field_where_clause
				$dag_sql group by s.query_status";
		$q = db_query($sql);
		// Pre-load array with 0s
		$statuses = array('OPEN'=>0, 'CLOSED'=>0, 'OPEN_UNRESPONDED'=>0, 'OPEN_RESPONDED'=>0, 'VERIFIED'=>0, 'DEVERIFIED'=>0);
		while ($row = db_fetch_assoc($q)) {
			$statuses[$row['query_status']] = $row['count_status'];
		}
		// Now get sub-statuses for Open: Responded and unresponded. Add to array
		$sql = "select count(1) as unresponded from (select s.status_id, max(r.res_id) as res_id_max
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.query_status = 'OPEN' and s.status_id = r.status_id
				$field_where_clause
				$dag_sql group by s.status_id) x, redcap_data_quality_resolutions y
				where x.res_id_max = y.res_id and y.response_requested = 1";
		$q = db_query($sql);
		$statuses['OPEN_UNRESPONDED'] = db_result($q, 0);
		$statuses['OPEN_RESPONDED'] = $statuses['OPEN'] - $statuses['OPEN_UNRESPONDED'];
		// Return array
		return $statuses;
	}

	// Display Data Quality tabs
	public function renderTabs()
	{
		global $lang, $user_rights, $data_resolution_enabled;
		// Set html to display video link(s)
		$videoLinks = "";
		// Determine tabs to display
		$tabs = array();
		if ($user_rights['data_quality_execute'] + $user_rights['data_quality_design'] > 0) {
			// Add DQ tab
			$tabs['DataQuality/index.php']   = '<i class="fas fa-search"></i> ' . $lang['dataqueries_193'];
		}
		if ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] > 0) {
			// Get a count of unresolved issues
			$queryStatuses = $this->countDataResIssues();
			$numOpenIssues = $queryStatuses['OPEN'];
			// Set html for badge with count of unresolved issues
			$numOpenIssuesHtml = ($numOpenIssues == 0) ? ''
				: RCView::span(array('id'=>'dq_tab_issue_count', 'class'=>'badgerc'), $numOpenIssues);
			// Add DRW tabs
			$tabs['DataQuality/resolve.php'] = '<i class="fas fa-comments"></i> ' . $lang['dataqueries_148'] . $numOpenIssuesHtml;
			$tabs['DataQuality/metrics.php']  = '<i class="fas fa-chart-bar"></i> ' . $lang['dataqueries_194'];
			// Video link (only on Resolve Issues and Metrics pages)
			if (PAGE == 'DataQuality/metrics.php' || PAGE == 'DataQuality/resolve.php') {
				$videoLinks = 	RCView::div(array('style'=>'max-width:700px;text-align:right;padding-bottom:10px;'),
									'<i class="fas fa-film"></i> ' .
									RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('data_resolution_workflow01.swf','".js_escape($lang['dataqueries_137'])."');"),
										$lang['global_80'] . " " . $lang['dataqueries_137']
									) .
									RCView::span(array('style'=>'color:gray;margin:0 5px;'), $lang['global_47']) .
									RCView::a(array('href'=>'javascript:;', 'style'=>'color:#800000;text-decoration:underline;', 'onclick'=>"openDataResolutionIntroPopup();"),
										$lang['dataqueries_274']
									)
								);
			}
		}
		// Render the tabs
		RCView::renderTabs($tabs);
		// Render video links (if applicable)
		print $videoLinks;
	}

	// Validate data resolution issue type (open, closed). Set as default value if invalid.
	private function validateDataResIssueType($issueStatusType)
	{
		// Validate issue status. If not valid, set to default.
		if (!in_array($issueStatusType, $this->validDataResStatuses)) {
			$issueStatusType = $this->defaultDataResStatus;
		}
		// Return status
		return $issueStatusType;
	}

	// Obtain all data issues (either open or closed or null) for a given rule. Optionally limit to single record-event
	// Return as array with record-event as key.
	public function getDataIssuesByRule($rule_id, $record=null, $event_id=null)
	{
		global $Proj;
		// Place info in array to return
		$dataIssues = array();
		// Set subquery for rule_id/field
		$rule_sql = "";
		if (is_numeric($rule_id)) {
			// Determine if custom rule contains one field in logic
			$ruleContainsOneField = $this->ruleContainsOneField($rule_id);
			if ($ruleContainsOneField !== false) {
				// Custom rule with one field in logic (so consider it rule-less as field-level)
				$rule_sql = "and s.field_name = '".db_escape($ruleContainsOneField)."'";
			} else {
				// Custom rule-level (multiple fields)
				$rule_sql = "and s.rule_id = $rule_id";
			}
		}
		// If limiting by single record-event, add subquery
		$recevt_sql = (is_numeric($event_id) && $record != null) ? "and s.record = '".db_escape($record)."' and s.event_id = $event_id" : "";
		// Pull all issues of given rule from table
		$sql = "select x.*, y.response from (select s.*, count(1) as num_comments, max(r.res_id) as res_id_max
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.status_id = r.status_id
				$recevt_sql $rule_sql group by s.status_id
				order by abs(s.record), s.record, s.event_id, s.field_name) x, redcap_data_quality_resolutions y
				where x.res_id_max = y.res_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Remove some elements not needed in array
			$record = $row['record'];
			$event_id = $row['event_id'];
			unset($row['record'], $row['event_id'], $row['project_id']);
			// If field_name no longer exists, then ignore this orphaned result
			if ($row['field_name'] != '' && !isset($Proj->metadata[$row['field_name']])) continue;
			// Add to array
			if ($row['field_name'] == '') {
				// Custom rule w/ multiple fields in logic
				$dataIssues[$record][$event_id] = $row;
			} else {
				// Field-level, pre-defined rule, or custom rule w/ only one field in logic
				$dataIssues[$record][$event_id][$row['field_name']] = $row;
			}
		}
		// Return array of all issues
		return $dataIssues;
	}

	// Obtain all data issues (either open or closed) - get only most recent for record-event-field/rule
	public function getDataIssuesByStatus($issueStatusType='')
	{
		global $user_rights, $Proj, $double_data_entry;
		// Put issues into array
		$this->dataIssues = array();
		// Validate issue status
		$issueStatusType = $issueStatusTypeProper = $this->validateDataResIssueType($issueStatusType);
		// For sub-statuses of OPEN, set issueStatusTypeProper to OPEN and leave issueStatusType as original value
		if (substr($issueStatusType, 0, 5) == 'OPEN_') {
			$issueStatusTypeProper = 'OPEN';
		}
		// Pull all issues of given status from table
		$sql = "select x.*, a.ts as ts_first, a.user_id as user_id_first, a.comment as comment_first,
				b.ts as ts_last, b.user_id as user_id_last, b.comment as comment_last,
				if ('$issueStatusTypeProper'='CLOSED' or b.response is null, 0, 1) as responded
				from (select s.*, count(1) as num_comments, min(r.res_id) as res_id_first, max(r.res_id) as res_id_last
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . ($issueStatusType == '' ? "" : " and s.query_status = '$issueStatusTypeProper'") . "
				and s.status_id = r.status_id group by s.status_id) x,
				redcap_data_quality_resolutions a, redcap_data_quality_resolutions b
				where x.res_id_first = a.res_id and x.res_id_last = b.res_id";
		if ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0) {
			$sql .= " and x.record like '%--{$user_rights['double_data']}'";
		}
		$sql .= " order by abs(x.record), x.record, x.event_id, x.field_name";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Remove DDE ending of record, if using DDE
			$row['record'] = removeDDEending($row['record']);
			// If user is in a DAG, then only add records that are in the user's DAG
			if ($user_rights['group_id'] != '' && (!isset($this->dag_records[$row['record']])
				|| (isset($this->dag_records[$row['record']]) && $this->dag_records[$row['record']] != $user_rights['group_id'])))
			{
				continue;
			}
			// If field_name no longer exists, then ignore this orphaned result
			if ($row['field_name'] != '' && !isset($Proj->metadata[$row['field_name']])) continue;
			// If status is sub-status of OPEN, filter by sub-status
			if ($issueStatusType == 'OPEN_RESPONDED' && $row['responded'] == '0') continue;
			if ($issueStatusType == 'OPEN_UNRESPONDED' && $row['responded'] == '1') continue;
			// Remove some elements not needed in array
			$status_id = $row['status_id'];
			unset($row['status_id'], $row['project_id']);
			// Add to array
			$this->dataIssues[$status_id] = $row;
		}
		// Return array of all issues
		return $this->dataIssues;
	}

	// Determine if a DQ custom rule's logic contains a single field from the project (excludes pre-defined rules).
	// Return field name on true, else false.
	public function ruleContainsOneField($rule_id)
	{
		// Load the DQ custom rules
		$this->loadRules();
		// If rule_id is not numeric or doesn't exist, return false
		if (!is_numeric($rule_id) || !isset($this->rules[$rule_id])) return false;
		// Get the logic for this single rule
		if (isset($this->rules[$rule_id]['contains_one_field']) && !empty($this->rules[$rule_id]['contains_one_field'])) {
			return $this->rules[$rule_id]['contains_one_field'];
		} else {
			return false;
		}
	}

	// Render the html for displaying the resolution table
	public function renderResolutionTable($issueStatusType='', $fieldRuleFilter='', $event_id='', $group_id='', $assigned_user_id='', $returnCSV=false)
	{
		global $Proj, $lang, $user_rights, $longitudinal;
		// Increase memory limit in case needed for lots of output
		System::increaseMemory(2048);
		// Set max comment length to display in table
		$maxCommentLen = 100;
		// Create array to store all fields involved for this status (key=field, value=count)
		$fieldsThisStatus = array();
		// Put user_id=>username in array so we don't have to query each every time
		$userids = array();
		// Create array to store all rules involved for this status (key=rule, value=count)
		$rulesThisStatus = array();
		// Load the DQ custom rules
		$this->loadRules();
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Validate group_id input
		if (!isset($dags[$group_id])) $group_id = '';
		// Load array of records as key with their corresponding DAG as value
		if (!empty($dags)) $this->loadDagRecords();
		// Retrieve all data resolution info to fill the table
		$dataIssues = $this->getDataIssuesByStatus($issueStatusType);
		// If longitudinal and filtering by event, then remove results not in that event
		if ($longitudinal && $event_id != '')
		{
			// Loop though all results
			foreach ($dataIssues as $key=>$results)
			{
				if ($event_id != $results['event_id']) {
					// Remove result if not on the selected event
					unset($dataIssues[$key]);
				}
			}
		}
		// If DAGs exist, then reorder results grouped by DAG
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			// Add group_id, record, and event_id to arrays so we can do a multisort to sort them by DAG
			$groupRecEvts = $dataIssues2 = array();
			// Loop though all results
			foreach ($dataIssues as $key=>$results)
			{
				// Get group_id for this result (if exists)
				$this_group_id = (isset($this->dag_records[$results['record']])) ? $this->dag_records[$results['record']] : '0';
				// If filtering by DAG, then ignore any issues for records not in this DAG
				if ($group_id != '' && $group_id != $this_group_id) {
					unset($dataIssues[$key]);
					continue;
				}
				// Add values to respective arrays
				$groupRecEvts[$key] = $this_group_id . "-" . $results['record'] . "-" . $results['event_id'];
			}
			// Sort according to group, record, event
			asort($groupRecEvts);
			// Now sort the results by DAG, thus grouping them by DAG in the list
			foreach (array_keys($groupRecEvts) as $key) {
				$dataIssues2[$key] = $dataIssues[$key];
			}
			// Replace arrays and unset things no longer needed
			$dataIssues = $dataIssues2;
			unset($groupRecEvts, $results, $dataIssues2);
		}
		// Obtain custom record label & secondary unique field labels for ALL records.
		$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords();
		// Loop through all data resolution rows
		$resData = array();
		if ($returnCSV) {
			// Add CSV headers
			$resData[] = array(
				        $lang['dataqueries_310'],
						$lang['dataqueries_299'],
						$lang['global_49'] . ((!empty($dags) && $user_rights['group_id'] == "") ? " " . $lang['dataqueries_26'] : ""), // Record
                        $lang['global_78'], // DAG
                        $lang['global_141'], // Event
						strip_tags(str_replace("\n", " ", br2nl($lang['dataqueries_311']))),
						strip_tags(str_replace("\n", " ", br2nl($lang['graphical_view_23']))),
						$lang['dataqueries_176'],
						$lang['dataqueries_175'],
						$lang['dataqueries_177'],
						$lang['dataqueries_178'],
                        $lang['dataqueries_312'],
                        $lang['dataqueries_313']
					);
		}
		foreach ($dataIssues as $status_id=>&$attr)
		{
			// If first comment is over X characters, then truncate with ellipsis
			if (!$returnCSV && strlen($attr['comment_first']) > $maxCommentLen) {
				$attr['comment_first'] = substr($attr['comment_first'], 0, $maxCommentLen-2) . "...";
			}
			// If last comment is over X characters, then truncate with ellipsis
			if (!$returnCSV && strlen($attr['comment_last']) > $maxCommentLen) {
				$attr['comment_last'] = substr($attr['comment_last'], 0, $maxCommentLen-2) . "...";
			}
			// If is assigned to user, then get username info and also put in $userids array
			$userAssignedItem = RCView::span(array('style'=>'color:gray;margin-left:10px;'), '-');
			if ($attr['assigned_user_id'] != '') {
				$user_assigned = $userids[$attr['assigned_user_id']] = (isset($userids[$attr['assigned_user_id']]))
							? $userids[$attr['assigned_user_id']] : User::getUserInfoByUiid($attr['assigned_user_id']);
				$userAssignedItem = RCView::span(array('class'=>'wrap','style'=>'color:#800000;'), $user_assigned['username']);
			}
			// Concatenate first user, time, and comment into single column
			$user_first = $userids[$attr['user_id_first']] = (isset($userids[$attr['user_id_first']]))
						? $userids[$attr['user_id_first']] : User::getUserInfoByUiid($attr['user_id_first']);
			if ($returnCSV) {
				$firstItem = "{$user_first['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts_first']) . ")" . $lang['colon'] .
							' "'.$attr['comment_first'].'"';
			} else {
				$firstItem = RCView::div(array('class'=>'dq_daglabel','style'=>'font-size:11px;'),
								"{$user_first['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts_first']) . ")" . $lang['colon']
							) .
							RCView::div(array('style'=>'line-height:11px;color:#444;padding:4px 0 2px;'),
								'"'.RCView::escape($attr['comment_first'],false).'"'
							);
			}
			// Concatenate last user, time, and comment into single column
			$timeRaised = $attr['ts_first'];
            $timeResolved = '';
			if ($attr['res_id_last'] == $attr['res_id_first']) {
				// Last is same as first, so note that
				$lastItem = $returnCSV ? $lang['dataqueries_192'] : RCView::div(array('style'=>'color:#999;'), $lang['dataqueries_192']);
			} else {
				$user_last = $userids[$attr['user_id_last']] = (isset($userids[$attr['user_id_last']]))
							? $userids[$attr['user_id_last']] : User::getUserInfoByUiid($attr['user_id_last']);
				if ($returnCSV) {
					if ($attr['query_status'] == 'CLOSED') $timeResolved = $attr['ts_last'];
					$lastItem = "{$user_last['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts_last']) . ")" . $lang['colon'] .
								' "'.$attr['comment_last'].'"';
				} else {
					$lastItem = RCView::div(array('class'=>'dq_daglabel','style'=>'font-size:11px;'),
									"{$user_last['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts_last']) . ")" . $lang['colon']
								) .
								RCView::div(array('style'=>'line-height:11px;color:#444;padding:4px 0 2px;'),
									'"'.RCView::escape($attr['comment_last'],false).'"'
								);
				}
			}
			$field_label = '';
			// Display the field name (if field-level) or rule name (if rule-level)
			if ($attr['field_name'] != '') {
				// If field label is long, truncate it
				$field_label = strip_tags($Proj->metadata[$attr['field_name']]['element_label']);
				if (!$returnCSV && strlen($field_label) > $this->maxFieldLabelLen) {
					$field_label = substr($field_label, 0, $this->maxFieldLabelLen-2) . "...";
				}
				// Field-level: Display variable and its label
				$fieldRule = RCView::div(array('class'=>'wrap'),
								$lang['reporting_49']." <b>{$attr['field_name']}</b> " .
								RCView::div(array('style'=>'color:#777;'),
									'(' . RCView::escape($field_label) . ')'
								)
							 );
				$rule_id = '';
				// Add fieldname to array and/or increment count
				if (isset($fieldsThisStatus[$attr['field_name']])) {
					$fieldsThisStatus[$attr['field_name']]++;
				} else {
					$fieldsThisStatus[$attr['field_name']] = 1;
				}
				$fieldRuleText = '';
			} else {
				// Rule-level: Display rule name
				$rule_id = ($attr['pd_rule_id'] != '') ? 'pd-'.$attr['pd_rule_id'] : $attr['rule_id'];
				$this_rule = $this->rules[$rule_id];
				$fieldRule = RCView::div(array('class'=>'wrap','style'=>'color:#800000;'),
								"<b>" . $lang['dataqueries_14'] . " " . (is_numeric($rule_id) ? '#' : '') .
								$this_rule['order'] . $lang['colon'] . "</b> " . $this_rule['name']
							 );
				$fieldRuleText = $lang['dataqueries_14'] . " " . (is_numeric($rule_id) ? '#' : '') . $this_rule['order'] . $lang['colon'] . " " . $this_rule['name'];
				// If also field-level, then display field name as well
				if ($attr['field_name'] != '') {
					// If field label is long, truncate it
					$field_label = strip_tags($Proj->metadata[$attr['field_name']]['element_label']);
					if (!$returnCSV && strlen($field_label) > $this->maxFieldLabelLen) {
						$field_label = substr($field_label, 0, $this->maxFieldLabelLen-2) . "...";
					}
					$fieldRule .= RCView::div(array('class'=>'wrap'),
									$lang['reporting_49']." <b>{$attr['field_name']}</b> " .
									RCView::div(array('style'=>'color:#777;'),
										'(' . RCView::escape($field_label) . ')'
									)
								  );
				}
				// Add rule to array and/or increment count
				if (isset($rulesThisStatus[$rule_id])) {
					$rulesThisStatus[$rule_id]++;
				} else {
					$rulesThisStatus[$rule_id] = 1;
				}
			}
			// Add filter to limit by rule or fieldname
			if ($fieldRuleFilter != '') {
				if ($fieldRuleFilter == 'all-rules' && $attr['rule_id'] == '' && $attr['pd_rule_id'] == '') {
					// All rules
					continue;
				} elseif ($fieldRuleFilter == 'all-fields' && ($attr['rule_id'] != '' || $attr['pd_rule_id'] != '')) {
					// All fields
					continue;
				} elseif (is_numeric($fieldRuleFilter) && $fieldRuleFilter != $attr['rule_id']) {
					// Custom rule
					continue;
				} elseif (substr($fieldRuleFilter, 0, 3) == 'pd-' && is_numeric(substr($fieldRuleFilter, 3)) && substr($fieldRuleFilter, 3) != $attr['pd_rule_id']) {
					// Pre-defined rule
					continue;
				} elseif (isset($Proj->metadata[$fieldRuleFilter]) && $fieldRuleFilter != $attr['field_name']) {
					// Field name
					continue;
				}
			}
			// If filtering by assigned user, then skip this loop if not assigned to that user
			if ($assigned_user_id != '--NOTASSIGNED--' && $assigned_user_id != '' && $assigned_user_id != $attr['assigned_user_id']) continue;
			if ($assigned_user_id == '--NOTASSIGNED--' && $attr['assigned_user_id'] != '') continue;
			
			// Display instance number if a repeating form/event
			$instanceLabel = ($Proj->isRepeatingEvent($attr['event_id']) || $Proj->isRepeatingForm($attr['event_id'], $Proj->metadata[$attr['field_name']]['form_name']))
							? " (#{$attr['instance']})"
							: "";

			// Set record label text (append w/ DAG or Event or Custom Record Label or Secondary PK, when applicable)
			if ($attr['field_name'] == '') {
				// Provide link to the record home page
				$record_label = RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/record_home.php?pid=".PROJECT_ID."&arm={$Proj->eventInfo[$attr['event_id']]['arm_num']}&id={$attr['record']}", 'style'=>'text-decoration:underline;'), $attr['record'].$instanceLabel);
			} else {
				// Provide link to the field on the form
				$record_label = RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&instance={$attr['instance']}&event_id={$attr['event_id']}&id={$attr['record']}&page=".$Proj->metadata[$attr['field_name']]['form_name']."&fldfocus={$attr['field_name']}#{$attr['field_name']}-tr", 'style'=>'text-decoration:underline;'), $attr['record'].$instanceLabel);
			}
			// Display custom record label or secondary unique field (if applicable)
			$record_label .= (isset($extra_record_labels[$attr['record']]) ? '&nbsp;&nbsp;' . $extra_record_labels[$attr['record']] : '');
			// Show event name if longitudinal
            $this_event = '';
			if ($longitudinal && isset($Proj->eventInfo[$attr['event_id']])) {
				$this_event = $Proj->eventInfo[$attr['event_id']]['name_ext'];
				$record_label .= " ".RCView::div(array('class'=>'dq_evtlabel'), $this_event);
			}
			// Show DAG label if record is in a DAG
			if (!empty($dags) && isset($this->dag_records[$attr['record']]) && $user_rights['group_id'] == "")
			{
				$group_name = $dags[$this->dag_records[$attr['record']]];
				$record_label .= " ".RCView::div(array('class'=>'dq_daglabel'), "($group_name)");
			}
			// Set "comments" text label
			$this_comment_text = ($attr['num_comments'] != 1) ? $lang['dataqueries_02'] : $lang['dataqueries_01'];
			// Set balloon icon for buttons
			$thisIssueStatusType = $attr['query_status'];
			if ($thisIssueStatusType == 'OPEN_UNRESPONDED' || ($thisIssueStatusType == 'OPEN' && !$attr['responded'])) {
				$balloonIcon = 'balloon_exclamation.gif';
			} elseif ($thisIssueStatusType == 'OPEN_RESPONDED' || ($thisIssueStatusType == 'OPEN' && $attr['responded'])) {
				$balloonIcon = 'balloon_exclamation_blue.gif';
			} elseif ($thisIssueStatusType == 'VERIFIED') {
				$balloonIcon = 'tick_circle.png';
			} elseif ($thisIssueStatusType == 'DEVERIFIED') {
				$balloonIcon = 'exclamation_red.png';
			} else {
				$balloonIcon = 'balloon_tick.gif';
			}
			// Calculate the number of days the query has been open
			if ($thisIssueStatusType == 'CLOSED') {
				// For closed queries, do datediff from when opened till when closed
				$daysOpen = rounddown(datediff($attr['ts_first'],$attr['ts_last'],"d"),1);
			} elseif ($thisIssueStatusType == 'VERIFIED' || $thisIssueStatusType == 'DEVERIFIED') {
				// For just [de]verified, do not show anything
				$daysOpen = "-";
			} else {
				// For open queries, do datediff from when opened till now
				$daysOpen = rounddown(datediff($attr['ts_first'],NOW,"d"),1);
			}
			// Add row to table
			if ($returnCSV) {
				// CSV
				$resData[] = array(
					            $thisIssueStatusType,
								$attr['num_comments'],
					            $attr['record'].$instanceLabel,
					            $group_name,
					            $this_event,
								trim(strip_tags($fieldRuleText)),
                                ($attr['field_name'] == '' ? '' : $attr['field_name']." (".trim(strip_tags($field_label)).")"),
								trim(strip_tags($userAssignedItem)),
								$daysOpen,
								$firstItem,
								$lastItem,
					            $timeRaised,
				                $timeResolved
							 );
			} else {
				// HTML
				$resData[] = array(
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;',
									'onclick'=>"dataResPopup('{$attr['field_name']}',{$attr['event_id']},'".js_escape($attr['record'])."',1,'$rule_id',{$attr['instance']});"),
									RCView::img(array('src'=>$balloonIcon,'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'),
										$attr['num_comments']." $this_comment_text"
									)
								),
								RCView::div(array('class'=>'wrap'), $record_label),
								$fieldRule,
								$userAssignedItem,
								$daysOpen,
								RCView::div(array('class'=>'wrap'), $firstItem),
								RCView::div(array('class'=>'wrap'), $lastItem)
							 );
			}
			unset($dataIssues[$status_id], $attr);
		}
		// If there are no rows to display, then give informational row
		if (empty($resData)) {
			if ($returnCSV) {
				$resData[] = array($lang['dataqueries_190'], '-', '-', '-', '-', '-', '-');
			} else {
				$resData[] = array(RCView::div(array('class'=>'wrap','style'=>'color:#777;padding:5px;font-size:12px;'),
								$lang['dataqueries_190']), '-', '-', '-', '-', '-', '-');
			}
		}
		// CSV export only
		if ($returnCSV) {
			return arrayToCsv($resData, false);
		}
		// Get a count of issues by type
		$queryStatuses = $this->countDataResIssues();
		// Construct drop-down of resolution statuses
		$resStatusDropdown = RCView::select(array('id'=>'choose_status_type', 'style'=>'margin-left:5px;font-size:11px;',
								'onchange'=>"dataResLogReload(1);"),
								array(''=>$lang['dataqueries_300']." (".(array_sum($queryStatuses)-$queryStatuses['OPEN']).")",
									  'VERIFIED'=>"{$lang['dataqueries_220']} ({$queryStatuses['VERIFIED']})",
									  'DEVERIFIED'=>"{$lang['dataqueries_222']} ({$queryStatuses['DEVERIFIED']})",
									  'OPEN'=>"{$lang['dataqueries_186']} ({$queryStatuses['OPEN']})",
									  'OPEN_UNRESPONDED'=>" - {$lang['dataqueries_187']} ({$queryStatuses['OPEN_UNRESPONDED']})",
									  'OPEN_RESPONDED'=>" - {$lang['dataqueries_188']} ({$queryStatuses['OPEN_RESPONDED']})",
									  'CLOSED'=>"{$lang['dataqueries_189']} ({$queryStatuses['CLOSED']})"
								), $issueStatusType, 200
							 );
		// Construct drop-down of resolution statuses
		$fieldsRulesDropdownOptions  = "<option value=''>{$lang['dataqueries_185']}</option>";
		$fieldsRulesDropdownOptions .= "<option value='all-rules' ".($fieldRuleFilter == 'all-rules' ? 'selected' : '').">{$lang['dataqueries_184']}</option>";
		$fieldsRulesDropdownOptions .= "<option value='all-fields' ".($fieldRuleFilter == 'all-fields' ? 'selected' : '').">{$lang['dataqueries_183']}</option>";
		if (!empty($rulesThisStatus)) {
			$fieldsRulesDropdownOptions .= "<optgroup label='" . js_escape($lang['dataqueries_191']) . "'></optgroup>";
			foreach ($rulesThisStatus as $thisRule=>$thisCount) {
				$optionChecked = ($fieldRuleFilter == $thisRule) ? 'selected' : '';
				$ruleName = $lang['dataqueries_14'] . " " . (is_numeric($thisRule) ? '#' : '') .
							$this->rules[$thisRule]['order'] . $lang['colon'] . " " . $this->rules[$thisRule]['name'];
				$fieldsRulesDropdownOptions .= "<option value='$thisRule' $optionChecked> &nbsp; &nbsp; $ruleName ($thisCount)</option>";
			}
		}
		if (!empty($fieldsThisStatus)) {
			$fieldsRulesDropdownOptions .= "<optgroup label='" . js_escape($lang['dataqueries_182']) . "'></optgroup>";
			foreach ($fieldsThisStatus as $thisField=>$thisCount) {
				$optionChecked = ($fieldRuleFilter == $thisField) ? 'selected' : '';
				$fieldsRulesDropdownOptions .= "<option value='$thisField' $optionChecked> &nbsp; &nbsp; $thisField ($thisCount)</option>";
			}
		}
		$fieldsRulesDropdown = 	"<select id='choose_field_rule' style='margin-left:5px;font-size:11px;' onchange=\"dataResLogReload(1);\">
									$fieldsRulesDropdownOptions
								</select>";
		// Construct DAG drop-down list (if user is not in a DAG)
		$dagDropdown = '';
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			$dagDropdownOptions = array(''=>$lang['dataqueries_135']);
			foreach ($dags as $this_group_id=>$this_group_name) {
				$dagDropdownOptions[$this_group_id] = $this_group_name;
			}
			$dagDropdown = RCView::select(array('id'=>'choose_dag', 'style'=>'margin-left:5px;font-size:11px;',
								'onchange'=>"dataResLogReload(1);"), $dagDropdownOptions, $group_id);
		}
		// Construct event drop-down list (if longitudinal only)
		$eventDropdown = '';
		if ($longitudinal)
		{
			$eventDropdownOptions = array(''=>$lang['dataqueries_136']);
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$eventDropdownOptions[$this_event_id] = $attr['name_ext'];
			}
			$eventDropdown = RCView::select(array('id'=>'choose_event', 'style'=>'margin-left:5px;font-size:11px;',
								'onchange'=>"dataResLogReload(1);"), $eventDropdownOptions, $event_id);

		}
		// Construct assigned user drop-down list (if any were assigned)
		$assignedUserDropdown = '';
		if (!empty($userids))
		{
			$userDropdownOptions  = "<option value=''>{$lang['dataqueries_158']}</option>";
			$optionChecked = ($assigned_user_id == '--NOTASSIGNED--') ? 'selected' : '';
			$userDropdownOptions .= "<option value='--NOTASSIGNED--' $optionChecked>{$lang['dataqueries_159']}</option>";
			$userDropdownOptions .= "<optgroup label='" . js_escape($lang['dataqueries_181']) . "'></optgroup>";
			foreach ($userids as $attr) {
				$optionChecked = ($assigned_user_id == $attr['ui_id']) ? 'selected' : '';
				$userDropdownOptions .= "<option value='{$attr['ui_id']}' $optionChecked> &nbsp; &nbsp; {$attr['username']} ({$attr['user_firstname']} {$attr['user_lastname']})</option>";
			}
			$assignedUserDropdown = "<select id='choose_assigned_user' style='margin-left:5px;font-size:11px;' onchange=\"dataResLogReload(1);\">
										$userDropdownOptions
									</select>";
		}
		// Set up the table headers
		$hdrs = array(
					array(110, RCView::span(array('class'=>'wrap','style'=>'color:#888;'), $lang['dataqueries_173']), "center"),
					array(89, RCView::span(array('class'=>'wrap'), "<b>{$lang['global_49']}</b>" . ((!empty($dags) && $user_rights['group_id'] == "") ? "<br>" . $lang['dataqueries_26'] : ""))),
					array(170, RCView::span(array('class'=>'wrap'), "<b>{$lang['dataqueries_174']}</b>")),
					array(83, "<b>{$lang['dataqueries_176']}</b>"),
					array(30, RCView::span(array('class'=>'wrap'), "<b>{$lang['dataqueries_175']}</b>"), 'center', 'float'),
					array(190, "<b>{$lang['dataqueries_177']}</b>"),
					array(190, "<b>{$lang['dataqueries_178']}</b>")
				);
		// Set title of table
		$resTableTitle = RCView::div(array('style'=>'font-size:14px;float:left;padding:5px 0 0 10px;'),
							$lang['dataqueries_180'] .
							RCView::div(array('style'=>'margin:10px 0 5px;'),
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:12px;', 
									'onclick'=>"window.location.href = app_path_webroot+'DataQuality/resolve_csv_export.php'+window.location.search;"),
									RCView::img(array('src'=>'xls.gif')) .
									RCView::span(array('style'=>'vertical-align:middle;'), $lang['global_71'])
								)
							)
						) .
						// Filters
						RCView::div(array('style'=>'font-size:11px;float:left;font-weight:normal;margin-left:50px;padding-top:2px;'),
							// Resolution statuses
							RCView::div(array('style'=>'float:left;width:45px;padding-top:4px;'), $lang['dataqueries_179']) .
							RCView::div(array('style'=>'float:left;'), $resStatusDropdown) .
							RCView::div(array('style'=>'clear:both;margin-left:45px;'), $fieldsRulesDropdown) .
							RCView::div(array('style'=>'margin-left:45px;'), $eventDropdown) .
							RCView::div(array('style'=>'margin-left:45px;'), $dagDropdown) .
							RCView::div(array('style'=>'margin-left:45px;'), $assignedUserDropdown)
						) .
						RCView::div(array('class'=>'clear'));
		// Return the html for displaying the resolution table
		return renderGrid("dq_resolution_table", $resTableTitle, 947, "auto", $hdrs, $resData, true, true, false);
	}

	// Return array of record, event_id, field name, and rule_id of a data query from the res_id provided
	public function getDataResAttributesFromResId($res_id)
	{
		$sql = "select s.record, s.event_id, s.field_name, s.rule_id
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.status_id = r.status_id	and r.res_id = ".checkNull($res_id)." limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			return db_fetch_assoc($q);
		} else {
			return false;
		}
	}

	// Data Resolution Workflow: Auto de-verify a data value (if already verified).
	// Provide array with record as key, event_id as 2nd key, field_name as 3rd key, and nothing as the value (ignored).
	// Return count of record/event/fields that were de-verified, and perform auto de-verify by
	// adding new row in DQ resolutions db table for each record/event/field.
	public static function dataResolutionAutoDeverify($data=array(), $project_id=null)
	{
		// If no data exists, then return
		if (empty($data)) return;
		// Get current user's ui_id
		if (defined("USERID")) {
			$userInitiator = User::getUserInfo(USERID);
			$ui_id = $userInitiator['ui_id'];
			$userid = USERID;
		} else {
			$uid_id = $userid = '';
		}
		// Get project_id
		if ($project_id == null && defined("PROJECT_ID")) {
			$project_id = PROJECT_ID;
		}
		// Save original event_id, if was set
		if (isset($_GET['event_id'])) $event_id_orig = $_GET['event_id'];
		if (isset($_GET['instance'])) $instance_orig = $_GET['instance'];
		// Set counter for items de-verified
		$num_deverified = 0;
		// Place all status_ids in array for those that will be de-verified
		$status_ids = array();
		// Obtain array of all events and all fields listed in $data to limit the query
		$events_sql = $fields_sql = array();
		foreach ($data as $this_record=>$rattr) {
			foreach ($rattr as $this_event_id=>$eattr) {
				$events_sql[] = $this_event_id;
				foreach ($eattr as $this_field=>$fattr) {
					$fields_sql[] = $this_field;
				}
			}
		}
		// Query to pull existing Verified record/event/fields
		$sql = "select status_id, record, event_id, field_name, instance
				from redcap_data_quality_status where query_status = 'VERIFIED'
				and non_rule = 1 and project_id = $project_id
				and record in (" . prep_implode(array_keys($data)) . ")
				and event_id in (" . prep_implode(array_unique($events_sql)) . ")
				and field_name in (" . prep_implode(array_unique($fields_sql)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If rule/event/field exists in $data, then add to status_ids array
			if (isset($data[$row['record']][$row['event_id']][$row['field_name']][$row['instance']])) {
				$status_ids[$row['status_id']] = array('r'=>$row['record'], 'e'=>$row['event_id'], 'f'=>$row['field_name'], 'i'=>$row['instance']);
			}
			// Remove values from array to conserve memory
			unset($data[$row['record']][$row['event_id']][$row['field_name']][$row['instance']]);
		}
		// Loop through all status ids and de-verify each
		foreach ($status_ids as $status_id=>$attr) {
			// Update the tables
			$sql = "update redcap_data_quality_status set query_status = 'DEVERIFIED'
					where status_id = $status_id";
			if (db_query($sql)) {
				// Insert new row into DQ resoulutions table
				$sql = "insert into redcap_data_quality_resolutions
						(status_id, ts, user_id, current_query_status) values
						($status_id, '".NOW."', ".checkNull($ui_id).", 'DEVERIFIED')";
				$q = db_query($sql);
				// Get autoid
				$res_id = db_insert_id();
				// Set vars
				$record = $attr['r'];
				$event_id = $attr['e'];
				$field = $attr['f'];
				$instance = $attr['i'];
				## Log this
				// Set data values as json_encoded
				$logDataValues = json_encode(array('status_id'=>$status_id,'res_id'=>$res_id,'record'=>$record,
												   'event_id'=>$event_id,'field'=>$field,'instance'=>$instance));
				// Set event_id in query string for logging purposes only
				$_GET['event_id'] = $event_id;
				$_GET['instance'] = $instance;
				// Log it
				Logging::logEvent($sql,"redcap_data_quality_resolutions","MANAGE",$record,$logDataValues,"De-verified data value","",$userid,$project_id);
				// Increment counter
				$num_deverified++;
			}
		}
		// Reset original event_id
		if (isset($instance_orig)) $_GET['instance'] = $instance_orig;
		if (isset($event_id_orig)) $_GET['event_id'] = $event_id_orig;
		// Return number deverified
		return $num_deverified;
	}

	// split a string into an array of space-delimited tokens, taking double-quoted and single-quoted strings into account
	public static function tokenizeQuoted($string, $quotationMarks='"\'') {
		$tokens = array();
		for ($nextToken=strtok($string, ' '); $nextToken!==false; $nextToken=strtok(' ')) {
			if (strpos($quotationMarks, $nextToken[0]) !== false) {
				if (strpos($quotationMarks, $nextToken[strlen($nextToken)-1]) !== false) {
					$tokens[] = substr($nextToken, 1, -1);
				} else {
					$tokens[] = substr($nextToken, 1) . ' ' . strtok($nextToken[0]);
				}
			} else {
				$tokens[] = $nextToken;
			}
		}
		return $tokens;
	}

	// Obtain entire Field Comment Log. Return array.
	public function getFieldCommentLog($record=null, $event_id=null, $field=null, $group_id=null)
	{
		global $user_rights, $Proj;
		// Place all comments into array
		$comments = array();
		// Limit records pulled only to those in user's Data Access Group
		$group_sql = "";
		if ($user_rights['group_id'] != "") {
			$group_sql = "and s.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])).")";
		} elseif (is_numeric($group_id)) {
			$group_sql = "and s.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $group_id)).")";
		}
		// Use filters to limit SQL
		$record_sql = $event_sql = $field_sql = $keyword_sql = "";
		if ($record != '') $record_sql = "and s.record = '".db_escape($record)."'";
		if (is_numeric($event_id)) $event_sql = "and s.event_id = '".db_escape($event_id)."'";
		if ($field != '') $field_sql = "and s.field_name = '".db_escape($field)."'";
		// Get field comments
		$sql = "select s.record, s.event_id, s.field_name, s.instance, r.comment, r.ts, i.username
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				right outer join redcap_user_information i on i.ui_id = r.user_id
				where s.project_id = ".PROJECT_ID." and s.status_id = r.status_id and s.query_status is null
				$group_sql $record_sql $event_sql $field_sql $keyword_sql and s.non_rule = 1
				order by s.event_id, s.field_name, r.res_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// If field no longer exists, then skip this one
			if (!isset($Proj->metadata[$row['field_name']])) continue;
			// Form Level Rights check: If user does not have rights to view the form contain this field, then skip it
			if (!SUPER_USER) {
				$this_field_form = $Proj->metadata[$row['field_name']]['form_name'];
				if (!isset($user_rights['forms'][$this_field_form])
					|| (isset($user_rights['forms'][$this_field_form]) && $user_rights['forms'][$this_field_form] == '0')) {
					continue;
				}
			}
			// Add values to array
			$comments[$row['record']][$row['event_id']][$row['instance']][$row['field_name']][]
				= array('ts'=>$row['ts'], 'username'=>$row['username'], 'comment'=>$row['comment']);
		}
		// Order by record
		natcaseksort($comments);
		// Return array of comments
		return $comments;
	}

	// Render table of Field Comment Log. Return html of table.
	public function renderFieldCommentLog($record=null, $event_id=null, $field=null, $group_id=null, $user=null, $keyword=null)
	{
		global $user_rights, $lang, $Proj, $longitudinal;
		// Check if any DAGs exist. If so, create a new column in the table for each DAG.
		$dags = $Proj->getGroups();
		// Validate group_id input
		if (!isset($dags[$group_id])) $group_id = '';
		// Load array of records as key with their corresponding DAG as value
		if (!empty($dags)) $this->loadDagRecords();
		// Obtain custom record label & secondary unique field labels for ALL records.
		$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(($record==null?array():array($record)));
		// Place all comments into array
		$rows = array();
		// Row counter
		$row_counter = 0;
		// Set max comment display length
		$max_comment_length = 200;
		// Set max comments to display per row
		$max_comments_per_row = 3;
		// Get the field comment log
		$comments = $this->getFieldCommentLog($record, $event_id, $field, $group_id);
		// Parse keywords into separate words (unless surrounded by quotes)
		$keyword_array = array();
		if ($keyword != '') {
			// Clean
			$keyword = str_replace(array("`","="), array("",""), $keyword);
			// Split the search terms up into an array so that quoted phrases get searched literally
			$keyword_array = self::tokenizeQuoted($keyword);
			// Remove any keywords that we should ignore
			foreach ($keyword_array as $this_key=>$this_keyword) {
				if (in_array($this_keyword, $this->FCL_keywords_ignore)) {
					unset($keyword_array[$this_key]);
				}
			}
		}
		// Set flag to perform keyword search
		$doKeywordSearch = (!empty($keyword_array));
		// Set flag to filter by user
		$filterByUser = ($user != null);
		if ($filterByUser) $user = strtolower($user);
		// Loop through all comments
		foreach ($comments as $this_record=>$rattr) {
			foreach ($rattr as $this_event_id=>$eattr) {
				foreach ($eattr as $this_instance=>$battr) {
					foreach ($battr as $this_field=>$fattr) {
						// Get count of comments for this record/event/field
						$num_comments = count($fattr);
						// Set counter to count comments for this record/event/field
						$comment_counter = 1;
						// Reset $comment
						$comment = '';
						// Set flag if found at least one keyword for each record/event/field
						$foundKeywordThisRow = false;
						// Set flag if found the user for this row that was selected in the filter
						$foundUserThisRow = false;
						// Loop through each comment for this record/event/field
						foreach ($fattr as $attr) {
							// Add values to array
							if (!isset($rows[$row_counter]))
							{
								// Display instance number if a repeating form/event
								$instanceLabel = ($Proj->isRepeatingEvent($this_event_id) || $Proj->isRepeatingForm($this_event_id, $Proj->metadata[$this_field]['form_name']))
												? " (#$this_instance)"
												: "";
								// Set record display (add event or DAG, if applicable)
								$record_display = RCView::a(array('target'=>'_blank', 'href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&instance=$this_instance&event_id=$this_event_id&id=$this_record&page=".$Proj->metadata[$this_field]['form_name']."&fldfocus=$this_field#$this_field-tr", 'style'=>'text-decoration:underline;'), 
													$this_record . $instanceLabel
												  )
												. (isset($extra_record_labels[$this_record]) ? " ".$extra_record_labels[$this_record] : '')
												. (($longitudinal && isset($Proj->eventInfo[$this_event_id])) ? "<div class='dq_evtlabel'>" . $Proj->eventInfo[$this_event_id]['name_ext'] . "</div>" : "");
								// Show DAG label if record is in a DAG
								if (!empty($dags) && isset($this->dag_records[$this_record]) && $user_rights['group_id'] == "")
								{
									$group_name = $dags[$this->dag_records[$this_record]];
									$record_display .= RCView::div(array('class'=>'dq_daglabel'), "($group_name)");
								}
								// Set field name and label (truncate if too long)
								$field_label = strip_tags($Proj->metadata[$this_field]['element_label']);
								if (strlen($field_label) > $this->maxFieldLabelLen) {
									$field_label = substr($field_label, 0, $this->maxFieldLabelLen-2) . "...";
								}
								$field_display = "<b>$this_field</b>" .
												RCView::div(array('style'=>'color:#777;'),
													'(' . RCView::escape($field_label) . ')'
												);
								// Set "comment" or "comments" text
								$this_comment_text = ($num_comments != 1) ? $lang['dataqueries_02'] : $lang['dataqueries_01'];
								// Begin new row
								$rows[$row_counter] = array(
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;',
										'onclick'=>"dataResPopup('$this_field',$this_event_id,'".js_escape($this_record)."',1,'','$this_instance');"),
										RCView::img(array('src'=>'balloon_left.png','style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											"$num_comments $this_comment_text"
										)
									),
									RCView::div(array('class'=>'wrap'), $record_display),
									RCView::div(array('class'=>'wrap'), $field_display),
									''
								);
							}

							// Set flag if user of current query item is the selected user in the user filter
							$foundUser = ($filterByUser && strtolower($attr['username']) == $user);
							if ($foundUser) $foundUserThisRow = true;

							// Set flag if at least one keyword was found in comment
							$foundKeyword = false;
							// Only display X comments max per record/event/field (except when doing keyword searches)
							if ($doKeywordSearch || $comment_counter <= $max_comments_per_row) {
								// If performing keyword search
								if ($doKeywordSearch) {
									// Initialize var to capture position of first keyword in comment
									$pos_first_keyword = false;
									// Go ahead and escape the comment first
									$attr['comment'] = RCView::escape($attr['comment'],false);
									// Loop through all keywords and replace with with same word with style
									foreach ($keyword_array as $this_keyword) {
										// Escape the keyword so it replaces fine since the comment is also already escaped
										$this_keyword = RCView::escape($this_keyword,false);
										// If found keyword...
										$pos_this_keyword = stripos($attr['comment'], $this_keyword);
										if ($pos_this_keyword !== false) {
											// Mark the keyword's position if occurs earlier in comment than other keywords thus far
											if ($pos_first_keyword === false || ($pos_first_keyword !== false && $pos_this_keyword < $pos_first_keyword)) {
												$pos_first_keyword = $pos_this_keyword;
											}
											// Replace the keyword with {RK{{keyword}}KR}
											$attr['comment'] = str_ireplace($this_keyword, RCView::span(array('class'=>'keyword_search'), $this_keyword), $attr['comment']);
											// Set flag that a keyword was found in comment
											$foundKeyword = $foundKeywordThisRow = true;
										}
									}
									// If found at least one keyword, then display comment on this row
									if ($foundKeyword) {
										// Get position of first line break
										$pos_first_line_break = strpos($attr['comment'], "\n");
										// If the first keyword will not get displayed on first line of comment, then truncate comment at
										// beginning and prepend with ellipsis so that the first keyword is visible.
										$avgNumCharsPerRow = 60;
										if (strlen($attr['comment']) > $avgNumCharsPerRow && $pos_first_keyword > ($avgNumCharsPerRow-20)) {
											$attr['comment'] = "..." . substr($attr['comment'], $pos_first_keyword-25);
										}
										// If comment contains a natural line break, then truncate it there with ellipsis
										if ($pos_first_line_break !== false) {
											// If line break occurs BEFORE first keyword, then remove all up to that line break
											if ($pos_first_line_break < $pos_first_keyword) {
												$attr['comment'] = "..." . substr($attr['comment'], $pos_first_line_break+1);
												// If double ellipsis somehow ended up prepended, then remove one
												if (substr($attr['comment'], 0, 6) == "......") {
													$attr['comment'] = substr($attr['comment'], 3);
												}
											}
											// If line break occurs AFTER first keyword, then remove everything after that line break
											else {
												$attr['comment'] = substr($attr['comment'], 0, $pos_first_line_break) . "...";
											}
										}
										// Add comment
										$comment .=	RCView::div(array('class'=>'dq_daglabel','style'=>'font-size:11px;'),
														"{$attr['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts']) . ")" . $lang['colon']
													) .
													RCView::div(array('style'=>'margin-bottom:4px;line-height:11px;color:#444;text-overflow:ellipsis;'),
														'"'.nl2br($attr['comment']).'"'
													);
									}
								}
								// If keywords were NOT entered
								else {
									// If comment contains a natural line break, then truncate it there with ellipsis
									$pos_first_line_break = strpos($attr['comment'], "\n");
									if ($pos_first_line_break !== false) {
										$attr['comment'] = substr($attr['comment'], 0, $pos_first_line_break) . "...";
									}
									// Set comment
									$comment .=	RCView::div(array('class'=>'dq_daglabel','style'=>'font-size:11px;'),
													"{$attr['username']} (" . DateTimeRC::format_ts_from_ymd($attr['ts']) . ")" . $lang['colon']
												) .
												RCView::div(array('style'=>'margin-bottom:4px;line-height:11px;color:#444;text-overflow:ellipsis;'),
													'"'.nl2br(RCView::escape($attr['comment'],false)).'"'
												);
								}
							}
							// Finish off this last comment for this record/event/field
							if ($comment_counter == $num_comments)
							{
								// If filtering by user but thread does not contain user, then remove row from results
								if ($filterByUser && !$foundUserThisRow) {
									unset($rows[$row_counter]);
									$foundUserThisRow = false;
									continue;
								}
								// If searching for a keyword, but no keywords were found, then remove this row and start the next.
								if ($doKeywordSearch && !$foundKeywordThisRow) {
									unset($rows[$row_counter]);
									$foundKeywordThisRow = false;
									continue;
								}
								// Finally add comment(s) to existing row
								$rows[$row_counter][3] .= $comment;
								// If some comments are not displayed, tell how many are not being displayed.
								// Do NOT display number of hidden comments if searching by keyword.
								if ($num_comments > $max_comments_per_row && !$doKeywordSearch)
								{
									$num_comments_not_displayed = $num_comments - $max_comments_per_row;
									$num_comments_not_displayed_text = ($num_comments_not_displayed == 1 ) ? $lang['dataqueries_227'] : $lang['dataqueries_228'];
									$rows[$row_counter][3] .= RCView::div(array('style'=>'color:#999;'),
																"[$num_comments_not_displayed $num_comments_not_displayed_text]"
															  );
								}
								// Reset flag for next row (next record/event/field)
								$foundKeywordThisRow = $foundUserThisRow = false;
								// Increment row counter
								$row_counter++;
							}
							// Increment comment counter and row counter
							$comment_counter++;
						}
					}
				}
			}
		}

		// Count number of rows/results
		$numFclResults = count($rows);

		// If returned more results than the max, truncate results and give notice to filter current results more
		if ($numFclResults > $this->FCL_max_results) {
			// Remove all results AFTER the max
			$rows = array_slice($rows, 0, $this->FCL_max_results);
		}
		// If returned no results, display one row with message
		elseif ($numFclResults == 0) {
			$rows[] = array(RCView::div(array('class'=>'wrap','style'=>'padding:10px 0;color:#800000;'), $lang['dataqueries_233']),'-','-','-');
		}

		## Create drop-down list of all records
		$allRecords = Records::getRecordList(PROJECT_ID);
		$recordDropdownOptions = array(''=>$lang['reporting_37']);
		foreach ($allRecords as $this_key=>$this_record) {
			// Add custom record label and 2ndary PK to record list in drop-down
			$this_record_label = $this_record . (isset($extra_record_labels[$this_record]) ? " " . strip_tags($extra_record_labels[$this_record]) : "");
			// Add to options
			$recordDropdownOptions[$this_record] = $this_record_label;
			// Remove record from $allRecords to conserve memory
			unset($allRecords[$this_key]);
		}
		$recordDropdown = RCView::select(array('id'=>'choose_record', 'style'=>'margin-bottom:1px;max-width:300px;font-size:11px;'), $recordDropdownOptions, $record);

		## Construct event drop-down list (if longitudinal only)
		$eventDropdown = '';
		if ($longitudinal)
		{
			$eventDropdownOptions = array(''=>$lang['dataqueries_136']);
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$eventDropdownOptions[$this_event_id] = $attr['name_ext'];
			}
			$eventDropdown = RCView::span(array('style'=>'color:#777;margin:0 4px 0 2px;'), $lang['data_entry_67']) .
							 RCView::select(array('id'=>'choose_event', 'style'=>'margin-bottom:1px;max-width:300px;font-size:11px;',
								'onchange'=>""), $eventDropdownOptions, $event_id);
		}

		## Construct field name drop-down list
		$fieldDropdown = '';
		$fieldDropdownOptions = array(''=>$lang['dataqueries_183']);
		foreach ($Proj->metadata as $this_field=>$attr) {
			$fieldDropdownOptions[$this_field] = $this_field . " (" . $attr['element_label'] . ")";
		}
		$fieldDropdown = RCView::select(array('id'=>'choose_field', 'style'=>'margin-bottom:1px;max-width:300px;font-size:11px;',
							'onchange'=>""), $fieldDropdownOptions, $field);

		## Construct DAG drop-down list (if user is not in a DAG)
		$dagDropdown = '';
		if (!empty($dags) && $user_rights['group_id'] == "")
		{
			$dagDropdownOptions = array(''=>$lang['dataqueries_135']);
			foreach ($dags as $this_group_id=>$this_group_name) {
				$dagDropdownOptions[$this_group_id] = $this_group_name;
			}
			$dagDropdown = RCView::select(array('id'=>'choose_dag', 'style'=>'margin-bottom:1px;max-width:300px;font-size:11px;',
								'onchange'=>""), $dagDropdownOptions, $group_id);
		}

		## Construct User drop-down list
		$userDropdown = '';
		$userDropdownOptions = array(''=>$lang['control_center_182']);
		foreach (User::getProjectUsernames() as $this_user) {
			$userDropdownOptions[$this_user] = $this_user;
		}
		$userDropdown = RCView::select(array('id'=>'choose_user', 'style'=>'margin-bottom:1px;max-width:300px;font-size:11px;',
							'onchange'=>""), $userDropdownOptions, $user);

		## Construct "keyword search" textbox
		$keywordTextbox = RCView::text(array('id'=>'choose_keyword','style'=>'font-size:11px;','placeholder'=>$lang['dataqueries_229'],'value'=>$keyword));

		// Set up the table headers
		$hdrs = array(
					array(110, RCView::span(array('class'=>'wrap','style'=>'color:#888;'), $lang['dataqueries_226']), "center"),
					array(110, "<b>{$lang['global_49']}</b>"),
					array(143, "<b>{$lang['graphical_view_23']}</b>"),
					array(338, RCView::span(array('class'=>'wrap'), "<b>{$lang['dataqueries_146']}</b>"))
				);
		// Set title of table
		$tableTitle = RCView::div(array('style'=>'width:170px;font-size:13px;float:left;padding:5px 0 0 10px;'),
							$lang['dataqueries_141'] .
							RCView::div(array('style'=>'padding-left:10px;font-size:13px;margin-top:20px;color:#C00000;font-weight:normal;'),
								"{$lang['dataqueries_234']} <b>$numFclResults</b>" .
								($numFclResults <= $this->FCL_max_results ? ""
									: RCView::div(array('style'=>'color:#888;font-size:11px;margin-top:10px;line-height:12px;'),
										"{$lang['dataqueries_235']} = {$this->FCL_max_results} {$lang['dataqueries_237']}<br>{$lang['dataqueries_236']}"
									  )
								)
							)
						 ) .
						// Filters
						RCView::div(array('style'=>'font-size:11px;float:left;font-weight:normal;padding-top:2px;'),
							// Drop-downs to filter results
							RCView::div(array('style'=>'float:left;width:45px;padding-top:4px;'), $lang['dataqueries_179']) .
							RCView::div(array('style'=>'float:left;'),
								$recordDropdown .
								$eventDropdown
							) .
							RCView::div(array('style'=>'clear:both;margin-left:45px;'), $fieldDropdown) .
							RCView::div(array('style'=>'clear:both;margin-left:45px;'), $userDropdown) .
							RCView::div(array('style'=>'margin-left:45px;'), $dagDropdown) .
							RCView::div(array('style'=>'margin-left:45px;'),
								$keywordTextbox .
								RCView::a(array('href'=>'javascript:;','onclick'=>'openFieldCommentLogSearchTips();','style'=>'margin-left:5px;text-decoration:underline;font-weight:normal;font-size:11px;color:#800000;'), $lang['dataqueries_231'])
							) .
							RCView::div(array('style'=>'margin-left:45px;'),
								RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:2px;font-size:11px;color:#800000;','onclick'=>"reloadFieldCommentLog(1);"), $lang['survey_442']) .
								RCView::a(array('href'=>APP_PATH_WEBROOT."DataQuality/field_comment_log.php?pid=".PROJECT_ID,'style'=>'margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
							)
						) .
						// Button to download comment log as CSV
						RCView::div(array('style'=>'float:right;padding:5px 10px 0 3px;'),
							RCView::button(array('class'=>'jqbuttonmed','style'=>'','onclick'=>"window.location.href=app_path_webroot+'DataQuality/field_comment_log_export.php?pid='+pid"),
								RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
								RCView::span(array('style'=>'vertical-align:middle;'), $lang['dataqueries_238'])
							)
						) .
			RCView::div(array('class'=>'clear'), '&nbsp;');
		// Return the html for displaying the resolution table
		return renderGrid("dq_field_comment_table", $tableTitle, 750, "auto", $hdrs, $rows, true, true, false);
	}

	// Output Field Comment Log as CSV. Return CSV-formatted string.
	public function getFieldCommentLogCSV($record=null, $event_id=null, $field=null, $group_id=null)
	{
		global $user_rights, $lang, $Proj, $longitudinal;
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Set CSV header
		if ($longitudinal) {
			$hdr = array($lang['global_49'], $lang['global_10'], $lang['graphical_view_23'], $lang['global_17'], $lang['global_55'], $lang['dataqueries_195']);
		} else {
			$hdr = array($lang['global_49'], $lang['graphical_view_23'], $lang['global_17'], $lang['global_55'], $lang['dataqueries_195']);
		}
		fputcsv($fp, $hdr);
		// Get the field comment log
		$comments = $this->getFieldCommentLog($record, $event_id, $field, $group_id);
		// Loop through all comments
		foreach ($comments as $this_record=>$rattr) {
			foreach ($rattr as $this_event_id=>$eattr) {
				foreach ($eattr as $this_instance=>$battr) {
					foreach ($battr as $this_field=>$fattr) {
						// Display instance number if a repeating form/event
						$instanceLabel = ($Proj->isRepeatingEvent($this_event_id) || $Proj->isRepeatingForm($this_event_id, $Proj->metadata[$this_field]['form_name']))
										? " (#$this_instance)"
										: "";
						foreach ($fattr as $attr) {
							// Set this line of CSV data
							if ($longitudinal) {
								$line = array($this_record.$instanceLabel, $Proj->eventInfo[$this_event_id]['name_ext'], $this_field, $attr['username'], $attr['ts'], $attr['comment']);
							} else {
								$line = array($this_record.$instanceLabel, $this_field, $attr['username'], $attr['ts'], $attr['comment']);
							}
							// Write this line to CSV file
							fputcsv($fp, $line);
						}
					}
				}
			}
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		// Output the file contents
		return addBOMtoUTF8(stream_get_contents($fp));
	}

	// Output HTML for displaying the DRW detailed instructions (used in dialog pop-up)
	public static function renderDRWinstructions()
	{
		global $lang;
		// Video link
		return 	RCView::div(array('style'=>'text-align:right;margin:10px 20px 0;'),
					'<i class="fas fa-film"></i> ' .
					RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('data_resolution_workflow01.swf','".js_escape($lang['dataqueries_137'])."');"),
						$lang['global_80'] . " " . $lang['dataqueries_137']
					)
				) .
				// Set user privileges
				RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:5px 0 2px;'), "1) ".$lang['dataqueries_262']) .
				RCView::div(array('style'=>''), $lang['dataqueries_263']) .
				// Opening queries
				RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), "2) ".$lang['dataqueries_264']) .
				RCView::div(array('style'=>''), $lang['dataqueries_266'].RCView::br().RCView::br().$lang['dataqueries_272']) .
				// Responding to and closing queries
				RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), "3) ".$lang['dataqueries_267']) .
				RCView::div(array('style'=>''), $lang['dataqueries_268']) .
				// Using the Resolve Issues page
				RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), "4) ".$lang['dataqueries_265']) .
				RCView::div(array('style'=>''), $lang['dataqueries_269']) .
				// View metrics
				RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), "5) ".$lang['dataqueries_270']) .
				RCView::div(array('style'=>''), $lang['dataqueries_271']);
	}

	// Calculate the average number of days that a query was resolved (from time opened to time closed)
	public function calculateAvgTimeToQueryResolution()
	{
		$sql = "select round(avg(z.sec_to_resolve)/86400,1) as avg_days_to_resolve
				from (select TIMESTAMPDIFF(SECOND, x.min_ts, max(y.ts)) as sec_to_resolve
				from (select s.status_id, min(r.ts) as min_ts from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.query_status = 'CLOSED' and r.status_id = s.status_id
				and r.current_query_status = 'OPEN' group by s.status_id) x, redcap_data_quality_resolutions y
				where x.status_id = y.status_id group by y.status_id) z";
		$q = db_query($sql);
		$numDays = (db_num_rows($q) ? db_result($q, 0) : 0);
		return (is_numeric($numDays) ? $numDays : 0);
	}

	// Calculate the average number of days that a query was responded by (from time opened)
	public function calculateAvgTimeForQueryResponse()
	{
		$sql = "select round(avg(z.sec_to_respond)/86400,1) as avg_days_to_respond
				from (select TIMESTAMPDIFF(SECOND, y.open_ts, x.response_ts) as sec_to_respond
				from (select s.status_id, min(r.ts) as response_ts from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.query_status in ('OPEN', 'CLOSED')
				and r.status_id = s.status_id and r.current_query_status = 'OPEN' and r.response is not null
				group by s.status_id) x, (select s.status_id, min(r.ts) as open_ts from redcap_data_quality_status s,
				redcap_data_quality_resolutions r where s.project_id = " . PROJECT_ID . "
				and s.query_status in ('OPEN', 'CLOSED') and r.status_id = s.status_id and r.current_query_status = 'OPEN'
				group by s.status_id) y where x.status_id = y.status_id group by x.status_id) z";
		$q = db_query($sql);
		$numDays = (db_num_rows($q) ? db_result($q, 0) : 0);
		return (is_numeric($numDays) ? $numDays : 0);
	}

	// Calculate the average number of days that a query is/was open  (include both open and closed queries)
	public function calculateAvgTimeQueryOpen()
	{
		$sql = "select round(avg(z.sec_open )/86400,1) as avg_sec_open
				from (select TIMESTAMPDIFF(SECOND, min(r.ts), '".NOW."') as sec_open
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.query_status = 'OPEN'
				and r.status_id = s.status_id and r.current_query_status = 'OPEN'
				group by s.status_id) z";
		$q = db_query($sql);
		$numDays = (db_num_rows($q) ? db_result($q, 0) : 0);
		return (is_numeric($numDays) ? $numDays : 0);
	}

	// Delete a field comment
	public function deleteFieldComment($res_id)
	{
		// First, confirm that this res_id belongs to this project
		$sql = "select s.record, s.event_id, s.field_name, r.comment
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and r.status_id = s.status_id
				and r.res_id = $res_id limit 1";
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		// Get values
		$row = db_fetch_assoc($q);
		// Delete from table
		$sql = "delete from redcap_data_quality_resolutions where res_id = $res_id";
		$q = db_query($sql);
		if (db_affected_rows() == 1) {
			// Log the deletion
			$logDataValues = json_encode(array('res_id'=>$res_id,'record'=>$row['record'],'event_id'=>$row['event_id'],
								'field'=>$row['field_name'],'comment'=>html_entity_decode($row['comment'], ENT_QUOTES)));
			// Log it
			Logging::logEvent("","redcap_data_quality_resolutions","MANAGE",$row['record'],$logDataValues,"Delete field comment");
			return true;
		} else {
			return false;
		}
	}

	// Edit a field comment
	public function editFieldComment($res_id, $comment)
	{
		// First, confirm that this res_id belongs to this project
		$sql = "select s.record, s.event_id, s.field_name
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and r.status_id = s.status_id
				and r.res_id = $res_id limit 1";
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		// Get values
		$row = db_fetch_assoc($q);
		// Delete from table
		$sql = "update redcap_data_quality_resolutions set comment = '".db_escape($comment)."',
				field_comment_edited = 1 where res_id = $res_id";
		if (db_query($sql)) {
			// Log the deletion
			$logDataValues = json_encode(array('res_id'=>$res_id,'record'=>$row['record'],'event_id'=>$row['event_id'],
								'field'=>$row['field_name'],'comment'=>$comment));
			// Log it
			Logging::logEvent("","redcap_data_quality_resolutions","MANAGE",$row['record'],$logDataValues,"Edit field comment");
			return true;
		} else {
			return false;
		}
	}
	
	// Return the field label used in pre-defined DQ rule results
	private function getFieldLabelForDiscrepantResults($field, $Proj) 
	{
		$field_label = strip_tags(br2nl(label_decode($Proj->metadata[$field]['element_label'])));
		if (strlen($field_label) > 50) $field_label = substr($field_label, 0, 48) . "...";
		return '"<i>'.RCView::escape($field_label).'</i>"<br>&nbsp&nbsp;&nbsp;&nbsp;&nbsp;';
	}

}
