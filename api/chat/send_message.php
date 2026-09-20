<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Chat.php';
require_once '../../classes/ChatGuard.php';
require_once '../../classes/Notification.php';
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
$message = trim($_POST['message'] ?? '');

$chat = new Chat();

if (!$chat->userBelongsToConversation($conversationId, $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not part of this conversation.']);
    exit;
}

if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (2000 characters maximum).']);
    exit;
}

$conversation = $chat->getConversationById($conversationId);

if (!ChatGuard::isOpen($conversation)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'This chat is closed because there is no active booking between you. Old messages stay visible.'
    ]);
    exit;
}

// Hides phone numbers / emails until the landlord's contact details are unlocked.
[$message, $notice] = ChatGuard::prepareText($conversation, $message);

try {
    $saved = $chat->sendMessage($conversationId, $userId, $message, 'user');
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Tell the other person there is a new message (one notification per conversation
// while it stays unread). A notification failure must never break sending.
try {
    $senderIsTenant = ((int) $conversation['tenant_id'] === $userId);

    $recipientId = $senderIsTenant ? (int) $conversation['other_user_id'] : (int) $conversation['tenant_id'];
    $recipientRole = $senderIsTenant ? (string) ($conversation['other_role'] ?? 'landlord') : 'tenant';

    (new Notification())->notifyNewMessage(
        $recipientId,
        $conversationId,
        $chat->getUserName($userId),
        (string) $saved['message'],
        BASE_URL . '/' . $recipientRole . '/messages?c=' . $conversationId
    );
} catch (Throwable $e) {
    error_log('LUX EMPIRE chat notification failed: ' . $e->getMessage());
}

echo json_encode(['message' => $saved, 'notice' => $notice]);