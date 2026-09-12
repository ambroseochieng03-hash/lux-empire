<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';
require_once '../../config/csrf.php';
require_once '../../classes/TruckRequest.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenant_id = (int) Session::user()['id'];
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$truck = new TruckRequest();

$result = $truck->updateEditableFields($request_id, $tenant_id, [
    'items_description' => $_POST['items_description'] ?? '',
    'scheduled_at' => $_POST['scheduled_at'] ?? '',
]);

if (!$result['success']) {
    http_response_code(409);
    echo json_encode($result);
    exit;
}

echo json_encode($result);