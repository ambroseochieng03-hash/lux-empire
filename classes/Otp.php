<?php

/**
 * LUX EMPIRE
 * One-Time Password (email) handling
 *
 * Used for both:
 *  - registration email verification (purpose='registration')
 *  - new-device login verification (purpose='new_device_login')
 *
 * Codes are 6 digits, hashed at rest (never store the raw code),
 * expire after 5 minutes, and are single-use (consumed_at set on
 * successful verification). Resend/attempt throttling is the
 * caller's job (via RateLimiter), not this class's.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Otp
{
    private const EXPIRY_SECONDS = 300; // 5 minutes
    private const MAX_ATTEMPTS = 5;

    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Generate and store a new OTP for this user/purpose, invalidating
     * any previous unconsumed code for the same purpose.
     *
     * Returns the raw 6-digit code — the ONLY time it exists in plain
     * form. Caller is responsible for emailing it immediately.
     */
    public function generate(int $userId, string $purpose): string
    {
        // Invalidate any still-pending code for this user/purpose so
        // only the most recently sent one is ever valid.
        $invalidate = $this->conn->prepare("
            UPDATE login_otps
            SET consumed_at = NOW()
            WHERE user_id = :user_id
            AND purpose = :purpose
            AND consumed_at IS NULL
        ");

        $invalidate->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $insert = $this->conn->prepare("
            INSERT INTO login_otps (user_id, purpose, code_hash, expires_at)
            VALUES (:user_id, :purpose, :code_hash, :expires_at)
        ");

        $insert->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose,
            ':code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ':expires_at' => date('Y-m-d H:i:s', time() + self::EXPIRY_SECONDS)
        ]);

        return $code;
    }

    /**
     * Verify a submitted code. Returns true and consumes the code on
     * success. Returns false on wrong code, expiry, or too many
     * attempts — all without revealing which, to avoid helping
     * someone brute-force it.
     */
    public function verify(int $userId, string $purpose, string $submittedCode): bool
    {
        $stmt = $this->conn->prepare("
            SELECT id, code_hash, expires_at, attempts
            FROM login_otps
            WHERE user_id = :user_id
            AND purpose = :purpose
            AND consumed_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (strtotime($row['expires_at']) < time()) {
            return false;
        }

        if (!password_verify($submittedCode, $row['code_hash'])) {

            $bump = $this->conn->prepare("
                UPDATE login_otps SET attempts = attempts + 1 WHERE id = :id
            ");
            $bump->execute([':id' => $row['id']]);

            return false;
        }

        $consume = $this->conn->prepare("
            UPDATE login_otps SET consumed_at = NOW() WHERE id = :id
        ");
        $consume->execute([':id' => $row['id']]);

        return true;
    }

    /**
     * Seconds remaining until a resend is reasonable — the caller
     * (via RateLimiter) enforces the actual cooldown; this just
     * reports how long the *current* code has left, for UI display.
     */
    public function secondsUntilExpiry(int $userId, string $purpose): int
    {
        $stmt = $this->conn->prepare("
            SELECT expires_at
            FROM login_otps
            WHERE user_id = :user_id
            AND purpose = :purpose
            AND consumed_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return 0;
        }

        return max(0, strtotime($row['expires_at']) - time());
    }
}
