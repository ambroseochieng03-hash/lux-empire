<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

/**
 * LUX EMPIRE
 * Publisher for the APP_EMAILS stream — every transactional email
 * except OTP, which keeps its own OTP_EMAILS stream and
 * workers/otp_mail_worker.php, both untouched by this.
 */
final class EmailJobPublisher
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

    /**
     * $subject must be one of the subjects registered on APP_EMAILS
     * (see scripts/setup_app_emails_stream.php).
     */
    public static function publish(string $subject, array $job): void
    {
        $stream = self::client()->getApi()->getStream('APP_EMAILS');
        $stream->put($subject, json_encode($job));
    }
}
