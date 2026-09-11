<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';
require_once '../../classes/TruckRequest.php';
require_once '../../classes/IdempotencyGuard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$tenant_id = (int) Session::user()['id'];

$idempotencyKey = trim($_POST['idempotency_key'] ?? '');

if ($idempotencyKey === '') {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'request_truck', $tenant_id);

if ($guardResult['status'] === 'processing') {
    $_SESSION['error'] = "This request is already being processed.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

if ($guardResult['status'] === 'completed') {
    // Already ran — replay the ORIGINAL outcome's flash message
    // rather than silently re-submitting a second truck request.
    $_SESSION[$guardResult['response_code'] === 200 ? 'success' : 'error'] = $guardResult['response_body'];
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

require_once '../../classes/DistanceCalculator.php';

$pickup_location = trim($_POST['pickup_location'] ?? '');
$destination     = trim($_POST['destination'] ?? '');

$pickup_lat = filter_input(INPUT_POST, 'pickup_lat', FILTER_VALIDATE_FLOAT);
$pickup_lng = filter_input(INPUT_POST, 'pickup_lng', FILTER_VALIDATE_FLOAT);
$destination_lat = filter_input(INPUT_POST, 'destination_lat', FILTER_VALIDATE_FLOAT);
$destination_lng = filter_input(INPUT_POST, 'destination_lng', FILTER_VALIDATE_FLOAT);

if (empty($pickup_location) || empty($destination)) {
    $_SESSION['error'] = "Pickup and destination are required.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

if ($pickup_lat === null || $pickup_lng === null || $destination_lat === null || $destination_lng === null) {
    $_SESSION['error'] = "Could not determine coordinates for pickup/destination. Please re-select them on the map.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

$tripType = ($_POST['trip_type'] ?? 'instant') === 'scheduled' ? 'scheduled' : 'instant';
$itemsDescription = trim($_POST['items_description'] ?? '');
$itemsDescription = $itemsDescription !== '' ? mb_substr($itemsDescription, 0, 2000) : null;

$scheduledAt = null;

if ($tripType === 'scheduled') {

    $scheduledAtRaw = trim($_POST['scheduled_at'] ?? '');

    // datetime-local posts "YYYY-MM-DDTHH:MM" — validate the shape
    // strictly rather than trusting it, since this becomes a real
    // DATETIME column and drives driver-side accept-gating (item 3).
    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $scheduledAtRaw);

    if (!$parsed) {
        $_SESSION['error'] = "Please choose a valid date and time for your scheduled move.";
        header("Location: ../../dashboard/tenant/request_truck.php");
        exit;
    }

    $minimumLeadSeconds = TRUCK_MIN_SCHEDULE_LEAD_MINUTES * 60;

    if ($parsed->getTimestamp() < (time() + $minimumLeadSeconds)) {
        $_SESSION['error'] = "Scheduled moves must be booked at least " . TRUCK_MIN_SCHEDULE_LEAD_MINUTES . " minutes in advance.";
        header("Location: ../../dashboard/tenant/request_truck.php");
        exit;
    }

    $scheduledAt = $parsed->format('Y-m-d H:i:s');
}

/*
 * Price is NEVER taken from the client — recomputed here, from
 * server-validated coordinates, every single time. A client could
 * submit any $_POST['price'] they like; this line is what makes
 * that irrelevant.
 */
$distanceResult = DistanceCalculator::calculate(
    $pickup_lat, $pickup_lng, $destination_lat, $destination_lng
);

if ($distanceResult === null) {
    $_SESSION['error'] = "Could not calculate a price for this route. Please try again.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

$truck = new TruckRequest();

$data = [
    'tenant_id' => $tenant_id,
    'trip_type' => $tripType,
    'scheduled_at' => $scheduledAt,
    'items_description' => $itemsDescription,
    'pickup_location' => $pickup_location,
    'destination' => $destination,
    'pickup_lat' => $pickup_lat,
    'pickup_lng' => $pickup_lng,
    'destination_lat' => $destination_lat,
    'destination_lng' => $destination_lng,
    'price' => $distanceResult['price'],
    'distance_km' => $distanceResult['distance_km'],
];

$result = $truck->createRequest($data);

$resultMessage = $result ? "Truck request submitted successfully!" : "Failed to submit request. Try again.";

$idempotency->complete($idempotencyKey, 'request_truck', $result ? 200 : 500, $resultMessage);

$_SESSION[$result ? 'success' : 'error'] = $resultMessage;

header("Location: ../../dashboard/tenant/request_truck.php");
exit;