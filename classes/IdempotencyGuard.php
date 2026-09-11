<?php

/**
 * LUX EMPIRE
 * Idempotency Guard
 *
 * Prevents the same logical action (a booking, a truck request, a
 * house creation) from being processed twice when the client retries
 * — a slow connection double-tap, a browser auto-resubmit, or a
 * genuine simultaneous double-click.
 *
 * Mechanism: the client sends a random key alongside the real
 * request. begin() tries to INSERT (key, endpoint) as a new
 * "processing" row — the table's UNIQUE(idempotency_key, endpoint)
 * makes this atomic at the database level, not a check-then-insert
 * race. If the insert succeeds, this really is a new attempt: the
 * caller proceeds and calls complete() when done. If it fails on
 * the duplicate key, this key has been seen before: begin() fetches
 * what happened last time and hands it back, so the endpoint can
 * replay the original result instead of doing the work again.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class IdempotencyGuard
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Returns:
     *   ['status' => 'new']
     *     — first time this key has been seen; proceed with the
     *       real action, then call complete().
     *   ['status' => 'processing']
     *     — another request with this exact key is still running
     *       right now (race between two near-simultaneous clicks).
     *       The caller should reject with a "still processing" style
     *       response, not perform the action.
     *   ['status' => 'completed', 'response_code' => int, 'response_body' => string]
     *     — this key already ran to completion; replay that exact
     *       result instead of doing anything again.
     */
    /**
     * A 'processing' row older than this is assumed abandoned (the
     * original request crashed/died before calling complete()) and
     * gets reclaimed rather than blocking forever. Generous — well
     * beyond how long any real booking/request/listing action here
     * should ever take.
     */
    private const PROCESSING_TIMEOUT_SECONDS = 120;

    public function begin(string $key, string $endpoint, int $userId): array
    {
        try {

            $stmt = $this->conn->prepare("
                INSERT INTO idempotency_keys (idempotency_key, endpoint, user_id, status)
                VALUES (:key, :endpoint, :user_id, 'processing')
            ");

            $stmt->execute([
                ':key' => $key,
                ':endpoint' => $endpoint,
                ':user_id' => $userId
            ]);

            return ['status' => 'new'];

        } catch (PDOException $e) {

            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $existing = $this->conn->prepare("
                SELECT status, response_code, response_body, created_at
                FROM idempotency_keys
                WHERE idempotency_key = :key AND endpoint = :endpoint
                LIMIT 1
            ");

            $existing->execute([':key' => $key, ':endpoint' => $endpoint]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return ['status' => 'processing'];
            }

            if ($row['status'] === 'processing') {

                $ageSeconds = time() - strtotime($row['created_at']);

                if ($ageSeconds < self::PROCESSING_TIMEOUT_SECONDS) {
                    return ['status' => 'processing'];
                }

                // Abandoned — reclaim it. The WHERE clause (still
                // 'processing' AND matching the exact created_at we
                // just read) makes this a compare-and-swap: if two
                // requests both try to reclaim the same stale row at
                // once, only one UPDATE actually matches a row (the
                // first one's UPDATE changes created_at, so the
                // second one's WHERE no longer matches) — same
                // pattern as Booking::acceptBooking()'s conditional
                // UPDATE.
                $reclaim = $this->conn->prepare("
                    UPDATE idempotency_keys
                    SET created_at = NOW(), user_id = :user_id
                    WHERE idempotency_key = :key AND endpoint = :endpoint
                    AND status = 'processing' AND created_at = :original_created_at
                ");

                $reclaim->execute([
                    ':user_id' => $userId,
                    ':key' => $key,
                    ':endpoint' => $endpoint,
                    ':original_created_at' => $row['created_at']
                ]);

                if ($reclaim->rowCount() > 0) {
                    return ['status' => 'new'];
                }

                // Someone else reclaimed it in the same instant.
                return ['status' => 'processing'];
            }

            return [
                'status' => 'completed',
                'response_code' => (int) $row['response_code'],
                'response_body' => (string) $row['response_body']
            ];
        }
    }

    /**
     * Record the real result so a future replay of this same key
     * returns exactly what actually happened, rather than running
     * the action again.
     */
    public function complete(string $key, string $endpoint, int $responseCode, string $responseBody): void
    {
        $stmt = $this->conn->prepare("
            UPDATE idempotency_keys
            SET status = 'completed', response_code = :code, response_body = :body
            WHERE idempotency_key = :key AND endpoint = :endpoint
        ");

        $stmt->execute([
            ':code' => $responseCode,
            ':body' => $responseBody,
            ':key' => $key,
            ':endpoint' => $endpoint
        ]);
    }
}
