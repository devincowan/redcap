<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

/**
 * Messenger Class
 */
class Messenger
{

  public static function getConversationMembersSql($thread_id){
    // $sql = "select u.username, u.ui_id
    // from redcap_messages m
    // left join redcap_user_information u
    // on u.ui_id = m.author_user_id
    // where m.thread_id = '".db_escape($thread_id)."'
    // group by m.author_user_id";
    $sql = "select u.username, u.ui_id
    from redcap_messages_recipients r
    left join redcap_user_information u
    on u.ui_id = r.recipient_user_id
    where r.thread_id = '".db_escape($thread_id)."'
    group by r.recipient_user_id";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      $conversation_member[$row['username']] = $row;
    }

    $members = '';
    foreach ($conversation_member as $member) {
      $members .= 'u.username != "'.$member['username'].'" and ';
    }

    return $members = substr($members,0,-4);
  }

  public static function getProjectMembersSql($pid){
    $sql = "select u.username, u.ui_id, u.user_firstname from redcap_user_information u
    left join redcap_user_rights r on u.username = r.username
    where (u.username = r.username and project_id = '".db_escape($pid)."')
    group by u.username
    order by u.username ASC";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      $project_member[$row['username']] = $row;
    }

    $members = '';
    foreach ($project_member as $member) {
      $members .= 'u.username != "'.$member['username'].'" and ';
    }

    return $members = substr($members,0,-4);
  }

  public static function getProjectIdsSql(){
    $sql = "select r.project_id
    from redcap_user_rights r
    where username = '".db_escape(USERID)."'";
    $q = db_query($sql);

    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $ids[$count_i] = $row;
      $count_i++;
    }
    $list = '';
    foreach ($ids as $id) {
      $list .= 'r.project_id = "'.$id['project_id'].'" or ';
    }

    return substr($list,0,-3);
  }

  public static function getChannelTileByThreadID($thread_id){
    $sql = "select channel_name from redcap_messages_threads
    where thread_id = '".db_escape($thread_id)."'
    limit 1";
    $q = db_query($sql);
    return db_result($q, 0);
  }

  public static function hasDevPrivileges(){
	return (isDev() && SUPER_USER); // Tied only to the REDCap development team's dev servers
    // return ( (USERID == 'donald' || USERID == 'test_user' || USERID == 'taylorr4' || USERID == 'delacqg' || USERID == 'rob.taylor@vumc.org') ? true : false);
  }

  public static function getMessageHistoryArray($msg_id){
    $sql = "select message_body, attachment_doc_id, stored_url
    from redcap_messages
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      if($row['attachment_doc_id']){
        $row['doc_id_hash'] = Files::docIdHash($row['attachment_doc_id']);
      }
      $message['data'] = $row;
    }
    return $message;
  }

  public static function getMessageHistory($msg_id){
    $sql = "select message_body from redcap_messages
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);
    return db_result($q, 0);
  }

  public static function checkIfMessageCreator($msg_id){
    $ui_id = User::getUIIDByUsername(USERID);
    $sql = "select author_user_id from redcap_messages
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);
    $author_user_id = db_result($q, 0);
    return ($ui_id == $author_user_id ? true : false);
  }

  public static function checkIfMessageDeleted($msg_id){
    $sql = "select message_body from redcap_messages
    where message_id = '".db_escape($msg_id)."'";
    $q = db_query($sql);
    $msg_body = db_result($q, 0);
    $data = json_decode($msg_body);
    $action = $data[count($data)-1]->action;
    // $pos = strpos($msg_body, '-|-delete');
    return ($action == 'delete' ? true : false);
    //try debug
  }

  public static function checkMessagePrivileges($msg_id){
    $super_user = Self::checkIfSuperUser(USERID);
    if(self::checkIfMessageDeleted($msg_id)){
      return 2;//message deleted
    }elseif($super_user == '1'){
      return 1;
    }elseif(self::checkIfMessageCreator($msg_id)){
      return 1;
    }else{
      return 0;
    }
  }

  public static function getChannelStatusByThreadID($thread_id){
    $sql = "select archived from redcap_messages_threads
    where thread_id = '".db_escape($thread_id)."'
    limit 1";
    $q = db_query($sql);
    return db_result($q, 0);
  }

  public static function getChannelStatusInfo($thread_id){
    $sql = "select * from redcap_messages_threads
    where thread_id = '".db_escape($thread_id)."'
    limit 1";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      $channel_info[0] = $row;
    }

    return $channel_info;
  }

	public static function createNewMessageNotifications($message_id,$thread_id=null,$urgent=0,$tagged_users=null)
	{
		$urgent = empty($urgent) ? '0' : '1';
		if (!is_numeric($thread_id)) return;
		if ($thread_id == '3' || $thread_id == '1') {
			// what's new or notifications (in which thread_id = recipient_id)
			$sql = "insert into redcap_messages_status (message_id, recipient_id, recipient_user_id, urgent)
					select '".db_escape($message_id)."', $thread_id, u.ui_id, '0' from redcap_user_information u
					where u.user_suspended_time is null and u.ui_id != '".UI_ID."'";
		} else {
			// normal message
			$sql = "insert into redcap_messages_status (message_id, recipient_id, recipient_user_id, urgent)
					select m.message_id, r.recipient_id, r.recipient_user_id, '$urgent' from redcap_messages_recipients r, redcap_messages m
					where m.thread_id = r.thread_id and m.message_id = '".db_escape($message_id)."' and r.recipient_user_id != '".UI_ID."'";
		}
		$q = db_query($sql);
		// Tagging
		if (!empty($tagged_users) && is_array($tagged_users) && $thread_id != '3' && $thread_id != '1')
		{
			$recipientIdUsername = array();
			foreach ($tagged_users as $tagged_user) {
				$recipientIdUsername[] = User::getUIIDByUsername($tagged_user);
			}
			$sql = "update redcap_messages_status set urgent = 1 where message_id = '".db_escape($message_id)."'
					and recipient_user_id in (".prep_implode($recipientIdUsername).")";
			$q = db_query($sql);
		}
	}

  public static function checkIfConvAdmin($thread_id,$ui_id){
    $sql = "select conv_leader from redcap_messages_recipients
    where recipient_user_id = '".db_escape($ui_id)."' and thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);
    $conv_leader = db_result($q, 0);
    return $conv_leader;
  }

  public static function checkIfConvMember($thread_id,$ui_id){
    $sql = "select recipient_id from redcap_messages_recipients
    where recipient_user_id = '".db_escape($ui_id)."' and thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);
    $member = db_result($q, 0);
    return $member;
  }

  public static function checkIfProjMember($user,$pid){
    $sql = "select count(1) as count
    from redcap_user_information u
    left join redcap_user_rights r on u.username = r.username
    where (u.username = '".db_escape($user)."' and project_id = '".db_escape($pid)."')
    group by u.username";
    $q = db_query($sql);
    $count = db_result($q, 0);
    return $count;
  }

  public static function checkIfUrgent($id){
    $sql = "select urgent from redcap_messages_status
    where message_id = '".db_escape($id)."'
    LIMIT 1";
    $q = db_query($sql);
    $urgent = db_result($q, 0);
    return $urgent;
  }

  public static function getChannelMessagesAndMembers($thread_id,$limit)
  {
	global $lang;
    //get members
    $sql = "select u.username, u.user_firstname, u.user_lastname, u.ui_id,
	if (u.super_user='1','1',r.conv_leader) as conv_leader, t.project_id
    from redcap_messages_threads t, redcap_messages_recipients r
    left join redcap_user_information u
    on u.ui_id = r.recipient_user_id
    where t.thread_id = '".db_escape($thread_id)."' and t.thread_id = r.thread_id
    group by u.username";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
	  $linked_project_id = $row['project_id'];
      $member[$row['username']] = $row;
    }
    $limit = ($limit ? 'limit '.(int)$limit : '');

	$message = array();

	// If this is a conversation linked to a project and the user has expired privileges in the project,
	// then do NOT allow them to view the thread's messages.
	$isExpiredFromLinkedProject = empty($linked_project_id) ? false : UserRights::hasUserRightsExpired($linked_project_id, USERID);

	$sql = "select m.*, m.sent_time as normalized_ts, if (s.urgent is null, 0, s.urgent) as urgent,
	if (s.status_id is null, 0, 1) as unread, u.username, u.user_firstname, u.user_lastname, u.user_firstvisit
	from redcap_messages m
	left join redcap_user_information u on u.ui_id = m.author_user_id
	left join redcap_messages_status s on s.message_id = m.message_id and s.recipient_user_id = ".(defined("UI_ID") ? UI_ID : '0')."
	where m.thread_id = '".db_escape($thread_id)."'
	order by m.sent_time desc, m.message_id desc $limit";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$row['sent_time'] = DateTimeRC::format_ts_from_ymd($row['sent_time']);
		if($row['attachment_doc_id']){
			$row['doc_id_hash'] = Files::docIdHash($row['attachment_doc_id']);
		}
		// Sanitize
        $thisMsgBody = json_decode($row['message_body']);
        foreach ($thisMsgBody as $mkey=>$mattr) {
            $mattr->msg_body = filter_tags($mattr->msg_body);
            $thisMsgBody[$mkey] = $mattr;
        }
        $row['message_body'] = json_encode_rc($thisMsgBody);
        // Add to message array
		$message[$row['message_id']] = $row;
		// If user is expired from the project this conversation is linked to, then replace message and return single error message
		if ($isExpiredFromLinkedProject) {
			$msg_body = RCView::div(array('class'=>'red'), str_replace(array("\r\n", "\n", "\t"), array(" ", " ", " "), "<b>{$lang['messaging_05']}</b> {$lang['messaging_20']}"));
			$jsonArray = array(array('msg_body'=>$msg_body, 'action'=>'post'));
			$message[$row['message_id']]['message_body'] = json_encode_rc($jsonArray);
			break;
		}
	}

    // get total messages in channel
    $sql = "select count(1)
    from redcap_messages m
    left join redcap_user_information u on u.ui_id = m.author_user_id
    where m.thread_id = '".db_escape($thread_id)."'
    order by m.sent_time desc;";
    $q = db_query($sql);
    $total_msgs = db_result($q, 0);

    $results = array(
      "messages" => json_encode_rc($message),
      "total_messages" => $total_msgs,
      "members" => json_encode_rc($member),
    );
    return $results;
  }

  public static function findSingleConvUnread($ui_id, $findLastMessage=true){
    $sql = "select t.*, count(1) as unread, MAX(m.sent_time) as last_message_ts
    from redcap_messages_threads t, redcap_messages m
    left join redcap_messages_status s on s.message_id = m.message_id
    where m.thread_id = t.thread_id and s.recipient_user_id = '".db_escape($ui_id)."'
	and t.invisible = '0' and t.archived = '0'
    group by t.thread_id";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
		if ($findLastMessage) {
			$row['last_message'] = Messenger::findLastMessage($row['thread_id']);
		}
		$single_conv_unread[$row['thread_id']] = $row;
    }

    return $single_conv_unread;
  }
  public static function findUnreadCount($thread_id,$ui_id){
    $sql = "select count(1) as unread
    from redcap_messages_threads t, redcap_messages m
    left join redcap_messages_status s on s.message_id = m.message_id
    where m.thread_id = t.thread_id and s.recipient_user_id = '".db_escape($ui_id)."' and t.thread_id = '".db_escape($thread_id)."'
    group by t.thread_id";
    $q = db_query($sql);

    $result = db_result($q, 0);
    return ($result ? $result : 0);
  }

  public static function getListOfProjects(){
    $list = self::getProjectIdsSql();

    $sql = "select r.project_id, r.app_title as project_name
    from redcap_projects r
    where (".$list.") and r.status <= 1 and r.date_deleted is null
    order by r.project_id ASC";
    $q = db_query($sql);

    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
		$row['project_name'] = strip_tags($row['project_name']);
		$project[$count_i] = $row;
		$count_i++;
    }

    return json_encode_rc($project);
  }

  public static function findProjectName($pid){
    $sql = "select p.app_title
    from redcap_projects p
    where p.project_id = '".db_escape($pid)."'";
    $q = db_query($sql);

    $result = db_result($q, 0);
    return ($result ? strip_tags($result) : 'none');
  }

  public static function getChannelsByUIID($ui_id){
    // get conversatiions list
    $sql = "select t.*, r.prioritize, MAX(m.sent_time) as last_message_sent
    from redcap_messages m, redcap_messages_threads t
    left join redcap_messages_recipients r on r.thread_id = t.thread_id
    where t.thread_id = m.thread_id and r.recipient_user_id = '".db_escape($ui_id)."' and t.type = 'CHANNEL' and t.invisible = '0' and t.archived = '0'
    group by t.thread_id
    order by r.prioritize desc, last_message_sent desc";
    $q = db_query($sql);

    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $row['unread_count'] = self::findUnreadCount($row['thread_id'],$ui_id);
      $row['project_name'] = self::findProjectName($row['project_id']);
      $channel[$count_i] = $row;
      $count_i++;
    }

    return json_encode_rc($channel);
  }

  public static function findLastMessage($thread_id){
    $sql = "select m.message_body from redcap_messages m
    where m.thread_id = '".db_escape($thread_id)."'
    order by m.sent_time desc
    limit 1";
    $q = db_query($sql);

    $result = db_result($q, 0);

    return str_replace('.','',substr($result, 22, 8));

  }

  public static function checkConvStatus($thread_id){
    $sql = "select t.invisible, t.archived from redcap_messages_threads t
    where t.thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);

    $result = db_fetch_assoc($q);
    $invisible = $result['invisible'];
    $archived = $result['archived'];
    if($archived == '1'){
      $result = 'archived';
    }elseif($invisible == '1'){
      $result = 'deleted';
    }else{
      $result = 'active';
    }

    return $result;

  }

  public static function checkIfSuperUser($user){
    $sql = "select super_user from redcap_user_information
    where username = '".db_escape($user)."'";
    $q = db_query($sql);
    $super_user = db_result($q, 0);
    return $super_user;

  }

  public static function getAllChannelsByUIID($ui_id){
    //get conversations list
    $sql = "select t.*, r.prioritize from redcap_messages_threads t
    left join redcap_messages_recipients r on r.thread_id = t.thread_id
    where r.recipient_user_id = '".db_escape($ui_id)."' and t.type = 'CHANNEL' and t.invisible = '0'
    group by t.thread_id
    order by r.prioritize desc, t.thread_id desc";
    $q = db_query($sql);

    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $channel[$count_i] = $row;
      $count_i++;
    }

    return json_encode_rc($channel);
  }

  public static function searchChannelsLike($ui_id,$term){
    // Search conversation titles
    $sql = "select u.username, t.*, r.prioritize
    from redcap_user_information u, redcap_messages_threads t
    left join redcap_messages_recipients r on r.thread_id = t.thread_id
    where r.recipient_user_id = '".db_escape($ui_id)."' and u.ui_id = '".db_escape($ui_id)."' and t.type = 'CHANNEL'
	and u.user_suspended_time is null and t.invisible = '0' and (t.channel_name like '%".db_escape($term)."%')
    group by t.thread_id
    order by r.prioritize desc, t.thread_id desc";
    $q = db_query($sql);
    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $channel[$count_i] = $row;
      $count_i++;
    }

	// Search message bodies
    $sql = "select u.username, m.* from redcap_user_information u, redcap_messages m, redcap_messages_threads t
    left join redcap_messages_recipients r on r.thread_id = t.thread_id
    where r.recipient_user_id = '".db_escape($ui_id)."'
    and m.thread_id = t.thread_id
    and t.type = 'CHANNEL'
    and t.invisible = '0'
    and m.message_body like '%msg_body%".db_escape($term)."%msg_end%'
    and u.ui_id = m.author_user_id
    group by m.message_id
    order by m.message_id desc";
    $q = db_query($sql);
    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $row['channel_name'] = self::getChannelTileByThreadID($row['thread_id']);
      $row['channel_status'] = self::getChannelStatusByThreadID($row['thread_id']);
      $row['sent_time'] = DateTimeRC::format_ts_from_ymd($row['sent_time']);
      $message[$count_i] = $row;
      $count_i++;
    }

    $results = array(
      "channels" => json_encode_rc($channel),
      "messages" => json_encode_rc($message)
    );

    return $results;
  }

  public static function checkIfNewMessages($ui_id){
    // $ui_id = User::getUIIDByUsername(USERID);
    $sql = "select m.*, t.type, t.channel_name, count(1) as unread
    from redcap_messages_threads t, redcap_messages m
    left join redcap_messages_status s on s.message_id = m.message_id
    where m.thread_id = t.thread_id and s.recipient_user_id = '".db_escape($ui_id)."'
    group by t.type, m.message_id";
    $q = db_query($sql);

    while ($row = db_fetch_assoc($q))
    {
      $new_message[$row['message_id']] = $row;
    }

    return count($new_message);
  }

  public static function renderHeaderIcon($origin){
    global $lang, $user_messaging_enabled;
    if (!$user_messaging_enabled) return;
    $new_message_count = self::checkIfNewMessages(UI_ID);
    $badgeClass = ($new_message_count > 0 ? 'new-message-total-badge-show' : '');
    $badgeClassNav = ($new_message_count > 0 ? 'newmsgs' : '');
    if($origin == 'project-page'){
      $html = "<div class='notifications-icon-container'>
      <span class='badgerc new-message-total-badge ".$badgeClass."'>!</span>
      </div>";
    }else if($origin == 'navbar'){
      $html = "<a class='nav-link navbar-user-messaging $badgeClassNav'>
      <i class='fas fa-comment-alt'></i>
      {$lang['messaging_08']}
      <span class='badgerc new-message-total-badge ".$badgeClass."'>!</span></a>";
    }
    return $html;
  }

	// Obtain array list of all users in a given conversation
	public static function getConversationUsers($thread_id, $exclude_current_user=false)
	{
		$users = array();
		if (!is_numeric($thread_id)) return $users;
		$sqlb = ($exclude_current_user && defined("UI_ID")) ? "and i.ui_id != ".UI_ID : "";
		$sql = "select trim(lower(i.username)) as username
				from redcap_messages_recipients r, redcap_messages_threads t, redcap_user_information i
				where t.thread_id = r.thread_id and t.thread_id = '".db_escape($thread_id)."' and r.recipient_user_id = i.ui_id
				and i.username != '' and i.username is not null $sqlb order by trim(lower(i.username))";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$users[] = $row['username'];
		}
		return $users;
	}

	// Return boolean if a user belongs to this thread
	public static function isConversationMember($thread_id=null, $ui_id=null)
	{
		if (!is_numeric($ui_id) || !is_numeric($thread_id) || $thread_id == '1' || $thread_id == '3') return false;
		$sql = "select 1 from redcap_messages_recipients
				where thread_id = $thread_id and recipient_user_id = $ui_id limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Obtain array list of all users whose username begins with a search term where username=key and conversation member=value.
	public static function searchConversationUsers($usernameSearchTerm='', $thread_id=null, $exclude_current_user=false)
	{
		$users = array();
		$usernameSearchTerm = trim($usernameSearchTerm);
		if ($usernameSearchTerm == '' || !is_numeric($thread_id)) return $users;
		$sqlb = ($exclude_current_user && defined("UI_ID")) ? "and i.ui_id != ".UI_ID : "";
		$sql = "select trim(lower(i.username)) as username, if (r.recipient_user_id is null, 0, 1) as member
				from redcap_user_information i
				left join redcap_messages_recipients r on r.recipient_user_id = i.ui_id and r.thread_id = '".db_escape($thread_id)."'
				left join redcap_messages_threads t on t.thread_id = r.thread_id
				where i.username like '".db_escape($usernameSearchTerm)."%' $sqlb
				order by r.recipient_user_id desc, trim(lower(i.username))";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$users[$row['username']] = $row['member'];
		}
		return $users;
	}

  public static function messagingNotificationsPreferences($messaging_email_preference, $messaging_email_urgent_all){
    global $lang, $user_messaging_enabled;
    if (!$user_messaging_enabled) return '';
    $html = "<tr>
    			<td colspan='2' style='border-top:1px solid #ddd;padding:10px 8px 5px;'>
    				<div style='color:#800000;font-weight:bold;font-size:14px;'>{$lang['messaging_10']}</div>
    				<div style='color:#555;font-size:11px;line-height:13px;padding:6px 0 3px;'>{$lang['messaging_11']}</div>
    			</td>
    			</tr>
    			<tr>
    				<td valign='top' style='padding-top:8px;'>
    					{$lang['messaging_12']}
    				</td>
    				<td style='white-space:nowrap;color:#800000;'>
    					".RCView::select(array('name'=>'messaging_email_preference', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
    					User::getMessagingEmailPreferencesOptions(), isset($messaging_email_preference) ? $messaging_email_preference : '')."
    				</td>
    			</tr>
    			<tr>
    				<td valign='top' style='padding-top:8px;'>
    					{$lang['messaging_13']}
    				</td>
    				<td class='clearfix' style='color:#800000;'>
						<div class='float-left'>
							".RCView::checkbox(array('name'=>'messaging_email_urgent_all', 'value'=>'1', ($messaging_email_urgent_all == '1' ? 'checked' : '')=>($messaging_email_urgent_all == '1' ? 'checked' : '')))."
    					</div>
						<div class='float-right' style='width:260px;color:#800000;font-size:11px;padding-top:3px;'>
    						{$lang['messaging_14']}
    					</div>
    				</td>
    			</tr>
     		<tr>
    			<td></td>
    			<td style='white-space:nowrap;color:#800000;padding-bottom:20px;'>
    				<button class='jqbutton' style='font-weight:bold;font-family:arial;' onclick=\"if(validateUserInfoForm()){ $('#form').submit(); } return false;\">{$lang['user_98']}</button>
    			</td>
    		</tr>";

    return $html;
  }

  public static function setMessagingEmailTs($username){
    $username = db_escape($username);

    $sql = "update redcap_user_information
    SET messaging_email_ts = '".NOW."'
    WHERE username = '$username'
    LIMIT 1";
    $q = db_query($sql);
    return ($q && $q !== false);
  }

  public static function processEmailPref($mep, $me_ts)
  {
    switch ($mep) {
      case 'NONE':
		return false;
		break;
      case 'DAILY':
        $hours = 24;
        $twentyFourHoursAgo = date("Y-m-d H:i:s", mktime(date("H")-$hours,date("i"),date("s"),date("m"),date("d"),date("Y")));
        return ($me_ts == '' || $me_ts < $xHoursAgo);
        break;
      default:
		list ($hours, $word) = explode("_", $mep, 2);
        $xHoursAgo = date("Y-m-d H:i:s", mktime(date("H")-$hours,date("i"),date("s"),date("m"),date("d"),date("Y")));
        return ($me_ts == '' || $me_ts < $xHoursAgo);
    }
  }

  public static function calculateNewLimit($single_conv_unread,$thread_id,$limit){
    $unread = $single_conv_unread[$thread_id]['unread'];
    return $limit+$unread;
  }

  public static function findLastMessageTs($thread_id){
    $sql = "select m.sent_time from redcap_messages m
    where m.thread_id = '".db_escape($thread_id)."'
    order by m.sent_time desc
    limit 1";
    $q = db_query($sql);

    $result = db_result($q, 0);

    return $result;

  }

  public static function checkIfNoNewMessages($ui_id,$me_ts){
    $unread_convs = self::findSingleConvUnread($ui_id,false);
    foreach ($unread_convs as $item) {
      if ($me_ts == '' || $item['last_message_ts'] > $me_ts){
        return true;
      }
    }
    return false;
  }

  public static function checkIfConvLeader($thread_id,$username){
    $ui_id = User::getUIIDByUsername($username);
    $sql = "select conv_leader from redcap_messages_recipients
    where recipient_user_id = '".db_escape($ui_id)."' and thread_id = '".db_escape($thread_id)."'";
    $q = db_query($sql);
    $prioritize = db_result($q, 0);
    return ($prioritize == '1' ? '' : 'hidden');
  }

  public static function sendNotificationToParticipants($participants,$thread_id,$author_ui_id){
    foreach ($participants as $participant) {
      $participant_ui_id = $participant['participant'];
      //-----retrieve message_id--------//
      $sql = "select message_id from redcap_messages
      where (thread_id = '".db_escape($thread_id)."' and author_user_id = '".db_escape($author_ui_id)."')
      ORDER by message_id desc
      LIMIT 1";
      $q = db_query($sql);
      $message_id = db_result($q, 0);
      //-----retrieve recipient_id-----//
      $sql = "select recipient_id from redcap_messages_recipients
      where (thread_id = '".db_escape($thread_id)."' and recipient_user_id = '".db_escape($participant_ui_id)."')
      ORDER by recipient_id desc
      LIMIT 1";
      $q = db_query($sql);
      $recipient_id = db_result($q, 0);
      // var_dump($recipient_id);
      //--Insert data into redcap_messages_status---//
      $sql = "insert into redcap_messages_status (message_id, recipient_id, recipient_user_id) values
      ('".db_escape($message_id)."', '".db_escape($recipient_id)."' ,'".db_escape($participant_ui_id)."')";
      $q = db_query($sql);
    }
  }

  public static function getUserPermissions($username){
    $userinfo = User::getUserInfo($username);
    $super_user = $userinfo['super_user'];
    return $super_user;
  }

	public static function checkIfTaggedMembers($msg_body, $thread_id=null)
	{
		$tagged_users = array();
		// If thread_id is not a number, return
		if (!is_numeric($thread_id)) return array($tagged_users, $msg_body);
		// If message doesn't contain @, then skip all this
		if (strpos($msg_body, '@') === false) {
			return array($tagged_users, $msg_body);
		}
		// First remove all non-username characters from the string
		$msg_body_formatted = preg_replace("/[^a-zA-Z0-9-.@_]/", " ", $msg_body);
		// Find username pattern matches in string
		preg_match_all("/(@\S+)/", $msg_body_formatted, $matches);
		if (isset($matches[0]) && !empty($matches[0]))
		{
			// Obtain list of users in this conversatino
			$usersInConversation = self::getConversationUsers($thread_id);
			// Loop through possible
			foreach ($matches[0] as $possible_user) {
				// Remove @
				$possible_user = substr($possible_user, 1);
				// Remove period at end, if applicable
				if (substr($possible_user, -1) == '.') $possible_user = substr($possible_user, 0, -1);
				// Is this user in the conversation
				if (in_array($possible_user, $usersInConversation)) {
					$tagged_users[$possible_user] = strlen($possible_user);
				}
			}
		}
		// Re-order tagged_users array by longest name first to deal with sub-matches inside other usernames
		arsort($tagged_users);
		// Loop through all tagged users and wrap each with tag/CSS styling in msg
		foreach (array_keys($tagged_users) as $this_tagged_user) {
			// Replace it in the string
			$msg_body = str_replace("@".$this_tagged_user, '<span class="mention">@'.$this_tagged_user.'</span>', $msg_body);
		}
		// Return results
		return array(array_keys($tagged_users), $msg_body);
	}

  public static function getArchivedConversations($username){
    $ui_id = User::getUIIDByUsername($username);
    $sql = "select t.* from redcap_messages_threads t
    left join redcap_messages_recipients r on r.thread_id = t.thread_id
    where t.type = 'CHANNEL' and t.invisible = '0' and t.archived = '1' and r.recipient_user_id = '".db_escape($ui_id)."'
    group by t.thread_id
    order by t.thread_id asc";
    $q = db_query($sql);

    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $channel[$count_i] = $row;
      $count_i++;
    }

    return $channel;
  }

  public static function buildMessageInput($thread_id,$username,$input_message){
	global $lang;
    $is_dev = self::hasDevPrivileges();
    switch ($thread_id) {
      case '1'://what's new
        $input_container = ''; //($is_dev ? '<div class="msg-input-new-container"><a class="add-new-whatsnew">add new</a></div>' : '');
        break;
      case '3'://notifications
         $input_container = (SUPER_USER ? '<div class="msg-input-new-container"><textarea class="msg-input-new tinyNoEditor" row="1" placeholder="'.js_escape2($lang['messaging_29']).'">'.$input_message.'</textarea><div class="messaging-mark-as-important-container"><p class="messaging-mark-as-important-text">Mark as important</p><input class="messaging-mark-as-important-cb" type="checkbox"></div></div>': '');
        break;
      default: //conversations
        $input_container = '<div class="msg-input-new-container" data-tagged="'.$_SESSION['tagged_data'].'">
          <textarea class="msg-input-new tinyNoEditor" row="1" placeholder="'.js_escape2($lang['messaging_32']).'">'.$input_message.'</textarea>
          <button class="btn btn-defaultrc btn-xs upload-file-button-container">
          '.RCView::img(array("src"=>"attach.png","class"=>"upload-file-button-icon")).'
          '.$lang['messaging_31'].'
          </button>
          <div class="messaging-mark-as-important-container">
            <p class="messaging-mark-as-important-text">"'.$lang['messaging_30'].'"</p>
            <input class="messaging-mark-as-important-cb" type="checkbox">
          </div>
        </div>';
    }
    return $input_container;
  }

  public static function csvDownload($thread_id){
    $sql = "select m.sent_time as time_posted, u.username as posted_by_username, u.user_firstname as posted_by_firstname,
	u.user_lastname as posted_by_lastname, m.message_body as message
    from redcap_messages m
    left join redcap_user_information u on u.ui_id = m.author_user_id
    where m.thread_id = '".db_escape($thread_id)."'
    order by m.sent_time";
    $q = db_query($sql);
    $count_i = 0;
    while ($row = db_fetch_assoc($q))
    {
      $row['message'] = self::parseMessageDataForCsv($row['message']);
      $row['time_posted'] = DateTimeRC::format_ts_from_ymd($row['time_posted']);
      $message[$count_i] = $row;
      $count_i++;
    }

    $content = arrayToCsv($message);

    return $content;
  }

  public static function calculateMessageWindowPosition(){
    $top = $_SESSION['msg_container_top'];
    $int = (int)$top;
    $pos = strpos($_SERVER['REQUEST_URI'], 'pid=');
    return $top;
  }

  public static function checkIfNewNotifications($username){
    $ui_id = User::getUIIDByUsername($username);
    $sql = "select count(1) as unread
    from redcap_messages_threads t, redcap_messages m
    left join redcap_messages_status s on s.message_id = m.message_id
    where m.thread_id = t.thread_id and s.recipient_user_id = '".db_escape($ui_id)."' and (m.thread_id = 3 or m.thread_id = 1)
    group by t.type, m.message_id";
    $q = db_query($sql);
    $num = db_num_rows($q);
    return ($num > 0 ? '1' : '0');
  }

  public static function generateChannelsHtml($thread_id){
    if(USERID == 'USERID'){
      return;
    }else{
      $ui_id = User::getUIIDByUsername(USERID);
      $channels = self::getChannelsByUIID($ui_id);
      $channels = json_decode($channels);
      $channels_html = '';
      foreach($channels as $channel){
        $thread_selected = ($channel->thread_id == $thread_id ? 'thread-selected' : '');
        $thread_status = ($channel->archived == 0 ? 'active' : 'archived');
        $glyphicon = ($channel->prioritize == '1' ? 'fas fa-thumbtack' : 'fas fa-comment-alt');
        $span = '<span class="'.$glyphicon.'"></span>';
        $project_id = ($channel->project_id ? $channel->project_id : 'none');
        $project_name = ($channel->project_id ? $channel->name : 'none');
        $project_icon = ($channel->project_id ? '<img class="conv-title-proj" src="'.APP_PATH_IMAGES.'blog.png">' : '');
        $title = '<p class="mc-message-text" data-tooltip="'.RCView::escape($channel->channel_name).'">'.$project_icon.RCView::escape($channel->channel_name).'</p>';
        $badge = '<span class="mc-message-badge">'.($channel->unread_count != 0 ? $channel->unread_count : '').'</span>';
        $priority_class= ($channel->prioritize == '1' ? 'priority-class' : '');
        $html = '<div class="mc-message '.$priority_class.' '.$thread_selected.'" thread-id="'.$channel->thread_id.'" data-updated="0" data-prioritize="'.$channel->prioritize.'" data-status="'.$thread_status.'" data-project-id="'.$project_id.'" data-project-name="'.htmlspecialchars($project_name, ENT_QUOTES).'">'
        .$span
        .$title
        .$badge
        .'</div>';
        $channels_html .= $html;
      }
    }
    return $channels_html;
  }

  public static function checkPageRestrictions(){
    global $user_messaging_enabled, $auth_meth_global, $auth_meth;
    //restrictions list
    $user_messaging_enabled = ((PAGE == 'surveys/index.php' || PAGE == 'Surveys/theme_view.php') ? 0 : $user_messaging_enabled);
    $user_messaging_enabled = (USERID == 'USERID' ? 0 : $user_messaging_enabled);
    $user_messaging_enabled = (defined("PLUGIN") && !isset($_GET['pid']) ? 0 : $user_messaging_enabled);
    $user_messaging_enabled = ((defined("PROJECT_ID") && $auth_meth_global != 'none' && $auth_meth == 'none') ? 0 : $user_messaging_enabled);
    $user_messaging_enabled = ((isset($_SESSION[FhirLauncher::SESSION_NAME]) && is_array($_SESSION[FhirLauncher::SESSION_NAME])) ? 0 : $user_messaging_enabled);
    return $user_messaging_enabled;
  }

  public static function generateAttachmentData($file_preview,$doc_name,$img_height=null){
    if($file_preview == '1'){
      $action = 'start-download';
    }elseif($file_preview == '2'){
      $action = 'start-download';
    }else{
      $action = 'display';
    }
    $attachment_data = array('doc_name'=>$doc_name, 'action'=>$action);
	if (is_numeric($img_height)) {
		$attachment_data['img_height'] = $img_height;
	}
    return json_encode_rc(array($attachment_data));
  }

  public static function createMsgBodyObj($msg_body,$username,$action,$msg_id='',$important='0'){
    // JSON encode the message elements.
	// Use 'msg_end' as bookend to aid in keyword searching inside messages.
    if($action == 'post'){
		$jsonArray = array(array('msg_body'=>$msg_body, 'msg_end'=>'', 'important'=>$important, 'action'=>$action, 'user'=>$username, 'ts'=>DateTimeRC::format_ts_from_ymd(NOW)));
    }elseif($action == 'edit' || $action == 'delete'){
      $sql = "select message_body from redcap_messages where message_id = '".db_escape($msg_id)."'";
      $q = db_query($sql);
	  $jsonArray = array_shift(json_decode(db_result($q, 0), true));
      $jsonArray = array(
						$jsonArray,
						array('msg_body'=>$msg_body, 'msg_end'=>'', 'important'=>$important, 'action'=>$action, 'user'=>$username, 'ts'=>DateTimeRC::format_ts_from_ymd(NOW))
					);
    }elseif($action == 'notify'){
		$jsonArray = array(array('msg_body'=>$msg_body, 'msg_end'=>'', 'important'=>$important, 'action'=>"$action($msg_id)", 'user'=>$username, 'ts'=>DateTimeRC::format_ts_from_ymd(NOW)));
    }elseif($action == 'what-new'){
		$jsonArray = array(array('title'=>$msg_body['title'], 'description'=>$msg_body['description'], 'link'=>$msg_body['link'],
					'action'=>$action, 'ts'=>DateTimeRC::format_ts_from_ymd(NOW)));
    }
	return json_encode_rc($jsonArray);
  }

  public static function parseMessageDataForCsv($data){
    $data = json_decode($data);
    $message = $data[count($data)-1]->msg_body;
    return $message;
  }

  public static function parseMessageData($thread_id,$data){
	global $lang;
    //convert data string to php obj
    $data = json_decode($data);
    //check last action in data array and define message variables
    $action = $data[count($data)-1]->action;
    $message = $data[count($data)-1]->msg_body;
    $who_did_it = $data[count($data)-1]->user;
    $data_edited = '';
    $msg_id = '';
    //this handles the notify message
    if($action != 'edit' && $action != 'delete' && $action != 'post' && $action != 'what-new'){
      $action = str_replace('notify(','',$action);
      $msg_id = str_replace(')','',$action);
      $msg_id = 'data-anchor="'.$msg_id.'"';
      $message .= ' <span class="click-to-go-notify">'.$lang['messaging_33'].'</span>';
    }
    if($action == 'edit'){
      $message .= ' <span class="msg-edited-click">'.$lang['messaging_34'].'</span>';
      $data_edited = 'edited';
    }elseif($action == 'delete'){
      $message = '<span class="msg-deleted-click">'.$lang['messaging_35'].' "'.$who_did_it.'"'.$lang['period'].' '.$lang['messaging_36'].'</span>';
      $data_edited = 'deleted';
    }elseif($action == 'what-new'){
      $message = '<div class="what-new-message-wrapper"><h4 class="new-feature-title">'.$data[count($data)-1]->title.'</h4>
      <h5 class="what-new-message-description">'.nl2br($data[count($data)-1]->description).'</h5>'.
      ($data[count($data)-1]->link == '' ? '' : '<h5 class="what-new-message-link"><a href="'.$data[count($data)-1]->link.'">'.$lang['control_center_62'].'</a></h5>').
      '</div>';
		// Replace URLs with clickable links
		$message = preg_replace('/(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '<a href="$1" style="text-decoration:underline;" target="_blank">$1</a>', $message);
		$message = preg_replace('/(([a-zA-Z0-9\-\_\.])+@[a-zA-Z\_]+?(\.[a-zA-Z]{2,6})+)/ims', '<a href="mailto:$1" style="text-decoration:underline;" target="_blank">$1</a>', $message);
      return $message;
    }
    // Replace URLs with clickable links
    $message = preg_replace('/(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '<a href="$1" style="text-decoration:underline;" target="_blank">$1</a>', $message);
    $message = preg_replace('/(([a-zA-Z0-9\-\_\.])+@[a-zA-Z\_]+?(\.[a-zA-Z]{2,6})+)/ims', '<a href="mailto:$1" style="text-decoration:underline;" target="_blank">$1</a>', $message);
    return '<p class="'.($thread_id == '3' ? 'msg-body-notifications': 'msg-body').'" data-edited="'.$data_edited.'" '.$msg_id.'>'.filter_tags(nl2br($message)).'</p>';
  }

  public static function renderMessenger(){
    global $lang, $user_messaging_enabled;
    $user_messaging_enabled = self::checkPageRestrictions();
    if ($user_messaging_enabled)
	{
      loadJS('Messenger.js');
      if (!isset($_SESSION['mc_open'])) {
        $_SESSION["mc_open"] = '0';
      }
      // var_dump($_SESSION['mc-open']);
      $open_class = ($_SESSION['mc_open'] == '1' ? 'mc-open' : 'mc-close');
      $thread_id = $_SESSION['thread_id'];
      $input_message = $_SESSION['thread_msg'];
      $textarea_class = (strlen($input_message) > 20 ? 'expanded' : '');
      $username = defined("USERID") ? USERID : $_SESSION['username'];
      $conv_win_size = ($_SESSION['conv_win_size'] != '' ? $_SESSION['conv_win_size'] : '100px');
      $important = ($_SESSION['important'] == '1' ? 'checked' : '');
      $conversation_info = self::getChannelStatusInfo($thread_id);
      if($conversation_info[0]['archived'] == '1' || $conversation_info[0]['invisible'] == '1'){
        $thread_id = '';
        $class = '';//just a start
      }
      // check if new Notifications
      $newNotifications = self::checkIfNewNotifications($username);
      $notificationsClass = ($newNotifications == '1' ? 'notifications-open' : 'notifications-close');
      if($thread_id == '1' || $thread_id == '3'){
        $notificationsClass = 'notifications-open';
      }
      //if a thread window is open
      if($thread_id != ''){
        if($thread_id != '1' && $thread_id != '3'){//conversations
          $conversation_info = self::getChannelStatusInfo($thread_id);
          $conversation_title = self::getChannelTileByThreadID($thread_id);
          $action_icons_state = $_SESSION["action_icons_state"];
        }else{
          $conversation_title = ($thread_id == '1' ? $lang['messaging_06'] : $lang['messaging_04']);
          $action_icons_state = 'closed';
        }
        $class = 'show-override';
        $data = self::getChannelMessagesAndMembers($thread_id,10);
        $messages = $data['messages'];
        $total_msgs = $data['total_messages'];
        $messages = json_decode($messages);
        // $result = gettype($messages);
        $members = $data['members'];
        $members = json_decode($members);
        $members_html = '';
        if($thread_id != '1' && $thread_id != '3'){//only for conversations
          $total_members = count((array)$members);
          $total_members_string = $total_members.' Members';
          $members_i = 0;
          $input_container = self::buildMessageInput($thread_id,$username,$input_message);
         //logic for members
          foreach($members as $member){
            $convLeaderClass = ($member->conv_leader == '1' ? 'conv-leader' : '');
            if($total_members == 1){
              $members_html .= '( '.'<p class="conv-members-text '.$convLeaderClass.'" data-id="'.$member->ui_id.'" data-full-name="'.$member->user_firstname.' '.$member->user_lastname.'">'.$member->username.'</p>'.')';
            }elseif($members_i == 0){
              $members_html .= '( '.'<p class="conv-members-text '.$convLeaderClass.'" data-id="'.$member->ui_id.'" data-full-name="'.$member->user_firstname.' '.$member->user_lastname.'">'.$member->username.'</p>'.', ';
            }elseif(($total_members - $members_i) == 1){
              $members_html .= '<p class="conv-members-text '.$convLeaderClass.'" data-id="'.$member->ui_id.'" data-full-name="'.$member->user_firstname.' '.$member->user_lastname.'">'.$member->username.'</p>'.')';
            }else{
              $members_html .= '<p class="conv-members-text '.$convLeaderClass.'" data-id="'.$member->ui_id.'" data-full-name="'.$member->user_firstname.' '.$member->user_lastname.'">'.$member->username.'</p>'.', ';
            }
            $members_i++;
          }
        }elseif($thread_id == '1' || $thread_id == '3'){
          $total_members_string = $lang['messaging_37'].' '.$lang['messaging_38'];
          $input_container = self::buildMessageInput($thread_id,$username,$input_message);
        }
        $messages_html = '';
        $separator_var = '';
        //logic for messages
        $imageCount = 0;
        $html_array = array();
        foreach($messages as $message){
          array_push($html_array, $message);
        }
        if(count($messages) == 0){
          $messages_html = '<p class="empty-notification">'.$lang['messaging_39'].'</p>';
        }
        foreach(array_reverse($html_array) as $message){
          $messageDay = substr($message->normalized_ts,8,2);
          $messageMonth = substr($message->normalized_ts,5,2);
          $messageYear = substr($message->normalized_ts,0,4);
          if($separator_var != $messageYear.$messageMonth.$messageDay){
            $sent_time_array = explode(' ',$message->sent_time);
            $separator_var = $messageYear.$messageMonth.$messageDay;
			$dateDisplay = ($separator_var == date("Ymd")) ? $lang['dashboard_32'] : $sent_time_array[0];
			$time_separator= '<p class="messages-date-recents" data-date="'.$separator_var.'">'.$dateDisplay.'</p>';
          }else{
            $time_separator= '';
          }
          //refactor
          switch($thread_id){
            case '1':// what's new
              $title = '';
              $image = RCView::img(array('src'=>'redcap-logo-letter.png','class'=>'msg-user-image'));
              $msg_body = self::parseMessageData($thread_id,$message->message_body);
              $link = '';
              $name = $lang['messaging_06'];
            break;
            case '3'://general notifications
              $title = '';
			  $msgBodyJson = json_decode($message->message_body);
              $urgentSpan = (isset($msgBodyJson[0]->important) && $msgBodyJson[0]->important ===  '1' ? '<span class="urgent-span">!</span>' : '');
              $image = RCView::img(array('src'=>'redcap-logo-letter.png','class'=>'msg-user-image'));
              $msg_body = self::parseMessageData($thread_id,$message->message_body);
              $name = $lang['messaging_07'];
              $link = '';
              $edit = '';
              $download = '';
            break;
            default: //conversations
            $str = substr($message->user_firstvisit,11,strlen($message->user_firstvisit));
            $arr = explode(':',$str);
            $bk_color = 'rgb('.($arr[0]*1).'0,'.($arr[1]*1).'0,'.($arr[2]*1).'0)';
            $title = '';
			$msgBodyJson = json_decode($message->message_body);
            $urgentSpan = (isset($msgBodyJson[0]->important) && $msgBodyJson[0]->important ===  '1' ? '<span class="urgent-span">!</span>' : '');
            $image = '<div class="channel-msg-user-image" style="background-color:'.$bk_color.'">'.substr($message->user_firstname,0,1).substr($message->user_lastname,0,1).'</div>';
            $msg_body = self::parseMessageData($thread_id,$message->message_body);
            $name = $message->username;
            $link = '';
            $download = '';
            // $edit = (!strpos($msg_body, 'data-anchor') ? RCView::img(array("src"=>"dot_dot_dot.png","class"=>"dot-dot-dot")) : '');
            if(!strpos($msg_body, 'data-anchor') && !strpos($msg_body, 'data-edited="deleted"')){
              $edit = RCView::img(array("src"=>"dot_dot_dot.png","class"=>"dot-dot-dot"));
            }else{
              $edit = '';
            }
            //if there is attachment_doc_id and message is not deleted show image
            if($message->attachment_doc_id && !strpos($msg_body, 'data-edited="deleted"')){
              if($message->stored_url){
                $attachment_data = json_decode($message->stored_url);
                $file_name = $attachment_data[0]->doc_name;
                $action = $attachment_data[0]->action;
                $file_name_html = '<p class="uploaded-file-text">'.$file_name.'</p>';
                if($action === 'iframe-preview' || $action === 'start-download'){
                  $data_url = APP_PATH_WEBROOT.'DataEntry/file_download.php?doc_id_hash='.$message->doc_id_hash.'&id='.$message->attachment_doc_id.'&origin=messaging';
                  $download = '<div class="msgs-dowload-file" data-url="'.$data_url.'" data-hash-id="'.$message->doc_id_hash.'" data-id="'.$message->attachment_doc_id.'">
                  <span>'.$file_name_html.'</span>
                  </div>';
                }else{
				  // Manually set image height to improve auto-scrolling
				  $styleHeight = '';
				  if (isset($attachment_data[0]->img_height)) {
					if ($attachment_data[0]->img_height > 100) {
						$styleHeight = 'style="height:100px;"';
					} else {
						$styleHeight = 'style="height:'.$attachment_data[0]->img_height.'px;"';
					}
				  }
                  $usleep = (100000)*$imageCount;
                  $data_url = APP_PATH_WEBROOT.'DataEntry/image_view.php?doc_id_hash='.$message->doc_id_hash.'&id='.$message->attachment_doc_id.'&origin=messaging';
                  $imageCount += 1;
                  $download = '<div class="msgs-dowload-file" data-hash-id="'.$message->doc_id_hash.'" data-id="'.$message->attachment_doc_id.'">
                  <img class="msgs-image-preview" data-url="'.$data_url.'" '.$styleHeight.' src="'.$data_url.'&usleep='.$usleep.'">
                  '.$file_name_html.'
                  </div>';
                }
              }
            }
            break;
          }
          //end refactor
          $html = $time_separator.'<div class="msg-container" data-id="'.$message->message_id.'">'
          .$image
          .'<p class="msg-name">'.$name.'</p>'
          .'<p class="msg-time" data-urgent="'.$message->urgent.'" data-time="'.$message->sent_time.'">'.$message->sent_time.$urgentSpan.'</p>'
          .$edit
          .$title
          .$msg_body
          .$link//use as debugger
          .$download
          .'</div>';
          $messages_html .= $html;
        }
        // var_dump($html_array);
        $action_icons_html = '';
        $action_icon_class = self::checkIfConvLeader($thread_id,$username);
        $conv_leader = (SUPER_USER ? '1' : ($action_icon_class === 'hidden' ? '0' : '1'));
        $nonleader_icon_class = ($action_icon_class === 'hidden' ? '' : 'hidden');
        //channels
        $ui_id = User::getUIIDByUsername($username);
        $channels = self::getChannelsByUIID($ui_id);
        $channels = json_decode($channels);
        $channels_html = '';
        foreach($channels as $channel){
          $thread_selected = ($channel->thread_id == $thread_id ? 'thread-selected' : '');
          $thread_status = ($channel->archived == 0 ? 'active' : 'archived');
          $glyphicon = ($channel->prioritize == '1' ? 'fas fa-thumbtack' : 'fas fa-comment-alt');
          $span = '<span class="'.$glyphicon.'"></span>';
          $title = '<p class="mc-message-text" data-tooltip="'.RCView::escape($channel->channel_name).'">'.RCView::escape($channel->channel_name).'</p>';
          $badge = '<span class="mc-message-badge"></span>';
          $priority_class= ($channel->prioritize == '1' ? 'priority-class' : '');
          $html = '<div class="mc-message '.$priority_class.' '.$thread_selected.'" thread-id="'.$channel->thread_id.'" data-updated="0" data-prioritize="'.$channel->prioritize.'" data-status="'.$thread_status.'">'
          .$span
          .$title
          .$badge
          .'</div>';
          $channels_html .= $html;
        }
        $action_icons_class = ($thread_id != '1' && $thread_id != '3' ? '' : 'hidden');
        if($thread_id == '1'){
          $what_is_new_thread_selected = 'thread-selected';
          $notifications_thread_selected = '';
        }elseif($thread_id == '3'){
          $what_is_new_thread_selected = '';
          $notifications_thread_selected = 'thread-selected';
          $small_screen_class = 'admin-small-screen';
        }else{
          $what_is_new_thread_selected = '';
          $notifications_thread_selected = '';
        }
      }else{
        $conversation_title = '';
        $class = '';
        $input_container = '';
        $total_msgs = '';
        $mark_as_important = '';
        $action_icons_html = '';
        $action_icon_class = $nonleader_icon_class = '';
        $small_screen_class = '';
        $conv_leader = '';
        $action_icons_state = 'closed';
        $what_is_new_thread_selected = '';
        $notifications_thread_selected = '';
      }

      $html = "<div class='message-center-container ".$open_class."' data_open='".$_SESSION["mc_open"]."' style='height:".$_SESSION["message_center_container_height"]."'>
        ".RCView::img(array('src'=>'close_button_white.png','class'=>'messaging-close-btn'))."
        ".RCView::img(array('src'=>'gear-white.png','class'=>'messaging-settings-button'))."
        <div class='message-center-header clearfix'>
			<img src='".APP_PATH_IMAGES."messenger_logo.png'>  
		</div>
        <div class='message-center-notifications-container mc-section-container'>
          <h4 class='mc-section-title'>".$lang['messaging_40']."</h4>
          <span class='mc-what-new-alert hidden' data-thread='notification'></span>
          ".RCView::img(array('src'=>'up-arrow-white.png','class'=>'message-center-show-hide message-center-conv-icon '.$notificationsClass.'','data-section'=>'notification','data-tooltip'=>'expand'))."
          <div class='message-section ".$notificationsClass."' data-section='notification'>
            <div class='mc-message ".$what_is_new_thread_selected."' thread-id='1'><div class='mc-message-text' data-tooltip='".js_escape($lang['messaging_06'])."'>".$lang['messaging_06']."</div><span class='mc-message-badge'></span></div>
            <div class='mc-message ".$notifications_thread_selected."' thread-id='3' data-admin='".(SUPER_USER ? 'yes' : 'no')."'><div class='mc-message-text' data-tooltip='".js_escape($lang['messaging_04'])."'>".$lang['messaging_04']."</div><span class='mc-message-badge'></span></div>
          </div>
        </div>
        <div class='message-center-channels-container mc-section-container'>
          <h4 class='mc-section-title'>".$lang['messaging_24']."</h4>
          <span class='mc-what-new-alert hidden' data-thread='channel'></span>
          ".RCView::img(array('src'=>'archived_icon_white.png','class'=>'message-center-archived-conv message-center-conv-icon','data-section'=>'channel','data-tooltip'=>$lang['messaging_41']))."
          ".RCView::img(array('src'=>'magnifying-glass-white.png','class'=>'message-center-search-conversation message-center-conv-icon','data-section'=>'channel','data-tooltip'=>$lang['messaging_42']))."
          ".RCView::img(array('src'=>'plus-icon-white.png','class'=>'message-center-create-new message-center-conv-icon','data-section'=>'channel','data-tooltip'=>$lang['messaging_01']))."
          ".RCView::img(array('src'=>'list_red_messaging.png','class'=>'message-center-expand message-center-conv-icon','data-section'=>'channel','data-open'=>"$conv_win_size"))."
          <div class='message-section' data-section='channel' style='height:".$conv_win_size."px'>
            ".self::generateChannelsHtml($thread_id)."
          </div>
        </div>
        <div class='message-center-messages-container ".$class."' data-thread_id='".$thread_id."' data-total-msgs='".$total_msgs."' style='top: ".self::calculateMessageWindowPosition().";height:".$_SESSION['msg_container_height']."px'>
          ".RCView::img(array('src'=>'up-arrow-black.png','class'=>'close-btn-small'))."
          <h4 class='conversation-title' data-tooltip=".json_encode_rc($conversation_title).">".RCView::escape($conversation_title)."</h4>
          <div class='action-icons-wrapper'>
			<div class='btn-group'>
				<button type='button' class='btn btn-defaultrc btn-xs dropdown-toggle' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
					".$lang['control_center_4540']." <span class='caret'></span>
				</button>
				<ul class='dropdown-menu'>
					<li class='mc-action-icon mc-icon-add-users $action_icon_class'>
						<a href='#'>".RCView::img(array('src'=>'user_add3.png'))." ".$lang['messaging_44']."</a></li>
					<li class='mc-action-icon mc-icon-remove-users $action_icon_class'>
						<a href='#'>".RCView::img(array('src'=>'list-remove-user.png'))." ".$lang['messaging_45']."</a></li>
					<li class='mc-action-icon mc-icon-remove-self $nonleader_icon_class'>
						<a href='#'>".RCView::img(array('src'=>'list-remove-user.png'))." ".$lang['messaging_46']."</a></li>
					<li class='mc-action-icon mc-icon-rename-conv $action_icon_class'>
						<a href='#'>".RCView::i(array('class'=>'fas fa-pencil-alt'),'')." ".$lang['messaging_47']."</a></li>
					<li class='mc-action-icon mc-icon-member-perm'>
						<a href='#'>".RCView::i(array('class'=>'fas fa-user'),'')." ".$lang['messaging_48']."</a></li>
					<li class='mc-action-icon mc-icon-pin-to-top'>
						<a href='#'>".RCView::span(array('class'=>'fas fa-thumbtack'),'')." ".$lang['messaging_49']."</a></li>
					<li class='mc-action-icon mc-icon-download-csv-conv' data-id='$thread_id'>
						<a href='#'>".RCView::img(array('src'=>'xls.gif'))." ".$lang['messaging_50']."</a></li>
					<li class='mc-action-icon mc-icon-archive-conv $action_icon_class' data-action='archive'>
						<a href='#'>".RCView::img(array('src'=>'archive_icon.png'))." ".$lang['messaging_51']."</a></li>
					<li class='mc-action-icon mc-icon-delete-conversation $action_icon_class' data-action='delete'>
						<a href='#'>".RCView::span(array('class'=>'fas fa-times'),'')." ".$lang['messaging_52']."</a></li>
				</ul>
			</div>
          </div>
          <p class='channel-members-count'>".$total_members_string."</p>
          <div class='channel-members-username' data-leader-conv='".$conv_leader."'>
            ".$members_html."
          </div>
          <div class='msgs-wrapper ".$small_screen_class."' data-thread_id='".$thread_id."' style='".(isset($_SESSION['msg_wrapper_height']) && $_SESSION['msg_wrapper_height'] > 0 ? "height:".($_SESSION['msg_wrapper_height']+6)."px;" : "")."'>
            ".$messages_html."
          </div>
          ".$input_container."
        </div>
        <div class='messaging-create-new-big-container'>
        	<a class='mr-3' title='".js_escape($lang['alerts_32'])."' href='javascript:;' onclick=\"$.get(app_path_webroot+'Messenger/info.php',{},function(data){ simpleDialog(data,'".js_escape($lang['messaging_09'])."','msgrInfo',800);fitDialog($('#msgrInfo')); });return false;\"><i class=\"fas fa-info-circle text-white fs15 opacity75\"></i></a>        
		    <p class='messaging-create-new-big mr-4' href='#'>{$lang['messaging_01']}</p>
        </div>
      </div>";

      return $html;
    }else{//user_messaging_enabled false
      return;
    }
  }

	// Ensure the file attachment belongs to a thread that the current user has access to
	public static function fileBelongsToUserThread($doc_id)
	{
		if (empty($doc_id) || !is_numeric($doc_id)) return false;
		$sql = "select 1 from redcap_messages m, redcap_edocs_metadata e, redcap_messages_recipients r
				where m.attachment_doc_id = $doc_id and m.attachment_doc_id = e.doc_id
				and m.thread_id = r.thread_id and r.recipient_user_id = " . UI_ID . " limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Give warning if user being deleted from project is part of conversations associated with this project
	public static function getUserConvTitlesForProject($username, $project_id)
	{
		$userInfo = User::getUserInfo($username);
		// Obtain list of threads that user is on
		$convList = array();
		$sql = "select t.thread_id, t.channel_name from redcap_messages_recipients r, redcap_messages_threads t
				where recipient_user_id = '".db_escape($userInfo['ui_id'])."' and t.thread_id = r.thread_id
				and t.type = 'CHANNEL' and t.project_id = $project_id order by t.thread_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$convList[$row['thread_id']] = strip_tags(label_decode($row['channel_name']));
		}
		return $convList;
	}

	// Generate the SQL for adding to REDCap upgrades to generate a system notification in Messenger
	public static function generateNewSystemNotificationSQL($title, $msg, $link='')
	{
		if ($title == '' && $msg == '') return '';
		$jsonArray = array(array('title'=>$title, 'description'=>$msg, 'link'=>$link,
					'action'=>'what-new'));
		$jsonEscaped = db_escape(json_encode_rc($jsonArray));
		return	"-- Generate system notification from REDCap Messenger\n" .
				"insert into redcap_messages (thread_id, sent_time, message_body) values (1, NOW(), '$jsonEscaped');\n" .
				"insert into redcap_messages_status (message_id, recipient_id, recipient_user_id)\n" .
				"select last_insert_id(), '1', ui_id from redcap_user_information where user_suspended_time is null;\n";
	}

	// Remove user from a linked project's conversation (and add a message in the conversation noting this)
	public static function removeUserFromLinkedProjectConversation($project_id, $user)
	{
		global $lang, $user_messaging_enabled;
		if (!$user_messaging_enabled) return;
		// Remove from any linked conversations in Messenger.
		$sql5 = "select t.thread_id, r.recipient_id FROM redcap_messages_threads t, redcap_messages_recipients r, redcap_user_information i
				where t.project_id = $project_id and r.thread_id = t.thread_id and r.recipient_user_id = i.ui_id
				and i.username = '".db_escape($user)."'";
		$q5 = db_query($sql5);
		if (db_num_rows($q5) > 0)
		{
			// Add post in Messenger about them leaving the conversation
			$msg = Messenger::createMsgBodyObj($user." ".$lang['messaging_17']." ".$lang['messaging_19'],USERID,'post');
			$kicker_ui_id = User::getUIIDByUsername($user);
			while ($row = db_fetch_assoc($q5))
			{
				$thread_id = $row['thread_id'];
				$recipient_id = $row['recipient_id'];
				// Also delete from redcap_messages_recipients table if any conversations linked to this project
				$sql6 = "delete from redcap_messages_recipients where recipient_id = $recipient_id";
				db_query($sql6);
				// Add message
				$sql6 = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body) values
						('".db_escape($thread_id)."', '".NOW."', '".db_escape(UI_ID)."', '".db_escape($msg)."')";
				db_query($sql6);
				//find all ui_ids of remaining members of the conversation to send notification to
				$sql6 = "select recipient_user_id as participant from redcap_messages_recipients
						where thread_id = $thread_id group by recipient_user_id";
				$q6 = db_query($sql6);
				while ($row2 = db_fetch_assoc($q6))
				{
				  $result[$row2['participant']] = $row2;
				}
				Messenger::sendNotificationToParticipants($result,$thread_id,$kicker_ui_id);
			}
		}
	}

	// Return count of total number of messages (if $sentInPastXDays=0, return all-time total)
	public static function countTotalMessages($sentInPastXDays=0)
	{
		$sqlb = "";
		if ($sentInPastXDays > 0) {
			$xDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$sentInPastXDays,date("Y")));
			$sqlb = "and sent_time > '$xDaysAgo'";
		}
		$sql = "select count(1) from redcap_messages where thread_id > 3 $sqlb";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Return count of total number of conversations
	public static function countTotalConversations()
	{
		$sql = "select count(1) from redcap_messages_threads where thread_id > 3";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Return count of total number of conversations that are linked to a project
	public static function countTotalLinkedConversations()
	{
		$sql = "select count(1) from redcap_messages_threads where thread_id > 3 and project_id is not null";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	// Output all the JavaScript variables needed for Messenger
	public static function outputJSvars($outputScriptTags=true)
	{
		global $user_messaging_enabled, $lang;
		if (!$user_messaging_enabled) return;
		// Only render variables on pageload if Messenger panel is already open
		if ($outputScriptTags && (!isset($_SESSION['mc_open']) || $_SESSION['mc_open'] == '0')) return;
		if ($outputScriptTags) print '<script type="text/javascript">';
		?>
		window.MessengerLangInit = 1;
		window.langMsg01 = '<?php print js_escape($lang['messaging_01']) ?>';
		window.langMsg02 = '<?php print js_escape($lang['messaging_80']) ?>';
		window.langMsg03 = '<?php print js_escape($lang['messaging_183']." \"delete\" ".$lang['messaging_184']) ?>';
		window.langMsg04 = '<?php print js_escape($lang['global_19']) ?>';
		window.langMsg05 = '<?php print js_escape($lang['messaging_82']) ?>';
		window.langMsg06 = '<?php print js_escape($lang['messaging_83']) ?>';
		window.langMsg07 = '<?php print js_escape($lang['messaging_84']) ?>';
		window.langMsg08 = '<?php print js_escape($lang['messaging_85']) ?>';
		window.langMsg09 = '<?php print js_escape($lang['control_center_4540']) ?>';
		window.langMsg10 = '<?php print js_escape($lang['messaging_44']) ?>';
		window.langMsg11 = '<?php print js_escape($lang['messaging_45']) ?>';
		window.langMsg12 = '<?php print js_escape($lang['messaging_46']) ?>';
		window.langMsg13 = '<?php print js_escape($lang['messaging_47']) ?>';
		window.langMsg14 = '<?php print js_escape($lang['messaging_48']) ?>';
		window.langMsg15 = '<?php print js_escape($lang['messaging_49']) ?>';
		window.langMsg16 = '<?php print js_escape($lang['messaging_50']) ?>';
		window.langMsg17 = '<?php print js_escape($lang['messaging_51']) ?>';
		window.langMsg18 = '<?php print js_escape($lang['messaging_52']) ?>';
		window.langMsg19 = '<?php print js_escape($lang['messaging_86']) ?>';
		window.langMsg20 = '<?php print js_escape($lang['messaging_87']) ?>';
		window.langMsg21 = '<?php print js_escape($lang['messaging_30']) ?>';
		window.langMsg22 = '<?php print js_escape($lang['messaging_89']) ?>';
		window.langMsg23 = '<?php print js_escape($lang['global_48']) ?>';
		window.langMsg24 = '<?php print js_escape($lang['design_243']) ?>';
		window.langMsg25 = '<?php print js_escape($lang['messaging_90']) ?>';
		window.langMsg26 = '<?php print js_escape($lang['messaging_91']) ?>';
		window.langMsg27 = '<?php print js_escape($lang['messaging_92']) ?>';
		window.langMsg28 = '<?php print js_escape($lang['global_53']) ?>';
		window.langMsg29 = '<?php print js_escape($lang['design_654']) ?>';
		window.langMsg30 = '<?php print js_escape($lang['messaging_93']) ?>';
		window.langMsg31 = '<?php print js_escape($lang['messaging_94']) ?>';
		window.langMsg32 = '<?php print js_escape($lang['messaging_95']) ?>';
		window.langMsg33 = '<?php print js_escape($lang['messaging_96']) ?>';
		window.langMsg34 = '<?php print js_escape($lang['messaging_97']) ?>';
		window.langMsg35 = '<?php print js_escape($lang['messaging_34']) ?>';
		window.langMsg36 = '<?php print js_escape($lang['messaging_98']) ?>';
		window.langMsg37 = '<?php print js_escape($lang['messaging_183']." \"delete\" ".$lang['messaging_185']) ?>';
		window.langMsg38 = '<?php print js_escape($lang['messaging_100']) ?>';
		window.langMsg39 = '<?php print js_escape($lang['global_27']) ?>';
		window.langMsg40 = '<?php print js_escape($lang['design_170']) ?>';
		window.langMsg41 = '<?php print js_escape($lang['global_47']) ?>';
		window.langMsg42 = '<?php print js_escape($lang['messaging_101']) ?>';
		window.langMsg43 = '<?php print js_escape($lang['messaging_102']) ?>';
		window.langMsg44 = '<?php print js_escape($lang['messaging_103']) ?>';
		window.langMsg45 = '<?php print js_escape($lang['messaging_104']) ?>';
		window.langMsg46 = '<?php print js_escape($lang['messaging_105']) ?>';
		window.langMsg47 = '<?php print js_escape($lang['messaging_106']) ?>';
		window.langMsg48 = '<?php print js_escape($lang['messaging_107']) ?>';
		window.langMsg49 = '<?php print js_escape($lang['messaging_108']) ?>';
		window.langMsg50 = '<?php print js_escape($lang['messaging_109']) ?>';
		window.langMsg51 = '<?php print js_escape($lang['global_17']) ?>';
		window.langMsg52 = '<?php print js_escape($lang['reporting_21']) ?>';
		window.langMsg53 = '<?php print js_escape($lang['messaging_110']) ?>';
		window.langMsg54 = '<?php print js_escape($lang['messaging_111']) ?>';
		window.langMsg55 = '<?php print js_escape($lang['messaging_112']) ?>';
		window.langMsg56 = '<?php print js_escape($lang['global_75']) ?>';
		window.langMsg57 = '<?php print js_escape($lang['messaging_113']) ?>';
		window.langMsg58 = '<?php print js_escape($lang['messaging_114']) ?>';
		window.langMsg59 = '<?php print js_escape($lang['messaging_115']) ?>';
		window.langMsg60 = '<?php print js_escape($lang['messaging_33']) ?>';
		window.langMsg61 = '<?php print js_escape($lang['messaging_35']) ?>';
		window.langMsg62 = '<?php print js_escape($lang['period']) ?>';
		window.langMsg63 = '<?php print js_escape($lang['messaging_116']) ?>';
		window.langMsg64 = '<?php print js_escape($lang['messaging_39']) ?>';
		window.langMsg65 = '<?php print js_escape($lang['messaging_06']) ?>';
		window.langMsg66 = '<?php print js_escape($lang['messaging_07']) ?>';
		window.langMsg67 = '<?php print js_escape($lang['messaging_117']) ?>';
		window.langMsg70 = '<?php print js_escape($lang['messaging_120']) ?>';
		window.langMsg71 = '<?php print js_escape($lang['messaging_121']) ?>';
		window.langMsg72 = '<?php print js_escape($lang['messaging_122']) ?>';
		window.langMsg73 = '<?php print js_escape($lang['messaging_123']) ?>';
		window.langMsg74 = '<?php print js_escape($lang['messaging_124']) ?>';
		window.langMsg75 = '<?php print js_escape($lang['messaging_125']) ?>';
		window.langMsg76 = '<?php print js_escape($lang['messaging_32']) ?>';
		window.langMsg77 = '<?php print js_escape($lang['messaging_126']) ?>';
		window.langMsg78 = '<?php print js_escape($lang['messaging_127']) ?>';
		window.langMsg79 = '<?php print js_escape($lang['messaging_128']) ?>';
		window.langMsg80 = '<?php print js_escape($lang['messaging_129']) ?>';
		window.langMsg81 = '<?php print js_escape($lang['data_entry_369']) ?>';
		window.langMsg82 = '<?php print js_escape($lang['messaging_130']) ?>';
		window.langMsg83 = '<?php print js_escape($lang['messaging_131']) ?>';
		window.langMsg84 = '<?php print js_escape($lang['messaging_132']) ?>';
		window.langMsg85 = '<?php print js_escape($lang['messaging_133']) ?>';
		window.langMsg86 = '<?php print js_escape($lang['messaging_134']) ?>';
		window.langMsg87 = '<?php print js_escape($lang['messaging_135']) ?>';
		window.langMsg88 = '<?php print js_escape($lang['messaging_136']) ?>';
		window.langMsg89 = '<?php print js_escape($lang['messaging_137']) ?>';
		window.langMsg90 = '<?php print js_escape($lang['messaging_138']) ?>';
		window.langMsg91 = '<?php print js_escape($lang['design_171']) ?>';
		window.langMsg92 = '<?php print js_escape($lang['design_248']) ?>';
		window.langMsg93 = '<?php print js_escape($lang['scheduling_57']) ?>';
		window.langMsg94 = '<?php print js_escape($lang['messaging_139']) ?>';
		window.langMsg95 = '<?php print js_escape($lang['messaging_140']) ?>';
		window.langMsg96 = '<?php print js_escape($lang['messaging_141']) ?>';
		window.langMsg97 = '<?php print js_escape($lang['messaging_01']) ?>';
		window.langMsg98 = '<?php print js_escape($lang['messaging_142']) ?>';
		window.langMsg99 = '<?php print js_escape($lang['messaging_143']) ?>';
		window.langMsg100 = '<?php print js_escape($lang['messaging_144']) ?>';
		window.langMsg101 = '<?php print js_escape($lang['calendar_popup_01']) ?>';
		window.langMsg102 = '<?php print js_escape($lang['messaging_145']) ?>';
		window.langMsg103 = '<?php print js_escape($lang['messaging_146']) ?>';
		window.langMsg104 = '<?php print js_escape($lang['control_center_4497']) ?>';
		window.langMsg105 = '<?php print js_escape($lang['messaging_147']) ?>';
		window.langMsg106 = '<?php print js_escape($lang['extres_28']) ?>';
		window.langMsg107 = '<?php print js_escape($lang['global_06']) ?>';
		window.langMsg108 = '<?php print js_escape($lang['messaging_148']) ?>';
		window.langMsg109 = '<?php print js_escape($lang['messaging_149']) ?>';
		window.langMsg110 = '<?php print js_escape($lang['messaging_150']) ?>';
		window.langMsg111 = '<?php print js_escape($lang['messaging_151']) ?>';
		window.langMsg112 = '<?php print js_escape($lang['messaging_152']) ?>';
		window.langMsg113 = '<?php print js_escape($lang['messaging_153']) ?>';
		window.langMsg114 = '<?php print js_escape($lang['messaging_154']) ?>';
		window.langMsg115 = '<?php print js_escape($lang['messaging_155']) ?>';
		window.langMsg116 = '<?php print js_escape($lang['messaging_156']) ?>';
		window.langMsg117 = '<?php print js_escape($lang['messaging_157']) ?>';
		window.langMsg118 = '<?php print js_escape($lang['messaging_158']) ?>';
		window.langMsg119 = '<?php print js_escape($lang['messaging_159']) ?>';
		window.langMsg120 = '<?php print js_escape($lang['messaging_160']) ?>';
		window.langMsg121 = '<?php print js_escape($lang['messaging_161']) ?>';
		window.langMsg122 = '<?php print js_escape($lang['messaging_162']) ?>';
		window.langMsg123 = '<?php print js_escape($lang['messaging_163']) ?>';
		window.langMsg124 = '<?php print js_escape($lang['messaging_164']) ?>';
		window.langMsg125 = '<?php print js_escape($lang['messaging_165']) ?>';
		window.langMsg126 = '<?php print js_escape($lang['messaging_166']) ?>';
		window.langMsg127 = '<?php print js_escape($lang['messaging_167']) ?>';
		window.langMsg128 = '<?php print js_escape($lang['messaging_168']) ?>';
		window.langMsg129 = '<?php print js_escape($lang['messaging_169']) ?>';
		window.langMsg130 = '<?php print js_escape($lang['messaging_170']) ?>';
		window.langMsg131 = '<?php print js_escape($lang['messaging_171']) ?>';
		window.langMsg132 = '<?php print js_escape($lang['messaging_172']) ?>';
		window.langMsg133 = '<?php print js_escape($lang['messaging_173']) ?>';
		window.langMsg134 = '<?php print js_escape($lang['messaging_174']) ?>';
		window.langMsg135 = '<?php print js_escape($lang['messaging_175']) ?>';
		window.langMsg136 = '<?php print js_escape($lang['messaging_176']) ?>';
		window.langMsg138 = '<?php print js_escape($lang['messaging_178']) ?>';
		window.langMsg139 = '<?php print js_escape($lang['messaging_179']) ?>';
		window.langMsg140 = '<?php print js_escape($lang['data_import_tool_20']) ?>';
		window.langMsg141 = '<?php print js_escape($lang['data_entry_63']) ?>';
		window.langMsg142 = '<?php print js_escape($lang['design_530']) ?>';
		window.langMsg143 = '<?php print js_escape($lang['messaging_180']) ?>';
		window.langMsg144 = '<?php print js_escape($lang['messaging_181']) ?>';
		window.langMsg145 = '<?php print js_escape($lang['messaging_182']) ?>';
		window.langMsg146 = '<?php print js_escape($lang['design_121']) ?>';
		window.langMsg147 = '<?php print js_escape($lang['report_builder_87']) ?>';
		<?php
		if ($outputScriptTags) print '</script>';
	}
}
