<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * LUX EMPIRE
 * A standing "you have unseen good news" flag, not a live event — so it reaches a
 * tenant whether they were logged in at the moment of approval or log in a week
 * later. One row per approved booking; shown exactly once, ever.
 */
final class BookingCelebration
{
    private static function connect(): PDO
    {
        return (new Database())->connect();
    }

    public static function create(int $bookingId, int $tenantId, string $houseTitle): void
    {
        try {
            self::connect()->prepare("
                INSERT IGNORE INTO booking_celebrations (booking_id, tenant_id, house_title)
                VALUES (:booking_id, :tenant_id, :title)
            ")->execute([':booking_id' => $bookingId, ':tenant_id' => $tenantId, ':title' => $houseTitle]);
        } catch (Throwable $e) {
            error_log('LUX EMPIRE BookingCelebration::create failed: ' . $e->getMessage());
        }
    }

    /**
     * Returns the oldest unseen celebration for this tenant and marks it seen in the
     * SAME call, so a slow client or a double request can never show it twice.
     */
    public static function claimNext(int $tenantId): ?array
    {
        $pdo = self::connect();

        $stmt = $pdo->prepare("
            SELECT id, booking_id, house_title
            FROM booking_celebrations
            WHERE tenant_id = :tenant_id AND shown_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $update = $pdo->prepare("UPDATE booking_celebrations SET shown_at = NOW() WHERE id = :id AND shown_at IS NULL");
        $update->execute([':id' => $row['id']]);

        // Someone else's request claimed it a moment before this one — nothing to show now.
        if ($update->rowCount() === 0) {
            return null;
        }

        return $row;
    }
}
