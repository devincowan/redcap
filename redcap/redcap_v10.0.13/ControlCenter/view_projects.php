<?php


include 'header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);
?>
<style type="text/css">
#pagecontainer { max-width: 1050px; }
</style>

<h4 style="margin-top: 0;"><i class="fas fa-layer-group"></i> <?php echo $lang['control_center_110'] ?></h4>

<p style='margin-top:0;margin-bottom:15px;'><?php echo $lang['control_center_4649'] ?></p>


<script type="text/javascript">
$(function(){
	// Auto-suggest for adding new users
	$('#user_search').autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?searchEmail=1&searchSuspended=1&searchSuspended=1",
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			$('#user_search_btn').click();
			return false;
		}
	})
	.data('ui-autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
});
function viewBtnClick(titleBtn) {
	var user = '', title = '';
	// Get user
	var us_ob = $('#user_search');
	us_ob.trigger('focus');
	us_ob.val( trim(us_ob.val()) );
	var userParts = us_ob.val().split(' ');
	us_ob.val( trim(userParts[0]) );
	// Get title	
	var ti_ob = $('#project_search');
	ti_ob.val( trim(ti_ob.val()) );
	if (us_ob.val().length > 0) {
		if (!chk_username(us_ob)) {
			return alertbad(us_ob,'<?php print js_escape($lang['rights_354']) ?>');
		}
		user = us_ob.val();
	}
	if (ti_ob.val().length > 0) {
		title = ti_ob.val();
	}
	if (title == '' && titleBtn) {
		ti_ob.trigger('focus');
		return;
	}
	if (title == '' && user == '') return;
	// Submit
	$(':input, .btn').prop('disabled',true);
	var url = app_path_webroot+page+'?p';
	if (user != '') url += '&userid='+user;
	if (title != '') url += '&title='+encodeURIComponent(title);
	window.location.href = url;
}
function viewAllBtnClick() {
	$(':input, .btn').prop('disabled',true);
	window.location.href = app_path_webroot+page+'?view_all=1';
}
</script>
<?php
// If user is selected, then display a link to view their user information
$userInfoLink = "";
if (isset($_GET['userid']) && $_GET['userid'] != "")
{
	$userInfoArray = User::getUserInfo($_GET['userid']);
	if ($userInfoArray !== false) {
		// Link to user info page
		$userInfoLink =  "<button class='btn btn-success btn-xs' style='font-size:11px;' onclick=\"window.location.href='".APP_PATH_WEBROOT."ControlCenter/view_users.php?username={$_GET['userid']}';\"><i class='far fa-user'></i> {$lang['system_config_241']} \"{$_GET['userid']}\"</button>";
	} else {
		// Not a valid username
		$userInfoLink = RCView::span(array('class'=>'yellow', 'style'=>'padding:2px 5px;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							$lang['control_center_441']
						);
	}
	$userInfoLink = RCView::div(array('style'=>'text-align:right;padding:3px 0 0;margin-right:108px;'), $userInfoLink);
}
// Create "add new user" text box
$usernameTextboxValue  = (isset($_GET['userid']) ? $_GET['userid'] : '');
$usernameTextbox = RCView::text(array('id'=>'user_search', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
					'style'=>'width:400px;', 'value'=>$usernameTextboxValue,
					'onkeydown'=>"if(event.keyCode==13) viewBtnClick(0);",
					'placeholder'=>$lang['control_center_4428']));
print 	RCView::div(array('style'=>'margin:10px 0 0;'),
			$userInfoLink .
			RCView::b($lang['control_center_437']) . "<br>" . $usernameTextbox .
			RCView::button(array('id'=>'user_search_btn', 'class'=>'btn btn-primaryrc btn-xs', 'style'=>'font-size:13px;margin-left:4px;', 'onclick'=>"viewBtnClick(0)"), $lang['global_84']) .
			// View All button
			RCView::span(array('style'=>'margin:0 5px 0 2px;color:#888;font-size:11px;'), " &ndash; ".$lang['global_46']. " &ndash; ") .
			RCView::button(array('onclick'=>"viewAllBtnClick()", 'class'=>'btn btn-info btn-xs', 'style'=>'font-size:12px;'), $lang['control_center_4380'])
		);
print RCView::div(array('style'=>'margin:9px 0 5px 5px;color:#777;font-size:12px;'), "&ndash; ".$lang['control_center_4648']. " &ndash;");
// Project search
$projectTitleSearchVal = isset($_GET['title']) ? rawurldecode(urldecode($_GET['title'])) : '';
$projectTextbox = RCView::text(array('id'=>'project_search', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255', 'value'=>$projectTitleSearchVal,
					'style'=>'width:400px;', 'onkeydown'=>"if(event.keyCode==13) viewBtnClick(1);"));
print 	RCView::div(array('style'=>'margin:0 0 20px;'),
			RCView::b($lang['control_center_4647']) . "<br>" . $projectTextbox .
			RCView::button(array('id'=>'project_search_btn', 'class'=>'btn btn-primaryrc btn-xs', 'style'=>'font-size:13px;margin-left:4px;', 'onclick'=>"viewBtnClick(1)"), $lang['control_center_4646'])
		);

// Display listing of all existing projects
$projects = new RenderProjectList ();
$projects->renderprojects("control_center");

// Hidden "undelete project" div
print RCView::simpleDialog("", $lang['control_center_378'], 'undelete_project_dialog');

include 'footer.php';
