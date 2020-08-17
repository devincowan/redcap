<?php
define('PLUGIN', true);
require_once __DIR__ . '/../../redcap_connect.php';
abstract class REDCapTestCase extends PHPUnit\Framework\TestCase
{
    function setUp(){
        // The database connection seems to get closed between unit tests for some reason...
        db_connect();
    }
}