<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/GroqClient.php';

class Chat
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Get an existing conversation between a tenant and a landlord/driver,
     * or create one if it doesn't exist yet.
     */
    public function getOrCreateConversation(
        int $tenantId,
        int $otherUserId,
        string $otherRole,
        ?int $houseId = null,
        ?int $truckRequestId = null
    ): array {

        $stmt = $this->conn->prepare("
            SELECT * FROM conversations
            WHERE tenant_id = :tenant_id AND other_user_id = :other_user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':other_user_id' => $otherUserId
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO conversations
                (tenant_id, other_user_id, other_role, house_id, truck_request_id)
            VALUES
                (:tenant_id, :other_user_id, :other_role, :house_id, :truck_request_id)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':other_user_id' => $otherUserId,
            ':other_role' => $otherRole,
            ':house_id' => $houseId,
            ':truck_request_id' => $truckRequestId
        ]);

        $id = (int) $this->conn->lastInsertId();

        return $this->getConversationById($id);
    }

    public function getConversationById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM conversations WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Confirm this user is actually a participant of the conversation.
     * Always call this before letting a user read/send in a conversation.
     */
    public function userBelongsToConversation(int $conversationId, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT id FROM conversations
            WHERE id = :id AND (tenant_id = :uid OR other_user_id = :uid2)
            LIMIT 1
        ");
        $stmt->execute([':id' => $conversationId, ':uid' => $userId, ':uid2' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * All conversations for a given user (tenant, landlord, or driver),
     * newest activity first, with unread counts and the other party's name.
     */
    public function getConversationsForUser(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                c.*,
                CASE WHEN c.tenant_id = :uid THEN c.other_user_id ELSE c.tenant_id END AS with_user_id,
                u.full_name AS with_name,
                u.profile_image AS with_image,
                u.last_seen_at AS with_last_seen,
                (
                    SELECT COUNT(*) FROM messages m
                    WHERE m.conversation_id = c.id
                    AND (m.sender_id IS NULL OR m.sender_id != :uid2)
                    AND m.created_at > COALESCE(
                        CASE WHEN c.tenant_id = :uid3 THEN c.tenant_last_read_at ELSE c.other_last_read_at END,
                        '1970-01-01'
                    )
                ) AS unread_count,
                (
                    SELECT message FROM messages m
                    WHERE m.conversation_id = c.id
                    ORDER BY m.id DESC LIMIT 1
                ) AS last_message
            FROM conversations c
            JOIN users u ON u.id = CASE WHEN c.tenant_id = :uid4 THEN c.other_user_id ELSE c.tenant_id END
            WHERE c.tenant_id = :uid5 OR c.other_user_id = :uid6
            ORDER BY c.last_message_at IS NULL, c.last_message_at DESC
        ");

        $stmt->execute([
            ':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId,
            ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sendMessage(int $conversationId, int $senderId, string $message, string $senderType = 'user'): array
    {
        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO messages (conversation_id, sender_id, sender_type, message)
            VALUES (:conversation_id, :sender_id, :sender_type, :message)
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderType === 'ai' ? null : $senderId,
            ':sender_type' => $senderType,
            ':message' => $message
        ]);

        $messageId = (int) $this->conn->lastInsertId();

        // Reset the AI "one notice per gap" flag whenever a human speaks.
        $update = $this->conn->prepare("
            UPDATE conversations
            SET last_message_at = NOW(), ai_notice_sent = :reset
            WHERE id = :id
        ");
        $update->execute([
            ':reset' => $senderType === 'ai' ? 1 : 0,
            ':id' => $conversationId
        ]);

        // Clear the sender's typing flag immediately after sending.
        if ($senderType === 'user') {
            $clear = $this->conn->prepare("DELETE FROM chat_typing WHERE conversation_id = :c AND user_id = :u");
            $clear->execute([':c' => $conversationId, ':u' => $senderId]);
        }

        return $this->getMessageById($messageId);
    }

    public function getMessageById(int $id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM messages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMessages(int $conversationId, int $afterId = 0): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM messages
            WHERE conversation_id = :id AND id > :after
            ORDER BY id ASC
        ");
        $stmt->execute([':id' => $conversationId, ':after' => $afterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markRead(int $conversationId, int $userId): void
    {
        $conversation = $this->getConversationById($conversationId);
        if (!$conversation) return;

        $column = ((int) $conversation['tenant_id'] === $userId) ? 'tenant_last_read_at' : 'other_last_read_at';

        $stmt = $this->conn->prepare("UPDATE conversations SET {$column} = NOW() WHERE id = :id");
        $stmt->execute([':id' => $conversationId]);
    }

    public function setTyping(int $conversationId, int $userId): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO chat_typing (conversation_id, user_id, updated_at)
            VALUES (:c, :u, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->execute([':c' => $conversationId, ':u' => $userId]);
    }

    /**
     * Is the OTHER participant (not $userId) currently typing?
     */
    public function isOtherTyping(int $conversationId, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM chat_typing
            WHERE conversation_id = :c AND user_id != :u
            AND updated_at > (NOW() - INTERVAL 4 SECOND)
            LIMIT 1
        ");
        $stmt->execute([':c' => $conversationId, ':u' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function touchLastSeen(int $userId): void
    {
        $stmt = $this->conn->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    public function getUserPresence(int $userId): array
    {
        $stmt = $this->conn->prepare("SELECT last_seen_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $lastSeen = $row['last_seen_at'] ?? null;
        $online = $lastSeen && (strtotime($lastSeen) > time() - 20);

        return ['online' => $online, 'last_seen_at' => $lastSeen];
    }

    /**
     * Called on every poll. If the last message is from a human and it's
     * been silent for CHAT_AI_SILENCE_MINUTES with no reply, Groq sends
     * exactly one contextual notice, then goes quiet until a human replies.
     */
    public function maybeTriggerAi(int $conversationId): void
    {
        $conversation = $this->getConversationById($conversationId);
        if (!$conversation || (int) $conversation['ai_notice_sent'] === 1) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM messages
            WHERE conversation_id = :id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':id' => $conversationId]);
        $lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastMessage || $lastMessage['sender_type'] !== 'user') {
            return;
        }

        $silentSince = strtotime($lastMessage['created_at']);
        $minutesSilent = (time() - $silentSince) / 60;

        if ($minutesSilent < CHAT_AI_SILENCE_MINUTES) {
            return;
        }

        $isTenantWaiting = ((int) $lastMessage['sender_id']) !== (int) $conversation['tenant_id']
            ? false : true; // last message was from the tenant -> tenant is waiting on the other party

        $waitingOnRole = $isTenantWaiting ? $conversation['other_role'] : 'tenant';

        $recentMessages = $this->getMessages($conversationId, max(0, $lastMessage['id'] - 10));

        $groq = new GroqClient();
        $reply = $groq->generateSilenceNotice($recentMessages, $waitingOnRole);

        if ($reply !== null) {
            $this->sendMessage($conversationId, 0, $reply, 'ai');
        }
    }
}
