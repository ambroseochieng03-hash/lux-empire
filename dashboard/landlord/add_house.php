<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
require_once '../../config/csrf.php';

requireRoleAccess('landlord');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/form-validation.css">

<style>

/* =========================================
   LANDLORD ADD HOUSE
========================================= */

.landlord-layout{
    display:flex;
    min-height:100vh;
}

.landlord-main{
    flex:1;
    padding:40px;
    margin-left:280px;
    width:calc(100% - 280px);
    box-sizing:border-box;
}

.landlord-page-header{
    margin-bottom:35px;
}

.landlord-page-title{
    font-family:'Cinzel', serif;
    color:var(--gold);
    font-size:3rem;
    line-height:1.2;
    margin-bottom:10px;
}

.landlord-page-text{
    color:var(--gray);
    font-size:1rem;
    line-height:1.8;
    max-width:700px;
}

.landlord-alert{
    background:var(--lux-danger-bg);
    border:1px solid var(--lux-danger);
    padding:14px 18px;
    border-radius:14px;
    margin-bottom:25px;
    color:var(--lux-danger);
    word-break:break-word;
}

.landlord-form-card{
    max-width:1000px;
    border-radius:28px;
    padding:35px;
    box-sizing:border-box;
}

.landlord-form-group{
    margin-bottom:22px;
}

.landlord-label{
    display:block;
    color:var(--white);
    margin-bottom:10px;
    font-weight:600;
}

.landlord-input,
.landlord-textarea,
.landlord-file{
    width:100%;
    padding:16px;
    border:1px solid var(--lux-card-border);
    border-radius:16px;
    box-sizing:border-box;
    outline:none;
    background:var(--glass);
    color:var(--white);
    font-size:1rem;
}

.landlord-input::placeholder,
.landlord-textarea::placeholder{
    color:var(--gray);
}

.landlord-textarea{
    resize:none;
    min-height:160px;
}

.landlord-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:20px;
    margin-bottom:22px;
}

.landlord-submit-btn{
    width:100%;
    padding:18px;
    font-size:1.05rem;
    border-radius:18px;
    border:none;
    cursor:pointer;
}

/* =========================================
   MEDIA UPLOAD
========================================= */

.landlord-media-box{
    position:relative;
    border-radius:16px;
    overflow:hidden;
    background:rgba(255,255,255,0.06);
    border:1px dashed rgba(212,175,55,0.4);
    transition:0.3s;
}

.landlord-media-box:hover{
    border-color:rgba(212,175,55,0.8);
}

.landlord-media-box input{
    width:100%;
    padding:18px;
    color:var(--gray);
    cursor:pointer;
    background:transparent;
    border:none;
    box-sizing:border-box;
}

.landlord-media-help{
    margin-top:9px;
    color:var(--gray);
    font-size:0.85rem;
    line-height:1.6;
}

/* =========================================
   TABLET
========================================= */

@media (max-width:992px){

    .landlord-main{
        margin-left:0;
        width:100%;
        padding:30px 22px;
    }

    .landlord-page-title{
        font-size:2.5rem;
    }

    .landlord-form-card{
        max-width:100%;
        padding:28px;
    }

}

/* =========================================
   MOBILE
========================================= */

@media (max-width:768px){

    .landlord-main{
        padding:22px 16px 40px;
    }

    .landlord-page-title{
        font-size:2rem;
    }

    .landlord-page-text{
        font-size:0.95rem;
    }

    .landlord-form-card{
        padding:22px;
        border-radius:24px;
    }

    .landlord-grid{
        grid-template-columns:1fr;
        gap:18px;
    }

    .landlord-input,
    .landlord-textarea,
    .landlord-file{
        padding:15px;
        font-size:0.95rem;
    }

    .landlord-submit-btn{
        padding:16px;
        font-size:1rem;
    }

}

/* =========================================
   SMALL MOBILE
========================================= */

@media (max-width:480px){

    .landlord-main{
        padding:18px 12px 35px;
    }

    .landlord-page-title{
        font-size:1.7rem;
    }

    .landlord-form-card{
        padding:18px;
        border-radius:20px;
    }

    .landlord-input,
    .landlord-textarea,
    .landlord-file{
        border-radius:14px;
    }

    .landlord-submit-btn{
        border-radius:14px;
    }

}

.landlord-media-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
}

.landlord-media-tab{
    flex: 1;
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(212,175,55,0.3);
    background: var(--glass);
    color: var(--gray);
    cursor: pointer;
    font-weight: 600;
    transition: 0.2s;
}

.landlord-media-tab.active {
    background: rgba(212,175,55,0.15);
    color: var(--gold);
    border-color: var(--gold);
}

.landlord-checkbox-label{
    display:inline-flex;
    align-items:center;
    gap:10px;
    color:var(--white);
    font-weight:600;
    cursor:pointer;
    user-select:none;
}

.landlord-checkbox-input{
    position:absolute;
    opacity:0;
    width:0;
    height:0;
}

.landlord-checkbox-box{
    width:22px;
    height:22px;
    border-radius:6px;
    border:2px solid rgba(212,175,55,0.5);
    background:var(--glass);
    position:relative;
    transition:0.2s;
    flex-shrink:0;
}

.landlord-checkbox-input:checked + .landlord-checkbox-box{
    background:var(--gold);
    border-color:var(--gold);
}

.landlord-checkbox-input:checked + .landlord-checkbox-box::after{
    content:'✓';
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--black);
    font-size:0.85rem;
    font-weight:bold;
}

</style>


<div class="landlord-layout">

    <main class="landlord-main">

        <!-- PAGE HEADER -->

        <div class="landlord-page-header">

            <h1 class="landlord-page-title">
                Add Luxury Property
            </h1>

            <p class="landlord-page-text">
                Present your premium property to the Empire marketplace.
            </p>

        </div>


        <!-- ERROR -->

        <div class="landlord-alert" id="addHouseErrorAlert" style="display:none;"></div>


        <!-- FORM -->

        <div class="lux-card landlord-form-card">

            <form id="landlordAddHouseForm" enctype="multipart/form-data">
                <input type="hidden" name="idempotency_key" id="addHouseIdemKey" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">

                <!-- =====================================
                     PROPERTY TITLE
                ====================================== -->

                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Property Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        required
                        maxlength="255"
                        placeholder="Luxury Penthouse in Westlands"
                        class="landlord-input"
                        data-validate="house_title"
                    >

                </div>


                <!-- =====================================
                     DESCRIPTION
                ====================================== -->

                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="6"
                        maxlength="5000"
                        placeholder="Describe the elegance, features, amenities, and prestige of the property..."
                        class="landlord-textarea"
                    ></textarea>

                </div>


                <!-- =====================================
                     PROPERTY DETAILS
                ====================================== -->

                <div class="landlord-grid">

                    <!-- PRICE -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Monthly Price (KES)
                        </label>

                        <input
                            type="number"
                            name="price"
                            required
                            min="1"
                            step="0.01"
                            placeholder="85000"
                            class="landlord-input"
                            data-validate="price"
                        >

                    </div>


                    <!-- LOCATION -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Location
                        </label>

                        <input
                            type="text"
                            name="location"
                            id="landlordLocationInput"
                            required
                            maxlength="255"
                            placeholder="Westlands, Nairobi"
                            class="landlord-input"
                            data-validate="location"
                            autocomplete="off"
                        >

                    </div>


                    <!-- BEDROOMS -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Bedrooms
                        </label>

                        <input
                            type="number"
                            name="bedrooms"
                            min="0"
                            value="1"
                            class="landlord-input"
                            data-validate="room_count"
                        >

                    </div>


                    <!-- BATHROOMS -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Bathrooms
                        </label>

                        <input
                            type="number"
                            name="bathrooms"
                            min="0"
                            value="1"
                            class="landlord-input"
                            data-validate="room_count"
                        >

                    </div>


                    <!-- HOUSE TYPE -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Property Type
                        </label>

                        <select
                            name="house_type"
                            class="landlord-input"
                        >

                            <option value="Apartment">
                                Apartment
                            </option>

                            <option value="Penthouse">
                                Penthouse
                            </option>

                            <option value="Villa">
                                Villa
                            </option>

                            <option value="Maisonette">
                                Maisonette
                            </option>

                            <option value="Townhouse">
                                Townhouse
                            </option>

                            <option value="Bungalow">
                                Bungalow
                            </option>

                            <option value="Studio">
                                Studio
                            </option>

                            <option value="Bedsitter">
                                Bedsitter
                            </option>

                        </select>

                    </div>


                    <!-- RATING -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Luxury Rating
                        </label>

                        <select
                            name="rating"
                            class="landlord-input"
                        >

                            <option value="5">
                                ⭐⭐⭐⭐⭐ | Premium Luxury
                            </option>

                            <option value="4">
                                ⭐⭐⭐⭐☆ | High-End
                            </option>

                            <option value="3">
                                ⭐⭐⭐☆☆ | Standard Luxury
                            </option>

                            <option value="2">
                                ⭐⭐☆☆☆ | Basic Comfort
                            </option>

                            <option value="1">
                                ⭐☆☆☆☆ | Budget Tier
                            </option>

                        </select>

                    </div>

                </div>


                <!-- =====================================
                     LOCATION COORDINATES — never shown, never
                     typeable. Auto-filled from the Location field
                     above via Google Places Autocomplete (script at
                     the bottom of this page). Submitted as part of
                     the same form POST under the same field names
                     the backend already expects.
                ====================================== -->

                <input type="hidden" name="latitude" id="landlordLatitudeInput">
                <input type="hidden" name="longitude" id="landlordLongitudeInput">


                <!-- =====================================
                     PARKING
                ====================================== -->

                <div class="landlord-form-group">

                    <label class="landlord-checkbox-label">
                        <input type="hidden" name="has_parking" value="0">
                        <input type="checkbox" name="has_parking" value="1" class="landlord-checkbox-input">
                        <span class="landlord-checkbox-box"></span>
                        Parking Available
                    </label>

                </div>


                <!-- =====================================
                    MEDIA
                ====================================== -->

                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Property Media
                    </label>

                    <div class="landlord-media-toggle">
                        <button type="button" class="landlord-media-tab active" data-mode="images">
                            Multiple Images
                        </button>
                        <button type="button" class="landlord-media-tab" data-mode="video">
                            Single Video
                        </button>
                    </div>

                    <div class="landlord-media-box" id="landlordImagesBox">
                        <input
                            type="file"
                            name="images[]"
                            id="landlordImagesInput"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                        >
                    </div>

                    <div class="landlord-media-box" id="landlordVideoBox" style="display:none;">
                        <input
                            type="file"
                            name="video"
                            id="landlordVideoInput"
                            accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska"
                        >
                    </div>

                    <div class="landlord-media-help">
                        Choose either multiple images OR one video — a property
                        cannot contain both. Images are automatically compressed
                        and videos are normalized to MP4 by the backend.
                    </div>

                </div>

                <script src="<?php echo BASE_URL; ?>/assets/js/idempotency.js"></script>

                <script>
                (function () {
                    const tabs = document.querySelectorAll('.landlord-media-tab');
                    const imagesBox = document.getElementById('landlordImagesBox');
                    const videoBox = document.getElementById('landlordVideoBox');
                    const imagesInput = document.getElementById('landlordImagesInput');
                    const videoInput = document.getElementById('landlordVideoInput');

                    tabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            tabs.forEach(t => t.classList.remove('active'));
                            tab.classList.add('active');

                            const mode = tab.dataset.mode;

                            if (mode === 'images') {
                                imagesBox.style.display = '';
                                videoBox.style.display = 'none';
                                videoInput.value = ''; // clear so it can never submit alongside images
                            } else {
                                imagesBox.style.display = 'none';
                                videoBox.style.display = '';
                                imagesInput.value = ''; // clear so it can never submit alongside video
                            }
                        });
                    });
                })();
                </script>


                <!-- =====================================
                     SUBMIT
                ====================================== -->

                <button
                    type="submit"
                    class="lux-btn landlord-submit-btn"
                >
                    Publish Luxury Property
                </button>

            </form>

        </div>

    </main>

</div>


<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/payment-modal.css">
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/idempotency.js"></script>
<script>
    window.LUX_PAYMENT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>"
    };
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/payment-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/limit-modal.js"></script>

<script
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initLuxLocationAutocomplete"
    async
    defer
></script>

<script>
function initLuxLocationAutocomplete() {
    const input = document.getElementById('landlordLocationInput');
    const latInput = document.getElementById('landlordLatitudeInput');
    const lngInput = document.getElementById('landlordLongitudeInput');

    if (!input || !window.google || !window.google.maps || !window.google.maps.places) {
        return;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
        fields: ['geometry'],
        componentRestrictions: { country: 'ke' }
    });

    autocomplete.addListener('place_changed', function () {
        const place = autocomplete.getPlace();

        if (!place.geometry || !place.geometry.location) {
            // Person typed free text and hit Enter without picking a
            // suggestion from the dropdown — we have no coordinates
            // for that, so leave the hidden fields empty rather than
            // guessing or submitting stale coordinates.
            latInput.value = '';
            lngInput.value = '';
            return;
        }

        latInput.value = place.geometry.location.lat();
        lngInput.value = place.geometry.location.lng();
    });

    // Editing the text after a suggestion was picked invalidates the
    // coordinates that were filled in for the PREVIOUS text — clear
    // them so we never submit lat/lng for a place the person no
    // longer has selected.
    input.addEventListener('input', function () {
        latInput.value = '';
        lngInput.value = '';
    });
}
</script>

<script>
(function () {

    const form = document.getElementById('landlordAddHouseForm');
    const errorAlert = document.getElementById('addHouseErrorAlert');
    const submitBtn = form.querySelector('.landlord-submit-btn');

    const LIMIT_CODES = ['LISTING_LIMIT_REACHED', 'IMAGE_LIMIT_REACHED', 'VIDEO_REQUIRES_PRO'];

    function showError(message, errorCode, isPro) {

        if (LIMIT_CODES.includes(errorCode)) {

            if (isPro) {
                // Already Pro — upgrading won't help. Offer to manage
                // existing listings instead.
                window.LuxLimitModal.show({
                    message: message,
                    mode: 'manage'
                });
            } else {
                window.LuxLimitModal.show({
                    message: message,
                    mode: 'upgrade',
                    onUpgrade: () => {
                        window.LuxPayment.open({
                            purpose: 'landlord_pro',
                            title: 'Upgrade to Pro',
                            amountLabel: 'KES 499 / month',
                            onSuccess: () => {
                                // Files already selected in the form are still
                                // there — nothing was lost, no page reload.
                                submitForm();
                            }
                        });
                    }
                });
            }
            return;
        }

        errorAlert.textContent = message;
        errorAlert.style.display = 'block';
    }

    async function submitForm() {

        if (!window.LuxFormValidation.validateForm(form)) {
            return;
        }

        document.getElementById('addHouseIdemKey').value = window.LuxIdempotency.get(form);

        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Publishing...';

        try {

            const response = await fetch("<?php echo BASE_URL; ?>/api/houses/create_house.php", {
                method: 'POST',
                body: new FormData(form),
            });

            // A 413 (body too large — Nginx's client_max_body_size,
            // or PHP's post_max_size), a 502/504 from PHP-FPM timing
            // out on a big video, or any other server-level failure
            // returns a plain HTML error page, not JSON. Parsing that
            // as JSON always throws — which is exactly why every one
            // of these used to collapse into the same generic
            // "Network error", hiding what actually happened.
            let data;

            try {
                data = await response.json();
            } catch (parseError) {

                window.LuxIdempotency.reset(form);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;

                if (response.status === 413) {
                    showError('That upload is too large for the server to accept. Try fewer or smaller images, or a shorter video.', null, false);
                } else if (response.status === 504 || response.status === 502) {
                    showError('The server took too long processing this upload. Try a smaller video, or try again.', null, false);
                } else {
                    showError('The server returned an unexpected response (HTTP ' + response.status + '). Please try again or contact support if this continues.', null, false);
                }

                return;
            }

            if (data.success) {
                window.location.href = "<?php echo BASE_URL; ?>/dashboard/landlord/manage_houses.php?success=" + encodeURIComponent(data.message);
                return;
            }

            window.LuxIdempotency.reset(form);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            showError(data.message || 'Unable to publish property.', data.error_code, !!data.is_pro);

        } catch (e) {
            // This catch now only covers genuine network failures —
            // DNS failure, connection refused, no internet — since
            // both response branches above are already handled.
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            showError('Could not reach the server. Check your connection and try again.', false);
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm();
    });

})();
</script>

<?php require_once '../../includes/footer.php'; ?>