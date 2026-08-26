<?php

/**
 * LUX EMPIRE
 * Login Security Orchestrator
 *
 * Coordinates the security controls required during
 * the authentication process.
 *
 * This class does not authenticate users.
 * Authentication remains the responsibility of Auth.php.
 *
 * This class coordinates:
 * - Application-level DoS protection
 * - Brute-force protection
 * - Progressive throttling
 * - CAPTCHA verification
 *
 * CSRF protection is intentionally NOT used here.
 *
 * Login is intentionally exempt from CSRF protection because
 * authentication does not operate on an already authenticated
 * user session. Login-specific abuse protection is handled by
 * the controls above.
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Security Components
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/BruteForce.php';
require_once __DIR__ . '/Throttle.php';
require_once __DIR__ . '/Captcha.php';
require_once __DIR__ . '/DoSProtection.php';

final class LoginSecurity
{
    /**
     * Number of failed attempts after which CAPTCHA
     * becomes required when CAPTCHA is configured.
     */
    private const CAPTCHA_THRESHOLD = 3;

    /**
     * Protect the login request before authentication.
     *
     * This should be called before Auth::login().
     */
    public static function beforeAuthentication(
        string $email,
        string $ip
    ): void {
        /*
         * Application-level request protection.
         */
        DoSProtection::check();

        /*
         * Authentication-specific restrictions.
         */
        if (BruteForce::isBlocked($email, $ip)) {
            self::reject(
                'Too many failed authentication attempts. Please try again later.',
                BruteForce::retryAfter($email, $ip)
            );
        }
    }

    /**
     * Determine whether CAPTCHA is required for this login attempt.
     *
     * CAPTCHA is only required after repeated failures and only
     * when CAPTCHA has actually been configured.
     */
    public static function requiresCaptcha(
        string $email,
        string $ip
    ): bool {
        /*
         * CAPTCHA cannot be required when no provider is configured.
         */
        if (!Captcha::isConfigured()) {
            return false;
        }

        /*
         * The current BruteForce class intentionally exposes
         * blocking and failure recording, but not the current
         * counter. CAPTCHA therefore remains an explicit policy
         * hook until an attempt-count accessor is introduced.
         *
         * For now, configuration alone does not force CAPTCHA.
         */
        return false;
    }

    /**
     * Verify CAPTCHA when the caller explicitly requires it.
     */
    public static function verifyCaptcha(
        ?string $token,
        string $ip
    ): bool {
        return Captcha::verify(
            $token,
            $ip
        );
    }

    /**
     * Record a failed authentication attempt.
     *
     * Returns the resulting highest failure count.
     */
    public static function authenticationFailed(
        string $email,
        string $ip
    ): int {
        $failures = BruteForce::recordFailure(
            $email,
            $ip
        );

        /*
         * Slow repeated failures down before the response
         * is returned to the client.
         */
        Throttle::delay(
            $failures
        );

        return $failures;
    }

    /**
     * Clear authentication abuse state after success.
     */
    public static function authenticationSucceeded(
        string $email,
        string $ip
    ): void {
        BruteForce::clear(
            $email,
            $ip
        );
    }

    /**
     * Obtain the trusted client IP.
     *
     * REMOTE_ADDR is intentionally used rather than blindly
     * trusting proxy-controlled headers.
     */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (
            !is_string($ip)
            || $ip === ''
            || filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            return 'unknown';
        }

        return $ip;
    }

    /**
     * Reject a request controlled by login security.
     */
    private static function reject(
        string $message,
        int $retryAfter = 0
    ): never {
        header(
            'Location: '
            . BASE_URL
            . '/login?error='
            . urlencode($message)
        );

        exit;
    }
}