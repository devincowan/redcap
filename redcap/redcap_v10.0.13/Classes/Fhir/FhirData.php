<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

class FhirData implements \JsonSerializable
{
    /**
     * data
     *
     * @var array
     */
    private $data = array();

    /**
     * errors
     *
     * @var FhirException[]
     */
    private $errors = array();

    private static $metadata_reserved_keys = ['created_at'];

    /**
     * metadata
     *
     * @var array
     */
    private $metadata = array();

    public function __construct()
    {
        $this->metadata['created_at'] = date("Y-m-d H:i:s");
    }

    /**
     * create an instance with a set of data and errors
     *
     * @param array $data
     * @param FhirException[] $errors
     * @return void
     */
    public static function create($data=null, $errors=null)
    {
        $instance = new self();
        if($data) $instance->setData($data);
        if($errors) $instance->setErrors($errors);
        return $instance;
    }

    /**
     * reset data and errors
     *
     * @return void
     */
    public function reset()
    {
        $this->data = $this->errors = array();
    }

    /**
     * merge data in a single array
     *
     * @param array $data
     * @return void
     */
    public function addData($data)
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * return all available data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * set the data
     *
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * add an Exception error identified by a key
     *
     * @param string $key
     * @param string $message
     * @param Exception $exception
     * @return void
     */
    public function addError($message, $exception=null, $data=null)
    {
        $fhirException = new FhirException($message, $code=0, $exception, $data);
        $this->errors[] = $fhirException;
    }
    
    /**
     * return errors
     *
     * @return FhirException[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * set errors
     * 
     * @param FhirException $errors
     *
     * @return void
     */
    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    /**
     * check if any error exists
     *
     * @return boolean
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * return metadata
     *
     * @return array
     */
    public function getMetadata()
    {
        $metadata = array(
            'total' => count($this->data),
        );
        return array_merge($this->metadata, $metadata);
    }

    /**
     * set a metadata value
     *
     * @param string $key
     * @param mixed $value any value
     * @return FhirData
     */
    public function setMetadata($key, $value)
    {
        
        if(!in_array($key, self::$metadata_reserved_keys))
        {
            $this->metadata[$key] = $value;
        }
        return $this;
    }

    
    /**
     * serialized object
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $data = array(
            'data' => $this->data,
            'metadata' => $this->getMetadata(),
        );
        if(($this->hasErrors())) $data['errors'] = $this->errors;

        return $data;
    }
}