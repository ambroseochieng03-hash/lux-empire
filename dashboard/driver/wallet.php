<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../classes/Payment.php';
require_once '../../classes/PaymentWaiver.php';
require_once '../../config/csrf.php';

$driverId = (int) Session::user()['id'];
$payment = new Payment();
$balance = $payment->getWalletBalance($driverId);
$isWaived = PaymentWaiver::isWaived($driverId, 'driver');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<div style="display:flex; min-height:100vh;">
<main class="tenant-main" style="flex:1; padding:40px; margin-left:280px;">

    <h1 style="font-family:'Cinzel', serif; color:var(--gold); font-size:2.5rem; margin-bottom:20px;">
        Commission Wallet
    </h1>

    <div class="lux-card" style="padding:30px; border-radius:24px; max-width:500px;">

        <p style="color:var(--gray); margin-bottom:10px;">Current Balance</p>
        <h2 style="color:<?php echo $balance < 0 ? '#ff6b6b' : 'lightgreen'; ?>; font-size:2.5rem;">
            KES <?php echo number_format($balance, 2); ?>
        </h2>

        <?php if ($isWaived): ?>
            <p style="color:var(--gold); margin-top:10px;">
                <i class="fa-solid fa-gift"></i> You have complimentary commission-free access.
            </p>
        <?php else: ?>
            <p style="color:var(--gray); margin-top:14px; line-height:1.6;">
                A <?php echo TRUCK_COMMISSION_PERCENT; ?>% commission is deducted from each completed trip.
                You can accept jobs at any balance, including zero or negative — top up any time.
            </p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;">
                <button type="button" class="lux-btn" id="topUpWalletBtn">
                    Top Up Wallet
                </button>
                <button type="button" class="lux-btn lux-payment-retry-btn" id="reportPaymentBtn">
                    Already Paid? Report It
                </button>
            </div>
        <?php endif; ?>

    </div>

</main>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/payment-modal.css">
<script>
    window.LUX_PAYMENT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/payment-modal.js"></script>
<script>
    const topUpBtn = document.getElementById('topUpWalletBtn');
    if (topUpBtn) {
        topUpBtn.addEventListener('click', () => {
            window.LuxPayment.open({
                purpose: 'driver_wallet_topup',
                title: 'Top Up Wallet',
                description: <?php echo json_encode($balance <= 0 ? 'Your wallet balance is at zero or below. You can still accept jobs — topping up just keeps your account in good standing.' : ''); ?>,
                onSuccess: () => window.location.reload(),
            });
        });
    }

    // Covers BOTH recovery cases:
    //  - paid via Paybill directly, never touched STK at all
    //  - paid via STK, but our side never confirmed it (rare, but
    //    the fallback exists so nobody's money is ever just stuck)
    // Either way: paste the message, server tries to auto-verify,
    // and if it can't, it queues for admin to confirm manually.
    const reportBtn = document.getElementById('reportPaymentBtn');
    if (reportBtn) {
        reportBtn.addEventListener('click', () => {
            window.LuxPayment.open({
                purpose: 'driver_wallet_topup',
                title: 'Report a Payment',
                startStep: 'paybill',
                onSuccess: () => window.location.reload(),
            });
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
