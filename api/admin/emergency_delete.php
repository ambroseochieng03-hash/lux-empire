<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminEmergencyService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$id) {
    adminJsonError('Invalid alert id.');
}

if ($reason === '') {
    adminJsonError('A reason is required to delete an emergency alert.');
}

$service = new AdminEmergencyService();
$ok = $service->deleteAlert($id, $currentAdminId, $reason);

if (!$ok) {
    adminJsonError('Alert not found.', 404);
}

adminJsonResponse(['status' => 'deleted']);
