<?php

/**
 * LUX EMPIRE
 * Daraja STK Push callback receiver.
 *
 * Public by necessity (Safaricom calls this server-to-server, no
 * session/CSRF applies) — must always return HTTP 200 with a JSON
 * body Safaricom recognizes, regardless of what happened internally,
 * or Safaricom will retry aggressively. All real error handling
 * happens via logging, never via the HTTP response to Safaricom.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';
require_once '../../classes/Payment.php';

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    error_log('LUX EMPIRE STK callback: unparseable body: ' . $raw);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

try {
    $payment = new Payment();
    $payment->handleStkCallback($data);
} catch (Throwable $e) {
    error_log('LUX EMPIRE STK callback: unhandled error — ' . $e->getMessage());
}

// Always acknowledge — Safaricom's retry behavior on non-200/malformed
// responses will otherwise flood this endpoint.
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
