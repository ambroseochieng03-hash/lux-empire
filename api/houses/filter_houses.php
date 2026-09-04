<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../classes/House.php';

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

        // Attach full media (all images / the video) per house — the
        // 'image' field from filterHouses() is just the first thumbnail,
        // not enough to render the same carousel/video the PHP pages do.
        foreach ($result['houses'] as &$house) {
            $house['media'] = $houseModel->getHouseMedia((int) $house['id']);
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
