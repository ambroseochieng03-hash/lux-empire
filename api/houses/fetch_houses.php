<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once '../../config/db.php';

try {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid house ID.'
        ]);

        exit;
    }

    $houseId = (int) $_GET['id'];

    // =====================================
    // HOUSE DETAILS
    // =====================================

    $stmt = $pdo->prepare("
        SELECT

            h.id,
            h.title,
            h.description,
            h.price,
            h.location,
            h.latitude,
            h.longitude,
            h.bedrooms,
            h.bathrooms,
            h.house_type,
            h.status,
            h.created_at,

            u.id AS landlord_id,
            u.full_name AS landlord_name,
            u.email AS landlord_email,
            u.phone AS landlord_phone

        FROM houses h

        JOIN users u
            ON h.landlord_id = u.id

        WHERE h.id = ?

        LIMIT 1
    ");

    $stmt->execute([$houseId]);

    $house = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$house) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'House not found.'
        ]);

        exit;
    }

    // =====================================
    // HOUSE IMAGES
    // =====================================

    $imageStmt = $pdo->prepare("
        SELECT image_path
        FROM house_images
        WHERE house_id = ?
        ORDER BY id ASC
    ");

    $imageStmt->execute([$houseId]);

    $images = $imageStmt->fetchAll(
        PDO::FETCH_COLUMN
    );

    $house['images'] = $images;

    // =====================================
    // SUCCESS RESPONSE
    // =====================================

    echo json_encode([
        'success' => true,
        'house' => $house
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);
}