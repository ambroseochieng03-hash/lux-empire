<?php

/**
 * LUX EMPIRE
 * Core Application Configuration
 *
 * Configuration only.
 * No session startup.
 * No authentication logic.
 * No database connections.
 */

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Composer / Environment
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();


/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

define(
    'APP_NAME',
    'LUX EMPIRE'
);

define(
    'APP_TAGLINE',
    'Luxury Living. Elite Movement. One Empire.'
);


/*
|--------------------------------------------------------------------------
| Application URL
|--------------------------------------------------------------------------
*/

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$isHttps =
    (
        !empty($_SERVER['HTTPS'])
        &&
        $_SERVER['HTTPS'] !== 'off'
    )
    ||
    (
        isset($_SERVER['SERVER_PORT'])
        &&
        (int) $_SERVER['SERVER_PORT'] === 443
    )
    ||
    str_contains($host, 'ngrok');

define(
    'BASE_URL',
    ($isHttps ? 'https' : 'http')
    . '://'
    . $host
    . '/luxempire'
);


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

define(
    'DB_HOST',
    $_ENV['DB_HOST']
);

define(
    'DB_NAME',
    $_ENV['DB_NAME']
);

define(
    'DB_USER',
    $_ENV['DB_USER']
);

define(
    'DB_PASS',
    $_ENV['DB_PASS']
);


/*
|--------------------------------------------------------------------------
| External Services
|--------------------------------------------------------------------------
*/

define(
    'GOOGLE_MAPS_API_KEY',
    $_ENV['GOOGLE_MAPS_API_KEY']
);


/*
|--------------------------------------------------------------------------
| Upload Directories
|--------------------------------------------------------------------------
*/

define(
    'UPLOAD_PATH_HOUSES',
    $_SERVER['DOCUMENT_ROOT']
    . '/house_truck_platform/assets/uploads/house_images/'
);

define(
    'UPLOAD_PATH_IDS',
    $_SERVER['DOCUMENT_ROOT']
    . '/house_truck_platform/assets/uploads/user_ids/'
);

define(
    'UPLOAD_PATH_DRIVER_DOCS',
    $_SERVER['DOCUMENT_ROOT']
    . '/house_truck_platform/assets/uploads/driver_docs/'
);


/*
|--------------------------------------------------------------------------
| Application Assets
|--------------------------------------------------------------------------
*/

define(
    'DEFAULT_PROFILE_IMAGE',
    'assets/images/profiles/default.png'
);


/*
|--------------------------------------------------------------------------
| Session Configuration
|--------------------------------------------------------------------------
|
| All session lifetime/security timing belongs here.
|
| Change these values without modifying Session.php.
|
*/

/*
 * How long a session can remain inactive.
 *
 * Example:
 * 1800 = 30 minutes
 * 300  = 5 minutes
 * 2    = 2 seconds (useful for testing)
 */
define(
    'SESSION_IDLE_TIMEOUT',
    1800
);


/*
 * Maximum lifetime of a session,
 * regardless of activity.
 *
 * 28800 = 8 hours
 */
define(
    'SESSION_ABSOLUTE_TIMEOUT',
    28800
);


/*
 * How frequently the session ID should rotate.
 *
 * 1800 = 30 minutes
 */
define(
    'SESSION_REGENERATE_INTERVAL',
    1800
);


/*
 * Browser cookie lifetime.
 *
 * 0 means the cookie is a session cookie
 * and normally disappears when the browser closes.
 */
define(
    'SESSION_LIFETIME',
    0
);


/*
 * Custom session cookie name.
 */
define(
    'SESSION_NAME',
    'LUXEMPIRESESSION'
);


/*
 * Session cookie path.
 */
define(
    'SESSION_COOKIE_PATH',
    '/'
);


/*
 * SameSite policy.
 */
define(
    'SESSION_COOKIE_SAMESITE',
    'Lax'
);


/*
|--------------------------------------------------------------------------
| General Application Configuration
|--------------------------------------------------------------------------
*/

define(
    'CURRENCY',
    'KES'
);


/*
|--------------------------------------------------------------------------
| LUX EMPIRE Brand
|--------------------------------------------------------------------------
*/

define(
    'BRAND_PRIMARY',
    '#D4AF37'
);

define(
    'BRAND_DARK',
    '#0A0A0A'
);

define(
    'BRAND_LIGHT',
    '#FFFFFF'
);

define(
    'BRAND_ACCENT',
    '#1A1A1A'
);

/*
|--------------------------------------------------------------------------
| CSRF Configuration
|--------------------------------------------------------------------------
*/

define(
    'CSRF_TOKEN_LIFETIME',
    1800
);

/*
|--------------------------------------------------------------------------
| Chat / Groq Configuration
|--------------------------------------------------------------------------
*/

define('GROQ_API_KEY', $_ENV['GROQ_API_KEY'] ?? '');
define('GROQ_MODEL', $_ENV['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile');
define('CHAT_AI_SILENCE_MINUTES', 5);

/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set(
    'Africa/Nairobi'
);


/*
|--------------------------------------------------------------------------
| Error Handling
|--------------------------------------------------------------------------
|
| Development:
|   display_errors = 1
|
| Production:
|   display_errors = 0
|
| Keep errors logged even when hidden from users.
|--------------------------------------------------------------------------
*/

ini_set(
    'display_errors',
    '0'
);

ini_set(
    'log_errors',
    '1'
);

error_reporting(
    E_ALL
);