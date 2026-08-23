<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

$user = Session::user();
$currentUserId = (int) $user['id'];

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/chat_panel.php';
require_once '../../includes/footer.php';
