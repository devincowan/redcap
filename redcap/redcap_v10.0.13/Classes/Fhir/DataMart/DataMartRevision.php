<?php


namespace Vanderbilt\REDCap\Classes\Fhir\DataMart
{

    use Vanderbilt\REDCap\Classes\Fhir\FhirLogsMapper;
    use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
    
    /**
     * Model of the DataMart revision that is saved on the database
     * 
     * exposed properties:
     * @property integer $id The primary key for the model
     * @property integer $user_id ID of the user creating the revision
     * @property integer $project_id ID of the project associated to this revision
     * @property integer $request_id ID of the request associated to this revision
     * @property string $request_status status of the request (if applicable)
     * @property array $mrns list of MRN numbers 
     * @property \DateTime|string $date_min minimum date for temporal data
     * @property \DateTime|string $date_max maximum date for temporal data
     * @property array $fields list of fields to use when fetching data
     * @property boolean $approved the revision has been approved by an administrator
     * @property \DateTime $created_at creation date
     * @property \DateTime $executed_at date of first execution
     */
    class DataMartRevision implements \JsonSerializable
    {

        /**
         * datetime in FHIR compatible format
         * https://www.hl7.org/fhir/datatypes.html#dateTime
         */
        const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

        /**
         * The primary key for the model
         *
         * @var int
         */
        private $id;

        /**
         * ID of the project associated to this revision
         *
         * @var integer
         */
        private $project_id;

        /**
         * ID of the user creating the revision
         *
         * @var int
         */
        private $user_id;
        
        /**
         * ID of the request associated to this revision
         *
         * @var integer
         */
        private $request_id;

        /**
         * status of the request (if applicable)
         *
         * @var string
         */
        private $request_status;

        /**
         * list of MRN numbers 
         *
         * @var array
         */
        private $mrns = array();

        /**
         * minimum date for temporal data
         *
         * @var \DateTime|string
         */
        private $date_min;

        /**
         * maximum date for temporal data
         *
         * @var \DateTime|string
         */
        private $date_max;

        /**
         * list of fields to use when fetching data
         *
         * @var array
         */
        private $fields = array();

        /**
         * the revision has been approved by an administrator
         *
         * @var boolean
         */
        private $approved = false;

        /**
         * creation date
         *
         * @var \DateTime
         */
        private $created_at;

        /**
         * date of first execution
         *
         * @var \DateTime
         */
        private $executed_at;

        /**
         * list of the instance variables that are public  for reading
         *
         * @var array
         */
        private static $readable_variables = array(
            'id',
            'project_id',
            'request_id',
            'user_id',
            'mrns',
            'date_min',
            'date_max',
            'fields',
            'approved',
            'created_at',
            'executed_at',
            'request_status',
        );

        /**
         * list of keys that can be provided in constructor
         *
         * @var array
         */
        private static $constructor_keys = array(
            'id',
            'project_id',
            'request_id',
            'user_id',
            'fields',
            'date_min',
            'date_max',
            'mrns',
            'approved',
            'created_at',
            'executed_at',
        );

        private static $table_name = 'redcap_ehr_datamart_revisions';
        private static $request_table_name = 'redcap_todo_list';

        /**
         * fields in the revisions table
         * used to build the update query for the database
         *
         * @var array
         */
        private static $fillable = array(
            'project_id',
            'request_id',
            // 'user_id',
            'mrns',
            'date_min',
            'date_max',
            'fields',
            'approved',
            'created_at',
            'executed_at',
        );

        private static $string_delimiter = "\n";
        private static $dateTimeFormat = 'Y-m-d H:i:s';
        private static $mandatory_fields = array(
            'project_id|request_id', //project_id OR request_id must be present
            'user_id',
            // 'mrns',
            'fields',
        );

        /**
         * constructor
         *
         * @param array $params an array with any value listed in self::$constructor_keys
         */
        function __construct($params=array())
        {
            try {
                $this->checkRequirements($params);
                // cycle through the permitetd constructor keys
                foreach (self::$constructor_keys as $key) {
                    if(array_key_exists($key, $params)) $this->set($key, $params[$key]);
                }
            } catch (\Exception $e) {
                $messages = array(
                    'Error instantianting the revision.',
                    $e->getMessage(),
                );
                throw new \Exception(implode("\n", $messages));
            }
        }

        /**
         * check minimum requirements from revision creation
         *
         * @param array $params
         * @return void
         */
        private function checkRequirements($params)
        {
            foreach (self::$mandatory_fields as $field) {
                $valid = false;
                foreach (array_keys($params) as $key) {
                    preg_match("/^{$field}$/", $key, $matches);
                    $valid = !empty($matches);
                    if($valid) break;
                }
                if(!$valid)
                    throw new \Exception("Mandatory field '{$field}' is missing.", 1);
            }
        }

        /**
         * get Data Mart settings for a project
         * the settings are divided in revisions
         *
         * @param int $project_id
         * 
         * @return array list of DataMartRevision
         */
        public static function all($project_id)
        {
            $select_query = self::getSelectQuery();
            $order_by_query_clause = self::getOrderByQueryClause();
            $query = sprintf($select_query." AND r.project_id = %d ".$order_by_query_clause, db_real_escape_string(intval($project_id)));
            $result = db_query($query);
            //print_array($query);
            
            if(!$result) return;

            $revisions = array();
            while($data = db_fetch_array($result))
            {
                $revision = new self($data);
                $revisions[] = $revision;
            }

            if( empty($revisions) ) return array();
            return $revisions;
        }

        /**
         * return the active revision for a project
         * the revision must be approved and not soft-deleted
         *
         * @param integer $project_id
         * @return DataMartRevision|false
         */
        public static function getActive($project_id)
        {
            $query_string = sprintf("SELECT id
                            FROM  %s
                            WHERE project_id=%u
                            AND is_deleted!=1
                            ORDER BY created_at DESC
                            LIMIT 1",
                            self::$table_name, $project_id);
            $result = db_query($query_string);
            if($result && $row = db_fetch_assoc($result))
            {
                $revision_id = $row['id'];
                return self::get($revision_id);
            }
            return false;
        }

        /**
         * get a revision from the database using the ID
         *
         * @param int $id
         * @return DataMartRevision|false
         */
        public static function get($id)
        {
            $select_query = self::getSelectQuery();
            $order_by_query_clause = self::getOrderByQueryClause();
            $query_string = sprintf($select_query." AND r.id=%u ".$order_by_query_clause, db_real_escape_string(intval($id)));

            $result = db_query($query_string);
            if($result && $params=db_fetch_assoc($result)) return new self($params);
            else return false;
        }

        /**
         * create a revision
         *
         * @param array $settings
         * 
         * @return DataMartRevision
         */
        public static function create($settings)
        {
            $revision = new self($settings);
            return $revision->save();
        }

        /**
         * persist a revision to the database
         * 
         * @throws Exception if the revision can not be saved
         *
         * @return DataMartRevision
         */
        public function save()
        {
            $new_instance = empty($this->id); //check if we are creating a new instance
            if($new_instance) {
                $this->set('created_at', NOW); // set the creation date using PHP time
                $query_string = $this->getInsertQuery();
            }else {
                $query_string = $this->getUpdateQuery();
            }
            if($result = db_query($query_string)) {
                if($id=db_insert_id()) $this->id = $id; // set the revision ID if inserting
                $log_message = ($new_instance===true) ? 'Create Clinical Data Mart revision' : 'Update Clinical Data Mart revision';
                \Logging::logEvent($query_string, "redcap_ehr_datamart_revisions", "MANAGE", $this->project_id, sprintf("revision_id = %u", $this->id), $log_message);
                return self::get($this->id); // get the revision from the database
            }else {
                throw new \Exception("Could not save the revision to the database",1);
            }
        }

        /**
         * set the exectuted_at property of a revision
         *
         * @param string $time
         * @return DataMartRevision
         */
        public function setExecutionTime($time=null)
        {
            $time = $time ? $time : NOW;
            return $this->set('executed_at', $time);
        }

        /**
         * set the request_id property of a revision
         *
         * @param string $request_id
         * @return DataMartRevision
         */
        public function setRequestId($request_id)
        {
            return $this->set('request_id', $request_id);
        }

        /**
         * set the project_id property of a revision
         *
         * @param string $project_id
         * @return DataMartRevision
         */
        public function setProjectId($project_id)
        {
            return $this->set('project_id', $project_id);
        }

        /**
         * approve a revision
         * 
         * @return DataMartRevision
         */
        public function approve()
        {
            $revision = $this->set('approved', true);
            // create empty fields for each mrn in this revision which is not already available in the project
            $results = $revision->createRecords();
            return $revision;
        }

        /**
         * delete the revision
         * defaults to a soft delete
         *
         * @param boolean $soft_delete
         * @throws Exception if no revision can be returned
         * 
         * @return DataMartRevision
         */
        public function delete($soft_delete=true)
        {
            if($soft_delete==true)
            {
                $query_string = sprintf("UPDATE %s SET is_deleted=1 WHERE id=%u", 
                    self::$table_name,
                    db_real_escape_string($this->id)
                );
            }else
            {
                $query_string = sprintf("DELETE FROM %s WHERE id=%u", 
                    self::$table_name,
                    db_real_escape_string($this->id)
                );
            }
            
            // check if query is successful and the $id is valid
            if($result = db_query($query_string))
            {
                \Logging::logEvent($query_string,"redcap_ehr_datamart_revisions","MANAGE",$this->project_id,sprintf("revision_id = %u",$this->id),'Delete Clinical Data Mart revision');

                return true;
            }else
            {
                throw new \Exception("Could't delete the revision from the database",1);
            }
        }

        /**
         * get a query string to UPDATE a Revision on the database 
         *
         * @return string query
         */
        private function getUpdateQuery()
        {
            $db_formatted = $this->toDatabaseFormat();
            $query_string = sprintf("UPDATE %s", db_real_escape_string(self::$table_name));
            $set_fields = array();
            foreach (self::$fillable as $key)
            {
                if(!empty($db_formatted->{$key}))
                    $set_fields[] = sprintf( "%s=%s", $key, $db_formatted->{$key} );
            }
            $query_string .= " SET ".implode(', ', $set_fields);
            $query_string .= sprintf(" WHERE id=%d", db_real_escape_string($this->id));
            return $query_string;
        }

        /**
         * get a query string to INSERT into the database a new Revision
         *
         * @return string query
         */
        private function getInsertQuery()
        {
            $db_formatted = $this->toDatabaseFormat();
            $query_fields = array(
                'user_id' => $db_formatted->user_id,
                'mrns' => $db_formatted->mrns,
                'date_min' => $db_formatted->date_min,
                'date_max' => $db_formatted->date_max,
                'fields' => $db_formatted->fields,
                'approved' => $db_formatted->approved,
                'created_at' => $db_formatted->created_at,
            );
            if($project_id = $db_formatted->project_id) $query_fields['project_id'] = $project_id;
            if($request_id = $db_formatted->request_id) $query_fields['request_id'] = $request_id;
            $keys = array_keys($query_fields);
            $values = array_values($query_fields);

            $query_string = sprintf("INSERT INTO %s", db_real_escape_string(self::$table_name));
            $query_string .= sprintf(" (%s) VALUES (%s)", implode(', ', $keys), implode(', ', $values));
            return $query_string;
        }

        /**
         * get the SELECT query for the revisions
         * select only the revisions that have not been marked as deleted
         *
         * @return string
         */
        private static function getSelectQuery()
        {
            return sprintf("SELECT r.*, t.status AS request_status FROM redcap_ehr_datamart_revisions AS r
                            LEFT JOIN redcap_todo_list AS t ON r.request_id=t.request_id
                            WHERE is_deleted != 1",
                            self::$table_name, self::$request_table_name);
        }

        /**
         * get the ORDER BY clause query for the revisions
         *
         * @return string
         */
        private static function getOrderByQueryClause()
        {
            return "ORDER BY created_at ASC";
        }

        /**
         * get a revision from the database using the request_id
         *
         * @param int $request_id
         * @return DataMartRevision|false
         */
        public static function getRevisionFromRequest($request_id)
        {
            $select_query = self::getSelectQuery();
            $order_by_query_clause = self::getOrderByQueryClause();
            $query_string = sprintf($select_query." AND r.request_id=%u ".$order_by_query_clause, db_real_escape_string(intval($request_id)));

            $result = db_query($query_string);
            if($result && $params=db_fetch_assoc($result)) return new self($params);
            else return false;
        }

        /**
         * get a range of dates compatible with the FHIR specifiction
         *
         * @return void
         */
        public function getFHIRDateRange()
        {
            $date_min = $this->date_min;
            $date_max = $this->date_max;
            // check if $date_max is in the future
            if( !empty($date_max) && $date_max->getTimestamp() >= time() ) $date_max = '';
            if( !empty($date_min) && !empty($date_max) && $date_min > $date_max)
            {
                // If min is bigger than max, then simply swap them
                $temp_max = $date_max;
                $date_max = $date_min;
                $date_min = $temp_max;
            }
            // Reformat dates for temporal window
            if( !empty($date_min) ) $date_min = $date_min->setTime(0, 0, 0)->format(self::FHIR_DATETIME_FORMAT);
            if( !empty($date_max) ) $date_max = $date_max->setTime(23, 59, 59)->format(self::FHIR_DATETIME_FORMAT);

            return array(
                'date_min' => $date_min,
                'date_max' => $date_max,
            );
        }

        /**
         * return an object in a db compatible format
         *
         * @return object
         */
        public function toDatabaseFormat()
        {
            $date_min = ($this->date_min instanceof \DateTime) ? $this->date_min->format(self::$dateTimeFormat) : null;
            $date_max = ($this->date_max instanceof \DateTime) ? $this->date_max->format(self::$dateTimeFormat) : null;
            $executed_at = ($this->executed_at instanceof \DateTime) ? $this->executed_at->format(self::$dateTimeFormat) : null;
            $created_at = ($this->created_at instanceof \DateTime) ? $this->created_at->format(self::$dateTimeFormat) : null;
            
            $db_format = (object) array(
                'id' => db_real_escape_string($this->id),
                'project_id' => db_real_escape_string($this->project_id),
                'request_id' => db_real_escape_string($this->request_id),
                'user_id' => db_real_escape_string($this->user_id),
                'mrns' => checkNull( implode(self::$string_delimiter, $this->mrns)),
                'date_min' => checkNull($date_min),
                'date_max' => checkNull($date_max),
                'fields' => checkNull( implode(self::$string_delimiter, $this->fields)),
                'approved' => db_real_escape_string((int)!!$this->approved),
                'executed_at' => checkNull($executed_at),
                'created_at' => checkNull($created_at),
            );
            // remove null or empty values
            /* foreach ($db_format as $key => $value) {
                if(empty($value)) unset($db_format->{$key});
            } */
            return $db_format;
        }

        /**
         * check if a revision is duplicated
         *
         * @param array $settings
         * @return boolean
         */
        public function isDuplicate($settings)
        {
            $date_min = self::getDate($settings['date_min']);
            $date_max = self::getDate($settings['date_max']);
            $sameSettings = self::compareArrays($this->mrns, $settings['mrns']) &&
                            self::compareArrays($this->fields, $settings['fields']) &&
                            self::compareDates($this->date_min, $date_min) &&
                            self::compareDates($this->date_max, $date_max);
            return $sameSettings;
        }

        /**
         * show if the revision has already been executed
         *
         * @return boolean
         */
        public function hasBeenExecuted()
        {
            return !empty($this->executed_at);
        }

        /**
         * get a list of patient's MRN numbers whose
         * data has been succesfully fetched for
         * the current revision.
         * the list IS user dependent
         *
         * @return array
         */
        public function getFecthedMrnList($user_id)
        {
            $project_mrn_list = $this->getProjectMrnList();
            $revision_creation_date = $this->created_at;
            if(!is_a($revision_creation_date, \DateTime::class)) throw new \Exception("cannot determine the creation date of the revision", 1);
            $project_id = $this->project_id;
            $creation_date = $revision_creation_date->format(DataMartRevision::FHIR_DATETIME_FORMAT);
            $entries = FhirLogsMapper::getLogsAfterDate($creation_date, $project_id, $user_id);
            $fetched_mrns = [];
            foreach ($entries as $entry) {
                $mrn = $entry->mrn;
                $status = $entry->status;
                if(!in_array($mrn, $project_mrn_list)) continue; // skip if not available in project
                if($status!=FhirLogsMapper::STATUS_OK) continue; // skip fetch that resulted in HTTP error
                if(in_array($mrn, $fetched_mrns)) continue; // skip duplicates
                $fetched_mrns[] = $mrn;
            }
            return $fetched_mrns;
        }

        /**
         * get a list of patient's MRNs whose
         * data can be fetched for
         * the current revision.
         * the list IS user dependent 
         *
         * @return array
         */
        public function getFetchableMrnList()
        {
            global $userid;
            $user_id = \User::getUIIDByUsername($userid); // get current user
            $project_id = $this->project_id;
            $fhir_user = new FhirUser($user_id, $project_id);
            $project_mrn_list = $this->getProjectMrnList();
            if($fhir_user->can_repeat_revision) {
                return $project_mrn_list;
            }else {
                $fetched_mrns = $this->getFecthedMrnList($user_id);
                $fetchable_mrn_list = array_diff($project_mrn_list, $fetched_mrns);
                return $fetchable_mrn_list;
            }
        }

        /**
         * return a list of the MRNs stores in the records of the revision's project
         *
         * @return array
         */
        public function getProjectMrnList()
        {
            $query_string = sprintf(
                "SELECT DISTINCT value FROM redcap_data
                WHERE project_id=%u
                AND field_name='mrn'",
                $this->project_id
            );
            $result = db_query($query_string);
            $mrns = array();
            while($row = db_fetch_object($result))
            {
                $mrns[] = $row->value;
            }
            return $mrns;
        }

        /**
         * get the firts available record ID
         *
         * @param integer $project_id
         * @param boolean $useRecordListCache
         * @return string
         */
        private static function getAutoId($project_id=null, $useRecordListCache=true)
        {
            global $user_rights;
            if (!is_numeric($project_id) && defined("PROJECT_ID")) $project_id = PROJECT_ID;
            if (!is_numeric($project_id)) return '';
            $Proj = new \Project($project_id);
            // See if the record list has alrady been cached. If so, use it.
            $recordListCacheStatus = \Records::getRecordListCacheStatus($project_id);
            ## USE RECORD LIST CACHE (if completed)
            if ($useRecordListCache && $recordListCacheStatus == 'COMPLETE') {
                // User is in a DAG, so only pull records from this DAG
                if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
                {
                    $sql = "select distinct(substring(a.record,".(strlen($user_rights['group_id'])+2).")) as record
                            from redcap_record_list a 
                            where a.record like '{$user_rights['group_id']}-%' and a.project_id = " . $project_id;
                }
                // User is not in a DAG
                else {
                    $sql = "select distinct record from redcap_record_list where project_id = " . $project_id;
                }
            }
            ## USE DATA TABLE
            else {
                // User is in a DAG, so only pull records from this DAG
                if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
                {
                    $sql = "select distinct(substring(a.record,".(strlen($user_rights['group_id'])+2).")) as record
                            from redcap_data a left join redcap_data b
                            on a.project_id = b.project_id and a.record = b.record and b.field_name = '__GROUPID__'
                            where a.record like '{$user_rights['group_id']}-%' and a.field_name = '{$Proj->table_pk}'
                            and a.project_id = " . $project_id;
                }
                // User is not in a DAG
                else {
                    $sql = "select distinct record from redcap_data where project_id = " . $project_id . " and field_name = '{$Proj->table_pk}'";
                }
            }
            $recs = db_query($sql);
            //Use query from above and find the largest record id and add 1
            $holder = 0;
            while ($row = db_fetch_assoc($recs))
            {
                if (is_numeric($row['record']) && is_int($row['record'] + 0) && $row['record'] > $holder)
                {
                    $holder = $row['record'];
                }
            }
            db_free_result($recs);
            // Increment the highest value by 1 to get the new value
            $holder++;
            //If user is in a DAG append DAGid+dash to beginning of record
            if (isset($user_rights['group_id']) && $user_rights['group_id'] != "")
            {
                $holder = $user_rights['group_id'] . "-" . $holder;
            }
            // Return new auto id value
            return $holder;
        }

        /**
         * create empty records if the revision contains MRNs
         *
         * @return array results from saved data
         */
        public function createRecords()
        {
            if(count($this->mrns)===0) return;
            $project = new \Project($this->project_id);
            $event_id = $project->firstEventId;
            $project_mrns = $this->getProjectMrnList();
            $results = array();
            foreach ($this->mrns as $mrn) {
                if(!in_array($mrn, $project_mrns))
                {
                    $record_id = self::getAutoId($this->project_id); // get auto record number
                    $record = array('mrn' => $mrn);
                    $events = array($event_id => $record);
                    $data = array($record_id => $events);
                    $result = \REDCap::saveData($this->project_id, 'array', $data);
                    $results[] = $result;
                }
            }
            return $results;
        }

        /**
         * compare 2 arrays
         *
         * @param array $array_a
         * @param array $array_b
         * @return void
         */
        private static function compareArrays($array_a, $array_b)
        {
            sort($array_a);
            sort($array_b);
            return $array_a == $array_b;
        }

        private static function compareDates($date_a, $date_b)
        {
            $date_a = ($date_a instanceof \DateTime) ? $date_a->format(self::$dateTimeFormat) : $date_a;
            $date_b = ($date_b instanceof \DateTime) ? $date_b->format(self::$dateTimeFormat) : $date_b;
            return $date_a === $date_b;
        }

        /**
         * transform a string into a DateTime or in a null value
         *
         * @param string $date_string
         * @return null|\DateTime
         */
        private static function getDate($date_string)
        {
            if(empty($date_string)) return null;
            $time = strtotime($date_string);
            $date_time = new \DateTime();
            $date_time->setTimestamp($time);
            return $date_time;
        }


        /**
         * get info about the user who created the revision
         */
        public function getCreator()
        {
            return new FhirUser($this->user_id, $this->project_id);
        }

        /**
         * get the data of the revision
         *
         * @return array
         */
        public function getData()
        {
            return array(
                'mrns' => $this->mrns,
                'fields' => $this->fields,
                'dateMin' => ($this->date_min instanceof \DateTime) ? $this->date_min->format(self::$dateTimeFormat) : '',
                'dateMax' => ($this->date_max instanceof \DateTime) ? $this->date_max->format(self::$dateTimeFormat) : '',
            );
        }

        /**
         * magic getter for properties specified in self::$readable_variables
         *
         * @param string $name
         * @return void
         */
        public function __get($property)
        {
            if (property_exists($this, $property) && in_array($property, self::$readable_variables)) {
                return $this->$property;
            }

            $trace = debug_backtrace();
            trigger_error(
                'Undefined property via __get(): ' . $property .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE);
            return null;
        }

        /**
         * setter for instance properties
         * helps to set the right format for dates, arrays and booleans
         *
         * @param string|array $property
         * @param mixed $value
         * @return DataMartRevision
         */
        private function set($property, $value=null)
        {
            if(!property_exists($this, $property)) return $this;
            switch ($property) {
                case 'mrns':
                case 'fields':
                    $list = $value;
                    // convert string to array
                    if(!is_array($list))
                    {
                        $text = trim($value);
                        if(strlen($text)===0)
                        {
                            // empry array if string is empty
                            $list = array();
                        }else {
                            $list = explode(self::$string_delimiter, $text);
                        }
                    }
                    $list = array_unique($list, SORT_STRING); // discard duplicates
                    $this->{$property} = $list;
                    break;
                case 'date_min':
                case 'date_max':
                case 'executed_at':
                case 'created_at':
                    if (is_a($value, \DateTime::class))
                    {
                        $this->{$property} = $value; // assign it if it is a DateTime
                    }else
                    {
                        $this->{$property} = self::getDate($value);
                    }
                    break;
                case 'approved':
                    $this->{$property} =  boolval($value); //convert to boolean
                    break;
                default:
                    $this->{$property} = $value;
                    break;
            }
            return $this;
        }

        /**
         * get the date range for an MRN
         *
         * @param string $mrn
         * @return void
         */
        public function getDateRangeForMrn($mrn)
        {
            $project_id = $this->project_id;
            $record_date_range = $this->getRecordDateRange($project_id, $mrn);
            // priority to record date range
            if(!empty($record_date_range))
            {
                // use the date range specified in the instrument 'Project Settings' if available
                $date_min = $record_date_range['date_min'];
                $date_max = $record_date_range['date_max'];
                return compact('date_min', 'date_max');
            }
            // use the revision date range if no date range has benn specified in the 'Project Settings' instrument 
            $revision_date_range = $this->getFHIRDateRange(); // get a date range compatible with the FHIR specification
            $date_min = $revision_date_range['date_min'];
            $date_max = $revision_date_range['date_max'];
            return compact('date_min', 'date_max');
        }

        /**
         * get records settings for the specified MRN
         *
         * @param integer $project_id
         * @param string $mrn
         * @return array|false return an array of settings or false if no settings ar found
         */
        private function getRecordSettings($project_id, $mrn)
        {
            $query_string = sprintf("SELECT
                            redcap_data.record, redcap_data.value AS mrn,
                            data0.value AS date_min,
                            data1.value AS date_max
                            FROM redcap_data
                            LEFT JOIN redcap_data AS data0 ON redcap_data.project_id=data0.project_id AND redcap_data.record=data0.record AND redcap_data.event_id=data0.event_id AND data0.field_name='date_min' 
                            LEFT JOIN redcap_data AS data1 ON redcap_data.project_id=data1.project_id AND redcap_data.record=data1.record AND redcap_data.event_id=data1.event_id AND data1.field_name='date_max'                     
                            WHERE redcap_data.project_id=%u
                            AND redcap_data.field_name='mrn'
                            AND redcap_data.value='%s'
                ",
                db_real_escape_string($project_id),
                db_real_escape_string($mrn)
            );
            $query = db_query($query_string);
            $result = db_fetch_assoc($query);
            if($result) return $result;
            return false;
        }

        /**
         * return a date range as specified in the record instrument 'Project Settings'
         * return false if no date has ben specified
         *
         * @param integer $project_id
         * @param string $mrn
         * @return array (date_min, date_max)
         */
        private function getRecordDateRange($project_id, $mrn)
        {
            $record_settings = $this->getRecordSettings($project_id, $mrn);
            if(!$record_settings) return false;
            $date_min = $record_settings['date_min'] ? $record_settings['date_min'] : '';
            $date_max = $record_settings['date_max'] ? $record_settings['date_max'] : '';
            if(!empty($date_min)) $date_min = $this->getFhirDate($date_min);
            if(!empty($date_max)) $date_max = $this->getFhirDate($date_max);
            if(empty($date_min) && empty($date_max)) return false;
            return compact('date_min', 'date_max');
        }

        /**
         * get a datetime compatible with the FHIR standard
         *
         * @param string $date_string
         * @return string
         */
        private function getFhirDate($date_string)
        {
            $datetime = new \DateTime($date_string);
            return $datetime->format(self::FHIR_DATETIME_FORMAT);
        }

        /**
        * Returns data which can be serialized
        * this format is used in the client javascript app
        *
        * @return array
        */
        public function jsonSerialize()
        {
            $serialized = array(
                'metadata' => array(
                    'id' => $this->id,
                    'request_id' => $this->request_id,
                    'request_status' => $this->request_status,
                    'date' => $this->created_at->format(self::$dateTimeFormat),
                    // 'date' => ($this->created_at instanceof DateTime) ? $this->created_at->format(self::$dateTimeFormat) : '',
                    'executed' => $this->hasBeenExecuted(),
                    'executed_at' => ($this->executed_at instanceof \DateTime) ? $this->executed_at->format(self::$dateTimeFormat) : '',
                    'approved' => boolval($this->approved),
                    'creator' => $this->getCreator(),
                    'project_mrns' => $this->getProjectMrnList(),
                    'fetchable_mrns' => $this->getFetchableMrnList(),
                ),
                'data' => $this->getData(),
            );
            return $serialized;
        }

        /**
         * print a DataMart Revision as a string
         *
         * @return string
         */
        public function __toString()
        {
            $string = '';
            $string .= $this->id;
            return $string;
        }
        
    }
}