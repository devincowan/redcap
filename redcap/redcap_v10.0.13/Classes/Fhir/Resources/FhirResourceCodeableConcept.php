<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceCoding;

class FhirResourceCodeableConcept extends FhirResource
{

    /**
     * codings
     *
     * @var FhirResourceCoding[]
     */
    private $_codings;

    /**
     * text
     *
     * @var string
     */
    private $_text;

    /**
     * get the codings
     *
     * @return FhirResourceCoding[]
     */
    public function getCodings()
    {
        if(!isset($this->_codings)) {
            try {
                $this->_codings = array_map(function($params) {
                    return new FhirResourceCoding($params);
                }, $this->coding);
            } catch (\Exception $e) {
                $this->_codings = array();
            }
        }
        return $this->_codings;
    }

    /**
     * get the text
     *
     * @return string
     */
    public function getText()
    {
        if(!isset($this->_text)) {
            $this->_text = $this->text ?: '';
        }
        return $this->_text;
    }

    public function getData()
    {
        $data = array(
            'text' => $this->getText(),
            'codings' => $this->getCodings(),
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