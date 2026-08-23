<?php

require_once '../../config/app.php';

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: " . BASE_URL . "/forgot-password");
    exit();
}

$db = new Database();
$pdo = $db->connect();

$email = trim($_POST['email'] ?? '');

if (empty($email)) {

    header(
        "Location: " . BASE_URL . "/forgot-password?error=Email is required"
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| CHECK USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$email]);

$user = $stmt->fetch();

if (!$user) {

    header(
        "Location: '. BASE_URL . '/forgot-password?error=Account not found"
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| GENERATE SECURE TOKEN
|--------------------------------------------------------------------------
*/

$rawToken = bin2hex(random_bytes(32));

$hashedToken = hash(
    'sha256',
    $rawToken
);

/*
|--------------------------------------------------------------------------
| EXPIRY TIME (8 MINUTES)
|--------------------------------------------------------------------------
*/

$expiresAt = date(
    'Y-m-d H:i:s',
    strtotime('+8 minutes')
);

/*
|--------------------------------------------------------------------------
| DELETE OLD TOKENS
|--------------------------------------------------------------------------
*/

$delete = $pdo->prepare("
    DELETE FROM password_resets
    WHERE email = ?
");

$delete->execute([$email]);

/*
|--------------------------------------------------------------------------
| STORE NEW TOKEN
|--------------------------------------------------------------------------
*/

$insert = $pdo->prepare("
    INSERT INTO password_resets (
        email,
        token,
        expires_at
    )
    VALUES (?, ?, ?)
");

$insert->execute([
    $email,
    $hashedToken,
    $expiresAt
]);

/*
|--------------------------------------------------------------------------
| RESET LINK
|--------------------------------------------------------------------------
*/

$resetLink =
    BASE_URL
    . '/reset-password?token='
    . urlencode($rawToken);

/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
*/

require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = require '../../config/mail.php';

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();

    $mail->Host = $mailConfig['host'];

    $mail->SMTPAuth = true;

    $mail->Username = $mailConfig['username'];

    $mail->Password = $mailConfig['password'];

    $mail->SMTPSecure = $mailConfig['encryption'];

    $mail->Port = $mailConfig['port'];

    $mail->setFrom(
        $mailConfig['from_email'],
        $mailConfig['from_name']
    );

    $mail->addAddress($email);

    $mail->isHTML(true);

    $mail->Subject =
        'LUX EMPIRE Password Reset';

    $mail->Body = "
        <div style='
            font-family:Arial;
            padding:30px;
            background:#0a0f1c;
            color:white;
        '>

            <h1 style='color:gold;'>
                🔐 Password Recovery
            </h1>

            <p>
                We received a password reset request
                for your LUX EMPIRE account.
            </p>

            <p>
                Click the secure button below
                to reset your password:
            </p>

            <a href='{$resetLink}'
                style='
                    display:inline-block;
                    padding:14px 24px;
                    background:gold;
                    color:black;
                    border-radius:12px;
                    text-decoration:none;
                    font-weight:bold;
                    margin-top:20px;
                '>
                Reset Password
            </a>

            <p style='margin-top:30px; color:#999;'>
                This link expires in 8 minutes.
            </p>

            <p style='color:#999;'>
                If you did not request this,
                ignore this email.
            </p>

        </div>
    ";

    $mail->send();

    header(
        "Location: '. BASE_URL . '/forgot-password?success="
        . urlencode(
            "Password reset link sent, if the email exists."
        )
    );

    exit();

} catch (Exception $e) {

    header(
        "Location: '. BASE_URL . '/forgot-password?error="
        . urlencode(
            "Failed to send recovery email."
        )
    );

    exit();
}
?>