<?php

require_once '../config/db.php';

require_once '../includes/header.php';
require_once '../includes/navbar.php';

$db = new Database();
$pdo = $db->connect();

$token = trim($_GET['token'] ?? '');

if (empty($token)) {

    die("Invalid reset token.");
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

    die("Invalid reset token.");
}

/*
|--------------------------------------------------------------------------
| CHECK EXPIRY
|--------------------------------------------------------------------------
*/

if (
    strtotime($reset['expires_at']) < time()
) {

    die("Reset link expired.");
}

/*
|--------------------------------------------------------------------------
| CHECK USED
|--------------------------------------------------------------------------
*/

if ((int)$reset['used'] === 1) {

    die("This reset link has already been used.");
}



?>

<section class="hero" style="min-height: 100vh; padding-top: 120px;">

    <div class="lux-card" style="
        max-width: 650px;
        width: 100%;
        margin: auto;
        padding: 50px;
        border-radius: 30px;
    ">

        <!-- Identity -->
        <div style="text-align:center; margin-bottom:30px;">
            <div style="font-size:4rem; color:var(--gold);"><i class="fa-solid fa-crown"></i></div>

            <h1 style="
                font-family:'Cinzel', serif;
                color: var(--gold);
                font-size: 2.6rem;
                margin-bottom:10px;
            ">
                Reset Empire Key
            </h1>

            <p style="color: var(--gray);">
                Create a new secure password and reclaim your empire.
            </p>
        </div>

        <!-- Error Message -->

        <?php if (isset($_GET['error'])): ?>

            <div style="
                background:rgba(255,77,77,0.08);
                border:1px solid rgba(255,77,77,0.25);
                color:#ffb3b3;
                padding:16px;
                border-radius:16px;
                margin-bottom:25px;
                text-align:center;
                box-shadow:0 10px 30px rgba(255,0,0,0.08);
            ">

                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($_GET['error']) ?>

            </div>

        <?php endif; ?>

        

        <!-- Form -->
        <form action="<?php echo BASE_URL; ?>/reset-handler" method="POST">

            <!-- New Password -->
            <div style="margin-bottom:18px;">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token); ?>"
                >

                <label>New Password</label>
                <input type="password" name="password" required
                    placeholder="New secure empire key"
                    style="
                        width:100%;
                        padding:14px;
                        margin-top:8px;
                        border-radius:14px;
                        border:none;
                    ">
            </div>

            <!-- Confirm -->
            <div style="margin-bottom:25px;">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required
                    placeholder="Confirm your new key"
                    style="
                        width:100%;
                        padding:14px;
                        margin-top:8px;
                        border-radius:14px;
                        border:none;
                    ">
            </div>

            <!-- Submit -->
            <button type="submit" class="lux-btn" style="
                width:100%;
                padding:16px;
                font-size:1.1rem;
                border-radius:16px;
            ">
                <i class="fa-solid fa-lock"></i> Update Empire Key
            </button>

        </form>

        <!-- Back -->
        <div style="text-align:center; margin-top:25px;">
            <a href="<?php echo BASE_URL; ?>/login" style="color:var(--gold); text-decoration:none;">
                Return to Empire Gate
            </a>
        </div>

    </div>

</section>

<?php require_once '../includes/footer.php'; ?>