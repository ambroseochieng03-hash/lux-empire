<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../classes/BookingCelebration.php';
require_once '../../config/security/DoSProtection.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated() || (Session::user()['role'] ?? '') !== 'tenant') {
    http_response_code(401);
    echo json_encode(['celebration' => null]);
    exit;
}

DoSProtection::check((int) Session::user()['id'], 'polling');

echo json_encode(['celebration' => BookingCelebration::claimNext((int) Session::user()['id'])]);
