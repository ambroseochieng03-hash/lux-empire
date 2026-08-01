<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
/* =========================================
   LANDLORD ADD HOUSE RESPONSIVE STYLES
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
   TABLET
========================================= */

@media (max-width: 992px){

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

    .landlord-grid{
        grid-template-columns:1fr 1fr;
    }
}

/* =========================================
   MOBILE
========================================= */

@media (max-width: 768px){

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

@media (max-width: 480px){

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
</style>

<div class="landlord-layout">

    <!-- MAIN CONTENT -->
    <main class="landlord-main">

        <!-- PAGE HEADER -->
        <div class="landlord-page-header">

            <h1 class="landlord-page-title">
                👑 Add Luxury Property
            </h1>

            <p class="landlord-page-text">
                Present your premium property to the Empire marketplace.
            </p>

        </div>

        <!-- ALERT -->
        <?php if (isset($_GET['error'])): ?>

            <div class="landlord-alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>

        <?php endif; ?>

        <!-- FORM CARD -->
        <div class="lux-card landlord-form-card">

            <form
                action="../../api/houses/create_house.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <!-- TITLE -->
                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Property Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        required
                        placeholder="Luxury Penthouse in Westlands"
                        class="landlord-input"
                    >

                </div>

                <!-- DESCRIPTION -->
                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="6"
                        placeholder="Describe the elegance, features, and prestige..."
                        class="landlord-textarea"
                    ></textarea>

                </div>

                <!-- GRID -->
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
                            placeholder="3"
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
                            placeholder="2"
                            class="landlord-input"
                        >

                    </div>

                </div>

                <!-- LUXURY RATING -->
                <div class="landlord-form-group">

                    <label class="landlord-label">
                        ⭐ Luxury Rating
                    </label>

                    <div style="
                        position:relative;
                    ">

                        <select
                            name="rating"
                            class="landlord-input"
                            style="
                                appearance:none;
                                cursor:pointer;
                                padding-right:45px;
                                font-size:1rem;
                            "
                        >
                            <option value="5">⭐⭐⭐⭐⭐  |  Premium Luxury</option>
                            <option value="4">⭐⭐⭐⭐☆   |  High-End</option>
                            <option value="3">⭐⭐⭐☆☆    |  Standard Luxury</option>
                            <option value="2">⭐⭐☆☆☆     |  Basic Comfort</option>
                            <option value="1">⭐☆☆☆☆      |  Budget Tier</option>
                        </select>

                        <!-- dropdown arrow -->
                        <div style="
                            position:absolute;
                            right:16px;
                            top:50%;
                            transform:translateY(-50%);
                            pointer-events:none;
                            color:var(--gold);
                            font-size:1.2rem;
                        ">
                            ▾
                        </div>

                    </div>

                </div>

                <!-- IMAGE UPLOAD -->
                <div class="landlord-form-group">

                    <label class="landlord-label">
                        Property Image
                    </label>

                    <div style="
                        position:relative;
                        border-radius:16px;
                        overflow:hidden;
                        background:rgba(255,255,255,0.06);
                        border:1px dashed rgba(212,175,55,0.4);
                        transition:0.3s;
                    "
                    onmouseover="this.style.borderColor='rgba(212,175,55,0.8)'"
                    onmouseout="this.style.borderColor='rgba(212,175,55,0.4)'"
                    >

                        <input
                            type="file"
                            name="image"
                            accept="image/*"
                            style="
                                width:100%;
                                padding:18px;
                                color:var(--gray);
                                cursor:pointer;
                                background:transparent;
                                border:none;
                            "
                        >

                    </div>

                </div>

                <!-- SUBMIT -->
                <button
                    type="submit"
                    class="lux-btn landlord-submit-btn"
                >
                    👑 Publish Luxury Property
                </button>

            </form>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>