<?php
namespace REDCap\SSE;

use Session;

class EventEmitter
{
    /**
     * store data using file
     */
    const STORE_FILE = "1";
    /**
     * store data using Memcached
     */
    const STORE_MEMCACHED = "2";

    /**
     * storage strategy
     *
     * @var StorageInterface
     */
    private $store;

    /**
     * create a new Event Emitter
     * an event emitte sends server events
     */
    public function __construct($channel=null)
    {        
        $this->channel = $channel;

        if (method_exists('\Memcached', 'addServer')) {
            $this->setStore(self::STORE_MEMCACHED);
        } else {
            // use FileStorage if Memcached is not available
            $this->setStore(self::STORE_FILE);
        }
        
        // $this->setStore(self::STORE_FILE); // force file storage for events
    }

    private function setStore($storage_type)
    {
        switch ($storage_type) {
            case self::STORE_MEMCACHED:
                $this->store = new MemcachedStorage($this->channel);
                break;
            case self::STORE_FILE:
                $root_path = defined('APP_PATH_TEMP') ? APP_PATH_TEMP.'SSE'.DIRECTORY_SEPARATOR : null; 
                $this->store = new FileStorage($this->channel, $root_path);
                break;
            default:
                throw new \Exception("Error: no store defined", 1);
                break;
        }
    }

    /**
     * send a message
     *
     * @param string $data data
     * @param integer $retry milliseconds
     * @param string $event set a custom event name
     * @return string
     */
    public function getMessage($id, $data, $event='message', $retry=1000)
    {
        $lines = array(
            sprintf("id: %s", $id),
            sprintf("event: %s", $event),
            sprintf("data: %s", $data),
            sprintf("retry: %u", $retry),
            PHP_EOL, //extra space for the final line
        );
        $text = implode(PHP_EOL, $lines);
        return $text;
    }

    /**
     * get stored event
     *
     * @return ServerSentEvent
     */
    public function getEvent()
    {
        $data = $this->store->get();
        $lines = explode(PHP_EOL, $data);
        $data = json_decode(array_pop($lines));
        if(empty($data)) {
            $data = new ServerSentEvent(null, 'ping');
        }
        
        return $data;
    }

    /**
     * set the event
     *
     * @param string $data event data
     * @param string $type event type
     * @return mixed data written to storage
     */
    public function storeEvent($data, $type=null)
    {
        $sse = new ServerSentEvent($data, $type);
        $this->store->add(json_encode($sse));
    }

    /**
     * get the last event id sent from the server
     *
     * @return string
     */
    public function getLastEventID()
    {
        $lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);
        if ($lastEventId == 0) {
            $lastEventId = floatval(isset($_GET["lastEventId"]) ? $_GET["lastEventId"] : 0);
        }
        return $lastEventId;
    }

    /**
     * print the stored event
     *
     * @return void
     */
    public function printEvent()
    {
        $event = $this->getEvent();
        $data = isset($event) ? $event : (object)array();

        $this->printJSON($data);
    }


    /**
     * print a JSON response
     *
     * @param mixed $response
     * @param integer $status_code
     * @return void
     */
    private function printJSON($response, $status_code=200)
	{
		http_response_code($status_code); // set the status header
		header('Content-Type: application/json');
		print json_encode_rc( $response );
		exit;
	}

    /**
     * start a stream of server events.
     * also:
     *  - set correct headers
     *  - disable buffering
     *  - disable zlib compression
     * 
     * Reference:
     * https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events
     * 
     * @param integer $interval microseconds
     *
     * @return void
     */
    public function startStream($interval=1000000) //1000*1000
    {
        // make sessions read-only, or will lock everywhere 
		Session::init();
        session_write_close();

        // disable the server-level output buffer 
        ob_implicit_flush();

        // disable default disconnect checks
        ignore_user_abort(true);

        // set headers
        header('X-Accel-Buffering: no');
        // Explicitly disable caching so Varnish and other upstreams won't cache.
        header("Cache-Control: no-cache, must-revalidate");
        header("Access-Control-Allow-Origin: *");
        // You cannot use zlib output compression along ob_ output handler.
        // See the php docs on zlib.output_compression
        ini_set('zlib.output_compression', 'off');
        header('Content-Type: text/event-stream');

        $lastEventID = $this->getLastEventID();
        // here you will want to get the latest event id you have created on the server, but for now we will increment and force an update
        $eventID = $lastEventID+1;

        while(true)
        {
            // exit if the connection is aborted
            if ( connection_aborted() ) exit;
            if (connection_status() != CONNECTION_NORMAL) {
                ob_end_flush();
                break;
            }
            
            $event = $this->getEvent();

            $message = $this->getMessage($eventID++, json_encode($event->data), $event->type);

            ob_start();
            echo $message;
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
            // ob_flush();
            
            usleep($interval);
        }
    }

}