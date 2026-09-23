<?php

declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/ContactMasker.php';
require_once __DIR__ . '/../config/db.php';

/**
 * LUX EMPIRE
 * Chat rules in ONE place, for both house-booking chats and truck-trip chats.
 * Called generically by api/chat/send_message.php and fetch_messages.php —
 * neither of those files needs to know which kind of conversation it is.
 *
 *   landlord chat: open only while the tenant holds a paid, live booking with
 *                  that landlord. Frozen the moment the booking ends.
 *   driver chat:   open only from the moment the driver ACCEPTS the trip until
 *                  it completes or is cancelled. Frozen after that — old
 *                  messages stay readable, nothing new can be sent.
 */
final class ChatGuard
{
    public static function isOpen(array $conversation): bool
    {
        $role = $conversation['other_role'] ?? '';

        if ($role === 'landlord') {
            return (new Booking())->hasLiveBookingWithLandlord(
                (int) $conversation['tenant_id'],
                (int) $conversation['other_user_id']
            );
        }

        if ($role === 'driver') {

            $truckRequestId = (int) ($conversation['truck_request_id'] ?? 0);

            if ($truckRequestId <= 0) {
                return false;
            }

            $pdo = (new Database())->connect();

            $stmt = $pdo->prepare("
                SELECT 1 FROM truck_requests
                WHERE id = :id AND tenant_id = :tenant_id AND driver_id = :driver_id
                AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $truckRequestId,
                ':tenant_id' => (int) $conversation['tenant_id'],
                ':driver_id' => (int) $conversation['other_user_id'],
            ]);

            return (bool) $stmt->fetchColumn();
        }

        return true;
    }

    /**
     * @return array{0: string, 1: ?string} [text to store, notice for the sender]
     */
    public static function prepareText(array $conversation, string $text): array
    {
        $notice = null;

        // Masking only matters for the landlord pre-reveal window. A driver trip
        // chat is only ever open once contact is already mutually visible, so
        // there is nothing to mask there.
        if (($conversation['other_role'] ?? '') === 'landlord') {

            $bookingModel = new Booking();

            if (!$bookingModel->hasContactRevealBetween(
                (int) $conversation['tenant_id'],
                (int) $conversation['other_user_id']
            )) {
                [$text, $wasMasked] = ContactMasker::mask($text);

                if ($wasMasked) {
                    $notice = 'For everyone\'s safety, phone numbers and emails are hidden until the landlord accepts the booking. You can keep chatting here in the meantime.';
                }
            }
        }

        return [$text, $notice];
    }
}