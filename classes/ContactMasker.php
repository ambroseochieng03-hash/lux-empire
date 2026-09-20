<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Hides phone numbers and email addresses in chat messages until the
 * landlord has accepted the booking (see ListingState::contactRevealStatuses()).
 *
 * A deterrent, not a guarantee: it stops casual sharing of numbers, it cannot
 * stop someone determined to spell digits out in words.
 */
final class ContactMasker
{
    /**
     * @return array{0: string, 1: bool} [maskedText, wasMasked]
     */
    public static function mask(string $text): array
    {
        $patterns = [
            // Kenyan numbers: 07xx / 01xx / +254 7xx / 254 1xx, with any spaces, dots, dashes or brackets between digits.
            '/(?<!\w)(?:\+?\s*254|0)[\s().\-]*[17](?:[\s().\-]*\d){8}(?!\d)/' => '[number hidden]',

            // Any other run of 9+ digits (with separators): foreign numbers, wa.me links, ID-like strings.
            '/(?<!\d)(?:\d[\s().\-]*){9,}(?!\d)/' => '[number hidden]',

            // Email addresses.
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[email hidden]',
        ];

        $masked = false;

        foreach ($patterns as $pattern => $replacement) {
            $count = 0;
            $text = (string) preg_replace($pattern, $replacement, $text, -1, $count);

            if ($count > 0) {
                $masked = true;
            }
        }

        return [$text, $masked];
    }
}
