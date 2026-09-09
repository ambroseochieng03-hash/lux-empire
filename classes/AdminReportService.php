<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * LUX EMPIRE
 * Aggregate-only queries for the admin reports page — moderation
 * queue health plus a recent feed of admin actions. No mutation
 * methods; this class never changes data.
 */
final class AdminReportService
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getModerationSummary(): array
    {
        return [
            'flagged_users' => (int) $this->conn->query("SELECT COUNT(*) FROM users WHERE is_flagged = 1")->fetchColumn(),
            'flagged_listings' => (int) $this->conn->query("SELECT COUNT(*) FROM houses WHERE is_flagged = 1")->fetchColumn(),
            'hidden_listings' => (int) $this->conn->query("SELECT COUNT(*) FROM houses WHERE is_hidden = 1")->fetchColumn(),
            'pending_landlord_verification' => (int) $this->conn->query("
                SELECT COUNT(*) FROM users WHERE role = 'landlord' AND verified_at IS NULL
            ")->fetchColumn(),
            'pending_driver_verification' => (int) $this->conn->query("
                SELECT COUNT(*) FROM users WHERE role = 'driver' AND verified_at IS NULL
            ")->fetchColumn(),
            'suspended_users' => (int) $this->conn->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn(),
            'active_emergencies' => (int) $this->conn->query("SELECT COUNT(*) FROM emergency_alerts WHERE status = 'active'")->fetchColumn(),
        ];
    }

    public function getRecentAdminActions(int $limit = 10): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                aar.action_type, aar.target_table, aar.target_id, aar.reason, aar.created_at,
                admin.full_name AS admin_name
            FROM admin_action_reasons aar
            JOIN users admin ON aar.admin_id = admin.id
            ORDER BY aar.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
