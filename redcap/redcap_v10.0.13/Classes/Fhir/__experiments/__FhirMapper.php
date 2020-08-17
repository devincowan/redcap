
<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use  Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceMedicationOrder;

/**
 * map REDCap fields to FHIR requests
 */
class FhirMapper
{
    /**
     * datetime in FHIR compatible format
     * https://www.hl7.org/fhir/datatypes.html#dateTime
     */
    const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

    public function construct()
    {

    }

    /**
     * build the endpoint URL
     *
     * @param string $resource_type FHIR resource type
     * @param array $fields REDCap fields
     * @return void
     */
	private function getQueryParams($resource_type, $patient_id, $properties)
	{
        global $fhir_convert_timestamp_from_gmt;
        
        $params = array();

        switch ($this->name) {
            case 'Patient':
				// Set URL
                $params = $patient_id;
                return $params;
                break;
			case 'MedicationOrder':
			    // If "Medications" endpoint for "Active medications list", then set specific URL for it
                // Set URL
                $status_list = array(
                    FhirResourceMedicationOrder::STATUS_ACTIVE,
                    FhirResourceMedicationOrder::STATUS_COMPLETED,
                    FhirResourceMedicationOrder::STATUS_ON_HOLD,
                    FhirResourceMedicationOrder::STATUS_STOPPED,
                );
                $requested_status = array();
                // build a regexp that matches all available medication status
                $regExp = sprintf("/^(%s)-medications-list\$/i", implode('|', $status_list));
                foreach ($properties['fields'] as $field) {
                    preg_match($regExp, $field, $matches);
                    if($matches)
                    {
                        $requested_status[] = $matches[1]; // get the matched status
                    }
                }
                $params = $requested_status;
                return $params;
                break;
			case 'Condition':
                $params['patient'] = urlencode($patient_id);
                break;
			case 'AllergyIntolerance':
                $params['patient'] = urlencode($patient_id);
                break;
            case 'Observation':
                $params['patient'] = urlencode($patient_id);
                break;
            default:
                break;
        }
        if (isset(FhirEhr::$fhirEndpointQueryStringCodeParameter[$this->name])) {
            // Get param names, which may differ for endpoints
            $dateParamName = FhirEhr::$fhirEndpointQueryStringDateParameter[$this->name];
            $codeParamName = FhirEhr::$fhirEndpointQueryStringCodeParameter[$this->name];
            $date_range = $this->getDateRangeQueryParams($properties['minDate'], $properties['maxDate']);
            foreach ($date_range as $key => $date) {
                $url .= "&{$dateParamName}={$date}";
            }

            // add the coding system in front of the code. this is ignored by Epic but required in cerner
            $properties['fields'] = array_map(function($val) { return 'http://loinc.org|'.$val;} , $properties['fields']);
            // Set fields, codes, etc.
            $fields = empty($properties['fields']) ? "" : "&{$codeParamName}=" . urlencode(implode(",", $properties['fields']));
            // Set URL
            $url .=  $fields;
        }
		return $url;
    }

    /**
     * Get the min and max date parameters.
     * check the 'fhir_convert_timestamp_from_gmt' system setting and performs
     * additions/sottactions accordingly
     *
     * @param string $date_min
     * @param string $date_max
     * @return array
     */
    private function getDateRangeQueryParams($date_min, $date_max)
    {
        global $fhir_convert_timestamp_from_gmt;

        $params = array();
        if (!empty($date_min)) {
            // If dealing with GMT conversion, open window of time by one extra day to compensate for local time offset from GMT				
            if ($fhir_convert_timestamp_from_gmt == '1') {
                $date_min = date(self::FHIR_DATETIME_FORMAT, strtotime($date_min . ' - 1 days'));
            }
            $params['date_min'] = "gt{$date_min}";
        }
        if (!empty($date_max)) {
            // If dealing with GMT conversion, open window of time by one extra day to compensate for local time offset from GMT
            if ($fhir_convert_timestamp_from_gmt == '1') {
                $date_max = date(self::FHIR_DATETIME_FORMAT, strtotime($date_max . ' + 1 days'));
            }
            $params['date_max'] = "lt{$date_max}";
        }
        return $params;
    }

    public function _get($name)
    {

    }
}