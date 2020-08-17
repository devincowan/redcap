<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!$super_user) redirect(APP_PATH_WEBROOT);


// Get list of requests
function getOpenRequests($mysql_ids=null)
{
	// First, get the minimum lvr_id (get lowest value in the past X minutes)
	if (!defined("MIN_LVR_ID")) {
		$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-30,date("s"),date("m"),date("d"),date("Y")));
		$sql = "select min(r.lvr_id) from redcap_log_view_requests r, redcap_log_view v
				where v.log_view_id = r.log_view_id and v.ts >= '$xMinAgo'";
		$q = db_query($sql);
		if ($q) {
			define("MIN_LVR_ID", db_result($q, 0));
		}
	}
	// Get request list
	$current_mysql_process = db_thread_id();
	$reqs = array();
	$sql = "select r.*, timestamp(v.ts) as ts, v.user, v.project_id, v.full_url
			from redcap_log_view_requests r left join redcap_log_view v on v.log_view_id = r.log_view_id
			where r.lvr_id >= " . MIN_LVR_ID . " and r.script_execution_time is null";
	if (is_array($mysql_ids)) {
		$sql .= " and r.mysql_process_id in (".prep_implode($mysql_ids).")";
	}
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Ignore the current MySQL process running THIS script
		if ($current_mysql_process == $row['mysql_process_id']) continue;
		// Calculate the time that this script has been running (in seconds)
		if ($row['ts'] != '') {
			$row['ts'] = strtotime(NOW) - strtotime($row['ts']);
			if ($row['ts'] < 0) $row['ts'] = 0;
		}
		// Add to array
		$reqs[$row['mysql_process_id']] = $row;
	}
	return $reqs;
}


// Get formatted timestamp for NOW
list ($nowDate, $nowTime) = explode(" ", NOW, 2);
$nowTS = (method_exists('DateTimeRC', 'format_user_datetime')) ? DateTimeRC::format_user_datetime($nowDate, 'Y-M-D_24', null) . " $nowTime" : NOW;


if (!$isAjax) {
	// Regular page display
	include 'header.php';

	?>
	<style type="text/css">
	#pagecontainer { max-width: 1200px; }
	div#mysql_dashboard { max-width:940px;width:100%; }
	</style>
	<script type="text/javascript">
	var reload = '';
	var view = 'partial';
	var kill = '';
	// Reload the dashboard table
	function reload_mysql_dashboard() {
		// Show progress circle and disable button
		$('#mysql_dash_progress').css('visibility','visible');
		$('#mysql_dash_reload_btn').prop('disabled', true).fadeTo(0,0.5);
		// AJAX call
		$.get(app_path_webroot+page, { view: view, reload: reload, kill: kill }, function(data){
			// Reload div
			$('#mysql_dashboard').html(data);
			// Reload in X seconds?
			if (isNumeric(reload)) {
				$.doTimeout('reload_dash', reload*1000, function(){ reload_mysql_dashboard(); }, true);
			} else {
				$.doTimeout('reload_dash', 0, function(){  }, true);
			}
			// Reset kill
			if (isNumeric(kill)) {
				kill = '';
			}
		});
	}
	// Open dialog to kill a query
	function kill_query(mysql_id) {
		$('#mysql_process_id').html(mysql_id);
		simpleDialog(null,null,'kill_query_dialog',350,null,'<?php print js_escape($lang['global_53']) ?>',function(){
			kill = mysql_id;
			reload_mysql_dashboard();
		},'<?php print js_escape($lang['control_center_4460']) ?>');
	}
	// Stop the page refresh
	function stop_reload() {
		$.doTimeout('reload_dash', 0, function(){  }, true);
		reload = '';
		$('#reload_dropdown').val('');
		$('#stop_reload_btn').css('visibility','hidden');
	}
	</script>
	<?php

	// Instructions
	print RCView::p(array('style'=>'margin-top:0;max-width:900px;'),
			$lang['control_center_4459'] .
			// Enable MySQL KILL ability
			($allow_kill_mysql_process ? '' :
				" " . $lang['control_center_4461'] . " " .
				RCView::span(array('style'=>'color:#888;'),
					"UPDATE redcap_config SET value = '1' WHERE field_name = 'allow_kill_mysql_process';"
				)
			)
		  );

	// Hidden dialog for killing MySQL queries
	print RCView::div(array('id'=>'kill_query_dialog', 'class'=>'simpleDialog', 'style'=>'font-weight:bold;font-size:14px;', 'title'=>$lang['control_center_4464']),
			$lang['control_center_4465'] . " " .
			RCView::span(array('id'=>'mysql_process_id', 'style'=>'color:#800000;font-weight:bold;'), '') . $lang['questionmark'] .
			RCView::div(array('style'=>'font-weight:normal;color:#555;margin-top:15px;font-size:12px;line-height:13px;'),
				$lang['control_center_4466']
			)
		  );

	// Div for dashboard
	print '<div id="mysql_dashboard">';
}

// KILL single process (if enabled)
$killed_process_id = null;
$killed_process_msg = "";
if ($allow_kill_mysql_process && isset($_GET['kill']) && is_numeric($_GET['kill']))
{
	$killed_process_id = $_GET['kill'];
	if (db_query("KILL $killed_process_id")) {
		$killed_process_msg = "<br><br><img src='".APP_PATH_IMAGES."tick.png'> <span style='font-weight:bold;color:green;'>{$lang['control_center_4467']} #$killed_process_id</span>";
	} else {
		$killed_process_msg = "<br><br><img src='".APP_PATH_IMAGES."delete.png'> <span style='font-weight:bold;color:#E00000;'>{$lang['control_center_4468']} #$killed_process_id</span>";
		// Reset process_id for later processing (since it could not be killed)
		$killed_process_id = null;
	}
}

// Get list of open requests
$openRequests = getOpenRequests();

// Query to get process list
$bgcolor = '';
$count_rows = 0;
$processes = array();
$times = array();
$openRequestsNew = array();
$strlen_appwebfull = strlen(APP_PATH_WEBROOT_FULL);
$sql  = "SHOW FULL PROCESSLIST";
$result = db_query($sql);
while ($row = db_fetch_assoc($result))
{
	// Ignore ourself running the process list
	if ($row['Info'] == $sql) continue;
	// Skip this thread if it was just killed (it sometimes still shows up in processlist for a moment)
	if ($row['Id'] == $killed_process_id) continue;
	// Format query
	$row['Info'] = str_replace(array("\r\n", "\r", "\n", "\t"), array(" ", " ", " ", " "), $row["Info"]);
	if ((!isset($_GET['view']) || $_GET['view'] != "full") && strlen($row['Info']) > 100) {
        $row['Info'] = substr($row['Info'], 0, 98)."...";
    }
	// Add to arrays
	$processes[] = $row;
	// If mysql id was not in $openRequests, then add to $openRequestsNew to check again
	$this_time = $row['Time'];
	if (!isset($openRequests[$row['Id']])) {
		$openRequestsNew[] = $row['Id'];
	} else {
		if ($openRequests[$row['Id']]['ts'] != '') {
			$this_time = $openRequests[$row['Id']]['ts'];
		}
	}
	// Add sorting time to array
	$times[] = $this_time;
}
// Sort the array by time
array_multisort($times, SORT_NUMERIC, SORT_DESC, $processes);
// print_array($processes);
// print_array($openRequests);

// Get list of open requests AGAIN after we got process list (just in case we missed some)
if (!empty($openRequestsNew)) {
	$openRequests2 = getOpenRequests($openRequestsNew);
	if (!empty($openRequests2)) {
		$openRequests = $openRequests + $openRequests2;
	}
}
unset($openRequests2, $openRequestsNew);



// Build link and drop-down html
$link = "<a style='font-weight:normal;text-decoration:underline;' href='javascript:;'
			onclick=\"view = (view == 'full' ? 'partial' : 'full'); reload_mysql_dashboard();\">"
			. (isset($_GET['view']) && $_GET['view'] == "full" ? $lang['control_center_4463'] : $lang['control_center_4462'])
	  . "</a>";
$reload_select =   "<select id='reload_dropdown' style='font-weight:normal;font-size:11px;' onchange=\"reload = this.value; reload_mysql_dashboard();\">
						<option value=''>- {$lang['control_center_4470']} -</option>
						<option value='2'  ".(isset($_GET['reload']) && $_GET['reload'] == '2'  ? 'selected' : '').">{$lang['ws_91']} 2 {$lang['control_center_4469']}</option>
						<option value='5'  ".(isset($_GET['reload']) && $_GET['reload'] == '5'  ? 'selected' : '').">{$lang['ws_91']} 5 {$lang['control_center_4469']}</option>
						<option value='10' ".(isset($_GET['reload']) && $_GET['reload'] == '10' ? 'selected' : '').">{$lang['ws_91']} 10 {$lang['control_center_4469']}</option>
						<option value='30' ".(isset($_GET['reload']) && $_GET['reload'] == '30' ? 'selected' : '').">{$lang['ws_91']} 30 {$lang['control_center_4469']}</option>
						<option value='60' ".(isset($_GET['reload']) && $_GET['reload'] == '60' ? 'selected' : '').">{$lang['ws_91']} 60 {$lang['control_center_4469']}</option>
					</select>";

// Show table of current processes running with this web userecho '<table border="0" cellpadding="5" style="margin-top:10px;table-layout:fixed;max-width:1000px;width:1000px;border:1px solid #ccc;font-family: arial,helvetica; font-size: 10pt;">';
echo '<table border="0" cellspacing="0" cellpadding="0" style="table-layout:fixed;width:100%;border:1px solid #ccc;border-bottom:0;font-family: arial,helvetica; font-size: 10pt;">';
echo '<tr style="font-weight: bold;">
		<td valign="top" style="border-bottom:1px solid #ccc;background-color:#e5e5e5;padding:5px 10px;">
			<div style="float:left;margin-top:5px;margin-left:5px;">
				<span style="font-weight:bold;font-size:16px;"><i class="fas fa-database" style="margin-left:2px;margin-right:2px;"></i> '.$lang['control_center_4457'].'</span>
				<button id="mysql_dash_reload_btn" onclick="reload_mysql_dashboard();" style="margin:0 10px 0 110px;color:#555;">
					<img src="'.APP_PATH_IMAGES.'arrow_circle_double_gray.gif" style="vertical-align:middle;">
					<span style="vertical-align:middle;">'.$lang['control_center_4471'].'</span>
				</button>
				<img id="mysql_dash_progress" src="'.APP_PATH_IMAGES.'progress_circle.gif" style="visibility:hidden;">
				<br><br>
				'."$link &nbsp; &nbsp; $reload_select".
				'<button id="stop_reload_btn" onclick="stop_reload();" style="margin-left:10px;font-size:11px;color:#800000;font-weight:normal;'.(!isset($_GET['reload']) || $_GET['reload'] == '' ? 'visibility:hidden;' : '').'">'.$lang['control_center_4483'].'</button>'.
				$killed_process_msg.'
			</div>
			<div style="float:left;margin-left:30px;">
				<table align="center" cellpadding=0 cellspacing=1 style="">
					<tr><td>'.$lang['control_center_4472'].'</td><td style="color:#C00000;padding-left:10px;font-family:tahoma;font-weight:normal;">'.$hostname.'</td></tr>
					<tr><td>'.$lang['control_center_4473'].'</td><td style="color:#C00000;padding-left:10px;font-family:tahoma;font-weight:normal;">'.$db.'</td></tr>
					<tr><td>'.$lang['control_center_4474'].'</td><td style="color:#000066;padding-left:10px;font-family:tahoma;font-weight:normal;">'.$nowTS.'</td></tr>
					<tr><td>'.$lang['control_center_4484'].'</td><td style="color:#222;padding-left:10px;font-family:tahoma;font-weight:normal;">'.count($processes).'</td></tr>
				</table>
			</div>
			<div class="clear"></div>
		</td>
	  </tr>';
echo '</table>';
echo '<table border="0" cellspacing="0" cellpadding="0" style="table-layout:fixed;width:100%;border:1px solid #ccc;border-top:0;font-family: arial,helvetica; font-size: 10pt;">';
echo '<tr style="font-weight: bold;">
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;font-size:11px;width:80px;text-align:center;">MySQL<br>'.$lang['control_center_4480'].'</td>
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;font-size:11px;width:55px;text-align:center;">PHP<br>'.$lang['control_center_4480'].'</td>
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;font-size:11px;width:205px;">
			'.$lang['control_center_4475'].'
		</td>
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;text-align:center;width:50px;font-size:11px;">
			'.$lang['control_center_4476'].'<div style="font-weight:normal;font-size:10px;">'.$lang['control_center_4482'].'</div></td>
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;text-align:center;width:50px;font-size:11px;">
			'.$lang['control_center_4477'].'<div style="font-weight:normal;font-size:10px;">'.$lang['control_center_4482'].'</div></td>
		<td style="background-color:#fff;border-right:1px solid #eee;padding:5px;width:40px;text-align:center;font-size:11px;">'.$lang['control_center_4478'].'</td>
		<td style="background-color:#fff;padding:5px;max-width:340px;">'.$lang['control_center_4479'].'</td>
	  </tr>';

// Add truncation limit for query length
// Now loop and render the rows
foreach ($processes as $row)
{
	// Set bg color
	$bgcolor = ($bgcolor == '#F2F2F2') ? '#FFFFFF' : '#F2F2F2';

	// Add REDCap full URL info, if we have it for this process
	if (isset($openRequests[$row['Id']])) {
		$full_url = $openRequests[$row['Id']]['full_url'];
		if ($full_url == APP_PATH_WEBROOT_FULL) {
			$pos_version = false;
		} elseif (substr($full_url, 0, $strlen_appwebfull) == APP_PATH_WEBROOT_FULL) {
			$pos_version = $strlen_appwebfull-1;
		} else {
			$pos_version = strpos($full_url, "/redcap_v{$redcap_version}/");
		}
		$display_url = ($pos_version !== false) ? substr($full_url, $pos_version) : $full_url;
		if (!$openRequests[$row['Id']]['is_ajax']) {
			$display_url = "<a style='color:#000;font-size:11px;line-height:12px;' href='$full_url' target='_blank'>$display_url</a>";
		}
		$row["Host"] = "<div style='margin:6px 0 2px;color:#777;'>
							$display_url
						 </div>";
	} else {
		$row["Host"] = '';
	}

	echo   '<tr bgcolor="'.$bgcolor.'">
				<td valign="top" style="padding:3px;width:80px;font-size:11px;text-align:center;">
					'.$row["Id"].
					(!$allow_kill_mysql_process ? '' :
						'&nbsp;&nbsp;&nbsp;<a style="margin:0 1px;text-decoration:underline;" href="javascript:;" onclick="kill_query('.$row["Id"].');">'.$lang['control_center_4460'].'</a>').'
				</td>
				<td valign="top" style="width:55px;font-size:11px;padding:3px;text-align:center;">' .
					(isset($openRequests[$row['Id']]) ? $openRequests[$row['Id']]['php_process_id'] : "") .
				'</td>
				<td valign="top" style="color:#666;width:205px;font-size:11px;padding:3px;word-wrap:break-word;">' .
					((isset($openRequests[$row['Id']]) && is_numeric($openRequests[$row['Id']]['project_id']))
						? $lang['control_center_4481'].$lang['colon'].' <a style="font-size:11px;color:#C00000;text-decoration:underline;" href="'.APP_PATH_WEBROOT.'index.php?pid='.$openRequests[$row['Id']]['project_id'].'" target="_blank">'.$openRequests[$row['Id']]['project_id'].'</a>'
						: "") .
					((isset($openRequests[$row['Id']]) && $openRequests[$row['Id']]['user'] != "")
						? ((isset($openRequests[$row['Id']]) && is_numeric($openRequests[$row['Id']]['project_id'])) ? ',&nbsp;&nbsp;' : '') .
							$lang['global_17'].$lang['colon'].' '.
							($openRequests[$row['Id']]['user'] == '[survey respondent]' ? '<span style="color:#333;">'.$openRequests[$row['Id']]['user'].'</span>' : '<a style="font-size:11px;color:#000066;text-decoration:underline;" href="'.APP_PATH_WEBROOT.'ControlCenter/view_users.php?username='.$openRequests[$row['Id']]['user'].'#view_user_div" target="_blank">'.$openRequests[$row['Id']]['user'].'</a>')
						: "") .
					$row["Host"] .
				'</td>
				<td valign="top" style="padding:3px;width:50px;text-align:center;">'.(isset($openRequests[$row['Id']]) ? $openRequests[$row['Id']]['ts'] : '').'</td>
				<td valign="top" style="padding:3px;width:50px;text-align:center;">'.$row["Time"].'</td>
				<td valign="top" class="wrap" style="width:40px;padding:3px 5px;color:#777;font-size:11px;word-wrap:break-word;">'.$row["State"].'</td>
				<td valign="top" style="padding:3px;font-size:11px;width:340px;line-height:12px;">
					<div style="'.($_GET['view'] == 'full' ? '' : 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;').'">'.$row["Info"].'</div>
				</td>
			</tr>';
}
if (empty($processes)) {
	echo '<tr bgcolor="#F2F2F2"><td style="font-size:15px;padding:15px;color:#800000;padding-left:60px;" NOWRAP colspan="7">'.$lang['control_center_4458'].'</td></tr>';
}
echo '</table>';


if (!$isAjax) {
	print "</div>";
	include 'footer.php';
}