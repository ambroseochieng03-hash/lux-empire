<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

/**
 * LUX EMPIRE
 * Publisher for the REFUNDS stream — consumed by
 * workers/refund_worker.php (part 2). Mirrors EmailJobPublisher's
 * shape exactly.
 */
final class RefundJobPublisher
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

    public static function publish(array $job): void
    {
        $stream = self::client()->getApi()->getStream('REFUNDS');
        $stream->put('refund.requested', json_encode($job));
    }
}
