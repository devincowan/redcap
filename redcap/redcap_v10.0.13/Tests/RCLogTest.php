<?php
require_once __DIR__ . '/REDCapTestCase.php';

class RCLogTest extends REDCapTestCase
{
	function testSysConfig(){
		$projectId = rand();
		$tableName = 'unit_test_dummy_table';
		$sql = "UPDATE $tableName SET fieldOne = 'value one', fieldTwo = 'value two' WHERE project_id = $projectId";

		$logEventId = RCLog::sysConfig($sql);
		$event = Logging::getEventById($logEventId);

		$this->assertSame($sql, $event['sql_log']);
		$this->assertSame($tableName, $event['object_type']);
		$this->assertSame(RCLog::EVENT_MANAGE, $event['event']);
		$this->assertSame(null, $event['pk']);
		$this->assertSame("fieldOne = 'value one',\nfieldTwo = 'value two'", $event['data_values']);
		$this->assertSame(RCLog::DESC_SYSCON, $event['description']);
	}

	function testModifyProject(){
		$projectId = rand();
		$tableName = 'unit_test_dummy_table';
		$sql = "UPDATE $tableName SET fieldOne = 'value one' WHERE project_id = $projectId";

		$logEventId = RCLog::modifyProject($sql);
		$event = Logging::getEventById($logEventId);

		$this->assertSame($sql, $event['sql_log']);
		$this->assertSame($tableName, $event['object_type']);
		$this->assertSame(RCLog::EVENT_MANAGE, $event['event']);
		$this->assertSame("$projectId", $event['pk']);
		$this->assertSame("project_id = $projectId", $event['data_values']);
		$this->assertSame(RCLog::DESC_MODPROJ, $event['description']);
	}
}