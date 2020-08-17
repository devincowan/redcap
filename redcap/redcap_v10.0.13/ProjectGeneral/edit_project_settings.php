<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Default message to return
$msg = "error";

// Modifying project settings
if (isset($_POST['app_title']))
{
	// Catch if user selected multiple Research options for Purpose
	if (is_array($_POST['purpose_other'])) {
		$_POST['purpose_other'] = implode(",", $_POST['purpose_other']);
	} elseif ($_POST['purpose'] != '1' && $_POST['purpose'] != '2') {
		$_POST['purpose_other'] == "";
	}
	// Do not allow normal users to edit project settings (scheduling, primary use) if in Production, so reset values if somehow were submitted
	if ($status > 0 && !$super_user) {
		$_POST['scheduling']  = $scheduling;
		$_POST['repeatforms'] = $repeatforms;
		$_POST['randomization'] = $randomization;
	}
	$_POST['surveys_enabled'] = (isset($_POST['surveys_enabled']) && is_numeric($_POST['surveys_enabled'])) ? $_POST['surveys_enabled'] : '0';
	$_POST['randomization'] = (isset($_POST['randomization']) && is_numeric($_POST['randomization'])) ? $_POST['randomization'] : '0';
	$_POST['scheduling'] = (isset($_POST['scheduling']) && is_numeric($_POST['scheduling'])) ? $_POST['scheduling'] : '0';
	// Update redcap_projects table
	$sql = "update redcap_projects set
			scheduling = {$_POST['scheduling']},
			repeatforms = {$_POST['repeatforms']},
			purpose = {$_POST['purpose']},
			purpose_other = ".checkNull($_POST['purpose_other']).",
			project_pi_firstname = '".db_escape($_POST['project_pi_firstname'])."',
			project_pi_mi = '".db_escape($_POST['project_pi_mi'])."',
			project_pi_lastname = '".db_escape($_POST['project_pi_lastname'])."',
			project_pi_email = '".db_escape($_POST['project_pi_email'])."',
			project_pi_alias = '".db_escape($_POST['project_pi_alias'])."',
			project_pi_username = '".db_escape(isset($_POST['project_pi_username']) ? $_POST['project_pi_username'] : '')."',
			project_irb_number = '".db_escape($_POST['project_irb_number'])."',
			project_grant_number = '".db_escape(isset($_POST['project_grant_number']) ? $_POST['project_grant_number'] : '')."',
			app_title = '".db_escape($_POST['app_title'])."',
			surveys_enabled = {$_POST['surveys_enabled']},
			randomization = {$_POST['randomization']},
			project_note = ".checkNull(trim($_POST['project_note']))."
			where project_id = $project_id";
	if (db_query($sql))
	{
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project settings");
		// Set msg as successful
		$msg = "projectmodified";
	}
}


// Making customizations (when in production, only super users can modify)
elseif (isset($_GET['action']) && $_GET['action'] == 'customize')
{
	// Check if Data Resolution Workflow was just enabled
	$drwWasEnabled = (isset($_POST['data_resolution_enabled']) && $_POST['data_resolution_enabled'] == '2' && $data_resolution_enabled != '2');
	// Customization fields
	$display_today_now_button = (isset($_POST['display_today_now_button']) && $_POST['display_today_now_button'] == 'on') ? '1' : (isset($_POST['display_today_now_button']) && is_numeric($_POST['display_today_now_button']) ? $_POST['display_today_now_button'] : '0');
	$require_change_reason = (isset($_POST['require_change_reason']) && $_POST['require_change_reason'] == 'on') ? '1' : (isset($_POST['require_change_reason']) && is_numeric($_POST['require_change_reason']) ? $_POST['require_change_reason'] : '0');
	$history_widget_enabled = (isset($_POST['history_widget_enabled']) && $_POST['history_widget_enabled'] == 'on') ? '1' : (isset($_POST['history_widget_enabled']) && is_numeric($_POST['history_widget_enabled']) ? $_POST['history_widget_enabled'] : '0');
	$secondary_pk = (isset($_POST['secondary_pk']) && isset($Proj->metadata[$_POST['secondary_pk']])) ? $_POST['secondary_pk'] : "";
	$custom_record_label = (isset($_POST['custom_record_label'])) ? trim($_POST['custom_record_label']) : "";
	$order_id_by = (isset($_POST['order_id_by']) && !$longitudinal) ? $_POST['order_id_by'] : "";
	$data_resolution_enabled = (isset($_POST['data_resolution_enabled']) && is_numeric($_POST['data_resolution_enabled'])) ? $_POST['data_resolution_enabled'] : '0';
	$field_comment_edit_delete = ($data_resolution_enabled == '1' && isset($_POST['field_comment_edit_delete_chkbx']) && $_POST['field_comment_edit_delete_chkbx'] == 'on') ? '1' : '0';
	$_POST['pdf_custom_header_text'] = (isset($_POST['pdf_custom_header_text']) && trim($_POST['pdf_custom_header_text']) != System::confidential) ? "'".db_escape($_POST['pdf_custom_header_text'])."'" : 'null';
	$_POST['pdf_show_logo_url'] = (isset($_POST['pdf_show_logo_url']) && $_POST['pdf_show_logo_url'] == '0') ? '0' : '1';
	$_POST['pdf_hide_secondary_field'] = (isset($_POST['pdf_hide_secondary_field']) && $_POST['pdf_hide_secondary_field'] == '1') ? '1' : '0';
	$_POST['pdf_hide_record_id'] = (isset($_POST['pdf_hide_record_id']) && $_POST['pdf_hide_record_id'] == '1') ? '1' : '0';
	$secondary_pk_display_value = (isset($_POST['secondary_pk_display_value']) && $_POST['secondary_pk_display_value'] == 'on') ? '1' : '0';
	$secondary_pk_display_label = (isset($_POST['secondary_pk_display_label']) && $_POST['secondary_pk_display_label'] == 'on' && $secondary_pk_display_value == '1') ? '1' : '0';
    $file_upload_vault_enabled = (isset($_POST['file_upload_vault_enabled']) && $_POST['file_upload_vault_enabled'] == 'on') ? '1' : '0';
    $file_upload_versioning_enabled = (isset($_POST['file_upload_versioning_enabled']) && $_POST['file_upload_versioning_enabled'] == 'on') ? '1' : '0';
	$record_locking_pdf_vault_enabled = (isset($_POST['record_locking_pdf_vault_enabled']) && $_POST['record_locking_pdf_vault_enabled'] == 'on') ? '1' : '0';

	// Update redcap_projects table
	$sql = "update redcap_projects set
			history_widget_enabled = $history_widget_enabled,
			display_today_now_button = $display_today_now_button,
			require_change_reason = $require_change_reason,
			secondary_pk = ".checkNull($secondary_pk).",
			secondary_pk_display_value = ".checkNull($secondary_pk_display_value).",
			secondary_pk_display_label = ".checkNull($secondary_pk_display_label).",
			custom_record_label = ".checkNull($custom_record_label).",
			order_id_by = ".checkNull($order_id_by).",
			data_entry_trigger_url = ".checkNull(isset($_POST['data_entry_trigger_url']) ? $_POST['data_entry_trigger_url'] : null).",
			missing_data_codes = ".checkNull(isset($_POST['missing_data_codes']) ? $_POST['missing_data_codes'] : null).",
			data_resolution_enabled = $data_resolution_enabled,
			field_comment_edit_delete = $field_comment_edit_delete,
			pdf_custom_header_text = ".$_POST['pdf_custom_header_text'].",
			pdf_show_logo_url = ".checkNull($_POST['pdf_show_logo_url']).",
			pdf_hide_secondary_field = ".checkNull($_POST['pdf_hide_secondary_field']).",
			pdf_hide_record_id = ".checkNull($_POST['pdf_hide_record_id']).",
			file_upload_vault_enabled = ".checkNull($file_upload_vault_enabled).",
			file_upload_versioning_enabled = ".checkNull($file_upload_versioning_enabled).",
			record_locking_pdf_vault_enabled = ".checkNull($record_locking_pdf_vault_enabled)."
			where project_id = $project_id";
	if (db_query($sql))
	{
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Make project customizations");
		// Return msg in query string for notification purposes
		if ($drwWasEnabled) {
			// Set msg that DRW was just enabled
			$msg = "data_resolution_enabled";
		} else {
			// Set generic msg as successful
			$msg = "projectmodified";
		}
	}
}



// Redirect back
redirect(APP_PATH_WEBROOT."ProjectSetup/index.php?pid=$project_id&msg=$msg");
