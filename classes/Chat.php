<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/GroqClient.php';
require_once __DIR__ . '/ContactMasker.php';
require_once __DIR__ . '/../config/security/RedisThrottle.php';

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
        ?int $truckRequestId = null,
        ?int $bookingId = null
    ): array {

        // Scoped to the SPECIFIC house/trip/booking, not just the two people —
        // a new truck trip with the same driver, or a NEW booking (even on
        // the same house, with the same landlord) must never reopen an old
        // conversation. booking_id is what makes a rebooking always get a
        // fresh thread instead of resurrecting a rejected one.
        $stmt = $this->conn->prepare("
            SELECT * FROM conversations
            WHERE tenant_id = :tenant_id AND other_user_id = :other_user_id
            AND house_id <=> :house_id AND truck_request_id <=> :truck_request_id
            AND booking_id <=> :booking_id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':other_user_id' => $otherUserId,
            ':house_id' => $houseId,
            ':truck_request_id' => $truckRequestId,
            ':booking_id' => $bookingId,
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO conversations
                (tenant_id, other_user_id, other_role, house_id, truck_request_id, booking_id)
            VALUES
                (:tenant_id, :other_user_id, :other_role, :house_id, :truck_request_id, :booking_id)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':other_user_id' => $otherUserId,
            ':other_role' => $otherRole,
            ':house_id' => $houseId,
            ':truck_request_id' => $truckRequestId,
            ':booking_id' => $bookingId
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

    public function getUserName(int $userId): string
    {
        $stmt = $this->conn->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);

        return (string) ($stmt->fetchColumn() ?: 'Someone');
    }

    /** Which "cleared up to message id" column belongs to this user in this conversation. */
    private function clearedColumn(array $conversation, int $userId): string
    {
        return ((int) $conversation['tenant_id'] === $userId)
            ? 'tenant_cleared_upto_id'
            : 'other_cleared_upto_id';
    }

    /** Messages with an id at or below this were cleared ("delete chat") by this user. */
    public function getClearedUptoId(int $conversationId, int $userId): int
    {
        $conversation = $this->getConversationById($conversationId);

        if (!$conversation) {
            return 0;
        }

        return (int) ($conversation[$this->clearedColumn($conversation, $userId)] ?? 0);
    }

    /**
     * All conversations for a given user (tenant, landlord, or driver),
     * newest activity first, with unread counts and the other party's name.
     * Respects this user's own "delete chat" and "delete for me" choices:
     * a cleared conversation stays out of the list until a new message arrives.
     */
    public function getConversationsForUser(int $userId): array
    {
        $params = [];
        $n = 0;

        $u = function () use (&$params, &$n, $userId): string {
            $name = ':u' . (++$n);
            $params[$name] = $userId;
            return $name;
        };

        $cleared = function () use ($u): string {
            return 'CASE WHEN c.tenant_id = ' . $u() . ' THEN c.tenant_cleared_upto_id ELSE c.other_cleared_upto_id END';
        };

        $withUserExpr = 'CASE WHEN c.tenant_id = ' . $u() . ' THEN c.other_user_id ELSE c.tenant_id END';
        $joinExpr = 'CASE WHEN c.tenant_id = ' . $u() . ' THEN c.other_user_id ELSE c.tenant_id END';

        $unreadSql = "
            (
                SELECT COUNT(*) FROM messages m
                WHERE m.conversation_id = c.id
                AND (m.sender_id IS NULL OR m.sender_id != " . $u() . ")
                AND m.deleted_at IS NULL
                AND m.id > (" . $cleared() . ")
                AND NOT EXISTS (
                    SELECT 1 FROM message_user_hides h
                    WHERE h.message_id = m.id AND h.user_id = " . $u() . "
                )
                AND m.created_at > COALESCE(
                    CASE WHEN c.tenant_id = " . $u() . " THEN c.tenant_last_read_at ELSE c.other_last_read_at END,
                    '1970-01-01'
                )
            ) AS unread_count";

        $lastSql = "
            (
                SELECT CASE WHEN m.deleted_at IS NULL THEN m.message ELSE 'This message was deleted' END
                FROM messages m
                WHERE m.conversation_id = c.id
                AND m.id > (" . $cleared() . ")
                AND NOT EXISTS (
                    SELECT 1 FROM message_user_hides h
                    WHERE h.message_id = m.id AND h.user_id = " . $u() . "
                )
                ORDER BY m.id DESC LIMIT 1
            ) AS last_message";

        $whereSql = "
            (c.tenant_id = " . $u() . " OR c.other_user_id = " . $u() . ")
            AND (
                (" . $cleared() . ") = 0
                OR EXISTS (
                    SELECT 1 FROM messages m2
                    WHERE m2.conversation_id = c.id AND m2.id > (" . $cleared() . ")
                )
            )
            AND NOT (c.other_role = 'driver' AND tr.status IN ('completed', 'cancelled'))
            AND NOT (
                c.other_role = 'landlord' AND c.booking_id IS NOT NULL AND (
                    bk.id IS NULL
                    OR bk.status IN ('rejected', 'cancelled')
                    OR (bk.status = 'approved' AND bk.updated_at <= (NOW() - INTERVAL " . (int) LANDLORD_CHAT_APPROVED_VISIBLE_DAYS . " DAY))
                )
            )
        ";

        $sql = "
            SELECT
                c.*,
                {$withUserExpr} AS with_user_id,
                usr.full_name AS with_name,
                usr.profile_image AS with_image,
                usr.last_seen_at AS with_last_seen,
                {$unreadSql},
                {$lastSql}
            FROM conversations c
            JOIN users usr ON usr.id = {$joinExpr}
            LEFT JOIN truck_requests tr ON tr.id = c.truck_request_id
            LEFT JOIN bookings bk ON bk.id = c.booking_id
            WHERE {$whereSql}
            ORDER BY c.last_message_at IS NULL, c.last_message_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * True if this is a driver-trip conversation whose trip is completed
     * or cancelled (or whose trip reference is gone/invalid). Used to
     * hard-block access to the conversation entirely, not just hide it
     * from the list — a saved/guessed conversation_id must not still
     * be able to read a finished trip's chat. Landlord conversations
     * are never affected by this: they persist by design (see the
     * production notes on chat lifecycle).
     */
    public function isFinishedDriverConversation(array $conversation): bool
    {
        if (($conversation['other_role'] ?? '') !== 'driver') {
            return false;
        }

        $truckRequestId = (int) ($conversation['truck_request_id'] ?? 0);

        if ($truckRequestId <= 0) {
            return true;
        }

        $stmt = $this->conn->prepare("SELECT status FROM truck_requests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $truckRequestId]);
        $status = $stmt->fetchColumn();

        return $status === false || in_array($status, ['completed', 'cancelled'], true);
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

    /** Public shape of one message: deleted messages never expose their text. */
    public function getMessageById(int $id): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                m.id, m.conversation_id, m.sender_id, m.sender_type,
                CASE WHEN m.deleted_at IS NULL THEN m.message ELSE '' END AS message,
                m.created_at,
                (m.edited_at IS NOT NULL) AS is_edited,
                (m.deleted_at IS NOT NULL) AS is_deleted
            FROM messages m
            WHERE m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Raw row, including the real text — for internal checks only. */
    public function findMessage(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM messages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Messages of a conversation. With $viewerId set, messages that person
     * deleted for themselves (or cleared with "delete chat") are left out.
     * Deleted-for-everyone messages come back with an empty text.
     */
    public function getMessages(int $conversationId, int $afterId = 0, int $initialLimit = 50, ?int $viewerId = null): array
    {
        $select = "
            SELECT
                m.id, m.conversation_id, m.sender_id, m.sender_type,
                CASE WHEN m.deleted_at IS NULL THEN m.message ELSE '' END AS message,
                m.created_at,
                (m.edited_at IS NOT NULL) AS is_edited,
                (m.deleted_at IS NOT NULL) AS is_deleted
            FROM messages m";

        $where = " WHERE m.conversation_id = :conv";
        $params = [':conv' => $conversationId];

        if ($viewerId !== null) {
            $where .= " AND m.id > :cleared
                        AND NOT EXISTS (
                            SELECT 1 FROM message_user_hides h
                            WHERE h.message_id = m.id AND h.user_id = :viewer
                        )";
            $params[':cleared'] = $this->getClearedUptoId($conversationId, $viewerId);
            $params[':viewer'] = $viewerId;
        }

        if ($afterId === 0) {
            // First load of a conversation: only the most recent N,
            // fetched newest-first then reversed back into chronological
            // order — never the entire history in one query.
            $stmt = $this->conn->prepare($select . $where . " ORDER BY m.id DESC LIMIT :lim");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }

            $stmt->bindValue(':lim', $initialLimit, PDO::PARAM_INT);
            $stmt->execute();

            return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Polling for new messages since $afterId — naturally bounded.
        $stmt = $this->conn->prepare($select . $where . " AND m.id > :after ORDER BY m.id ASC");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':after', $afterId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Already-delivered messages (id <= $upToId) that were edited or deleted
     * for everyone since $since (a database timestamp string). Lets an open
     * chat window update messages the other person just changed.
     */
    public function getMessageChanges(int $conversationId, int $upToId, string $since, int $viewerId): array
    {
        if ($upToId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                m.id, m.conversation_id, m.sender_id, m.sender_type,
                CASE WHEN m.deleted_at IS NULL THEN m.message ELSE '' END AS message,
                m.created_at,
                (m.edited_at IS NOT NULL) AS is_edited,
                (m.deleted_at IS NOT NULL) AS is_deleted
            FROM messages m
            WHERE m.conversation_id = :conv
            AND m.id <= :upto
            AND m.id > :cleared
            AND (m.edited_at >= :since1 OR m.deleted_at >= :since2)
            AND NOT EXISTS (
                SELECT 1 FROM message_user_hides h
                WHERE h.message_id = m.id AND h.user_id = :viewer
            )
            ORDER BY m.id ASC
            LIMIT 100
        ");

        $stmt->bindValue(':conv', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':upto', $upToId, PDO::PARAM_INT);
        $stmt->bindValue(':cleared', $this->getClearedUptoId($conversationId, $viewerId), PDO::PARAM_INT);
        $stmt->bindValue(':since1', $since, PDO::PARAM_STR);
        $stmt->bindValue(':since2', $since, PDO::PARAM_STR);
        $stmt->bindValue(':viewer', $viewerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServerTime(): string
    {
        return (string) $this->conn->query("SELECT NOW()")->fetchColumn();
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

    /**
     * Total unread messages across all of this person's conversations, respecting
     * their own "delete for me" and "delete chat" choices. Feeds the sidebar badge.
     */
    public function getUnreadTotalForUser(int $userId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE (c.tenant_id = :u1 OR c.other_user_id = :u2)
            AND (m.sender_id IS NULL OR m.sender_id != :u3)
            AND m.deleted_at IS NULL
            AND m.id > CASE WHEN c.tenant_id = :u4 THEN c.tenant_cleared_upto_id ELSE c.other_cleared_upto_id END
            AND NOT EXISTS (
                SELECT 1 FROM message_user_hides h
                WHERE h.message_id = m.id AND h.user_id = :u5
            )
            AND m.created_at > COALESCE(
                CASE WHEN c.tenant_id = :u6 THEN c.tenant_last_read_at ELSE c.other_last_read_at END,
                '1970-01-01'
            )
        ");

        $stmt->execute([
            ':u1' => $userId, ':u2' => $userId, ':u3' => $userId,
            ':u4' => $userId, ':u5' => $userId, ':u6' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getUserPresence(int $userId): array
    {
        $stmt = $this->conn->prepare("SELECT last_seen_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $lastSeen = $row['last_seen_at'] ?? null;
        $online = $lastSeen && (strtotime($lastSeen) > time() - 45);

        return ['online' => $online, 'last_seen_at' => $lastSeen];
    }

    /* ==========================================================
     * EDIT / DELETE — every action is written to message_audit
     * ========================================================== */

    private function audit(?int $messageId, int $conversationId, int $actorId, string $action, ?string $oldText, ?string $newText, ?string $ip): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO message_audit
                (message_id, conversation_id, actor_id, action, old_text, new_text, ip_address)
            VALUES
                (:message_id, :conversation_id, :actor_id, :action, :old_text, :new_text, :ip)
        ");

        $stmt->execute([
            ':message_id' => $messageId,
            ':conversation_id' => $conversationId,
            ':actor_id' => $actorId,
            ':action' => $action,
            ':old_text' => $oldText,
            ':new_text' => $newText,
            ':ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
        ]);
    }

    /**
     * Locks a message row for an edit / delete-for-everyone and checks the
     * common rules. Must be called inside an open transaction.
     *
     * @return array{error?: string, row?: array}
     */
    private function lockOwnEditableMessage(int $messageId, int $actorId, int $windowMinutes, string $tooLateMessage): array
    {
        $stmt = $this->conn->prepare("
            SELECT m.*, (m.created_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)) AS in_window
            FROM messages m
            WHERE m.id = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $messageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['error' => 'Message not found.'];
        }

        if ($row['sender_type'] !== 'user' || (int) $row['sender_id'] !== $actorId) {
            return ['error' => 'You can only change your own messages.'];
        }

        if ($row['deleted_at'] !== null) {
            return ['error' => 'This message was already deleted.'];
        }

        if ((int) $row['in_window'] !== 1) {
            return ['error' => $tooLateMessage];
        }

        return ['row' => $row];
    }

    public function editMessage(int $messageId, int $actorId, string $newText, ?string $ip = null): array
    {
        $minutes = defined('CHAT_EDIT_WINDOW_MINUTES') ? (int) CHAT_EDIT_WINDOW_MINUTES : 15;

        try {
            $this->conn->beginTransaction();

            $locked = $this->lockOwnEditableMessage(
                $messageId,
                $actorId,
                $minutes,
                "Messages can only be edited within {$minutes} minutes of sending."
            );

            if (isset($locked['error'])) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => $locked['error']];
            }

            $row = $locked['row'];

            if ($row['message'] === $newText) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Nothing was changed.'];
            }

            $this->conn->prepare("UPDATE messages SET message = :message, edited_at = NOW() WHERE id = :id")
                ->execute([':message' => $newText, ':id' => $messageId]);

            $this->audit($messageId, (int) $row['conversation_id'], $actorId, 'edit', $row['message'], $newText, $ip);

            $this->conn->commit();

            return ['success' => true, 'message' => $this->getMessageById($messageId)];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE chat edit failed: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Could not edit this message. Please try again.'];
        }
    }

    public function deleteMessageForEveryone(int $messageId, int $actorId, ?string $ip = null): array
    {
        $minutes = defined('CHAT_DELETE_EVERYONE_WINDOW_MINUTES') ? (int) CHAT_DELETE_EVERYONE_WINDOW_MINUTES : 60;

        try {
            $this->conn->beginTransaction();

            $locked = $this->lockOwnEditableMessage(
                $messageId,
                $actorId,
                $minutes,
                "A message can only be deleted for everyone within {$minutes} minutes of sending. You can still delete it for yourself."
            );

            if (isset($locked['error'])) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => $locked['error']];
            }

            $row = $locked['row'];

            // The original text stays in the row and in the audit trail; it is
            // simply never sent to anyone's screen again.
            $this->conn->prepare("UPDATE messages SET deleted_at = NOW(), deleted_by = :actor WHERE id = :id")
                ->execute([':actor' => $actorId, ':id' => $messageId]);

            $this->audit($messageId, (int) $row['conversation_id'], $actorId, 'delete_everyone', $row['message'], null, $ip);

            $this->conn->commit();

            return ['success' => true, 'message' => $this->getMessageById($messageId)];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE chat delete-for-everyone failed: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Could not delete this message. Please try again.'];
        }
    }

    /** Hide one message for ONE person. The other participant is unaffected. */
    public function deleteMessageForMe(int $messageId, int $actorId, ?string $ip = null): bool
    {
        $row = $this->findMessage($messageId);

        if (!$row || !$this->userBelongsToConversation((int) $row['conversation_id'], $actorId)) {
            return false;
        }

        $this->conn->prepare("INSERT IGNORE INTO message_user_hides (message_id, user_id) VALUES (:m, :u)")
            ->execute([':m' => $messageId, ':u' => $actorId]);

        $this->audit($messageId, (int) $row['conversation_id'], $actorId, 'delete_me', null, null, $ip);

        return true;
    }

    /** "Delete chat" for ONE person: hides everything up to now for them only. */
    public function clearConversationForUser(int $conversationId, int $userId, ?string $ip = null): bool
    {
        $conversation = $this->getConversationById($conversationId);

        if (!$conversation || !$this->userBelongsToConversation($conversationId, $userId)) {
            return false;
        }

        $maxStmt = $this->conn->prepare("SELECT COALESCE(MAX(id), 0) FROM messages WHERE conversation_id = :c");
        $maxStmt->execute([':c' => $conversationId]);
        $maxId = (int) $maxStmt->fetchColumn();

        $column = $this->clearedColumn($conversation, $userId);

        $this->conn->prepare("UPDATE conversations SET {$column} = :max WHERE id = :id")
            ->execute([':max' => $maxId, ':id' => $conversationId]);

        $this->audit(null, $conversationId, $userId, 'clear_conversation_me', null, 'cleared up to message #' . $maxId, $ip);

        return true;
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

        // Only one concurrent poller gets to actually call Groq for
        // this conversation. Without this, N simultaneous pollers all
        // pass the ai_notice_sent check above and all fire a 10s Groq
        // call before any of them commits the flag that would have
        // stopped the others.
        if (!RedisThrottle::tryAcquire("chat:ai_claim:{$conversationId}", 30)) {
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