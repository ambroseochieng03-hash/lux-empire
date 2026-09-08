<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminListingService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);

if (!$houseId) {
    adminJsonError('Invalid listing id.');
}

$service = new AdminListingService();
$ok = $service->verifyListing($houseId, $currentAdminId);

if (!$ok) {
    adminJsonError('Listing not found.', 404);
}

adminJsonResponse(['status' => 'verified']);
