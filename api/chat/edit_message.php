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
$newText = trim((string) ($_POST['message'] ?? ''));

if ($messageId <= 0 || $newText === '') {
    http_response_code(400);
    echo json_encode(['error' => 'A message cannot be empty.']);
    exit;
}

if (mb_strlen($newText) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (2000 characters maximum).']);
    exit;
}

$chat = new Chat();
$row = $chat->findMessage($messageId);

if (!$row || !$chat->userBelongsToConversation((int) $row['conversation_id'], $userId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Message not found.']);
    exit;
}

$conversation = $chat->getConversationById((int) $row['conversation_id']);

if (!ChatGuard::isOpen($conversation)) {
    http_response_code(403);
    echo json_encode(['error' => 'This chat is closed, so messages can no longer be edited.']);
    exit;
}

// Edits go through the same masking as new messages, so editing can't be used to
// slip a phone number in after the fact.
[$newText, $notice] = ChatGuard::prepareText($conversation, $newText);

$result = $chat->editMessage($messageId, $userId, $newText, $_SERVER['REMOTE_ADDR'] ?? null);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode(['message' => $result['message'], 'notice' => $notice]);
