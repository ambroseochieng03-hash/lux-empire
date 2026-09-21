<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentWaiver.php';
require_once __DIR__ . '/../../config/security/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$target = trim((string) ($_POST['target'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$amount = (int) ($_POST['amount'] ?? 0);
$unit = trim((string) ($_POST['unit'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));

if ($reason === '') {
    adminJsonError('A reason is required.');
}

if (PaymentWaiver::normalizeDuration($amount, $unit) === null) {
    adminJsonError('Choose a valid duration: up to 720 hours, 365 days or 52 weeks.');
}

try {

    if ($target === 'user') {

        $person = PaymentWaiver::findUserByEmail($email);

        if ($person === null) {
            adminJsonError('No account found with that email.', 404);
        }

        $kind = PaymentWaiver::kindForRole((string) $person['role']);

        if ($kind === null || $kind === PaymentWaiver::KIND_DRIVER) {
            adminJsonError('Vouchers for this account type are not enabled yet.', 409);
        }

        $result = PaymentWaiver::grantToUser((int) $person['id'], (string) $person['role'], $kind, $amount, $unit, $reason, $currentAdminId);

        if (!$result['success']) {
            adminJsonError($result['message'], 409);
        }

        PaymentWaiver::notifyGranted((int) $person['id'], $kind, $result['expires_at']);

        Audit::log("Admin #{$currentAdminId} granted a {$kind} voucher to user #{$person['id']} ({$reason})", $currentAdminId);

        adminJsonResponse(['message' => PaymentWaiver::kindLabel($kind) . ' voucher granted to ' . $person['full_name'] . '.']);
        exit;
    }

    if ($target === 'role:tenant' || $target === 'role:landlord') {

        $role = substr($target, 5);

        $result = PaymentWaiver::grantToRole($role, $amount, $unit, $reason, $currentAdminId);

        Audit::log("Admin #{$currentAdminId} granted {$result['count']} {$role} voucher(s) in bulk ({$reason})", $currentAdminId);

        adminJsonResponse([
            'count' => $result['count'],
            'message' => $result['count'] > 0
                ? $result['count'] . ' voucher(s) granted.'
                : 'Nobody needed one — every active ' . $role . ' already holds a live voucher.',
        ]);
        exit;
    }

    adminJsonError('Invalid target.');

} catch (InvalidArgumentException $e) {
    adminJsonError($e->getMessage());
} catch (Throwable $e) {
    error_log('LUX EMPIRE admin waiver_grant error: ' . $e->getMessage());
    adminJsonError('Could not grant the voucher.', 500);
}
