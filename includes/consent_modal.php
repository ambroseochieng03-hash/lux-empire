<?php
/**
 * LUX EMPIRE — CONSENT NOTICE MODAL (partial)
 *
 * SAVE THIS FILE AT: includes/consent_modal.php
 *
 * Usage: set $consentRole to 'landlord' or 'driver', then `require`
 * this file from INSIDE the registration <form>, but OUTSIDE the
 * .auth-form-fields wrapper — see auth/register_landlord.php for
 * the exact structure. The checkbox rendered here must be a real
 * field of that same form, so the server-side handler can reject
 * submission if it's missing, even if someone bypasses the JS
 * overlay entirely.
 *
 * DRAFT COPY: the text below fulfils the "starts with a specific
 * phrase, plain language, checkbox to acknowledge" request, but it
 * has not been reviewed by a lawyer and should be treated as a
 * starting point, not final compliance-approved language.
 */

if (!isset($consentRole) || !in_array($consentRole, ['landlord', 'driver'], true)) {
    throw new InvalidArgumentException('consent_modal.php requires $consentRole to be "landlord" or "driver".');
}

$consentRoleLabel = $consentRole === 'landlord' ? 'landlord' : 'driver';
?>

<div class="consent-modal" id="consentModal" aria-hidden="false">

    <div class="consent-modal-overlay"></div>

    <div class="consent-modal-box" role="dialog" aria-modal="true" aria-labelledby="consentModalTitle">

        <h2 id="consentModalTitle" class="consent-modal-title">
            Before You Continue
        </h2>

        <div class="consent-modal-body">

            <p>
                LUX EMPIRE is committed to protecting your personal data and
                complies with applicable data protection law, including
                Kenya's Data Protection Act, 2019.
            </p>

            <p>
                To register as a <?php echo htmlspecialchars($consentRoleLabel); ?> we need to
                collect and process the following information:
            </p>

            <ul class="consent-modal-list">
                <li>Your full name, email address, and phone number</li>
                <?php if ($consentRole === 'landlord'): ?>
                    <li>Your National ID number, used to verify your identity as a property owner</li>
                <?php else: ?>
                    <li>Your driver's license number and vehicle details, used to verify you're authorized to drive and to match you with moving requests</li>
                <?php endif; ?>
            </ul>

            <p>
                <strong>Who can access it:</strong> only authorized LUX EMPIRE staff,
                for the purposes described above. We do not sell your information
                to third parties.
            </p>

            <p>
                <strong>How it's stored:</strong> your password is never stored in
                plain text — it's hashed. Sensitive identification numbers
                (National ID / license / vehicle plate) are encrypted before
                they're saved, so even someone with direct database access
                cannot read them without the separate encryption key.
            </p>

            <p>
                <strong>Your rights:</strong> you can request access to, correction
                of, or deletion of your data at any time by contacting Empire
                support.
            </p>

        </div>

        <label class="consent-modal-checkbox-row">
            <input type="checkbox"
                   name="consent_accepted"
                   value="1"
                   id="consentCheckbox"
                   required>
            I have read and understood this notice and consent to LUX EMPIRE
            collecting and processing my information as described above.
        </label>

        <button type="button" class="consent-modal-decline-btn" id="consentDeclineBtn">
            Decline &amp; Leave
        </button>

    </div>

</div>