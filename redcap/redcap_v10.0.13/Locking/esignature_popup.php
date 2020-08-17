<?php



// Default value regarding if user has already entered their username for an e-signature in this session
$alreadyEnteredUsername = ($auth_meth == 'none'); // False for all auth methods except 'none'

if (!$alreadyEnteredUsername)
{
	// Check within a window of 3 hours in the past
	$threeHrsAgo = date("Y-m-d H:i:s", mktime(date("H")-6,date("i"),date("s"),date("m"),date("d"),date("Y")));
	// Get last login time during this session (will fail for 'none' and 'shibolleth' authentication)
	$sql = "select v.ts from redcap_log_view v, redcap_sessions s where v.ts > '$threeHrsAgo' and
			v.user = '".db_escape($userid)."' and v.event = 'LOGIN_SUCCESS' and v.session_id = s.session_id order by v.log_view_id desc limit 1";
	$q = db_query($sql);
	$lastLogin = ($q && db_num_rows($q) > 0) ? db_result($q, 0) : 0;
	// Get most recent e-signature during this session. If exists, then don't ask for username for e-signature anymore during this session.
	if ($lastLogin != 0)
	{
		$sql = "select 1 from redcap_esignatures where username = '".db_escape($userid)."' and timestamp > '$lastLogin' order by esign_id desc limit 1";
		$q = db_query($sql);
		$alreadyEnteredUsername = ($q && db_num_rows($q) > 0);
	}
}

// Set html for username form field
$esign_username_input = ($alreadyEnteredUsername) ? " value=\"$userid\" readonly style=\"background:#ddd;\" " : " style=\"\" ";


?>

<!-- E-signature: username/password -->
<div id="esign_popup" title="E-signature: Username/password verification" style="display:none;">
	<p style="margin-bottom:25px;">
		<?php print $lang['esignature_21'] ?>
	</p>
	<div style="float:left;display:block;margin-left:50px;width:100px;font-weight:bold;"><?php print $lang['global_11'] . $lang['colon'] ?></div>
	<div style="float:left;display:block;">
		<input type="text" id="esign_username" autocomplete="new-password" class="x-form-text x-form-field" <?php echo $esign_username_input ?>>
	</div><br><br>
	<div style="float:left;display:block;margin-left:50px;width:100px;font-weight:bold;"><?php print $lang['global_32'] . $lang['colon'] ?></div>
	<div style="float:left;display:block;">
		<input type="password" id="esign_password" autocomplete="new-password" style="" class="x-form-text x-form-field">
	</div><br><br>
	<!-- Hidden error message -->
	<div id="esign_popup_error" class="red" style="display:none;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<?php print $lang['esignature_24'] ?>
	</div>
</div>
