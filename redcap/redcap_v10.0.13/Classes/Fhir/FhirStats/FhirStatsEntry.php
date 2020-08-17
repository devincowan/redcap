<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

/**
 * @property integer $record_id ID of the record
 * @property string $resource_type FHIR resource type 
 */
class FhirStatsEntry
{
    /**
     * record ID
     *
     * @var integer
     */
    private $record_id;

    /**
     * FHIR resource type
     *
     * @var string
     */
    private $resource_type;


    public function __construct($record_id, $resource_type)
    {
        $this->record_id = $record_id;
        $this->resource_type = $resource_type;
    }

    /**
     * magic getter for private properties
     *
     * @param string $name
     * @return void
     */
    public function __get($name)
    {
        if (property_exists($this, $name))
        {
            return $this->{$name};
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }
}