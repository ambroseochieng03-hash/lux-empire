/*
=========================================
LUX EMPIRE GLOBAL FRONTEND ENGINE
=========================================
*/

document.addEventListener("DOMContentLoaded", () => {

    initializeSidebar();
    initializeAnimations();
    initializeConfirmActions();

});

/*
=========================================
SIDEBAR TOGGLE
=========================================
*/

function initializeSidebar()
{
    const toggleBtn =
        document.querySelector(".sidebar-toggle");

    const sidebar =
        document.querySelector(".sidebar");

    if (!toggleBtn || !sidebar)
        return;

    toggleBtn.addEventListener("click", () => {

        sidebar.classList.toggle("active");

    });

    document.addEventListener("click", (event) => {

        if (
            window.innerWidth <= 992 &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)
        ) {
            sidebar.classList.remove("active");
        }

    });
}

/*
=========================================
FADE-IN ANIMATION
=========================================
*/

function initializeAnimations()
{
    const elements =
        document.querySelectorAll(".fade-in");

    const observer =
        new IntersectionObserver(entries => {

            entries.forEach(entry => {

                if (entry.isIntersecting) {

                    entry.target.classList.add(
                        "visible"
                    );

                }

            });

        });

    elements.forEach(el => {

        observer.observe(el);

    });
}

/*
=========================================
CONFIRM BUTTONS
=========================================
*/

function initializeConfirmActions()
{
    const buttons =
        document.querySelectorAll(
            "[data-confirm]"
        );

    buttons.forEach(button => {

        button.addEventListener(
            "click",
            function(event)
            {
                const message =
                    this.dataset.confirm ||
                    "Proceed?";

                if (!confirm(message)) {

                    event.preventDefault();

                }
            }
        );

    });
}

/*
=========================================
TOAST NOTIFICATIONS
=========================================
*/

function showToast(
    message,
    type = "success"
)
{
    const toast =
        document.createElement("div");

    toast.className =
        `lux-toast ${type}`;

    toast.innerText =
        message;

    document.body.appendChild(
        toast
    );

    setTimeout(() => {

        toast.classList.add(
            "show"
        );

    }, 50);

    setTimeout(() => {

        toast.classList.remove(
            "show"
        );

        setTimeout(() => {

            toast.remove();

        }, 300);

    }, 3500);
}

/*
=========================================
BUTTON LOADING STATE
=========================================
*/

function setButtonLoading(
    button,
    loading = true
)
{
    if (!button)
        return;

    if (loading)
    {
        button.dataset.originalText =
            button.innerHTML;

        button.disabled = true;

        button.innerHTML =
            "Please wait...";
    }
    else
    {
        button.disabled = false;

        button.innerHTML =
            button.dataset.originalText;
    }
}

/*
=========================================
AJAX HELPER
=========================================
*/

async function postRequest(
    url,
    formData
)
{
    const response =
        await fetch(url, {

            method: "POST",
            body: formData

        });

    return await response.json();
}

/*
=========================================
COPY TO CLIPBOARD
=========================================
*/

async function copyText(text)
{
    try {

        await navigator.clipboard.writeText(
            text
        );

        showToast(
            "Copied successfully"
        );

    }
    catch {

        showToast(
            "Copy failed",
            "error"
        );

    }
}

/*
=========================================
FORMAT CURRENCY
=========================================
*/

function formatKES(amount)
{
    return new Intl.NumberFormat(
        "en-KE",
        {
            style: "currency",
            currency: "KES"
        }
    ).format(amount);
}

/*
=========================================
AUTO HIDE ALERTS
=========================================
*/

document.addEventListener(
    "DOMContentLoaded",
    () =>
    {
        const alerts =
            document.querySelectorAll(
                ".alert"
            );

        alerts.forEach(alert => {

            setTimeout(() => {

                alert.style.opacity = "0";

                setTimeout(() => {

                    alert.remove();

                }, 400);

            }, 5000);

        });
    }
);

/*
=========================================
SCROLL TO TOP
=========================================
*/

function scrollToTop()
{
    window.scrollTo({

        top: 0,
        behavior: "smooth"

    });
}

/*
=========================================
EXPOSE HELPERS
=========================================
*/

window.showToast =
    showToast;

window.postRequest =
    postRequest;

window.copyText =
    copyText;

window.formatKES =
    formatKES;

window.scrollToTop =
    scrollToTop;

window.setButtonLoading =
    setButtonLoading;