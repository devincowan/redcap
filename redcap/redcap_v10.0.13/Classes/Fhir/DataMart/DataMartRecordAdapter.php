<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsEntry;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;

/**
 * Adapter to save data coming from FHIR endpoints into a Data Mart record
 */
class DataMartRecordAdapter
{
	/**
	 * Fhir endpoints/category and matching Data Mart form name
	 */
	public static $form_name_mapping = array(
		'Patient' => 'demography',
		'Observation/Vital Signs' => 'vital_signs',
		'Observation/Labs' => 'labs',
		'MedicationOrder' => 'medications',
		'Condition' => 'problem_list',
		'AllergyIntolerance' => 'allergies',
	);

	/**
	 * FHIR to REDCap mapping 
	 * used in the demography form
	 * (keys are the result of FhirEhr->parseData)
	 *
	 * @var array
	 */
	private static $fhir_to_redcap_mapping = array(
		// fhir => redcap
		'fhir_id' => 'demography_fhir_id',
		'id' => 'mrn',
		'address-city' => 'address_city',
		'address-country' => 'address_country',
		'address-postalCode' => 'address_postalcode',
		'address-state' => 'address_state',
		'address-line' => 'address_line',
		'birthDate' => 'dob',
		'name-given' => 'first_name',
		'name-family' => 'last_name',
		'phone-home' => 'phone_home',
		'phone-mobile' => 'phone_mobile',
		'gender' => 'sex',
		'ethnicity' => 'ethnicity',
		'race' => 'race',
		'email' => 'email',
		'deceasedBoolean' => 'is_deceased',
		'preferred-language' => 'preferred_language',
	);

	
	/**
	 * Create an instance of the adapter
	 *
	 * @param \DataMartRevision $revision
	 */
	public function __construct($revision)
	{
		$this->revision = $revision;
		$project_id = $this->revision->project_id;
		$this->project = new \Project($project_id);
		// cache a list of fields in the current project
	}
	
	/**
	 * combine the existing record on REDCap with the one that
	 * is being built with new data.
	 * this combined values will be used to determine if
	 * the incoming data has already been stored in REDCap.
	 * also this data is used to determine the index of the new repeated fields
	 *
	 * @param string $form_name
	 * @param string $event_id
	 * @param array $redcap_record
	 * @param array $new_record
	 * @return array
	 */
	private function getExistingData($form_name, $event_id, $redcap_record, $new_record)
	{
		$redcap_form_data = $redcap_record['repeat_instances'][$event_id][$form_name];
		if(!is_array($redcap_form_data)) $redcap_form_data = array();
		$new_form_data = $new_record['repeat_instances'][$event_id][$form_name];
		if(!is_array($new_form_data)) $new_form_data = array();
		// merge the arrays and preserve the keys (default, override)
		$existing_form_data = array_replace_recursive($redcap_form_data, $new_form_data);
		return $existing_form_data;
	}

	/**
	 * save data to a project
	 *
	 * @param string $mrn
	 * @param array $data
	 * @return FhirStatsEntry[]
	 */
	public function saveData($mrn, $data)
	{
		// Instantiate project
		$project = $this->project;
		$project_id = $project->project_id;
		// get FHIR metadata for reference
		$fhir_metadata = $this->getFhirMetadata();
		// get the event ID. Will be used to save data in the record structure
		$event_id = $project->firstEventId;
		
		// Init data array
		$record_data = array($event_id => array(
			// self::$fhir_to_redcap_mapping['id']=>$mrn // no need to update the MRN (it is already stored)
		));
		
		// get the record ID for the current MRN
		$record_id = $this->getRecordID($project_id, $mrn);
		
		// get existing data
		$existing_records_data = \Records::getData(
			$project_id,
			$return_format='array',
			$records=$record_id // current record
		);
		// existing data is an associative array; get the current record data
		$existing_record_data = $existing_records_data[$record_id];
		

		$date_range = $this->revision->getDateRangeForMrn($mrn);
		list($date_min, $date_max) = $date_range;
		
		$fhir_stats_collector = new FhirStatsCollector($project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDM);
		
		foreach ($data as $attr) {
			if (isset($attr['resourceType'])) {
				$resource_type = $attr['resourceType'];
				switch ($resource_type) {
					case 'Patient':
						$form_name = self::$form_name_mapping['Patient'];
						$field_key = self::$fhir_to_redcap_mapping[$attr['field']];
						$form_fields = $this->project->forms[$form_name]['fields'];
						if(!array_key_exists($field_key, $form_fields)) continue; // skip field if not in project
						$field_value = $attr['value'];
						$record_data[$event_id][$field_key] = $field_value;
						$record_data[$event_id][$form_name.'_complete'] = '2';
						// increse the FHIR stats counter if the value is new or updated
						if(!array_key_exists($field_key, $existing_record_data[$event_id]) || $existing_record_data[$event_id][$field_key]!=$field_value)
						{
							$fhir_stats_collector->addEntry($record_id, $resource_type);
						}
						break;
					case 'MedicationOrder':
						$form_name = self::$form_name_mapping['MedicationOrder'];
						$form_data = array(
							$form_name.'_fhir_id' => $attr['fhir_id'],
							'medication_label' => $attr['display'],
							'medication_date' => $attr['timestamp'],
							'medication_dosage' => $attr['dosage'],
							'medication_status' => $attr['status'],
							'medication_rxnorm_display' => $attr['rxnorm_display'],
							'medication_rxnorm_code' => $attr['rxnorm_code'],
							$form_name.'_complete' => '2',
						);
						$existing_data = $this->getExistingData($form_name, $event_id, $existing_record_data, $record_data);
						$instance_index = $this->shouldUpdateOrInsert($record_id, $form_name, $form_data, $existing_data);
						if($instance_index===false) break; // no need to update or insert
						$record_data['repeat_instances'][$event_id][$form_name][$instance_index] = $form_data;
						$fhir_stats_collector->addEntry($record_id, $resource_type); // increment the stats counter
						break;
					
					case 'Condition':
						$form_name = self::$form_name_mapping['Condition'];
						$form_data = array(
							$form_name.'_fhir_id' => $attr['fhir_id'],
							'problem_recorded_date' => $attr['timestamp'],
							'problem_clinical_status' => $attr['clinical_status'],
							'problem_icd10_display' => $attr['icd10_display'],
							'problem_icd10_code' => $attr['icd10_code'],
							'problem_icd9_display' => $attr['icd9_display'],
							'problem_icd9_code' => $attr['icd9_code'],
							'problem_snomed_display' => $attr['snomed_display'],
							'problem_snomed_code' => $attr['snomed_code'],
							$form_name.'_complete' => '2',

						);
						$existing_data = $this->getExistingData($form_name, $event_id, $existing_record_data, $record_data);
						$instance_index = $this->shouldUpdateOrInsert($record_id, $form_name, $form_data, $existing_data);
						if($instance_index===false) break; // no need to update or insert
						$record_data['repeat_instances'][$event_id][$form_name][$instance_index] = $form_data;
						$fhir_stats_collector->addEntry($record_id, $resource_type);  // increment the stats counter
						break;
					
					case 'AllergyIntolerance':
						$form_name = self::$form_name_mapping['AllergyIntolerance'];
						$form_data = array(
							$form_name.'_fhir_id' => $attr['fhir_id'],
							'allergy_recorded_date' => $attr['timestamp'],
							'allergy_snomed_display' => $attr['snomed_display'],
							'allergy_snomed_code' => $attr['snomed_code'],
							'allergy_fdaunii_display' => $attr['unii_display'],
							'allergy_fdaunii_code' => $attr['unii_code'],
							'allergy_ndfrt_display' => $attr['ndfrt_display'],
							'allergy_ndfrt_code' => $attr['ndfrt_code'],
							$form_name.'_complete' => '2',
						);
						$existing_data = $this->getExistingData($form_name, $event_id, $existing_record_data, $record_data);
						$instance_index = $this->shouldUpdateOrInsert($record_id, $form_name, $form_data, $existing_data);
						if($instance_index===false) break; // no need to update or insert
						$record_data['repeat_instances'][$event_id][$form_name][$instance_index] = $form_data;
						$fhir_stats_collector->addEntry($record_id, $resource_type);
						break;
					
					case 'Observation':
						// Temporal/repeating instrument
						$timestamp = $this->normalizeTimestamp($attr['timestamp']);
						// skip values not in the specified time range
						if (($date_min != '' && $timestamp < $date_min) || ($date_max != '' && $timestamp > $date_max)) continue; // skip this loop

						$field_mapping = $fhir_metadata->{$attr['field']};
						$category = $field_mapping->category;
						$label = $field_mapping->label;
						
						if ($category == 'Vital Signs') {
							$form_name = self::$form_name_mapping['Observation/Vital Signs'];
							$form_data = array(
								$form_name.'_fhir_id' => $attr['fhir_id'],
								'vitals_label' => $label,
								'vitals_loinc_code' => $attr['field'],
								'vitals_time' => $timestamp,
								'vitals_value' => $attr['value'],
								$form_name . '_complete' => '2',
							);
							$existing_data = $this->getExistingData($form_name, $event_id, $existing_record_data, $record_data);
							$instance_index = $this->shouldUpdateOrInsert($record_id, $form_name, $form_data, $existing_data);
							if($instance_index===false) break; // no need to update or insert
							$record_data['repeat_instances'][$event_id][$form_name][$instance_index] = $form_data;
							$fhir_stats_collector->addEntry($record_id, $resource_type); //increment the stats counter
						} else { // $category == 'Laboratory'
							$form_name = self::$form_name_mapping['Observation/Labs'];
							$form_data = array(
								$form_name.'_fhir_id' => $attr['fhir_id'],
								'labs_label' => $label,
								'labs_loinc_code' => $attr['field'],
								'labs_time' => $timestamp,
								'labs_value' => $attr['value'],
								$form_name . '_complete' => '2',
							);
							$existing_data = $this->getExistingData($form_name, $event_id, $existing_record_data, $record_data);
							$instance_index = $this->shouldUpdateOrInsert($record_id, $form_name, $form_data, $existing_data);
							if($instance_index===false) break; // no need to update or insert
							$record_data['repeat_instances'][$event_id][$form_name][$instance_index] = $form_data;
							$fhir_stats_collector->addEntry($record_id, $resource_type); // increment the stats counter
						}
						break;
		
					default:
						break;
				}
			}
		}

		// Build array for saveData()
		$record = array($record_id=>$record_data);

		// Save the record in the project
		// $save_response = array(); // TODO: restore saving data!!!!
		$save_response = \Records::saveData($project_id, 'array', $record);

		$entries = $fhir_stats_collector->getEntries();
		// calculate FHIR stats using entries for current record
		$stats = $fhir_stats_collector->countResourceEntries($entries);
		// log FHIR stats to database
		$logged = $fhir_stats_collector->logEntries();
		
		// Record any errors
		if (!empty($save_response['errors'])) {
			$message = 'Error saving data to database';
			throw new \DataException($message, $save_response['errors']);
		}
		return $stats;
	}
	
	private function normalizeTimestamp($timestamp)
	{
		global $fhir_convert_timestamp_from_gmt;
		if(empty($timestamp)) return ''; //skip empty dates
		$normalized_date_format = 'Y-m-d H:i';
		// make sure to have the correct datetime format
		$time = strtotime($timestamp); // convert any string to seconds
		$datetime = new \DateTime();
		$datetime->setTimestamp($time);
		$normalized_timestamp = $datetime->format($normalized_date_format);

		// If we're shifting the timestamp from GMT to local/server time, then convert time
		if ($fhir_convert_timestamp_from_gmt)
		{
			$user_timezone = new \DateTimeZone(getTimeZone());
			$gmt_timezone = new \DateTimeZone('GMT');
			$converted_datetime = new \DateTime($normalized_timestamp, $gmt_timezone);
			$offset = $user_timezone->getOffset($converted_datetime);
			$date_interval = \DateInterval::createFromDateString((string)$offset . 'seconds');
			$converted_datetime->add($date_interval);
			$normalized_timestamp = $converted_datetime->format($normalized_date_format);
		}

		return $normalized_timestamp;
	}

	/**
	 * get the FHIR metadata
	 * serve the cached version if available and not too old
	 *
	 * @return object
	 */
	private function getFhirMetadata()
	{
		$redcap_version = defined('REDCAP_VERSION') ? sprintf("_%s", REDCAP_VERSION) : '';
		$cache_file_name = "fhir_metadata{$redcap_version}.json";
			// use cached version for at least 6 hour
		if($cache_file = \FileManager::getCachedFile($cache_file_name, $max_life_time=60*60*1) )
		{
			// Serve from the cache if it is younger than $cachetime
			return json_decode($cache_file);
		}
		$fhir_metadata = \DynamicDataPull::getFhirMetadata();
		// save data to cache
		$encoded = json_encode($fhir_metadata);
		\FileManager::cacheFile($cache_file_name, $encoded);
		return json_decode($encoded);
	}

	/**
	 * retrieve an entry with a specific FHIR id from the database
	 *
	 * @param string $record_id
	 * @param string $fhir_id
	 * @return object[]
	 */
	private function findFhirId($record_id, $fhir_id)
	{
		$query_string = "SELECT *
						FROM redcap_data
						WHERE
						project_id={$this->project->project_id}
						AND record='$record_id'
						AND value='$fhir_id'
						AND field_name LIKE '%_fhir_id'";
		$result = db_query($query_string);
		$results = array();
		while($row=db_fetch_object($result)) {
			$results[] = $row;
		}
		return $results;
	}

	/**
	 * check if the incoming data should
	 *  - update an existing one
	 *  - create a new entry
	 *  - be ignored because already stored
	 * 
	 * NOTE: we must add 1 to the returned index because
	 * repeated instances start with 1 (not 0 as arrays)
	 *
	 * @param string $record_id used to retrieve existing FHIR ids from the database
	 * @param string $form_name
	 * @param array $new_data
	 * @param array $existing_entries
	 * @return integer|false return the index of the entry to update or to insert
	 */
	private function shouldUpdateOrInsert($record_id, $form_name, $new_data, $existing_entries)
	{
		$form_fields = $this->project->forms[$form_name]['fields'];
		$fhir_id_field = $form_name.'_fhir_id';
		$new_fhir_id = $new_data[$fhir_id_field];
		// check if the fhir_id is used somewhere in the project record
		$found = $this->findFhirId($record_id, $new_fhir_id);
		if(!empty($found)) {
			// found and could be used more than once (i.e. systolic, diastolic)
			foreach ($found as $fhir_id_data) {
				$instance = $fhir_id_data->instance ?: 1;
				$existing_entry = array_intersect_key($existing_entries[$instance], $form_fields);
				// if existing data is null then the fhir_id could have been orphaned in the database
				if(is_null($existing_entry)) return $instance;
				$are_equal = $this->compareData($form_name, $new_data, $existing_entry);
				if($are_equal) return false; // no need to update
			}
		}
		// check if the FHIR ID key is available in the instrument
		if(!array_key_exists($fhir_id_field, $form_fields)) {
			// no fhir ID found. check if we have an entry to update anyway
			foreach ($existing_entries as $instance => $entry) {
				// add hidden field for future reference
				$are_equal = $this->compareData($form_name, $new_data, $entry);
				// if are equal then update so the FHIR id will be stored for future reference
				if($are_equal) return $instance;
			}
		}
		// no entries to update so we return the index of the new entry to insert
		return max(array_keys($existing_entries))+1; // get the max ID and add 1
	}

	/**
	 * check if all keys in new data are available in $existing data
	 * and if have the same values
	 *
	 * @param array $new_data
	 * @param array $existing_data
	 * @return boolean
	 */
	private function compareData($form_name, $new_data, $existing_data)
	{
		// get the fields of the instrument
		$form_fields = $this->project->forms[$form_name]['fields'];
		// get only new data that fits the instrument (a field can contain it's values)
		$filtred_new_data = array_intersect_key($new_data, $form_fields);
		$filtred_existing_data = array_intersect_key($existing_data, $filtred_new_data);
		$are_equal = true; // assume they are equal before comparison
		foreach ($filtred_new_data as $key => $value) {
			$value = $value ?: ""; // if null turn into empty string
			$existing_value = $filtred_existing_data[$key];
			// check if entry contains a value and if it is equal
			if(!array_key_exists($key, $filtred_existing_data) || trim($existing_value)!==trim($value)) {
				$are_equal = false;
				break; // not equal; stop the loop
			}
		}
		return $are_equal;
	}

	/**
	 * get the record number for an MRN
	 * if the record is not found return the first available record_id
	 *
	 * @param integer $project_id
	 * @param string $mrn
	 * @return string
	 */
	public function getRecordID($project_id, $mrn)
	{
		$project = new \Project($project_id);
		$event_info = (array) $project->eventInfo;
		$events_ids =  array_keys($event_info);
		if(count($events_ids)>0)
			$events_query = ' AND event_id IN ('. implode(', ',$events_ids) . ')';
			
		$query_string = sprintf("SELECT record, value FROM redcap_data
									WHERE value='%s'
									AND field_name='mrn' %s
									AND project_id=%s LIMIT 1",
									db_real_escape_string($mrn),
									db_real_escape_string($events_query),
									db_real_escape_string($project_id));

		$result = db_query($query_string);
		$row = db_fetch_assoc($result);
			
		if($row) return $row['record'];
		return \DataEntry::getAutoId($project_id);
	}

}