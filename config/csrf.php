<?php

/**
 * LUX EMPIRE
 * CSRF Protection
 *
 * Responsible only for:
 * - CSRF token generation
 * - CSRF token storage
 * - CSRF token expiration
 * - CSRF token validation
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';


final class Csrf
{
    private const SESSION_KEY = '_csrf';


    /**
     * Get a valid CSRF token.
     *
     * Creates a new token when:
     * - no token exists
     * - the existing token has expired
     */
    public static function token(): string
    {
        self::ensureSession();

        if (!self::isValidStoredToken()) {
            self::regenerate();
        }

        return $_SESSION[self::SESSION_KEY]['token'];
    }


    /**
     * Regenerate the CSRF token.
     */
    public static function regenerate(): string
    {
        self::ensureSession();

        $token = bin2hex(
            random_bytes(32)
        );

        $_SESSION[self::SESSION_KEY] = [
            'token'     => $token,
            'created_at' => time(),
        ];

        return $token;
    }


    /**
     * Validate a submitted CSRF token.
     */
    public static function validate(
        ?string $submittedToken
    ): bool {

        self::ensureSession();

        if (
            $submittedToken === null
            ||
            $submittedToken === ''
        ) {
            return false;
        }

        if (!self::isValidStoredToken()) {
            return false;
        }

        return hash_equals(
            $_SESSION[self::SESSION_KEY]['token'],
            $submittedToken
        );
    }


    /**
     * Require a valid CSRF token.
     *
     * Terminates the request when validation fails.
     */
    public static function requireValid(
        ?string $submittedToken
    ): void {

        if (!self::validate($submittedToken)) {

            http_response_code(403);

            exit('Invalid or expired CSRF token.');
        }
    }


    /**
     * Determine whether the stored token is valid.
     */
    private static function isValidStoredToken(): bool
    {
        if (
            !isset($_SESSION[self::SESSION_KEY])
            ||
            !is_array($_SESSION[self::SESSION_KEY])
        ) {
            return false;
        }

        if (
            !isset(
                $_SESSION[self::SESSION_KEY]['token'],
                $_SESSION[self::SESSION_KEY]['created_at']
            )
        ) {
            return false;
        }

        $createdAt =
            (int) $_SESSION[self::SESSION_KEY]['created_at'];

        /*
         * Token expired.
         */
        if (
            time() - $createdAt
            > CSRF_TOKEN_LIFETIME
        ) {
            return false;
        }

        /*
         * Make sure the token has the expected format.
         */
        if (
            !is_string(
                $_SESSION[self::SESSION_KEY]['token']
            )
            ||
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $_SESSION[self::SESSION_KEY]['token']
            )
        ) {
            return false;
        }

        return true;
    }


    /**
     * Ensure the session is available.
     */
    private static function ensureSession(): void
    {
        if (!Session::isActive()) {
            Session::start();
        }
    }
}