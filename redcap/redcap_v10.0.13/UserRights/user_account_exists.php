<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Remove illegal characters (if somehow posted bypassing javascript)
$user = preg_replace("/[^a-zA-Z0-9-.@_]/", "", $_POST['username']);
if (!isset($_POST['username']) || $user != $_POST['username']) exit('0');
$user = $_POST['username'];

// Get user info
$user_info = User::getUserInfo($user);
print ($user_info !== false && $user_info['user_email'] != '') ? '1' : '0';