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