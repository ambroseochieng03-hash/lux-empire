<?php

/**
 * LUX EMPIRE
 * Field-Level Encryption
 *
 * Encrypts/decrypts individual sensitive values (National ID,
 * driver license number, vehicle plate) before they touch the
 * database, using libsodium's crypto_secretbox (XSalsa20-Poly1305
 * — authenticated encryption, built into PHP core since 7.2, no
 * extra extension required).
 *
 * This is NOT for passwords — passwords must continue to use
 * password_hash(..., PASSWORD_ARGON2ID) exactly as User::register()
 * already does. Hashing and encryption solve different problems:
 * a password is never decrypted, these fields sometimes need to be.
 *
 * KEY MANAGEMENT
 * ---------------
 * The key lives in .env as APP_ENCRYPTION_KEY (base64-encoded, 32
 * raw bytes) — never in source code, never in the database. Losing
 * this key means every encrypted value becomes permanently
 * unreadable, so back it up somewhere safe outside the repo.
 *
 * Generate one (run once, e.g. via `php -r "..."` on the server):
 *
 *   php -r "echo base64_encode(sodium_crypto_secretbox_keygen()) . PHP_EOL;"
 *
 * Then add to .env:
 *
 *   APP_ENCRYPTION_KEY=<paste the output here>
 */

declare(strict_types=1);

final class Crypto
{
    /**
     * Encrypt a plaintext string.
     *
     * Returns a base64 string containing the random nonce followed
     * by the ciphertext — safe to store directly in a TEXT column.
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::key();

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            $key
        );

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a value previously produced by encrypt().
     *
     * Throws if the value can't be decrypted — either the key
     * changed, or the stored value is corrupted/tampered with.
     * Callers should not silently swallow this; a failed decryption
     * of someone's ID number is worth knowing about.
     */
    public static function decrypt(string $encoded): string
    {
        $key = self::key();

        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Encrypted value is malformed.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed — the encryption key may have changed, ' .
                'or this value has been corrupted.'
            );
        }

        return $plaintext;
    }

    /**
     * Load and validate the encryption key from .env.
     */
    private static function key(): string
    {
        $encoded = $_ENV['APP_ENCRYPTION_KEY'] ?? '';

        if ($encoded === '') {
            throw new RuntimeException(
                'APP_ENCRYPTION_KEY is not configured. See classes/Crypto.php ' .
                'for how to generate one.'
            );
        }

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_ENCRYPTION_KEY is invalid or the wrong length.');
        }

        return $key;
    }
}