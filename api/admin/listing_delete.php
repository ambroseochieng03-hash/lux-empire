<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminListingService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$houseId) {
    adminJsonError('Invalid listing id.');
}

if ($reason === '') {
    adminJsonError('A reason is required to permanently delete a listing.');
}

$service = new AdminListingService();

try {
    $ok = $service->deleteListingPermanently($houseId, $currentAdminId, $reason);
} catch (RuntimeException $e) {
    adminJsonError($e->getMessage(), 409);
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin listing_delete error: ' . $e->getMessage());
    adminJsonError('Could not delete listing.', 500);
}

if (!$ok) {
    adminJsonError('Listing not found.', 404);
}

adminJsonResponse(['status' => 'deleted']);
