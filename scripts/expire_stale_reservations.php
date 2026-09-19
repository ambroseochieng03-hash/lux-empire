<?php

/**
 * LUX EMPIRE
 * Declines paid booking requests the landlord never answered within
 * RESERVATION_RESPONSE_HOURS: releases the house, queues the automatic
 * refund, and tells the tenant and the landlord.
 *
 * Usage:
 *   php scripts/expire_stale_reservations.php            # do it
 *   php scripts/expire_stale_reservations.php --dry-run  # only report
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/EmailJobPublisher.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$hours = (int) RESERVATION_RESPONSE_HOURS;

$conn = (new Database())->connect();

$stale = $conn->query("
    SELECT b.id, b.landlord_id, b.tenant_id, b.payment_id,
           COALESCE(h.title, b.house_title_snapshot) AS title
    FROM bookings b
    LEFT JOIN houses h ON b.house_id = h.id
    WHERE b.status = 'pending'
    AND b.payment_status = 'paid'
    AND b.booking_date < (NOW() - INTERVAL {$hours} HOUR)
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($stale)) {
    echo "[" . date('Y-m-d H:i:s') . "] No unanswered reservations older than {$hours}h." . PHP_EOL;
    exit(0);
}

$bookingModel = new Booking();
$paymentModel = new Payment();
$notification = new Notification();

foreach ($stale as $row) {

    $bookingId = (int) $row['id'];
    $title = (string) ($row['title'] ?? 'the property');

    if ($dryRun) {
        echo "[" . date('Y-m-d H:i:s') . "] DRY RUN: would decline booking #{$bookingId} (\"{$title}\")." . PHP_EOL;
        continue;
    }

    try {

        // Same code path as the landlord clicking Reject: the booking is
        // rejected and the house goes back to 'available' in one transaction.
        $result = $bookingModel->rejectBooking($bookingId, (int) $row['landlord_id']);

        if (!$result['success']) {
            echo "[" . date('Y-m-d H:i:s') . "] Skipped booking #{$bookingId}: " . $result['message'] . PHP_EOL;
            continue;
        }

        if (($result['payment_status'] ?? '') === 'paid' && !empty($result['payment_id'])) {
            $paymentModel->createAutoRefundForPayment(
                (int) $result['payment_id'],
                'booking_rejected',
                ['booking_id' => $bookingId, 'house_id' => $result['house_id'], 'auto_declined' => true]
            );
        }

        $notification->create(
            (int) $row['tenant_id'],
            'booking_rejected_refund',
            'Booking Expired — Refund Processing',
            'The landlord did not respond to your request for "' . $title . '" within ' . $hours . ' hours, so it was cancelled. Your booking fee is being refunded automatically to your M-Pesa — you\'ll get a confirmation once it completes.',
            BASE_URL . '/tenant/my-bookings'
        );

        $notification->create(
            (int) $row['landlord_id'],
            'booking_expired',
            'Booking Request Expired',
            'A booking request for "' . $title . '" was declined automatically because it was not answered within ' . $hours . ' hours. The property is available again.',
            BASE_URL . '/booking-requests'
        );

        $tenantStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $tenantStmt->execute([(int) $row['tenant_id']]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            EmailJobPublisher::publish('email.booking_rejected', [
                'email' => $tenant['email'],
                'name' => $tenant['full_name'],
                'house_title' => $title,
            ]);
        }

        echo "[" . date('Y-m-d H:i:s') . "] Declined unanswered booking #{$bookingId} (\"{$title}\"), refund queued." . PHP_EOL;

    } catch (Throwable $e) {
        error_log('LUX EMPIRE expire_stale_reservations failed for booking #' . $bookingId . ': ' . $e->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] FAILED booking #{$bookingId}: " . $e->getMessage() . PHP_EOL;
    }
}
