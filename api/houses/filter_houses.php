<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../classes/House.php';
require_once '../../classes/Booking.php';
require_once '../../classes/ListingState.php';

DoSProtection::check();

try {

    $houseModel = new House();

    $limit  = isset($_GET['limit'])  ? max(1, min(24, (int) $_GET['limit'])) : 12;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    $filters = [
        'keyword'         => trim($_GET['keyword'] ?? ''),
        'min_price'       => $_GET['min_price'] ?? '',
        'max_price'       => $_GET['max_price'] ?? '',
        'house_type'      => trim($_GET['house_type'] ?? ''),
        'location'        => trim($_GET['location'] ?? ''),
        'bedrooms'        => $_GET['bedrooms'] ?? '',
        'bathrooms'       => $_GET['bathrooms'] ?? '',
        'institution_id'  => $_GET['institution_id'] ?? '',
        'max_distance_km' => $_GET['max_distance_km'] ?? '',
        'sort'            => trim($_GET['sort'] ?? 'newest'),
        '_mode'           => trim($_GET['mode'] ?? 'exact'),
    ];

    $result = $houseModel->filterHouses($filters, $limit, $offset);

    $houseIds = array_map(static fn ($h) => (int) $h['id'], $result['houses']);

    $mediaByHouse = $houseModel->getMediaForHouseIds($houseIds);

    // What may THIS visitor do per house?
    //   chat_visible    -> logged-in tenant with a paid, live booking (pending/approved)
    //   contact_visible -> tenant whose booking unlocks phone/email
    //                      (ListingState::contactRevealStatuses())
    // Guests and everyone else get neither.
    $chatHouseMap = [];
    $contactHouseMap = [];

    Session::start();

    if (Session::isAuthenticated() && (Session::user()['role'] ?? '') === 'tenant') {
        $bookingModel = new Booking();
        $tenantId = (int) Session::user()['id'];

        $chatHouseMap = $bookingModel->getPaidHouseIdsForTenant($tenantId, $houseIds);
        $contactHouseMap = $bookingModel->getContactHouseIdsForTenant($tenantId, $houseIds);
    }

    foreach ($result['houses'] as &$house) {

        $houseId = (int) $house['id'];

        $house['media'] = $mediaByHouse[$houseId] ?? [];

        $house['chat_visible'] = isset($chatHouseMap[$houseId]);
        $house['contact_visible'] = isset($contactHouseMap[$houseId]);

        if (!$house['contact_visible']) {
            unset($house['landlord_email'], $house['landlord_phone']);
        }

        $status = (string) ($house['status'] ?? 'available');
        $house['is_bookable'] = ListingState::isBookable($status);
        $house['availability_label'] = ListingState::label($status);
    }
    unset($house);

    echo json_encode([
        'success'     => true,
        'houses'      => $result['houses'],
        'total'       => $result['total'],
        'exact_match' => $result['exact_match'],
        'relaxed'     => $result['relaxed'],
        'has_more'    => ($offset + $limit) < $result['total'],
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Could not load properties.',
    ]);

    error_log('LUX EMPIRE filter_houses error: ' . $e->getMessage());
}