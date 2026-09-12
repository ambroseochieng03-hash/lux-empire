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
            trip_type,
            scheduled_at,
            items_description,
            pickup_location,
            destination,
            pickup_lat,
            pickup_lng,
            destination_lat,
            destination_lng,
            price,
            distance_km,
            status
        )
        VALUES
        (
            :tenant_id,
            :trip_type,
            :scheduled_at,
            :items_description,
            :pickup_location,
            :destination,
            :pickup_lat,
            :pickup_lng,
            :destination_lat,
            :destination_lng,
            :price,
            :distance_km,
            'pending'
        )";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([

            ':tenant_id' => $data['tenant_id'],
            ':trip_type' => $data['trip_type'] ?? 'instant',
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':items_description' => $data['items_description'] ?? null,
            ':pickup_location' => $data['pickup_location'],
            ':destination' => $data['destination'],
            ':pickup_lat' => $data['pickup_lat'],
            ':pickup_lng' => $data['pickup_lng'],
            ':destination_lat' => $data['destination_lat'],
            ':destination_lng' => $data['destination_lng'],
            ':price' => $data['price'],
            ':distance_km' => $data['distance_km'] ?? null
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

    /**
     * Update ONLY the fields safe to change after a request is
     * created: items_description always, scheduled_at for scheduled
     * trips. Pickup/destination/price are immutable once created —
     * the price is tied to the original coordinates, and re-picking
     * a route is a map-picker UI problem, not an edit-form one.
     * Refuses anything not still 'pending' (an accepted trip is
     * locked, per spec).
     */
    public function updateEditableFields(int $requestId, int $tenantId, array $data): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM " . $this->table . "
            WHERE id = :id AND tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $requestId, ':tenant_id' => $tenantId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return ['success' => false, 'message' => 'Truck request not found.'];
        }

        if ($request['status'] !== 'pending') {
            return ['success' => false, 'message' => 'This request has already been accepted and can no longer be edited.'];
        }

        $itemsDescription = trim($data['items_description'] ?? '');
        $itemsDescription = $itemsDescription !== '' ? mb_substr($itemsDescription, 0, 2000) : null;

        $scheduledAt = $request['scheduled_at'];

        if ($request['trip_type'] === 'scheduled') {

            $scheduledAtRaw = trim($data['scheduled_at'] ?? '');
            $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $scheduledAtRaw);

            if (!$parsed) {
                return ['success' => false, 'message' => 'Please choose a valid date and time.'];
            }

            $minimumLeadSeconds = TRUCK_MIN_SCHEDULE_LEAD_MINUTES * 60;

            if ($parsed->getTimestamp() < (time() + $minimumLeadSeconds)) {
                return ['success' => false, 'message' => 'Scheduled moves must be at least ' . TRUCK_MIN_SCHEDULE_LEAD_MINUTES . ' minutes from now.'];
            }

            $scheduledAt = $parsed->format('Y-m-d H:i:s');
        }

        $update = $this->conn->prepare("
            UPDATE " . $this->table . "
            SET items_description = :items_description,
                scheduled_at = :scheduled_at
            WHERE id = :id AND tenant_id = :tenant_id AND status = 'pending'
        ");

        $update->execute([
            ':items_description' => $itemsDescription,
            ':scheduled_at' => $scheduledAt,
            ':id' => $requestId,
            ':tenant_id' => $tenantId
        ]);

        if ($update->rowCount() === 0) {
            // Status flipped to accepted between our SELECT and this
            // UPDATE (race) — same conditional-UPDATE pattern used
            // elsewhere in this codebase.
            return ['success' => false, 'message' => 'This request has just been accepted and can no longer be edited.'];
        }

        return [
            'success' => true,
            'message' => 'Request updated.',
            'items_description' => $itemsDescription,
            'scheduled_at' => $scheduledAt
        ];
    }

}