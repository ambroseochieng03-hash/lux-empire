<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';
require_once '../../config/app.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

$rateKey = 'delete_booking:' . $tenantId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 60);

if ($attempts > 20) {
    RateLimiter::block($rateKey, 120);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

$bookingModel = new Booking();

$booking = $bookingModel->getBookingById($booking_id);

if (!$booking || (int) $booking['tenant_id'] !== $tenantId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized booking.']);
    exit;
}

/*
 * A live request can never be "deleted" — that would leave the house
 * reserved and the fee unrefunded. It has to be cancelled first, which
 * frees the house and refunds the fee.
 */
if ($booking['status'] === 'pending') {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'This request is still waiting for the landlord. Cancel it first (your fee is refunded), then you can remove it from your list.'
    ]);
    exit;
}

/*
 * Soft delete: hides the booking from THIS tenant's list only. The row
 * stays for the landlord's history, admin oversight and payment disputes.
 */
if ($bookingModel->hideBookingForTenant($booking_id, $tenantId)) {
    echo json_encode([
        'success' => true,
        'message' => 'Removed from your list.',
        'booking_id' => $booking_id
    ]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to remove this booking.']);
exit;