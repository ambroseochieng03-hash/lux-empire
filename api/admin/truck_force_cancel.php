<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/AdminTruckService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { adminJsonError('Method not allowed.', 405); }

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$requestId) { adminJsonError('Invalid trip.'); }
if ($reason === '') { adminJsonError('A reason is required.'); }

$result = (new AdminTruckService())->forceCancelActiveTrip($requestId, $currentAdminId, $reason);
if (!$result['success']) { adminJsonError($result['message'], $result['code'] ?? 400); }
adminJsonResponse(['status' => 'cancelled']);
