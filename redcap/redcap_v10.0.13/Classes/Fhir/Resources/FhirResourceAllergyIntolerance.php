<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AllergyIntolerance\Reaction;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AllergyIntolerance\Substance;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AllergyIntolerance\Reaction\Manifestation;

class FhirResourceAllergyIntolerance extends FhirResourceEntry
{

    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'AllergyIntolerance';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'onset';
    
    /**
     * substance
     *
     * @var Substance
     */
    private $_substance;
    /**
     * reactions
     *
     * @var Reaction[]
     */
    private $_reactions;
    /**
     * codings
     *
     * @var FhirResourceCoding[]
     */
    private $_codings;

    /**
     * get code
     *
     * @var FhirResourceCodeableConcept
     */
    private $_code;

    /**
     * attribute that contains the date for the resource entry
     *
     * @var string
     */
    protected static $dateAttribute = 'recordedDate';

    public function __construct($params) {
        parent::__construct($params);
        $this->setSubstance();
        $this->setReactions();
        $this->setCodings();
    }

    /**
     * Undocumented function
     *
     * @return Substance
     */
    public function getSubstance()
    {
        return $this->_substance;
    }

    private function setSubstance()
    {
        if(isset($this->substance)) $this->_substance = new Substance($this->substance);
    }

    /**
     * returl the reactions
     *
     * @return Reaction[]
     */
    public function getReactions()
    {
        return $this->_reactions;
    }

    private function setReactions()
    {
        $reactions = $this->reaction ?: array();
        if(!is_array($reactions)) $reactions = array();
        $this->_reactions = array_map(function($params) {
            return new Reaction($params);
        }, $reactions);
    }

    /**
     * return the code
     *
     * @return FhirResourceCodeableConcept
     */
    public function getCode()
    {
        return $this->_code;
    }
    public function setCode()
    {
        if(isset($this->code)) $this->_code = new FhirResourceCodeableConcept($this->code);
    }

    public function getCodings() {
        return $this->_codings;
    }

    private function setCodings()
    {
        $codings = array();
        // add direct codings of the allergy
        if(is_a($this->getCode(), FhirResourceCodeableConcept::class)) {
            $codings = array_merge($codings, $this->getCode()->getCodings());
        }
        // add codings of the substance
        $substance = $this->getSubstance();
        if(is_a($substance, Substance::class)) {
            $codings = array_merge($codings, $substance->getCodings());
        }
        $reactions = $this->getReactions();
        foreach ($reactions as $reaction) {
            $manifestations = $reaction->getManifestations();
            foreach ($manifestations as $manifestation) {
                if(!is_a($manifestation, FhirResourceCodeableConcept::class)) continue;
                $codings = array_merge($codings, $manifestation->getCodings());
            }
            $reaction_substance = $reaction->getSubstance();
            if(is_a($reaction_substance, FhirResourceCodeableConcept::class)) {
                $codings = array_merge($codings, $reaction_substance->getCodings());
            }
        }
        $this->_codings = $codings;
    }

    /**
     * create a long text version of the allergies using
     * all codes found inside the resource
     * codes can be found in:
     * - code.coding[]
     * - substance.coding[]
     * - reaction[].substance.coding[]
     * - reaction[].manifestation[].coding[]
     *
     * @return void
     */
    public function getLongText()
    {
        $codings = $this->getCodings();
        $text_list = array();
        foreach($codings as $coding)
        {
            $text = $coding->getDisplay();
            $standard = $coding->getStandard();
            $code = $coding->code;
            if($code_text = trim("$standard $code")) $text .= sprintf(" (%s)", $code_text);

            $text_list[] = $text;
        }
        $longText = implode(", ", $text_list);
        if($date = $this->getFormattedDate('Y-m-d')) $longText .= " - ".$date;
        return $longText;
    }
    
    public function getDate()
    {
        $date_string = $this->{self::$dateAttribute};
        if(empty($date_string)) return '';
        $timestamp = trim(substr($date_string, 0, 10));
        if (strlen($timestamp) == 4) $timestamp .= "-01-01";
        $dateTime =  new \DateTime($date_string);
        return $dateTime;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
     * parse all codings and group them by standard
     * a specific standard
     *
     * @param array $standard
     * @return FhirResourceCoding[]
     */
    public function groupCodingsByStandard($standards=array())
    {
        if(empty($standards)) return;
        $codings = $this->getCodings();
        $group = array();
        foreach($codings as $coding)
        {
            $standard = $coding->getStandard();
            if(in_array($standard, $standards)) {
                $group[$standard] = $coding;
            }
        }
        return $group;
    }

    public function getData()
    {
        $data = array(
            'status' => $this->getStatus(),
            'date' => $this->getDate(),
            'reactions' => $this->reactions,
            'substance' => $this->substance,
            'codings' => $this->getCodings(),
        );
        // json_key => standard
        $standards_mapping = array(
            'unii' => FhirResourceCoding::SYSTEM_FDA_UNII,
            'ndfrt' => FhirResourceCoding::SYSTEM_NDF_RT,
            'snomed' => FhirResourceCoding::SYSTEM_SNOMED_CT,
        );
        // get the codings keyed by standard
        $coding_standards = $this->groupCodingsByStandard(array_values($standards_mapping));
        foreach ($standards_mapping as $key => $standard) {
            if(array_key_exists($standard, $coding_standards)) {
                $coding = $coding_standards[$standard];
                $data["{$key}_code"] = $coding->getCode();
                $data["{$key}_display"] = $coding->getDisplay();
            }
        }
        return $data;
    }

}