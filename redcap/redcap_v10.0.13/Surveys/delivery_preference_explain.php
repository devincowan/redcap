<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
$content = 	RCView::div(array('style'=>''),
				$lang['survey_861']
			) .
			RCView::div(array('style'=>''),
				" &nbsp; &bull; " . implode("<br> &nbsp; &bull; ", Survey::getDeliveryMethods())
			);
print json_encode_rc(array('title'=>$lang['survey_687']. " " . $lang['survey_691'], 'content'=>$content));