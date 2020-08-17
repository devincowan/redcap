<?php


/**
 * UIState Class
 * Contains methods used for remembering the state of UI components (e.g., collapsed tables in projects)
 */
class UIState
{	
	// Return a value from the UI state config. Return null if key doesn't exist. (e.g., $object = 'sidebar')
	public static function getUIStateValue($project_id, $object, $key)
	{
		// Return value if exists, else return null.
		return (isset($GLOBALS['ui_state'][$project_id][$object][$key]) ? $GLOBALS['ui_state'][$project_id][$object][$key] : null);
	}
	
	// Save a value in the UI state config (e.g., $object = 'sidebar')
	public static function saveUIStateValue($project_id, $object, $key, $value)
	{
		// Add value to array
		$GLOBALS['ui_state'][$project_id][$object][$key] = $value;
		// Save state with desired expiration
		self::saveUIState();
	}
	
	// Remove key-value from the UI state config
	public static function removeUIStateValue($project_id, $object, $key)
	{
		// Remove value
		unset($GLOBALS['ui_state'][$project_id][$object][$key]);
		// Save state with desired expiration
		self::saveUIState();
	}
	
	// Save the UI state by passing the array of values
	private static function saveUIState()
	{
		// Add value to global variable
		$ui_serialized = (empty($GLOBALS['ui_state']) || !is_array($GLOBALS['ui_state'])) ? "" : serialize($GLOBALS['ui_state']);
		// Update in user information table in serialized format
		$sql = "update redcap_user_information set ui_state = ".checkNull($ui_serialized)." 
				where username = '".db_escape(USERID)."'";
		db_query($sql);
	}

	// Return boolean of the collapsed state of a project menu item (TRUE = collapsed, FALSE = visible)
	public static function getMenuCollapseState($project_id, $menu_id)
	{
		return (self::getUIStateValue($project_id, 'sidebar', $menu_id) !== null);
	}
	
	// Determine collapsed state of tables on Record Home page (maybe elsewhere). Return true if collapsed, false if displayed.
	public static function isTableCollapsed($project_id, $tableid)
	{
		return (self::getUIStateValue($project_id, 'record_home', $tableid) == '1');
	}
	
	// Determine collapsed state of event columns on Record Home page. Return true if collapsed, false if displayed.
	public static function isEventColumnCollapsed($project_id, $eventid)
	{
		return (self::getUIStateValue($project_id, 'record_home', 'repeat_event-'.$eventid) == '1');
	}
}