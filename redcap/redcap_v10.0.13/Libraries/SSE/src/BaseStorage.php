<?php
namespace REDCap\SSE;

abstract class BaseStorage implements StorageInterface
{
    const EXPIRATION_TIME = 7200; //seconds = 60 * 60 * 2 = 2 hours
    /**
     * channle where the data is stored and retrieved
     *
     * @var string
     */
    protected $channel;

    public function __construct($channel)
    {
        if(empty($channel)) $channel = rand();
        $this->channel = $channel;
    }

    abstract public function set($data);

    abstract public function add($data);

    abstract public function get();

    abstract public function delete();
}