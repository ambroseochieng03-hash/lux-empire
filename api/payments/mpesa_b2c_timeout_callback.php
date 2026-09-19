<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';
require_once '../../classes/Payment.php';

if (DARAJA_CALLBACK_SECRET === '' || ($_GET['token'] ?? '') !== DARAJA_CALLBACK_SECRET) {
    error_log('LUX EMPIRE B2C timeout callback: rejected — missing/invalid token.');
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    error_log('LUX EMPIRE B2C timeout callback: unparseable body: ' . $raw);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

try {
    $payment = new Payment();
    $payment->handleB2cTimeoutCallback($data);
} catch (Throwable $e) {
    error_log('LUX EMPIRE B2C timeout callback: unhandled error — ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
