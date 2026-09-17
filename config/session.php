<?php

/**
 * LUX EMPIRE
 * Session Management
 *
 * Responsible only for session lifecycle and security.
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';


final class Session
{
    /**
     * Start and validate the current session.
     */
    public static function start(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Already active
        |--------------------------------------------------------------------------
        */

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Configure secure cookies
        |--------------------------------------------------------------------------
        */

        self::configureCookies();
        self::configureRedisSessionStorage();

        /*
        |--------------------------------------------------------------------------
        | Session name
        |--------------------------------------------------------------------------
        */

        session_name(SESSION_NAME);

        /*
        |--------------------------------------------------------------------------
        | Start PHP session
        |--------------------------------------------------------------------------
        */

        session_start();

        /*
        |--------------------------------------------------------------------------
        | Initialize metadata
        |--------------------------------------------------------------------------
        */

        self::initialize();

        /*
        |--------------------------------------------------------------------------
        | Validate session
        |--------------------------------------------------------------------------
        */

        self::validate();
    }


    /**
     * Configure PHP session security.
     */
    private static function configureCookies(): void
    {
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
            (
                // Reverse proxy / load balancer terminated TLS and
                // forwarded plain HTTP internally — the common shape
                // of most cloud deployments (Oracle Cloud included).
                // This header is only trustworthy when you KNOW your
                // own proxy sets it and the app isn't directly
                // internet-facing on a port an attacker could hit
                // and spoof this header on — true once this sits
                // behind Oracle's load balancer / your own nginx,
                // not true if Apache is ever exposed raw to the
                // internet on its own.
                isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                &&
                strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
            )
            ||
            str_contains($_SERVER['HTTP_HOST'] ?? '', 'ngrok');

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => SESSION_COOKIE_PATH,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => SESSION_COOKIE_SAMESITE,
        ]);

        ini_set(
            'session.use_only_cookies',
            '1'
        );

        ini_set(
            'session.use_strict_mode',
            '1'
        );

        ini_set(
            'session.use_trans_sid',
            '0'
        );

        ini_set(
            'session.use_cookies',
            '1'
        );
    }


    /**
     * Store sessions in Redis instead of local disk files.
     *
     * Required before this app can run behind more than one web
     * server — file-based sessions (session.save_handler = files)
     * live on one server's local disk only, so a user's very next
     * request landing on a different server would find them logged
     * out. Uses phpredis's own built-in session handler — the same
     * extension config/RedisConnection.php already uses for caching
     * — so no custom SessionHandlerInterface class is needed.
     *
     * Falls back to PHP's default file-based sessions automatically
     * if REDIS_HOST isn't set, so local development without Redis
     * running keeps working unmodified.
     */
    private static function configureRedisSessionStorage(): void
    {
        // Same default-to-localhost fallback as
        // config/RedisConnection.php's own pconnect() call — your
        // .env apparently doesn't set REDIS_HOST explicitly (Redis
        // caching has been working this whole time only because of
        // that same fallback there), so this needs to match it
        // rather than require a variable that was never actually set.
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
        $pass = $_ENV['REDIS_PASS'] ?? '';

        $savePath = "tcp://{$host}:{$port}?database=0";

        if ($pass !== '') {
            $savePath .= '&auth=' . rawurlencode($pass);
        }

        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $savePath);
    }


    /**
     * Initialize session metadata.
     */
    private static function initialize(): void
    {
        $now = time();

        if (!isset($_SESSION['_session'])) {

            $_SESSION['_session'] = [
                'created_at'       => $now,
                'last_activity'   => $now,
                'last_regeneration' => $now,
            ];
        }
    }


    /**
     * Validate the current session.
     */
    private static function validate(): void
    {
        self::validateIdleTimeout();

        /*
         * A timeout destroys the session.
         * Do not continue validating the destroyed session.
         */
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        self::validateAbsoluteLifetime();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        self::rotateSessionIdIfNeeded();

        /*
         * Only update activity for a valid session.
         */
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_session']['last_activity'] = time();
        }
    }


    /**
     * Validate inactivity timeout.
     */
    private static function validateIdleTimeout(): void
    {
        if (
            !isset(
                $_SESSION['_session']['last_activity']
            )
        ) {
            return;
        }

        $lastActivity =
            (int) $_SESSION['_session']['last_activity'];

        if (
            time() - $lastActivity
            <= SESSION_IDLE_TIMEOUT
        ) {
            return;
        }

        self::expire();
    }


    /**
     * Validate absolute session lifetime.
     */
    private static function validateAbsoluteLifetime(): void
    {
        if (
            !isset(
                $_SESSION['_session']['created_at']
            )
        ) {
            return;
        }

        $createdAt =
            (int) $_SESSION['_session']['created_at'];

        if (
            time() - $createdAt
            <= SESSION_ABSOLUTE_TIMEOUT
        ) {
            return;
        }

        self::expire();
    }


    /**
     * Periodically regenerate the session ID.
     */
    private static function rotateSessionIdIfNeeded(): void
    {
        if (
            !isset(
                $_SESSION['_session']['last_regeneration']
            )
        ) {
            return;
        }

        $lastRegeneration =
            (int) $_SESSION['_session']['last_regeneration'];

        if (
            time() - $lastRegeneration
            < SESSION_REGENERATE_INTERVAL
        ) {
            return;
        }

        session_regenerate_id(true);

        $_SESSION['_session']['last_regeneration'] =
            time();
    }


    /**
     * Regenerate session after successful authentication.
     *
     * This prevents session fixation during login.
     */
    public static function regenerateAfterLogin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        session_regenerate_id(true);

        $now = time();

        $_SESSION['_session'] = [
            'created_at'        => $now,
            'last_activity'     => $now,
            'last_regeneration' => $now,
        ];
    }


    /**
     * Completely destroy the current session.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (
            ini_get('session.use_cookies')
            &&
            session_status() === PHP_SESSION_ACTIVE
        ) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' =>
                        $params['samesite']
                        ?? SESSION_COOKIE_SAMESITE,
                ]
            );
        }

        if (
            session_status() === PHP_SESSION_ACTIVE
        ) {
            session_destroy();
        }
    }


    /**
     * Expire the current session.
     */
    private static function expire(): void
    {
        self::destroy();
    }


    /**
     * Check whether a session is currently active.
     */
    public static function isActive(): bool
    {
        return session_status()
            === PHP_SESSION_ACTIVE;
    }


    /**
     * Check whether the user is authenticated.
     *
     * Authentication structure will be redesigned later.
     */
    public static function isAuthenticated(): bool
    {
        return isset(
            $_SESSION['user']
        );
    }


    /**
     * Get authenticated user data.
     */
    public static function user(): ?array
    {
        if (
            !isset($_SESSION['user'])
            ||
            !is_array($_SESSION['user'])
        ) {
            return null;
        }

        return $_SESSION['user'];
    }
}