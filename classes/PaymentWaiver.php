<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

/**
 * LUX EMPIRE
 * Waivers are SINGLE-PERSON VOUCHERS — never "everything free for a whole role".
 * A promotion for a role simply creates one voucher per active user.
 *
 *   tenant_booking     one free booking (any house). Attempt 1 fails to be approved
 *                      -> returned once (at least RESTORE_GRACE_DAYS more days).
 *                      Attempt 2 -> final, whatever the result.
 *   landlord_pro       Pro plan, free, until the voucher expires.
 *   driver_commission  reserved for a later step (not enabled yet).
 *
 * Statuses: active -> in_use (booking pending) -> used | back to active | expired;
 *           or revoked / expired by date. Every step is written to waiver_events.
 */
final class PaymentWaiver
{
    public const KIND_TENANT = 'tenant_booking';
    public const KIND_LANDLORD = 'landlord_pro';
    public const KIND_DRIVER = 'driver_commission';

    private const RESTORE_GRACE_DAYS = 7;

    private const UNITS = ['hours' => 'HOUR', 'days' => 'DAY', 'weeks' => 'WEEK'];
    private const UNIT_LIMITS = ['hours' => 720, 'days' => 365, 'weeks' => 52];

    private static function connect(): PDO
    {
        return (new Database())->connect();
    }

    /* ==================== helpers ==================== */

    public static function kindForRole(string $role): ?string
    {
        return match ($role) {
            'tenant' => self::KIND_TENANT,
            'landlord' => self::KIND_LANDLORD,
            'driver' => self::KIND_DRIVER,
            default => null,
        };
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_TENANT => 'Free booking',
            self::KIND_LANDLORD => 'Free Pro plan',
            self::KIND_DRIVER => 'Commission-free jobs',
            default => $kind,
        };
    }

    /** @return array{0:int,1:string}|null [amount, SQL unit] or null when invalid */
    public static function normalizeDuration(int $amount, string $unit): ?array
    {
        if (!isset(self::UNITS[$unit]) || $amount < 1 || $amount > self::UNIT_LIMITS[$unit]) {
            return null;
        }

        return [$amount, self::UNITS[$unit]];
    }

    private static function maxAttemptsFor(string $kind): int
    {
        return $kind === self::KIND_TENANT ? 2 : 1;
    }

    /** @return array{0:string,1:string,2:string} [title, message, link] */
    private static function grantedText(string $kind, string $expiresAt): array
    {
        $until = date('d M Y, H:i', strtotime($expiresAt));

        if ($kind === self::KIND_LANDLORD) {
            return [
                'Pro plan activated — free',
                'Your LUX EMPIRE Pro plan is active free of charge until ' . $until . ': up to ' . PRO_MAX_LISTINGS
                    . ' listings, each with up to ' . PRO_MAX_IMAGES_PER_LISTING . ' photos or one video.',
                BASE_URL . '/manage-houses',
            ];
        }

        return [
            'You have a free booking',
            'Book any property without paying the booking fee. Your voucher is valid until ' . $until
                . '. If a landlord declines your first booking, the voucher comes back once.',
            BASE_URL . '/tenant/search-houses',
        ];
    }

    public static function logEvent(PDO $pdo, int $waiverId, int $userId, string $event, ?int $bookingId = null, ?int $actorId = null, ?string $note = null): void
    {
        $pdo->prepare("
            INSERT INTO waiver_events (waiver_id, user_id, event, booking_id, actor_id, note)
            VALUES (:waiver_id, :user_id, :event, :booking_id, :actor_id, :note)
        ")->execute([
            ':waiver_id' => $waiverId,
            ':user_id' => $userId,
            ':event' => $event,
            ':booking_id' => $bookingId,
            ':actor_id' => $actorId,
            ':note' => $note !== null ? mb_substr($note, 0, 255) : null,
        ]);
    }

    public static function findUserByEmail(string $email): ?array
    {
        $stmt = self::connect()->prepare("SELECT id, full_name, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /* ==================== granting ==================== */

    public static function grantToUser(int $userId, string $role, string $kind, int $amount, string $unit, string $reason, int $grantedBy, ?string $batchLabel = null): array
    {
        $duration = self::normalizeDuration($amount, $unit);

        if ($duration === null) {
            throw new InvalidArgumentException('Invalid duration.');
        }

        [$n, $sqlUnit] = $duration;

        $pdo = self::connect();

        $exists = $pdo->prepare("
            SELECT id FROM payment_waivers
            WHERE user_id = :user_id AND kind = :kind
            AND status IN ('active', 'in_use') AND expires_at > NOW()
            LIMIT 1
        ");
        $exists->execute([':user_id' => $userId, ':kind' => $kind]);

        if ($exists->fetchColumn() !== false) {
            return ['success' => false, 'message' => 'This person already has a live voucher of that kind. Revoke it first, or wait until it is used or expires.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO payment_waivers
                (scope, user_id, role, kind, status, attempts_used, max_attempts, reason, granted_by, expires_at, batch_label)
            VALUES
                ('user', :user_id, :role, :kind, 'active', 0, :max_attempts, :reason, :granted_by,
                 DATE_ADD(NOW(), INTERVAL {$n} {$sqlUnit}), :batch)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
            ':kind' => $kind,
            ':max_attempts' => self::maxAttemptsFor($kind),
            ':reason' => $reason,
            ':granted_by' => $grantedBy,
            ':batch' => $batchLabel,
        ]);

        $waiverId = (int) $pdo->lastInsertId();

        $expires = (string) $pdo->query("SELECT expires_at FROM payment_waivers WHERE id = {$waiverId}")->fetchColumn();

        self::logEvent($pdo, $waiverId, $userId, 'granted', null, $grantedBy, $reason);

        return ['success' => true, 'waiver_id' => $waiverId, 'expires_at' => $expires];
    }

    public static function notifyGranted(int $userId, string $kind, string $expiresAt): void
    {
        try {
            require_once __DIR__ . '/Notification.php';

            [$title, $message, $link] = self::grantedText($kind, $expiresAt);

            (new Notification())->create($userId, 'waiver_granted', $title, $message, $link);
        } catch (Throwable $e) {
            error_log('LUX EMPIRE waiver grant notification failed: ' . $e->getMessage());
        }
    }

    /**
     * One voucher per ACTIVE user of the role (skipping anyone who already holds a
     * live one). Returns ['success' => true, 'count' => n, 'expires_at' => ...].
     */
    public static function grantToRole(string $role, int $amount, string $unit, string $reason, int $grantedBy): array
    {
        $kind = self::kindForRole($role);

        if ($kind === null || $kind === self::KIND_DRIVER) {
            throw new InvalidArgumentException('Vouchers for this role are not enabled yet.');
        }

        $duration = self::normalizeDuration($amount, $unit);

        if ($duration === null) {
            throw new InvalidArgumentException('Invalid duration.');
        }

        [$n, $sqlUnit] = $duration;

        $pdo = self::connect();
        $batch = 'bulk-' . date('YmdHis') . '-a' . $grantedBy . '-' . $role;

        $insert = $pdo->prepare("
            INSERT INTO payment_waivers
                (scope, user_id, role, kind, status, attempts_used, max_attempts, reason, granted_by, expires_at, batch_label)
            SELECT 'user', u.id, u.role, :kind, 'active', 0, :max_attempts, :reason, :granted_by,
                   DATE_ADD(NOW(), INTERVAL {$n} {$sqlUnit}), :batch
            FROM users u
            WHERE u.role = :role AND u.status = 'active'
            AND NOT EXISTS (
                SELECT 1 FROM payment_waivers w
                WHERE w.user_id = u.id AND w.kind = :kind2
                AND w.status IN ('active', 'in_use') AND w.expires_at > NOW()
            )
        ");
        $insert->execute([
            ':kind' => $kind,
            ':max_attempts' => self::maxAttemptsFor($kind),
            ':reason' => $reason,
            ':granted_by' => $grantedBy,
            ':batch' => $batch,
            ':role' => $role,
            ':kind2' => $kind,
        ]);

        $count = $insert->rowCount();

        if ($count === 0) {
            return ['success' => true, 'count' => 0, 'expires_at' => null];
        }

        $expiresStmt = $pdo->prepare("SELECT expires_at FROM payment_waivers WHERE batch_label = :batch LIMIT 1");
        $expiresStmt->execute([':batch' => $batch]);
        $expires = (string) $expiresStmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO waiver_events (waiver_id, user_id, event, actor_id, note)
            SELECT id, user_id, 'granted', :actor, :note FROM payment_waivers WHERE batch_label = :batch
        ")->execute([':actor' => $grantedBy, ':note' => mb_substr($reason, 0, 255), ':batch' => $batch]);

        [$title, $message, $link] = self::grantedText($kind, $expires);

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            SELECT user_id, 'waiver_granted', :title, :message, :link FROM payment_waivers WHERE batch_label = :batch
        ")->execute([':title' => $title, ':message' => $message, ':link' => $link, ':batch' => $batch]);

        return ['success' => true, 'count' => $count, 'expires_at' => $expires];
    }

    public static function revoke(int $waiverId, int $adminId, string $reason): array
    {
        $pdo = self::connect();

        $stmt = $pdo->prepare("SELECT id, user_id, status FROM payment_waivers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $waiverId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'Voucher not found.', 'code' => 404];
        }

        if ($row['status'] === 'in_use') {
            return ['success' => false, 'message' => 'This voucher is attached to a pending booking. It can be revoked once that booking is answered.', 'code' => 409];
        }

        $update = $pdo->prepare("UPDATE payment_waivers SET status = 'revoked', revoked_at = NOW() WHERE id = :id AND status = 'active'");
        $update->execute([':id' => $waiverId]);

        if ($update->rowCount() === 0) {
            return ['success' => false, 'message' => 'Only an active voucher can be revoked.', 'code' => 409];
        }

        self::logEvent($pdo, $waiverId, (int) $row['user_id'], 'revoked', null, $adminId, $reason);

        return ['success' => true];
    }

    /* ==================== landlord (time-based Pro) ==================== */

    public static function landlordProUntil(int $userId): ?string
    {
        $stmt = self::connect()->prepare("
            SELECT expires_at FROM payment_waivers
            WHERE user_id = :user_id AND kind = 'landlord_pro' AND status = 'active' AND expires_at > NOW()
            ORDER BY expires_at DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : null;
    }

    /**
     * Kept for PlanLimits. Only the landlord Pro voucher works silently in the
     * background; tenant vouchers are ALWAYS redeemed explicitly.
     */
    public static function isWaived(int $userId, string $role): bool
    {
        if ($role !== 'landlord') {
            return false;
        }

        return self::landlordProUntil($userId) !== null;
    }

    /* ==================== tenant (one free booking) ==================== */

    public static function activeTenantVoucher(int $userId): ?array
    {
        $stmt = self::connect()->prepare("
            SELECT id, expires_at, attempts_used, max_attempts
            FROM payment_waivers
            WHERE user_id = :user_id AND kind = 'tenant_booking' AND status = 'active' AND expires_at > NOW()
            ORDER BY expires_at ASC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Claims the tenant's voucher for ONE booking. Must run inside the caller's
     * transaction (pass the same PDO), so the claim and the booking are atomic.
     */
    public static function claimForBooking(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, attempts_used, max_attempts
            FROM payment_waivers
            WHERE user_id = :user_id AND kind = 'tenant_booking' AND status = 'active' AND expires_at > NOW()
            ORDER BY expires_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':user_id' => $userId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            return null;
        }

        $update = $pdo->prepare("
            UPDATE payment_waivers
            SET status = 'in_use', attempts_used = attempts_used + 1
            WHERE id = :id AND status = 'active'
        ");
        $update->execute([':id' => $voucher['id']]);

        if ($update->rowCount() === 0) {
            return null;
        }

        $voucher['attempts_used'] = (int) $voucher['attempts_used'] + 1;

        return $voucher;
    }

    /** The landlord approved the booking: the voucher is permanently used. */
    public static function settleOnApproval(PDO $pdo, int $bookingId): void
    {
        $stmt = $pdo->prepare("
            SELECT w.id, w.user_id
            FROM bookings b
            JOIN payment_waivers w ON w.id = b.waiver_id
            WHERE b.id = :booking_id AND w.status = 'in_use'
            FOR UPDATE
        ");
        $stmt->execute([':booking_id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        $pdo->prepare("UPDATE payment_waivers SET status = 'used' WHERE id = :id AND status = 'in_use'")
            ->execute([':id' => $row['id']]);

        self::logEvent($pdo, (int) $row['id'], (int) $row['user_id'], 'used', $bookingId, null, 'booking approved');
    }

    /**
     * The booking ended WITHOUT approval (declined / cancelled / expired).
     * First attempt: the voucher comes back, with at least RESTORE_GRACE_DAYS more days.
     * Second attempt: it is finished. Returns 'restored', 'exhausted', or null when
     * the booking did not use a voucher.
     */
    public static function settleOnEnd(PDO $pdo, int $bookingId): ?string
    {
        $stmt = $pdo->prepare("
            SELECT w.id, w.user_id, w.attempts_used, w.max_attempts
            FROM bookings b
            JOIN payment_waivers w ON w.id = b.waiver_id
            WHERE b.id = :booking_id AND w.status = 'in_use'
            FOR UPDATE
        ");
        $stmt->execute([':booking_id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if ((int) $row['attempts_used'] < (int) $row['max_attempts']) {

            $pdo->prepare("
                UPDATE payment_waivers
                SET status = 'active',
                    expires_at = GREATEST(expires_at, DATE_ADD(NOW(), INTERVAL " . self::RESTORE_GRACE_DAYS . " DAY))
                WHERE id = :id AND status = 'in_use'
            ")->execute([':id' => $row['id']]);

            self::logEvent($pdo, (int) $row['id'], (int) $row['user_id'], 'restored', $bookingId, null, 'booking ended without approval — voucher returned');

            return 'restored';
        }

        $pdo->prepare("UPDATE payment_waivers SET status = 'expired' WHERE id = :id AND status = 'in_use'")
            ->execute([':id' => $row['id']]);

        self::logEvent($pdo, (int) $row['id'], (int) $row['user_id'], 'exhausted', $bookingId, null, 'second attempt not approved — voucher used up');

        return 'exhausted';
    }

    /** Post-commit notification telling the tenant what happened to their voucher. */
    public static function notifyOutcome(int $tenantId, ?string $outcome, string $houseTitle): void
    {
        if ($outcome === null) {
            return;
        }

        try {
            require_once __DIR__ . '/Notification.php';

            $notification = new Notification();
            $link = BASE_URL . '/tenant/search-houses';

            if ($outcome === 'restored') {
                $notification->create(
                    $tenantId,
                    'waiver_restored',
                    'Free Booking Voucher Returned',
                    'Your booking for "' . $houseTitle . '" did not go ahead, so your free booking voucher is back. You can use it on another property.',
                    $link
                );
                return;
            }

            $notification->create(
                $tenantId,
                'waiver_exhausted',
                'Free Booking Voucher Used Up',
                'Your booking for "' . $houseTitle . '" did not go ahead. This was your voucher\'s second attempt, so it has now been used up.',
                $link
            );

        } catch (Throwable $e) {
            error_log('LUX EMPIRE waiver outcome notification failed: ' . $e->getMessage());
        }
    }

    /** Marks vouchers past their expiry date as expired. Run from the reconcile timer. */
    public static function expireOverdue(): int
    {
        $pdo = self::connect();

        $rows = $pdo->query("
            SELECT id, user_id FROM payment_waivers
            WHERE status = 'active' AND expires_at <= NOW()
            LIMIT 500
        ")->fetchAll(PDO::FETCH_ASSOC);

        $update = $pdo->prepare("
            UPDATE payment_waivers SET status = 'expired'
            WHERE id = :id AND status = 'active' AND expires_at <= NOW()
        ");

        $count = 0;

        foreach ($rows as $row) {
            $update->execute([':id' => $row['id']]);

            if ($update->rowCount() > 0) {
                self::logEvent($pdo, (int) $row['id'], (int) $row['user_id'], 'expired', null, null, 'expiry date reached');
                $count++;
            }
        }

        return $count;
    }

    /* ==================== admin listing ==================== */

    /** @return array{waivers: array, total: int} */
    public static function listForAdmin(?string $status, int $limit = 30, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $pdo = self::connect();

        $where = "WHERE w.scope = 'user'";
        $params = [];

        if ($status === 'active') {
            $where .= " AND w.status = 'active' AND w.expires_at > NOW()";
        } elseif ($status === 'expired') {
            $where .= " AND (w.status = 'expired' OR (w.status = 'active' AND w.expires_at <= NOW()))";
        } elseif (in_array($status, ['in_use', 'used', 'revoked'], true)) {
            $where .= " AND w.status = :status";
            $params[':status'] = $status;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_waivers w {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT w.*, u.full_name, u.email, a.full_name AS granted_by_name
            FROM payment_waivers w
            JOIN users u ON u.id = w.user_id
            LEFT JOIN users a ON a.id = w.granted_by
            {$where}
            ORDER BY w.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $waivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($waivers as &$waiver) {
            $waiver['effective_status'] = ($waiver['status'] === 'active' && strtotime((string) $waiver['expires_at']) <= time())
                ? 'expired'
                : $waiver['status'];
        }
        unset($waiver);

        return ['waivers' => $waivers, 'total' => $total];
    }

    public static function getHistory(int $waiverId): array
    {
        $stmt = self::connect()->prepare("
            SELECT e.id, e.event, e.booking_id, e.note, e.created_at, a.full_name AS actor_name
            FROM waiver_events e
            LEFT JOIN users a ON a.id = e.actor_id
            WHERE e.waiver_id = :waiver_id
            ORDER BY e.id ASC
        ");
        $stmt->execute([':waiver_id' => $waiverId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}