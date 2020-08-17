<?php
namespace REDCap\SSE;

class ServerSentEvent implements \JsonSerializable
{
    public $data;
    public $type;
    
    public function __construct($data, $type='message', $id=null)
    {
        $this->id = $id;
        $this->data = $data;
        $this->type = $type;
    }

    public function __toString()
    {
        return json_encode($this);
    }

    /**
    * Returns data which can be serialized
    *
    * @return array
    */
    public function jsonSerialize() {
        
        $serialized = array(
            'id' => $this->data,
            'data' => json_decode($this->data),
            'type' => $this->type,
        );
        return $serialized;
    }
    
}