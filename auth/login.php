<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<section class="hero" style="min-height: 100vh; padding-top: 120px;">

    <div class="lux-card" style="
        max-width: 600px;
        width: 100%;
        margin: auto;
        padding: 50px;
        border-radius: 30px;
    ">

        <!-- Crown Identity -->
        <div style="text-align:center; margin-bottom:30px;">
            <div style="font-size:4rem;">👑</div>

            <h1 style="
                font-family:'Cinzel', serif;
                color: var(--gold);
                font-size: 2.8rem;
                margin-bottom:10px;
            ">
                Enter The Empire
            </h1>

            <p style="color: var(--gray);">
                Access your luxury world of property, movement, and prestige.
            </p>
        </div>

        <!-- Success -->
        <?php if (isset($_GET['success'])): ?>
            <div style="
                background: rgba(0,255,100,0.08);
                border: 1px solid rgba(0,255,100,0.25);
                padding: 12px;
                border-radius: 12px;
                margin-bottom: 20px;
                color: #b8ffd2;
                text-align:center;
            ">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Error -->
        <?php if (isset($_GET['error'])): ?>
            <div style="
                background: rgba(255,0,0,0.08);
                border: 1px solid rgba(255,0,0,0.25);
                padding: 12px;
                border-radius: 12px;
                margin-bottom: 20px;
                color: #ffb3b3;
                text-align:center;
            ">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="<?php echo BASE_URL; ?>/login-handler" method="POST">

            <!-- Email -->
            <div style="margin-bottom:18px;">
                <label>Email Address</label>
                <input type="email" name="email" required
                    placeholder="Enter your empire email"
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Password -->
            <div style="margin-bottom:25px;">
                <label>Password</label>
                <input type="password" name="password" required
                    placeholder="Your secure empire key"
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Submit -->
            <button type="submit" class="lux-btn" style="
                width:100%;
                padding:16px;
                font-size:1.1rem;
                border-radius:16px;
            ">
                <i class="fa-solid fa-right-to-bracket"></i> Enter Now
            </button>

        </form>

        <!-- Extra Links -->
        <div style="text-align:center; margin-top:25px;">
            <p style="margin-bottom:10px;">
                <a href="forgot_password.php" style="color:var(--gold); text-decoration:none;">
                    Forgotten your Empire key?
                </a>
            </p>

            <p style="color:var(--gray);">
                New to the Empire?
                <a href="register.php" style="color:var(--gold); text-decoration:none;">
                    Join Here
                </a>
            </p>
        </div>

    </div>

</section>

<?php require_once '../includes/footer.php'; ?>