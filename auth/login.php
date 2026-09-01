<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth-forms.css">

<section class="auth-hero">

    <div class="lux-card auth-card">

        <div class="auth-header">
            <h1 class="auth-title">Enter The Empire</h1>
            <p class="auth-subtitle">Access your luxury world of property, movement, and prestige.</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="auth-alert auth-alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/login-handler" method="POST">

            <div class="auth-field">
                <label for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" required
                       placeholder="Enter your empire email">
            </div>

            <div class="auth-field">
                <label for="loginPassword">Password</label>
                <input type="password" id="loginPassword" name="password" required
                       placeholder="Your secure empire key">
            </div>

            <button type="submit" class="lux-btn auth-submit-btn">
                <i class="fa-solid fa-right-to-bracket"></i> Enter Now
            </button>

        </form>

        <div class="auth-footer-link">
            <p>
                <a href="<?php echo BASE_URL; ?>/forgot-password">
                    Forgotten your Empire key?
                </a>
            </p>

            <p>
                New to the Empire?
                <a href="#" data-open-role-select>Join Here</a>
            </p>
        </div>

    </div>

</section>

<?php require_once '../includes/footer.php'; ?>