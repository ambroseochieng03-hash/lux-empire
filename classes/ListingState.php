<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Single source of truth for what a house's status means to a tenant or
 * guest. Pages and APIs ask this class instead of repeating status checks.
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
}
