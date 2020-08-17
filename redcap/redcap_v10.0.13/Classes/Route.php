<?php



/**
 * Route Class
 */
class Route
{
	// The actual route (e.g., "Class/Method")
	private $route = null;
	private $routeClass = null;
	private $routeMethod = null;
	
	// Allowlist all non-project routes from query string (&route=Class:Method)
	private $validRoutes = array(
		"SendItController:upload",
		"SendItController:download",
		"ControlCenterController:oneClickUpgrade",
		"ControlCenterController:executeUpgradeSQL",
		"ControlCenterController:autoFixTables",
		"ControlCenterController:hideEasyUpgrade",
		"ControlCenterController:getFhirStringIdentifiers",
		// Mapping Helper
		"FhirMappingHelperController:index",
		// DataMart
		"DataMartController:revisions",
		"DataMartController:getUser",
		"DataMartController:getSettings",
		"DataMartController:addRevision",
		"DataMartController:runRevision",
		"DataMartController:approveRevision",
		"DataMartController:sourceFields",
		"DataMartController:importRevision",
		// FhirStats
		"FhirStatsController:index",
		"FhirStatsController:export",
	);
	
	// Initialize routing if needed
	public function __construct($applyRoute=true)
	{
		// Determine if route exists and set it
		$this->set();
		// Apply it?
		if (!$applyRoute) return;
		// Check user permissions for this route
		$this->checkRights();
		// Apply routing
		$this->apply();
	}
	
	// Routing to controller: Check "route" param in query string (if exists)
	private function set()
	{
		// Look for param
		if (!isset($_GET['route']) || empty($_GET['route'])) return;
		// Validate param
		list ($class, $method) = explode(":", $_GET['route'], 2);
		// Remove invalid characters
		$class = preg_replace("/[^0-9a-zA-Z-_]/", "", $class);
		$method = preg_replace("/[^0-9a-zA-Z-_]/", "", $method);
		if (empty($class) || empty($method)) return;		
		// Set variables
		$this->route = "$class:$method";
		$this->routeClass = $class;
		$this->routeMethod = $method;
	}
	
	// Return the "route" after determining its value
	public function get()
	{
		return $this->route;
	}
	
	// Apply routing
	private function apply()
	{
		global $isAjax, $lang;
		// If route not set, then nothing to do here
		if ($this->route == null) {
			// If not an AJAX request, then return here to just display the index php (default behavior
			if (!$isAjax) return;
			// For AJAX pages, return "ERROR"
			exit($lang['global_01']);
		}
		// Call the method dynamically
		$method = $this->routeMethod;
		$obj = new $this->routeClass();
		$obj->$method();
		// Stop here
		exit;
	}
	
	// Determine if current route is valid. Return boolean.
	private function isValidRoute()
	{
		return in_array($this->route, $this->validRoutes);
	}
	
	// Make sure this user has privileges for the given route (similar to page-level privileges)
	private function checkRights()
	{
		// If route not set, then nothing to do here
		if ($this->route == null) return;
		// Validate the route as being white-listed
		if (!isset($_GET['pid'])) {
			// For non-project pages, ensure route is valid, else redirect to REDCap Home page
			if (!$this->isValidRoute()) System::redirectHome();
		} else {
			// Get user_rights global array
			global $user_rights;
			if (!isset($user_rights) || !is_array($user_rights)) $this->route = null;
			// If route not set, then nothing to do here
			if ($this->route == null) return;
			// For project-level pages, check project-level user privileges
			$Privileges = new UserRights();
			// Determine if user has rights to this route
			if (!isset($Privileges->page_rights[$this->route])) 
			{
				// The path has not been white-listed in UserRights->page_rights, so it is not accessible
				exit("ERROR: ILLEGAL PATH! The path \"".RCView::escape($this->route)."\" could not be found in UserRights->page_rights.");
			}
			elseif (
				// If the route's privilege designation is mappable to an element in $user_rights
				isset($user_rights[$Privileges->page_rights[$this->route]])
				// If item in page_rights is set to "", then it is not restricted by certain user privileges (i.e., accessible to all users)
				&& $Privileges->page_rights[$this->route] != ""
				// Does this user have access to this route based on their $user_rights? If not (0), then reset route to disallow access to route.
				&& $user_rights[$Privileges->page_rights[$this->route]] == 0)
			{
				// Since user does not have rights to this route, reset route var so that it is ignored
				$this->route = null;
			}
		}
	}
	
}