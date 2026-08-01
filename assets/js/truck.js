/*
=========================================
LUX EMPIRE - TRUCK ENGINE
=========================================
Handles:
- Truck requests (tenant)
- Driver actions
- Trip status updates
=========================================
*/

document.addEventListener("DOMContentLoaded", () => {

    initTruckRequestForm();
    initDriverActions();

});

/*
=========================================
TENANT: REQUEST TRUCK
=========================================
*/

function initTruckRequestForm()
{
    const form = document.querySelector(".truck-request-form");

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

                showToast("Truck request sent successfully", "success");

                form.reset();

                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                }

            } else {

                showToast(data.message || "Request failed", "error");

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
DRIVER ACTIONS (ACCEPT / STATUS UPDATE)
=========================================
*/

function initDriverActions()
{
    // Accept request
    document.querySelectorAll(".accept-request").forEach(btn => {

        btn.addEventListener("click", async function (e) {

            e.preventDefault();

            const url = this.dataset.url;

            if (!confirm("Accept this truck request?")) return;

            await sendTruckAction(url);

        });

    });

    // Reject request
    document.querySelectorAll(".reject-request").forEach(btn => {

        btn.addEventListener("click", async function (e) {

            e.preventDefault();

            const url = this.dataset.url;

            if (!confirm("Reject this request?")) return;

            await sendTruckAction(url);

        });

    });

    // Update trip status (in_transit / completed)
    document.querySelectorAll(".update-trip-status").forEach(btn => {

        btn.addEventListener("click", async function (e) {

            e.preventDefault();

            const url = this.dataset.url;

            await sendTruckAction(url);

        });

    });
}

/*
=========================================
COMMON TRUCK ACTION HANDLER
=========================================
*/

async function sendTruckAction(url)
{
    try {

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {

            showToast(data.message || "Action successful", "success");

            setTimeout(() => {
                location.reload();
            }, 800);

        } else {

            showToast(data.message || "Action failed", "error");

        }

    } catch (err) {

        showToast("Network error", "error");

    }
}