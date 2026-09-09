<?php
/**
 * LUX EMPIRE — emergency alert compose + response modals.
 * Include once from sidebar.php, only for tenant/driver roles.
 */
?>
<div class="lux-modal-overlay" id="luxEmergencyModal" aria-hidden="true">
    <div class="lux-modal-box">
        <h3 class="lux-confirm-title" style="color:#ff4d4d;">Report an Emergency</h3>
        <p class="lux-confirm-message">
            Briefly describe what's happening. Our safety team will be notified immediately.
        </p>
        <textarea id="luxEmergencyMessage" class="lux-confirm-reason-input"
                  placeholder="What's going on?" maxlength="1000"></textarea>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-emergency-cancel>Cancel</button>
            <button class="lux-btn lux-btn-danger" id="luxEmergencySubmit">Send Alert</button>
        </div>
    </div>
</div>

<div class="lux-modal-overlay" id="luxEmergencyResponseModal" aria-hidden="true">
    <div class="lux-modal-box" style="text-align:center;">
        <div style="font-size:2.5rem; margin-bottom:14px;">&#9888;&#65039;</div>
        <h3 class="lux-confirm-title">Help Is On The Way</h3>
        <p class="lux-confirm-message" id="luxEmergencyResponseText"></p>
        <div class="lux-modal-actions" style="justify-content:center;">
            <button class="lux-btn lux-btn-success" data-emergency-response-close>Close</button>
        </div>
    </div>
</div>
