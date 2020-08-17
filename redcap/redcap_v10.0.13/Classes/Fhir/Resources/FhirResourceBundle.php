<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * class for a FHIR resource boudle
 * @property FhirResourceEntry[] $entries
 */
class FhirResourceBundle extends FhirResource
{

    const RESOURCE_NAME = 'Bundle';

    public function __construct($params)
    {
        parent::__construct($params);
        $this->entries = $this->getEntries();
    }
    /**
     * get all entries available in the resoruce boundle
     *
     * @return FhirResourceEntry[]
     */
    protected function getEntries()
    {
        $raw_entries = is_array($this->entry) ? $this->entry : array();
        $entries = array();
        foreach($raw_entries as $raw_entry)
        {
            $type = $raw_entry->resource->resourceType;
            $data = $raw_entry->resource;
            switch ($type) {
                case 'Observation':
                    $entries[] = new FhirResourceObservation($data);
                    break;
                case 'MedicationOrder':
                    $entries[] = new FhirResourceMedicationOrder($data);
                    break;
                case 'Condition':
                    $entries[] = new FhirResourceCondition($data);
                    break;
                case 'AllergyIntolerance':
                    $entries[] = new FhirResourceAllergyIntolerance($data);
                    break;
                case 'DocumentReference':
                    $entries[] = new FhirResourceDocumentReference($data);
                    break;
                case 'OperationOutcome':
                // do nothing; no results
                    $entries[] = new FhirResourceOperationOutcome($data);
                    break;
                default:
                    $entries[] = new FhirResourceEntry($data);
                    break;
            }
        }
        return $entries;
    }

    /**
     * get all data available for this resource
     *
     * @return array
     */
    public function getData()
    {
        $data = array(
            'entries' => $this->entries,
        );
        return array_merge(parent::getData(), $data);
    }


    /**
     * get the metadata of the resource
     *
     * @return array
     */
    public function getMetaData()
    {
        $entries = $this->entries;
        $metadata = array(
            'total' => count($entries),
        );
        return array_merge(parent::getMetaData(), $metadata);
    }
   
}