<?php

/**
 * PROJECT
 * Object that holds all basic attributes of a project (metadata fields and forms, events, project-level values)
 */
class Project
{
	// Array of config fields that will overwrite project-level fields of the same name if the project-level fields are blank
	public static $overwritableGlobalVars = array('project_contact_name', 'project_contact_email', 'institution', 'site_org_type', 'grant_cite', 'headerlogo');
	// Reserved field names that cannot be used as project field names/variables
	public static $reserved_field_names = array(
		// These variables are forbidden because they are used internally by REDCap
		"redcap_event_name"=>"Event Name", "redcap_csrf_token"=>"REDCap CSRF Token",
		"redcap_survey_timestamp"=>"Survey Timestamp", "redcap_survey_identifier"=>"Survey Identifier",
		"redcap_survey_return_code"=>"Survey Return Code", "redcap_data_access_group"=>"Data Access Group",
		"hidden_edit_flag"=>"hidden_edit_flag", "instance"=>"instance", "redcap_repeat_instance"=>"Repeat Instance",
		"redcap_repeat_instrument"=>"Repeat Instrument",
		// These variables are forbidden because some web browsers (mostly IE) throw errors when they are using in branching logic or calculations
		"submit"=>"submit", "new"=>"new", "return"=>"return", "continue"=>"continue", "case"=>"case", "switch"=>"switch",
		"class"=>"class", "enum"=>"enum", "catch"=>"catch", "throw"=>"throw", "document"=>"document", "super"=>"super",
		"focus"=>"focus", "elements"=>"elements", "action"=>"action", "event_id"=>"event_id"
	);
	// Set time delay (in days) for deleting projects after they have been scheduled for deletion
	const DELETE_PROJECT_DAY_LAG = 30;
	// Maximum length of grid_name string in metadata (i.e. matrix group name)
	const GRID_NAME_MAX_LENGTH = 60;
	// Current project_id for this object
	public $project_id = null;
	// Array with project's basic values
	public $project = null;
	// Array with field_names as keys and other attributes as sub-array
	public $metadata = null;
	// Array of Draft Mode fields with field_names as keys and other attributes as sub-array
	public $metadata_temp =null;
	// Record identifer variable
	public $table_pk;
	public $table_pk_temp;
	// Record identifer variable's status as PHI
	public $table_pk_phi;
	// Record identifer variable's label
	public $table_pk_label;
	// Array of form names with form_position and form menu description
	public $forms = null;
	public $forms_temp = null;
	// Array of events
	public $events = null;
	// Array of events and forms (event_id as array keys)
	public $eventsForms = null;
	// Array of event information (event_id as array keys)
	public $eventInfo = null;
	// Array of unique event names
	public $uniqueEventNames = null;
	// Array of unique Data Access Group names
	public $uniqueGroupNames = null;
	// Array of surveys (survey_id as array keys)
	public $surveys = null;
	// Flag if fields are out of order
	public $fieldsOutOfOrder = false;
	// Number of project fields
	public $numFields = 0;
	public $numFieldsTemp = 0;
	// Number of data entry forms
	public $numForms = 0;
	public $numFormsTemp = 0;
	// Number of arms
	public $numArms = 1;
	// Number of events
	public $numEvents = 0;
	// If project is longitudinal (has multiple events)
	public $longitudinal = false;
	// If project has multiple arms
	public $multiple_arms = false;
	// First form_name
	public $firstForm;
	// First form_menu_description name
	public $firstFormMenu;
	// First arm_id
	public $firstArmId = null;
	// First arm name
	public $firstArmName = null;
	// First arm number
	public $firstArmNum = null;
	// First event_id
	public $firstEventId = null;
	// survey_id of first form
	public $firstFormSurveyId = null;
	// First event name
	public $firstEventName = null;
	// Contains forms downloaded from the REDCap Shared Library
	public $formsFromLibrary = null;
	// Array of all Data Access Groups
	public $groups = null;
	// Array of all users in Data Access Groups
	public $groupUsers = null;
	// Array of unique list of matrix group names
	public $matrixGroupNames     = null;
	public $matrixGroupNamesTemp = null;
	// Array of unique list of matrix group names that have ranking
	public $matrixGroupHasRanking = null;
	// Boolean to designate if any File Upload fields exist in the project
	public $hasFileUploadFields = null;
	// Array of repeating events and forms, with event_id as key and form_names as values
	public $RepeatingFormsEvents = null;
	public $eventsWhereAllFormsAreRepeatingForms = null;
	// Boolean to designate if any surveys have the e-Consent Framework enabled
	public $hasEconsentSurveys = false;
	// Survey email invitation fields (across all surveys in the project)
	public $hasSurveyEmailInvitationFields = false;
	public $surveyEmailInvitationFields = array();
	// Store the executed query results for SQL fields
	public $executedSqlFields = array();
	/** @var array A cache [project_id => instance] for all instantiated Project objects in a request. */
	private static $project_cache = array();

	/**
	 * Constructor
	 * @param mixed $this_project_id
	 */
	public function __construct($this_project_id = null)
	{
		// Set project_id for this object
		if ($this_project_id == null) {
			if (defined("PROJECT_ID")) {
				$this->project_id = PROJECT_ID;
			} else {
				throw new Exception('No project_id provided!');
			}
		} else {
			$this->project_id = $this_project_id;
		}
		// Validate project_id as numeric
		if (!is_numeric($this->project_id)) throw new Exception('Project_id must be numeric!');
		// If project already exists in the cache, then return its cached value from the array (no need to re-run queries to build it)
		if (isset(self::$project_cache[$this->project_id])) {
			// Set this object's attributes from cached one in array
			foreach (self::$project_cache[$this->project_id] as $key=>$val) {
				$this->$key = $val;
			}
		}
		else {
			// Load all project attributes
			$this->loadProjectValues();
			$this->loadMetadata();
			$this->loadEvents();
			$this->loadEventsForms();
			$this->loadSurveys();
			// Place the object into a larger array that is a collection of all Proj objects from this request. 
			// This will reduce number of queries run and allow us to not have to always call "global $Proj" inside methods.
			self::$project_cache[$this->project_id] = $this;
		}
	}

	public static function getDataEntry($form_names)
	{
		return '[' . implode(',1][', $form_names) . ',1]';
	}

	public static function insertUserRightsProjectCreator($project_id, $userid, $randomization, $mobile_app, $form_names)
	{
		$project_id = (int)$project_id;
		$userid = db_escape($userid);
		$data_entry = db_escape(self::getDataEntry($form_names));
		$randomization = (int)$randomization;
		$mobile_app = (int)$mobile_app;

		$sql = "
			INSERT INTO redcap_user_rights (
				project_id, username, data_entry, design, data_quality_design, data_quality_execute,
				random_setup, random_dashboard, random_perform, mobile_app, mobile_app_download_data
			) VALUES (
				$project_id, '$userid', '$data_entry', 1, 1, 1,
				$randomization, $randomization, $randomization, $mobile_app, $mobile_app
			)
		";

		$q = db_query($sql);

		return ($q && $q !== false);
	}

	public static function insertDefaultArmAndEvent($project_id)
	{
		$project_id = (int)$project_id;

		$sql = "
			INSERT INTO redcap_events_arms (
				project_id
			) VALUES (
				$project_id
			)
		";

		$q = db_query($sql);

		if($q && $q !== false)
		{
			$arm_id = db_insert_id();

			$sql = "
				INSERT INTO redcap_events_metadata (
					arm_id
				) VALUES (
					$arm_id
				)
			";

			$q = db_query($sql);

			return ($q && $q !== false);
		}

		return false;
	}

	public static function setDefaults($project_id)
	{
		// Get default values for redcap_projects table columns
		$redcap_projects_defaults = getTableColumns('redcap_projects');

		// Insert project defaults into redcap_projects

		$field_names = prep_implode(array_keys($redcap_projects_defaults));

		$sql = "
			SELECT *
			FROM redcap_config
			WHERE field_name IN ($field_names)
		";

		$q = db_query($sql);

		if(!$q || $q === false)
		{
			return;
		}

		$updateVals = array();

		while($row = db_fetch_assoc($q))
		{
			// If field is a project-level var that would overwrite a global var, then leave it blank so that global value is used
			if (in_array($row['field_name'], self::$overwritableGlobalVars)) continue;

			// Use checkNull if column's default value is NULL
			if ($redcap_projects_defaults[$row['field_name']] === null)
			{
				$updateVals[] = "{$row['field_name']} = " . checkNull($row['value']);
			}
			else
			{
				$updateVals[] = "{$row['field_name']} = '" . db_escape($row['value']) . "'";
			}
		}

		if(!empty($updateVals))
		{
			// Update table
			$set = implode(', ', $updateVals);

			$sql2 = "
				UPDATE redcap_projects
				SET $set
				WHERE project_id = $project_id
			";

			$q2 = db_query($sql2);

			if(!($q2 && $q2 !== false) && SUPER_USER) queryFail($sql2);
		}
	}

	public static function apiCreate($userid, $data, $odm=null)
	{
		global $auth_meth_global, $field_comment_log_enabled_default;

		$app_title = db_escape(Project::cleanTitle($data['project_title']));
		$project_name = db_escape(self::getValidProjectName($app_title));

		// Do not enable longitudinal or surveys by default
		$longitudinal = (isset($data['is_longitudinal']) && $data['is_longitudinal'] == '1') ? '1' : '0';
		$surveys_enabled = (isset($data['surveys_enabled']) && $data['surveys_enabled'] == '1') ? '1' : '0';
		// Eanble record autonumbering by default
		$record_autonumbering_enabled = (isset($data['record_autonumbering_enabled']) && $data['record_autonumbering_enabled'] == '0') ? '0' : '1';

		$purpose = (isset($data['purpose']) && is_numeric($data['purpose'])) ? (int)$data['purpose'] : 'NULL';
		$purpose_other = (isset($data['purpose_other']) && $purpose == '1') ? checkNull(self::purpToStr($data['purpose_other'])) : 'NULL';

		$now = NOW;
		$created_by = db_escape(User::getUIIDByUsername($userid));

		$auth_meth = db_escape($auth_meth_global);
		$data_resolution_enabled = $field_comment_log_enabled_default == '0' ? 0 : 1;
		$project_note = checkNull(trim($data['project_notes']));
		$log_event_table = Logging::getSmallestLogEventTable();

		$sql = "
			INSERT INTO redcap_projects (
				project_name, scheduling, repeatforms, purpose, purpose_other, app_title, creation_time, created_by,
				surveys_enabled, auto_inc_set, randomization, auth_meth, data_resolution_enabled, project_note, log_event_table
			) VALUES (
				'$project_name', 0, $longitudinal, $purpose, $purpose_other, '$app_title', '$now', '$created_by',
				$surveys_enabled, $record_autonumbering_enabled, 0, '$auth_meth', $data_resolution_enabled, $project_note, '$log_event_table'
			)
		";

		$q = db_query($sql);

		if($q && $q !== false)
		{
			// Set project defaults
			$project_id = db_insert_id();
			defined("PROJECT_ID") or define("PROJECT_ID", $project_id);
			self::setDefaults($project_id);
			// If ODM XML was provided, then import it now
			if ($odm !== null) {
				// Get uploaded file's contents and parse it
				$odm_response = ODM::parseOdm($odm);
				// Check for errors
				if (!empty($odm_response['errors'])) {
					// Remove any HTML in the errors
					$errors = array();
					foreach ($odm_response['errors'] as $this_error) {
						$errors[] = strip_tags($this_error);
					}
					// Return the errors
					return $errors;
				}
				// Get form names to use for user rights of creator
				$sql = "select distinct form_name from redcap_metadata where project_id = $project_id";
				$q = db_query($sql);
				$form_names = array();
				while ($row = db_fetch_assoc($q)) {
					$form_names[] = $row['form_name'];
				}
			} else {
				// Insert Arms, Events, and Metadata
				self::insertDefaultArmAndEvent($project_id);
				$form_names = createMetadata($project_id, 0);
			}
			// Set user rights of creator
			self::insertUserRightsProjectCreator($project_id, $userid, 0, 0, $form_names);
			// Generate new API tokent for project-user
			$db = new RedCapDB();
			$db->setAPIToken($userid, $project_id);
			$db->saveAPIRights($userid, $project_id, 1, 1, 1);
			return $db->getAPIToken($userid, $project_id);
		}
		return false;
	}

	// Available attributes for API Create Project method
	public static function getApiCreateProjectAttr()
	{
		return array('project_title', 'purpose', 'purpose_other', 'project_notes', 'is_longitudinal', 'surveys_enabled',
					 'record_autonumbering_enabled');
	}


	public static function validateApiCreateProjectInput($data, $odm=null)
	{
		global $lang;

		$errors = array();

		// Check for basic attributes needed
		if (empty($data) || !isset($data['project_title']) || $data['project_title'] == ''
			|| !isset($data['purpose']) || !is_numeric($data['purpose']) || $data['purpose'] < 0 || $data['purpose'] > 4)
		{
			return array($lang['design_641'] . " project_title, purpose" . $lang['period'] . " " . $lang['api_121']);
		}
		if ($data['purpose'] == '1' && (!isset($data['purpose']) || $data['purpose_other'] == '')) {
			return array($lang['api_122']);
		}

		// Check ODM XML
		if ($odm !== null) {
			$odm = trim($odm);
			list ($validOdm, $hasMetadata, $hasData) = ODM::validateOdmInitial($odm);
			if (!$validOdm || !$hasMetadata) return array($lang['data_import_tool_240']);
		}

		// Return any errors
		return $errors;
	}


	public static function purpToStr($purpose_other)
	{
		return is_array($purpose_other) ? implode(',', $purpose_other) : $purpose_other;
	}

	public static function projectNameExists($name)
	{
		$name = db_escape($name);

		$sql = "
			SELECT COUNT(1)
			FROM redcap_projects
			WHERE project_name = '$name'
		";
		$q = db_query($sql);
		return ($q && $q !== false) ? db_result($q, 0) : false;
	}

	public static function cleanTitle($title)
	{
		return htmlspecialchars(filter_tags(html_entity_decode($title, ENT_QUOTES)), ENT_QUOTES);
	}

	public static function getValidProjectName($title)
	{
		$name = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower(html_entity_decode($title, ENT_QUOTES))));

		// Remove double underscores, beginning numerals, and beginning/ending underscores
		while (strpos($name, '__') !== false) 	$name = str_replace('__', '_', $name);
		while (substr($name, 0, 1) == '_') 		$name = substr($name, 1);
		while (substr($name, -1) == '_') 		$name = substr($name, 0, -1);
		while (is_numeric(substr($name, 0, 1))) $name = substr($name, 1);

		// If longer than 50 characters, then shorten it to that length
		if (strlen($name) > 50) $name = substr($name, 0, 50);

		// Check to make sure the current value isn't already a project title. If it is, append 4 random alphanums and do over.
		$exists = self::projectNameExists($name);
		while($exists)
		{
			if(strlen($name) > 46) substr($name, 0, 46);
			$name .= substr(sha1(rand()), 0, 4);
			// Check again if still exists
			$exists = self::projectNameExists($name);
		}

		// If somehow still blank, assign random alphanum as app_name
		if($name == '')	$name = substr(sha1(rand()), 0, 10);

		return $name;
	}

	public function setProjectValues()
	{
		$project_fields = Project::getAttributesApiImportProjectInfo();
		foreach ($this->project as $key=>$value)
		{
			if (isset($project_fields[$key]))
			{
				if ($key == "longitudinal")
				{
					$key = "repeatforms";
				}
				$sql = "update redcap_projects SET $key='".db_escape($value)."' WHERE project_id = ".$this->project_id;
				$q = db_query($sql);
			}
		}
	}

	// Load this project's basic values from redcap_projects
	public function loadProjectValues()
	{
		$this->project = array();
		$sql = "select * from redcap_projects where project_id = " . $this->project_id;
		$q = db_query($sql);
		foreach (db_fetch_assoc($q) as $key=>$value)
		{
			$this->project[$key] = $value;
		}
		db_free_result($q);
		// Add miscellaneous project values
		$this->twilio_enabled_surveys = ($this->project['twilio_enabled'] && ($this->project['twilio_modules_enabled'] == 'SURVEYS' || $this->project['twilio_modules_enabled'] == 'SURVEYS_ALERTS'));
		$this->twilio_enabled_alerts = ($this->project['twilio_enabled'] && ($this->project['twilio_modules_enabled'] == 'ALERTS' || $this->project['twilio_modules_enabled'] == 'SURVEYS_ALERTS'));
	}

	// Load this project's metadata into array
	public function loadMetadata()
	{
		// Make sure loadProjectValues() has been run
		if ($this->project == null) $this->loadProjectValues();
		// Initialize
		$this->numFields = 0;
		$this->numForms  = 0;
		$this->metadata = array();
		$this->forms = array();
		$this->lastFormName = null;
		$this->hasFileUploadFields = false;
		$this->matrixGroupNames = array();
		$this->matrixGroupHasRanking = array();

		// Loop through all fields
		$sql = "select * from redcap_metadata where project_id = " . $this->project_id . " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// If project somehow has a blank field_name, delete it
			if ($row['field_name'] == "")
			{
				// Delete it
				db_query("delete from redcap_metadata where project_id = " . $this->project_id . " and field_name = ''");
				// Now skip to next field
				continue;
			}
			// If form name is somehow missing for first field of a form (how?), set its value as the form_name of the NEXT field
			if ($row['form_name'] == "")
			{
				// Get form_name of next form
				$sql = "select form_name from redcap_metadata where project_id = " . $this->project_id . "
						and field_order > " . $row['field_order'] . " order by field_order limit 1";
				$q2 = db_query($sql);
				if (db_num_rows($q2)) {
					$row['form_name'] = $next_field_form_name = db_result($q2, 0);
				} else {
					$row['form_name'] = $next_field_form_name = "my_first_instrument";
				}
				// Set form_name
				$sql = "update redcap_metadata set form_name = '".db_escape($next_field_form_name)."'
						where project_id = " . $this->project_id . " and field_name = '".db_escape($row['field_name'])."'";
				db_query($sql);
			}
			// If form menu is somehow missing for first field of a form (how?), set its value as the form_name
			if ($this->lastFormName != $row['form_name'] && $row['form_name'] != "" && $row['form_menu_description'] == "")
			{
				$row['form_menu_description'] = trim(ucwords(str_replace("_", " ", $row['form_name'])));
				// Now actually fix it in the table to prevent from messing other things up downstream
				$sql = "update redcap_metadata set form_menu_description = '".db_escape($row['form_menu_description'])."'
						where project_id = " . $this->project_id . " and field_name = '".db_escape($row['field_name'])."'";
				db_query($sql);
			}
			// Add form names with form_position and form menu description
			if ($this->numFields == 0 || $row['form_menu_description'] != "")
			{
				if (!isset($this->forms[$row['form_name']])) {
					// Add form menu and number
					$this->forms[$row['form_name']] = array( 'form_number'	=> (count($this->forms) + 1),
															 'menu' 		=> filter_tags(label_decode($row['form_menu_description'])),
															 'has_branching'=> 0);
				} else {
					// This field should NOT have a form_menu_description, so remove it from the table
					$sql = "update redcap_metadata set form_menu_description = null
							where project_id = ".$this->project_id." and field_name = '".db_escape($row['field_name'])."' limit 1";
					db_query($sql);
				}
				// For first field, set values to be global variables later
				if ($this->numForms == 0)
				{
					$this->table_pk 	  = $row['field_name'];
					$this->table_pk_order = $row['field_order'];
					$this->table_pk_phi   = ($row['field_phi'] == "1");
					$this->table_pk_label = htmlspecialchars(strip_tags(label_decode($row['element_label'])), ENT_QUOTES);
					// Set first form variables
					$this->firstForm 	  = $row['form_name'];
					$this->firstFormMenu  = $row['form_menu_description'];
				}
				// Increment form count
				$this->numForms++;
			}

			// If field has a legacy date validation, then update it to non-legacy on the fly for this $Proj object
			if ($row['element_type'] == 'text' && in_array($row['element_validation_type'], array('date', 'datetime', 'datetime_seconds'))) {
				$row['element_validation_type'] .= '_ymd';
			}
			// If field is yesno or truefalse field (has pre-defined choices), then add those choices
			elseif ($row['element_type'] == "yesno" && defined('YN_ENUM')) {
				$row['element_enum'] = YN_ENUM;
			} elseif ($row['element_type'] == "truefalse" && defined('TF_ENUM')) {
				$row['element_enum'] = TF_ENUM;
			}
			// Set boolean to designate if any File Upload fields exist in the project
			elseif ($row['element_type'] == "file") {
				$this->hasFileUploadFields = true;
			}

			// If the Form Status field's section header has gotten mangled somehow, fix it
			if ($row['field_name'] == $row['form_name'] . "_complete" && $row['element_preceding_header'] != "Form Status") {
				$row['element_preceding_header'] = "Form Status";
				$sql = "update redcap_metadata set element_preceding_header = '".db_escape($row['element_preceding_header'])."'
						where project_id = ".$this->project_id." and field_name = '".db_escape($row['field_name'])."' limit 1";
				db_query($sql);
			}

			// Add metadata row with field_name as key and other attributes as sub-array
			$this->metadata[$row['field_name']] = $row;
			// Add this field to the forms array
			$this->forms[$row['form_name']]['fields'][$row['field_name']] = label_decode($row['element_label']);
			// Increment field count
			$this->numFields++;
			// Set for next loop
			$this->lastFormName = $row['form_name'];
			// Save matrix group name, if exists
			if ($row['grid_name'] != '') {
				$this->matrixGroupNames[$row['grid_name']][] = $row['field_name'];
				// If the matrix has ranking, add to other array
				if ($row['grid_rank'] == '1') {
					$this->matrixGroupHasRanking[$row['grid_name']] = true;
				}
			}
			// Compare current field count with field_order value (if different, then set to renumber field_order for ALL fields)
			if ($this->numFields != $row['field_order'] && !$this->fieldsOutOfOrder)
			{
				$this->fieldsOutOfOrder = true;
			}
			// If the field has branching logic, add attribute to "forms" array for it
			if ($row['branching_logic'] != '') {
				$this->forms[$row['form_name']]['has_branching'] = 1;
			}
		}
		db_free_result($q);

		// If fields are out of order, then renumber their order
		if ($this->fieldsOutOfOrder)
		{
			$this->reorderFields();
		}

		// If in Draft Mode while in Production, load the drafted field changes as well
		if ($this->project['status'] > 0 && $this->project['draft_mode'] > 0)
		{
			$this->loadMetadataTemp();
		}
	}

	// Load this project's metadata_temp (Draft Mode fields) into array
	private function loadMetadataTemp()
	{
		// Initialize
		$this->numFieldsTemp = 0;
		$this->numFormsTemp  = 0;
		$this->metadata_temp = array();
		$this->forms_temp = array();
		$this->matrixGroupNamesTemp = array();

		// Query table
		$sql = "select * from redcap_metadata_temp where project_id = " . $this->project_id . " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// If project somehow has a blank field_name, delete it
			if ($row['field_name'] == "")
			{
				// Delete it
				db_query("delete from redcap_metadata_temp where project_id = " . $this->project_id . " and field_name = ''");
				// Now skip to next field
				continue;
			}
			// Add form names with form_position and form menu description
			if ($this->numFieldsTemp == 0 || $row['form_menu_description'] != "")
			{
				// Add form menu and number
				$this->forms_temp[$row['form_name']] = array( 'form_number'	=> (count($this->forms_temp) + 1),
															  'menu' 		=> filter_tags(label_decode($row['form_menu_description'])),
															  'has_branching'=> 0 );
				// If form menu is somehow missing for first field (how?), set its value as the form_name
				if ($this->numFieldsTemp == 0 && $row['form_menu_description'] == "")
				{
					$row['form_menu_description'] = $row['form_name'];
					// Now actually fix it in the table to prevent from messing other things up downstream
					$sql = "update redcap_metadata_temp set form_menu_description = '".db_escape($row['form_menu_description'])."'
							where project_id = " . $this->project_id . " and field_name = '".db_escape($row['field_name'])."'";
					db_query($sql);
				}
				if ($this->numFormsTemp == 0) {
					// For first field, set values to be global variables later
					$this->table_pk_temp = $row['field_name'];
				}
				// Increment form count
				$this->numFormsTemp++;
			}
			// If field has a legacy date validation, then update it to non-legacy on the fly for this $Proj object
			if ($row['element_type'] == 'text' && in_array($row['element_validation_type'], array('date', 'datetime', 'datetime_seconds'))) {
				$row['element_validation_type'] .= '_ymd';
			}
			// If field is yesno or truefalse field (has pre-defined choices), then add those choices
			if ($row['element_type'] == "yesno" && defined('YN_ENUM')) {
				$row['element_enum'] = YN_ENUM;
			} elseif ($row['element_type'] == "truefalse" && defined('TF_ENUM')) {
				$row['element_enum'] = TF_ENUM;
			}

			// If the Form Status field's section header has gotten mangled somehow, fix it
			if ($row['field_name'] == $row['form_name'] . "_complete" && $row['element_preceding_header'] != "Form Status") {
				$row['element_preceding_header'] = "Form Status";
				$sql = "update redcap_metadata_temp set element_preceding_header = '".db_escape($row['element_preceding_header'])."'
						where project_id = ".$this->project_id." and field_name = '".db_escape($row['field_name'])."' limit 1";
				db_query($sql);
			}

			// Add metadata row with field_name as key and other attributes as sub-array
			$this->metadata_temp[$row['field_name']] = $row;
			// Add this field to the forms array
			$this->forms_temp[$row['form_name']]['fields'][$row['field_name']] = label_decode($row['element_label']);
			// Increment field count
			$this->numFieldsTemp++;
			// Save matrix group name, if exists
			if ($row['grid_name'] != '') {
				$this->matrixGroupNamesTemp[$row['grid_name']][] = $row['field_name'];
			}
			// Compare current field count with field_order value (if different, then set to renumber field_order for ALL fields)
			if ($this->numFieldsTemp != $row['field_order'] && !$this->fieldsOutOfOrder)
			{
				$this->fieldsOutOfOrder = true;
			}
			// If the field has branching logic, add attribute to "forms" array for it
			if ($row['branching_logic'] != '') {
				$this->forms_temp[$row['form_name']]['has_branching'] = 1;
			}
		}
		db_free_result($q);

		// If fields are out of order, then renumber their order
		if ($this->fieldsOutOfOrder)
		{
			$this->reorderFields("redcap_metadata_temp");
		}
	}

	// AUTO-NUMBERING CHECK: If the first instrument is a survey, make sure the project has auto-numbering enabled
	private function checkAutoNumbering()
	{
		// If has surveys enabled AND first instrument is a survey AND auto-numbering NOT enabled, then enable it
		if ($this->project['surveys_enabled'] > 0 && !$this->project['auto_inc_set'] && isset($this->forms[$this->firstForm]['survey_id']))
		{
			// Set as enabled in table
			$sql = "update redcap_projects set auto_inc_set = 1 where project_id = " . $this->project_id;
			db_query($sql);
			// Also set global variable for this pageload instance
			$GLOBALS['auto_inc_set'] = '1';
		}
	}

	// Manually check if fields are out of order, and renumber them if so. (Allow manually setting of table name.)
	public function checkReorderFields($table="redcap_metadata")
	{
		// Check table name first
		if (substr($table, 0, 15) != "redcap_metadata") return false;
		// Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
		// and make sure it begins with 1 and ends with field order equal to the total field count.
		$sql = "select sum(field_order) as actual, count(1)*(count(1)+1)/2 as ideal, min(field_order) as min, max(field_order) as max,
				count(1) as field_count from $table where project_id = " . $this->project_id;
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		db_free_result($q);
		if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['field_count']) )
		{
			return $this->reorderFields($table);
		}
		return false;
	}

	// If fields are out of order, then renumber their order. (Allow manually setting of table name.)
	public function reorderFields($table="redcap_metadata")
	{
		// Check table name first
		if (substr($table, 0, 15) != "redcap_metadata") return false;
		// Go through all metadata and place into an array according to form (allows us to segregate forms to prevent overlapping)
		$forms_fields = array();
		$forms_menus = array();
		$sql = "select field_name, form_name, form_menu_description from $table where project_id = " . $this->project_id . " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Store field/form
			$forms_fields[$row['form_name']][] = $row['field_name'];
			// Store menu name
			if ($row['form_menu_description'] != "" && !isset($forms_menus[$row['form_name']])) {
				$forms_menus[$row['form_name']] = $row['form_menu_description'];
			}
		}
		db_free_result($q);
		// Counters
		$counter = 1;
		$errors = 0;
		// Reset field_order of all fields, beginning with "1"
		foreach ($forms_fields as $this_form=>$field_array)
		{
			// Set form menu for first form field
			$this_form_menu = checkNull($forms_menus[$this_form]);
			// Loop through each field on this form
			foreach ($field_array as $this_field)
			{
				$sql = "update $table set field_order = $counter, form_menu_description = $this_form_menu where project_id = " . $this->project_id . "
						and field_name = '$this_field' limit 1";
				if (!db_query($sql))
				{
					$errors++;
				}
				// Set form menu to null for all other fields on form except the first
				$this_form_menu = "null";
				// Increment counter
				$counter++;
			}
		}
        // Reset value
        $this->fieldsOutOfOrder = false;
		// Return
		return ($errors < 1);
	}

	// Load this project's events and arms
	public function loadEvents()
	{
		// Make sure loadProjectValues() has been run
		if ($this->project == null) $this->loadProjectValues();
		// If $this->events is already populated, then wipe it out and build anew
		$this->events = array();
		$this->eventInfo = array();
		$this->firstArmId = null;
		$this->firstEventId = null;
		// Query to obtain arm/event info
		$sql = "select * from redcap_events_metadata e, redcap_events_arms a where a.project_id = " . $this->project_id . "
				and a.arm_id = e.arm_id order by a.arm_num, e.day_offset, e.descrip";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Decode text labels
			$row['arm_name'] = filter_tags(html_entity_decode($row['arm_name'], ENT_QUOTES));
			$row['descrip'] = filter_tags(html_entity_decode($row['descrip'], ENT_QUOTES));
			// Arm name
			$this->events[$row['arm_num']]['name'] = $row['arm_name'];
			// Arm id
			$this->events[$row['arm_num']]['id'] = $row['arm_id'];
			// Events for this arm
			$this->events[$row['arm_num']]['events'][$row['event_id']] = array( 'day_offset' => $row['day_offset'],
																				'offset_min' => $row['offset_min'],
																				'offset_max' => $row['offset_max'],
																				'descrip' 	 => $row['descrip'] );
			// Event name
			$this->eventInfo[$row['event_id']] = array( 'arm_id'	 => $row['arm_id'],
														'arm_num'	 => $row['arm_num'],
														'arm_name'	 => $row['arm_name'],
														'day_offset' => $row['day_offset'],
														'offset_min' => $row['offset_min'],
														'offset_max' => $row['offset_max'],
														'name' 	 	 => $row['descrip'],
														// Later in this function, we'll append arm name to name_ext if more than one arm exists
														'name_ext' 	 => $row['descrip'],
														'custom_event_label' => $row['custom_event_label']);
			// Set first arm_id and event_id
			if (empty($this->firstArmId)) {
				$this->firstArmId 	= $row['arm_id'];
				$this->firstArmName	= $row['arm_name'];
				$this->firstArmNum	= $row['arm_num'];
			}
			if (empty($this->firstEventId)) {
				$this->firstEventId   = $row['event_id'];
				$this->firstEventName = $row['descrip'];
			}
			// Increment the number of events
			$this->numEvents++;
			// If not longitudinal, then stop looping after first loop (since only one arm and event exists)
			if (!$this->project['repeatforms']) break;
		}
		db_free_result($q);

		// Set the number of arms
		$this->numArms = count($this->events);

		// Determine if longitudinal (has multiple events) and multiple arms
		if ($this->project['repeatforms'])
		{
			$this->longitudinal  = ($this->numEvents > 1);
			$this->multiple_arms = ($this->numArms   > 1);
			// If more than one arm exists, then append arm name to name_ext
			if ($this->multiple_arms)
			{
				foreach ($this->eventInfo as $event_id=>$event_info)
				{
					$this->eventInfo[$event_id]['name_ext'] .= " (Arm {$event_info['arm_num']}: {$event_info['arm_name']})";
				}
			}
		}
	}

	// Load this project's forms for each event
	public function loadEventsForms()
	{
		// Make sure loadMetadata() has been run
		if ($this->metadata == null) $this->loadMetadata();
		// Make sure loadEvents() has been run
		if ($this->events == null) $this->loadEvents();
		// If $this->eventsForms is already populated, then wipe it out and build anew
		$this->eventsForms = array();
		// If longitudinal...
		if ($this->longitudinal)
		{
			// Set event_id as array key with all forms ONLY for that event listed
			$sql = "select distinct e.event_id, f.form_name from redcap_events_metadata e, redcap_events_forms f, redcap_events_arms a,
					redcap_metadata m where f.event_id = e.event_id and a.arm_id = e.arm_id and a.project_id = m.project_id
					and m.form_name = f.form_name and a.project_id = " . $this->project_id . " order by a.arm_num, e.day_offset, e.descrip, m.field_order";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$this->eventsForms[$row['event_id']][] = $row['form_name'];
			}
		}
		// If not longitudinal...
		else
		{
			// Obtain the single event_id for this project
			$eventsArray = min($this->events);
			foreach (array_keys($eventsArray['events']) as $event_id)
			{
				// Set event_id as array key with all forms listed
				$this->eventsForms[$event_id] = array_keys($this->forms);
				// Leave now that we have the first one
				return;
			}
		}
	}

	// Load this project's surveys
	public function loadSurveys()
	{
		// Make sure loadMetadata() has been run
		if ($this->metadata == null) $this->loadMetadata();
		// Initialize array
		$this->surveys = array();
        $surveys_order = array();
		// Check if surveys are enabled at all
		if ($this->project['surveys_enabled'] > 0)
		{
			// Get survey list
			$sql = "select * from redcap_surveys where project_id = " . $this->project_id;
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// If form_name is NULL or blank, manually give it name of first form
				if ($row['form_name'] == '') {
					$row['form_name'] = $this->firstForm;
					// Also fix this on the back-end to prevent issues
					$q2 = db_query("update redcap_surveys set form_name = '" . db_escape($this->firstForm) . "' where survey_id = " . $row['survey_id']);
					// If first form is already set for another survey, then do not add it to surveys array
					if (!$q2 && db_errno() == '1062') {
						continue;
					}
				}
				// If the survey has been orphaned (was attached to an instrument that got deleted),
				// then don't include it here in the "surveys" array.
				if (!isset($this->forms[$row['form_name']])) continue;
				// Add survey information
				foreach ($row as $key=>$value)
				{
					if ($key != 'project_id' && $key != 'survey_id') {
						// Remove any HTML from survey title
						if ($key == 'title') $value = label_decode($value);
						// Add to array
						$this->surveys[$row['survey_id']][$key] = $value;
					}
				}
				// Also add the survey_id to the "forms" array (and also to the "forms_temp" array for consistency)
				// BUT only if they are a real form and not an orphaned form that still exists in redcap_surveys.
				if (isset($this->forms[$row['form_name']])) {
					$this->forms[$row['form_name']]['survey_id'] = $row['survey_id'];
					// If survey has question auto-numbering enabled BUT some fields in the survey have branching logic,
					// then automatically update the back-end to disable question auto-numbering.
					if ($this->surveys[$row['survey_id']]['question_auto_numbering'] && $this->forms[$row['form_name']]['has_branching']) {
						$q2 = db_query("update redcap_surveys set question_auto_numbering = 0 where survey_id = " . $row['survey_id']);
						$this->surveys[$row['survey_id']]['question_auto_numbering'] = 0;
					}
				}
				// Add survey_id to "forms_temp" array too
				if (isset($this->forms_temp[$row['form_name']])) {
					$this->forms_temp[$row['form_name']]['survey_id'] = $row['survey_id'];
				}
				// Set flag is any project has e-Consent Framework enabled
				if (!$this->hasEconsentSurveys && $row['pdf_auto_archive'] == '2') {
					$this->hasEconsentSurveys = true;	
				}
				// Has email invitation field set for the survey?
				if ($row['email_participant_field'] != '' && isset($this->metadata[$row['email_participant_field']])) {
					$this->hasSurveyEmailInvitationFields = true;
					$this->surveyEmailInvitationFields[$row['survey_id']] = $row['email_participant_field'];
				}
                // Make sure surveys are in form order
                $surveys_order[$row['survey_id']] = $this->forms[$row['form_name']]['form_number'];
			}
			db_free_result($q);
		}
		// Make sure surveys are in form order
        asort($surveys_order);
		$surveys2 = array();
		foreach ($surveys_order as $this_survey_id=>$order) {
            $surveys2[$this_survey_id] = $this->surveys[$this_survey_id];
        }
        $this->surveys = $surveys2;
		unset($surveys2);
		// Set survey_id of first form
		if (isset($this->forms[$this->firstForm]['survey_id'])) {
			$this->firstFormSurveyId = $this->forms[$this->firstForm]['survey_id'];
		}
		// Auto-numbering check: If the first instrument is a survey, make sure the project has auto-numbering enabled
		$this->checkAutoNumbering();
	}
	
	// Return array of all survey-level email invitation fields (if any)
	public function getSurveyEmailInvitationFields($includeProjectLevelEmailField=false)
	{
		$fields = $this->surveyEmailInvitationFields;
		if ($includeProjectLevelEmailField && $this->project['survey_email_participant_field'] != '' && isset($this->metadata[$this->project['survey_email_participant_field']])) {
			$fields[''] = $this->project['survey_email_participant_field'];
		}
		return $fields;
	}

	// Return boolean if field is a checkbox field type
	public function isCheckbox($field)
	{
		return (isset($this->metadata[$field]) && $this->metadata[$field]['element_type'] == 'checkbox');
	}

	// Return the true variable name of a checkbox by passing in the export-version of the checkbox variable (with triple underscore + code).
	// If the field is not a checkbox, then it simply returns the same value passed in. Return false if not a valid field.
	public function getTrueVariableName($export_var)
	{
		// Is a real field?
		if (!isset($this->metadata[$export_var])) {
			// Is it a checkbox?
			$triple_underscore_pos = strpos($export_var, "___");
			if ($triple_underscore_pos !== false) {
				$true_var = substr($export_var, 0, $triple_underscore_pos);
				if (!$this->isCheckbox($true_var)) {
					$true_var = false;
				}
			} else {
				$true_var = false;
			}
		} else {
			$true_var = $export_var;
		}
		return $true_var;
	}

	// Return boolean if field is a multiple choice field ("advcheckbox", "radio", "select", "checkbox", "dropdown", "yesno", "truefalse")
	public function isMultipleChoice($field)
	{
		$mcFieldTypes = array("advcheckbox", "radio", "select", "checkbox", "dropdown", "yesno", "truefalse");
		return (isset($this->metadata[$field]) && in_array($this->metadata[$field]['element_type'], $mcFieldTypes));
	}

	// Return boolean if field is a Form Status field
	public function isFormStatus($field)
	{
		return (isset($this->metadata[$field]) && $field == $this->metadata[$field]['form_name']."_complete");
	}

	// Check if form has been designated for this event
	public function validateFormEvent($form_name,$event_id)
	{
		return ($this->longitudinal ? in_array($form_name, $this->eventsForms[$event_id]) : true);
	}

	public function addEventForms($data)
	{
		$count = 0;

		foreach($data as $a)
		{
			$name = isset($a['unique_event_name']) ? $a['unique_event_name'] : null;
			if(!$name) continue;

			$event_id = $this->getEventIdUsingUniqueEventName($a['unique_event_name']);
			if(!$event_id) continue;

			$form = isset($this->forms[$a['form']]) ? $a['form'] : null;
			if(!$form) continue;

			$event_id = (int)$event_id;
			$form = db_escape($form);

			$sql = "
				INSERT INTO redcap_events_forms (
					event_id, form_name
				) VALUES (
					$event_id, '$form'
				)
			";

			db_query($sql);

			++$count;
		}

		return $count;
	}

	public function clearEventForms()
	{
		$event_ids = array_keys($this->eventsForms);
		if(count($event_ids))
		{
			$event_ids = implode(',', $event_ids);

			$sql = "
				DELETE FROM redcap_events_forms
				WHERE event_id IN ($event_ids)
			";

			db_query($sql);
		}
	}

	// Get event_id using unique event name
	public function getEventIdUsingUniqueEventName($unique_event_name)
	{
		return array_search($unique_event_name, $this->getUniqueEventNames());
	}

	// Check if a given unique event name is valid
	public function uniqueEventNameExists($unique_event_name)
	{
		return in_array($unique_event_name, $this->getUniqueEventNames());
	}

	// Get list of unique event names (based upon event name text) with event_id as array key and unique name as element
	public function getUniqueEventNames($event_id=null)
	{
		// If unique names not defined yet
		if ($this->uniqueEventNames == null)
		{
			$this->uniqueEventNames = array();
			// Loop through all events and create unique event names
			$events = array();
			foreach ($this->events as $this_arm_num=>$arm_attr)
			{
				foreach ($arm_attr['events'] as $this_event_id=>$event_attr)
				{
					// Get original event descrip
					$event_descrip = trim(label_decode($event_attr['descrip']));
					// Remove all spaces and non-alphanumeric characters, then make it lower case.
					$text = preg_replace("/[^0-9a-z_ ]/i", '', $event_descrip);
					$text = strtolower(substr(str_replace(" ", "_", $text), 0, 18));
					// Remove any underscores at the beginning
					while (substr($text, 0, 1) == "_") {
						$text = substr($text, 1);
					}
					// Remove any underscores at the end
					while (substr($text, -1, 1) == "_") {
						$text = substr($text, 0, -1);
					}
					// If event name is still blank (maybe because of using multi-byte characters)
					if ($text == '') {
						// Get first 10 letters of MD5 of the event label
						$text = substr(md5($event_descrip), 0, 10);
					}
					// Append arm number
					$text .= '_arm_' . $this_arm_num;
					// If this unique name alread exists, append with "a", "b", "c", etc.
					$count = count(array_keys($events, $text));
					$append_text = '';
					if ($count > 0 && $count < 26) {
						$append_text = chr(97+$count);
					} elseif ($count >= 26 && $count < 702) {
						$append_text = chr(96+floor($count/26)) . chr(97+($count%26));
					} elseif ($count >= 702) {
						$append_text = '??';
					}
					// Collect the original unique name to check for duplicates later
					$events[] = $text;
					// Add unique name to array in object
					$this->uniqueEventNames[$this_event_id] = $text . $append_text;
				}
			}
		}
		// If unique names ARE defined and we are to return ONLY one event
		if ($event_id != null) {
			return $this->uniqueEventNames[$event_id];
		}
		// Return array of unique event names
		else {
			return $this->uniqueEventNames;
		}
	}

	// Check if any forms have been downloaded from the REDCap Shared Library (return as 1 or 0)
	public function formsFromLibrary()
	{
		if ($this->formsFromLibrary == null)
		{
			$sql = "select 1 from redcap_library_map where type = 1
					and project_id = " . $this->project_id . " limit 1";
			$q = db_query($sql);
			$this->formsFromLibrary = db_num_rows($q);
		}
		return $this->formsFromLibrary;
	}

	// Validate survey_id for this project
	public function validateSurveyId($survey_id)
	{
		return isset($this->surveys[$survey_id]);
	}

	// Validate event_id for this project
	public function validateEventId($event_id)
	{
		return ($this->longitudinal ? isset($this->eventInfo[$event_id]) : ($event_id == $this->firstEventId));
	}

	// Validate event_id-survey_id pair for this project
	// (i.e. make sure that this survey's instrument has been designated for this event)
	public function validateEventIdSurveyId($event_id, $survey_id)
	{
		// First, validate both survey_id and event_id individually
		if (!$this->validateSurveyId($survey_id) || !$this->validateEventId($event_id)) return false;
		// Get the instrument name of the survey
		$form_name = $this->surveys[$survey_id]['form_name'];
		// Return true if survey is utilized for this event
		return in_array($form_name, $this->eventsForms[$event_id]);
	}

	// Check if this survey_id is a follow-up survey (i.e. a survey not associated with the first instrument). Return boolean.
	public function isFollowUpSurvey($survey_id)
	{
		// Return true if NOT the first instrument's survey_id
		return ($survey_id != $this->firstFormSurveyId);
	}

	// Return boolean regarding if Data Access Groups exist in this project (TRUE if at least one DAG exists)
	public function hasGroups()
	{
		$groups = $this->getGroups();
		return (count($groups) > 0);
	}

	// Populate array of all Data Access Groups OR return single group's name if group_id is input
	public function getGroups($group_id=null)
	{
		if ($this->groups === null)
		{
			$this->groups = array();
			// Query for group id and name
			$sql = "select * from redcap_data_access_groups where project_id = " . $this->project_id . " order by trim(group_name)";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$this->groups[$row['group_id']] = $row['group_name'];
			}
		}
		// If requesting single group_id, then return only it
		if ($group_id != null)
		{
			return (is_numeric($group_id) && isset($this->groups[$group_id])) ? $this->groups[$group_id] : false;
		}
		return $this->groups;
	}

	// Validate group_id of Data Access Group for this project
	public function validateGroupId($group_id)
	{
		$this->getGroups();
		return isset($this->groups[$group_id]);
	}

	// Check if a given unique group name is valid
	public function uniqueGroupNameExists($unique_group_name)
	{
		return in_array($unique_group_name, $this->getUniqueGroupNames());
	}

	// Get list of unique Data Access Group names (based upon group name text) with group_id as array key and unique name as element
	public function getUniqueGroupNames($group_id=null)
	{
		// If unique names not defined yet
		if ($this->uniqueGroupNames == null)
		{
			$this->uniqueGroupNames = array();
			// Loop through all groups and create unique event names
			$groups = array();
			foreach ($this->getGroups() as $this_group_id=>$group_name)
			{
				// Set original group label
				$group_label = $group_name;
				// Remove all spaces and non-alphanumeric characters, then make it lower case.
				$group_name = preg_replace("/[^0-9a-z_ ]/i", '', trim(label_decode($group_name)));
				$group_name = strtolower(substr(str_replace(" ", "_", $group_name), 0, 18));
				// Remove any underscores at the end
				if (substr($group_name, -1, 1) == "_") {
					$group_name = substr($group_name, 0, -1);
				}
				// If group_name is still blank (maybe because of using multi-byte characters)
				if ($group_name == '') {
					// Get first 10 letters of MD5 of the group label
					$group_name = substr(md5($group_label), 0, 10);
				}
				// If this unique name alread exists, append with "b", "c", "d", etc.
				$count = count(array_keys($groups, $group_name));
				$append_text = '';
				if ($count > 0 && $count < 26) {
					$append_text = chr(97+$count);
				} elseif ($count >= 26 && $count < 702) {
					$append_text = chr(96+floor($count/26)) . chr(97+($count%26));
				} elseif ($count >= 702) {
					$append_text = '??';
				}
				// Collect the original unique name to check for duplicates later
				$groups[] = $group_name;
				if ($group_id == null) {
					// Add unique name to array in object
					$this->uniqueGroupNames[$this_group_id] = $group_name . $append_text;
				} elseif ($group_id == $this_group_id) {
					// Return the unique name for the specified event
					return $group_name . $append_text;
				}
			}
		}
		// If unique names ARE defined and we are to return ONLY one group
		elseif ($this->uniqueGroupNames != null && $group_id != null)
		{
			return $this->uniqueGroupNames[$group_id];
		}
		// Return array of unique group names
		return $this->uniqueGroupNames;
	}

	// Clear all groups from object variable and retrieve again (in case some were modified in same script)
	public function resetGroups()
	{
		// Reset arrays to null
		$this->groups = null;
		$this->uniqueGroupNames = null;
		$this->groupsUsers = null;
		// Re-fill array
		$this->getGroups();
	}

	// Populate array of users that are in a Data Access Group (return group_id as array key)
	public function getGroupUsers($group_id=null,$includeUsersNotAssigned=false)
	{
		if ($this->groupsUsers === null)
		{
			// Query for group id and name
			$sql = "select if(group_id is null,0,group_id) as group_id, username from redcap_user_rights where project_id = " . $this->project_id;
			if (!$includeUsersNotAssigned) {
				$sql .= " and group_id is not null";
			}
			$sql .= " order by group_id";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$this->groupUsers[$row['group_id']][] = strtolower($row['username']);
			}
		}
		// If requesting single group_id, then return only it
		if ($group_id != null)
		{
			return (is_numeric($group_id) && isset($this->groupUsers[$group_id])) ? $this->groupUsers[$group_id] : false;
		}
		return $this->groupUsers;
	}

	// Obtain the event_id of the first event on a given arm using the arm number
	public function getFirstEventIdArm($arm_num)
	{
		if (is_numeric($arm_num) && isset($this->events[$arm_num]))
		{
			// Return the first event_id for this arm
			foreach (array_keys($this->events[$arm_num]['events']) as $event_id)
			{
				return $event_id;
			}
		}
		return $this->firstEventId;
	}

	// Obtain the event_id of the first event of an arm by providing an event_id from that arm
	public function getFirstEventIdInArmByEventId($event_id, $form=null)
	{
		if (is_numeric($event_id) && isset($this->eventInfo[$event_id]))
		{
			// Return the first event_id for this arm if the event_id exists in this arm
			foreach ($this->events as $arm=>$arm_attr) {
				if (isset($arm_attr['events'][$event_id])) {
					if ($form != null) {
						// If form is provided, then find the first *designated* event for that form
						foreach (array_keys($arm_attr['events']) as $this_event_id) {
							if (in_array($form, $this->eventsForms[$this_event_id])) {
								return $this_event_id;
							}
						}
						return false;
					}
					return array_shift(array_keys($arm_attr['events']));
				}
			}
		}
		return $this->firstEventId;
	}

	// Obtain the event_id of the last event of an arm by providing an event_id from that arm
	public function getLastEventIdInArmByEventId($event_id, $form=null)
	{
		if (is_numeric($event_id) && isset($this->eventInfo[$event_id]))
		{
			// Return the first event_id for this arm if the event_id exists in this arm
			foreach ($this->events as $arm=>$arm_attr) {
				if (isset($arm_attr['events'][$event_id])) {
					if ($form != null) {
						// If form is provided, then find the first *designated* event for that form
						foreach (array_reverse(array_keys($arm_attr['events'])) as $this_event_id) {
							if (in_array($form, $this->eventsForms[$this_event_id])) {
								return $this_event_id;
							}
						}
						return false;
					}
					return array_pop(array_keys($arm_attr['events']));
				}
			}
		}
		return $this->firstEventId;
	}


	// Function to obtain the previous event right before the one passed as parameter
	public function getPrevEventId($event_id, $form=null)
	{
		if (!isset($this->eventInfo[$event_id])) return false;
		if ($form != null) {
			// If form is provided, then find the previous *designated* event for that form
			$foundEventId = false;
			foreach (array_reverse($this->eventsForms, true) as $this_event_id=>$forms) {
				if (!$foundEventId) {
					$foundEventId = ($event_id == $this_event_id);
					continue;
				}
				if (in_array($form, $forms)) {
					return $this_event_id;
				}
			}
			return false;
		}
		$events = array_keys($this->eventInfo);
		$prevEventIndex = array_search($event_id, $events)-1;
		return (isset($events[$prevEventIndex])) ? $events[$prevEventIndex] : false;
	}


	// Function to obtain the next event following the one passed as parameter
	public function getNextEventId($event_id, $form = null)
	{
		if (!isset($this->eventInfo[$event_id])) return false;
		if ($form != null) {
			// If form is provided, then find the next *designated* event for that form
			$foundEventId = false;
			foreach ($this->eventsForms as $this_event_id=>$forms) {
				if (!$foundEventId) {
					$foundEventId = ($event_id == $this_event_id);
					continue;
				}
				if (in_array($form, $forms)) {
					return $this_event_id;
				}
			}
			return false;
		}
		$events = array_keys($this->eventInfo);
		$nextEventIndex = array_search($event_id, $events)+1;
		return (isset($events[$nextEventIndex])) ? $events[$nextEventIndex] : false;
	}

	// Obtain the event_id of the first event on a given arm using the arm_id
	public function getFirstEventIdArmId($arm_id)
	{
		if (is_numeric($arm_id))
		{
			// Return the first event_id for this arm
			foreach ($this->events as $arm=>$arm_attr) {
				if ($arm_attr['id'] == $arm_id) {
					foreach (array_keys($arm_attr['events']) as $event_id) {
						return $event_id;
					}
				}
			}
		}
		return $this->firstEventId;
	}

	// Return boolean if the event_id provided is the first event in the arm to which it belongs
	public function isFirstEventIdInArm($event_id)
	{
		if (!is_numeric($event_id)) return false;
		// Return the first event_id for this arm
		foreach ($this->events as $arm=>$arm_attr) {
			// Does this event_id belong to this arm?
			if (isset($arm_attr['events'][$event_id])) {
				// If we are on correct arm, then determine if event_id is the first event in this arm
				$arm_events_keys = array_keys($arm_attr['events']);
				return (array_shift($arm_events_keys) == $event_id);
			}
		}
		// Could not find event, so return false
		return false;
	}

	// Obtains unique matrix group name (grid_name) that does not currently exist in metadata table
	private function generateMatrixGroupName($prependString="")
	{
		global $status;
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		do {
			// If original group name is too long, truncate it
			$maxLengthPrependString = (self::GRID_NAME_MAX_LENGTH - 5);
			if (strlen($prependString) > $maxLengthPrependString) {
				$prependString = substr($prependString, 0, $maxLengthPrependString);
			}
			// Generate a new random grid name (based on original)
			$grid_name = $prependString . "_" . strtolower(generateRandomHash(4));
			// Ensure that the grid name doesn't already exist
			$sql = "select 1 from $metadata_table where project_id = ".$this->project_id." and grid_name = '".$grid_name."' limit 1";
			$gridExists = (db_num_rows(db_query($sql)) > 0);
		} while ($gridExists);
		// Grid name is unique, so return it
		return $grid_name;
	}

	// Fix any orphaned matrix-formatted fields by automatically giving them a new grid_name
	public function fixOrphanedMatrixFields($form=null)
	{
		global $status;
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		// Get fields (all fields or just a single form)
		$fields = array();
		$sql = "select field_name, grid_name from $metadata_table where project_id = ".$this->project_id;
		if ($form != null) $sql .= " and form_name = '".db_escape($form)."'";
		$sql .= " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$fields[$row['field_name']] = $row['grid_name'];
		}
		// Loop through fields and note any duplicate grid names or any orphans
		$last_group = "";
		$groups = array();
		$group_count = 0;
		foreach ($fields as $field=>$group)
		{
			// If has group
			if ($group != "") {
				if ($group != $last_group) {
					$groups[++$group_count] = array('group'=>$group,'fields'=>array($field));
				} else {
					$groups[$group_count]['fields'][] = $field;
				}
			}
			// Set for next loop
			$last_group = $group;
		}
		// Now loop through our groups list and note any duplicates (i.e. non-adjacent groups with same name)
		$groups_existing = array();
		$groups_duplicated = array();
		foreach ($groups as $key=>$attr) {
			// If group already exists previously, then compare it to previous and remove one with lowest count
			if (isset($groups_existing[$attr['group']])) {
				$prev_key = $groups_existing[$attr['group']];
				$prev_group_field_count = count($groups[$prev_key]['fields']);
				if ($prev_group_field_count < count($attr['fields'])) {
					// Add fields from previous instance
					$groups_duplicated[$attr['group']] = $groups[$prev_key]['fields'];
					$groups_existing[$attr['group']] = $key;
				} else {
					$groups_duplicated[$attr['group']] = $attr['fields'];
					$groups_existing[$attr['group']] = $prev_key;
				}
			} else {
				// Add name to existing groups list
				$groups_existing[$attr['group']] = $key;
			}
		}
		// If found orphaned fields, change the grid_name for each field OR each group of fields that were orphaned
		$fields_changed = 0;
		foreach ($groups_duplicated as $old_group=>$fields) {
			// Generate new unique grid_name (use old grid name to prepend)
			$grid_name = $this->generateMatrixGroupName($old_group);
			// Auto-change grid_name of fields to unique value in project's metadata
			$sql = "update $metadata_table set grid_name = '".db_escape($grid_name)."'
					where project_id = ".$this->project_id." and field_name in ('".implode("', '", $fields)."')";
			if (db_query($sql)) $fields_changed++;
		}
		// Unset arrays
		unset($fields,$groups);
		// Return true if any group names were modified
		return ($fields_changed > 0);
	}


	// Function to obtain the previous field right before the one passed as parameter
	public function getPrevField($this_field)
	{
		if (!isset($this->metadata[$this_field])) return false;
		$fields = array_keys($this->metadata);
		$prevFieldIndex = array_search($this_field, $fields)-1;
		return (isset($fields[$prevFieldIndex])) ? $fields[$prevFieldIndex] : false;
	}


	// Function to obtain the next field following the one passed as parameter
	public function getNextField($this_field)
	{
		if (!isset($this->metadata[$this_field])) return false;
		$fields = array_keys($this->metadata);
		$prevFieldIndex = array_search($this_field, $fields)+1;
		return (isset($fields[$prevFieldIndex])) ? $fields[$prevFieldIndex] : false;
	}


	// Function to obtain array of all the event_id's for a given Arm NUMBER
	public function getEventsByArmNum($arm_num)
	{
		return (isset($this->events[$arm_num]) ? array_keys($this->events[$arm_num]['events']) : array());
	}


	public static function saveInfo($data)
	{
		$project_title        = db_escape($data['project_title']);
		$project_language     = db_escape($data['project_language']);
		$purpose_other        = db_escape($data['purpose_other']);
		$project_notes        = db_escape($data['project_notes']);
		$custom_record_label  = db_escape($data['custom_record_label']);
		$project_pi_firstname = db_escape($data['project_pi_firstname']);
		$project_pi_lastname  = db_escape($data['project_pi_lastname']);

		$sql = "
			UPDATE redcap_projects
			SET
				project_name                 = '$project_title',
				production_time              = '$data[production_time]',
				status                       = '$data[in_production]',
				project_language             = '$project_language',
				purpose                      = '$data[purpose]',
				purpose_other                = '$purpose_other',
				project_note                 = '$project_notes',
				custom_record_label          = '$custom_record_label',
				surveys_enabled              = '$data[surveys_enabled]',
				scheduling                   = '$data[scheduling_enabled]',
				auto_inc_set                 = '$data[record_autonumbering_enabled]',
				randomization                = '$data[randomization_enabled]',
				realtime_webservice_enabled  = '$data[ddp_enabled]',
				project_irb_number           = '$data[irb_number]',
				project_grant_number         = '$data[project_grant_number]',
				project_pi_firstname         = '$project_pi_firstname',
				project_pi_lastname          = '$project_pi_lastname'
			WHERE project_id = '$data[project_id]'
		";

		$q = db_query($sql);
		return ($q && $q !== false);
	}


	public static function instrEventMapToCSV($dataset)
	{
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		fputcsv($fp, array_keys($dataset[0]));
		// Loop through array and output line as CSV
		if (count($dataset) > 1 && trim(implode("", $dataset[0])) != '') {
			foreach ($dataset as $line) {
				fputcsv($fp, $line);
			}
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		$output = trim(stream_get_contents($fp));
		fclose($fp);
		return $output;
	}


	public static function getInstrEventMapRecords($post=array())
	{
		$arms = array();

		// Determine if these params are arrays.  If not, make them into arrays

		$tempArms = is_array($post['arms']) ? $post['arms'] : (!isset($post['arms']) ? array() : explode(",", $post['arms']));

		// Loop through all elements and remove any spaces
		foreach($tempArms as $id => $value) {
			if (trim($value) != "") {
				$arms[] = trim($value);
			}
		}

		# get project information
		$Proj = new Project();
		$events = $Proj->events;
		$uniqueNames = $Proj->getUniqueEventNames();

		$eventForms = $Proj->eventsForms;
		// Don't output any CATs because they cannot be used in the Mobile App
		$cat_list = ($post['mobile_app']) ? PROMIS::getPromisInstruments() : array();
		if (!empty($cat_list)) {
			foreach ($eventForms as $this_event_id=>$these_forms) {
				foreach ($these_forms as $this_key=>$this_form) {
					if (in_array($this_form, $cat_list)) {
						unset($eventForms[$this_event_id][$this_key]);
					}
				}
			}
		}

		//This function only works for longitudinal projects
		if (!$Proj->project['repeatforms']) die(RestUtility::sendResponse(400, 'You cannot export form/event mappings for classic projects'));

		$results = array();
		$addEvents = true;
		$armsEmpty = empty($arms);

		foreach($events as $num => $data)
		{
			# if filtering by ARMs, determine if current event is in a desired ARM
			$addEvents = ( $armsEmpty || (!$armsEmpty && in_array($num, $arms)) );

			if ($addEvents)
			{
				foreach($data['events'] as $id => $events2) {
					foreach ($eventForms[$id] as $form) {
						$results[] = array('arm_num'=>$num, 'unique_event_name'=>$uniqueNames[$id], 'form'=>$form);
					}
				}
			}
		}
		
		if (empty($results)) {
			$results[] = array('arm_num'=>'', 'unique_event_name'=>'', 'form'=>'');
		}

		return $results;
	}

	public static function eventsToCSV($dataset)
	{
		// Open connection to create file in memory and write to it
		$fp = fopen('php://memory', "x+");
		// Add header row to CSV
		fputcsv($fp, array_keys($dataset[0]));
		// Loop through array and output line as CSV
		foreach ($dataset as $line) {
			fputcsv($fp, $line);
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		$output = trim(stream_get_contents($fp));
		fclose($fp);
		return $output;
	}

	public static function getEventRecords($post=array(), $returnSchedulingAttributes=true)
	{
		$arms = array();
		$tempArms = array();

		// Determine if these params are arrays.  If not, make them into arrays
		$tempArms = is_array($post['arms']) ? $post['arms'] : explode(",", $post['arms']);

		// Loop through all elements and remove any spaces
		foreach($tempArms as $id => $value) {
			if (trim($value) != "") {
				$arms[] = trim($value);
			}
		}

		# get project information
		$Proj = new Project();
		$eventInfo = $Proj->eventInfo;
		$uniqueNames = $Proj->getUniqueEventNames();

		//This function only works for longitudinal projects
		if (!$Proj->project['repeatforms']) die(RestUtility::sendResponse(400, 'You cannot export events for classic projects'));

		$events = array();
		$addEvents = true;
		$armsEmpty = empty($arms);
		$i = 0;

		foreach($eventInfo as $id => $data)
		{
			# if filtering by ARMs, determine if current event is in a desired ARM
			$addEvents = ( $armsEmpty || (!$armsEmpty && in_array($data["arm_num"], $arms)) );

			if ($addEvents)
			{
				$events[$i]['event_name'] = label_decode($data['name']);
				$events[$i]['arm_num'] = (int)$data['arm_num'];
				if ($returnSchedulingAttributes) {
					$events[$i]['day_offset'] =( int)$data['day_offset'];
					$events[$i]['offset_min'] = (int)$data['offset_min'];
					$events[$i]['offset_max'] = (int)$data['offset_max'];
				}
				$events[$i]['unique_event_name'] = $uniqueNames[$id];
				$events[$i]['custom_event_label'] = $data['custom_event_label'];
				$i++;
			}
		}

		return $events;
	}

	public static function getArmRecords($post=array())
	{
		# get project information
		$Proj = new Project();

		//This function only works for longitudinal projects
		if (!$Proj->longitudinal) die(RestUtility::sendResponse(400, 'You cannot export arms for classic projects'));

		$arms = array();
		foreach ($Proj->events as $num => $data)
		{
			// If arms were explicitly set as API parameter array, then return only those
			if (!empty($post['arms']) && !in_array($num, $post['arms'])) continue;

			// Add to array
			$arms[] = array(
				'arm_num' => $num,
				'name'    => label_decode($data['name'])
			);
		}

		return $arms;
	}

	// Function to obtain the special import/export-formatted checkbox fieldname (e.g., field+___+code)
	public static function getExtendedCheckboxFieldname($field_name, $raw_coded_value)
	{
		return $field_name . "___" . self::getExtendedCheckboxCodeFormatted($raw_coded_value);
	}


	// Function to obtain the special import/export-formatted value to be used in the extended checkbox fieldname
	public static function getExtendedCheckboxCodeFormatted($raw_coded_value)
	{
		// Replace all negative signs with underscore first so they don't conflict with same number with postive value
		$raw_coded_value = str_replace("-", "_", $raw_coded_value);
		// Set as lower case and remove invalid characters
		return preg_replace("/[^a-z_0-9]/", "", strtolower($raw_coded_value));
	}


	// Initialize the repeating form/event array. Returns nothing.
	public function setRepeatingFormsEvents()
	{
		// If already defined, then just return the array
		if ($this->RepeatingFormsEvents === null) 
		{
			// Initialize as array
			$this->RepeatingFormsEvents = array();
			$repeating_form_labels = array();
			// Query the table to see if in table
			$sql = "select * from redcap_events_repeat 
					where event_id in (" . prep_implode(array_keys($this->eventInfo)) . ")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) 
			{
				// Longitudinal w/ repeating event
				if ($this->longitudinal && $row['form_name'] == '') {
					// Add entire event
					if (!isset($this->RepeatingFormsEvents[$row['event_id']])) {
						// Remove event if it somehow already existed
						unset($this->RepeatingFormsEvents[$row['event_id']]);
					}
					$this->RepeatingFormsEvents[$row['event_id']] = 'WHOLE';
				} else {
					// Add selected forms
					$this->RepeatingFormsEvents[$row['event_id']][$row['form_name']] = $this->forms[$row['form_name']]['form_number'];
					// Add custom form label
					$repeating_form_labels[$row['event_id']][$row['form_name']] = ($row['custom_repeat_form_label'] === null) ? "" : $row['custom_repeat_form_label'];
				}
			}
			// Loop through repeating forms and sort them according to true form order
			foreach ($this->RepeatingFormsEvents as $event_id=>$unordered_forms) {
				if (!is_array($unordered_forms)) continue;
				asort($unordered_forms);
				// Remove the event's forms from the array before we add them back
				unset($this->RepeatingFormsEvents[$event_id]);
				// Add any custom form labels
				foreach (array_keys($unordered_forms) as $form) {
					$this->RepeatingFormsEvents[$event_id][$form] = $repeating_form_labels[$event_id][$form];
				}
			}
			// Update this also in the project cache
			$cachedProj = self::$project_cache[$this->project_id];
			$cachedProj->RepeatingFormsEvents = $this->RepeatingFormsEvents;
		}
	}


	// Return array of all events and all forms that are set to repeat, with event_id as key and form_names as values
	public function getRepeatingFormsEvents()
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Return array
		return $this->RepeatingFormsEvents;
	}


	// Returns boolean regarding whether project has any repeating events OR forms
	public function hasRepeatingFormsEvents()
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Return booean
		return !empty($this->RepeatingFormsEvents);
	}
	
	
	// Returns boolean regarding whether project contains ENTIRE repeating events
	public function hasRepeatingEvents()
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Loop through all events
		foreach ($this->RepeatingFormsEvents as $forms) {
			// If is not an array, then is a repeated event
			if (!is_array($forms)) return true;			
		}		
		// If got this far, then return false
		return false;
	}
	
	
	// Returns boolean regarding whether project contains repeating forms
	public function hasRepeatingForms()
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Loop through all events
		foreach ($this->RepeatingFormsEvents as $forms) {
			// If is an array, then has repeating forms
			if (is_array($forms) && !empty($forms)) return true;			
		}		
		// If got this far, then return false
		return false;
	}


	// Determine if the entire event OR the form is set to repeat
	public function isRepeatingFormOrEvent($event_id, $form=null)
	{
		// Get array of repeating forms/events
		$RepeatingFormsEvents = $this->getRepeatingFormsEvents();
		// Return boolean
		return ($this->isRepeatingEvent($event_id) || ($form != null && $this->isRepeatingForm($event_id, $form)));
	}
	
	
	// Returns boolean regarding whether a specific event is set to repeat
	public function isRepeatingEvent($event_id)
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Return boolean
		return (isset($this->RepeatingFormsEvents[$event_id]) && !is_array($this->RepeatingFormsEvents[$event_id]));
	}
	
	
	// Returns boolean regarding whether a specific event-form is set to repeat (i.e. a repeating form)
	public function isRepeatingForm($event_id, $form)
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// If don't have form, can't do anything
		if ($form == '') return false;
		// Return boolean
		return (isset($this->RepeatingFormsEvents[$event_id][$form]) && is_array($this->RepeatingFormsEvents[$event_id]));
	}
	
	
	// Returns boolean regarding whether a form is set to repeat on at least one event in a project
	public function isRepeatingFormAnyEvent($form)
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// Loop through all events
		foreach ($this->RepeatingFormsEvents as $forms) {
			// If is an array with the form, then return true
			if (is_array($forms) && !empty($forms) && isset($forms[$form])) return true;			
		}		
		// If got this far, then return false
		return false;
	}
	
	
	// Returns boolean regarding whether ALL forms in an event are set as a Repeating Instrument
	public function allEventFormsAreRepeatingForms($event_id=null)
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		$this->setAllEventFormsAreRepeatingForms();
		// Return boolean
		return in_array($event_id, $this->eventsWhereAllFormsAreRepeatingForms);
	}
	
	
	// Sets array regarding whether ALL forms in an event are set as a Repeating Instrument
	public function setAllEventFormsAreRepeatingForms()
	{
		// Init array if not set
		$this->setRepeatingFormsEvents();
		// If already defined, then just return the array
		if ($this->eventsWhereAllFormsAreRepeatingForms === null) {
			// Initialize as array
			$this->eventsWhereAllFormsAreRepeatingForms = array();
			if (empty($this->RepeatingFormsEvents)) return;
			// Loop through events
			foreach ($this->eventsForms as $this_event_id=>$forms) {
				if (!isset($this->RepeatingFormsEvents[$this_event_id]) || !is_array($this->RepeatingFormsEvents[$this_event_id])) continue;
				$thisEventRepeatingFormsEvents = array_keys($this->RepeatingFormsEvents[$this_event_id]);
				$eventsDiff = array_diff($forms, $thisEventRepeatingFormsEvents);
				if (empty($eventsDiff)) {
					$this->eventsWhereAllFormsAreRepeatingForms[] = $this_event_id;
				}
			}			
		}
	}


	// Return array of attributes to be returned from the API method "Export Project Information"
	public static function getAttributesApiExportProjectInfo()
	{
		// Set array of fields we want to return from the API request.
		// The keys are their values from the redcap_projects table, and the corresponding values are
		// their user-facing names that serve as the headers returned in the API request.
		$project_fields = array(
			'project_id'=>'project_id',
			'app_title'=>'project_title',
			'creation_time'=>'creation_time',
			'production_time'=>'production_time',
			'status'=>'in_production',
			'project_language'=>'project_language',
			'purpose'=>'purpose',
			'purpose_other'=>'purpose_other',
			'project_note'=>'project_notes',
			'custom_record_label'=>'custom_record_label',
			'secondary_pk'=>'secondary_unique_field',
			'longitudinal'=>'is_longitudinal',
			'has_repeating_instruments_or_events'=>'has_repeating_instruments_or_events',
			'surveys_enabled'=>'surveys_enabled',
			'scheduling'=>'scheduling_enabled',
			'auto_inc_set'=>'record_autonumbering_enabled',
			'randomization'=>'randomization_enabled',
			'realtime_webservice_enabled'=>'ddp_enabled',
			'project_irb_number'=>'project_irb_number',
			'project_grant_number'=>'project_grant_number',
			'project_pi_firstname'=>'project_pi_firstname',
			'project_pi_lastname'=>'project_pi_lastname',
			'display_today_now_button'=>'display_today_now_button',
			'missing_data_codes'=>'missing_data_codes',
			'external_modules'=>'external_modules'
		);
		// Return array
		return $project_fields;
	}


	// Return array of attributes that can be modified via the API method "Import Project Information"
	public static function getAttributesApiImportProjectInfo()
	{
		// Set array of fields we want to return from the API request.
		// The keys are their values from the redcap_projects table, and the corresponding values are
		// their user-facing names that serve as the headers returned in the API request.
		$project_fields = array(
			'app_title'=>'project_title',
			'project_language'=>'project_language',
			'purpose'=>'purpose',
			'purpose_other'=>'purpose_other',
			'project_note'=>'project_notes',
			'custom_record_label'=>'custom_record_label',
			'secondary_pk'=>'secondary_unique_field',
			'longitudinal'=>'is_longitudinal',
			'surveys_enabled'=>'surveys_enabled',
			'scheduling'=>'scheduling_enabled',
			'auto_inc_set'=>'record_autonumbering_enabled',
			'randomization'=>'randomization_enabled',
			'project_irb_number'=>'project_irb_number',
			'project_grant_number'=>'project_grant_number',
			'project_pi_firstname'=>'project_pi_firstname',
			'project_pi_lastname'=>'project_pi_lastname',
			'display_today_now_button'=>'display_today_now_button'
		);
		// Return array
		return $project_fields;
	}


	// Return number of events where a form is designated. You may exclude certain event_id's from the count (optional)
	public function numEventsFormDesignated($form, $exclude_event_ids=array())
	{
		// Collect all event_id's where form is designated
		$desigEventIds = $this->getEventsFormDesignated($form, $exclude_event_ids);
		// Return count
		return count($desigEventIds);
	}


	// Return array of events where a form is designated. You may exclude certain event_id's from the count (optional)
	public function getEventsFormDesignated($form, $exclude_event_ids=array())
	{
		// Collect all event_id's where form is designated
		$desigEventIds = array();
		foreach ($this->eventsForms as $event_id=>$forms) {
			if (in_array($form, $forms) && !in_array($event_id, $exclude_event_ids)) {
				$desigEventIds[] = $event_id;
			}
		}
		// Return count
		return $desigEventIds;
	}


	// Modify project attribute
	public function setAttribute($attr_name, $attr_value)
	{
		if (defined("PROJECT_ID")) {
			$project_id = PROJECT_ID;
		} else {
			$project_id = $this->project_id;
		}
		// Update redcap_projects table
		$sql = "update redcap_projects set {$attr_name} = '".db_escape($attr_value)."' where project_id = $project_id";
		return db_query($sql);
	}


	// PROJECT XML EXPORT (ODM)
	// Returns the contents of an entire project (all records, events, arms, instruments, fields, and project attributes)
	// as a single XML file, which is in CDISC ODM format.
	public static function getProjectXML()
	{
		// Get function arguments
		$args = func_get_args();
		// Make sure we have a project_id
		if (!is_numeric($args[0]) && !defined("PROJECT_ID")) throw new Exception('No project_id provided!');
		// If first parameter is numerical, then assume it is $project_id and that second parameter is $returnFormat
		if (is_numeric($args[0])) {
			$project_id = $args[0];
			$returnMetadataOnly = (isset($args[1])) ? $args[1] : false;
			$records = (isset($args[2])) ? $args[2] : array();
			$fields = (isset($args[3])) ? $args[3] : array();
			$events = (isset($args[4])) ? $args[4] : array();
			$groups = (isset($args[5])) ? $args[5] : array();
			$outputDags = (isset($args[6])) ? $args[6] : false;
			$outputSurveyFields = (isset($args[7])) ? $args[7] : false;
			$filterLogic = (isset($args[8])) ? $args[8] : false;
			$exportFiles = (isset($args[9])) ? $args[9] : false;
		} else {
			$project_id = PROJECT_ID;
			$returnMetadataOnly = (isset($args[0])) ? $args[0] : false;
			$records = (isset($args[1])) ? $args[1] : array();
			$fields = (isset($args[2])) ? $args[2] : array();
			$events = (isset($args[3])) ? $args[3] : array();
			$groups = (isset($args[4])) ? $args[4] : array();
			$outputDags = (isset($args[5])) ? $args[5] : false;
			$outputSurveyFields = (isset($args[6])) ? $args[6] : false;
			$filterLogic = (isset($args[7])) ? $args[7] : false;
			$exportFiles = (isset($args[8])) ? $args[8] : false;
		}
		// Retrieve ODM XML file
		if ($returnMetadataOnly) {
			// Create Proj object
			$Proj = new Project($project_id);
			// Get opening XML tags
			$xml = ODM::getOdmOpeningTag($Proj->project['app_title']);
			// MetadataVersion section
			$xml .= ODM::getOdmMetadata($Proj, false, false);
			// End XML string
			$xml .= ODM::getOdmClosingTag();
			// Return XML string
			return $xml;
		} else {
			return Records::getData($project_id, 'odm', $records, $fields, $events, $groups, false, $outputDags, $outputSurveyFields,
									$filterLogic, false, false, false, false, false, array(), false, !$exportFiles, false,
									true, $outputSurveyFields, false, 'EVENT', true);
		}
	}


	/*
	// Export REDCap project XML file (CDISC ODM format)
	// Can optionally include data in the file
	public static function getProjectXmlFile($project_id, $includeData=false)
	{
		$project_id = (int)$project_id;
		if (!is_numeric($project_id)) return false;
		// ODM with metadata & data
		if ($includeData)
		{
			global $user_rights;
			// Does user have De-ID rights?
			$deidRights = (!empty($user_rights) && $user_rights['data_export_tool'] == '2');
			// Export DAG names?
			$outputDags = (!empty($user_rights) && $user_rights['group_id'] == '');
			$userInDAG = (!empty($user_rights) && isset($user_rights['group_id']) && is_numeric($user_rights['group_id']));
			$dags = ($userInDAG) ? $user_rights['group_id'] : array();
			// Export survey fields
			$outputSurveyFields = true;
			// ODM Only: Include ODM metadata
			$includeOdmMetadata = true;
			$outputFormat = 'odm';
			// De-Identification settings
			$hashRecordID = false;
			if ($deidRights) {
				// Determine if the project's record ID field is tagged as an Identifier field
				$sql = "select field_phi from redcap_metadata where project_id = $project_id and field_order = 1 and field_phi = 1";
				$q = db_query($sql);
				$hashRecordID = (db_num_rows($q) > 0);
			}
			$removeIdentifierFields = ((!empty($user_rights) && $user_rights['data_export_tool'] == '3') || $deidRights);
			$removeUnvalidatedTextFields = $removeNotesFields = $deidRights;
			$removeDateFields = true;
			$dateShiftDates = $dateShiftSurveyTimestamps = !$removeDateFields;
			// Instrument and event filtering
			$selectedInstruments = $selectedEvents = array();
			// Export all the data and metadata for this project
			$data_content = Records::getData($project_id, $outputFormat, array(), array(), array(), $dags, false, $outputDags,
											$outputSurveyFields, "", false, false, $hashRecordID, $dateShiftDates,
											$dateShiftSurveyTimestamps, array(), false, false, false,
											true, true, false, 'EVENT', $includeOdmMetadata);
			// Replace any MS Word chacters in the data
			replaceMSchars($data_content);
			return $data_content;
		}
		// ODM with just metadata
		else {
			// Create Proj object
			$Proj = new Project($project_id);
			// Get opening XML tags
			$xml = ODM::getOdmOpeningTag($Proj->project['app_title']);
			// MetadataVersion section
			$xml .= ODM::getOdmMetadata($Proj, false, false);
			// End XML string
			$xml .= ODM::getOdmClosingTag();
			// Return XML string
			return $xml;
		}
	}
	*/
	
	
	// Return arm_id of an arm using its arm number. Returns false on error.
	public function getArmIdFromArmNum($arm)
	{
		if (!is_numeric($arm)) return false;
		$sql = "select arm_id from redcap_events_arms where project_id = " . $this->project_id 
			 . " and arm_num = '" . db_escape($arm) . "'";
		$q = db_query($sql);
		return (db_num_rows($q) > 0) ? db_result($q, 0) : false;
	}	
	
	// If one or more email invitation fields are set, return values FOR SINGLE RECORD ONLY
	public function getEmailInvitationFieldValues($record, $emailFields=array(), $arm=1)
	{
		// If emailFields is empty, then fetch for all of them
		$emailValues = array();
		if (empty($emailFields)) {
			$emailFields = $this->getSurveyEmailInvitationFields(true);
		}
		// Query the fields for value across all instances and all events (this arm only)
		$sql = "select distinct field_name, value from redcap_data 
				where field_name in (".prep_implode($emailFields).") and project_id = " . $this->project_id . "
				and record = '" . db_escape($record) . "' and value != '' 
				and event_id in (".prep_implode(array_keys($this->events[$arm]['events'])).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$emailValues[$row['field_name']] = $row['value'];
		}
		// Return array of fields and their values
		return $emailValues;
	}
	
	// If one or more email invitation fields are set, SAVE values FOR SINGLE RECORD ONLY
	public function saveEmailInvitationFieldValues($record, $emailFieldsValues=array(), $arm=1)
	{
		if (empty($emailFieldsValues)) return;
		// Save the field value across all instances and all events (this arm only)
		foreach ($emailFieldsValues as $emailField=>$emailValue) 
		{
			// Find all events where this email field is designated for this arm only
			$emailFieldForm = $this->metadata[$emailField]['form_name'];
			$designatedEvents = array();
			foreach ($this->eventsForms as $this_event_id=>$these_forms) {
				if (in_array($emailFieldForm, $these_forms) && isset($this->events[$arm]['events'][$this_event_id])) {
					$designatedEvents[] = $this_event_id;
				}
			}
			// Gather all events/instances that have data but do NOT have a row in redcap_data for this field			
			$sql = "insert into redcap_data (project_id, event_id, record, field_name, value, instance)
					select distinct '" . $this->project_id . "', a.event_id, '" . db_escape($record) . "', 
					'" . db_escape($emailField) . "', '" . db_escape($emailValue) . "', a.instance from redcap_data a 
					left join redcap_data b on a.project_id = b.project_id and a.record = b.record and a.event_id = b.event_id 
					and (a.instance = b.instance or (a.instance is null and b.instance is null)) and b.field_name = '" . db_escape($emailField) . "'
					where a.project_id = " . $this->project_id . " and a.record = '" . db_escape($record) . "' 
					and a.event_id in (".prep_implode($designatedEvents).")	and b.field_name is null";
			$q = db_query($sql);
			// Update all existing rows for this field in redcap_data
			$sql = "update redcap_data set value = '" . db_escape($emailValue) . "'
					where field_name = '" . db_escape($emailField) . "' and project_id = " . $this->project_id . "
					and record = '" . db_escape($record) . "'
					and event_id in (".prep_implode(array_keys($this->events[$arm]['events'])).")";
			$q = db_query($sql);
            // Log this event so that it shows up in Data History widget for all places where this field is displayed
            $sql = "select distinct event_id, if(instance is null, 1, instance) as instance, value from redcap_data
					where field_name = '" . db_escape($emailField) . "' and project_id = " . $this->project_id . "
					and record = '" . db_escape($record) . "'
					and event_id in (".prep_implode(array_keys($this->events[$arm]['events'])).")";
            if (isset($_GET['event_id'])) $sql .= " and event_id != ".$_GET['event_id']; // The current event value has already been logged, so don't do it again.
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                Logging::logEvent($sql, "redcap_data", "update", $record, "$emailField = '{$row['value']}'", "Update record",
                    "", "", $this->project_id, true, $row['event_id'], $row['instance']);
            }
		}
	}
	
	
	// If a secondary identifier field is set, return value FOR SINGLE RECORD ONLY. Always query first Event (classic or longitudinal).
	public function getSecondaryIdVal($record)
	{
		// Default value
		$secondary_pk_val = '';
		// If 2ndary id set, get value for this record
		if ($this->project['secondary_pk'] != '')
		{
			//Query the field for value
			$sql = "select value from redcap_data where field_name = '{$this->project['secondary_pk']}' and project_id = " . PROJECT_ID . "
					and record = '" . db_escape($record) . "' and event_id = " . $this->getFirstEventIdArm(getArm()) . " and value != '' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				// Set the value
				$secondary_pk_val = db_result($q, 0);
				// If the secondary unique field is a date/time field in MDY or DMY format, then convert to that format
				$val_type = $this->metadata[$this->project['secondary_pk']]['element_validation_type'];
				if (substr($val_type, 0, 5) == 'date_' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_mdy')) {
					$secondary_pk_val = DateTimeRC::datetimeConvert($secondary_pk_val, 'ymd', substr($val_type, -3));
				}
			}
		}
		// Return value
		return $secondary_pk_val;
	}

	// Obtain values from redcap_projects table
	public static function getProjectVals()
	{
		$vars = array();
		// Query redcap_projects table for project-level values
		$sql  = "select * from redcap_projects where ";
		$sql .= isset($_GET['pnid']) ? "project_name = '" . db_escape($_GET['pnid']) . "'" : "project_id = " . (isset($_GET['pid']) ? (int)$_GET['pid'] : '');
		$q = db_query($sql);
		// If project doesn't exist, then redirect to Home page
		if ($q === false || db_num_rows($q) < 1) return false;
		// Assign all redcap_projects table fields as variables and/or constants
		foreach (db_fetch_assoc($q) as $key => $value)
		{
			// Keep value NULL if NULL for pdf_custom_header_text
			if ($key == 'pdf_custom_header_text' && $value === null) {
				$vars[$key] = $value;
				continue;
			}
			if ($key != 'report_builder') {
				$value = html_entity_decode($value, ENT_QUOTES);
			}
			$vars[$key] = trim($value);
		}
		// Return variables
		return $vars;
	}
	
	// Obtain values from redcap_projects table and set as global variables
	public static function setProjectVals()
	{
		// Get project values
		$projectVals = self::getProjectVals();
		// If project doesn't exist, then redirect to Home page
		if ($projectVals === false) System::redirectHome();
		// Prevent some project values from overwriting global values if they are blank
		foreach (Project::$overwritableGlobalVars as $globalVar) {
			// If project value is blank, then revert to global value
			if ($projectVals[$globalVar] == '') {
				$projectVals[$globalVar] = $GLOBALS[$globalVar];
			}
		}
		// Loop through all values and set as global variables
		foreach ($projectVals as $field_name=>$value) {
			$GLOBALS[$field_name] = $value;
		}
		// If project-level SALT does not exist yet, then create it as 10-digit random alphanum
		if (empty($GLOBALS['__SALT__'])) {
			$GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);
			db_query("update redcap_projects set __SALT__ = '{$GLOBALS['__SALT__']}' where project_id = " . $GLOBALS['project_id']);
		}
		// Return array of values
		return $projectVals;
	}
	
	// Determine if the current page is a project-level page. Return boolean.
	public static function isProjectPage()
	{
		return (isset($_GET['pnid']) || (isset($_GET['pid']) && is_numeric($_GET['pid'])));
	}
	
	// For caching purposes, store the results of an SQL field's query
	public function getExecutedSql($field)
	{
		if (!isset($this->metadata[$field])) return "";
		if (!isset($this->executedSqlFields[$field])) {
			$this->executedSqlFields[$field] = getSqlFieldEnum($this->metadata[$field]['element_enum'], $this->project_id);
		}
		return $this->executedSqlFields[$field];
	}
}