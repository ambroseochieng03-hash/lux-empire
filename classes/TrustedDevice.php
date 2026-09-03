<?php

/**
 * LUX EMPIRE
 * Trusted Devices
 *
 * A "trusted device" is a long-lived, signed, httponly cookie whose
 * value we only ever store HASHED here. Presence of a matching,
 * unexpired row for the logging-in user means login (email/password
 * path only — see login_handler.php) can skip OTP.
 *
 * Trust policy (chosen deliberately, not arbitrary):
 *   - Sliding 30-day window: every successful use extends trust by
 *     another 30 days from that moment, so an actively-used device
 *     is effectively never re-challenged — this is what makes the
 *     "stay logged in like Facebook" experience work.
 *   - 180-day ABSOLUTE cap from first trust, regardless of activity.
 *     Even a daily user re-verifies (password + OTP) roughly twice a
 *     year — hygiene against a long-forgotten trusted session on a
 *     shared/old/stolen device staying valid forever.
 *   - Token ROTATES on every successful use (new random value issued,
 *     old one invalidated) rather than just extending the same
 *     token's expiry. A stolen cookie has a shrinking window of
 *     usefulness instead of staying valid for a full 30 days from
 *     whenever it was stolen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class TrustedDevice
{
    private const COOKIE_NAME = 'lux_trusted_device';
    private const SLIDING_WINDOW_DAYS = 30;
    private const ABSOLUTE_CAP_DAYS = 180;

    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Check whether the current request's device cookie is a valid
     * trusted device for this user. On success, ROTATES the token
     * (new cookie issued, old value invalidated) and slides the
     * expiry forward — capped at 180 days from the device's original
     * trust date, never beyond it.
     */
    public function isTrusted(int $userId): bool
    {
        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? '';

        if ($rawToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->conn->prepare("
            SELECT id, created_at, expires_at
            FROM trusted_devices
            WHERE user_id = :user_id
            AND token_hash = :token_hash
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $now = time();
        $createdAt = strtotime($row['created_at']);
        $absoluteCapAt = $createdAt + (self::ABSOLUTE_CAP_DAYS * 86400);

        // Past the sliding expiry, OR past the absolute cap — either
        // way this device must re-verify from scratch.
        if (strtotime($row['expires_at']) < $now || $absoluteCapAt < $now) {

            $delete = $this->conn->prepare("DELETE FROM trusted_devices WHERE id = :id");
            $delete->execute([':id' => $row['id']]);

            $this->clearCookie();

            return false;
        }

        // New expiry: 30 days from now, but never past the absolute
        // cap from this device's original trust date.
        $slidingExpiry = $now + (self::SLIDING_WINDOW_DAYS * 86400);
        $newExpiresAt = min($slidingExpiry, $absoluteCapAt);

        $newRawToken = bin2hex(random_bytes(32));
        $newTokenHash = hash('sha256', $newRawToken);

        $update = $this->conn->prepare("
            UPDATE trusted_devices
            SET token_hash = :token_hash,
                expires_at = :expires_at,
                last_used_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            ':token_hash' => $newTokenHash,
            ':expires_at' => date('Y-m-d H:i:s', $newExpiresAt),
            ':id' => $row['id']
        ]);

        $this->setCookie($newRawToken, $newExpiresAt);

        return true;
    }

    /**
     * Issue a brand-new trusted-device token (new row, created_at =
     * now, so a fresh 180-day absolute cap starts here). Called after
     * a successful OTP verification, a completed Google + password
     * registration, or an explicit new-device login.
     */
    public function trust(int $userId): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = time() + (self::SLIDING_WINDOW_DAYS * 86400);

        $stmt = $this->conn->prepare("
            INSERT INTO trusted_devices (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => date('Y-m-d H:i:s', $expiresAt)
        ]);

        $this->setCookie($rawToken, $expiresAt);
    }

    /**
     * Revoke the current device's trust (e.g. on explicit logout, if
     * that's ever wired to also forget the device — currently only
     * called internally when a stale/expired token is found).
     */
    public function revokeCurrent(int $userId): void
    {
        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? '';

        if ($rawToken !== '') {

            $tokenHash = hash('sha256', $rawToken);

            $delete = $this->conn->prepare("
                DELETE FROM trusted_devices
                WHERE user_id = :user_id AND token_hash = :token_hash
            ");

            $delete->execute([
                ':user_id' => $userId,
                ':token_hash' => $tokenHash
            ]);
        }

        $this->clearCookie();
    }

    private function setCookie(string $rawToken, int $expiresAtTimestamp): void
    {
        setcookie(self::COOKIE_NAME, $rawToken, [
            'expires' => $expiresAtTimestamp,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Keep the in-request superglobal in sync in case something
        // else reads $_COOKIE later in the same request.
        $_COOKIE[self::COOKIE_NAME] = $rawToken;
    }

    private function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || str_contains($_SERVER['HTTP_HOST'] ?? '', 'ngrok');
    }
}