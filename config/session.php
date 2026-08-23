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
            );

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