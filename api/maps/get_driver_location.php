<?php

header('Content-Type: application/json');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

// Only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

// Get driver ID
$driver_id = $_GET['driver_id'] ?? null;

// Validate
if (!$driver_id) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Driver ID missing.'
    ]);

    exit;
}

try {

    // Fetch driver location
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

    // No location found
    if (!$location) {

        echo json_encode([
            'success' => false,
            'message' => 'Driver location not found.'
        ]);

        exit;
    }

    // Success
    echo json_encode([
        'success' => true,
        'driver_id' => $driver_id,
        'location' => $location
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);
}