<?php
namespace REDCap\SSE;

class MemcachedStorage extends BaseStorage
{
    /**
     * Undocumented variable
     *
     * @var \Memcached
     */
    private $cache;

    public function __construct($channel)
    {
        parent::__construct($channel);

        $this->cache = new \Memcached();
        $this->cache->addServer("127.0.0.1", 11211);
    }

    /**
     * delete data
     *
     * @return boolean
     */
    public function delete()
    {
        return $this->cache->delete($this->channel);
    }

    /**
     * get data
     *
     * @return string
     */
    public function get()
    {
        $data = $this->cache->get($this->channel);
        return $data;
    }

    /**
     * store data
     *
     * @param string $data
     * @return boolean
     */
    public function set($data)
    {
        return $this->cache->set($this->channel, $data, self::EXPIRATION_TIME);
    }

    /**
     * store data
     *
     * @param string $data
     * @return boolean
     */
    public function add($data)
    {
        $previous_data = $this->get();
        if(!empty($previous_data)) $data = $previous_data.PHP_EOL.$data;
        return $this->cache->set($this->channel, $data);
    }
}