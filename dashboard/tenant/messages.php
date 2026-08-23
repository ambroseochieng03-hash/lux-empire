<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

$user = Session::user();
$currentUserId = (int) $user['id'];

$autoOpenWithUserId = isset($_GET['with']) ? (int) $_GET['with'] : null;
$autoOpenHouseId = $_GET['house_id'] ?? null;
$autoOpenRole = 'landlord'; // tenant/messages only auto-creates landlord threads for now

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/chat_panel.php';
require_once '../../includes/footer.php';
