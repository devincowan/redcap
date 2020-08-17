<?php

namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use Vanderbilt\REDCap\Classes\BreakTheGlass\Basket;
use Vanderbilt\REDCap\Classes\BreakTheGlass\Settings;

/**
 * Facade that returns an instance of the GlassBreaker using settings from REDCap
 */
class Facade
{

    protected static $instance;

    /**
     * get an instance on the Glass Breaker with settings
     * based on the global REDCap variables
     *
     * @return GlassBreaker
     */
    public static function getInstance()
    {
        if(is_a(self::$instance, GlassBreaker::class)) return self::$instance;
        $settings = self::default_settings();
        self::$instance = new GlassBreaker($settings);
        return self::$instance;
    }

    /**
     * settings based on the global REDCap variables
     *
     * @return array
     */
    public static function default_settings()
    {
        global  $userid,
            $fhir_endpoint_base_url,
            $fhir_client_id,
            $fhir_break_the_glass_enabled,
            $fhir_break_the_glass_token_usertype,
            $fhir_break_the_glass_token_username,
            $fhir_break_the_glass_token_password,
            $fhir_break_the_glass_username_token_base_url;
        $settings = array(
            'authorization_mode' => $fhir_break_the_glass_enabled,
            'redcap_userid' => $userid,
            'fhir_client_id' => $fhir_client_id,
            'username_token_usertype' => $fhir_break_the_glass_token_usertype,
            'username_token_username' => $fhir_break_the_glass_token_username,
            'username_token_password' => $fhir_break_the_glass_token_password,
            'username_token_base_url' => $fhir_break_the_glass_username_token_base_url,
            'access_token' => null,
            'username_token' => null,
            'fhir_endpoint_base_url' => $fhir_endpoint_base_url,
        );
        return $settings;
    }

    public function __call($name, $args)
    {
        $instance = self::getInstance();
        if(!method_exists($instance, $name)) throw new \Exception("Method not available", 1);
        return call_user_func_array(array($instance, $name), $args);
    }

    public static function __callStatic($name, $arguments)
    {
        $class_name = __NAMESPACE__ ."\\".GlassBreaker::class;
        if(!method_exists($class_name, $name)) throw new \Exception("Method not available", 1);
        return call_user_func_array(array($class_name, $name), $arguments);
    }


}