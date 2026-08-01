<?php

declare(strict_types=1);

header('Content-Type: application/json');


require_once '../../config/session.php';
require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

try {

    // =====================================
    // AUTH CHECK
    // =====================================

    if (!isset($_SESSION['user_id'])) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);

        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method.'
        ]);

        exit;
    }

    // =====================================
    // INPUTS
    // =====================================

    $houseId = (int) ($_POST['house_id'] ?? 0);

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);

    $location = trim($_POST['location'] ?? '');

    $bedrooms = (int) ($_POST['bedrooms'] ?? 1);
    $bathrooms = (int) ($_POST['bathrooms'] ?? 1);

    $houseType = trim($_POST['house_type'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);

    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    // =====================================
    // VALIDATION
    // =====================================

    if ($houseId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid house ID.'
        ]);

        exit;
    }

    if (
        empty($title) ||
        empty($location) ||
        $price <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Required fields missing.'
        ]);

        exit;
    }

    // =====================================
    // VERIFY OWNERSHIP
    // =====================================

    $stmt = $pdo->prepare("
        SELECT landlord_id
        FROM houses
        WHERE id = ?
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
    // CURRENT USER
    // =====================================

    $currentUser = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : (
        isset($_SESSION['id'])
            ? (int) $_SESSION['id']
            : 0
    );

    $role = $_SESSION['role'] ?? '';

    if ($currentUser === 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Session user_id missing.',
            'session' => $_SESSION
        ]);

        exit;
    }

    $isOwner =
        ((int)$house['landlord_id'] === $currentUser);

    $isAdmin =
        ($role === 'admin');

    if (!$isOwner && !$isAdmin) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Permission denied.'
        ]);

        exit;
    }


    // =====================================
    // HANDLE IMAGE UPLOAD
    // =====================================

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === 0
    ) {

        $uploadDir =
            '../../assets/uploads/house_images/';

        // CREATE FOLDER IF MISSING
        if (!is_dir($uploadDir)) {

            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo(
            $_FILES['image']['name'],
            PATHINFO_EXTENSION
        );

        $newImageName =
            'house_' .
            time() .
            '_' .
            rand(1000,9999) .
            '.' .
            $extension;

        $targetPath =
            $uploadDir . $newImageName;

        // MOVE FILE
        if (
            move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $targetPath
            )
        ) {

            // CHECK IF IMAGE EXISTS
            $checkImage = $pdo->prepare("
                SELECT id
                FROM house_images
                WHERE house_id = ?
                LIMIT 1
            ");

            $checkImage->execute([$houseId]);

            $existingImage =
                $checkImage->fetch(PDO::FETCH_ASSOC);

            // UPDATE EXISTING IMAGE
            if ($existingImage) {

                $updateImage = $pdo->prepare("
                    UPDATE house_images
                    SET image_path = ?
                    WHERE house_id = ?
                ");

                $updateImage->execute([
                    $newImageName,
                    $houseId
                ]);

            }

            // INSERT NEW IMAGE
            else {

                $insertImage = $pdo->prepare("
                    INSERT INTO house_images
                    (
                        house_id,
                        image_path
                    )
                    VALUES (?, ?)
                ");

                $insertImage->execute([
                    $houseId,
                    $newImageName
                ]);
            }
        }
    }


    // =====================================
    // UPDATE HOUSE
    // =====================================

    $update = $pdo->prepare("
        UPDATE houses
        SET
            title = ?,
            description = ?,
            price = ?,
            location = ?,
            bedrooms = ?,
            bathrooms = ?,
            house_type = ?,
            rating = ?,
            latitude = ?,
            longitude = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $update->execute([
        $title,
        $description,
        $price,
        $location,
        $bedrooms,
        $bathrooms,
        $houseType,
        $rating,
        $latitude,
        $longitude,
        $houseId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Property updated successfully.'
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);
}