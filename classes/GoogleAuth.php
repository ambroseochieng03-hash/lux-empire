<?php

/**
 * LUX EMPIRE
 * Google Sign-In verification
 *
 * The frontend (Google Identity Services button) hands us a signed
 * ID token. We verify it server-side via Google's own tokeninfo
 * endpoint rather than implementing JWT signature verification
 * ourselves — simpler, and it's Google's officially documented way
 * to do this without a JWT library.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

final class GoogleAuth
{
    /**
     * Verify a Google ID token.
     *
     * Returns ['sub' => ..., 'email' => ..., 'name' => ...] on
     * success, or null if the token is invalid, expired, or was
     * issued for a different app (aud mismatch).
     */
    public static function verify(string $idToken): ?array
    {
        $clientId = GOOGLE_OAUTH_CLIENT_ID;

        if ($clientId === '') {
            error_log('LUX EMPIRE: GOOGLE_OAUTH_CLIENT_ID is not configured.');
            return null;
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            error_log('LUX EMPIRE: Google tokeninfo request failed.');
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        // Must have been issued for OUR client id, not some other app.
        if (($data['aud'] ?? '') !== $clientId) {
            error_log('LUX EMPIRE: Google ID token aud mismatch.');
            return null;
        }

        if (($data['email_verified'] ?? 'false') !== 'true') {
            return null;
        }

        if (empty($data['sub']) || empty($data['email'])) {
            return null;
        }

        return [
            'sub' => $data['sub'],
            'email' => $data['email'],
            'name' => $data['name'] ?? $data['email']
        ];
    }
}
