<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Script can only be accessed via ajax
if (!$isAjax) exit("ERROR!");

/**
 * CHANGE THE PROJECT STATUS
 */

## ACTION: prod/inactive/archived=>dev
if (isset($_POST['moveToDev']) && $status > 0 && $super_user)
{
	// Remove production date and set
	$sql = "update redcap_projects set status = 0, draft_mode = 0, production_time = NULL, inactive_time = NULL
			where project_id = $project_id";
	if (db_query($sql))
	{
		// If project is in Draft Mode, then perform a DD snapshot in order to be able to restore the drafted changes later
		if ($draft_mode > 0) MetaData::createDataDictionarySnapshot();
		// Make sure there are no residual fields from Draft Mode
		db_query("delete from redcap_metadata_temp where project_id = $project_id");
		db_query("delete from redcap_metadata_prod_revisions where project_id = $project_id");
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Move project back to Development status");
		exit("1");
	}
	exit("0");
}


## ACTION: Lock or unlock all data in project for Analysis/Cleanup status
if (isset($_POST['data_locked']) && $status == '2')
{
	// Remove production date and set
	$sql = "update redcap_projects set data_locked = '".db_escape($_POST['data_locked'])."' where project_id = $project_id";
	if (db_query($sql))
	{
		$descrip = ($_POST['data_locked'] == '0') ? "Project data set to Editable mode" : "Project data set to Read-only/Locked mode";
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id",$descrip);
		exit("1");
	}
	exit("0");
}


## ACTION: completed=>*
if (isset($_POST['restore_completed']) && $super_user)
{
	// Remove production date and set
	$sql = "update redcap_projects set completed_time = NULL, completed_by = NULL where project_id = $project_id";
	if (db_query($sql))
	{
		// Logging
		if ($status == '0') {
			$logstatus = "Development";
		} elseif ($status == '1') {
			$logstatus = "Production";
		} else {
			$logstatus = "Analysis/Cleanup";
		}
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Project moved from Completed status back to $logstatus status");
		exit("1");
	}
	exit("0");
}




## ACTIONS: dev=>prod, prod=>inactive, inactive=>prod, *=>completed
elseif ($_POST['do_action_status'])
{
	// Set to Inactive
	if ($status == 1 && $_POST['archive'] == 0) {
		$newstatus = 2;
		// Set timestamp for inactivity
		db_query("update redcap_projects set inactive_time = '".NOW."' where project_id = $project_id");
		// Logging
		Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Move project to Analysis/Cleanup status");

	// Set to Completed
	} elseif ($_POST['archive'] == 1) {
		$newstatus = "$status, completed_time = '".NOW."', completed_by = '".db_escape(USERID)."'";
		// Logging
		Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Project marked as Completed");
	// Set to Production
	} else {
		$newstatus = 1;
		// If dev=>prod, then delete ALL data for this project and reset all logging, docs, etc.
		if ($status == 0) {
			// If a normal user, then make sure that normal users can push to prod
			if (!$super_user && $superusers_only_move_to_prod == '1') exit('0');
			// Delete project data and all documents and calendar events, if user checked the checkbox to do so
			if ($_POST['delete_data'])
			{
				// "Delete" edocs for 'file' field type data (keep its record in table so actual files can be deleted later from web server, if needed)
				$sql = "select e.doc_id from redcap_metadata m, redcap_data d, redcap_edocs_metadata e where m.project_id = $project_id
						and m.project_id = d.project_id and e.project_id = m.project_id and m.element_type = 'file'
						and d.field_name = m.field_name and d.value = e.doc_id";
				$fileFieldEdocIds = pre_query($sql);
				db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where project_id = $project_id and doc_id in ($fileFieldEdocIds)");
				// Delete project data
				db_query("delete from redcap_data where project_id = $project_id");
				// Delete calendar events
				db_query("delete from redcap_events_calendar where project_id = $project_id");
				// Delete logged events (only delete data-related logs)
				$sql = "delete from ".Logging::getLogEventTable($project_id)." where project_id = $project_id and object_type not like '%\_rights'
						and (event in ('UPDATE', 'INSERT', 'DELETE', 'DATA_EXPORT', 'DOC_UPLOAD', 'DOC_DELETE')
						or (event = 'MANAGE' and description = 'Download uploaded document')
						or (event = 'MANAGE' and description = 'Randomize record')
						or (event = 'MANAGE' and description = 'Automatically schedule survey invitation')
						or (event = 'MANAGE' and description = 'Automatically remove scheduled survey invitation')
						or (event = 'MANAGE' and description = 'Delete scheduled survey invitation')
						or (event = 'MANAGE' and description = 'Modify send time for scheduled survey invitation'))";
				db_query($sql);
				// Delete docs (but only export files, not user-uploaded files)
				db_query("delete from redcap_docs where project_id = $project_id and export_file = 1");
				// Delete locking data
				db_query("delete from redcap_locking_data where project_id = $project_id");
				// Delete esignatures
				db_query("delete from redcap_esignatures where project_id = $project_id");
				// Delete survey-related info (response tracking, emails, participants) but not actual survey structure
				$survey_ids = pre_query("select survey_id from redcap_surveys where project_id = $project_id");
				if ($survey_ids != "''") {
					$participant_ids = pre_query("select participant_id from redcap_surveys_participants where survey_id in ($survey_ids)");
					db_query("delete from redcap_surveys_emails where survey_id in ($survey_ids)");
					if ($participant_ids != "''") {
						// First get list of participant_ids from redcap_surveys_response
						$response_participant_ids = pre_query("select distinct participant_id from redcap_surveys_response where participant_id in ($participant_ids)");
						// Delete all participants connected to a record so that some are not left orphaned
						if ($response_participant_ids != "''") {
							db_query("delete from redcap_surveys_participants where participant_id in ($response_participant_ids) and participant_email is not null");
						}
						// Now delete from redcap_surveys_response
						db_query("delete from redcap_surveys_response where participant_id in ($participant_ids)");
					}
				}
				// Delete all records in redcap_data_quality_status
				db_query("delete from redcap_data_quality_status where project_id = $project_id");
				// Delete all records in redcap_ddp_records
				db_query("delete from redcap_ddp_records where project_id = $project_id");
				// Delete all records in redcap_surveys_queue_hashes
				db_query("delete from redcap_surveys_queue_hashes where project_id = $project_id");
				// Delete records in redcap_new_record_cache
				db_query("delete from redcap_new_record_cache where project_id = $project_id");
				// Delete rows in redcap_surveys_phone_codes
				db_query("delete from redcap_surveys_phone_codes where project_id = $project_id");
				// Delete rows in redcap_surveys_pdf_archive
				db_query("delete a.* from redcap_surveys_pdf_archive a, redcap_surveys s where s.survey_id = a.survey_id and s.project_id = $project_id");
                // Delete rows in alerts tables
                db_query("delete s.* from redcap_alerts a, redcap_alerts_sent s where s.alert_id = a.alert_id and a.project_id = $project_id");
                db_query("delete s.* from redcap_alerts a, redcap_alerts_recurrence s where s.alert_id = a.alert_id and a.project_id = $project_id");
				// RESET RECORD COUNT CACHE: Remove the count of records in the cache table.
				Records::resetRecordCountAndListCache($project_id);
			}
			// If not deleting all data BUT using the randomization module, DELETE ONLY the randomization field's data
			elseif ($randomization && Randomization::setupStatus())
			{
				// Get randomization setup values first
				$randAttr = Randomization::getRandomizationAttributes();
				if ($randAttr !== false) {
					Randomization::deleteSingleFieldData($randAttr['targetField']);
				}
			}
			// Add production date
			db_query("update redcap_projects set production_time = '".NOW."', inactive_time = NULL where project_id = $project_id");
			// Set the to-do list item to "completed" (if one exists)
			ToDoList::updateTodoStatus($_GET['pid'], 'move to prod','completed');
			// Logging
			Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Move project to Production status");
		// Moving BACK to production from inactive
		} else {
			// Logging
			Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Return project to Production from Analysis/Cleanup status");
		}
	}
	// Query
	$sql = "update redcap_projects set status = $newstatus where project_id = $project_id";
	// Run query and set response
	print db_query($sql) ? $newstatus : 0;
	exit;
}

// Not supposed to be here, so redirect
redirect(APP_PATH_WEBROOT_PARENT);
