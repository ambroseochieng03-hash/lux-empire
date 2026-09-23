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

DoSProtection::check($userId, 'polling');

$driverIdsRaw = trim((string) ($_GET['driver_ids'] ?? ''));

if ($driverIdsRaw === '') {
    echo json_encode(['success' => true, 'locations' => []]);
    exit;
}

$requestedIds = array_values(array_unique(array_filter(array_map(
    static fn ($v) => (int) trim($v),
    explode(',', $driverIdsRaw)
), static fn ($v) => $v > 0)));

// Hard cap — a batch endpoint must never become an unbounded query.
$requestedIds = array_slice($requestedIds, 0, 20);

if (empty($requestedIds)) {
    echo json_encode(['success' => true, 'locations' => []]);
    exit;
}

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| AUTHORIZATION — same rule as get_driver_location.php, applied per id.
| A tenant only ever gets back locations for drivers on THEIR OWN
| currently-active trips; a bad-faith request for someone else's
| driver id is silently dropped from the result, not errored — the
| response only ever contains what this user is allowed to see.
|--------------------------------------------------------------------------
*/

$authorizedIds = [];

if ($role === 'admin') {

    $authorizedIds = $requestedIds;

} elseif ($role === 'driver') {

    $authorizedIds = in_array($userId, $requestedIds, true) ? [$userId] : [];

} elseif ($role === 'tenant') {

    $placeholders = implode(',', array_fill(0, count($requestedIds), '?'));

    $stmt = $pdo->prepare("
        SELECT DISTINCT driver_id FROM truck_requests
        WHERE tenant_id = ?
        AND driver_id IN ({$placeholders})
        AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
    ");
    $stmt->execute(array_merge([$userId], $requestedIds));

    $authorizedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if (empty($authorizedIds)) {
    echo json_encode(['success' => true, 'locations' => []]);
    exit;
}

$locations = [];

try {
    $redis = RedisConnection::get();

    foreach ($authorizedIds as $driverId) {
        try {
            $cached = $redis->get("driverloc:current:{$driverId}");
            if ($cached !== false && is_array($cached)) {
                $locations[$driverId] = $cached;
            }
        } catch (Throwable $e) {
            // Fall through to the MySQL fallback below for this one driver.
        }
    }
} catch (Throwable $e) {
    // Redis unreachable entirely — every id falls through below.
}

$missingIds = array_values(array_diff($authorizedIds, array_map('intval', array_keys($locations))));

if (!empty($missingIds)) {
    $placeholders = implode(',', array_fill(0, count($missingIds), '?'));

    $stmt = $pdo->prepare("
        SELECT driver_id, latitude, longitude, updated_at
        FROM driver_locations
        WHERE driver_id IN ({$placeholders})
    ");
    $stmt->execute($missingIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $locations[(int) $row['driver_id']] = [
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'updated_at' => $row['updated_at'],
        ];
    }
}

echo json_encode(['success' => true, 'locations' => $locations]);
