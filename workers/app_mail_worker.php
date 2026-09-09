<?php

/**
 * LUX EMPIRE
 * APP_EMAILS worker — polls one queue per email subject (see
 * scripts/setup_app_emails_stream.php), in the same round-robin
 * polling shape as workers/media_worker.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/EmailTemplate.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

$configuration = new Configuration([
    'host' => NATS_HOST,
    'port' => NATS_PORT,
    'user' => NATS_USER !== '' ? NATS_USER : null,
    'pass' => NATS_PASS !== '' ? NATS_PASS : null,
]);

$client = new Client($configuration);
$stream = $client->getApi()->getStream('APP_EMAILS');

$consumerNames = [
    'email.landlord_verified'       => 'app_email_landlord_verified',
    'email.driver_verified'         => 'app_email_driver_verified',
    'email.booking_accepted'        => 'app_email_booking_accepted',
    'email.booking_rejected'        => 'app_email_booking_rejected',
    'email.truck_request_accepted'  => 'app_email_truck_request_accepted',
    'email.admin_broadcast'         => 'app_email_admin_broadcast',
    'email.admin_direct_message'    => 'app_email_admin_direct_message',
    'email.emergency_acknowledged' => 'app_email_emergency_acknowledged'
];

$queues = [];

foreach ($consumerNames as $subject => $consumerName) {
    $consumer = $stream->getConsumer($consumerName);
    $consumer->getConfiguration()->setSubjectFilter($subject);
    $consumer->create();
    $queues[$subject] = $consumer->getQueue();
}

$mailer = new Mailer();

function isControlMessage($message): bool
{
    return ($message->payload->headers['Status-Code'] ?? null) === '408';
}

function buildEmail(string $subject, array $job): ?array
{
    switch ($subject) {

        case 'email.landlord_verified':
        case 'email.driver_verified':
            $role = ucfirst($job['role'] ?? 'account');
            return [
                'subject' => 'Your LUX EMPIRE ' . $role . ' account has been verified',
                'body' => EmailTemplate::render(
                    'Account Verified',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Your ' . $role . ' account has been verified by LUX EMPIRE administration. You now have full access to your dashboard.',
                    'Go to your dashboard',
                    BASE_URL . '/' . strtolower($job['role'] ?? '')
                ),
            ];

        case 'email.booking_accepted':
            return [
                'subject' => 'Your booking request has been accepted',
                'body' => EmailTemplate::render(
                    'Booking Accepted',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Great news — your booking request for "' . htmlspecialchars($job['house_title'] ?? 'the property') . '" has been accepted.',
                    'View my bookings',
                    BASE_URL . '/tenant/my-bookings'
                ),
            ];

        case 'email.booking_rejected':
            return [
                'subject' => 'Update on your booking request',
                'body' => EmailTemplate::render(
                    'Booking Update',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Your booking request for "' . htmlspecialchars($job['house_title'] ?? 'the property') . '" was not accepted this time.',
                    'Browse more homes',
                    BASE_URL . '/browse'
                ),
            ];

        case 'email.truck_request_accepted':
            return [
                'subject' => 'A driver has accepted your move request',
                'body' => EmailTemplate::render(
                    'Driver Assigned',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    htmlspecialchars($job['driver_name'] ?? 'A driver') . ' has accepted your truck request and will be in touch.',
                    'Track your driver',
                    BASE_URL . '/tenant/track-driver'
                ),
            ];

        case 'email.emergency_acknowledged':
            return [
                'subject' => 'We\'ve received your emergency alert',
                'body' => EmailTemplate::render(
                    'Your Alert Has Been Received',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'We\'ve received your emergency alert and it is being reviewed right now by the LUX EMPIRE safety team.' .
                    (!empty($job['message']) ? '<br><br><em>Your message: "' . htmlspecialchars($job['message']) . '"</em>' : '') .
                    '<br><br>If your situation is life-threatening, please also contact local emergency services immediately. ' .
                    'We will follow up as soon as possible.',
                    'View my notifications',
                    BASE_URL . '/' . strtolower($job['role'] ?? 'tenant') . '/notifications'
                ),
            ];

        case 'email.admin_broadcast':
        case 'email.admin_direct_message':
            return [
                'subject' => $job['subject'] ?? 'A message from LUX EMPIRE',
                'body' => EmailTemplate::render(
                    $job['subject'] ?? 'A message from LUX EMPIRE',
                    nl2br(htmlspecialchars($job['body'] ?? ''))
                ),
            ];

        default:
            return null;
    }
}

function handleJob($message, string $subject, Mailer $mailer): void
{
    $job = json_decode((string) $message->payload, true);

    if (!is_array($job) || empty($job['email'])) {
        error_log('LUX EMPIRE app mail worker: malformed job on ' . $subject . ', dropping: ' . (string) $message->payload);
        $message->ack();
        return;
    }

    $email = buildEmail($subject, $job);

    if ($email === null) {
        error_log('LUX EMPIRE app mail worker: no template for subject ' . $subject . ', dropping.');
        $message->ack();
        return;
    }

    try {
        $sent = $mailer->send($job['email'], $job['name'] ?? '', $email['subject'], $email['body']);

        if (!$sent) {
            throw new RuntimeException('Mailer returned false.');
        }

        $message->ack();
        echo "[" . date('Y-m-d H:i:s') . "] Sent " . $subject . " to " . $job['email'] . PHP_EOL;

    } catch (Throwable $e) {
        error_log('LUX EMPIRE app mail worker send failed for ' . $job['email'] . ' (' . $subject . '): ' . $e->getMessage());
        $message->nack(10);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] LUX EMPIRE app mail worker started (" . count($queues) . " subjects)." . PHP_EOL;

while (true) {
    $handledSomething = false;

    foreach ($queues as $subject => $queue) {
        $message = $queue->next();

        if ($message !== null && !isControlMessage($message)) {
            $handledSomething = true;
            handleJob($message, $subject, $mailer);
        }
    }

    if (!$handledSomething) {
        usleep(200000);
    }
}
