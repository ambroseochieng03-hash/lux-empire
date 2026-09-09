<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminMessagingService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

if (!$userId) {
    adminJsonError('Invalid user id.');
}

if ($subject === '' || mb_strlen($subject) > 150) {
    adminJsonError('Subject is required (max 150 characters).');
}

if ($body === '' || mb_strlen($body) > 5000) {
    adminJsonError('Message body is required (max 5000 characters).');
}

$service = new AdminMessagingService();
$ok = $service->sendDirectMessage($userId, $subject, $body, $currentAdminId);

if (!$ok) {
    adminJsonError('User not found.', 404);
}

adminJsonResponse(['status' => 'sent']);
