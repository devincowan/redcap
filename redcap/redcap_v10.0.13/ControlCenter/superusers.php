<?php


include 'header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

if (isset($_GET['saved']))
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['setup_09']}
			</div>
			<script type='text/javascript'>
			$(function(){
				setTimeout(function(){
					$('.yellow').hide();
				},2500);
			});
			</script>";
}
?>
<script type="text/javascript">
// Add new Super User
function add_super_user() {
	var new_super_user = $('#new_super_user').val();
	if (new_super_user.length < 1) return;
	document.getElementById('add_super_user_btn').disabled = true;
	document.getElementById('new_super_user').disabled = true;
	$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?user_view=super_user&view=user_controls&action=add_super_user&username='+new_super_user, { },
		function(data) {
			if (data == '0') {
				alert(woops);
				window.location.href = app_path_webroot+page;
			} else {
				window.location.href = app_path_webroot+page+'?saved=1';
			}
		}
	);
}
// Remove Super User
function remove_super_user(super_user) {
	if (confirm("Remove \""+super_user+"\" as a REDCap Administrator?")) {
		$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?user_view=super_user&view=user_controls&action=remove_super_user&username='+super_user, { },
			function(data) {
				if (data == '0') {
					alert(woops);
					window.location.href = app_path_webroot+page;
				} else {
					super_user2 = super_user.replace(/([ #;&,.+*~\':"!^$[\]()=>|\/@])/g,'\\$1');
					highlightTableRow('su-'+super_user2,1500);
					setTimeout(function(){
						$('#su-'+super_user2).remove();
						$('#new_super_user').append('<option value="'+super_user+'">'+super_user+'</option>');
					},500);
				}
			}
		);
	}
}
// Add new Account Manager
function add_account_manager() {
	var new_account_manager = $('#new_account_manager').val();
	if (new_account_manager.length < 1) return;
	document.getElementById('add_account_manager_btn').disabled = true;
	document.getElementById('new_account_manager').disabled = true;
	$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?user_view=super_user&view=user_controls&action=add_account_manager&username='+new_account_manager, { },
		function(data) {
			if (data == '0') {
				alert(woops);
				window.location.href = app_path_webroot+page;
			} else {
				window.location.href = app_path_webroot+page+'?saved=1';
			}
		}
	);
}
// Remove Super User
function remove_account_manager(account_manager) {
	if (confirm("Remove \""+account_manager+"\" as an Account Manager?")) {
		$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?user_view=super_user&view=user_controls&action=remove_account_manager&username='+account_manager, { },
			function(data) {
				if (data == '0') {
					alert(woops);
					window.location.href = app_path_webroot+page;
				} else {
					account_manager2 = account_manager.replace(/([ #;&,.+*~\':"!^$[\]()=>|\/@])/g,'\\$1');
					highlightTableRow('am-'+account_manager2,1500);
					setTimeout(function(){
						$('#am-'+account_manager2).remove();
						$('#new_account_manager').append('<option value="'+account_manager+'">'+account_manager+'</option>');
						// If there are no acct mgrs left, then re-display the hidden row
						if (!$('[id^=am-]').length) $('#rowAcctMgrNone').show();
					},500);
				}
			}
		);
	}
}
</script>

<h4 style="margin-top: 0;"><i class="fas fa-user-shield"></i> <?php echo $lang['control_center_4571'] ?></h4>

<p><?php echo $lang['control_center_4606'] ?></p>

<div class="container-fluid" style="padding:0;margin-top:20px;"><div class="row">
	<div class="col-6">
		<!-- Administrators -->
		<div style="font-weight:bold;font-size:15px;margin:8px 0;color:#C00000;"><?php echo $lang['control_center_4576'] ?></div>
		<select id='new_super_user' class='x-form-text x-form-field' style='max-width:180px;margin-top:2px;'>
			<option value=''>--- <?php echo $lang['control_center_22'] ?> ---</option>
			<?php
			$query = "select username, concat(user_firstname, ' ', user_lastname) as name 
					  from redcap_user_information where super_user = 0 and account_manager = 0 
					  and username != '' order by trim(lower(username))";
			$q = db_query($query);
			while ($row = db_fetch_assoc($q)) {
				$row['username'] = strtolower(trim($row['username']));
				print  "<option value='".htmlspecialchars($row['username'], ENT_QUOTES)."'>".RCView::escape("{$row['username']} ({$row['name']})")."</option>";
			}
			?>
		</select>
		<button class='jqbuttonmed' style='margin-top:2px;' id='add_super_user_btn' onclick="add_super_user();"><?php echo $lang['control_center_4573'] ?></button>
		<div style="margin:15px 0 0;">
			<table style='border:0;border-collapse:collapse;'>
			<tr>
				<td class='labelrc' style='background-color:#eee;color:#C00000;' colspan='2'>
					<?php echo $lang['control_center_4578'] ?>
				</td>
			</tr>
			<?php
			$q = db_query("select username, concat(user_firstname, ' ', user_lastname) as name 
						   from redcap_user_information where super_user = 1 order by username");
			while ($row = db_fetch_assoc($q))
			{
				$row['username'] = strtolower(trim($row['username']));
				// If authentication is not enabled yet, do not allow them to remove site_admin as super user
				$img = ($auth_meth == 'none' && $row['username'] == 'site_admin')
						 ? "<img src='" . APP_PATH_IMAGES . "spacer.gif' style='width:16px;height:16px;'>"
						 : "<a href='javascript:;' onclick=\"remove_super_user('{$row['username']}');\"
								><img src='" . APP_PATH_IMAGES . "cross.png' alt='{$lang['control_center_70']}'
									title='{$lang['control_center_70']}'></a>";
				// Render row
				print  "<tr id='su-{$row['username']}'>
							<td class='data2'>
								{$row['username']} ({$row['name']})
							</td>
							<td class='data2' style='padding:0 4px;text-align:center;'>
								$img
							</td>
						</tr>";
			}
			?>
			</table>
		</div>
	</div>
	<div class="col-6">
		<!-- Acct Managers -->
		<div style="font-weight:bold;font-size:15px;margin:8px 0;color:#3E72A8;"><?php echo $lang['control_center_4577'] ?></div>
		<select id='new_account_manager' class='x-form-text x-form-field' style='max-width:180px;margin-top:2px;'>
			<option value=''>--- <?php echo $lang['control_center_22'] ?> ---</option>
			<?php
			$query = "select username, concat(user_firstname, ' ', user_lastname) as name 
					  from redcap_user_information where super_user = 0 and account_manager = 0
					  and username != '' order by trim(lower(username))";
			$q = db_query($query);
			while ($row = db_fetch_assoc($q)) {
				$row['username'] = strtolower(trim($row['username']));
				print  "<option value='".htmlspecialchars($row['username'], ENT_QUOTES)."'>".RCView::escape("{$row['username']} ({$row['name']})")."</option>";
			}
			?>
		</select>
		<button class='jqbuttonmed' style='margin-top:2px;' id='add_account_manager_btn' onclick="add_account_manager();"><?php echo $lang['control_center_4574'] ?></button>
		<div style="margin:15px 0 0;">
			<table style='border:0;border-collapse:collapse;'>
			<tr>
				<td class='labelrc' style='background-color:#eee;color:#3E72A8;' colspan='2'>
					<?php echo $lang['control_center_4579'] ?>
				</td>
			</tr>
			<?php
			$q = db_query("select username, concat(user_firstname, ' ', user_lastname) as name 
						   from redcap_user_information where account_manager = 1 order by username");
			while ($row = db_fetch_assoc($q))
			{
				$row['username'] = strtolower(trim($row['username']));
				// Render row
				print  "<tr id='am-{$row['username']}'>
							<td class='data2'>
								{$row['username']} ({$row['name']})
							</td>
							<td class='data2' style='padding:0 4px;text-align:center;'>
								<a href='javascript:;' onclick=\"remove_account_manager('{$row['username']}');\"
								><img src='" . APP_PATH_IMAGES . "cross.png' alt='{$lang['control_center_70']}'
									title='{$lang['control_center_70']}'></a>
							</td>
						</tr>";
			}
			// Placeholder row if no account managers yet
			$rowAcctMgrNoneStyle = (db_num_rows($q) > 0) ? "style='display:none;'" : "";
			print  "<tr id='rowAcctMgrNone' $rowAcctMgrNoneStyle>
						<td class='data2' colspan=2 style='color:#999;padding:10px;'>{$lang['control_center_4580']}</td>
					</tr>";
			?>
			</table>
		</div>
	</div>
</div></div>

<?php 
include 'footer.php';