<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

class FhirEndpointObservation extends FhirEndpoint
{
    const RESOURCE_TYPE = 'Observation';

    /**
     * Map the FHIR endpoint category to the name of the query string "date" parameter to limit the request's time period.
     * Note: The 'Patient', 'Device', and 'FamilyMemberHistory' categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'date';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const CODE_PARAMETER_NAME = 'code'; // or 'category', Vitals as 'category=vital-signs'

    public function search($params)
    {
        $accepted_keys = array(
            'category',
            'code',
            'code-value-[x]', // TODO: manage as regular expression
            'component-code',
            'component-code-value-[x]', // TODO: manage as regular expression
            'component-data-absent-reason',
            'component-value-concept',
            'component-value-quantity',
            'component-value-string',
            'data-absent-reason',
            'date',
            'device',
            'encounter',
            'identifier',
            'patient',
            'performer',
            'related',
            'related-target',
            'related-type',
            'specimen',
            'status',
            'subject',
            'value-concept',
            'value-date',
            'value-quantity',
            'value-string',
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

        // add codes (mostrly for labs and vitals)
        $code_key = self::CODE_PARAMETER_NAME;
        // add loinc indentificator
        array_walk($parameters['fields'], function(&$field) { $field = 'http://loinc.org|'.$field;});
        $fields = $parameters['fields'] ?: array();
        $params[] = array($code_key, $fields, \UrlQueryBuilder::QUERY_ARRAY_FORMAT_COMMA);

        return $params;
    }

}