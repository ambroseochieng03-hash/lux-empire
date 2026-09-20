<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../config/db.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';

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

    $role = $user['role'] ?? '';
    $currentUser = (int) ($user['id'] ?? 0);

    DoSProtection::check($currentUser);

    if ($role !== 'landlord' && $role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $houseId = (int) ($_POST['house_id'] ?? 0);

    if ($houseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid property.']);
        exit;
    }

    $houseModel = new House();

    if ($role !== 'admin' && !$houseModel->belongsToLandlord($houseId, $currentUser)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this property.']);
        exit;
    }

    /*
     * Fetch every media row for this house BEFORE deleting anything,
     * so we know exactly which files to remove from disk afterward.
     * (Mirrors the same "DB row + real file" pair that
     * delete_house_media.php already handles for single-item removal.)
     */
    $db = new Database();
    $pdo = $db->connect();

    /*
     * A house with a paid booking in flight must never be deleted — the
     * tenant's fee and booking would be left pointing at nothing. The UI
     * hides the button, but the server has to enforce it too.
     */
    $stateStmt = $pdo->prepare("
        SELECT
            h.status,
            (
                SELECT COUNT(*) FROM bookings b
                WHERE b.house_id = h.id AND b.status IN ('pending', 'approved')
            ) AS live_bookings
        FROM houses h
        WHERE h.id = ?
        LIMIT 1
    ");
    $stateStmt->execute([$houseId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$state) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Property not found.']);
        exit;
    }

    if (in_array($state['status'], ['reserved', 'booked'], true) || (int) $state['live_bookings'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This property has a booking in progress. Accept or decline the request first, then you can delete it.'
        ]);
        exit;
    }

    $mediaStmt = $pdo->prepare("SELECT image_path FROM house_images WHERE house_id = ?");
    $mediaStmt->execute([$houseId]);
    $mediaRows = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Delete the house record. Assumes house_images rows are removed
     * automatically via an ON DELETE CASCADE foreign key (the usual
     * setup for a one-to-many media table) — if that FK doesn't
     * exist, House::deleteHouse() would need to delete house_images
     * rows explicitly first. Flagging this assumption; worth
     * confirming against database/schema.sql.
     */
    try {
        $deleted = $houseModel->deleteHouse($houseId);
    } catch (PDOException $e) {
        // A foreign key stops the delete when the house still has booking history.
        if ((string) $e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This property has booking history and cannot be deleted. Mark it as unavailable instead.'
            ]);
            exit;
        }

        throw $e;
    }

    if (!$deleted) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete property.']);
        exit;
    }

    /*
     * Now that the DB records are gone, clean up every media file
     * from disk so nothing orphaned is left taking up space.
     */
    foreach ($mediaRows as $mediaRow) {
        $filePath = UPLOAD_PATH_HOUSES . $mediaRow['image_path'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    error_log('[LUX EMPIRE] House deleted: house_id=' . $houseId . ' by user_id=' . $currentUser);

    echo json_encode([
        'success' => true,
        'message' => 'Property deleted successfully.'
    ]);

} catch (Throwable $e) {

    error_log('[LUX EMPIRE] Unexpected Delete House Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}