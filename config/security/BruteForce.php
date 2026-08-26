<?php

/**
 * LUX EMPIRE
 * Authentication Brute-Force Protection
 *
 * Responsible only for detecting and controlling repeated
 * authentication failures.
 */

declare(strict_types=1);

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Audit.php';

final class BruteForce
{
    /*
     * Number of failed attempts allowed within the observation window.
     */
    private const MAX_ATTEMPTS = 5;

    /*
     * Observation window.
     */
    private const WINDOW_SECONDS = 300; // 5 minutes

    /*
     * Initial restriction after the threshold is reached.
     */
    private const INITIAL_BLOCK_SECONDS = 60; // 1 minute

    /*
     * Maximum restriction.
     */
    private const MAX_BLOCK_SECONDS = 900; // 15 minutes

    /**
     * Determine whether authentication should currently be restricted.
     *
     * Checks:
     * - source IP
     * - normalized email
     * - IP + email combination
     */
    public static function isBlocked(
        string $email,
        string $ip
    ): bool {
        $keys = self::buildKeys(
            $email,
            $ip
        );

        foreach ($keys as $key) {
            if (RateLimiter::isBlocked($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the longest remaining restriction.
     */
    public static function retryAfter(
        string $email,
        string $ip
    ): int {
        $keys = self::buildKeys(
            $email,
            $ip
        );

        $longest = 0;

        foreach ($keys as $key) {
            $remaining = RateLimiter::retryAfter($key);

            if ($remaining > $longest) {
                $longest = $remaining;
            }
        }

        return $longest;
    }

    /**
     * Record a failed authentication attempt.
     *
     * Returns the number of attempts on the most restrictive
     * bucket.
     */
    public static function recordFailure(
        string $email,
        string $ip
    ): int {
        $keys = self::buildKeys(
            $email,
            $ip
        );

        $highestAttempts = 0;

        foreach ($keys as $key) {
            $attempts = RateLimiter::hit(
                $key,
                self::WINDOW_SECONDS
            );

            if ($attempts > $highestAttempts) {
                $highestAttempts = $attempts;
            }

            if ($attempts >= self::MAX_ATTEMPTS) {
                $blockSeconds = self::calculateBlockDuration(
                    $attempts
                );

                RateLimiter::block(
                    $key,
                    $blockSeconds
                );
            }
        }

        Audit::log(
            'Authentication failure recorded.',
            null
        );

        return $highestAttempts;
    }

    /**
     * Clear brute-force state after successful authentication.
     */
    public static function clear(
        string $email,
        string $ip
    ): void {
        $keys = self::buildKeys(
            $email,
            $ip
        );

        foreach ($keys as $key) {
            RateLimiter::reset($key);
        }
    }

    /**
     * Calculate progressive restriction duration.
     */
    private static function calculateBlockDuration(
        int $attempts
    ): int {
        if ($attempts < self::MAX_ATTEMPTS) {
            return 0;
        }

        /*
         * Every additional group of attempts increases
         * the restriction.
         *
         * 5 attempts  = 1 minute
         * 6–7         = 2 minutes
         * 8–9         = 4 minutes
         * 10+         = capped at 15 minutes
         */
        $level = $attempts - self::MAX_ATTEMPTS;

        $seconds = self::INITIAL_BLOCK_SECONDS
            * (2 ** $level);

        return min(
            $seconds,
            self::MAX_BLOCK_SECONDS
        );
    }

    /**
     * Build independent rate-limit buckets.
     */
    private static function buildKeys(
        string $email,
        string $ip
    ): array {
        $normalizedEmail = strtolower(
            trim($email)
        );

        $normalizedIp = trim($ip);

        return [
            'login:ip:' . hash(
                'sha256',
                $normalizedIp
            ),

            'login:email:' . hash(
                'sha256',
                $normalizedEmail
            ),

            'login:ip_email:' . hash(
                'sha256',
                $normalizedIp
                . '|'
                . $normalizedEmail
            ),
        ];
    }
}