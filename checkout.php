<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = strtolower(trim($_GET['plan'] ?? ''));
$item = catalog_item($slug);
if (!$item) { header('Location: /services'); exit; }

// Send guests to log in first, then straight back here.
$user = require_login();

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

<header class="navbar scrolled ck-nav">
  <div class="container nav-inner">
    <a href="/" class="brand">
      <img src="assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <div class="app-nav" style="margin:0;">
      <span class="who">Logged in as <b><?= e($user['name']) ?></b> &middot;
        <a href="/dashboard" style="color:var(--blue-light);">Dashboard</a></span>
    </div>
  </div>
</header>

<section class="section ck-page" style="padding-top:36px;">
  <div class="container app-wrap wide">

    <div class="ck-hero">
      <div class="ck-logo-ring">
        <img src="assets/img/logo-mark.png" alt="">
      </div>
      <div>
        <div class="eyebrow">Secure Checkout</div>
        <h1>Complete your order</h1>
      </div>
    </div>

    <div class="ck-steps">
      <span class="ck-step done"><i>1</i> Selected</span>
      <span class="ck-step active"><i>2</i> Your details</span>
      <span class="ck-step"><i>3</i> Payment</span>
    </div>

    <div class="checkout-grid">

      <!-- ORDER SUMMARY -->
      <div class="glass-card ck-summary fade-up">
        <h3 class="ck-heading">Order Summary</h3>

        <div class="ck-line">
          <div>
            <b><?= e($item['name']) ?></b>
            <span><?= $item['recurring'] ? 'Monthly subscription' : 'One-time purchase' ?></span>
          </div>
          <span class="ck-amt" id="basePrice">&#8377;<?= number_format($item['amount']) ?></span>
        </div>

        <div class="ck-line ck-discount" id="discountRow" hidden>
          <div><b>Coupon <span id="couponTag"></span></b><span id="couponPct"></span></div>
          <span class="ck-amt ck-green" id="discountAmt"></span>
        </div>

        <div class="ck-total">
          <span>Total payable</span>
          <b id="totalAmt">&#8377;<?= number_format($item['amount']) ?></b>
        </div>

        <!-- coupon -->
        <div class="ck-coupon">
          <label for="couponInput">Have a coupon?</label>
          <div class="ck-coupon-row">
            <input type="text" id="couponInput" placeholder="Enter code" autocomplete="off">
            <button type="button" class="btn btn-ghost btn-sm" id="applyCoupon">Apply</button>
          </div>
          <p class="ck-coupon-msg" id="couponMsg"></p>
        </div>

        <?php if ($item['recurring']): ?>
          <ul class="ck-perks">
            <li>Unlimited spending limit</li>
            <li>Instant replacement, unlimited times</li>
            <li>24/7 priority support</li>
          </ul>
        <?php endif; ?>
      </div>

      <!-- DETAILS -->
      <div class="glass-card form-wrap ck-form fade-up delay" style="margin:0;">
        <h3 class="ck-heading">Your Details</h3>

        <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

        <form id="checkoutForm">
          <div class="form-row">
            <div class="form-group">
              <label for="fullname">Full Name</label>
              <input type="text" id="fullname" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="form-group">
              <label for="phone">WhatsApp Number</label>
              <input type="tel" id="phone" value="<?= e($user['phone']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" value="<?= e($user['email']) ?>" required>
          </div>

          <div class="form-group">
            <label for="business">Business / Brand Name</label>
            <input type="text" id="business" placeholder="Your business name" required>
          </div>

          <?php if ($item['recurring']): ?>
            <div class="form-group">
              <label for="bmid">Business Portfolio ID (BM ID)</label>
              <input type="text" id="bmid" placeholder="e.g. 123456789012345" required>
              <small class="field-hint">Meta Business Suite &rarr; Settings &rarr; Business Info</small>
            </div>
            <div class="form-group">
              <label for="niche">What do you advertise?</label>
              <input type="text" id="niche" placeholder="e.g. clothing store, real estate, coaching">
            </div>
            <div class="form-group">
              <label for="spend">Expected monthly ad spend</label>
              <select id="spend">
                <option value="">Select a range</option>
                <option>Under &#8377;50,000</option>
                <option>&#8377;50,000 &ndash; &#8377;2,00,000</option>
                <option>&#8377;2,00,000 &ndash; &#8377;10,00,000</option>
                <option>Above &#8377;10,00,000</option>
              </select>
            </div>
          <?php else: ?>
            <div class="form-group">
              <label for="niche">What will you use this for?</label>
              <input type="text" id="niche" placeholder="e.g. running ads for my clothing brand">
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="notes">Anything we should know? <span class="opt">(optional)</span></label>
            <textarea id="notes" rows="2" placeholder="Special requests or questions"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">
            <?= $paymentsReady ? 'Pay &#8377;' . number_format($item['amount']) : 'Place Order &mdash; &#8377;' . number_format($item['amount']) ?>
          </button>

          <p class="ck-foot">
            <?php if ($paymentsReady): ?>
              Secure payment via Razorpay. Your order shows up in your
              <a href="/dashboard">Dashboard</a> instantly.
            <?php else: ?>
              Online payment isn't switched on yet &mdash; we'll confirm this order
              on WhatsApp and share payment details there.
            <?php endif; ?>
          </p>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- success overlay -->
<div class="ck-done" id="ckDone" hidden>
  <div class="ck-done-box">
    <svg class="ck-check" viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M14 27l8 8 16-16"/></svg>
    <h2>Order placed!</h2>
    <p>Taking you to your dashboard&hellip;</p>
  </div>
</div>

<script>
const PLAN_SLUG     = <?= json_encode($slug) ?>;
const BASE_AMOUNT   = <?= (int)$item['amount'] ?>;
const PAYMENTS_READY= <?= $paymentsReady ? 'true' : 'false' ?>;
const RZP_KEY       = <?= json_encode($paymentsReady ? RAZORPAY_KEY_ID : '') ?>;
const PLAN_NAME     = <?= json_encode($item['name']) ?>;

let appliedCoupon = null;
let currentTotal  = BASE_AMOUNT;

const inr = n => '\u20B9' + Number(n).toLocaleString('en-IN');

const errBox   = document.getElementById('checkoutError');
const payBtn   = document.getElementById('payBtn');
const couponIn = document.getElementById('couponInput');
const couponMsg= document.getElementById('couponMsg');

function setPayLabel() {
  payBtn.innerHTML = (PAYMENTS_READY ? 'Pay ' : 'Place Order \u2014 ') + inr(currentTotal);
}

function showError(msg) {
  errBox.textContent = msg;
  errBox.style.display = 'block';
  payBtn.disabled = false;
  setPayLabel();
  errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ---------- coupon ---------- */
document.getElementById('applyCoupon').addEventListener('click', async () => {
  const code = couponIn.value.trim();
  if (!code) { couponIn.focus(); return; }

  couponMsg.textContent = 'Checking\u2026';
  couponMsg.className = 'ck-coupon-msg';

  try {
    const res = await fetch('api/apply_coupon.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ plan: PLAN_SLUG, code })
    });
    const d = await res.json();

    if (!d.ok) {
      appliedCoupon = null;
      currentTotal = BASE_AMOUNT;
      document.getElementById('discountRow').hidden = true;
      document.getElementById('totalAmt').textContent = inr(BASE_AMOUNT);
      couponMsg.textContent = d.error;
      couponMsg.className = 'ck-coupon-msg bad';
      setPayLabel();
      return;
    }

    appliedCoupon = d.code;
    currentTotal  = d.total;
    document.getElementById('couponTag').textContent   = d.code;
    document.getElementById('couponPct').textContent   = d.percent + '% off applied';
    document.getElementById('discountAmt').textContent = '\u2212' + inr(d.discount);
    document.getElementById('discountRow').hidden      = false;
    document.getElementById('totalAmt').textContent    = inr(d.total);
    couponMsg.textContent = 'Coupon applied \u2014 you save ' + inr(d.discount) + '!';
    couponMsg.className = 'ck-coupon-msg good';
    setPayLabel();
  } catch (e) {
    couponMsg.textContent = 'Could not check that code. Try again.';
    couponMsg.className = 'ck-coupon-msg bad';
  }
});

couponIn.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('applyCoupon').click(); }
});

/* ---------- submit ---------- */
document.getElementById('checkoutForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  errBox.style.display = 'none';
  payBtn.disabled = true;
  payBtn.textContent = 'Please wait\u2026';

  const el = id => document.getElementById(id);
  const extras = [
    el('niche') && el('niche').value.trim() ? 'Niche: ' + el('niche').value.trim() : '',
    el('spend') && el('spend').value ? 'Spend: ' + el('spend').value : '',
    el('notes') && el('notes').value.trim() ? 'Notes: ' + el('notes').value.trim() : ''
  ].filter(Boolean).join(' | ');

  const business = el('business').value.trim() + (extras ? ' (' + extras + ')' : '');

  try {
    const res = await fetch('api/create_order.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan: PLAN_SLUG,
        bmid: el('bmid') ? el('bmid').value.trim() : '',
        business: business,
        coupon: appliedCoupon
      })
    });
    const data = await res.json();
    if (!data.ok) return showError(data.error || 'Could not place your order.');

    if (!data.razorpay_order_id) {
      showDone(() => { window.location.href = 'dashboard.php?placed=1'; });
      return;
    }

    const rzp = new Razorpay({
      key: RZP_KEY,
      amount: data.razorpay_amount,
      currency: 'INR',
      name: 'BlueSky Agency',
      description: PLAN_NAME,
      image: 'assets/img/logo-mark.png',
      order_id: data.razorpay_order_id,
      prefill: { name: el('fullname').value, email: el('email').value, contact: el('phone').value },
      theme: { color: '#3b62ff' },
      handler: async function (r) {
        const v = await fetch('api/verify_payment.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_id: data.order_id,
            razorpay_order_id: r.razorpay_order_id,
            razorpay_payment_id: r.razorpay_payment_id,
            razorpay_signature: r.razorpay_signature
          })
        });
        const vd = await v.json();
        if (vd.ok) showDone(() => { window.location.href = 'dashboard.php?paid=1'; });
        else showError(vd.error || 'Payment verification failed. Please contact support.');
      },
      modal: { ondismiss: () => showError('Payment cancelled \u2014 your order is saved as pending.') }
    });
    rzp.open();
  } catch (err) {
    showError('Something went wrong. Please try again.');
  }
});

function showDone(then) {
  const o = document.getElementById('ckDone');
  o.hidden = false;
  requestAnimationFrame(() => o.classList.add('show'));
  setTimeout(then, 1400);
}
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
