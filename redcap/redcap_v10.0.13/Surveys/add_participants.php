<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = Survey::getSurveyId();
}

// Ensure the survey_id belongs to this project and that Post method was used
if (!Survey::checkSurveyProject($_GET['survey_id']))
{
	exit("0");
}

$response = "0"; //Default


if (isset($_POST['participants']) && !empty($_POST['participants']))
{
	// Process the emails/identifiers
	$invalid_emails = array();
	$invalid_phones = array();
	$participants 	= array();
	$disableIdentErrors = array();

	// Get delivery preference
	$delivery_preference = ''; // Default
	$all_delivery_methods = Survey::getDeliveryMethods();
	if ($twilio_enabled && $Proj->twilio_enabled_surveys && isset($_POST['delivery_preference']) && isset($all_delivery_methods[$_POST['delivery_preference']])) {
		$delivery_preference = $_POST['delivery_preference'];
	}

	// Loop through all participants submitted
	$i = 1;
	foreach (explode("\n", trim(strip_tags(label_decode($_POST['participants'])))) as $i=>$line)
	{
		$line = trim($line);
		if ($line != '')
		{
			// If line has comma, separate as email, [phone,] identifier
			if (strpos($line, ",") !== false)
			{
				if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
					// Twilio: Expect a number as the second item or as first item
					list ($this_email, $this_phone, $this_ident) = explode(",", $line, 3);
					$this_email = trim($this_email);
					$this_phone = $this_phone_orig = trim($this_phone);
					$this_ident = trim($this_ident);
					// If email is not blank AND is not email-formatted BUT it IS formatted like a phone number,
					// then assume that the first item is the phone number.
					if ($this_email != '' && !isEmail($this_email) && strlen(preg_replace("/[^0-9]/", "", $this_email)) >= 7) {
						$this_ident = $this_phone;
						$this_phone = $this_phone_orig = $this_email;
						$this_email = '';
					}
					// Remove all non-numerals from phone numbers
					$this_phone = preg_replace("/[^0-9]/", "", $this_phone);
				} else {
					list ($this_email, $this_ident) = explode(",", $line, 2);
					$this_email = trim($this_email);
					$this_ident = trim($this_ident);
					$this_phone_orig = $this_phone = "";
				}
				// If trying to add an identifier when identifiers are disabled, give error message
				if (!$enable_participant_identifiers && $this_ident != "") {
					$disableIdentErrors[] = $line;
				}
			}
			// Only one item on this line (assume it is just an email address)
			else {
				$this_email = trim($line);
				$this_ident = $this_phone = "";
				// If email is not blank AND is not email-formatted BUT it IS formatted like a phone number,
				// then assume that the first item is the phone number.
				if ($twilio_enabled && $Proj->twilio_enabled_surveys && !isEmail($this_email) && strlen(preg_replace("/[^0-9]/", "", $this_email)) >= 7) {
					$this_phone = $this_phone_orig = $this_email;
					$this_email = '';
				}
			}
			// Check for formatting errors in phone or email
			if ($this_email != '' && !isEmail($this_email)) $invalid_emails[] = $this_email;
			if ($this_phone != '' && strlen($this_phone) < 7) $invalid_phones[] = $this_phone_orig;
			// Add to array
			if ((!($twilio_enabled && $Proj->twilio_enabled_surveys) && $this_email != '')
					|| ($twilio_enabled && $Proj->twilio_enabled_surveys && !($this_email == '' && $this_phone == ''))) {
				$participants[$i]['email'] 		= $this_email;
				$participants[$i]['identifier'] = $this_ident;
				$participants[$i]['phone'] = $this_phone;
			}
		}
	}

	// Give response back if trying to add an identifier when identifiers are disabled
	if (count($disableIdentErrors) > 0)
	{
		print "{$lang['survey_269']}<br><br><span style='color:#C00000;'>{$lang['survey_270']}</span> - "
			 . implode("<br><span style='color:#C00000;'>{$lang['survey_270']}</span> - ", $disableIdentErrors);
		exit;
	}

	// Give response back if some emails are not formatted correctly
	if ((count($invalid_emails) + count($invalid_phones)) > 0)
	{
		$error_msg = "";
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			if (count($invalid_emails) > 0) {
				$error_msg .= "{$lang['survey_157']}<br><br><span style='color:#C00000;'>{$lang['survey_158']}</span> - ";
				$error_msg .= implode("<br><span style='color:#C00000;'>{$lang['survey_158']}</span> - ", $invalid_emails);
			}
			if (count($invalid_phones) > 0) {
				if (count($invalid_emails) > 0) {
					$error_msg .= "<div class='spacer'></div>";
				}
				$error_msg .= "{$lang['survey_790']}<br><br><span style='color:#C00000;'>{$lang['survey_158']}</span> - ";
				$error_msg .= implode("<br><span style='color:#C00000;'>{$lang['survey_158']}</span> - ", $invalid_phones);
			}
		} else {
			$error_msg = "{$lang['survey_157']}<br><br><span style='color:#C00000;'>{$lang['survey_158']}</span> - "
					   . implode("<br><span style='color:#C00000;'>{$lang['survey_158']}</span> - ", $invalid_emails);
		}
		exit($error_msg);
	}

	// Loop through all submitted participants and add to tables
	foreach ($participants as $attr)
	{
		// Add to participant table and retrieve its hash
		Survey::setHash($_GET['survey_id'], $attr['email'], $_GET['event_id'], $attr['identifier'], false, $attr['phone'], $delivery_preference);
	}

	// Logging
	Logging::logEvent("","redcap_surveys_participants","MANAGE",$_GET['survey_id'],"survey_id = {$_GET['survey_id']}\nevent_id = {$_GET['event_id']}","Add survey participants");

	$response = "1";

}

exit($response);