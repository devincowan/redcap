<?php
use PHPMailer\PHPMailer\PHPMailer;

class Message
{

    /*
    * PUBLIC PROPERTIES
    */


    // @var to string
    // @access public
    private $to;

	// @var toName string
    // @access public
    private $toName;

    // @var from string
    // @access public
    private $from;

	// @var fromName string
    // @access public
    private $fromName;

    // @var from string
    // @access public
    private $cc;

    // @var from string
    // @access public
    private $bcc;

    // @var subject string
    // @access public
    private $subject;

    // @var body string
    // @access public
    private $body;

    // @var attachments array
    // @access public
    public $attachments = array();
	public $attachmentsNames = array();

	// @var ErrorInfo string
	// @access public
	public $ErrorInfo = false;

    /*
    * PUBLIC FUNCITONS
    */

    function getTo()            { return $this->to; }
	function getCc()            { return $this->cc; }
	function getBcc()           { return $this->bcc; }
    function getFrom() 			{ return $this->from; }
	function getFromName()      { return $this->fromName; }
    function getSubject()       { return $this->subject; }
    function getBody()          { return $this->body; }
	function getAllRecipientAddresses()
	{
		$email_to = str_replace(array(" ",","), array("",";"), $this->getTo());
		$email_cc = str_replace(array(" ",","), array("",";"), $this->getCc());
		$email_bcc = str_replace(array(" ",","), array("",";"), $this->getBcc());
		$email_recipient_string = $email_to.";".$email_cc.";".$email_bcc;
		$email_recipient_array = array();
		foreach (explode(";", $email_recipient_string) as $this_email) {
			if ($this_email == "") continue;
			$email_recipient_array[] = $this_email;
		}
		return $email_recipient_array;
	}

    function setTo($val)        { $this->to = $val; }
    function setCc($val)       	{ $this->cc = $val; }
    function setBcc($val)       { $this->bcc = $val; }
    function setFrom($val)      { $this->from = $val; }
	function setFromName($val) 	{ $this->fromName = $val; }
    function setSubject($val)   { $this->subject = $val; }

	/**
	 * Attaches a file
	 * @param string $file_full_path The full file path of a file (including its file name)
	 */
    function setAttachment($file_full_path, $filename="")
	{
		if (!empty($file_full_path)) {
			if ($filename == "") {
				$filename = basename($file_full_path);
			}
			$this->attachments[] = $file_full_path;
			$this->attachmentsNames[] = $filename;
		}
	}
    function getAttachments()
	{
    	return $this->attachments;
    }
    function getAttachmentsWithNames()
	{
		$attachmentsNames = array();
		$attachments = $this->getAttachments();
		if (!empty($attachments)) {
			foreach ($attachments as $attachment_key=>$this_attachment_path) {
				$attachmentName = $this->attachmentsNames[$attachment_key];
				// If another attachment has the same name, then rename it on the fly to prevent conflict
				if (isset($attachmentsNames[$attachmentName])) {
					// Prepend file name with timestamp and random alphanum to ensure uniqueness
					$attachmentName = date("YmdHis")."_".substr(md5(rand()), 0, 4)."_".$attachmentName;
				}
				$attachmentsNames[$attachmentName] = $this_attachment_path;
			}
		}
		return $attachmentsNames;
	}

	/**
	 * Sets the content of this HTML email.
	 * @param string $val the HTML that makes up the email.
	 * @param boolean $onlyBody true if the $html parameter only contains the message body. If so,
	 * then html/body tags will be automatically added, and the message will be prepended with the
	 * standard REDCap notice.
	 */
    function setBody($val, $onlyBody=false) {
		global $lang;		
		// If want to use the "automatically sent from REDCap" message embedded in HTML
		if ($onlyBody) {
			$val =
				"<html>\r\n" .
				"<body style=\"font-family:arial,helvetica;font-size:10pt;\">\r\n" .
				$lang['global_21'] . "<br /><br />\r\n" .
				$val .
				"</body>\r\n" .
				"</html>";
		}
		// For compatibility purposes, make sure all line breaks are \r\n (not just \n) 
		// and that there are no bare line feeds (i.e., for a space onto a blank line)
		$val = str_replace(array("\r\n", "\r", "\n", "\r\n\r\n"), array("\n", "\n", "\r\n", "\r\n \r\n"), $val);
		// Set body for email message
		$this->body = $val;
	}

	// Format email body for text/plain: Replace HTML link with "LINKTEXT (URL)" and fix tabs and line breaks
	public function formatPlainTextBody($body)
	{
		$plainText = $body;
		if (preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $plainText, $matches)) {
			foreach ($matches[0] as $key=>$this_match) {
				$plainText = str_replace($this_match, $matches[3][$key]." (".$matches[2][$key].")", $plainText);
			}
		}
		$plainText = trim(str_replace(array("\r", "\n"), array("", ""), $plainText));
		$plainText = strip_tags(br2nl($plainText));
		$plainText = preg_replace("/\n\t+/", "\n", $plainText);
		$plainText = trim(preg_replace("/\t+/", " ", $plainText));
		return $plainText;
	}

    // Send the email
    public function send($removeDisplayName=false)
	{
		// Have email Display Names been disabled at the system level?
		global $use_email_display_name;
		if (isset($use_email_display_name) && $use_email_display_name == '0') {
			$removeDisplayName = true;
		}

		// Call the email hook
		$sendEmail = Hooks::call('redcap_email', array($this->getTo(), $this->getFrom(), $this->getSubject(), $this->getBody(), $this->getCc(),
								$this->getBcc(), $this->getFromName(), $this->getAttachmentsWithNames()));
		if (!$sendEmail) {
			// If the hook returned FALSE, then exit here without sending the email through normal methods below
			return true; // Return TRUE to note that the email was sent successfully because FALSE would imply some sort of error
		}

		// Get the Universal FROM Email address (if being used)
		$from_email = System::getUniversalFromAddess();

		// Suppress Universal FROM Address? (based on the sender's address domain)
		if (System::suppressUniversalFromAddress($this->getFrom())) {
			$from_email = ''; // Set Universal FROM address as blank so that it is ignored for this outgoing email
		}

		// Using the Universal FROM email?
		$usingUniversalFrom = ($from_email != '');

		// Set the From email for this message
		$this_from_email = (!$usingUniversalFrom ? $this->getFrom() : $from_email);

		// If the FROM email address is not valid, then return false
		if (!isEmail($this_from_email)) return false;

		if ($this->getFromName() == '') {
			// If no Display Name, then use the Sender address as the Display Name if using Universal FROM address
			$fromDisplayName = $usingUniversalFrom ? $this->getFrom() : "";
			$replyToDisplayName = '';
		} else {
			// If has a Display Name, then use the Sender address+real Display Name if using Universal FROM address
			$fromDisplayName = $usingUniversalFrom ? $this->getFromName()." <".$this->getFrom().">" : $this->getFromName();
			$replyToDisplayName = $this->getFromName();
		}
		// Remove the display name(s), if applicable
		if ($removeDisplayName) {
			$fromDisplayName = $replyToDisplayName = '';
		}

		if(!empty($GLOBALS["mandrill_api_key"])) {
			$messageData = [
					"to" => [],
					"from_email" => $this_from_email,
					"from_name" => $fromDisplayName,
					"headers" => ["Reply-To" => $this->getFrom()],
					"subject" => $this->getSubject(),
					"text" => $this->formatPlainTextBody($this->getBody()),
					"html" => $this->getBody()
			];

			foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
				$thisTo = trim($thisTo);
				if ($thisTo == '') continue;
				$messageData["to"][] = ["email" => $thisTo,"type" => "to"];
			}
			if ($this->getCc() != "") {
				foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
					$thisCc = trim($thisCc);
					if ($thisCc == '') continue;
					$messageData["to"][] = ["email" => $thisCc,"type" => "cc"];
				}
			}
			if ($this->getBcc() != "") {
				foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
					$thisBcc = trim($thisBcc);
					if ($thisBcc == '') continue;
					$messageData["to"][] = ["email" => $thisBcc,"type" => "bcc"];
				}
			}
			// Attachments, if any
			$attachments = $this->getAttachmentsWithNames();
			if (!empty($attachments)) {
				$messageData["attachments"] = [];
				foreach ($attachments as $attachmentName=>$this_attachment_path) {
					$mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($this_attachment_path))));
					if (empty($mime_type)) $mime_type = "application/octet-stream";
					$messageData["attachments"][] = ["type"=>$mime_type, "name"=>$attachmentName, "content"=>file_get_contents($this_attachment_path)];
				}
			}
			$data = [
				"message" => $messageData
			];

			$output = self::sendMandrillRequest($data,"messages/send.json");

			if(empty($output)) {
				error_log("Email: Failed send - Unknown reason (Mandrill not available?)");
			}

			$decodedOutput = json_decode($output,true);

			## Check for error message and log if needed
			if($decodedOutput["status"] == "error") {
				error_log("Email: Failed send ".$decodedOutput["message"]);
				$this->ErrorInfo = $decodedOutput["message"];
				return false;
			}
			if($decodedOutput[0]["status"] == "rejected") {
				error_log("Email: Failed send from ".$this_from_email." rejected because ".$decodedOutput[0]["reject_reason"]);
				$this->ErrorInfo = $output;
				return false;
			}
			return true;
		}

		## GOOGLE APP ENGINE ONLY
		if (isset($_SERVER['APPLICATION_ID']))
		{
			require APP_PATH_DOCROOT . 'ProjectGeneral/message_google_app_engine.php';
			return true;
		}

		## NORMAL ENVIRONMENT (using PHPMailer)
		// Init
		$mail = new PHPMailer;
		$mail->CharSet = 'UTF-8';
		// Subject and body
		$mail->Subject = $this->getSubject();
		$mail->msgHTML($this->getBody());
		// Format email body for text/plain: Replace HTML link with "LINKTEXT (URL)" and fix tabs and line breaks
		$mail->AltBody = $this->formatPlainTextBody($this->getBody());
		// From, Reply-To, and Return-Path. Also, set Display Name if possible.
		// From/Sender and Reply-To
		$mail->setFrom($this_from_email, $fromDisplayName, false);
		$mail->addReplyTo($this->getFrom(), $replyToDisplayName);
		$mail->Sender = $this_from_email; // Return-Path; This also represents the -f header in mail().
		// To, CC, and BCC
		foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
			$thisTo = trim($thisTo);
			if ($thisTo == '') continue;
			$mail->addAddress($thisTo);
		}
		if ($this->getCc() != "") {
			foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
				$thisCc = trim($thisCc);
				if ($thisCc == '') continue;
				$mail->addCC($thisCc);
			}
		}
		if ($this->getBcc() != "") {
			foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
				$thisBcc = trim($thisBcc);
				if ($thisBcc == '') continue;
				$mail->addBCC($thisBcc);
			}
		}
		// Attachments
		$attachments = $this->getAttachmentsWithNames();
		if (!empty($attachments)) {
			foreach ($attachments as $attachmentName=>$this_attachment_path) {
				$mail->addAttachment($this_attachment_path, $attachmentName);
			}
		}

		/*
		// Use DKIM?
		$dkim = new DKIM();
		if ($dkim->isEnabled())
		{
			$mail->DKIM_domain = $dkim->DKIM_domain;
			$mail->DKIM_private_string = $dkim->privateKey;
			$mail->DKIM_selector = $dkim->DKIM_selector;
			$mail->DKIM_passphrase = $dkim->DKIM_passphrase;
			$mail->DKIM_copyHeaderFields = false;
			// $mail->DKIM_extraHeaders = ['List-Unsubscribe', 'List-Help'];
			// $mail->DKIM_identity = $mail->From;
		}
		*/

		// Send it
		$sentSuccessfully = $mail->send();
		// Add error message, if failed to send
		if (!$sentSuccessfully) {
			$this->ErrorInfo = $mail->ErrorInfo;
		}
		// Return boolean for success/fail
		return $sentSuccessfully;
    }

	/**
	 * Returns HTML suitable for displaying to the user if an email fails to send.
	 */
	function getSendError()
	{
		global $lang;
		return  "<div style='font-size:12px;background-color:#F5F5F5;border:1px solid #C0C0C0;padding:10px;'>
			<div style='font-weight:bold;border-bottom:1px solid #aaaaaa;color:#800000;'>
			<img src='".APP_PATH_IMAGES."exclamation.png'>
			{$lang['control_center_243']}
			</div><br>
			{$lang['global_37']} <span style='color:#666;'>{$this->fromName} &#60;{$this->from}&#62;</span><br>
			{$lang['global_38']} <span style='color:#666;'>{$this->toName} &#60;{$this->to}&#62;</span><br>
			{$lang['control_center_28']} <span style='color:#666;'>{$this->subject}</span><br><br>
			{$this->body}<br>
			</div><br>";
	}

	## Set up a curl call to the specified Mandrill endpoint and attach the API key to the data to be sent
	## Return the response data, or else return an error message if HTTP response code is not 200
	/**
	 * Set up a curl call to the specified Mandrill endpoint and attach the API key to the data to be sent
	 * Return the response data, or else return an error message if HTTP response code is not 200
	 * @param $data array
	 * @param $endpoint string
	 * @return string
	 */
	public static function sendMandrillRequest($data,$endpoint) {
		## Don't send if API key doesn't exist
		if(empty($GLOBALS["mandrill_api_key"])) return false;

		## Append API key to data to send
		$data["key"] = $GLOBALS["mandrill_api_key"];

		$data = http_build_query($data);
		$url = 'https://mandrillapp.com/api/1.0/'.$endpoint;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POST,true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mandrill-Curl/1.0');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$output = curl_exec($ch);
		$httpCode = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if($httpCode != 200) {
			$output = ["status" => "error","message" => "$url returned a status $httpCode :\r\n".var_export($output,true)];
			$output = json_encode($output);
		}

		## Return the response
		return $output;
	}
}
