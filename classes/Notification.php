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

        $id = (int) $this->conn->lastInsertId();

        // Keep the unread-count cache in step with the real count
        // instead of letting every poll hit COUNT(*) on notifications.
        try {
            require_once __DIR__ . '/../config/RedisConnection.php';
            RedisConnection::get()->incr("notif:unread:{$userId}");
        } catch (Throwable $e) {
            // If this fails, getUnreadCount() below falls back to a
            // real COUNT(*) whenever the cache key is missing/stale —
            // never blocks notification creation.
        }

        return $id;
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
        require_once __DIR__ . '/../config/RedisConnection.php';

        $cacheKey = "notif:unread:{$userId}";

        try {
            $redis = RedisConnection::get();
            $cached = $redis->get($cacheKey);

            if ($cached !== false) {
                return (int) $cached;
            }
        } catch (Throwable $e) {
            // Redis unreachable — fall through to the real count below.
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        $count = (int) $stmt->fetchColumn();

        // Warm the cache so the next poll doesn't hit the DB again.
        // No TTL — this counter is kept exactly in sync by
        // create()/markRead()/markAllRead()/delete() below, so it
        // never needs to expire on its own.
        try {
            RedisConnection::get()->set($cacheKey, $count);
        } catch (Throwable $e) {
            // Not fatal — just means the next call recomputes too.
        }

        return $count;
    }

    public function markRead(int $id, int $userId): bool
    {
        // The added "AND is_read = 0" means rowCount() reliably tells
        // us whether this call actually flipped an unread notification
        // to read — that's how we know whether to decrement the cache.
        $stmt = $this->conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = :id AND user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            try {
                require_once __DIR__ . '/../config/RedisConnection.php';
                $redis = RedisConnection::get();
                $newValue = $redis->decr("notif:unread:{$userId}");
                if ($newValue < 0) {
                    // Cache had drifted negative somehow — clamp it
                    // back to a sane floor rather than let it compound.
                    $redis->set("notif:unread:{$userId}", 0);
                }
            } catch (Throwable $e) {
                // Not fatal — getUnreadCount() recovers from the DB
                // the next time the cache key is missing.
            }
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

        try {
            require_once __DIR__ . '/../config/RedisConnection.php';
            RedisConnection::get()->set("notif:unread:{$userId}", 0);
        } catch (Throwable $e) {
            // Not fatal — getUnreadCount() recovers from the DB the
            // next time the cache key is missing.
        }

        return $result;
    }

    public function delete(int $id, int $userId): bool
    {
        // Need to know whether this notification was unread BEFORE
        // deleting it, so the cached counter can be decremented
        // correctly — once it's deleted there's no way to check.
        $check = $this->conn->prepare("
            SELECT is_read FROM notifications
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ");
        $check->execute([':id' => $id, ':user_id' => $userId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $wasUnread = ($row !== false) && ((int) $row['is_read'] === 0);

        $stmt = $this->conn->prepare("
            DELETE FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0 && $wasUnread) {
            try {
                require_once __DIR__ . '/../config/RedisConnection.php';
                $redis = RedisConnection::get();
                $newValue = $redis->decr("notif:unread:{$userId}");
                if ($newValue < 0) {
                    $redis->set("notif:unread:{$userId}", 0);
                }
            } catch (Throwable $e) {
                // Not fatal — recovers on next cache miss.
            }
        }

        return $stmt->rowCount() > 0;
    }
}
