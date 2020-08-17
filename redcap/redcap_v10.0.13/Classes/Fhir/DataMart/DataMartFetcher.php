<?php



namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

use Vanderbilt\REDCap\Classes\Fhir\FhirData;

class DataMartFetcher
{

    public function __construct()
    {
    }


    /**
     * fetch data for a single MRN using a revision
     *
     * @param DataMartRevision $revision
     * @param string $mrn
     * @return FhirData
     */
    public function fetchData($revision, $mrn)
    {
        global $lang, $fhir_convert_timestamp_from_gmt;
        // get date range for record
        $date_range = $revision->getDateRangeForMrn($mrn);
        $date_min = $date_range['date_min'];
        $date_max = $date_range['date_max'];
        // get revision fields
        $revision_fields = $revision->fields;
        // Normalize the FHIR fields into DDP compatible field array
        $normalized_fields = $this->normalizeFhirDataMartFields($revision_fields, $date_min, $date_max);

        $ddp = new \DynamicDataPull($revision->project_id, 'FHIR');
        $fhir_data = $ddp->getFhirData($mrn, $normalized_fields, $is_dataMart=true);

        return $fhir_data;
    }

    // Data Mart: Normalize the FHIR fields into DDP compatible field array
    private function normalizeFhirDataMartFields($fields=array(), $date_min='', $date_max='')
    {
        $normalized = array();
        foreach ($fields as $field) {
            $normalized[] = array('field'=>$field, 'timestamp_min'=>$date_min, 'timestamp_max'=>$date_max);
        }
        return $normalized;
    }
}