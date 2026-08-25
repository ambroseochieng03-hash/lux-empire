<?php

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$driver_id = (int) Session::user()['id'];

$latitude  = $_POST['latitude']  ?? null;
$longitude = $_POST['longitude'] ?? null;

if (empty($latitude) || empty($longitude)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing coordinates.']);
    exit;
}

try {

    $check = $pdo->prepare("SELECT id FROM driver_locations WHERE driver_id = ? LIMIT 1");
    $check->execute([$driver_id]);
    $existing = $check->fetch();

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE driver_locations
            SET latitude = ?, longitude = ?, updated_at = CURRENT_TIMESTAMP
            WHERE driver_id = ?
        ");
        $update->execute([$latitude, $longitude, $driver_id]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO driver_locations (driver_id, latitude, longitude)
            VALUES (?, ?, ?)
        ");
        $insert->execute([$driver_id, $latitude, $longitude]);
    }

    $tripStmt = $pdo->prepare("
        SELECT id FROM truck_requests
        WHERE driver_id = ?
        AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
        LIMIT 1
    ");
    $tripStmt->execute([$driver_id]);
    $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        $log = $pdo->prepare("
            INSERT INTO trip_location_logs (trip_id, user_id, role, latitude, longitude)
            VALUES (?, ?, ?, ?, ?)
        ");
        $log->execute([$trip['id'], $driver_id, 'driver', $latitude, $longitude]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully.',
        'latitude' => $latitude,
        'longitude' => $longitude
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.', 'error' => $e->getMessage()]);
}