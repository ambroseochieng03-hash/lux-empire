<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/House.php';
require_once '../../classes/Booking.php';
require_once '../../classes/Notification.php';
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

$landlord_id = (int) Session::user()['id'];

/**
 * Booking-action throttle, scoped per landlord. Landlords may
 * legitimately process a burst of requests in a row (e.g. clearing
 * a queue), so this is more generous than the tenant booking
 * throttle: 30 actions / 60s, then a 2 minute cool-down.
 */
$rateKey = 'booking_action:' . $landlord_id;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many actions. Please wait a moment and try again.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 60);

if ($attempts > 30) {
    RateLimiter::block($rateKey, 120);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many actions. Please wait a moment and try again.']);
    exit;
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$action     = $_POST['action'] ?? null;

$allowedActions = ['accept', 'reject'];

if (!$booking_id || !in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$bookingModel = new Booking();
$houseModel   = new House();
$notification = new Notification();

if ($action === 'accept') {

    $result = $bookingModel->acceptBooking($booking_id, $landlord_id);

    if (!$result['success']) {
        http_response_code(409);
        echo json_encode($result);
        exit;
    }

    $house = $houseModel->getHouseById($result['house_id']);
    $houseTitle = $house['title'] ?? 'the property';

    /**
     * Notifications only happen after the transaction inside
     * acceptBooking() has already committed successfully.
     */
    $notification->create(
        $result['tenant_id'],
        'booking_approved',
        'Booking Approved',
        'Your booking for "' . $houseTitle . '" has been approved by the landlord.',
        BASE_URL . '/tenant/my-bookings'
    );

    foreach ($result['rejected'] as $competitor) {
        $notification->create(
            $competitor['tenant_id'],
            'booking_rejected',
            'Booking Rejected',
            'Your booking request for "' . $houseTitle . '" was not accepted because another tenant\'s request was accepted by the landlord.',
            BASE_URL . '/tenant/my-bookings'
        );
    }

    $notification->create(
        $landlord_id,
        'booking_accepted_confirmation',
        'Request Accepted',
        'You approved a booking request for "' . $houseTitle . '". All other pending requests for this property were automatically declined.',
        BASE_URL . '/booking-requests'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Booking approved. Competing requests were automatically declined.',
        'action' => 'approved',
        'booking_id' => $result['booking_id'],
        'house_id' => $result['house_id'],
        'rejected_ids' => array_map(
            static function ($row) {
                return $row['booking_id'];
            },
            $result['rejected']
        )
    ]);
    exit;
}

if ($action === 'reject') {

    $result = $bookingModel->rejectBooking($booking_id, $landlord_id);

    if (!$result['success']) {
        http_response_code(409);
        echo json_encode($result);
        exit;
    }

    $house = $houseModel->getHouseById($result['house_id']);
    $houseTitle = $house['title'] ?? 'the property';

    $notification->create(
        $result['tenant_id'],
        'booking_rejected',
        'Booking Rejected',
        'Your booking request for "' . $houseTitle . '" was declined by the landlord.',
        BASE_URL . '/tenant/my-bookings'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Booking rejected.',
        'action' => 'rejected',
        'booking_id' => $result['booking_id']
    ]);
    exit;
}