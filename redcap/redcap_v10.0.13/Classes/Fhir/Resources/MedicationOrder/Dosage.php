<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources\MedicationOrder;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;

/**
 * Medication dosage
 * 
 * @property FhirResourceMedicationOrder\Dosage\Route $route
 */
class Dosage extends FhirResource
{

    const REPEAT_PERIOD_START = 'start';
    const REPEAT_PERIOD_END = 'end';

    public function __construct($params)
    {
        parent::__construct($params);
        $this->route = $this->getRoute();
    }

    protected function getRoute()
    {
        $route = new Dosage\Route($this->route);
        return $route;
    }

    public function getText()
    {
        if(!empty($this->text)) return $this->text;
        try {
            return $this->timing->code->text;
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getTiming()
    {
        return $this->timing;
    }

    public function getQuantity()
    {
        $doseQuantity = $this->doseQuantity;
        if(!$doseQuantity) return;
        $value = $doseQuantity->value ?: '';
        $unit = $doseQuantity->unit ?: '';
        return sprintf("%s %s", $value, $unit);
    }

    /**
     * get repeat instructions
     *
     * @param string $period start|end
     * @return void
     */
    public function getRepeat($period=self::REPEAT_PERIOD_START)
    {
        $periods = array(self::REPEAT_PERIOD_START, self::REPEAT_PERIOD_END);
        if(!in_array($period, $periods)) return '';
        try {
            return $this->timing->repeat->boundsPeriod->{$period};
        } catch (\Exception $e) {
            return '';
        }
    }


    public function getAsNeeded()
    {
        return $this->asNeededBoolean;
    }

    public function getData()
    {
        $data = array(
            'asNeeded' => $this->getAsNeeded(),
            'quantity' => $this->getQuantity(),
            'route' => $this->getRoute(),
            'timing' => $this->getTiming(),
            'text' => $this->getText(),
            'repeat_start' => $this->getRepeat(self::REPEAT_PERIOD_START),
            'repeat_end' => $this->getRepeat(self::REPEAT_PERIOD_END),
        );
        return array_merge(parent::getData(), $data);
    }

    /**
     * get JSON serialized version of the object
     *
     * @return array
     */
    public function jsonSerialize()
    {
        
        return $this->getData();
    }

}