<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/Audit.php';

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();

if (($user['role'] ?? '') !== 'landlord') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$landlordId = (int) $user['id'];
DoSProtection::check($landlordId);

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

$houseId = (int) ($_POST['house_id'] ?? 0);

if ($houseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property.']);
    exit;
}

$houseModel = new House();
$house = $houseModel->getHouseById($houseId);

if (!$house || (int) $house['landlord_id'] !== $landlordId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to remove this property.']);
    exit;
}

// A house someone already paid to reserve on LUX EMPIRE itself must
// be resolved through Accept/Reject first — deleting it here would
// silently strand a tenant who paid through the app.
if ($house['status'] === 'reserved') {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'This property has a pending paid booking on LUX EMPIRE. Accept or reject that request first before removing the listing.',
    ]);
    exit;
}

$database = new Database();
$pdo = $database->connect();

$mediaStmt = $pdo->prepare("SELECT image_path FROM house_images WHERE house_id = :id");
$mediaStmt->execute([':id' => $houseId]);
$mediaFiles = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

try {
    $pdo->beginTransaction();

    Audit::log(
        'Landlord permanently removed listing #' . $houseId . ' ("' . $house['title'] . '") — booked outside the platform.',
        $landlordId
    );

    // Preserve booking history: null house_id first (title is
    // already snapshotted at booking time, or backfilled here as a
    // fallback) — the FK is RESTRICT on purpose, so this MUST run
    // before the DELETE below or it will fail.
    $pdo->prepare("
        UPDATE bookings
        SET house_id = NULL,
            house_title_snapshot = COALESCE(house_title_snapshot, :title)
        WHERE house_id = :house_id
    ")->execute([':title' => $house['title'], ':house_id' => $houseId]);

    $pdo->prepare("DELETE FROM houses WHERE id = :id")->execute([':id' => $houseId]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('LUX EMPIRE: external-booking deletion failed for house ' . $houseId . ' — ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not remove this property. Please try again.']);
    exit;
}

// Files come AFTER the DB commit — never delete from disk first and
// risk an orphaned DB row if the transaction then failed.
foreach ($mediaFiles as $fileName) {
    $path = UPLOAD_PATH_HOUSES . $fileName;
    if (is_file($path)) {
        @unlink($path);
    }
}

echo json_encode(['success' => true, 'message' => 'Property removed.']);
