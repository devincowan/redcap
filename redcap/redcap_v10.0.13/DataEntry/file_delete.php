<?php



// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}


// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
$instance = (isset($_GET['instance']) && is_numeric($_GET['instance']) && $_GET['instance'] > 1) ? $_GET['instance'] : 1;
$file_upload_delete_reason = (Files::fileUploadPasswordVerifyExternalStorageEnabledProject($project_id) && isset($_POST['file_upload_delete_reason']) && trim($_POST['file_upload_delete_reason']) != '') ? trim($_POST['file_upload_delete_reason']) : '';

// Older version of a file via File Version History in Data History popup
$version_log = $version_log2 = '';
$delete_data_value = true;
if (isset($_GET['doc_version']) && is_numeric($_GET['doc_version']) && isset($_GET['doc_version_hash']))
{
    if ($_GET['doc_version_hash'] != Files::docIdHash($_GET['id']."v".$_GET['doc_version'])) {
        exit("{$lang['global_01']}!");
    }
    $version_log = " (V{$_GET['doc_version']})";
    $version_log2 = " - V{$_GET['doc_version']}";
    $delete_data_value = false;
}
// Surveys only: Perform double checking to make sure the survey participant has rights to this file
elseif (isset($_GET['s']) && !empty($_GET['s']))
{
	DataEntry::checkSurveyFileRights();
}
// Non-surveys: Check form-level rights and DAGs to ensure user has access to this file
elseif (!isset($_GET['s']) || empty($_GET['s']))
{
	DataEntry::checkFormFileRights();
}


if (is_numeric($_GET['event_id']) && is_numeric($_GET['id']) && isset($Proj->metadata[$_GET['field_name']]))
{
	// If user is a double data entry person, append --# to record id when saving
	if (isset($user_rights) && $double_data_entry && $user_rights['double_data'] != 0)
	{
		$_GET['record'] .= "--" . $user_rights['double_data'];
	}

	// Set the file as "deleted" in redcap_edocs_metadata table, but don't really delete the file or the table entry.
	// NOTE: If *somehow* another record has the same doc_id attached to it (not sure how this would happen), then do NOT
	// set the file to be deleted (hence the left join of d2).
	if ($delete_data_value) {
        $sql = "update redcap_edocs_metadata e, redcap_data d left join redcap_data d2
				on d2.project_id = d.project_id and d2.value = d.value and d2.field_name = d.field_name and d2.record != d.record
				set e.delete_date = '" . NOW . "'
				where e.project_id = " . PROJECT_ID . " and e.project_id = d.project_id
				and d.field_name = '{$_GET['field_name']}' and d.value = e.doc_id and d.record = '" . db_escape($_GET['record']) . "'
				and d.instance " . ($instance == '1' ? "is null" : "= '$instance'") . "
				and e.delete_date is null and d2.project_id is null and e.doc_id = '" . $_GET['id'] . "'";
    } else {
        $sql = "UPDATE redcap_edocs_metadata SET delete_date = '" . NOW . "' WHERE doc_id = " . $_GET['id'];
    }
	$q = db_query($sql);

	// Delete data for this field from data table
    if ($delete_data_value) {
        $sql = "DELETE FROM redcap_data WHERE record = '" . db_escape($_GET['record']) . "' AND field_name = '{$_GET['field_name']}'
			AND project_id = $project_id AND event_id = {$_GET['event_id']} and instance " . ($instance == '1' ? "is null" : "= '$instance'");
        $q = db_query($sql);
    }
	Logging::logEvent($sql,"redcap_data","doc_delete",$_GET['record'],$_GET['field_name'],"Delete uploaded document".$version_log,
						"", "", "", true, null, $_GET['instance']);

    if ($file_upload_delete_reason != '') {
        // Log this extra event (but not for surveys)
        defined("NOAUTH") or Logging::logEvent("", "redcap_data", "update", $_GET['record'], "Reason for document deletion (field = '{$_GET['field_name']}'$version_log2):\n$file_upload_delete_reason", "",
            "", "", "", true, null, $_GET['instance']);
    }

	// Boolean if a signature file upload type
	$signature_field = ($Proj->metadata[$_GET['field_name']]['element_validation_type'] == 'signature') ? '1' : '0';

	// Link text
	$file_link_text = ($signature_field) ? $lang['form_renderer_31'] : $lang['form_renderer_23'];
	$file_link_icon = ($signature_field) ? '<i class="fas fa-signature mr-1"></i>' : '<i class="fas fa-upload mr-1 fs12"></i>';

	// Send back HTML for uploading a new file (since this one has been removed)
	print  '<a href="javascript:;" class="fileuploadlink" onclick="filePopUp(\''.$_GET['field_name'].'\','.$signature_field.',0);return false;">'.$file_link_icon.$file_link_text.'</a>';

}