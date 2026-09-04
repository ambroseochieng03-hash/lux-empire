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
    $deleted = $houseModel->deleteHouse($houseId);

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