<?php

declare(strict_types=1);

require_once '../config/app.php';
require_once '../config/session.php';
require_once '../classes/TrustedDevice.php';

Session::start();

/**
 * Revoke this device's trust before the session (which holds the
 * user id TrustedDevice needs) is destroyed. If nothing was ever
 * trusted for this device, revokeCurrent() is a safe no-op — it just
 * won't find a matching row to delete.
 */
if (Session::isAuthenticated()) {

    $user = Session::user();

    if ($user !== null && isset($user['id'])) {
        $trustedDevice = new TrustedDevice();
        $trustedDevice->revokeCurrent((int) $user['id']);
    }
}

/**
 * Completely destroy the authenticated session.
 */
Session::destroy();

/**
 * Redirect to login.
 */
header(
    'Location: ' . BASE_URL . '/login?success=' .
    urlencode('You have safely exited the Empire.')
);

exit;