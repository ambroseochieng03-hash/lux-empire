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

        <!-- Identity -->
        <div style="text-align:center; margin-bottom:30px;">
            <div style="font-size:4rem;">🔐</div>

            <h1 style="
                font-family:'Cinzel', serif;
                color: var(--gold);
                font-size: 2.5rem;
                margin-bottom:10px;
            ">
                Recover Empire Access
            </h1>

            <p style="color: var(--gray);">
                Enter your registered empire email and reclaim your kingdom.
            </p>
        </div>

        <!-- Info -->
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

        <!-- Form -->
        <form action="../api/auth/forgot_handler.php" method="POST">

            <div style="margin-bottom:25px;">
                <label>Email Address</label>
                <input type="email" name="email" required
                    placeholder="your@email.com"
                    style="
                        width:100%;
                        padding:14px;
                        margin-top:8px;
                        border-radius:14px;
                        border:none;
                    ">
            </div>

            <button type="submit" class="lux-btn" style="
                width:100%;
                padding:16px;
                font-size:1.1rem;
                border-radius:16px;
            ">
                👑 Continue Recovery
            </button>

        </form>

        <!-- Back -->
        <div style="text-align:center; margin-top:25px;">
            <a href="login.php" style="color:var(--gold); text-decoration:none;">
                Return to Empire Gate
            </a>
        </div>

    </div>

</section>

<?php require_once '../includes/footer.php'; ?>