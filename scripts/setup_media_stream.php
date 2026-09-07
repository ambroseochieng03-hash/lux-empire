<?php

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

$stream = $client->getApi()->getStream('MEDIA_JOBS');

$stream->getConfiguration()
    ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
    ->setStorageBackend(StorageBackend::FILE)
    ->setSubjects(['media.video.transcode', 'media.image.compress']);

$stream->create();

echo "MEDIA_JOBS stream ready (video + image subjects)." . PHP_EOL;

// Separate consumer for image jobs — kept distinct from the existing
// 'media_transcoder' consumer rather than widening that consumer's
// subject filter, since changing a durable consumer's filter after
// creation isn't something I could confirm is safe with this NATS
// server/library version. Two consumers on the same stream avoids
// that question entirely.
$imageConsumer = $client->getApi()->getStream('MEDIA_JOBS')->getConsumer('media_image_compressor');
$imageConsumer->getConfiguration()->setSubjectFilter('media.image.compress');
$imageConsumer->create();

echo "media_image_compressor consumer ready." . PHP_EOL;
