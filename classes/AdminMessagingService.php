<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';
require_once __DIR__ . '/EmailJobPublisher.php';
require_once __DIR__ . '/Notification.php';

/**
 * LUX EMPIRE
 * Admin-to-user messaging: broadcast to a role (or everyone) and
 * direct single-user messages. Every message becomes an email job
 * (via APP_EMAILS) plus a row in notifications, same pattern as
 * every other trigger in this codebase.
 */
final class AdminMessagingService
{
    private PDO $conn;
    private Notification $notification;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
        $this->notification = new Notification();
    }

    /**
     * $targetRole is one of: 'all', 'tenant', 'landlord', 'driver', 'admin'.
     * Returns the number of recipients messaged.
     */
    public function broadcast(string $targetRole, string $subject, string $body, int $adminId): int
    {
        $allowedRoles = ['all', 'tenant', 'landlord', 'driver', 'admin'];

        if (!in_array($targetRole, $allowedRoles, true)) {
            throw new InvalidArgumentException('Invalid target role.');
        }

        if ($targetRole === 'all') {
            $stmt = $this->conn->query("SELECT id, full_name, email FROM users");
        } else {
            $stmt = $this->conn->prepare("SELECT id, full_name, email FROM users WHERE role = :role");
            $stmt->execute([':role' => $targetRole]);
        }

        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($recipients as $recipient) {
            EmailJobPublisher::publish('email.admin_broadcast', [
                'email' => $recipient['email'],
                'name' => $recipient['full_name'],
                'subject' => $subject,
                'body' => $body,
            ]);

            $this->notification->create(
                (int) $recipient['id'],
                'admin_broadcast',
                $subject,
                $body,
                null
            );
        }

        Audit::log("Admin #{$adminId} broadcast a message to role '{$targetRole}' (" . count($recipients) . " recipients)", $adminId);

        return count($recipients);
    }

    public function sendDirectMessage(int $userId, string $subject, string $body, int $adminId): bool
    {
        $stmt = $this->conn->prepare("SELECT id, full_name, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            return false;
        }

        EmailJobPublisher::publish('email.admin_direct_message', [
            'email' => $recipient['email'],
            'name' => $recipient['full_name'],
            'subject' => $subject,
            'body' => $body,
        ]);

        $this->notification->create(
            (int) $recipient['id'],
            'admin_direct_message',
            $subject,
            $body,
            null
        );

        Audit::log("Admin #{$adminId} sent a direct message to user #{$userId}", $adminId);

        return true;
    }
}
