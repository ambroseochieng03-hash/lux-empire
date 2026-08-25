<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Notification.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$user = Session::user();
$notification = new Notification();

if (isset($_POST['id'])) {
    $notification->markRead((int) $_POST['id'], (int) $user['id']);
} else {
    $notification->markAllRead((int) $user['id']);
}

echo json_encode(['ok' => true]);
