<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * class for a FHIR resource boudle
 */
class FhirResourceBinary extends FhirResource
{
    /**
     * get all data available for this resource
     *
     * @return array
     */
    public function getData()
    {
        $data = array();
        return $data;
    }

    /**
     * get JSON serialized version of the object
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getData();
    }

   
}