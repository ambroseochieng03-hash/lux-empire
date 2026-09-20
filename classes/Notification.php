<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class Notification
{
    private PDO $conn;

    /*
     * The unread counter is only a short-lived cache. Every write in this class
     * simply deletes it, and it also expires by itself after a few seconds. That
     * way it can NEVER drift, even if something inserts notifications directly
     * into the table without going through this class.
     */
    private const UNREAD_CACHE_SECONDS = 15;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    private function unreadKey(int $userId): string
    {
        return "notif:unread2:{$userId}";
    }

    private function forgetUnreadCache(int $userId): void
    {
        try {
            require_once __DIR__ . '/../config/RedisConnection.php';
            RedisConnection::get()->del($this->unreadKey($userId));
        } catch (Throwable $e) {
            // Not fatal — the cache expires by itself within seconds.
        }
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

        $id = (int) $this->conn->lastInsertId();

        $this->forgetUnreadCache($userId);

        return $id;
    }

    /**
     * One notification per conversation while it is unread: further messages
     * update that notification (newest preview, moved to the top) instead of
     * creating a new one for every single message.
     */
    public function notifyNewMessage(int $userId, int $conversationId, string $fromName, string $preview, string $link): void
    {
        $preview = mb_strlen($preview) > 90 ? mb_substr($preview, 0, 90) . '…' : $preview;
        $title = 'New message from ' . $fromName;

        $existing = $this->conn->prepare("
            SELECT id FROM notifications
            WHERE user_id = :user_id AND type = 'new_message' AND is_read = 0 AND link = :link
            LIMIT 1
        ");
        $existing->execute([':user_id' => $userId, ':link' => $link]);
        $existingId = $existing->fetchColumn();

        if ($existingId !== false) {
            $this->conn->prepare("
                UPDATE notifications SET title = :title, message = :message, created_at = NOW()
                WHERE id = :id
            ")->execute([':title' => $title, ':message' => $preview, ':id' => (int) $existingId]);

            return;
        }

        $this->create($userId, 'new_message', $title, $preview, $link);
    }

    /**
     * The person opened the conversation — its "new message" notification is
     * read. Links always end in ?c=<conversation id>.
     */
    public function markConversationRead(int $userId, int $conversationId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = :user_id AND type = 'new_message' AND is_read = 0 AND link LIKE :pattern
        ");
        $stmt->execute([':user_id' => $userId, ':pattern' => '%?c=' . $conversationId]);

        if ($stmt->rowCount() > 0) {
            $this->forgetUnreadCache($userId);
        }
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
        $cacheKey = $this->unreadKey($userId);
        $redis = null;

        try {
            require_once __DIR__ . '/../config/RedisConnection.php';
            $redis = RedisConnection::get();
            $cached = $redis->get($cacheKey);

            if ($cached !== false) {
                return max(0, (int) $cached);
            }
        } catch (Throwable $e) {
            $redis = null; // Redis unreachable — count straight from the database.
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        $count = (int) $stmt->fetchColumn();

        if ($redis !== null) {
            try {
                $redis->setex($cacheKey, self::UNREAD_CACHE_SECONDS, (string) $count);
            } catch (Throwable $e) {
                // Not fatal.
            }
        }

        return $count;
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = :id AND user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            $this->forgetUnreadCache($userId);
        }

        return true;
    }

    public function markAllRead(int $userId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = :user_id AND is_read = 0
        ");
        $result = $stmt->execute([':user_id' => $userId]);

        $this->forgetUnreadCache($userId);

        return $result;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        $deleted = $stmt->rowCount() > 0;

        if ($deleted) {
            $this->forgetUnreadCache($userId);
        }

        return $deleted;
    }
}