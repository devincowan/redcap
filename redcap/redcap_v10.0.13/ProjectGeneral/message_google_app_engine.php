<?php

// Send email using Google App Engine's Message class.
// Contained in its own file for PHP 5.1 and 5.2, which throw a PHP parsing
// error due to the use of namespace.

try
{
	// Set up email paramas
	$message = new \google\appengine\api\mail\Message();
	$message->setSender($this_from_email);
	$message->setReplyTo($this->getFrom());
	$message->addTo($this->getTo());
	if ($this->getCc() != "") {
		$message->addCc($this->getCc());
	}
	if ($this->getBcc() != "") {
		$message->addBcc($this->getBcc());
	}
	$message->setSubject($this->getSubject());
	$message->setHtmlBody($this->getBody());
	// Attachments, if any
	$attachments = $this->getAttachmentsWithNames();
	if (!empty($attachments)) {
		foreach ($attachments as $attachmentName=>$this_attachment_path) {
			$message->addAttachment($attachmentName, file_get_contents($this_attachment_path), "<".sha1(rand()).">");
		}
	}
	// Send email
	try { $message->send(); } catch (InvalidArgumentException $e) { }
	return true;
} catch (InvalidArgumentException $e) {
	print "<br><b>ERROR: ".$e->getMessage()."</b>";
	return false;
}