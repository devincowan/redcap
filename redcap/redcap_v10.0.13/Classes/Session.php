<?php

/**
 * SESSION HANDLING: DATABASE SESSION STORAGE
 * Adjust PHP session configuration to store sessions in database instead of as file on web server
 */
class Session
{
	const cookie_samesite = 'None';

	public static function start($savePath, $sessionName)
	{
		return true;
	}

	public static function end()
	{
		return true;
	}

	public static function read($key)
	{
		// Force session_id to only have 32 characters (for compatibility issues)
		$key = db_escape(substr($key, 0, 32));

		$sql = "SELECT session_data FROM redcap_sessions WHERE session_id = '$key' AND session_expiration > '" . NOW . "'";
		$sth = db_query($sql);
		return ($sth ? (string)db_result($sth, 0) : $sth);
	}

	public static function write($key, $val)
	{
		// Force session_id to only have 32 characters (for compatibility issues)
		$key = db_escape(substr($key, 0, 32));
		$val = db_escape($val);

		if (session_name() == "survey") {
			// For surveys, set expiration time as 1 day (i.e. arbitrary long time)
			$expiration = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+1,date("Y")));
		} else {
			// For non-survey pages (all else), set expiration time using value defined on System Config page
			global $autologout_timer;
			$expiration = date("Y-m-d H:i:s", mktime(date("H"),date("i")+$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
		}
		
		// If PREVENT_SESSION_EXTEND is defined, then do not update the session expiration (so it doesn't interfere with auto-logout)
		if (defined("PREVENT_SESSION_EXTEND")) {
			$sql = "UPDATE redcap_sessions SET session_data = '$val' WHERE session_id = '$key'";
		} else {
			$sql = "REPLACE INTO redcap_sessions (session_id, session_data, session_expiration) VALUES ('$key', '$val', '$expiration')";
		}
		// Return boolean on success
		return (db_query($sql) !== false);
	}

	public static function destroy($key)
	{
		// Force session_id to only have 32 characters (for compatibility issues)
		$key = substr($key, 0, 32);

		$sql = "DELETE FROM redcap_sessions WHERE session_id = '$key'";
		return (db_query($sql) !== false);
	}

	public static function gc($max_lifetime)
	{
		// Delete all sessions more than 1 day old, which is the session expiration time used by surveys (ignore the system setting $max_lifetime)
		$max_session_time = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));

		$sql = "DELETE FROM redcap_sessions WHERE session_expiration < '$max_session_time'";
		return (db_query($sql) !== false);
	}
	
	public static function writeClose()
	{
		session_write_close();
	}

	// Initialize the PHP session (and set session name, but only if specified)
	public static function init($name=null)
	{
		// Session has started already, then do nothing and return true
		if (session_id() != '') return true;
		// Set session name? If not specified, the session name and associated cookie name will be PHPSESSID
		if ($name != null) {
			session_name($name);
		} else {
			$name = "PHPSESSID";
		}
		// Start session
		$sessionStarted = @session_start();
		// If we're on <PHP 7.3, we can't manually set samesite=None via session_set_cookie_params() but only via the Set-Cookie header,
		// so we need to manipulate the session cookie manually to add samesite=None after it has been created.
		if ($sessionStarted && version_compare(PHP_VERSION, '7.3.0', '<')) {
			// We only need to do this if the secure attribute is set for cookies, so check that
			$cookie_params = session_get_cookie_params();
			if ($cookie_params['secure'] === true) {
				// Modify the PHPSESSID cookie. This will automatically apply samesite=None to the cookie.
				self::savecookie($name, session_id(), 0, true);
			}
		}
		// Return success
		return $sessionStarted;
	}

	// Set session handlers and session cookie params
	public static function preInit()
	{
		// Set session handler functions
		session_set_save_handler('Session::start', 'Session::end', 'Session::read', 'Session::write', 'Session::destroy', 'Session::gc');

		// Set session cookie parameters to make sure that HttpOnly flag is set as TRUE for all cookies created server-side
		$cookie_params = session_get_cookie_params();
		$new_cookie_params = array(
			'lifetime' => 0,
			'path' => '/',
			'domain' => '',
			'secure' => ($cookie_params['secure']===true), // Use the server's default value for 'Secure' cookie attribute to allow it to be set to TRUE via PHP.INI
			'httponly' => true,
			'samesite' => self::cookie_samesite
		);
		if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
			// Keep samesite=None attribute?
			if (self::removeCookieSamesiteAttribute()) {
				unset($new_cookie_params['samesite']);
			}
			return session_set_cookie_params($new_cookie_params);
		} else {
			return session_set_cookie_params($new_cookie_params['lifetime'], $new_cookie_params['path'], $new_cookie_params['domain'], $new_cookie_params['secure'], $new_cookie_params['httponly']);
		}
	}

	// Store a cookie by name, value, and expiration (0=will expire when session ends)
	public static function savecookie($name, $value='', $expirationInSeconds=0, $isSessionCookie=false)
	{
		if ($name == '') return;
		$cookie_params = session_get_cookie_params();
		$new_cookie_params = array(
			'expires' => ($isSessionCookie ? '0' : time() + (int)$expirationInSeconds),
			'path' => $cookie_params['path'],
			'domain' => $cookie_params['domain'],
			'secure' => $cookie_params['secure'],
			'httponly' => $cookie_params['httponly'],
			'samesite' => self::cookie_samesite // Add this manually in case we're on <PHP 7.3.0, in which it won't be returned from session_get_cookie_params()
		);
		// Keep samesite=None attribute?
		if (self::removeCookieSamesiteAttribute()) {
			unset($new_cookie_params['samesite']);
		}
		if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
			// Set cookie using array of params for PHP 7.3.0+
			setcookie($name, $value, $new_cookie_params);
		} elseif (isset($new_cookie_params['samesite'])) {
			// Must use header method if <PHP 7.3.0 and samesite=None (this also assumes that secure=true)
			$max_age = ($isSessionCookie ? "" : "; Max-Age=$expirationInSeconds");
			$header_cookie_string = "$name=".urlencode($value)."; Path={$new_cookie_params['path']}{$max_age}; "
				. "Domain={$new_cookie_params['domain']}; SameSite={$new_cookie_params['samesite']}; Secure";
			if ($new_cookie_params['httponly'] == true) $header_cookie_string .= "; httpOnly";
			header("Set-Cookie: " . $header_cookie_string);
		} else {
			// Use legacy method to set cookie
			setcookie($name, $value, $new_cookie_params['expires'], $new_cookie_params['path'], $new_cookie_params['domain'], $new_cookie_params['secure'], $new_cookie_params['httponly']);
		}
	}

	// Delete a cookie by name
	public static function deletecookie($name)
	{
		// Set cookie's expiration to a time in the past to destroy it
		self::savecookie($name, '', 0);
		// Unset the cookie
		unset($_COOKIE[$name]);
	}

	// The the cookie "SameSite" attribute be removed from cookies and cookie params
	private static function removeCookieSamesiteAttribute()
	{
		$cookie_params = session_get_cookie_params();
		// Don't add samesite=None if cookie secure attribute !==true
		if ($cookie_params['secure'] !== true) return true;
		// Don't add samesite=None if not compatible with certain browsers
		$browser = new Browser();
		// If IE (incompatible)
		if ($browser->getBrowser() == Browser::BROWSER_IE) return true;
		// If Chrome 51-66 (incompatible)
		if ($browser->getBrowser() == Browser::BROWSER_CHROME && intval($browser->getVersion()) >= 51 && intval($browser->getVersion()) <= 66) return true;
		// If Edge <80 (incompatible)
		if ($browser->getBrowser() == Browser::BROWSER_EDGE && intval($browser->getVersion()) < 80) return true;
		// If Android UCBrowser <12.13.2 (incompatible)
		if ($browser->getBrowser() == Browser::BROWSER_UCBROWSER && $browser->getVersion() < '12.13.2') return true;
		// If Safari on MacOS 10.14 Mojave (will mistakenly set as Strict when specifying it to be None, so remove so that it applies the browser's default value)
		if ($browser->getPlatform() == Browser::PLATFORM_APPLE && $browser->getBrowser() == Browser::BROWSER_SAFARI
			&& stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X 10_14') !== false) return true;
		// If any browser on iOS 12 (will mistakenly set as Strict when specifying it to be None, so remove so that it applies the browser's default value)
		if (in_array($browser->getPlatform(), array(Browser::PLATFORM_IPAD, Browser::PLATFORM_IPOD, Browser::PLATFORM_IPHONE))
			&& stripos($_SERVER['HTTP_USER_AGENT'], ' OS 12_') !== false) return true;
		// If we got this far, then return false
		return false;
	}
}
