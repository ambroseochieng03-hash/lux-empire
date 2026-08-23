<?php

require_once '../../config/app.php';

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: '. BASE_URL . '/login");
    exit();
}

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$token = trim($_POST['token'] ?? '');

$password = trim($_POST['password'] ?? '');

$confirmPassword = trim(
    $_POST['confirm_password'] ?? ''
);

/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (
    empty($token) ||
    empty($password) ||
    empty($confirmPassword)
) {

    header(
        "Location: '. BASE_URL . '/login?error="
        . urlencode("Invalid reset request.")
    );

    exit();
}

if ($password !== $confirmPassword) {

    header(
        "Location: '. BASE_URL . '/reset-password?token="
        . urlencode($token)
        . "&error="
        . urlencode("Passwords do not match.")
    );

    exit();
}

if (strlen($password) < 8) {

    header(
        "Location: '. BASE_URL . '/reset-password?token="
        . urlencode($token)
        . "&error="
        . urlencode(
            "Empire key must contain at least 8 characters."
        )
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| HASH TOKEN
|--------------------------------------------------------------------------
*/

$hashedToken = hash(
    'sha256',
    $token
);

/*
|--------------------------------------------------------------------------
| FIND TOKEN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM password_resets
    WHERE token = ?
    LIMIT 1
");

$stmt->execute([$hashedToken]);

$reset = $stmt->fetch();

if (!$reset) {

    header(
        "Location: '. BASE_URL . '/login?error="
        . urlencode("Invalid reset token.")
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| CHECK USED
|--------------------------------------------------------------------------
*/

if ((int)$reset['used'] === 1) {

    header(
        "Location: '. BASE_URL . '/login?error="
        . urlencode("This reset link has already been used.")
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| CHECK EXPIRY
|--------------------------------------------------------------------------
*/

if (
    strtotime($reset['expires_at']) < time()
) {

    header(
        "Location: '. BASE_URL . '/login?error="
        . urlencode("Reset link expired.")
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| HASH NEW PASSWORD
|--------------------------------------------------------------------------
*/

$newPassword = password_hash(
    $password,
    PASSWORD_ARGON2ID
);

/*
|--------------------------------------------------------------------------
| UPDATE USER PASSWORD
|--------------------------------------------------------------------------
*/

$update = $pdo->prepare("
    UPDATE users
    SET password = ?
    WHERE email = ?
");

$success = $update->execute([
    $newPassword,
    $reset['email']
]);

if (!$success) {

    header(
        "Location: '. BASE_URL . '/login?error="
        . urlencode("Failed to update password.")
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| MARK TOKEN USED
|--------------------------------------------------------------------------
*/

$markUsed = $pdo->prepare("
    UPDATE password_resets
    SET used = 1
    WHERE id = ?
");

$markUsed->execute([
    $reset['id']
]);

/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

header(
    "Location: '. BASE_URL . '/login?success="
    . urlencode("Empire key updated successfully.")
);

exit();
?>