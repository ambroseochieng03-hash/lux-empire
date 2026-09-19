<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';
require_once '../../classes/Payment.php';

if (DARAJA_CALLBACK_SECRET === '' || ($_GET['token'] ?? '') !== DARAJA_CALLBACK_SECRET) {
    error_log('LUX EMPIRE status query result callback: rejected — missing/invalid token.');
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    error_log('LUX EMPIRE status query result callback: unparseable body: ' . $raw);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// Log the FULL raw payload the first several times this fires in
// sandbox — Payment::handleStatusQueryResultCallback()'s key-name
// assumptions need verifying against your actual account's response
// shape before you trust its auto-resolution in production.
error_log('LUX EMPIRE status query result callback: raw payload — ' . $raw);

try {
    $payment = new Payment();
    $payment->handleStatusQueryResultCallback($data);
} catch (Throwable $e) {
    error_log('LUX EMPIRE status query result callback: unhandled error — ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
