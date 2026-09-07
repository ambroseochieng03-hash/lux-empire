<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

final class OtpDelivery
{
    public const STREAM_NAME = 'OTP_EMAILS';
    public const SUBJECT = 'otp.email';

    public static function sendOtpEmail(string $email, string $name, string $code): void
    {
        $client = self::connect();

        $stream = $client->getApi()->getStream(self::STREAM_NAME);

        $payload = json_encode([
            'email' => $email,
            'name' => $name,
            'code' => $code,
            'queued_at' => time(),
        ], JSON_THROW_ON_ERROR);

        $stream->put(self::SUBJECT, $payload);
    }

    private static function connect(): Client
    {
        $configuration = new Configuration(
            host: NATS_HOST,
            port: NATS_PORT,
            user: NATS_USER !== '' ? NATS_USER : null,
            pass: NATS_PASS !== '' ? NATS_PASS : null,
        );

        return new Client($configuration);
    }
}
