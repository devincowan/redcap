<?php

// Check if enable_http_compression is already set from a previous version. If not, add it.
$q = db_query("select value from redcap_config where field_name = 'enable_http_compression'");
if (db_num_rows($q) == 0) {
	print "-- Add option to disable HTTP compression\ninsert into redcap_config values ('enable_http_compression', '1');";
}