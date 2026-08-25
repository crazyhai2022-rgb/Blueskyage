<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = strtolower(trim($_GET['plan'] ?? ''));
$item = catalog_item($slug);
if (!$item) { header('Location: /services'); exit; }

// Send guests to log in first, then straight back here.
$user = require_login();

$fields   = $item['fields'] ?? [];
$usdRate  = $item['usd_rate'] ?? 105;

/* The stored phone may already carry a country code — split it so the
   selector and the number field don't end up showing it twice. */
$storedPhone = trim($user['phone'] ?? '');
$localPhone  = $storedPhone;
foreach (array_keys(country_codes()) as $cc) {
    if (str_starts_with($storedPhone, $cc)) {
        $localPhone = trim(substr($storedPhone, strlen($cc)));
        break;
    }
}

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
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
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

        <div class="ck-line" id="depositRow" hidden>
          <div><b>Ad Account Deposit</b><span id="depositLabel"></span></div>
          <span class="ck-amt" id="depositAmt"></span>
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

      </div>

      <!-- DETAILS -->
      <div class="glass-card form-wrap ck-form fade-up delay" style="margin:0;">
        <h3 class="ck-heading">Your Details</h3>

        <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

        <form id="checkoutForm">
          <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" value="<?= e($user['name']) ?>" required>
          </div>

          <div class="form-group">
            <label for="phone">WhatsApp Number</label>
            <div class="phone-row">
              <select id="ccode" aria-label="Country code">
                <?php foreach (country_codes() as $code => $country): ?>
                  <option value="<?= e($code) ?>"<?= $code === '+91' ? ' selected' : '' ?>><?= e($code) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="tel" id="phone" value="<?= e($localPhone) ?>" placeholder="98765 43210" required>
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" value="<?= e($user['email']) ?>" required>
          </div>

<?php if (in_array('business', $fields, true)): ?>
          <div class="form-group">
            <label for="business">Business / Brand Name <span class="opt">(optional)</span></label>
            <input type="text" id="business" placeholder="Your business name">
          </div>
<?php endif; ?>

<?php if (in_array('bmid', $fields, true)): ?>
          <div class="form-group">
            <label for="bmid">Business Portfolio ID (BM ID)</label>
            <input type="text" id="bmid" placeholder="e.g. 123456789012345" required>
            <small class="field-hint">Meta Business Suite &rarr; Settings &rarr; Business Info</small>
          </div>
<?php endif; ?>

<?php if (in_array('profile_link', $fields, true)): ?>
          <div class="form-group">
            <label for="profile_link">Facebook Profile Link</label>
            <input type="url" id="profile_link" placeholder="https://facebook.com/yourprofile" required>
            <small class="field-hint">We add you to the page from this profile.</small>
          </div>
<?php endif; ?>

<?php if (in_array('page_name', $fields, true)): ?>
          <div class="form-group">
            <label for="page_name">What page name do you want?</label>
            <input type="text" id="page_name" placeholder="The name your page should carry" required>
            <small class="field-hint">This plan lets us set the name you choose.</small>
          </div>
<?php endif; ?>

<?php if (in_array('deposit', $fields, true)): ?>
          <div class="form-group">
            <label for="deposit">Ad Account Deposit Fund</label>
            <div class="deposit-row">
              <select id="deposit">
                <option value="0">$0 — add funds later</option>
                <?php foreach (deposit_options() as $usd): ?>
                  <option value="<?= (int)$usd ?>">$<?= (int)$usd ?></option>
                <?php endforeach; ?>
              </select>
              <div class="deposit-inr" id="depositInr">&#8377;0</div>
            </div>
            <small class="field-hint">Loaded straight into your ad account at &#8377;<?= (int)$usdRate ?> per $. Minimum first deposit is $15.</small>
          </div>
<?php endif; ?>

          <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">
            <?= $paymentsReady ? 'Pay &#8377;' . number_format($item['amount']) : 'Place Order &mdash; &#8377;' . number_format($item['amount']) ?>
          </button>

          <p class="ck-foot">
            <?php if ($paymentsReady): ?>
              Your payment is encrypted and processed securely by Razorpay &mdash; the moment it's confirmed, your order lands in your
              <a href="/dashboard">Dashboard</a> instantly.
            <?php else: ?>
              Online payment isn't switched on yet &mdash; we'll confirm this order
              on WhatsApp and share payment details there.
            <?php endif; ?>
          </p>
          <?php if ($paymentsReady): ?>
            <div class="ck-badge">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Secured by</span>
              <img src="/assets/img/razorpay.svg" alt="Razorpay">
            </div>
          <?php endif; ?>
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
const USD_RATE      = <?= (int)$usdRate ?>;

let appliedCoupon = null;
let couponDiscount = 0;
let depositUsd    = 0;
let currentTotal  = BASE_AMOUNT;

/* Total = plan price − coupon discount + ad funds.
   The coupon only ever discounts the plan, never the deposit — that money
   goes into the ad account at face value. */
function recalcTotal() {
  const depositInr = depositUsd * USD_RATE;
  currentTotal = Math.max(1, BASE_AMOUNT - couponDiscount) + depositInr;

  const row = document.getElementById('depositRow');
  if (row) {
    if (depositUsd > 0) {
      document.getElementById('depositLabel').textContent = '$' + depositUsd + ' at \u20B9' + USD_RATE + ' per $';
      document.getElementById('depositAmt').textContent = inr(depositInr);
      row.hidden = false;
    } else {
      row.hidden = true;
    }
  }

  document.getElementById('totalAmt').textContent = inr(currentTotal);
  setPayLabel();
}

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
      appliedCoupon  = null;
      couponDiscount = 0;
      document.getElementById('discountRow').hidden = true;
      couponMsg.textContent = d.error;
      couponMsg.className = 'ck-coupon-msg bad';
      recalcTotal();
      return;
    }

    appliedCoupon  = d.code;
    couponDiscount = d.discount;
    document.getElementById('couponTag').textContent   = d.code;
    document.getElementById('couponPct').textContent   = d.percent + '% off applied';
    document.getElementById('discountAmt').textContent = '\u2212' + inr(d.discount);
    document.getElementById('discountRow').hidden      = false;
    couponMsg.textContent = 'Coupon applied \u2014 you save ' + inr(d.discount) + '!';
    couponMsg.className = 'ck-coupon-msg good';
    recalcTotal();
  } catch (e) {
    couponMsg.textContent = 'Could not check that code. Try again.';
    couponMsg.className = 'ck-coupon-msg bad';
  }
});

couponIn.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('applyCoupon').click(); }
});

/* ---------- deposit ---------- */
const depositSel = document.getElementById('deposit');
if (depositSel) {
  depositSel.addEventListener('change', () => {
    depositUsd = parseInt(depositSel.value, 10) || 0;
    document.getElementById('depositInr').textContent = inr(depositUsd * USD_RATE);
    recalcTotal();
  });
}

/* ---------- submit ---------- */
document.getElementById('checkoutForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  errBox.style.display = 'none';
  payBtn.disabled = true;
  payBtn.textContent = 'Please wait\u2026';

  const el = id => document.getElementById(id);
  const val = id => (el(id) ? el(id).value.trim() : '');

  // Everything the plan asked for, kept together on the order record.
  const extras = [
    val('profile_link') ? 'Profile: ' + val('profile_link') : '',
    val('page_name') ? 'Page name: ' + val('page_name') : '',
    depositUsd > 0 ? 'Deposit: $' + depositUsd + ' (\u20B9' + (depositUsd * USD_RATE) + ')' : ''
  ].filter(Boolean).join(' | ');

  const businessName = val('business') || '—';
  const business = businessName + (extras ? ' (' + extras + ')' : '');

  try {
    const res = await fetch('api/create_order.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan: PLAN_SLUG,
        bmid: val('bmid'),
        business: business,
        coupon: appliedCoupon,
        deposit_usd: depositUsd,
        phone: (el('ccode') ? el('ccode').value : '') + ' ' + val('phone')
      })
    });
    const data = await res.json();
    if (!data.ok) return showError(data.error || 'Could not place your order.');

    if (!data.razorpay_order_id) {
      showDone(() => { window.location.href = '/dashboard?placed=1'; });
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
      prefill: {
        name: val('fullname'),
        email: val('email'),
        contact: (el('ccode') ? el('ccode').value : '') + val('phone')
      },
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
        if (vd.ok) showDone(() => { window.location.href = '/dashboard?paid=1'; });
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
<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
