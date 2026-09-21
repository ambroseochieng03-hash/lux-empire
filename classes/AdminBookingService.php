<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security/Audit.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/PaymentWaiver.php';
require_once __DIR__ . '/Notification.php';

/**
 * LUX EMPIRE
 * Admin oversight of bookings.
 *
 * A booking with money attached is NEVER hard-deleted:
 *   - pending + paid                 -> cancelAndRefund()   (approved bookings are never refunded)
 *   - finished (rejected/cancelled)  -> archiveBooking()  (hidden from this list, kept)
 *   - unpaid junk                    -> deleteUnpaidBooking()
 * Every action records a reason.
 */
final class AdminBookingService
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Returns ['bookings' => [...], 'total' => int]. $limit capped
     * at 100 server-side. Archived bookings are left out.
     */
    public function listBookings(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = " WHERE b.hidden_by_admin_at IS NULL";
        $params = [];

        if ($status !== null) {
            $where .= " AND b.status = :status";
            $params[':status'] = $status;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings b{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                b.id, b.status, b.payment_status, b.payment_id, b.waiver_id, b.booking_date,
                p.amount AS paid_amount,
                r.status AS refund_status,
                h.id AS house_id, COALESCE(h.title, b.house_title_snapshot) AS house_title,
                tenant.id AS tenant_id, tenant.full_name AS tenant_name, tenant.email AS tenant_email,
                landlord.id AS landlord_id, landlord.full_name AS landlord_name
            FROM bookings b
            LEFT JOIN houses h ON b.house_id = h.id
            LEFT JOIN payments p ON p.id = b.payment_id
            LEFT JOIN refunds r ON r.payment_id = b.payment_id
            JOIN users tenant ON b.tenant_id = tenant.id
            JOIN users landlord ON b.landlord_id = landlord.id
            {$where}
            ORDER BY b.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Admin resolves a PENDING booking (stuck request, unresponsive landlord,
     * suspicious tenant): cancels it, frees the house and refunds the fee in full.
     *
     * Only PENDING bookings qualify. Once the landlord has approved, both people
     * hold each other's contact details and have talked in chat, so the fee has
     * been earned and is never refunded from here.
     */
    public function cancelAndRefund(int $bookingId, int $adminId, string $reason): array
    {
        try {

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, house_id, tenant_id, landlord_id, status, payment_status, payment_id, house_title_snapshot
                FROM bookings
                WHERE id = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Booking not found.', 'code' => 404];
            }

            if ($booking['status'] !== 'pending') {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => $booking['status'] === 'approved'
                        ? 'This booking was approved, so the booking fee has been earned and cannot be refunded from here.'
                        : 'Only a pending booking can be cancelled and refunded.',
                    'code' => 409,
                ];
            }

            $update = $this->conn->prepare("
                UPDATE bookings SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
            $update->execute([':id' => $bookingId]);

            if ($update->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This booking was just handled by someone else.', 'code' => 409];
            }

            $voucherOutcome = PaymentWaiver::settleOnEnd($this->conn, $bookingId);

            if (!empty($booking['house_id'])) {
                $houseStatus = 'available';

                $this->conn->prepare("
                    UPDATE houses
                    SET status = :status, reserved_by_booking_id = NULL, booked_at = NULL
                    WHERE id = :house_id AND reserved_by_booking_id = :booking_id
                ")->execute([
                    ':status' => $houseStatus,
                    ':house_id' => $booking['house_id'],
                    ':booking_id' => $bookingId,
                ]);
            }

            $this->conn->commit();

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE admin cancelAndRefund failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not cancel this booking. Please try again.', 'code' => 500];
        }

        // ---- after the commit: refund, notifications, records ----

        $title = trim((string) ($booking['house_title_snapshot'] ?? ''));

        if ($title === '' && !empty($booking['house_id'])) {
            $titleStmt = $this->conn->prepare("SELECT title FROM houses WHERE id = :id");
            $titleStmt->execute([':id' => $booking['house_id']]);
            $title = (string) ($titleStmt->fetchColumn() ?: '');
        }

        if ($title === '') {
            $title = 'the property';
        }

        $refundQueued = false;
        $amount = (float) BOOKING_FEE_AMOUNT;

        if (($booking['payment_status'] ?? '') === 'paid' && !empty($booking['payment_id'])) {

            try {
                $payment = new Payment();

                $paidRow = $payment->getPaymentStatus((int) $booking['payment_id'], (int) $booking['tenant_id']);

                if ($paidRow) {
                    $amount = (float) $paidRow['amount'];
                }

                $refundRef = $payment->createAutoRefundForPayment(
                    (int) $booking['payment_id'],
                    'admin_manual',
                    ['booking_id' => $bookingId, 'house_id' => $booking['house_id'], 'admin_id' => $adminId]
                );

                $refundQueued = ($refundRef !== null);

            } catch (Throwable $e) {
                // The safety-net job (scripts/reconcile_pending_payments.php) creates a missing refund.
                error_log('LUX EMPIRE admin cancelAndRefund: refund creation failed for booking #' . $bookingId . ' — ' . $e->getMessage());
            }
        }

        try {
            $notification = new Notification();

            $notification->create(
                (int) $booking['tenant_id'],
                'booking_cancelled_by_admin',
                'Booking Cancelled by LUX EMPIRE',
                'Your booking for "' . $title . '" was cancelled by our team.'
                    . ($refundQueued
                        ? ' Your KES ' . number_format($amount) . ' booking fee is being refunded automatically to your M-Pesa — you\'ll get a confirmation once it completes.'
                        : ''),
                BASE_URL . '/tenant/my-bookings'
            );

            $notification->create(
                (int) $booking['landlord_id'],
                'booking_cancelled_by_admin',
                'Booking Cancelled by LUX EMPIRE',
                'The booking for "' . $title . '" was cancelled by our team.',
                BASE_URL . '/landlord'
            );
        } catch (Throwable $e) {
            error_log('LUX EMPIRE admin cancelAndRefund: notification failed for booking #' . $bookingId . ' — ' . $e->getMessage());
        }

        PaymentWaiver::notifyOutcome((int) $booking['tenant_id'], $voucherOutcome ?? null, $title);

        $this->recordReason($adminId, 'cancel_refund_booking', 'bookings', $bookingId, $reason);
        Audit::log("Admin #{$adminId} cancelled booking #{$bookingId}" . ($refundQueued ? ' and queued a refund' : '') . " ({$reason})", $adminId);

        return ['success' => true, 'refund_queued' => $refundQueued];
    }

    /**
     * Hide a FINISHED booking from the admin list. Nothing is deleted:
     * the row, its payment and its refund all stay for disputes and accounting.
     */
    public function archiveBooking(int $bookingId, int $adminId, string $reason): array
    {
        $stmt = $this->conn->prepare("
            UPDATE bookings SET hidden_by_admin_at = NOW()
            WHERE id = :id AND status IN ('rejected', 'cancelled') AND hidden_by_admin_at IS NULL
        ");
        $stmt->execute([':id' => $bookingId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Only a rejected or cancelled booking can be archived (and only once).', 'code' => 409];
        }

        $this->recordReason($adminId, 'archive_booking', 'bookings', $bookingId, $reason);
        Audit::log("Admin #{$adminId} archived booking #{$bookingId} ({$reason})", $adminId);

        return ['success' => true];
    }

    /**
     * The ONLY real delete: a booking with no payment attached (old test rows).
     */
    public function deleteUnpaidBooking(int $bookingId, int $adminId, string $reason): array
    {
        $stmt = $this->conn->prepare("
            DELETE FROM bookings
            WHERE id = :id AND payment_status = 'unpaid' AND payment_id IS NULL
        ");
        $stmt->execute([':id' => $bookingId]);

        if ($stmt->rowCount() === 0) {

            $exists = $this->conn->prepare("SELECT COUNT(*) FROM bookings WHERE id = :id");
            $exists->execute([':id' => $bookingId]);

            if ((int) $exists->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'message' => 'This booking has a payment attached, so it cannot be deleted. Cancel & refund it, or archive it once it is finished.',
                    'code' => 409,
                ];
            }

            return ['success' => false, 'message' => 'Booking not found.', 'code' => 404];
        }

        $this->recordReason($adminId, 'delete_booking', 'bookings', $bookingId, $reason);
        Audit::log("Admin #{$adminId} deleted unpaid booking #{$bookingId} ({$reason})", $adminId);

        return ['success' => true];
    }

    private function recordReason(int $adminId, string $actionType, string $targetTable, int $targetId, string $reason): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO admin_action_reasons (admin_id, action_type, target_table, target_id, reason)
            VALUES (:admin_id, :action_type, :target_table, :target_id, :reason)
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action_type' => $actionType,
            ':target_table' => $targetTable,
            ':target_id' => $targetId,
            ':reason' => $reason,
        ]);
    }
}