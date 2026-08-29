<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Consent.php';
require_once '../../config/security/DoSProtection.php';

Session::start();

header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$role = $_POST['role'] ?? '';

if (!in_array($role, ['landlord', 'driver'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$consent = new Consent();

try {
    $consent->record(null, $role, 'declined', $ip);
} catch (Throwable $e) {
    error_log('LUX EMPIRE consent decline logging failed: ' . $e->getMessage());
}

/**
 * Beacons don't read the response, but return something sane anyway.
 */
echo json_encode(['success' => true]);
