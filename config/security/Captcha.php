<?php

/**
 * LUX EMPIRE
 * CAPTCHA Protection
 *
 * Provider-agnostic CAPTCHA verification.
 *
 * This class is responsible only for:
 * - determining whether CAPTCHA is configured
 * - obtaining the submitted challenge token
 * - verifying the token with the configured provider
 *
 * CAPTCHA is not a replacement for rate limiting,
 * throttling, or brute-force protection.
 */

declare(strict_types=1);

final class Captcha
{
    /**
     * Environment variable containing the CAPTCHA secret.
     */
    private const SECRET_ENV = 'CAPTCHA_SECRET';

    /**
     * Environment variable containing the CAPTCHA verification URL.
     *
     * This allows the provider to be changed without modifying
     * application logic.
     */
    private const VERIFY_URL_ENV = 'CAPTCHA_VERIFY_URL';

    /**
     * Maximum time allowed for the provider request.
     */
    private const TIMEOUT_SECONDS = 5;

    /**
     * Determine whether CAPTCHA is configured.
     */
    public static function isConfigured(): bool
    {
        $secret = getenv(self::SECRET_ENV);
        $verifyUrl = getenv(self::VERIFY_URL_ENV);

        return is_string($secret)
            && $secret !== ''
            && is_string($verifyUrl)
            && filter_var(
                $verifyUrl,
                FILTER_VALIDATE_URL
            ) !== false;
    }

    /**
     * Verify a CAPTCHA response.
     *
     * Returns false when:
     * - CAPTCHA is not configured
     * - token is missing
     * - provider cannot be reached
     * - provider rejects the challenge
     */
    public static function verify(
        ?string $token,
        ?string $remoteIp = null
    ): bool {
        if (!self::isConfigured()) {
            return false;
        }

        if (
            $token === null
            || $token === ''
            || strlen($token) > 4096
        ) {
            return false;
        }

        $secret = getenv(self::SECRET_ENV);
        $verifyUrl = getenv(self::VERIFY_URL_ENV);

        if (
            !is_string($secret)
            || !is_string($verifyUrl)
        ) {
            return false;
        }

        $payload = [
            'secret'   => $secret,
            'response' => $token,
        ];

        if (
            is_string($remoteIp)
            && filter_var(
                $remoteIp,
                FILTER_VALIDATE_IP
            ) !== false
        ) {
            $payload['remoteip'] = $remoteIp;
        }

        $ch = curl_init($verifyUrl);

        if ($ch === false) {
            return false;
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
            ]
        );

        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);

            return false;
        }

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if (
            $httpCode < 200
            || $httpCode >= 300
        ) {
            return false;
        }

        $result = json_decode(
            $response,
            true
        );

        if (!is_array($result)) {
            return false;
        }

        return ($result['success'] ?? false) === true;
    }

    /**
     * Get the client IP used for CAPTCHA verification.
     *
     * Only REMOTE_ADDR is trusted here.
     */
    public static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (
            !is_string($ip)
            || $ip === ''
        ) {
            return null;
        }

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            return null;
        }

        return $ip;
    }
}