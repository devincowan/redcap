<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * abstrac class for a FHIR resource entry (Observation, MedicationOrder...)
 */
class FhirResourceEntry extends FhirResource
{
    /**
     * attribute that contains the date for the resource entry
     *
     * @var string
     */
    protected static $dateAttribute = 'date';

    /**
     * The time or time-period the observed value is asserted as being true.
     * For biological subjects - e.g. human patients - this is usually called the "physiologically relevant time".
     * This is usually either the time of the procedure or of specimen collection,
     * but very often the source of the date/time is not known, only the date/time itself.
     *
     * @return DateTime|null
     */
    public function getDate()
    {
        $date_fields = is_array(static::$dateAttribute) ? static::$dateAttribute : array(static::$dateAttribute);
        $date_string = '';
        foreach ($date_fields as $field)
        {
            if(!array_key_exists($field, $this->attributes)) continue; // check if property exists
            if(empty($this->{$field})) continue; // check if property is empty
            // stop as soon as a date attribute is found
            $date_string = $this->{$field};
            break;
        }
        if(empty($date_string)) return '';
        $timestamp = trim(substr($date_string, 0, 10));
        if (strlen($timestamp) == 4) $timestamp .= "-01-01";
        
        if(empty($date_string)) return;
        return new \DateTime($date_string);
    }

    /**
     * return the string version of the date
     *
     * @param string $format
     * @return string
     */
    public function getFormattedDate($format='Y-m-d H:i:s.u')
    {
        $date = $this->getDate();
        return ($date instanceof \DateTime) ? $date->format($format) : '';
    }

}