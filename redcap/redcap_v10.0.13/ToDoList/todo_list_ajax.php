<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if (isset($_POST['action'])) 
{
	if (SUPER_USER) 
	{
	  if ($_POST['action'] == 'update-token' && isset($_POST['project_id'])) {
		//update todo status
		$project_id = (int)$_POST['project_id'];
		$sql = "update redcap_todo_list set status='completed', request_completion_time='".NOW."' where (project_id = '" . db_escape($project_id) ."' and todo_type='token access')";
		$q = db_query($sql);
		echo '1';
	  }elseif($_POST['action'] == 'delete-todo'){
		$id = $_POST['id'];
		$sql = "delete from  redcap_todo_list where request_id = '" . db_escape($id) ."' ";
		$q = db_query($sql);
		echo '1';
	  }elseif($_POST['action'] == 'ignore-todo'){
		$id = $_POST['id'];
		$status = $_POST['status'];
		$sql = "update redcap_todo_list set status='".db_escape($status)."' where request_id = '" . db_escape($id) ."' ";
		$q = db_query($sql);
		echo '1';
	  }elseif($_POST['action'] == 'archive-todo'){
		$id = $_POST['id'];
		$status = $_POST['status'];
		$sql = "update redcap_todo_list set status='".db_escape($status)."' where request_id = '" . db_escape($id) ."' ";
		$q = db_query($sql);
		if ($q) {
            // Update datamart revision table in case this is connected to a datamart project
            $sql = "update redcap_ehr_datamart_revisions set is_deleted = 1 where request_id = '" . db_escape($id) ."' ";
            db_query($sql);
        }
		echo '1';
		// Log this event
		Logging::logEvent($sql, "redcap_todo_list", "MANAGE", $id, "request_id = '" . db_escape($id) ."'", "Archive a request in the To-Do List");
	  }elseif($_POST['action'] == 'toggle-notifications'){
		$checked = ($_POST['checked'] == 'true' ? 1 : 0);
		// print $checked;
		$sql = "update redcap_config set value='".db_escape($checked)."' where field_name='send_emails_admin_tasks' ";
		$q = db_query($sql);
		echo '1';
		// Log this event
		$descrip = $checked ? "Enable email notifications for administrators" : "Disable email notifications for administrators";
		Logging::logEvent($sql, "redcap_config", "MANAGE", 'send_emails_admin_tasks', "field_name='send_emails_admin_tasks'", $descrip);
		}elseif($_POST['action'] == 'write-comment'){
		$id = $_POST['id'];
		$comment = $_POST['comment'];
		$sql = "update redcap_todo_list set comment='".db_escape($comment)."' where request_id = '".db_escape($id)."' ";
		$q = db_query($sql);
		echo '1';
	  }
	}
	
	// Not required to be a super user
	elseif ($_POST['action'] == 'delete-request'){
		$pid = $_POST['pid'];
		$ui_id = $_POST['ui_id'];
		$req_type = $_POST['req_type'];
		$sql = "delete from redcap_todo_list where project_id = '".db_escape($pid)."' and todo_type = '".db_escape($req_type)."' and request_from = '".db_escape($ui_id)."' ";
		$q = db_query($sql);
		if ($q && $send_emails_admin_tasks) {
			// Send email to admin to let them know that the request was cancelled
			$email = new Message();
			$email->setFrom($user_email);
			$email->setFromName("$user_firstname $user_lastname");
			$email->setTo($project_contact_email);
			if ($req_type == 'move to prod') {
				$emailSubject  =   "[REDCap] {$lang['email_admin_23']}";
				$emailContents =   "{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
								(<a href='mailto:$user_email'>$user_email</a>)
								{$lang['email_admin_25']} <b>" . strip_tags(html_entity_decode(ToDoList::getProjectTitle($pid), ENT_QUOTES)) . "</b> (PID $pid){$lang['period']}";
				$email->setBody($emailContents, true);
				$email->setSubject($emailSubject);
				$email->send();
			} elseif ($req_type == 'delete project') {
				$emailSubject  =   "[REDCap] {$lang['email_admin_24']}";
				$emailContents =   "{$lang['email_admin_03']} <b>" . html_entity_decode("$user_firstname $user_lastname", ENT_QUOTES) . "</b>
								(<a href='mailto:$user_email'>$user_email</a>)
								{$lang['email_admin_26']} <b>" . strip_tags(html_entity_decode(ToDoList::getProjectTitle($pid), ENT_QUOTES)) . "</b> (PID $pid){$lang['period']}";
				$email->setBody($emailContents, true);
				$email->setSubject($emailSubject);
				$email->send();
			}
		}
		echo '1';
	}

}
