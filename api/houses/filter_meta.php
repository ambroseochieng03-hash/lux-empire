<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../classes/House.php';

// Read-only, but still counts as a request — same abuse-detection as
// everything else. No CSRF here: CSRF protects state-changing
// requests, this is a plain GET with no side effects.
DoSProtection::check();

try {

    $houseModel = new House();

        echo json_encode([
            'success'      => true,
            'price'        => $houseModel->getPriceBounds(),
            'house_types'  => $houseModel->getDistinctHouseTypes(),
            'locations'    => $houseModel->getDistinctLocations(),
            'institutions' => $houseModel->getInstitutions(),   
        ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Could not load filter options.',
    ]);

    error_log('LUX EMPIRE filter_meta error: ' . $e->getMessage());
}
