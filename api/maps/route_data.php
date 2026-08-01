<?php

header('Content-Type: application/json');

// Only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

// =====================================
// GET COORDINATES
// =====================================

$pickup_lat = $_GET['pickup_lat'] ?? null;
$pickup_lng = $_GET['pickup_lng'] ?? null;

$destination_lat = $_GET['destination_lat'] ?? null;
$destination_lng = $_GET['destination_lng'] ?? null;

// =====================================
// VALIDATION
// =====================================

if (
    !$pickup_lat ||
    !$pickup_lng ||
    !$destination_lat ||
    !$destination_lng
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Missing coordinates.'
    ]);

    exit;
}

// =====================================
// HAVERSINE FORMULA
// =====================================

function calculateDistance(
    $lat1,
    $lon1,
    $lat2,
    $lon2
) {

    $earth_radius = 6371; // KM

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a =
        sin($dLat / 2) * sin($dLat / 2)
        +
        cos(deg2rad($lat1))
        *
        cos(deg2rad($lat2))
        *
        sin($dLon / 2)
        *
        sin($dLon / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earth_radius * $c;
}

// =====================================
// CALCULATE DISTANCE
// =====================================

$distance_km = calculateDistance(
    $pickup_lat,
    $pickup_lng,
    $destination_lat,
    $destination_lng
);

// =====================================
// ESTIMATED TIME
// Assume average 40km/h
// =====================================

$estimated_hours = $distance_km / 40;

$estimated_minutes =
    round($estimated_hours * 60);

// =====================================
// RESPONSE
// =====================================

echo json_encode([

    'success' => true,

    'route' => [

        'pickup' => [
            'lat' => $pickup_lat,
            'lng' => $pickup_lng
        ],

        'destination' => [
            'lat' => $destination_lat,
            'lng' => $destination_lng
        ],

        'distance_km' =>
            round($distance_km, 2),

        'estimated_minutes' =>
            $estimated_minutes,

        'estimated_hours' =>
            round($estimated_hours, 2)

    ]
]);