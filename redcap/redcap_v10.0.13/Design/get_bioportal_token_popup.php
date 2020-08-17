<?php


require dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must be accessed via POST AJAX
if (!$isAjax || $_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Default
$error_msg = '';

// SAVE TOKEN
if (SUPER_USER && isset($_POST['bioportal_api_token']) && !empty($_POST['bioportal_api_token']))
{
	// First, validate the token
	$bioportal_api_token = $_POST['bioportal_api_token'];
	$results = BioPortal::getOntologyList();
	if (empty($results)) {
		// ERROR
		unset($_POST['bioportal_api_token']);
		$error_msg = RCView::div(array('class'=>'red', 'style'=>'margin:15px 0;font-size:14px;'), $lang['design_595']);
	} else {
		// SUCCESS
		// Save the token
		$sql = "update redcap_config set value = '".db_escape($_POST['bioportal_api_token'])."' where field_name = 'bioportal_api_token'";
		db_query($sql);
		// Title
		$title = $lang['design_593'];
		// Content
		$content = RCView::div(array('style'=>'color:green;font-size:14px;'), $lang['design_594']);
	}
}

// DISPLAY ERROR AND HOW TO GET TOKEN
if (!isset($_POST['bioportal_api_token']))
{
	// Title
	$title = $lang['design_586'];
	// Content
	$content = $lang['design_587'];
	if (SUPER_USER) {
		// Allow super users to enter token
		$content .= $error_msg .
					RCView::div(array('style'=>'color:#800000;margin-top:15px;'),
						$lang['design_588'] . " " .
						RCView::a(array('href'=>BioPortal::$SIGNUP_URL, 'target'=>'_blank', 'style'=>'font-size:14px;text-decoration:underline;'), $lang['design_589']) .
						" " . $lang['design_590']
					) .
					RCView::div(array('style'=>'color:#800000;margin:10px 0 10px;font-weight:bold;'),
						$lang['design_592'] .
						RCView::text(array('id'=>'bioportal_api_token', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:10px;width:200px;')) .
						RCView::button(array('id'=>'bioportal_api_token_btn', 'class'=>'jqbuttonmed', 'style'=>'color:#333;margin-left:10px;', 'onclick'=>'saveBioPortalToken();'), $lang['design_591'])
					);
	}
	$content = RCView::div(array('style'=>'font-size:14px;'), $content);
}

// Return JSON
print json_encode_rc(array('success'=>($error_msg == '' ? '1' : '0'), 'content'=>$content, 'title'=>$title));