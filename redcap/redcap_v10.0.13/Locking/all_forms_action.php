<?php


include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$response			= 0; //default response
$action	  	   		= $_GET['action'];
$id 				= $_GET['id'];
$arm = (!isset($_GET['arm']) || !isset($Proj->events[$_GET['arm']])) ? $Proj->firstArmNum : getArm();
$arm_name = $Proj->events[$arm]['name'];

// Set up logging details for this event
$log = "Record: $id";
// Show arm name if multiple arms exist
if ($multiple_arms) {
	$log .= " - {$lang['global_08']} $arm: $arm_name";
}

// LOCK ENTIRE RECORD
if ($action == "lock")
{
	$locking = new Locking();
	if ($locking->lockWholeRecord($project_id, $id, $arm)) {
		// If record-locking PDF vault feature is enabled, then store PDF in File Repository and external server
		$archivedPdf = null;
		if ($record_locking_pdf_vault_filesystem_type != '' && $record_locking_pdf_vault_enabled) {
			$archivedPdf = Files::archiveRecordAsPDF($project_id, $id, $arm);
			if ($archivedPdf) $log .= "\n(PDF of locked record was stored in File Repository)";
		}
		// Log the event
		Logging::logEvent("","redcap_locking_record","LOCK_RECORD",$id,$log,"Lock entire record");
		if (isDev() || $archivedPdf !== false) $response = 1;
	}
}
// UNLOCK ENTIRE RECORD
elseif ($action == "unlock")
{
	$locking = new Locking();
	if ($locking->unlockWholeRecord($project_id, $id, $arm)) {
		Logging::logEvent("","redcap_locking_record","LOCK_RECORD",$id,$log,"Unlock entire record");
		$response = 1;
	}
}

// Send response
print $response;