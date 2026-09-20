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
require_once __DIR__ . '/RefundJobPublisher.php';

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

        // A booking fee may only be charged while the house is still
        // 'available'. Reserved (someone already paid), booked and
        // unavailable houses are refused BEFORE any STK push is sent,
        // so nobody is charged just to be refunded a minute later.
        if ($purpose === 'booking_fee') {

            $houseId = (int) ($metadata['house_id'] ?? 0);

            $houseCheck = $this->conn->prepare("SELECT status FROM houses WHERE id = :id LIMIT 1");
            $houseCheck->execute([':id' => $houseId]);
            $houseStatus = $houseCheck->fetchColumn();

            if ($houseStatus !== 'available') {
                $message = $houseStatus === 'reserved'
                    ? 'Another tenant has just reserved this property. It will reopen if the landlord declines their request.'
                    : 'This property is no longer available.';

                return ['success' => false, 'message' => $message];
            }
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

                    $refund = $this->createRefundRecord(
                        (int) $payment['id'],
                        'house_unavailable',
                        ['house_id' => $houseId]
                    );

                    if ($refund !== null) {
                        $actions[] = [
                            'type' => 'refund_publish',
                            'refund_id' => $refund['refund_id'],
                            'refund_reference' => $refund['refund_reference'],
                        ];
                    }

                    $actions[] = [
                        'type' => 'notification',
                        'user_id' => (int) $payment['user_id'],
                        'notif_type' => 'payment_refund_pending',
                        'title' => 'This property was just taken',
                        'message' => 'Someone secured this property moments before your payment completed. Your KES ' . number_format((float) $payment['amount']) . ' booking fee is being refunded automatically to your M-Pesa — you\'ll get a confirmation once it completes.',
                        'link' => BASE_URL . '/tenant/my-bookings',
                    ];

                    error_log('LUX EMPIRE: booking_fee payment #' . $payment['id'] . ' auto-refund queued — house ' . $houseId . ' no longer available.');

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
                } elseif ($action['type'] === 'refund_publish') {
                    $this->publishRefundJob($action['refund_id'], $action['refund_reference']);
                }

            } catch (Throwable $e) {
                error_log('LUX EMPIRE Payment: post-commit action failed — ' . $e->getMessage());
            }
        }
    }

    /**
     * ============================================================
     * AUTO-REFUNDS
     *
     * Two-phase, mirroring the notification/email post-commit
     * pattern above: createRefundRecord() only ever writes the DB
     * row — safe to call from inside an already-open transaction
     * (e.g. from applyEntitlement() above) — and publishRefundJob()
     * talks to NATS, which must NEVER happen before the caller's
     * transaction has committed, or a rollback would leave a
     * phantom queued refund for a row that no longer exists.
     *
     * refunds.payment_id has a UNIQUE constraint — that's the real,
     * database-enforced guard against ever refunding the same
     * payment twice. A caught duplicate-key error here just means
     * "a refund already exists for this payment", not a failure.
     * ============================================================
     */
    public function createRefundRecord(int $paymentId, string $reason, array $metadataExtra = []): ?array
    {
        $payment = $this->getPaymentById($paymentId);

        if ($payment === null || $payment['status'] !== 'completed') {
            error_log('LUX EMPIRE Refund: refund requested for payment #' . $paymentId . ' but it is not in completed status.');
            return null;
        }

        // Waived / free payments (amount 0) never moved any money — there is
        // nothing to refund, and a KES 0 B2C request would only fail.
        if ((float) $payment['amount'] <= 0) {
            return null;
        }

        $refundReference = 'RFND-' . $paymentId . '-' . bin2hex(random_bytes(8));

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO refunds
                    (refund_reference, payment_id, user_id, amount, phone, reason, status, metadata)
                VALUES
                    (:ref, :payment_id, :user_id, :amount, :phone, :reason, 'pending', :metadata)
            ");

            $stmt->execute([
                ':ref' => $refundReference,
                ':payment_id' => $paymentId,
                ':user_id' => $payment['user_id'],
                ':amount' => $payment['amount'],
                ':phone' => $payment['phone'],
                ':reason' => $reason,
                ':metadata' => json_encode($metadataExtra),
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                error_log('LUX EMPIRE Refund: duplicate refund attempt blocked for payment #' . $paymentId);
                return null;
            }
            throw $e;
        }

        return [
            'refund_id' => (int) $this->conn->lastInsertId(),
            'refund_reference' => $refundReference,
        ];
    }

    /**
     * Call this ONLY after the caller's transaction has committed.
     */
    public function publishRefundJob(int $refundId, string $refundReference): void
    {
        try {
            RefundJobPublisher::publish([
                'refund_id' => $refundId,
                'refund_reference' => $refundReference,
            ]);

            $this->conn->prepare("UPDATE refunds SET nats_published_at = NOW() WHERE id = :id")
                ->execute([':id' => $refundId]);

        } catch (Throwable $e) {
            // Not fatal — nats_published_at stays NULL, and the
            // reconciliation sweep (part 2) republishes anything still
            // unpublished after a short delay. The refund itself is
            // never lost, only delayed.
            error_log('LUX EMPIRE Refund: NATS publish failed for refund #' . $refundId . ' — ' . $e->getMessage());
        }
    }

    /**
     * Convenience wrapper for call sites OUTSIDE any existing Payment
     * transaction (e.g. api/houses/update_booking_status.php, where
     * the booking rejection has already committed independently) —
     * does both steps back-to-back since there's no outer transaction
     * to wait on here.
     */
    public function createAutoRefundForPayment(int $paymentId, string $reason, array $metadataExtra = []): ?string
    {
        $refund = $this->createRefundRecord($paymentId, $reason, $metadataExtra);

        if ($refund === null) {
            return null;
        }

        $this->publishRefundJob($refund['refund_id'], $refund['refund_reference']);

        return $refund['refund_reference'];
    }

    /**
     * ============================================================
     * B2C — ACTUALLY SENDING THE MONEY
     *
     * The single UPDATE at the top ("claim") is the ONLY place a
     * refund is allowed to move pending -> processing. That's the
     * one choke point guaranteeing Safaricom is ever asked to send
     * money for a given refund row AT MOST ONCE — no matter how many
     * times this method gets called (worker retry, redelivered NATS
     * message, two worker processes running at once, a cron sweep
     * poking it). Everything below this point assumes that claim
     * already succeeded for THIS call.
     * ============================================================
     */
    public function sendB2cPayment(int $refundId): string
    {
        $claim = $this->conn->prepare("
            UPDATE refunds
            SET status = 'processing', attempts = attempts + 1
            WHERE id = :id AND status = 'pending'
        ");
        $claim->execute([':id' => $refundId]);

        if ($claim->rowCount() === 0) {
            return 'skipped-already-claimed-or-resolved';
        }

        $refund = $this->getRefundById($refundId);

        if ($refund === null) {
            error_log('LUX EMPIRE Refund: claimed refund #' . $refundId . ' vanished — should be impossible.');
            return 'error-vanished';
        }

        $normalizedPhone = self::normalizePhone($refund['phone']);

        if ($normalizedPhone === null) {
            // Not a Daraja failure — a bad stored phone. Never let
            // this become a silent, endlessly-retried no-op: escalate
            // to a human immediately instead.
            $this->failRefundPermanently($refundId, 'Stored phone number is not a valid Safaricom number: ' . $refund['phone']);
            return 'failed-invalid-phone';
        }

        try {
            $accessToken = $this->getAccessToken();
            $securityCredential = $this->getB2cSecurityCredential();
        } catch (Throwable $e) {
            // Nothing was sent to Safaricom at all yet — 100% safe to
            // treat as a definite, retryable failure.
            error_log('LUX EMPIRE Refund: credential/token setup failed for refund #' . $refundId . ' — ' . $e->getMessage());
            return $this->handleDefiniteSendFailure($refundId, 'Setup failed: ' . $e->getMessage());
        }

        $originatorConversationId = 'REFUND-' . $refundId . '-' . bin2hex(random_bytes(4));

        $this->conn->prepare("
            UPDATE refunds SET mpesa_originator_conversation_id = :ocid WHERE id = :id
        ")->execute([':ocid' => $originatorConversationId, ':id' => $refundId]);

        $payload = [
            'OriginatorConversationID' => $originatorConversationId,
            'InitiatorName' => DARAJA_INITIATOR_NAME,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'BusinessPayment',
            'Amount' => (int) round((float) $refund['amount']),
            'PartyA' => DARAJA_B2C_SHORTCODE,
            'PartyB' => $normalizedPhone,
            'Remarks' => 'LUX EMPIRE refund #' . $refundId,
            'QueueTimeOutURL' => DARAJA_B2C_TIMEOUT_URL,
            'ResultURL' => DARAJA_B2C_RESULT_URL,
            'Occasion' => 'Refund',
        ];

        $ch = curl_init(DARAJA_BASE_URL . '/mpesa/b2c/v3/paymentrequest');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /*
         * A curl-level failure (timeout, DNS blip, connection reset)
         * means we genuinely do NOT know whether Safaricom received
         * this request. Treating that as "safe to retry" is exactly
         * the bug that refunds a tenant twice. Leave the row at
         * 'processing' — untouched — and let the reconciliation
         * sweep, which actively asks Safaricom via
         * TransactionStatusQuery, be the only thing that ever moves
         * it out of 'processing' from here.
         */
        if ($curlErrno !== 0) {
            error_log('LUX EMPIRE Refund: network error sending refund #' . $refundId . ' (ocid ' . $originatorConversationId . ') — curl errno ' . $curlErrno . ': ' . $curlError . '. Left in processing for reconciliation.');
            return 'ambiguous-left-processing';
        }

        $data = json_decode((string) $response, true);

        /*
         * HTTP 200 + ResponseCode "0" means Safaricom ACCEPTED the
         * request into its queue — NOT that money has moved yet. The
         * row stays 'processing'; only the result callback (or the
         * reconciliation sweep) may ever mark it 'completed'.
         */
        if ($httpCode === 200 && isset($data['ResponseCode']) && (string) $data['ResponseCode'] === '0') {
            $this->conn->prepare("
                UPDATE refunds SET mpesa_conversation_id = :cid WHERE id = :id
            ")->execute([':cid' => $data['ConversationID'] ?? null, ':id' => $refundId]);

            error_log('LUX EMPIRE Refund: refund #' . $refundId . ' accepted by Safaricom, awaiting result callback. ConversationID=' . ($data['ConversationID'] ?? 'null'));
            return 'accepted-awaiting-callback';
        }

        /*
         * A clean response that REJECTS the request (bad initiator,
         * invalid phone, insufficient utility balance, etc.) means
         * Safaricom is telling us, synchronously, that nothing was
         * queued — no money moved, no ambiguity. The ONLY case safe
         * to treat as a definite failure eligible for retry.
         */
        $errorMessage = $data['errorMessage'] ?? $data['ResponseDescription'] ?? ('HTTP ' . $httpCode . ': ' . (string) $response);
        error_log('LUX EMPIRE Refund: refund #' . $refundId . ' rejected synchronously by Safaricom — ' . $errorMessage);

        return $this->handleDefiniteSendFailure($refundId, $errorMessage);
    }

    private function getB2cSecurityCredential(): string
    {
        if (DARAJA_SECURITY_CREDENTIAL_PRECOMPUTED !== '') {
            return DARAJA_SECURITY_CREDENTIAL_PRECOMPUTED;
        }

        if (!is_file(DARAJA_B2C_CERT_PATH)) {
            throw new RuntimeException('Daraja B2C certificate not found at ' . DARAJA_B2C_CERT_PATH);
        }

        $certificateContent = file_get_contents(DARAJA_B2C_CERT_PATH);

        $x509 = openssl_x509_read($certificateContent);

        if ($x509 === false) {
            throw new RuntimeException('Could not parse the file at DARAJA_B2C_CERT_PATH as a valid X.509 certificate (check it is actually the certificate and not an HTML error/login page). openssl error: ' . openssl_error_string());
        }

        $publicKey = openssl_pkey_get_public($x509);

        if ($publicKey === false) {
            throw new RuntimeException('Could not extract a public key from the Daraja certificate: ' . openssl_error_string());
        }

        $encrypted = '';

        if (!openssl_public_encrypt(DARAJA_INITIATOR_PASSWORD, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Failed to encrypt Daraja security credential: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    /**
     * Called ONLY when we KNOW, synchronously, that nothing was sent
     * (pre-send setup failure, or Safaricom's own clean rejection).
     * Safe to hand the row back to 'pending' for another attempt —
     * never call this for a network-level failure, where we don't
     * actually know what happened.
     */
    private function handleDefiniteSendFailure(int $refundId, string $errorMessage): string
    {
        $refund = $this->getRefundById($refundId);
        $attempts = (int) ($refund['attempts'] ?? REFUND_MAX_ATTEMPTS);

        if ($attempts >= REFUND_MAX_ATTEMPTS) {
            $this->failRefundPermanently($refundId, $errorMessage);
            return 'failed-max-attempts';
        }

        $reverted = $this->conn->prepare("
            UPDATE refunds SET status = 'pending', last_error = :err, needs_admin_review = 0
            WHERE id = :id AND status = 'processing'
        ");
        $reverted->execute([':err' => $errorMessage, ':id' => $refundId]);

        if ($reverted->rowCount() > 0 && $refund !== null) {
            // Goes back through NATS rather than retrying inline, so
            // attempts are naturally spaced by the worker's poll
            // cadence instead of hammering Daraja in a tight loop.
            RefundJobPublisher::publish([
                'refund_id' => $refundId,
                'refund_reference' => $refund['refund_reference'],
            ]);
        }

        return 'requeued-for-retry';
    }

    private function failRefundPermanently(int $refundId, string $reason): void
    {
        $this->conn->prepare("
            UPDATE refunds SET status = 'failed', last_error = :err, needs_admin_review = 1
            WHERE id = :id AND status IN ('processing', 'pending')
        ")->execute([':err' => $reason, ':id' => $refundId]);

        $refund = $this->getRefundById($refundId);

        if ($refund !== null) {
            try {
                (new Notification())->create(
                    (int) $refund['user_id'],
                    'refund_needs_review',
                    'Refund Delayed',
                    'We\'re processing your KES ' . number_format((float) $refund['amount']) . ' refund and it needs a quick manual check — our team has been notified and will complete it shortly.',
                    null
                );
            } catch (Throwable $e) {
                error_log('LUX EMPIRE Refund: notification failed for refund #' . $refundId . ' — ' . $e->getMessage());
            }
        }

        error_log('LUX EMPIRE Refund: refund #' . $refundId . ' permanently failed after max attempts — ' . $reason . ' — needs admin review.');
    }

    /**
     * ============================================================
     * B2C RESULT / TIMEOUT CALLBACKS
     * ============================================================
     */
    public function handleB2cResultCallback(array $callback): void
    {
        $result = $callback['Result'] ?? null;

        if ($result === null || (!isset($result['OriginatorConversationID']) && !isset($result['ConversationID']))) {
            error_log('LUX EMPIRE B2C result callback: malformed payload: ' . json_encode($callback));
            return;
        }

        $resultCode = (int) ($result['ResultCode'] ?? 1);

        $refund = null;

        if (!empty($result['OriginatorConversationID'])) {
            $refund = $this->getRefundByOriginatorConversationId((string) $result['OriginatorConversationID']);
        }

        if ($refund === null && !empty($result['ConversationID'])) {
            $refund = $this->getRefundByConversationId((string) $result['ConversationID']);
        }

        if ($refund === null) {
            error_log('LUX EMPIRE B2C result callback: no refund found for OriginatorConversationID '
                . ($result['OriginatorConversationID'] ?? 'null') . ' / ConversationID ' . ($result['ConversationID'] ?? 'null'));
            return;
        }

        if ($resultCode === 0) {
            $items = $result['ResultParameters']['ResultParameter'] ?? [];
            $values = [];
            foreach ($items as $item) {
                if (isset($item['Key'])) {
                    $values[$item['Key']] = $item['Value'] ?? null;
                }
            }

            $mpesaTransactionId = $values['TransactionReceipt'] ?? ($result['TransactionID'] ?? null);

            $stmt = $this->conn->prepare("
                UPDATE refunds
                SET status = 'completed', mpesa_transaction_id = :txn, completed_at = NOW(), needs_admin_review = 0
                WHERE id = :id AND status = 'processing'
            ");
            $stmt->execute([':txn' => $mpesaTransactionId, ':id' => $refund['id']]);

            if ($stmt->rowCount() === 0) {
                // Already resolved by an earlier callback delivery or
                // by the reconciliation sweep — nothing more to do.
                return;
            }

            $this->dispatchRefundCompletedActions($refund, $mpesaTransactionId);
            return;
        }

        // A genuine failure RESULT from Safaricom (e.g. recipient
        // account restricted) — this is the definitive async answer
        // to the exact request we sent, so it's safe to treat as
        // known-not-sent.
        $resultDesc = $result['ResultDesc'] ?? ('ResultCode ' . $resultCode);
        error_log('LUX EMPIRE B2C result callback: refund #' . $refund['id'] . ' failed — ResultCode ' . $resultCode . ': ' . $resultDesc);

        if ($refund['status'] !== 'processing') {
            // Already resolved elsewhere (admin, reconciliation) — ignore a late failure result.
            return;
        }

        // These fail identically on every retry, so retrying only burns
        // attempts. Send straight to admin review instead.
        $nonRetryableCodes = [2001, 2040];

        if (in_array($resultCode, $nonRetryableCodes, true)) {
            $this->failRefundPermanently((int) $refund['id'], 'ResultCode ' . $resultCode . ': ' . $resultDesc);
            return;
        }

        $this->handleDefiniteSendFailure((int) $refund['id'], $resultDesc);
    }

    public function handleB2cTimeoutCallback(array $callback): void
    {
        $result = $callback['Result'] ?? $callback;
        $originatorConversationId = $result['OriginatorConversationID'] ?? null;

        if ($originatorConversationId === null) {
            error_log('LUX EMPIRE B2C timeout callback: malformed payload: ' . json_encode($callback));
            return;
        }

        $refund = $this->getRefundByOriginatorConversationId($originatorConversationId);

        if ($refund === null) {
            error_log('LUX EMPIRE B2C timeout callback: no refund found for OriginatorConversationID ' . $originatorConversationId);
            return;
        }

        error_log('LUX EMPIRE B2C timeout callback: refund #' . $refund['id'] . ' timed out on Safaricom\'s side — querying transaction status for a definitive answer before touching it.');

        $this->reconcileStuckRefund((int) $refund['id']);
    }

    /**
     * ============================================================
     * RECONCILIATION — asks Safaricom directly rather than guessing.
     *
     * TransactionStatusQuery is ITSELF asynchronous (its own
     * ResultURL/QueueTimeOutURL) — this call only confirms Safaricom
     * accepted the QUESTION. The actual answer arrives later at
     * handleStatusQueryResultCallback(). This method always flags
     * the row for admin visibility too, so a human can see it
     * regardless of whether the automatic parsing below resolves it.
     * ============================================================
     */
    public function reconcileStuckRefund(int $refundId): void
    {
        $refund = $this->getRefundById($refundId);

        if ($refund === null || $refund['status'] !== 'processing') {
            return;
        }

        $this->conn->prepare("UPDATE refunds SET needs_admin_review = 1 WHERE id = :id AND status = 'processing'")
            ->execute([':id' => $refundId]);

        if (empty($refund['mpesa_originator_conversation_id'])) {
            $this->failRefundPermanently($refundId, 'Stuck in processing with no OriginatorConversationID — cannot query status.');
            return;
        }

        try {
            $accessToken = $this->getAccessToken();
            $securityCredential = $this->getB2cSecurityCredential();
        } catch (Throwable $e) {
            error_log('LUX EMPIRE Refund: reconcile credential setup failed for refund #' . $refundId . ' — ' . $e->getMessage());
            return;
        }

        $payload = [
            'Initiator' => DARAJA_INITIATOR_NAME,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'TransactionStatusQuery',
            'OriginatorConversationID' => $refund['mpesa_originator_conversation_id'],
            'PartyA' => DARAJA_B2C_SHORTCODE,
            'IdentifierType' => '4',
            'ResultURL' => DARAJA_STATUS_QUERY_RESULT_URL,
            'QueueTimeOutURL' => DARAJA_STATUS_QUERY_TIMEOUT_URL,
            'Remarks' => 'Reconcile refund #' . $refundId,
            'Occasion' => 'Refund reconciliation',
        ];

        $ch = curl_init(DARAJA_BASE_URL . '/mpesa/transactionstatus/v1/query');

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

        $this->conn->prepare("UPDATE refunds SET last_reconcile_attempt_at = NOW() WHERE id = :id")
            ->execute([':id' => $refundId]);

        error_log('LUX EMPIRE Refund: reconciliation query sent for refund #' . $refundId . ' — ' . (string) $response);
    }

    /**
     * NOTE: Safaricom's exact ResultParameter key names for
     * TransactionStatusQuery are not fully standardized across
     * accounts/versions in public docs. Log the raw payload the
     * first several times you trigger this in sandbox and adjust
     * the keys checked below ('TransactionStatus', 'ReceiptNo',
     * 'OriginatorConversationID') if your actual response differs.
     * Until verified, needs_admin_review already gives you a manual
     * backstop regardless of whether this parses correctly.
     */
    public function handleStatusQueryResultCallback(array $callback): void
    {
        $result = $callback['Result'] ?? null;

        if ($result === null) {
            error_log('LUX EMPIRE status query callback: malformed payload: ' . json_encode($callback));
            return;
        }

        $resultCode = (int) ($result['ResultCode'] ?? 1);

        $items = $result['ResultParameters']['ResultParameter'] ?? [];
        $values = [];
        foreach ($items as $item) {
            if (isset($item['Key'])) {
                $values[$item['Key']] = $item['Value'] ?? null;
            }
        }

        $queriedOcid = $values['OriginatorConversationID'] ?? ($result['OriginatorConversationID'] ?? null);

        if ($queriedOcid === null) {
            error_log('LUX EMPIRE status query callback: no OriginatorConversationID found: ' . json_encode($callback));
            return;
        }

        $refund = $this->getRefundByOriginatorConversationId($queriedOcid);

        if ($refund === null || $refund['status'] !== 'processing') {
            return;
        }

        $transactionStatus = strtolower((string) ($values['TransactionStatus'] ?? ''));

        if ($resultCode === 0 && str_contains($transactionStatus, 'complet')) {
            $mpesaTransactionId = $values['ReceiptNo'] ?? $values['TransactionID'] ?? null;

            $stmt = $this->conn->prepare("
                UPDATE refunds SET status = 'completed', mpesa_transaction_id = :txn, completed_at = NOW(), needs_admin_review = 0
                WHERE id = :id AND status = 'processing'
            ");
            $stmt->execute([':txn' => $mpesaTransactionId, ':id' => $refund['id']]);

            if ($stmt->rowCount() > 0) {
                $this->dispatchRefundCompletedActions($refund, $mpesaTransactionId);
            }
            return;
        }

        if ($resultCode === 0 && (str_contains($transactionStatus, 'fail') || str_contains($transactionStatus, 'not found') || str_contains($transactionStatus, 'reject'))) {
            error_log('LUX EMPIRE status query callback: refund #' . $refund['id'] . ' confirmed NOT completed by Safaricom — ' . $transactionStatus);
            $this->handleDefiniteSendFailure((int) $refund['id'], 'Confirmed not completed: ' . $transactionStatus);
            return;
        }

        // Still inconclusive — leave it exactly as is; needs_admin_review
        // is already set, and the next sweep pass will ask again.
        error_log('LUX EMPIRE status query callback: refund #' . $refund['id'] . ' status still inconclusive — ' . json_encode($values));
    }

    private function dispatchRefundCompletedActions(array $refund, ?string $mpesaTransactionId): void
    {
        try {
            (new Notification())->create(
                (int) $refund['user_id'],
                'refund_completed',
                'Refund Completed',
                'Your KES ' . number_format((float) $refund['amount']) . ' refund has been sent to your M-Pesa' . ($mpesaTransactionId ? ' (ref: ' . $mpesaTransactionId . ')' : '') . '.',
                BASE_URL . '/tenant/my-bookings'
            );
        } catch (Throwable $e) {
            error_log('LUX EMPIRE Refund: notification failed for refund #' . $refund['id'] . ' — ' . $e->getMessage());
        }

        try {
            $userStmt = $this->conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
            $userStmt->execute([':id' => $refund['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                EmailJobPublisher::publish('email.refund_completed', [
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'amount' => number_format((float) $refund['amount']),
                    'mpesa_transaction_id' => $mpesaTransactionId ?? '',
                ]);
            }
        } catch (Throwable $e) {
            error_log('LUX EMPIRE Refund: email publish failed for refund #' . $refund['id'] . ' — ' . $e->getMessage());
        }

        error_log('LUX EMPIRE Refund: refund #' . $refund['id'] . ' completed. Receipt=' . ($mpesaTransactionId ?? 'unknown'));
    }

    private function getRefundById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM refunds WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getRefundByOriginatorConversationId(string $ocid): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM refunds WHERE mpesa_originator_conversation_id = :ocid LIMIT 1");
        $stmt->execute([':ocid' => $ocid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getRefundByConversationId(string $cid): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM refunds WHERE mpesa_conversation_id = :cid LIMIT 1");
        $stmt->execute([':cid' => $cid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listRefundsNeedingReview(): array
    {
        return $this->conn->query("
            SELECT r.*, u.full_name, u.email, u.phone AS user_phone
            FROM refunds r JOIN users u ON r.user_id = u.id
            WHERE r.needs_admin_review = 1
            ORDER BY r.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
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

        // 4999 = Safaricom is still processing the request — not a failure. Leave it pending.
        if ($resultCode === 4999) {
            return;
        }

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

        if (!$c2b) {
            return [
                'success' => false,
                'message' => "We haven't received that transaction yet. It can take a minute — try again shortly, or contact support if it doesn't appear.",
            ];
        }

        // The receipt must have paid AT LEAST the fixed price of what is being
        // claimed. Without this, a KES 1 paybill payment could be claimed as a
        // booking fee or a Pro plan.
        $requiredAmount = match ($purpose) {
            'booking_fee' => (float) BOOKING_FEE_AMOUNT,
            'landlord_pro' => (float) PRICE_LANDLORD_PRO_MONTHLY,
            default => 0.0,
        };

        if ((float) $c2b['amount'] < $requiredAmount) {
            error_log('LUX EMPIRE Payment: C2B receipt ' . $receipt . ' paid KES ' . $c2b['amount'] . ' but ' . $purpose . ' costs KES ' . $requiredAmount . ' — refused.');

            return [
                'success' => false,
                'message' => 'That payment was less than the required KES ' . number_format($requiredAmount) . ', so it cannot be used here. Please contact support.',
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

    public function listRefundsForAdmin(): array
    {
        return $this->conn->query("
            SELECT r.*, u.full_name, u.email, u.phone AS user_phone
            FROM refunds r JOIN users u ON r.user_id = u.id
            WHERE r.status <> 'completed'
            ORDER BY r.needs_admin_review DESC, r.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Admin confirms the money reached the tenant (sent manually, or
     * verified in the M-Pesa portal). Safe against double-sending: the
     * worker only claims 'pending' rows and Safaricom's callback only
     * updates 'processing' rows, so a 'completed' row is never touched again.
     */
    public function adminMarkRefundCompleted(int $refundId, int $adminId, string $mpesaRef, string $notes): array
    {
        if ($mpesaRef === '') {
            return ['success' => false, 'message' => 'M-Pesa reference is required.'];
        }

        $dup = $this->conn->prepare("SELECT id FROM refunds WHERE mpesa_transaction_id = :txn AND id <> :id LIMIT 1");
        $dup->execute([':txn' => $mpesaRef, ':id' => $refundId]);

        if ($dup->fetchColumn()) {
            return ['success' => false, 'message' => 'That M-Pesa reference is already recorded on another refund.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE refunds
            SET status = 'completed', mpesa_transaction_id = :txn, completed_at = NOW(),
                needs_admin_review = 0, admin_notes = :notes, resolved_by_admin_id = :admin
            WHERE id = :id AND status IN ('pending','processing','failed')
        ");
        $stmt->execute([':txn' => $mpesaRef, ':notes' => $notes, ':admin' => $adminId, ':id' => $refundId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Refund not found or already completed.'];
        }

        $refund = $this->getRefundById($refundId);

        if ($refund !== null) {
            $this->dispatchRefundCompletedActions($refund, $mpesaRef);
        }

        return ['success' => true, 'message' => 'Refund marked completed and the tenant was notified.'];
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
