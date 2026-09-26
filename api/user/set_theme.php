<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
require_once '../../classes/User.php';
require_once '../../config/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$theme = $_POST['theme'] ?? '';

if (!in_array($theme, ['light', 'dark'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid theme value.']);
    exit;
}

$userId = (int) Session::user()['id'];

$userModel = new User();
$updated = $userModel->updateThemePreference($userId, $theme);

echo json_encode(['success' => $updated]);
exit;
