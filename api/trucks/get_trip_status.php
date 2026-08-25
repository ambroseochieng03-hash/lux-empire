<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

require_once '../../config/db.php';

$user = Session::user();
$tripId = (int) ($_GET['trip_id'] ?? 0);

$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("
    SELECT id, tenant_id, driver_id, status
    FROM truck_requests
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $tripId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip || ((int) $trip['tenant_id'] !== (int) $user['id'] && (int) $trip['driver_id'] !== (int) $user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not part of this trip.']);
    exit;
}

echo json_encode(['status' => $trip['status']]);
