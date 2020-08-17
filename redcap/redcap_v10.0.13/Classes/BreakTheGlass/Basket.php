<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use Vanderbilt\REDCap\Classes\Fhir\FhirLogsMapper;

/**
 * container object for the list of the patients that need to be processed
 * with the "break the glass" methods
 */
class Basket
{
    /**
     * name of the session variable containing the list of patients
     * that must be checked for "break the glass"
     *
     */
    const SESSION_VAR_NAME = "break_the_glass_basket";

    public function __construct($project_id)
    {
        $this->project_id = $project_id;

        $this->checkSession();
    }

    public function &getList()
    {
        $list = &$_SESSION[self::SESSION_VAR_NAME];
        // make sure the list in the session is an array
        if(!is_array($list)) $list = array();
        // also make sure there is a list for the current project
        if(!is_array($list[$this->project_id])) $list[$this->project_id] = array();
        return $list;
    }

    /**
     * add a patient to the list
     * skip duplicates
     *
     * @param string $patient
     * @param string $status
     * @return string|false the inserted patient or false if duplicate
     */
    public function add($patient, $status=GlassBreaker::PATIENT_ACCESS_BLOCKED)
    {
        $list = &$this->getList();
        $project_list = &$list[$this->project_id];
        // check for duplicatues
        // if(in_array($patient, $project_list)) return false;
        $project_list[$patient] = array(
            'inserted_at' => new \DateTime(),
            'status' => $status,
        );
        return $list;
    }

    /**
     * remove a patient from the list
     *
     * @param string $patient
     * @return void
     */
    public function remove($patient)
    {
        $list = $this->getList();
        $project_list = &$list[$this->project_id];
        if(array_key_exists($patient, $project_list))
            unset($project_list[$patient]);
        return $list;
        // return $offset !== false;
    }

    /**
     * empty the list
     *
     * @return void
     */
    public function empty()
    {
        $list = &$this->getList();
        $list = array();
    }

    /**
     * make sure that the session is started
     *
     * @return void
     */
    private function checkSession()
    {
        if(session_status()!==PHP_SESSION_ACTIVE) session_start();
    }
}