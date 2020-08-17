<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

class FhirEndpointCondition extends FhirEndpoint
{
    const RESOURCE_TYPE = 'Condition';
    
    /**
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     * 
     * 'category' or 'clinicalStatus'
     * Problem List as 'category=problem,complaint,symptom,finding,diagnosis,health-concern'
     * @see http://argonautwiki.hl7.org/index.php?title=Problems_and_Health_Concerns
     */
    const CODE_PARAMETER_NAME = 'category';

    /** 
     * Map the FHIR endpoint category to the name of the query string "date" parameter to limit the request's time period.
     * Note: The 'Patient', 'Device', and 'FamilyMemberHistory' categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'onset';

    public function search($params)
    {
        $accepted_keys = array(
            'asserter',
            'body-site',
            'category',
            'clinicalstatus',
            'code',
            'date-recorded',
            'encounter',
            'evidence',
            'identifier',
            'onset',
            'onset-info',
            'patient',
            'severity',
            'stage',
        );
        
        $filtered_params = $this->filterParams($params, $accepted_keys);
        return parent::search($filtered_params);
    }
}