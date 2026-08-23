<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Chat.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$user = Session::user();
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

$chat = new Chat();

if (!$chat->userBelongsToConversation($conversationId, (int) $user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not part of this conversation.']);
    exit;
}

try {
    $saved = $chat->sendMessage($conversationId, (int) $user['id'], $message, 'user');
    echo json_encode(['message' => $saved]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
