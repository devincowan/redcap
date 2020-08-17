<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Observation;

/**
 * value of an Observation FHIR Resource
 * 
 * @property mixed $value the value
 * 
 */
class Value implements \JsonSerializable
{
    /**
     * all keys tha could store the value for the observation
     *
     * @var array
     */
    private static $value_keys = array(
        'valueQuantity',
        'valueCodeableConcept',
        'valueString',
        'valueRange',
        'valueRatio',
        'valueSampledData',
        'valueAttachment',
        'valueTime',
        'valueDateTime',
        'valuePeriod',
    );

    /**
     * the component received upon creation
     *
     * @var object
     */
    private $component;

    /**
     * A Coding is a representation of a defined concept using a symbol from a defined "code system"
     *
     * @var object
     */
    private $coding;

    /**
     * contains all attributes of the instance
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * create an instance
     *
     * @param object $component
     * @param object $coding Code defined by a terminology system
     */
    public function __construct($component, $coding=null)
    {
        $this->component = $component;
        $this->coding = $coding;
    }

    /**
     * get the code: symbol in syntax defined by the system
     *
     * @return string
     */
    public function getCode()
    {
        if(!isset($this->coding)) return;
        $code = $this->coding->code; //Symbol in syntax defined by the system
        return $code;
    }

    /**
     * Representation defined by the system
     *
     * @return string
     */
    public function getDisplay()
    {
        // code is the parent of the $coding object. can contain the text if coding has no display
        $code = $this->component->code;
        $display = isset($this->coding->display) ? $this->coding->display : $code->text;
        return $display;
    }


    /**
     * get the key of the object containing the value
     *
     * @return string
     */
    public function getValueKey()
    {
        $attributes = get_object_vars($this->component); // get all attributes available for the resource
        $value_keys = array_intersect(self::$value_keys, array_keys($attributes)); // value keys available for the resource
        if(empty($value_keys)) return;
        // get only the first key available. there should be just one key0
        $value_key = reset($value_keys);
        return $value_key;
    }

    /**
     * get the value of the current Observation
     *
     * @return mixed
     */
    public function getValue()
    {
        $value_key = $this->getValueKey();
        if(empty($value_key)) return;
        $value = $this->component->{$value_key};
        return $value;
    }

    public function getTextValue()
    {
        $value_key = $this->getValueKey();
        if(empty($value_key)) return '';

        $text = '';
        $value = $this->getValue();

        switch ($value_key) {
            case 'valueQuantity':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#quantity
                 * 
                 * "value" : <decimal>, // Numerical value (with implicit precision)
                 * "comparator" : "<code>", // < | <= | >= | > - how to understand the value
                 * "unit" : "<string>", // Unit representation
                 * "system" : "<uri>", // C? System that defines coded unit form
                 * "code" : "<code>" // Coded form of the unit
                 */
                $text = $value->value;
                break;
            case 'valueCodeableConcept':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#codeableconcept
                 * 
                 * "coding" : [{ Coding }], // Code defined by a terminology system
                 * "text" : "<string>" // Plain text representation of the concept
                 */
                $text = $value->text;
                break;
            case 'valueRange':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#range
                 * 
                 * "low" : { Quantity(SimpleQuantity) }, // Low limit
                 * "high" : { Quantity(SimpleQuantity) } // High limit
                 */
                $range = array();
                if(!empty($value->low)) $range[] = $value->low;
                if(!empty($value->high)) $range[] = $value->high;
                $text = implode(', ', $range);
                break;
            case 'valueRatio':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#ratio
                 * "numerator" : { Quantity }, // Numerator value
                 *  "denominator" : { Quantity } // Denominator value
                 */
                try {
                    // watch out with divide by zero!
                    $text = ($value->numerator)/($value->denominator);
                } catch (\Exception $e) {
                    $text = $e->getMessage();
                }
                break;
            case 'valueSampledData':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#sampleddata
                 * 
                 * "origin" : { Quantity(SimpleQuantity) }, // R!  Zero value and units
                 * "period" : <decimal>, // R!  Number of milliseconds between samples
                 * "factor" : <decimal>, // Multiply data by this before adding to origin
                 * "lowerLimit" : <decimal>, // Lower limit of detection
                 * "upperLimit" : <decimal>, // Upper limit of detection
                 * "dimensions" : "<positiveInt>", // R!  Number of sample points at each time point\
                 * "data" : "<string>" // Decimal values with spaces, or "E" | "U" | "L"
                */
                $text = $value->data;
                break;
            case 'valueAttachment':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#attachment
                 * 
                 * // from Element: extension
                 * "contentType" : "<code>", // Mime type of the content, with charset etc.
                 * "language" : "<code>", // Human language of the content (BCP-47)
                 * "data" : "<base64Binary>", // Data inline, base64ed
                 * "url" : "<url>", // Uri where the data can be found
                 * "size" : "<unsignedInt>", // Number of bytes of content (if url provided)
                 * "hash" : "<base64Binary>", // Hash of the data (sha-1, base64ed)
                 * "title" : "<string>", // Label to display in place of the data
                 * "creation" : "<dateTime>" // Date attachment was first created
                 */
                $text = $value->title;
                break;
            case 'valuePeriod':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#period
                 * 
                 * "start" : "<dateTime>", // C? Starting time with inclusive boundary
                 * "end" : "<dateTime>" // C? End time with inclusive boundary, if not ongoing
                */
                $text = sprintf("from %s to %s", $value->start, $value->end);
                break;
            case 'valueTime':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#time
                 */
            case 'valueDateTime':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#dateTime
                 */
            case 'valueString':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#string
                 */
                $text = $value;
                break;
            default:
                // get all data and store it as text
                $text = print_r($value, true);
                break;
        }
        return strval($text);
    }

    public function __toString()
    {
        return $this->getTextValue();
    }

    /**
     * get JSON serialized version of the object
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $data = array(
            'code' => $this->getCode(),
            'display' => $this->getDisplay(),
            'text_value' => $this->getTextValue(),
            'type' => $this->getValueKey(),
            'value' => $this->getValue(),
        );
        return $data;
    }
}