<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Chat.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

$user = Session::user();
$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);

$chat = new Chat();

if (!$chat->userBelongsToConversation($conversationId, (int) $user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not part of this conversation.']);
    exit;
}

$chat->touchLastSeen((int) $user['id']);
$chat->maybeTriggerAi($conversationId);
$chat->markRead($conversationId, (int) $user['id']);

$conversation = $chat->getConversationById($conversationId);
$withUserId = ((int) $conversation['tenant_id'] === (int) $user['id'])
    ? (int) $conversation['other_user_id']
    : (int) $conversation['tenant_id'];

echo json_encode([
    'messages' => $chat->getMessages($conversationId, $afterId),
    'typing' => $chat->isOtherTyping($conversationId, (int) $user['id']),
    'presence' => $chat->getUserPresence($withUserId)
]);
