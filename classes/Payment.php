<?php

/**
 * LUX EMPIRE
 * Payment Service
 *
 * Owns everything M-Pesa: STK push initiation, OAuth token caching,
 * the STK callback's state transition, and C2B receipt matching for
 * the "I already paid, here's my code" self-service path.
 *
 * Money-state transitions (marking a payment completed + granting
 * the entitlement) happen ONLY in handleStkCallback() and
 * matchC2bReceipt() — both inside a single DB transaction each, and
 * both idempotent against replays (Safaricom can and does call back
 * more than once for the same transaction).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/RedisConnection.php';
require_once __DIR__ . '/House.php';
require_once __DIR__ . '/EmailJobPublisher.php';

require_once __DIR__ . '/Notification.php';

final class Payment
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * ============================================================
     * OAUTH TOKEN (cached in Redis — Daraja tokens last ~3600s)
     * ============================================================
     */
    private function getAccessToken(): string
    {
        $redis = RedisConnection::get();
        $cacheKey = 'daraja:access_token';

        $cached = $redis->get($cacheKey);

        if ($cached !== false) {
            return (string) $cached;
        }

        $ch = curl_init(DARAJA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(DARAJA_CONSUMER_KEY . ':' . DARAJA_CONSUMER_SECRET)
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new RuntimeException('Failed to obtain Daraja access token (HTTP ' . $httpCode . ').');
        }

        $data = json_decode((string) $response, true);

        if (!isset($data['access_token'])) {
            throw new RuntimeException('Daraja token response missing access_token.');
        }

        // Cache for slightly less than the stated lifetime so we never
        // hand out a token that expires mid-request.
        $expiresIn = (int) ($data['expires_in'] ?? 3599);
        $redis->setex($cacheKey, max(60, $expiresIn - 60), $data['access_token']);

        return (string) $data['access_token'];
    }

    /**
     * Normalize a Kenyan phone number to Daraja's required
     * 2547XXXXXXXX / 2541XXXXXXXX format. Returns null if the input
     * doesn't look like a valid Safaricom-shaped number.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 9) {
            $digits = '254' . $digits;
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 9) {
            $digits = '254' . $digits;
        }

        if (!preg_match('/^254(7|1)\d{8}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * ============================================================
     * INITIATE STK PUSH
     *
     * $purpose must be one of payments.purpose's enum values.
     * $amount and $phone are trusted ONLY from server-side
     * constants/session — callers must never pass through raw
     * client-submitted values for $amount.
     * ============================================================
     */
    public function initiateStkPush(
        int $userId,
        string $purpose,
        float $amount,
        string $phone,
        array $metadata = []
    ): array {

        $normalizedPhone = self::normalizePhone($phone);

        if ($normalizedPhone === null) {
            return ['success' => false, 'message' => 'Enter a valid Safaricom number, e.g. 07XX XXX XXX.'];
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Throwable $e) {
            error_log('LUX EMPIRE Payment: token fetch failed — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment service is temporarily unavailable. Please try again shortly.'];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode(DARAJA_SHORTCODE . DARAJA_PASSKEY . $timestamp);

        $accountReference = strtoupper($purpose) . '-' . $userId . '-' . time();

        $payload = [
            'BusinessShortCode' => DARAJA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amount), // Daraja expects a whole-number amount
            'PartyA' => $normalizedPhone,
            'PartyB' => DARAJA_SHORTCODE,
            'PhoneNumber' => $normalizedPhone,
            'CallBackURL' => DARAJA_CALLBACK_URL,
            'AccountReference' => substr($accountReference, 0, 20),
            'TransactionDesc' => ucfirst(str_replace('_', ' ', $purpose)),
        ];

        $ch = curl_init(DARAJA_BASE_URL . '/mpesa/stkpush/v1/processrequest');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        if ($httpCode !== 200 || !isset($data['CheckoutRequestID'])) {
            error_log('LUX EMPIRE Payment: STK push rejected — ' . (string) $response);
            return [
                'success' => false,
                'message' => $data['errorMessage'] ?? 'Could not initiate M-Pesa payment. Please try again.',
            ];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO payments
                (user_id, purpose, amount, phone, status, checkout_request_id, merchant_request_id, metadata)
            VALUES
                (:user_id, :purpose, :amount, :phone, 'pending', :checkout_id, :merchant_id, :metadata)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose,
            ':amount' => $amount,
            ':phone' => $normalizedPhone,
            ':checkout_id' => $data['CheckoutRequestID'],
            ':merchant_id' => $data['MerchantRequestID'] ?? null,
            ':metadata' => json_encode($metadata),
        ]);

        return [
            'success' => true,
            'payment_id' => (int) $this->conn->lastInsertId(),
            'checkout_request_id' => $data['CheckoutRequestID'],
            'message' => 'Enter your M-Pesa PIN on your phone to complete payment.',
        ];
    }

    /**
     * ============================================================
     * HANDLE STK CALLBACK
     *
     * Idempotent: the conditional UPDATE (status='pending' in the
     * WHERE clause) means a duplicate callback simply matches zero
     * rows the second time — same compare-and-swap pattern as
     * IdempotencyGuard's reclaim logic and Booking::acceptBooking().
     * ============================================================
     */
    public function handleStkCallback(array $callback): void
    {
        $stkCallback = $callback['Body']['stkCallback'] ?? null;

        if ($stkCallback === null || !isset($stkCallback['CheckoutRequestID'])) {
            error_log('LUX EMPIRE Payment: malformed STK callback: ' . json_encode($callback));
            return;
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'];
        $resultCode = (int) ($stkCallback['ResultCode'] ?? 1);

        if ($resultCode !== 0) {
            // User cancelled, insufficient funds, timeout, etc.
            $this->conn->prepare("
                UPDATE payments SET status = 'failed'
                WHERE checkout_request_id = :id AND status = 'pending'
            ")->execute([':id' => $checkoutRequestId]);

            return;
        }

        $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $values = [];
        foreach ($items as $item) {
            if (isset($item['Name'])) {
                $values[$item['Name']] = $item['Value'] ?? null;
            }
        }

        $mpesaReceipt = $values['MpesaReceiptNumber'] ?? null;

        if ($mpesaReceipt === null) {
            error_log('LUX EMPIRE Payment: successful callback missing receipt: ' . json_encode($callback));
            return;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE payments
                SET status = 'completed', mpesa_receipt = :receipt
                WHERE checkout_request_id = :id AND status = 'pending'
            ");

            $stmt->execute([':receipt' => $mpesaReceipt, ':id' => $checkoutRequestId]);

            if ($stmt->rowCount() === 0) {
                // Already processed by an earlier callback delivery — nothing more to do.
                $this->conn->rollBack();
                return;
            }

            $payment = $this->getPaymentByCheckoutId($checkoutRequestId);

            if ($payment !== null) {
                $this->applyEntitlement($payment);
            }

            $this->conn->commit();

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('LUX EMPIRE Payment: callback processing failed — ' . $e->getMessage());
        }
    }

    private function getPaymentByCheckoutId(string $checkoutRequestId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE checkout_request_id = :id LIMIT 1");
        $stmt->execute([':id' => $checkoutRequestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Grant whatever the payment was for. Called only from inside
     * the transaction in handleStkCallback() / matchC2bReceipt(),
     * once, guaranteed by the conditional UPDATE above.
     */
    private function applyEntitlement(array $payment): void
    {
        $metadata = json_decode((string) ($payment['metadata'] ?? '{}'), true) ?: [];

        switch ($payment['purpose']) {

            case 'landlord_pro':
                // Extend from whichever is later: now, or their current
                // expiry — so renewing before expiry stacks time rather
                // than wasting the remaining days.
                $stmt = $this->conn->prepare("
                    UPDATE users
                    SET plan_tier = 'pro',
                        plan_expires_at = DATE_ADD(
                            GREATEST(COALESCE(plan_expires_at, NOW()), NOW()),
                            INTERVAL 30 DAY
                        )
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $payment['user_id']]);

                (new Notification())->create(
                    (int) $payment['user_id'],
                    'payment',
                    'Pro plan activated',
                    'Your LUX EMPIRE Pro plan is now active for 30 days.',
                    BASE_URL . '/manage-houses'
                );
                break;

            case 'booking_fee':

                $houseId = (int) ($metadata['house_id'] ?? 0);

                if ($houseId <= 0) {
                    break;
                }

                // Row-locked read — this is the entire race-condition fix. Every
                // concurrent callback for a different payment on the same house
                // queues behind this lock; only the first to arrive can see
                // status = 'available' and proceed.
                $houseLock = $this->conn->prepare("
                    SELECT status, landlord_id, title
                    FROM houses
                    WHERE id = :id
                    FOR UPDATE
                ");
                $houseLock->execute([':id' => $houseId]);
                $house = $houseLock->fetch(PDO::FETCH_ASSOC);

                if (!$house || $house['status'] !== 'available') {

                    // Payment succeeded but the house is already gone — cannot be
                    // avoided without instant refunds (needs Daraja B2C, not set
                    // up yet). Flag it clearly so admin can process a manual
                    // refund, and tell the tenant plainly rather than pretending
                    // their booking went through.
                    $this->conn->prepare("
                        UPDATE payments
                        SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.refund_required', true)
                        WHERE id = :id
                    ")->execute([':id' => $payment['id']]);

                    (new Notification())->create(
                        (int) $payment['user_id'],
                        'payment_refund_pending',
                        'This property was just taken',
                        'Someone secured this property moments before your payment completed. Your KES ' . number_format((float) $payment['amount']) . ' booking fee will be refunded — our team has been notified and will process it shortly.',
                        BASE_URL . '/tenant/my-bookings'
                    );

                    error_log('LUX EMPIRE: booking_fee payment #' . $payment['id'] . ' needs manual refund — house ' . $houseId . ' no longer available.');

                    break;
                }

                $bookingInsert = $this->conn->prepare("
                    INSERT INTO bookings (tenant_id, house_id, landlord_id, status, payment_status, payment_id)
                    VALUES (:tenant_id, :house_id, :landlord_id, 'pending', 'paid', :payment_id)
                ");
                $bookingInsert->execute([
                    ':tenant_id' => $payment['user_id'],
                    ':house_id' => $houseId,
                    ':landlord_id' => $house['landlord_id'],
                    ':payment_id' => $payment['id'],
                ]);
                $bookingId = (int) $this->conn->lastInsertId();

                $this->conn->prepare("
                    UPDATE houses
                    SET status = 'reserved', reserved_by_booking_id = :booking_id
                    WHERE id = :house_id
                ")->execute([':booking_id' => $bookingId, ':house_id' => $houseId]);

                // Tenant confirmation — immediate, per your requirement.
                $tenantRow = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
                $tenantRow->execute([':id' => $payment['user_id']]);
                $tenant = $tenantRow->fetch(PDO::FETCH_ASSOC);

                (new Notification())->create(
                    (int) $payment['user_id'],
                    'payment_confirmed',
                    'Booking fee paid',
                    'Your KES ' . number_format((float) $payment['amount']) . ' booking fee for "' . $house['title'] . '" was received. The landlord has been notified.',
                    BASE_URL . '/tenant/my-bookings'
                );

                if ($tenant) {
                    EmailJobPublisher::publish('email.payment_confirmed', [
                        'email' => $tenant['email'],
                        'name' => $tenant['full_name'],
                        'house_title' => $house['title'],
                        'amount' => number_format((float) $payment['amount']),
                    ]);
                }

                // Landlord notification — only fires now, once payment is real.
                (new Notification())->create(
                    (int) $house['landlord_id'],
                    'new_booking_request',
                    'New Booking Request',
                    ($tenant['full_name'] ?? 'A tenant') . ' has paid to book "' . $house['title'] . '".',
                    BASE_URL . '/booking-requests'
                );

                $landlordRow = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
                $landlordRow->execute([':id' => $house['landlord_id']]);
                $landlord = $landlordRow->fetch(PDO::FETCH_ASSOC);

                if ($landlord) {
                    EmailJobPublisher::publish('email.new_booking_request', [
                        'email' => $landlord['email'],
                        'name' => $landlord['full_name'],
                        'tenant_name' => $tenant['full_name'] ?? 'A tenant',
                        'house_title' => $house['title'],
                    ]);
                }

                break;

            case 'driver_wallet_topup':
                $this->creditWallet(
                    (int) $payment['user_id'],
                    (float) $payment['amount'],
                    'topup',
                    (int) $payment['id']
                );
                break;
        }
    }

    /**
     * ============================================================
     * DRIVER WALLET LEDGER
     *
     * Row-locks the driver's latest balance before writing the next
     * entry, so two near-simultaneous writes (a top-up landing at
     * the same moment a trip completes) can't both read the same
     * stale balance and overwrite each other.
     * ============================================================
     */
    public function creditWallet(int $driverId, float $amount, string $type, ?int $referenceId): void
    {
        $stmt = $this->conn->prepare("
            SELECT balance_after FROM wallet_transactions
            WHERE driver_id = :driver_id
            ORDER BY id DESC LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([':driver_id' => $driverId]);
        $current = (float) ($stmt->fetchColumn() ?: 0);

        $newBalance = $current + $amount;

        $insert = $this->conn->prepare("
            INSERT INTO wallet_transactions (driver_id, type, amount, reference_id, balance_after)
            VALUES (:driver_id, :type, :amount, :reference_id, :balance_after)
        ");

        $insert->execute([
            ':driver_id' => $driverId,
            ':type' => $type,
            ':amount' => $amount,
            ':reference_id' => $referenceId,
            ':balance_after' => $newBalance,
        ]);
    }

    /**
     * Deduct commission at trip completion. $amount should be passed
     * in already negated by the caller (e.g. -300.00), so this
     * method stays a thin, symmetrical wrapper around creditWallet().
     */
    public function deductCommission(int $driverId, float $tripPrice, int $tripId): float
    {
        $commission = round($tripPrice * (TRUCK_COMMISSION_PERCENT / 100), 2);
        $this->creditWallet($driverId, -$commission, 'commission', $tripId);
        return $commission;
    }

    public function getWalletBalance(int $driverId): float
    {
        $stmt = $this->conn->prepare("
            SELECT balance_after FROM wallet_transactions
            WHERE driver_id = :driver_id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':driver_id' => $driverId]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * ============================================================
     * C2B RECEIPT MATCHING (self-service "I already paid" flow)
     * ============================================================
     */
    public function matchC2bReceipt(
        int $userId,
        string $purpose,
        string $mpesaReceipt,
        array $metadata = []
    ): array {

        $receipt = strtoupper(trim($mpesaReceipt));

        $stmt = $this->conn->prepare("
            SELECT * FROM mpesa_c2b_transactions
            WHERE mpesa_receipt = :receipt AND status = 'unmatched'
            LIMIT 1
        ");
        $stmt->execute([':receipt' => $receipt]);
        $c2b = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($c2b === null) {
            return [
                'success' => false,
                'message' => "We haven't received that transaction yet. It can take a minute — try again shortly, or contact support if it doesn't appear.",
            ];
        }

        try {
            $this->conn->beginTransaction();

            $paymentStmt = $this->conn->prepare("
                INSERT INTO payments
                    (user_id, purpose, amount, phone, status, checkout_request_id, mpesa_receipt, metadata)
                VALUES
                    (:user_id, :purpose, :amount, :phone, 'completed', :checkout_id, :receipt, :metadata)
            ");

            // C2B payments never had a Daraja CheckoutRequestID — use
            // the receipt itself so the UNIQUE constraint still guards
            // against this same receipt being claimed twice.
            $paymentStmt->execute([
                ':user_id' => $userId,
                ':purpose' => $purpose,
                ':amount' => $c2b['amount'],
                ':phone' => $c2b['phone'],
                ':checkout_id' => 'C2B-' . $receipt,
                ':receipt' => $receipt,
                ':metadata' => json_encode($metadata),
            ]);

            $paymentId = (int) $this->conn->lastInsertId();

            $this->conn->prepare("
                UPDATE mpesa_c2b_transactions
                SET status = 'matched', matched_payment_id = :payment_id
                WHERE id = :id
            ")->execute([':payment_id' => $paymentId, ':id' => $c2b['id']]);

            $payment = $this->getPaymentById($paymentId);
            if ($payment !== null) {
                $this->applyEntitlement($payment);
            }

            $this->conn->commit();

            return ['success' => true, 'message' => 'Payment verified — access granted.'];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('LUX EMPIRE Payment: C2B match failed — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong verifying that payment. Please try again.'];
        }
    }

    private function getPaymentById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPaymentStatus(int $paymentId, int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT status, purpose, amount FROM payments
            WHERE id = :id AND user_id = :user_id LIMIT 1
        ");
        $stmt->execute([':id' => $paymentId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
