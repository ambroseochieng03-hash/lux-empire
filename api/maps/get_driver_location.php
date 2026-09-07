<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$driver_id = (int) ($_GET['driver_id'] ?? 0);

if ($driver_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Driver ID missing.']);
    exit;
}

$user = Session::user();
$role = $user['role'] ?? '';
$userId = (int) ($user['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| AUTHORIZATION — three different rules depending on who's asking:
|
|   admin  -> can see any driver's location
|   driver -> can only see their OWN location
|   tenant -> can only see a driver they currently have an active
|             trip with (mirrors the same status set
|             update_driver_location.php uses for trip-location
|             logging, so "active" means the same thing everywhere)
|
| Anyone else (or a tenant with no matching trip) is denied.
|--------------------------------------------------------------------------
*/

$authorized = false;

if ($role === 'admin') {

    $authorized = true;

} elseif ($role === 'driver' && $userId === $driver_id) {

    $authorized = true;

} elseif ($role === 'tenant') {

    $tripStmt = $pdo->prepare("
        SELECT id FROM truck_requests
        WHERE tenant_id = ?
        AND driver_id = ?
        AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
        LIMIT 1
    ");
    $tripStmt->execute([$userId, $driver_id]);

    $authorized = (bool) $tripStmt->fetchColumn();
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized to view this driver\'s location.']);
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            latitude,
            longitude,
            updated_at
        FROM driver_locations
        WHERE driver_id = ?
        LIMIT 1
    ");

    $stmt->execute([$driver_id]);

    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        echo json_encode(['success' => false, 'message' => 'Driver location not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'driver_id' => $driver_id,
        'location' => $location
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.', 'error' => $e->getMessage()]);
}