<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/account_nav.php';

$user = require_login();

$stmt = get_db()->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$justPaid   = isset($_GET['paid']);
$justPlaced = isset($_GET['placed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
/* Runs before paint so a light-theme user never sees a dark flash. */
(function(){try{var t=localStorage.getItem("bluesky-theme");
if(!t)t="light";
document.documentElement.setAttribute("data-theme",t);
document.documentElement.style.colorScheme=t;}catch(e){}})();
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — BlueSky Agency</title>
<link rel="icon" href="/assets/img/logo-mark.png">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<?= account_navbar($user, 'dashboard') ?>

<section class="section app-page">
  <div class="container app-wrap wide">
    <div class="app-nav">
      <div>
        <div class="eyebrow">Your Account</div>
        <h1 style="margin-bottom:0;">Dashboard</h1>
      </div>
    </div>

    <?php if ($justPaid): ?>
      <div class="alert alert-success">Payment received! Your order is now <b>Preparing</b> — we'll confirm your account details here shortly.</div>
    <?php elseif ($justPlaced): ?>
      <div class="alert alert-success">Order placed! It's saved below as <b>Pending Payment</b> — message us on WhatsApp and we'll share the payment details and get you set up.</div>
    <?php endif; ?>

    <?php if (!$orders): ?>
      <div class="glass-card" style="text-align:center;padding:50px 30px;">
        <p style="color:var(--mist);margin-bottom:20px;">You don't have any orders yet.</p>
        <a href="/services" class="btn btn-primary">View Plans</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="order-card">
          <div class="order-card-top">
            <div>
              <?php $isPlan = stripos($o['plan'], 'Plan') !== false; ?>
              <h3><?= e($o['plan']) ?> — ₹<?= number_format((int)$o['amount']) ?><?= $isPlan ? '/mo' : '' ?></h3>
              <span><?= $o['invoice_no'] ? e($o['invoice_no']) : 'Order #' . (int)$o['id'] ?> · <?= date('d M Y', strtotime($o['created_at'])) ?></span>
            </div>
            <span class="<?= status_class($o['status']) ?>"><?= status_label($o['status']) ?></span>
          </div>

          <?php if ($o['status'] === 'pending_payment'): ?>
            <p style="font-size:13px;color:var(--mist);">Payment not completed. <a href="/services" style="color:var(--blue-light);">Try again</a></p>
          <?php elseif ($o['status'] === 'paid_preparing'): ?>
            <p style="font-size:13px;color:var(--mist);">Your payment is confirmed — our team is setting up your ad account. This usually takes a few hours.</p>
          <?php elseif ($o['status'] === 'active'): ?>
            <div class="order-details">
              <div><span>Ad Account Number</span><b><?= e($o['slot_id'] ?: '—') ?></b></div>
              <div><span>Business Portfolio ID</span><b><?= e($o['bm_id'] ?: '—') ?></b></div>
              <div><span>Status</span><b>Live &amp; Active</b></div>
            </div>
          <?php elseif ($o['status'] === 'cancelled'): ?>
            <p style="font-size:13px;color:var(--mist);">This order was cancelled.</p>
          <?php endif; ?>

          <?php if (!empty($o['coupon_code'])): ?>
            <p class="order-coupon">Coupon <b><?= e($o['coupon_code']) ?></b> applied — you saved ₹<?= number_format((int)$o['discount']) ?></p>
          <?php endif; ?>

          <?php
            $det = json_decode($o['details'] ?? '', true);
            if (!is_array($det)) $det = [];
            unset($det['business'], $det['bmid']);
          ?>
          <?php if ($det): ?>
            <details class="order-more">
              <summary>Details you submitted</summary>
              <div class="order-detail-grid">
                <?php foreach ($det as $k => $v): ?>
                  <div><span><?= e(ucwords(str_replace('_', ' ', $k))) ?></span><b><?= e($v) ?></b></div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<script src="/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
