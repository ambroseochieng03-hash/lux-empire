<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';

final class AdminEmergencyService
{
    private const ALLOWED_STATUSES = ['active', 'responding', 'resolved', 'dismissed'];

    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function listAlerts(): array
    {
        $stmt = $this->conn->query("
            SELECT
                ea.*,
                u.full_name, u.phone, u.role,
                tr.status AS trip_status, tr.pickup_location, tr.destination,
                tenant_loc.latitude AS tenant_latitude, tenant_loc.longitude AS tenant_longitude,
                driver_loc.latitude AS driver_latitude, driver_loc.longitude AS driver_longitude,
                driver.full_name AS driver_name, driver.phone AS driver_phone
            FROM emergency_alerts ea
            JOIN users u ON ea.user_id = u.id
            LEFT JOIN truck_requests tr ON ea.trip_id = tr.id
            LEFT JOIN users driver ON tr.driver_id = driver.id
            LEFT JOIN tenant_locations tenant_loc ON tr.tenant_id = tenant_loc.tenant_id
            LEFT JOIN driver_locations driver_loc ON tr.driver_id = driver_loc.driver_id
            ORDER BY ea.created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $alertId, string $status, int $adminId): bool
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status value.');
        }

        $stmt = $this->conn->prepare("UPDATE emergency_alerts SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $alertId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        Audit::log("Admin #{$adminId} set emergency alert #{$alertId} status to '{$status}'", $adminId);

        return true;
    }

    public function deleteAlert(int $alertId, int $adminId, string $reason): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM emergency_alerts WHERE id = :id");
        $stmt->execute([':id' => $alertId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $record = $this->conn->prepare("
            INSERT INTO admin_action_reasons (admin_id, action_type, target_table, target_id, reason)
            VALUES (:admin_id, 'delete_emergency_alert', 'emergency_alerts', :target_id, :reason)
        ");
        $record->execute([':admin_id' => $adminId, ':target_id' => $alertId, ':reason' => $reason]);

        Audit::log("Admin #{$adminId} permanently deleted emergency alert #{$alertId} ({$reason})", $adminId);

        return true;
    }
}
