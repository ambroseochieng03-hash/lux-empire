<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminListingService.php';

$landlordId = filter_input(INPUT_GET, 'landlord_id', FILTER_VALIDATE_INT);

if (!$landlordId) {
    adminJsonError('Invalid landlord id.');
}

$listingService = new AdminListingService();
$listings = $listingService->getListingsForLandlord($landlordId);

foreach ($listings as &$listing) {
    $listing['media'] = $listingService->getListingMedia((int) $listing['id']);
}
unset($listing);

adminJsonResponse(['listings' => $listings]);
