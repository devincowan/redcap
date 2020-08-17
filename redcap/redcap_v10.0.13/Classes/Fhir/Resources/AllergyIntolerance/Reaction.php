<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\AllergyIntolerance;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceCodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AllergyIntolerance\Reaction\Manifestation;

/**
* @property Manifestation[] $manifestations
*/
class Reaction extends FhirResource
{
    /**
     * manifestations
     *
     * @var Manifestation[]
     */
    private $_manifestations;

    /**
     * substance
     *
     * @var FhirResourceCodeableConcept
     */
    private $_substance;

    public function getCertainty()
    {
        return $this->certainty;
        $this->setManifestations();
        $this->setSubstance();
    }

    /**
    * return the codings for the reaction
    *
    * @return Manifestation[] object with system, code and display values
    */
    public function getManifestations()
    {
        return $this->_manifestations;
    }

    private function setManifestations()
    {
        $manifestations = $this->manifestation ?: array();
        if(!is_array($manifestations)) $manifestations = array();
        $this->_manifestations = array_map(function($manifestation) {
            return new Manifestation($manifestation);
        }, $manifestations);
    }

    /**
    * return substance
    *
    * @return FhirResourceCodeableConcept object with system, code and display values
    */
    public function getSubstance(){
        return $this->_substance;
    }

    private function setSubstance()
    {
        if(isset($this->substance)) $this->_substance = new FhirResourceCodeableConcept($this->substance);
    }

    public function getData()
    {
        $data = array(
            'certainty' => $this->getCertainty(),
            'manifestations' => $this->getManifestations(),
            'substance' => $this->getSubstance(),
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