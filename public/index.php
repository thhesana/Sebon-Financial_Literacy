<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Fix subdirectory rewrite (hide /public in URL)
|--------------------------------------------------------------------------
|
| When Apache rewrites /Financial_Literacy/* → public/index.php, SCRIPT_NAME
| still contains /public, so Laravel builds the wrong base path and 404s.
| Strip /public from the script path unless the browser URL really includes it.
|
*/
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (
    str_contains($scriptName, '/public/index.php')
    && ! preg_match('#/Financial_Literacy/public(/|$)#i', $requestPath)
) {
    $_SERVER['SCRIPT_NAME'] = str_replace('/public/index.php', '/index.php', $scriptName);
    if (! empty($_SERVER['PHP_SELF'])) {
        $_SERVER['PHP_SELF'] = str_replace('/public/index.php', '/index.php', $_SERVER['PHP_SELF']);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
