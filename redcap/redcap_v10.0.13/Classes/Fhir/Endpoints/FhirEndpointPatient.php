<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

class FhirEndpointPatient extends FhirEndpoint
{
    const RESOURCE_TYPE = 'Patient';

    public function search($params)
    {
        $accepted_keys = array(
            '_id',
            'identifier',
            'family',
            'given',
            'birthdate',
            'address',
            'gender',
            'telecom',
        );
        $filtered_params = $this->filterParams($params, $accepted_keys);
        return parent::search($filtered_params);
    }

    /**
     * helper function to search a patient by SSN
     * @see https://www.hl7.org/fhir/identifier-registry.html
     *
     * @param [type] $ssn
     * @return void
     */
    public function searchBySsn($params)
    {
        $identifier_code = '2.16.840.1.113883.4.1'; // code for the SSN system
        $ssn = $params['identifier'];
        $identifier = sprintf("%s|%s", $identifier_code, $ssn);
        $params = compact('identifier');
        return $this->search($params);
    }
}