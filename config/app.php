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
 * Primary favicon. SVG is what modern browsers (Chrome, Firefox,
 * Edge) will actually use — scales cleanly at any size/DPI.
 *
 * Safari and older browsers don't support SVG favicons and will
 * silently show nothing unless a raster fallback is also listed.
 * See APP_FAVICON_PNG_32 / APP_FAVICON_PNG_16 / APP_APPLE_TOUCH_ICON
 * below, wired in via the <link> stack in header.php.
 */
define(
    'APP_FAVICON',
    'assets/images/logo.svg'
);

define(
    'APP_FAVICON_PNG_32',
    'assets/images/favicon-32.png'
);

define(
    'APP_FAVICON_PNG_16',
    'assets/images/favicon-16.png'
);

/*
 * Used for "Add to Home Screen" on iOS — Safari ignores both the
 * SVG and the 16/32px PNGs for this and looks for this specifically.
 */
define(
    'APP_APPLE_TOUCH_ICON',
    'assets/images/apple-touch-icon.png'
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
 * Google Identity Services client id (tenant "Sign in with Google").
 * Safe to expose to the frontend — this is a client id, not a
 * secret. No client secret is needed for this flow (see
 * classes/GoogleAuth.php).
 */
define(
    'GOOGLE_OAUTH_CLIENT_ID',
    $_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? ''
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


define('NATS_HOST', $_ENV['NATS_HOST'] ?? 'localhost');
define('NATS_PORT', (int) ($_ENV['NATS_PORT'] ?? 4222));
define('NATS_USER', $_ENV['NATS_USER'] ?? '');
define('NATS_PASS', $_ENV['NATS_PASS'] ?? '');

/*
|--------------------------------------------------------------------------
| Media & Account Limits
|--------------------------------------------------------------------------
|
| Sized for a 2 OCPU Oracle Ampere A1 instance with ~150GB usable disk.
| MAX_LISTINGS_PER_LANDLORD is the free-tier cap — paid tiers (not yet
| built) will override this per-account rather than change the constant.
*/

define('MAX_IMAGE_SIZE_BYTES', 15 * 1024 * 1024);       // 15MB per image
define('MAX_IMAGES_PER_HOUSE', 10);
define('MAX_VIDEO_SIZE_BYTES', 300 * 1024 * 1024);      // 300MB per video

define('MAX_VIDEOS_PROCESSING_PER_LANDLORD', 1);         // concurrent in-flight
define('MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY', 5);

define('MAX_LISTINGS_PER_LANDLORD', 5);                 // free-tier cap

/*
|--------------------------------------------------------------------------
| Truck Request Pricing
|--------------------------------------------------------------------------
|
| Distance-based, Uber-style: a flat base fare plus a per-km rate,
| with a floor so a very short trip is never absurdly cheap. These
| are placeholder values — you said you'd tune them later, so these
| exist purely to make the formula real and testable now.
*/
define('TRUCK_BASE_FARE', 500);       // KES, flat, every trip
define('TRUCK_RATE_PER_KM', 100);      // KES per km
define('TRUCK_MINIMUM_FARE', 800);    // KES, floor regardless of distance

/*
| Server-side Google API key for the Distance Matrix API — this MUST
| be a DIFFERENT key from GOOGLE_MAPS_API_KEY (which is restricted to
| your domain's HTTP referrer for browser use, and won't work at all
| for server-to-server calls). Create a second, unrestricted-by-
| referrer key in Google Cloud Console, restrict it by IP instead
| (your server's IP) if possible, and enable the Distance Matrix API
| specifically — it's billed separately from the Maps JavaScript API
| and needs its own explicit enablement + billing on your Google
| Cloud project. I can't do that account-level setup for you.
*/
define('GOOGLE_SERVER_API_KEY', $_ENV['GOOGLE_SERVER_API_KEY'] ?? '');
/*
| Minimum notice required for a scheduled (non-instant) trip — must
| be comfortably longer than the 30-minute driver accept-gating
| window (item 3), or a request could be scheduled so close to "now"
| that no driver ever gets a legitimate chance to accept it.
*/
define('TRUCK_MIN_SCHEDULE_LEAD_MINUTES', 90);

/*
| How close to a scheduled move's time a driver may accept it.
| Chosen deliberately shorter than TRUCK_MIN_SCHEDULE_LEAD_MINUTES
| (90) so there's always a real gap between "tenant can no longer
| schedule this close" and "drivers can now accept" — they should
| never be the same number, or a trip booked at the exact minimum
| lead time would be simultaneously just-barely-bookable and
| already-acceptable, which defeats the point of gating at all.
*/
define('TRUCK_ACCEPT_WINDOW_MINUTES', 30);

/*
|--------------------------------------------------------------------------
| Daraja (M-Pesa)
|--------------------------------------------------------------------------
*/

define('DARAJA_ENV', $_ENV['DARAJA_ENV'] ?? 'sandbox'); // 'sandbox' | 'production'

define(
    'DARAJA_BASE_URL',
    DARAJA_ENV === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke'
);

define('DARAJA_CONSUMER_KEY', $_ENV['DARAJA_CONSUMER_KEY'] ?? '');
define('DARAJA_CONSUMER_SECRET', $_ENV['DARAJA_CONSUMER_SECRET'] ?? '');
define('DARAJA_SHORTCODE', $_ENV['DARAJA_SHORTCODE'] ?? '');
define('DARAJA_PASSKEY', $_ENV['DARAJA_PASSKEY'] ?? '');

/*
 * Must be a publicly reachable HTTPS URL — Safaricom calls this
 * server-to-server, so localhost/ngrok-only dev needs the ngrok
 * tunnel URL here during testing (same ngrok quirk your
 * BASE_URL/session cookie logic already accounts for elsewhere).
 */
define('DARAJA_CALLBACK_URL', $_ENV['DARAJA_CALLBACK_URL'] ?? BASE_URL . '/api/payments/mpesa_stk_callback.php');

/*
|--------------------------------------------------------------------------
| Monetization Pricing — server is the ONLY source of truth for these.
| Never trust a client-submitted amount for any payment purpose.
|--------------------------------------------------------------------------
*/

define('PRICE_LANDLORD_PRO_MONTHLY', 5);   // KES
define('BOOKING_FEE_AMOUNT', 5);            // KES
define('TRUCK_COMMISSION_PERCENT', 10);       // % of trip price
define('WALLET_MIN_BALANCE_TO_ACCEPT', 0);    // KES — floor before a driver is blocked