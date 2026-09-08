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

try {
    $ok = $service->deleteUser($userId, $currentAdminId, $reason !== '' ? $reason : null);
} catch (RuntimeException $e) {
    adminJsonError($e->getMessage(), 403);
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin user_delete error: ' . $e->getMessage());
    adminJsonError('Could not delete user.', 500);
}

if (!$ok) {
    adminJsonError('User not found.', 404);
}

adminJsonResponse(['status' => 'deleted']);
