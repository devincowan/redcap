<?php
namespace ExternalModules;

use DateTime;

abstract class FrameworkBaseTest extends BaseTest
{
	function __construct(){
		parent::__construct();

		preg_match('/[0-9]+/', get_class($this), $matches);
		$this->frameworkVersion = (int) $matches[0];
	}

	protected function getReflectionClass()
	{
		return $this->getFramework();
	}

	function getFrameworkVersion(){
		return $this->frameworkVersion;
	}

	function testQuery_noParameters(){
		$value = (string)rand();
		$result = $this->query("select $value", []);
		$row = $result->fetch_row();
		$this->assertSame($value, $row[0]);

		$frameworkVersion = $this->getFrameworkVersion();
		if($frameworkVersion < 4){
			$value = (string)rand();
			$result = $this->query("select $value");
			$row = $result->fetch_row();
			$this->assertSame($value, $row[0]);	
		}
		else{
			$this->assertThrowsException((function(){
				$this->query("select 1");
			}), ExternalModules::tt('em_errors_117'));
		}
	}

	function testQuery_trueReturnForDatalessQueries(){
		$r = $this->query('update redcap_ip_banned set time_of_ban=now() where ?=?', [1,2]);
        $this->assertTrue($r);
	}

	function testQuery_invalidQuery(){
		$this->assertThrowsException(function(){
			ob_start();
			$this->query("select * from ??", ['some_table_that_doesnt_exist']);
		}, ExternalModules::tt("em_errors_29"));

		ob_end_clean();
	}

	function testQuery_paramTypes(){
		$dateTimeString = '2001-02-03 04:05:06';

		$values = [
			true,
			2,
			3.3,
			'four',
			null,
			new DateTime($dateTimeString)
		];

		$row = $this->query('select ?, ?, ?, ?, ?, ?', $values)->fetch_row();

		$values[0] = 1; // The boolean 'true' will get converted to the integer '1'.  This is excepted.
		$values[5] = $dateTimeString;

		$this->assertSame($values, $row);
	}

	function testQuery_invalidParamType(){
		$this->assertThrowsException(function(){
			ob_start();
			$invalidParam = new \stdClass();
			$this->query("select ?", [$invalidParam]);
		}, ExternalModules::tt('em_errors_109'));

		ob_end_clean();
	}
	
	function testQuery_singleParam(){
		$value = rand();
		$row = $this->query('select ?', $value)->fetch_row();
		$this->assertSame($value, $row[0]);
	}

	function testGetSubSettings_complexNesting()
	{
		if($this->getFrameworkVersion() === 1){
			// This test is intended for newer framework versions only.
			return;
		}

		$m = $this->getInstance();
		$_GET['pid'] = TEST_SETTING_PID;

		// This json file can be copied into a module for hands on testing/modification via the settings dialog.
		$this->setConfig(json_decode(file_get_contents(__DIR__ . '/complex-nested-settings.json'), true));

		// These values were copied directly from the database after saving them through the settings dialog (as configured by the json file above).
		$m->setProjectSetting('countries', ["true","true"]);
		$m->setProjectSetting('country-name', ["USA","Canada"]);
		$m->setProjectSetting('states', [["true","true"],["true"]]);
		$m->setProjectSetting('state-name', [["Tennessee","Alabama"],["Ontario"]]);
		$m->setProjectSetting('cities', [[["true","true"],["true"]],[["true"]]]);
		$m->setProjectSetting('city-name', [[["Nashville","Franklin"],["Huntsville"]],[["Toronto"]]]);
		$m->setProjectSetting('city-size', [[["large","small"],["medium"]],[[null]]]); // The null is an important scenario to test here, as it can change output behavior.

		$expectedCountries = [
			[
				"states" => [
					[
						"state-name" => "Tennessee",
						"cities" => [
							[
								"city-name" => "Nashville",
								"city-size" => "large"
							],
							[
								"city-name" => "Franklin",
								"city-size" => "small"
							]
						]
					],
					[
						"state-name" => "Alabama",
						"cities" => [
							[
								"city-name" => "Huntsville",
								"city-size" => "medium"
							]
						]
					]
				],
				"country-name" => "USA"
			],
			[
				"states" => [
					[
						"state-name" => "Ontario",
						"cities" => [
							[
								"city-name" => "Toronto",
								"city-size" => null
							]
						]
					]
				],
				"country-name" => "Canada"
			]
		];

		// Call the new implementation on the framework object directly.
		// The old implementation was available via the module instance directly until v5,
		// and it does NOT support complex nesting correctly.
		$this->assertEquals($expectedCountries, $this->getFramework()->getSubSettings('countries'));
	}

	function testGetSubSettings_plainOldRepeatableInsideSubSettings(){
		$m = $this->getInstance();
		$_GET['pid'] = TEST_SETTING_PID;

		$this->setConfig('
			{
				"project-settings": [
					{
						"key": "one",
						"name": "one",
						"type": "sub_settings",
						"repeatable": true,
						"sub_settings": [
							{
								"key": "two",
								"name": "two",
								"type": "text",
								"repeatable": true
							}
						]
					}
				]
			}
		');

		$m->setProjectSetting('one', ["true"]);
		$m->setProjectSetting('two', [["value"]]);

		$this->assertEquals(
			[
				[
					'two' => [
						'value'
					]
				]
			],
			$this->getSubSettings('one')
		);
	}

	function testGetProjectsWithModuleEnabled(){
		$assert = function($enableValue, $expectedPids){
			$m = $this->getInstance();
			$m->setProjectSetting(ExternalModules::KEY_ENABLED, $enableValue, TEST_SETTING_PID);
			$pids = $this->getProjectsWithModuleEnabled();
			$this->assertSame($expectedPids, $pids);
		};

		$assert(true, [TEST_SETTING_PID]);
		$assert(false, []);
	}

	function testProject_getUsers(){
		$result = $this->getFramework()->query("
			select user_email
			from redcap_user_rights r
			join redcap_user_information i
				on r.username = i.username
			where project_id = ?
			order by r.username
		", TEST_SETTING_PID);

		$actualUsers = $this->getProject(TEST_SETTING_PID)->getUsers();

		$i = 0;
		while($row = $result->fetch_assoc()){
			$this->assertSame($row['user_email'], $actualUsers[$i]->getEmail());
			$i++;
		}
	}

	function testRecords_lock(){
		$_GET['pid'] = TEST_SETTING_PID;
		$recordIds = [1, 2];
		$records = $this->getFramework()->records;
		
		foreach($recordIds as $recordId){
			$this->ensureRecordExists($recordId);
		}

		$records->lock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertTrue($records->isLocked($recordId));
		}

		$records->unlock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertFalse($records->isLocked($recordId));
		}
	}

	function testUser_isSuperUser(){
		$result = ExternalModules::query('select username from redcap_user_information where super_user = 1 limit 1', []);
		$row = $result->fetch_assoc();
		$username = $row['username'];
		
		$user = $this->getUser($username);
		$this->assertTrue($user->isSuperUser());
	}

	function testUser_getRights(){
		$result = ExternalModules::query("
			select * from redcap_user_rights
			where username != ''
			order by rand() limit 1
		", []);

		$row = $result->fetch_assoc();
		$projectId = $row['project_id'];
		$username = $row['username'];
		$expectedRights = \UserRights::getPrivileges($projectId, $username)[$projectId][$username];

		$user = $this->getUser($username);
		
		$actualRights = $user->getRights($projectId, $username);
		$this->assertSame($expectedRights, $actualRights);

		$_GET['pid'] = $projectId;
		$actualRights = $user->getRights(null, $username);
		$this->assertSame($expectedRights, $actualRights);
	}
	
	function testGetEventId(){
		$this->assertThrowsException(function(){
			$this->getEventId();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$_GET['pid'] = (string) TEST_SETTING_PID;
		$project1EventId = $this->getEventId();
		$this->assertIsInt($project1EventId);

		$urlEventId = rand();
		$_GET['event_id'] = $urlEventId;
		$this->assertEquals($urlEventId,  $this->getEventId());

		$project2EventId =  $this->getEventId(TEST_SETTING_PID_2);
		$this->assertIsInt($project2EventId);
		$this->assertNotSame($project1EventId, $project2EventId);
		$this->assertNotSame($urlEventId, $project2EventId);
	}

    function testGetSafePath(){
        $test = function($path, $root=null){
            // Get the actual value before manipulating the root for testing.
            $actual = call_user_func_array([$this, 'getSafePath'], func_get_args());

			$moduleDirectory = ExternalModules::getModuleDirectoryPath(TEST_MODULE_PREFIX);
            if(!$root){
                $root = $moduleDirectory;
            }
            else if(!file_exists($root)){
                $root = "$moduleDirectory/$root";
            }

            $root = realpath($root);
            $expected = "$root/$path";
            if(file_exists($expected)){
                $expected = realpath($expected);
            }

            $this->assertEquals($expected, $actual);
        };

        $test(basename(__FILE__));
        $test('.');
        $test('non-existant-file.php');
        $test('test-subdirectory');
        $test('test-file.php', 'test-subdirectory'); // relative path
        $test('test-file.php', ExternalModules::getTestModuleDirectoryPath() . '/test-subdirectory'); // absolute path

        $expectedExceptions = [
            'outside of your allowed parent directory' => [
                '../index.php',
                '..',
                '../non-existant-file',
                '../../../passwd'
            ],
            'only works on directories that exist' => [
                'non-existant-directory/non-existant-file.php',
                'non-existant-directory/../../../passwd'
            ],
            'does not exist as either an absolute path or a relative path' => [
                ['foo', 'non-existent-root']
            ]
        ];

        foreach($expectedExceptions as $excerpt=>$calls){
            foreach($calls as $args){
                if(!is_array($args)){
                    $args = [$args];
                }    

                $this->assertThrowsException(function() use ($test, $args){
                    call_user_func_array($test, $args);
                }, $excerpt);
            }
        }
    }

    function testConvertIntsToStrings(){
        $assert = function($expected, $data){
            $actual = $this->convertIntsToStrings($data);
            $this->assertSame($expected, $actual);
        };

        $assert(['1', 'b', null], [1, 'b', null]);
        $assert(['a' => '1', 'b'=>'b', 'c' => null], ['a' => 1, 'b'=>'b', 'c' => null]);
    }

    function testIsPage(){
        $originalRequestURI = $_SERVER['REQUEST_URI'];
        
        $path = 'foo/goo.php';

        $this->assertFalse($this->isPage($path));
        
        $_SERVER['REQUEST_URI'] = APP_PATH_WEBROOT . $path;
        $this->assertTrue($this->isPage($path));

        $_SERVER['REQUEST_URI'] = $originalRequestURI;
    }
	
	function testGetLinkIconHtml(){
		$iconName = 'fas fa-whatever';
		$link = ['icon' => $iconName];
		$html = ExternalModules::getLinkIconHtml($this->getInstance(), $link);

		if($this->getFrameworkVersion() < 3){
			$expected = "<img src='" . APP_PATH_IMAGES . "$iconName.png'";
		}
		else{
			$expected = "<i class='$iconName'";
		}

		$this->assertTrue(strpos($html, $expected) > 0, "Could not find '$expected' in '$html'");
	}
	
	function testGetSQLInClause(){
		// This method is tested more thoroughly in ExternalModulesTest.

		$getSQLInClause = function(){
			$clause = $this->getSQLInClause('a', [1]);
			$this->assertSame("(a IN ('1'))", $clause);
		};

		if($this->getFrameworkVersion() < 4){
			$getSQLInClause();
		}
		else{
			$this->assertThrowsException(function() use ($getSQLInClause){
				$getSQLInClause();
			}, ExternalModules::tt('em_errors_122'));
		}
	}

	function testCountLogs(){
		$whereClause = "message = ?";
		$message = rand();

		$assert = function($expected) use ($whereClause, $message){
			$actual = $this->countLogs($whereClause, $message);
			$this->assertSame($expected, $actual);
		};
		
		$assert(0);

		$this->log($message);
		$assert(1);

		$this->log($message);
		$assert(2);

		$this->getInstance()->removeLogs($whereClause, $message);
		$assert(0);
	}

	function testIsSafeToForwardMethodToFramework(){
		// The 'tt' methods are grandfathered in.
		$this->assertTrue($this->isSafeToForwardMethodToFramework('tt'));

		$passThroughAllowed = $this->getFrameworkVersion() >= 5;
		$this->assertSame($passThroughAllowed, $this->isSafeToForwardMethodToFramework('getRecordIdField'));

		$methodName = 'getRecordIdField';
		$passThroughCall = function() use ($methodName){
			$this->getInstance()->{$methodName}(TEST_SETTING_PID);
		};
		
		if($passThroughAllowed){
			// Make sure no exception is thrown.
			$passThroughCall();
		}
		else{
			$this->assertThrowsException(function() use ($passThroughCall){
				$passThroughCall();
			}, ExternalModules::tt("em_errors_69", $methodName));
		}
	}

	function testGetRecordIdField(){
		$metadata = ExternalModules::getMetadata(TEST_SETTING_PID);
		$expected = array_keys($metadata)[0];
		
		$this->assertThrowsException(function(){
			$this->getRecordIdField();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$this->assertSame($expected, $this->getRecordIdField(TEST_SETTING_PID));

		$_GET['pid'] = TEST_SETTING_PID;
		$this->assertSame($expected, $this->getRecordIdField());
	}

	function testGetProjectSettings(){
		$_GET['pid'] = TEST_SETTING_PID;

		$value = rand();
		$this->setProjectSetting($value);
		$array = $this->getProjectSettings();

		$actual = $array[TEST_SETTING_KEY];

		if($this->getFrameworkVersion() < 5){
			$this->assertSame(null, @$actual['system_value']);
			$actual = $actual['value'];
		}

		$this->assertSame($value, $actual);
	}

	function testSetProjectSettings(){
		$_GET['pid'] = TEST_SETTING_PID;

		$value = rand();
		$this->setProjectSettings([
			TEST_SETTING_KEY => $value
		]);

		if($this->getFrameworkVersion() >= 5){
			$expected = $value;
		}
		else{
			$expected = null;
		}

		$this->assertSame($expected, $this->getProjectSetting(TEST_SETTING_KEY));
	}

	function testObjectReferencePassThrough(){
		$name = 'records';
		$expected = $this->getFramework()->{$name};
		$this->assertNotNull($expected);
		$this->assertSame($expected, $this->getInstance()->{$name});
	}
}