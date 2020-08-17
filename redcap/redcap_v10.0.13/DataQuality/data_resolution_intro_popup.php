<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

print json_encode_rc(array('content'=>DataQuality::renderDRWinstructions(), 'title'=>$lang['dataqueries_275']));
