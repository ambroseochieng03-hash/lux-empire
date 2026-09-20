<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';
require_once '../../classes/Payment.php';
require_once '../../classes/House.php';
require_once '../../classes/Notification.php';
require_once '../../config/app.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

$rateKey = 'cancel_booking:' . $tenantId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 60);

if ($attempts > 20) {
    RateLimiter::block($rateKey, 120);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

$bookingModel = new Booking();

/*
 * Ownership, "still pending" and the house release all happen inside ONE
 * locked transaction in cancelPendingBooking(), so a tenant cancel and a
 * landlord accept at the same moment can never both succeed.
 */
$result = $bookingModel->cancelPendingBooking($booking_id, $tenantId);

if (!$result['success']) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

$houseTitle = trim((string) ($result['title'] ?? ''));

if ($houseTitle === '' && !empty($result['house_id'])) {
    $house = (new House())->getHouseById((int) $result['house_id']);
    $houseTitle = (string) ($house['title'] ?? '');
}

if ($houseTitle === '') {
    $houseTitle = 'the property';
}

/*
 * Refund the fee in full. The tenant received nothing and the landlord
 * never acted. createAutoRefundForPayment() is safe to call twice: the
 * UNIQUE payment_id on `refunds` means a payment can never be refunded
 * twice, and it returns null for free/waived payments (nothing to refund).
 * If it throws, scripts/reconcile_pending_payments.php creates the missing
 * refund on its next run.
 */
$refundQueued = false;
$refundAmount = (float) BOOKING_FEE_AMOUNT;

if (($result['payment_status'] ?? '') === 'paid' && !empty($result['payment_id'])) {

    try {
        $paymentModel = new Payment();

        $paidRow = $paymentModel->getPaymentStatus((int) $result['payment_id'], $tenantId);

        if ($paidRow) {
            $refundAmount = (float) $paidRow['amount'];
        }

        $refundRef = $paymentModel->createAutoRefundForPayment(
            (int) $result['payment_id'],
            'tenant_cancelled',
            ['booking_id' => $booking_id, 'house_id' => $result['house_id']]
        );

        $refundQueued = ($refundRef !== null);

    } catch (Throwable $e) {
        error_log('LUX EMPIRE cancel_booking: refund creation failed for booking #' . $booking_id . ' — ' . $e->getMessage());
    }
}

try {

    $notification = new Notification();

    $tenantMessage = 'You cancelled your booking request for "' . $houseTitle . '".'
        . ($refundQueued
            ? ' Your KES ' . number_format($refundAmount) . ' booking fee is being refunded automatically to your M-Pesa — you\'ll get a confirmation once it completes.'
            : '');

    $notification->create(
        $tenantId,
        'booking_cancelled',
        'Booking Cancelled',
        $tenantMessage,
        BASE_URL . '/tenant/my-bookings'
    );

    if (!empty($result['landlord_id'])) {
        $notification->create(
            (int) $result['landlord_id'],
            'booking_cancelled_by_tenant',
            'Booking Request Cancelled',
            'A tenant cancelled their booking request for "' . $houseTitle . '". The property is available again.',
            BASE_URL . '/booking-requests'
        );
    }

} catch (Throwable $e) {
    error_log('LUX EMPIRE cancel_booking: notification failed for booking #' . $booking_id . ' — ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => $refundQueued
        ? 'Booking cancelled. Your booking fee is being refunded to your M-Pesa.'
        : 'Booking cancelled.',
    'booking_id' => $booking_id
]);
exit;