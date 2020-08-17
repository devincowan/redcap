<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\Resources\DocumentReference\Attachment;


/**
 * @property Attachment[] $attachments
 */
class FhirResourceDocumentReference extends FhirResourceEntry
{

    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'DocumentReference';

    /** 
     * Map the FHIR endpoint category to the name of the query string "code" parameter.
     * Note: Most categories do not have this structure.
     */
    const DATE_PARAMETER_NAME = 'created';

    // classes for DSTU2
    const CLASS_CCD = '34133-9'; // Summarization of Episode Note
    const CLASS_ENCOUNTER = '11506-3'; // Subsequent Evaluation Note.

    // types for STU3
    const TYPE_DISCHARGE_DOCUMENTATION = '18842-5'; // Discharge Documentation
    const TYPE_CONSULTATION = '11488-4'; // Consultation
    const TYPE_HISTORY_AND_PHYSICAL = '34117-2'; // History & Physical
    const TYPE_PROGRESS_NOTE = '11506-3'; // Progress Note
    const TYPE_PROCEDURE_NOTE = '28570-0'; // Procedure Note
    const TYPE_EMERGENCY_DEPARTMENT_NOTE = '34111-5'; // Emergency Department Note
    const TYPE_PATIENT_INSTRUCTIONS = '69730-0'; // Patient Instructions
    const TYPE_NURSE_NOTE = '34746-8'; // Nurse Note

    public function __construct($params)
    {
        parent::__construct($params);
        $this->attachments = $this->getAttachments();
    }

    /* public static function getSearchUrl($params=array())
    {
        $url = parent::getSearchUrl($params);
        $STU3_url = preg_replace("/(.+\/)DSTU2(\/.+)/", '$1STU3$2', $url);
        return $STU3_url;
    } */

    /**
     * attribute that contains the date for the resource
     *
     * @var string
     */
    protected static $dateAttribute = 'indexed';

    public function getStatus()
    {
        return $this->status;
    }

    public function getLabel()
    {
        $type = $this->type;
        $text = $type->text;
        return $text;
    }

    public function getContents()
    {
        $contents = $this->content;
        return $contents;
    }

    protected function getAttachments()
    {
        $contents = $this->getContents();
        $attachments = array();
        foreach ($contents as $content) {
            $attachment = $content->attachment;
            $attachments[] = new Attachment($attachment);
        }
        return $attachments;
    }

    public function getData()
    {
        $data = array(
            'label' => $this->getLabel(),
            // 'status' => $this->getStatus(),
            'content' => $this->getContents(),
            'date' => $this->getFormattedDate(),
            'attachments' => $this->attachments,
        );
        return $data;
    }
}

