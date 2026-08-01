<?php
/**
 * LUX EMPIRE - Core Application Configuration
 * Premium Real Estate + Logistics Platform
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

define('APP_NAME', 'LUX EMPIRE');
define('APP_TAGLINE', 'Luxury Living. Elite Movement. One Empire.');

$host = $_SERVER['HTTP_HOST'];

$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443
    || str_contains($_SERVER['HTTP_HOST'], 'ngrok');

define('BASE_URL',
    ($isHttps ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . '/house_truck_platform'
);

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

define('GOOGLE_MAPS_API_KEY', $_ENV['GOOGLE_MAPS_API_KEY']);

define('UPLOAD_PATH_HOUSES', $_SERVER['DOCUMENT_ROOT'] . '/house_truck_platform/assets/uploads/house_images/');
define('UPLOAD_PATH_IDS', $_SERVER['DOCUMENT_ROOT'] . '/house_truck_platform/assets/uploads/user_ids/');
define('UPLOAD_PATH_DRIVER_DOCS', $_SERVER['DOCUMENT_ROOT'] . '/house_truck_platform/assets/uploads/driver_docs/');

define('DEFAULT_PROFILE_IMAGE', 'assets/images/profiles/default.png');

define('SESSION_TIMEOUT', 3600); // 1 hour

define('CURRENCY', 'KES');

define('BRAND_PRIMARY', '#D4AF37'); // Gold
define('BRAND_DARK', '#0A0A0A');   // Black
define('BRAND_LIGHT', '#FFFFFF');  // White
define('BRAND_ACCENT', '#1A1A1A'); // Dark Gray

date_default_timezone_set('Africa/Nairobi');

/**
 * Error Reporting (Development)
 * Change to 0 in production
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
?>