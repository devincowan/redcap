<?php

class JsonError implements JsonSerializable
{
    /**
     * title
     *
     * @var [type]
     */
    private $title;
    
    /**
     * detail
     *
     * @var [type]
     */
    private $detail;
    
    /**
     * status
     *
     * @var [type]
     */
    private $status;
    
    /**
     * source
     *
     * @var [type]
     */
    private $source;


    /**
     * constructor
     *
     * @param string $title
     * @param string $detail
     * @param integer $status
     * @param string $source
     */
    public function __construct($title, $detail, $status=0, $source=null)
    {
        $this->title = $title;
        $this->detail = $detail;
        $this->status = $status;
        $this->source = $source;
    }


    public function getData()
    {
        return array(
            "status" => $this->status,
            "source" => array('pointer' => $this->source),
            "title" =>  $this->title,
            "detail" => $this->detail,
        );
    }

    /**
    * Returns data which can be serialized
    * this format is used in the client javascript app
    *
    * @return array
    */
    public function jsonSerialize()
    {
        $error = $this->getData();
        //TODO: responseJSON is does not convert arrays with single elements
        return array(
            'errors' => array($error)
        );
    }
}