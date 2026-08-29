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

/**
 * Previously had NO CSRF check at all — closing that gap here,
 * matching the convention used by every other booking endpoint.
 */
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

/*
|--------------------------------------------------------------------------
| VERIFY BOOKING — ownership check derived from session, never from
| client-supplied tenant_id.
|--------------------------------------------------------------------------
*/

$booking = $bookingModel->getBookingById($booking_id);

if (!$booking || (int) $booking['tenant_id'] !== $tenantId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized booking.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE BOOKING
|--------------------------------------------------------------------------
*/

$deleted = $bookingModel->deleteBooking($booking_id, $tenantId);

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Booking deleted successfully.',
        'booking_id' => $booking_id
    ]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to delete booking.']);
exit;