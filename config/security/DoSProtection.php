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
 * Buckets (each tracked independently, per profile):
 *   ip          — everything from one IP address. Must be HIGH: a campus
 *                 Wi-Fi or office NAT puts many real people behind one
 *                 address and they all share this bucket.
 *   ip_endpoint — one IP hitting one script.
 *   user        — one logged-in user.
 *
 * Profiles:
 *   default — normal pages and actions.
 *   polling — light endpoints the browser calls on a timer (chat, presence).
 *             They get their OWN counters, so background polling can never
 *             use up the budget of real actions such as "send" or "pay".
 */

declare(strict_types=1);

require_once __DIR__ . '/RedisThrottle.php';
require_once __DIR__ . '/Audit.php';

final class DoSProtection
{
    private const WINDOW_SECONDS = 60;
    private const BLOCK_SECONDS = 300;

    /** Requests allowed per WINDOW_SECONDS, per bucket, per profile. */
    private const LIMITS = [
        'default' => ['ip' => 600, 'ip_endpoint' => 120, 'user' => 90],
        'polling' => ['ip_endpoint' => 3000, 'user' => 300],
    ];

    /**
     * Protect the current request.
     *
     * @param int|null $userId  The authenticated user's id when known, so their
     *                          requests are tracked separately from others
     *                          sharing their IP.
     * @param string   $profile 'default' or 'polling'.
     */
    public static function check(?int $userId = null, string $profile = 'default'): bool
    {
        if (!isset(self::LIMITS[$profile])) {
            $profile = 'default';
        }

        $limits = self::LIMITS[$profile];

        $ip = self::clientIp();
        $endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';

        $dimensions = [];

        if (isset($limits['ip'])) {
            $dimensions['ip'] = hash('sha256', $ip);
        }

        if (isset($limits['ip_endpoint'])) {
            $dimensions['ip_endpoint'] = hash('sha256', $ip . '|' . $endpoint);
        }

        if (isset($limits['user']) && $userId !== null) {
            $dimensions['user'] = (string) $userId;
        }

        // Check existing blocks across all buckets before counting anything.
        foreach ($dimensions as $name => $suffix) {
            $blockKey = "dos:block:{$profile}:{$name}:{$suffix}";

            if (RedisThrottle::isBlocked($blockKey)) {
                self::reject(RedisThrottle::retryAfter($blockKey));
                return false;
            }
        }

        // Register this request against every bucket.
        foreach ($dimensions as $name => $suffix) {
            $countKey = "dos:cnt:{$profile}:{$name}:{$suffix}";
            $attempts = RedisThrottle::incrWithExpiry($countKey, self::WINDOW_SECONDS);

            if ($attempts > $limits[$name]) {
                RedisThrottle::block("dos:block:{$profile}:{$name}:{$suffix}", self::BLOCK_SECONDS);

                Audit::log("Application DoS protection triggered ({$profile}/{$name}) for IP: {$ip}");

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