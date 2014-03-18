<?php

require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$db = new DB;

$db->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => '',
    'username'  => '',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

// Make this Capsule instance available globally via static methods... (optional)
$db->setAsGlobal();
