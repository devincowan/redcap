<?php

class DDPDataTool {

	private $DDP;

    public function __construct(DynamicDataPull $ddp)
    {
		$this->DDP = $ddp; // so I can access private methods
    }

    /**
     * import mapping fields from a CSV file
     * @todo manage json files too
     */
    public function importMappingFields()
	{
		try{
			$files = FileManager::getUploadedFiles();
			foreach($files as $file)
			{
				//check file type
				/* if($file['type']!='text/csv')
				{
					throw new Exception('error parsing the file');
				} */
				// parse the file and get the csv data as array of lines
				$csv = FileManager::readCSV($file['tmp_name']);
				// check if the csv contains a record identifier
				self::checkRecordIdentifier($csv);
				// check if the file is valid
				self::checkValidCSV($csv);
				
				$mappingFields = $this->formatCsvMappingFileds($csv);
				$mappingFieldsSettings = $this->getMappingFieldsSettings(); // get the currents mapping fields settings

				$post = array_merge([], $mappingFieldsSettings, $mappingFields);
				
				$_POST = $post;
				$this->DDP->saveFieldMappings(); //save the mapping fields

				self::showMessage($message = 'SUCCESS: data imported');
				return;
			}
		}catch(Exception $e)
		{
			$message = $e->getMessage();
			self::showMessage($message,$type='error');
			return;
		}
	}

	/**
	 * export the mapping fields in CSV format
	 * if format is json also exports the preview and day offset settings
	 */
	public function exportMappingFields($filename='mapping-fields')
	{
		try{
			$format= isset($_POST['format']) ? $_POST['format'] : 'csv';

			switch ($format) {
				case 'json':
					$data = [
						"settings" => $this->getSettingsArray(),
						"fields" => $this->getMappingFieldsArray(),
					];
					FileManager::exportJSON($data, 'mapped-fields');
					break;
				//csv or anything else:
				case 'csv':
				default:
					$rows = $this->getMappingFieldsArray();
					FileManager::exportCSV($rows, $filename);
					break;
			}
		}catch(Exception $e)
		{
			$message = $e->getMessage();
			self::showMessage($message,$type='error');
			return;
		}
	}

	/**
	 * display a message
	 *
	 * @param string $message
	 * @param string $type
	 * @param boolean $echo
	 * @return void
	 */
	private static function showMessage($message, $type='success', $echo = true)
	{
		$response = [
			'status' => $type,
			'message' => $message,
		];
		self::printJSON($response);
	}

	/**
	 * get the current settings for the mapping fields:
	 *  - preview fields
	 *  - realtime_webservice_offset_days
	 *  - realtime_webservice_offset_plusminus
	 *
	 * @return array settings
	 */
	private function getMappingFieldsSettings()
	{
		global $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus;
		$preview_fields = $this->DDP->getPreviewFields();
		$settings = [
			'preview_field' => $preview_fields,
			'rtws_offset_plusminus' => $realtime_webservice_offset_plusminus,
			'rtws_offset_days' => $realtime_webservice_offset_days,
		];
		return $settings;
	}

	/**
	 * return the index of the row containing the record identifier
	 */
	/**
	 * Undocumented function
	 *
	 * @param string[] $csv
	 * @return void
	 */
	static private function checkRecordIdentifier($csv)
	{
		$mappingFieldsArray = FileManager::csvToAssociativeArray($csv);
		$recordIdentifierIndex = array_search('1', array_column($mappingFieldsArray, 'is_record_identifier'));
		if($recordIdentifierIndex===false)
		{
			throw new Exception("No record identifier found in the file", 1);
		}
	}

	static private function checkValidCSV($csv)
	{
		if(empty($csv) || count($csv)<=1 )
		{
			throw new Exception("Error parsing the file", 1);
		}
	}

	/**
	 * transform an array of csv lines
	 * in a format compatible with the method saveFieldMappings
	 */
	private function formatCsvMappingFileds($csv)
	{
		$data = [];
		// get the keys from the first line
		$keys = array_shift($csv); //remove the first row with the keys
		foreach($csv as $row)
		{
			$name = ''; // reset the name
			foreach($row as $index => $column)
			{
				$prefix = ''; // reset the prefix
				$key = $keys[$index];
				switch ($key) {
					case 'external_source_field_name':
						//get the name 
						$name = $column;
						break;
					case 'field_name':
						//get the id
						$prefix = 'ddf';
						break;
					case 'event_name':
						//get the id
						$column = Event::getEventIdByName($this->project_id, $column);
						$prefix = 'dde';
						break;
					case 'preselect':
						$prefix = 'ddp';
						break;
					case 'temporal_field':
						$prefix = 'ddt';
						break;
					case 'is_record_identifier':
						$prefix = 'id';
						break;
					default:
						$prefix = '';
						break;
				}
				if(!empty($prefix) && !empty($column))
				{
					$data["{$prefix}-{$name}"][] = $column;
				}
			}
		}
		return $data;
	}

	/**
	 * @return array of mapping fields in an export ready format
	 */
	private function getMappingFieldsArray()
	{
		// 'is_record_identifier',
		// 'project_id', //do not export
		// 'event_id', // should be name of the event
		// 'event_name' // can get this from the event id
		// 'field_name',
		// 'temporal_field',
		// 'preselect',
		$fields = $this->DDP->getMappedFields();
		$rows = [];
		foreach($fields as $external_source_field_name => $events)
		{
			foreach($events as $event_id=>$event)
			{
				$event_name = Event::getEventNameById($this->project_id, $event_id);
				foreach($event as $field_name => $field_values)
				{
					$row = [];
					$row['external_source_field_name'] = $external_source_field_name;
					$row['event_name'] = $event_name;
					$row['field_name'] = $field_name;
					foreach($field_values as $key => $value)
					{
						$row[$key] = $value;
					}
					unset($row['map_id']); // do not want to export this
					$rows[] = $row;
				}
			}
		}
		return $rows;
	}

	/**
	 * @return array with preview fields and default day offset settings
	 * in an export ready format
	 */
	private function getSettingsArray()
	{
		global $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus;
		$rows['rtws_offset_days'] = $realtime_webservice_offset_days;
		$rows['rtws_offset_plusminus'] = $realtime_webservice_offset_plusminus;
		$rows['preview_field'] = $this->DDP->getPreviewFields();
		return $rows;
	}

	// print a JSON response and exit
	private static function printJSON($response)
	{
		header('Content-Type: application/json');
		print json_encode_rc( $response );
		exit;
	}
}