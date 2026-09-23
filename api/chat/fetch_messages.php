<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
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

$user = Session::user();
$userId = (int) $user['id'];
DoSProtection::check($userId, 'polling');

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
$since = trim((string) ($_GET['since'] ?? ''));

$chat = new Chat();

if (!$chat->userBelongsToConversation($conversationId, $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not part of this conversation.']);
    exit;
}

$conversation = $chat->getConversationById($conversationId);

// Captured BEFORE reading, so an edit that lands mid-request is picked up next poll.
$serverTime = $chat->getServerTime();

$closed = !ChatGuard::isOpen($conversation);

if (!$closed) {
    $chat->maybeTriggerAi($conversationId);
}

$chat->markRead($conversationId, $userId);

$messages = $chat->getMessages($conversationId, $afterId, 50, $userId);

$changes = [];

if ($afterId > 0 && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $changes = $chat->getMessageChanges($conversationId, $afterId, $since, $userId);
}

/*
 * Once a truck trip's chat has closed for good (completed/cancelled),
 * contact info inside the message HISTORY is redacted on every read.
 * This runs only on the text already fetched into memory — it never
 * rewrites the stored row — so nothing here is destructive or
 * irreversible at the database level; it just never leaves the server
 * once the trip is over. ContactMasker is already loaded via
 * ChatGuard.php's own require at the top of this file.
 */
if ($closed && ($conversation['other_role'] ?? '') === 'driver') {
    foreach ($messages as &$m) {
        if (!empty($m['message'])) {
            [$m['message']] = ContactMasker::mask($m['message']);
        }
    }
    unset($m);

    foreach ($changes as &$c) {
        if (!empty($c['message'])) {
            [$c['message']] = ContactMasker::mask($c['message']);
        }
    }
    unset($c);
}

// Opening the conversation clears its "new message" notification (and the bell).
if (!empty($messages)) {
    try {
        (new Notification())->markConversationRead($userId, $conversationId);
    } catch (Throwable $e) {
        // Not fatal.
    }
}

$withUserId = ((int) $conversation['tenant_id'] === $userId)
    ? (int) $conversation['other_user_id']
    : (int) $conversation['tenant_id'];

echo json_encode([
    'messages' => $messages,
    'changes' => $changes,
    'server_time' => $serverTime,
    'typing' => $chat->isOtherTyping($conversationId, $userId),
    'presence' => $chat->getUserPresence($withUserId),
    'closed' => $closed
]);