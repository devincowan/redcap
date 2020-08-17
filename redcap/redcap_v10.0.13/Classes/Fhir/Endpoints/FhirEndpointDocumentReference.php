<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;
use Vanderbilt\REDCap\Classes\Fhir\FhirException;

class FhirEndpointDocumentReference extends FhirEndpoint
{
    const RESOURCE_TYPE = 'Condition';

    public function search($params)
    {
        $accepted_keys = array(
            'asserter',
            'body-site',
            'category',
            'clinicalstatus',
            'code',
            'date-recorded',
            'encounter',
            'evidence',
            'identifier',
            'onset',
            'onset-info',
            'patient',
            'severity',
            'stage',
        );
        
        $filtered_params = $this->filterParams($params, $accepted_keys);
        return parent::search($filtered_params);
    }

    /**
     * get the attachment data
     * if the data has already been saved locally then get the local file
     * otherwise get the remote binary data.
     * when the data is fetch from the remote FHIR endpoint it is also persisted locally.
     * download new data also if the file exists and is older than $max_age
     * 
     *
     * @param string $access_token
     * @param string $mrn
     * @param object $entry
     * @param object $attachment
     * @param integer $max_age 600 = 10 minutes
     * @return void
     */
    public function getAttachment($access_token, $document_id, $attachment, $max_age=600)
    {
        $contentType = $attachment->contentType;
        $url = $attachment->url;
        $file_extension = explode('/', $contentType )[1];
        $destination = EDOC_PATH.'document_reference';
        $file_name = sprintf("%s.%s", $document_id, $file_extension);
        $file_path = sprintf("%s/%s", $destination, $file_name);
        $creation_date = filemtime($file_path);
        $now = time();
        if($creation_date===false || ($now - $creation_date)>=$max_age)
        {
            $binary_data = $this->getBinaryData($access_token, $url, $contentType);
            $success = $this->saveFile($binary_data, $file_path);
            if(!$success)
            {
                $message = sprintf("Error Processing Request. Could not save the file to path %s", $file_path);
                throw new \Exception($message, 1);
            }
        }else {
            $binary_data = file_get_contents($file_path);
        }
        return $binary_data;
    }

    /**
     * save a file locally
     *
     * @param string $binary_data
     * @param string $path
     * @return void
     */
    private function saveFile($binary_data, $path)
    {
        $destination = dirname($path);
        if(!file_exists($destination)) mkdir($destination, 0777, true);
        $flags = FILE_APPEND;
        $flags = null; // overwrite
        return file_put_contents( $path , $binary_data , $flags );
    }

    /**
     * get the data from a binary file
     *
     * @param string $url
     * @param string $patient_id used to retreive the same access token of the patient owning the file
     * @return string
     */
    public function getBinaryData($url, $access_token, $contentType)
    {
        // Instantiate FHIR Services
        try {
            $headers = array(
                'Accept' => null,
                'Content-Type' => $contentType,
            );
            $fhirData = $this->getFhirData($url, $access_token, $headers);
            return $fhirData;
        } catch (\Exception $e) {
            throw new FhirException($message=$e->getMessage(), $code=$e->getCode());
        }
    }

}
