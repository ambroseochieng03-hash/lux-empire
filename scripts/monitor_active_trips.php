<?php

/**
 * LUX EMPIRE
 * Watchdog for trips 'in_transit' whose driver has gone silent (no location ping
 * in STALE_LOCATION_ALERT_MINUTES). Raises it automatically into the SAME
 * emergency_alerts table your Emergencies admin page already watches, so a driver
 * who stops sending location updates is caught by the system, not only by a
 * worried tenant calling in. Never fires twice for the same silence — it only
 * raises a new alert once the previous one has been closed by an admin
 * (status moves out of active/responding) or the trip's status changes.
 *
 * Usage: php scripts/monitor_active_trips.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Notification.php';

$conn = (new Database())->connect();
$minutes = (int) STALE_LOCATION_ALERT_MINUTES;

$stale = $conn->prepare("
    SELECT tr.id AS trip_id, tr.driver_id, tr.tenant_id, tr.destination, dl.updated_at
    FROM truck_requests tr
    LEFT JOIN driver_locations dl ON dl.driver_id = tr.driver_id
    WHERE tr.status = 'in_transit'
    AND (dl.updated_at IS NULL OR dl.updated_at < (NOW() - INTERVAL :minutes MINUTE))
    AND NOT EXISTS (
        SELECT 1 FROM emergency_alerts ea
        WHERE ea.trip_id = tr.id
        AND ea.message LIKE 'AUTO:%'
        AND ea.status IN ('active', 'responding')
    )
");
$stale->bindValue(':minutes', $minutes, PDO::PARAM_INT);
$stale->execute();

$rows = $stale->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "[" . date('Y-m-d H:i:s') . "] No silent trips found." . PHP_EOL;
    exit(0);
}

foreach ($rows as $row) {

    $lastSeen = $row['updated_at'] ? date('d M H:i', strtotime($row['updated_at'])) : 'never';

    $insert = $conn->prepare("
        INSERT INTO emergency_alerts (user_id, role, trip_id, message, status)
        VALUES (:driver_id, 'driver', :trip_id, :message, 'active')
    ");
    $insert->execute([
        ':driver_id' => $row['driver_id'],
        ':trip_id' => $row['trip_id'],
        ':message' => "AUTO: No location update from this driver for over {$minutes} minutes while a trip is in transit to \"" . $row['destination'] . "\". Last known location: {$lastSeen}. This is an automatic system flag, not a report from the driver or tenant.",
    ]);

    $alertId = (int) $conn->lastInsertId();

    $conn->prepare("
        INSERT INTO emergency_activity_logs (emergency_id, activity)
        VALUES (:id, 'Automatically raised by scripts/monitor_active_trips.php — driver location has gone silent.')
    ")->execute([':id' => $alertId]);

    error_log('LUX EMPIRE monitor_active_trips: raised alert #' . $alertId . ' for trip #' . $row['trip_id'] . ' (driver #' . $row['driver_id'] . ', last seen ' . $lastSeen . ')');

    echo "[" . date('Y-m-d H:i:s') . "] Flagged trip #{$row['trip_id']} — driver #{$row['driver_id']} silent since {$lastSeen}." . PHP_EOL;
}
