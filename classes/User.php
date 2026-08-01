<?php
require_once __DIR__ . '/../config/db.php';

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
?>