<?php

/**
 * LUX EMPIRE
 * Application-Level DoS Protection
 *
 * Responsible for detecting and limiting abusive request patterns
 * that could exhaust application resources.
 *
 * This is NOT a network-level DDoS mitigation system.
 *
 * Backed by Redis, not MariaDB, because this runs on EVERY request —
 * it needs to be fast, and losing counters on a Redis restart is an
 * acceptable trade-off for a short-lived abuse signal.
 *
 * Multi-dimensional: a single shared public IP (campus Wi-Fi, office
 * NAT, mobile carrier) can no longer trip the bucket for everyone
 * behind it, because the IP+endpoint bucket and the per-user bucket
 * (when a user is authenticated) are tracked independently.
 */

declare(strict_types=1);

require_once __DIR__ . '/RedisThrottle.php';
require_once __DIR__ . '/Audit.php';

final class DoSProtection
{
    private const MAX_REQUESTS = 60;
    private const WINDOW_SECONDS = 60;
    private const BLOCK_SECONDS = 300;

    /**
     * Protect the current request.
     *
     * @param int|null $userId Pass the authenticated user's id when
     *                         known (from $_SESSION, after
     *                         Session::start()) so their requests are
     *                         tracked separately from others sharing
     *                         their IP. Safe to omit — falls back to
     *                         IP + endpoint only.
     */
    public static function check(?int $userId = null): bool
    {
        $ip = self::clientIp();
        $endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';

        $dimensions = [
            'ip'          => hash('sha256', $ip),
            'ip_endpoint' => hash('sha256', $ip . '|' . $endpoint),
        ];

        if ($userId !== null) {
            $dimensions['user'] = (string) $userId;
        }

        // Check existing blocks across all dimensions before counting anything.
        foreach ($dimensions as $name => $suffix) {
            $blockKey = "dos:block:{$name}:{$suffix}";

            if (RedisThrottle::isBlocked($blockKey)) {
                self::reject(RedisThrottle::retryAfter($blockKey));
                return false;
            }
        }

        // Register this request against every dimension.
        foreach ($dimensions as $name => $suffix) {
            $countKey = "dos:cnt:{$name}:{$suffix}";
            $attempts = RedisThrottle::incrWithExpiry($countKey, self::WINDOW_SECONDS);

            if ($attempts > self::MAX_REQUESTS) {
                RedisThrottle::block("dos:block:{$name}:{$suffix}", self::BLOCK_SECONDS);

                Audit::log("Application DoS protection triggered ({$name}) for IP: {$ip}");

                self::reject(self::BLOCK_SECONDS);
                return false;
            }
        }

        return true;
    }

    private static function reject(int $retryAfter): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . max(1, $retryAfter));

        echo json_encode(
            ['success' => false, 'error' => 'Too many requests. Please try again later.'],
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!is_string($ip) || $ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'unknown';
        }

        return $ip;
    }
}