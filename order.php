<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invoice.php';
require_once __DIR__ . '/includes/account_nav.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = get_db()->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);
$order = $stmt->fetch();

// Someone else's order id simply doesn't exist as far as this account is concerned.
if (!$order) { header('Location: /dashboard'); exit; }

$order['user_name'] = $user['name'];
$order['email']     = $user['email'];
$order['phone']     = $user['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function(){try{var t=localStorage.getItem("bluesky-theme");
if(!t)t="light";
document.documentElement.setAttribute("data-theme",t);
document.documentElement.style.colorScheme=t;}catch(e){}})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= e($order['invoice_no'] ?: '#' . $order['id']) ?> — BlueSky Agency</title>
<link rel="icon" href="/assets/img/logo-mark.png">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<?= account_navbar($user, 'dashboard') ?>

<section class="section app-page">
  <div class="container app-wrap wide">

    <div class="app-nav no-print">
      <div>
        <div class="eyebrow">Your Account</div>
        <h1 style="margin-bottom:0;">Order Details</h1>
      </div>
      <div class="inv-actions">
        <a href="/dashboard" class="btn btn-ghost btn-sm">← Back</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Download Invoice</button>
      </div>
    </div>

    <?= render_invoice($order) ?>

  </div>
</section>

<script src="/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
