<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Chat.php';
require_once '../../config/security/DoSProtection.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authenticated.']));
}

require_once '../../config/csrf.php';

$user = Session::user();
DoSProtection::check((int) $user['id']);

Csrf::requireValid($_POST['csrf_token'] ?? null);

$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$chat = new Chat();

if ($chat->userBelongsToConversation($conversationId, (int) $user['id'])) {
    $chat->setTyping($conversationId, (int) $user['id']);
}

echo json_encode(['ok' => true]);
