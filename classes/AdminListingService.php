<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/Audit.php';
require_once __DIR__ . '/../config/app.php';

/**
 * LUX EMPIRE
 * Admin moderation actions on house listings. Deliberately separate
 * from classes/House.php, which is frozen (1000+ lines, no
 * additions/removals permitted). Reads/writes houses/house_images
 * directly for admin-only concerns: hide/restore, flag/unflag,
 * verify, and permanent delete.
 */
final class AdminListingService
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Accepted (booked) listings drop off the admin pages
     * LANDLORD_BOOKED_VISIBLE_HOURS after acceptance — the same moment
     * scripts/cleanup_expired_listings.php deletes their media from disk.
     * The houses row itself stays in the database.
     */
    private function visibleSql(string $alias): string
    {
        $hours = (int) LANDLORD_BOOKED_VISIBLE_HOURS;

        return "NOT ({$alias}.status = 'booked' AND {$alias}.booked_at IS NOT NULL AND {$alias}.booked_at < (NOW() - INTERVAL {$hours} HOUR))";
    }

    /**
     * Returns ['listings' => [...], 'total' => int]. $limit capped
     * at 100 server-side.
     */
    public function listListings(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $visible = $this->visibleSql('h');

        $total = (int) $this->conn->query("SELECT COUNT(*) FROM houses h WHERE {$visible}")->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                h.id, h.title, h.location, h.price, h.status AS booking_status,
                h.house_type, h.is_hidden, h.is_flagged, h.flag_reason,
                h.verified_at, h.created_at,
                u.id AS landlord_id, u.full_name AS landlord_name, u.email AS landlord_email
            FROM houses h
            JOIN users u ON h.landlord_id = u.id
            WHERE {$visible}
            ORDER BY h.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'listings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function getListingsForLandlord(int $landlordId): array
    {
        $visible = $this->visibleSql('h');

        $stmt = $this->conn->prepare("
            SELECT h.id, h.title, h.location, h.price, h.status AS booking_status, h.house_type,
                   h.is_hidden, h.is_flagged, h.flag_reason, h.verified_at, h.created_at
            FROM houses h
            WHERE h.landlord_id = :landlord_id
            AND {$visible}
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([':landlord_id' => $landlordId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Media for one listing, shaped for assets/js/property-media.js:
     * a single video URL, or an array of image URLs — never both,
     * matching the existing "images OR one video" upload rule.
     * Only status='ready' rows are surfaced (processing/failed rows
     * would otherwise render as broken media).
     */
    public function getListingMedia(int $houseId): array
    {
        $stmt = $this->conn->prepare("
            SELECT image_path
            FROM house_images
            WHERE house_id = :house_id AND status = 'ready'
            ORDER BY id ASC
        ");
        $stmt->execute([':house_id' => $houseId]);

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $baseUrl = BASE_URL . '/assets/uploads/house_images/';

        $video = null;
        $images = [];

        foreach ($rows as $path) {
            $url = $baseUrl . $path;

            if (preg_match('/\.mp4$/i', $path)) {
                $video = $url;
            } else {
                $images[] = $url;
            }
        }

        return ['video' => $video, 'images' => $images];
    }

    /**
     * BATCH MEDIA FETCH — same shape as getListingMedia() but for
     * many houses in ONE query instead of one query per house. Use
     * this instead of calling getListingMedia() inside a foreach
     * over a list of listings (that's an N+1 query bug).
     *
     * Returns [house_id => ['video' => ..., 'images' => [...]], ...]
     */
    public function getMediaForListingIds(array $houseIds): array
    {
        $houseIds = array_values(array_unique(array_map('intval', $houseIds)));

        if (empty($houseIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($houseIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT house_id, image_path
            FROM house_images
            WHERE house_id IN ({$placeholders}) AND status = 'ready'
            ORDER BY house_id ASC, id ASC
        ");
        $stmt->execute($houseIds);

        $baseUrl = BASE_URL . '/assets/uploads/house_images/';
        $grouped = [];

        foreach ($houseIds as $id) {
            $grouped[$id] = ['video' => null, 'images' => []];
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['house_id'];
            $url = $baseUrl . $row['image_path'];

            if (preg_match('/\.mp4$/i', $row['image_path'])) {
                $grouped[$id]['video'] = $url;
            } else {
                $grouped[$id]['images'][] = $url;
            }
        }

        return $grouped;
    }

    public function toggleHidden(int $houseId, int $adminId): ?bool
    {
        $stmt = $this->conn->prepare("SELECT is_hidden FROM houses WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $houseId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            return null;
        }

        $newValue = ((int) $current) === 1 ? 0 : 1;

        $update = $this->conn->prepare("UPDATE houses SET is_hidden = :hidden WHERE id = :id");
        $update->execute([':hidden' => $newValue, ':id' => $houseId]);

        $action = $newValue === 1 ? 'hid' : 'restored';
        Audit::log("Admin #{$adminId} {$action} listing #{$houseId}", $adminId);

        return (bool) $newValue;
    }

    public function flagListing(int $houseId, ?string $reason, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE houses SET is_flagged = 1, flag_reason = :reason WHERE id = :id
        ");
        $stmt->execute([':reason' => $reason, ':id' => $houseId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} flagged listing #{$houseId} as suspicious" . ($reason ? " ({$reason})" : ''), $adminId);
            return true;
        }

        return false;
    }

    public function unflagListing(int $houseId, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE houses SET is_flagged = 0, flag_reason = NULL WHERE id = :id
        ");
        $stmt->execute([':id' => $houseId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} cleared suspicious flag on listing #{$houseId}", $adminId);
            return true;
        }

        return false;
    }

    public function verifyListing(int $houseId, int $adminId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE houses SET verified_at = NOW(), verified_by = :admin_id WHERE id = :id
        ");
        $stmt->execute([':admin_id' => $adminId, ':id' => $houseId]);

        if ($stmt->rowCount() > 0) {
            Audit::log("Admin #{$adminId} verified listing #{$houseId}", $adminId);
            return true;
        }

        return false;
    }

    /**
     * Permanently delete a listing: removes physical media files
     * from disk, then deletes the houses row (house_images rows
     * cascade via the existing FK). Requires a reason.
     */
    public function deleteListingPermanently(int $houseId, int $adminId, string $reason): bool
    {   

        $activeBookingCheck = $this->conn->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE house_id = :house_id AND status IN ('pending', 'approved')
        ");
        $activeBookingCheck->execute([':house_id' => $houseId]);

        if ((int) $activeBookingCheck->fetchColumn() > 0) {
            throw new RuntimeException(
                'This listing has pending or approved bookings and cannot be permanently deleted. ' .
                'Resolve those bookings first from the Bookings admin page.'
            );
        }
        
        $stmt = $this->conn->prepare("SELECT image_path FROM house_images WHERE house_id = :id");
        $stmt->execute([':id' => $houseId]);
        $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $delete = $this->conn->prepare("DELETE FROM houses WHERE id = :id");
        $delete->execute([':id' => $houseId]);

        if ($delete->rowCount() === 0) {
            return false;
        }

        $uploadDir = UPLOAD_PATH_HOUSES;

        foreach ($paths as $path) {
            $fullPath = $uploadDir . $path;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->recordActionReason($adminId, 'delete_listing', 'houses', $houseId, $reason);
        Audit::log("Admin #{$adminId} permanently deleted listing #{$houseId} ({$reason})", $adminId);

        return true;
    }

    private function recordActionReason(int $adminId, string $actionType, string $targetTable, int $targetId, string $reason): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO admin_action_reasons (admin_id, action_type, target_table, target_id, reason)
            VALUES (:admin_id, :action_type, :target_table, :target_id, :reason)
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action_type' => $actionType,
            ':target_table' => $targetTable,
            ':target_id' => $targetId,
            ':reason' => $reason,
        ]);
    }
}
