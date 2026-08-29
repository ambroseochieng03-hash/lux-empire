<?php

/**
 * LUX EMPIRE
 * Consent Records
 *
 * Logs every accept/decline decision made on the data-processing
 * consent notice shown during landlord/driver registration.
 *
 * user_id is nullable: a decline happens before any account exists,
 * so there's nothing to link it to except the role and a timestamp.
 * On accept, this is called AFTER the account is successfully
 * created, with the real user_id.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Consent
{
    private PDO $conn;
    private string $table = 'consent_records';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function record(
        ?int $userId,
        string $role,
        string $decision,
        string $ipAddress,
        string $consentType = 'data_processing'
    ): void {

        if (!in_array($decision, ['accepted', 'declined'], true)) {
            throw new InvalidArgumentException('Invalid consent decision.');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
                (user_id, role, consent_type, decision, ip_address)
            VALUES
                (:user_id, :role, :consent_type, :decision, :ip_address)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
            ':consent_type' => $consentType,
            ':decision' => $decision,
            ':ip_address' => $ipAddress
        ]);
    }
}
