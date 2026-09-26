<?php

declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/ContactMasker.php';
require_once __DIR__ . '/../config/db.php';

/**
 * LUX EMPIRE
 * Chat rules in ONE place, for both house-booking chats and truck-trip chats.
 *
 *   landlord chat: gated by the SPECIFIC booking this conversation is tied
 *                  to (conversations.booking_id) — open while that booking
 *                  is paid + pending/approved. A conversation created
 *                  before per-booking scoping existed (booking_id NULL)
 *                  falls back to "any live booking with this landlord".
 *   driver chat:   open only from the moment the driver ACCEPTS the trip until
 *                  it completes or is cancelled.
 */
final class ChatGuard
{
    public static function isOpen(array $conversation): bool
    {
        $role = $conversation['other_role'] ?? '';

        if ($role === 'landlord') {

            $bookingModel = new Booking();
            $bookingId = (int) ($conversation['booking_id'] ?? 0);

            if ($bookingId > 0) {
                return $bookingModel->isBookingLiveById(
                    $bookingId,
                    (int) $conversation['tenant_id'],
                    (int) $conversation['other_user_id']
                );
            }

            // Legacy conversation, created before booking_id existed.
            return $bookingModel->hasLiveBookingWithLandlord(
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

        if (($conversation['other_role'] ?? '') === 'landlord') {

            $bookingModel = new Booking();
            $bookingId = (int) ($conversation['booking_id'] ?? 0);

            $revealed = $bookingId > 0
                ? $bookingModel->hasContactRevealForBooking($bookingId, (int) $conversation['tenant_id'], (int) $conversation['other_user_id'])
                : $bookingModel->hasContactRevealBetween((int) $conversation['tenant_id'], (int) $conversation['other_user_id']);

            if (!$revealed) {
                [$text, $wasMasked] = ContactMasker::mask($text);

                if ($wasMasked) {
                    $notice = 'For everyone\'s safety, phone numbers and emails are hidden until the landlord accepts the booking. You can keep chatting here in the meantime.';
                }
            }
        }

        return [$text, $notice];
    }

    /**
     * True if this is a landlord conversation whose booking has ended badly
     * (rejected/cancelled — hidden immediately) or ended well long enough
     * ago to age out (approved, past LANDLORD_CHAT_APPROVED_VISIBLE_DAYS).
     * Used to hard-block direct access, not just hide it from the list.
     * A legacy conversation with no booking_id is never auto-expired.
     */
    public static function isFinishedLandlordConversation(array $conversation): bool
    {
        if (($conversation['other_role'] ?? '') !== 'landlord') {
            return false;
        }

        $bookingId = (int) ($conversation['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            return false;
        }

        $bookingModel = new Booking();
        $info = $bookingModel->getBookingLifecycleInfo($bookingId);

        if (!$info) {
            return true;
        }

        if (in_array($info['status'], ['rejected', 'cancelled'], true)) {
            return true;
        }

        if ($info['status'] === 'approved') {

            if (!empty($info['updated_at'])) {
                $ageSeconds = time() - strtotime((string) $info['updated_at']);
                if ($ageSeconds > (LANDLORD_CHAT_APPROVED_VISIBLE_DAYS * 86400)) {
                    return true;
                }
            }

            // Superseded: this tenant has since had a DIFFERENT, more
            // recent booking with this same landlord approved (a
            // different property). Only the newest one's conversation
            // stays reachable — this is what makes an older approved
            // chat disappear the moment a second booking with the
            // same landlord gets accepted, exactly like a rejection
            // already does for a still-pending one.
            $bookingModel = new Booking();
            $latestApprovedId = $bookingModel->getLatestApprovedBookingId(
                (int) $conversation['tenant_id'],
                (int) $conversation['other_user_id']
            );

            if ($latestApprovedId !== null && $latestApprovedId !== $bookingId) {
                return true;
            }
        }

        return false;
    }
}