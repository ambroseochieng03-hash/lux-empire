<?php

/**
 * LUX EMPIRE
 * Cron job — permanently removes listings that have been booked for
 * more than 48 hours, including their physical media files on disk.
 *
 * Reuses House::deleteHouse() unmodified (that class is frozen) —
 * it already fetches the house's media rows before deleting, then
 * removes the physical files afterward, so nothing here duplicates
 * that logic.
 *
 * IMPORTANT: deleting the house cascades (ON DELETE CASCADE per
 * schema.sql) to its bookings row too. The completed booking's
 * history is therefore also permanently gone from the admin
 * Bookings oversight page once this runs — this script does not
 * archive it first. If a permanent audit record of completed
 * bookings is ever needed, add an INSERT into a booking_archive
 * table here, before the deleteHouse() call below.
 *
 * Suggested cron entry (runs every 15 minutes):
 *   /15 * * * * php /path/to/lux-empire/scripts/cleanup_expired_listings.php >> /path/to/lux-empire/logs/cleanup.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/House.php';

$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("
    SELECT id, title, landlord_id
    FROM houses
    WHERE status = 'booked'
    AND booked_at IS NOT NULL
    AND booked_at < (NOW() - INTERVAL 48 HOUR)
");
$stmt->execute();
$expiredHouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($expiredHouses)) {
    echo "[" . date('Y-m-d H:i:s') . "] No expired booked listings to clean up." . PHP_EOL;
    exit(0);
}

$houseModel = new House();

foreach ($expiredHouses as $expired) {

    try {
        $deleted = $houseModel->deleteHouse((int) $expired['id']);

        if ($deleted) {
            echo "[" . date('Y-m-d H:i:s') . "] Deleted expired listing #{$expired['id']} (\"{$expired['title']}\") for landlord #{$expired['landlord_id']}." . PHP_EOL;
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Listing #{$expired['id']} was already gone." . PHP_EOL;
        }
    } catch (Throwable $e) {
        error_log('LUX EMPIRE cleanup_expired_listings failed for house #' . $expired['id'] . ': ' . $e->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] FAILED to delete listing #{$expired['id']}: " . $e->getMessage() . PHP_EOL;
    }
}
