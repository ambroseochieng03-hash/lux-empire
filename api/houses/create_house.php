<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../classes/House.php';
require_once '../../config/session.php';

/**
 * Must be logged in
 */
requireLogin();

/**
 * Only landlords allowed
 */
if ($_SESSION['role'] !== 'landlord') {
    header("Location: ../../index.php?error=Unauthorized access");
    exit();
}

/**
 * Only POST allowed
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard/landlord/add_house.php");
    exit();
}

/**
 * Collect data
 */
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = trim($_POST['price'] ?? '');
$location = trim($_POST['location'] ?? '');
$bedrooms = trim($_POST['bedrooms'] ?? 0);
$bathrooms = trim($_POST['bathrooms'] ?? 0);
$landlord_id = $_SESSION['user_id'];
$rating = (int) ($_POST['rating'] ?? 0);

/**
 * Basic validation
 */
if (empty($title) || empty($price) || empty($location)) {
    header("Location: ../../dashboard/landlord/add_house.php?error=Missing required fields");
    exit();
}

/**
 * IMAGE UPLOAD WITH COMPRESSION
 */
$imageName = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

    /**
     * FILE SIZE LIMIT
     */
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($_FILES['image']['size'] > $maxSize) {
        header("Location: ../../dashboard/landlord/add_house.php?error=Image must be below 10MB");
        exit();
    }

    /**
     * STRICT IMAGE VALIDATION (reject pdf, videos, docs, etc.)
     */
    $tmpFile = $_FILES['image']['tmp_name'];

    $imageInfo = getimagesize($tmpFile);

    if ($imageInfo === false) {
        header("Location: ../../dashboard/landlord/add_house.php?error=Only image files are allowed");
        exit();
    }

    $uploadDir = "../../assets/uploads/house_images/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $tmpFile = $_FILES['image']['tmp_name'];

    $imageInfo = getimagesize($tmpFile);

    if (!$imageInfo) {
        header("Location: ../../dashboard/landlord/add_house.php?error=Invalid image");
        exit();
    }

    $mime = $imageInfo['mime'];

    switch ($mime) {

        case 'image/jpeg':
            $source = imagecreatefromjpeg($tmpFile);
            break;

        case 'image/png':
            $source = imagecreatefrompng($tmpFile);
            break;

        case 'image/webp':
            $source = imagecreatefromwebp($tmpFile);
            break;

        default:
            header("Location: ../../dashboard/landlord/add_house.php?error=Unsupported image format");
            exit();
    }

    $originalWidth = imagesx($source);
    $originalHeight = imagesy($source);

    $maxWidth = 1600;

    if ($originalWidth > $maxWidth) {

        $newWidth = $maxWidth;
        $newHeight = floor(($originalHeight * $newWidth) / $originalWidth);

    } else {

        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
    }

    $compressed = imagecreatetruecolor($newWidth, $newHeight);

    imagecopyresampled(
        $compressed,
        $source,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $originalWidth,
        $originalHeight
    );

    $imageName = uniqid('house_', true) . '.webp';

    $targetFile = $uploadDir . $imageName;

    if (!imagewebp($compressed, $targetFile, 82)) {
        header("Location: ../../dashboard/landlord/add_house.php?error=Failed to save image");
        exit();
    }

    imagedestroy($source);
    imagedestroy($compressed);
}

/**
 * Create house
 */
$house = new House();

$result = $house->createHouse([
    'title' => $title,
    'description' => $description,
    'price' => $price,
    'location' => $location,
    'bedrooms' => $bedrooms,
    'bathrooms' => $bathrooms,
    'image' => $imageName,
    'landlord_id' => $landlord_id,
    'rating' => $rating
]);

/**
 * Response
 */
if ($result) {
    header("Location: ../../dashboard/landlord/manage_houses.php?success=House added successfully");
    exit();
} else {
    header("Location: ../../dashboard/landlord/add_house.php?error=Failed to add house");
    exit();
}
?>