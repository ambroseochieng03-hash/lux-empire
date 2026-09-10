<?php

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$tenant_id = (int) Session::user()['id'];

$latitude  = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

/*
|--------------------------------------------------------------------------
| VALIDATE
|--------------------------------------------------------------------------
*/

if (!$latitude || !$longitude) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Missing coordinates.'
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | UPDATE CURRENT LOCATION
    |--------------------------------------------------------------------------
    */

    $upsert = $pdo->prepare("
        INSERT INTO tenant_locations (tenant_id, latitude, longitude)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            updated_at = CURRENT_TIMESTAMP
    ");

    $upsert->execute([$tenant_id, $latitude, $longitude]);

    /*
    |--------------------------------------------------------------------------
    | FIND ACTIVE TRIP
    |--------------------------------------------------------------------------
    */

    $tripStmt = $pdo->prepare("
        SELECT id
        FROM truck_requests
        WHERE tenant_id = ?
        AND status IN ('accepted', 'in_transit')
        LIMIT 1
    ");

    $tripStmt->execute([$tenant_id]);

    $trip = $tripStmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | STORE LOCATION HISTORY
    |--------------------------------------------------------------------------
    */

    if ($trip) {

        $log = $pdo->prepare("
            INSERT INTO trip_location_logs (
                trip_id,
                user_id,
                role,
                latitude,
                longitude
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $log->execute([
            $trip['id'],
            $tenant_id,
            'tenant',
            $latitude,
            $longitude
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'message' => 'Tenant location updated.'
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);
}
?>