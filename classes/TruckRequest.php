<?php

require_once __DIR__ . '/../config/db.php';

class TruckRequest {

    private $conn;
    private $table = "truck_requests";

    public function __construct() {

        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * CREATE TRUCK REQUEST
     */
    public function createRequest($data) {

        $query = "INSERT INTO " . $this->table . "
        (
            tenant_id,
            pickup_location,
            destination,
            pickup_lat,
            pickup_lng,
            destination_lat,
            destination_lng,
            price,
            status
        )
        VALUES
        (
            :tenant_id,
            :pickup_location,
            :destination,
            :pickup_lat,
            :pickup_lng,
            :destination_lat,
            :destination_lng,
            :price,
            'pending'
        )";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([

            ':tenant_id' => $data['tenant_id'],
            ':pickup_location' => $data['pickup_location'],
            ':destination' => $data['destination'],
            ':pickup_lat' => $data['pickup_lat'],
            ':pickup_lng' => $data['pickup_lng'],
            ':destination_lat' => $data['destination_lat'],
            ':destination_lng' => $data['destination_lng'],
            ':price' => $data['price']
        ]);
    }

    /**
     * GET ALL PENDING REQUESTS
     */
    public function getPendingRequests() {

        $query = "SELECT
                    tr.*,
                    u.full_name,
                    u.phone
                  FROM " . $this->table . " tr
                  JOIN users u ON tr.tenant_id = u.id
                  WHERE tr.status = 'pending'
                  ORDER BY tr.id DESC";

        $stmt = $this->conn->query($query);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ASSIGN DRIVER
     */
    public function assignDriver($request_id, $driver_id) {

        $query = "UPDATE " . $this->table . "
                  SET
                    driver_id = :driver_id,
                    status = 'accepted'
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':driver_id' => $driver_id,
            ':id' => $request_id
        ]);
    }

    /**
     * UPDATE STATUS
     */
    public function updateStatus($request_id, $status) {

        $allowed = [
            'pending',
            'accepted',
            'in_transit',
            'completed',
            'cancelled'
        ];

        if (!in_array($status, $allowed)) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET status = :status
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':status' => $status,
            ':id' => $request_id
        ]);
    }

    /**
     * GET SINGLE REQUEST BY ID
     *
     * Added for the AJAX cancel/delete endpoints, so they can look
     * up + verify ownership through the model instead of running
     * raw SQL directly in the API file.
     */
    public function getRequestById($request_id) {

        $query = "SELECT *
                  FROM " . $this->table . "
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([':id' => $request_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * DELETE REQUEST
     *
     * Ownership is enforced here (tenant_id must match) rather than
     * relying solely on the caller — mirrors Booking::deleteBooking().
     */
    public function deleteRequest($request_id, $tenant_id) {

        $query = "DELETE FROM " . $this->table . "
                  WHERE id = :id
                  AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':id' => $request_id,
            ':tenant_id' => $tenant_id
        ]);
    }

    /**
     * GET TENANT REQUESTS
     */
    public function getTenantRequests($tenant_id) {

        $query = "SELECT *
                  FROM " . $this->table . "
                  WHERE tenant_id = :tenant_id
                  ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':tenant_id', $tenant_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * GET DRIVER TRIPS
     */
    public function getDriverTrips($driver_id) {

        $query = "SELECT
                    tr.*,
                    u.full_name AS tenant_name,
                    u.phone AS tenant_phone
                  FROM " . $this->table . " tr
                  JOIN users u ON tr.tenant_id = u.id
                  WHERE tr.driver_id = :driver_id
                  ORDER BY tr.id DESC";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':driver_id', $driver_id);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}