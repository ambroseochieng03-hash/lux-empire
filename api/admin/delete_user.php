<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$user_id = $_POST['user_id'] ?? null;

if (!$user_id) {
    die("Invalid user.");
}

/*
|--------------------------------------------------------------------------
| DO NOT DELETE ADMINS
|--------------------------------------------------------------------------
*/

$check = $pdo->prepare("
    SELECT role
    FROM users
    WHERE id = ?
");

$check->execute([$user_id]);

$user = $check->fetch();

if (!$user) {
    die("User not found.");
}

if ($user['role'] === 'admin') {
    die("Admin accounts cannot be deleted.");
}

/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/

$delete = $pdo->prepare("
    DELETE FROM users
    WHERE id = ?
");

$delete->execute([$user_id]);

header(
    'Location: ' . BASE_URL . '/admin/users'
);

exit();
?>