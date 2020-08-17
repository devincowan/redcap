<?php
namespace Vanderbilt\REDCap\Classes\Fhir
{
    class FhirLogsMapper
    {

        /**
         * date format as used in the database
         */
        const DATE_FORMAT = "Y-m-d\TH:i:s\Z";

        /**
         * table where the logs are stored
         *
         * @var string
         */
        const TABLE_NAME = 'redcap_ehr_fhir_logs';

        /**
         * fields as stored on the database
         *
         * @var array
         */
        private static $table_fields = array(
            'user_id',
            'fhir_id',
            'mrn',
            'project_id',
            'resource_type',
            'status',
            'created_at',
       );

        /**
         * ID of the user using the FHIR endpoint
         *
         * @var integer
         */
        private $user_id;

        /**
         * FHIR identifier of the patient
         *
         * @var string
         */
        private $fhir_id;

        /**
         * Medical record number of the patient
         *
         * @var string
         */
        private $mrn;

        /**
         * ID of the project where the data has been pulled
         *
         * @var string
         */
        private $project_id;

        /**
         * FHIR endpoint
         *
         * @var string
         */
        private $resource_type;

        /**
         * status of the interaction (successful or not)
         *
         * @var integer
         */
        private $status;

        /**
         * status for data fetched without HTTP errors
         */
        const STATUS_OK = 200;

        /**
         * date and time of the interaction
         *
         * @var \DateTime
         */
        private $created_at;

        /**
         * create a FHIR log
         *
         * @param integer $user_id
         * @param string $fhir_id
         * @param string $resource_type
         * @param string $status
         * @param \DateTime $created_at
         */
        public function __construct($params)
        {
            $fields = self::$table_fields;
            foreach ($fields as $field) {
                // check if the property exists
                if(!property_exists($this, $field)) continue;
                // check if the property has been provided as parameter
                if(!array_key_exists($field, $params)) continue;
                $value = $params[$field];
                $this->{$field} = $value;
            }
        }

        /**
         * save on the database
         *
         * @return FhirLogsMapper
         */
        private function save()
        {
            // helper function to enclose string in quotes
            $add_quotes = function($string) {
                return implode(array("'", $string, "'"));
            };
            $query_fields = array(
                 'user_id' => checknull($this->user_id),
                 'fhir_id' => $add_quotes($this->fhir_id),
                 'mrn' => $add_quotes($this->mrn),
                 'project_id' => $this->project_id,
                 'resource_type' => $add_quotes($this->resource_type),
                 'status' => $add_quotes($this->status),
                 'created_at' => $add_quotes($this->created_at),
            );
            $keys = array_keys($query_fields);
            $values = array_values($query_fields);
            $query_string = sprintf("INSERT INTO %s", db_real_escape_string(self::TABLE_NAME));
            $query_string .= sprintf(" (%s) VALUES (%s)", implode(', ', $keys), implode(', ', $values));
            $result = db_query($query_string);
            if(!$result)
            {
                throw new \Exception("Error saving FHIR logs on the database", 1);
            }

            $id = db_insert_id();
            return self::get($id);
        }

        /**
         * get an instance of the log
         *
         * @param integer $id ID of the log on the database
         * @return FhirLogsMapper
         */
        public static function get($id)
        {
            $query_string = sprintf(
                "SELECT * FROM %s
                WHERE id=%u", self::TABLE_NAME, $id
            );
            $result = db_query($query_string);
            $instance = null;
            if($result && $row=db_fetch_assoc($result))
            {
                $params = array();
                foreach (self::$table_fields as $field) {
                    if($value = ($row[$field])) $params[$field] = $value;
                }
                $instance = new self($params);
            }
            return $instance;
        }

        /**
         * get all logs occourred after a specified date
         * useful to find out when data has been pulled for a user
         *
         * @param \DateTime $date_time
         * @param integer $project_id
         * @param integer $user_id
         * @param string $mrn
         * @return FhirLogsMapper[]
         */
        public static function getLogsAfterDate($date_time, $project_id, $user_id=null, $mrn=null)
        {
            // convert datetime to string
            if(is_a($date_time, \DateTime::class)) $date_time = $date_time->format(self::DATE_FORMAT);
            $query_string = sprintf(
                "SELECT * FROM %s
                WHERE created_at>='%s'
                AND project_id=%u",
                self::TABLE_NAME,
                db_real_escape_string($date_time),
                db_real_escape_string($project_id)
            );
            // select only specified MRN if provided
            if($user_id) $query_string .= sprintf(" AND user_id=%u", db_real_escape_string($user_id));
            if($mrn) $query_string .= sprintf(" AND mrn='%s'", db_real_escape_string($mrn));
            $query_string .= " ORDER BY created_at DESC";
            $result = db_query($query_string);
            $list = array();
            while ($row=db_fetch_assoc($result)) {
                $list[] = new self($row);
            }
            return $list;
        }

        /**
         * store a log on the database
         *
         * @param array $params
         * @param \DateTime $created_at
         * @return FhirLogsMapper
         */
        public static function log($params)
        {
            $instance = new self($params);
            return $instance->save();
        }

         /**
         * magic getter for private properties
         *
         * @param string $name
         * @return void
         */
        public function __get($name)
        {
            $fields = self:: $table_fields;
            if (property_exists($this, $name) && in_array($name, $fields))
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
}