<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/Booking.php';

$bookingModel = new Booking();

$bookings = $bookingModel->getBookingsByLandlord($_SESSION['user_id']);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
/* =========================================
   LANDLORD BOOKING REQUESTS RESPONSIVE
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

.landlord-header{
    margin-bottom:35px;
}

.landlord-title{
    font-family:'Cinzel', serif;
    color:var(--gold);
    font-size:3rem;
    line-height:1.2;
    margin-bottom:10px;
}

.landlord-subtitle{
    color:var(--gray);
    line-height:1.8;
    max-width:700px;
}

.bookings-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
    gap:30px;
}

.booking-card{
    border-radius:24px;
    overflow:hidden;
    height:100%;
    display:flex;
    flex-direction:column;
}

.booking-image{
    height:220px;
    overflow:hidden;
    background:rgba(255,255,255,0.04);
}

.booking-image img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.booking-placeholder{
    width:100%;
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--gold);
    font-size:2rem;
}

.booking-content{
    padding:25px;
    display:flex;
    flex-direction:column;
    flex:1;
}

.booking-property-title{
    color:white;
    margin-bottom:10px;
    font-size:1.5rem;
    line-height:1.4;
    word-break:break-word;
}

.booking-location{
    color:var(--gray);
    margin-bottom:12px;
    line-height:1.6;
}

.booking-price{
    color:var(--gold);
    font-weight:bold;
    margin-bottom:18px;
    font-size:1.1rem;
}

.booking-tenant-box{
    background:rgba(255,255,255,0.05);
    padding:15px;
    border-radius:14px;
    margin-bottom:18px;
    color:var(--gray);
    font-size:0.95rem;
    line-height:1.8;
    word-break:break-word;
}

.booking-status{
    font-weight:bold;
    margin-bottom:22px;
}

.booking-actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.booking-btn{
    flex:1;
    min-width:120px;
    text-align:center;
    padding:14px;
    border-radius:14px;
    font-weight:bold;
    text-decoration:none;
    transition:0.3s;
    box-sizing:border-box;
}

.booking-btn:hover{
    transform:translateY(-2px);
}

.booking-btn-approve{
    background:linear-gradient(135deg,#00c853,#64dd17);
    color:black;
}

.booking-btn-reject{
    background:#ff3b3b;
    color:white;
}

.booking-finished{
    text-align:center;
    color:var(--gray);
    padding-top:5px;
}

.empty-card{
    padding:50px;
    text-align:center;
    grid-column:1/-1;
    border-radius:28px;
}

.empty-title{
    color:var(--gold);
    margin-bottom:15px;
    font-size:2rem;
}

.empty-text{
    color:var(--gray);
    line-height:1.7;
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

    .landlord-title{
        font-size:2.5rem;
    }

    .bookings-grid{
        grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
        gap:24px;
    }
}

/* =========================================
   MOBILE
========================================= */

@media (max-width: 768px){

    .landlord-main{
        padding:22px 16px 40px;
    }

    .landlord-title{
        font-size:2rem;
    }

    .landlord-subtitle{
        font-size:0.95rem;
    }

    .bookings-grid{
        grid-template-columns:1fr;
        gap:22px;
    }

    .booking-image{
        height:200px;
    }

    .booking-content{
        padding:20px;
    }

    .booking-property-title{
        font-size:1.3rem;
    }

    .booking-actions{
        flex-direction:column;
    }

    .booking-btn{
        width:100%;
    }

    .empty-card{
        padding:35px 20px;
    }
}

/* =========================================
   SMALL MOBILE
========================================= */

@media (max-width: 480px){

    .landlord-main{
        padding:18px 12px 35px;
    }

    .landlord-title{
        font-size:1.7rem;
    }

    .booking-image{
        height:180px;
    }

    .booking-content{
        padding:18px;
    }

    .booking-property-title{
        font-size:1.15rem;
    }

    .booking-tenant-box{
        font-size:0.9rem;
    }

    .booking-btn{
        padding:13px;
        border-radius:12px;
        font-size:0.95rem;
    }

    .empty-title{
        font-size:1.6rem;
    }
}
</style>

<div class="landlord-layout">

    <!-- MAIN -->
    <main class="landlord-main">

        <!-- HEADER -->
        <div class="landlord-header">

            <h1 class="landlord-title">
                👑 Booking Requests
            </h1>

            <p class="landlord-subtitle">
                Manage tenant requests for your luxury properties.
            </p>

        </div>

        <!-- BOOKINGS GRID -->
        <div class="bookings-grid">

            <?php if (count($bookings) > 0): ?>

                <?php foreach ($bookings as $booking): ?>

                    <div class="lux-card booking-card">

                        <!-- IMAGE -->
                        <div class="booking-image">

                            <?php if (!empty($booking['image'])): ?>

                                <img
                                    src="../../assets/uploads/house_images/<?php echo htmlspecialchars($booking['image']); ?>"
                                    alt="Luxury Property"
                                >

                            <?php else: ?>

                                <div class="booking-placeholder">
                                    🏛
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- CONTENT -->
                        <div class="booking-content">

                            <h2 class="booking-property-title">
                                <?php echo htmlspecialchars($booking['title']); ?>
                            </h2>

                            <br>

                            <!-- HOUSE RATING -->
                            <div style="
                                display:flex;
                                align-items:center;
                                gap:10px;
                                margin-bottom:18px;
                                flex-wrap:wrap;
                            ">

                                <?php
                                    $rating = (int)($booking['rating'] ?? 0);
                                ?>

                                <div style="
                                    display:flex;
                                    gap:3px;
                                    font-size:1.1rem;
                                ">

                                    <?php for($i = 1; $i <= 5; $i++): ?>

                                        <span style="
                                            color: <?php echo ($i <= $rating) ? '#d4af37' : 'rgba(255,255,255,0.2)'; ?>;
                                            text-shadow: <?php echo ($i <= $rating) ? '0 0 8px rgba(212,175,55,0.4)' : 'none'; ?>;
                                            transition:0.3s;
                                        ">
                                            ★
                                        </span>

                                    <?php endfor; ?>

                                </div>

                                <span style="
                                    color:var(--gray);
                                    font-size:0.9rem;
                                ">
                                    <?php echo $rating; ?>/5 Property Rating
                                </span>

                            </div>

                            <p class="booking-location">
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </p>

                            <p class="booking-price">
                                KES <?php echo number_format($booking['price']); ?>
                            </p>

                            <!-- TENANT INFO -->
                            <div class="booking-tenant-box">

                                
                                <?php echo htmlspecialchars($booking['tenant_name']); ?>

                                <br>
                                <?php echo htmlspecialchars($booking['tenant_phone']); ?>

                                <br>
                                <?php echo htmlspecialchars($booking['tenant_email']); ?>

                            </div>

                            <!-- STATUS -->
                            <?php
                                $status = $booking['status'];
                                $color = "gray";

                                if ($status == "pending") {
                                    $color = "orange";
                                }

                                if ($status == "approved") {
                                    $color = "lightgreen";
                                }

                                if ($status == "rejected") {
                                    $color = "red";
                                }
                            ?>

                            <div
                                class="booking-status"
                                style="color:<?php echo $color; ?>;"
                            >
                                Status:
                                <?php echo ucfirst($status); ?>
                            </div>

                            <!-- ACTIONS -->
                            <?php if ($status == "pending"): ?>

                                <div class="booking-actions">

                                    <!-- APPROVE -->
                                    <a
                                        href="../../api/houses/update_booking_status.php?id=<?php echo $booking['id']; ?>&status=approved"
                                        class="booking-btn booking-btn-approve"
                                    >
                                        Approve
                                    </a>

                                    <!-- REJECT -->
                                    <a
                                        href="../../api/houses/update_booking_status.php?id=<?php echo $booking['id']; ?>&status=rejected"
                                        class="booking-btn booking-btn-reject"
                                    >
                                        Reject
                                    </a>

                                </div>

                            <?php else: ?>

                                <div class="booking-finished">
                                    Decision completed
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card empty-card">

                    <h2 class="empty-title">
                        No Booking Requests
                    </h2>

                    <p class="empty-text">
                        Tenant requests will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>