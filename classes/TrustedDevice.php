<?php

/**
 * LUX EMPIRE
 * Trusted Devices
 *
 * A "trusted device" is a long-lived, signed, httponly cookie whose
 * value we only ever store HASHED in trusted_devices. Presence of a
 * matching, unexpired row for the logging-in user means the login
 * (email/password path only — see login_handler.php) can skip OTP.
 *
 * The raw token lives ONLY in the cookie — losing the cookie means
 * losing trust for that browser, which is correct (re-verify via
 * OTP, then a new trusted-device row/cookie is issued).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class TrustedDevice
{
    private const COOKIE_NAME = 'lux_trusted_device';
    private const LIFETIME_DAYS = 90;

    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Check whether the current request's device cookie is a valid,
     * unexpired trusted device for this user.
     */
    public function isTrusted(int $userId): bool
    {
        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? '';

        if ($rawToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->conn->prepare("
            SELECT id, expires_at
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

        if (strtotime($row['expires_at']) < time()) {
            return false;
        }

        $touch = $this->conn->prepare("
            UPDATE trusted_devices SET last_used_at = NOW() WHERE id = :id
        ");
        $touch->execute([':id' => $row['id']]);

        return true;
    }

    /**
     * Issue a new trusted-device token: stores the hash, sets the
     * cookie. Called after a successful OTP verification or a
     * completed Google + password registration.
     */
    public function trust(int $userId): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = date('Y-m-d H:i:s', time() + (self::LIFETIME_DAYS * 86400));

        $stmt = $this->conn->prepare("
            INSERT INTO trusted_devices (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || str_contains($_SERVER['HTTP_HOST'] ?? '', 'ngrok');

        setcookie(self::COOKIE_NAME, $rawToken, [
            'expires' => time() + (self::LIFETIME_DAYS * 86400),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
