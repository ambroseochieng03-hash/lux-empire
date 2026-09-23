<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';
require_once __DIR__ . '/Crypto.php';

/**
 * LUX EMPIRE
 * Admin moderation actions on users (tenants, landlords, drivers).
 * Kept entirely separate from classes/User.php — this class owns
 * admin-only mutations: suspend, activate, flag, verify, permanent
 * delete, and listing users for the admin dashboard.
 */
final class AdminUserService
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Returns ['users' => [...], 'total' => int]. $limit is capped
     * at 100 server-side regardless of what's passed in, so a typo
     * or a malicious query string can't force a full table scan render.
     */
    public function listUsers(?string $role = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = '';
        $params = [];

        if ($role !== null) {
            $where = " WHERE role = :role";
            $params[':role'] = $role;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM users{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                id, full_name, email, phone, role, status,
                is_flagged, flag_reason, verified_at, verified_by,
                created_at
            FROM users
            {$where}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function getDriverProfile(int $userId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM drivers WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userId]);

        $driver = $stmt->fetch(PDO::FETCH_ASSOC);

        return $driver ?: null;
    }

    public function suspendUser(int $userId, int $adminId): bool
    {
        $check = $this->conn->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $check->execute([':id' => $userId]);
        $role = $check->fetchColumn();

        if ($role === 'driver') {
            $active = $this->conn->prepare("
                SELECT 1 FROM truck_requests
                WHERE driver_id = :id AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
                LIMIT 1
            ");
            $active->execute([':id' => $userId]);

            if ($active->fetchColumn()) {
                throw new RuntimeException('This driver has a trip in progress. Resolve it first from Logistics Operations before suspending.');
            }
        }

        $stmt = $this->conn->prepare("
            UPDATE users SET status = 'suspended'
            WHERE id = :id AND role <> 'admin'
        ");
        $stmt->execute([':id' => $userId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} suspended user #{$userId}", $adminId);
            return true;
        }

        return false;
    }

    public function activateUser(int $userId, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE users SET status = 'active'
            WHERE id = :id AND role <> 'admin'
        ");
        $stmt->execute([':id' => $userId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} activated user #{$userId}", $adminId);
            return true;
        }

        return false;
    }

    public function flagUser(int $userId, ?string $reason, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE users SET is_flagged = 1, flag_reason = :reason
            WHERE id = :id AND role <> 'admin'
        ");
        $stmt->execute([':reason' => $reason, ':id' => $userId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} flagged user #{$userId}" . ($reason ? " ({$reason})" : ''), $adminId);
            return true;
        }

        return false;
    }

    public function unflagUser(int $userId, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE users SET is_flagged = 0, flag_reason = NULL
            WHERE id = :id AND role <> 'admin'
        ");
        $stmt->execute([':id' => $userId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} cleared flag on user #{$userId}", $adminId);
            return true;
        }

        return false;
    }

    /**
     * Verify a landlord or driver account. Returns false if the
     * user does not exist or is not a landlord/driver.
     */
    public function verifyUser(int $userId, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE users
            SET verified_at = NOW(), verified_by = :admin_id
            WHERE id = :id AND role IN ('landlord', 'driver')
        ");
        $stmt->execute([':admin_id' => $adminId, ':id' => $userId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} verified user #{$userId}", $adminId);
            return true;
        }

        return false;
    }

    /**
     * Permanently delete a user. Refuses to delete admin accounts.
     * Relies on the existing ON DELETE CASCADE foreign keys for
     * cleanup of dependent rows (houses, bookings, drivers, etc.).
     */
    public function deleteUser(int $userId, int $adminId, ?string $reason = null): bool
    {
        $check = $this->conn->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $check->execute([':id' => $userId]);
        $target = $check->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            return false;
        }

        if ($target['role'] === 'admin') {
            throw new RuntimeException('Admin accounts cannot be deleted.');
        }

        // Money records must survive: refunds, disputes and accounting all depend on them.
        $paymentCount = $this->conn->prepare("SELECT COUNT(*) FROM payments WHERE user_id = :id");
        $paymentCount->execute([':id' => $userId]);

        if ((int) $paymentCount->fetchColumn() > 0) {
            throw new RuntimeException(
                'This user has payment records that must be kept for accounting and refunds. Suspend the account instead of deleting it.'
            );
        }

        if ($target['role'] === 'driver') {
            $walletCount = $this->conn->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE driver_id = :id");
            $walletCount->execute([':id' => $userId]);

            if ((int) $walletCount->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This driver has commission wallet records that must be kept. Suspend the account instead of deleting it.'
                );
            }
        }

        if ($target['role'] === 'landlord') {
            $listingCount = $this->conn->prepare("SELECT COUNT(*) FROM houses WHERE landlord_id = :id");
            $listingCount->execute([':id' => $userId]);

            if ((int) $listingCount->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This landlord still has active listings. Remove or reassign their listings first from the Houses admin page.'
                );
            }
        }

        if ($target['role'] === 'tenant') {
            $activeBooking = $this->conn->prepare("
                SELECT COUNT(*) FROM bookings WHERE tenant_id = :id AND status IN ('pending', 'approved')
            ");
            $activeBooking->execute([':id' => $userId]);

            if ((int) $activeBooking->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This tenant has a pending or active booking. Resolve it first from the Bookings admin page.'
                );
            }
        }

        if ($target['role'] === 'driver') {
            $activeTrip = $this->conn->prepare("
                SELECT COUNT(*) FROM truck_requests
                WHERE driver_id = :id AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
            ");
            $activeTrip->execute([':id' => $userId]);

            if ((int) $activeTrip->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This driver has an active trip in progress. It must complete or be reassigned first.'
                );
            }
        }

        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = :id AND role <> 'admin'");
        $stmt->execute([':id' => $userId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        if ($reason !== null && $reason !== '') {
            $this->recordActionReason($adminId, 'delete_user', 'users', $userId, $reason);
        }

        Audit::log("Admin #{$adminId} permanently deleted user #{$userId}" . ($reason ? " ({$reason})" : ''), $adminId);

        return true;
    }

    /**
     * Listings owned by a landlord — for the admin "view this
     * landlord's listings" drill-down. Reads houses directly; does
     * not touch classes/House.php.
     */
    public function getListingsForLandlord(int $landlordId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, title, location, price, status, house_type,
                   is_hidden, is_flagged, flag_reason, verified_at, created_at
            FROM houses
            WHERE landlord_id = :landlord_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':landlord_id' => $landlordId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function recordActionReason(int $adminId, string $actionType, string $targetTable, int $targetId, string $reason): void
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

    /**
     * Decrypt and return a landlord/driver's sensitive identity
     * value for admin review. Every call is audited — viewing this
     * data is itself a sensitive action worth a permanent record,
     * separate from verifying the account.
     *
     * Returns null if the user isn't found or has no encrypted
     * identity on file for their role.
     */
    public function revealIdentity(int $userId, int $adminId): ?array
    {
        $user = $this->getUserById($userId);

        if (!$user) {
            return null;
        }

        if ($user['role'] === 'landlord') {
            if (empty($user['national_id_encrypted'])) {
                return null;
            }

            $value = Crypto::decrypt($user['national_id_encrypted']);

            Audit::log("Admin #{$adminId} viewed decrypted national ID for landlord #{$userId}", $adminId);

            return ['type' => 'National ID', 'value' => $value];
        }

        if ($user['role'] === 'driver') {
            $driver = $this->getDriverProfile($userId);

            if (!$driver) {
                return null;
            }

            $isLicense = $driver['identity_type'] === 'license';
            $encryptedValue = $isLicense
                ? ($driver['license_number'] ?? null)
                : ($driver['national_id_encrypted'] ?? null);

            if (empty($encryptedValue)) {
                return null;
            }

            $value = Crypto::decrypt($encryptedValue);
            $label = $isLicense ? 'Driving License' : 'National ID';

            Audit::log("Admin #{$adminId} viewed decrypted {$label} for driver #{$userId}", $adminId);

            return ['type' => $label, 'value' => $value];
        }

        return null;
    }
}
