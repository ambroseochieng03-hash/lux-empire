<?php
require_once '../../config/session.php';
require_once '../../classes/House.php';

/**
 * Login required
 */
requireLogin();

/**
 * Landlords only
 */
if ($_SESSION['role'] !== 'landlord') {
    header("Location: ../../index.php?error=Unauthorized");
    exit();
}

/**
 * Validate ID
 */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../../dashboard/landlord/manage_houses.php?error=Invalid property");
    exit();
}

$house_id = (int) $_GET['id'];
$landlord_id = (int) Session::user()['id'];

$houseModel = new House();

/**
 * Ownership verification
 */
if (!$houseModel->belongsToLandlord($house_id, $landlord_id)) {

    header("Location: ../../dashboard/landlord/manage_houses.php?error=Access denied");
    exit();
}

/**
 * Get house first
 * (for image cleanup)
 */
$house = $houseModel->getHouseById($house_id);

/**
 * Delete house
 */
$deleted = $houseModel->deleteHouse($house_id);

if ($deleted) {

    /**
     * Remove image file
     */
    if (!empty($house['image'])) {

        $imagePath = "../../assets/uploads/house_images/" . $house['image'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    header("Location: ../../dashboard/landlord/manage_houses.php?success=Property deleted successfully");
    exit();

} else {

    header("Location: ../../dashboard/landlord/manage_houses.php?error=Failed to delete property");
    exit();
}
?>