<?php
/**
 * LUX EMPIRE — TENANT REGISTRATION MODAL (partial)
 * SAVE AT: includes/tenant_register_modal.php
 *
 * Include this ONCE per page, anywhere after navbar.php. It starts
 * hidden — opened via JS by clicking any element with
 * data-open-tenant-register (see includes/nav_menu.php's "Register
 * as Tenant" link, which now triggers this instead of navigating).
 *
 * Requires config/csrf.php to already be required + Session started
 * by this point, so $csrfToken can be computed. Also requires
 * config/app.php for GOOGLE_OAUTH_CLIENT_ID.
 */
?>

<div class="tenant-register-modal" id="tenantRegisterModal" aria-hidden="true">

    <div class="tenant-register-modal-overlay" data-tr-close></div>

    <div class="tenant-register-modal-box" role="dialog" aria-modal="true">

        <button type="button" class="tenant-register-modal-close" data-tr-close aria-label="Close">×</button>

        <!-- STEP 1: details + Google -->
        <div class="tr-step" id="trStepDetails">

            <h2 class="tenant-register-modal-title">Join The Empire</h2>
            <p class="tenant-register-modal-subtitle">Create your tenant account</p>

            <div id="googleSignInButton" class="tr-google-btn-wrap"></div>

            <div class="tr-divider"><span>or</span></div>

            <div class="tr-error" id="trDetailsError" hidden></div>

            <div class="tenant-register-modal-field">
                <label for="trFullName">Full Name</label>
                <input type="text" id="trFullName" placeholder="Your full name">
            </div>

            <div class="tenant-register-modal-field">
                <label for="trEmail">Email Address</label>
                <input type="email" id="trEmail" placeholder="you@example.com">
            </div>

            <div class="tenant-register-modal-field">
                <label for="trPhone">Phone Number (optional)</label>
                <input type="text" id="trPhone" placeholder="+254...">
            </div>

            <div class="tenant-register-modal-field">
                <label for="trPassword">Password</label>
                <input type="password" id="trPassword" placeholder="At least 8 characters">
            </div>

            <button type="button" class="lux-btn tenant-register-modal-submit" id="trSubmitDetails">
                Continue
            </button>

        </div>

        <!-- STEP 2a: OTP (email/password path) -->
        <div class="tr-step" id="trStepOtp" hidden>

            <h2 class="tenant-register-modal-title">Check Your Email</h2>
            <p class="tenant-register-modal-subtitle">
                Enter the 6-digit code we sent you.
            </p>

            <div class="tr-error" id="trOtpError" hidden></div>

            <div class="tenant-register-modal-field">
                <label for="trOtpCode">Verification Code</label>
                <input type="text" id="trOtpCode" inputmode="numeric" maxlength="6" placeholder="000000">
            </div>

            <button type="button" class="lux-btn tenant-register-modal-submit" id="trSubmitOtp">
                Verify &amp; Continue
            </button>

            <div class="tr-resend-row">
                <span id="trOtpTimer">Code expires in 5:00</span>
                <button type="button" id="trResendOtp" disabled>Resend Code</button>
            </div>

        </div>

        <!-- STEP 2b: set password (Google new-signup path) -->
        <div class="tr-step" id="trStepGooglePassword" hidden>

            <h2 class="tenant-register-modal-title">Secure Your Account</h2>
            <p class="tenant-register-modal-subtitle">
                Set a password to finish creating your account.
            </p>

            <div class="tr-error" id="trGooglePasswordError" hidden></div>

            <div class="tenant-register-modal-field">
                <label for="trGooglePassword">Password</label>
                <input type="password" id="trGooglePassword" placeholder="At least 8 characters">
            </div>

            <button type="button" class="lux-btn tenant-register-modal-submit" id="trSubmitGooglePassword">
                Finish
            </button>

        </div>

    </div>

</div>
