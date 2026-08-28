<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/invoice.php';

$admin = require_admin();
$db    = get_db();

$id = (int)($_GET['id'] ?? 0);
$ok = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);

    $status = array_key_exists($_POST['status'] ?? '', [
        'pending_payment' => 1, 'paid_preparing' => 1, 'active' => 1, 'cancelled' => 1,
    ]) ? $_POST['status'] : 'pending_payment';

    $db->prepare(
        "UPDATE orders SET invoice_no = ?, plan = ?, amount = ?, bm_id = ?, business_name = ?,
                slot_id = ?, ad_account_id = ?, status = ?, deposit_usd = ?, usd_rate = ?,
                platform_fee = ?, contact_name = ?, contact_phone = ?, contact_email = ?, admin_note = ?
         WHERE id = ?"
    )->execute([
        trim($_POST['invoice_no'] ?? ''),
        trim($_POST['plan'] ?? ''),
        (int)($_POST['amount'] ?? 0),
        trim($_POST['bm_id'] ?? ''),
        trim($_POST['business_name'] ?? ''),
        trim($_POST['slot_id'] ?? ''),
        trim($_POST['ad_account_id'] ?? ''),
        $status,
        (int)($_POST['deposit_usd'] ?? 0),
        trim($_POST['usd_rate'] ?? ''),
        (int)($_POST['platform_fee'] ?? 0),
        trim($_POST['contact_name'] ?? ''),
        trim($_POST['contact_phone'] ?? ''),
        trim($_POST['contact_email'] ?? ''),
        trim($_POST['admin_note'] ?? ''),
        $id,
    ]);
    $ok = 'Order updated.';
}

$stmt = $db->prepare("SELECT o.*, u.name AS user_name, u.email, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) { header('Location: /admin'); exit; }

$editing = isset($_GET['edit']);
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
<title>Order <?= e($order['invoice_no'] ?: '#' . $order['id']) ?> — Admin</title>
<link rel="icon" href="/assets/img/logo-mark.png">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<header class="navbar scrolled no-print">
  <div class="container nav-inner">
    <a href="/admin" class="brand">
      <img src="/assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <div class="nav-cta">
      <span class="who" style="margin-right:14px;">Admin: <b><?= e($admin['username']) ?></b></span>
      <a href="/admin/logout" class="btn btn-ghost btn-sm">Log Out</a>
    </div>
  </div>
</header>

<section class="section app-page">
  <div class="container app-wrap wide">

    <div class="app-nav no-print">
      <div>
        <div class="eyebrow">Admin</div>
        <h1 style="margin-bottom:0;">Order <?= e($order['invoice_no'] ?: '#' . $order['id']) ?></h1>
      </div>
      <div class="inv-actions">
        <a href="/admin" class="btn btn-ghost btn-sm">← All Orders</a>
        <?php if ($editing): ?>
          <a href="/admin/order?id=<?= (int)$order['id'] ?>" class="btn btn-ghost btn-sm">Cancel Edit</a>
        <?php else: ?>
          <a href="/admin/order?id=<?= (int)$order['id'] ?>&edit=1" class="btn btn-ghost btn-sm">✎ Edit</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Download Invoice</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success no-print"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error no-print"><?= e($err) ?></div><?php endif; ?>

    <?php if ($editing): ?>
      <div class="glass-card profile-card no-print" style="margin-bottom:24px;">
        <h3 class="ck-heading">Edit Order</h3>
        <form method="POST" action="/admin/order">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

          <div class="form-row">
            <div class="form-group"><label>Invoice No.</label>
              <input type="text" name="invoice_no" value="<?= e($order['invoice_no'] ?? '') ?>" placeholder="BSA-0001"></div>
            <div class="form-group"><label>Status</label>
              <select name="status">
                <?php foreach (['pending_payment','paid_preparing','active','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>

          <div class="form-group"><label>Plan / Product</label>
            <input type="text" name="plan" value="<?= e($order['plan']) ?>" required></div>

          <div class="form-row">
            <div class="form-group"><label>Amount Charged (₹)</label>
              <input type="number" name="amount" value="<?= (int)$order['amount'] ?>" required></div>
            <div class="form-group"><label>Platform Fee (₹)</label>
              <input type="number" name="platform_fee" value="<?= (int)($order['platform_fee'] ?? 0) ?>"></div>
          </div>

          <div class="form-row">
            <div class="form-group"><label>Ad Fund Deposit ($)</label>
              <input type="number" name="deposit_usd" value="<?= (int)($order['deposit_usd'] ?? 0) ?>"></div>
            <div class="form-group"><label>Rate (₹ per $)</label>
              <input type="number" name="usd_rate" value="<?= e($order['usd_rate'] ?? '') ?>" placeholder="105"></div>
          </div>

          <div class="form-row">
            <div class="form-group"><label>Ad Account Number (Slot)</label>
              <input type="text" name="slot_id" value="<?= e($order['slot_id'] ?? '') ?>" placeholder="A7"></div>
            <div class="form-group"><label>Ad Account Reference</label>
              <input type="text" name="ad_account_id" value="<?= e($order['ad_account_id'] ?? '') ?>"></div>
          </div>

          <div class="form-row">
            <div class="form-group"><label>Business Portfolio ID</label>
              <input type="text" name="bm_id" value="<?= e($order['bm_id'] ?? '') ?>"></div>
            <div class="form-group"><label>Business Name</label>
              <input type="text" name="business_name" value="<?= e($order['business_name'] ?? '') ?>"></div>
          </div>

          <div class="form-row">
            <div class="form-group"><label>Contact Name</label>
              <input type="text" name="contact_name" value="<?= e($order['contact_name'] ?? $order['user_name']) ?>"></div>
            <div class="form-group"><label>Contact Phone</label>
              <input type="text" name="contact_phone" value="<?= e($order['contact_phone'] ?? $order['phone']) ?>"></div>
          </div>

          <div class="form-group"><label>Contact Email</label>
            <input type="email" name="contact_email" value="<?= e($order['contact_email'] ?? $order['email']) ?>"></div>

          <div class="form-group"><label>Note on Invoice <span class="opt">(optional)</span></label>
            <input type="text" name="admin_note" value="<?= e($order['admin_note'] ?? '') ?>"
                   placeholder="e.g. Original account B2 disabled — replaced with A6"></div>

          <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
        </form>
      </div>
    <?php endif; ?>

    <?= render_invoice($order) ?>

  </div>
</section>

<script src="/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
