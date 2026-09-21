<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentWaiver.php';

$waiverId = filter_input(INPUT_GET, 'waiver_id', FILTER_VALIDATE_INT);

if (!$waiverId) {
    adminJsonError('Invalid voucher.');
}

adminJsonResponse(['events' => PaymentWaiver::getHistory($waiverId)]);
