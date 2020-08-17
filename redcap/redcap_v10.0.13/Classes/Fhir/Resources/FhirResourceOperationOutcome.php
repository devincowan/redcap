<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * class for a FHIR resource boudle
 */
class FhirResourceOperationOutcome extends FhirResource
{

    /**
     * return the list of issues
     *
     * @return array
     */
    public function getIssues() {
        return $this->issue;
    }
    
    /**
     * get all data available for this resource
     *
     * @return array
     */
    public function getData()
    {
        $data = $this->params;
        return $data;
    }
}