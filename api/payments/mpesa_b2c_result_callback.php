<?php

/**
 * LUX EMPIRE
 * Daraja B2C result callback — public by necessity (Safaricom calls
 * this server-to-server). Protected only by the shared-secret token
 * in the URL (Daraja itself never signs callbacks).
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';
require_once '../../classes/Payment.php';

if (DARAJA_CALLBACK_SECRET === '' || ($_GET['token'] ?? '') !== DARAJA_CALLBACK_SECRET) {
    error_log('LUX EMPIRE B2C result callback: rejected — missing/invalid token.');
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    error_log('LUX EMPIRE B2C result callback: unparseable body: ' . $raw);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

error_log('LUX EMPIRE B2C result callback: raw payload — ' . $raw);

try {
    $payment = new Payment();
    $payment->handleB2cResultCallback($data);
} catch (Throwable $e) {
    error_log('LUX EMPIRE B2C result callback: unhandled error — ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);