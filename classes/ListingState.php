<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Single source of truth for what a house's status means to a tenant or
 * guest, and for which bookings unlock the landlord's contact details.
 *
 *   available    -> can be booked
 *   reserved     -> a tenant has PAID and is waiting for the landlord.
 *                   Temporary: returns to 'available' if the landlord
 *                   declines or never answers (RESERVATION_RESPONSE_HOURS).
 *   booked       -> the landlord ACCEPTED a booking. Final.
 *   unavailable  -> the landlord marked it taken elsewhere. The landlord
 *                   can reopen it.
 *   rented       -> reserved for future use.
 */
final class ListingState
{
    public static function isBookable(string $status): bool
    {
        return $status === 'available';
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'available' => '',
            'reserved' => 'Reserved',
            'booked' => 'Booked',
            'rented' => 'Rented',
            default => 'Unavailable',
        };
    }

    /**
     * Statuses of a PAID booking that unlock the landlord's phone/email.
     * See REVEAL_CONTACT_BEFORE_ACCEPTANCE in config/app.php.
     */
    public static function contactRevealStatuses(): array
    {
        return (defined('REVEAL_CONTACT_BEFORE_ACCEPTANCE') && REVEAL_CONTACT_BEFORE_ACCEPTANCE)
            ? ['pending', 'approved']
            : ['approved'];
    }
}