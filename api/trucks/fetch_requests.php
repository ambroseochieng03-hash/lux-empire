<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->connect();

try {

    $stmt = $pdo->prepare("
        SELECT
            truck_requests.id,
            truck_requests.trip_type,
            truck_requests.scheduled_at,
            truck_requests.items_description,
            truck_requests.distance_km,
            truck_requests.pickup_location,
            truck_requests.destination,
            truck_requests.price,
            truck_requests.status,
            truck_requests.requested_at,

            users.full_name,
            users.phone

        FROM truck_requests

        JOIN users
        ON truck_requests.tenant_id = users.id

        WHERE truck_requests.status = 'pending'

        ORDER BY
            (truck_requests.trip_type = 'instant') DESC,
            truck_requests.scheduled_at ASC,
            truck_requests.requested_at DESC
    ");

    $stmt->execute();

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $windowSeconds = TRUCK_ACCEPT_WINDOW_MINUTES * 60;
    $now = time();

    foreach ($requests as &$request) {

        $request['is_acceptable_now'] = true;
        $request['window_opens_at'] = null;

        if ($request['trip_type'] === 'scheduled' && $request['scheduled_at'] !== null) {
            $scheduledAtTimestamp = strtotime($request['scheduled_at']);
            $windowOpensAt = $scheduledAtTimestamp - $windowSeconds;
            $request['window_opens_at'] = $windowOpensAt;
            $request['is_acceptable_now'] = ($now >= $windowOpensAt);
        }
    }
    unset($request);

    echo json_encode([
        'success' => true,
        'count'   => count($requests),
        'requests'=> $requests
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch requests.',
        'error'   => $e->getMessage()
    ]);
}