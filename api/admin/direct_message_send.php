<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminMessagingService.php';
require_once __DIR__ . '/../../classes/IdempotencyGuard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));

if ($idempotencyKey === '' || !preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $idempotencyKey)) {
    adminJsonError('Missing or invalid request key. Please refresh and try again.');
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'admin_direct_message_send', $currentAdminId);

if ($guardResult['status'] === 'processing') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This message is already being sent. Please wait.'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($guardResult['status'] === 'completed') {
    http_response_code((int) $guardResult['response_code']);
    echo $guardResult['response_body'];
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

if (!$userId) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => 'Invalid user id.'], JSON_UNESCAPED_SLASHES);
    $idempotency->complete($idempotencyKey, 'admin_direct_message_send', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

if ($subject === '' || mb_strlen($subject) > 150) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => 'Subject is required (max 150 characters).'], JSON_UNESCAPED_SLASHES);
    $idempotency->complete($idempotencyKey, 'admin_direct_message_send', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

if ($body === '' || mb_strlen($body) > 5000) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => 'Message body is required (max 5000 characters).'], JSON_UNESCAPED_SLASHES);
    $idempotency->complete($idempotencyKey, 'admin_direct_message_send', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

$service = new AdminMessagingService();

try {
    $ok = $service->sendDirectMessage($userId, $subject, $body, $currentAdminId);

    if (!$ok) {
        $responseCode = 404;
        $responseBody = json_encode(['success' => false, 'error' => 'User not found.'], JSON_UNESCAPED_SLASHES);
    } else {
        $responseCode = 200;
        $responseBody = json_encode(['success' => true, 'status' => 'sent'], JSON_UNESCAPED_SLASHES);
    }
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin direct message error: ' . $e->getMessage());
    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'error' => 'Could not send message.'], JSON_UNESCAPED_SLASHES);
}

$idempotency->complete($idempotencyKey, 'admin_direct_message_send', $responseCode, $responseBody);

http_response_code($responseCode);
echo $responseBody;
exit;