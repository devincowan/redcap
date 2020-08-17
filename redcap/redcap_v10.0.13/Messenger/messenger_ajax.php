<?php


// Prevent this request from extending the session expiration (so it doesn't interfere with auto-logout)
define("PREVENT_SESSION_EXTEND", true);
// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if (isset($_POST['action'])) {
  if($_POST['action'] == 'get_js_vars'){
	// Output all the JavaScript variables needed for Messenger
	Messenger::outputJSvars(false);
  }elseif($_POST['action'] == 'retrieve-conversations'){
    $channels = Messenger::getChannelsByUIID(UI_ID);

    $results = array(
      "channels" => $channels,
      "session" => json_encode_rc($_SESSION)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'check-if-new-messages'){
    if($user_messaging_enabled == 0){
      echo 'reload';//automaticly disabled message center
    }else{//send updated information
      if (isset($_POST['thread_id'])) {
        //set and update session vars
        $thread_id = $_POST['thread_id'];
        $thread_msg = $_POST['thread_msg'];
        $limit = $_POST['limit'];
        $action_icons_state = $_POST['action_icons_state'];
        $conv_win_size = $_POST['conv_win_size'];
        $important = $_POST['important'];
        $top_value = $_POST['msg_container_top'];
        $msg_container_height = $_POST['msg_container_height'];
        $msg_wrapper_height = $_POST['msg_wrapper_height'];
        $messaging_container_height = $_POST['message_center_container_height'];
        $tagged_data = $_POST['tagged_data'];
        $_SESSION['thread_id'] = $thread_id;
        $_SESSION['thread_msg'] = $thread_msg;
        $_SESSION['action_icons_state'] = $action_icons_state;
        $_SESSION['conv_win_size'] = $conv_win_size;
        $_SESSION['important'] = $important;
        $_SESSION['msg_container_top'] = $top_value;
        $_SESSION['message_center_container_height'] = $messaging_container_height;
        $_SESSION['msg_wrapper_height'] = $msg_wrapper_height;
        $_SESSION['msg_container_height'] = $msg_container_height;
        $_SESSION['tagged_data'] = $tagged_data;
      }
      $username = USERID;
      $ui_id = UI_ID;
      $thread_id = $_POST['thread_id'];
      $sql = "select m.*, t.type, t.channel_name, count(1) as unread
      from redcap_messages_threads t, redcap_messages m
      left join redcap_messages_status s on s.message_id = m.message_id
      where m.thread_id = t.thread_id and s.recipient_user_id = '".db_escape($ui_id)."'
	  and t.invisible = '0' and t.archived = '0'
      group by t.type, m.message_id";
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $new_message[$row['message_id']] = $row;
      }

      $new_message_count = count($new_message);

      $single_conv_unread = Messenger::findSingleConvUnread($ui_id);
      //get conversatiions list
      $channels = Messenger::getChannelsByUIID($ui_id);
      $messages = '';
      $members = '';

      //if there are new messages and a thread window is open
      if($new_message_count > 0 && $thread_id != '')
	  {
        $new_limit = Messenger::calculateNewLimit($single_conv_unread,$thread_id,$limit);
        $data = Messenger::getChannelMessagesAndMembers($thread_id,$new_limit);
        $messages = $data['messages'];
        $members = $data['members'];
        //mark messages as read if in the current thread
		$deleteStatus = array();
        foreach ($new_message as $item) {
			if($item['author_user_id'] != $ui_id && $item['thread_id'] == $thread_id) {
				$deleteStatus[] = $item['message_id'];
			}
        }
		if (!empty($deleteStatus)) {
            $sql = "delete from redcap_messages_status
					where message_id in (".prep_implode($deleteStatus).") and recipient_user_id = $ui_id";
            $q = db_query($sql);
		}
      }

      $is_dev = Messenger::hasDevPrivileges();
      $max_upload_size = maxUploadSizeAttachment();
      $archived_channels = json_encode_rc(Messenger::getArchivedConversations($username));

      $results = array(
      "newmessages" => json_encode_rc($new_message),
      "singleconvunread" => json_encode_rc($single_conv_unread),
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev),
      "maxuploadsize" => $max_upload_size,
      "archived_channels" => $archived_channels,
      "super_user" => SUPER_USER
      );

      echo json_encode_rc($results);
    }

  }elseif($_POST['action'] == 'retrieve-list-of-all-users-in-project'){
    $pid = $_POST['pid'];
    if($pid != ''){
      //select all users in project
      $sql = "select u.username, u.ui_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
	if(u.user_lastname is null,'',u.user_lastname) as user_lastname from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where (u.username = r.username and project_id = '".db_escape($pid)."')
      and u.user_suspended_time is null
      group by u.username
      order by u.username ASC";
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $projuser[$row['username']] = $row;
      }

      $members = Messenger::getProjectMembersSql($pid);
      $list = Messenger::getProjectIdsSql();
      $sql = "select u.username, u.ui_id, r.project_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where (u.username = r.username and ".$members.") and (".$list.")
      group by u.username
      order by u.username ASC";
      // var_dump($sql);
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $user[$row['username']] = $row;
      }

      $current_user = '';

    }else{//outside project
      //select all users across MY projects
	  $sqladmin = ($user_messaging_prevent_admin_messaging && !SUPER_USER) ? "and u.super_user = 0" : "";
      $list = Messenger::getProjectIdsSql();
      $sql = "select distinct u.username, u.ui_id, r.project_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where u.username = r.username and (".$list.") and u.user_suspended_time is null $sqladmin
      group by u.username
      order by u.username ASC";
      // var_dump($sql);
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $user[$row['username']] = $row;
      }
	  // Make sure current user is always in the list
	  if (empty($user)) {
		$user[USERID] = array('ui_id'=>UI_ID, 'username'=>USERID, 'user_firstname'=>$GLOBALS['user_firstname'], 'user_lastname'=>$GLOBALS['user_lastname']);
	  }

      $projuser = '';
    }

    $projects = Messenger::getListOfProjects();

    $results = array(
      "allUsers" => json_encode_rc($user),
      "projUsers" => json_encode_rc($projuser),
      "currentUser" => UI_ID,
      "project_list" => $projects
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'retrieve-list-of-proj-users-not-in-conv'){
    $thread_id = $_POST['thread_id'];
    $pid = $_POST['pid'];
    if($pid != ''){
      $members = Messenger::getConversationMembersSql($thread_id);
      $proj_members = Messenger::getProjectMembersSql($pid);
      $list = Messenger::getProjectIdsSql();
	  
      //select all users across projects minus the ones that are members already
      $sql = "select u.username, u.ui_id, r.project_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where (u.username = r.username and ".$members." and ".$proj_members.") and (".$list.") and u.user_suspended_time is null
      group by u.username
      order by u.username ASC";
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $user[$row['username']] = $row;
      }

      $sql = "select u.username, u.ui_id, r.project_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname
      from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where (r.project_id = '".db_escape($pid)."' and ".$members.")
      order by u.username ASC";
      // var_dump($sql);
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $projuser[$row['username']] = $row;
      }

      $member = '';

    }else{//outside project
	  $sqladmin = ($user_messaging_prevent_admin_messaging && !SUPER_USER) ? "and u.super_user = 0" : "";
      //select all users across projects
      $sql = "select u.username, u.ui_id, r.project_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname from redcap_user_information u
      left join redcap_user_rights r on u.username = r.username
      where u.username = r.username and u.user_suspended_time is null $sqladmin
      group by u.username
      order by u.username ASC";
      // var_dump($sql);
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $user[$row['username']] = $row;
      }

      $sql = "select recipient_user_id as ui_id from redcap_messages_recipients
      where thread_id = '".db_escape($thread_id)."'
      group by recipient_user_id
      order by recipient_user_id ASC";
      $q = db_query($sql);

      while ($row = db_fetch_assoc($q))
      {
        $member[$row['ui_id']] = $row;
      }

      $projuser = '';
    }

    $results = array(
      "allUsers" => json_encode_rc($user),
      "projUsers" => json_encode_rc($projuser),
      "members" => json_encode_rc($member)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'retrieve-list-of-users-in-conv'){
    $thread_id = $_POST['thread_id'];
    $sql = "select u.username, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname, u.ui_id, 
		if (u.super_user='1','1',r.conv_leader) as conv_leader
    from redcap_messages_recipients r
    left join redcap_user_information u
    on u.ui_id = r.recipient_user_id
    where r.thread_id = '".db_escape($thread_id)."'
    group by u.username";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
		{
			$result[$row['username']] = $row;
		}

    echo json_encode_rc($result);

  }elseif($_POST['action'] == 'get-archived-conversations'){
    $channels = json_encode_rc(Messenger::getArchivedConversations(USERID));

    $results = array(
      "channels" => $channels,
      "super_user" => SUPER_USER
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'resurrect-archived-conversation'){
    $thread_id = $_POST['thread_id'];

    $sql = "update redcap_messages_threads set archived = '0'
    where thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);

    //post message to resurrected conversation
    $action = 'post';
    $msg_body = "Conversation status changed to \"active\"";
    $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action);
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', '".db_escape(UI_ID)."',
    '".db_escape($msg_body)."', NULL)";
    $q = db_query($sql);
	$message_id = db_insert_id();

    //insert unread messages for all thread participants
    Messenger::createNewMessageNotifications($message_id,$thread_id);
	
    $channels = json_encode_rc(Messenger::getArchivedConversations(USERID));
    $results = array(
      "channels" => $channels,
      "super_user" => SUPER_USER
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'set-message-center-session'){
		if (isset($_POST['thread_id'])) {
		  $_SESSION['thread_id'] = $_POST['thread_id'];
		  $_SESSION['thread_msg'] = $_POST['msg'];
		  $_SESSION['conv_win_size'] = $_POST['conv_win_size'];
		  $_SESSION['important'] = $_POST['important'];
		}
		
		if($_SESSION['mc_open'] == '0'){
		  $_SESSION['mc_open'] = '1';
		}else{
		  $_SESSION['mc_open'] = '0';
		}

		echo json_encode_rc($_SESSION);

	}elseif($_POST['action'] == 'toggle-conv-leader'){
		if (isset($_POST['thread_id'])) {
		  $thread_id = $_POST['thread_id'];
		  $ui_id = $_POST['ui_id'];
		  $userInfo = User::getUserInfoByUiid($ui_id);
		  if(Messenger::checkIfSuperUser($userInfo['username']) == 1){
			exit('0');
		  }
		  $value = $_POST['value'];
		  $limit = $_POST['limit'];
		  $leader_ui_id = UI_ID;
		  $new_status_username = $userInfo['username'];
		  
		  $sql = "update redcap_messages_recipients set conv_leader = '".db_escape($value)."'
		  where thread_id = '".db_escape($thread_id)."' and recipient_user_id = '".db_escape($ui_id)."'";
		  $q = db_query($sql);

		  $message = ($value == '0' ? '"Conversation Member"' : '"Conversation Admin"');

		  //notify message
		  $action = 'post';
		  $msg_body = $new_status_username."'s status was changed to ".$message;
		  $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action);
		  $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
		  ('".db_escape($thread_id)."', '".NOW."', '".db_escape($leader_ui_id)."',
		  '".db_escape($msg_body)."', NULL)";
		  $q = db_query($sql);
		  $message_id = db_insert_id();

		  //find all ui_ids of remaining members of the conversation to send notification to
		  $sql = "select recipient_user_id as participant
		  from redcap_messages_recipients
		  where (thread_id = '".db_escape($thread_id)."' and recipient_user_id != '".db_escape($leader_ui_id)."')
		  group by recipient_user_id ASC";
		  $q = db_query($sql);
		  while ($row = db_fetch_assoc($q))
		  {
			$participants[$row['participant']] = $row;
		  }

		  Messenger::sendNotificationToParticipants($participants,$thread_id,$leader_ui_id);
		}

		$channels = Messenger::getChannelsByUIID($leader_ui_id);

		$data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
		$messages = $data['messages'];
		$members = $data['members'];
		$is_dev = Messenger::hasDevPrivileges();

		$results = array(
		  "channels" => $channels,
		  "messages" => $messages,
		  "members" => $members,
		  "isdev" => json_encode_rc($is_dev),
		  "newtitle" => json_encode_rc($new_title)
		);

		echo json_encode_rc($results);

	}elseif($_POST['action'] == 'tag-suggest'){
		// Remove illegal characters for usernames (if somehow posted bypassing javascript)
		$tag = preg_replace("/[^a-zA-Z0-9-.@_]/", "", $_POST['word']);
		// Return nothing if not a real username or thread
		if ($_POST['word'] != $tag || substr($tag, 0, 1) != '@' || !is_numeric($_POST['thread_id'])) exit;
		// Ensure the current user belongs to this thread
		if (!Messenger::isConversationMember($_POST['thread_id'], UI_ID)) exit;
		// Remove @ from beginning of username
		$tag = substr($tag, 1);
		// Obtain array of users found
		$usersToTag = Messenger::searchConversationUsers($tag, $_POST['thread_id'], true);
		if (empty($usersToTag)) exit;
		// Loop through users and output HTML
		print RCView::div(array(), "<div>{$lang['messaging_70']}</div>");
		$addBtn = " " . RCView::button(array('class'=>'btn btn-success btn-xs'), "+{$lang['messaging_71']}");
		foreach ($usersToTag as $user=>$member)
		{
			$class = $member ? "ut" : "utn";
			$thisAddBtn = $member ? "" : $addBtn;
			print RCView::div(array("class"=>$class), RCView::span(array(), '@' . RCView::escape($user)) . $thisAddBtn);
		}
		
  }elseif($_POST['action'] == 'retrieve-list-of-all-users-like'){
    $term = $_POST['term'];
	$sqladmin = ($user_messaging_prevent_admin_messaging && !SUPER_USER) ? "and u.super_user = 0" : "";     
    $sql = "select u.username, u.ui_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
	if(u.user_lastname is null,'',u.user_lastname) as user_lastname
    from redcap_user_information u
    where (u.username like '%".db_escape($term)."%' or u.user_firstname like '%".db_escape($term)."%' 
		or u.user_lastname like '%".db_escape($term)."%' or u.user_email like '%".db_escape($term)."%')
    and u.user_suspended_time is null $sqladmin and u.username != 'site_admin'
    order by u.username ASC";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
		{
			$result[$row['username']] = $row;
		}

    echo json_encode_rc($result);

  }elseif($_POST['action'] == 'find-user-conversations'){
    $username = $_POST['username'];
    $ui_id = User::getUIIDByUsername($username);

    $searched_user_channels = Messenger::getAllChannelsByUIID($ui_id);
    $logged_user_channels = Messenger::getAllChannelsByUIID(UI_ID);
    $searched_user_channels = json_decode($searched_user_channels);
    $logged_user_channels = json_decode($logged_user_channels);
    // do the comparison between the two arrays
    $empty_one = array();
    $empty_two = array();
    foreach ($searched_user_channels as $channel) {
      array_push($empty_one,$channel->thread_id);
    }
    foreach ($logged_user_channels as $channel) {
      array_push($empty_two,$channel->thread_id);
    }
    $intersect = array_intersect($empty_one,$empty_two);
    $sql_channel = '';
    foreach ($intersect as $channel) {
      $sql_channel .= 't.thread_id = "'.$channel.'" or ';
    }
    $sql_channel = substr($sql_channel,0,-4);
    $sql = "select t.* from redcap_messages_threads t
    where (".$sql_channel.")";
    $q = db_query($sql);
    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $channels[$count_i] = $row;
      $count_i++;
    }

    echo json_encode_rc($channels);

  }elseif($_POST['action'] == 'find-conversations-like'){
    $term = $_POST['value'];
    $search_method = $_POST['search_method'];
    $ui_id = User::getUIIDByUsername(USERID);
    if($search_method == 'keyword'){

      $data = Messenger::searchChannelsLike($ui_id,$term);
      // var_dump($data);
      $results = json_encode_rc($data);
    }else{//search by members

      $sql = "select u.username, u.ui_id, if(u.user_firstname is null,'',u.user_firstname) as user_firstname, 
		if(u.user_lastname is null,'',u.user_lastname) as user_lastname, u.user_email
      from redcap_user_information u
      where (u.user_email like '%".db_escape($term)."%' or u.username like '%".db_escape($term)."%' 
		or u.user_firstname like '%".db_escape($term)."%' or u.user_lastname like '%".db_escape($term)."%') and u.user_suspended_time is null";
      $q = db_query($sql);
      $count_i = 0;
      while ($row = db_fetch_assoc($q))
      {
        $members[$count_i] = $row;
        $count_i++;
      }
      $results = json_encode_rc($members);
    }

    echo $results;

  }elseif($_POST['action'] == 'change-conversation-title'){
    $thread_id = $_POST['thread_id'];
    $new_title = $_POST['new_title'];
    $username = USERID;
    $limit = $_POST['limit'];
    $ui_id = UI_ID;
	
	if(Messenger::checkIfConvAdmin($thread_id,$ui_id) != 1){
		exit;
	}
	
    $old_conversation_title = Messenger::getChannelTileByThreadID($thread_id);
    $sql = "update redcap_messages_threads set channel_name = '".db_escape($new_title)."'
    where thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);

    $action = 'post';
    $msg_body = "Conversation title changed to ".'"'."".db_escape($new_title)."".'"'." from ".'"'."".db_escape($old_conversation_title)."".'"'."";
    $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action);
    // var_dump($msg_body);exit;
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', '".db_escape($ui_id)."',
    '".db_escape($msg_body)."', NULL)";
    $q = db_query($sql);
	$message_id = db_insert_id();

    //insert unread messages for all thread participants
    Messenger::createNewMessageNotifications($message_id,$thread_id);

    //get conversatiions list
    $channels = Messenger::getChannelsByUIID($ui_id);

    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];
    $is_dev = Messenger::hasDevPrivileges();

    $results = array(
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev),
      "newtitle" => json_encode_rc($new_title)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'post-channel-message'){
    $thread_id = $_POST['thread_id'];
    $msg_body = trim(decode_filter_tags($_POST['msg_body']));
    $limit = $_POST['limit'];
    $tagged_data = $_POST['tagged_data'];

    //check if message is urgent
    if (isset($_POST['important'])) {
      $urgent = $_POST['important'];
    }else{
      $urgent = '';
    }
	$important = (isset($_POST['important']) && $_POST['important'] == '1') ? '1' : '0';
	$creator_ui_id = UI_ID;
    if (isset($_POST['users'])) 
	{  
		//if adding new users to existing conversation
		$users = $_POST['users'];
		$pid = (isset($_POST['pid']) ? $_POST['pid'] : 0);
		if(Messenger::checkIfConvAdmin($thread_id,$creator_ui_id) != 1){
			exit('0');
		}
		$users_array = explode(",", $users);
		//add users to redcap_messages_recipients
		foreach ($users_array as $user) {
			$added_user_ui_id = User::getUIIDByUsername($user);
			$sql = "insert into redcap_messages_recipients (thread_id, recipient_user_id, conv_leader) values
			('".db_escape($thread_id)."', '".db_escape($added_user_ui_id)."', '".Messenger::checkIfSuperUser($user,$pid)."')";
			$q = db_query($sql);
		}
		if($msg_body == '') $msg_body = $users.' '.$lang['messaging_72'];
        //post invitation message
        $action = 'post';
        $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action,'',$important);
        $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
        ('".db_escape($thread_id)."', '".NOW."', '".db_escape($creator_ui_id)."',
        '".db_escape($msg_body)."', NULL)";
	} else {
		//if posting a new message
		list ($tagged_users, $msg_body) = Messenger::checkIfTaggedMembers($msg_body, $thread_id);
		$action = 'post';
		$msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action,'',$important);
		$sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
		('".db_escape($thread_id)."', '".NOW."', '".db_escape($creator_ui_id)."',
		'".db_escape($msg_body)."', NULL)";
    }
	$q = db_query($sql);
	$message_id = db_insert_id();

    //insert unread messages for all thread participants
    Messenger::createNewMessageNotifications($message_id,$thread_id,$urgent,$tagged_users);

    //get conversatiions list
    $channels = Messenger::getChannelsByUIID($creator_ui_id);

    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];

    $is_dev = Messenger::hasDevPrivileges();

    $results = array(
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'post-new-feature-message'){
    $thread_id = $_POST['thread_id'];
    $msg_body = $_POST['msg_body'];
    $limit = $_POST['limit'];
    $action = 'what-new';
    $msg_data = array(
      'title' => $_POST['title'],
      'description' => $_POST['description'],
      'link' => $_POST['link']
    );
    $msg = Messenger::createMsgBodyObj($msg_data,USERID,$action);
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', NULL,
    '".db_escape($msg)."', NULL)";
    $q = db_query($sql);
	$message_id = db_insert_id();

    //insert unread messages for all thread participants
    Messenger::createNewMessageNotifications($message_id,$thread_id);

    //get conversatiions list
    $channels = Messenger::getChannelsByUIID($creator_ui_id);

    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];

    $is_dev = Messenger::hasDevPrivileges();
    // echo count($channel);
    // var_dump($result);

    $results = array(
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev)
    );

    echo json_encode_rc($results);
    // echo '1';

  }elseif($_POST['action'] == 'create-new-conversation'){
    $title = $_POST['title'];
    $username = USERID;
    $creator_ui_id = UI_ID;
    $pid = (isset($_POST['pid']) ? $_POST['pid'] : 0);
    $proj_id = $_POST['proj_id'];
    if($title != ''){//if creating new conversation
      $users = $_POST['users'];
      $msg = $msgOrig = $_POST['msg'];
      $users_array = explode(",", $users);
	  // Make sure the current user is always part of the new conversation
	  $users_array[] = $username;
	  $users_array = array_unique($users_array);
	  // Create new thread
      if(!is_numeric($proj_id)) $proj_id = null;
	  $sql = "insert into redcap_messages_threads (type, channel_name, project_id) values
			('CHANNEL', '".db_escape($title)."',".checkNull($proj_id).")";
      $q = db_query($sql);
	  $new_thread_id = db_insert_id();
      //create message from creator
      $action = 'post';
      $msg = Messenger::createMsgBodyObj($msg,USERID,$action);
      $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
      ('".db_escape($new_thread_id)."', '".NOW."', '".db_escape($creator_ui_id)."', '".db_escape($msg)."', NULL)";
      $q = db_query($sql);
	  $message_id = db_insert_id();

      //insert unread messages for all thread participants but not the creator
	  $recipientIdUsername = array();
      foreach ($users_array as $user) 
	  {
        $participant_ui_id = User::getUIIDByUsername($user);
		if (!is_numeric($participant_ui_id)) continue;
		// If this is the creator
		$conv_leader = ($participant_ui_id == $creator_ui_id || Messenger::checkIfSuperUser($user,$pid)) ? 1 : 0;
        //create invites for all participants
        $sql = "insert into redcap_messages_recipients (thread_id, recipient_user_id, conv_leader) values
        ('".db_escape($new_thread_id)."', '".db_escape($participant_ui_id)."', '$conv_leader')";
        $q = db_query($sql);
	    $recipient_id = db_insert_id();
        //--last step---//
		if ($user != USERID) {
			$sql = "insert into redcap_messages_status (message_id, recipient_id, recipient_user_id) values
			('".db_escape($message_id)."', '".db_escape($recipient_id)."' ,'".db_escape($participant_ui_id)."')";
			$q = db_query($sql);
			$recipientIdUsername[$user] = db_insert_id();
		}
      }
	  
	  // If anyone was tagged in original message on thread, then resave the message with tagging in it	  
	  list ($taggedUsers, $msgOrigTagged) = Messenger::checkIfTaggedMembers($msgOrig, $new_thread_id);
	  if ($msgOrigTagged != $msgOrig) {
		  // Update the message body
		  $msgOrigTagged = Messenger::createMsgBodyObj($msgOrigTagged,USERID,$action);
		  $sql = "update redcap_messages set message_body = '".db_escape($msgOrigTagged)."' where message_id = $message_id";
		  $q = db_query($sql);
		  // Remove current user from $taggedUsers
		  $currentUserKey = array_search(USERID, $taggedUsers);
		  if ($currentUserKey) unset($taggedUsers[$currentUserKey]);
		  // Update status table's urgent value for tagged users
		  if (!empty($recipientIdUsername) && !empty($taggedUsers)) {
			  foreach ($taggedUsers as $taggedUser) {
				  if (!isset($recipientIdUsername[$taggedUser])) {
					  unset($recipientIdUsername[$taggedUser]);
				  }
			  }
			  // Update table
			  if (!empty($recipientIdUsername)) {
				  $sql = "update redcap_messages_status set urgent = 1 where status_id in (".prep_implode($recipientIdUsername).")";
				  $q = db_query($sql);
			  }
		  }
	  }	  
    }//end creating new message

    $channels = Messenger::getChannelsByUIID($creator_ui_id);

    $results = array(
      "channels" => $channels,
      "thread_id" => $new_thread_id
    );
    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'remove-users-from-conversation'){
    $thread_id = $_POST['thread_id'];
    $limit = $_POST['limit'];
    $kicker_ui_id = UI_ID;
	// Only allow conv admins to remove others from conv, except all single user to remove theirself
    if (strtolower($_POST['users']) != strtolower(USERID) && Messenger::checkIfConvAdmin($thread_id,$kicker_ui_id) != 1){
      exit('0');
    }
    $users_array = explode(",", $_POST['users']);
    // remove users from recipients table
    foreach ($users_array as $key=>$user) {
      $ui_id = User::getUIIDByUsername($user);
	  if (!is_numeric($ui_id)) {
		  unset($users_array[$key]);
		  continue;
	  }
      $sql = "delete from redcap_messages_recipients
      where thread_id = '".db_escape($thread_id)."' and recipient_user_id = '".db_escape($ui_id)."' ";
      $q = db_query($sql);
    }
    //notify message
    $action = 'post';
    $msg = implode(", ", $users_array) . " " . 
			(count($users_array) == 1 ? " ".$lang['messaging_17'] : " ".$lang['messaging_18']);
    $msg = Messenger::createMsgBodyObj($msg,USERID,$action);
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', '".db_escape($kicker_ui_id)."',
    '".db_escape($msg)."', NULL)";
    $q = db_query($sql);
	  $message_id = db_insert_id();

    //find all ui_ids of remaining members of the conversation to send notification to
    $sql = "select recipient_user_id as participant
    from redcap_messages_recipients
    where (thread_id = '".db_escape($thread_id)."' and recipient_user_id != '".db_escape($kicker_ui_id)."')
    group by recipient_user_id";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      $result[$row['participant']] = $row;
    }
    //insert unread messages for all thread participants
    Messenger::sendNotificationToParticipants($result,$thread_id,$kicker_ui_id);
	
    //get conversatiions list
    $channels = Messenger::getChannelsByUIID($kicker_ui_id);

    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];
    $is_dev = Messenger::hasDevPrivileges();

    $results = array(
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev),
      "newtitle" => json_encode_rc($new_title)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'delete-archive-conversation' && isset($_POST['thread_id'])){
    $thread_id = $_POST['thread_id'];
    $action = $_POST['action_type'];
    $ui_id = UI_ID;
    if(Messenger::checkIfConvAdmin($thread_id,$ui_id) != 1){
      exit('0');
    }
    $field = ($action == 'delete' ? 'invisible' : 'archived');
    $message = ($action == 'delete' ? $lang['messaging_73'] : $lang['messaging_74']);
    $sql = "update redcap_messages_threads set ".$field." = '1'
    where thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);
    //insert message
    $action = 'post';
    $msg_body = $message." ".$lang['messaging_75'];
    $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action);
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', '".db_escape($ui_id)."',
    '".db_escape($msg_body)."', NULL)";
    $q = db_query($sql);
	  $message_id = db_insert_id();
    //delete previous new message notifications in redcap_messages_status
    $sql = "delete s
    from redcap_messages_threads t, redcap_messages m
    left join redcap_messages_status s on s.message_id = m.message_id
    where m.thread_id = t.thread_id and t.thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);

    echo $thread_id;

  }elseif($_POST['action'] == 'prioritize-conversation' && isset($_POST['thread_id'])){
    $thread_id = $_POST['thread_id'];
    $value = $_POST['value'];
    $ui_id = UI_ID;
	
    $sql = "update redcap_messages_recipients set prioritize = '".db_escape($value)."'
    where thread_id = '".db_escape($thread_id)."' and recipient_user_id = '".db_escape($ui_id)."'";
    $q = db_query($sql);
    $channels = Messenger::getChannelsByUIID($ui_id);

    echo $channels;

  }elseif($_POST['action'] == 'retrieve-channel-global-data' && isset($_POST['thread_id'])){
	  
	if (isset($_POST['thread_id'])) {
	  $_SESSION['thread_id'] = $_POST['thread_id'];
	}
		
    $thread_id = $_POST['thread_id'];
    $limit = $_POST['limit'];
    $ui_id = UI_ID;
    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];
    $total_msgs = $data['total_messages'];
    $channels = Messenger::getChannelsByUIID($ui_id);
    $is_dev = Messenger::hasDevPrivileges();

    //mark messages as read
	$deleteStatus = array();
    foreach (json_decode($messages,true) as $item) {
      if($item['author_user_id'] != $ui_id){
		$deleteStatus[] = $item['message_id'];
      }
    }
	if (!empty($deleteStatus)) {
		$sql = "delete from redcap_messages_status
				where message_id in (".prep_implode($deleteStatus).") and recipient_user_id = $ui_id";
		$q = db_query($sql);
	}

    $results = array(
      "members" => $members,
      "messages" => $messages,
      "total_msgs" => $total_msgs,
      "conversations" => $channels,
      "isdev" => json_encode_rc($is_dev)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'check-available-actions'){

    $permissions = Messenger::checkMessagePrivileges($_POST['msgId']);

    echo $permissions;

  }elseif($_POST['action'] == 'show-deleted-message'){
    $msg_id = $_POST['msgId'];

    if(Messenger::checkIfSuperUser(USERID) == '1'){
      echo '1';
    }else{
      echo '0';
    }

  }elseif($_POST['action'] == 'find-specific-message'){

    echo $_POST['msgId'];

  }elseif($_POST['action'] == 'edit-delete-message'){
    $msg_id = $_POST['msgId'];
    $thread_id = $_POST['thread_id'];
    $msg_body = trim(decode_filter_tags($_POST['msg_body']));
    $msg_type = $_POST['msg_type'];	
    $limit = $_POST['limit'];
    // find original author and post time
    $sql = "select author_user_id, sent_time, message_body
    from redcap_messages
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);
	$userInfo = User::getUserInfoByUiid(db_result($q, 0));
    $original_message_creator = $userInfo['username'];
    $sent_time = db_result($q, 0, 1);

    if($_POST['action_type'] == 'delete'){
      $notification_msg = $lang['messaging_76'].' "'.$original_message_creator.'" '.$lang['messaging_77'].' '.$sent_time.' '.$lang['messaging_78'];
    }else{//edit
      $notification_msg = $lang['messaging_76'].' "'.$original_message_creator.'" '.$lang['messaging_77'].' '.$sent_time.' '.$lang['messaging_79'];
    }
    //update the message
    $action = $_POST['action_type'];
    $msg_body = Messenger::createMsgBodyObj($msg_body,USERID,$action,$msg_id);
    // var_dump($msg_body);exit;
    $sql = "update redcap_messages
    set message_body = '".db_escape($msg_body)."'
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);

    $creator_ui_id = UI_ID;
    $action = 'notify';
    $notification_msg = Messenger::createMsgBodyObj($notification_msg,USERID,$action,$msg_id);
    $sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id) values
    ('".db_escape($thread_id)."', '".NOW."', '".db_escape($creator_ui_id)."',
    '".db_escape($notification_msg)."', NULL)";
    $q = db_query($sql);
	$message_id = db_insert_id();

    //insert unread messages for all thread participants
    Messenger::createNewMessageNotifications($message_id,$thread_id);

    //get conversations list
    $channels = Messenger::getChannelsByUIID($creator_ui_id);

    $data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
    $messages = $data['messages'];
    $members = $data['members'];

    $is_dev = Messenger::hasDevPrivileges();

    $results = array(
      "channels" => $channels,
      "messages" => $messages,
      "members" => $members,
      "isdev" => json_encode_rc($is_dev)
    );

    echo json_encode_rc($results);

  }elseif($_POST['action'] == 'display-message-history'){
    $msg_id = $_POST['msgId'];

    // echo Messenger::getMessageHistory($msg_id);
    $msg_data =  Messenger::getMessageHistoryArray($msg_id);

    echo json_encode_rc($msg_data);

  }elseif($_POST['action'] == 'check-why-kicked'){
    //check the table to make more reliable
    $status = Messenger::checkConvStatus($_POST['thread_id']);
    if($status == 'deleted'){
      $result = 'deleted';
    }elseif($status == 'archived'){
      $result = 'archived';
    }elseif(Messenger::checkIfConvMember($_POST['thread_id'],UI_ID) == ''){
      $result = 'removed';
    }else{
      $result = 'generic';
    }

    echo $result;

  }elseif($_POST['action'] == 'get-doc-id'){

    $result = Files::docIdHash($_POST['id']);
    echo $result;
	
  }elseif($_POST['action'] == 'close-channel-window'){

	unset($_SESSION['thread_id']);
  }

}