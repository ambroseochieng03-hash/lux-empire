<?php

session_start();

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

// Only drivers
requireRoleAccess('driver');

try {

    $stmt = $pdo->prepare("
        SELECT
            truck_requests.id,
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

        ORDER BY truck_requests.requested_at DESC
    ");

    $stmt->execute();

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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