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
     */
    public function createBooking($tenant_id, $house_id, $landlord_id) {

        /**
         * Prevent duplicate pending booking
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

        $success = $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':house_id' => $house_id,
            ':landlord_id' => $landlord_id
        ]);

        return $success;
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
     * GET BOOKINGS BY LANDLORD
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
     * UPDATE BOOKING STATUS
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