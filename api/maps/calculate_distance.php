<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/DistanceCalculator.php';
require_once '../../config/security/DoSProtection.php';

header('Content-Type: application/json');

DoSProtection::check((int) Session::user()['id']);

$originLat = filter_input(INPUT_GET, 'pickup_lat', FILTER_VALIDATE_FLOAT);
$originLng = filter_input(INPUT_GET, 'pickup_lng', FILTER_VALIDATE_FLOAT);
$destLat = filter_input(INPUT_GET, 'destination_lat', FILTER_VALIDATE_FLOAT);
$destLng = filter_input(INPUT_GET, 'destination_lng', FILTER_VALIDATE_FLOAT);

$coords = [$originLat, $originLng, $destLat, $destLng];

if (
    in_array(null, $coords, true)
    || in_array(false, $coords, true)
    || abs($originLat) > 90 || abs($destLat) > 90
    || abs($originLng) > 180 || abs($destLng) > 180
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid coordinates.']);
    exit;
}

$result = DistanceCalculator::calculate($originLat, $originLng, $destLat, $destLng);

if ($result === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Could not calculate a price right now. Please try again.']);
    exit;
}

echo json_encode(['success' => true] + $result);
