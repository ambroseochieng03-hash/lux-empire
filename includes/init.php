<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LUX EMPIRE Development Initialization
|--------------------------------------------------------------------------
| Development only.
| Disable display_errors before production deployment.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config/app.php';
