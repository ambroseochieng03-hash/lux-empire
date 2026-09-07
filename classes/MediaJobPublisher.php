<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

/**
 * Thin publisher for background media-processing jobs.
 *
 * Deliberately independent of whatever OtpDelivery.php does
 * internally (I haven't seen that file) rather than guessing at
 * its shape. Once I do see it, if it turns out to already wrap
 * this exact NATS connection logic, the two can be merged into one
 * shared publisher class — not done now to avoid assuming.
 */
final class MediaJobPublisher
{
    private static ?Client $client = null;

    private static function client(): Client
    {
        if (self::$client === null) {
            $configuration = new Configuration([
                'host' => NATS_HOST,
                'port' => NATS_PORT,
                'user' => NATS_USER !== '' ? NATS_USER : null,
                'pass' => NATS_PASS !== '' ? NATS_PASS : null,
            ]);

            self::$client = new Client($configuration);
        }

        return self::$client;
    }

    public static function publishVideoJob(array $job): void
    {
        $stream = self::client()->getApi()->getStream('MEDIA_JOBS');
        $stream->put('media.video.transcode', json_encode($job));
    }

    public static function publishImageJob(array $job): void
    {
        $stream = self::client()->getApi()->getStream('MEDIA_JOBS');
        $stream->put('media.image.compress', json_encode($job));
    }
}
