<?php

/**
 * LUX EMPIRE
 * REFUNDS JetStream setup — one subject, one durable consumer,
 * consumed by workers/refund_worker.php (part 2).
 *
 * WORK_QUEUE retention: each message goes to exactly one consumer
 * instance and is removed once acked — same choice already made for
 * APP_EMAILS (see setup_app_emails_stream.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;

$configuration = new Configuration([
    'host' => NATS_HOST,
    'port' => NATS_PORT,
    'user' => NATS_USER !== '' ? NATS_USER : null,
    'pass' => NATS_PASS !== '' ? NATS_PASS : null,
]);

$client = new Client($configuration);

if (!$client->ping()) {
    fwrite(STDERR, "Could not reach NATS server at " . NATS_HOST . ':' . NATS_PORT . PHP_EOL);
    exit(1);
}

$stream = $client->getApi()->getStream('REFUNDS');

$stream->getConfiguration()
    ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
    ->setStorageBackend(StorageBackend::FILE)
    ->setSubjects(['refund.requested']);

$stream->create();

echo "REFUNDS stream ready." . PHP_EOL;

$consumer = $client->getApi()->getStream('REFUNDS')->getConsumer('refund_worker');
$consumer->getConfiguration()->setSubjectFilter('refund.requested');
$consumer->create();

echo "  consumer 'refund_worker' ready for subject 'refund.requested'." . PHP_EOL;
