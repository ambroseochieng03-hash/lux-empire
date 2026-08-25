<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<section class="hero" style="min-height: 100vh; padding-top: 120px;">

    <div class="lux-card" style="
        max-width: 700px;
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
                Join The Empire
            </h1>

            <p style="color: var(--gray); font-size:1rem;">
                Enter a world of luxury living, elite property access, and powerful movement.
            </p>
        </div>

        <!-- Alerts -->
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

        <!-- Registration Form -->
        <form action="<?php echo BASE_URL; ?>/register-handler" method="POST" enctype="multipart/form-data">

            <!-- Full Name -->
            <div style="margin-bottom:18px;">
                <label>Full Name</label>
                <input type="text" name="full_name" required placeholder="Your royal identity"
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Email -->
            <div style="margin-bottom:18px;">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="example@luxempire.com"
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Phone -->
            <div style="margin-bottom:18px;">
                <label>Phone Number</label>
                <input type="text" name="phone" required placeholder="+254..."
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- National ID -->
            <div style="margin-bottom:18px;">
                <label>National ID / Identification</label>
                <input type="text" name="national_id" required placeholder="Identification number"
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Role -->
            <div style="margin-bottom:18px;">
                <label>Choose Your Empire Role</label>
                <select name="role" required
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
                    <option value="">Select Role</option>
                    <option value="tenant">Client / Tenant</option>
                    <option value="landlord">Property Owner / Landlord</option>
                    <option value="driver">Transport Partner / Driver</option>
                </select>
            </div>

            <!-- Password -->
            <div style="margin-bottom:18px;">
                <label>Password</label>
                <input type="password" name="password" required
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Confirm Password -->
            <div style="margin-bottom:25px;">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required
                    style="width:100%; padding:14px; margin-top:8px; border-radius:14px; border:none;">
            </div>

            <!-- Submit -->
            <button type="submit" class="lux-btn" style="
                width:100%;
                padding:16px;
                font-size:1.1rem;
                border-radius:16px;
            ">
                Claim Your Place
            </button>

        </form>

        <!-- Login Redirect -->
        <div style="text-align:center; margin-top:25px;">
            <p style="color:var(--gray);">
                Already part of the Empire?
                <a href="<?php echo BASE_URL; ?>/login" style="color:var(--gold); text-decoration:none;">
                    Enter Here
                </a>
            </p>
        </div>

    </div>

</section>

<?php require_once '../includes/footer.php'; ?>