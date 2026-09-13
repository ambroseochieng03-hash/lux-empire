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

if ($title === '' || $price === '' || $location === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title, price and location are required.']);
    exit;
}

if (!is_numeric($price) || (float) $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property price.']);
    exit;
}

$price = (float) $price;
$bedrooms = $bedrooms !== '' ? (int) $bedrooms : 1;
$bathrooms = $bathrooms !== '' ? (int) $bathrooms : 1;

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

if ($houseModelForLimits->countListingsByLandlord($landlordId) >= $planLimits['max_listings']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => "You've reached the " . $planLimits['max_listings'] . "-listing limit for your plan.",
        'error_code' => 'LISTING_LIMIT_REACHED',
    ]);
    exit;
}

if (count($images) > $planLimits['max_images']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You can upload up to ' . $planLimits['max_images'] . ' images per property on your plan.',
        'error_code' => 'IMAGE_LIMIT_REACHED',
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
        ]);
        exit;
    }

    $inFlightKey = "video:inflight:{$landlordId}";
    $dailyKey = "video:daily:{$landlordId}:" . date('Y-m-d');

    if (RedisThrottle::getCount($inFlightKey) >= MAX_VIDEOS_PROCESSING_PER_LANDLORD) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'You already have a video being processed. Please wait for it to finish before uploading another.']);
        exit;
    }

    if (RedisThrottle::incrWithExpiry($dailyKey, 86400) > MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => "You've reached today's video upload limit (" . MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY . "). Please try again tomorrow."]);
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

try {

    $house = new House();

    if ($video !== null) {
        RedisThrottle::increment("video:inflight:{$landlordId}");
    }

    $houseId = $house->createHouse([
        'title' => $title, 'description' => $description, 'price' => $price,
        'location' => $location, 'bedrooms' => $bedrooms, 'bathrooms' => $bathrooms,
        'house_type' => $houseType, 'rating' => $rating,
        'latitude' => $latitude, 'longitude' => $longitude,
        'landlord_id' => $landlordId, 'images' => $images, 'video' => $video,
    ]);

    if ($houseId > 0) {
        $responseCode = 200;
        $responseBody = json_encode(['success' => true, 'message' => 'Luxury property published successfully.', 'house_id' => $houseId]);
        $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
        http_response_code($responseCode);
        echo $responseBody;
        exit;
    }

    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'message' => 'Failed to publish property.']);
    $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;

} catch (Throwable $e) {

    error_log('[' . date('Y-m-d H:i:s') . '] House creation error: ' . $e->getMessage());

    $responseCode = 500;
    $responseBody = json_encode(['success' => false, 'message' => $e->getMessage()]);
    $idempotency->complete($idempotencyKey, 'create_house', $responseCode, $responseBody);
    http_response_code($responseCode);
    echo $responseBody;
    exit;
}