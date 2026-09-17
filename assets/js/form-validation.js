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

        driver_license: {
            test(value) {
                return /^[A-Za-z0-9]{5,15}$/.test(value.trim());
            },
            message: 'Enter a valid license number (letters/numbers, 5-15 characters).'
        },

        password: {
            test(value) {
                return value.length >= 8;
            },
            message: 'Password must be at least 8 characters.'
        },

        mpesa_receipt: {
            test(value) {
                const v = value.trim();
                if (v === '') return false;

                const bareCode = /^[A-Za-z][A-Za-z0-9]{9}$/;
                if (bareCode.test(v)) {
                    return /\d/.test(v);
                }

                const messageMatch = v.match(/\b([A-Za-z][A-Za-z0-9]{9})\s+Confirmed\b/i);
                if (messageMatch) {
                    return /\d/.test(messageMatch[1]);
                }

                return false;
            },
            message: 'Enter a valid 10-character M-Pesa code, or paste the full confirmation message exactly as received.'
        },

        vehicle_plate: {
            test(value) {
                return /^[A-Za-z]{3} \d{3}[A-Za-z]$/.test(value.trim());
            },
            message: 'Enter a valid plate number (e.g. KDA 123A).'
        },

        price: {
            test(value) {
                const v = value.trim();
                if (!/^\d{1,9}(\.\d{1,2})?$/.test(v)) return false;
                return parseFloat(v) > 0;
            },
            message: 'Enter a valid price (numbers only, up to 2 decimal places).'
        },

        latitude: {
            test(value) {
                const v = value.trim();
                if (v === '') return true;
                if (!/^-?\d{1,2}(\.\d+)?$/.test(v)) return false;
                const n = parseFloat(v);
                return n >= -90 && n <= 90;
            },
            message: 'Latitude must be a number between -90 and 90.'
        },

        longitude: {
            test(value) {
                const v = value.trim();
                if (v === '') return true;
                if (!/^-?\d{1,3}(\.\d+)?$/.test(v)) return false;
                const n = parseFloat(v);
                return n >= -180 && n <= 180;
            },
            message: 'Longitude must be a number between -180 and 180.'
        },

        room_count: {
            test(value) {
                const v = value.trim();
                if (!/^\d{1,2}$/.test(v)) return false;
                const n = parseInt(v, 10);
                return n >= 0 && n <= 20;
            },
            message: 'Enter a whole number between 0 and 20.'
        },

        house_title: {
            test(value) {
                const v = value.trim();
                return v.length >= 3 && v.length <= 255;
            },
            message: 'Title must be 3–255 characters.'
        },

        location: {
            test(value) {
                const v = value.trim();
                return v.length >= 2 && v.length <= 255;
            },
            message: 'Location must be 2–255 characters.'
        },

        house_type: {
            test(value) {
                const v = value.trim();
                if (v === '') return true;
                return v.length >= 2 && v.length <= 100;
            },
            message: 'Property type must be 2–100 characters.'
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