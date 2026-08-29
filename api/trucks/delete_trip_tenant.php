<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/TruckRequest.php';
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
 * Previously had NO CSRF check at all — closing that gap here.
 */
Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

$rateKey = 'delete_trip:' . $tenantId;

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
| VERIFY OWNERSHIP
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
| DELETE TRIP
|--------------------------------------------------------------------------
*/

$success = $truckModel->deleteRequest($trip_id, $tenantId);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Truck request deleted successfully.',
        'trip_id' => $trip_id
    ]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to delete truck request.']);
exit;