<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

/**
 * abstrac class for a FHIR resource
 */
abstract class FhirResource implements \JsonSerializable
{
    /**
     * parameters used to create the resource
     *
     * @var object
     */
    protected $params;


    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = '';

    /**
     * Map the FHIR endpoint category to the name of the query string "date" parameter to limit the request's time period.
     * Note: The 'Patient', 'Device', and 'FamilyMemberHistory' categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = null;

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const CODE_PARAMETER_NAME = null;

    /**
     * contains all attributes of the resource
     *
     * @var array
     */
    protected $attributes = array();

    public function __construct($params)
    {
        if (is_array($params) || is_object($params))
        {
            $this->params = $params;
            foreach ($params as $key => $value)
            {
                $this->{$key} = $value; // use magic setter
            }
        }
    }

    /**
     * search for a value using a JSONPATH expression
     * @see http://jmespath.org/examples.html
     *
     * @param string $expression
     * @return array|string
     */
    public function search($expression)
    {
        try {
            $data = $this->params;
            $results = \JmesPath\search($expression, $data);
            return $results;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getResourceType()
    {
        return $this->resourceType;
    }

    public static function getReadUrl($resource_id, $params= array())
    {
        $enpoint_base_URL = FhirEhr::getFhirEndpointBaseUrl();
        // Build Epic URL
        $url = $enpoint_base_URL.sprintf("%s/%s", static::RESOURCE_NAME, urlencode($resource_id));
        if(!empty($params))
        {
            $query_params = http_build_query($params);
            $url .= sprintf("?%s", $query_params);
        }
        return $url;
    }

    public static function getSearchUrl($params=array())
    {
        $enpoint_base_URL = FhirEhr::getFhirEndpointBaseUrl();
        // Build Epic URL
        $url = $enpoint_base_URL.sprintf("%s", static::RESOURCE_NAME);
        if(!empty($params))
        {
            $url_encoded_params = array_map('urlencode', $params);
            $query_params = http_build_query($url_encoded_params);
            $url .= sprintf("?%s", $query_params);
        }
        return $url;
    }

    public function getDate()
    {
        return '';
    }

    public function flatten()
    {
        return $this->flatten_object($this->params);
    }

    /**
     * Undocumented function
     *
     * @param object|array $object
     * @param array $flattened
     * @param array $path
     * @param string $path_separator character that separates the elements of the path
     * @return array
     */
    private function flatten_object($object, $flattened=array(), $path=array(), $path_separator = ':')
    {
        foreach($object as $key => $value)
        {
            $path[] = $key;
            if(is_array($value) || is_object($value))
            {
                $flattened = $this->flatten_object($value, $flattened, $path);
                array_pop($path);
                continue;
            }
            $string_path = implode($path_separator, $path);
            $flattened[$string_path] = $value;
            array_pop($path);
        }
        return $flattened;
    }

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
     * get the source object
     *
     * @return void
     */
    public function getSource()
    {
        return $this->params;
    }


    /**
     * get the metadata of the resource
     *
     * @return array
     */
    public function getMetaData()
    {
        $metadata = array(
            'resourceType' => $this->getResourceType(),
            'source' => $this->params,
        );
        return $metadata;
    }

    /**
     * get JSON serialized version of the object
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $data = array(
            'data' => $this->getData(),
            'metadata' => $this->getMetaData(),
        );

        $data = $this->getData();
        return $data;
    }

    /**
     * magic getter for attributes
     *
     * @param string $name
     * @return void
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /**
     * magic setter for attributes
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * magic method that is invoked when isset() or empty()
     * check non-existent or inaccessible class property
     *
     * @param string $name
     * @return boolean
     */
    public function __isset($name){
        return array_key_exists($name, $this->attributes);
    }
}