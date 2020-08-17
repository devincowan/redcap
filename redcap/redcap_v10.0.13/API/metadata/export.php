<?php
global $format, $returnFormat, $post;

// Get project attributes
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

# get all fields for a set of forms, if provided
$fieldArray = array();
$fields = $post['fields'];
if (!empty($post['forms'])) {
	$formList = prep_implode($post['forms']);
	$query = "SELECT field_name FROM redcap_metadata
		WHERE project_id = ".$post['projectid']." AND form_name IN ($formList)
		ORDER BY field_order";
	$fieldResults = db_query($query);
	while ($row = db_fetch_assoc($fieldResults))
	{
		$key = array_search($row['field_name'], $fields);
		if ($key != NULL && $key !== false)
			unset($fields[$key]);

		$fieldArray[] = $row['field_name'];
	}
}
$fieldArray = array_merge($fields, $fieldArray);
// Return metadata in desired format
$content = MetaData::getDataDictionary($format, false, $fieldArray, array(), $post['mobile_app']);

/************************** log the event **************************/
$log_event_id = Logging::logEvent("", "redcap_metadata", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download data dictionary (API$playground)");
// If this is the mobile app initializing a project, then log that in the mobile app log
if ($post['mobile_app'] && $post['project_init'] > 0) 
{
	$userInfo = User::getUserInfo(USERID);
	$mobile_app_event = ($post['project_init'] == '1') ? 'INIT_PROJECT' : 'REINIT_PROJECT';

        if ($post['uuid'] !== "")
        {
                $presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($post['uuid'])."') AND (project_id = ".PROJECT_ID.") LIMIT 1;";
                $preq1 = db_query($presql1);
                $row = db_fetch_assoc($preq1);
                if (!$row)
                {
                        $presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($post['uuid'])."', ".PROJECT_ID.");";
                        db_query($presql2);
                        $preq1 = db_query($presql1);
                        $row = db_fetch_assoc($preq1);
                }
                if ($row && ($row['revoked'] == "0"))
                {
	                $sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, device_id, ui_id) values
			                (".PROJECT_ID.", $log_event_id, '$mobile_app_event', ".$row['device_id'].", '".db_escape($userInfo['ui_id'])."')";
	                db_query($sql);
                        # Send the response to the requestor
                        RestUtility::sendResponse(200, $content, $format);
                }
                else
                {
                        RestUtility::sendResponse(403, "Your device does not have appropriate permissions to download the metadata.");
                }
        }
        else
        {
	        $sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, ui_id) values
			        (".PROJECT_ID.", $log_event_id, '$mobile_app_event', '".db_escape($userInfo['ui_id'])."')";
			db_query($sql);
			# Send the response to the requestor
			RestUtility::sendResponse(200, $content, $format);
		}
}
else
{
# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);
}
