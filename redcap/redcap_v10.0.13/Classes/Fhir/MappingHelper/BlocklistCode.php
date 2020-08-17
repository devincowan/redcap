<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

/**
 * data structure for balcklisted codes
 */
class BlocklistCode
{
    /**
     * code that identifies the field
     *
     * @var string
     */
    public $code;

    /**
     * reason explaining why the code is not being used
     *
     * @var string
     */
    public $reason;

    public function __construct($code, $reason)
    {
        $this->code = $code;
        $this->reason = $reason;
    }
}

