/*
=========================================
LUX EMPIRE — LIVE FORM VALIDATION
=========================================
Attach data-validate="fullname|email|phone|national_id|password|vehicle_plate"
to any input. This file handles live red-border feedback as the user
types/blurs, and exposes window.LuxFormValidation.validateForm(container)
for submit-time blocking.

These rules MIRROR classes/Validator.php exactly — if one changes,
change the other. The server copy is the one that's actually
enforced; this is UX only, never trusted as the real gate.
=========================================
*/

(function () {

    const RULES = {

        fullname: {
            test(value) {
                const v = value.trim();
                if (v.length < 2 || v.length > 100) return false;
                if (!/^[\p{L} .'-]+$/u.test(v)) return false;
                if (!/\p{L}/u.test(v)) return false;
                return true;
            },
            message: 'Enter a valid full name (letters only, at least 2 characters).'
        },

        email: {
            test(value) {
                const v = value.trim();
                if (v === '' || v.length > 254) return false;
                if (/\s/.test(v)) return false;
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            },
            message: 'Enter a valid email address.'
        },

        phone: {
            test(value) {
                const v = value.trim();
                if (v === '') return true; // optional field — emptiness is handled by `required` separately
                return /^(0|\+254)[71]\d{8}$/.test(v);
            },
            message: 'Enter a valid Kenyan phone number (e.g. 0712345678 or +254712345678).'
        },

        phone_required: {
            test(value) {
                return /^(0|\+254)[71]\d{8}$/.test(value.trim());
            },
            message: 'Enter a valid Kenyan phone number (e.g. 0712345678 or +254712345678).'
        },

        national_id: {
            test(value) {
                return /^\d{7,9}$/.test(value.trim());
            },
            message: 'National ID must be 7–9 digits, numbers only.'
        },

        password: {
            test(value) {
                return value.length >= 8;
            },
            message: 'Password must be at least 8 characters.'
        },

        vehicle_plate: {
            test(value) {
                return /^[A-Za-z]{3} \d{3}[A-Za-z]$/.test(value.trim());
            },
            message: 'Enter a valid plate number (e.g. KDA 123A).'
        }
    };

    function getErrorEl(input) {

        let errorEl = input.nextElementSibling;

        if (!errorEl || !errorEl.classList.contains('field-error-msg')) {
            errorEl = document.createElement('div');
            errorEl.className = 'field-error-msg';
            errorEl.hidden = true;
            input.insertAdjacentElement('afterend', errorEl);
        }

        return errorEl;
    }

    function validateField(input) {

        const type = input.dataset.validate;
        const rule = RULES[type];

        if (!rule) {
            return true;
        }

        const errorEl = getErrorEl(input);
        const isValid = rule.test(input.value);

        if (isValid) {
            input.classList.remove('field-invalid');
            errorEl.hidden = true;
        } else {
            input.classList.add('field-invalid');
            errorEl.textContent = rule.message;
            errorEl.hidden = false;
        }

        return isValid;
    }

    document.addEventListener('input', (event) => {
        if (event.target.dataset && event.target.dataset.validate) {
            // Only clear the red state while typing — don't nag with
            // the error message until they've actually left the field.
            if (event.target.classList.contains('field-invalid')) {
                validateField(event.target);
            }
        }
    });

    document.addEventListener('blur', (event) => {
        if (event.target.dataset && event.target.dataset.validate) {
            validateField(event.target);
        }
    }, true);

    /*
     * Validates every [data-validate] field inside `container`.
     * Returns true only if all pass. Focuses and highlights the
     * first invalid field found. Call this from a submit handler
     * BEFORE letting the form/AJAX call proceed.
     */
    function validateForm(container) {

        const fields = container.querySelectorAll('[data-validate]');
        let firstInvalid = null;
        let allValid = true;

        fields.forEach((field) => {

            const isValid = validateField(field);

            if (!isValid) {
                allValid = false;
                if (!firstInvalid) {
                    firstInvalid = field;
                }
            }
        });

        if (firstInvalid) {
            firstInvalid.focus();
        }

        return allValid;
    }

    window.LuxFormValidation = { validateForm, validateField };

})();
