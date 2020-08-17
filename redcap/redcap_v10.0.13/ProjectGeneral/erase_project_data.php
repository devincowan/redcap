<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if ($_POST['action'] == "erase_data" && (($user_rights['design'] && $status < 1) || $super_user))
{
	// Set up all actions as a transaction to ensure everything is done here
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	// "Delete" edocs for 'file' field type data (keep its record in table so actual files can be deleted later from web server, if needed)
	$sql = "update redcap_metadata m, redcap_data d, redcap_edocs_metadata e
			set e.delete_date = '".NOW."' where m.project_id = $project_id
			and m.project_id = d.project_id and e.project_id = m.project_id and m.element_type = 'file'
			and d.field_name = m.field_name and d.value = e.doc_id";
	$q5 = db_query($sql);
	// Also delete all File Repository files from edocs_metadata
	$sql = "update redcap_docs d, redcap_docs_to_edocs t, redcap_edocs_metadata e
			set e.delete_date = '".NOW."' where d.project_id = $project_id
			and t.docs_id = d.docs_id and e.doc_id = t.doc_id and d.export_file = 1";
	db_query($sql);
	// Delete docs
	$q4 = db_query("delete from redcap_docs where project_id = $project_id and export_file = 1");
	// Delete project data
	$q1 = db_query("delete from redcap_data where project_id = $project_id");
	// Delete calendar events
	$q2 = db_query("delete from redcap_events_calendar where project_id = $project_id");
	// Delete logged events (only delete data-related logs)
	$sql = "delete from ".Logging::getLogEventTable($project_id)." where project_id = $project_id and object_type not like '%\_rights'
			and (event in ('UPDATE', 'INSERT', 'DELETE', 'DATA_EXPORT', 'DOC_UPLOAD', 'DOC_DELETE')
			or (event = 'MANAGE' and description = 'Download uploaded document')
			or (event = 'MANAGE' and description = 'Randomize record')
			or (event = 'MANAGE' and description = 'Automatically schedule survey invitation')
			or (event = 'MANAGE' and description = 'Automatically remove scheduled survey invitation')
			or (event = 'MANAGE' and description = 'Delete scheduled survey invitation')
			or (event = 'MANAGE' and description = 'Modify send time for scheduled survey invitation'))";
	$q3 = db_query($sql);
	// Delete locking data
	$q6 = db_query("delete from redcap_locking_data where project_id = $project_id");
	// Delete esignatures
	$q10 = db_query("delete from redcap_esignatures where project_id = $project_id");
	// Delete survey-related info (response tracking, emails, participants) but not actual survey structure
	$survey_ids = pre_query("select survey_id from redcap_surveys where project_id = $project_id");
	// Defaults
	$q7 = $q8 = $q9 = $q12 = $q11 = true;
	if ($survey_ids != "''") {
		// Delete "participants" for follow-up surveys only (do NOT delete public survey "participants" or initial survey participants)
		$q7 = db_query("delete from redcap_surveys_participants where survey_id in ($survey_ids) and participant_email = ''
						and (participant_phone = '' or participant_phone is null)");
		// Delete emails to those in Participant List
		$q8 = db_query("delete from redcap_surveys_emails where survey_id in ($survey_ids)");
		// Delete survey responses
		$response_ids = pre_query("select r.response_id from redcap_surveys_response r, redcap_surveys_participants p
								   where p.participant_id = r.participant_id and p.survey_id in ($survey_ids)");
		if ($response_ids != "''") {
			// First get list of participant_ids from redcap_surveys_response
			$response_participant_ids = pre_query("select distinct participant_id from redcap_surveys_response where response_id in ($response_ids)");
			// Delete all participants connected to a record so that some are not left orphaned
			if ($response_participant_ids != "''") {
				db_query("delete from redcap_surveys_participants where participant_id in ($response_participant_ids) and participant_email is not null");
			}
			// Now delete from redcap_surveys_response
			$q9 = db_query("delete from redcap_surveys_response where response_id in ($response_ids)");
		}
		// Remove all survey invitations that were queued for records in this project
		$ss_ids = pre_query("select ss_id from redcap_surveys_scheduler where survey_id in ($survey_ids)");
		if ($ss_ids != "''") {
			$q11 = db_query("delete from redcap_surveys_scheduler_queue where ss_id in ($ss_ids)");
		}
	}
	// Remove any randomization assignments
	$q12 = db_query("update redcap_randomization_allocation a, redcap_randomization r set a.is_used_by = null
					 where r.project_id = $project_id and r.rid = a.rid");
	// Delete all records in redcap_data_quality_status
	$q13 = db_query("delete from redcap_data_quality_status where project_id = $project_id");
	// Delete all records in redcap_ddp_records
	$q14 = db_query("delete from redcap_ddp_records where project_id = $project_id");
	// Delete all records in redcap_surveys_queue_hashes
	$q15 = db_query("delete from redcap_surveys_queue_hashes where project_id = $project_id");
	// Delete records in redcap_new_record_cache
	$q16 = db_query("delete from redcap_new_record_cache where project_id = $project_id");
	// Delete rows in redcap_surveys_phone_codes
	$q17 = db_query("delete from redcap_surveys_phone_codes where project_id = $project_id");
	// Delete rows in redcap_surveys_pdf_archive
	db_query("delete a.* from redcap_surveys_pdf_archive a, redcap_surveys s where s.survey_id = a.survey_id and s.project_id = $project_id");
    // Delete rows in alerts tables
    db_query("delete s.* from redcap_alerts a, redcap_alerts_sent s where s.alert_id = a.alert_id and a.project_id = $project_id");
    db_query("delete s.* from redcap_alerts a, redcap_alerts_recurrence s where s.alert_id = a.alert_id and a.project_id = $project_id");
	// RESET RECORD COUNT CACHE: Remove the count of records in the cache table.
	Records::resetRecordCountAndListCache($project_id);

	// Commit changes
	if ( !$q1 || !$q2 || !$q3 || !$q4 || !$q5 || !$q6 || !$q7 || !$q8 || !$q9 || !$q10 || !$q11
		|| !$q12 || !$q13 || !$q14 || !$q15 || !$q16 || !$q17) {
		// Errors occurred
		db_query("ROLLBACK");
		// Give unsuccessful response back
		exit("0");
	} else {
		// All good
		db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");
		// Logging
		Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Erase all data");
		// Give affirmative response back
		exit("1");
	}

}

// Not supposed to be here, so redirect
redirect(APP_PATH_WEBROOT_PARENT);
