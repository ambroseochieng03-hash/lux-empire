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
        require_once __DIR__ . '/Validator.php';

        $input = trim($input);

        if (!Validator::isValidMpesaReceiptInput($input)) {
            return null;
        }

        if (Validator::isValidMpesaReceiptCode($input)) {
            return strtoupper($input);
        }

        preg_match('/\b([A-Za-z][A-Za-z0-9]{9})\s+Confirmed\b/i', $input, $matches);

        return strtoupper($matches[1]);
    }

    /**
     * Pulls the amount out of a real M-Pesa message ("...Ksh500.00
     * sent to..."). Used so a driver reporting a Paybill payment
     * never has to manually re-type an amount that's already sitting
     * right there in the message they're pasting.
     */
    public static function extractAmount(string $input): ?float
    {
        if (preg_match('/Ksh\s?([\d,]+(?:\.\d{1,2})?)/i', $input, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        return null;
    }
}
