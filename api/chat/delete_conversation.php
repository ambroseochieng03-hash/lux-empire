<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Chat.php';
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

$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$chat = new Chat();

if ($conversationId <= 0 || !$chat->clearConversationForUser($conversationId, $userId, $_SERVER['REMOTE_ADDR'] ?? null)) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversation not found.']);
    exit;
}

echo json_encode(['ok' => true]);
