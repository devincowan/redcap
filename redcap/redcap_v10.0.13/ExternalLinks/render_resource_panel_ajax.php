<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Output the HTML for the left-hand menu panel
print $ExtRes->renderHtmlPanel();