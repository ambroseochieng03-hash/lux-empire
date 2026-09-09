<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminUserService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    adminJsonError('Invalid user id.');
}

$service = new AdminUserService();

try {
    $identity = $service->revealIdentity($userId, $currentAdminId);
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin user_reveal_identity error: ' . $e->getMessage());
    adminJsonError('Could not decrypt identity value.', 500);
}

if ($identity === null) {
    adminJsonError('No identity document on file for this user.', 404);
}

adminJsonResponse(['identity' => $identity]);
