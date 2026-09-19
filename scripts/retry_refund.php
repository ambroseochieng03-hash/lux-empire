<?php

/**
 * LUX EMPIRE
 * Manual retry for a refund sitting in 'failed' or 'pending' after
 * you've fixed whatever caused it (e.g. wrong shortcode, broken cert).
 *
 * SAFETY: refuses 'processing' rows. Those are AMBIGUOUS (Safaricom
 * accepted the request and we don't know the outcome) and can only be
 * resolved by Safaricom's own answer (callback / reconciliation) or an
 * admin confirming the money moved. 'failed' rows are always definite
 * failures, so resetting them cannot cause a double refund.
 *
 * Usage: php scripts/retry_refund.php <refund_id>
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/RefundJobPublisher.php';
require_once __DIR__ . '/../config/db.php';

$refundId = (int) ($argv[1] ?? 0);

if ($refundId <= 0) {
    fwrite(STDERR, "Usage: php scripts/retry_refund.php <refund_id>\n");
    exit(1);
}

$database = new Database();
$conn = $database->connect();

$stmt = $conn->prepare("SELECT * FROM refunds WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $refundId]);
$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if ($refund === null) {
    fwrite(STDERR, "Refund #{$refundId} not found.\n");
    exit(1);
}

if ($refund['status'] === 'completed') {
    echo "Refund #{$refundId} is already completed — nothing to do.\n";
    exit(0);
}

if (!in_array($refund['status'], ['failed', 'pending'], true)) {
    fwrite(STDERR, "Refund #{$refundId} is in status '{$refund['status']}' — only 'failed'/'pending' rows are safe to reset here. A 'processing' row must be resolved by Safaricom's answer or by an admin.\n");
    exit(1);
}

$conn->prepare("
    UPDATE refunds
    SET status = 'pending', attempts = 0, last_error = NULL, needs_admin_review = 0,
        mpesa_originator_conversation_id = NULL, mpesa_conversation_id = NULL
    WHERE id = :id AND status IN ('failed', 'pending')
")->execute([':id' => $refundId]);

RefundJobPublisher::publish([
    'refund_id' => $refundId,
    'refund_reference' => $refund['refund_reference'],
]);

echo "Refund #{$refundId} reset to pending and republished.\n";