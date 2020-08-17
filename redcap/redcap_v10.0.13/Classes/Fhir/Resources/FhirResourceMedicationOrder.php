<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\Resources\MedicationOrder\Dosage;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceCodeableConcept;

class FhirResourceMedicationOrder extends FhirResourceEntry
{

    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'MedicationOrder';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'dateWritten';

    /**
     * list of possible status for a medication
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ON_HOLD = 'on-hold';
    const STATUS_STOPPED = 'stopped';

    /**
     * doses
     * 
     * @var Dosage[] $doses
     */
    private $_doses = array();

        
    /**
     * codings
     *
     * @var FhirResourceCodeableConcept[]
     */
    private $_medication_concept = array();

    public function __construct($params)
    {
        parent::__construct($params);
        $this->setDoses();
        $this->setMedicationConcept();
    }

    public static function getSearchUrl($params = array())
    {
        /**
         * set the status to retrieve from the endpoint
         * default status is active
         * available status are: active, completed, on-hold, stopped
         */
        // $status_list = array('active', 'completed', 'on-hold', 'stopped');

        $status_list = isset($params['status']) ? $params['status'] : array(static::STATUS_ACTIVE);
        if(!is_array($status_list)) $status_list = explode(',', $status_list); // make sure status_list is an array
        unset($params['status']);
        $url = parent::getSearchUrl($params);
        $status = implode(',', $status_list);
        $url .= sprintf("&status=%s", $status);
        return $url;
    }

    public function getDoses()
    {
        return $this->_doses;
    }
    
    protected function setDoses()
    {
        $this->_doses = array();
        $dosageInstructions = $this->dosageInstruction ?: array();
        foreach ($dosageInstructions as $dosageInstruction)
        {
            $this->_doses[] = new Dosage($dosageInstruction);
        }
    }

    /**
     * set the FhirResourceCodeableConcept
     *
     * @return void
     */
    private function setMedicationConcept()
    {
        if(isset($this->medicationCodeableConcept)) {
            $this->_medication_concept = new FhirResourceCodeableConcept($this->medicationCodeableConcept);
        }
    }

    /**
     * get the FhirResourceCodeableConcept
     *
     * @return FhirResourceCodeableConcept|null
     */
    public function getMedicationConcept() {
        return $this->_medication_concept;
    }

    public function getCodings()
    {
        $medication_concept = $this->getMedicationConcept();
        if(is_a($medication_concept, FhirResourceCodeableConcept::class) ) {
            return $medication_concept->getCodings();
        }
        return array();
    }

    /**
     * get the medication name
     * the name could be stored in different locations
     *
     * @return string
     */
    public function getText()
    {
        $medicationReference = $this->medicationReference;
        if(!empty($medicationReference->display)) return $medicationReference->display;
        $medication_concept = $this->getMedicationConcept();
        if(is_a($medication_concept, FhirResourceCodeableConcept::class) ) {
            return $medication_concept->getText();
        }
        return '';
    }

    public function getLongText()
    {
        
        $timestamp = $this->getFormattedDate();
        $status = $this->getStatus();

        // helper function to get a list of strings from the codings
        $getStringFromCodings = function($codings) {
            $codes_list = array();
            foreach($codings as $coding)
            {
                $display = $coding->getDisplay();
                $code_text = array();
                if($system = $coding->getSystem()) $code_text[] = $system;
                if($code = $coding->getCode()) $code_text[] = $code;
                if(empty($code_text))
                {
                    // cody has only display available
                    $codes_list[] = $display;
                }else
                {
                    // we also have code and/or system; show them in parenthesis
                    $codes_list[] = sprintf("%s (%s)", $display, implode(" ", $code_text));
                }
            }
            return $codes_list;
        };
        $codes_list = $getStringFromCodings($this->getCodings());
        if(empty($codes_list))
        {
            // if there are no codings then use the generic text
            $display = $this->getText();
        }else
        {
            // use all codes available
            $display = implode(', ', $codes_list);
        }
        
        $doses = $this->getDoses(); // get the doses for the current medication
        $firstDose = reset($doses); // get the first dose
        $dosage = ($firstDose instanceof Dosage) ? $firstDose->getText() : '';
        // compose the text to display in CDP
        $text_values = array();
        if(!empty($display)) $text_values[] = $display;
        if(!empty($dosage)) $text_values[] = $dosage;
        $text_values = array(implode(', ', $text_values)); // join text values and store back in array
        if(!empty($status)) $text_values[] = $status;
        if(!empty($timestamp)) $text_values[] = $timestamp;
        $text = implode(' - ', $text_values); // join text and date
        $text = trim($text);
        return $text;
    }
    
    /**
     * return when the medication was written
     *
     * @return string
     */
    public function getDate()
    {
        $value = $this->dateWritten;
        return $value;
    }

    public function getFormattedDate($format='Y-m-d')
    {
        $date = $this->getDate();
        if(empty($date)) return;
        $dateTime = new \DateTime($date);
        return $dateTime->format($format);
    }
    
    /**
     * get the status
     * if the status is empty return the default status (active)
     *
     * @return string
     */
    public function getStatus()
    {
        $value = $this->status;
        return $value;
    }

    public function getData()
    {
        $doses = $this->getDoses();
        $dosageInstructions = array_map(function($dose) {
            return $dose->getTiming();
        }, $doses);

        $data = array(
            'text' => $this->getText(),
            'date' => $this->getFormattedDate(),
            'dosage_instruction' => implode(', ', $dosageInstructions),
            'prescriber' => $this->prescriber->display,
            'status' => $this->status,
            'doses' => $doses,
        );
        return $data;
    }

    public function __toString()
    {
        return $this->getLongText();
    }
}
