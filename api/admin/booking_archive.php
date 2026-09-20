<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminBookingService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$bookingId) {
    adminJsonError('Invalid booking id.');
}

if ($reason === '') {
    adminJsonError('A reason is required to archive a booking.');
}

$service = new AdminBookingService();
$result = $service->archiveBooking($bookingId, $currentAdminId, $reason);

if (!$result['success']) {
    adminJsonError($result['message'], $result['code'] ?? 400);
}

adminJsonResponse(['status' => 'archived']);
