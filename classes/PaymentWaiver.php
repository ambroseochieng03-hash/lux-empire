<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class PaymentWaiver
{
    private static function connect(): PDO
    {
        return (new Database())->connect();
    }

    public static function isWaived(int $userId, string $role): bool
    {
        $pdo = self::connect();

        $stmt = $pdo->prepare("
            SELECT id FROM payment_waivers
            WHERE revoked_at IS NULL
            AND expires_at > NOW()
            AND (
                (scope = 'user' AND user_id = :user_id)
                OR (scope = 'role' AND role = :role)
            )
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId, ':role' => $role]);

        return (bool) $stmt->fetchColumn();
    }

    public static function listActive(): array
    {
        $stmt = self::connect()->query("
            SELECT w.*, u.full_name, u.email, a.full_name AS granted_by_name
            FROM payment_waivers w
            LEFT JOIN users u ON w.user_id = u.id
            JOIN users a ON w.granted_by = a.id
            WHERE w.revoked_at IS NULL AND w.expires_at > NOW()
            ORDER BY w.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findUserByEmail(string $email): ?array
    {
        $stmt = self::connect()->prepare("SELECT id, full_name, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function grant(string $scope, ?int $userId, ?string $role, string $reason, int $grantedBy, string $expiresAt): int
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare("
            INSERT INTO payment_waivers (scope, user_id, role, reason, granted_by, expires_at)
            VALUES (:scope, :user_id, :role, :reason, :granted_by, :expires_at)
        ");
        $stmt->execute([
            ':scope' => $scope, ':user_id' => $userId, ':role' => $role,
            ':reason' => $reason, ':granted_by' => $grantedBy, ':expires_at' => $expiresAt,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function revoke(int $waiverId): void
    {
        self::connect()->prepare("UPDATE payment_waivers SET revoked_at = NOW() WHERE id = :id")
            ->execute([':id' => $waiverId]);
    }
}
