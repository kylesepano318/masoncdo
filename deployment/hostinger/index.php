<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$appRoot = dirname(__DIR__).'/lodge';
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
