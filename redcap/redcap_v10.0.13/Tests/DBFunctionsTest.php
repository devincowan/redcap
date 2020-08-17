<?php

use ExternalModules\ExternalModules;

require_once APP_PATH_DOCROOT . '/Tests/REDCapTestCase.php';

class DBFunctionsTest extends REDCapTestCase
{
    function test_db_fetch_functions(){
        $this->assertFetchFunction('db_fetch_row', MYSQLI_NUM);
        $this->assertFetchFunction('db_fetch_assoc', MYSQLI_ASSOC);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_NUM);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_ASSOC);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_BOTH);
    }

    private function assertFetchFunction($functionName, $resultType){
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName, $resultType){
            $row = $functionName($result, $resultType);

            if($resultType !== MYSQLI_ASSOC){
                $this->assertSame($value, $row[0]);
            }

            if($resultType !== MYSQLI_NUM){
                $this->assertSame($value, $row[$columnName]);
            }

            $expectedFieldCount = 1;
            if($resultType === MYSQLI_BOTH){
                $expectedFieldCount = 2;
            }
            $this->assertSame($expectedFieldCount, count($row));

            $this->assertNull($functionName($result));
        });
    }

    function test_db_free_result(){
        $functionName = 'db_free_result';
        
        $this->getTestResult($functionName, function($result) use ($functionName){
            $functionName($result);
        });
    }

    function test_db_fetch_fields(){
        $functionName = 'db_fetch_fields';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $fields = $functionName($result);
            $this->assertSame($columnName, $fields[0]->name);
        });
    }

    function test_db_result(){
        $functionName = 'db_result';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $actualValue = $functionName($result, 0, $columnName);
            $this->assertSame($actualValue, $value);
        });
    }

    function test_db_field_name(){
        $functionName = 'db_field_name';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertSame($columnName, $functionName($result, 0));
        });
    }

    function test_db_fetch_object(){
        $functionName = 'db_fetch_object';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $expected = new stdClass;
            $expected->$columnName = $value;

            $actual = $functionName($result);

            $this->assertEquals($expected, $actual);
            $this->assertNull($functionName($result));
        });
    }

    function test_db_num_fields(){
        $functionName = 'db_num_fields';

        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertEquals(1, $functionName($result));
        });
    }

    function test_db_num_rows(){
        $functionName = 'db_num_rows';

        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertEquals(1, $functionName($result));
        });
    }

    private function getTestResult($functionName, $action){
        $value = rand();
        $columnName = 'a';

        // MySQLi result object
        $result = db_query("select $value as $columnName");
        $action($result, (string) $value, $columnName);
        
        // // ExternalModules\StatementResult object
        $result = ExternalModules::query("select ? as $columnName", $value);
        $action($result, $value, $columnName);

        $expectedReturnValue = false;
        if(
            $functionName === 'db_field_name'
            ||
            PHP_MAJOR_VERSION === 5 && $functionName !== 'db_result'
        ){
            $expectedReturnValue = null;
        }

        // Closed MySQLi result object
        $result = db_query("select 1");
        $result->close();
        $this->assertSame($expectedReturnValue, $functionName($result, null));

        // Closed ExternalModules\StatementResult object
        $result = ExternalModules::query("select ?", 1);
        $result->close();
        $this->assertSame($expectedReturnValue, $functionName($result, null));

        $expectedReturnValue = null;
        if(in_array($functionName, ['db_result'])){
            $expectedReturnValue = false;
        }

        // Some other object
        $this->assertSame($expectedReturnValue, $functionName(new stdClass, null));

        // Null
        $this->assertSame($expectedReturnValue, $functionName(null, null));
    }
}
