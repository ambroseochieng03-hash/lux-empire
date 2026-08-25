<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class Notification
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (:user_id, :type, :title, :message, :link)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':link' => $link
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function getForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications
            WHERE user_id = :user_id
            ORDER BY id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    public function markAllRead(int $userId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = :user_id AND is_read = 0
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");

        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }
}
