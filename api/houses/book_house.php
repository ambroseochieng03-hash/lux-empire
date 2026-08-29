<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';
require_once '../../classes/Booking.php';
require_once '../../classes/Notification.php';
require_once '../../config/app.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

header('Content-Type: application/json');

/**
 * General application-level abuse protection (existing, IP-based).
 */
DoSProtection::check();

/**
 * This is now a state-changing AJAX action — POST only.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenant_id = (int) Session::user()['id'];
$tenant_name = Session::user()['full_name'] ?? 'A tenant';

/**
 * Booking-specific throttle, in addition to the general DoS
 * protection above. This is deliberately tighter and scoped per
 * authenticated tenant (rather than per IP), since a single
 * misbehaving/looping client could otherwise stay under the
 * IP-wide DoS threshold while still hammering this one endpoint.
 * 15 attempts / 60s, then a 2 minute cool-down — generous enough
 * for normal use (nobody legitimately submits more than a couple
 * of booking requests a minute) while stopping automated flooding.
 */
$rateKey = 'book_house:' . $tenant_id;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many booking attempts. Please wait a moment and try again.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 60);

if ($attempts > 15) {
    RateLimiter::block($rateKey, 120);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many booking attempts. Please wait a moment and try again.']);
    exit;
}

/**
 * Validate house ID
 */
$house_id = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);

if (!$house_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property.']);
    exit;
}

/**
 * Get house
 */
$houseModel = new House();

$house = $houseModel->getHouseById($house_id);

if (!$house) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Property not found.']);
    exit;
}

/**
 * Prevent landlord booking own house
 */
if ((int) $house['landlord_id'] === $tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You cannot book your own property.']);
    exit;
}

/**
 * Create booking (transactional — see Booking::createBooking()).
 */
$bookingModel = new Booking();

$result = $bookingModel->createBooking(
    $tenant_id,
    $house_id,
    (int) $house['landlord_id']
);

/**
 * Handle response
 */
if ($result === true) {

    $notification = new Notification();

    $notification->create(
        (int) $house['landlord_id'],
        'new_booking_request',
        'New Booking Request',
        $tenant_name . ' has requested to book "' . $house['title'] . '".',
        BASE_URL . '/booking-requests'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your booking request has been submitted.',
        'status' => 'pending',
        'house_id' => $house_id
    ]);
    exit;

}

/**
 * $result is a user-safe error string returned by Booking::createBooking().
 */
http_response_code(409);
echo json_encode(['success' => false, 'message' => $result]);
exit;