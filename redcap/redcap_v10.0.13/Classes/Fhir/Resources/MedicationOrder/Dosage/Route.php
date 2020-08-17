<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources\MedicationOrder\Dosage;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;

class Route extends FhirResource
{
    public function __construct($params)
    {
        parent::__construct($params);
        // override the default path of the coding systems
        $this->coding_systems = $this->coding;
    }

    public function getText()
    {
        return $this->text;
    }

    public function getData()
    {
        $data = array(
            'text' => $this->getText(),
        );
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