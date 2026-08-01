<?php

class DriverTracker
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Update driver's live location
     */
    public function updateLocation(
        int $driverId,
        float $latitude,
        float $longitude
    ): bool {

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM driver_locations
            WHERE driver_id = ?
            LIMIT 1
        ");

        $stmt->execute([$driverId]);

        $existing = $stmt->fetch();

        if ($existing) {

            $update = $this->pdo->prepare("
                UPDATE driver_locations
                SET
                    latitude = ?,
                    longitude = ?,
                    updated_at = NOW()
                WHERE driver_id = ?
            ");

            return $update->execute([
                $latitude,
                $longitude,
                $driverId
            ]);
        }

        $insert = $this->pdo->prepare("
            INSERT INTO driver_locations
            (
                driver_id,
                latitude,
                longitude
            )
            VALUES (?, ?, ?)
        ");

        return $insert->execute([
            $driverId,
            $latitude,
            $longitude
        ]);
    }

    /**
     * Get driver's latest coordinates
     */
    public function getLocation(
        int $driverId
    ): ?array {

        $stmt = $this->pdo->prepare("
            SELECT
                latitude,
                longitude,
                updated_at
            FROM driver_locations
            WHERE driver_id = ?
            LIMIT 1
        ");

        $stmt->execute([$driverId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Set driver availability
     */
    public function setAvailability(
        int $driverId,
        bool $available
    ): bool {

        $stmt = $this->pdo->prepare("
            UPDATE drivers
            SET is_available = ?
            WHERE user_id = ?
        ");

        return $stmt->execute([
            $available ? 1 : 0,
            $driverId
        ]);
    }

    /**
     * Check availability
     */
    public function isAvailable(
        int $driverId
    ): bool {

        $stmt = $this->pdo->prepare("
            SELECT is_available
            FROM drivers
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$driverId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['is_available']);
    }

    /**
     * Active trip for driver
     */
    public function getActiveTrip(
        int $driverId
    ): ?array {

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM truck_requests
            WHERE driver_id = ?
            AND status IN ('accepted','in_transit')
            ORDER BY requested_at DESC
            LIMIT 1
        ");

        $stmt->execute([$driverId]);

        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        return $trip ?: null;
    }

    /**
     * Driver current status
     */
    public function getStatus(
        int $driverId
    ): string {

        $trip = $this->getActiveTrip($driverId);

        if ($trip) {
            return $trip['status'];
        }

        return 'available';
    }

    /**
     * Get all available drivers
     */
    public function getAvailableDrivers(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                users.id,
                users.full_name,
                drivers.vehicle_type,
                drivers.vehicle_plate
            FROM users

            JOIN drivers
            ON users.id = drivers.user_id

            WHERE drivers.is_available = 1
            ORDER BY users.full_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Driver last seen timestamp
     */
    public function getLastSeen(
        int $driverId
    ): ?string {

        $stmt = $this->pdo->prepare("
            SELECT updated_at
            FROM driver_locations
            WHERE driver_id = ?
            LIMIT 1
        ");

        $stmt->execute([$driverId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['updated_at'] ?? null;
    }

    /**
     * Count active drivers
     */
    public function countAvailableDrivers(): int
    {
        return (int)$this->pdo->query("
            SELECT COUNT(*)
            FROM drivers
            WHERE is_available = 1
        ")->fetchColumn();
    }

    /**
     * Count active deliveries
     */
    public function countActiveTrips(): int
    {
        return (int)$this->pdo->query("
            SELECT COUNT(*)
            FROM truck_requests
            WHERE status IN ('accepted','in_transit')
        ")->fetchColumn();
    }

    /**
     * Count completed trips
     */
    public function countCompletedTrips(): int
    {
        return (int)$this->pdo->query("
            SELECT COUNT(*)
            FROM truck_requests
            WHERE status = 'completed'
        ")->fetchColumn();
    }

    /**
     * Driver dashboard summary
     */
    public function getDriverSummary(
        int $driverId
    ): array {

        $activeTrip = $this->getActiveTrip($driverId);

        return [
            'available' => $this->isAvailable($driverId),
            'status' => $this->getStatus($driverId),
            'active_trip' => $activeTrip,
            'last_seen' => $this->getLastSeen($driverId)
        ];
    }
}