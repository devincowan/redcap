<?php
include_once(__DIR__.'/BladeOne/BladeOne.php');
use eftec\bladeone\BladeOne;

/**
 * helper class to get a blade instance
 * @method static Renderer run(string $path, array $variables)
 */
 class Renderer
{

    /**
     * get an instance of the blade template engine
     * @see https://github.com/EFTEC/BladeOne
     *
     * @return BladeOne
     */
    public static function getBlade($templatePath = null, $compiledPath = null, $mode = 0)
    {
        if(!isset($templatePath)) $templatePath =  APP_PATH_VIEWS . 'blade';
        if(!isset($compiledPath)) $compiledPath =  APP_PATH_TEMP . 'cache';
        // crete the cache directory if does not exists
        if (!file_exists($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }
        $blade = new BladeOne($templatePath,$compiledPath,BladeOne::MODE_AUTO);
        return $blade;
    }

    /**
     * use static methods with the blade instance 
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    public static function __callStatic($method, $params=array())
    {
        // Note: value of $name is case sensitive.
        $blade = self::getBlade();
        if(!method_exists($blade, $method)) return;
        return call_user_func_array( array($blade, $method), $params );
    }






}