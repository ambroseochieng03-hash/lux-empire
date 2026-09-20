<?php

declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/ContactMasker.php';

/**
 * LUX EMPIRE
 * The chat rules in ONE place, used by send / edit / delete:
 *
 *  - A tenant <-> landlord chat is OPEN only while the tenant holds a paid,
 *    live booking with that landlord. Once it ends (declined, cancelled,
 *    expired) the chat is CLOSED and frozen: no new messages, no edits, no
 *    "delete for everyone". Old messages stay readable, and "delete for me"
 *    still works.
 *  - Until the landlord's contact details are unlocked, phone numbers and
 *    emails are hidden from message text.
 *  - Driver chats are not affected.
 */
final class ChatGuard
{
    public static function isOpen(array $conversation): bool
    {
        if (($conversation['other_role'] ?? '') !== 'landlord') {
            return true;
        }

        return (new Booking())->hasLiveBookingWithLandlord(
            (int) $conversation['tenant_id'],
            (int) $conversation['other_user_id']
        );
    }

    /**
     * @return array{0: string, 1: ?string} [text to store, notice for the sender]
     */
    public static function prepareText(array $conversation, string $text): array
    {
        $notice = null;

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
