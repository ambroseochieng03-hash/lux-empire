<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Mailer.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

$configuration = new Configuration(
    host: NATS_HOST,
    port: NATS_PORT,
    user: NATS_USER !== '' ? NATS_USER : null,
    pass: NATS_PASS !== '' ? NATS_PASS : null,
);

$client = new Client($configuration);

$stream = $client->getApi()->getStream('OTP_EMAILS');

$consumer = $stream->getConsumer('otp_mailer');
$consumer->getConfiguration()->setSubjectFilter('otp.email');
$consumer->create();

echo "[" . date('Y-m-d H:i:s') . "] LUX EMPIRE OTP worker started, waiting for jobs..." . PHP_EOL;

$queue = $consumer->getQueue();

while (true) {
    $message = $queue->next();

    if ($message === null) {
        continue;
    }

    if (($message->payload->headers['Status-Code'] ?? null) === '408') {
        continue;
    }

    $job = json_decode((string) $message->payload, true);

    if (
        !is_array($job)
        || empty($job['email'])
        || empty($job['name'])
        || empty($job['code'])
    ) {
        error_log(
            'LUX EMPIRE OTP worker: malformed job payload, dropping: '
            . (string) $message->payload
        );
        $message->ack();
        continue;
    }

    try {
        $mailer = new Mailer();

        $sent = $mailer->send(
            $job['email'],
            $job['name'],
            'Your LUX EMPIRE verification code',
            'Your verification code is ' . $job['code'] . '. It expires in 5 minutes.'
        );

        if (!$sent) {
            throw new RuntimeException('Mailer returned false.');
        }

        $message->ack();

        echo "[" . date('Y-m-d H:i:s') . "] Sent OTP to " . $job['email'] . PHP_EOL;
    } catch (Throwable $e) {
        error_log(
            'LUX EMPIRE OTP worker send failed for '
            . $job['email']
            . ': '
            . $e->getMessage()
        );

        $message->nack(10);
    }
}
