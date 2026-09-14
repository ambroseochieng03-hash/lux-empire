<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../classes/PaymentWaiver.php';
require_once '../../classes/Notification.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$adminId = (int) Session::user()['id'];
$formAction = $_POST['form_action'] ?? '';

if ($formAction === 'grant') {

    $scope = $_POST['scope'] ?? '';
    $days = max(1, min(365, (int) ($_POST['days'] ?? 30)));
    $reason = trim($_POST['reason'] ?? '');
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

    if ($scope === 'user') {

        $email = trim($_POST['email'] ?? '');
        $target = PaymentWaiver::findUserByEmail($email);

        if ($target === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
            exit;
        }

        PaymentWaiver::grant('user', (int) $target['id'], null, $reason, $adminId, $expiresAt);

        (new Notification())->create(
            (int) $target['id'],
            'waiver_granted',
            'Complimentary Access Granted',
            "You've been granted complimentary access until " . date('d M Y', strtotime($expiresAt)) . '.',
            null
        );

        echo json_encode(['success' => true, 'message' => 'Waiver granted to ' . $target['full_name'] . '.']);
        exit;
    }

    if (str_starts_with($scope, 'role:')) {

        $roleValue = substr($scope, 5);
        $rolesToGrant = $roleValue === 'all' ? ['tenant', 'landlord', 'driver'] : [$roleValue];

        foreach ($rolesToGrant as $role) {
            PaymentWaiver::grant('role', null, $role, $reason, $adminId, $expiresAt);
        }

        echo json_encode(['success' => true, 'message' => 'Waiver granted for: ' . implode(', ', $rolesToGrant) . '.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid scope.']);
    exit;
}

if ($formAction === 'revoke') {

    $waiverId = (int) ($_POST['waiver_id'] ?? 0);

    if ($waiverId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    PaymentWaiver::revoke($waiverId);

    echo json_encode(['success' => true, 'message' => 'Waiver revoked.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
