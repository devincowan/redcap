<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

class FhirResourceCondition extends FhirResourceEntry
{

    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'Condition';

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

    /**
     * codings
     *
     * @var FhirResourceCoding[]
     */
    private $codings;

    /**
     * attribute that contains the date for the resource entry
     *
     * @var string
     */
    protected static $dateAttribute = 'dateRecorded';

    public function __construct($params)
    {
        parent::__construct($params);
        // override the default path of the coding systems
        $this->codings = $this->getCodings();
    }

    public function getCodings()
    {
        if($this->codings) return $this->codings;
        if(!isset($this->code->coding)) {
            $this->codings = array();
            return $this->codings;
        }
        try {
           $this->codings = array_map(function($coding) {
                return new FhirResourceCoding($coding);
           }, $this->code->coding);
        } catch (\Exception $e) {
            $this->codings = array();
        }finally {
            return $this->codings;
        }
    }

    public function getLongText()
    {
        $codes_list = array();
        foreach($this->getCodings() as $coding)
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
        $text = implode(', ', $codes_list);
        if($status = $this->getClinicalStatus()) $text .= " - ". $status;
        if($timestamp = $this->getDateRecorded()) $text .= " - ". $timestamp;
        return $text;
        
    }

    /**
     * get the substance coding of a specific system
     *
     * @param string $system
     * @return FhirResourceCoding|null
     */
    public function getCodingByStandard($standard)
    {
        if(empty($standard)) return;
        $codings = $this->getCodings();
        foreach ($codings as $coding) {
            if($coding->getStandard()==$standard) return $coding;
        }
        return;
    }


    /**
     * return the value for the resource
     *
     * @return string
     */
    public function getText()
    {
        return isset($this->code->text) ? $this->code->text : '';
    }

    public function getCategory()
    {
        return isset($this->category->text) ? $this->category->text : '';
    }

    public function getClinicalStatus()
    {
        return $this->clinicalStatus ?: '';
    }

    public function getDateRecorded()
    {
        return $this->getFormattedDate('Y-m-d');
    }

    /**
     * get data specific fot this resource
     * data from the parent class is returned as well
     *
     * @return array
     */
    public function getData()
    {
        $data = array(
            'timestamp' => $this->getDateRecorded('Y-m-d'),
            'clinical_status' => $this->getClinicalStatus(),
            /* 'icd9_code' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_ICD_9_CM, 'code'),
            'icd9_display' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_ICD_9_CM, 'display'),
            'icd10_code' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_ICD_10_CM, 'code'),
            'icd10_display' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_ICD_10_CM, 'display'),
            'snomed_code' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_SNOMED_CT, 'code'),
            'snomed_display' => $this->getCodeFieldByCodingSystem(FhirResourceCoding::SYSTEM_SNOMED_CT, 'display'), */
            'text' => $this->getText(),
            'verification_status' => $this->verificationStatus,
            'category' => $this->getCategory(),
            'codings' => $this->getCodings(),
        );

        return $data;
    }

    public function __toString()
    {
        return $this->getLongText();
    }
}