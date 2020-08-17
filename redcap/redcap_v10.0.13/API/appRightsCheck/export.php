<?php

global $format, $returnFormat, $post;

// Get project attributes
$Proj = new Project();

// Already passed token check
if (isset($post['uuid']))
{
        $presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($post['uuid'])."') AND (project_id = ".PROJECT_ID.") LIMIT 1;";
        $preq1 = db_query($presql1);
        if ($row = db_fetch_assoc($preq1))
        {
                if ($row['revoked'] != 0)
                {
                        RestUtility::sendResponse(403, 'This device has been blocked.', $format);
                }
                else
                {
                        // Return nothing
                        RestUtility::sendResponse(200, '', $format);
                }
        }
        else
        {
                // no device on record
                $presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($post['uuid'])."', ".PROJECT_ID.");";
                db_query($presql2);
 
                // Return nothing
                RestUtility::sendResponse(200, '', $format);
        }
}
else
{
        // Return nothing
        RestUtility::sendResponse(200, '', $format);
}
