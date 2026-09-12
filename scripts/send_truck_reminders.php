<?php

/**
 * LUX EMPIRE
 * Truck reminder sweep — run periodically via cron (every 10-15
 * minutes recommended), NOT as a long-running process. Time-driven,
 * not event-driven: nothing "happens" to trigger a reminder, so a
 * queue/worker model doesn't fit here the way OTP/media jobs do.
 *
 * For every ACCEPTED scheduled trip in the future:
 *   - once per calendar day: a reminder email to the driver
 *   - once, when within 1 hour of scheduled_at: an "it's soon" email
 *     to both driver and tenant
 * Each reminder type has its own *_sent_at column so a trip already
 * reminded today (or already given its hour-before notice) is never
 * reminded twice, even if this script runs many times per day.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/EmailJobPublisher.php';
require_once __DIR__ . '/../classes/Notification.php';

$database = new Database();
$pdo = $database->connect();

$notification = new Notification();

$stmt = $pdo->query("
    SELECT
        tr.id, tr.pickup_location, tr.destination, tr.scheduled_at,
        tr.last_daily_reminder_sent_at, tr.hour_reminder_sent_at, tr.tenant_reminder_sent_at,
        tenant.id AS tenant_id, tenant.full_name AS tenant_name, tenant.email AS tenant_email,
        driver.id AS driver_id, driver.full_name AS driver_name, driver.email AS driver_email
    FROM truck_requests tr
    JOIN users tenant ON tr.tenant_id = tenant.id
    JOIN users driver ON tr.driver_id = driver.id
    WHERE tr.trip_type = 'scheduled'
    AND tr.status = 'accepted'
    AND tr.scheduled_at > NOW()
");

$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sentCount = 0;

foreach ($trips as $trip) {

    $scheduledAtTimestamp = strtotime($trip['scheduled_at']);
    $secondsUntil = $scheduledAtTimestamp - time();
    $today = date('Y-m-d');

    /*
     * DAILY driver reminder — once per calendar day, every day
     * until the move.
     */
    if ($trip['last_daily_reminder_sent_at'] !== $today) {

        EmailJobPublisher::publish('email.truck_daily_reminder', [
            'email' => $trip['driver_email'],
            'name' => $trip['driver_name'],
            'pickup_location' => $trip['pickup_location'],
            'destination' => $trip['destination'],
            'scheduled_at' => $trip['scheduled_at'],
        ]);

        $notification->create(
            (int) $trip['driver_id'],
            'truck_daily_reminder',
            'Upcoming Scheduled Move',
            'Reminder: you have a scheduled move from "' . $trip['pickup_location'] . '" to "' . $trip['destination'] . '" on ' . date('M d, Y g:i A', $scheduledAtTimestamp) . '.',
            BASE_URL . '/driver/active-trip'
        );

        $update = $pdo->prepare("UPDATE truck_requests SET last_daily_reminder_sent_at = :today WHERE id = :id");
        $update->execute([':today' => $today, ':id' => $trip['id']]);

        $sentCount++;
    }

    /*
     * ONE-HOUR-BEFORE reminders — driver AND tenant, each sent
     * exactly once, only once we're within 60 minutes of the
     * scheduled time.
     */
    if ($secondsUntil <= 3600 && $trip['hour_reminder_sent_at'] === null) {

        EmailJobPublisher::publish('email.truck_hour_reminder', [
            'email' => $trip['driver_email'],
            'name' => $trip['driver_name'],
            'pickup_location' => $trip['pickup_location'],
            'destination' => $trip['destination'],
            'scheduled_at' => $trip['scheduled_at'],
        ]);

        $notification->create(
            (int) $trip['driver_id'],
            'truck_hour_reminder',
            'Move Starting Soon',
            'Your scheduled move from "' . $trip['pickup_location'] . '" to "' . $trip['destination'] . '" starts in about an hour.',
            BASE_URL . '/driver/active-trip'
        );

        $update = $pdo->prepare("UPDATE truck_requests SET hour_reminder_sent_at = NOW() WHERE id = :id");
        $update->execute([':id' => $trip['id']]);

        $sentCount++;
    }

    if ($secondsUntil <= 3600 && $trip['tenant_reminder_sent_at'] === null) {

        EmailJobPublisher::publish('email.truck_tenant_reminder', [
            'email' => $trip['tenant_email'],
            'name' => $trip['tenant_name'],
            'driver_name' => $trip['driver_name'],
            'pickup_location' => $trip['pickup_location'],
            'destination' => $trip['destination'],
            'scheduled_at' => $trip['scheduled_at'],
        ]);

        $notification->create(
            (int) $trip['tenant_id'],
            'truck_tenant_reminder',
            'Your Move Starts Soon',
            'Your scheduled move to "' . $trip['destination'] . '" starts in about an hour. ' . $trip['driver_name'] . ' will be arriving soon.',
            BASE_URL . '/tenant/track-driver'
        );

        $update = $pdo->prepare("UPDATE truck_requests SET tenant_reminder_sent_at = NOW() WHERE id = :id");
        $update->execute([':id' => $trip['id']]);

        $sentCount++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Truck reminder sweep complete — {$sentCount} reminder(s) sent across " . count($trips) . " upcoming scheduled trip(s)." . PHP_EOL;
