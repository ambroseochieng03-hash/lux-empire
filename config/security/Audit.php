<?php

/**
 * LUX EMPIRE
 * Security Audit Logging
 *
 * Responsible only for recording security/application events.
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';

final class Audit
{
    /**
     * Record an audit event.
     *
     * @param string   $activity Description of the event.
     * @param int|null $userId   Associated user, if available.
     */
    public static function log(
        string $activity,
        ?int $userId = null
    ): void {
        try {
            $database = new Database();
            $pdo = $database->connect();

            $ipAddress = self::getClientIp();

            $stmt = $pdo->prepare(
                "INSERT INTO activity_logs
                    (user_id, activity, ip_address)
                 VALUES
                    (?, ?, ?)"
            );

            $stmt->execute([
                $userId,
                $activity,
                $ipAddress
            ]);
        } catch (Throwable $e) {
            /*
             * Audit logging must never bring down the application.
             *
             * Do not expose database/security errors to the client.
             */
            error_log(
                'LUX EMPIRE Audit failure: '
                . $e->getMessage()
            );
        }
    }

    /**
     * Record an event for the currently authenticated user.
     */
    public static function user(
        string $activity
    ): void {
        $user = Session::user();

        $userId = null;

        if (
            is_array($user)
            && isset($user['id'])
        ) {
            $userId = (int) $user['id'];
        }

        self::log(
            $activity,
            $userId
        );
    }

    /**
     * Obtain the client IP address.
     *
     * We intentionally do not blindly trust X-Forwarded-For.
     */
    private static function getClientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (
            !is_string($ip)
            || $ip === ''
        ) {
            return null;
        }

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            return null;
        }

        return $ip;
    }
}
