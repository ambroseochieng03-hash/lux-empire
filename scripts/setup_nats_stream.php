<?php

/**
 * LUX EMPIRE
 * NATS JetStream setup — run ONCE, from the command line:
 *
 *   php scripts/setup_nats_stream.php
 *
 * Creates the OTP_EMAILS stream with work-queue retention (each
 * message is consumed exactly once and then removed — appropriate
 * for a one-shot job queue, not an event log) and file-backed
 * storage (durable across a NATS server restart, unlike MEMORY).
 *
 * Safe to re-run — if the stream already exists with this
 * configuration it's a no-op.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;

$configuration = new Configuration(
    host: NATS_HOST,
    port: NATS_PORT,
    user: NATS_USER !== '' ? NATS_USER : null,
    pass: NATS_PASS !== '' ? NATS_PASS : null,
);

$client = new Client($configuration);

if (!$client->ping()) {
    fwrite(STDERR, "Could not reach NATS server at " . NATS_HOST . ':' . NATS_PORT . PHP_EOL);
    exit(1);
}

$stream = $client->getApi()->getStream('OTP_EMAILS');

$stream->getConfiguration()
    ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
    ->setStorageBackend(StorageBackend::FILE)
    ->setSubjects(['otp.email']);

$stream->create();

echo "OTP_EMAILS stream ready." . PHP_EOL;
