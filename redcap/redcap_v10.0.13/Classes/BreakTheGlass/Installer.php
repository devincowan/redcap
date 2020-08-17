<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass
{

    use RedCapDB;
    use Vanderbilt\REDCap\Classes\BreakTheGlass\Settings;

    /**
     * container object for the list of the patients that need to be processed
     * with the "break the glass" methods
     */
    class Installer
    {
        /**
         * configuration
         * 
         * configuration name => default value
         *
         * @var array
         */
        private static $default_settings = array(
            'fhir_break_the_glass_enabled' => GlassBreaker::AUTHORIZATION_MODE_ACCESS_TOKEN,
            'fhir_break_the_glass_token_usertype' => GlassBreaker::USER_INTERNAL,
            'fhir_break_the_glass_token_username' => '',
            'fhir_break_the_glass_token_password' => '',
            'fhir_break_the_glass_username_token_base_url' => '',
        );

        /**
         * get REDCap configuration settings
         *
         * @return array field_name => value
         */
        public function getREDCapSettings()
        {
            $query_string = "SELECT * FROM redcap_config";
            $result = db_query($query_string);
            $settings = array();
            while ($row=db_fetch_assoc($result)) {
                // field_name, value
                $settings[$row['field_name']] = $row['value'];
            }
            return $settings;
        }

        public function isInstalled()
        {
            $settings = $this->getREDCapSettings();
            // check for the keys already stored in REDCap
            $intersection = array_intersect_key(self::$default_settings, $settings);
            // if the amount is different then the GlassBreaker is not properly installed
            $total_missing_keys = count(self::$default_settings)-count($intersection);
            return $total_missing_keys==0;
        }

        /**
         * set default values in REDCap configuration table
         * check if the values are available first
         *
         * @return void
         */
        public function install()
        {
            $settings = $this->getREDCapSettings();
            $values_list = array();
            foreach (self::$default_settings as $key => $value) {
                if( array_key_exists($key, $settings)) continue;
                $values_list[] = "('$key', '$value')";
            }
            $values_string = implode(', ', $values_list);
            $query_string = sprintf("INSERT INTO redcap_config (`field_name`, `value`) VALUES %s", $values_string);
            $result = db_query($query_string);
            return $result;
        }

        public function uninstall()
        {
            $formatted_keys = array_map(function($key) {
                return "`$key`";
            }, array_keys(self::$default_settings));
            $keys_to_delete = implode(', ', $formatted_keys);
            $query_string = sprintf("DELETE FROM redcap_config WHERE field_name IN (%s)", $keys_to_delete);
            $result = db_query($query_string);
            return $result;
        }

    }
}