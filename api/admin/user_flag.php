<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminUserService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$userId) {
    adminJsonError('Invalid user id.');
}

$service = new AdminUserService();
$ok = $service->flagUser($userId, $reason !== '' ? $reason : null, $currentAdminId);

if (!$ok) {
    adminJsonError('User not found or cannot be modified.', 404);
}

adminJsonResponse(['status' => 'flagged']);
