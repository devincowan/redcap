<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceMedicationOrder;

class FhirEndpointMedicationOrder extends FhirEndpoint
{
    const RESOURCE_TYPE = 'MedicationOrder';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'dateWritten';

    public function search($params)
    {
        $accepted_keys = array(
            'patient',
            'status',
            'code',
            'datewritten',
            'encounter',
            'identifier',
            'medication',
            'prescriber',
        );

        $filtered_params = $this->filterParams($params, $accepted_keys);
        return parent::search($filtered_params);
    }

    /**
     * convert a set of REDCap defined parameters in a FHIR compatible format
     *
     * @param string $patient_id
     * @param array $parameters (minDate, maxDate, fields)
     * @return array
     */
    public function convertRedcapParametersToFhir($patient_id, $parameters)
    {
        $params = parent::convertRedcapParametersToFhir($patient_id, $parameters);
        // If "Medications" endpoint for "Active medications list", then set specific URL for it
        // Set URL
        $status_list = array(
            FhirResourceMedicationOrder::STATUS_ACTIVE,
            FhirResourceMedicationOrder::STATUS_COMPLETED,
            FhirResourceMedicationOrder::STATUS_ON_HOLD,
            FhirResourceMedicationOrder::STATUS_STOPPED,
        );
        $requested_status = array();
        // build a regexp that matches all available medication status
        $regExp = sprintf("/^(%s)-medications-list\$/i", implode('|', $status_list));
        foreach ($parameters['fields'] as $field) {
            preg_match($regExp, $field, $matches);
            if($matches)
            {
                $requested_status[] = $matches[1]; // get the matched status
            }
        }
        if(!empty($requested_status)) $params[] = array($key='status', $requested_status, \UrlQueryBuilder::QUERY_ARRAY_FORMAT_COMMA);
        return $params;
    }
}