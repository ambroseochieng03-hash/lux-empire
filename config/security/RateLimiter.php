<?php

/**
 * LUX EMPIRE
 * Rate Limiting
 *
 * Same public interface as the original MariaDB-backed version
 * (isBlocked, retryAfter, hit, block, reset) — every existing caller
 * (BruteForce.php, and anything else) keeps working unchanged.
 * Backend is now Redis: a rolling attempt counter and an explicit
 * block are separate keys, each with its own TTL, so an expired
 * block simply stops existing rather than needing to be manually
 * noticed and cleared (the old clearBlock() is gone — Redis's own
 * expiry does that job for free).
 *
 * Login-specific brute-force policy belongs in BruteForce.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../RedisConnection.php';
require_once __DIR__ . '/RedisThrottle.php';

final class RateLimiter
{
    private const COUNT_PREFIX = 'ratelimit:cnt:';
    private const BLOCK_PREFIX = 'ratelimit:block:';

    /**
     * Determine whether a rate-limit bucket is currently blocked.
     */
    public static function isBlocked(string $key): bool
    {
        return RedisThrottle::isBlocked(self::BLOCK_PREFIX . $key);
    }

    /**
     * Get the number of seconds remaining on a block.
     */
    public static function retryAfter(string $key): int
    {
        return RedisThrottle::retryAfter(self::BLOCK_PREFIX . $key);
    }

    /**
     * Register an attempt inside a rate-limit window.
     *
     * Returns the resulting attempt count. INCR + conditional EXPIRE
     * (only set on the very first increment) is atomic per-key in
     * Redis — no race between two simultaneous callers on the same
     * key, same guarantee the old single UPDATE statement gave.
     */
    public static function hit(string $key, int $windowSeconds): int
    {
        if ($windowSeconds <= 0) {
            throw new InvalidArgumentException(
                'Rate-limit window must be greater than zero.'
            );
        }

        return RedisThrottle::incrWithExpiry(self::COUNT_PREFIX . $key, $windowSeconds);
    }

    /**
     * Block a rate-limit bucket.
     */
    public static function block(string $key, int $seconds): void
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException(
                'Block duration must be greater than zero.'
            );
        }

        RedisThrottle::block(self::BLOCK_PREFIX . $key, $seconds);
    }

    /**
     * Clear a rate-limit bucket's block and attempts.
     */
    public static function reset(string $key): void
    {
        $redis = RedisConnection::get();
        $redis->del(self::COUNT_PREFIX . $key);
        $redis->del(self::BLOCK_PREFIX . $key);
    }
}