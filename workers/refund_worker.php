<?php

/**
 * LUX EMPIRE
 * Refund worker — consumes refund.requested from the REFUNDS stream
 * and drives each job through Payment::sendB2cPayment().
 *
 * Money-safety rule: the refunds.status DB row, not this message, is
 * the single source of truth for "has this been sent". The
 * compare-and-swap inside sendB2cPayment() is what actually prevents
 * a double-send — this file is just delivery plumbing and can be
 * redelivered, crashed, or run in parallel without that guarantee
 * breaking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Payment.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

$configuration = new Configuration([
    'host' => NATS_HOST,
    'port' => NATS_PORT,
    'user' => NATS_USER !== '' ? NATS_USER : null,
    'pass' => NATS_PASS !== '' ? NATS_PASS : null,
]);

$client = new Client($configuration);
$stream = $client->getApi()->getStream('REFUNDS');

$consumer = $stream->getConsumer('refund_worker');
$consumer->getConfiguration()->setSubjectFilter('refund.requested');
$consumer->create();

$queue = $consumer->getQueue();

function isControlMessage($message): bool
{
    return ($message->payload->headers['Status-Code'] ?? null) === '408';
}

function handleRefundJob($message): void
{
    $job = json_decode((string) $message->payload, true);

    if (!is_array($job) || empty($job['refund_id'])) {
        error_log('LUX EMPIRE refund worker: malformed job, dropping: ' . (string) $message->payload);
        $message->ack();
        return;
    }

    $refundId = (int) $job['refund_id'];

    try {
        $payment = new Payment();
        $outcome = $payment->sendB2cPayment($refundId);

        echo "[" . date('Y-m-d H:i:s') . "] Refund #{$refundId}: {$outcome}" . PHP_EOL;
        $message->ack();

    } catch (Throwable $e) {
        error_log('LUX EMPIRE refund worker: unhandled error for refund #' . $refundId . ' — ' . $e->getMessage());
        // Safe to redeliver — sendB2cPayment()'s compare-and-swap
        // means a redelivery either claims a still-'pending' row
        // (fine) or no-ops on a 'processing'/'completed' one (fine).
        $message->nack(15);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] LUX EMPIRE refund worker started." . PHP_EOL;

while (true) {
    $message = $queue->next();

    if ($message !== null && !isControlMessage($message)) {
        handleRefundJob($message);
    } else {
        usleep(200000);
    }
}
