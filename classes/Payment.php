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
require_once __DIR__ . '/ReceiptExtractor.php';

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
            $postCommitActions = [];

            if ($payment !== null) {
                $postCommitActions = $this->applyEntitlement($payment);
            }

            $this->conn->commit();

            $this->dispatchPostCommitActions($postCommitActions);

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
     * the transaction in handleStkCallback() / reconcilePendingPayment()
     * / matchC2bReceipt() — once, guaranteed by the conditional
     * UPDATE those callers each do first.
     *
     * IMPORTANT: never sends notifications/emails directly here.
     * Notification::create() opens its OWN separate PDO connection —
     * if this method is still inside an open transaction that just
     * locked a row in `users` (the landlord_pro case does exactly
     * this), a notification INSERT on a different connection needing
     * a foreign-key lock on that same locked row deadlocks against
     * this transaction's own uncommitted state. Instead, this
     * collects what needs to happen as plain data and returns it —
     * the caller commits first, THEN dispatches these.
     *
     * @return array<int, array<string, mixed>> post-commit actions
     */
    private function applyEntitlement(array $payment): array
    {
        $metadata = json_decode((string) ($payment['metadata'] ?? '{}'), true) ?: [];
        $actions = [];

        switch ($payment['purpose']) {

            case 'landlord_pro':
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

                $actions[] = [
                    'type' => 'notification',
                    'user_id' => (int) $payment['user_id'],
                    'notif_type' => 'payment',
                    'title' => 'Pro plan activated',
                    'message' => 'Your LUX EMPIRE Pro plan is now active for 30 days.',
                    'link' => BASE_URL . '/manage-houses',
                ];

                $landlordRow = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
                $landlordRow->execute([':id' => $payment['user_id']]);
                $landlord = $landlordRow->fetch(PDO::FETCH_ASSOC);

                if ($landlord) {
                    $actions[] = [
                        'type' => 'email',
                        'subject_key' => 'email.landlord_pro_activated',
                        'payload' => [
                            'email' => $landlord['email'],
                            'name' => $landlord['full_name'],
                            'amount' => number_format((float) $payment['amount']),
                        ],
                    ];
                }

                break;

            case 'booking_fee':

                $houseId = (int) ($metadata['house_id'] ?? 0);

                if ($houseId <= 0) {
                    break;
                }

                $houseLock = $this->conn->prepare("
                    SELECT status, landlord_id, title
                    FROM houses
                    WHERE id = :id
                    FOR UPDATE
                ");
                $houseLock->execute([':id' => $houseId]);
                $house = $houseLock->fetch(PDO::FETCH_ASSOC);

                if (!$house || $house['status'] !== 'available') {

                    $this->conn->prepare("
                        UPDATE payments
                        SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.refund_required', true)
                        WHERE id = :id
                    ")->execute([':id' => $payment['id']]);

                    $actions[] = [
                        'type' => 'notification',
                        'user_id' => (int) $payment['user_id'],
                        'notif_type' => 'payment_refund_pending',
                        'title' => 'This property was just taken',
                        'message' => 'Someone secured this property moments before your payment completed. Your KES ' . number_format((float) $payment['amount']) . ' booking fee will be refunded — our team has been notified and will process it shortly.',
                        'link' => BASE_URL . '/tenant/my-bookings',
                    ];

                    error_log('LUX EMPIRE: booking_fee payment #' . $payment['id'] . ' needs manual refund — house ' . $houseId . ' no longer available.');

                    break;
                }

                $bookingInsert = $this->conn->prepare("
                    INSERT INTO bookings (tenant_id, house_id, house_title_snapshot, landlord_id, status, payment_status, payment_id)
                    VALUES (:tenant_id, :house_id, :house_title_snapshot, :landlord_id, 'pending', 'paid', :payment_id)
                ");
                $bookingInsert->execute([
                    ':tenant_id' => $payment['user_id'],
                    ':house_id' => $houseId,
                    ':house_title_snapshot' => $house['title'],
                    ':landlord_id' => $house['landlord_id'],
                    ':payment_id' => $payment['id'],
                ]);
                $bookingId = (int) $this->conn->lastInsertId();

                $this->conn->prepare("
                    UPDATE houses
                    SET status = 'reserved', reserved_by_booking_id = :booking_id
                    WHERE id = :house_id
                ")->execute([':booking_id' => $bookingId, ':house_id' => $houseId]);

                $tenantRow = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
                $tenantRow->execute([':id' => $payment['user_id']]);
                $tenant = $tenantRow->fetch(PDO::FETCH_ASSOC);

                $actions[] = [
                    'type' => 'notification',
                    'user_id' => (int) $payment['user_id'],
                    'notif_type' => 'payment_confirmed',
                    'title' => 'Booking fee paid',
                    'message' => 'Your KES ' . number_format((float) $payment['amount']) . ' booking fee for "' . $house['title'] . '" was received. The landlord has been notified.',
                    'link' => BASE_URL . '/tenant/my-bookings',
                ];

                if ($tenant) {
                    $actions[] = [
                        'type' => 'email',
                        'subject_key' => 'email.payment_confirmed',
                        'payload' => [
                            'email' => $tenant['email'],
                            'name' => $tenant['full_name'],
                            'house_title' => $house['title'],
                            'amount' => number_format((float) $payment['amount']),
                        ],
                    ];
                }

                $actions[] = [
                    'type' => 'notification',
                    'user_id' => (int) $house['landlord_id'],
                    'notif_type' => 'new_booking_request',
                    'title' => 'New Booking Request',
                    'message' => ($tenant['full_name'] ?? 'A tenant') . ' has paid to book "' . $house['title'] . '".',
                    'link' => BASE_URL . '/booking-requests',
                ];

                $landlordRow = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
                $landlordRow->execute([':id' => $house['landlord_id']]);
                $landlord = $landlordRow->fetch(PDO::FETCH_ASSOC);

                if ($landlord) {
                    $actions[] = [
                        'type' => 'email',
                        'subject_key' => 'email.new_booking_request',
                        'payload' => [
                            'email' => $landlord['email'],
                            'name' => $landlord['full_name'],
                            'tenant_name' => $tenant['full_name'] ?? 'A tenant',
                            'house_title' => $house['title'],
                        ],
                    ];
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

        return $actions;
    }

    /**
     * Runs the post-commit actions collected by applyEntitlement().
     * ALWAYS call this AFTER $this->conn->commit() has already
     * returned — never before, never inside the transaction.
     */
    private function dispatchPostCommitActions(array $actions): void
    {
        foreach ($actions as $action) {

            try {

                if ($action['type'] === 'notification') {
                    (new Notification())->create(
                        $action['user_id'],
                        $action['notif_type'],
                        $action['title'],
                        $action['message'],
                        $action['link'] ?? null
                    );
                } elseif ($action['type'] === 'email') {
                    EmailJobPublisher::publish($action['subject_key'], $action['payload']);
                }

            } catch (Throwable $e) {
                error_log('LUX EMPIRE Payment: post-commit action failed — ' . $e->getMessage());
            }
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
     * QUERY STK STATUS DIRECTLY (fallback when the callback is
     * late or never arrives — Daraja sandbox in particular is known
     * to sometimes skip callback delivery even for a genuinely
     * completed/reversed transaction).
     * ============================================================
     */
    public function queryStkStatus(string $checkoutRequestId): ?array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Throwable $e) {
            error_log('LUX EMPIRE Payment: token fetch failed during query — ' . $e->getMessage());
            return null;
        }

        $timestamp = date('YmdHis');
        $password = base64_encode(DARAJA_SHORTCODE . DARAJA_PASSKEY . $timestamp);

        $payload = [
            'BusinessShortCode' => DARAJA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $ch = curl_init(DARAJA_BASE_URL . '/mpesa/stkpushquery/v1/query');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Actively resolve a payment that's still 'pending' by asking
     * Safaricom directly, instead of waiting indefinitely on a
     * callback that may never arrive. Idempotent and safe to call
     * even if a real callback lands at almost the same moment — the
     * conditional UPDATE (status='pending' in the WHERE) means
     * whichever one gets there first wins; the second finds 0 rows
     * and does nothing.
     *
     * Note: unlike a real callback, the Query API response does not
     * include the M-Pesa receipt number, so a payment resolved this
     * way has mpesa_receipt left NULL. The entitlement is still
     * granted correctly — only the receipt display is missing,
     * recoverable later via admin lookup if ever needed.
     */
    public function reconcilePendingPayment(int $paymentId): void
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === null || $payment['status'] !== 'pending') {
            return;
        }

        $queryResult = $this->queryStkStatus($payment['checkout_request_id']);

        if ($queryResult === null || !isset($queryResult['ResultCode'])) {
            // Safaricom itself doesn't have a final answer yet
            // (still processing) or the query call failed — leave it
            // pending, the next poll will try again.
            error_log('LUX EMPIRE Payment: query inconclusive for payment #' . $paymentId . ' — ' . json_encode($queryResult));
            return;
        }

        $resultCode = (int) $queryResult['ResultCode'];

        try {
            $this->conn->beginTransaction();

            if ($resultCode !== 0) {

                $this->conn->prepare("
                    UPDATE payments SET status = 'failed'
                    WHERE id = :id AND status = 'pending'
                ")->execute([':id' => $paymentId]);

                $this->conn->commit();
                return;
            }

            $stmt = $this->conn->prepare("
                UPDATE payments SET status = 'completed'
                WHERE id = :id AND status = 'pending'
            ");
            $stmt->execute([':id' => $paymentId]);

            if ($stmt->rowCount() === 0) {
                // A real callback landed first — nothing more to do.
                $this->conn->rollBack();
                return;
            }

            $postCommitActions = $this->applyEntitlement($payment);
            $this->conn->commit();

            $this->dispatchPostCommitActions($postCommitActions);

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('LUX EMPIRE Payment: reconciliation failed for payment #' . $paymentId . ' — ' . $e->getMessage());
        }
    }

    /**
     * Self-service "I already paid" path — the person pastes the
     * SMS or bare code for a SPECIFIC payment_id they already know
     * about (the modal only offers this after its own polling gave
     * up, so it already has the id). Not proof on its own — it
     * re-asks Safaricom via the trusted checkout_request_id, and
     * only escalates to admin review if that's still inconclusive.
     */
    public function submitUserReceipt(int $userId, int $paymentId, string $rawInput): array
    {
        $code = ReceiptExtractor::extract($rawInput);

        if ($code === null) {
            return [
                'success' => false,
                'message' => "We couldn't find an M-Pesa code in that. Paste the full confirmation message, or just the code itself.",
            ];
        }

        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute([':id' => $paymentId, ':user_id' => $userId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === null) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }

        if ($payment['status'] === 'completed') {
            return ['success' => true, 'message' => 'This payment is already confirmed.'];
        }

        if ($payment['status'] !== 'pending') {
            return ['success' => false, 'message' => 'This payment can no longer be verified. Please start a new payment.'];
        }

        $this->conn->prepare("UPDATE payments SET user_submitted_receipt = :code WHERE id = :id")
            ->execute([':code' => $code, ':id' => $paymentId]);

        $this->reconcilePendingPayment($paymentId);

        $recheck = $this->conn->prepare("SELECT status FROM payments WHERE id = :id");
        $recheck->execute([':id' => $paymentId]);

        if ($recheck->fetchColumn() === 'completed') {
            return ['success' => true, 'message' => 'Payment verified — access granted.'];
        }

        $this->conn->prepare("UPDATE payments SET needs_admin_review = 1 WHERE id = :id")
            ->execute([':id' => $paymentId]);

        return [
            'success' => true,
            'pending_review' => true,
            'message' => "We've recorded your code and flagged this for our team to confirm — you'll be notified once it's verified, usually within a few hours.",
        ];
    }

    /**
     * Standalone "I paid via Paybill" flow — no STK push was ever
     * initiated. Tries an automatic C2B match first (only works once
     * Go-Live C2B webhook registration is done with a real Paybill);
     * otherwise queues a pending payment for admin review.
     */
    public function submitPaybillPayment(int $userId, string $purpose, ?float $amount, string $rawInput, array $metadata = []): array
    {
        $code = ReceiptExtractor::extract($rawInput);

        if ($code === null) {
            return [
                'success' => false,
                'message' => "We couldn't find an M-Pesa code in that. Paste the full confirmation message, or just the code itself.",
            ];
        }

        // driver_wallet_topup has no fixed price — if the person
        // didn't type an amount, read it straight out of the
        // message they pasted instead of forcing a second step.
        // landlord_pro/booking_fee always use the server's own
        // fixed price regardless of what's passed in — never trust
        // an amount for those two, whether typed or extracted.
        if ($purpose === 'driver_wallet_topup' && $amount === null) {
            $amount = ReceiptExtractor::extractAmount($rawInput);
        }

        if ($amount === null || $amount <= 0) {
            return [
                'success' => false,
                'message' => "We couldn't read an amount from that. Please paste the FULL M-Pesa confirmation message (it should contain \"Ksh...\").",
            ];
        }

        $autoResult = $this->matchC2bReceipt($userId, $purpose, $code, $metadata);

        if ($autoResult['success']) {
            return $autoResult;
        }

        $checkoutId = 'PAYBILL-' . $userId . '-' . time() . '-' . bin2hex(random_bytes(3));

        $this->conn->prepare("
            INSERT INTO payments
                (user_id, purpose, amount, phone, status, checkout_request_id, user_submitted_receipt, needs_admin_review, metadata)
            VALUES
                (:user_id, :purpose, :amount, 'PAYBILL', 'pending', :checkout_id, :code, 1, :metadata)
        ")->execute([
            ':user_id' => $userId, ':purpose' => $purpose, ':amount' => $amount,
            ':checkout_id' => $checkoutId, ':code' => $code, ':metadata' => json_encode($metadata),
        ]);

        return [
            'success' => true,
            'pending_review' => true,
            'message' => "We've recorded your code and flagged this for our team to confirm — you'll be notified once it's verified, usually within a few hours.",
        ];
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
            $postCommitActions = [];

            if ($payment !== null) {
                $postCommitActions = $this->applyEntitlement($payment);
            }

            $this->conn->commit();

            $this->dispatchPostCommitActions($postCommitActions);

            return ['success' => true, 'message' => 'Payment verified — access granted.'];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('LUX EMPIRE Payment: C2B match failed — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong verifying that payment. Please try again.'];
        }
    }

    public function manuallyApprove(int $paymentId, int $adminId, string $notes = '', ?float $overrideAmount = null): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === null) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }

        // Admin can correct the amount before granting — matters
        // for driver_wallet_topup, where the figure came from
        // regex-reading a pasted message and could be off.
        if ($overrideAmount !== null && $overrideAmount > 0 && $overrideAmount != $payment['amount']) {
            $this->conn->prepare("UPDATE payments SET amount = :amount WHERE id = :id")
                ->execute([':amount' => $overrideAmount, ':id' => $paymentId]);
            $payment['amount'] = $overrideAmount;
        }

        if ($payment['status'] === 'completed') {
            $this->conn->prepare("
                UPDATE payments SET needs_admin_review = 0, admin_notes = :notes, resolved_by_admin_id = :admin_id
                WHERE id = :id
            ")->execute([':notes' => $notes, ':admin_id' => $adminId, ':id' => $paymentId]);
            return ['success' => true, 'message' => 'This payment was already completed — review cleared.'];
        }

        if ($payment['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only a pending payment can be manually approved.'];
        }

        try {
            $this->conn->beginTransaction();

            $update = $this->conn->prepare("
                UPDATE payments
                SET status = 'completed', needs_admin_review = 0, admin_notes = :notes, resolved_by_admin_id = :admin_id
                WHERE id = :id AND status = 'pending'
            ");
            $update->execute([':notes' => $notes, ':admin_id' => $adminId, ':id' => $paymentId]);

            if ($update->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment was already resolved.'];
            }

            $postCommitActions = $this->applyEntitlement($payment);
            $this->conn->commit();

            $this->dispatchPostCommitActions($postCommitActions);

            (new Notification())->create(
                (int) $payment['user_id'],
                'payment_manually_verified',
                'Payment Verified',
                'Our team has manually verified your payment — access has been granted.',
                null
            );

            return ['success' => true, 'message' => 'Payment approved and access granted.'];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('LUX EMPIRE Payment: manual approval failed — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong approving this payment.'];
        }
    }

    public function manuallyReject(int $paymentId, int $adminId, string $notes = ''): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === null) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }

        $this->conn->prepare("
            UPDATE payments
            SET status = 'failed', needs_admin_review = 0, admin_notes = :notes, resolved_by_admin_id = :admin_id
            WHERE id = :id AND status = 'pending'
        ")->execute([':notes' => $notes, ':admin_id' => $adminId, ':id' => $paymentId]);

        (new Notification())->create(
            (int) $payment['user_id'],
            'payment_rejected',
            'Payment Could Not Be Verified',
            'We were unable to verify the payment code you submitted.' . ($notes ? ' Note: ' . $notes : '') . ' Please try again or contact support.',
            null
        );

        return ['success' => true, 'message' => 'Payment marked as failed and the user was notified.'];
    }

    public function markRefundResolved(int $paymentId, int $adminId, string $notes = ''): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === null) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }

        $this->conn->prepare("
            UPDATE payments SET refund_resolved_at = NOW(), admin_notes = :notes, resolved_by_admin_id = :admin_id
            WHERE id = :id
        ")->execute([':notes' => $notes, ':admin_id' => $adminId, ':id' => $paymentId]);

        (new Notification())->create(
            (int) $payment['user_id'],
            'refund_processed',
            'Refund Processed',
            'Your KES ' . number_format((float) $payment['amount']) . ' refund has been processed.',
            null
        );

        return ['success' => true, 'message' => 'Refund marked resolved and the user was notified.'];
    }

    public function grantWaivedPayment(int $userId, string $purpose, float $amount, array $metadata = []): array
    {
        try {
            $this->conn->beginTransaction();

            $checkoutId = 'WAIVER-' . $userId . '-' . time() . '-' . bin2hex(random_bytes(3));

            $stmt = $this->conn->prepare("
                INSERT INTO payments (user_id, purpose, amount, phone, status, checkout_request_id, metadata)
                VALUES (:user_id, :purpose, :amount, 'WAIVED', 'completed', :checkout_id, :metadata)
            ");
            $stmt->execute([
                ':user_id' => $userId, ':purpose' => $purpose, ':amount' => $amount,
                ':checkout_id' => $checkoutId, ':metadata' => json_encode($metadata),
            ]);
            $paymentId = (int) $this->conn->lastInsertId();

            $payment = ['id' => $paymentId, 'user_id' => $userId, 'purpose' => $purpose, 'amount' => $amount, 'metadata' => json_encode($metadata)];
            $postCommitActions = $this->applyEntitlement($payment);

            $this->conn->commit();
            $this->dispatchPostCommitActions($postCommitActions);

            return ['success' => true, 'payment_id' => $paymentId, 'waived' => true, 'message' => 'Granted at no charge.'];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('LUX EMPIRE Payment: waiver grant failed — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong granting this.'];
        }
    }

    public function listNeedingReview(): array
    {
        return $this->conn->query("
            SELECT p.*, u.full_name, u.email, u.phone
            FROM payments p JOIN users u ON p.user_id = u.id
            WHERE p.needs_admin_review = 1 AND p.status = 'pending'
            ORDER BY p.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listRefundsPending(): array
    {
        return $this->conn->query("
            SELECT p.*, u.full_name, u.email, u.phone
            FROM payments p JOIN users u ON p.user_id = u.id
            WHERE p.refund_required = 1 AND p.refund_resolved_at IS NULL
            ORDER BY p.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, u.full_name, u.email
            FROM payments p JOIN users u ON p.user_id = u.id
            ORDER BY p.id DESC LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                SELECT status, purpose, amount, created_at FROM payments
                WHERE id = :id AND user_id = :user_id LIMIT 1
            ");
        $stmt->execute([':id' => $paymentId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
