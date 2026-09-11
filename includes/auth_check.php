<?php

/**
 * LUX EMPIRE
 * Authentication Protection Layer
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/security/DoSProtection.php';


/**
 * Start and validate session.
 */
Session::start();


/**
 * Require authenticated user.
 */
if (!Session::isAuthenticated()) {

    header(
        'Location: ' . BASE_URL . '/login?access_denied=1'
    );

    exit();
}

/**
 * Re-check the account's live status on every authenticated
 * request. $_SESSION['user'] is a snapshot from login time — an
 * admin suspending this account afterward wouldn't otherwise be
 * reflected until the person logs in again. This makes suspension
 * take effect on their very next request instead.
 */
require_once __DIR__ . '/../config/db.php';

$sessionUser = Session::user();

$statusStmt = (new Database())->connect()->prepare("
    SELECT status FROM users WHERE id = :id LIMIT 1
");
$statusStmt->execute([':id' => $sessionUser['id']]);
$currentStatus = $statusStmt->fetchColumn();

if ($currentStatus === false || $currentStatus !== 'active') {
    Session::destroy();
    header('Location: ' . BASE_URL . '/login?access_denied=suspended');
    exit();
}


/**
 * Now that we know who this is, run DoS protection with the
 * authenticated user's id — so their requests are tracked
 * separately from anyone else sharing their IP (campus Wi-Fi,
 * office NAT, etc.), instead of everyone behind that IP sharing
 * one bucket.
 */
DoSProtection::check(Session::user()['id'] ?? null);


/**
 * Require a specific role.
 */
function requireRoleAccess(string $requiredRole): void
{
    $user = Session::user();

    if ($user === null) {

        header(
            'Location: ' . BASE_URL . '/login?access_denied=1'
        );

        exit();
    }

    if (($user['role'] ?? null) !== $requiredRole) {

        http_response_code(403);

        exit('Access denied.');
    }
}