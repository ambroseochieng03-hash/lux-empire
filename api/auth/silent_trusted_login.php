<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/TrustedDevice.php';

Session::start();
header('Content-Type: application/json');

/*
 * Deliberately GET-safe (idempotent, no state change beyond the
 * trusted-device cookie's own rotation, which already happens on
 * every use regardless) — this runs automatically from guest-browse
 * page interactions, not a user-initiated form submit, so requiring
 * a CSRF token here would be protecting nothing meaningful.
 */

if (Session::isAuthenticated()) {
    // Already logged in somehow — nothing to do, but don't treat
    // this as a failure either.
    echo json_encode(['success' => true, 'already_logged_in' => true]);
    exit;
}

$trustedDevice = new TrustedDevice();
$userId = $trustedDevice->identifyTrustedUser();

if ($userId === null) {
    echo json_encode(['success' => false]);
    exit;
}

$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("SELECT id, full_name, email, role, status FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/*
 * Tenant-only, by explicit design: this endpoint exists purely to
 * smooth over a guest clicking "Book Now" / submitting a truck
 * request when they're actually already a known tenant on this
 * device. A landlord or driver's trusted-device cookie must NEVER
 * silently authenticate them on a public page — those roles have
 * real dashboard access and no business being logged in via a
 * guest-page side effect.
 */
if (!$user || $user['role'] !== 'tenant' || ($user['status'] ?? 'active') !== 'active') {
    echo json_encode(['success' => false]);
    exit;
}

Session::regenerateAfterLogin();

$_SESSION['user'] = [
    'id'        => $user['id'],
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
];

echo json_encode([
    'success' => true,
    'csrf_token' => Csrf::regenerate()
]);
