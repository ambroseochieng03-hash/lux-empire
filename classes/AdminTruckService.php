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

    /**
     * Last-resort: admin cancels a trip that is already accepted/underway.
     * No commission is charged (the trip never completed), no money changes
     * hands, both parties are notified with the reason, and it's permanently
     * recorded — this is NOT a delete, the row and its full history survive.
     */
    public function forceCancelActiveTrip(int $requestId, int $adminId, string $reason): array
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, tenant_id, driver_id, status, pickup_location, destination
                FROM truck_requests WHERE id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $requestId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trip not found.', 'code' => 404];
            }

            // Once a trip is in_transit, cargo is already moving — cancelling
            // outright no longer makes sense (use "Force Resolve" instead,
            // which is built for exactly that case). Cancellation here is
            // only for a trip that hasn't actually started moving yet.
            if (!in_array($trip['status'], ['accepted', 'arrived_at_pickup'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This trip is already in transit and can no longer be cancelled — use Force Resolve instead.', 'code' => 409];
            }

            $update = $this->conn->prepare("
                UPDATE truck_requests
                SET status = 'cancelled', cancel_reason = :reason, cancelled_by_admin_id = :admin_id
                WHERE id = :id AND status IN ('accepted', 'arrived_at_pickup')
            ");
            $update->execute([':reason' => $reason, ':admin_id' => $adminId, ':id' => $requestId]);

            if ($update->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This trip was just updated by someone else.', 'code' => 409];
            }

            $this->conn->prepare("
                INSERT INTO trip_status_history (trip_id, status, changed_by, ip_address)
                VALUES (:trip_id, 'cancelled', :admin_id, :ip)
            ")->execute([':trip_id' => $requestId, ':admin_id' => $adminId, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null]);

            $this->conn->prepare("DELETE FROM driver_active_trip_lock WHERE trip_id = :trip_id")
                ->execute([':trip_id' => $requestId]);

            $this->conn->commit();

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) { $this->conn->rollBack(); }
            error_log('LUX EMPIRE forceCancelActiveTrip failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not cancel this trip. Please try again.', 'code' => 500];
        }

        require_once __DIR__ . '/Notification.php';
        $notification = new Notification();

        $summary = $trip['pickup_location'] . ' to ' . $trip['destination'];

        $notification->create(
            (int) $trip['tenant_id'], 'trip_admin_cancelled', 'Trip Cancelled by LUX EMPIRE',
            'Your move (' . $summary . ') was cancelled by our team. Reason: ' . $reason,
            BASE_URL . '/tenant/my-bookings'
        );

        if (!empty($trip['driver_id'])) {
            $notification->create(
                (int) $trip['driver_id'], 'trip_admin_cancelled', 'Trip Cancelled by LUX EMPIRE',
                'The trip (' . $summary . ') was cancelled by our team. Reason: ' . $reason,
                BASE_URL . '/driver/active-trip'
            );
        }

        $this->recordReason($adminId, 'force_cancel_trip', 'truck_requests', $requestId, $reason);
        Audit::log("Admin #{$adminId} force-cancelled active trip #{$requestId} ({$reason})", $adminId);

        return ['success' => true];
    }

    /**
     * Force-resolves a trip stuck in 'in_transit' that a driver can no longer
     * close normally (e.g. app closed, phone lost). Deliberately does NOT run
     * commission deduction automatically for 'trip_completed' — that money
     * decision is made explicitly by the admin choosing this option, with a
     * reason on record, not silently by a script.
     */
    public function resolveStuckTrip(int $requestId, int $adminId, string $outcome, string $reason): array
    {
        if (!in_array($outcome, ['trip_completed', 'trip_cancelled'], true)) {
            return ['success' => false, 'message' => 'Invalid outcome.', 'code' => 400];
        }

        $newStatus = $outcome === 'trip_completed' ? 'completed' : 'cancelled';

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT id, tenant_id, driver_id, price, status, destination
                FROM truck_requests WHERE id = :id AND status = 'in_transit' FOR UPDATE
            ");
            $stmt->execute([':id' => $requestId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a trip currently in transit can be resolved this way.', 'code' => 409];
            }

            $this->conn->prepare("
                UPDATE truck_requests SET status = :status, cancel_reason = :reason WHERE id = :id AND status = 'in_transit'
            ")->execute([':status' => $newStatus, ':reason' => $reason, ':id' => $requestId]);

            $this->conn->prepare("
                INSERT INTO trip_status_history (trip_id, status, changed_by, ip_address)
                VALUES (:trip_id, :status, :admin_id, :ip)
            ")->execute([':trip_id' => $requestId, ':status' => $newStatus, ':admin_id' => $adminId, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null]);

            if ($outcome === 'trip_completed' && !empty($trip['driver_id'])) {
                require_once __DIR__ . '/Payment.php';
                (new Payment())->deductCommission((int) $trip['driver_id'], (float) $trip['price'], $requestId);
            }

            // Trip is ending either way (completed or cancelled) — free the driver.
            $this->conn->prepare("DELETE FROM driver_active_trip_lock WHERE trip_id = :trip_id")
                ->execute([':trip_id' => $requestId]);

            $this->conn->commit();

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) { $this->conn->rollBack(); }
            error_log('LUX EMPIRE resolveStuckTrip failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not resolve this trip.', 'code' => 500];
        }

        $this->recordReason($adminId, 'resolve_stuck_trip', 'truck_requests', $requestId, "{$outcome}: {$reason}");
        Audit::log("Admin #{$adminId} resolved stuck trip #{$requestId} as {$outcome} ({$reason})", $adminId);

        return ['success' => true];
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
