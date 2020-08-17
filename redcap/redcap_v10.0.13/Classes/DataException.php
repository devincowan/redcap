<?php
/**
 * Exception that supports an array of data
 */
class DataException extends Exception {
    private $_data = array();

    /**
     * Undocumented function
     *
     * @param string $message
     * @param array $data
     * @param integer $code
     * @param Throwable $previous
     */
    public function __construct($message, $data=array(), $code = 0, $previous = null) 
    {
        $this->_data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData()
    {
        return $this->_data;
    }

}