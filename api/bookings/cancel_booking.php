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

/**
 * General application-level abuse protection (existing, IP-based).
 */
DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

/**
 * This endpoint previously had NO CSRF check at all — closing that
 * gap here, using the same convention as every other booking AJAX
 * endpoint (Csrf::requireValid($_POST['csrf_token'] ?? null)).
 */
Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

/**
 * Booking-specific throttle, scoped per tenant. Same shape/reasoning
 * as the book_house.php throttle: generous for normal use, tight
 * enough to stop automated flooding of this one endpoint.
 */
$rateKey = 'cancel_booking:' . $tenantId;

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
| VERIFY BOOKING — never trust the booking_id alone; it must belong
| to the authenticated tenant (derived from the session, never from
| client input).
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
| ONLY A PENDING BOOKING CAN BE CANCELLED
|--------------------------------------------------------------------------
*/

if ($booking['status'] !== 'pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Only pending bookings can be cancelled.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL BOOKING
|--------------------------------------------------------------------------
*/

$cancelled = $bookingModel->tenantUpdateStatus($booking_id, 'cancelled');

if ($cancelled) {
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully.',
        'booking_id' => $booking_id
    ]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to cancel booking.']);
exit;