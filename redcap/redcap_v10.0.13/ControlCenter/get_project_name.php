<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

print  RCView::b(decode_filter_tags($app_title)) .
	   "<small class='mt-2 text-secondary d-block'>
			{$lang['control_center_108']}
		</small>";