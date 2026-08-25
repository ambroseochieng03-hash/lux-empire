<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Notification.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

$user = Session::user();
$notification = new Notification();

echo json_encode([
    'notifications' => $notification->getForUser((int) $user['id']),
    'unread_count' => $notification->getUnreadCount((int) $user['id'])
]);
