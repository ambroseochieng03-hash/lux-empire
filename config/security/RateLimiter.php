<?php

/**
 * LUX EMPIRE
 * Rate Limiting
 *
 * Responsible only for generic request rate limiting.
 *
 * Login-specific brute-force policy belongs in BruteForce.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class RateLimiter
{
    /**
     * Determine whether a rate-limit bucket is currently blocked.
     */
    public static function isBlocked(
        string $key
    ): bool {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare(
            "SELECT blocked_until
             FROM rate_limits
             WHERE rate_key = ?
             LIMIT 1"
        );

        $stmt->execute([$key]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['blocked_until'] === null) {
            return false;
        }

        $blockedUntil = strtotime(
            $row['blocked_until']
        );

        if ($blockedUntil === false) {
            return false;
        }

        /*
         * The block has expired.
         */
        if ($blockedUntil <= time()) {
            self::clearBlock($key);

            return false;
        }

        return true;
    }

    /**
     * Get the number of seconds remaining on a block.
     */
    public static function retryAfter(
        string $key
    ): int {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare(
            "SELECT blocked_until
             FROM rate_limits
             WHERE rate_key = ?
             LIMIT 1"
        );

        $stmt->execute([$key]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['blocked_until'] === null) {
            return 0;
        }

        $blockedUntil = strtotime(
            $row['blocked_until']
        );

        if ($blockedUntil === false) {
            return 0;
        }

        return max(
            0,
            $blockedUntil - time()
        );
    }

    /**
     * Register an attempt inside a rate-limit window.
     *
     * Returns the resulting attempt count.
     */
    public static function hit(
        string $key,
        int $windowSeconds
    ): int {
        if ($windowSeconds <= 0) {
            throw new InvalidArgumentException(
                'Rate-limit window must be greater than zero.'
            );
        }

        $database = new Database();
        $pdo = $database->connect();

        $now = time();

        $windowStartedAt = date(
            'Y-m-d H:i:s',
            $now
        );

        /*
         * Insert the bucket if it does not exist.
         */
        $stmt = $pdo->prepare(
            "INSERT INTO rate_limits
                (
                    rate_key,
                    attempts,
                    window_started_at,
                    blocked_until
                )
             VALUES
                (?, 1, ?, NULL)
             ON DUPLICATE KEY UPDATE
                attempts = IF(
                    UNIX_TIMESTAMP(window_started_at)
                    + ?
                    <= UNIX_TIMESTAMP(?),
                    1,
                    attempts + 1
                ),
                window_started_at = IF(
                    UNIX_TIMESTAMP(window_started_at)
                    + ?
                    <= UNIX_TIMESTAMP(?),
                    ?,
                    window_started_at
                )"
        );

        $stmt->execute([
            $key,
            $windowStartedAt,
            $windowSeconds,
            $windowStartedAt,
            $windowSeconds,
            $windowStartedAt,
            $windowStartedAt
        ]);

        /*
         * Retrieve the resulting counter.
         */
        $stmt = $pdo->prepare(
            "SELECT attempts
             FROM rate_limits
             WHERE rate_key = ?
             LIMIT 1"
        );

        $stmt->execute([$key]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Block a rate-limit bucket.
     */
    public static function block(
        string $key,
        int $seconds
    ): void {
        if ($seconds <= 0) {
            throw new InvalidArgumentException(
                'Block duration must be greater than zero.'
            );
        }

        $database = new Database();
        $pdo = $database->connect();

        $blockedUntil = date(
            'Y-m-d H:i:s',
            time() + $seconds
        );

        $stmt = $pdo->prepare(
            "INSERT INTO rate_limits
                (
                    rate_key,
                    attempts,
                    window_started_at,
                    blocked_until
                )
             VALUES
                (?, 0, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                blocked_until = ?"
        );

        $stmt->execute([
            $key,
            $blockedUntil,
            $blockedUntil
        ]);
    }

    /**
     * Clear a rate-limit bucket's block and attempts.
     */
    public static function reset(
        string $key
    ): void {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare(
            "DELETE FROM rate_limits
             WHERE rate_key = ?"
        );

        $stmt->execute([$key]);
    }

    /**
     * Remove only an expired block.
     */
    private static function clearBlock(
        string $key
    ): void {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare(
            "UPDATE rate_limits
             SET blocked_until = NULL
             WHERE rate_key = ?
             AND blocked_until IS NOT NULL
             AND blocked_until <= NOW()"
        );

        $stmt->execute([$key]);
    }
}