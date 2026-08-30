<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Crypto.php';

class User {
    private $conn;
    private $table = "users";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Check if email already exists
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if phone already exists
     */
    public function phoneExists($phone) {
        $query = "SELECT id FROM " . $this->table . " WHERE phone = :phone LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Register new user
     *
     * This is the ORIGINAL universal registration path (still used
     * by the old auth/register.php as a tenant stopgap). Left
     * completely unchanged — writes plaintext national_id, exactly
     * as before. Do not point the new landlord/driver forms at this.
     */
    public function register($full_name, $email, $phone, $national_id, $password, $role) {

        if ($this->emailExists($email)) {
            return "Email already belongs to an Empire member.";
        }

        if ($this->phoneExists($phone)) {
            return "Phone number already registered.";
        }

        // Secure password hashing
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        $query = "INSERT INTO " . $this->table . "
            (full_name, email, phone, national_id, password, role, status)
            VALUES
            (:full_name, :email, :phone, :national_id, :password, :role, 'active')";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':national_id', $national_id);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role', $role);

        if ($stmt->execute()) {
            return true;
        }

        return "Empire registration failed. Please try again.";
    }

    /**
     * REGISTER LANDLORD
     *
     * National ID is encrypted (Crypto::encrypt()) into
     * users.national_id_encrypted. The old plaintext
     * users.national_id column is left NULL for this path.
     *
     * Returns the new user's id (int) on success, or a user-safe
     * error string on failure — same convention as register().
     */
    public function registerLandlord(
        string $fullName,
        string $email,
        string $phone,
        string $nationalId,
        string $password
    ) {
        if ($this->emailExists($email)) {
            return "Email already belongs to an Empire member.";
        }

        if ($this->phoneExists($phone)) {
            return "Phone number already registered.";
        }

        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
        $encryptedNationalId = Crypto::encrypt($nationalId);

        $query = "INSERT INTO " . $this->table . "
            (full_name, email, phone, national_id_encrypted, password, role, status)
            VALUES
            (:full_name, :email, :phone, :national_id_encrypted, :password, 'landlord', 'active')";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':national_id_encrypted' => $encryptedNationalId,
            ':password' => $hashedPassword
        ]);

        if ($stmt->rowCount() === 0) {
            return "Empire registration failed. Please try again.";
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * REGISTER DRIVER
     *
     * Creates the users row AND the drivers row (license_number,
     * vehicle_plate — both encrypted; vehicle_type stays plaintext,
     * not sensitive) in a single transaction, so a driver never ends
     * up with a user account but no driver profile or vice versa.
     *
     * Returns the new user's id (int) on success, or a user-safe
     * error string on failure.
     */
    public function registerDriver(
        string $fullName,
        string $email,
        string $phone,
        string $password,
        string $licenseNumber,
        string $vehiclePlate,
        string $vehicleType
    ) {
        if ($this->emailExists($email)) {
            return "Email already belongs to an Empire member.";
        }

        if ($this->phoneExists($phone)) {
            return "Phone number already registered.";
        }

        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
        $encryptedLicense = Crypto::encrypt($licenseNumber);
        $encryptedPlate = Crypto::encrypt($vehiclePlate);

        try {

            $this->conn->beginTransaction();

            $userStmt = $this->conn->prepare("
                INSERT INTO " . $this->table . "
                    (full_name, email, phone, password, role, status)
                VALUES
                    (:full_name, :email, :phone, :password, 'driver', 'active')
            ");

            $userStmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => $hashedPassword
            ]);

            $userId = (int) $this->conn->lastInsertId();

            $driverStmt = $this->conn->prepare("
                INSERT INTO drivers
                    (user_id, vehicle_type, vehicle_plate, license_number, is_available)
                VALUES
                    (:user_id, :vehicle_type, :vehicle_plate, :license_number, 0)
            ");

            /*
             * is_available starts FALSE — a newly registered driver
             * shouldn't be eligible for trip assignment until
             * whatever verification step this platform requires
             * (out of scope here) has happened.
             */

            $driverStmt->execute([
                ':user_id' => $userId,
                ':vehicle_type' => $vehicleType,
                ':vehicle_plate' => $encryptedPlate,
                ':license_number' => $encryptedLicense
            ]);

            $this->conn->commit();

            return $userId;

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE driver registration failed: ' . $e->getMessage());

            return "Empire registration failed. Please try again.";
        }
    }

    /**
     * REGISTER TENANT (pending — awaiting OTP email verification)
     *
     * Creates the account immediately in 'pending' status so the
     * email is reserved (prevents a race where two people register
     * the same address before either verifies), but the account
     * cannot log in normally until activateTenant() runs.
     *
     * Returns the new user's id (int) on success, or a user-safe
     * error string on failure.
     */
    public function registerTenantPending(
        string $fullName,
        string $email,
        string $phone,
        string $password
    ) {
        if ($this->emailExists($email)) {
            return "Email already belongs to an Empire member.";
        }

        if ($phone !== '' && $this->phoneExists($phone)) {
            return "Phone number already registered.";
        }

        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $this->conn->prepare("
            INSERT INTO " . $this->table . "
                (full_name, email, phone, password, role, status)
            VALUES
                (:full_name, :email, :phone, :password, 'tenant', 'pending')
        ");

        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone !== '' ? $phone : null,
            ':password' => $hashedPassword
        ]);

        if ($stmt->rowCount() === 0) {
            return "Empire registration failed. Please try again.";
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * ACTIVATE TENANT after successful OTP verification.
     */
    public function activateTenant(int $userId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE " . $this->table . "
            SET status = 'active', email_verified_at = NOW()
            WHERE id = :id
            AND status = 'pending'
        ");

        $stmt->execute([':id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * FIND OR CREATE a user from a verified Google identity.
     *
     * Returns one of:
     *   ['status' => 'existing_active', 'user' => array]
     *       — already-linked, fully active account. Treat as login.
     *   ['status' => 'blocked_existing_email']
     *       — this email already has a password-based account.
     *         Auto-linking is intentionally NOT done (see the
     *         data-protection discussion this was decided in) —
     *         the person must log in with their password instead.
     *   ['status' => 'new_pending_password', 'user_id' => int]
     *       — brand-new account created, email already verified by
     *         Google, but still needs a password set before it's
     *         fully active (password stays mandatory for everyone).
     */
    public function findOrCreateGoogleUser(string $sub, string $email, string $name): array
    {
        $linkStmt = $this->conn->prepare("
            SELECT u.*
            FROM oauth_identities oi
            JOIN " . $this->table . " u ON u.id = oi.user_id
            WHERE oi.provider = 'google'
            AND oi.provider_user_id = :sub
            LIMIT 1
        ");

        $linkStmt->execute([':sub' => $sub]);
        $existingUser = $linkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            return ['status' => 'existing_active', 'user' => $existingUser];
        }

        if ($this->emailExists($email)) {
            return ['status' => 'blocked_existing_email'];
        }

        try {

            $this->conn->beginTransaction();

            // Unusable random password — real one is set via the
            // "set your password" step that follows for new Google
            // sign-ups. Never intended to be guessed/used as-is.
            $placeholderPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);

            $userStmt = $this->conn->prepare("
                INSERT INTO " . $this->table . "
                    (full_name, email, password, role, status, email_verified_at)
                VALUES
                    (:full_name, :email, :password, 'tenant', 'pending', NOW())
            ");

            $userStmt->execute([
                ':full_name' => $name,
                ':email' => $email,
                ':password' => $placeholderPassword
            ]);

            $userId = (int) $this->conn->lastInsertId();

            $linkInsert = $this->conn->prepare("
                INSERT INTO oauth_identities (user_id, provider, provider_user_id, email)
                VALUES (:user_id, 'google', :sub, :email)
            ");

            $linkInsert->execute([
                ':user_id' => $userId,
                ':sub' => $sub,
                ':email' => $email
            ]);

            $this->conn->commit();

            return ['status' => 'new_pending_password', 'user_id' => $userId];

        } catch (Throwable $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('LUX EMPIRE Google tenant creation failed: ' . $e->getMessage());

            return ['status' => 'error'];
        }
    }

    /**
     * SET PASSWORD for a Google-registered tenant completing signup.
     * Only transitions a 'pending' + already-email-verified account
     * to 'active' — cannot be used to reset an arbitrary user's
     * password.
     */
    public function setTenantPassword(int $userId, string $password): bool
    {
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $this->conn->prepare("
            UPDATE " . $this->table . "
            SET password = :password, status = 'active'
            WHERE id = :id
            AND status = 'pending'
            AND email_verified_at IS NOT NULL
        ");

        $stmt->execute([
            ':password' => $hashedPassword,
            ':id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * GET USER BY ID — needed by the OTP/Google completion endpoints
     * to load the pending account's data (for the session) after
     * activation.
     */
    public function getUserById(int $id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
