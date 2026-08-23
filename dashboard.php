<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

$stmt = get_db()->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$justPaid = isset($_GET['paid']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — BlueSky Agency</title>
<link rel="icon" href="assets/img/logo-mark.png">
<link rel="stylesheet" href="assets/css/style.css">
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
    <nav class="nav-links">
      <a href="services.html">Plans</a>
      <a href="contact.html">Contact</a>
    </nav>
    <div class="nav-cta">
      <a href="logout.php" class="btn btn-ghost btn-sm">Log Out</a>
    </div>
  </div>
</header>

<section class="section" style="padding-top:40px;">
  <div class="container app-wrap wide">
    <div class="app-nav">
      <div>
        <div class="eyebrow">Your Account</div>
        <h1 style="margin-bottom:0;">Dashboard</h1>
      </div>
      <span class="who">Logged in as <b><?= e($user['name']) ?></b></span>
    </div>

    <?php if ($justPaid): ?>
      <div class="alert alert-success">Payment received! Your order is now <b>Preparing</b> — we'll confirm your account details here shortly.</div>
    <?php endif; ?>

    <?php if (!$orders): ?>
      <div class="glass-card" style="text-align:center;padding:50px 30px;">
        <p style="color:var(--mist);margin-bottom:20px;">You don't have any orders yet.</p>
        <a href="services.html" class="btn btn-primary">View Plans</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="order-card">
          <div class="order-card-top">
            <div>
              <h3><?= e($o['plan']) ?> Plan — ₹<?= (int)$o['amount'] ?>/mo</h3>
              <span><?= $o['invoice_no'] ? e($o['invoice_no']) : 'Order #' . (int)$o['id'] ?> · <?= date('d M Y', strtotime($o['created_at'])) ?></span>
            </div>
            <span class="<?= status_class($o['status']) ?>"><?= status_label($o['status']) ?></span>
          </div>

          <?php if ($o['status'] === 'pending_payment'): ?>
            <p style="font-size:13px;color:var(--mist);">Payment not completed. <a href="checkout.php?plan=<?= urlencode($o['plan']) ?>" style="color:var(--blue-light);">Try again</a></p>
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
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

</body>
</html>
