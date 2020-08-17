<?php

/**
 * DatetimeRC
 * This class is used for processes related to viewing, saving, or converting date/times.
 */
class DateTimeRC
{
	// Default system date format
	public static $default_datetime_format_system = 'M/D/Y_12';
	// Array of all date formats
	public static $datetime_formats = array('M-D-Y_24', 'M-D-Y_12', 'M/D/Y_24', 'M/D/Y_12', 'M.D.Y_24', 'M.D.Y_12',
											'D-M-Y_24', 'D-M-Y_12', 'D/M/Y_24', 'D/M/Y_12', 'D.M.Y_24', 'D.M.Y_12',
											'Y-M-D_24', 'Y-M-D_12', 'Y/M/D_24', 'Y/M/D_12', 'Y.M.D_24', 'Y.M.D_12');
	
	// Get system default's datetime format
	public static function get_system_format_full()
	{
		global $default_datetime_format;
		// Return system format. Use value from config table, if valid.
		return (!in_array($default_datetime_format, self::$datetime_formats)) ? self::$default_datetime_format_system : $default_datetime_format;
	}

	// Get user's datetime format (e.g., M-D-Y_24, D.M.Y_12)
	public static function get_user_format_full()
	{
		global $datetime_format;
		// Set destination format
		return ($datetime_format == null ? self::get_system_format_full() : $datetime_format);
	}

	// Get user's BASE date format (e.g., MDY)
	public static function get_user_format_base()
	{
		// Get user's datetime format
		$full_format = self::get_user_format_full();
		// Get delimiter (will be 2nd character)
		$delimiter = substr($full_format, 1, 1);
		// Return the base format
		return str_replace($delimiter, '', substr($full_format, 0, 5));
	}

	// Get user's date label format (e.g., M.D.Y, D/M/Y, Y-M-D) for displaying format to users on page
	public static function get_user_format_label()
	{
		return substr(self::get_user_format_full(), 0, 5);
	}

	// Get user's date format for jQueryUI datepicker (e.g., mm.dd.yy, dd/mm/yy)
	public static function get_user_format_jquery()
	{
		// Get date format
		$date_format = self::get_user_format_label();
		// Get delimiter (will be 2nd character)
		$delimiter = substr($date_format, 1, 1);
		// Get components
		$comp1 = substr($date_format, 0, 1);
		$comp2 = substr($date_format, 2, 1);
		$comp3 = substr($date_format, 4, 1);
		// Return all components together
		return strtolower($comp1.$comp1.$delimiter.$comp2.$comp2.$delimiter.$comp3.$comp3);
	}

	// Get user's date format for PHP's date() function (e.g., m-d-Y, Y-m-d)
	public static function get_user_format_php()
	{
		// Get date format
		$date_format = self::get_user_format_label();
		// Set all to lower case except Y
		return str_replace('y', 'Y', strtolower($date_format));
	}

	// Get user's date format's delimiter character (dot, slash, or dash)
	public static function get_user_format_delimiter()
	{
		// Get date format
		$date_format = self::get_user_format_label();
		// Get delimiter (will be 2nd character)
		return substr($date_format, 1, 1);
	}


	/**
	 * FORMAT A DATE[TIME] FROM ONE FORMAT TO USER'S PREFERRED FORMAT
	 * If $return_format is provided, use it instead of user's preferred format.
	 * If $force24hour is TRUE, then force 24hr military time for time component returned.
	 */
	public static function format_user_datetime($val, $original_format, $return_format=null, $force24hour=false, $displaySeconds=false)
	{
		// Set destination format
		if ($return_format == null) $return_format = self::get_user_format_full();
		// Trim value
		$val = ($val === null) ? '' : trim($val);
		// If value is blank/null, then return it
		if ($val == '') return $val;
		// Validate $original_format
		if (!in_array($original_format, self::$datetime_formats)) {
			// Not valid, set to default system format
			$original_format = self::$default_datetime_format_system;
		}
		// If the format is not changing, then do nothin and return same value
		if ($original_format == $return_format) return $val;
		// Split the formats into date and time format components
		list ($date_return_format, $time_return_format) = explode("_", $return_format, 2);
		if ($force24hour) $time_return_format = '24';
		list ($date_original_format, $time_original_format) = explode("_", $original_format, 2);
		// Split the value into date and (maybe) time components
		if (strpos($val, ' ') !== false) {
			list ($date_val, $time_val) = explode(" ", $val, 2);
		} else {
			$time_val = '';
			$date_val = $val;
		}
		// If value is 5 or 8 characters long with a colon, then we know this is a Time value only (no date component)
		$isTimeOnly = false;
		if ((strlen($date_val) == 5 || strlen($date_val) == 8) && substr($date_val, 2, 1) == ":") {
			$time_val = $date_val;
			$isTimeOnly = true;
		}
		// Remove seconds off of time_val, if needed
		if (!$displaySeconds && strlen($time_val) > 5) $time_val = substr($time_val, 0, 5);
		// If the time needs to be converted from 24hr to 12hr format, then convert it
		if ($time_val != '' && $time_return_format == '12' && $time_original_format == '24') {
			$time_val = self::format_time($time_val, "", $displaySeconds);
		}
		// If just time component only, then return
		if ($isTimeOnly) return $time_val;
		// Get base format (DMY, YMD, MDY)
		$date_original_base_format = str_replace(array('/','-','.'), array('','',''), $date_original_format);
		$date_return_base_format = str_replace(array('/','-','.'), array('','',''), $date_return_format);
		// Get date delimiter
		$date_original_delimiter = (strpos($date_original_format, '-') !== false) ? '-' : ((strpos($date_original_format, '/') !== false) ? '/' : '.');
		$date_return_delimiter = (strpos($date_return_format, '-') !== false) ? '-' : ((strpos($date_return_format, '/') !== false) ? '/' : '.');
		// If the date needs to be converted, then convert it
		if ($date_original_base_format != $date_return_base_format) {
			$date_val = self::datetimeConvert($date_val, $date_original_base_format, $date_return_base_format);
		}
		// If the date delimiter needs to be converted, then convert it
		if ($date_original_delimiter != $date_return_delimiter) {
			$date_val = str_replace($date_original_delimiter, $date_return_delimiter, $date_val);
		}
		// Return the newly formated datetime
		return trim("$date_val $time_val");
	}


	// Convert date format from DD-MM-YYYY to YYYY-MM-DD
	public static function date_dmy2ymd($val)
	{
		$val = trim($val);
		if ($val == '') return $val;
		$delimiter = (strpos($val, '-') !== false) ? '-' : ((strpos($val, '/') !== false) ? '/' : '.');
		list ($day, $month, $year) = explode($delimiter, $val);
		return sprintf("%04d{$delimiter}%02d{$delimiter}%02d", $year, $month, $day);
	}


	// Convert date format from YYYY-MM-DD to DD-MM-YYYY
	public static function date_ymd2dmy($val)
	{
		$val = trim($val);
		if ($val == '') return $val;
		$delimiter = (strpos($val, '-') !== false) ? '-' : ((strpos($val, '/') !== false) ? '/' : '.');
		list ($year, $month, $day) = explode($delimiter, $val);
		return sprintf("%02d{$delimiter}%02d{$delimiter}%04d", $day, $month, $year);
	}


	// Convert date format from MM-DD-YYYY to YYYY-MM-DD
	public static function date_mdy2ymd($val)
	{
		$val = trim($val);
		if ($val == '') return $val;
		$delimiter = (strpos($val, '-') !== false) ? '-' : ((strpos($val, '/') !== false) ? '/' : '.');
		list ($month, $day, $year) = explode($delimiter, $val);
		return sprintf("%04d{$delimiter}%02d{$delimiter}%02d", $year, $month, $day);
	}


	// Convert date format from YYYY-MM-DD to MM-DD-YYYY
	public static function date_ymd2mdy($val)
	{
		$val = trim($val);
		if ($val == '') return $val;
		$delimiter = (strpos($val, '-') !== false) ? '-' : ((strpos($val, '/') !== false) ? '/' : '.');
		list ($year, $month, $day) = explode($delimiter, $val);
		return sprintf("%02d{$delimiter}%02d{$delimiter}%04d", $month, $day, $year);
	}


	// Convert date, datetime, or datetime_seconds value from YMD/DMY/MDY to YMD/DMY/MDY
	// Formats should be provided as ymd, dmy, or mdy (case insensitive).
	public static function datetimeConvert($val, $origFormat, $returnFormat)
	{
		global $missingDataCodes;
		// Array of possible formats
		$formats = array('ymd', 'dmy', 'mdy');
		// Trim value
		$val = trim($val);
		// If blank or missing data code, return blank
		if ($val == '' || isset($missingDataCodes[$val])) return $val;
		// Trim and make formats as lower case
		$origFormat   = strtolower(trim($origFormat));
		$returnFormat = strtolower(trim($returnFormat));
		// Make sure a correct format is given, else return False
		if (!in_array($origFormat,   $formats)) return false;
		if (!in_array($returnFormat, $formats)) return false;
		// If format not changing, then return value given
		if ($origFormat == $returnFormat) return $val;
		// Break up the value into date and (maybe) time components
		if (strpos($val, ' ') !== false) {
			list ($this_date, $this_time) = explode(" ", $val, 2);
		} else {
			$this_time = '';
			$this_date = $val;
		}
		// Convert original value to YMD first, if not already in YMD format
		if ($origFormat == 'mdy') {
			$this_date = self::date_mdy2ymd($this_date);
		} elseif ($origFormat == 'dmy') {
			$this_date = self::date_dmy2ymd($this_date);
		}
		// If returning in MDY or DMY format, then convert our date (currently in YMD) to that format.
		if ($returnFormat == 'mdy') {
			$this_date = self::date_ymd2mdy($this_date);
		} elseif ($returnFormat == 'dmy') {
			$this_date = self::date_ymd2dmy($this_date);
		}
		// Now combing date and time components, then trim, then return value
		return trim("$this_date $this_time");
	}


	// Function to reformat Time from military time to am/pm format
	public static function format_time($time, $f="", $displaySeconds=false) {
		if (strpos($time,":")) {
		    $ss = "";
		    if ($displaySeconds) {
                list($hh, $mm, $ss) = explode(":", $time);
                if ($ss == '') $ss = "00";
                $ss = ":".$ss;
            } else {
                list($hh, $mm) = explode(":", $time);
            }
			if($f == "") {
				$hh += 0;
				if ($hh > 12) {
					$hh -= 12;
					$ampm = "pm";
				} elseif ($hh == 12) {
					$ampm = "pm";
				} else {
					$ampm = "am";
				}
				return (($hh == "0") ? "12" : $hh) . ":" . $mm . $ss . $ampm;
			}else {
				return date($f, mktime($hh,$mm));
			}
		} else {
			return "";
		}
	}


	// Format YMD timestamp into user's preferred format
	public static function format_ts_from_ymd($val, $force24hour=false, $displaySeconds=false) {
		return self::format_user_datetime($val, 'Y-M-D_24', null, $force24hour, $displaySeconds);
	}


	// Format user's preferred datetime format into YMD timestamp
	public static function format_ts_to_ymd($val) {
		return self::format_user_datetime($val, self::get_user_format_full(), 'Y-M-D_24');
	}


	// Format TS value from log_event table into readable timestamp value for Excel (i.e. YYYY-MM-DD HH:MM)
	public static function format_ts_from_int_to_ymd($val) {
		return ($val == "") ? "" : substr($val, 0, 4) . "-" . substr($val, 4, 2) . "-" . substr($val, 6, 2) . " " . substr($val, 8, 2) . ":" . substr($val, 10, 2);
	}


	// Function to return day of week for date in YYYY-MM-DD format
	public static function getDay($YYYY_MM_DD) {
		if ($YYYY_MM_DD != "") {
			$start_month = substr($YYYY_MM_DD,5,2);
			$start_day 	 = substr($YYYY_MM_DD,8,2);
			$start_year	 = substr($YYYY_MM_DD,0,4);
			return date("l", mktime(0, 0, 0, $start_month, $start_day, $start_year));
		} else {
			return "";
		}
	}


	// Return array of all date/timestamp formats (to be used as options in drop-down)
	public static function getDatetimeDisplayFormatOptions()
	{
		global $lang;
		$options = array();
		// Loop through all options
		foreach (self::$datetime_formats as $option)
		{
			$val = $option;
			// Determine if 24hr or 12hr
			$time_label = (substr($option, -2) == '24') ? $lang['user_90'] : $lang['user_91'];
			// Set and format date label
			$date_label = substr($option, 0, 5);
			$date_label = str_replace(array('M', 'D', 'Y'), array('MM', 'DD', 'YYYY'), $date_label);
			// Add to array
			$options[$val] = "$date_label $time_label";
		}
		// Return options
		return $options;
	}

}