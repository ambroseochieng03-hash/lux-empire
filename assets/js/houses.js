/*
=========================================
LUX EMPIRE - HOUSES ENGINE
=========================================
Handles all house-related frontend actions
=========================================
*/

document.addEventListener("DOMContentLoaded", () => {

    initHouseForms();
    initImagePreview();

});

/*
=========================================
CREATE / UPDATE HOUSE FORM
=========================================
*/

function initHouseForms()
{
    const forms = document.querySelectorAll(".house-form");

    forms.forEach(form => {

        form.addEventListener("submit", async (e) => {

            e.preventDefault();

            const button = form.querySelector("button[type='submit']");
            setButtonLoading(button, true);

            const formData = new FormData(form);

            try {

                const response = await fetch(
                    form.action,
                    {
                        method: "POST",
                        body: formData
                    }
                );

                const data = await response.json();

                if (data.success || data.house_id || data.message) {

                    showToast(
                        data.message || "House saved successfully",
                        "success"
                    );

                    // optional redirect
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }

                } else {

                    showToast(
                        data.message || "Something went wrong",
                        "error"
                    );

                }

            } catch (error) {

                showToast(
                    "Network error occurred",
                    "error"
                );

            } finally {

                setButtonLoading(button, false);

            }

        });

    });
}

/*
=========================================
IMAGE PREVIEW BEFORE UPLOAD
=========================================
*/

function initImagePreview()
{
    const imageInputs = document.querySelectorAll("input[type='file'][data-preview]");

    imageInputs.forEach(input => {

        input.addEventListener("change", function () {

            const file = this.files[0];

            if (!file) return;

            const reader = new FileReader();

            reader.onload = function (e) {

                const previewId = input.dataset.preview;
                const previewElement = document.getElementById(previewId);

                if (previewElement) {

                    previewElement.src = e.target.result;
                    previewElement.style.display = "block";

                }

            };

            reader.readAsDataURL(file);

        });

    });
}

/*
=========================================
DELETE HOUSE (OPTIONAL HOOK)
=========================================
*/

async function deleteHouse(url)
{
    if (!confirm("Are you sure you want to delete this house?")) {
        return;
    }

    try {

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {

            showToast("House deleted", "success");

            setTimeout(() => {
                location.reload();
            }, 1000);

        } else {

            showToast(data.message || "Delete failed", "error");

        }

    } catch (error) {

        showToast("Network error", "error");

    }
}