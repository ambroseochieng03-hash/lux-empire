<?php

declare(strict_types=1);

require_once __DIR__ . '/../RedisConnection.php';

final class RedisThrottle
{
    /** Atomic "only one caller gets this" lock. True = you got it. */
    public static function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return (bool) RedisConnection::get()->set($key, '1', ['nx', 'ex' => $ttlSeconds]);
    }

    public static function retryAfter(string $key): int
    {
        $ttl = RedisConnection::get()->ttl($key);
        return $ttl > 0 ? $ttl : 0;
    }

    /**
     * Atomically increment a counter, setting its expiry only on the
     * very first increment. INCR is atomic and returns a unique
     * sequential value per call, so exactly one caller will ever see
     * 1 — no transaction needed to make this safe.
     */
    public static function incrWithExpiry(string $key, int $ttlSeconds): int
    {
        $redis = RedisConnection::get();
        $count = (int) $redis->incr($key);

        if ($count === 1) {
            $redis->expire($key, $ttlSeconds);
        }

        return $count;
    }

    /** Set an explicit block for a fixed duration, independent of any counter. */
    public static function block(string $key, int $ttlSeconds): void
    {
        RedisConnection::get()->setex($key, $ttlSeconds, '1');
    }

    public static function isBlocked(string $key): bool
    {
        return (bool) RedisConnection::get()->exists($key);
    }

    /** Increment a plain gauge counter (no expiry) — for "how many X are in flight". */
    public static function increment(string $key): int
    {
        return (int) RedisConnection::get()->incr($key);
    }

    /** Decrement a gauge counter, deleting it once it hits zero so it doesn't linger forever. */
    public static function decrement(string $key): int
    {
        $redis = RedisConnection::get();
        $value = (int) $redis->decr($key);

        if ($value <= 0) {
            $redis->del($key);
            return 0;
        }

        return $value;
    }

    public static function getCount(string $key): int
    {
        $value = RedisConnection::get()->get($key);
        return $value !== false ? (int) $value : 0;
    }
}