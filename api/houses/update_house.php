<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../classes/Validator.php';

/**
 * Returns a message safe to show to the user. Walks the exception
 * chain: if the ROOT cause is a raw system/driver failure (a
 * PDOException, TypeError, or Error — never something House.php or
 * MediaService.php deliberately throws with a human-readable
 * message), $fallback is returned instead and nothing about the
 * real cause reaches the response body. The real exception is
 * still logged separately, in full, by the caller.
 */
function lux_public_error_message(Throwable $e, string $fallback): string
{
    $root = $e;

    while ($root->getPrevious() !== null) {
        $root = $root->getPrevious();
    }

    if ($root instanceof PDOException || $root instanceof TypeError || $root instanceof Error) {
        return $fallback;
    }

    return preg_replace('/^Error (creating|updating) house:\s*/', '', $e->getMessage());
}

$videoInflightReserved = false;

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

    $hasParking = (($_POST['has_parking'] ?? '0') === '1') ? 1 : 0;

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

    if (!Validator::isValidHouseTitle($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title must be 3–255 characters.']);
        exit;
    }

    if (!Validator::isValidLocationText($location)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Location must be 2–255 characters.']);
        exit;
    }

    if (!Validator::isValidHouseType($houseType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid property type.']);
        exit;
    }

    if (!Validator::isValidDescription($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Description is too long.']);
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

    if (($_POST['latitude'] ?? '') !== '' && !Validator::isValidLatitude((string) $_POST['latitude'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Latitude must be between -90 and 90.']);
        exit;
    }

    if (($_POST['longitude'] ?? '') !== '' && !Validator::isValidLongitude((string) $_POST['longitude'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Longitude must be between -180 and 180.']);
        exit;
    }

    if (!Validator::isValidRoomCount((string) ($_POST['bedrooms'] ?? '1'))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bedrooms must be a whole number between 0 and 20.']);
        exit;
    }

    if (!Validator::isValidRoomCount((string) ($_POST['bathrooms'] ?? '1'))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bathrooms must be a whole number between 0 and 20.']);
        exit;
    }
            
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

    if (isset($files['images']) && is_array($files['images']['name'] ?? null)) {
        foreach ($files['images']['error'] as $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $hasImages = true;
                break;
            }
        }
    }

    $hasVideo = isset($files['video']) && is_array($files['video'])
        && ($files['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasImages && $hasVideo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A property can contain multiple images or one video, not both.']);
        exit;
    }

    require_once '../../classes/PlanLimits.php';

    // Admins aren't subject to a landlord's own plan limits — everyone
    // else (the ownership check above already guarantees role === 'landlord' here) is.
    if ($role !== 'admin') {

        $planLimits = PlanLimits::forLandlord($currentUser);

        // Only the Pro tier ever has video_allowed = true, so this is a
        // reliable, zero-extra-query way to tell which tier we're
        // dealing with without needing to touch PlanLimits.php.
        $isPro = !empty($planLimits['video_allowed']);

        if ($hasImages) {
            $imageCount = 0;
            foreach ($files['images']['error'] as $error) {
                if ($error !== UPLOAD_ERR_NO_FILE) $imageCount++;
            }

            if ($imageCount > $planLimits['max_images']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => $isPro
                        ? 'You can upload up to ' . $planLimits['max_images'] . ' images per property on your Pro plan. Remove some images from this listing and try again.'
                        : 'You can upload up to ' . $planLimits['max_images'] . ' images per property on your plan.',
                    'error_code' => 'IMAGE_LIMIT_REACHED',
                    'is_pro' => $isPro,
                ]);
                exit;
            }
        }

        if ($hasVideo && !$planLimits['video_allowed']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Video uploads are a Pro feature. Upgrade to add video to your listings.',
                'error_code' => 'VIDEO_REQUIRES_PRO',
                'is_pro' => false,
            ]);
            exit;
        }
    }

    if ($hasVideo) {
        require_once '../../config/security/RedisThrottle.php';

        $inFlightKey = "video:inflight:{$currentUser}";

        if (RedisThrottle::getCount($inFlightKey) >= MAX_VIDEOS_PROCESSING_PER_LANDLORD) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'You already have a video being processed. Please wait for it to finish before uploading another.']);
            exit;
        }

        RedisThrottle::increment($inFlightKey);
        $videoInflightReserved = true;
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
            'has_parking' => $hasParking,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'files' => $files,
            'requesting_user_id' => $currentUser,
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

    if ($videoInflightReserved) {
        require_once '../../config/security/RedisThrottle.php';
        RedisThrottle::decrement("video:inflight:{$currentUser}");
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (RuntimeException $e) {

    if ($videoInflightReserved) {
        require_once '../../config/security/RedisThrottle.php';
        RedisThrottle::decrement("video:inflight:{$currentUser}");
    }

    // Full detail (including any raw DB error text) goes to the log
    // only. The user gets a generic message unless the failure was
    // one of our own deliberately human-readable exceptions.
    error_log(
        '[LUX EMPIRE] Update House Error: '
        . $e->getMessage()
    );

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => lux_public_error_message(
            $e,
            "We couldn't update this property right now. Please try again, and contact support if this continues."
        )
    ]);

} catch (Throwable $e) {

    if ($videoInflightReserved) {
        require_once '../../config/security/RedisThrottle.php';
        RedisThrottle::decrement("video:inflight:{$currentUser}");
    }

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
