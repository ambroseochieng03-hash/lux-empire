<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/session.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';

/**
 * ============================================================
 * AUTHENTICATION
 * ============================================================
 */

Session::start();

if (!Session::isAuthenticated()) {

    header(
        'Location: ' . BASE_URL . '/login?error='
        . urlencode('Authentication required.')
    );

    exit();
}

$user = Session::user();

if (
    $user === null ||
    ($user['role'] ?? '') !== 'landlord'
) {

    header(
        'Location: ' . BASE_URL . '/?error='
        . urlencode('Unauthorized access.')
    );

    exit();
}

$landlordId = (int) $user['id'];

DoSProtection::check($landlordId);

/**
 * ============================================================
 * REQUEST METHOD
 * ============================================================
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        "Location: ../../dashboard/landlord/add_house.php"
    );

    exit();
}

/**
 * ============================================================
 * COLLECT INPUT
 * ============================================================
 */

$title = trim($_POST['title'] ?? '');

$description = trim(
    $_POST['description'] ?? ''
);

$price = trim(
    $_POST['price'] ?? ''
);

$location = trim(
    $_POST['location'] ?? ''
);

$bedrooms = trim(
    $_POST['bedrooms'] ?? ''
);

$bathrooms = trim(
    $_POST['bathrooms'] ?? ''
);

$houseType = trim(
    $_POST['house_type'] ?? ''
);

$rating = (int) (
    $_POST['rating'] ?? 0
);

$latitude = ($_POST['latitude'] ?? '') !== ''
    ? (float) $_POST['latitude']
    : null;

$longitude = ($_POST['longitude'] ?? '') !== ''
    ? (float) $_POST['longitude']
    : null;

$landlordId = (int) $landlordId;


/**
 * ============================================================
 * BASIC VALIDATION
 * ============================================================
 */

if (
    $title === '' ||
    $price === '' ||
    $location === ''
) {

    header(
        "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode('Title, price and location are required.')
    );

    exit();
}

if (!is_numeric($price) || (float) $price <= 0) {

    header(
        "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode('Invalid property price.')
    );

    exit();
}

$price = (float) $price;


/**
 * ============================================================
 * NORMALIZE NUMERIC VALUES
 * ============================================================
 */

$bedrooms = $bedrooms !== ''
    ? (int) $bedrooms
    : 1;

$bathrooms = $bathrooms !== ''
    ? (int) $bathrooms
    : 1;


/**
 * ============================================================
 * VALIDATE RATING
 * ============================================================
 */

if ($rating < 1 || $rating > 5) {
    $rating = 5;
}


/**
 * ============================================================
 * NORMALIZE MEDIA INPUT
 *
 * The API does not process media.
 *
 * House.php + MediaService.php handle:
 *
 * - MIME validation
 * - Image processing
 * - Video processing
 * - File naming
 * - Database media records
 * - Transaction handling
 * ============================================================
 */

$images = [];

$video = null;


/**
 * ------------------------------------------------------------
 * MULTIPLE IMAGES
 *
 * Expected frontend field:
 *
 * images[]
 * ------------------------------------------------------------
 */

if (
    isset($_FILES['images']) &&
    is_array($_FILES['images']['name'] ?? null)
) {

    $imageFiles = $_FILES['images'];

    foreach ($imageFiles['name'] as $index => $name) {

        if (
            !isset(
                $imageFiles['tmp_name'][$index],
                $imageFiles['error'][$index],
                $imageFiles['size'][$index]
            )
        ) {
            continue;
        }

        if ($imageFiles['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $images[] = [
            'name' => $imageFiles['name'][$index],
            'type' => $imageFiles['type'][$index] ?? '',
            'tmp_name' => $imageFiles['tmp_name'][$index],
            'error' => $imageFiles['error'][$index],
            'size' => $imageFiles['size'][$index]
        ];
    }
}


/**
 * ------------------------------------------------------------
 * SINGLE VIDEO
 *
 * Expected frontend field:
 *
 * video
 * ------------------------------------------------------------
 */

if (
    isset($_FILES['video']) &&
    is_array($_FILES['video']) &&
    ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_NO_FILE
) {

    $video = [
        'name' => $_FILES['video']['name'] ?? '',
        'type' => $_FILES['video']['type'] ?? '',
        'tmp_name' => $_FILES['video']['tmp_name'] ?? '',
        'error' => $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE,
        'size' => $_FILES['video']['size'] ?? 0
    ];
}


/**
 * ============================================================
 * DEFENSIVE MEDIA RULE
 *
 * The backend refuses both media types even if somebody
 * manually bypasses the frontend.
 * ============================================================
 */

if (!empty($images) && $video !== null) {

    header(
        "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode(
            'A property can contain multiple images or one video, not both.'
        )
    );

    exit();
}

if (count($images) > MAX_IMAGES_PER_HOUSE) {

    header(
        "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode(
            'You can upload up to ' . MAX_IMAGES_PER_HOUSE . ' images per property.'
        )
    );

    exit();
}

require_once '../../classes/House.php';
require_once '../../config/security/RedisThrottle.php';

$houseModelForLimits = new House();

if ($houseModelForLimits->countListingsByLandlord($landlordId) >= MAX_LISTINGS_PER_LANDLORD) {

    header(
        "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode(
            "You've reached the " . MAX_LISTINGS_PER_LANDLORD . "-listing limit for free accounts. Upgrade your account to add more properties."
        )
    );

    exit();
}

if ($video !== null) {

    $inFlightKey = "video:inflight:{$landlordId}";
    $dailyKey = "video:daily:{$landlordId}:" . date('Y-m-d');

    if (RedisThrottle::getCount($inFlightKey) >= MAX_VIDEOS_PROCESSING_PER_LANDLORD) {

        header(
            "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
            . urlencode('You already have a video being processed. Please wait for it to finish before uploading another.')
        );

        exit();
    }

    if (RedisThrottle::incrWithExpiry($dailyKey, 86400) > MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY) {

        header(
            "Location: " . BASE_URL . "/dashboard/landlord/add_house.php?error="
            . urlencode("You've reached today's video upload limit (" . MAX_VIDEO_UPLOADS_PER_LANDLORD_PER_DAY . "). Please try again tomorrow.")
        );

        exit();
    }
}


/**
 * ============================================================
 * CREATE HOUSE
 * ============================================================
 */

require_once '../../classes/IdempotencyGuard.php';

$idempotencyKey = trim($_POST['idempotency_key'] ?? '');

if ($idempotencyKey === '') {
    header("Location: ../../dashboard/landlord/add_house.php?error=" . urlencode('Invalid request.'));
    exit();
}

$idempotency = new IdempotencyGuard();
$guardResult = $idempotency->begin($idempotencyKey, 'create_house', $landlordId);

if ($guardResult['status'] === 'processing') {
    header("Location: ../../dashboard/landlord/add_house.php?error=" . urlencode('This listing is already being submitted.'));
    exit();
}

if ($guardResult['status'] === 'completed') {
    // Replay the original outcome rather than creating a second listing.
    header('Location: ' . $guardResult['response_body']);
    exit();
}

try {

    $house = new House();

    if ($video !== null) {
        RedisThrottle::increment("video:inflight:{$landlordId}");
    }

    $houseId = $house->createHouse([

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

        'landlord_id' => $landlordId,

        'images' => $images,

        'video' => $video

    ]);


    /**
     * ========================================================
     * SUCCESS
     * ========================================================
     */

    if ($houseId > 0) {

        $redirectUrl = BASE_URL . "/dashboard/landlord/manage_houses.php?success="
            . urlencode('Luxury property published successfully.');

        $idempotency->complete($idempotencyKey, 'create_house', 200, $redirectUrl);

        header("Location: " . $redirectUrl);
        exit();
    }


    /**
     * ========================================================
     * CREATION FAILED
     * ========================================================
     */

    $redirectUrl = BASE_URL . "/dashboard/landlord/add_house.php?error="
        . urlencode('Failed to publish property.');

    $idempotency->complete($idempotencyKey, 'create_house', 500, $redirectUrl);

    header("Location: " . $redirectUrl);
    exit();

} catch (Throwable $e) {

    error_log(
        '[' . date('Y-m-d H:i:s') . '] '
        . 'House creation error: '
        . $e->getMessage()
    );

    $redirectUrl = BASE_URL . "/dashboard/landlord/add_house.php?error=" . urlencode($e->getMessage());

    $idempotency->complete($idempotencyKey, 'create_house', 500, $redirectUrl);

    header("Location: " . $redirectUrl);
    exit();
}
