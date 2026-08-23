<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST ID
|--------------------------------------------------------------------------
*/

$id = $_GET['id'] ?? null;

if (!$id) {

    die("Invalid truck request.");
}

/*
|--------------------------------------------------------------------------
| FETCH TRUCK REQUEST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE id = ?
    AND tenant_id = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $_SESSION['user_id']
]);

$request = $stmt->fetch();

if (!$request) {

    die("Truck request not found.");
}

/*
|--------------------------------------------------------------------------
| BLOCK EDITING IF ACCEPTED / ACTIVE
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $request['status'],
        ['accepted', 'in_transit', 'completed']
    )
) {

    die("This truck request can no longer be edited.");
}

?>

<style>

/* =========================================
   RESPONSIVE LAYOUT
========================================= */

.edit-request-wrapper {
    display:flex;
    min-height:100vh;
}

.edit-request-main {
    flex:1;
    margin-left:280px;
    padding:40px;
}

.edit-request-card {
    max-width:800px;
    padding:35px;
    border-radius:30px;
}

.form-input,
.form-textarea {
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    background:rgba(255,255,255,0.05);
    color:white;
    font-size:1rem;
}

.form-textarea {
    resize:none;
}

.form-label {
    display:block;
    margin-bottom:10px;
    color:gold;
    font-weight:bold;
}

.button-group {
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

.back-btn {
    padding:14px 22px;
    border-radius:16px;
    text-decoration:none;
    background:rgba(255,255,255,0.05);
    color:white;
}

/* =========================================
   MOBILE RESPONSIVE
========================================= */

@media (max-width: 768px) {

    .edit-request-main {

        margin-left:0;
        padding:
            110px 18px 30px 18px;
    }

    .edit-request-card {

        width:100%;
        padding:22px;
        border-radius:24px;
    }

    .edit-request-main h1 {

        font-size:2rem !important;
        line-height:1.3;
    }

    .button-group {

        flex-direction:column;
    }

    .button-group button,
    .button-group a {

        width:100%;
        text-align:center;
    }

    .form-input,
    .form-textarea {

        font-size:16px;
    }
}

</style>

<div class="edit-request-wrapper">

<main class="edit-request-main">

    <!-- HEADER -->
    <div style="margin-bottom:35px;">

        <h1 style="
            font-size:3rem;
            color:var(--gold);
            font-family:'Cinzel', serif;
            margin-bottom:12px;
        ">
            Edit Truck Request
        </h1>

        <p style="
            color:var(--gray);
            max-width:700px;
            line-height:1.8;
        ">
            Update your moving request details before a driver accepts the trip.
        </p>

    </div>

    <!-- FORM CARD -->
    <div class="lux-card edit-request-card">

        <form
            method="POST"
            action="../../api/trucks/update_truck_request.php"
        >

            <input
                type="hidden"
                name="request_id"
                value="<?= $request['id'] ?>"
            >

            <!-- PICKUP -->
            <div style="margin-bottom:25px;">

                <label class="form-label">
                    Pickup Location
                </label>

                <input
                    type="text"
                    name="pickup_location"
                    required
                    class="form-input"
                    value="<?= htmlspecialchars($request['pickup_location'] ?? '') ?>"
                >

            </div>

            <!-- DESTINATION -->
            <div style="margin-bottom:25px;">

                <label class="form-label">
                    Destination
                </label>

                <input
                    type="text"
                    name="destination"
                    required
                    class="form-input"
                    value="<?= htmlspecialchars($request['destination'] ?? '') ?>"
                >

            </div>

            <!-- MOVING DATE -->
            <div style="margin-bottom:25px;">

                <label class="form-label">
                    Moving Date
                </label>

                <input
                    type="date"
                    name="moving_date"
                    required
                    class="form-input"
                    value="<?= htmlspecialchars($request['moving_date'] ?? '') ?>"
                >

            </div>

            <!-- NOTES -->
            <div style="margin-bottom:30px;">

                <label class="form-label">
                    Additional Notes
                </label>

                <textarea
                    name="notes"
                    rows="5"
                    class="form-textarea"
                ><?= htmlspecialchars($request['notes'] ?? '') ?></textarea>

            </div>

            <!-- BUTTONS -->
            <div class="button-group">

                <button
                    type="submit"
                    class="lux-btn"
                >
                    Save Changes
                </button>

                <a
                    href="my_bookings.php"
                    class="back-btn"
                >
                    ← Back
                </a>

            </div>

        </form>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>