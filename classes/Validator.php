<?php

/**
 * LUX EMPIRE
 * Registration Field Validation
 *
 * Server-side is authoritative — the frontend (assets/js/form-
 * validation.js) mirrors these exact rules for live feedback, but
 * this class is what actually gets enforced. Never trust the client
 * to have run its copy of these checks.
 *
 * IMPORTANT DISTINCTION this class does NOT and CANNOT solve: format
 * validity is not the same as ownership. isValidEmail() confirms an
 * address is well-formed, not that the person registering actually
 * controls it — that's what OTP verification is for.
 */

declare(strict_types=1);

final class Validator
{
    /**
     * Full name: letters (including accented/Unicode letters),
     * spaces, hyphens, and apostrophes only. Must contain at least
     * one actual letter (rejects "- -" or "'''"). 2–100 characters.
     */
    public static function isValidFullName(string $name): bool
    {
        $name = trim($name);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return false;
        }

        if (!preg_match('/^[\p{L} .\'-]+$/u', $name)) {
            return false;
        }

        // Must contain at least one real letter, not just
        // spaces/dots/hyphens/apostrophes.
        if (!preg_match('/\p{L}/u', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Email: RFC-shape validity only (see class docblock — this is
     * NOT an ownership check). Rejects anything with whitespace,
     * enforces the 254-character RFC length cap.
     */
    public static function isValidEmail(string $email): bool
    {
        $email = trim($email);

        if ($email === '' || strlen($email) > 254) {
            return false;
        }

        if (preg_match('/\s/', $email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Kenyan phone number. Accepts exactly two formats, matching the
     * examples given:
     *   Local:         0116268903, 0748671072   (0 + 9 digits)
     *   International: +254116268903, +254748671072 (+254 + 9 digits)
     * The digit immediately after the 0/+254 must be 7 or 1 (current
     * Safaricom/Airtel/Telkom mobile ranges). No spaces, no dashes,
     * no other characters, no other lengths.
     */
    public static function isValidKenyanPhone(string $phone): bool
    {
        $phone = trim($phone);

        return (bool) preg_match('/^(0|\+254)[71]\d{8}$/', $phone);
    }

    /**
     * National ID: digits only, no spaces, no letters, no special
     * characters. Length 7–9 digits — Kenyan IDs vary by issuance
     * era (older ones are commonly 8 digits, some are 7 or 9).
     */
    public static function isValidNationalId(string $id): bool
    {
        $id = trim($id);

        return (bool) preg_match('/^\d{7,9}$/', $id);
    }

    /**
     * Password: length only here (8+ chars) — complexity rules are
     * a product decision, not added unilaterally. Kept as its own
     * method so it's in one place if that changes later.
     */
    public static function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8;
    }

    /**
     * Vehicle plate: Kenyan format is 3 letters, a space, 3 digits,
     * 1 letter (e.g. "KDA 123A") — letters/digits/one internal space
     * only, nothing else.
     */
    public static function isValidVehiclePlate(string $plate): bool
    {
        $plate = trim($plate);

        return (bool) preg_match('/^[A-Z]{3} \d{3}[A-Z]$/i', $plate);
    }
}
