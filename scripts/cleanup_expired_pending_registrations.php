<?php
// scripts/cleanup_expired_pending_registrations.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

$pdo = (new Database())->connect();

// A pending user with no unconsumed OTP left, older than 30 minutes,
// never verified — safe to purge. FK ON DELETE CASCADE handles
// the drivers row automatically.
$stmt = $pdo->prepare("
    DELETE u FROM users u
    LEFT JOIN login_otps o
        ON o.user_id = u.id AND o.purpose = 'registration' AND o.consumed_at IS NULL AND o.expires_at > NOW()
    WHERE u.status = 'pending'
    AND u.created_at < (NOW() - INTERVAL 30 MINUTE)
    AND o.id IS NULL
");
$stmt->execute();

echo "Purged {$stmt->rowCount()} stale pending registrations.\n";
