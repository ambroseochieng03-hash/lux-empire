<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Chat.php';
require_once '../../classes/ChatGuard.php';
require_once '../../config/security/DoSProtection.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$user = Session::user();
$userId = (int) $user['id'];
DoSProtection::check($userId);

$messageId = (int) ($_POST['message_id'] ?? 0);
$scope = (string) ($_POST['scope'] ?? '');

if ($messageId <= 0 || !in_array($scope, ['me', 'everyone'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$chat = new Chat();
$row = $chat->findMessage($messageId);

if (!$row || !$chat->userBelongsToConversation((int) $row['conversation_id'], $userId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Message not found.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($scope === 'me') {
    $chat->deleteMessageForMe($messageId, $userId, $ip);
    echo json_encode(['ok' => true, 'message_id' => $messageId]);
    exit;
}

// Delete for everyone: sender only, inside the time window, while the chat is open.
$conversation = $chat->getConversationById((int) $row['conversation_id']);

if (!ChatGuard::isOpen($conversation)) {
    http_response_code(403);
    echo json_encode(['error' => 'This chat is closed, so messages can no longer be deleted for everyone. You can still delete them for yourself.']);
    exit;
}

$result = $chat->deleteMessageForEveryone($messageId, $userId, $ip);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'message' => $result['message']]);