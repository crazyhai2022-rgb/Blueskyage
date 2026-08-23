<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login(); // must be logged in to buy

$slug = $_GET['plan'] ?? $_GET['item'] ?? 'basic';
$item = catalog_item($slug);
if (!$item) {                       // unknown slug — send them back to the plans page
    header('Location: services.html');
    exit;
}
$slug      = strtolower(trim($slug));
$plan      = $item['name'];
$amount    = $item['amount'];
$recurring = $item['recurring'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — BlueSky Agency</title>
<link rel="icon" href="assets/img/logo-mark.png">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
      <span class="who">Logged in as <b><?= e($user['name']) ?></b> · <a href="dashboard.php" style="color:var(--blue-light);">Dashboard</a></span>
    </div>
  </div>
</header>

<section class="section" style="padding-top:40px;">
  <div class="container app-wrap">
    <div class="eyebrow">Checkout</div>
    <h1 style="margin-bottom:24px;"><?= $recurring ? 'Activate Your Ad Account' : 'Complete Your Order' ?></h1>

    <div class="glass-card form-wrap" id="formCard">
      <div class="form-summary">
        <span><?= $recurring ? 'Selected Plan' : 'Selected Product' ?></span>
        <b><?= e($plan) ?> — ₹<?= number_format($amount) ?><?= $recurring ? '/mo' : '' ?></b>
      </div>

      <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

      <form id="checkoutForm">
<?php if ($recurring): ?>
        <div class="form-group">
          <label for="bmid">Business Portfolio ID (BM ID)</label>
          <input type="text" id="bmid" name="bmid" placeholder="e.g. 123456789012345" required>
        </div>
        <div class="form-group">
          <label for="business">Business Name</label>
          <input type="text" id="business" name="business" placeholder="Your business name">
        </div>
<?php else: ?>
        <div class="form-group">
          <label for="business">Business / Brand Name <span style="color:var(--mist-dim);">(optional)</span></label>
          <input type="text" id="business" name="business" placeholder="Where this will be used">
        </div>
<?php endif; ?>
        <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">Pay ₹<?= number_format($amount) ?> &amp; <?= $recurring ? 'Activate' : 'Order' ?></button>
      </form>
      <p style="margin-top:14px;font-size:12.5px;color:var(--mist);text-align:center;">
        After payment, your order appears instantly in your <a href="dashboard.php" style="color:var(--blue-light);">Dashboard</a> as "Preparing" — we'll confirm the details there.
      </p>
    </div>
  </div>
</section>

<script>
const planSlug = <?= json_encode($slug) ?>;
const plan = <?= json_encode($plan) ?>;
const amount = <?= (int)$amount ?>;
const razorpayKeyId = <?= json_encode(RAZORPAY_KEY_ID) ?>;
const payLabel = document.getElementById('payBtn').textContent;

document.getElementById('checkoutForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const errBox = document.getElementById('checkoutError');
  const payBtn = document.getElementById('payBtn');
  errBox.style.display = 'none';
  payBtn.disabled = true;
  payBtn.textContent = 'Please wait…';

  const bmidEl = document.getElementById('bmid');
  const bmid = bmidEl ? bmidEl.value.trim() : '';
  const business = document.getElementById('business').value.trim();

  try {
    // 1. Create a pending order on our server + a Razorpay order
    const res = await fetch('api/create_order.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ plan: planSlug, bmid, business })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Could not start checkout.');

    if (!data.razorpay_order_id) {
      // Razorpay keys not configured yet, or the order call failed
      errBox.textContent = 'Payments are not fully set up yet. Contact the site admin (Razorpay keys missing or unreachable in config.php).';
      errBox.style.display = 'block';
      payBtn.disabled = false;
      payBtn.textContent = payLabel;
      return;
    }

    // 2. Open Razorpay checkout
    const rzp = new Razorpay({
      key: razorpayKeyId,
      amount: data.razorpay_amount,
      currency: 'INR',
      name: 'BlueSky Agency',
      description: plan,
      image: 'assets/img/logo-mark.png',
      order_id: data.razorpay_order_id,
      prefill: { name: <?= json_encode($user['name']) ?>, email: <?= json_encode($user['email']) ?>, contact: <?= json_encode($user['phone']) ?> },
      theme: { color: '#3b62ff' },
      handler: async function(response){
        // 3. Verify payment on server
        const vRes = await fetch('api/verify_payment.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            order_id: data.order_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature
          })
        });
        const vData = await vRes.json();
        if (vData.ok) {
          window.location.href = 'dashboard.php?paid=1';
        } else {
          errBox.textContent = vData.error || 'Payment verification failed. Contact support.';
          errBox.style.display = 'block';
          payBtn.disabled = false;
          payBtn.textContent = payLabel;
        }
      },
      modal: {
        ondismiss: function(){
          payBtn.disabled = false;
          payBtn.textContent = payLabel;
        }
      }
    });
    rzp.open();

  } catch (err) {
    errBox.textContent = err.message;
    errBox.style.display = 'block';
    payBtn.disabled = false;
    payBtn.textContent = payLabel;
  }
});
</script>

</body>
</html>
