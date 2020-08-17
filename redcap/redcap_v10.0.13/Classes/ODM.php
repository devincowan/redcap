<?php

class ODM
{
	// Table mappings
	private static $tableMappings =	array(
										'redcap_surveys'=>array(
											'event_id'	=>	array('pdf_econsent_firstname_event_id', 'pdf_econsent_lastname_event_id', 'pdf_econsent_dob_event_id'),
											'file'		=>	array('logo', 'confirmation_email_attachment')
										),										
										'redcap_surveys_scheduler'=>array(
											'event_id'	=>	array('event_id', 'condition_surveycomplete_event_id'),
											'survey_id'	=>	array('survey_id', 'condition_surveycomplete_survey_id')
										),										
										'redcap_surveys_queue'=>array(
											'event_id'	=>	array('event_id', 'condition_surveycomplete_event_id'),
											'survey_id'	=>	array('survey_id', 'condition_surveycomplete_survey_id')
										),										
										'redcap_record_dashboards'=>array(
											'event_id'	=>	array('sort_event_id')
										),										
										'redcap_ddp_mapping'=>array(
											'event_id'	=>	array('event_id')
										),
                                        'redcap_alerts'=>array(
                                            'event_id'	=>	array('form_name_event'),
                                            'file'		=>	array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5')
                                        )
									);

	// Return array of all field types considered multiple choice for ODM export (exclude checkbox
	public static function getMcFieldTypes()
	{
		return array("sql", "checkbox", "advcheckbox", "radio", "select", "dropdown", "yesno", "truefalse");
	}


	// Return ODM RangeCheck tags for a given field based upon REDCap field attributes
	public static function getOdmRangeCheck($field_attr)
	{
		global $lang;
		$RangeCheck = "";
		if ($field_attr['element_validation_type'] != '' && ($field_attr['element_validation_min'] != '' || $field_attr['element_validation_max'] != '')) {
			// Set error message
			$errorMsgMin = $field_attr['element_validation_min'] != '' ? $field_attr['element_validation_min'] : $lang['config_functions_91'];
			$errorMsgMax = $field_attr['element_validation_max'] != '' ? $field_attr['element_validation_max'] : $lang['config_functions_91'];
			$errorMsg = $lang['config_functions_57'] . " ($errorMsgMin - $errorMsgMax)" . $lang['period'] . " " . $lang['config_functions_58'];
			// Min
			if ($field_attr['element_validation_min'] != '') {
				$RangeCheck .= "\t\t<RangeCheck Comparator=\"GE\" SoftHard=\"Soft\">\n";
				$RangeCheck .= "\t\t\t<CheckValue>{$field_attr['element_validation_min']}</CheckValue>\n";
				$RangeCheck .= "\t\t\t<ErrorMessage><TranslatedText>".RCView::escape($errorMsg)."</TranslatedText></ErrorMessage>\n";
				$RangeCheck .= "\t\t</RangeCheck>\n";
			}
			// Max
			if ($field_attr['element_validation_max'] != '') {
				$RangeCheck .= "\t\t<RangeCheck Comparator=\"LE\" SoftHard=\"Soft\">\n";
				$RangeCheck .= "\t\t\t<CheckValue>{$field_attr['element_validation_max']}</CheckValue>\n";
				$RangeCheck .= "\t\t\t<ErrorMessage><TranslatedText>".RCView::escape($errorMsg)."</TranslatedText></ErrorMessage>\n";
				$RangeCheck .= "\t\t</RangeCheck>\n";
			}
		}
		return $RangeCheck;
	}


	// Return StudyOID and StudyName derived from REDCap project title
	public static function getStudyOID($project_title, $prependProjectWord=true)
	{
		return ($prependProjectWord ? "Project." : "") . substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30);
	}


	// Return array of miscellaneous optional field attributes
	public static function getOptionalFieldAttr()
	{
		// Back-end name => front-end name
		return array('branching_logic'=>'branching_logic', 'custom_alignment'=>'custom_alignment', 'question_num'=>'question_number',
					 'grid_name'=>'matrix_group_name', 'misc'=>'field_annotation');
	}


	// Return all metadata fields (export format version) as array
	public static function getOdmExportFields($Proj, $outputSurveyFields=false, $outputDags=false, $outputDescriptiveFields=false)
	{
		// Put all export fields in array to return
		$all_fields = array();
		// First, add record ID field
		$all_fields[] = $Proj->table_pk;
		// Add DAG field?
		if ($outputDags) {
			$all_fields[] = 'redcap_data_access_group';
		}
		// Add survey identifier?
		if ($outputSurveyFields) {
			$all_fields[] = 'redcap_survey_identifier';
		}
		// Add all other fields
		$prev_form = "";
		foreach ($Proj->metadata as $this_field=>$attr)
		{
			// Skip record ID field (already added)
			if ($this_field == $Proj->table_pk) continue;
			// Set form
			$this_form = $Proj->metadata[$this_field]['form_name'];
			// Add survey timestamp?
			if ($outputSurveyFields && $this_form != $prev_form && isset($Proj->forms[$this_form]['survey_id'])) {
				$all_fields[] = $this_form . '_timestamp';
			}
			// If a checkbox field, then loop through choices to render pseudo field names for each choice
			if ($attr['element_type'] == 'checkbox')
			{
				foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $this_value) {
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$all_fields[] = Project::getExtendedCheckboxFieldname($this_field, $this_value);
				}
			} elseif ($attr['element_type'] != 'descriptive' || $outputDescriptiveFields) {
				// Add to array if not an invalid export field type
				$all_fields[] = $this_field;
			}
			// Set for next loop
			$prev_form = $this_form;
		}
		// Return field array
		return $all_fields;
	}


	// Return ODM Item Groups as array for determing what "item group" fields belong to
	public static function getOdmItemGroups($Proj, $outputSurveyFields=false, $outputDags=false, $outputDescriptiveFields=false)
	{
		// Set array containing special reserved field names that won't be in the project metadata
		$survey_timestamps = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp");
		// Get all export field names
		$fields = self::getOdmExportFields($Proj, $outputSurveyFields, $outputDags, $outputDescriptiveFields);
		// Store as array
		$itemGroup = array();
		// Loop through all forms, sections, and fields
		$prev_form = $prev_section = null;
		foreach ($fields as $this_field) {
			// If record ID field, then add it and any extra fields, if needed
			if ($this_field == $Proj->table_pk)
			{
				$this_form = $Proj->metadata[$this_field]['form_name'];
				$current_key = "$this_form.$this_field";
				$itemGroup[$current_key] = array($this_field);
				if ($outputDags) {
					$itemGroup[$current_key][] = 'redcap_data_access_group';
				}
				if ($outputSurveyFields) {
					$itemGroup[$current_key][] = 'redcap_survey_identifier';
					if (isset($Proj->forms[$this_form]['survey_id'])) {
						$itemGroup[$current_key][] = $this_form.'_timestamp';
					}
				}
			}
			// Non-record ID field
			else
			{
				// Is a real field or pseudo-field?
				$this_field_var = $Proj->getTrueVariableName($this_field);
				$is_survey_timestamp = false;
				if ($this_field_var === false) {
					$is_survey_timestamp = ($outputSurveyFields && in_array($this_field, $survey_timestamps));
					// Go to next field/loop
					if (!$is_survey_timestamp) continue;
				}
				$this_section = "";

				// If a survey timestamp, then this is the beginning of a new form
				if ($is_survey_timestamp)
				{
					$this_form = substr($this_field, 0, -10);
					$current_key = "$this_form.$this_field";
					$itemGroup[$current_key][] = $this_field;
				}
				// Normal field
				else
				{
					// Get field attributes
					$field_attr = $Proj->metadata[$this_field_var];
					$this_form = $field_attr['form_name'];
					if ($field_attr['element_preceding_header'] != '') {
						$this_section = $field_attr['element_preceding_header'];
					}
					// Is a new form or new section?
					$newForm = $prev_form."" !== $this_form."";
					$newSection = $prev_section."" !== $this_section."";
					// If a new item group (either a section header or the beginning of a new form)
					if ($newSection || $newForm) {
						$current_key = "$this_form.$this_field";
					}
					// Add to array
					$itemGroup[$current_key][] = $this_field;
				}
			}
			// Set for next loop
			$prev_form = $this_form;
			$prev_section = $this_section;
		}
		// Return array
		return $itemGroup;
	}


	// Return ODM MetadataVersion section
	public static function getMetadataVersionOID($app_title)
	{
		// Build MetadataVersionOID and return
		return "Metadata." . self::getStudyOID($app_title, false) . "_" . substr(str_replace(array(":"," "), array("","_"), NOW), 0, -2);
	}


	// Return array of specific project-level attributes to include in ODM and their database field counterparts
	public static function getProjectAttrMappings($prependRedcapInTag=true, $creatingProject=false, $includeSurveyQueueCustomText=false, $includeDDPFieldMapping=false)
	{
		$redcapPrepend = ($prependRedcapInTag ? "redcap:" : "");
		$array = array(
			// ODM tag name => db field name in redcap_projects table
			$redcapPrepend . 'RecordAutonumberingEnabled' => 'auto_inc_set',
			$redcapPrepend . 'CustomRecordLabel' => 'custom_record_label',
			$redcapPrepend . 'SecondaryUniqueField' => 'secondary_pk',
			$redcapPrepend . 'SchedulingEnabled' => 'scheduling',
			$redcapPrepend . 'SurveysEnabled' => 'surveys_enabled',
			$redcapPrepend . 'SurveyInvitationEmailField' => 'survey_email_participant_field'				
		);
		if (!$creatingProject) {
			$array[$redcapPrepend . 'Purpose'] = 'purpose';
			$array[$redcapPrepend . 'PurposeOther'] = 'purpose_other';
			$array[$redcapPrepend . 'ProjectNotes'] = 'project_note';
		}
		if ($includeSurveyQueueCustomText) {
			$array[$redcapPrepend . 'SurveyQueueCustomText'] = 'survey_queue_custom_text';
		}
		if ($includeDDPFieldMapping) {
			$array[$redcapPrepend . 'DdpType'] = 'realtime_webservice_type';
			$array[$redcapPrepend . 'DdpOffsetDays'] = 'realtime_webservice_offset_days';
			$array[$redcapPrepend . 'DdpOffsetPlusMinus'] = 'realtime_webservice_offset_plusminus';
		}
		$array[$redcapPrepend . 'MissingDataCodes'] = 'missing_data_codes';
		return $array;
	}


	// Return any project-level attributes to include in GlobalVariables
	public static function getProjectAttrGlobalVars($Proj, $includeSurveyQueueCustomText=false, $includeDDPFieldMapping=false)
	{
		// Is DDP mapping set?
		if ($includeDDPFieldMapping) {
			$DDP = new DynamicDataPull($Proj->project_id, $Proj->project['realtime_webservice_type']);
			$includeDDPFieldMapping = ((($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())) && $DDP->isMappingSetUp());
		}
		// Add single variable mappings (from redcap_projects table)
		$attr = "";
		$mappings = self::getProjectAttrMappings(true, false, $includeSurveyQueueCustomText, $includeDDPFieldMapping);
		foreach ($mappings as $tag=>$value) {
			$attr .= "\t<$tag>" . RCView::escape($Proj->project[$value]) . "</$tag>\n";
		}
		// If project has repeating forms/events, add repeating setup
		if ($Proj->hasRepeatingFormsEvents()) {
			$attr .= "\t<redcap:RepeatingInstrumentsAndEvents>\n";
			foreach ($Proj->getRepeatingFormsEvents() as $event_id=>$forms) {
				if ($Proj->longitudinal) {
					$event_name = $Proj->getUniqueEventNames($event_id);
				} else {
					// If classic, give it the default event name 
					$event_name = "event_1_arm_1";
				}
				if (is_array($forms)) {
					$attr .= "\t\t<redcap:RepeatingInstruments>\n";
					foreach ($forms as $form=>$custom_label) {
						if (!isset($Proj->forms[$form])) continue;
						$attr .= "\t\t\t<redcap:RepeatingInstrument redcap:UniqueEventName=\"$event_name\" redcap:RepeatInstrument=\"$form\" redcap:CustomLabel=\"".RCView::escape($custom_label)."\"/>\n";
					}
					$attr .= "\t\t</redcap:RepeatingInstruments>\n";
				} else {
					$attr .= "\t\t<redcap:RepeatingEvent redcap:UniqueEventName=\"$event_name\"/>\n";
				}
			}
			$attr .= "\t</redcap:RepeatingInstrumentsAndEvents>\n";
		}
		// Return XML string
		return $attr;
	}


	// Return ODM for Data Quality rules
	public static function getOdmDataQualityRules()
	{
		$dq = new DataQuality();
		$dq_rules = $dq->getRules();
		$array = array();
		$rule_order = 1;
		foreach ($dq_rules as $key=>$attr) {
			if (!is_numeric($key)) continue;
			$array['redcap_data_quality_rules'][] = array('rule_order'=>$rule_order++, 'rule_name'=>$attr['name'], 'rule_logic'=>$attr['logic'], 'real_time_execute'=>$attr['real_time_execute']);
		}
		return self::getOdmFromArray($array);
	}

	// Return ODM for Data Access Groups
	public static function getOdmDataAccessGroups()
	{
		$Proj = new Project(PROJECT_ID);
		$dags = $Proj->getGroups();
		$array = array();
		foreach ($dags as $dag) {
			$array['redcap_data_access_groups'][] = array('group_name'=>$dag);
		}
		return self::getOdmFromArray($array);
	}

    // Return ODM for Alerts & Notifications
    public static function getOdmAlerts()
    {
        $alert = new Alerts();
        $alerts = $alert->getAlertSettings();
        $Proj = new Project();
        $array = array();
        foreach ($alerts as $attr) {
            unset($attr['alert_id']);
            if ($attr['form_name_event'] != "") {
                $attr['form_name_event'] = $Proj->getUniqueEventNames($attr['form_name_event']);
            }
            // Add any attachments
            $alertAttachFields = array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5');
            foreach ($alertAttachFields as $thisAttachField) {
                // Convert logo to base64
                if ($attr[$thisAttachField] != '') {
                    // Get contents of edoc file as a string
                    list ($mimeType, $docName, $base64data) = Files::getEdocContentsAttributes($attr[$thisAttachField]);
                    if ($base64data !== false) {
                        // Put inside CDATA as base64encoded
                        $base64data = "<![CDATA[" . base64_encode($base64data) . "]]>";
                        // Set unique id to identify this file
                        $attr[$thisAttachField] = sha1(rand());
                        // Add as base64Binary data type
                        $array['__files'][$attr[$thisAttachField]] = array('MimeType' => $mimeType, 'DocName' => $docName, 'Content' => $base64data);
                    }
                }
            }
            // Add to array
            $array['redcap_alerts'][] = $attr;
        }
        return self::getOdmFromArray($array);
    }

	// Return ODM for User Roles
	public static function getOdmUserRoles()
	{
		$roles = UserRights::getRoles();
		$array = array();
		foreach ($roles as $attr) {
			unset($attr['role_id'], $attr['project_id']);
			$array['redcap_user_roles'][] = $attr;
		}
		return self::getOdmFromArray($array);
	}

	// Return ODM for Reports
	public static function getOdmReports()
	{
		$Proj = new Project(PROJECT_ID);
		$dags = $Proj->getUniqueGroupNames();
		$reports = DataExport::getReports();
		$array = array();
		foreach ($reports as $report_id=>$attr) {
			// Since we're not copying over users or worrying about user access, set user access to ALL
			$attr['user_access'] = 'ALL';
			$attr['user_edit_access'] = 'ALL';
			// Convert all field-level logic to advanced logic for simplicity
			if ($attr['advanced_logic'] == '' && $attr['limiter_logic'] != '') {
				$attr['advanced_logic'] = $attr['limiter_logic'];
			}
			// Longitudinal: Make sure [event-name] gets prepended to all fields lacking a prepended unique event name
            if ($Proj->longitudinal && $attr['advanced_logic'] != '') {
				$attr['advanced_logic'] = LogicTester::logicPrependEventName($attr['advanced_logic'], 'event-name', $Proj);
            }
			// Remove unneeded attributes
			unset($attr['report_id'], $attr['project_id']);
			// Set sub-arrays as comma-limited attributes to be parsed differently
			$attr['redcap_reports_fields'] = implode(",", $attr['fields']);
			foreach ($attr['limiter_dags'] as $key=>$group_id) {
				$attr['limiter_dags'][$key] = $dags[$group_id];
			}
			$attr['redcap_reports_filter_dags'] = implode(",", $attr['limiter_dags']);
			foreach ($attr['limiter_events'] as $key=>$event_id) {
				$attr['limiter_events'][$key] = $Proj->getUniqueEventNames($event_id);;
			}
			$attr['redcap_reports_filter_events'] = implode(",", $attr['limiter_events']);
			// Add hashed ID of the report_id for later reference
			$attr['ID'] = sha1($report_id);
			// Add to array
			$array['redcap_reports'][] = $attr;
		}
		return self::getOdmFromArray($array);
	}	

	// Return ODM for Record Status Dashboards
	public static function getOdmRecordDashboards()
	{
		$Proj = new Project(PROJECT_ID);
		$dashboards = RecordDashboard::getRecordDashboardsList();
		foreach ($dashboards as $rd_id=>$attr) {
			$event_id_forms = array();
			foreach (explode(",", $attr['selected_forms_events']) as $event_id_form) {
				list ($event_id, $form) = explode(":", $event_id_form, 2);
				$event_name = $Proj->getUniqueEventNames($event_id);
				$event_id_forms[] = $event_name.":".$form;
			}
			$attr['selected_forms_events'] = implode(",", $event_id_forms);
			$attr['sort_event_id'] = $Proj->getUniqueEventNames($attr['sort_event_id']);
			$array['redcap_record_dashboards'][] = $attr;
		}
		return self::getOdmFromArray($array);
	}	

	// Return ODM for DDP Field Mapping
	public static function getOdmDdpFieldMapping()
	{
		$Proj = new Project(PROJECT_ID);
		$DDP = new DynamicDataPull(PROJECT_ID, $Proj->project['realtime_webservice_type']);
		$ddpMappingComplete = ((($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())) && $DDP->isMappingSetUp());
		if (!$ddpMappingComplete) return '';
		// Get field mappings
		$ddpMapping = $DDP->getMappedFields();		
		$ddpMappingFlat = array();
		foreach ($ddpMapping as $sourceField=>$bttr) {
			foreach ($bttr as $event_id=>$cttr) {				
				foreach ($cttr as $rcField=>$attr) {
					$attr['external_source_field_name'] = $sourceField;
					$attr['event_id'] = $Proj->getUniqueEventNames($event_id);
					$attr['field_name'] = $rcField;
					unset($attr['map_id']);
					$array['redcap_ddp_mapping'][] = $attr;
				}
			}
		}
		// Also get preview fields
		$ddpPreviewFields = $DDP->getPreviewFields();
		$attr = array();
		for ($k = 1; $k <= 5; $k++) {
			$attr['field'.$k] = $ddpPreviewFields[$k-1];
		}
		$array['redcap_ddp_preview_fields'][] = $attr;
		// Return array
		return self::getOdmFromArray($array);
	}

	// Return ODM for Report Folders
	public static function getOdmReportFolders()
	{
		$report_folders = DataExport::getReportFolders(PROJECT_ID);
		$report_attr = DataExport::getReportNames();
		$reportFolderAssign = array();
		foreach ($report_attr as $attr) {
			if ($attr['folder_id'] == '') continue;
			$reportFolderAssign[$attr['folder_id']][] = sha1($attr['report_id']);
		}
		$array = array();
		$pos = 1;
		foreach ($report_folders as $folder_id=>$name) {
			// Add to array
			$array['redcap_reports_folders'][] = array('name'=>$name, 'position'=>$pos++, 
													   'redcap_reports_folders_items'=>implode(",", $reportFolderAssign[$folder_id]));
		}
		return self::getOdmFromArray($array);
	}

	// Return ODM for surveys
	public static function getOdmSurveys()
	{
		$Proj = new Project(PROJECT_ID);
		if (empty($Proj->surveys)) return "";
		$Surveys = array();
		foreach ($Proj->surveys as $attr) {
			// Convert event_ids to event names
			foreach ($attr as $field=>$val) {
				if ($val != "" && in_array($field, self::$tableMappings['redcap_surveys']['event_id'])) {
					$attr[$field] = $Proj->getUniqueEventNames($val);
				}
			}
			// Remove theme since they don't transfer from server to server
			$attr['theme'] = '';
			// Convert logo to base64
			if ($attr['logo'] != '') {
				// Get contents of edoc file as a string
				list ($mimeType, $docName, $base64data) = Files::getEdocContentsAttributes($attr['logo']);
				if ($base64data !== false) {
					// Put inside CDATA as base64encoded
					$base64data = "<![CDATA[" . base64_encode($base64data) . "]]>";
					// Set unique id to identify this file
					$attr['logo'] = sha1(rand());
					// Add as base64Binary data type
					$Surveys['__files'][$attr['logo']] = array('MimeType'=>$mimeType, 'DocName'=>$docName, 'Content'=>$base64data);
				}
			}
			// Convert confirmation_email_attachment to base64
			if ($attr['confirmation_email_attachment'] != '') {
				// Get contents of edoc file as a string
				list ($mimeType, $docName, $base64data) = Files::getEdocContentsAttributes($attr['confirmation_email_attachment']);
				if ($base64data !== false) {
					// Put inside CDATA as base64encoded
					$base64data = "<![CDATA[" . base64_encode($base64data) . "]]>";
					// Set unique id to identify this file
					$attr['confirmation_email_attachment'] = sha1(rand());
					// Add as base64Binary data type
					$Surveys['__files'][$attr['confirmation_email_attachment']] = array('MimeType'=>$mimeType, 'DocName'=>$docName, 'Content'=>$base64data);
				}
			}
			// Add to array
			$Surveys['redcap_surveys'][] = $attr;
		}
		return self::getOdmFromArray($Surveys);
	}

	// Return ODM for ASIs
	public static function getOdmAutomatedSurveyInvitations($leaveEnabled=false)
	{
		$Proj = new Project(PROJECT_ID);
		$surveyScheduler = new SurveyScheduler(PROJECT_ID);
		$surveyScheduler->setSchedules(true);
		// Get the ASI list as array as…
		$asi = $surveyScheduler->schedules;
		if (empty($asi)) return "";
		$array = array();
		foreach ($asi as $survey_id=>$bttr) {
			foreach ($bttr as $event_id=>$attr) {	
				// Add extra event/survey_id
				$attr['event_id'] = $event_id;
				$attr['survey_id'] = $survey_id;
				// Leave enabled?
				if (!$leaveEnabled) $attr['active'] = '0';
				// Convert event_ids and survey_ids to event names and form_names
				foreach ($attr as $field=>$val) {
					if ($val != "" && in_array($field, self::$tableMappings['redcap_surveys_scheduler']['event_id'])) {
						$attr[$field] = $Proj->getUniqueEventNames($val);
					} elseif ($val != "" && in_array($field, self::$tableMappings['redcap_surveys_scheduler']['survey_id'])) {
						$attr[$field] = $Proj->surveys[$val]['form_name'];
					}
				}
				$array['redcap_surveys_scheduler'][] = $attr;
			}
		}
		return self::getOdmFromArray($array);
	}

	// Return ODM for Survey Queue
	public static function getOdmSurveyQueue()
	{
		$Proj = new Project(PROJECT_ID);
		$sq = Survey::getProjectSurveyQueue(true);
		if (empty($sq)) return "";
		$array = array();
		foreach ($sq as $survey_id=>$bttr) {
			foreach ($bttr as $event_id=>$attr) {
				unset($attr['sq_id']);
				// Add extra event/survey_id
				$attr['event_id'] = $event_id;
				$attr['survey_id'] = $survey_id;			
				// Convert event_ids and survey_ids to event names and form_names
				foreach ($attr as $field=>$val) {
					if ($val != "" && in_array($field, self::$tableMappings['redcap_surveys_queue']['event_id'])) {
						$attr[$field] = $Proj->getUniqueEventNames($val);
					} elseif ($val != "" && in_array($field, self::$tableMappings['redcap_surveys_queue']['survey_id'])) {
						$attr[$field] = $Proj->surveys[$val]['form_name'];
					}
				}
				$array['redcap_surveys_queue'][] = $attr;
			}
		}
		return self::getOdmFromArray($array);
	}
	
	
	// Save to db table from associative array
	public static function addArrayToTable($array)
	{
		$Proj = new Project(PROJECT_ID);
		$miscAttr = array();
		foreach ($array as $table=>$attr) 
		{
			// Skip the pseudo-table
			if ($table == 'redcap_odm_attachment') continue;
			// Does table have project_id? If so, then add it to fields array
			$tableCols = getTableColumns($table);
			$addProjectId = array_key_exists('project_id', $tableCols);
			foreach ($attr as $k=>$fields) 
			{
				// Loop through each table row
				if ($table == 'redcap_reports') {
					$miscAttr[$table][$fields['ID']]['redcap_reports_fields'] = $fields['redcap_reports_fields'];
					$miscAttr[$table][$fields['ID']]['redcap_reports_filter_dags'] = $fields['redcap_reports_filter_dags'];
					$miscAttr[$table][$fields['ID']]['redcap_reports_filter_events'] = $fields['redcap_reports_filter_events'];
				} elseif ($table == 'redcap_reports_folders') {
					$miscAttr[$table][$k]['redcap_reports_folders_items'] = explode(",", $fields['redcap_reports_folders_items']);
				} elseif ($table == 'redcap_record_dashboards') {					
					$event_id_forms = array();
					foreach (explode(",", $fields['selected_forms_events']) as $event_form) {
						list ($event_name, $form) = explode(":", $event_form, 2);
						$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
						$event_id_forms[] = $event_id.":".$form;
					}
					$fields['selected_forms_events'] = implode(",", $event_id_forms);
				}
				// Remove fields that don't map to db table columns
				$fieldsOrig = $fields;
				foreach (array_keys($fields) as $field) {
					if (!array_key_exists($field, $tableCols)) {
						unset($fields[$field]);
					}
				}
				// If has any event names that need to be translated to event_ids, then do so
				if (isset(self::$tableMappings[$table]['event_id'])) 
				{
					foreach (self::$tableMappings[$table]['event_id'] as $thisField) {
						if (isset($fields[$thisField]) && $fields[$thisField] != "") {
							$fields[$thisField] = $Proj->getEventIdUsingUniqueEventName($fields[$thisField]);
						}
					}
				}
				// If has any form names that need to be translated to survey_ids, then do so
				if (isset(self::$tableMappings[$table]['survey_id'])) 
				{
					foreach (self::$tableMappings[$table]['survey_id'] as $thisField) {
						if (isset($fields[$thisField]) && $fields[$thisField] != "" && isset($Proj->forms[$fields[$thisField]])) {
							$fields[$thisField] = $Proj->forms[$fields[$thisField]]['survey_id'];
						}
					}
				}
				// If has any files, then import them now and replace with doc_id
				if (isset(self::$tableMappings[$table]['file'])) 
				{
					foreach (self::$tableMappings[$table]['file'] as $thisField) {
						if (isset($fields[$thisField]) && $fields[$thisField] != "") {
							$fileID = $fields[$thisField];
							if (isset($array['redcap_odm_attachment'][$fileID])) {
								// "Upload" the file to get the doc_id
								$odm_filename = APP_PATH_TEMP . date('YmdHis') . "_" . $array['redcap_odm_attachment'][$fileID]['DocName'];
								// Temporarily store file in temp
								file_put_contents($odm_filename, base64_decode($array['redcap_odm_attachment'][$fileID]['Content']));
								// Simulate a file upload for storing in edocs table
								$odm_file = array('name'=>basename($odm_filename), 'type'=>'application/csv', 
												  'size'=>filesize($odm_filename), 'tmp_name'=>$odm_filename);
								// Set value as new doc_id
								$fields[$thisField] = Files::uploadFile($odm_file);
								// Remove temp file
								unlink($odm_filename);
							}
						}
					}
				}
				// Add project_id, if needed
				if ($addProjectId) $fields['project_id'] = PROJECT_ID;
				// Insert to table
				$sql = "insert into $table (".implode(", ", array_keys($fields)).") values (".prep_implode($fields, true, true).")";
				db_query($sql);
				$insert_id = db_insert_id();
				// Table-specific actions
				if ($table == 'redcap_data_access_groups') 
				{
					// Add to groups array manually since we're in a transaction
					$Proj->groups[$insert_id] = $fields['group_name'];
				}
				elseif ($table == 'redcap_reports') 
				{
					$miscAttr[$table][$fieldsOrig['ID']]['report_id'] = $insert_id;
					$dags = $Proj->getUniqueGroupNames();
					foreach ($miscAttr[$table] as $mkey=>$mvals) {
						foreach ($mvals as $miscKey=>$miscVals) {
							$miscVals = explode(",", $miscVals);
							if ($miscKey == 'redcap_reports_fields') {
								$order = 1;
								foreach ($miscVals as $miscVal) {								
									$sql = "insert into $miscKey (report_id, field_name, field_order) 
											values ($insert_id, '".db_escape($miscVal)."', ".$order++.")";
									db_query($sql);
								}
							} elseif ($miscKey == 'redcap_reports_filter_dags' && !empty($dags)) {
								foreach ($miscVals as $miscVal) {
									$miscVal = array_search($miscVal, $dags);							
									$sql = "insert into $miscKey (report_id, group_id) 
											values ($insert_id, '".db_escape($miscVal)."')";
									db_query($sql);
								}							
							} elseif ($miscKey == 'redcap_reports_filter_events') {
								foreach ($miscVals as $miscVal) {
									$sql = "insert into $miscKey (report_id, event_id) 
											values ($insert_id, '".db_escape($Proj->getEventIdUsingUniqueEventName($miscVal))."')";
									db_query($sql);
								}
							}
							// Remove this so that it doesn't get re-queried
                            if ($miscKey != 'report_id') {
								unset($miscAttr[$table][$mkey][$miscKey]);
							}
						}
					}
				}
                elseif ($table == 'redcap_reports_folders')
                {
                    foreach ($miscAttr[$table][$k] as $miscKey=>$miscVals) {
                        foreach ($miscVals as $miscVal) {
                            $report_id = $miscAttr['redcap_reports'][$miscVal]['report_id'];
                            $sql = "insert into $miscKey (folder_id, report_id) 
										values ($insert_id, '".db_escape($report_id)."')";
                            db_query($sql);
                        }
                    }
                }
			}
			// If we just added surveys, then make sure $Proj gets updated with this new information
			if ($table == 'redcap_surveys') {
				$Proj->project['surveys_enabled'] = '1';
				$Proj->loadSurveys();
			}
		}
	}
	
	
	// Generate ODM from associative array
	public static function getOdmFromArray($array)
	{
		$xml = "";
		foreach ($array as $table=>$attr) 
		{
			$thisXml = "";			
			if ($table == '__files') {
				$tag = 'OdmAttachment';
			} else {
				$tag = camelCase(str_replace(array("redcap_","_"), array(""," "), $table));
			}
			foreach ($attr as $key1=>$fields) 
			{
				$thisXml .= "\t\t<redcap:{$tag}"; 
				foreach ($fields as $key=>$val) {
					if ($table == '__files' && $key == 'Content') {
						$thisXml .= " ID=\"".RCView::escape($key1)."\"";
					} else {
						$thisXml .= " $key=\"".RCView::escape(nl2crnl($val))."\"";
					}
				}
				if ($table == '__files') {
					$thisXml .= ">".$fields['Content']."</redcap:{$tag}>\n";	
				} else {
					$thisXml .= "/>\n";	
				}
			}		
			if ($thisXml != "") {
				$thisXml = "\t<redcap:{$tag}Group>\n$thisXml\t</redcap:{$tag}Group>\n";
			}
			$xml .= $thisXml;
		}
		return $xml;
	}

	// Return ODM MetadataVersion section
	public static function getOdmMetadata($Proj, $outputSurveyFields=false, $outputDags=false, $xml_metadata_options="")
	{
		// Set static array for reference
		$otherFieldAttrNames = self::getOptionalFieldAttr();
		// Get array of all item groups of fields
		$itemGroup = self::getOdmItemGroups($Proj, $outputSurveyFields, $outputDags, true);
		// Set array containing special reserved field names that won't be in the project metadata
		$survey_timestamps = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp");
		// Get any metadata options
		$xml_metadata_options = explode(",", $xml_metadata_options);
		// Opening study tag
		$xml = "<Study OID=\"" . self::getStudyOID($Proj->project['app_title']) . "\">\n";
		// Global variables and study definitions
		$xml .= "<GlobalVariables>\n"
			 . "\t<StudyName>".RCView::escape($Proj->project['app_title'])."</StudyName>\n"
			 . "\t<StudyDescription>This file contains the metadata, events, and data for REDCap project \"".RCView::escape($Proj->project['app_title'])."\".</StudyDescription>\n"
			 . "\t<ProtocolName>".RCView::escape($Proj->project['app_title'])."</ProtocolName>\n"
			 . self::getProjectAttrGlobalVars($Proj, (defined("API") || in_array('sq', $xml_metadata_options)), (defined("API") || in_array('ddpmapping', $xml_metadata_options)))
			 . (defined("API") || in_array('dqrules', $xml_metadata_options) ? self::getOdmDataQualityRules() : "")
			 . (defined("API") || in_array('dags', $xml_metadata_options) ? self::getOdmDataAccessGroups() : "")
			 . (defined("API") || in_array('surveys', $xml_metadata_options) ? self::getOdmSurveys() : "")
			 . (defined("API") || in_array('asi', $xml_metadata_options) ? self::getOdmAutomatedSurveyInvitations(in_array('asienable', $xml_metadata_options)) : "")
			 . (defined("API") || in_array('sq', $xml_metadata_options) ? self::getOdmSurveyQueue() : "")
			 . (defined("API") || in_array('userroles', $xml_metadata_options) ? self::getOdmUserRoles() : "")
			 . (defined("API") || in_array('reports', $xml_metadata_options) ? self::getOdmReports() : "")
			 . (defined("API") || in_array('reportfolders', $xml_metadata_options) ? self::getOdmReportFolders() : "")
			 . (defined("API") || in_array('recorddashboards', $xml_metadata_options) ? self::getOdmRecordDashboards() : "")
			 . (defined("API") || in_array('ddpmapping', $xml_metadata_options) ? self::getOdmDdpFieldMapping() : "")
             . (defined("API") || in_array('alerts', $xml_metadata_options) ? self::getOdmAlerts() : "")
			 . "</GlobalVariables>\n";
		// $xml .= "<BasicDefinitions/>\n";
		// MetaDataVersion outer tag
		$xml .= "<MetaDataVersion OID=\"" . self::getMetadataVersionOID($Proj->project['app_title']) . "\" Name=\"".RCView::escape($Proj->project['app_title'])."\""
			 .  " redcap:RecordIdField=\"{$Proj->table_pk}\">\n";
		// Protocol and StudyEventRef (longitudinal only)
		if ($Proj->longitudinal)
		{
			$xml .= "\t<Protocol>\n";
			$OrdNum = 1;
			$uniqueEvents = $Proj->getUniqueEventNames();
			foreach ($uniqueEvents as $this_event_name) {
				$xml .= "\t\t<StudyEventRef StudyEventOID=\"Event.$this_event_name\" OrderNumber=\"".$OrdNum++."\" Mandatory=\"No\"/>\n";
			}
			$xml .= "\t</Protocol>\n";
			// StudyEventDef
			foreach (array_keys($Proj->eventInfo) as $this_event_id) {
				$OrdNum = 1;
				$xml .= "\t<StudyEventDef OID=\"Event.".$Proj->getUniqueEventNames($this_event_id)."\" Name=\"".RCView::escape($Proj->eventInfo[$this_event_id]['name_ext'])."\""
					 .  " Type=\"Common\" Repeating=\"No\" redcap:EventName=\"".RCView::escape($Proj->eventInfo[$this_event_id]['name'])."\""
					 .  " redcap:CustomEventLabel=\"".RCView::escape($Proj->eventInfo[$this_event_id]['custom_event_label'])."\""
					 .  " redcap:UniqueEventName=\"".$Proj->getUniqueEventNames($this_event_id)."\" redcap:ArmNum=\"".$Proj->eventInfo[$this_event_id]['arm_num']."\" redcap:ArmName=\"".RCView::escape($Proj->eventInfo[$this_event_id]['arm_name'])."\""
					 .  " redcap:DayOffset=\"".$Proj->eventInfo[$this_event_id]['day_offset']."\" redcap:OffsetMin=\"".$Proj->eventInfo[$this_event_id]['offset_min']."\" redcap:OffsetMax=\"".$Proj->eventInfo[$this_event_id]['offset_max']."\">\n";
				foreach ($Proj->eventsForms[$this_event_id] as $this_form) {
					$xml .= "\t\t<FormRef FormOID=\"Form.$this_form\" OrderNumber=\"".$OrdNum++."\" Mandatory=\"No\" redcap:FormName=\"$this_form\"/>\n";
				}
				$xml .= "\t</StudyEventDef>\n";
			}
		}
		// Build FormDef tags
		$prev_form = null;
		foreach (array_keys($itemGroup) as $this_form_field_section)
		{
			list ($this_form, $this_field) = explode(".", $this_form_field_section, 2);
			// If a new form
			$newForm = $prev_form."" !== $this_form."";
			if ($newForm) {
				if ($prev_form !== null) $xml .= "\t</FormDef>\n";
				$xml .= "\t<FormDef OID=\"Form.$this_form\" Name=\"".RCView::escape($Proj->forms[$this_form]['menu'])."\" Repeating=\"No\" redcap:FormName=\"$this_form\">\n";
			}
			// Add ItemGroupRef
			$xml .= "\t\t<ItemGroupRef ItemGroupOID=\"$this_form.$this_field\" Mandatory=\"No\"/>\n";
			// Set for next loop
			$prev_form = $this_form;
		}
		$xml .= "\t</FormDef>\n";
		// Add ItemGroupDefs
		$ItemDef = $CodeList = "";
		foreach ($itemGroup as $thisGroupOID=>$these_fields) {
			// Get true variable of first field in section
			$first_field_var = $Proj->getTrueVariableName($these_fields[0]);
			// Set section header text as Name, and if form begins without a section, then use the Form name instead
			$thisGroupName = $Proj->metadata[$first_field_var]['element_preceding_header'];
			if ($outputSurveyFields && in_array($these_fields[0], $survey_timestamps)) {
				$thisGroupName = $Proj->forms[$Proj->metadata[$these_fields[1]]['form_name']]['menu'];
			} elseif ($thisGroupName == '') {
				$thisGroupName = $Proj->forms[$Proj->metadata[$these_fields[0]]['form_name']]['menu'];
			}
			// Collect the checkboxes that are processed to prevent running them multiple times
			$checkboxesProcessed = array();
			// Add section/group
			$xml .= "\t<ItemGroupDef OID=\"$thisGroupOID\" Name=\"".RCView::escape($thisGroupName)."\" Repeating=\"No\">\n";
			foreach ($these_fields as $this_field) {
				// Defaults
				$SignificantDigits = $RangeCheck = $FieldNote = $SectionHeader = $Calc = $Identifier = $ReqField
					= $MatrixRanking = $FieldType = $TextValidationType = $OtherAttr = $itemVendorExt = $FieldLabelFormatted 
					= $base64data = $OntologySearch = "";
				// Get true variable of first field in section
				$this_field_true = $Proj->getTrueVariableName($this_field);
				if ($this_field_true !== false) {
					$this_field = self::cleanVarName($this_field_true);
					// Is a required field?
					$mandatory = $Proj->metadata[$this_field]['field_req'] ? "Yes" : "No";
					// Other attributes
					$fieldType = $Proj->metadata[$this_field]['element_type'];
					// Add ItemDef (add Length attribute for integer, float, and text)
					$DataType = ODM::convertRedcapToOdmFieldType($fieldType, $Proj->metadata[$this_field]['element_validation_type']);
					$Length = ODM::getOdmFieldLength($Proj->metadata[$this_field]);
					$SignificantDigits = ($Proj->metadata[$this_field]['element_validation_type'] == 'float') ? " SignificantDigits=\"1\"" : "";
					$RangeCheck = ODM::getOdmRangeCheck($Proj->metadata[$this_field]);
					$FieldNote = ($Proj->metadata[$this_field]['element_note'] == '') ? "" : " redcap:FieldNote=\"".RCView::escape($Proj->metadata[$this_field]['element_note'])."\"";
					$SectionHeader = ($Proj->metadata[$this_field]['element_preceding_header'] == '') ? "" : " redcap:SectionHeader=\"".RCView::escape(nl2crnl($Proj->metadata[$this_field]['element_preceding_header']))."\"";
					$Calc = ($fieldType != 'calc') ? "" : " redcap:Calculation=\"".RCView::escape($Proj->metadata[$this_field]['element_enum'])."\"";
					$Identifier = ($Proj->metadata[$this_field]['field_phi'] != '1') ? "" : " redcap:Identifier=\"y\"";
					$ReqField = ($Proj->metadata[$this_field]['field_req'] != '1') ? "" : " redcap:RequiredField=\"y\"";
					$MatrixRanking = ($Proj->metadata[$this_field]['grid_rank'] != '1') ? "" : " redcap:MatrixRanking=\"y\"";
					$TrueVariable = " redcap:Variable=\"$this_field\"";
					$FieldType = " redcap:FieldType=\"{$fieldType}\"";
					$TextValidationType = ($Proj->metadata[$this_field]['element_validation_type'] == '') ? "" : " redcap:TextValidationType=\"{$Proj->metadata[$this_field]['element_validation_type']}\"";
					$OntologySearch = ($fieldType == 'text' && strpos($Proj->metadata[$this_field]['element_enum'], ":", 1) !== false) ? " redcap:OntologySearch=\"".RCView::escape($Proj->metadata[$this_field]['element_enum'])."\"" : "";
                    $SliderLabels = ($fieldType != 'slider') ? "" : " redcap:SliderLabels=\"".RCView::escape($Proj->metadata[$this_field]['element_enum'])."\"";
					// Only add formatted text if contains HTML
					$FieldLabel = label_decode($Proj->metadata[$this_field]['element_label']);
					if (strpos($FieldLabel, "<") !== false && strpos($FieldLabel, ">") !== false) {
						$FieldLabelFormatted = "<redcap:FormattedTranslatedText>".RCView::escape($FieldLabel)."</redcap:FormattedTranslatedText>";
					}
					$FieldLabel = strip_tags(br2nl($FieldLabel)); // Make sure only the formatted text has HTML
					// Other attributes
					foreach ($otherFieldAttrNames as $backendname=>$attrname) {
						if ($Proj->metadata[$this_field][$backendname] != '') {
							$OtherAttr .= " redcap:".camelCase(str_replace("_"," ",$attrname))."=\"".RCView::escape($Proj->metadata[$this_field][$backendname])."\"";
						}
					}
					// Add attachments for Descriptive fields
					if ($fieldType == 'descriptive') {
						// Add attachments for Descriptive fields
						if (is_numeric($Proj->metadata[$this_field]['edoc_id'])) {
							// Get contents of edoc file as a string
							list ($mimeType, $docName, $base64data) = Files::getEdocContentsAttributes($Proj->metadata[$this_field]['edoc_id']);
							if ($base64data !== false) {
								// Put inside CDATA as base64encoded
								$base64data = "<![CDATA[" . base64_encode($base64data) . "]]>";
								// Add as base64Binary data type
								$itemVendorExt .= "\t\t<redcap:Attachment DocName=\"".RCView::escape($docName)."\" MimeType=\"".RCView::escape($mimeType)."\">$base64data</redcap:Attachment>\n";
							}
							// Clear out all data to save some memory
							$base64data = '';
							// IMAGE? If an inline image, then add its attributes
							if ($Proj->metadata[$this_field]['edoc_display_img'] == '1') {
								$OtherAttr .= " redcap:InlineImage=\"{$Proj->metadata[$this_field]['edoc_display_img']}\"";
							}
						}
						// If is a video URL, then add its attributes
						elseif ($Proj->metadata[$this_field]['video_url'] != '') {
							$OtherAttr .= " redcap:VideoUrl=\"".RCView::escape($Proj->metadata[$this_field]['video_url'])."\""
									   .  " redcap:VideoDisplayInline=\"{$Proj->metadata[$this_field]['video_display_inline']}\"";
						}
					}
				} else {
					// DAG field or Survey fields
					$mandatory = "No";
					if (in_array($this_field, $survey_timestamps)) {
						$DataType = 'datetime';
						$FieldLabel = 'Survey Timestamp';
					} else {
						$DataType = 'text';
						$FieldLabel = Project::$reserved_field_names[$this_field];
					}
					$Length = 999;
					$TrueVariable = " redcap:Variable=\"$this_field\"";
				}
				// For checkboxes, loop through all choices
				if ($fieldType == 'checkbox') {
					// If we've already processed this checkbox, then skip this loop
					if (in_array($this_field, $checkboxesProcessed)) continue;
					// Add to array
					$checkboxesProcessed[] = $this_field;
					// Add all choices
					$allItemDefs = array();
					foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $this_code) {
						$allItemDefs[] = Project::getExtendedCheckboxFieldname($this_field, $this_code);
					}
				} else {
					$allItemDefs = array($this_field);
				}
				// Add field with tags
				foreach ($allItemDefs as $this_field2) {
					// Set code list values
					$CodeList .= $thisCodeList = ODM::getOdmCodeList($Proj->metadata[$this_field], $this_field2);
					$CodeListRef = ($thisCodeList != "") ? "\t\t<CodeListRef CodeListOID=\"$this_field2.choices\"/>\n" : "";
					// Add field/ItemRef
					$xml .= "\t\t<ItemRef ItemOID=\"$this_field2\" Mandatory=\"$mandatory\"{$TrueVariable}/>\n";
					// Add ItemDef
					$ItemDef .= "\t<ItemDef OID=\"$this_field2\" Name=\"$this_field2\" DataType=\"$DataType\" Length=\"$Length\""
							 .  $SignificantDigits . $TrueVariable . $FieldType . $TextValidationType . $SliderLabels . $FieldNote . $SectionHeader . $Calc
							 .  $Identifier . $ReqField . $MatrixRanking . $OntologySearch . $OtherAttr . ">\n"
							 .  "\t\t<Question><TranslatedText>".RCView::escape(nl2crnl($FieldLabel))."</TranslatedText>$FieldLabelFormatted</Question>\n"
							 .  $CodeListRef . $RangeCheck . $itemVendorExt . "\t</ItemDef>\n";
				}
			}
			$xml .= "\t</ItemGroupDef>\n";
		}
		// Add all ItemDefs and CodeList
		$xml .= $ItemDef . $CodeList . "</MetaDataVersion>\n";
		// End Study section
		$xml .= "</Study>\n";
		// Return metadata XML
		return $xml;
	}


	// Return ODM ClinicalData section
	public static function getOdmClinicalData(&$record_data_formatted, &$Proj, $outputSurveyFields, $outputDags,
											  $returnBlankValues=true, $write_data_to_file=false, $returnHeaders=true, $returnFooters=true)
	{
		global $record_data_tmp_filename;
		// Get item groups array
		$itemGroup = self::getOdmItemGroups($Proj, $outputSurveyFields, $outputDags);
		// Repeating forms/events?
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// Set array containing special reserved field names that won't be in the project metadata
		$survey_timestamps = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp");
		// Set ending tags
		$recordEndTags = "\t</SubjectData>\n";
		$eventEndTags = $Proj->longitudinal ? "\t\t</StudyEventData>\n" : "";
		$formEndTags = "\t\t\t</FormData>\n";
		$itemGroupEndTags = "\t\t\t\t</ItemGroupData>\n";
		// Begin section
        $xml = "";
        if ($returnHeaders) {
			$xml = "<ClinicalData StudyOID=\"" . self::getStudyOID($Proj->project['app_title']) . "\""
				. " MetaDataVersionOID=\"" . self::getMetadataVersionOID($Proj->project['app_title']) . "\">\n";
		}
		// Loop through record array and add to XML string
		if (!empty($record_data_formatted) || ($write_data_to_file && filesize($record_data_tmp_filename) > 0))
		{
			// Reset values for this loop
			$prev_record = $prev_event = $prev_form = $prev_section = null;
			$prev_repeat_instrument_instance = $prev_repeat_event_instance = $PrevStudyEventRepeatKey = $PrevFormRepeatKey = null;
			// If writing data to file, then make sure file exists
			if ($write_data_to_file) {
				// Instantiate FileObject for our opened temp file to extract single lines of data
				$fileSearch = new SplFileObject($record_data_tmp_filename);
				$line_num = 0;
				$fileSearch->seek($line_num++);
			}
			// Loop through data
			while ((!$write_data_to_file && !empty($record_data_formatted)) || ($write_data_to_file && !$fileSearch->eof()))
			{
				// If written to file, then unserialize it
				if ($write_data_to_file) {
					$items = array();
					foreach (unserialize($fileSearch->current()) as $item) { 					
						// If data was written to file, then make sure we restore any line breaks that were removed earlier
						foreach ($item as &$this_value) {
							$this_value = str_replace(array(Records::RC_NL_R, Records::RC_NL_N), array("\r", "\n"), $this_value);
						}
						$fileSearch->seek($line_num++); // this is zero based so need to subtract 1
						$items[] = $item;
					}
				} else {
					// Extract from array
					$item = each($record_data_formatted);
					if ($item === false) break;
					$key = $item[0];
					$item = $item[1];					
					$items = array($key=>$item);
				}
				// Loop through this item (or through multiple items if a repeating form)
				foreach ($items as $key=>$item)
				{
					// Begin item
					$this_record = $item[$Proj->table_pk];
					$this_event = (isset($item['redcap_event_name']) ? $item['redcap_event_name'] : null);
					$this_event_id = $Proj->longitudinal ? $Proj->getEventIdUsingUniqueEventName($this_event) : $Proj->firstEventId;
					$isRepeatingEvent = ($Proj->longitudinal && $hasRepeatingFormsEvents && $Proj->isRepeatingEvent($this_event_id) && isset($item['redcap_repeat_instance']) && $item['redcap_repeat_instance'] != "");
					$isRepeatingForm = ($hasRepeatingFormsEvents && !$isRepeatingEvent && isset($item['redcap_repeat_instrument']) && $item['redcap_repeat_instance'] != "");
					$isRepeatingFormOrEvent = ($isRepeatingEvent || $isRepeatingForm);
					$StudyEventRepeatKey = $isRepeatingEvent ? $item['redcap_repeat_instance'] : "1";
					$FormRepeatKey = $isRepeatingForm ? $item['redcap_repeat_instance'] : "1";
					if ($isRepeatingFormOrEvent) {
						$this_repeat_instrument = $item['redcap_repeat_instrument'];
						$this_repeat_instance = $item['redcap_repeat_instance'];
						$this_repeat_event_instance = ($Proj->longitudinal) ? $item['redcap_event_name']."-".$item['redcap_repeat_instance'] : null;
						$this_repeat_instrument_instance = $item['redcap_repeat_instrument']."-".$item['redcap_repeat_instance'];
					} else {
						$this_repeat_instrument_instance = 	$this_repeat_event_instance = null;
					}
					// Remove event name
					unset($item['redcap_event_name'], $item['redcap_repeat_instrument'], $item['redcap_repeat_instance']);
					// Determine what's different in this loop
					$newRecord = ($prev_record."" !== $this_record."");
					$newEvent = ($prev_event."" !== $this_event."");
					$newRepeatEventInstance = ($isRepeatingEvent && $prev_repeat_event_instance."" !== $this_repeat_event_instance."");
					$newRepeatInstance = ($this_repeat_instrument_instance."" !== $prev_repeat_instrument_instance."");
					$prev_repeat_instrument_instance = $this_repeat_instrument_instance;
					// If a new record
					$newSubjectData = false;
					if ($newRecord) {
						if ($prev_record !== null) {
							$xml .= $itemGroupEndTags . $formEndTags . $eventEndTags . $recordEndTags;
						}
						$xml .= "\t<SubjectData SubjectKey=\"".htmlspecialchars($this_record, ENT_QUOTES)."\" redcap:RecordIdField=\"{$Proj->table_pk}\">\n";
						$newSubjectData = true;
					}
					// If a new event
					$newStudyEventData = false;
					if ($newRecord || $newEvent || $newRepeatEventInstance) {
						if ((!$newRecord && $newEvent && $prev_event !== null)
							|| (!$newRecord && !$newEvent && $newRepeatEventInstance && $PrevStudyEventRepeatKey."" !== $StudyEventRepeatKey."")
						) {
							$xml .= $itemGroupEndTags . $formEndTags . $eventEndTags;
						}
						if ($Proj->longitudinal) {
							$xml .= "\t\t<StudyEventData StudyEventOID=\"Event.$this_event\" StudyEventRepeatKey=\"$StudyEventRepeatKey\" redcap:UniqueEventName=\"$this_event\">\n";
							$newStudyEventData = true;
						}
					}
					// Loop through all fields/values
					$recordEventLoops = 0;
					$this_section = $prev_section = null;
					foreach ($item as $this_field=>$this_value)
					{
						// If flag set to ignore blank values, then go to next value
						if ($this_value == '' && !$returnBlankValues) continue;
						// Get form name and section of this field
						$trueVarName = $Proj->getTrueVariableName($this_field);
						$this_section = "";
						if ($trueVarName !== false) {
							$this_form = $Proj->metadata[$trueVarName]['form_name'];
							$this_field_type = $Proj->metadata[$this_field]['element_type'];
							if ($Proj->metadata[$trueVarName]['element_preceding_header'] != '') {
								$this_section = $Proj->metadata[$trueVarName]['element_preceding_header'];
							}
						} elseif (in_array($this_field, $survey_timestamps)) {
							$this_field_type = 'text';
							$this_form = substr($this_field, 0, -10);
						}
						// If field's form is not designated for this event, then skip
						if ($Proj->longitudinal && !in_array($this_form, $Proj->eventsForms[$this_event_id])) continue;
						// If row is a repeating form but field is not on that repeating form
						if ($isRepeatingForm && $this_form != $this_repeat_instrument) continue;
						// If row is NOT a repeating form but field IS on that repeating form
						if (!$isRepeatingForm && $Proj->isRepeatingForm($this_event_id, $this_form)) continue;
						// Determine what's different in this loop
						$newForm = ($prev_form."" !== $this_form."");
						$newSection = ($newForm || $prev_section."" !== $this_section."");
						// If a new form
						if ($recordEventLoops === 0 || $newForm) {
							if ($prev_form !== null
								&& (($recordEventLoops > 0 && !$newRepeatInstance && !$newRepeatEventInstance) 
									|| (($newRepeatInstance || $newRepeatEventInstance) && !$newSubjectData && !$newStudyEventData && $recordEventLoops === 0)
									|| ($recordEventLoops > 0 && $newForm)
								   )
								&& !($newRepeatInstance && $newRepeatEventInstance && $recordEventLoops === 0)
							) {
								$xml .= $itemGroupEndTags . $formEndTags;
							}
							$xml .= "\t\t\t<FormData FormOID=\"Form.$this_form\" FormRepeatKey=\"$FormRepeatKey\">\n";						
							$PrevFormRepeatKey = $FormRepeatKey;
						}
						// If a new section
						if ($recordEventLoops === 0 || $newSection) {
							if (!$newForm && $recordEventLoops > 0) {
								$xml .= $itemGroupEndTags;
							}
							$itemGroupOid = isset($itemGroup["$this_form.$this_field"]) ? "$this_form.$this_field" : "";
							$xml .= "\t\t\t\t<ItemGroupData ItemGroupOID=\"$itemGroupOid\" ItemGroupRepeatKey=\"1\">\n";
						}
						// Skip "file" field data (set as blank)
						if ($this_field_type == 'file') {
							// If flag set to ignore blank values, then go to next value
							if ($this_value == '' && !$returnBlankValues) continue;
							// If it has a value, then get the binary contents
							$base64data = '';
							if (is_numeric($this_value)) {
								// Get contents of edoc file as a string
								list ($mimeType, $docName, $base64data) = Files::getEdocContentsAttributes($this_value);
								// Put inside CDATA as base64encoded
								if ($base64data !== false) $base64data = "<![CDATA[" . base64_encode($base64data) . "]]>";
								// Add as base64Binary data type
								$xml .= "\t\t\t\t\t<ItemDataBase64Binary ItemOID=\"$this_field\" redcap:DocName=\"".RCView::escape($docName)."\" redcap:MimeType=\"".RCView::escape($mimeType)."\">$base64data</ItemDataBase64Binary>\n";
								// Clear out all data to save some memory
								$base64data = '';
							}
						}
						// Add "T" inside datetime values
						elseif ($this_field_type == 'text' && strpos($Proj->metadata[$this_field]['element_validation_type'], "datetime") === 0) {
							$this_value = str_replace(" ", "T", $this_value);
						}
						// Add to ItemData (except for 'file' field types, which were added as base64Binary data type)
						if ($this_field_type != 'file') {
						    // Clean text to remove ASCII control codes, which won't translate
                           $this_value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $this_value);
                           // Add to XML
							$xml .= "\t\t\t\t\t<ItemData ItemOID=\"$this_field\" Value=\"".htmlspecialchars($this_value, ENT_QUOTES)."\"/>\n";
						}
						// Set for next loop
						$prev_form = $this_form;
						$prev_section = $this_section;
						$recordEventLoops++;
					}
					// Set for next loop
					$prev_record = $this_record;
					$prev_event = $this_event;
					$prev_repeat_event_instance = $this_repeat_event_instance;
					$PrevStudyEventRepeatKey = $StudyEventRepeatKey;
					if ($write_data_to_file) {
						// $fileSearch->next();
						// print "$this_record\n";
						// if ($fileSearch->eof()) exit;
					} else {
						// Remove line from array to free up memory as we go
						unset($record_data_formatted[$key]);
					}
					unset($items[$key]);
				}
			}
			// Ending tags
			$xml .= $itemGroupEndTags . $formEndTags . $eventEndTags . $recordEndTags;
		}
		// End the ClinicalData section
		if ($returnFooters) $xml .= "</ClinicalData>\n";
		// Return section
		return $xml;
	}


	// Return ODM CodeList tags for a given field based upon REDCap field attributes.
	// $field_oid is only provided for checkboxes, which will have a new field name/oid for each choice.
	public static function getOdmCodeList($field_attr, $field_oid)
	{
		$mcFieldTypes = self::getMcFieldTypes();
		$choices = array();
		$CheckboxChoices = "";

		// If Checkbox field
		if ($field_attr['element_type'] == 'checkbox') {
			$choices = array(1=>"Checked", 0=>"Unchecked");
			$CheckboxChoices = " redcap:CheckboxChoices=\"".RCView::escape(str_replace("\\n", "|", $field_attr['element_enum']))."\"";
		}
		// If YesNo field
		elseif ($field_attr['element_type'] == 'yesno') {
			$choices = array(1=>"Yes", 0=>"No");
		}
		// If TrueFalse field
		elseif ($field_attr['element_type'] == 'truefalse') {
			$choices = array(1=>"True", 0=>"False");
		}
		// Multiple Choice field
		elseif (in_array($field_attr['element_type'], $mcFieldTypes)) {
			// Determine size of biggest choice value
			if ($field_attr['element_type'] == 'sql') {
			    // Do not output the options of SQL fields because there is a chance that they might include PHI
				$field_attr['element_enum'] = "";
			}
			$choices = parseEnum($field_attr['element_enum']);
		}

		// Default: Return blank string
		if (empty($choices)) {
			return "";
		}
		// Return CodeList tags
		else {
			$DataType = ODM::convertRedcapToOdmFieldType($field_attr['element_type'], $field_attr['element_validation_type']);
			$CodeList = "\t<CodeList OID=\"$field_oid.choices\" Name=\"$field_oid\" DataType=\"$DataType\" redcap:Variable=\"{$field_attr['field_name']}\"{$CheckboxChoices}>\n";
			foreach ($choices as $key=>$choice_label) {
                // Clean text to remove ASCII control codes, which won't translate
                $choice_label = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $choice_label);
				// If choice label contains HTML tags, then add it as separate formatted text tag
				$choice_label_formatted = "";
				if (strpos($choice_label, "<") !== false && strpos($choice_label, ">") !== false) {
					$choice_label_formatted = "<redcap:FormattedTranslatedText>".RCView::escape($choice_label)."</redcap:FormattedTranslatedText>";
				}
				$choice_label = br2nl($choice_label); // Make sure only the formatted text has HTML
				$CodeList .= "\t\t<CodeListItem CodedValue=\"".RCView::escape($key)."\"><Decode><TranslatedText>".RCView::escape($choice_label, false)."</TranslatedText>$choice_label_formatted</Decode></CodeListItem>\n";
			}
			$CodeList .= "\t</CodeList>\n";
			return $CodeList;
		}
	}


	// Return the Length value to be used in ODM's ItemDef based upon REDCap field attributes
	public static function getOdmFieldLength($field_attr)
	{
		$mcFieldTypes = self::getMcFieldTypes();
		// If Checkbox field
		if ($field_attr['element_type'] == 'checkbox') {
			return 1;
		}
		// Multiple Choice field
		elseif (in_array($field_attr['element_type'], $mcFieldTypes)) {
			// Determine size of biggest choice value
			if ($field_attr['element_type'] == 'sql') {
				$field_attr['element_enum'] = getSqlFieldEnum($field_attr['element_enum']);
			}
			$maxlength = 1;
			foreach (array_keys(parseEnum($field_attr['element_enum'])) as $this_choice) {
				$this_choice_len = strlen($this_choice."");
				if ($this_choice_len > $maxlength) {
					$maxlength = $this_choice_len;
				}
			}
			return $maxlength;
		}
		// Default
		return 999;
	}


	// Return array for converting the ODM field type to its corresponding REDCap field type.
	// Incorporate the REDCap validation type to determine how to convert text fields.
	public static function getOdmFieldTypeReverseConversion()
	{
		// ODM field type => REDCap field type and validation_type
		$fieldTypeConversion = array();
		$fieldTypeConversion['float'] = array('field_type'=>'text', 'validation_type'=>'number');
		$fieldTypeConversion['integer'] = array('field_type'=>'text', 'validation_type'=>'integer');
		$fieldTypeConversion['date'] = array('field_type'=>'text', 'validation_type'=>'date_ymd');
		$fieldTypeConversion['datetime'] = array('field_type'=>'text', 'validation_type'=>'datetime_seconds_ymd');
		$fieldTypeConversion['boolean'] = array('field_type'=>'truefalse', 'validation_type'=>'');
		// Everything else will be assumed as "text" type for compatibility purposes
		return $fieldTypeConversion;
	}

	// Convert the REDCap field type to its corresponding ODM field type.
	// Incorporate the REDCap validation type to determine how to convert text fields.
	public static function convertOdmToRedcapFieldType($field_type=null)
	{
		$fieldTypeConversion = self::getOdmFieldTypeReverseConversion();
		// Return the RC field type (default=text if could not determine)
		return isset($fieldTypeConversion[$field_type]) ? $fieldTypeConversion[$field_type] : array('field_type'=>'text', 'validation_type'=>'');
	}


	// Return array for converting the REDCap field type to its corresponding ODM field type.
	// Incorporate the REDCap validation type to determine how to convert text fields.
	public static function getOdmFieldTypeConversion()
	{
		// REDCap field type[validation_type] => ODM field type
		$fieldTypeConversion = array();
		$fieldTypeConversion['textarea'][''] = 'text';
		$fieldTypeConversion['calc'][''] = 'float';
		$fieldTypeConversion['select'][''] = 'text';
		$fieldTypeConversion['radio'][''] = 'text';
		$fieldTypeConversion['checkbox'][''] = 'boolean';
		$fieldTypeConversion['yesno'][''] = 'boolean';
		$fieldTypeConversion['truefalse'][''] = 'boolean';
		$fieldTypeConversion['file'][''] = 'text';
		$fieldTypeConversion['slider'][''] = 'integer';
		$fieldTypeConversion['sql'][''] = 'text';
		$fieldTypeConversion['text'][''] = 'text';
		$fieldTypeConversion['text']['int'] = 'integer';
		$fieldTypeConversion['text']['float'] = 'float';
		$fieldTypeConversion['text']['time'] = 'partialTime';
		$fieldTypeConversion['text']['date'] = 'date';
		$fieldTypeConversion['text']['datetime'] = 'partialDatetime';
		$fieldTypeConversion['text']['datetime_seconds'] = 'datetime';
		// Return array
		return $fieldTypeConversion;
	}

	// Convert the REDCap field type to its corresponding ODM field type.
	// Incorporate the REDCap validation type to determine how to convert text fields.
	public static function convertRedcapToOdmFieldType($field_type=null, $validation_type='')
	{
		$fieldTypeConversion = self::getOdmFieldTypeConversion();
		// Format date
		if ($field_type == 'text') {
			// Normalize all date/time fields
			if (in_array($validation_type, array('date_ymd', 'date_mdy', 'date_dmy'))) {
				$validation_type = 'date';
			} elseif (in_array($validation_type, array('datetime_ymd', 'datetime_mdy', 'datetime_dmy'))) {
				$validation_type = 'datetime';
			} elseif (in_array($validation_type, array('datetime_seconds_ymd', 'datetime_seconds_mdy', 'datetime_seconds_dmy'))) {
				$validation_type = 'datetime_seconds';
			} else {
				// Get data type to determine if a different type of number
				$validation_key = getValTypes($validation_type);
				if (isset($validation_key['data_type']) && $validation_key['data_type'] == 'number') {
					$validation_type = 'float';
				}
			}
		}
		// If the validation type isn't listed, then revert to string
		if ($validation_type === null || $field_type != 'text' || ($field_type == 'text' && !isset($fieldTypeConversion[$field_type][$validation_type]))) {
			$validation_type = '';
		}
		// Return the ODM field type (default=string if could not determine)
		return isset($fieldTypeConversion[$field_type][$validation_type]) ? $fieldTypeConversion[$field_type][$validation_type] : 'text';
	}


	// Parse an ODM document and return only the Clinical Data in CSV format as a string
	public static function convertOdmClinicalDataToCsv($xml, $Proj=null)
	{
		return self::parseOdm($xml, true, false, $Proj);
	}


	// Parse an ODM document and return count of arms, events, fields, records added + any errors as an array.
	// Set $returnCsvDataOnly=true to return CSV ClinicalData instead of committing all metadata/data (default).
	public static function parseOdm($xml, $returnCsvDataOnly=false, $removeDagAssignmentsInData=false, $Proj=null)
	{
		global $lang;

		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);

		$armCount = $eventCount = $fieldCount = $recordCount = 0;

		// Clean the XML (replace line breakas with HTML character code to preserve them)
		$xml = trim($xml);
		// First remove any indentations:
		$xml = str_replace("\t","", $xml);
		// Next replace unify all new-lines into unix LF:
		$xml = str_replace("\r","\n", $xml);
		$xml = str_replace("\n\n","\n", $xml);
		// Next replace all new lines with the unicode:
		$xml = str_replace("\n","&#10;", $xml);
		// Finally, replace any new line entities between >< with a new line:
		$xml = str_replace(">&#10;<",">\n<", $xml);

		// Validate the ODM document to make sure it has the basic components needed
		list ($validOdm, $hasMetadata, $hasData) = self::validateOdmInitial($xml);
		if (!$validOdm) return array('errors'=>array($lang['data_import_tool_240']));
		if ($returnCsvDataOnly && !$hasData) return array('errors'=>array($lang['data_import_tool_240']));
		if ($returnCsvDataOnly) $hasMetadata = false;

	    //Get the XML parser of PHP - PHP must have this module for the parser to work
		if (!function_exists('xml_parser_create')) {
			exit("Missing PHP XML Parser!");
		}
	    $parser = xml_parser_create('');
	    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
	    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
	    xml_parse_into_struct($parser, $xml, $xml_values);
	    xml_parser_free($parser);
		// If could not be parsed, then return false
	    if (!$xml_values) return array('errors'=>array($lang['data_import_tool_240']));

	    //Initializations
		$arms = $events = $eventsForms = $forms = $fields = $metadata = $data = $metadata_extra
			  = $metadata_choices_formatted = $codeListOIDs = array();
		$day_offset = 1;
		$currentEvent = ($Proj !== null) ? $Proj->getUniqueEventNames($Proj->firstEventId) : null;; // Use this to determine if data is longitudinal or classic
		$recordIdField = ($Proj !== null) ? $Proj->table_pk : null; // Determine record ID field if ODM contains metadata (only in REDCap-exported ODM files)
		$metadataDefaults = array_fill_keys(MetaData::getDataDictionaryHeaders(), "");
		// Get array of project attribute mappings available
		$projectAttrMappings = self::getProjectAttrMappings(true, true, true);
		$projectAttrValues = array();
		$RepeatingInstrumentsAndEvents = array();
		$redcapVendorExt = array();
		// Repeating forms/events - get array from $Proj if in project context
		if ($Proj !== null && !$hasMetadata) {
			// Add unique event name as key instead of event_id
			foreach ($Proj->getRepeatingFormsEvents() as $this_event_id=>$these_forms) {
				$this_event_name = $Proj->getUniqueEventNames($this_event_id);
				if (!is_array($these_forms)) {
					$RepeatingInstrumentsAndEvents[$this_event_name] = 'WHOLE';
				} else {
					foreach (array_keys($these_forms) as $this_form) {
						$RepeatingInstrumentsAndEvents[$this_event_name][$this_form] = true;
					}
				}
			}
		}

		// Set array of fields to skip (e.g. Form Status fields from REDCap vendor extension)
		$skipFields = array();
	    //Go through the tags.
	    $repeated_tag_index = array();	//Multiple tags with same name will be turned into an array
		$isLongitudinal = $hasRepeatingData = false;
		$FormRepeatKey = '1';
		$StudyEventRepeatKey = '';
	    foreach ($xml_values as $tagdata)
	    {
			//Remove existing values, or there will be trouble
	        unset($attributes,$value);
	        //This command will extract these variables into the foreach scope
	        // tag(string), type(string), level(int), attributes(array).
	        extract($tagdata);
			// We only need to look at open or complete tags
			if ($type == 'close') continue;
			## METADATA
			if ($hasMetadata)
			{
				// Check for REDCap-vendor project attribute tag
				if (isset($projectAttrMappings[$tag])) {
					if ($value != "") {
						$projectAttrValues[$projectAttrMappings[$tag]] = $value;
					}
				}
				// Get record ID field
				elseif ($tag == 'MetaDataVersion' && isset($attributes['redcap:RecordIdField'])) {
					$recordIdField = $attributes['redcap:RecordIdField'];
				}
				// Store events/arms/forms/fields info into arrays
				elseif ($tag == 'StudyEventRef' && isset($attributes['StudyEventOID'])) {
					$events[$attributes['StudyEventOID']] = array();
				}
				elseif ($tag == 'StudyEventDef' && isset($events[$attributes['OID']])) {
					$isLongitudinal = true;
					if (isset($attributes['redcap:EventName'])) {
						$events[$attributes['OID']]['event_name'] = $attributes['redcap:EventName'];
						$events[$attributes['OID']]['arm_num'] = $attributes['redcap:ArmNum'];
						$events[$attributes['OID']]['day_offset'] = $attributes['redcap:DayOffset'];
						$events[$attributes['OID']]['offset_min'] = $attributes['redcap:OffsetMin'];
						$events[$attributes['OID']]['offset_max'] = $attributes['redcap:OffsetMax'];
						$events[$attributes['OID']]['custom_event_label'] = $attributes['redcap:CustomEventLabel'];
						$arms[$attributes['redcap:ArmNum']] = array('arm_num'=>$attributes['redcap:ArmNum'], 'name'=>$attributes['redcap:ArmName']);
					} else {
						// Make sure event name is not too long
						if (strlen($attributes['Name']) >= 30) {
							$attributes['Name'] = trim(substr($attributes['Name'], 0, 24)) . " " . substr(sha1(rand()), 0, 5);
						}
						// Set default values for event/arm
						$events[$attributes['OID']]['event_name'] = $attributes['Name'];
						$events[$attributes['OID']]['arm_num'] = '1';
						$events[$attributes['OID']]['day_offset'] = $day_offset++;
						$events[$attributes['OID']]['offset_min'] = '0';
						$events[$attributes['OID']]['offset_max'] = '0';
						$arms['1'] = array('arm_num'=>'1', 'name'=>"Arm 1");
					}
					// Add unique name to events array and set for when looping through forms in FormRef
					$currenEventOid = $attributes['OID'];
					$currentUniqueEvent = $events[$attributes['OID']]['unique_event_name']
						= self::cleanVarName(isset($attributes['redcap:UniqueEventName']) ? $attributes['redcap:UniqueEventName'] : $attributes['OID']);
				}
				elseif ($tag == 'FormRef') {
					$eventsForms[] = array('arm_num'=>$events[$currenEventOid]['arm_num'], 'unique_event_name'=>$currentUniqueEvent,
										   'form'=>self::cleanVarName(isset($attributes['redcap:FormName']) ? $attributes['redcap:FormName'] : $attributes['FormOID']));
				}
				elseif ($tag == 'FormDef' && isset($attributes['OID'])) {
					if (isset($attributes['redcap:FormName'])) {
						$attributes['OID'] = $attributes['redcap:FormName'];
					}
					$forms[$attributes['OID']] = $attributes['Name'];
					// Set for when looping through forms in ItemGroupRef
					$currentForm = self::cleanVarName($attributes['OID']);
				}
				elseif ($tag == 'ItemGroupRef' && isset($attributes['ItemGroupOID'])) {
					$fieldGroups[$attributes['ItemGroupOID']]['form'] = $currentForm;
				}
				elseif ($tag == 'ItemGroupDef' && isset($fieldGroups[$attributes['OID']])) {
					$fieldGroups[$attributes['OID']]['name'] = $attributes['Name'];
					$fieldGroups[$attributes['OID']]['fields'] = array();
					// Set for when looping through forms in ItemGroupRef
					$currentFieldGroup = $attributes['OID'];
				}
				// FIELDS
				elseif ($tag == 'ItemRef' && isset($fieldGroups[$currentFieldGroup])) {
					if (isset($attributes['redcap:Variable'])) {
						$field = $attributes['redcap:Variable'];
						// If this is a REDCap vendor extension Form Status field, then skip it
						if ($field == $fieldGroups[$currentFieldGroup]['form']."_complete") {
							$skipFields[$field] = true;
							continue;
						}
					} else {
						$field = self::cleanVarName($attributes['ItemOID']);
					}
					// Add field to array
					$metadata[$field] = $metadataDefaults;
					$metadata[$field]['form_name'] = $fieldGroups[$currentFieldGroup]['form'];
					$metadata[$field]['required_field'] = (isset($attributes['Mandatory']) && strtolower($attributes['Mandatory']) == 'yes') ? 'y' : '';
					// If field has section header, then add it
					if ($currentFieldGroup == $metadata[$field]['form_name'].".".$attributes['ItemOID']) {
						$metadata[$field]['section_header'] = $fieldGroups[$currentFieldGroup]['name'];
					}
				}
				elseif ($tag == 'ItemDef' && isset($attributes['OID'])) {
					if (isset($attributes['redcap:Variable'])) {
						$field = $attributes['redcap:Variable'];
					} else {
						$field = self::cleanVarName($attributes['OID']);
					}
					$currentField = $field;
					if (!isset($skipFields[$field])) {
						$metadata[$field]['field_name'] = $field;
						list ($field_type, $val_type) = array_values(self::convertOdmToRedcapFieldType($attributes['DataType']));
						$metadata[$field]['field_type'] = (isset($attributes['redcap:FieldType'])) ? $attributes['redcap:FieldType'] : $field_type;
						$metadata[$field]['text_validation_type_or_show_slider_number'] = (isset($attributes['redcap:TextValidationType'])
							? $attributes['redcap:TextValidationType']
							: ((isset($attributes['redcap:FieldType']) && $attributes['redcap:FieldType'] == 'calc') ? "" : $val_type));
						// REDCap vendor extensions
						if (isset($attributes['redcap:SectionHeader'])) {
							$metadata[$field]['section_header'] = $attributes['redcap:SectionHeader'];
						} elseif ($metadata[$field]['section_header'] != '') {
							// Set the section header to blank because it only got set as non-null because ItemGroups have to have a Name,
							// in which a placeholder section header gets added automatically to the first field on a form.
							$metadata[$field]['section_header'] = '';
						}
						if (isset($attributes['redcap:FieldNote'])) {
							$metadata[$field]['field_note'] = $attributes['redcap:FieldNote'];
						}
						if (isset($attributes['redcap:Calculation'])) {
							$metadata[$field]['select_choices_or_calculations'] = $attributes['redcap:Calculation'];
						}
						if (isset($attributes['redcap:Identifier'])) {
							$metadata[$field]['identifier'] = $attributes['redcap:Identifier'];
						}
						if (isset($attributes['redcap:BranchingLogic'])) {
							$metadata[$field]['branching_logic'] = $attributes['redcap:BranchingLogic'];
						}
						if (isset($attributes['redcap:RequiredField'])) {
							$metadata[$field]['required_field'] = $attributes['redcap:RequiredField'];
						}
						if (isset($attributes['redcap:CustomAlignment'])) {
							$metadata[$field]['custom_alignment'] = $attributes['redcap:CustomAlignment'];
						}
						if (isset($attributes['redcap:QuestionNumber'])) {
							$metadata[$field]['question_number'] = $attributes['redcap:QuestionNumber'];
						}
						if (isset($attributes['redcap:MatrixGroupName'])) {
							$metadata[$field]['matrix_group_name'] = $attributes['redcap:MatrixGroupName'];
						}
						if (isset($attributes['redcap:MatrixRanking'])) {
							$metadata[$field]['matrix_ranking'] = $attributes['redcap:MatrixRanking'];
						}
						if (isset($attributes['redcap:FieldAnnotation'])) {
							$metadata[$field]['field_annotation'] = $attributes['redcap:FieldAnnotation'];
						}
						// Extra attributes not in data dictionary
						if (isset($attributes['redcap:VideoUrl'])) {
							$metadata_extra[$field]['video_url'] = $attributes['redcap:VideoUrl'];
						}
						if (isset($attributes['redcap:VideoDisplayInline'])) {
							$metadata_extra[$field]['video_display_inline'] = $attributes['redcap:VideoDisplayInline'];
						}
						if (isset($attributes['redcap:InlineImage'])) {
							$metadata_extra[$field]['edoc_display_img'] = $attributes['redcap:InlineImage'];
						}
						if (isset($attributes['redcap:OntologySearch'])) {
							$metadata[$field]['select_choices_or_calculations'] = $attributes['redcap:OntologySearch'];
						}
                        if ($metadata[$field]['field_type'] == 'slider' && isset($attributes['redcap:SliderLabels'])) {
                            $metadata[$field]['select_choices_or_calculations'] = $attributes['redcap:SliderLabels'];
                        }
					}
					// Set for when looping through ItemDef children
					$parentTag = $tag;
					$parentTag2 = "";
				}
				elseif (($tag == 'Question' || $tag == 'Description') && $parentTag == 'ItemDef' && !isset($skipFields[$currentField])) {
					$parentTag2 = $tag;
				}
				elseif ($tag == 'TranslatedText' && $parentTag == 'ItemDef' && $parentTag2 == 'Question' && !isset($skipFields[$currentField]) && $metadata[$currentField]['field_label'] == '') {
					$metadata[$currentField]['field_label'] = $value;
					$parentTag2 = "";
				}
				elseif ($tag == 'CodeListRef' && $parentTag == 'ItemDef' && !isset($skipFields[$currentField]) && isset($attributes['CodeListOID'])) {
					$codeListOIDs[self::cleanVarName($attributes['CodeListOID'])] = $currentField;
					// If field is not listed as multiple choice, then change it to drop-down
					if (!in_array($metadata[$currentField]['field_type'], array("radio", "checkbox", "dropdown", "yesno", "truefalse"))) {
						$metadata[$currentField]['field_type'] = "dropdown";
					}
				}
				elseif ($tag == 'redcap:FormattedTranslatedText' && $parentTag == 'ItemDef' && !isset($skipFields[$currentField])) {
					$metadata[$currentField]['field_label'] = $value;
				}
				elseif ($tag == 'redcap:Attachment' && $parentTag == 'ItemDef' && !isset($skipFields[$currentField])) {
					$metadata_extra[$currentField]['doc_contents'] = base64_decode($value);
					$metadata_extra[$currentField]['mime_type'] = $attributes['MimeType'];
					$metadata_extra[$currentField]['doc_name'] = $attributes['DocName'];
				}
				elseif ($tag == 'RangeCheck' && $parentTag == 'ItemDef' && isset($attributes['Comparator'])) {
					$currentComparator = $attributes['Comparator'];
					$parentTag = $tag;
				}
				elseif ($tag == 'CheckValue' && $parentTag == 'RangeCheck' && in_array($currentComparator, array('LE', 'LT', 'GE', 'GT'))) {
					$attr_name = ($currentComparator == 'LE' || $currentComparator == 'LT') ? 'text_validation_max' : 'text_validation_min';
					$metadata[$currentField][$attr_name] = $value;
					// Reset parent tag for next range check validation
					$parentTag = 'ItemDef';
				}
				// CODELISTS
				elseif ($tag == 'CodeList' && isset($attributes['OID'])) {
					$parentTag = $tag;
					$skipCodeListItems = false;
					if (isset($attributes['redcap:Variable'])) {
						$currentField = $attributes['redcap:Variable'];
						if (isset($skipFields[$currentField])) continue;
						// If REDCap vendor extension includes the checkbox options, then use it instead of CodeListItems
						if (isset($attributes['redcap:CheckboxChoices'])) {
							$metadata[$currentField]['select_choices_or_calculations'] = $attributes['redcap:CheckboxChoices'];
							$skipCodeListItems = true;
						}
					} else {
						$currentField = $codeListOIDs[self::cleanVarName($attributes['OID'])];
					}
				}
				elseif ($tag == 'CodeListItem' && !$skipCodeListItems && isset($metadata[$currentField]) && !isset($skipFields[$currentField])) {
					// Get choice value to use next tag
					$currentCodedValue = $attributes['CodedValue'];
				}
				elseif ($tag == 'TranslatedText' && !$skipCodeListItems && isset($metadata[$currentField]) && $parentTag == 'CodeList' && !isset($skipFields[$currentField])) {
					// Add choice value and label
					if ($metadata[$currentField]['select_choices_or_calculations'] != "") {
						$metadata[$currentField]['select_choices_or_calculations'] .= " | ";
					}
					// Re-add HTML break tags in case they got converted to \n
					$value = str_replace(array("\r\n", "\n"), array("\n", ""), nl2br($value));
					// Add to field
					$metadata[$currentField]['select_choices_or_calculations'] .= "$currentCodedValue, $value";
				}
				elseif ($tag == 'redcap:FormattedTranslatedText' && !$skipCodeListItems && isset($metadata[$currentField]) && $parentTag == 'CodeList' && !isset($skipFields[$currentField])) {
					// Add choice value and label
					if (isset($metadata_choices_formatted[$currentField]) && $metadata_choices_formatted[$currentField] != "") {
						$metadata_choices_formatted[$currentField] .= " | ";
					}
					$metadata_choices_formatted[$currentField] .= "$currentCodedValue, $value";
				}
				elseif ($tag == 'redcap:RepeatingEvent' && isset($attributes['redcap:UniqueEventName'])) {
					$RepeatingInstrumentsAndEvents[$attributes['redcap:UniqueEventName']] = 'WHOLE';
				}
				elseif ($tag == 'redcap:RepeatingInstrument' && isset($attributes['redcap:UniqueEventName']) && isset($attributes['redcap:RepeatInstrument'])) {
					$RepeatingInstrumentsAndEvents[$attributes['redcap:UniqueEventName']][$attributes['redcap:RepeatInstrument']] = $attributes['redcap:CustomLabel'];
				}
				// Catch all other redcap vendor extensions
				elseif (substr($tag, 0, 7) == 'redcap:' && isset($attributes) && !empty($attributes)) {
					list ($prefix, $table) = explode(':', $tag, 2);
					$db_table = "redcap_".fromCamelCase($table);
					if ($db_table == 'redcap_odm_attachment') {
						$attributes['Content'] = $value;
						$fileId = $attributes['ID'];
						unset($attributes['ID']);
						$redcapVendorExt[$db_table][$fileId] = $attributes;
					} else {
						$redcapVendorExt[$db_table][] = $attributes;
					}
				}
			}
			## DATA
			if ($hasData)
			{
				// Default subtype and encoding
				$subtype = null;
				// Record
				if ($tag == 'SubjectData' && isset($attributes['SubjectKey'])) {
					$currentRecord = $attributes['SubjectKey'];
					$RepeatInstrument = "";
					$RepeatInstance = "1";
					// Get the recordId field if contained in tag (if no metadata in ODM)
					if (!$hasMetadata && isset($attributes['redcap:RecordIdField'])) {
						$recordIdField = $attributes['redcap:RecordIdField'];
					}
				}
				// Event
				if ($tag == 'StudyEventData' && isset($attributes['StudyEventOID'])) {
					$currentEvent = self::cleanVarName(isset($attributes['redcap:UniqueEventName']) ? $attributes['redcap:UniqueEventName'] : $attributes['StudyEventOID']);
					$StudyEventRepeatKey = isset($attributes['StudyEventRepeatKey']) ? $attributes['StudyEventRepeatKey'] : 1;
					$currentInstance = $StudyEventRepeatKey;
					if ($StudyEventRepeatKey > 1) {
						$RepeatKeys[$currentEvent] = 'WHOLE';
						$hasRepeatingData = true;
					}
				}
				// Form
				if ($tag == 'FormData' && isset($attributes['FormOID'])) {
					// If $currentEvent is null, then this is a classic project metadata+data, so auto-add unique event name
					if ($currentEvent === null) $currentEvent = 'event_1_arm_1';
					// Get data entry form name
					list ($nothing, $currentDataForm) = explode(".", $attributes['FormOID'], 2);
					$FormRepeatKey = isset($attributes['FormRepeatKey']) ? $attributes['FormRepeatKey'] : 1;					
					if ($FormRepeatKey > 1) {
						if (!is_array($RepeatKeys[$currentEvent.""])) {
							$RepeatKeys[$currentEvent.""] = array();
						}
						$RepeatKeys[$currentEvent.""][$currentDataForm] = true;
						$currentInstance = $FormRepeatKey;
						$hasRepeatingData = true;
					}
					// If we already know what the repeating forms/events are (because we have metadata or are in a project), 
					// then set the repeat instrument and instance correctly here (rather than guessing now and reparsing later).
					if (!empty($RepeatingInstrumentsAndEvents)) {
						if ($currentEvent != '' && isset($RepeatingInstrumentsAndEvents[$currentEvent.""]) && !is_array($RepeatingInstrumentsAndEvents[$currentEvent.""])) {
							// If current event is a repeating event, then set instrument as blank
							$currentRepeatForm = "";
							$hasRepeatingData = true;
						}
						elseif (!isset($RepeatingInstrumentsAndEvents[$currentEvent.""][$currentDataForm])) {
							// If current form is not a repeating form or event, then set as blank
							$currentRepeatForm = "";
							$currentInstance = "";
						} else {
							// Repeating form
							$currentRepeatForm = $currentDataForm;
							$currentInstance = $FormRepeatKey;							
							$hasRepeatingData = true;
						}
					}
				}
				// Check for ItemDataAny type
				if ($tag != 'ItemData' && substr($tag, 0, 8) == 'ItemData'&& isset($attributes['ItemOID'])) {
					// If has no value, then set to blank string
					if (!isset($value)) $value = "";
					// Set subtype
					$subtype = substr($tag, 8);
					// Change to ItemData type
					$tag = 'ItemData';
					// Set value as Value attribute so it works as ItemData
					if (substr($subtype, 0, 3) == 'Hex') {
						// Decode hex value
						if ($subtype == 'HexFloat') {
							$attributes['Value'] = base_convert($value, 16, 10); /// base16 = hex
						} elseif ($subtype == 'HexBinary') {
							$attributes['Value'] = hex2bin($value);
						}
					} elseif (substr($subtype, 0, 6) == 'Base64') {
						// Decode base64 value
						if ($subtype == 'Base64Float') {
							// $attributes['Value'] = base_convert($value, 64, 10); /// base_convert cannot accept "64" as base
							$attributes['Value'] = base64_decode($value);
						} elseif ($subtype == 'Base64Binary') {
							$attributes['Value'] = base64_decode($value);
						}
					} elseif ($subtype == 'Double') {
						// Convert double to float value
						$attributes['Value'] = floatval($value);
					} elseif ($subtype == 'Boolean') {
						// Convert double to float value
						$value = (string)$value;
						if ($value != "") {
							$attributes['Value'] = ($value === '1' || strtolower($value) === 'true' || strtolower($value) === 'yes') ? "1" : "0";
						} else {
							$attributes['Value'] = "";
						}
					} else {
						// Set value as is
						$attributes['Value'] = $value;
					}
				}
				// Data field and value
				if ($subtype !== null || ($tag == 'ItemData' && isset($attributes['Value']) && isset($attributes['ItemOID'])))
				{
					// Clean the field name
					$attributes['ItemOID'] = self::cleanVarName($attributes['ItemOID']);
					// Make sure we add record ID field first
					if ($recordIdField !== null) {
						$fields[$recordIdField] = $recordIdField;
						if (!isset($data["$currentRecord-$currentEvent-$currentRepeatForm-$currentInstance"][$recordIdField])) {
							$data["$currentRecord-$currentEvent-$currentRepeatForm-$currentInstance"][$recordIdField] = $currentRecord;
						}
					}
					// If ignoring DAG assignments in the data, then go to next loop
					if ($removeDagAssignmentsInData && $attributes['ItemOID'] == 'redcap_data_access_group') {
						continue;
					}
					// Add event name if longitudinal
					if ($currentEvent !== null) {
						$fields['redcap_event_name'] = 'redcap_event_name';
					}
					if ($currentEvent !== null || (!$hasMetadata || ($hasMetadata && count($events) > 1)) && !isset($data["$currentRecord-$currentEvent-$currentRepeatForm-$currentInstance"]['redcap_event_name'])) {
						$data["$currentRecord-$currentEvent-$currentRepeatForm-$currentInstance"]['redcap_event_name'] = $currentEvent;
					}
					// File Upload or Signature field
					if ($subtype == 'Base64Binary') {
						// Get doc name
						if (isset($attributes['redcap:DocName'])) {
							$fileAttr['doc_name'] = $attributes['redcap:DocName'];
						} else {
							$fileAttr['doc_name'] = substr(sha1(rand()), 0, 10);
						}
						// Create as file in temp directory. Replace any spaces with underscores in filename for compatibility.
						$filename_tmp = APP_PATH_TEMP . substr(sha1(rand()), 0, 8) . str_replace(" ", "_", $fileAttr['doc_name']);
						file_put_contents($filename_tmp, $attributes['Value']);
						// Get mime type
						if (isset($attributes['redcap:MimeType'])) {
							$fileAttr['mime_type'] = $attributes['redcap:MimeType'];
						} else {
							// Attempt to determine the mime type and file extension since we don't know them
							$fileAttr['mime_type'] = Files::mime_content_type($filename_tmp);
							$file_extension = Files::get_file_extension_by_mime_type($fileAttr['mime_type']);
							if ($file_extension !== false) {
								// Add file extension that was determined
								$fileAttr['doc_name'] .= ".$file_extension";
							}
						}
						// Set file attributes as if just uploaded
						$edoc_id = Files::uploadFile(array('name'=>$fileAttr['doc_name'], 'type'=>$fileAttr['mime_type'],
													'size'=>filesize($filename_tmp), 'tmp_name'=>$filename_tmp));
						if (is_numeric($edoc_id)) {
							$attributes['Value'] = $edoc_id;
						}
					}
					// Add value
					$data["$currentRecord-$currentEvent-$currentRepeatForm-$currentInstance"][$attributes['ItemOID']] = $attributes['Value'];
					// Make sure we add the field to our field list array
					if (!isset($fields[$attributes['ItemOID']])) {
						$fields[$attributes['ItemOID']] = $attributes['ItemOID'];
					}
				}
			}
		}
		
		// If we're importing the metadata and having repeating forms/events, then set them here for parsing the repeating stuff in the data
		if (($hasMetadata || $Proj !== null) && !empty($RepeatingInstrumentsAndEvents)) {
			$RepeatKeys = $RepeatingInstrumentsAndEvents;			
		}
		
		// If has repeating data, add the 2 repeating fields to $fields array
		if ($hasRepeatingData) {
			$fields['redcap_repeat_instrument'] = 'redcap_repeat_instrument';
			$fields['redcap_repeat_instance'] = 'redcap_repeat_instance';
		}

		// Metadata: Add any HTML-formatted MC choices
		if (!empty($metadata_choices_formatted)) {
			foreach ($metadata_choices_formatted as $this_field=>$these_choices) {
				// Get array of non-formatted choices
				$nonFormatChoices = parseEnum(str_replace("|", "\\n", $metadata[$this_field]['select_choices_or_calculations']));
				$formatChoices = parseEnum(str_replace("|", "\\n", $these_choices));
				$thisEnum = array();
				foreach ($nonFormatChoices as $this_key=>$this_label) {
					if (isset($formatChoices[$this_key])) {
						$nonFormatChoices[$this_key] = $this_label = $formatChoices[$this_key];
					}
					$thisEnum[] = "$this_key, $this_label";
				}
				// Rebuild the enum string 
				$metadata[$this_field]['select_choices_or_calculations'] = implode(" | ", $thisEnum);
			}
		}

		// SAVE METADATA
		$errors = array();
		// Commit changes for arms, events, and metadata
		if ($hasMetadata) {
			// Begin transaction
			db_query("SET AUTOCOMMIT=0");
			db_query("BEGIN");
			// If not longitudinal (missing Protocol and Study tags), then pre-fill single arm and event
			if ($isLongitudinal && count($events) == 1) $isLongitudinal = false;
			if (!$isLongitudinal) {
				$arms = array(array('arm_num'=>'1', 'name'=>"Arm 1"));
				$events = array(array('event_name'=>'Event 1', 'arm_num'=>'1', 'day_offset'=>'1', 'offset_min'=>'0', 'offset_max'=>'0', 'unique_event_name'=>'event_1_arm_1'));
				$eventsForms = array();
				foreach(array_keys($forms) as $this_form) {
					$eventsForms[] = array('arm_num'=>'1', 'unique_event_name'=>'event_1_arm_1', 'form'=>$this_form);
				}
			}
			// Arms
			list ($armCount, $errors) = Arm::addArms(PROJECT_ID, array_values($arms), true);
			if (empty($errors)) {
				// Events
				list ($eventCount, $errors) = Event::addEvents(PROJECT_ID, array_values($events), true);
				if (empty($errors)) {
					// Set project as longitudinal if more than 1 event
					if (count($events) > 1) Project::setAttribute('repeatforms', '1');
					// Metadata
					list ($fieldCount, $errors) = MetaData::saveMetadataFlat($metadata, true);
					if (empty($errors)) {
						// Save any extra metadata attributes
						MetaData::saveMetadataExtraAttr($metadata_extra);
						// Event Mapping
						if ($isLongitudinal) {
							list ($eventMappingCount, $errors) = Event::saveEventMapping(PROJECT_ID, array_values($eventsForms), $forms);
						}
					}
				}
			}
			// Add form labels from $forms
			foreach ($forms as $this_form=>$this_label) {
				MetaData::setFormLabel($this_form, $this_label);
			}
			// Add project attributes, if any
			foreach ($projectAttrValues as $this_attr=>$this_value) {
				Project::setAttribute($this_attr, $this_value);
			}
			// Add any repeating forms/events
			if (!empty($RepeatingInstrumentsAndEvents)) {
				self::addRepeatingInstrumentsAndEvents($RepeatingInstrumentsAndEvents);
			}
			// Add various vendor extension tags (e.g., surveys, data quality rules)
			if (!empty($redcapVendorExt)) {
				self::addArrayToTable($redcapVendorExt);
			}
			// Free up memory
			unset($arms, $events, $eventsForms, $metadata, $forms);
			// If any errors occurred, stop here
			if (!empty($errors)) {
				db_query("ROLLBACK");
				db_query("SET AUTOCOMMIT=1");
				return array('errors'=>$errors);
			}
			// Commit changes
			db_query("COMMIT");
			db_query("SET AUTOCOMMIT=1");
		}

		// SAVE DATA (note: we don't need to do a transaction here because saveData() does that automatically)
		$errors = array();
		if ($hasData && !empty($data))
		{
			// If we don't have metadata ODM to tell us beforehand what were the repeating events/forms, then we'll have
			// to reparse the data now to clump it into correct record-event-repeat_instrument-repeat_instance keys
			// since we couldn't do it while initially collecting data in $data.
			if ($hasRepeatingData && !$hasMetadata) 
			{
				$data2 = array();
				foreach (array_keys($data) as $recordEvent) 
				{
					list ($this_record, $this_event, $this_repeat_instrument, $this_instance) = explode_right("-", $recordEvent, 4);
					// Get true repeating events/forms values since our originals might now have been right (since we didn't have the metadata beforehand)
					$this_repeat_instrument = isset($RepeatKeys[$this_event][$this_repeat_instrument]) ? $this_repeat_instrument : "";
					$this_instance = isset($RepeatKeys[$this_event][$this_repeat_instrument]) ? $this_instance 
						: ((isset($RepeatKeys[$this_event]) && !is_array($RepeatKeys[$this_event])) ? $this_instance : "");
					// Add repeat instrument and repeat instance fields
					$data2["$this_record-$this_event-$this_repeat_instrument-$this_instance"]['redcap_repeat_instrument'] = $this_repeat_instrument;
					$data2["$this_record-$this_event-$this_repeat_instrument-$this_instance"]['redcap_repeat_instance'] = $this_instance;
					// Loop through all item values and add to $data2
					foreach ($data[$recordEvent] as $this_field=>$this_value) {
						// If value doesn't exist or exists as blank, then add
						if (!isset($data2["$this_record-$this_event-$this_repeat_instrument-$this_instance"][$this_field])
							|| $data2["$this_record-$this_event-$this_repeat_instrument-$this_instance"][$this_field] == '') 
						{
							$data2["$this_record-$this_event-$this_repeat_instrument-$this_instance"][$this_field] = $this_value;
						}
					}
					// Remove to save memory
					unset($data[$recordEvent]);
				}
				// Replace $data with $data2 now that we're done restructuring
				$data = $data2;
				unset($data2);
			}
			// Open connection to create file in memory and write to it
			$fp = fopen('php://memory', "x+");
			// Add header row to CSV
			fputcsv($fp, $fields);
			// Now that we have all data in array format, loop through it and convert to CSV
			foreach ($data as $recordEvent=>&$values) {
				// Load fields with blank defaults
				$line = array_fill_keys($fields, "");
				list ($this_record, $this_event, $this_repeat_instrument, $this_instance) = explode_right("-", $recordEvent, 4);
				// If we're missing the recordId field but know which variable it is, then set record name as recordID field's value
				if ($recordIdField != '' && !isset($line[$recordIdField])) {
					$line[$recordIdField] = $this_record;
				}
				// Add repeat instrument and repeat instance fields
				if ($hasRepeatingData) {
					$line['redcap_repeat_instrument'] = $this_repeat_instrument;
					$line['redcap_repeat_instance'] = $this_instance;
				}
				// Loop through the values we have an overlay onto defaults
				foreach ($values as $this_field=>$this_value) {
					if (isset($line[$this_field])) $line[$this_field] = $this_value;
				}
				// Remove line to conserve memory
				unset($data[$recordEvent], $values);
				// Add record-event to CSV
				fputcsv($fp, $line);
			}
			unset($data);
			// Open file for reading and output CSV to string
			fseek($fp, 0);
			$dataCsv = trim(stream_get_contents($fp));			
			fclose($fp);
			// If only returning the CSV data and NOT saving it, then stop here
			if ($returnCsvDataOnly) return $dataCsv;
			// Save data
			$saveDataResponse = REDCap::saveData(PROJECT_ID, 'csv', $dataCsv, 'normal', 'YMD', 'flat', null, false,
												 true, true, false, true, array(), false, false);
			$recordCount = count($saveDataResponse['ids']);
			$errors = is_array($saveDataResponse['errors']) ? $saveDataResponse['errors'] : array($saveDataResponse['errors']);
			// If any errors occurred, stop here
			if (!empty($errors)) {
				// If metadata was submitted (i.e., creating new project), then delete this project altogether to appear as if it never was created
				if ($hasMetadata) {
					// Delete project permanently
					deleteProjectNow(PROJECT_ID, false);
				}
				// Return errors
				return array('errors'=>$errors);
			}
		}

		// If we got this far, we were successful
		return array('arms'=>$armCount, 'events'=>$eventCount, 'fields'=>$fieldCount, 'records'=>$recordCount, 'errors'=>$errors);
	}
	
	
	// Save to db table any repeating forms/events uploaded in the ODM file
	public static function addRepeatingInstrumentsAndEvents(&$RepeatingInstrumentsAndEvents)
	{
		$Proj = new Project(PROJECT_ID);
		foreach ($RepeatingInstrumentsAndEvents as $event_name=>$forms) {
			if ($Proj->longitudinal) {
				$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
				if (!is_numeric($event_id)) continue;
			} else {
				$event_id = $Proj->firstEventId;
				$event_name2 = $Proj->getUniqueEventNames($event_id);
				// If unique event name doesn't match what was in the XML file, then correct it in the array
				if ($event_name != $event_name2) {
					unset($RepeatingInstrumentsAndEvents[$event_name]);
					$RepeatingInstrumentsAndEvents[$event_name2] = $forms;
				}
			}
			if (is_array($forms)) {
				foreach ($forms as $form=>$customLabel) {
					$sql = "insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) 
							values ($event_id, '".db_escape($form)."', ".checkNull(filter_tags($customLabel)).")";
					db_query($sql);
				}
			} else {
				$sql = "insert into redcap_events_repeat (event_id) values ($event_id)";
				db_query($sql);
			}
		}
	}
	

	// Validate the ODM document to make sure it has the basic components needed
	public static function validateOdmInitial($xml)
	{
		// Write XML to temp file so that we can begin parsing it
		$tempfile = tempnam(sys_get_temp_dir(), "red");
		$fp = fopen($tempfile, 'w');
		fwrite($fp, $xml);
		fclose($fp);
		$reader = new XMLReader();
		$success = $reader->open($tempfile, "UTF-8");
		// Set flags to determine what the XML file has
		$hasOdmTag = $hasStudyTag = $hasGlobalVarsTag = $hasMetaDataVersionTag = $hasClinicalDataTag = false;
		// Loop through tags in XML file
		while ($reader->read())
		{
			// TAG/ELEMENT			
			if ($reader->nodeType == XMLReader::ELEMENT)
			{
				// Tag name
				$tagname = $reader->name;
				// ODM tag
				if (!$hasOdmTag) {
					if ($tagname == 'ODM') {
						$hasOdmTag = true;
					} else {
						// If we don't yet have the ODM tag, then stop now
						return false;
					}
				}
				// Study tag
				if ($hasOdmTag && !$hasStudyTag && $tagname == 'Study') {
					$hasStudyTag = true;
				}
				// GlobalVariables tag
				if ($hasStudyTag && !$hasGlobalVarsTag && $tagname == 'GlobalVariables') {
					$hasGlobalVarsTag = true;
				}
				// MetaDataVersion tag
				if ($hasStudyTag && !$hasMetaDataVersionTag && $tagname == 'MetaDataVersion') {
					$hasMetaDataVersionTag = true;
				}
				// ClinicalData tag
				if ($hasOdmTag && !$hasClinicalDataTag && $tagname == 'ClinicalData') {
					$hasClinicalDataTag = true;
				}
			}
		}
		// Remove the temp file
		$reader->close();
		unlink($tempfile);
		// Do we have everything?
		return array(
			// Is valid ODM?
			($hasOdmTag && (($hasClinicalDataTag && !$hasStudyTag) || ($hasStudyTag && $hasGlobalVarsTag && $hasMetaDataVersionTag))),
			// Has metadata?
			($hasStudyTag && $hasGlobalVarsTag && $hasMetaDataVersionTag),
			// Has data?
			$hasClinicalDataTag
		);
	}


	// Get XML of opening <XML> and <ODM> tags for ODM export
	public static function getOdmOpeningTag($project_title)
	{
		global $redcap_version;
		return "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n"
			 . "<ODM xmlns=\"http://www.cdisc.org/ns/odm/v1.3\""
			 . " xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\""
			 . " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""
			 . " xmlns:redcap=\"https://projectredcap.org\""
			 . " xsi:schemaLocation=\"http://www.cdisc.org/ns/odm/v1.3 schema/odm/ODM1-3-1.xsd\""
			 . " ODMVersion=\"1.3.1\""
			 . " FileOID=\"000-00-0000\""
			 . " FileType=\"Snapshot\""
			 . " Description=\"".RCView::escape($project_title)."\""
			 . " AsOfDateTime=\"" . str_replace(" ", "T", NOW) . "\""
			 . " CreationDateTime=\"" . str_replace(" ", "T", NOW) . "\""
			 . " SourceSystem=\"REDCap\""
			 . " SourceSystemVersion=\"$redcap_version\">\n";
	}


	// Get XML of closing <ODM> tag for ODM export
	public static function getOdmClosingTag()
	{
		return "</ODM>";
	}


	// Clean field variables, unique event names, etc. to ensure only lower case letters, numbers, and underscores
	public static function cleanVarName($name)
	{
		// Make lower case
		$name = trim(strtolower(html_entity_decode($name, ENT_QUOTES)));
		// Convert spaces and dots to underscores
		$name = trim(str_replace(array(" ", "."), array("_", "_"), $name));
		// Remove invalid characters
		$name = preg_replace("/[^0-9a-z_]/", "", $name);
		// Remove beginning/ending underscores
		while (substr($name, 0, 1) == '_') 		$name = substr($name, 1);
		while (substr($name, -1) == '_') 		$name = substr($name, 0, -1);
		// If somehow still blank, assign random alphanum as name
		if ($name == '') $name = substr(sha1(rand()), 0, 10);
		// Return cleaned value
		return $name;
	}


	// Check if any errors occurred when uploading an ODM file on Create Project page.
	// If so, display an error message.
	public static function checkErrorsOdmFileUpload($odmFile)
	{
		global $lang;
		// ODM file size check: Check if file is larger than max file upload limit
		if (isset($odmFile['size']) && $odmFile['size'] > 0 && (($odmFile['size']/1024/1024) > maxUploadSize() || $odmFile['error'] != UPLOAD_ERR_OK))
		{
			// Delete temp file
			unlink($odmFile['tmp_name']);
			// Give error response
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();
			?>
			<table border=0 align=center cellpadding=0 cellspacing=0 width=100%>
			<tr valign=top><td colspan=2 align=center><img id="logo_home" src="<?php echo APP_PATH_IMAGES ?>redcap-logo-large.png"></td></tr>
			<tr valign=top><td colspan=2 align=center>
			<?php
			// TABS
			include APP_PATH_VIEWS . 'HomeTabs.php';
			
			// Errors
			print "<b>ERROR: CANNOT UPLOAD FILE!</b><br><br>The uploaded file is ".round_up($odmFile['size']/1024/1024)." MB in size,
					thus exceeding the maximum file size limit of ".maxUploadSize()." MB.";
			$objHtmlPage->PrintFooter();
			exit;
		}
	}

}