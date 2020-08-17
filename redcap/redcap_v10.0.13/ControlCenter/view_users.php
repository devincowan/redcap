<?php


include 'header.php';

// If username is in query string, then load that user information upon pageload
if (isset($_GET['username']) && $_GET['username'] != "")
{
	$_GET['username'] = strip_tags(label_decode(urldecode($_GET['username'])));
	if (!preg_match("/^([a-zA-Z0-9 _\.\-\@])+$/", $_GET['username'])) redirect(PAGE_FULL);
	// First, ensure that this is a valid username
	$sql = "(select username from redcap_user_rights where username = '" . db_escape($_GET['username']) . "')
			union (select username from redcap_user_information where username = '" . db_escape($_GET['username']) . "')";
	$q = db_query($sql);
	if (db_num_rows($q) < 1) {
		redirect(PAGE_FULL);
	}
	?>
	<script type="text/javascript">
	$(function(){
		var user = '<?php echo js_escape($_GET['username']) ?>';
		$('#select_username').val(user);
		// Make sure this user is listed in the drop-down first
		if ($('#select_username').val() == user) {
			view_user(user);
		}
	});
	</script>
	<?php
}
?>

<h4 style="margin-top: 0;"><i class="fas fa-user-friends"></i> <?php echo $lang['control_center_109'] ?></h4>

<?php
// Tabs
$tabs = array('ControlCenter/view_users.php'.(isset($_GET['username']) ? "?".$_SERVER['QUERY_STRING'] : "")=>RCView::img(array('src'=>'user_info3.png')) . $lang['control_center_4640'],
			  'ControlCenter/view_users.php'.(isset($_GET['criteria_search']) ? "?".$_SERVER['QUERY_STRING'] : "?criteria_search=1")=>RCView::img(array('src'=>'users3.png')) . $lang['control_center_33']);
RCView::renderTabs($tabs);
?>


<div style="margin-bottom:20px;padding:10px 15px;border:1px solid #d0d0d0;background-color:#f5f5f5;max-width:800px;">
	
	<?php if (!isset($_GET['criteria_search'])) { ?>
	<!-- User Search -->
	<div id="user-search" class="browse-users-search-box">
		<div id="view_user_div">
			<?php
			// Set value for including
			$_GET['user_view'] = "view_user";
			include APP_PATH_DOCROOT . "ControlCenter/user_controls_ajax.php";
			?>
		</div>
	</div>
	
	<?php } else { ?>
	
	<div id="user-search-criteria" class="browse-users-search-box">
		<?php echo RCView::img(array('src'=>'users3.png')) ?>
		<b><?php echo $lang['control_center_33'] ?></b>
		<div style="margin-top:10px;">
			<?php echo $lang['control_center_34'] ?>
			<span style="margin-left:4px;color:#777;font-family:tahoma;font-size:9px;"><?php echo $lang['control_center_193'];?></span>
		</div>
		<div style="margin: 10px 0px;">
			<span style="margin-right:4px;font-weight:bold;"><?php echo $lang['control_center_4641'] ?></span>
			<select id="activity-level" name="activity_level" class="x-form-text x-form-field">

				<optgroup label="<?php echo js_escape2($lang['control_center_360']) ?>">
					<option value="" selected><?php echo $lang['control_center_182'];?></option>
					<?php if ($auth_meth_global == 'ldap_table' || $auth_meth_global == 'none' || strpos($auth_meth_global,'aaf')>-1 ) { ?><option value="T"><?php echo $lang['control_center_4441'];?></option><?php } ?>
					<?php if ($auth_meth_global == 'ldap_table' || strpos($auth_meth_global,'aaf')>-1 ) { ?><option value="NT"><?php echo $lang['control_center_4442'];?></option><?php } ?>
				</optgroup>

				<optgroup label="<?php echo js_escape2($lang['control_center_4385']) ?>">
					<option value="I"><?php echo $lang['control_center_183'];?></option>
					<option value="NI"><?php echo $lang['control_center_4384'];?></option>
				</optgroup>

				<optgroup label="<?php echo js_escape2($lang['control_center_4386']) ?>">
					<option value="E"><?php echo $lang['control_center_4387'];?></option>
					<option value="NE"><?php echo $lang['control_center_4388'];?></option>
				</optgroup>

				<optgroup label="<?php echo js_escape2($lang['control_center_359']) ?>">
					<option value="CL"><?php echo $lang['control_center_355'];?></option>
					<option value="NCL"><?php echo $lang['control_center_356'];?></option>
				</optgroup>

				<optgroup label="<?php echo js_escape2($lang['control_center_358']) ?>">
					<option value="L-0"><?php echo $lang['control_center_4652'];?></option>
					<option value="L-0.0417"><?php echo $lang['control_center_347'];?></option>
					<option value="L-0.5"><?php echo $lang['control_center_345'];?></option>
					<option value="L-1"><?php echo $lang['control_center_343'];?></option>
					<option value="L-30"><?php echo $lang['control_center_198'];?></option>
					<option value="L-90"><?php echo $lang['control_center_199'];?></option>
					<option value="L-183"><?php echo $lang['control_center_200'];?></option>
					<option value="L-365"><?php echo $lang['control_center_201'];?></option>

					<option value="NL-0.0417"><?php echo $lang['control_center_348'];?></option>
					<option value="NL-0.5"><?php echo $lang['control_center_346'];?></option>
					<option value="NL-1"><?php echo $lang['control_center_344'];?></option>
					<option value="NL-30"><?php echo $lang['control_center_202'];?></option>
					<option value="NL-90"><?php echo $lang['control_center_203'];?></option>
					<option value="NL-183"><?php echo $lang['control_center_204'];?></option>
					<option value="NL-365"><?php echo $lang['control_center_205'];?></option>
				</optgroup>

				<optgroup label="<?php echo js_escape2($lang['control_center_357']) ?>">
					<option value="0.0417"><?php echo $lang['control_center_353'];?></option>
					<option value="0.5"><?php echo $lang['control_center_351'];?></option>
					<option value="1"><?php echo $lang['control_center_349'];?></option>
					<option value="30"><?php echo $lang['control_center_184'];?></option>
					<option value="90"><?php echo $lang['control_center_186'];?></option>
					<option value="183"><?php echo $lang['control_center_187'];?></option>
					<option value="365"><?php echo $lang['control_center_188'];?></option>

					<option value="NA-0.0417"><?php echo $lang['control_center_354'];?></option>
					<option value="NA-0.5"><?php echo $lang['control_center_352'];?></option>
					<option value="NA-1"><?php echo $lang['control_center_350'];?></option>
					<option value="NA-30"><?php echo $lang['control_center_194'];?></option>
					<option value="NA-90"><?php echo $lang['control_center_195'];?></option>
					<option value="NA-183"><?php echo $lang['control_center_196'];?></option>
					<option value="NA-365"><?php echo $lang['control_center_197'];?></option>
				</optgroup>

			</select>
		</div>
		
		<div style="margin-bottom:10px;">
			<span style="margin-right:4px;font-weight:bold;"><?php echo $lang['control_center_60'] ?></span>
			<input type="text" placeholder="<?php echo js_escape2($lang['dataqueries_229']) ?>" id="user_list_search" size="20" class="x-form-text x-form-field" style="" value="<?php if (isset($_GET['search_term'])) print htmlspecialchars($_GET['search_term'], ENT_QUOTES) ?>">
			<span style="margin:0 4px;"><?php echo $lang['global_107'] ?></span>
			<select id="user_list_search_attr" class="x-form-text x-form-field" style="margin-right:5px;">
				<option value="" <?php if ($_GET['search_attr'] == "") print "selected"; ?>><?php echo $lang['control_center_4496'] ?></option>
				<option value="username" <?php if ($_GET['search_attr'] == "username") print "selected"; ?>><?php echo $lang['global_11'] ?></option>
				<option value="user_firstname" <?php if ($_GET['search_attr'] == "user_firstname") print "selected"; ?>><?php echo $lang['global_41'] ?></option>
				<option value="user_lastname" <?php if ($_GET['search_attr'] == "user_lastname") print "selected"; ?>><?php echo $lang['global_42'] ?></option>
				<option value="user_email" <?php if ($_GET['search_attr'] == "user_email") print "selected"; ?>><?php echo $lang['control_center_56'] ?></option>
				<option value="user_sponsor" <?php if ($_GET['search_attr'] == "user_sponsor") print "selected"; ?>><?php echo $lang['user_72'] ?></option>
				<option value="user_inst_id" <?php if ($_GET['search_attr'] == "user_inst_id") print "selected"; ?>><?php echo $lang['control_center_236'] ?></option>
				<option value="user_comments" <?php if ($_GET['search_attr'] == "user_comments") print "selected"; ?>><?php echo $lang['dataqueries_146'] ?></option>
			</select>
		</div>
		
		<div style="margin:20px 0 5px;">
			<button class="btn btn-primaryrc btn-sm" style="font-size:13px;" onclick="openUserHistoryList();"><?php print $lang['control_center_4642'] ?></button>
		</div>
	</div>	
	<?php } ?>
	
</div>

<!-- Dialog Box for Comprehensive User List -->
<div id="userList" style="width:1000px;">
	<div id="userListProgress" style="display:none;padding:20px 0;font-weight:bold;">
		<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif"> <?php echo $lang['control_center_41'] ?>...
	</div>
	<div id="userListTable" style="margin:10px 0 20px;">
	<?php 
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		User::renderSponsorDashboard(true); 
		// Unset some things so that we can redisplay the user table as if loaded via GET
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$usersInfo = User::getUserInfoByCriteria();
		User::renderSponsorDashboard(true, array_keys($usersInfo)); 
	}
	?>
	</div>
</div>

<script type="text/javascript">
// Auto-suggest for adding new users
function enableUserSearch() {
	if ($('#user_search').length) {
		$('#user_search').autocomplete({
			source: app_path_webroot+"UserRights/search_user.php?searchEmail=1&searchSuspended=1",
			minLength: 2,
			delay: 150,
			select: function( event, ui ) {
				$(this).val(ui.item.value);
				window.location.href = app_path_webroot+page+'?username='+ui.item.value;
				return false;
			}
		})
		.data('ui-autocomplete')._renderItem = function( ul, item ) {
			return $("<li></li>")
				.data("item", item)
				.append("<a>"+item.label+"</a>")
				.appendTo(ul);
		};
	}
}
// Resend user verification email for email address
function resendVerificationEmail(username,email_account) {
	// Confirmation message
	simpleDialog('<?php echo js_escape($lang['control_center_4418']) ?>','<?php echo js_escape($lang['control_center_4415']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',function(){
		// Ajax call
		$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?action=resend_verification_email&username='+username+'&email_account='+email_account, { }, function(data){
			if (data == '0') {
				alert(woops);
			} else {
				simpleDialog(data,'<?php echo js_escape($lang['control_center_4415']) ?>');
			}
		});
	},'<?php echo js_escape($lang['control_center_4419']) ?>');
}
// Remove a user's email verification code
function autoVerifyEmail(username,email_account) {
	// Confirmation message
	simpleDialog('<?php echo js_escape($lang['control_center_4421']) ?>','<?php echo js_escape($lang['control_center_4416']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',function(){
		// Ajax call
		$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?action=remove_verification_code&username='+username+'&email_account='+email_account, { }, function(data){
			if (data == '0') {
				alert(woops);
			} else {
				simpleDialog(data,'<?php echo js_escape($lang['control_center_4416']) ?>');
				view_user(username);
			}
		});
	},'<?php echo js_escape($lang['control_center_4416']) ?>');
}
$(function(){
	enableUserSearch();
});
</script>


<?php
include 'footer.php';
