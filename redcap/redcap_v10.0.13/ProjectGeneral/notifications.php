<?php


if (isset($_GET['pnid']) || isset($_GET['pid'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;

// Set up email to be sent
$email = new Message();


// Determine which type of email to send
if (isset($_GET['type']))
{
	// Catch if user selected multiple Research options for Purpose
	if (isset($_POST['purpose_other']))
	{
		if (is_array($_POST['purpose_other'])) {
			$_POST['purpose_other'] = implode(",", $_POST['purpose_other']);
		} elseif ($_POST['purpose_other'] != '1' && $_POST['purpose_other'] != '2') {
			$_POST['purpose_other'] == "";
		}
	}

	switch ($_GET['type'])
	{
		// Send email request to super user to delete project
		case 'delete_project':
			// If the project contains 0 records, then delete now
			if (Records::getRecordCount(PROJECT_ID) == 0) {
				// Flag it for deletion in 30 days. Add "date_deleted" timestamp to project
				$sql = "update redcap_projects set date_deleted = '".NOW."'
						where project_id = $project_id and date_deleted is null";
				if (db_query($sql)) {
					// Logging
					Logging::logEvent($sql,"redcap_projects","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,"Delete project");
					// Return message that it was deleted just now
					print 	RCView::div(array('class'=>'darkgreen'),
								$lang['design_720']
							);
				}
			}
			// If the project has some records, then request super user delete it
			else {
				$db = new RedCapDB();
				$userInfo = $db->getUserInfoByUsername($userid);
				$ui_id = $userInfo->ui_id;
				$projInfo = $db->getProject($project_id);
				$request_to = $projInfo->project_contact_email;
				$todo_type = "delete project";
				$project_url = APP_PATH_WEBROOT.'index.php?pid='.$project_id;
				$action_url = APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/other_functionality.php?pid=$project_id&action=prompt_delete_window";
				$request_id = ToDoList::insertAction($ui_id, $request_to, $todo_type, $action_url, $project_id);
				$action_url .= "&request_id=".$request_id;
				$email->setFrom($user_email);
				$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
				$email->setTo($project_contact_email);
				$emailSubject  =   "[REDCap] {$lang['email_admin_18']} (PID $project_id)";
				$emailContents =   "{$lang['global_21']}<br><br>
									{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
									(<a href='mailto:$user_email'>$user_email</a>)
									{$lang['email_admin_17']}
									<b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>
									{$lang['email_admin_05']}<br>
									<a href='".$action_url."'>{$lang['email_admin_18']}</a>";
				// Finalize email
				$email->setBody("<html><head><title>$emailSubject</title></head><body style='font-family:arial,helvetica;font-size:10pt;'>$emailContents</body></html>");
				$email->setSubject($emailSubject);
				// Send email and notify with "0" if does not send
				if ($send_emails_admin_tasks) $email->send();
				// Log this event
				Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to delete project");
			}
			exit;
		// Send email request to super user to move project to PRODUCTION
		case 'move_to_prod':
			//create todo in redcap_todo_list
			$db = new RedCapDB();
			$userInfo = $db->getUserInfoByUsername($userid);
			$ui_id = $userInfo->ui_id;
			$projInfo = $db->getProject($project_id);
			$request_to = $projInfo->project_contact_email;
			$todo_type = "move to prod";
			$project_url = APP_PATH_WEBROOT.'index.php?pid='.$project_id;
			$action_url = APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$project_id&type={$_GET['type']}&delete_data={$_GET['delete_data']}&user_email=$user_email";
			$request_id = ToDoList::insertAction($ui_id, $request_to, $todo_type, $action_url, $project_id);
			$action_url .= "&request_id=$request_id";
			$email->setFrom($user_email);
			$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
			$email->setTo($project_contact_email);
			$emailSubject  =   "[REDCap] {$lang['email_admin_01']}";
			$emailContents =   "{$lang['global_21']}<br><br>
								{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
								(<a href='mailto:$user_email'>$user_email</a>)
								{$lang['email_admin_04']}
								<b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>
								{$lang['email_admin_05']}<br>
								<a href='$action_url'>{$lang['email_admin_06']}</a>";
			// Finalize email
			$email->setBody("<html><head><title>$emailSubject</title></head><body style='font-family:arial,helvetica;font-size:10pt;'>$emailContents</body></html>");
			$email->setSubject($emailSubject);
			// Send email and notify with "0" if does not send
			If ($send_emails_admin_tasks) {
			print $email->send() ? "1" : "0";
			}else{
				print "1";
			}
			// Log this event
			Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to move project to production status");
			exit;

		// Send email confirmation to user that project was moved to PRODUCTION
		case 'move_to_prod_user':
			//update redcap_todo_list first
			ToDoList::updateTodoStatus($_GET['pid'], 'move to prod','completed');
			$_GET['this_user_email'] = html_entity_decode($_GET['this_user_email'], ENT_QUOTES);
			$email->setFrom($project_contact_email);
			$email->setFromName($GLOBALS['project_contact_name']);
			$email->setTo($_GET['this_user_email']);
			$emailSubject  =   "[REDCap] {$lang['email_admin_07']}";
			$emailContents =   "{$lang['global_21']}<br><br>
								{$lang['email_admin_08']}
								<b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b>.<br><br>
								<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/index.php?pid=$project_id'>{$lang['email_admin_09']}</a>";
			// Finalize email
			$email->setBody("<html><head><title>$emailSubject</title></head><body style='font-family:arial,helvetica;font-size:10pt;'>$emailContents</body></html>");
			$email->setSubject($emailSubject);
			// Send email and notify with "0" if does not send
			If ($send_emails_admin_tasks) {
				print $email->send() ? "1" : "0";
			}else{
				print "1";
			}
			exit;

		// Send email request to super user to CREATE NEW project
		case 'request_new':
			// Check if any errors occurred when uploading an ODM file (if applicable)
			$url_edoc_id = "";
			if (isset($_FILES['odm']) && $_FILES['odm']['size'] > 0) {
				// Check ODM file for errors
				ODM::checkErrorsOdmFileUpload($_FILES['odm']);
				// Store file and get edoc_id
				$odm_edoc_id = Files::uploadFile($_FILES['odm']);
				if (is_numeric($odm_edoc_id)) {
					$url_edoc_id = "&odm_edoc_id=$odm_edoc_id&odm_edoc_id_hash=" . Files::docIdHash($odm_edoc_id);
				}
			}

			$email->setFrom($user_email);
			$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
			$email->setTo($project_contact_email);
			$emailSubject  =   "[REDCap] {$lang['email_admin_10']}";
			$folder_ids = isset($_POST['folder_ids']) ? implode(',', $_POST['folder_ids']) : '';

			$emailUrl = APP_PATH_WEBROOT_FULL . "index.php?action=create&type={$_GET['type']}"
					 . "&username=$userid&user_email=$user_email&scheduling={$_POST['scheduling']}&repeatforms={$_POST['repeatforms']}"
					 . "&purpose={$_POST['purpose']}&purpose_other=".urlencode($_POST['purpose_other'])
					 . "&surveys_enabled={$_POST['surveys_enabled']}"
					 . "&randomization={$_POST['randomization']}"
					 . "&project_pi_firstname=".urlencode($_POST['project_pi_firstname'])
					 . "&project_pi_mi=".urlencode($_POST['project_pi_mi'])
					 . "&project_pi_lastname=".urlencode($_POST['project_pi_lastname'])
					 . "&project_pi_email=".urlencode($_POST['project_pi_email'])
					 . "&project_pi_alias=".urlencode($_POST['project_pi_alias'])
					 . "&project_pi_username=".urlencode($_POST['project_pi_username'])
					 . "&project_irb_number=".urlencode($_POST['project_irb_number'])
					 . "&project_grant_number=".urlencode($_POST['project_grant_number'])
					 . "&project_note=".urlencode(nl2br(trim($_POST['project_note'])))
					 . "&template=".urlencode($_POST['copyof'])
					 . "&folder_ids=".urlencode($folder_ids)
					 . "&app_title=".urlencode($_POST['app_title'])
					 . $url_edoc_id;
					 
			// add DataMart flag if DataMart settings are detected
			if($datamart_settings = $_POST['datamart']) $emailUrl .= '&datamart=1';

			//create todo in redcap_todo_list
			$db = new RedCapDB();
			$userInfo = $db->getUserInfoByUsername($userid);
			$ui_id = $userInfo->ui_id;
			//add $project_title
			$request_id = ToDoList::insertAction($ui_id, $project_contact_email, "new project", $emailUrl, '');


			/**
			 * helper function to create a DataMart revision
			 *
			 * @param array $settings
			 * @param int $ui_id
			 * @param int $request_id
			 * @return DataMartRevision
			 */
			$createDataMartRevision = function($settings, $ui_id, $request_id) {
				// DataMart Settings
				$revision_settings = array(
					'user_id' => $ui_id,
					'request_id' => $request_id, // no revision request is issued because a request id is provided
				);
				if($dataMart_mrns = $settings['mrns']) $revision_settings['mrns'] = $dataMart_mrns;
				if($dataMart_dateMin = $settings['daterange']['min']) $revision_settings['date_min'] = $dataMart_dateMin;
				if($dataMart_dateMax = $settings['daterange']['max']) $revision_settings['date_max'] = $dataMart_dateMax;
				if($dataMart_fields = $settings['fields']) $revision_settings['fields'] =  $dataMart_fields; // get a all fields, one per line
				$dataMart = new DataMart();

				$revision = $dataMart->addRevision($revision_settings);
				return $revision;
			};

			if($datamart_settings = $_POST['datamart'])
			{
				try {
					$revision = $createDataMartRevision($datamart_settings, $ui_id, $request_id);
				} catch (\Exception $th) {
					$message = $th->getMessage();
					echo $message;
					return;
				}
			}

			// Add request_id to emailUrl
			$emailUrl .= "&request_id=$request_id";
			// Set email contents
			$emailContents =   "{$lang['global_21']}<br><br>
								{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
								(<a href='mailto:$user_email'>$user_email</a>)
								{$lang['email_admin_11']}
								<b>" . strip_tags(html_entity_decode($_POST['app_title'], ENT_QUOTES)) . "</b>.<br><br>
								{$lang['email_admin_05']}<br>
								<a href='$emailUrl'>{$lang['email_admin_12']}</a>";
			// Finalize email
			$email->setBody("<html><head><title>$emailSubject</title></head><body style='font-family:arial,helvetica;font-size:10pt;'>$emailContents</body></html>");
			$email->setSubject($emailSubject);
			if ($send_emails_admin_tasks) {
				if ($email->send()) {
					Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to create project");
				} else {
					exit($emailContents);
				}
			}else{
				Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to create project");
			}

			


			// Redirect user to a confirmation page
			redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=requested_new");

		// Send email request to admin to COPY project
		case 'request_copy':
			$email->setFrom($user_email);
			$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
			$email->setTo($project_contact_email);
			$emailSubject  =   "[REDCap] {$lang['email_admin_13']}";
			$emailUrl = APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectGeneral/copy_project_form.php?pid=$project_id"
								 . "&username=$userid&user_email=$user_email&scheduling={$_POST['scheduling']}&repeatforms={$_POST['repeatforms']}"
								 . "&purpose={$_POST['purpose']}&purpose_other=".urlencode($_POST['purpose_other'])
								 . "&surveys_enabled={$_POST['surveys_enabled']}"
								 . "&randomization={$_POST['randomization']}"
								 . "&project_pi_firstname=".urlencode($_POST['project_pi_firstname'])
								 . "&project_pi_mi=".urlencode($_POST['project_pi_mi'])
								 . "&project_pi_lastname=".urlencode($_POST['project_pi_lastname'])
								 . "&project_pi_email=".urlencode($_POST['project_pi_email'])
								 . "&project_pi_alias=".urlencode($_POST['project_pi_alias'])
								 . "&project_pi_username=".urlencode($_POST['project_pi_username'])
								 . "&project_irb_number=".urlencode($_POST['project_irb_number'])
								 . "&project_grant_number=".urlencode($_POST['project_grant_number'])
                                . "&c_report_folders=".urlencode($_POST['copy_report_folders'])
                                . "&c_dq_rules=".urlencode($_POST['copy_dq_rules'])
                                . "&c_external_links=".urlencode($_POST['copy_external_links'])
                                . "&c_record_dash=".urlencode($_POST['copy_record_dash'])
                                . "&c_alerts=".urlencode($_POST['copy_alerts'])
			 . "&c_users={$_POST['copy_users']}&c_roles={$_POST['copy_roles']}&c_reports={$_POST['copy_reports']}&c_folders={$_POST['copy_folders']}&c_records={$_POST['copy_records']}&c_queue_asi={$_POST['copy_survey_queue_auto_invites']}&app_title=".urlencode($_POST['app_title']);
			
			$db = new RedCapDB();
			$userInfo = $db->getUserInfoByUsername($userid);
			$todo_type = "copy project";
			$request_id = ToDoList::insertAction($userInfo->ui_id, $project_contact_email, $todo_type, $emailUrl, $project_id);
			$emailUrl .= "&request_id=$request_id";
			
			$emailContents =   "{$lang['global_21']}<br><br>
								{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
								(<a href='mailto:$user_email'>$user_email</a>)
								{$lang['email_admin_14']}
								<b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>
								{$lang['email_admin_05']}<br>
								<a href='" . $emailUrl . "'>{$lang['email_admin_15']}</a>";
			// Finalize email
			$email->setBody("<html><body style='font-family:arial,helvetica;font-size:10pt;'>$emailContents</body></html>");
			$email->setSubject($emailSubject);
			if ($send_emails_admin_tasks) {
			if ($email->send()) {
				Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to copy project");
			} else {
				exit($emailContents);
			}
			}else{
				Logging::logEvent("","redcap_data","MANAGE",$project_id,"project_id = $project_id","Send request to copy project");
			}
			// Redirect user to a confirmation page
			// redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=requested_copy&app_title=$app_title");
			// echo APP_PATH_WEBROOT . "ProjectSetup/other_functionality.php?pid=".$project_id."&?action=prompt_confirm_window";
			redirect(APP_PATH_WEBROOT . "ProjectSetup/other_functionality.php?pid=".$project_id."&action=prompt_confirm_window");

	}
}
