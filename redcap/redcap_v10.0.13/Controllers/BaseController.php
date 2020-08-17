<?php

class BaseController
{

    /**
     * expose private functions with specific request methods
     * the BaseController __call magic method checks if a route is valid
     *
     * @var array function_name => request_method
     */
    protected $routes = array();

    /**
     * method used in the request 
     *
     * @var string
     */
    protected $request_method;
    
	function __construct()
    {
        $this->request_method = $_SERVER['REQUEST_METHOD'];
    }
    
    /**
     * before calling a route check if is a valid route and if the method is allowed
     * the routes are functions not visible in the context (private or protected)
     *
     * @param string $method
     * @param array $arguments
     * @return mixed call a controller function
     */
    public function __call($method, $arguments=array()) {
        if(method_exists($this, $method)) {
            $methods = array_keys($this->routes);
            if( !in_array($method, $methods) ) return $this->notFound();
            
            $allowed_request_methods = $this->routes[$method];
            if( !in_array($this->request_method, $allowed_request_methods) ) return $this->notAllowed();

            $rm = new ReflectionMethod($this, $method);
            if (!$rm->isPublic()) {
                $rm->setAccessible(true);
            }
            return $rm->invokeArgs($this, $arguments);
            // return call_user_func_array(array($this, $method), $arguments);
        }
    }

	/**
	 *  CORS-compliant method.
	 *  It will allow any GET, POST, OPTIONS, PUT, PATCH, HEAD requests from any origin.
	 *
	 *  In a production environment, you probably want to be more restrictive.
	 *  For more read:
	 *
	 *  - https://developer.mozilla.org/en/HTTP_access_control
	 *  - http://www.w3.org/TR/cors/
	 *
	 */
	protected function enableCORS()
	{

		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			// Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
			// you want to allow, and if so:
			header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}
		
		// Access-Control headers are received during OPTIONS requests
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
			header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, HEAD, DELETE");
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
			header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
			
			exit(0);
		}else {
			header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, HEAD, DELETE");
		}

		// echo "You have CORS!";
	}

	function test()
	{
		$response = array(
			"message" => "this is just a test",
		);
		$this->printJSON($response);
	}

	// error 404
	function notFound()
	{
		$code = 404;
		$response = array(
			"error" => true,
			"message" => "page not found",
		);
		$this->printJSON($response, $code);
	}

	// error 405
	function notAllowed()
	{
		$code = 405;
		$response = array(
			"error" => true,
			"message" => "method not allowed",
		);
		$this->printJSON($response, $code);
    }

	// echo a JSON response and exit
	public function printJSON($response, $status_code=200)
	{
		return HttpClient::printJSON($response, $status_code);
	}

}