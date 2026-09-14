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
    'email.new_booking_request'     => 'app_email_new_booking_request',
    'email.new_truck_request'       => 'app_email_new_truck_request',
    'email.booking_accepted'        => 'app_email_booking_accepted',
    'email.booking_rejected'        => 'app_email_booking_rejected',
    'email.truck_request_accepted'  => 'app_email_truck_request_accepted',
    'email.truck_daily_reminder'    => 'app_email_truck_daily_reminder',
    'email.truck_hour_reminder'     => 'app_email_truck_hour_reminder',
    'email.truck_tenant_reminder'   => 'app_email_truck_tenant_reminder',
    'email.admin_broadcast'         => 'app_email_admin_broadcast',
    'email.admin_direct_message'    => 'app_email_admin_direct_message',
    'email.emergency_acknowledged' => 'app_email_emergency_acknowledged',
    'email.payment_confirmed' => 'app_email_payment_confirmed',
    'email.landlord_pro_activated'  => 'app_email_landlord_pro_activated',
    'email.wallet_negative' => 'app_email_wallet_negative'
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

            case 'email.new_truck_request':

            $isScheduled = ($job['trip_type'] ?? 'instant') === 'scheduled';

            $whenLine = $isScheduled
                ? 'Scheduled for: ' . htmlspecialchars(date('M d, Y \a\t g:i A', strtotime($job['scheduled_at'] ?? 'now')))
                : 'Requested for: right now (instant move)';

            $items = is_array($job['items'] ?? null) ? $job['items'] : [];

            $itemsHtml = count($items) > 0
                ? '<ul style="margin:10px 0 0 0; padding-left:20px;">' .
                    implode('', array_map(static fn($item) => '<li>' . htmlspecialchars($item) . '</li>', $items)) .
                  '</ul>'
                : '<em>No items listed.</em>';

            $distanceLine = !empty($job['distance_km'])
                ? '<br>Distance: approx. ' . htmlspecialchars((string) $job['distance_km']) . ' km'
                : '';

            return [
                'subject' => ($isScheduled ? 'New scheduled move request' : 'New instant move request') . ' available',
                'body' => EmailTemplate::render(
                    'New Move Request',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    htmlspecialchars($job['tenant_name'] ?? 'A tenant') . ' has posted a new ' .
                    ($isScheduled ? 'scheduled' : 'instant') . ' move request:<br><br>' .
                    '<strong>Type:</strong> ' . ($isScheduled ? 'Scheduled' : 'Instant (move now)') . '<br>' .
                    '<strong>' . $whenLine . '</strong><br>' .
                    'Pickup: ' . htmlspecialchars($job['pickup_location'] ?? '') . '<br>' .
                    'Destination: ' . htmlspecialchars($job['destination'] ?? '') .
                    $distanceLine . '<br><br>' .
                    '<strong>Items (' . count($items) . '):</strong>' .
                    $itemsHtml,
                    'View this request',
                    BASE_URL . '/driver/available-requests'
                ),
            ];

        case 'email.new_booking_request':
            return [
                'subject' => 'New booking request for "' . ($job['house_title'] ?? 'your property') . '"',
                'body' => EmailTemplate::render(
                    'New Booking Request',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    htmlspecialchars($job['tenant_name'] ?? 'A tenant') . ' has requested to book "' .
                    htmlspecialchars($job['house_title'] ?? 'your property') . '".',
                    'Review this request',
                    BASE_URL . '/booking-requests'
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

        case 'email.truck_daily_reminder':
            return [
                'subject' => 'Reminder: upcoming scheduled move',
                'body' => EmailTemplate::render(
                    'Upcoming Scheduled Move',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'This is a reminder that you have a scheduled move from "' . htmlspecialchars($job['pickup_location'] ?? '') .
                    '" to "' . htmlspecialchars($job['destination'] ?? '') . '" on ' .
                    htmlspecialchars(date('M d, Y g:i A', strtotime($job['scheduled_at'] ?? 'now'))) . '.',
                    'View trip details',
                    BASE_URL . '/driver/active-trip'
                ),
            ];

        case 'email.truck_hour_reminder':
            return [
                'subject' => 'Your scheduled move starts in about an hour',
                'body' => EmailTemplate::render(
                    'Move Starting Soon',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Your scheduled move from "' . htmlspecialchars($job['pickup_location'] ?? '') .
                    '" to "' . htmlspecialchars($job['destination'] ?? '') . '" starts in about an hour.',
                    'View trip details',
                    BASE_URL . '/driver/active-trip'
                ),
            ];

        case 'email.truck_tenant_reminder':
            return [
                'subject' => 'Your move starts in about an hour',
                'body' => EmailTemplate::render(
                    'Your Move Starts Soon',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Your scheduled move to "' . htmlspecialchars($job['destination'] ?? '') . '" starts in about an hour. ' .
                    htmlspecialchars($job['driver_name'] ?? 'Your driver') . ' will be on the way soon.',
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

        case 'email.payment_confirmed':
            return [
                'subject' => 'Payment confirmed',
                'body' => EmailTemplate::render(
                    'Payment Confirmed',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'We have received and confirmed your payment of KES ' . htmlspecialchars(number_format((float) ($job['amount'] ?? 0), 2)) . '. Thank you for your prompt payment.',
                    'View my payments',
                    BASE_URL . '/tenant/my-payments'
                ),
            ];
            
        case 'email.landlord_pro_activated':
            return [
                'subject' => 'Your LUX EMPIRE Pro plan is active',
                'body' => EmailTemplate::render(
                    'Pro Plan Activated',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Thanks for upgrading — your LUX EMPIRE Pro plan is now active for the next 30 days. ' .
                    'We received your payment of KES ' . htmlspecialchars($job['amount'] ?? '') . '.<br><br>' .
                    'You now have access to more listings, more photos per listing, video uploads, and priority placement in search.',
                    'Manage your properties',
                    BASE_URL . '/manage-houses'
                ),
            ];
            
        case 'email.wallet_negative':
            return [
                'subject' => 'Your commission wallet balance is low',
                'body' => EmailTemplate::render(
                    'Wallet Balance Low',
                    'Hi ' . htmlspecialchars($job['name'] ?? '') . ',<br><br>' .
                    'Your commission wallet balance is now KES ' . htmlspecialchars(number_format((float) ($job['balance'] ?? 0), 2)) . ' after your recent trip. Please top up your wallet to stay in good standing and continue accepting jobs.',
                    'Top up my wallet',
                    BASE_URL . '/driver/wallet'
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
