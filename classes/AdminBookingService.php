<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';

/**
 * LUX EMPIRE
 * Admin oversight of bookings — listing and reason-required delete.
 * Does not touch classes/Booking.php.
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
     * at 100 server-side so a manipulated query string can't force a
     * full table scan render.
     */
    public function listBookings(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = '';
        $params = [];

        if ($status !== null) {
            $where = " WHERE b.status = :status";
            $params[':status'] = $status;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings b{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                b.id, b.status, b.booking_date,
                h.id AS house_id, COALESCE(h.title, b.house_title_snapshot) AS house_title,
                tenant.id AS tenant_id, tenant.full_name AS tenant_name, tenant.email AS tenant_email,
                landlord.id AS landlord_id, landlord.full_name AS landlord_name
            FROM bookings b
            LEFT JOIN houses h ON b.house_id = h.id
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

    public function deleteBooking(int $bookingId, int $adminId, string $reason): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM bookings WHERE id = :id");
        $stmt->execute([':id' => $bookingId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $this->recordReason($adminId, 'delete_booking', 'bookings', $bookingId, $reason);
        Audit::log("Admin #{$adminId} deleted booking #{$bookingId} ({$reason})", $adminId);

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
