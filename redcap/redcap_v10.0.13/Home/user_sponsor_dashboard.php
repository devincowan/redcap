<?php


// Make sure the page can't be called directly and only if enabled
if (!defined("REDCAP_VERSION") || !($user_sponsor_dashboard_enable || SUPER_USER || ACCOUNT_MANAGER)) exit("ERROR!");
// Render the Sponsor Dashboard
User::renderSponsorDashboard();