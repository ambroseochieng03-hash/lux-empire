<?php

/**
 * LUX EMPIRE
 * Finds refunds stuck in 'processing' past
 * REFUND_STUCK_PROCESSING_TIMEOUT_SECONDS and asks Safaricom directly
 * via TransactionStatusQuery instead of ever assuming it's safe to
 * just resend. Also re-checks rows that already had one inconclusive
 * reconcile attempt, spaced out by the same window so we don't spam
 * Safaricom while a transaction is still genuinely in flight.
 *
 * Run on a schedule via lux-refund-reconcile.timer (systemd), not cron.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../config/db.php';

$database = new Database();
$conn = $database->connect();

$stmt = $conn->prepare("
    SELECT id FROM refunds
    WHERE status = 'processing'
    AND created_at <= (NOW() - INTERVAL :seconds1 SECOND)
    AND (last_reconcile_attempt_at IS NULL OR last_reconcile_attempt_at <= (NOW() - INTERVAL :seconds2 SECOND))
");
$stmt->execute([
    ':seconds1' => REFUND_STUCK_PROCESSING_TIMEOUT_SECONDS,
    ':seconds2' => REFUND_STUCK_PROCESSING_TIMEOUT_SECONDS,
]);

$stuckIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($stuckIds)) {
    echo "[" . date('Y-m-d H:i:s') . "] No stuck refunds found." . PHP_EOL;
    exit(0);
}

$payment = new Payment();

foreach ($stuckIds as $refundId) {
    echo "[" . date('Y-m-d H:i:s') . "] Reconciling refund #{$refundId}..." . PHP_EOL;
    $payment->reconcileStuckRefund((int) $refundId);
}
