<?php

/**
 * LUX EMPIRE
 * Request Throttling
 *
 * Responsible only for introducing controlled delays.
 *
 * It does not:
 * - authenticate users
 * - perform rate limiting
 * - block IP addresses
 * - validate CSRF
 * - handle CAPTCHA
 */

declare(strict_types=1);

final class Throttle
{
    /**
     * Minimum delay in milliseconds.
     */
    private const MIN_DELAY_MS = 100;

    /**
     * Maximum delay in milliseconds.
     */
    private const MAX_DELAY_MS = 2000;

    /**
     * Apply a delay based on the number of failures.
     */
    public static function delay(
        int $failures
    ): void {
        if ($failures <= 0) {
            return;
        }

        $milliseconds = self::calculateDelay(
            $failures
        );

        usleep(
            $milliseconds * 1000
        );
    }

    /**
     * Calculate progressive delay.
     */
    public static function calculateDelay(
        int $failures
    ): int {
        if ($failures <= 0) {
            return 0;
        }

        /*
         * Exponential backoff:
         *
         * 1 failure  = 100ms
         * 2 failures = 200ms
         * 3 failures = 400ms
         * 4 failures = 800ms
         * 5+         = 1600ms+
         *
         * The maximum is capped to prevent excessive
         * server-side resource consumption.
         */
        $delay = self::MIN_DELAY_MS
            * (2 ** ($failures - 1));

        return min(
            $delay,
            self::MAX_DELAY_MS
        );
    }
}