<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

class FhirEndpointAllergyIntolerance extends FhirEndpoint
{
    const RESOURCE_TYPE = 'AllergyIntolerance';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'onset';

    public function search($params)
    {
        $accepted_keys = array(
            'asserter',
            'category',
            'clinical-status',
            'code',
            'criticality',
            'date',
            'identifier',
            'last-date',
            'manifestation',
            'onset',
            'patient',
            'recorder',
            'route',
            'severity',
            'type',
            'verification-status',
        );
        $filtered_params = $this->filterParams($params, $accepted_keys);
        return parent::search($filtered_params);
    }

}
