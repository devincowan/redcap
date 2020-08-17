<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";


if (Survey::checkSurveyProject($_GET['survey_id']) && isset($_GET['hash']) && isset($_GET['custom_url']))
{
	if(isset($_GET['arm_id'])){
		$arm_id = $_GET['arm_id'];
		$sql = "select * from redcap_events_arms where project_id = $project_id and arm_id = '".db_escape($arm_id)."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$arm_num = $row['arm_num'];
		}
	}else{
		$arm_num = 1;
	}
	if ($GLOBALS['enable_url_shortener_redcap']) {
		// Sanitize the custom URL
		$_GET['custom_url'] = str_replace(" ", "", trim($_GET['custom_url']));
		$custom_url = preg_replace("/[^a-zA-Z0-9-_.]/", "", $_GET['custom_url']);
		if ($custom_url != $_GET['custom_url']) {
			exit($lang['global_01'].$lang['colon']." ".$lang['survey_1272']." ".$lang['locking_25']);
		}
		// Get custom URL
		$shorturl_status = getREDCapShortUrl(APP_PATH_SURVEY_FULL . '?s=' . $_GET['hash'], $custom_url);
		if (isset($shorturl_status['error'])) exit($lang['global_01'].$lang['colon']." ".$shorturl_status['error']);
		if (!isset($shorturl_status['url_short'])) exit("0");
		$shorturl = $shorturl_status['url_short'];
	} else {
		$shorturl = Survey::getCustomShortUrl(APP_PATH_SURVEY_FULL . "index.php?s=" . $_GET['hash'], $_GET['custom_url']);
		if ($shorturl == '') exit('0');
	}
	if (!isURL($shorturl)) exit("".RCView::escape($shorturl));
	// save to db logic
	if($custom_public_survey_links != '' && is_array(json_decode($custom_public_survey_links, true))) {
		// convert JSON encoded string into a PHP variable
		$result = json_decode($custom_public_survey_links);
		foreach ($result as &$value) {
		  if($arm_num == $value->{'arm_number'}){
			$value->{'custom_url'} = $shorturl;
			$newCustomurlString = "]";
		  }else{
			$newCustomurlString = ',{"arm_number":"'.$arm_num.'", "custom_url":"'.$shorturl.'"}]';
		  }
		}
		$jsonString = json_encode($result);
		$jsonString = str_replace(']', $newCustomurlString, $jsonString);
	} else {
		$jsonString = '[{"arm_number":"'.$arm_num.'", "custom_url":"'.$shorturl.'"}]';
	}
	$sql = "update redcap_projects set custom_public_survey_links='".db_escape($jsonString)."' where project_id = $project_id";
	$q = db_query($sql);
    //send data to front-end
    echo 	RCView::div(array('style'=>'font-size:15px;'), 
				$lang['control_center_4568'] . 
				RCView::a(array('href'=>$shorturl, 'style'=>'font-weight: bold; letter-spacing: 1px;display:block;font-size:16px;line-height: 24px;text-decoration:underline;', 'target'=>'_blank'), $shorturl) .
				RCView::div(array('style'=>'font-size:14px;margin-top:15px;'), 
					$lang['control_center_4569']
				)
			);
  
}


elseif (isset($_GET['action'])) 
{
  if ($_GET['action'] == 'retrieve-list') 
  {
    // arm number
    $arm_id = $_GET['arm_id'];
    $sql = "select * from redcap_events_arms where project_id = $project_id and arm_id = '".db_escape($arm_id)."'";
    $q = db_query($sql);
    while ($row = db_fetch_assoc($q)) {
      $arm_num = $row['arm_num'];
    }
    // retreive custom_public_survey_links
    $result = json_decode($custom_public_survey_links);
    $customurl_data = array();
    $customurl_data['arm_number'] = null;
    $customurl_data['custom_url'] = null;
    foreach ($result as &$value) {
      if($arm_num == $value->{'arm_number'}){
        // print $value->{'custom_url'};
        $customurl_data['arm_number'] = $value->{'arm_number'};
        $customurl_data['custom_url'] = $value->{'custom_url'};
      }
    }
    // echo $customurl_data;
    echo json_encode($customurl_data);
  }
  // Delete custom URL
  elseif($_GET['action'] == 'delete-customurl')
  {
    $arm_number = $_GET['arm_number'];
    $result = json_decode($custom_public_survey_links);
    foreach ($result as &$value) {
      if($arm_number == $value->{'arm_number'}){
        // print $value->{'arm_number'};
        $value->{'arm_number'} = $value->{'arm_number'}.'-deleted';
      }
    }
    $jsonString = json_encode($result);
    // print $jsonString;
    $sql = "update redcap_projects set custom_public_survey_links='".db_escape($jsonString)."' where project_id = $project_id";
    $q = db_query($sql);
    echo '1';
  }

} else {
	echo '0';
}
