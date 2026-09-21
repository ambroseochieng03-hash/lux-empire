<?php

/**
 * LUX EMPIRE
 * Safety net for money the app never heard about, and refunds that were
 * never created.
 *
 * 1) STK payments still 'pending' after 3 minutes: ask Safaricom directly
 *    (Payment::reconcilePendingPayment). It completes them (granting what
 *    was paid for) or marks them failed. Safe to run repeatedly.
 * 2) Paid bookings that ended WITHOUT the landlord accepting (rejected or
 *    cancelled) but have no refund row: create the refund. The UNIQUE
 *    payment_id on `refunds` means a payment can never be refunded twice.
 *
 * Usage: php scripts/reconcile_pending_payments.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Payment.php';

$conn = (new Database())->connect();
$payment = new Payment();

/* ---------- 1) pending STK payments ---------- */

$pendingIds = $conn->query("
    SELECT id FROM payments
    WHERE status = 'pending'
    AND checkout_request_id NOT LIKE 'PAYBILL-%'
    AND checkout_request_id NOT LIKE 'C2B-%'
    AND checkout_request_id NOT LIKE 'WAIVER-%'
    AND created_at <= (NOW() - INTERVAL 3 MINUTE)
    AND created_at >= (NOW() - INTERVAL 2 DAY)
    ORDER BY id ASC
    LIMIT 50
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($pendingIds as $paymentId) {
    try {
        $payment->reconcilePendingPayment((int) $paymentId);
        echo "[" . date('Y-m-d H:i:s') . "] Reconciled pending payment #{$paymentId}." . PHP_EOL;
    } catch (Throwable $e) {
        error_log('LUX EMPIRE reconcile_pending_payments: payment #' . $paymentId . ' failed — ' . $e->getMessage());
    }
}

/* ---------- 2) paid bookings that ended without a refund row ---------- */

$orphans = $conn->query("
    SELECT b.id AS booking_id, b.house_id, b.payment_id, b.status
    FROM bookings b
    JOIN payments p ON p.id = b.payment_id AND p.status = 'completed'
    LEFT JOIN refunds r ON r.payment_id = b.payment_id
    WHERE b.status IN ('rejected', 'cancelled')
    AND b.payment_status = 'paid'
    AND b.payment_id IS NOT NULL
    AND r.id IS NULL
    AND b.booking_date < (NOW() - INTERVAL 10 MINUTE)
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($orphans as $orphan) {

    $reason = $orphan['status'] === 'cancelled' ? 'tenant_cancelled' : 'booking_rejected';

    try {
        $ref = $payment->createAutoRefundForPayment(
            (int) $orphan['payment_id'],
            $reason,
            ['booking_id' => (int) $orphan['booking_id'], 'house_id' => $orphan['house_id'], 'created_by_sweep' => true]
        );

        if ($ref !== null) {
            echo "[" . date('Y-m-d H:i:s') . "] Created missing refund {$ref} for booking #{$orphan['booking_id']}." . PHP_EOL;
        }
    } catch (Throwable $e) {
        error_log('LUX EMPIRE reconcile_pending_payments: refund for booking #' . $orphan['booking_id'] . ' failed — ' . $e->getMessage());
    }
}

/* ---------- 3) vouchers past their expiry date ---------- */

require_once __DIR__ . '/../classes/PaymentWaiver.php';

$expiredVouchers = PaymentWaiver::expireOverdue();

if ($expiredVouchers > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Marked {$expiredVouchers} voucher(s) as expired." . PHP_EOL;
}

if (empty($pendingIds) && empty($orphans) && $expiredVouchers === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Nothing to reconcile." . PHP_EOL;
}
