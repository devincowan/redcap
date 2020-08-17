<?php

class SurveyController extends Controller
{
	// Change a participant's Link Expiration time (time limit)
	public function changeLinkExpiration()
	{
		if ($_POST['action'] == 'save') {
			Survey::changeLinkExpiration();
		} else {
			Survey::changeLinkExpirationRenderDialog();
		}
	}
	
	// Render the HTML table for a record's scheduled survey invitations to be sent in the next X days
	public function renderUpcomingScheduledInvites()
	{
		global $lang;
		$SurveyScheduler = new SurveyScheduler();
		print RCView::div(array('style'=>'margin-bottom:10px;'), $lang['survey_1134'] . " ". RCView::b((int)$_POST['days'] . " " . $lang['scheduling_25']) . $lang['period']);
		print $SurveyScheduler->renderSurveyInvitationLog(rawurldecode(urldecode($_POST['record'])), false, $_POST['days']);
	}

    // Enable/disable Google reCaptcha
    public function enableCaptcha()
    {
        $enable = (isset($_POST['enable']) && (int)$_POST['enable'] === 1) ? '1' : '0';
        print Survey::enableCaptcha($enable) ? '1' : '0';
    }
}