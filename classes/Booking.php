<?php

require_once __DIR__ . '/../config/db.php';

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

            $this->conn->commit();

            return true;

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
                AND status = 'available'
            ");

            $houseUpdate->execute([':house_id' => $houseId]);

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

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Booking rejected.',
                'booking_id' => $bookingId,
                'house_id' => (int) $booking['house_id'],
                'tenant_id' => (int) $booking['tenant_id']
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
     * GET BOOKINGS BY TENANT
     */
    public function getBookingsByTenant($tenant_id) {

    $query = "SELECT 
            b.*,
            h.title,
            h.location,
            h.price,
            h.rating,
            h.bedrooms,
            h.bathrooms,
                (
                    SELECT hi.image_path 
                    FROM house_images hi 
                    WHERE hi.house_id = h.id 
                    LIMIT 1
                ) AS image
              FROM bookings b
              JOIN houses h ON b.house_id = h.id
              WHERE b.tenant_id = :tenant_id
              ORDER BY b.booking_date DESC";

    $stmt = $this->conn->prepare($query);

    $stmt->bindParam(':tenant_id', $tenant_id);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * GET BOOKINGS BY LANDLORD (all statuses — history)
     */
    public function getBookingsByLandlord($landlord_id) {

        $query = "SELECT
                    b.*,
                    h.title,
                    h.location,
                    h.price,
                    h.rating,

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

                JOIN houses h
                ON b.house_id = h.id

                JOIN users u
                ON b.tenant_id = u.id

                WHERE b.landlord_id = :landlord_id

                ORDER BY b.id DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':landlord_id', $landlord_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    h.title,
                    h.location,
                    h.price,
                    h.rating,

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

                JOIN houses h
                ON b.house_id = h.id

                JOIN users u
                ON b.tenant_id = u.id

                WHERE b.landlord_id = :landlord_id
                AND b.status = 'pending'

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