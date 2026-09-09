<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminMessagingService.php';
require_once __DIR__ . '/../../classes/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$targetRole = $_POST['target_role'] ?? '';
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

if ($subject === '' || mb_strlen($subject) > 150) {
    adminJsonError('Subject is required (max 150 characters).');
}

if ($body === '' || mb_strlen($body) > 5000) {
    adminJsonError('Message body is required (max 5000 characters).');
}

$service = new AdminMessagingService();

try {
    $count = $service->broadcast($targetRole, $subject, $body, $currentAdminId);
} catch (InvalidArgumentException $e) {
    adminJsonError($e->getMessage());
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin broadcast error: ' . $e->getMessage());
    adminJsonError('Could not send broadcast.', 500);
}

adminJsonResponse(['status' => 'sent', 'recipient_count' => $count]);
