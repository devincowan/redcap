<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

class FhirStatsCollector
{
    /**
     * project types
     */
    const REDCAP_TOOL_TYPE_CDM = 'CDM'; // type for clinical data mart
    const REDCAP_TOOL_TYPE_CDP = 'CDP'; // type for clinical data pull

    /**
     * list of status of each field saved using Records::saveData
     */
    const SAVE_RECORD_STATUS_KEEP = 'keep'; // do not change
    const SAVE_RECORD_STATUS_ADD = 'add'; // new entry
    const SAVE_RECORD_STATUS_UPDATE = 'update'; // update entry
    const SAVE_RECORD_STATUS_CACHE = 'cache'; // cache (CDP, pre adjudication)
    const SAVE_RECORD_STATUS_CHANGE = 'change'; // update or add

    const COUNTS_TABLE_NAME = 'redcap_ehr_import_counts';

    // resource types
    const RESOURCE_TYPE_PATIENT = 'Patient';
    const RESOURCE_TYPE_OBSERVATION = 'Observation';
    const RESOURCE_TYPE_ALLERGY_INTOLERANCE = 'AllergyIntolerance';
    const RESOURCE_TYPE_MEDICATION_ORDER = 'MedicationOrder';
    const RESOURCE_TYPE_CONDITION = 'Condition';

    public static $counted_fhir_resources = array(
        self::RESOURCE_TYPE_PATIENT,
        self::RESOURCE_TYPE_OBSERVATION,
        self::RESOURCE_TYPE_ALLERGY_INTOLERANCE,
        self::RESOURCE_TYPE_MEDICATION_ORDER,
        self::RESOURCE_TYPE_CONDITION,
    );

    /**
     * map the fields category in FHIR external fields to a FHIR endpoint
     *
     * @var array
     */
    private static $category_to_resource_mapping = array(
        'Demographics' => self::RESOURCE_TYPE_PATIENT,
        'Vital Signs' => self::RESOURCE_TYPE_OBSERVATION,
        'Laboratory' => self::RESOURCE_TYPE_OBSERVATION,
        'Allergy Intolerance' => self::RESOURCE_TYPE_ALLERGY_INTOLERANCE,
        'Medications' => self::RESOURCE_TYPE_MEDICATION_ORDER,
        'Condition' => self::RESOURCE_TYPE_CONDITION,
    );

    /**
     * map the datamart forms to a FHIR endpoint
     *
     * @var array
     */
    private static $datamart_forms_to_resource_mapping = array(
        'demography' => self::RESOURCE_TYPE_PATIENT,
        'vital_signs' => self::RESOURCE_TYPE_OBSERVATION,
        'labs' => self::RESOURCE_TYPE_OBSERVATION,
        'allergies' => self::RESOURCE_TYPE_ALLERGY_INTOLERANCE,
        'medications' => self::RESOURCE_TYPE_MEDICATION_ORDER,
        'problem_list' => self::RESOURCE_TYPE_CONDITION,
    );

    /**
     * store all entries during CDIS operations
     * before storing them to the database
     * entries could have different types and could
     * be adjudicated or not
     *
     * @var FhirStatsEntry[]
     */
    private $entries = array();

    /**
     * stats could be for CDM or CDP
     *
     * @var string
     */
    private $type;

    /**
     * cache external fields when working with CDP type projects
     *
     * @var array
     */
    private $fhir_external_fields = null;

    /**
     * create a stats collector
     *
     * @param integer $project_id
     * @param string $type type of the project (CDM or CDP)
     */
    public function __construct($project_id, $type)
    {
        // check if the provided type is valid
        $valid_types = array(self::REDCAP_TOOL_TYPE_CDM, self::REDCAP_TOOL_TYPE_CDP);
        if(!in_array($type, $valid_types)) throw new \Exception("Invalid type", 1);
        $this->type = $type;
        $this->project_id = $project_id;
        $this->project = new \Project($project_id);
    }

    /**
     * get the list of entries
     *
     * @return FhirStatsEntry[]
     */
    public function getEntries()
    {
        return $this->entries;
    }
    
    public function logEntries($time=null, $returnUnexecutedQuery=false)
    {
        $time = intval($time) ?: time();
        $timestamp = date('Y-m-d H:i:s', $time);

        $row_default_values = array(
            'ts' => checkNull($timestamp),
            'type' => checkNull($this->type),
            'project_id' => $this->project_id,
            'record' => null,
            'counts_Patient' => 0,
            'counts_Observation' => 0,
            'counts_Condition' => 0,
            'counts_MedicationOrder' => 0,
            'counts_AllergyIntolerance' => 0,
        );
        $row_keys = array_keys($row_default_values);
        // start building the query string
        $query_string = "INSERT INTO ".self::COUNTS_TABLE_NAME;
        $query_string .= sprintf(" (%s) ", implode(', ', $row_keys));

        $counts_by_record = $this->countResourceEntriesByRecord();

        // loop throug records
        $insert_queries = array();
        foreach($counts_by_record as $record_id => $counts)
        {
            $row = $row_default_values;
            $row['record'] = $record_id;

            $subtotal = 0; //sum all the data; if teh subtotal is 0 then do not log
            foreach($counts as $resource_type => $total)
            {
                $subtotal += $total;
                $row["counts_{$resource_type}"] = $total;
            }
            if($subtotal<1) continue; // do not log if subtotal is 0
            $values = array_values($row);
            $insert_queries[] = sprintf("(%s)", implode(', ', $values));
        }
        if(empty($insert_queries)) return;
        $query_string .= "VALUES ";
        $query_string .= implode(','.PHP_EOL, $insert_queries);

        if ($returnUnexecutedQuery) {
            return $query_string;
        } else {
            return db_query($query_string);
        }
    }

    /**
     * render the entries in a schema that groups
     * data by record_id, modified/not_modified resources
     * and resource type
     *
     * @param FhirStatsEntry[] $entries if no entries are provided use instance entries
     * @return array
     */
    public function countResourceEntriesByRecord($entries=null)
    {
        $entries = $entries ?: $this->entries;
        $schema = array_reduce($entries, function($schema, $entry) {
            if(!is_a($entry, FhirStatsEntry::class)) throw new \Exception("An array of FhirStatsCollector\Entry must be provided", 1);
            $record_id = $entry->record_id;
            $resource_type = $entry->resource_type;
            // make sure each record has his own container with groups for modified and not modified counts
            if(!is_array($schema[$record_id])) $schema[$record_id] = array();
            $schema[$record_id][$resource_type] += 1;
            return $schema;
        }, array());
        return $schema;
    }

    /**
     * render the entries in a schema that groups
     * data by resource type
     *
     * @param FhirStatsEntry[] $entries if no entries are provided use instance entries
     * @return array
     */
    public function countResourceEntries($entries=null)
    {
        $entries = $entries ?: $this->entries;
        $schema = array_reduce($entries, function($schema, $entry) {
            if(!is_a($entry, FhirStatsEntry::class)) throw new \Exception("An array of FhirStatsEntry must be provided", 1);
            $resource_type = $entry->resource_type;
            // make sure each record has his own container with groups for modified and not modified counts
            $schema[$resource_type] += 1;
            return $schema;
        }, array());
        return $schema;
    }


    /**
     * add entries using data in a REDCap record format
     *
     * @param array $record
     * @return FhirStatsEntry[]
     */
    public function addEntriesFromRecord($record)
    {
        $entries = array();
        foreach ($record as $record_id => $events)
        {
            foreach ($events as $event_id => $instances)
            {
                foreach ($instances as $instance_key => $fields)
                {
                    foreach ($fields as $field_key => $value) {
                        $entries[] = $this->addEntryUsingField($record_id, $field_key, $event_id );
                    }
                }
            }
        }
        return $entries;
    }

    /**
     * use a field key to find the resource type that must be incremented
     * then create and store a new entry
     *
     * @param integer $record_id ID of the record
     * @param string $field_key key of the field in the form
     * @param string $event_id event of the form where the field is stored
     * @return FhirStatsEntry
     */
    public function addEntryUsingField($record_id, $field_key, $event_id=null)
    {
        $fhir_resource_type = $this->getFhirResourceTypeForField($field_key, $event_id);
        if(!$fhir_resource_type) return; // exit if no resource type
        return $this->addEntry($record_id, $fhir_resource_type);
    }

    /**
     * increment the counter of the FHIR resource for a specific record
     *
     * @param integer $record_id
     * @param string $fhir_resource_type
     * @return FhirStatsEntry
     */
    public function addEntry($record_id, $fhir_resource_type)
    {
        $entry = new FhirStatsEntry($record_id, $fhir_resource_type);
        $this->entries[] = $entry;
        return $entry;
    }

    /**
     * get a FHIR resource for a field
     * - in CDM projects the FHIR resources are grouped by forms
     * - in CDP projects use the redcap_ddp_mapping table and the external fields list (csv file)
     *
     * @param string $field_name
     * @param integer $event_id used to retrieve the mapping for CDP projects
     * @return string name of the FHIR resource
     */
    public function getFhirResourceTypeForField($field_name, $event_id=null)
    {
        switch($this->type)
        {
            case self::REDCAP_TOOL_TYPE_CDM:
                // in datamart the FHIR resources are divided by form
                $form_name = $this->getFormNameForField($field_name);
                $fhir_resource_type = self::$datamart_forms_to_resource_mapping[$form_name];
                break;
            case self::REDCAP_TOOL_TYPE_CDP:
                // search the resource type using the external fields and the mapping table
                $query_string = sprintf(
                                "SELECT * FROM redcap_ddp_mapping
                                WHERE project_id=%u
                                AND field_name='%s'",
                                $this->project_id,
                                db_real_escape_string($field_name)
                            );
                if($event_id) $query_string .= " AND event_id={$event_id}";
                $result = db_query($query_string);
                if($row = db_fetch_assoc($result))
                {
                    $fhir_external_fields = $this->getFhirExternalFields();
                    $external_source_field_name = $row['external_source_field_name'];
                    $fhir_external_field_data = $fhir_external_fields[$external_source_field_name];
                    $category = $fhir_external_field_data['category'];
                    // check if a category mapping is available
                    if(!array_key_exists($category, self::$category_to_resource_mapping)) $fhir_resource_type = null;
                    else $fhir_resource_type = self::$category_to_resource_mapping[$category];
                }
                break;
            default:
                $fhir_resource_type = null;
                break;
        }
        return $fhir_resource_type;
    }

    /**
     * get FHIR fields from csv file
     * fields are cached for performance reasons
     *
     * @return array
     */
    private function getFhirExternalFields()
    {
        // return the cached list if available
        if($this->fhir_external_fields) return $this->fhir_external_fields;
        $DDP = new \DynamicDataPull(0, $realtime_webservice_type = 'FHIR');
        
        $external_fields = $DDP->getExternalSourceFields();
        $this->fhir_external_fields = $external_fields; //cache the list
        return $this->fhir_external_fields;
    }

    /**
     * the the name of the form that contains a field
     *
     * @param string $field_name
     * @return string
     */
    private function getFormNameForField($field_name)
    {
        $metadata = $this->project->metadata;
        $field_info = $metadata[$field_name];
        if(!$field_info) return false;
        return $field_info['form_name'];
    }
}