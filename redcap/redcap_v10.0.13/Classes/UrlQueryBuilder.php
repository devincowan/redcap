<?php
namespace
{
    class UrlQueryBuilder
    {
        /**
         * the query parameters in a GET request can have multiple formats.
         * in FHIR we usually use only comma and repeat.
         * the 'repeat' format appears to be the most compatible
         * 
         */
        const QUERY_ARRAY_FORMAT_REPEAT = 'repeat'; // used for AND logic (dates)
        const QUERY_ARRAY_FORMAT_COMMA = 'comma'; // used for OR logic (codes, status)
        const QUERY_ARRAY_FORMAT_BRACKETS = 'brackets';
        const QUERY_ARRAY_FORMAT_INDICES = 'indices';

        public static $array_formats = array(
            self::QUERY_ARRAY_FORMAT_REPEAT,
            self::QUERY_ARRAY_FORMAT_COMMA,
            self::QUERY_ARRAY_FORMAT_BRACKETS,
            self::QUERY_ARRAY_FORMAT_INDICES,
        );

        /**
         * collect the queries for the current builder
         *
         * @var UrlQueryBuilder\Query[] list of queries
         */
        private $queries = array();

        public function __construct()
        {

        }

        /**
         * get the full query string
         *
         * @param string $separator
         * @return string
         */
        public function get($separator='&')
        {
            $query_strings = array();
            foreach ($this->queries as $query) {
                $query_string = $query->get();
                if(!empty($query_string)) $query_strings[] = $query_string;
            }
            return implode($separator, $query_strings);
        }

        /**
         * create a query string using a list of parameters
         *
         * @param array $parameters list of parameters containing $key, $value and $array_format (UrlQueryBuilder\Query)
         * @return string 
         */
        public static function build($parameters)
        {
            $builder = new self();
            $array_formats = \UrlQueryBuilder::$array_formats;
            foreach ($parameters as $settings) {
                list($key, $value, $array_format) = $settings;
                $array_format = in_array($array_format, $array_formats) ? $array_format : \UrlQueryBuilder::QUERY_ARRAY_FORMAT_COMMA; // default to comma separated (OR in FHIR)
                $builder->where($key, $value, $array_format);
            }
            $query_string = $builder->get(); // join all params
            return $query_string;
        }

        public function where($key, $value, $array_format=self::QUERY_ARRAY_FORMAT_COMMA)
        {
            $this->queries[] = new UrlQueryBuilder\Query($key, $value, $array_format);
            return $this; // return the instance so the method can be chained
        }

    }
}
namespace UrlQueryBuilder
{
    class Query
    {

        private $key;
        private $value;
        private $array_format;

        public function __construct($key, $value, $array_format=null)
        {
            $this->key = $key;
            $this->value = $value;
            $this->array_format = $array_format;
        }

        public function get()
        {
            if(!is_array($this->value))
            {
                return $this->getQueryStringSingle();
            }else
            {
                return $this->getQueryStringMultiple();
            }
        }

        /**
         * get a classic query string portion
         * with both $key and $value being a string (single value for a key)
         *
         * @return string
         */
        private function getQueryStringSingle()
        {
            if(is_array($this->value)) throw new \Exception("Value cannot be an array", 1);
            return "{$this->key}={$this->value}";
        }
        
        /**
         * get a query string for array values (multiple values for the same key)
         * suported formats are 'comma', 'repeat', 'brackets' and 'indices'
         * FHIR only uses 'repeat' for AND logic and 'comma' for OR logic
         *
         * @param string $key
         * @param string $value
         * @param string $array_format
         * @return string
         */
        public function getQueryStringMultiple()
        {
            if(!is_array($this->value)) throw new \Exception("Value must be an array", 1);

            $query_string = '';
            switch ($this->array_format) {
                case \UrlQueryBuilder::QUERY_ARRAY_FORMAT_COMMA:
                    // trasform array in comma separated values (OR logic in FHIR)
                    $joined_value = implode(',', $this->value);
                    $query_string = "{$this->key}=$joined_value";
                    break;
                case \UrlQueryBuilder::QUERY_ARRAY_FORMAT_REPEAT:
                    // same key repeated with different values (AND logic in FHIR)
                    $query_strings = array();
                    foreach ($this->value as $sub_value) $query_strings[] = "{$this->key}={$sub_value}";
                    $query_string = implode('&', $query_strings);
                    break;
                case \UrlQueryBuilder::QUERY_ARRAY_FORMAT_BRACKETS:
                    // 2020-01-28 TODO. not needed for now
                    $query_strings = array();
                    foreach ($this->value as $sub_value) $query_strings[] = "{$this->key}[]={$sub_value}";
                    $query_string = implode('&', $query_strings);
                case \UrlQueryBuilder::QUERY_ARRAY_FORMAT_INDICES:
                    // 2020-01-28 TODO. not needed for now
                    $query_strings = array();
                    $index = 0;
                    foreach ($this->value as $sub_value) $query_strings[] = sprintf("{$this->key}[%d]={$sub_value}", $index++);
                    $query_string = implode('&', $query_strings);
                default:
                    break;
            }
            return $query_string;
        }
    }
}