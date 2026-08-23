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

$stmt = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = ?
");

$stmt->execute([$user_id]);

header(
    'Location: ' . BASE_URL . '/admin/users'
);

exit();
?>