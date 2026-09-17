<?php

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';
require_once '../../config/csrf.php';
require_once '../../config/RedisConnection.php';
require_once '../../config/security/RedisThrottle.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$driver_id = (int) Session::user()['id'];

$latitude  = $_POST['latitude']  ?? null;
$longitude = $_POST['longitude'] ?? null;

if (empty($latitude) || empty($longitude)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing coordinates.']);
    exit;
}

try {

    // Redis is the hot path — written on EVERY ping. This is what
    // get_driver_location.php reads, so the tenant-facing map is
    // always current. 300s TTL: if a driver goes silent for 5
    // minutes (app closed, trip ended), their marker naturally
    // disappears instead of showing a stale position forever.
    RedisConnection::get()->setex("driverloc:current:{$driver_id}", 300, [
        'latitude'   => $latitude,
        'longitude'  => $longitude,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // MySQL is the durable/audit copy, not the hot read path —
    // writing it on every single ping (times however many drivers
    // are active) is DB load with no benefit, since nothing reads
    // driver_locations at that frequency anymore. Throttled to once
    // per 10 seconds per driver instead.
    $shouldWriteToDb = RedisThrottle::tryAcquire("driverloc:db_throttle:{$driver_id}", 10);

    if ($shouldWriteToDb) {

        $upsert = $pdo->prepare("
            INSERT INTO driver_locations (driver_id, latitude, longitude)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                updated_at = CURRENT_TIMESTAMP
        ");
        $upsert->execute([$driver_id, $latitude, $longitude]);

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
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.', 'error' => $e->getMessage()]);
}