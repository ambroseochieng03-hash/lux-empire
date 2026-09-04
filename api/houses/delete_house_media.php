<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../config/db.php';
require_once '../../classes/House.php';

try {

    Session::start();

    if (!Session::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
        exit;
    }

    $user = Session::user();

    if ($user === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    $currentUser = (int) ($user['id'] ?? 0);
    $role = $user['role'] ?? '';

    $houseId = (int) ($_POST['house_id'] ?? 0);
    $mediaId = (int) ($_POST['media_id'] ?? 0);

    if ($houseId <= 0 || $mediaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    /*
     * Ownership check — reuses the same pattern as update_house.php.
     * A landlord can only remove media from their own listings; an
     * admin can remove from any.
     */
    $house = new House();

    if ($role !== 'admin') {

        if ($role !== 'landlord') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }

        if (!$house->belongsToLandlord($houseId, $currentUser)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this property.']);
            exit;
        }
    }

    $db = new Database();
    $pdo = $db->connect();

    /*
     * Fetch the media row, scoped to BOTH media_id AND house_id — this
     * is what stops a landlord from deleting another landlord's media
     * by simply guessing/sending a different media_id while owning
     * house_id (belongsToLandlord above only proved they own the
     * house they SAID they're editing, not that this specific media
     * row is really attached to it).
     */
    $stmt = $pdo->prepare("
        SELECT id, image_path
        FROM house_images
        WHERE id = ?
        AND house_id = ?
        LIMIT 1
    ");
    $stmt->execute([$mediaId, $houseId]);
    $mediaRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mediaRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Media not found.']);
        exit;
    }

    /*
     * Delete the DB row FIRST. If this fails, we exit before touching
     * the disk — better to have an orphaned file (recoverable, just
     * wasted space) than a DB row pointing at a file that no longer
     * exists (broken, shows as a dead image on the site).
     */
    $deleteStmt = $pdo->prepare("DELETE FROM house_images WHERE id = ?");
    $deleted = $deleteStmt->execute([$mediaId]);

    if (!$deleted) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove media record.']);
        exit;
    }

    /*
     * Now remove the actual file from disk so it doesn't sit there
     * unused. Path is built the same way UPLOAD_PATH_HOUSES is
     * defined in config/app.php, so this stays consistent with
     * wherever uploads actually live regardless of environment.
     */
    $filePath = UPLOAD_PATH_HOUSES . $mediaRow['image_path'];

    if (is_file($filePath)) {
        @unlink($filePath);
        // Not treated as fatal if unlink fails (e.g. permissions) —
        // the DB record is already gone, which is what matters for
        // the site working correctly. Logged below either way.
    }

    error_log('[LUX EMPIRE] House media deleted: media_id=' . $mediaId . ' house_id=' . $houseId . ' by user_id=' . $currentUser);

    echo json_encode([
        'success' => true,
        'message' => 'Media removed.'
    ]);

} catch (Throwable $e) {

    error_log('[LUX EMPIRE] Unexpected Delete House Media Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}
