<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';

try {

    /*
     * ============================================================
     * SESSION
     * ============================================================
     */

    Session::start();

    /*
     * ============================================================
     * AUTHENTICATION
     * ============================================================
     */

    if (!Session::isAuthenticated()) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);

        exit;
    }

    /*
     * ============================================================
     * REQUEST METHOD
     * ============================================================
     */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method.'
        ]);

        exit;
    }

    /*
     * ============================================================
     * CSRF
     * ============================================================
     */

    if (
        !Csrf::validate(
            $_POST['csrf_token'] ?? null
        )
    ) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired CSRF token.'
        ]);

        exit;
    }

    /*
     * ============================================================
     * CURRENT USER
     * ============================================================
     */

    $user = Session::user();

    if ($user === null) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);

        exit;
    }

    $currentUser = (int) ($user['id'] ?? 0);
    $role = $user['role'] ?? '';

    if ($currentUser <= 0) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid authenticated user.'
        ]);

        exit;
    }

    DoSProtection::check($currentUser);

    /*
     * ============================================================
     * INPUT
     * ============================================================
     */

    $houseId = (int) (
        $_POST['house_id'] ?? 0
    );

    $title = trim(
        $_POST['title'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $priceInput = trim(
        $_POST['price'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    $bedrooms = (int) (
        $_POST['bedrooms'] ?? 1
    );

    $bathrooms = (int) (
        $_POST['bathrooms'] ?? 1
    );

    $houseType = trim(
        $_POST['house_type'] ?? ''
    );

    $rating = (int) (
        $_POST['rating'] ?? 5
    );

    $latitudeInput =
        trim($_POST['latitude'] ?? '');

    $longitudeInput =
        trim($_POST['longitude'] ?? '');

    /*
     * ============================================================
     * BASIC VALIDATION
     * ============================================================
     */

    if ($houseId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid property ID.'
        ]);

        exit;
    }

    if ($title === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Property title is required.'
        ]);

        exit;
    }

    if (
        $priceInput === '' ||
        !is_numeric($priceInput) ||
        (float) $priceInput <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid property price.'
        ]);

        exit;
    }

    if ($location === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Property location is required.'
        ]);

        exit;
    }

    if ($bedrooms < 1 || $bathrooms < 1) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Bedrooms and bathrooms must be at least 1.'
        ]);

        exit;
    }

    if ($rating < 1 || $rating > 5) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Rating must be between 1 and 5.'
        ]);

        exit;
    }

    $price = (float) $priceInput;

    $latitude =
        $latitudeInput !== ''
        ? (float) $latitudeInput
        : null;

    $longitude =
        $longitudeInput !== ''
        ? (float) $longitudeInput
        : null;

    /*
     * ============================================================
     * HOUSE + AUTHORIZATION
     * ============================================================
     */

    $house = new House();

    if ($role !== 'admin') {

        if ($role !== 'landlord') {

            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'Permission denied.'
            ]);

            exit;
        }

        if (
            !$house->belongsToLandlord(
                $houseId,
                $currentUser
            )
        ) {

            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to edit this property.'
            ]);

            exit;
        }

        $existingHouse = $house->getHouseById($houseId);

        if ($existingHouse !== null && !empty($existingHouse['is_hidden'])) {

            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'This listing is hidden by admin and cannot be edited.'
            ]);

            exit;
        }
    }

    /*
     * ============================================================
     * MEDIA CONTRACT
     * ============================================================
     *
     * images[] OR video.
     */

    $files = $_FILES;

    $hasImages = false;

    if (
        isset($files['images']) &&
        is_array($files['images']['name'] ?? null)
    ) {

        foreach (
            $files['images']['error']
            as $error
        ) {

            if ($error !== UPLOAD_ERR_NO_FILE) {
                $hasImages = true;
                break;
            }
        }
    }

    $hasVideo =
        isset($files['video']) &&
        is_array($files['video']) &&
        (
            $files['video']['error']
            ?? UPLOAD_ERR_NO_FILE
        ) !== UPLOAD_ERR_NO_FILE;

    if ($hasImages && $hasVideo) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'A property can contain multiple images or one video, not both.'
        ]);

        exit;
    }

    if ($hasImages) {
        $imageCount = 0;
        foreach ($files['images']['error'] as $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $imageCount++;
            }
        }

        if ($imageCount > MAX_IMAGES_PER_HOUSE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'You can upload up to ' . MAX_IMAGES_PER_HOUSE . ' images per property.'
            ]);
            exit;
        }
    }

    if ($hasVideo) {
        require_once '../../config/security/RedisThrottle.php';

        $inFlightKey = "video:inflight:{$currentUser}";
        $dailyKey = "video:daily:{$currentUser}:" . date('Y-m-d');

        if (RedisThrottle::getCount($inFlightKey) >= MAX_VIDEOS_PROCESSING_PER_LANDLORD) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'You already have a video being processed. Please wait for it to finish before uploading another.'
            ]);
            exit;
        }

        if (RedisThrottle::incrWithExpiry($dailyKey, 86400) > MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "You've reached today's video upload limit (" . MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY . "). Please try again tomorrow."
            ]);
            exit;
        }

        RedisThrottle::increment($inFlightKey);
    }

    /*
     * ============================================================
     * UPDATE
     * ============================================================
     */

    $updated = $house->updateHouse(
        $houseId,
        [
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'location' => $location,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'house_type' => $houseType,
            'rating' => $rating,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'files' => $files
        ]
    );

    if (!$updated) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Failed to update property.'
        ]);

        exit;
    }

    /*
     * ============================================================
     * SUCCESS
     * ============================================================
     */

    echo json_encode([
        'success' => true,
        'message' => 'Property updated successfully.'
    ]);

} catch (InvalidArgumentException $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (RuntimeException $e) {

    error_log(
        '[LUX EMPIRE] Update House Error: '
        . $e->getMessage()
    );

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (Throwable $e) {

    error_log(
        '[LUX EMPIRE] Unexpected Update House Error: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
