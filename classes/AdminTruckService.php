<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';

/**
 * LUX EMPIRE
 * Admin oversight of truck requests — listing and reason-required
 * delete, restricted to pending requests per spec (accepted/active/
 * completed requests are left alone; that's operational history).
 * Does not touch classes/TruckRequest.php.
 */
final class AdminTruckService
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Returns ['requests' => [...], 'total' => int]. $limit capped
     * at 100 server-side.
     */
    public function listRequests(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = '';
        $params = [];

        if ($status !== null) {
            $where = " WHERE tr.status = :status";
            $params[':status'] = $status;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM truck_requests tr{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                tr.id, tr.pickup_location, tr.destination, tr.status,
                tr.price, tr.requested_at,
                tenant.id AS tenant_id, tenant.full_name AS tenant_name, tenant.phone AS tenant_phone,
                driver.full_name AS driver_name
            FROM truck_requests tr
            JOIN users tenant ON tr.tenant_id = tenant.id
            LEFT JOIN users driver ON tr.driver_id = driver.id
            {$where}
            ORDER BY tr.requested_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function deletePendingRequest(int $requestId, int $adminId, string $reason): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM truck_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $requestId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $this->recordReason($adminId, 'delete_truck_request', 'truck_requests', $requestId, $reason);
        Audit::log("Admin #{$adminId} deleted pending truck request #{$requestId} ({$reason})", $adminId);

        return true;
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
