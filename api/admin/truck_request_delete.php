<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminTruckService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$requestId) {
    adminJsonError('Invalid request id.');
}

if ($reason === '') {
    adminJsonError('A reason is required to delete a truck request.');
}

$service = new AdminTruckService();
$ok = $service->deletePendingRequest($requestId, $currentAdminId, $reason);

if (!$ok) {
    adminJsonError('Request not found, or it is no longer pending.', 404);
}

adminJsonResponse(['status' => 'deleted']);
