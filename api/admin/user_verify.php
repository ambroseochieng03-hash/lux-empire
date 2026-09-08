<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminUserService.php';
require_once __DIR__ . '/../../classes/EmailJobPublisher.php';
require_once __DIR__ . '/../../classes/Notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    adminJsonError('Invalid user id.');
}

$service = new AdminUserService();
$targetUser = $service->getUserById($userId);

if (!$targetUser) {
    adminJsonError('User not found.', 404);
}

try {
    $ok = $service->verifyUser($userId, $currentAdminId);
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin user_verify error: ' . $e->getMessage());
    adminJsonError('Could not verify user.', 500);
}

if (!$ok) {
    adminJsonError('Only landlord or driver accounts can be verified.', 400);
}

EmailJobPublisher::publish('email.' . $targetUser['role'] . '_verified', [
    'email' => $targetUser['email'],
    'name'  => $targetUser['full_name'],
    'role'  => $targetUser['role'],
]);

$notification = new Notification();
$notification->create(
    $userId,
    'account_verified',
    'Account Verified',
    'Congratulations — your ' . ucfirst($targetUser['role']) . ' account has been verified by LUX EMPIRE.',
    null
);

adminJsonResponse(['status' => 'verified']);
