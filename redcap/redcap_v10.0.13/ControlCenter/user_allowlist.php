<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";


//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);


// Function to get current white list
function getAllowlist()
{
	$allowlist = array();
	$sql = "select l.username, i.user_firstname, i.user_lastname
			from redcap_user_allowlist l left outer join redcap_user_information i
			on l.username = i.username order by l.username";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Format name
		$userFirstLast = "";
		if ($row['user_firstname'] != '' && $row['user_lastname'] != '') {
			$userFirstLast = "({$row['user_firstname']} {$row['user_lastname']})";
		}
		$allowlist[$row['username']] = $userFirstLast;
	}
	return $allowlist;
}



// Get current white list
$allowlist = getAllowlist();




// If making an AJAX call
if ($isAjax && isset($_POST['action']))
{
	// Enable/disable the allowlist
	if ($_POST['action'] == 'enable')
	{
		// Check if we have everything
		if ($_POST['enable']   != '1' & $_POST['enable']   != '0') exit('0');
		if ($_POST['addusers'] != '1' & $_POST['addusers'] != '0') exit('0');
		// Save the change
		$sql = "update redcap_config set value = {$_POST['enable']} where field_name = 'enable_user_allowlist'";
		if (!db_query($sql)) exit('0');
		// Log the event
		$event = ($_POST['enable'] ? "Enable allowlist" : "Disable allowlist");
		Logging::logEvent($sql,"redcap_config","MANAGE","","",$event);
		// If enabling the allowlist, determine if we add all external auth users or just super users to allowlist
		if ($_POST['enable'])
		{
			// By default, add all users
			$sql = "insert into redcap_user_allowlist select username from redcap_user_information
					where user_email is not null and username != 'site_admin' and trim(username) != '' and
					username not in (" . pre_query("select username from redcap_auth") . ") and
					username not in (" . pre_query("select username from redcap_user_allowlist") . ")";
			// Limit to only super users, if has been selected
			if (!$_POST['addusers'])
			{
				$sql .= " and super_user = 1";
			}
			// Add users to allowlist table
			db_query($sql);
			// Display confirmation message to user
			?>
			<div id="enableSuccess" class="darkgreen" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<?php echo $lang['control_center_167'] ?>
			</div>
			<?php
		}
		else
		{
			// Display confirmation message to user
			?>
			<div id="disableSuccess" class="red" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>cross.png">
				<?php echo $lang['control_center_168'] ?>
			</div>
			<?php
		}
		// Get current white list so that table will populate
		$allowlist = getAllowlist();
	}
	// Delete all users from allowlist (excluding super users)
	elseif ($_POST['action'] == 'deleteall')
	{
		// Save the change
		$sql = "delete from redcap_user_allowlist where username
				not in (" . pre_query("select username from redcap_user_information where super_user = 1") . ")";
		if (db_query($sql))
		{
			// Log the event
			Logging::logEvent($sql,"redcap_user_allowlist","MANAGE","","","Remove all users from allowlist");
			// Get current white list
			$allowlist = getAllowlist();
			// Display confirmation message to user
			?>
			<div id="deleteAllSuccess" class="darkgreen" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<?php echo $lang['control_center_173'] ?>
			</div>
			<?php
		}
	}
	// Remove user from allowlist
	elseif ($_POST['action'] == 'remove' && isset($_POST['username']))
	{
		// First make sure user is actually in white list
		if (!isset($allowlist[$_POST['username']])) exit('0');
		// Save the change
		$sql = "delete from redcap_user_allowlist where username = '" . db_escape($_POST['username']) . "'";
		if (db_query($sql))
		{
			// Log the event
			Logging::logEvent($sql,"redcap_user_allowlist","MANAGE",$_POST['username'],"username = '" . db_escape($_POST['username']) . "'","Remove user from allowlist");
			// Give affirmative response
			exit('1');
		}
	}
	// Add users to allowlist
	elseif ($_POST['action'] == 'add' && isset($_POST['username']))
	{
		$sql_all = array();
		$allowlistNew = array();
		$allowlistFail = array();
		// Loop through all usernames submitted and add to table
		foreach (explode("\n", trim($_POST['username'])) as $thisUser)
		{
			// Clean the username and check for blanks
			$thisUser = trim(decode_filter_tags($thisUser));
			if ($thisUser == '') continue;
			// Check format of username
			$thisUser2 = preg_replace('/[^a-z A-Z0-9_\.\-\@]/', '', $thisUser);
			if ($thisUser == $thisUser2)
			{
				// Save the change
				$sql = "insert into redcap_user_allowlist values ('" . db_escape($thisUser) . "')";
				if (db_query($sql)) {
					$sql_all[] = $sql;
					// Add to white list so that it shows up in table rendered below
					$allowlist[$thisUser] = $allowlistNew[$thisUser] = "";
				}
			}
			else
			{
				// Add to list of usernames that couldn't be added
				$allowlistFail[$thisUser] = "";
			}
		}
		// Log the event
		if (!empty($sql_all))
		{
			Logging::logEvent(implode(";\n",$sql_all),"redcap_user_allowlist","MANAGE","","","Add users to allowlist");
			// Reorder allowlist now that we've added a new user
			ksort($allowlist);
			// Display confirmation message to user
			?>
			<div id="addSuccess" class="darkgreen" style="font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<?php echo $lang['control_center_158'] ?>
				<div style="font-weight:normal;padding-top:10px;">
					<?php echo $lang['control_center_169'] ?><br> &bull;
					<?php echo implode("<br> &bull; ", array_keys($allowlistNew)) ?>
				</div>
				<?php if (!empty($allowlistFail)) { ?>
				<div style="font-weight:normal;padding-top:10px;">
					<?php echo $lang['control_center_170'] ?><br> &bull;
					<?php echo implode("<br> &bull; ", array_keys($allowlistFail)) ?>
				</div>
				<?php } ?>
			</div>
			<?php
		}
		else
		{
			?>
			<div id="addSuccess" class="red" style="text-align:center;font-weight:bold;display:none;margin-bottom:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>cross.png">
				<?php echo $lang['control_center_171'] ?>
			</div>
			<?php
		}
	}
}
else
{
	// Regular page display
	include 'header.php';
	?>

	<style type="text/css">
	.data2 { padding: 1px 10px; }
	</style>

	<script type="text/javascript">
	// AJAX call to enable/disable allowlist
	function enableSave(enable,addusers) {
		$.post(app_path_webroot+page, { action: 'enable', enable: enable, addusers: addusers }, function(data){
			$('#allowlistEnableAsk').dialog('close');
			if (data=='0') {
				alert(woops);
			} else {
				$('#allowlist_table').html(data);
				initWidgets();
				$('#enableSaved').css('visibility','visible');
				setTimeout(function(){
					$('#enableSaved').css('visibility','hidden');
				},2000);
				$('#enableListBtn').button('option','disabled',true);
				if (enable=='1') {
					$('#allowlist_table table').fadeTo(0,1);
					$('.WLTableElement').css('visibility','visible');
					$('#enableSuccess').show('blind');
					setTimeout(function(){
						$('#enableSuccess').hide('blind');
					},5000);
				} else {
					$('#disableSuccess').show('blind');
					setTimeout(function(){
						$('#disableSuccess').hide('blind');
					},5000);
					disableTable();
				}
			}
		});
	}
	// Enable/disable the allowlist
	function enableAllowList() {
		var enable = $('#enable_user_allowlist').val();
		if (enable=='1') {
			$('#allowlistEnableAsk').dialog({ bgiframe: true, modal: true, width: 800, buttons: {
				Cancel: function() { $(this).dialog('close'); },
				'2) Enable and Do Not Add All Existing Users': function () {
					enableSave(enable,0);
				},
				'1) Enable and Add All Existing Users': function () {
					enableSave(enable,1);
				}
			} });
		} else {
			enableSave(enable,0);
		}
	}
	// AJAX call to delete all users from allowlist (excludes super users)
	function deleteUsersAllowList() {
		if (confirm("Remove all users from the allowlist (excludes super users)?")) {
			$.post(app_path_webroot+page, { action: 'deleteall' }, function(data){
				if (data=='0') {
					alert(woops);
				} else {
					$('#allowlist_table').html(data);
					initWidgets();
					$('#deleteAllSuccess').show('blind');
					setTimeout(function(){
						$('#deleteAllSuccess').hide('blind');
					},5000);
				}
			});
		}
	}
	// AJAX call to remove a user from allowlist
	function removeUserAllowList(username,userIsMe) {
		if (userIsMe == '1') {
			alert("Sorry, but you cannot delete yourself from the User Allowlist.");
			return;
		}
		if (confirm("Remove user '"+username+"' from the allowlist?")) {
			$.post(app_path_webroot+page, { username: username, action: 'remove' }, function(data){
				if (data=='0') {
					alert(woops);
				} else {
					highlightTableRow('user-'+username,3000);
					setTimeout(function(){
						$('#user-'+username).remove();
					},500);
				}
			});
		}
	}
	// Dialog for adding users to allowlist
	function addUsersAllowList() {
		$('#allowlistAdd').dialog({ bgiframe: true, modal: true, width: 400, buttons: {
			Cancel: function() { $('#newUsers').val(''); $(this).dialog('close'); },
			'Add Users to Allowlist': function () {
				$('#newUsers').val( trim($('#newUsers').val()) );
				if ($('#newUsers').val().length < 1) {
					alert('Add usernames');
					return;
				}
				$.post(app_path_webroot+page, { action: 'add', username: $('#newUsers').val() }, function(data){
					if (data=='0') {
						alert(woops);
					} else {
						$('#newUsers').val('');
						$('#allowlistAdd').dialog('close');
						$('#allowlist_table').html(data);
						initWidgets();
						$('#addSuccess').show('blind');
						setTimeout(function(){
							$('#addSuccess').hide('blind');
						},5000);
					}
				});
			}
		} });
	}
	// Disable the allowlist table
	function disableTable() {
		$('#allowlist_table table').fadeTo(0, 0.5);
		$('.WLTableElement').css('visibility','hidden');
	}
	<?php if (!$enable_user_allowlist) { ?>
	// Disable the table if allowlist is not enabled
	$(function(){
		disableTable();
	});
	<?php } ?>
	</script>

	<h4 style="margin-top: 0;"><i class="fas fa-user-check"></i> <?php echo $lang['control_center_164'] ?></h4>
	<p style='margin-top:0;'><?php echo $lang['control_center_159'] ?></p>
	<p style='margin-bottom:20px;'><?php echo $lang['control_center_160'] ?></p>

	<!-- Hidden dialog for adding users to allowlist -->
	<div id="allowlistAdd" title="<?php echo $lang['control_center_166'] ?>" style="display:none;">
		<p><?php echo $lang['control_center_157'] ?></p>
		<textarea id="newUsers" style="width:95%;height:100px;"></textarea>
	</div>
	<!-- Hidden dialog for prompting super user before enabling/disabling allowlist -->
	<div id="allowlistEnableAsk" title="<?php echo $lang['control_center_161'] ?>" style="display:none;">
		<p><?php echo $lang['control_center_163'] ?></p>
		<p><?php echo $lang['control_center_165'] ?></p>
	</div>

	<!-- Option to enable/disable the allowlist -->
	<table style="border: 1px solid #ccc; background-color: #f0f0f0;width:100%;max-width:500px;margin-bottom:30px;">
		<tr>
			<td class="cc_label" style="text-align:left;vertical-align:middle;">
				<?php echo $lang['control_center_161'] ?>
			</td>
			<td class="cc_label" style="text-align:left;vertical-align:middle;">
				<select class="x-form-text x-form-field" style="" id="enable_user_allowlist" onchange="$('#enableListBtn').button('option','disabled',false);">
					<option value='0' <?php echo (!$enable_user_allowlist) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
					<option value='1' <?php echo ($enable_user_allowlist) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
				</select>
				&nbsp;
				<button id="enableListBtn" class="jqbuttonmed" disabled onclick="enableAllowList();">Save</button>
				&nbsp; &nbsp;
				<span id="enableSaved" style="color:red;visibility:hidden;">Saved!</span>
			</td>
		</tr>
	</table>

	<div id="allowlist_table">

<?php
}
?>



	<!-- User Table -->
	<table style='border-collapse:collapse;width:100%;max-width:500px;'>
		<tr>
			<td class='labelrc' style='padding-top:10px;padding-bottom:10px;background-color:#eee;font-family:verdana;color:#800000;' colspan='2'>
				<table style='border-collapse:collapse;'  width=100%>
					<tr>
						<td style="font-size:14px;">
							<?php echo $lang['control_center_162'] ?>
						</td>
						<td style="text-align:right;">
							<button class="jqbuttonmed WLTableElement" onclick="addUsersAllowList();"><?php echo $lang['control_center_156'] ?></button> &nbsp;
							<button class="jqbuttonmed WLTableElement" onclick="deleteUsersAllowList();"><?php echo $lang['control_center_172'] ?></button>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	<?php foreach ($allowlist as $thisUser=>$thisName) { ?>
		<tr id="user-<?php echo $thisUser ?>">
			<td class='data2'>
				<?php echo $thisUser ?>
				<span style="color:#777;font-size:10px;padding-left:5px;"><?php echo $thisName ?></span>
			</td>
			<td class='data2' style='width:30px;text-align:center;'>
				<a href="javascript:;" onclick="removeUserAllowList('<?php echo $thisUser ?>',<?php echo ($thisUser == $userid ? 1 : 0) ?>);"><img title="Remove" src="<?php echo APP_PATH_IMAGES ?>cross.png" class="WLTableElement"></a>
			</td>
		</tr>
	<?php } ?>
	<?php if (empty($allowlist)) { ?>
		<tr>
			<td class='data2' colspan='2' style="padding:10px;">
				<b><?php echo $lang['control_center_155'] ?></b>
			</td>
		</tr>
	<?php } ?>
	</table>



<?php

if (!$isAjax) {
	print "</div>";
	include 'footer.php';
}
