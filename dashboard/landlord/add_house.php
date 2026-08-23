<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';

requireRoleAccess('landlord');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

?>

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
    background:rgba(255,0,0,0.08);
    border:1px solid rgba(255,0,0,0.25);
    padding:14px 18px;
    border-radius:14px;
    margin-bottom:25px;
    color:#ffb3b3;
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
    color:white;
    margin-bottom:10px;
    font-weight:600;
}

.landlord-input,
.landlord-textarea,
.landlord-file{
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    box-sizing:border-box;
    outline:none;
    background:rgba(255,255,255,0.06);
    color:white;
    font-size:1rem;
}

.landlord-input::placeholder,
.landlord-textarea::placeholder{
    color:#9f9f9f;
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

.landlord-media-tab {
    flex: 1;
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(212,175,55,0.3);
    background: rgba(255,255,255,0.04);
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

        <?php if (isset($_GET['error'])): ?>

            <div class="landlord-alert">
                <?= htmlspecialchars(
                    $_GET['error'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </div>

        <?php endif; ?>


        <!-- FORM -->

        <div class="lux-card landlord-form-card">

            <form action="<?php echo BASE_URL; ?>/api/houses/create_house.php" method="POST" enctype="multipart/form-data">

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
                            required
                            maxlength="255"
                            placeholder="Westlands, Nairobi"
                            class="landlord-input"
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
                     LOCATION COORDINATES
                ====================================== -->

                <div class="landlord-grid">

                    <!-- LATITUDE -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Latitude
                        </label>

                        <input
                            type="number"
                            name="latitude"
                            step="any"
                            placeholder="-1.2676"
                            class="landlord-input"
                        >

                    </div>


                    <!-- LONGITUDE -->

                    <div class="landlord-form-group">

                        <label class="landlord-label">
                            Longitude
                        </label>

                        <input
                            type="number"
                            name="longitude"
                            step="any"
                            placeholder="36.8108"
                            class="landlord-input"
                        >

                    </div>

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


<?php require_once '../../includes/footer.php'; ?>