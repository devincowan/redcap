<?php


include 'header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

if (isset($_POST['start_date']) && $_POST['start_date']) {
	$start_date = (int)str_replace('-', '', DateTimeRC::format_ts_to_ymd($_POST['start_date']));
} else {
	$start_date = (int)str_replace('-', '', TODAY);
	$_POST['start_date'] = DateTimeRC::format_ts_from_ymd(TODAY);
}

if (isset($_POST['end_date']) && $_POST['end_date']) {
	$end_date = (int)str_replace('-', '', DateTimeRC::format_ts_to_ymd($_POST['end_date']));
} else {
	$end_date = (int)str_replace('-', '', TODAY);
	$_POST['end_date'] = DateTimeRC::format_ts_from_ymd(TODAY);
}

?>
<script type="text/javascript">
$(function(){
	projTitlePopup();
	// Append the project title pop-up action onto the onclick event for the table headers
	$('div#todayActivityTable .hDivBox table tr th').each(function(){
		var onclick = $(this).attr('onclick') + "projTitlePopup();";
		$(this).attr('onclick',onclick);
	});
	// Setu up datepickers on start/end date fields
	var dates = $( "#start-date, #end-date" ).datepicker({
		defaultDate: "+0w",
		changeMonth: true,
		numberOfMonths: 1,
		dateFormat: user_date_format_jquery,
		onSelect: function( selectedDate ) {
			var option = this.id == "start-date" ? "minDate" : "maxDate",
				instance = $( this ).data( "datepicker" ),
				date = $.datepicker.parseDate(
					instance.settings.dateFormat ||
					$.datepicker._defaults.dateFormat,
					selectedDate, instance.settings );
			dates.not( this ).datepicker( "option", option, date );
		}
	});
});
// Enable the project title pop-ups on mouseover
function projTitlePopup() {
    $('.gearsm').hover(function() {
        var e = $(this);
        $('.gearsm').not(this).popover('hide');
        e.off('hover');
        $.get(app_path_webroot+'ControlCenter/get_project_name.php?pid='+$(this).attr('pid'),{ }, function(data){
            e.popover({ content: data, html: true, placement: 'right' }).popover('show');
        });
    }, function() {
        $('.gearsm').popover('hide');
        $(this).off('hover');
    });
    $(".gearsm").click(function(){
        var url = app_path_webroot+'index.php?pid='+$(this).attr('pid');
        window.open(url,'_blank','toolbar=1,location=1,directories=1,status=1,menubar=1,scrollbars=1,resizable=1');
    });
}
</script>
<?php

// Page title
echo '<h4 style="margin-top: 0;"><i class="fas fa-receipt" style="margin-left:2px;margin-right:2px;"></i> '. $lang['control_center_206'].'</h4>';
// Hidden pop-up div to display project name from mouseover
print 	"<div id='tooltip' class='tooltip1' style='width:100%;max-width:400px;padding:7px;'>
			<b>{$lang['control_center_107']}&nbsp;</b>
			<span id='titleload'><img src='".APP_PATH_IMAGES."progress_circle.gif'> {$lang['scheduling_20']}</span>
		</div>";
// Start/end date selection
print '<div style="margin: 0px 25px 15px 0px;vertical-align:middle;">';
print '<form method="post" action="'.PAGE_FULL.'">';
print $lang['control_center_207'];
print ' <input type="text" id="start-date" name="start_date" value="'.RCView::escape($_POST['start_date']).'" class="x-form-text x-form-field" style="width:90px;" /> &nbsp; ';
print $lang['control_center_208'];
print ' <input type="text" id="end-date" name="end_date" value="'.RCView::escape($_POST['end_date']).'" class="x-form-text x-form-field" style="width:90px;" />';
print ' &nbsp; <input type="submit" name="Search" value="Display" /></form>';
print '</div>';

// First, get list of all project_id's (in case some projects have been deleted, we don't need to show the gear icon)
$project_ids = array();
$sql = "select project_id from redcap_projects";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	$project_ids[$row['project_id']] = true;
}


/**
 * All User Activity for the date range selected
 */
$num_activity_today = 0;
$activityToday = array();
foreach (Logging::getLogEventTables() as $logEventTable)
{
	$sql = "SELECT ts, description, project_id, user FROM $logEventTable
             WHERE ts >= " . $start_date . "000000 AND ts <= " . $end_date . "235959";
	$q = db_query($sql);
	while ($row = db_fetch_array($q)) {
		// Ignore auto-calc logging since they are just duplicates
		if (strpos($row['description'], "(Auto calculation)") !== false) continue;
		// Set array key as timestamp + extra digits for padding for simultaneous events
		$key = strtotime($row['ts']) * 100;
		// Ensure that we don't overwrite existing logged events
		while (isset($activityToday[$key . ""])) $key++;
		// Add to array
		$activityToday[$key . ""] = array((!isset($project_ids[$row['project_id']]) ? "" : "<div pid='{$row['project_id']}' class='gearsm'>&nbsp;&nbsp;</div>"),
			DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts'])),
			$row['user'],
			RCView::div(array('class' => 'wrap'), $row['description'])
		);
		// Increment row
		$num_activity_today++;
	}
}
if ($num_activity_today == 0) {
	$activityToday[] = array('','',$lang['dashboard_02']);
}
// Sort by timestamp
krsort($activityToday);
$height = ($num_activity_today >= 26 ? 570 : "auto");
$col_widths_headers = array(
						array(10, ''),
						array(120, $lang['global_13']),
						array(130, $lang['global_17']),
						array(345, $lang['dashboard_21'])
					);

if ($start_date != $end_date) {
	$the_date = $_POST['start_date'] . " - ".$_POST['end_date'];
} else {
	$the_date = $_POST['start_date'];
}

if ($start_date == $end_date && $end_date == date('Ymd')) {
	$the_date = $lang['dashboard_32'];
}

// Render the table
renderGrid("todayActivityTable", "{$lang['dashboard_03']} {$the_date}<span style='font-size:11px;margin-left:7px;'>(".User::number_format_user($num_activity_today, 0)." {$lang['dashboard_04']})", 650, $height, $col_widths_headers, $activityToday);
print "<br />";







/**
 * Daily aggregate table
 */
$aggrToday = array();
$aggrTodayData = array();
foreach (Logging::getLogEventTables() as $logEventTable)
{
	$sql = "SELECT description, count(1) as count FROM $logEventTable
            WHERE ts >= " . $start_date . "000000 AND ts <= " . $end_date . "235959
            GROUP BY description";
	$q = db_query($sql);
	while ($row = db_fetch_array($q)) {
		// Ignore auto-calc logging since they are just duplicates
		if (strpos($row['description'], "(Auto calculation)") !== false) continue;
		// Add to array
        if (isset($aggrTodayData[$row['description']])) {
			$aggrTodayData[$row['description']] += $row['count'];
        } else {
            $aggrTodayData[$row['description']] = $row['count'];
		}
	}
}
// Order by count desc
arsort($aggrTodayData);
foreach ($aggrTodayData as $description=>$count) {
	$aggrToday[] = array(User::number_format_user($count), $description);
}
if (empty($aggrTodayData)) {
	$aggrToday[] = array('',$lang['dashboard_02']);
}
$height = (count($aggrToday) >= 18) ? 420 : "auto";
$col_widths_headers = array(
						array(60, $lang['dashboard_23'], "center", "int"),
						array(566, RCView::div(array('class'=>'wrap'), $lang['dashboard_21']))
					);
renderGrid("aggr_table", $lang['dashboard_91'] ." ". $the_date , 650, $height, $col_widths_headers, $aggrToday);



include 'footer.php';