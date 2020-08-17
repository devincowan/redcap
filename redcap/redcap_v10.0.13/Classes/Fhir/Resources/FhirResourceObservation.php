<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\Resources\Observation\Value;

class FhirResourceObservation extends FhirResourceEntry
{     
    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'Observation';

    /**
     * Map the FHIR endpoint category to the name of the query string "date" parameter to limit the request's time period.
     * Note: The 'Patient', 'Device', and 'FamilyMemberHistory' categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'date';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const CODE_PARAMETER_NAME = 'code'; // or 'category', Vitals as 'category=vital-signs'

    /**
     * attribute that contains the date for the resource
     *
     * @var string
     */
    protected static $dateAttribute = array('effectiveDateTime', 'issued');

    /**
     * available categories for the observation resource
     *
     * @var array
     */
    private static $categories = array(
        'Vital Signs',
        'Laboratory',
        'Social History',
    );

    public static function getSearchUrl($params = array())
    {
        $categories = static::$categories;
        // set the category to retrieve from the endpoint
        $params['category'] = isset($params['category']) ? $params['category'] : $categories[1];
        return parent::getSearchUrl($params);
    }

    public static function getCategories()
    {
        return static::$categories;
    }

    /**
     * get the category of the observation resource
     *
     * @return string|false
     */
    public function getCategoryCode()
    {
        $category_codes = $this->category->coding;
        // get the first element in the list of category codes
        if($category_code = reset($category_codes))
        {
            return $category_code->code;
        }
        return false;
    }
    
    /**
     * return the value for the resource
     *
     * @return Value[]
     */
    public function getValues()
    {
        $values = array(); // store all available values
        // observation can have components;
        // if no components are defined consider all the data as a component and store it in an array
        $components = isset($this->component) ? $this->component : array($this->params);
        foreach ($components as $component) {
            $coding_list = $component->code->coding;
            if(empty($coding_list)) $coding_list = array(null); // also get values with no coding providing a null value
            foreach($coding_list as $coding)
            {
                $observation_value = new Value($component, $coding);
                $values[] = $observation_value;
            }
        }
        return $values;
    }

    public function getData()
    {
        $data = array(
            'date' => $this->getFormattedDate(),
            'values' => $this->getValues(),
        );
        return $data;
    }

    public function getMetaData()
    {
        $metadata = array(
            'category' => $this->getCategoryCode()
        );
        return array_merge(parent::getMetadata(), $metadata);
    }
}