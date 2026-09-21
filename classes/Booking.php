<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ListingState.php';
require_once __DIR__ . '/PaymentWaiver.php';

class Booking {

    private $conn;
    private $table = "bookings";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * CREATE BOOKING
     *
     * Transactional and concurrency-safe:
     *
     * - Locks the house row (SELECT ... FOR UPDATE) so a concurrent
     *   Booking::acceptBooking() cannot flip the house's status
     *   underneath this check.
     * - Performs a friendly pre-check for an existing pending
     *   booking (nicer error message).
     * - The actual duplicate-prevention guarantee is the
     *   uniq_tenant_house_pending unique index added in
     *   database/migrations/001_booking_safety.sql — if two
     *   requests race past the pre-check, the losing INSERT fails
     *   with a 23000 integrity-constraint error, which is caught
     *   and turned into the same friendly message.
     */
    public function createBooking($tenant_id, $house_id, $landlord_id) {

        // Bookings are only ever created by Payment::applyEntitlement() AFTER the
        // booking fee has been paid. This legacy path created UNPAID requests, so
        // it is switched off for good. (The code below is kept only for reference.)
        return 'Booking requires payment. Please use the Book Now button and pay the booking fee.';

        try {

            $this->conn->beginTransaction();

            /**
             * Lock the house row for the duration of this transaction.
             */
            $houseStmt = $this->conn->prepare("
                SELECT status
                FROM houses
                WHERE id = :house_id
                FOR UPDATE
            ");

            $houseStmt->execute([
                ':house_id' => $house_id
            ]);

            $house = $houseStmt->fetch(PDO::FETCH_ASSOC);

            if (!$house) {
                $this->conn->rollBack();
                return "Property not found.";
            }

            if ($house['status'] !== 'available') {
                $this->conn->rollBack();
                return "This property is no longer available.";
            }

            /**
             * Friendly pre-check. The unique index is the real guarantee.
             */
            $checkQuery = "SELECT id
                           FROM " . $this->table . "
                           WHERE tenant_id = :tenant_id
                           AND house_id = :house_id
                           AND status = 'pending'
                           LIMIT 1";

            $checkStmt = $this->conn->prepare($checkQuery);

            $checkStmt->execute([
                ':tenant_id' => $tenant_id,
                ':house_id' => $house_id
            ]);

            if ($checkStmt->rowCount() > 0) {
                $this->conn->rollBack();
                return "You already have a pending booking for this property.";
            }

            /**
             * Insert booking
             */
            $query = "INSERT INTO " . $this->table . "
                     (tenant_id, house_id, landlord_id, status)
                     VALUES
                     (:tenant_id, :house_id, :landlord_id, 'pending')";

            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                ':tenant_id' => $tenant_id,
                ':house_id' => $house_id,
                ':landlord_id' => $landlord_id
            ]);

            $newBookingId = (int) $this->conn->lastInsertId();

            $this->conn->commit();

            return $newBookingId;   // was: return true;

        } catch (PDOException $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            /**
             * Unique constraint violation: a concurrent request won the race.
             */
            if ((string) $e->getCode() === '23000') {
                return "You already have a pending booking for this property.";
            }

            error_log('LUX EMPIRE booking creation failed: ' . $e->getMessage());

            return "Unable to submit booking request. Please try again.";
        }
    }

    /**
     * ACCEPT BOOKING (landlord)
     *
     * This is the single business operation described in the spec:
     *
     *   - Verify the booking belongs to this landlord and is pending.
     *   - Atomically flip the house from 'available' to 'booked'
     *     (the conditional UPDATE ... WHERE status = 'available' is
     *     what makes this safe under concurrent accept attempts —
     *     only one request's UPDATE can match a row still in
     *     'available' state; InnoDB's row lock on that UPDATE
     *     serializes concurrent attempts).
     *   - Approve this booking.
     *   - Reject every other pending booking for the same house.
     *
     * All in one transaction. No notifications are created here —
     * the caller creates them only after this method returns
     * success, i.e. only after the transaction has committed.
     *
     * Returns an array; 'success' => false on any conflict, with a
     * user-safe 'message'.
     */
    public function acceptBooking(int $bookingId, int $landlordId): array
    {
        try {

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, house_id, tenant_id, landlord_id, status
                FROM " . $this->table . "
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $bookingId]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking || (int) $booking['landlord_id'] !== $landlordId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Booking not found or unauthorized.'];
            }

            if ($booking['status'] !== 'pending') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This request has already been handled.'];
            }

            $houseId = (int) $booking['house_id'];

            /**
             * Atomic compare-and-swap. Succeeds only if the house is
             * still 'available'. If two accept attempts race, only
             * one UPDATE can affect a row (the other finds 0 rows
             * matching once the first has committed its row lock).
             */
            $houseUpdate = $this->conn->prepare("
                UPDATE houses
                SET status = 'booked', booked_at = NOW()
                WHERE id = :house_id
                AND status = 'reserved'
                AND reserved_by_booking_id = :booking_id
            ");

            $houseUpdate->execute([
                ':house_id' => $houseId,
                ':booking_id' => $bookingId
            ]);

            if ($houseUpdate->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This property has already been booked.'];
            }

            $approve = $this->conn->prepare("
                UPDATE " . $this->table . "
                SET status = 'approved'
                WHERE id = :id
                AND status = 'pending'
            ");

            $approve->execute([':id' => $bookingId]);

            if ($approve->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This request has already been handled.'];
            }

            // A free-voucher booking that gets approved uses its voucher up for good.
            PaymentWaiver::settleOnApproval($this->conn, $bookingId);

            /**
             * Lock and collect every other pending request for the
             * same house, so we can notify those tenants after commit.
             */
            $competitors = $this->conn->prepare("
                SELECT id, tenant_id
                FROM " . $this->table . "
                WHERE house_id = :house_id
                AND status = 'pending'
                AND id != :id
                FOR UPDATE
            ");

            $competitors->execute([
                ':house_id' => $houseId,
                ':id' => $bookingId
            ]);

            $rejected = $competitors->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rejected)) {

                $reject = $this->conn->prepare("
                    UPDATE " . $this->table . "
                    SET status = 'rejected'
                    WHERE house_id = :house_id
                    AND status = 'pending'
                    AND id != :id
                ");

                $reject->execute([
                    ':house_id' => $houseId,
                    ':id' => $bookingId
                ]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Booking approved.',
                'booking_id' => $bookingId,
                'house_id' => $houseId,
                'tenant_id' => (int) $booking['tenant_id'],
                'rejected' => array_map(
                    static function ($row) {
                        return [
                            'booking_id' => (int) $row['id'],
                            'tenant_id' => (int) $row['tenant_id']
                        ];
                    },
                    $rejected
                )
            ];

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE booking acceptance failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Unable to process this request. Please try again.'];
        }
    }

    /**
     * REJECT BOOKING (landlord)
     *
     * A direct, single rejection — the landlord declining one
     * specific pending request without accepting a competitor.
     * Does not touch the house's availability.
     */
    public function rejectBooking(int $bookingId, int $landlordId): array
    {
        try {

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, house_id, tenant_id, landlord_id, status, payment_status, payment_id
                FROM " . $this->table . "
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking || (int) $booking['landlord_id'] !== $landlordId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Booking not found or unauthorized.'];
            }

            if ($booking['status'] !== 'pending') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This request has already been handled.'];
            }

            $update = $this->conn->prepare("
                UPDATE " . $this->table . "
                SET status = 'rejected'
                WHERE id = :id
                AND status = 'pending'
            ");
            $update->execute([':id' => $bookingId]);

            if ($update->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This request has already been handled.'];
            }

            // Release the house back onto the market — only if THIS
            // booking was the one holding the reservation.
            $this->conn->prepare("
                UPDATE houses
                SET status = 'available', reserved_by_booking_id = NULL
                WHERE id = :house_id AND reserved_by_booking_id = :booking_id
            ")->execute([':house_id' => $booking['house_id'], ':booking_id' => $bookingId]);

            // Free-voucher booking: hand the voucher back (first attempt) or use it up (second).
            $voucherOutcome = PaymentWaiver::settleOnEnd($this->conn, $bookingId);

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Booking rejected.',
                'voucher_outcome' => $voucherOutcome,
                'booking_id' => $bookingId,
                'house_id' => (int) $booking['house_id'],
                'tenant_id' => (int) $booking['tenant_id'],
                'payment_status' => $booking['payment_status'] ?? 'unpaid',
                'payment_id' => $booking['payment_id'] ?? null,
            ];

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE booking rejection failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Unable to process this request. Please try again.'];
        }
    }

    /**
     * Which of these houses has THIS tenant paid for and still holds a live
     * booking on (pending or approved)? Decides who may see the landlord's
     * contact details. A rejected / cancelled / expired booking is refunded,
     * so it no longer counts. Returns [house_id => true].
     */
    public function getPaidHouseIdsForTenant(int $tenantId, array $houseIds): array
    {
        $houseIds = array_values(array_unique(array_filter(array_map('intval', $houseIds))));

        if (empty($houseIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($houseIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT DISTINCT house_id
            FROM " . $this->table . "
            WHERE tenant_id = ?
            AND payment_status = 'paid'
            AND status IN ('pending', 'approved')
            AND house_id IN ({$placeholders})
        ");

        $stmt->execute(array_merge([$tenantId], $houseIds));

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $houseId) {
            $map[(int) $houseId] = true;
        }

        return $map;
    }

    public function hasPaidBookingForHouse(int $tenantId, int $houseId): bool
    {
        return isset($this->getPaidHouseIdsForTenant($tenantId, [$houseId])[$houseId]);
    }

    /**
     * Which of these houses may THIS tenant see the landlord's phone/email for?
     * Depends on ListingState::contactRevealStatuses() (default: only after the
     * landlord has accepted). Returns [house_id => true].
     */
    public function getContactHouseIdsForTenant(int $tenantId, array $houseIds): array
    {
        $houseIds = array_values(array_unique(array_filter(array_map('intval', $houseIds))));

        if (empty($houseIds)) {
            return [];
        }

        $statuses = ListingState::contactRevealStatuses();

        $statusMarks = implode(',', array_fill(0, count($statuses), '?'));
        $houseMarks = implode(',', array_fill(0, count($houseIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT DISTINCT house_id
            FROM " . $this->table . "
            WHERE tenant_id = ?
            AND payment_status = 'paid'
            AND status IN ({$statusMarks})
            AND house_id IN ({$houseMarks})
        ");

        $stmt->execute(array_merge([$tenantId], $statuses, $houseIds));

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $houseId) {
            $map[(int) $houseId] = true;
        }

        return $map;
    }

    public function hasContactAccessForHouse(int $tenantId, int $houseId): bool
    {
        return isset($this->getContactHouseIdsForTenant($tenantId, [$houseId])[$houseId]);
    }

    /**
     * Chat gate: does this tenant hold a PAID, still-live (pending or approved)
     * booking with this landlord? Used in both directions — tenant -> landlord
     * and landlord -> tenant. When the booking ends (rejected / cancelled /
     * expired) the fee is refunded and this becomes false again.
     */
    public function hasLiveBookingWithLandlord(int $tenantId, int $landlordId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM " . $this->table . "
            WHERE tenant_id = :tenant_id
            AND landlord_id = :landlord_id
            AND payment_status = 'paid'
            AND status IN ('pending', 'approved')
            LIMIT 1
        ");

    $stmt->execute([':tenant_id' => $tenantId, ':landlord_id' => $landlordId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Has this landlord's contact reveal been unlocked for this tenant?
     * (A paid booking whose status is in ListingState::contactRevealStatuses().)
     * While false, phone numbers and emails are masked in their chat.
     */
    public function hasContactRevealBetween(int $tenantId, int $landlordId): bool
    {
        $statuses = ListingState::contactRevealStatuses();
        $marks = implode(',', array_fill(0, count($statuses), '?'));

        $stmt = $this->conn->prepare("
            SELECT 1
            FROM " . $this->table . "
            WHERE tenant_id = ?
            AND landlord_id = ?
            AND payment_status = 'paid'
            AND status IN ({$marks})
            LIMIT 1
        ");

        $stmt->execute(array_merge([$tenantId, $landlordId], $statuses));

        return (bool) $stmt->fetchColumn();
    }

    /**
     * TENANT SELF-CANCELLATION (only while the landlord has not decided)
     *
     * Same locking pattern as acceptBooking()/rejectBooking(): the booking
     * row is locked FOR UPDATE first, so if the landlord accepts at the same
     * instant only ONE of the two wins. On success the house goes back to
     * 'available' (only if THIS booking was holding the reservation).
     * Refund + notifications are the caller's job, AFTER this commits.
     */
    public function cancelPendingBooking(int $bookingId, int $tenantId): array
    {
        try {

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, house_id, tenant_id, landlord_id, status, payment_status, payment_id, house_title_snapshot
                FROM " . $this->table . "
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking || (int) $booking['tenant_id'] !== $tenantId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Booking not found or unauthorized.'];
            }

            if ($booking['status'] !== 'pending') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The landlord has already responded to this request, so it can no longer be cancelled.'];
            }

            $update = $this->conn->prepare("
                UPDATE " . $this->table . "
                SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
            $update->execute([':id' => $bookingId]);

            if ($update->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This request has already been handled.'];
            }

            $voucherOutcome = PaymentWaiver::settleOnEnd($this->conn, $bookingId);

            if (!empty($booking['house_id'])) {
                $this->conn->prepare("
                    UPDATE houses
                    SET status = 'available', reserved_by_booking_id = NULL
                    WHERE id = :house_id AND reserved_by_booking_id = :booking_id
                ")->execute([
                    ':house_id' => $booking['house_id'],
                    ':booking_id' => $bookingId
                ]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'booking_id' => $bookingId,
                'house_id' => $booking['house_id'] !== null ? (int) $booking['house_id'] : null,
                'landlord_id' => (int) $booking['landlord_id'],
                'title' => (string) ($booking['house_title_snapshot'] ?? ''),
                'voucher_outcome' => $voucherOutcome,
                'payment_status' => $booking['payment_status'] ?? 'unpaid',
                'payment_id' => $booking['payment_id'] ?? null,
            ];

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE booking self-cancel failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Unable to cancel this request. Please try again.'];
        }
    }

    /**
     * TENANT "DELETE" = remove from MY list only (soft delete). The row
     * stays for landlord history, admin oversight and payment/refund
     * disputes. The caller must already have verified ownership and that
     * the booking is not pending.
     */
    public function hideBookingForTenant(int $bookingId, int $tenantId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE " . $this->table . "
            SET hidden_by_tenant_at = COALESCE(hidden_by_tenant_at, NOW())
            WHERE id = :id AND tenant_id = :tenant_id AND status <> 'pending'
        ");

        return $stmt->execute([':id' => $bookingId, ':tenant_id' => $tenantId]);
    }

    /**
     * GET BOOKINGS BY TENANT
     */
    public function getBookingsByTenant($tenant_id) {

        $query = "SELECT
                    b.*,
                    COALESCE(h.title, b.house_title_snapshot) AS title,
                    h.location,
                    h.price,
                    h.rating,
                    h.bedrooms,
                    h.bathrooms,
                    h.verified_at,
                    u.full_name AS landlord_name,
                    u.phone AS landlord_phone,
                    u.email AS landlord_email,
                    r.status AS refund_status,
                    w.status AS waiver_status,
                    (
                        SELECT hi.image_path
                        FROM house_images hi
                        WHERE hi.house_id = h.id
                        LIMIT 1
                    ) AS image
                  FROM bookings b
                  LEFT JOIN houses h ON b.house_id = h.id
                  LEFT JOIN users u ON b.landlord_id = u.id
                  LEFT JOIN refunds r ON r.payment_id = b.payment_id
                  LEFT JOIN payment_waivers w ON w.id = b.waiver_id
                  WHERE b.tenant_id = :tenant_id
                  AND b.hidden_by_tenant_at IS NULL
                  ORDER BY b.booking_date DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':tenant_id', $tenant_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * BOOKING STATS FOR LANDLORD DASHBOARD
     *
     * Used by dashboard/landlord/dashboard.php for the Total/
     * Pending/Approved stat cards. One aggregate query — never
     * fetches the underlying booking rows just to count them in
     * PHP, so this stays fast no matter how many bookings a
     * landlord accumulates over years on the platform.
     */
    public function getBookingStatsForLandlord(int $landlordId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved
            FROM " . $this->table . "
            WHERE landlord_id = :landlord_id
        ");

        $stmt->execute([':landlord_id' => $landlordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
        ];
    }

    /**
     * RECENT BOOKINGS FOR LANDLORD DASHBOARD
     *
     * Feeds the "Recent Booking Activity" widget. Bounded by
     * $limit at the SQL level — never fetches more rows than the
     * widget actually shows.
     */
    public function getRecentBookingsByLandlord(int $landlordId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->conn->prepare("
            SELECT
                b.id,
                b.status,
                b.booking_date
            FROM " . $this->table . " b
            WHERE b.landlord_id = :landlord_id
            ORDER BY b.id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':landlord_id', $landlordId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * BOOKING HISTORY FOR LANDLORD (paginated)
     *
     * Requests that have been ANSWERED: approved, declined, cancelled by the
     * tenant, or expired. Live requests belong in the pending queue and unpaid
     * rows never count. Pass $days = null for all-time. Returns
     * ['bookings' => [...], 'total' => int].
     */
    public function getBookingHistoryForLandlord(int $landlordId, int $limit = 20, int $offset = 0, ?int $days = 90): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = "b.landlord_id = :landlord_id AND b.payment_status = 'paid' AND b.status <> 'pending'";
        $params = [':landlord_id' => $landlordId];

        if ($days !== null) {
            $where .= " AND b.booking_date >= (NOW() - INTERVAL :days DAY)";
            $params[':days'] = $days;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM " . $this->table . " b WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $query = "SELECT
                    b.*,
                    COALESCE(h.title, b.house_title_snapshot) AS title,
                    h.location,
                    h.price,
                    h.rating,
                    h.is_hidden,
                    h.is_flagged,

                    u.full_name AS tenant_name,
                    u.phone AS tenant_phone,
                    u.email AS tenant_email,

                    r.reason AS refund_reason

                FROM " . $this->table . " b

                LEFT JOIN houses h
                ON b.house_id = h.id

                JOIN users u
                ON b.tenant_id = u.id

                LEFT JOIN refunds r
                ON r.payment_id = b.payment_id

                WHERE {$where}

                ORDER BY b.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

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
     * GET PENDING BOOKINGS BY LANDLORD (work queue)
     *
     * Only requests that actually require landlord action.
     * Once a booking becomes approved/rejected it no longer
     * appears here — the underlying row is untouched for
     * history/auditing, it simply stops matching this query.
     */
    public function getPendingBookingsByLandlord($landlord_id) {

        $query = "SELECT
                    b.*,
                    COALESCE(h.title, b.house_title_snapshot) AS title,
                    h.location,
                    h.price,
                    h.rating,
                    h.is_hidden,
                    h.is_flagged,

                    (
                        SELECT hi.image_path
                        FROM house_images hi
                        WHERE hi.house_id = h.id
                        LIMIT 1
                    ) AS image,

                    u.full_name AS tenant_name,
                    u.phone AS tenant_phone,
                    u.email AS tenant_email

                FROM " . $this->table . " b

                LEFT JOIN houses h
                ON b.house_id = h.id

                JOIN users u
                ON b.tenant_id = u.id

                WHERE b.landlord_id = :landlord_id
                AND b.status = 'pending'
                AND b.payment_status = 'paid'

                ORDER BY b.id DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':landlord_id', $landlord_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * UPDATE BOOKING STATUS
     *
     * Legacy generic setter. Kept for backward compatibility.
     * Does NOT synchronize houses.status or reject competing
     * bookings — use acceptBooking()/rejectBooking() for the
     * landlord accept/reject flow instead.
     */
    public function updateBookingStatus($booking_id, $status) {

        $allowed = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed)) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET status = :status
                  WHERE id = :booking_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':status' => $status,
            ':booking_id' => $booking_id
        ]);
    }

    /**
     * GET THIS TENANT'S MOST RECENT BOOKING FOR A SPECIFIC HOUSE
     *
     * Used by view_house.php (and anywhere else showing a single
     * house to a tenant) to decide the booking button's state
     * ("Book Now" / "Request Pending" / "Booked by You") without
     * pulling every booking the tenant has ever made.
     *
     * Returns null if the tenant has never booked this house.
     */
    public function getTenantBookingForHouse(int $tenantId, int $houseId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, status
            FROM " . $this->table . "
            WHERE tenant_id = :tenant_id
            AND house_id = :house_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':house_id' => $houseId
        ]);

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        return $booking ?: null;
    }

        /**
     * GET SINGLE BOOKING BY ID
     */
    public function getBookingById($id) {

        $query = "SELECT *
                FROM " . $this->table . "
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $id);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * GET ALL BOOKINGS FOR A TENANT
     */
    public function getTenantBookings($tenant_id) {

        $query = "SELECT
                    b.*,
                    h.title,
                    h.location,
                    h.price,
                    h.id AS house_id
                FROM bookings b
                JOIN houses h ON b.house_id = h.id
                WHERE b.tenant_id = :tenant_id
                AND b.hidden_by_tenant_at IS NULL
                ORDER BY b.booking_date DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':tenant_id', $tenant_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CANCEL BOOKING
     */
    public function cancelBooking($booking_id) {

        $query = "UPDATE " . $this->table . "
                  SET status = 'cancelled'
                  WHERE id = :booking_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':booking_id' => $booking_id
        ]);
    }

    /**
     * UPDATE BOOKING STATUS (LANDLORD)
     *
     * Legacy method, kept for backward compatibility. Not used by
     * the new AJAX accept/reject endpoint — see acceptBooking()/
     * rejectBooking() above, which are transactional and keep
     * houses.status and competing bookings in sync.
     */
    public function landlordUpdateStatus($booking_id, $status) {

        $allowed = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed)) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET status = :status
                  WHERE id = :booking_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':status' => $status,
            ':booking_id' => $booking_id
        ]);
    }

    /**
     * UPDATE BOOKING STATUS (TENANT)
     */
    public function tenantUpdateStatus($booking_id, $status) {

        $allowed = ['pending', 'cancelled'];

        if (!in_array($status, $allowed)) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET status = :status
                  WHERE id = :booking_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':status' => $status,
            ':booking_id' => $booking_id
        ]);
    }

        /**
     * DELETE BOOKING
     */
    public function deleteBooking($booking_id, $tenant_id) {

        $query = "DELETE FROM " . $this->table . "
                  WHERE id = :booking_id
                  AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':booking_id' => $booking_id,
            ':tenant_id' => $tenant_id
        ]);
    }

}