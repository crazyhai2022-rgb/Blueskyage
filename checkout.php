<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';

$user = require_login();

// Landing here with ?plan=slug (from a "Buy Now" button) adds that item first.
if (!empty($_GET['plan'])) {
    cart_add($_GET['plan']);
    header('Location: checkout.php');
    exit;
}

if (cart_is_empty()) {
    header('Location: cart.php');
    exit;
}

$items         = cart_items();
$total         = cart_total();
$needsBmId     = cart_has_recurring();
$paymentsReady = defined('RAZORPAY_KEY_ID')
    && RAZORPAY_KEY_ID !== 'YOUR_RAZORPAY_KEY_ID'
    && RAZORPAY_KEY_ID !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — BlueSky Agency</title>
<link rel="icon" href="assets/img/logo-mark.png">
<link rel="stylesheet" href="assets/css/style.css">
<?php if ($paymentsReady): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<header class="navbar scrolled">
  <div class="container nav-inner">
    <a href="index.html" class="brand">
      <img src="assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <div class="app-nav" style="margin:0;">
      <span class="who">Logged in as <b><?= e($user['name']) ?></b> &middot;
        <a href="dashboard.php" style="color:var(--blue-light);">Dashboard</a></span>
    </div>
  </div>
</header>

<section class="section" style="padding-top:40px;">
  <div class="container app-wrap wide">
    <div class="eyebrow">Checkout</div>
    <h1 style="margin-bottom:24px;">Review &amp; Confirm</h1>

    <div class="checkout-grid">

      <div class="glass-card">
        <h3 class="ck-heading">Order Summary</h3>
        <?php foreach ($items as $i): ?>
          <div class="ck-line">
            <div>
              <b><?= e($i['name']) ?></b>
              <span><?= $i['recurring'] ? 'Monthly' : 'One-time' ?><?= $i['qty'] > 1 ? ' &times; ' . $i['qty'] : '' ?></span>
            </div>
            <span class="ck-amt">&#8377;<?= number_format($i['subtotal']) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="ck-total">
          <span>Total</span><b>&#8377;<?= number_format($total) ?></b>
        </div>
        <a href="cart.php" class="ck-edit">&larr; Edit cart</a>
      </div>

      <div class="glass-card form-wrap" style="margin:0;">
        <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

        <form id="checkoutForm">
          <?php if ($needsBmId): ?>
            <div class="form-group">
              <label for="bmid">Business Portfolio ID (BM ID)</label>
              <input type="text" id="bmid" name="bmid" placeholder="e.g. 123456789012345" required>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="business">Business / Brand Name
              <?php if (!$needsBmId): ?><span style="color:var(--mist-dim);">(optional)</span><?php endif; ?></label>
            <input type="text" id="business" name="business" placeholder="Your business name" <?= $needsBmId ? 'required' : '' ?>>
          </div>

          <?php if ($paymentsReady): ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">Pay &#8377;<?= number_format($total) ?></button>
            <p class="ck-foot">Secure payment via Razorpay. Your order appears in your <a href="dashboard.php">Dashboard</a> right after.</p>
          <?php else: ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">Place Order &mdash; &#8377;<?= number_format($total) ?></button>
            <p class="ck-foot">Online payment isn't switched on yet, so we'll confirm this order over WhatsApp and share payment details there.</p>
          <?php endif; ?>
        </form>
      </div>

    </div>
  </div>
</section>

<script>
const paymentsReady = <?= $paymentsReady ? 'true' : 'false' ?>;
const razorpayKeyId = <?= json_encode($paymentsReady ? RAZORPAY_KEY_ID : '') ?>;
const buyer = <?= json_encode(['name' => $user['name'], 'email' => $user['email'], 'contact' => $user['phone']]) ?>;

const form = document.getElementById('checkoutForm');
const errBox = document.getElementById('checkoutError');
const payBtn = document.getElementById('payBtn');
const payLabel = payBtn.textContent.trim();

function showError(msg) {
  errBox.textContent = msg;
  errBox.style.display = 'block';
  payBtn.disabled = false;
  payBtn.textContent = payLabel;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

form.addEventListener('submit', async function (e) {
  e.preventDefault();
  errBox.style.display = 'none';
  payBtn.disabled = true;
  payBtn.textContent = 'Please wait\u2026';

  const bmidEl = document.getElementById('bmid');

  try {
    const res = await fetch('api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        bmid: bmidEl ? bmidEl.value.trim() : '',
        business: document.getElementById('business').value.trim()
      })
    });
    const data = await res.json();
    if (!data.ok) return showError(data.error || 'Could not place your order.');

    if (!data.razorpay_order_id) {
      window.location.href = 'dashboard.php?placed=1';
      return;
    }

    const rzp = new Razorpay({
      key: razorpayKeyId,
      amount: data.razorpay_amount,
      currency: 'INR',
      name: 'BlueSky Agency',
      description: 'Order #' + data.group_id,
      image: 'assets/img/logo-mark.png',
      order_id: data.razorpay_order_id,
      prefill: buyer,
      theme: { color: '#3b62ff' },
      handler: async function (response) {
        const vRes = await fetch('api/verify_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            group_id: data.group_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature
          })
        });
        const vData = await vRes.json();
        if (vData.ok) window.location.href = 'dashboard.php?paid=1';
        else showError(vData.error || 'Payment verification failed. Please contact support.');
      },
      modal: { ondismiss: function () { showError('Payment cancelled \u2014 your order is saved as pending.'); } }
    });
    rzp.open();
  } catch (err) {
    showError('Something went wrong. Please try again.');
  }
});
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
