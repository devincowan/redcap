<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';

if (!$api_enabled) System::redirectHome();

$plang = isset($_GET['lang']) ? $_GET['lang'] : '';

// prevent dir hacking
if(!empty($plang) && !in_array($plang, APIPlayground::getLangs()))
{
	exit;
}

$dir = dirname(__FILE__) . '/examples';
$zipName = 'redcap-api-examples.zip';

// maybe a subset
if(!empty($plang))
{
	$dir .= DS . $plang;
	$zipName = "redcap-api-examples-$plang.zip";
}

$zipName = sys_get_temp_dir() . DS . $zipName;
$rootPath = realpath($dir);

$zip = new ZipArchive();
$zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($rootPath),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach($files as $name => $file)
{
	if (!$file->isDir())
	{
		$filePath = $file->getRealPath();
		$relativePath = substr($filePath, strlen($rootPath) + 1);
		$zip->addFile($filePath, $relativePath);
	}
}

$zip->close();

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($zipName));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($zipName));
readfile($zipName);
exit;
