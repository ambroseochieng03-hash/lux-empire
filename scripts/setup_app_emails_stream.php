<?php

/**
 * LUX EMPIRE
 * APP_EMAILS JetStream setup.
 *
 * One durable consumer PER subject — the same choice already made
 * in scripts/setup_media_stream.php (see that file's comment): this
 * NATS client's message-object shape for a wildcard/multi-subject
 * consumer isn't confirmed from anything in this repo, so each
 * email type gets its own consumer/queue instead, exactly like the
 * existing media_transcoder / media_image_compressor pair.
 *
 * Safe to re-run.
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

// subject => consumer name
$consumers = [
    'email.landlord_verified'       => 'app_email_landlord_verified',
    'email.driver_verified'         => 'app_email_driver_verified',
    'email.booking_accepted'        => 'app_email_booking_accepted',
    'email.booking_rejected'        => 'app_email_booking_rejected',
    'email.truck_request_accepted'  => 'app_email_truck_request_accepted',
    'email.admin_broadcast'         => 'app_email_admin_broadcast',
    'email.admin_direct_message'    => 'app_email_admin_direct_message',
];

$stream = $client->getApi()->getStream('APP_EMAILS');

$stream->getConfiguration()
    ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
    ->setStorageBackend(StorageBackend::FILE)
    ->setSubjects(array_keys($consumers));

$stream->create();

echo "APP_EMAILS stream ready." . PHP_EOL;

foreach ($consumers as $subject => $consumerName) {
    $consumer = $client->getApi()->getStream('APP_EMAILS')->getConsumer($consumerName);
    $consumer->getConfiguration()->setSubjectFilter($subject);
    $consumer->create();

    echo "  consumer '{$consumerName}' ready for subject '{$subject}'." . PHP_EOL;
}
