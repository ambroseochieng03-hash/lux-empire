<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * LUX EMPIRE
 * Read-only lookup of a user's verification status. Kept separate
 * from classes/House.php (frozen) and classes/User.php — used by
 * tenant- and landlord-facing pages to render "Verified" badges for
 * landlords (and drivers, wherever needed) without either of those
 * classes needing to expose it themselves.
 */
final class VerificationLookup
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * @param int[] $userIds
     * @return array<int,bool> user_id => isVerified
     */
    public function getVerifiedMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            $userIds,
            static fn ($id) => (int) $id > 0
        )));

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT id, verified_at FROM users WHERE id IN ($placeholders)
        ");
        $stmt->execute($userIds);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = $row['verified_at'] !== null;
        }

        return $map;
    }

    public function isVerified(int $userId): bool
    {
        return $this->getVerifiedMap([$userId])[$userId] ?? false;
    }
}
