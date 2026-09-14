<?php

/**
 * LUX EMPIRE
 * Pulls an M-Pesa receipt code out of either a bare code or a
 * pasted confirmation SMS. Real Safaricom messages read
 * "UIEQ46JHI7 Confirmed. Ksh..." — the code always sits right
 * before "Confirmed", so that's the primary pattern; a bare code
 * with nothing else is the fallback.
 */

declare(strict_types=1);

final class ReceiptExtractor
{
    public static function extract(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/i', $input, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/^[A-Z0-9]{8,12}$/i', $input)) {
            return strtoupper($input);
        }

        return null;
    }
}
