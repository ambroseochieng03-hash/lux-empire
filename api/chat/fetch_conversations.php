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
$chat = new Chat();
$chat->touchLastSeen((int) $user['id']);

echo json_encode(['conversations' => $chat->getConversationsForUser((int) $user['id'])]);
