<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Shared bootstrap for every AJAX admin API endpoint under
 * api/admin/. Include this INSTEAD of includes/auth_check.php
 * directly — it requires auth_check.php itself, then adds CSRF
 * validation (state-changing requests only) and JSON helpers.
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/security/Audit.php';

requireRoleAccess('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $submittedCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    Csrf::requireValid($submittedCsrf);
}

$currentAdmin = Session::user();
$currentAdminId = (int) ($currentAdmin['id'] ?? 0);

function adminJsonResponse(array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
}

function adminJsonError(string $message, int $statusCode = 400): never
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}
