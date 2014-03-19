<?php

// BEGIN BOOTSTRAP.
$filename = 'bootstrap.php'; $path = getcwd(); $len = strlen($path);
while (true) {
	if (file_exists($bootstrap = $path . DIRECTORY_SEPARATOR . $filename)) break;
	$path = dirname($path);
	if (strlen($path) == $len) throw new Exception('Could not find ' . $filename);
	$len = strlen($path);
}
require_once $bootstrap;
// END BOOTSTRAP.

use Illuminate\Database\Capsule\Manager as DB;
use Witangone\WitangoLib;


