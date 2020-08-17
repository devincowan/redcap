<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

//If user is not a super user, then disallow page access
if (SUPER_USER) {
	// Display redcap_info
	redcap_info(true, false);
} else {
	print RCView::div(array('style'=>'border:1px solid #C00000;color:#C00000;padding:10px;'),
			"<b>ACCESS DENIED!</b> Since this page displays some potentially private system information, it is only accessible to REDCap super users."
		  );
}