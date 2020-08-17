<?php

class Calendar
{
	// Return boolean if $record has at least one calendar event associated with it
	public static function recordHasCalendarEvents($record)
	{
		global $Proj;
		$sql = "select 1 from redcap_events_calendar where project_id = " . PROJECT_ID . " 
				and (event_id is null or event_id in (".prep_implode(array_keys($Proj->eventInfo)).")) 
				and record = '".db_escape($record)."' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);		
	}
	
	// Return HTML table agenda for calendar events in next $daysFromNow days (optional: limit to a specific $record)
	public static function renderUpcomingAgenda($daysFromNow=7, $record=null, $returnCountOnly=false, $showTableTitle=true)
	{
		global $lang, $user_rights, $Proj;
		if (!is_numeric($daysFromNow)) return false;
		// Exclude records not in your DDE group (if using DDE)
		$dde_sql = "";
		if ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0) {
			$dde_sql = "and record like '%--{$user_rights['double_data']}'";
		}
		// If returning single record
		$record_sql = "";
		if ($record !== null) {
			$record_sql = "and record = '".db_escape($record)."'";
		}
		// Get calendar events
		$sql = "select * from redcap_events_calendar where project_id = " . PROJECT_ID . " 
				and (event_id is null or event_id in (".prep_implode(array_keys($Proj->eventInfo)).")) and event_date >= '" . date("Y-m-d") . "' 
				and event_date <= '" . date("Y-m-d", mktime(0, 0, 0, date("m"), date("d")+$daysFromNow, date("Y"))) . "'
				" . (($user_rights['group_id'] == "") ? "" : "and group_id = " . $user_rights['group_id']) . " 
				$dde_sql $record_sql order by event_date, event_time";
		$q = db_query($sql);

		$cal_list = array();
		$num_rows = db_num_rows($q);
		
		if ($returnCountOnly) return $num_rows;

		if ($num_rows > 0) {

			while ($row = db_fetch_assoc($q))
			{
				$caldesc = "";
				// Set image to load calendar pop-up
				$popup = "<a href=\"javascript:;\" onclick=\"popupCal({$row['cal_id']},800);\">"
						 . "<img src=\"".APP_PATH_IMAGES."magnifier.png\" style=\"vertical-align:middle;\" title=\"".js_escape2($lang['scheduling_80'])."\" alt=\"".js_escape2($lang['scheduling_80'])."\"></a> ";
				// Trim notes text
				$row['notes'] = trim($row['notes']);
				// If this calendar event is tied to a project record, display record and Event
				if ($row['record'] != "") {
					$caldesc .= removeDDEending($row['record']);
				}
				if ($row['event_id'] != "") {
					$caldesc .= " (" . $Proj->eventInfo[$row['event_id']]['name_ext'] . ") ";
				}
				if ($row['group_id'] != "") {
					$caldesc .= " [" . $Proj->getGroups($row['group_id']) . "] ";
				}
				if ($row['notes'] != "") {
					if ($row['record'] != "" || $row['event_id'] != "") {
						$caldesc .= " - ";
					}
					$caldesc .= $row['notes'];
				}
				// Add to table
				$cal_list[] = array($popup, DateTimeRC::format_ts_from_ymd($row['event_time']), DateTimeRC::format_ts_from_ymd($row['event_date']), "<span class=\"notranslate\">".RCView::escape($caldesc)."</span>");
			}

		} else {

			$cal_list[] = array('', '', '', $lang['index_52']);

		}
		
		$height = (count($cal_list) < 9) ? "auto" : 220;
		$width = 500;
		$title = $instructions = $divClasses = "";
		if ($showTableTitle) {
			$divClasses = (PAGE == 'index.php') ? "" : "col-12 col-md-6";
			$title = "<div style=\"padding:0;\">
					  <span style=\"color:#800000;\"><i class=\"far fa-calendar-plus\"></i> {$lang['index_53']} &nbsp;<span style=\"font-weight:normal;\">{$lang['index_54']}</span></span></div>";
		} else {
			$instructions = RCView::div(array('style'=>'margin-bottom:10px;'), $lang['calendar_16'] . " ". RCView::b("$daysFromNow " . $lang['scheduling_25']) . $lang['period']);
		}
		$col_widths_headers = array(
								array(18, '', 'center'),
								array(50,  $lang['global_13']),
								array(70,  $lang['global_18']),
								array(316, $lang['global_20'])
							  );
		
		// Build HTML
		$html = "<div class='$divClasses'>$instructions";
		$html .= renderGrid("cal_table", $title, $width, $height, $col_widths_headers, $cal_list, true, true, false);
		$html .= "<div style='margin-top:10px;'></div></div>";
		
		return $html;
	}

	/**
	 * Retrieve logging-related info when adding/updating/deleting calendar events using the cal_id
	 */
	public static function calLogChange($cal_id) {
		if ($cal_id == "" || $cal_id == null || !is_numeric($cal_id)) return "";
		$logtext = array();
		$sql = "select c.*, (select m.descrip from redcap_events_metadata m, redcap_events_arms a where a.project_id = c.project_id
                and m.event_id = c.event_id and a.arm_id = m.arm_id) as descrip from redcap_events_calendar c where c.cal_id = $cal_id limit 1";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['record']     != "") $logtext[] = "Record: ".$row['record'];
			if ($row['descrip']    != "") $logtext[] = "Event: ".$row['descrip'];
			if ($row['event_date'] != "") $logtext[] = "Date: ".$row['event_date'];
			if ($row['event_time'] != "") $logtext[] = "Time: ".$row['event_time'];
			// Only display status change if event was scheduled (status is not listed for ad hoc events)
			if ($row['event_status'] != "" && $row['event_id'] != "") {
				switch ($row['event_status']) {
					case '0': $logtext[] = "Status: Due Date"; break;
					case '1': $logtext[] = "Status: Scheduled"; break;
					case '2': $logtext[] = "Status: Confirmed"; break;
					case '3': $logtext[] = "Status: Cancelled"; break;
					case '4': $logtext[] = "Status: No Show";
				}
			}
		}
		return implode(", ", $logtext);
	}

	/**
	 * RETRIEVE ALL CALENDAR EVENTS
	 */
	public static function getCalEvents($month, $year)
	{
		global $user_rights, $Proj;

		// Place info into arrays
		$event_info = array();
		$events = array();

		$year_month = (strlen($month) == 2) ? $year . "-" . $month : $year . "-0" . $month;
		$sql = "select * from redcap_events_metadata m right outer join redcap_events_calendar c on c.event_id = m.event_id
                where c.project_id = " . PROJECT_ID . " and YEAR(c.event_date)= {$year} and MONTH(c.event_date)= {$month}
                " . (($user_rights['group_id'] != "") ? "and c.group_id = {$user_rights['group_id']}" : "") . "
                order by c.event_date, c.event_time";
		$query_result = db_query($sql);
		$i = 0;
		while ($info = db_fetch_assoc($query_result))
		{
			$thisday = substr($info['event_date'],-2)+0;
			$events[$thisday][] = $event_id = $i;
			$event_info[$event_id]['0'] = $info['descrip'];
			$event_info[$event_id]['1'] = $info['record'];
			$event_info[$event_id]['2'] = $info['event_status'];
			$event_info[$event_id]['3'] = $info['cal_id'];
			$event_info[$event_id]['4'] = $info['notes'];
			$event_info[$event_id]['5'] = $info['event_time'];
			// Add DAG, if exists
			if ($info['group_id'] != "") {
				$event_info[$event_id]['6'] = $Proj->getGroups($info['group_id']);
			}
			$i++;
		}

		// Return the two arrays
		return array($event_info, $events);
	}
}
