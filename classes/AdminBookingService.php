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

    public function listBookings(): array
    {
        $stmt = $this->conn->query("
            SELECT
                b.id, b.status, b.booking_date,
                h.id AS house_id, COALESCE(h.title, b.house_title_snapshot) AS house_title,
                tenant.id AS tenant_id, tenant.full_name AS tenant_name, tenant.email AS tenant_email,
                landlord.id AS landlord_id, landlord.full_name AS landlord_name
            FROM bookings b
            LEFT JOIN houses h ON b.house_id = h.id
            JOIN users tenant ON b.tenant_id = tenant.id
            JOIN users landlord ON b.landlord_id = landlord.id
            ORDER BY b.id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
