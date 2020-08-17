<?php


namespace  Vanderbilt\REDCap\Classes\Fhir\DataMart
{


    class DataMartStats implements \JsonSerializable
    {

        const CATEGORY_DEMOGRAPHY = 'Demography';
        const CATEGORY_MEDICATION_ORDER = 'Medication Order';
        const CATEGORY_CONDITION = 'Condition';
        const CATEGORY_ALLERGY_INTOLERANCE = 'Allergy Intolerance';
        const CATEGORY_VITAL_SIGNS = 'Vital Signs';
        const CATEGORY_LAB_RESULTS = 'Laboratory Results';

        private static $categories = array(
            self::CATEGORY_DEMOGRAPHY,
            self::CATEGORY_MEDICATION_ORDER,
            self::CATEGORY_CONDITION,
            self::CATEGORY_ALLERGY_INTOLERANCE,
            self::CATEGORY_VITAL_SIGNS,
            self::CATEGORY_LAB_RESULTS,
        );

        private $statistics = array();

        public function __construct()
        {
            foreach (self::$categories as $category) {
                $this->statistics[$category] = 0;
            }
        }

        public function increase($category)
        {
            if(array_key_exists($category, $this->statistics))
            {
                return $this->statistics[$category]++;
            }
        }

        /**
        * Returns data which can be serialized
        * this format is used in the client javascript app
        *
        * @return array
        */
        public function jsonSerialize()
        {
            $serialized = array(
                'data' => $this->statistics,
            );
            return $serialized;
        }

    }
}