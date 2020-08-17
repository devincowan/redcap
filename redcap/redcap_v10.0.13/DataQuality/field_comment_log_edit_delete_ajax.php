<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Page is only usable if Field Comment Log is enabled
if ($data_resolution_enabled != '1') exit('0');
// Page is only usable if Field Comment Log editing/deleting is enabled
if (!$field_comment_edit_delete) exit('0');
// Instantiate DataQuality object
$dq = new DataQuality();
// Defaults
$dialog_title = $dialog_html = $action_button = '';
$close_button = $lang['calendar_popup_01'];

// Delete
if (isset($Proj->forms[$_POST['form_name']]) && $_POST['action'] == 'delete' && $_POST['confirmDelete'] == '0'
	// Make sure user has at least readonly or edit rights to this form
	&& $user_rights['forms'][$_POST['form_name']] != '0')
{
	if (!$dq->deleteFieldComment($_POST['res_id'])) {
		exit('0');
	}
}
// Confirm delete
elseif ($_POST['action'] == 'delete')
{
	$dialog_title = $lang['dataqueries_284'];
	$dialog_html = $lang['dataqueries_285'];
	$action_button = $lang['global_19'];
	$close_button = $lang['global_53'];
}
// Edit
if (isset($Proj->forms[$_POST['form_name']]) && isset($_POST['comment']) && $_POST['action'] == 'edit'
	// Make sure user has at least readonly or edit rights to this form
	&& $user_rights['forms'][$_POST['form_name']] != '0')
{
	if (!$dq->editFieldComment($_POST['res_id'], $_POST['comment'])) {
		exit('0');
	}
}

// Output JSON
print json_encode_rc(array('html'=>$dialog_html, 'title'=>$dialog_title, 'actionButton'=>$action_button, 'closeButton'=>$close_button));
