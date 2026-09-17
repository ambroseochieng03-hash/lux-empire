<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminMessagingService.php';
require_once __DIR__ . '/../../classes/Validator.php';
require_once __DIR__ . '/../../classes/IdempotencyGuard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

/*
 * A broadcast can go out to your entire user base — a double-click
 * from a laggy connection must never mean it sends twice. Same
 * idempotency-key pattern already used for bookings/house creation
 * (classes/IdempotencyGuard.php, assets/js/idempotency.js): the
 * client generates one key per send-attempt and resends it on any
 * retry; the server guarantees the underlying action runs at most
 * once per key, regardless of how many times the request arrives.
 */
$idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));

if ($idempotencyKey === '' || !preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $idempotencyKey)) {
    adminJsonError('Missing or invalid request key. Please refresh and try again.');
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'admin_broadcast_send', $currentAdminId);

if ($guardResult['status'] === 'processing') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This broadcast is already being sent. Please wait.'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($guardResult['status'] === 'completed') {
    // Exact replay of the first attempt's real result — the
    // broadcast itself is NOT sent again.
    http_response_code((int) $guardResult['response_code']);
    echo $guardResult['response_body'];
    exit;
}

$targetRole = $_POST['target_role'] ?? '';
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

if ($subject === '' || mb_strlen($subject) > 150) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => 'Subject is required (max 150 characters).'], JSON_UNESCAPED_SLASHES);
    $idempotency->complete($idempotencyKey, 'admin_broadcast_send', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

if ($body === '' || mb_strlen($body) > 5000) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => 'Message body is required (max 5000 characters).'], JSON_UNESCAPED_SLASHES);
    $idempotency->complete($idempotencyKey, 'admin_broadcast_send', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}

$service = new AdminMessagingService();

try {
    $count = $service->broadcast($targetRole, $subject, $body, $currentAdminId);
    $responseCode = 200;
    $responseBody = json_encode(['success' => true, 'status' => 'sent', 'recipient_count' => $count], JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    $responseCode = 400;
    $responseBody = json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin broadcast error: ' . $e->getMessage());
    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'error' => 'Could not send broadcast.'], JSON_UNESCAPED_SLASHES);
}

$idempotency->complete($idempotencyKey, 'admin_broadcast_send', $responseCode, $responseBody);

http_response_code($responseCode);
echo $responseBody;
exit;