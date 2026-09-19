<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/House.php';
require_once '../../classes/PlanLimits.php';
require_once '../../classes/IdempotencyGuard.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RedisThrottle.php';
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

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();

if ($user === null || ($user['role'] ?? '') !== 'landlord') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$landlordId = (int) $user['id'];
DoSProtection::check($landlordId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = trim($_POST['price'] ?? '');
$location = trim($_POST['location'] ?? '');
$bedrooms = trim($_POST['bedrooms'] ?? '');
$bathrooms = trim($_POST['bathrooms'] ?? '');
$houseType = trim($_POST['house_type'] ?? '');
$rating = (int) ($_POST['rating'] ?? 0);
$latitude = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
$longitude = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;
$hasParking = (($_POST['has_parking'] ?? '0') === '1') ? 1 : 0;

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

if ($title === '' || $price === '' || $location === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title, price and location are required.']);
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

if (!Validator::isValidPrice($price)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid price (numbers only, up to 2 decimal places).']);
    exit;
}

$price = (float) $price;
$bedrooms = $bedrooms !== '' ? (int) $bedrooms : 0;
$bathrooms = $bathrooms !== '' ? (int) $bathrooms : 0;

if ($rating < 1 || $rating > 5) {
    $rating = 5;
}

$images = [];
$video = null;

if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {

    foreach ($_FILES['images']['name'] as $index => $name) {

        if (!isset($_FILES['images']['tmp_name'][$index], $_FILES['images']['error'][$index], $_FILES['images']['size'][$index])) {
            continue;
        }

        if ($_FILES['images']['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $images[] = [
            'name' => $_FILES['images']['name'][$index],
            'type' => $_FILES['images']['type'][$index] ?? '',
            'tmp_name' => $_FILES['images']['tmp_name'][$index],
            'error' => $_FILES['images']['error'][$index],
            'size' => $_FILES['images']['size'][$index],
        ];
    }
}

if (isset($_FILES['video']) && is_array($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $video = [
        'name' => $_FILES['video']['name'] ?? '',
        'type' => $_FILES['video']['type'] ?? '',
        'tmp_name' => $_FILES['video']['tmp_name'] ?? '',
        'error' => $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE,
        'size' => $_FILES['video']['size'] ?? 0,
    ];
}

if (!empty($images) && $video !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A property can contain multiple images or one video, not both.']);
    exit;
}

$houseModelForLimits = new House();
$planLimits = PlanLimits::forLandlord($landlordId);

// Only the Pro tier ever has video_allowed = true, so this is a
// reliable, zero-extra-query way to tell which tier we're dealing
// with without needing to touch PlanLimits.php.
$isPro = !empty($planLimits['video_allowed']);

if ($houseModelForLimits->countListingsByLandlord($landlordId) >= $planLimits['max_listings']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $isPro
            ? "You've reached your Pro plan's " . $planLimits['max_listings'] . "-listing limit. Delete an existing listing, or edit one of your current listings instead."
            : "You've reached the " . $planLimits['max_listings'] . "-listing limit for your plan.",
        'error_code' => 'LISTING_LIMIT_REACHED',
        'is_pro' => $isPro,
    ]);
    exit;
}

if (count($images) > $planLimits['max_images']) {
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

if ($video !== null) {

    if (!$planLimits['video_allowed']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Video uploads are a Pro feature.',
            'error_code' => 'VIDEO_REQUIRES_PRO',
            'is_pro' => false,
        ]);
        exit;
    }

    $inFlightKey = "video:inflight:{$landlordId}";

    if (RedisThrottle::getCount($inFlightKey) >= MAX_VIDEOS_PROCESSING_PER_LANDLORD) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'You already have a video being processed. Please wait for it to finish before uploading another.']);
        exit;
    }
}

$idempotencyKey = trim($_POST['idempotency_key'] ?? '');

if ($idempotencyKey === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'create_house', $landlordId);

if ($guardResult['status'] === 'processing') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This listing is already being submitted.']);
    exit;
}

if ($guardResult['status'] === 'completed') {
    http_response_code((int) $guardResult['response_code']);
    echo $guardResult['response_body'];
    exit;
}

$videoInflightReserved = false;

try {

    $house = new House();

    if ($video !== null) {
        RedisThrottle::increment("video:inflight:{$landlordId}");
        $videoInflightReserved = true;
    }

    $houseId = $house->createHouse([
        'title' => $title, 'description' => $description, 'price' => $price,
        'location' => $location, 'bedrooms' => $bedrooms, 'bathrooms' => $bathrooms,
        'house_type' => $houseType, 'rating' => $rating, 'has_parking' => $hasParking,
        'latitude' => $latitude, 'longitude' => $longitude,
        'landlord_id' => $landlordId, 'images' => $images, 'video' => $video,
        'max_listings' => $planLimits['max_listings'],   // ← add this
    ]);

    if ($houseId > 0) {
        $responseCode = 200;
        $responseBody = json_encode(['success' => true, 'message' => 'Luxury property published successfully.', 'house_id' => $houseId]);
        $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
        http_response_code($responseCode);
        echo $responseBody;
        exit;
    }

    if ($videoInflightReserved) {
        RedisThrottle::decrement("video:inflight:{$landlordId}");
    }

    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'message' => 'Failed to publish property.']);
    $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;

} catch (Throwable $e) {

    // createHouse() threw before ever reaching its own video-publish
    // step (validation failure, DB error, staging failure, etc.) — the
    // internal decrement inside House.php never ran, so it's on us.
    if ($videoInflightReserved) {
        RedisThrottle::decrement("video:inflight:{$landlordId}");
    }

    // Full detail (including any raw DB error text) goes to the log
    // only. The user gets a generic message unless the failure was
    // one of our own deliberately human-readable exceptions.
    error_log('[' . date('Y-m-d H:i:s') . '] House creation error: ' . $e->getMessage());

    $publicMessage = lux_public_error_message(
        $e,
        "We couldn't publish this property right now. Please try again, and contact support if this continues."
    );

    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'message' => $publicMessage]);
    $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}