<?php
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/auth.php';

$items = cart_items();
$total = cart_total();
$user  = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — BlueSky Agency</title>
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
      <a href="products.html">Products</a>
      <a href="contact.html">Contact</a>
    </nav>
    <div class="nav-cta">
      <?php if ($user): ?>
        <a href="dashboard.php" class="btn btn-ghost btn-sm">My Account</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-ghost btn-sm">Log In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="section" style="padding-top:40px;">
  <div class="container app-wrap wide">
    <div class="eyebrow">Your Cart</div>
    <h1 style="margin-bottom:24px;">Cart</h1>

    <?php if (!$items): ?>
      <div class="glass-card" style="text-align:center;padding:56px 30px;">
        <p style="color:var(--mist);margin-bottom:22px;">Your cart is empty.</p>
        <a href="services.html" class="btn btn-primary">Browse Plans</a>
        <a href="products.html" class="btn btn-ghost" style="margin-left:8px;">Browse Products</a>
      </div>
    <?php else: ?>

      <div class="glass-card cart-card">
        <?php foreach ($items as $i): ?>
          <div class="cart-row">
            <div class="cart-info">
              <h3><?= e($i['name']) ?></h3>
              <span>
                ₹<?= number_format($i['price']) ?><?= $i['recurring'] ? ' / month' : '' ?>
                <?= $i['recurring'] ? '· Monthly subscription' : '· One-time' ?>
              </span>
            </div>

            <div class="cart-qty">
              <?php if ($i['recurring']): ?>
                <span class="qty-fixed">Qty 1</span>
              <?php else: ?>
                <form method="POST" action="cart-action.php" class="qty-form">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="item" value="<?= e($i['slug']) ?>">
                  <button type="submit" name="qty" value="<?= $i['qty'] - 1 ?>" class="qty-btn" aria-label="Decrease">−</button>
                  <span class="qty-num"><?= $i['qty'] ?></span>
                  <button type="submit" name="qty" value="<?= $i['qty'] + 1 ?>" class="qty-btn" aria-label="Increase">+</button>
                </form>
              <?php endif; ?>
            </div>

            <div class="cart-sub">₹<?= number_format($i['subtotal']) ?></div>

            <form method="POST" action="cart-action.php" class="cart-del">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="item" value="<?= e($i['slug']) ?>">
              <button type="submit" class="link-danger" aria-label="Remove">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>

        <div class="cart-total">
          <span>Total</span>
          <b>₹<?= number_format($total) ?></b>
        </div>
      </div>

      <div class="cart-actions">
        <a href="products.html" class="btn btn-ghost">Continue Shopping</a>
        <a href="checkout.php" class="btn btn-primary btn-lg">Proceed to Checkout →</a>
      </div>

      <?php if (cart_has_recurring()): ?>
        <p class="cart-note">Monthly plans renew every 30 days. You'll enter your Business Portfolio ID at checkout.</p>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<script src="assets/js/main.js"></script>
</body>
</html>
