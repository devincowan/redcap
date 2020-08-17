<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

/**
 * a JsonSerializable exception that can be consumed by clients
 */
class FhirException extends \Exception implements \JsonSerializable
{
    /**
     * additional data can be attached to this exception
     *
     * @var mixed
     */
    private $data;

    public function __construct($message = null, $code = 0, $previous = null, $data = null)
    {
        if($data) $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    /**
     * get additional data
     *
     * @return void
     */
    public function getData()
    {
        return $this->data;
    }

    public function getErrorDetail()
    {
        if(!$previous=$this->getPrevious()) return false;
        $message = $previous->getMessage();
        $code = $previous->getCode();
        return sprintf("%s (error code %u)", $message, $code);
    }

    public function jsonSerialize()
    {
        $data = array(
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
        );
        if($previous=$this->getPrevious())
        {
            $detail = array(
                'code' => $previous->getCode(),
                'message' => $previous->getMessage(),
            );
            $data['detail'] = $detail;
        }

        return $data;
    }
}