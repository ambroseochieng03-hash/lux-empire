<?php

/**
 * LUX EMPIRE
 * Cron job — media cleanup for accepted bookings.
 *
 * Once a landlord has accepted a booking (houses.status = 'booked') and
 * LANDLORD_BOOKED_VISIBLE_HOURS have passed, the listing is hidden from
 * the landlord and the admin, so its photos/video have no purpose. This
 * script deletes those media files from disk and their house_images rows.
 *
 * The houses row itself is NEVER deleted (and neither are its bookings),
 * so history and future disputes still have something to point at.
 *
 * Usage:
 *   php scripts/cleanup_expired_listings.php            # do it
 *   php scripts/cleanup_expired_listings.php --dry-run  # only report
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/RedisConnection.php';
require_once __DIR__ . '/../classes/House.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$hours = (int) LANDLORD_BOOKED_VISIBLE_HOURS;

$database = new Database();
$pdo = $database->connect();

$expiredHouses = $pdo->query("
    SELECT h.id, h.title, h.landlord_id
    FROM houses h
    WHERE h.status = 'booked'
    AND h.booked_at IS NOT NULL
    AND h.booked_at < (NOW() - INTERVAL {$hours} HOUR)
    AND EXISTS (SELECT 1 FROM house_images hi WHERE hi.house_id = h.id)
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($expiredHouses)) {
    echo "[" . date('Y-m-d H:i:s') . "] No expired booked listings with media to clean up." . PHP_EOL;
    exit(0);
}

$houseModel = new House();

foreach ($expiredHouses as $expired) {

    $houseId = (int) $expired['id'];
    $media = $houseModel->getHouseMedia($houseId);

    if ($dryRun) {
        echo "[" . date('Y-m-d H:i:s') . "] DRY RUN: would delete " . count($media) . " media file(s) for listing #{$houseId} (\"{$expired['title']}\")." . PHP_EOL;
        continue;
    }

    $deletedCount = 0;

    foreach ($media as $item) {
        try {
            if ($houseModel->deleteMedia((int) $item['id'])) {
                $deletedCount++;
            }
        } catch (Throwable $e) {
            error_log('LUX EMPIRE cleanup_expired_listings: media #' . $item['id'] . ' of house #' . $houseId . ' failed — ' . $e->getMessage());
        }
    }

    try {
        RedisConnection::get()->del("cache:house:{$houseId}");
    } catch (Throwable $e) {
        // Cache invalidation failing is not fatal.
    }

    echo "[" . date('Y-m-d H:i:s') . "] Removed {$deletedCount}/" . count($media) . " media file(s) for listing #{$houseId} (\"{$expired['title']}\"), landlord #{$expired['landlord_id']}. House row kept." . PHP_EOL;
}