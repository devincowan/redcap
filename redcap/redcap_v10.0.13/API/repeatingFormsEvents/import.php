<?php

class Importer {

	private $params = array(); // container for magic getter
	
	private $xml_collection_key = 'repeatingFormsEvents';
	private $xml_item_key = 'item';

    public function __construct()
    {
		global $post, $format, $returnFormat, $lang;

		$this->params = $post; // set params for magic getter

		$this->format = $format;
		$this->returnFormat = $returnFormat;
		$this->lang = $lang;

		$this->init($this->projectid);
	}

	/**
	 * check the user rights,
	 * reset the class paramenters
	 * instantiate the current project
	 *
	 * @return void
	 */
	private function init($project_id)
	{

		$this->checkUserRights();
		
		try {
			// try to instantiate a project
			$this->project = new Project($project_id);
		} catch (\Exception $e) {
			$error = $e->getMessage();
			RestUtility::sendResponse(400, $this->lang['api_150'] . " " . $error);
		}
	}
	
	/**
	 * import the submitted data
	 *
	 * @return void
	 */
	public function import()
	{
		$data = $this->loadData($this->data);
		$queries = $this->getQueries($data);
		$this->saveData($queries);
	}

	/**
	 * get the query for deleting the repeated events
	 *
	 * @return void
	 */
	private function getDeleteQuery()
	{
		// First, remove any existing row
		$eventInfo = $this->project->eventInfo;
		$query = "delete from redcap_events_repeat where event_id in (".prep_implode(array_keys($eventInfo)).")";
		return $query;
	}

	/**
	 * get the query for inserting a repeated event
	 *
	 * @param int $event_id id oof the event
	 * @param string $form_name name of the instrument
	 * @param string $custom_form_label label to display a preview of the repeated event
	 * @return string $query
	 */
	private function getInsertQuery($event_id, $form_name, $custom_form_label)
	{
		$query = sprintf(
			"insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) values (%s, '%s', %s)",
			$event_id, db_escape($form_name), checkNull($custom_form_label)
		);
		return $query;
	}

	/**
	 * check if the current user has privileges to import data
	 *
	 * @return void
	 */
	private function checkUserRights()
    {
        // Check for required privileges
        if ($this->user_rights != '1') RestUtility::sendResponse(400, $this->lang['api_103'], $this->returnFormat);
	}

	/**
	 * parse the submitted data 
	 *
	 * @return string $post_data
	 */
	private function loadData($post_data)
    {
        $data = array();

        switch($this->format)
        {
            case 'json':
                // Decode JSON into array
                $data = json_decode($post_data, true);
                if ($data == '') return $this->lang['data_import_tool_200'];
                break;
            case 'xml':
				// Decode XML into array
				$collection_key = $this->xml_collection_key;
				$item_key = $this->xml_item_key;
                $data = Records::xmlDecode(html_entity_decode($post_data, ENT_QUOTES));
                if ($data == '' || !isset($data[$collection_key][$item_key])) return $this->lang['data_import_tool_200'];
                $data = (isset($data[$collection_key][$item_key][0])) ? $data[$collection_key][$item_key] : array($data[$collection_key][$item_key]);
                break;
            case 'csv':
                // Decode CSV into array
                $post_data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $post_data);
                $data = csvToArray($post_data);
                break;
		}
		return $data;
	}

	/**
	 * check the data for error and get the databse queries
	 *
	 * @param array $data
	 * @return void
	 */
	private function getQueries($data)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$project_name = $project->project['app_title'];
		$forms = $project->forms; // to check for valid form names
		$eventsForms = $project->eventsForms; // to check for valid event names

		$errors = array(); // store errors if present
		$queries = array(); // store queries

		$queries[] =  $this->getDeleteQuery(); 

		foreach($data as $item)
		{
			// check for mandatory parameters
			if(($project->longitudinal && !array_key_exists('event_name', $item)) || !array_key_exists('form_name', $item))
			{
				$message = $this->lang['data_import_tool_275'];
				$errors[] =  sprintf("%s: '%s'", $message, $key);
			}else {
				$event_name = $item['event_name'];
				$form_name = $item['form_name'];
				$custom_form_label = $item['custom_form_label'];
				// check if project is longitudinal to set the correct event_id
				$event_id = ($project->longitudinal) ? Event::getEventIdByName($project_id, $event_name) : $project->firstEventId;

				// check if the event name is valid for the current project
				if(!array_key_exists($event_id, $eventsForms))
				{
					$errors[] = sprintf($this->lang['data_import_tool_276'],$event_name, $project_name);
				}
				// check if the form name is valid for the current project
				if($form_name != '' && !array_key_exists($form_name, $forms))
				{
					$errors[] = sprintf($this->lang['data_import_tool_277'],$form_name, $project_name);
				}
			}

			if(empty($errors)) {

				$queries[] =  $this->getInsertQuery($event_id, $form_name, $custom_form_label);
			}
		}
		if(!empty($errors))
		{
			RestUtility::sendResponse(400, implode("; ", $errors), $this->returnFormat);
		}
		return $queries;
	}
	
	/**
	 * save the data to the database and log the queries
	 * send an error if an exception is thrown
	 * 
	 * @return void
	 */
	private function saveData($queries)
	{
		try {

			if(!empty($queries))
			{
				// Begin transaction
				db_query("SET AUTOCOMMIT=0");
				db_query("BEGIN");

				$count = 0; //count successfull INSERT queries
				foreach ($queries as $query)
				{
					if( ($success = db_query($query)) && preg_match ('/^INSERT/i', $query) )
					{
						$count++; //count successfull INSERT queries
					}
				}
				Logging::logEvent(implode(";\n", $queries),"redcap_events_repeat","MANAGE",$this->project->project_id,"","Set up repeating instruments".($this->project->longitudinal ? "/events" : ""));
				db_query("COMMIT");
				db_query("SET AUTOCOMMIT=1");
				// Send the response to the requestor
				RestUtility::sendResponse(200, $count, $this->returnFormat);
			}
		} catch (\Exception $e) {
			$error = $e->getMessage();
			db_query("ROLLBACK");
			db_query("SET AUTOCOMMIT=1");
			RestUtility::sendResponse(400, $this->lang['api_150'] . " " . $error, $this->returnFormat);
		}
	}

	/**
	 * magic getter
	 *
	 * @param string $name
	 * @return mixed parameter
	 */
	public function __get($name)
	{
		if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        }
	}
}

$importer = new Importer();
$importer->import();