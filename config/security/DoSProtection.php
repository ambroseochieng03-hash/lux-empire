<?php

/**
 * LUX EMPIRE
 * Application-Level DoS Protection
 *
 * Responsible for detecting and limiting abusive request
 * patterns that could exhaust application resources.
 *
 * This is NOT a network-level DDoS mitigation system.
 */

declare(strict_types=1);

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Audit.php';

final class DoSProtection
{
    /**
     * Maximum requests permitted during the protection window.
     */
    private const MAX_REQUESTS = 60;

    /**
     * Protection window in seconds.
     */
    private const WINDOW_SECONDS = 60;

    /**
     * Block duration after the request threshold is exceeded.
     */
    private const BLOCK_SECONDS = 300;

    /**
     * Protect the current request.
     *
     * Returns true when the request may continue.
     * Terminates the request when the client is blocked.
     */
    public static function check(): bool
    {
        $ip = self::clientIp();

        $key = self::buildKey($ip);

        /*
         * Existing block.
         */
        if (RateLimiter::isBlocked($key)) {
            self::reject(
                RateLimiter::retryAfter($key)
            );

            return false;
        }

        /*
         * Register this request.
         */
        $attempts = RateLimiter::hit(
            $key,
            self::WINDOW_SECONDS
        );

        /*
         * Threshold has not been exceeded.
         */
        if ($attempts <= self::MAX_REQUESTS) {
            return true;
        }

        /*
         * Client exceeded the application-level
         * request threshold.
         */
        RateLimiter::block(
            $key,
            self::BLOCK_SECONDS
        );

        Audit::log(
            'Application DoS protection triggered for IP: '
            . $ip
        );

        self::reject(
            self::BLOCK_SECONDS
        );

        return false;
    }

    /**
     * Build an isolated rate-limit key.
     */
    private static function buildKey(string $ip): string
    {
        return 'dos:' . hash(
            'sha256',
            $ip
        );
    }

    /**
     * Reject an abusive request.
     */
    private static function reject(int $retryAfter): void
    {
        http_response_code(429);

        header(
            'Content-Type: application/json'
        );

        header(
            'Retry-After: ' . max(1, $retryAfter)
        );

        echo json_encode(
            [
                'success' => false,
                'error'   => 'Too many requests. Please try again later.',
            ],
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    /**
     * Obtain the client IP address.
     *
     * We intentionally do not blindly trust
     * X-Forwarded-For.
     */
    private static function clientIp(): string
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
}