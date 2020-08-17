<?php
namespace {
    /**
     * Bridge to map a field in a resource
     */
    class FhirResourceMapper 
    {
        private function getPathRegExp($path)
        {
            /**
             * helper function:
             * - transform numeric values in digit matcher
             * - quote regular expression special characters
             */
            $parsePathElements = function($item) {
                if(is_numeric($item)) return '\d+';
                return preg_quote($item, '/');
            };

            $mapping = array_map($parsePathElements, $path);
            $path_string = implode(':', $mapping);
            $regExp = sprintf("/%s/", $path_string);
            return $regExp;
        }

        /**
         * Undocumented function
         *
         * @param FhirResource $resource
         * @param array $path
         * @return void
         */
        public function getResourceValueAtPath($resource, $path)
        {
            if(!method_exists ($resource , 'flatten'))
            {
                throw new Exception("Error Processing Request; resource cannot be flattened", 1);
            }

            $flattened = $resource->flatten();
            $regExp = $this->getPathRegExp($path);

            $results = array();
            foreach ($flattened as $path => $value) {
                preg_match($regExp, $path, $matches);
                if(!empty($matches))
                {
                    $results[$path] = $value;
                }
            }

            return implode(' ', $results);
        }

        public function resourceHasValueAtPath($resource, $path, $value)
        {
            $path = explode(':', $path);
            if(!method_exists ($resource , 'flatten'))
            {
                throw new Exception("Error Processing Request; resource cannot be flattened", 1);
            }

            $flattened = $resource->flatten();
            $regExp = $this->getPathRegExp($path);
            $found = false;
            foreach ($flattened as $path => $path_value) {
                preg_match($regExp, $path, $matches);
                if(!empty($matches))
                {
                    $found = strcasecmp($value, $path_value)===0;
                    if($found) return true;
                }
            }
            return false;
        }
        
    }
}