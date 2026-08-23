<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/Chat.php';

Session::start();
header('Content-Type: application/json');

if (Session::isAuthenticated()) {
    $user = Session::user();
    (new Chat())->touchLastSeen((int) $user['id']);
}

echo json_encode(['ok' => true]);
