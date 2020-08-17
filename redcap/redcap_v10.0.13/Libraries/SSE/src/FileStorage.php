<?php
namespace REDCap\SSE;

class FileStorage extends BaseStorage
{

    /**
     * directory where the storage files are saved
     *
     * @var string
     */
    private $storage_dir;

    /**
     * Undocumented function
     *
     * @param string $channel
     * @param string $root path to the root folder for storing events
     */
    public function __construct($channel, $root=null)
    {
        parent::__construct($channel);

        $root = isset($root) ? $root : dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR;
        $this->storage_dir =  implode('', array($root, 'storage/'));
    }

    private function createStorageFile()
    {
        $filename = $this->getStorageFilePath();
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            // create folders recursively
            mkdir($dir, 0777, true);
        }
        // create file if does not exists
        touch($filename);
    }

    /**
     * cleanup on destruct
     */
    function __destruct()
    {
        $this->cleanUp();
    }

    /**
     * get the file where the data is saved
     *
     * @return void
     */
    public function getStorageFilePath()
    {
        $filename =  $this->channel.'.txt';
        $path =  $this->storage_dir.$filename;
        return $path;
    }

    /**
     * delete data
     *
     * @return boolean
     */
    public function delete()
    {
        $filename = $this->getStorageFilePath();
        if(file_exists($filename)) unlink($filename);
        // return $this->set(null);
    }

    /**
     * delete old files
     *
     * @return void
     */
    public function cleanUp()
    {
        $files = glob($this->storage_dir."*");
        $now   = time();
        $max_age = self::EXPIRATION_TIME;

        foreach ($files as $file) {
            if (is_file($file))
            {
                if ($now - filemtime($file) >= $max_age) unlink($file);
            }
        }
    }

    /**
     * get data
     *
     * @return string
     */
    public function get()
    {
        $filename = $this->getStorageFilePath();
        if(!file_exists($filename)) return;
        $data = file_get_contents($filename);
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
        $this->createStorageFile();
        $filename = $this->getStorageFilePath();
        return file_put_contents($filename, $data, $flags=LOCK_EX);
    }

    /**
     * add data to the file
     *
     * @param string $data
     * @return boolean
     */
    public function add($data)
    {
        $previous_data = $this->get();
        if(!empty($previous_data)) $data = $previous_data.PHP_EOL.$data;
        return $this->set($data);
    }
}