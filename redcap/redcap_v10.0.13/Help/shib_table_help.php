<?php
// PHP 5.3.0 compliant path builder
define('APP_PATH_WEBROOT', realpath(__DIR__ . '/..') . '/');
?>

<style>
img {
    max-width: 100%;
}
<?php
include APP_PATH_WEBROOT . 'ExternalModules/manager/css/markdown.css';
?>
</style>

<?php

$pagePath = APP_PATH_WEBROOT . 'Resources/misc/shib_table_auth_documentation/shib_table_readme.md';
require_once APP_PATH_WEBROOT . 'Libraries/Parsedown.php';

$Parsedown = new \Parsedown();
$html = $Parsedown->text(file_get_contents($pagePath));

$search = '<img src="';
// Cannot use APP_PATH_WEBROOT here for some reason, must be locally pathed
$replace = $search . '../Resources/misc/shib_table_auth_documentation/';
$html = str_replace($search, $replace, $html);

print($html);


?>
