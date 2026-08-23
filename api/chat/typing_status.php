<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Chat.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authenticated.']));
}

$user = Session::user();
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$chat = new Chat();

if ($chat->userBelongsToConversation($conversationId, (int) $user['id'])) {
    $chat->setTyping($conversationId, (int) $user['id']);
}

echo json_encode(['ok' => true]);
