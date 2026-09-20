<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Notification.php';
require_once '../../classes/Chat.php';
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

// Polled by every open dashboard page — its own budget, separate from real actions.
DoSProtection::check($userId, 'polling');

echo json_encode([
    'notifications' => (new Notification())->getUnreadCount($userId),
    'chats' => (new Chat())->getUnreadTotalForUser($userId),
]);
