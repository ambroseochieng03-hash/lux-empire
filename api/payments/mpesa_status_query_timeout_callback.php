<?php

/**
 * LUX EMPIRE
 * The status QUERY's own timeout — different from the B2C payment's
 * timeout (file #8). This just means Safaricom couldn't answer our
 * question in time; the row was already left untouched and flagged
 * needs_admin_review by reconcileStuckRefund(), and the next cron
 * sweep pass will simply ask again once the reconcile spacing window
 * has passed. Nothing to action here beyond logging.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';

if (DARAJA_CALLBACK_SECRET === '' || ($_GET['token'] ?? '') !== DARAJA_CALLBACK_SECRET) {
    error_log('LUX EMPIRE status query timeout callback: rejected — missing/invalid token.');
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$raw = file_get_contents('php://input');
error_log('LUX EMPIRE status query timeout callback: ' . $raw);

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
