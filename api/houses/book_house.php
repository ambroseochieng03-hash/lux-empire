<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Deprecated — bookings are now created exclusively as a side
 * effect of a successful booking_fee payment, inside
 * Payment::applyEntitlement(). This endpoint intentionally does
 * nothing else, so it cannot be used (accidentally or otherwise) to
 * create an unpaid booking that bypasses payment and reservation.
 */

header('Content-Type: application/json');
http_response_code(410);

echo json_encode([
    'success' => false,
    'message' => 'Booking now requires payment. Please use the Book Now button on the listing.'
]);