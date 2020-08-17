<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;


class FhirResourceCoding extends FhirResource
{
    /**
     * list of available coding systems
     */
    const SYSTEM_ICD_10 = 'ICD-10';
    const SYSTEM_ICD_10_CM = 'ICD-10-CM';
    const SYSTEM_ICD_9_CM = 'ICD-9-CM';
    const SYSTEM_LOINC = 'LOINC';
    const SYSTEM_SNOMED_CT = 'SNOMED CT';
    const SYSTEM_RxNorm = 'RxNorm';
    const SYSTEM_FDA_UNII = 'FDA UNII';
    const SYSTEM_NDF_RT = 'NDF-RT';
    const SYSTEM_CVX = 'CVX';
    const SYSTEM_NDC_NHRIC = 'NDC/NHRIC';
    const SYSTEM_AMA_CPT = 'AMA CPT';
    const SYSTEM_UCUM = 'UCUM';
    const SYSTEM_NCI_Metathesaurus = 'NCI Metathesaurus';

    /**
     * list of coding standars identified by their regular expressions
     *
     * @var array
     */
    private static $standards_regexps = array(
        '/^urn:oid:2.16.840.1.113883.4.642.1.567$/i' => self::SYSTEM_ICD_10,
		'/^urn:oid:2.16.840.1.113883.6.90$/i' => self::SYSTEM_ICD_10_CM,
		'/^urn:oid:2.16.840.1.113883.6.3$/i' => self::SYSTEM_ICD_10,
		'/^urn:oid:2.16.840.1.113883.6.3.1$/i' => self::SYSTEM_ICD_10,
		'/^urn:oid:2.16.840.1.113883.6.4$/i' => self::SYSTEM_ICD_10,
		'/icd-9-cm/i' => self::SYSTEM_ICD_9_CM,
        '/loinc/i' => self::SYSTEM_LOINC,
		'/snomed/i' => self::SYSTEM_SNOMED_CT,
		'/rxnorm/i' => self::SYSTEM_RxNorm,
		'/UNII/i' => self::SYSTEM_FDA_UNII,
        '/fdasis/i' => self::SYSTEM_FDA_UNII,
		'/ndfrt/i' => self::SYSTEM_NDF_RT,
		'/cvx/i' => self::SYSTEM_CVX,
		'/ndc/i' => self::SYSTEM_NDC_NHRIC,
		'/cpt/i' => self::SYSTEM_AMA_CPT,
		'/unitsofmeasure/i' => self::SYSTEM_UCUM,
		'/ncimeta/i' => self::SYSTEM_NCI_Metathesaurus,
    );

    /**
     * get the coding system
     *
     * @return string|null
     */
    public function getSystem()
    {
       return $this->system ?: '';
    }

    public function getCode()
    {
        return $this->code ?: '';
    }

    public function getDisplay()
    {
        return $this->display ?: '';
    }

    public function getStandard()
    {
        $system = $this->getSystem();
        foreach(self::$standards_regexps as $regExp => $standard)
        {
            if(preg_match($regExp, $system, $matches)) return $standard;
        }
        return;
    }

    public static function getStandardFromSystem($system)
    {
        foreach(self::$standards_regexps as $regExp => $standard)
        {
            if(preg_match($regExp, $system, $matches)) return $standard;
        }
        return;
    }

    public function getData()
    {
        $data = array(
            'system' => $this->getSystem(),
            'code' => $this->getCode(),
            'display' => $this->getDisplay(),
        );
        return $data;
    }

    public function jsonSerialize()
    {
        return $this->getData();
    }
}