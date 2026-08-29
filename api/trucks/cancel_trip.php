<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/TruckRequest.php';
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

/**
 * Previously had NO CSRF check at all — closing that gap here,
 * matching the convention used by the booking endpoints.
 */
Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

$rateKey = 'cancel_trip:' . $tenantId;

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

$trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

if (!$trip_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid trip.']);
    exit;
}

$truckModel = new TruckRequest();

/*
|--------------------------------------------------------------------------
| VERIFY OWNERSHIP — derived from session, never from client input.
|--------------------------------------------------------------------------
*/

$trip = $truckModel->getRequestById($trip_id);

if (!$trip || (int) $trip['tenant_id'] !== $tenantId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Trip not found.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| BLOCK CANCELLATION AFTER START (unchanged business rule)
|--------------------------------------------------------------------------
*/

if ($trip['status'] === 'in_transit' || $trip['status'] === 'completed') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Trip already started and cannot be cancelled.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL TRIP
|--------------------------------------------------------------------------
*/

$success = $truckModel->updateStatus($trip_id, 'cancelled');

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel trip.']);
    exit;
}

if (!empty($trip['driver_id'])) {

    $notification = new Notification();

    $notification->create(
        (int) $trip['driver_id'],
        'trip_cancelled',
        'Trip Cancelled',
        'The tenant has cancelled the move from ' . $trip['pickup_location'] . ' to ' . $trip['destination'] . '.',
        BASE_URL . '/driver/active-trip'
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Trip cancelled successfully.',
    'trip_id' => $trip_id
]);
exit;