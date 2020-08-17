<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$randomization) System::redirectHome();

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
renderPageTitle('<i class="fas fa-random"></i> ' . $lang['app_21']);

// Instructions
print Randomization::renderInstructions();

// Page tabs
Randomization::renderTabs();

// Render the dashboard (all subjects/combinations)
// Randomization::renderDashboardSubjects();

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';