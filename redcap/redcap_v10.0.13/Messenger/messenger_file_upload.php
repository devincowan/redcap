<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if($_POST['file_name'] == ''){
	$result = 0;
	print "<script language='javascript' type='text/javascript'>
	window.parent.window.messagingUploadFileHandler($result);
	</script>";
	exit;
}
$msg_body = $_POST['file_name'];
$username = $_POST['username'];
$thread_id = $_POST['thread_id'];
$file_type = $_POST['file_type'];
$file_preview = $_POST['file_preview'];
$limit = $_POST['limit'];
$message = $_POST['message'];
$blob = $_POST['blob'];
$important = (isset($_POST['important']) && $_POST['important'] == '1') ? '1' : '0';

// Upload the file and return the doc_id from the edocs table
$doc_id = $doc_size = 0;
$doc_name = "";
if (isset($_FILES['myfile'])) 
{
	$doc_size = $_FILES['myfile']['size'];
	if (($doc_size/1024/1024) > maxUploadSizeEdoc() || $_FILES['myfile']['error'] != UPLOAD_ERR_OK){
		$result = 2;
		print "<script language='javascript' type='text/javascript'>
		window.parent.window.messagingUploadFileTooBig($result);
		</script>";
		exit;
	}else{
		// Obtain the image height (if an image) so we can store it in the table
		list ($img_height, $img_width) = Files::getImgWidthHeight($_FILES['myfile']['tmp_name']);
		// Add the file to edocs table
		$doc_id = Files::uploadFile($_FILES['myfile']);
		$doc_hash = Files::docIdHash($doc_id);
		$doc_name = str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES));
		$creator_ui_id = User::getUIIDByUsername($username);
		$stored_url = Messenger::generateAttachmentData($file_preview,$doc_name,$img_height);
		$upload_msg = $message;
		$action = 'post';
		$message = Messenger::createMsgBodyObj($message,USERID,$action,'',$important);
		$sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id, stored_url) values
		('".db_escape($thread_id)."', '".NOW."', '".db_escape($creator_ui_id)."',
		'".db_escape($message)."', '".db_escape($doc_id)."', '".db_escape($stored_url)."')";
		$q = db_query($sql);
		$message_id = db_insert_id();

		//insert unread messages for all thread participants
		Messenger::createNewMessageNotifications($message_id,$thread_id,$important);

		//get data for refreshMessages
		$channels = Messenger::getChannelsByUIID($creator_ui_id);
		$data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
		$messages = $data['messages'];
		$members = $data['members'];
		$is_dev = isDev();
		$is_dev = json_encode_rc($is_dev);
		$messages = json_encode_rc($messages);
		$members = json_encode_rc($members);
		$channels = json_encode_rc($channels);

	}
}

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSizeEdoc() || (!isset($_POST['myfile_base64']) && $_FILES['myfile']['error'] != UPLOAD_ERR_OK))
{
	// Delete temp file
	unlink($_FILES['myfile']['tmp_name']);
	// Give error response
	print "<script language='javascript' type='text/javascript'>
			window.parent.window.stopUpload($result,'$field_name','$doc_id','$doc_name','".js_escape($id)."','$doc_size','$event_id','$file_download_page','$file_delete_page','');
			window.parent.window.alert('ERROR: CANNOT UPLOAD FILE!\\n\\nThe uploaded file is ".round_up($doc_size/1024/1024)." MB in size, '+
									'thus exceeding the maximum file size limit of ".maxUploadSizeEdoc()." MB.');
		   </script>";
	exit;
}

//Update tables if file was successfully uploaded
if ($doc_id != 0) {
	$result = 1;
}else{
	$creator_ui_id = User::getUIIDByUsername($username);
	$channels = Messenger::getChannelsByUIID($creator_ui_id);
	$data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
	$messages = $data['messages'];
	$members = $data['members'];
	$is_dev = isDev();
	$is_dev = json_encode_rc($is_dev);
	$messages = json_encode_rc($messages);
	$members = json_encode_rc($members);
	$channels = json_encode_rc($channels);
	$result = 0;
}

// Ouput javascript
print "<script language='javascript' type='text/javascript'>
		window.parent.window.messagingUploadFileHandler($result,$messages,$members,$is_dev,$channels);
	   </script>";
