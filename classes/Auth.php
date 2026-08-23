<?php

declare(strict_types=1);

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
     * Authenticate User
     *
     * Returns the authenticated user record.
     * Returns null when authentication fails.
     */
    public function login(
        string $email,
        string $password
    ): ?array {

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if (
            !password_verify(
                $password,
                $user['password']
            )
        ) {
            return null;
        }

        /*
         * Upgrade password hash when required.
         */
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

        return $user;
    }
}