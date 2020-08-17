<?php
/**
 * Created by PhpStorm.
 * User: taylorr4
 * Date: 4/30/2019
 * Time: 4:52 PM
 */

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'init_global.php';

$tableCheck = new SQLTableCheck();
$sql_fixes = $tableCheck->build_table_fixes();
print "Database table check:\n";
if ($sql_fixes == '') {
    print "SUCCESS";
} else {
    throw new Exception("ERROR", 1);
}