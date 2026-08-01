<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
    die("Invalid request");
}

/*
|--------------------------------------------------------------------------
| VALIDATE STATUS (IMPORTANT)
|--------------------------------------------------------------------------
*/

$allowedStatuses = ['active', 'responding', 'resolved', 'dismissed'];

$status = strtolower(trim($status));

if (!in_array($status, $allowedStatuses)) {
    die("Invalid status value");
}

/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE emergency_alerts
    SET status = ?
    WHERE id = ?
");

$stmt->execute([$status, $id]);

/*
|--------------------------------------------------------------------------
| REDIRECT BACK
|--------------------------------------------------------------------------
*/

header("Location: ../../dashboard/admin/emergency.php?success=updated");
exit();