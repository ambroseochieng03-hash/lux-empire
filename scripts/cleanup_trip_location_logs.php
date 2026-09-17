<?php

/**
 * LUX EMPIRE
 * Systemd-timer job — deletes trip_location_logs rows older than
 * 30 days. This table gets a new row on every GPS ping during
 * every active trip (see api/maps/update_driver_location.php /
 * update_tenant_location.php) and otherwise grows forever with no
 * natural cap. 30 days is generous for any dispute/audit lookback;
 * older rows have no operational value.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("
    DELETE FROM trip_location_logs
    WHERE created_at < (NOW() - INTERVAL 30 DAY)
");
$stmt->execute();

$deletedCount = $stmt->rowCount();

echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deletedCount} trip_location_logs rows older than 30 days." . PHP_EOL;
