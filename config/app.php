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
 * Social link-preview image (WhatsApp, Facebook, etc.) — must be a
 * raster image; most crawlers don't render SVG previews reliably.
 * Generated from logo.svg by assets/images/generate-icons.sh.
 */
define(
    'APP_OG_IMAGE',
    'assets/images/og-preview.png'
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

/*
 * Web requests derive the URL from the Host header. Workers, timers and
 * scripts (mail worker, refund worker, cleanup jobs) have NO request, so
 * without this every link they put in an email or notification would be
 * http://localhost/luxempire/... Set APP_PUBLIC_URL in .env to the public
 * URL of the site INCLUDING the /luxempire path.
 */
if (!isset($_SERVER['HTTP_HOST']) && !empty($_ENV['APP_PUBLIC_URL'])) {
    define('BASE_URL', rtrim((string) $_ENV['APP_PUBLIC_URL'], '/'));
} else {
    define(
        'BASE_URL',
        ($isHttps ? 'https' : 'http')
        . '://'
        . $host
        . '/luxempire'
    );
}


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
 * Cloudflare Turnstile site key — public, safe to expose to the
 * frontend. The SECRET key (CAPTCHA_SECRET in Captcha.php) never
 * leaves the server.
 */
define(
    'TURNSTILE_SITE_KEY',
    $_ENV['TURNSTILE_SITE_KEY'] ?? ''
);

/*
|--------------------------------------------------------------------------
| Upload Directories - For Apache.
|--------------------------------------------------------------------------
*/
/*
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

*/

/*
 * CHANGED from $_SERVER['DOCUMENT_ROOT'] . '/house_truck_platform/...'
 * to a fixed path derived from this file's own location instead.
 *
 * The old Apache setup apparently had DOCUMENT_ROOT pointing at a
 * PARENT directory (e.g. /srv/http) with house_truck_platform as a
 * subfolder, so appending '/house_truck_platform/...' was correct
 * THEN. Nginx's config sets `root` directly to
 * /srv/http/house_truck_platform (see nginx.conf) — no parent-folder
 * layer — so DOCUMENT_ROOT now equals that path already, and the
 * old code would append '/house_truck_platform/...' a SECOND time,
 * pointing at a directory that doesn't exist. dirname(__DIR__) is
 * this file's own parent's parent (config/app.php -> config/ ->
 * project root) — correct regardless of what any web server reports
 * as DOCUMENT_ROOT, so this can never break again on a future
 * server-config change.
 */
define(
    'UPLOAD_PATH_HOUSES',
    dirname(__DIR__) . '/assets/uploads/house_images/'
);

define(
    'UPLOAD_PATH_IDS',
    dirname(__DIR__) . '/assets/uploads/user_ids/'
);

define(
    'UPLOAD_PATH_DRIVER_DOCS',
    dirname(__DIR__) . '/assets/uploads/driver_docs/'
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
    21600
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
    3600
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
| Upload size caps — sized against your Contabo box (100GB total disk).
| Worst case: MAX_VIDEOS_PROCESSING_PER_LANDLORD concurrent video jobs
| across many simultaneous landlords, each staging up to
| MAX_VIDEO_SIZE_BYTES before compression even starts — on top of
| MariaDB, Redis, and the OS already living on that same disk. Both
| values also sit safely under your current post_max_size/
| upload_max_filesize = 128M, so no php.ini change is required.
*/

define('MAX_IMAGE_SIZE_BYTES', 8 * 1024 * 1024);         // 8MB per image — GD resizes to 1600px wide regardless, so a larger original just wastes staging space
define('MAX_IMAGES_PER_HOUSE', 10);
define('MAX_VIDEO_SIZE_BYTES', 100 * 1024 * 1024);        // 100MB per video, pre-compression
define('MIN_FREE_DISK_BYTES', 5 * 1024 * 1024 * 1024);    // 5GB — staging refuses NEW uploads once free space drops below this     // 300MB per video

define('MAX_VIDEOS_PROCESSING_PER_LANDLORD', 1);         // concurrent in-flight — NOT a daily cap, just "wait for the current one"

/*
|--------------------------------------------------------------------------
| Plan Tiers — single source of truth
|--------------------------------------------------------------------------
|
| No time-based ("daily"/"monthly") reset on any of these — once a
| cap is hit, the landlord deletes something or upgrades.
| classes/PlanLimits.php reads these constants; nothing else should
| ever hardcode these numbers.
*/

define('FREE_MAX_LISTINGS', 3);
define('FREE_MAX_IMAGES_PER_LISTING', 5);
define('FREE_VIDEO_ALLOWED', false);

define('PRO_MAX_LISTINGS', 10);
define('PRO_MAX_IMAGES_PER_LISTING', 10);
define('PRO_VIDEO_ALLOWED', true);                // free-tier cap

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

define('PRICE_LANDLORD_PRO_MONTHLY', 499);   // KES
define('BOOKING_FEE_AMOUNT', 5);            // KES
define('TRUCK_COMMISSION_PERCENT', 10);       // % of trip price
define('WALLET_MIN_BALANCE_TO_ACCEPT', 0);    // KES — floor before a driver is blocked



/*
|--------------------------------------------------------------------------
| Auto-Refunds (M-Pesa B2C)
|--------------------------------------------------------------------------
|
| B2C is a SEPARATE Safaricom API product from the STK/C2B config
| above — its own app registration on the Daraja portal, its own
| Initiator name + initiator password, often its own shortcode.
|
| SANDBOX: log into the Daraja app you added the B2C API product to,
| then visit https://developer.safaricom.co.ke/test_credentials while
| logged in — that page shows YOUR sandbox B2C shortcode (varies per
| app, never assume a number). Initiator name/password for sandbox
| are Safaricom's fixed published test values (see .env defaults).
|
| Daraja wants the initiator password RSA-encrypted with Safaricom's
| OWN public certificate, then base64-encoded — never the raw
| password. That encryption happens at request time in
| Payment::getB2cSecurityCredential() from the password + the cert
| file at DARAJA_B2C_CERT_PATH (see storage/mpesa/cert.cer).
|--------------------------------------------------------------------------
*/

define('DARAJA_B2C_SHORTCODE', $_ENV['DARAJA_B2C_SHORTCODE'] ?? '');
define('DARAJA_INITIATOR_NAME', $_ENV['DARAJA_INITIATOR_NAME'] ?? '');
define('DARAJA_INITIATOR_PASSWORD', $_ENV['DARAJA_INITIATOR_PASSWORD'] ?? 'Safaricom999!*!');
define('DARAJA_B2C_CERT_PATH', $_ENV['DARAJA_B2C_CERT_PATH'] ?? dirname(__DIR__) . '/storage/mpesa/cert.cer');

/*
| Safaricom's rebuilt developer portal no longer serves the
| certificate at any of the old static URLs — the Test Credentials
| page inside your logged-in app dashboard can hand you the
| already-encrypted Security Credential directly instead. When this
| is set, it's used as-is and DARAJA_B2C_CERT_PATH is never touched.
| Leave empty to use the cert-file + runtime-encryption path (needed
| for production, where you encrypt YOUR OWN initiator password
| yourself rather than using a value Safaricom generated for you).
*/
define('DARAJA_SECURITY_CREDENTIAL_PRECOMPUTED', $_ENV['DARAJA_SECURITY_CREDENTIAL_PRECOMPUTED'] ?? '');

/*
| Daraja never signs its callbacks — a shared secret in the query
| string is the only thing stopping a stranger from POSTing a fake
| "refund completed" event at these otherwise-public URLs. Generate
| a long random value for .env; this is NOT a Daraja credential.
*/
/*
| These four URLs are hit by Safaricom's servers, not a browser — and
| unlike STK (initiated from a real web request, where BASE_URL
| correctly reflects whatever host the tenant is actually on), B2C
| refunds are sent from workers/refund_worker.php and
| scripts/reconcile_stuck_refunds.php, both running under systemd
| with NO HTTP request context at all. There, $_SERVER['HTTP_HOST']
| is undefined and BASE_URL silently falls back to
| 'http://localhost/luxempire' — unreachable from Safaricom.
| DARAJA_PUBLIC_BASE_URL must be set explicitly in .env to your
| current publicly-reachable URL (your ngrok URL for now, your real
| domain in production) — it does NOT depend on request context.
*/
define('DARAJA_PUBLIC_BASE_URL', $_ENV['DARAJA_PUBLIC_BASE_URL'] ?? BASE_URL);

define('DARAJA_CALLBACK_SECRET', $_ENV['DARAJA_CALLBACK_SECRET'] ?? '');

define('DARAJA_B2C_RESULT_URL', ($_ENV['DARAJA_B2C_RESULT_URL'] ?? DARAJA_PUBLIC_BASE_URL . '/api/payments/mpesa_b2c_result_callback.php') . '?token=' . urlencode(DARAJA_CALLBACK_SECRET));
define('DARAJA_B2C_TIMEOUT_URL', ($_ENV['DARAJA_B2C_TIMEOUT_URL'] ?? DARAJA_PUBLIC_BASE_URL . '/api/payments/mpesa_b2c_timeout_callback.php') . '?token=' . urlencode(DARAJA_CALLBACK_SECRET));
define('DARAJA_STATUS_QUERY_RESULT_URL', ($_ENV['DARAJA_STATUS_QUERY_RESULT_URL'] ?? DARAJA_PUBLIC_BASE_URL . '/api/payments/mpesa_status_query_result_callback.php') . '?token=' . urlencode(DARAJA_CALLBACK_SECRET));
define('DARAJA_STATUS_QUERY_TIMEOUT_URL', ($_ENV['DARAJA_STATUS_QUERY_TIMEOUT_URL'] ?? DARAJA_PUBLIC_BASE_URL . '/api/payments/mpesa_status_query_timeout_callback.php') . '?token=' . urlencode(DARAJA_CALLBACK_SECRET));

/*
| A 'processing' refund with no result callback after this long is
| AMBIGUOUS, not failed — never auto-resend blind, that's exactly how
| you'd pay someone twice. The reconciliation sweep asks Safaricom's
| own Transaction Status API before ever touching its status, and
| ALWAYS flags the row for admin visibility regardless of whether
| that query resolves automatically.
*/
define('REFUND_STUCK_PROCESSING_TIMEOUT_SECONDS', 300);
define('REFUND_MAX_ATTEMPTS', 3);

/*
|--------------------------------------------------------------------------
| Booked-listing lifecycle
|--------------------------------------------------------------------------
| After a landlord ACCEPTS a booking (houses.status = 'booked'):
|   - tenants/guests stop seeing the house after TENANT_BOOKED_VISIBLE_HOURS
|   - landlord + admin stop seeing it after LANDLORD_BOOKED_VISIBLE_HOURS,
|     and scripts/cleanup_expired_listings.php deletes its media from disk
| The houses row itself is never deleted.
*/
define('TENANT_BOOKED_VISIBLE_HOURS', 6);
define('LANDLORD_BOOKED_VISIBLE_HOURS', 24);

/*
| A paid booking request the landlord never answers must not lock the
| house (and the tenant's money) forever. After this many hours,
| scripts/expire_stale_reservations.php declines it automatically,
| releases the house and queues the refund.
*/
define('RESERVATION_RESPONSE_HOURS', 48);

/*
| When may a tenant see a landlord's phone/email?
|   false (recommended): only AFTER the landlord accepts the booking. Until then the
|          tenant and landlord talk through the in-app chat. If contact details were shown as
|          soon as the fee is paid, a tenant could pay, phone the landlord, ask them to
|          decline in the app, get refunded and finish the deal off-platform.
|   true : as soon as the fee is paid (pending or approved booking).
*/
define('REVEAL_CONTACT_BEFORE_ACCEPTANCE', false);

/*
| A trip 'in_transit' with no driver location ping in this many minutes gets an
| automatic alert into emergency_alerts (scripts/monitor_active_trips.php), so a
| silent driver is caught by the system instead of only by a worried tenant calling in.
*/
define('STALE_LOCATION_ALERT_MINUTES', 20);

/*
| Chat edit / delete rules (enforced on the server, mirrored in the UI).
|   - Edit a message: sender only, within this many minutes of sending.
|   - Delete for everyone: sender only, within this many minutes.
|   - Delete for me: any participant, any time (hides it for them only).
| Every edit and delete is recorded in the message_audit table.
*/
define('CHAT_EDIT_WINDOW_MINUTES', 15);
define('CHAT_DELETE_EVERYONE_WINDOW_MINUTES', 60);

/*
| The floating "?" guided-tour button. Switched off for now because it covers page
| content. Set to true to bring it back.
*/
define('SHOW_HELP_TOUR', false);

/*
| How long a completed/cancelled truck trip stays visible on the
| tenant's My Bookings page before it's automatically filtered out
| of the list. The row itself is never deleted — trip_status_history
| has a hard foreign key to truck_requests with no ON DELETE action,
| so an accepted trip literally cannot be hard-deleted without first
| removing its history, and that history is exactly what you want
| kept for disputes/audits. This only hides it from the tenant's own
| view once it's old enough to no longer be "in progress" news.
*/
define('AUTO_CLEAR_FINISHED_TRIP_HOURS', 2);
/*
| How long an ACCEPTED house-booking conversation stays visible after
| approval before it's auto-hidden the same way a rejected/cancelled
| one is (removed from the list, hard-blocked on direct access). There
| is no "tenancy ended" event in this schema yet, so this is a
| placeholder default, not a modeled business rule — revisit once you
| have real usage data on how long tenants/landlords actually need to
| keep talking after move-in.
*/
define('LANDLORD_CHAT_APPROVED_VISIBLE_DAYS', 30);


/*
| The landlord's Booking History page only shows entries answered
| within this many hours (b.updated_at, not the original request
| date) — after that they're auto-hidden from THIS PAGE so it never
| piles up with stale requests. Nothing is deleted: the row, its
| refund trail, and every notification still exist exactly as
| before — this filter is presentation-only. The landlord sees a
| standing notice about this on the page itself.
*/
define('LANDLORD_BOOKING_HISTORY_VISIBLE_HOURS', 48);