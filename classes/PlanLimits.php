<?php

/**
 * LUX EMPIRE
 * Landlord plan tier + limits.
 *
 * Always checks plan_expires_at live against NOW() rather than
 * trusting users.plan_tier alone — so a lapsed subscription is
 * correctly treated as free even if the (future) daily expiry cron
 * hasn't run yet. plan_tier itself stays useful for admin listing
 * queries and history, but is never the sole source of truth for
 * access control.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class PlanLimits
{
    private const FREE = [
        'tier' => 'free',
        'max_listings' => 3,
        'max_images' => 5,
        'video_allowed' => false,
    ];

    private const PRO = [
        'tier' => 'pro',
        'max_listings' => 15,
        'max_images' => 20,
        'video_allowed' => true,
    ];

    private static function loadRow(int $landlordId): ?array
    {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare("
            SELECT plan_tier, plan_expires_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $landlordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function isPro(int $landlordId): bool
    {
        require_once __DIR__ . '/PaymentWaiver.php';

        if (PaymentWaiver::isWaived($landlordId, 'landlord')) {
            return true;
        }

        $row = self::loadRow($landlordId);

        return $row !== null
            && $row['plan_tier'] === 'pro'
            && $row['plan_expires_at'] !== null
            && strtotime($row['plan_expires_at']) > time();
    }

    public static function forLandlord(int $landlordId): array
    {
        return self::isPro($landlordId) ? self::PRO : self::FREE;
    }

    /**
     * NULL if still active, otherwise a DateTime of when it expired —
     * used by the dashboard card to show "Expired 3 days ago" vs
     * "Renews on ...".
     */
    public static function expiresAt(int $landlordId): ?string
    {
        $row = self::loadRow($landlordId);
        return $row['plan_expires_at'] ?? null;
    }
}
