<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/RedisConnection.php';
require_once '../../config/security/DoSProtection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user = Session::user();
$role = $user['role'] ?? '';
$userId = (int) ($user['id'] ?? 0);

// A tenant watching a trip polls this every few seconds — it gets its own
// budget, so tracking a driver can never use up the limit for real actions.
DoSProtection::check($userId, 'polling');

$driver_id = (int) ($_GET['driver_id'] ?? 0);

if ($driver_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Driver ID missing.']);
    exit;
}

$db = new Database();
$pdo = $db->connect();

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

    $location = null;

    try {
        $cached = RedisConnection::get()->get("driverloc:current:{$driver_id}");
        if ($cached !== false && is_array($cached)) {
            $location = $cached;
        }
    } catch (Throwable $e) {
        // Redis unreachable — fall through to the MySQL copy below.
    }

    if ($location === null) {

        // Redis miss (driver hasn't pinged since a Redis restart, or
        // the 300s TTL expired, or Redis is briefly down) — MySQL's
        // copy is at most ~10s stale thanks to the throttled write
        // in update_driver_location.php.
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
    }

    if (!$location) {
        echo json_encode(['success' => false, 'message' => 'Driver location not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'driver_id' => $driver_id,
        'location' => $location
    ]);

} catch (Throwable $e) {

    // The real error goes to the log only — never to the browser.
    error_log('LUX EMPIRE get_driver_location error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the driver location.']);
}