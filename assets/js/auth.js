/*
=========================================
LUX EMPIRE - AUTH ENGINE
=========================================
Handles:
- Login
- Register
- UX validation
=========================================
*/

document.addEventListener("DOMContentLoaded", () => {

    initLoginForm();
    initRegisterForm();
    initPasswordToggle();
    initPasswordStrength();

});

/*
=========================================
LOGIN FORM
=========================================
*/

function initLoginForm()
{
    const form = document.querySelector(".login-form");

    if (!form) return;

    form.addEventListener("submit", async (e) => {

        e.preventDefault();

        const button = form.querySelector("button[type='submit']");
        setButtonLoading(button, true);

        const formData = new FormData(form);

        try {

            const res = await fetch(form.action, {
                method: "POST",
                body: formData
            });

            const data = await res.json();

            if (data.success) {

                showToast("Login successful", "success");

                setTimeout(() => {

                    window.location.href = data.redirect || "/dashboard";

                }, 800);

            } else {

                showToast(data.message || "Login failed", "error");

            }

        } catch (err) {

            showToast("Network error", "error");

        } finally {

            setButtonLoading(button, false);

        }

    });
}

/*
=========================================
REGISTER FORM
=========================================
*/

function initRegisterForm()
{
    const form = document.querySelector(".register-form");

    if (!form) return;

    form.addEventListener("submit", async (e) => {

        e.preventDefault();

        const password = form.querySelector("input[name='password']").value;
        const confirm = form.querySelector("input[name='confirm_password']").value;

        if (password !== confirm) {

            showToast("Passwords do not match", "error");
            return;

        }

        const button = form.querySelector("button[type='submit']");
        setButtonLoading(button, true);

        const formData = new FormData(form);

        try {

            const res = await fetch(form.action, {
                method: "POST",
                body: formData
            });

            const data = await res.json();

            if (data.success) {

                showToast("Account created successfully", "success");

                setTimeout(() => {

                    window.location.href = data.redirect || "/auth/login.php";

                }, 1000);

            } else {

                showToast(data.message || "Registration failed", "error");

            }

        } catch (err) {

            showToast("Network error", "error");

        } finally {

            setButtonLoading(button, false);

        }

    });
}

/*
=========================================
PASSWORD TOGGLE
=========================================
*/

function initPasswordToggle()
{
    document.querySelectorAll(".toggle-password").forEach(btn => {

        btn.addEventListener("click", () => {

            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);

            if (!input) return;

            if (input.type === "password") {
                input.type = "text";
                btn.innerText = "🙈";
            } else {
                input.type = "password";
                btn.innerText = "👁";
            }

        });

    });
}

/*
=========================================
PASSWORD STRENGTH CHECKER
=========================================
*/

function initPasswordStrength()
{
    const input = document.querySelector("input[name='password']");

    if (!input) return;

    const meter = document.createElement("div");
    meter.className = "password-meter";

    input.parentNode.appendChild(meter);

    input.addEventListener("input", () => {

        const value = input.value;

        let strength = 0;

        if (value.length >= 6) strength++;
        if (value.match(/[A-Z]/)) strength++;
        if (value.match(/[0-9]/)) strength++;
        if (value.match(/[^A-Za-z0-9]/)) strength++;

        let label = "";
        let color = "";

        switch (strength) {

            case 0:
            case 1:
                label = "Weak";
                color = "red";
                break;

            case 2:
                label = "Fair";
                color = "orange";
                break;

            case 3:
                label = "Good";
                color = "yellow";
                break;

            case 4:
                label = "Strong";
                color = "green";
                break;

        }

        meter.innerText = `Strength: ${label}`;
        meter.style.color = color;

    });
}