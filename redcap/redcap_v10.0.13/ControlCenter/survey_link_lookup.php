<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
//If user is not a super user, go back to Home page
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

// AJAX request
if ($isAjax) {
    $lookupResult = array();
    if (isset($_GET['lookup'])) {
        $lookupResult = lookup($_GET['lookup']);
    } else {
        $lookupResult['lookup_success'] = false;
        $lookupResult['lookup_result'] = 'No lookup value provided';
    }
    echo json_encode($lookupResult);
    exit;
}

// Display page
include 'header.php';
include APP_PATH_VIEWS . 'HomeTabs.php';
renderPageTitle($lang['control_center_4702']);
?>
<style type="text/css">
    #pagecontent { margin-top: 70px; }
    #control_center_window { max-width:800px; }
</style>
<?php
loadJS('SurveyLinkLookup.js');
printPage(isset($_GET['lookup']) ? rawurldecode(urldecode($_GET['lookup'])) : '');
include 'footer.php';
        
/**
 * Print the module plugin page html content 
 * @global array $lang
 * @param string $link Optional value to be written to the plugin page
 * input text box and searched for on document ready.
 */
function printPage($link='')
{
    global $lang;

    $instructionText = $lang['control_center_4703'];
    $inputLabelText = $lang['control_center_4704'];

    print RCView::div(
            array('id'=>'lookup_form'),
            RCView::div(
                    array(),
                    $instructionText
            ).
            RCView::form(
                array('name'=>'form', 'onsubmit'=>'return false;'),
                    RCView::div(
                            array('class'=>'form-group', 'style'=>'margin:20px 0'),
                            RCView::label(array('for'=>'hash', 'style'=>'font-weight:bold;margin-right:6px;font-size:13px;'),$inputLabelText).
                            RCView::input(
                                    array('type'=>'text','class'=>'form-control', 'style'=>'font-size:13px;display:inline;max-width:400px;width:50%;margin:0 5px',
                                        'id'=>'lookup_val','value'=>htmlspecialchars($link, ENT_QUOTES))
                            ).
                            RCView::button(
                                    array('id'=>'btnFind', 'class'=>'btn btn-primaryrc btn-sm', 'style'=>'font-size: 16px;', 'type'=>'button'),
                                    '<span class="fa f-search"></span>&nbsp;'.$lang['control_center_439'].'&nbsp;'
                            )
                    )
            )
    );

    print RCView::div(
            array(
                    'id'=>'results', 'class'=>'container well', 'style'=>'width:730px; font-size:120%; display:none; padding: 19px 3px 19px 10px;'
            ),
            RCView::div(
                    array('id'=>'results_spin', 'class'=>'row', 'style'=>'display:block;text-align:center;'),
                    RCView::img(array('src'=>'progress_circle.gif'))
            ).
            RCView::div(
                    array('id'=>'results_error', 'class'=>'row', 'style'=>'display:block;text-align:center;'),
                    RCView::span(array('id'=>'result_error_msg', 'class'=>'text-danger'), 'error')
            ).
            RCView::div(
                    array('id'=>'results_detail', 'style'=>'display:block;'),
                    RCView::div(
                            array('class'=>'row'),
                            RCView::div(
                                    array('class'=>'col-sm-2 col-md-2 col-lg-2', 'style'=>'color:#888'),
                                    $lang['global_65'] //Project
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-6 col-md-6 col-lg-6'),
                                    '<span id="result_app_title"></span> <span class="text-muted">(pid=<span id="result_project_id"]."></span>)</span>'
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-3 col-md-3 col-lg-3'),
                                    RCView::a(
                                            array('class'=>'btn btn-xs btn-defaultrc', 'target'=>'_blank', 'style'=>'',
                                                'id'=>'result_link_setup_page', 'href'=>'#'),
                                            '<span class="fa fa-link"></span>&nbsp;'.$lang['app_17'].'&nbsp;&nbsp;<span class="fa fa-external-link-alt"></span>' //Project Setup
                                    )
                            )
                    ).
                    RCView::div(
                            array('class'=>'row', 'style'=>'margin-top:20px;margin-bottom:20px;'),
                            RCView::div(
                                    array('class'=>'col-sm-2 col-md-2 col-lg-2', 'style'=>'color:#888'),
                                    $lang['survey_437'] //Survey'
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-6 col-md-6 col-lg-6'),
                                    '<span id="result_survey_title"></span>'
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-3 col-md-3 col-lg-3'),
                                    RCView::a(
                                            array('class'=>'btn btn-xs btn-defaultrc', 'target'=>'_blank', 'style'=>'',
                                                'id'=>'result_link_designer_page', 'href'=>'#'),
                                            '<span class="fa fa-link"></span>&nbsp;'.$lang['design_25'].'&nbsp;&nbsp;<span class="fa fa-external-link-alt"></span>' //Online Designer
                                    )
                            )
                    ).
                    RCView::div(
                            array('class'=>'row'),
                            RCView::div(
                                    array('class'=>'col-sm-2 col-md-2 col-lg-2', 'style'=>'color:#888'),
                                    $lang['global_49'].'<br>'.$lang['global_141'].'<br>'.$lang['data_entry_246'] //Record<br>Event<br>Instance
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-6 col-md-6 col-lg-6'),
                                    '<span id="result_record"></span><br><span id="result_event_name"></span><br><span id="result_instance"></span>'
                            ).
                            RCView::div(
                                    array('class'=>'col-sm-3 col-md-3 col-lg-3'),
                                    RCView::a(
                                            array('class'=>'btn btn-xs btn-defaultrc', 'target'=>'_blank', 'style'=>'',
                                                'id'=>'result_link_data_entry_page', 'href'=>'#'),
                                            '<span class="fa fa-link"></span>&nbsp;'.$lang['global_35'].'&nbsp;&nbsp;<span class="fa fa-external-link-alt"></span>' //Data Collection Instrument
                                    ).
                                    RCView::a(
                                            array('class'=>'btn btn-xs btn-defaultrc', 'target'=>'_blank', 'style'=>'display:none;',
                                                'id'=>'result_link_public_survey_page', 'href'=>'#'),
                                            '<span class="fa fa-link"></span>&nbsp;'.$lang['app_24'].'&nbsp;&nbsp;<span class="fa fa-external-link-alt"></span>' //Manage Survey Participants (Public Survey Link)
                                    )
                            )
                    )
            )
    );
    return;
}

/**
 * Extract the section of the input string that looks like a survey hash
 * @param string $lookup_val The string from which the survey hash value 
 * will be extracted.
 * @return array Array with two elements: 1) lookup_success (bool), 
 * indicating whether a valid hash was found in $lookup_val; 
 * 2) lookup_result (mixed), array of survey details or error message 
 */
function lookup($lookup_val)
{
    $resultArray = array(
            'lookup_success' => false,
            'lookup_result' => ''
    );

    if (!isset($lookup_val) || $lookup_val=='') {
            $resultArray['lookup_result'] = 'No link or survey hash provided';
    } else {
            $hash = extractHash($lookup_val);
            if (!isset($hash) || $hash=='') {
                    $resultArray['lookup_result'] = "Could not extract survey hash value (?s=hash) from '".RCView::escape($lookup_val)."'";
            } else {
                    try {
                            $details = readSurveyDetailsFromHash($hash);
                            if (count($details) > 0) {
                                    $resultArray['lookup_success'] = true;
                                    $resultArray['lookup_result'] = $details;
                            } else {
                                $resultArray['lookup_result'] = "Survey hash '".RCView::escape($hash)."' not found";
                            }
                    } catch (Exception $ex) {
                            $resultArray['lookup_result'] = $ex->getMessage();
                    }
            }
    }
    return $resultArray;
}

/**
 * Extract the section of the input string that looks like a survey hash
 * @param string $lookup_val The string from which the survey hash value 
 * will be extracted.
 * @return string Hash value (generally 10 characters), or empty string 
 * if no hash value found.
 */
function extractHash($lookup_val)
{
    $hash = '';
    $matches = array();
    if (strpos($lookup_val, 's=')!==false) {
            if (preg_match('/(?<=s=)[^\&]*/', $lookup_val, $matches)) {
                    $hashPart = $matches[0];
            }
    } else {
            $hashPart = $lookup_val;
    }
    if (preg_match('/^\w{6,10}$/', $hashPart, $matches)) {
            $hash = $matches[0];
    }
    return $hash;
}

/**
 * Look up details of survey corresponding to the hash value provided
 * @param string $hash A (generally) 10-character value identifying an 
 * individual participant survey.
 * @return array
 */
function readSurveyDetailsFromHash($hash)
{
    global $lang;

    $details = array();

    if (isset($hash) && $hash!=='') {

            $sql = "SELECT s.survey_id,s.project_id,s.form_name,s.title as survey_title".
                    ",pr.app_title,pr.repeatforms".
                    ",p.participant_id,p.event_id,p.hash,IF(p.participant_email IS NULL,1,0) as is_public_survey_link".
                    ",em.descrip".
                    ",ea.arm_id,ea.arm_num,ea.arm_name".
                    ",proj_ea.num_project_arms".
                    ",r.response_id,r.record,r.instance,r.start_time,r.first_submit_time,r.completion_time,r.return_code,r.results_code ".
                "FROM redcap_surveys s ".
                "INNER JOIN redcap_projects pr ON s.project_id = pr.project_id ".
                "INNER JOIN redcap_surveys_participants p ON s.survey_id = p.survey_id ".
                "INNER JOIN redcap_events_metadata em ON em.event_id = p.event_id ".
                "INNER JOIN redcap_events_arms ea ON ea.arm_id = em.arm_id ".
                "INNER JOIN (SELECT project_id, COUNT(arm_id) as num_project_arms FROM redcap_events_arms GROUP BY project_id) proj_ea ON proj_ea.project_id = pr.project_id ".
                "LEFT OUTER JOIN redcap_surveys_response r ON p.participant_id = r.participant_id ".
                "WHERE hash = '".db_real_escape_string($hash)."' LIMIT 1";

            $result = db_query($sql);

            $details = db_fetch_assoc($result);
            db_free_result($result);

            // get event name (with arm ref, if multiple)
            if (isset($details['project_id']) && intval($details['project_id']) > 0) {
                    $event_name = '';

                    if ($details['is_public_survey_link']) {
                        $details['record'] = $lang['survey_279']; // Public Survey Link
                        if (intval($details['num_project_arms']) > 1) { $event_name = $details['descrip']." (".$details['arm_name'].")"; }
                        $details['instance'] = '';

                    } else if (!$details['repeatforms']) {
                            $event_name = $lang['control_center_149']; // N/A (not a longitudinal project)
                    } else {
                            $event_name = (intval($details['num_project_arms']) > 1)
                                    ? $event_name = $details['descrip']." (".$details['arm_name'].")"
                                    : $event_name = $details['descrip'];
                    }
                    $details['event_name'] = $event_name;
            }
    }

    return $details;
}
