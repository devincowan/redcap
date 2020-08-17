<?php

class AlertsController extends Controller
{
	// Render the setup page
	public function setup()
	{
        $alerts = new Alerts();
        $alerts->renderSetup();
	}

    // Find filename of edoc by doc_id
    public function getEdocName()
    {
        $edoc = isset($_POST['edoc']) && is_numeric($_POST['edoc']) ? (int)$_POST['edoc'] : null;
        $alerts = new Alerts();
        $alerts->getEdocNameById($edoc);
    }

    // Create a new alert or update an existing alert
    public function saveAlert()
    {
        $alerts = new Alerts();
        $alerts->saveAlert();
    }

    // Copy an alert
    public function copyAlert()
    {
        $alerts = new Alerts();
        $alerts->copyAlert();
    }

    // Delete an alert
    public function deleteAlert()
    {
        $alerts = new Alerts();
        $alerts->deleteAlert();
    }

    // Delete an alert (permanently)
    public function deleteAlertPermanent()
    {
        $alerts = new Alerts();
        $alerts->deleteAlertPermanent();
    }

    // Download an alert's attachment file
    public function downloadAttachment()
    {
        $alerts = new Alerts();
        $alerts->downloadAttachment();
    }

    // Upload an alert's attachment file
    public function saveAttachment()
    {
        $alerts = new Alerts();
        $alerts->saveAttachment();
    }

    // Delete an alert's attachment file
    public function deleteAttachment()
    {
        $alerts = new Alerts();
        $alerts->deleteAttachment();
    }

    // Determine if we need to display repeating instrument textbox option when manually queueing an alert for a record
    public function displayRepeatingFormTextboxQueue()
    {
        $alerts = new Alerts();
        $alerts->displayRepeatingFormTextboxQueue();
    }

    // View table of queued records for a given alert
    public function viewQueuedRecords()
    {
        $alerts = new Alerts();
        $alerts->viewQueuedRecords();
    }

    // Delete a queued record for a given alert
    public function deleteQueuedRecord()
    {
        $alerts = new Alerts();
        $alerts->deleteQueuedRecord();
    }

    // Manually add a queued record for a given alert
    public function addQueuedRecord()
    {
        $alerts = new Alerts();
        $alerts->addQueuedRecord();
    }

    // Display table of an alert's message contents
    public function previewAlertMessage()
    {
        $alerts = new Alerts();
        $alerts->previewAlertMessage();
    }

    // Display dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecordDialog()
    {
        $alerts = new Alerts();
        $alerts->previewAlertMessageByRecordDialog();
    }

    // Display table inside dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecord()
    {
        $alerts = new Alerts();
        $alert_sent_log_id = (isset($_POST['alert_sent_log_id']) ? $_POST['alert_sent_log_id'] : null);
        $aq_id = (isset($_POST['aq_id']) ? $_POST['aq_id'] : null);
        $alerts->previewAlertMessageByRecord($alert_sent_log_id, $aq_id);
    }

    // Migrate all Email Alerts (i.e., the external module) into Alerts for a give project
    public function migrateEmailAlerts()
    {
        $alerts = new Alerts();
        $alerts->migrateEmailAlerts();
    }

}