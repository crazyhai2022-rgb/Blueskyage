<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = strtolower(trim($_GET['plan'] ?? $_GET['item'] ?? ''));
$item = catalog_item($slug);
if (!$item) { header('Location: services.html'); exit; }

// Buying requires an account, so every order shows up in a dashboard.
$user = require_login();

$fields        = item_fields($slug);
$paymentsReady = defined('RAZORPAY_KEY_ID')
    && RAZORPAY_KEY_ID !== 'YOUR_RAZORPAY_KEY_ID'
    && RAZORPAY_KEY_ID !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout &mdash; <?= e($item['name']) ?> | BlueSky Agency</title>
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

<section class="section" style="padding-top:34px;">
  <div class="container app-wrap wide">

    <!-- animated logo header -->
    <div class="ck-hero">
      <div class="ck-logo-ring">
        <span class="ck-ring ck-ring-1"></span>
        <span class="ck-ring ck-ring-2"></span>
        <img src="assets/img/logo-mark.png" alt="" class="ck-logo">
      </div>
      <div class="ck-hero-text">
        <div class="eyebrow">Secure Checkout</div>
        <h1><?= e($item['name']) ?></h1>
        <p><?= $item['recurring'] ? 'Monthly subscription &middot; renews every 30 days' : 'One-time purchase' ?></p>
      </div>
    </div>

    <div class="ck-steps">
      <span class="ck-step done">1 &middot; Select</span>
      <span class="ck-step active">2 &middot; Your Details</span>
      <span class="ck-step">3 &middot; Payment</span>
    </div>

    <div class="checkout-grid">

      <!-- details form -->
      <div class="glass-card form-wrap" style="margin:0;">
        <h3 class="ck-heading">Your Details</h3>
        <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

        <form id="checkoutForm">
          <?php foreach ($fields as [$name, $label, $placeholder, $required]): ?>
            <div class="form-group">
              <label for="f_<?= e($name) ?>">
                <?= e($label) ?><?php if (!$required): ?> <span style="color:var(--mist-dim);">(optional)</span><?php endif; ?>
              </label>
              <input type="text" id="f_<?= e($name) ?>" name="<?= e($name) ?>"
                     placeholder="<?= e($placeholder) ?>" <?= $required ? 'required' : '' ?>>
            </div>
          <?php endforeach; ?>

          <div class="form-group">
            <label for="f_notes">Anything else we should know? <span style="color:var(--mist-dim);">(optional)</span></label>
            <textarea id="f_notes" name="notes" rows="2" placeholder="Special requirements, timelines, questions…"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">
            <?= $paymentsReady ? 'Pay &amp; Confirm' : 'Place Order' ?>
          </button>
          <p class="ck-foot">
            <?= $paymentsReady
                ? 'Secure payment via Razorpay. Your order appears in your <a href="dashboard.php">Dashboard</a> right after.'
                : "Online payment isn't switched on yet &mdash; we'll confirm this order over WhatsApp and share payment details there." ?>
          </p>
        </form>
      </div>

      <!-- summary + coupon -->
      <div class="glass-card ck-summary">
        <h3 class="ck-heading">Order Summary</h3>

        <div class="ck-line">
          <div>
            <b><?= e($item['name']) ?></b>
            <span><?= $item['recurring'] ? 'Monthly' : 'One-time' ?></span>
          </div>
          <span class="ck-amt">&#8377;<?= number_format($item['amount']) ?></span>
        </div>

        <div class="ck-line ck-discount" id="discountLine" style="display:none;">
          <div><b id="discountLabel">Coupon</b><span id="discountSub">applied</span></div>
          <span class="ck-amt" id="discountAmt">&minus;&#8377;0</span>
        </div>

        <div class="ck-total">
          <span>Total</span>
          <b id="totalAmt">&#8377;<?= number_format($item['amount']) ?></b>
        </div>

        <div class="coupon-box">
          <label for="couponCode">Have a coupon?</label>
          <div class="coupon-row">
            <input type="text" id="couponCode" placeholder="Enter code" autocomplete="off">
            <button type="button" class="btn btn-ghost btn-sm" id="applyCoupon">Apply</button>
          </div>
          <p class="coupon-msg" id="couponMsg"></p>
        </div>

        <a href="services.html" class="ck-edit">&larr; Choose a different plan</a>
      </div>

    </div>
  </div>
</section>

<script>
const planSlug      = <?= json_encode($slug) ?>;
const planName      = <?= json_encode($item['name']) ?>;
const baseAmount    = <?= (int)$item['amount'] ?>;
const paymentsReady = <?= $paymentsReady ? 'true' : 'false' ?>;
const razorpayKeyId = <?= json_encode($paymentsReady ? RAZORPAY_KEY_ID : '') ?>;
const buyer         = <?= json_encode(['name' => $user['name'], 'email' => $user['email'], 'contact' => $user['phone']]) ?>;

let appliedCoupon = null;
let finalAmount   = baseAmount;

const form     = document.getElementById('checkoutForm');
const errBox   = document.getElementById('checkoutError');
const payBtn   = document.getElementById('payBtn');
const payLabel = payBtn.innerHTML;

const money = (n) => '\u20B9' + Number(n).toLocaleString('en-IN');

function showError(msg) {
  errBox.textContent = msg;
  errBox.style.display = 'block';
  payBtn.disabled = false;
  payBtn.innerHTML = payLabel;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ---------------- coupon ---------------- */
const couponInput = document.getElementById('couponCode');
const couponMsg   = document.getElementById('couponMsg');

document.getElementById('applyCoupon').addEventListener('click', async function () {
  const code = couponInput.value.trim();
  if (!code) return;

  couponMsg.textContent = 'Checking\u2026';
  couponMsg.className = 'coupon-msg';

  try {
    const res = await fetch('api/check_coupon.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code, plan: planSlug })
    });
    const data = await res.json();

    if (!data.ok) {
      appliedCoupon = null;
      finalAmount = baseAmount;
      document.getElementById('discountLine').style.display = 'none';
      document.getElementById('totalAmt').textContent = money(baseAmount);
      couponMsg.textContent = data.error;
      couponMsg.className = 'coupon-msg bad';
      return;
    }

    appliedCoupon = code.toUpperCase();
    finalAmount = data.final;
    document.getElementById('discountLine').style.display = 'flex';
    document.getElementById('discountLabel').textContent = appliedCoupon;
    document.getElementById('discountSub').textContent = data.percent + '% off';
    document.getElementById('discountAmt').innerHTML = '\u2212' + money(data.discount);
    document.getElementById('totalAmt').textContent = money(data.final);
    couponMsg.textContent = 'Coupon applied \u2014 you save ' + money(data.discount) + '.';
    couponMsg.className = 'coupon-msg good';
  } catch (err) {
    couponMsg.textContent = 'Could not check that code. Try again.';
    couponMsg.className = 'coupon-msg bad';
  }
});

couponInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('applyCoupon').click(); }
});

/* ---------------- submit ---------------- */
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  errBox.style.display = 'none';
  payBtn.disabled = true;
  payBtn.textContent = 'Please wait\u2026';

  const details = {};
  form.querySelectorAll('input[name], textarea[name]').forEach((el) => {
    if (el.value.trim()) details[el.name] = el.value.trim();
  });

  try {
    const res = await fetch('api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ plan: planSlug, coupon: appliedCoupon, details })
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
      description: planName,
      image: 'assets/img/logo-mark.png',
      order_id: data.razorpay_order_id,
      prefill: buyer,
      theme: { color: '#3b62ff' },
      handler: async function (response) {
        const vRes = await fetch('api/verify_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_id: data.order_id,
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
