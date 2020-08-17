<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);


// Notification messages/settings (set defaults)
$msg_sent   = "";
$msg_unsent = "";
$batch = 20;



// Send emails if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{

	// Collect all user names that are to be emailed
	$user_list = array();
	if (isset($_POST['uiids']))
	{
		// Set the From address for the emails sent
		$fromEmailTemp = 'user_email' . ((isset($_POST['emailFrom']) && $_POST['emailFrom'] > 1) ? $_POST['emailFrom'] : '');
		$fromEmail = $$fromEmailTemp;
		if (!isEmail($fromEmail)) $fromEmail = $user_email;

		// Get basic values sent
		$uiids = explode(",", $_POST['uiids']);
		$emailContents = '<html><body style="font-family:arial,helvetica;font-size:10pt;">'.decode_filter_tags($_POST['emailMessage']).'</body></html>';
		$emailSubject  = decode_filter_tags($_POST['emailSubject']);
		// Send back a filtered unique list of uiid's (to prevent email duplication)
		if (isset($_POST['action']) && $_POST['action'] == 'get_unique')
		{
			// Make sure uiid's are numeric first
			foreach ($uiids as $key=>$this_uiid) {
				if (!is_numeric($this_uiid)) {
					// Remove uiid from original if not numeric
					unset($uiids[$key]);
				}
			}
			// Get unique list of uiid's
			$sql = "select min(ui_id) as ui_id, user_email from redcap_user_information where user_email != ''
					and user_email is not null and ui_id in (" . prep_implode($uiids) . ") group by user_email";
			$q = db_query($sql);
			$unique_uiids = array();
			$useremail_list = array();
			while ($row = db_fetch_assoc($q))
			{
				if (isEmail($row['user_email'])) {
					$unique_uiids[] = $row['ui_id'];
					$useremail_list[] = $row['user_email'];
				}
			}
			// Logging
			$log_vals = "From: $fromEmail\n"
					  . "To: " . implode(", ",$useremail_list) . "\n"
					  . "Subject: $emailSubject\n"
					  . "Message:\n$emailContents";
			Logging::logEvent("","","MANAGE","",$log_vals,"Email users");
			// Send back list of uiid's
			exit(implode(",", $unique_uiids));

		}
		// Loop through set amount for this batch
		else
		{
			$i = 0;
			foreach ($uiids as $key=>$this_uiid) {
				if (is_numeric($this_uiid)) {
					// Add uiid to user_list
					$user_list[] = $this_uiid;
					// Remove uiid from original submitted list of uiid's
					unset($uiids[$key]);
				}
				$i++;
				if ($i == $batch) break;
			}
		}
	}
	else
	{
		exit("0\n1");
	}

	// Set up email to be sent
	$email = new Message ();
	$email->setFrom($fromEmail);
	if (isset($_POST['emailFrom']) && $_POST['emailFrom'] > 1) {
		$email->setFromName(""); // Add user's secondary and tertiary display name
	} else {
		$email->setFromName($GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname']);
	}
	$email->setSubject($emailSubject);
	$email->setBody($emailContents);

	// Loop through all users submitted and send email to each
	$count_sent = $count_unsent = 0;
	$useremail_list = array();
	$sql = "select user_email from redcap_user_information where ui_id in (" . prep_implode($user_list) . ")";
	$q = db_query($sql);
	while ($row = db_fetch_array($q))
	{
		// Set email recipient
		$email->setTo($row['user_email']);
		// Send email. Notify if does not send.
		if ($email->send()) {
			// Sent
			$count_sent++;
		} else {
			// Did not send
			$count_unsent++;
		}
	}
	// Send back response as: number sent / list of uiid's still to send
	print "$count_sent\n" . implode(",", $uiids);
	exit;

}





## DISPLAY PAGE
include 'header.php';

print "<h4 id='email_users_header' style='margin-top: 0;'><i class=\"fas fa-envelope\"></i> {$lang['email_users_02']}</h4>";

print  "<p>{$lang['email_users_03']} {$lang['email_users_29']}</p><br>";
?>
<script type="text/javascript">
function sendEmails()
{
	if ($('#emailMessage').val().length==0 || $('#emailSubject').val().length==0) {
		simpleDialog('<?php echo js_escape($lang['email_users_01']) ?>');
		return false;
	}
	// Collect uiid's in array
	var selEmails = new Array();
	var i = 0;
	$(".user_chk").each(function(){
		if ($(this).prop("checked")) {
			selEmails[i] = $(this).prop("name").substr(5);
			i++;
		}
	});
	if (i == 0) {
		simpleDialog('<?php echo js_escape($lang['email_users_28']) ?>');
		return false;
	}
	// First get unique list of uiid's (because some email addresses may duplicate and we shouldn't send multiple emails to same user)
	var emailMessage = $('#emailMessage').val();
	var emailSubject = $('#emailSubject').val();
	var emailFrom = $('#emailFrom option:selected').val();
	$.post(app_path_webroot+page, { uiids: selEmails.join(','), action: 'get_unique', emailFrom: emailFrom,
		emailMessage: emailMessage, emailSubject: emailSubject}, function(uiids){
		// Add total count to page
		$('#send_total').html( uiids.split(',').length );
		// Hide email form and show status form
		window.location.href = '#email_users_header';
		$('#email_form').fadeTo('slow',0.2);
		$('#email_form input,textarea').prop('disabled',true);
		$('#status_form').toggle('blind',function(){
			// Make AJAX request(s) to send emails
			sendEmailsAjax(uiids, emailMessage, emailSubject, emailFrom);
		});
	});
}
// Make AJAX request(s) to send emails
function sendEmailsAjax(uiids, emailMessage, emailSubject, emailFrom)
{
	var total_sent = parseFloat($('#send_progress').html());
	$.post(app_path_webroot+page, { uiids: uiids, emailMessage: emailMessage, emailSubject: emailSubject, emailFrom: emailFrom}, function(data){
		var response = data.split("\n");
		var new_uiids = response[1];
		var sent = parseFloat(response[0]);
		total_sent += sent;
		$('#send_progress').html(total_sent);
		if (new_uiids.length > 0) {
			// Send more emails
			sendEmailsAjax(new_uiids, emailMessage, emailSubject, emailFrom);
		} else {
			// Done sending emails
			$('#progress_done').html('<img src="'+app_path_images+'accept.png"> <font color="green">Your emails have been successfully sent!</font>');
			$('#backBtn').removeAttr('disabled');
			$('#status_form').effect('highlight',{},3000);
		}
	});
}
initTinyMCEglobal();
</script>

<!-- EMAIL SENDING STATUS: Hidden div to show after sending emails -->
<div id="status_form" style="display:none;border:1px solid #ddd;background-color:#f5f5f5;padding:10px;margin-bottom:20px;">
	<b><?php echo $lang['survey_140'] ?> <span id="send_progress">0</span> <?php echo $lang['survey_133'] ?> <span id="send_total">?</span>
	<span id="progress_done" style="padding-left:15px;"><img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif"></span></b><br>
	<br><br>
	<button id="backBtn" disabled="disabled" onclick="window.location.href = app_path_webroot+page;"><?php echo $lang['email_users_22'] ?></button>
</div>

<style type="text/css">
#email-form-table { width:100%;padding:6px;padding-top:0px; }
#email-form-table td { padding:4px; }
#user_list_table { width:100%;border:1px solid #bbb;border-top:0; }
#user_list_table td { padding:1px; }
select.x-form-text {
    width: 100%;
}
</style>

<?php

// EMAIL FORM
print  '<form name="notify" id="email_form" method="post" action="email_users.php">
		<div class="well" style="padding-top:0;">
		<table style="width:100%;"><tr>
		<td valign="top" align="left">
			<table id="email-form-table">
			<tr>
				<td style="vertical-align:middle;width:50px;padding-top:20px;"><b>'.$lang['global_37'].'</b></td>
				<td style="vertical-align:middle;padding-top:20px;color:#555;">
				'.User::emailDropDownList().'
				</td>
			</tr>
			<tr>
				<td style="vertical-align:middle;width:50px;"><b>'.$lang['global_38'].'</b></td>
				<td style="vertical-align:middle;color:#666;font-weight:bold;">['.$lang['email_users_09'].']</td>
			</tr>
			<tr>
				<td style="vertical-align:middle;width:50px;"><b>'.$lang['email_users_10'].'</b></td>
				<td style="vertical-align:middle;">
					<input type="text" class="x-form-text x-form-field" id="emailSubject" name="emailSubject" style="width:95%;max-width:400px;"
					onkeydown="if(event.keyCode == 13){return false;}" /></td>
			</tr>
			<tr>
				<td colspan="2" style="padding-top:0;">
				    <div class="text-right mb-1 mr-2">
				        <a href="javascript:;" class="fs11" onclick="textareaTestPreviewEmail(\'#emailMessage\',0,\'#emailSubject\',\'#emailFrom option:selected\');">'.$lang['design_700'].'</a>
                    </div>
					<textarea id="emailMessage" name="emailMessage" class="x-form-textarea x-form-field mceEditor" style="width:100%;height:270px;"></textarea>
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top">
					<br>
					<button class="btn btn-sm btn-primaryrc" onclick="sendEmails();return false;">'.$lang['survey_285'].'</button>
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top">
					<br>';

//Build user list table
$htmlString =  '<table id="user_list_table">
				<tr style="background-color:#255079;color:#fff;font-weight:bold;">
					<td style="width:30px;"></td>
					<td style="padding:3px;">'.$lang['global_11'].'</td>
					<td style="padding:3px;">'.$lang['email_users_12'].'</td>
					<td style="padding:3px;">'.$lang['global_33'].'</td>
				</tr>';

// Get list of all users
$sql = "select i.ui_id, trim(lower(i.username)) as username, i.user_firstname, i.user_lastname, i.user_email,
		i.user_suspended_time, if(i.user_lastactivity > i.user_lastlogin, i.user_lastactivity, i.user_lastlogin) as user_lastactivity,
		if (a.username is null, 0, 1) as table_based_user
		from redcap_user_information i left join redcap_auth a on a.username = i.username
		where i.username != '' and i.display_on_email_users = 1 order by trim(lower(i.username))";
$q = db_query($sql);
$table_color = "#F5F5F5";
$id_uncheck = '';
$id_check   = '';
$timeNow    = strtotime(NOW);
$time1mAgo  = $timeNow - (30*24*60*60);
$time3mAgo  = $timeNow - (91*24*60*60);
$time6mAgo  = $timeNow - (183*24*60*60);
$time12mAgo = $timeNow - (365*24*60*60);
while ($row = db_fetch_assoc($q))
{
	$table_color = ($table_color == "#E6E4E4") ? "#F5F5F5" : "#E6E4E4";
	$htmlString .= '<tr style="background-color:'.$table_color.'">';
	// If user has no email address OR is suspended, then exclude from emailing
	if ($row['user_email'] == "" || $row['user_suspended_time'] != "")
	{
		$htmlString .= '<td><input type="checkbox" style="visibility:hidden;"></td>
						<td><b>'.$row['username'].'</b></td>
						<td>'.$row['user_firstname'].' '.$row['user_lastname'].'</td>';
		if ($row['user_suspended_time'] != "") {
			$htmlString .= '<td style="color:#800000;font-size:11px"><i>'.$lang['email_users_21'].'</i></td>';
		} else {
			$htmlString .= '<td style="color:#666;font-size:11px"><i>'.$lang['email_users_14'].'</i></td>';
		}
	}
	// If user has email and is NOT suspended
	else
	{
		// Determine if user has been active at all or if in past 1, 6, and 12 months
		if ($row['user_lastactivity'] == "") {
			$active_class = "user_chk_unactive";
		} else {
			$active_class = "user_chk_active";
			// Check their last activity time
			$timeLastActivity = strtotime($row['user_lastactivity']);
			if ($timeLastActivity >= $time1mAgo) {
				$active_class .= " user_chk_active1m";
			}
			if ($timeLastActivity >= $time3mAgo) {
				$active_class .= " user_chk_active3m";
			}
			if ($timeLastActivity >= $time6mAgo) {
				$active_class .= " user_chk_active6m";
			}
			if ($timeLastActivity >= $time12mAgo) {
				$active_class .= " user_chk_active12m";
			}
		}
		// Set flag if they're a table-based user
		$table_user_class = ($row['table_based_user']) ? 'user_chk_table' : '';
		// Output row
		$htmlString .= '<td style="text-align:center;"><input class="user_chk '.$active_class.' '.$table_user_class.'" type="checkbox" name="uiid_'.$row['ui_id'].'"></td>
						<td><b>'.$row['username'].'</b></td>
						<td>'.RCView::escape($row['user_firstname']).' '.RCView::escape($row['user_lastname']).'</td>
						<td><a href="mailto:'.$row['user_email'].'" style="font-size:11px">'.$row['user_email'].'</a></td>';
	}
	$htmlString .= '</tr>';
}
$htmlString .= '</table>';


// Begin table and links to check all users. <AAF Modification>
print  '<div style="border-bottom:1px solid #AAAAAA;padding-top:5px;padding-bottom:2px;text-align:left;">
			<div style="float:left;font-weight:bold;font-size:15px;">'.$lang['email_users_15'].'</div>
			<div style="float:right;color:#666;font-size:11px;padding:3px 3px 0 0;">'.$lang['email_users_30'].'</div>
			<div class="clear"></div>

			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",true);\'>'.$lang['email_users_17'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);\'>'.$lang['email_users_18'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_active").prop("checked",true);\'>'.$lang['email_users_19'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_unactive").prop("checked",true);\'>'.$lang['email_users_20'].'</a><br/>

			'.($auth_meth == 'ldap_table' || strpos($auth_meth,'aaf')>-1
				? '<span style="font-size:11px;">'.$lang['email_users_33'].'</span>&nbsp;
				   <a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_table").prop("checked",true);\'>'.$lang['email_users_31'].'</a> &nbsp;|&nbsp;
				   <a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",true);$(".user_chk_table").prop("checked",false);\'>'.$lang['email_users_32'].'</a><br/>'
				: ''
			).'

			<span style="font-size:11px;">'.$lang['email_users_23'].'</span>&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_active1m").prop("checked",true);\'>'.$lang['email_users_24'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_active3m").prop("checked",true);\'>'.$lang['email_users_27'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_active6m").prop("checked",true);\'>'.$lang['email_users_25'].'</a> &nbsp;|&nbsp;
			<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$(".user_chk").prop("checked",false);$(".user_chk_active12m").prop("checked",true);\'>'.$lang['email_users_26'].'</a>
		</div>';

print $htmlString;

print '			<br></td>
			</tr>';

//Show "send" button again if more than 50 users
if (db_num_rows($q) > 50)
{
	print  '<tr>
				<td colspan="2" valign="top">
					<br>
					<input type="button" name="submit" value="'.js_escape2($lang['survey_285']).'" onclick="sendEmails();return false;">
					<br><br>
				</td>
			</tr>';
}

print '		</table>
		</td>
		</tr></table>
		</div>
		</form><br><br>';

include 'footer.php';
