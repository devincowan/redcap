<?php



$html = '';

if (!defined("PROJECT_ID")) {
	include 'header.php';
	if (!SUPER_USER) redirect(APP_PATH_WEBROOT);
	$html .= RCView::h4(array('style' => 'margin-top: 0;color:#000066;'),
				'<i class="fas fa-coins"></i> ' .
				$lang['control_center_245']
			);
	$html .= RCView::p(array(), $lang['control_center_280']);
	$html .= RCView::p(array('style' => 'margin-bottom: 20px;'), $lang['control_center_281']);
	if (!$api_enabled) $html .= RCView::warnBox(RCView::disabledAPINote());
}

// Validate $_GET['api_pid']
if (isset($_GET['api_pid']) && !is_numeric($_GET['api_pid'])) exit('ERROR!');

/* NOTE: all JS is included at the bottom */

// set up the dialog that will allow quick access to API user rights
$html .= RCView::div(array('id' => 'rightsDialogId', 'style' => 'display: none;'), '');

// API token tables populated by JS
$dummy = RCView::select(array('name' => 'api_username', 'id' => 'apiUserSelId', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), array('' => ''));
$html .= RCView::div(array('id' => 'apiByUserId', 'style' => 'display: none;'), $dummy);
$html .= RCView::br(); RCView::br();

$dummy = RCView::select(array('name' => 'api_pid', 'id' => 'apiProjSelId', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), array('' => ''));
$html .= RCView::div(array('id' => 'apiByProjId', 'style' => 'display: none;'), $dummy);

$html .= RCView::h4(array('style' => 'margin-top: 35px;color:#800000;'),
			'<i class="fas fa-coins"></i> ' .
			$lang['control_center_4523']
		 );
$html .= RCView::p(array(), $lang['control_center_4522']);
$dummy = RCView::select(array('name' => 'api_username_s', 'id' => 'apiUserSelId_s', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), array('' => ''));
$html .= RCView::div(array('id' => 'apiByUserId_s', 'style' => 'display: none;'), $dummy);
$html .= RCView::br(); RCView::br();

echo RCView::div(array('id' => 'apiDummyContainer'), $html);

// Validate api_username in query string
if (isset($_GET['api_username'])) {
	$userInfo = User::getUserInfo($_GET['api_username']);
	if ($userInfo === false) redirect(PAGE_FULL);
}

?>

<script type="text/javascript">
function redrawAPITables(dialogMsg) {
	if (dialogMsg.length > 0) {
		// dialog always comes in an element with id "dialogAJAXId"
		$("#dialogAJAXId").remove(); // remove the existing dialog (if any)
		$("body").append(dialogMsg); // drop in the new dialog
		$("#dialogAJAXId").hide(); // hide it (jquery dialog() will display it)
		var buttons = { Close: function() { $(this).dialog('close'); }}
		<?php if (!empty($_GET['goto_proj'])) { ?>
			buttons["Return to project API page"] = function() {
				window.location = app_path_webroot + 'API/project_api.php?pid=' + <?php echo $_GET['api_pid']; ?>;
			};
		<?php } ?>
		$("#dialogAJAXId").dialog({ bgiframe: true, modal: true, width: 400, close: function() {$(this).dialog('destroy');}, buttons: buttons});
	}
	<?php if (!defined('PROJECT_ID')) { ?>
	// Only display "tokens by user" table in Control Center view
	$("#apiUserSelId").trigger("change");
	$("#apiUserSelId_s").trigger("change");
	<?php } ?>
	// Display "tokens by project" table
	$("#apiProjSelId").trigger("change");
}
$(function()
{
	function apiRightsCheck(url, urlAfterCheck) {
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		$.ajax({url: url, type: 'POST', data: {redcap_csrf_token: redcap_csrf_token},
			success: function(data) {
				$("#rightsDialogId").html(data);
				$("#rightsDialogId").attr('title', "<?php echo js_escape2($lang['control_center_283']) ?> \"" + $("#rightsUsername").val() + "\"");
				RedCapUtil.closeLoader($("#apiDummyContainer").parent());
				$("#rightsDialogId").dialog({ bgiframe: true, modal: true, width: 550, close: function() {$(this).dialog('destroy');}, buttons: {
					Cancel: function() { $(this).dialog('close'); },
					'<?php echo js_escape($lang['control_center_273']) ?>': function() {
						RedCapUtil.openLoader($("#apiDummyContainer").parent());
						$(this).dialog('close');
						$.post(urlAfterCheck+(urlAfterCheck.indexOf('?')>0 ? '&' : '?')+$.param({api_export: $("#api_export").is(":checked") ? 1 : 0,
								api_import: $("#api_import").is(":checked") ? 1 : 0,
								mobile_app: $("#mobile_app").is(":checked") ? 1 : 0,
								api_send_email: $("#api_send_email").is(":checked") ? 1 : 0}),
							{ 		},
							function (data) {
								$.post(app_path_webroot+'ToDoList/todo_list_ajax.php?'+$.param({ action: 'update-token', project_id: '<?php echo $_GET['api_pid']; ?>' }),
								{  },
								function(data){
									if (data == '1'){
										return;
									}else{
										return;
									}
								});
								redrawAPITables(data);
								RedCapUtil.closeLoader($("#apiDummyContainer").parent());
							}
						);
					}}
				});
			}
		});
	}

	function apiRightsCheck_s(url, urlAfterCheck) {
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		$.ajax({url: url, type: 'POST', data: { redcap_csrf_token: redcap_csrf_token },
			success: function(data) {
				$("#rightsDialogId").html(data);
				$("#rightsDialogId").attr('title', "<?php echo js_escape2($lang['control_center_4507']) ?> \"" + $("#rightsUsername").val() + "\"");
				RedCapUtil.closeLoader($("#apiDummyContainer").parent());
				$("#rightsDialogId").dialog({ bgiframe: true, modal: true, width: 550, close: function() {$(this).dialog('destroy');}, buttons: {
					Cancel: function() { $(this).dialog('close'); },
					'<?php echo js_escape($lang['control_center_4509']) ?>': function() {
						RedCapUtil.openLoader($("#apiDummyContainer").parent());
						$(this).dialog('close');
						$.post(urlAfterCheck+(urlAfterCheck.indexOf('?')>0 ? '&' : '?')+$.param({ api_send_email: $("#api_send_email").is(":checked") ? 1 : 0 }),
							{ 	},
							function (data) {
								redrawAPITables(data);
								RedCapUtil.closeLoader($("#apiDummyContainer").parent());
							}
						);
					}}
				});
			}
		});
	}

	// super
	$(document).delegate("#apiUserSelId_s", "change", function() {
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		var selValue = $("#apiUserSelId_s").val();
		$.post(app_path_webroot + 'ControlCenter/user_api_ajax.php?'+$.param({ action: 'tokensByUser_s', username: selValue }), {  },
			function(data) {
				$("#apiByUserId_s").html(data);
				$("#apiByUserId_s").show();
				$("#apiUserSelId_s").val(selValue);
				// update Used on later since this takes some time
				$.ajax({
					url: app_path_webroot + 'ControlCenter/user_api_ajax.php?action=getAPIDateForUserJS_s&username='+$("#apiUserSelId_s").val(),
					type: "POST",
					data: { redcap_csrf_token: redcap_csrf_token },
					success: function(data) { eval(data); },
					global: false // to bypass the loading overlay
				});
				RedCapUtil.closeLoader($("#apiDummyContainer").parent());
			}
		);
	});

	$(document).delegate("#apiUserSelId", "change", function() {
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		var selValue = $("#apiUserSelId").val();
		$.post(app_path_webroot + 'ControlCenter/user_api_ajax.php?'+$.param({ action: 'tokensByUser', username: selValue }),{  },
			function(data) {
				$("#apiByUserId").html(data);
				$("#apiByUserId").show();
				$("#apiUserSelId").val(selValue);
				// update Used on later since this takes some time
				$.ajax({
					url: app_path_webroot + 'ControlCenter/user_api_ajax.php?action=getAPIDateForUserJS&username='+$("#apiUserSelId").val(),
					type: "POST",
					data: { redcap_csrf_token: redcap_csrf_token },
					success: function(data) { eval(data); },
					global: false // to bypass the loading overlay
				});
				RedCapUtil.closeLoader($("#apiDummyContainer").parent());
			}
		);
	});

	$(document).delegate("#apiProjSelId", "change", function() {
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		<?php if (defined('PROJECT_ID')) { ?>
		// If in a project, always for it to select the current project
		var selValue = '<?php echo PROJECT_ID ?>';
		// Set if viewing page via Control Center view
		var controlCenterView = 0;
		<?php } else { ?>
		// Set project_id as the one selected in the drop-down
		var selValue = $("#apiProjSelId").val();
		// Set if viewing page via Control Center view
		var controlCenterView = 1;
		<?php } ?>
		$.post(app_path_webroot + 'ControlCenter/user_api_ajax.php?'+$.param({ action: 'tokensByProj', project_id: selValue, controlCenterView: controlCenterView }),{  },
			function(data) {
				$("#apiByProjId").html(data);
				$("#apiByProjId").show();
				$("#apiProjSelId").val(selValue);
				// update Used on later since this takes some time
				$.ajax({
					url: app_path_webroot + 'ControlCenter/user_api_ajax.php?action=getAPIDateForProjJS&project_id='+$("#apiProjSelId").val(),
					type: "POST",
					data: { redcap_csrf_token: redcap_csrf_token },
					success: function(data) { eval(data); },
					global: false // to bypass the loading overlay
				});
				RedCapUtil.closeLoader($("#apiDummyContainer").parent());
			}
		);
	});


	/* super */
	$(document).delegate("a[id^=sApiDelId]", "click", function() {
		if (confirm("<?php echo js_escape2($lang['control_center_4502']) ?>")) {
			RedCapUtil.openLoader($("#apiDummyContainer").parent());
			$.post($(this).attr("href"),{  },
				function (data) {
					redrawAPITables(data);
					RedCapUtil.closeLoader($("#apiDummyContainer").parent());
				}
			);
		}
		return false;
	});

	$(document).delegate("a[id^=sApiRegenId]", "click", function() {
		if (confirm("<?php echo js_escape2($lang['control_center_4501']) ?>")) {
			apiRightsCheck_s($(this).attr("href").replace(/regenToken/, "getAPIRights"), $(this).attr("href"));
		}
		return false;
	});

	$(document).delegate("a[id^=sApiCreateId]", "click", function() {
		apiRightsCheck_s($(this).attr("href").replace(/createToken/, "getAPIRights"), $(this).attr("href"));
		return false;
	});

	$(document).delegate("a[id^=sApiViewId]", "click", function() {
		var url = $(this).attr("href");
		var title1 = "<?php echo js_escape2($lang['control_center_4503']) ?>";
		var title2 = "<?php echo js_escape2($lang['control_center_4515']) ?>";
		initDialog("dialogAJAXId");
		$("#dialogAJAXId")
			.html("<?php echo js_escape2(RCView::b($lang['control_center_4504']).' '.$lang['control_center_4526'].RCView::br().RCView::br().RCView::b($lang['control_center_324'])) ?>")
			.addClass("simpleDialog")
			.dialog({ title: title1, bgiframe: true, modal: true, width: 500, close: function() {$(this).dialog('destroy');}, buttons: {
				'Cancel': function(){ $(this).dialog('destroy'); },
				'View Super Token': function(){
					$.post(url,{  },function(data){
						$("#dialogAJAXId").remove();
						simpleDialog(data,title2,'id',750);
						$('#api_token_dialog').effect('highlight',{},3000);
					});
				}
			}});
		return false;
	});

	$(document).delegate("a[id^=sApiReassignId]", "click", function() {
		var url = $(this).attr("href");
		var title1 = "<?php echo js_escape2($lang['control_center_4511']) ?>";
		// Get dropdown list of users via AJAX call
		$.post(url+(url.indexOf('?')>0 ? '&' : '?')+'showDropDownOnly=1' ,{  },function(userOptions){
			// Get selected username
			var regex = new RegExp( "[\\?&]api_username=([^&#]*)" );
			var results = regex.exec( url );
			var thisuser = results[1];
			// Set dialog content
			initDialog("dialogAJAXId");
			$("#dialogAJAXId")
				.html("<?php echo js_escape2(RCView::b($lang['control_center_4512']).' '.$lang['control_center_4517'].RCView::br().RCView::br()."<b>".$lang['control_center_4513'].' "<span id="reassign_user_old"></span>" '.$lang['data_access_groups_ajax_14']."</b>".RCView::select(array('id'=>'reassign_user', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:5px;'), array())) ?>")
				.addClass("simpleDialog");
			$('#reassign_user').html(userOptions);
			$('#reassign_user_old').html(thisuser);
			// Remove the current user
			$('#reassign_user option[value="'+thisuser+'"]').remove();
			// Display dialog
			$("#dialogAJAXId").dialog({ title: title1, bgiframe: true, modal: true, width: 500, close: function() {$(this).dialog('destroy');}, buttons: {
				'Cancel': function(){ $(this).dialog('destroy'); },
				"<?php echo js_escape2($lang['control_center_4446']) ?>": function(){
					if ($('#reassign_user').val() == '') {
						simpleDialog("<?php echo js_escape2($lang['data_access_groups_ajax_17']) ?>");
						return;
					}
					$.post(url+(url.indexOf('?')>0 ? '&' : '?')+$.param({ new_user: $('#reassign_user').val() }),{  },function(data){
						$("#dialogAJAXId").remove();
						simpleDialog(data,title1);
						// Reload tables
						if (page == 'ControlCenter/user_api_tokens.php') {
							redrawAPITables("");
						} else {
							$('#apiUserSelId_s', '#apiUserSelId, #apiProjSelId').trigger('change');
						}
					});
				}
			}});
		});
		return false;
	});
	/* end super */


	$(document).delegate("a[id^=apiDelId]", "click", function() {
		if (confirm("<?php echo js_escape2($lang['control_center_248']) ?>")) {
			RedCapUtil.openLoader($("#apiDummyContainer").parent());
			$.post($(this).attr("href"),{ },
				function (data) {
					redrawAPITables(data);
					RedCapUtil.closeLoader($("#apiDummyContainer").parent());
				}
			);
		}
		return false;
	});
	$(document).delegate("a[id^=apiRegenId]", "click", function() {
		if (confirm("<?php echo js_escape2($lang['control_center_250']) ?>")) {
			apiRightsCheck($(this).attr("href").replace(/regenToken/, "getAPIRights"), $(this).attr("href"));
		}
		return false;
	});
	$(document).delegate("a[id^=apiCreateId]", "click", function() {
		apiRightsCheck($(this).attr("href").replace(/createToken/, "getAPIRights"), $(this).attr("href"));
		return false;
	});
	$(document).delegate("a[id^=apiViewId]", "click", function() {
		var url = $(this).attr("href");
		var title1 = "<?php echo js_escape2($lang['control_center_322']) ?>";
		var title2 = "<?php echo js_escape2($lang['control_center_333']) ?>";
		initDialog("dialogAJAXId");
		$("#dialogAJAXId")
			.html("<?php echo js_escape2(RCView::b($lang['control_center_323']).' '.$lang['control_center_332'].RCView::br().RCView::br().RCView::b($lang['control_center_324'])) ?>")
			.addClass("simpleDialog")
			.dialog({ title: title1, bgiframe: true, modal: true, width: 500, close: function() {$(this).dialog('destroy');}, buttons: {
				'Cancel': function(){ $(this).dialog('destroy'); },
				'View Token': function(){
					$.post(url,{  },function(data){
						$("#dialogAJAXId").remove();
						simpleDialog(data,title2);
						$('#api_token_dialog').effect('highlight',{},3000);
					});
				}
			}});
		return false;
	});
	$(document).delegate("a[id^=apiReassignId]", "click", function() {
		var url = $(this).attr("href");
		var title1 = "<?php echo js_escape2($lang['control_center_4443']) ?>";
		// Get dropdown list of users via AJAX call
		$.post(url+(url.indexOf('?')>0 ? '&' : '?')+'showDropDownOnly=1',{ },function(userOptions){
			// Get selected username
			var regex = new RegExp( "[\\?&]api_username=([^&#]*)" );
			var results = regex.exec( url );
			var thisuser = results[1];
			// Set dialog content
			initDialog("dialogAJAXId");
			$("#dialogAJAXId")
				.html("<?php echo js_escape2(RCView::b($lang['control_center_4444']).' '.$lang['control_center_4445'].RCView::br().RCView::br()."<b>".$lang['control_center_4448'].' "<span id="reassign_user_old"></span>" '.$lang['data_access_groups_ajax_14']."</b>".RCView::select(array('id'=>'reassign_user', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:5px;'), array())) ?>")
				.addClass("simpleDialog");
			$('#reassign_user').html(userOptions);
			$('#reassign_user_old').html(thisuser);
			// Remove the current user
			$('#reassign_user option[value="'+thisuser+'"]').remove();
			// Display dialog
			$("#dialogAJAXId").dialog({ title: title1, bgiframe: true, modal: true, width: 500, close: function() {$(this).dialog('destroy');}, buttons: {
				'Cancel': function(){ $(this).dialog('destroy'); },
				"<?php echo js_escape2($lang['control_center_4446']) ?>": function(){
					if ($('#reassign_user').val() == '') {
						simpleDialog("<?php echo js_escape2($lang['data_access_groups_ajax_17']) ?>");
						return;
					}
					$.post(url+(url.indexOf('?')>0 ? '&' : '?')+$.param({ new_user: $('#reassign_user').val() }),{  },function(data){
						$("#dialogAJAXId").remove();
						simpleDialog(data,title1);
						// Reload tables
						if (page == 'API/project_api.php') {
							redrawAPITables("");
						} else {
							$('#apiUserSelId_s', '#apiUserSelId, #apiProjSelId').trigger('change');
						}
					});
				}
			}});
		});
		return false;
	});
	<?php if ($_GET['action'] == 'deleteToken') { ?>
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		$.post(app_path_webroot + 'ControlCenter/user_api_ajax.php?'+$.param({ action: '<?php echo $_GET['action']; ?>',
			api_username: '<?php echo $_GET['api_username']; ?>',
			api_pid: '<?php echo $_GET['api_pid']; ?>' }),
		{ },
		function(data) {
			redrawAPITables(data);
			RedCapUtil.closeLoader($("#apiDummyContainer").parent());
		}
	);
	<?php } ?>
	<?php if (in_array($_GET['action'], array('createToken', 'regenToken',))) { ?>
	var apiInitURL = app_path_webroot + 'ControlCenter/user_api_ajax.php' + '?action=' +
		'<?php echo $_GET['action']; ?>' + '&api_username=' + '<?php echo $_GET['api_username']; ?>' +
		'&api_pid=' + '<?php echo $_GET['api_pid']; ?>';
	apiRightsCheck(apiInitURL.replace(/createToken|regenToken/, "getAPIRights"), apiInitURL);
	<?php } ?>

	// super
	<?php if ($_GET['action'] == 'deleteToken_s') { ?>
		RedCapUtil.openLoader($("#apiDummyContainer").parent());
		$.post(app_path_webroot + 'ControlCenter/user_api_ajax.php?'+$.param({ action: '<?php echo $_GET['action']; ?>',
			api_username: '<?php echo $_GET['api_username']; ?>' }),
		{ },
		function(data) {
			redrawAPITables(data);
			RedCapUtil.closeLoader($("#apiDummyContainer").parent());
		}
	);
	<?php } ?>
	<?php if (in_array($_GET['action'], array('createToken_s', 'regenToken_s',))) { ?>
	var apiInitURL = app_path_webroot + 'ControlCenter/user_api_ajax.php' + '?action=' +
		'<?php echo $_GET['action']; ?>' + '&api_username=' + '<?php echo $_GET['api_username']; ?>';
	apiRightsCheck_s(apiInitURL.replace(/createToken_s|regenToken_s/, "getAPIRights_s"), apiInitURL);
	<?php } ?>

	// initialize the API token tables
	redrawAPITables("");
});
</script>

<?php

if (!defined("PROJECT_ID")) {
	include 'footer.php';
}
