<?php

class Auth
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Register User
     */
    public function register(
        string $fullName,
        string $email,
        string $phone,
        string $password,
        string $role = 'tenant'
    ): bool {

        // Check existing email
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            return false;
        }

        // Argon2ID Hash
        $hash = password_hash(
            $password,
            PASSWORD_ARGON2ID
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO users
            (
                full_name,
                email,
                phone,
                password,
                role
            )
            VALUES
            (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $fullName,
            $email,
            $phone,
            $hash,
            $role
        ]);
    }

    /**
     * Login User
     */
    public function login(
        string $email,
        string $password
    ): bool {

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (
            !password_verify(
                $password,
                $user['password']
            )
        ) {
            return false;
        }

        // Upgrade old hashes if needed
        if (
            password_needs_rehash(
                $user['password'],
                PASSWORD_ARGON2ID
            )
        ) {

            $newHash = password_hash(
                $password,
                PASSWORD_ARGON2ID
            );

            $update = $this->pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $update->execute([
                $newHash,
                $user['id']
            ]);
        }

        // Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        return true;
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check Login
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get Current User ID
     */
    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get Current Role
     */
    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Check Role
     */
    public static function hasRole(
        string $role
    ): bool {

        return (
            isset($_SESSION['role'])
            &&
            $_SESSION['role'] === $role
        );
    }

    /**
     * Require Login
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {

            header(
                "Location: /house_truck_platform/auth/login.php"
            );

            exit;
        }
    }

    /**
     * Require Role
     */
    public static function requireRole(
        string $role
    ): void {

        self::requireLogin();

        if (!self::hasRole($role)) {

            http_response_code(403);

            exit(
                "Access denied."
            );
        }
    }

    /**
     * Current User Name
     */
    public static function userName(): ?string
    {
        return $_SESSION['full_name'] ?? null;
    }

    /**
     * Current User Email
     */
    public static function email(): ?string
    {
        return $_SESSION['email'] ?? null;
    }
}