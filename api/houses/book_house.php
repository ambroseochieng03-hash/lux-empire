<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';
require_once '../../classes/Booking.php';
require_once '../../classes/Notification.php';
require_once '../../classes/IdempotencyGuard.php';
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

$tenant_id = (int) Session::user()['id'];
$tenant_name = Session::user()['full_name'] ?? 'A tenant';

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

$idempotencyKey = trim($_POST['idempotency_key'] ?? '');

if ($idempotencyKey === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'book_house', $tenant_id);

if ($guardResult['status'] === 'processing') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This booking is already being processed.']);
    exit;
}

if ($guardResult['status'] === 'completed') {
    // Already ran to completion — replay the ORIGINAL response
    // verbatim rather than creating a second booking.
    http_response_code($guardResult['response_code']);
    echo $guardResult['response_body'];
    exit;
}

$house_id = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);

if (!$house_id) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'message' => 'Invalid property.']);
    $idempotency->complete($idempotencyKey, 'book_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

$houseModel = new House();
$house = $houseModel->getHouseById($house_id);

if (!$house) {
    $responseCode = 404;
    $responseBody = json_encode(['success' => false, 'message' => 'Property not found.']);
    $idempotency->complete($idempotencyKey, 'book_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

if ((int) $house['landlord_id'] === $tenant_id) {
    $responseCode = 403;
    $responseBody = json_encode(['success' => false, 'message' => 'You cannot book your own property.']);
    $idempotency->complete($idempotencyKey, 'book_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

$bookingModel = new Booking();

$result = $bookingModel->createBooking(
    $tenant_id,
    $house_id,
    (int) $house['landlord_id']
);

if ($result === true) {

    $notification = new Notification();

    $notification->create(
        (int) $house['landlord_id'],
        'new_booking_request',
        'New Booking Request',
        $tenant_name . ' has requested to book "' . $house['title'] . '".',
        BASE_URL . '/booking-requests'
    );

    $responseCode = 200;
    $responseBody = json_encode([
        'success' => true,
        'message' => 'Your booking request has been submitted.',
        'status' => 'pending',
        'house_id' => $house_id
    ]);

    $idempotency->complete($idempotencyKey, 'book_house', $responseCode, $responseBody);

    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

$responseCode = 409;
$responseBody = json_encode(['success' => false, 'message' => $result]);

$idempotency->complete($idempotencyKey, 'book_house', $responseCode, $responseBody);

http_response_code($responseCode);
echo $responseBody;
exit;