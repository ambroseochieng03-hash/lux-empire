/*
=========================================
LUX EMPIRE — IDEMPOTENCY KEY HELPER
=========================================
One key per "attempt" of an action (booking a specific house, one
truck-request form fill, one house-listing submission) — generated
once and reused across retries of THAT SAME attempt, so a slow
connection double-tap or resubmit never duplicates the action
server-side. A genuinely new attempt (different house, form
reset/reopened) should call resetIdempotencyKey() to get a fresh one.
=========================================
*/

(function () {

    function getOrCreateIdempotencyKey(el) {

        if (!el.dataset.idempotencyKey) {
            el.dataset.idempotencyKey = (crypto.randomUUID
                ? crypto.randomUUID()
                : 'idem_' + Date.now() + '_' + Math.random().toString(36).slice(2));
        }

        return el.dataset.idempotencyKey;
    }

    function resetIdempotencyKey(el) {
        delete el.dataset.idempotencyKey;
    }

    function generateIdempotencyKey() {
        return crypto.randomUUID
            ? crypto.randomUUID()
            : 'idem_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    }

    window.LuxIdempotency = {
        get: getOrCreateIdempotencyKey,
        reset: resetIdempotencyKey,
        generate: generateIdempotencyKey
    };

})();
