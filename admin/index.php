<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$db = get_db();

$filter = $_GET['status'] ?? 'all';
$sql = "SELECT o.*, u.name AS user_name, u.email, u.phone
        FROM orders o JOIN users u ON u.id = o.user_id";
$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE o.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY o.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$stats = $db->query("SELECT status, COUNT(*) c, SUM(amount) rev FROM orders GROUP BY status")->fetchAll();
$totalClients = $db->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
$statsByStatus = [];
$totalRevenue = 0;
foreach ($stats as $s) {
    $statsByStatus[$s['status']] = (int)$s['c'];
    if ($s['status'] !== 'pending_payment' && $s['status'] !== 'cancelled') $totalRevenue += (int)$s['rev'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — BlueSky Agency</title>
<link rel="icon" href="../assets/img/logo-mark.png">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<header class="navbar scrolled">
  <div class="container nav-inner">
    <a href="index.php" class="brand">
      <img src="../assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <div class="nav-cta">
      <span class="who" style="margin-right:14px;">Admin: <b><?= e($admin['username']) ?></b></span>
      <a href="logout.php" class="btn btn-ghost btn-sm">Log Out</a>
    </div>
  </div>
</header>

<section class="section" style="padding-top:40px;">
  <div class="container app-wrap wide">
    <div class="eyebrow">Admin Panel</div>
    <h1 style="margin-bottom:24px;">All Orders &amp; Clients</h1>

    <div class="admin-stat-row">
      <div class="admin-stat"><span>Total Clients</span><b><?= (int)$totalClients ?></b></div>
      <div class="admin-stat"><span>Preparing</span><b><?= $statsByStatus['paid_preparing'] ?? 0 ?></b></div>
      <div class="admin-stat"><span>Active</span><b><?= $statsByStatus['active'] ?? 0 ?></b></div>
      <div class="admin-stat"><span>Total Revenue</span><b>₹<?= number_format($totalRevenue) ?></b></div>
    </div>

    <div class="inline-form" style="margin-bottom:18px;">
      <a href="?status=all" class="btn btn-ghost btn-sm">All</a>
      <a href="?status=pending_payment" class="btn btn-ghost btn-sm">Pending Payment</a>
      <a href="?status=paid_preparing" class="btn btn-ghost btn-sm">Preparing</a>
      <a href="?status=active" class="btn btn-ghost btn-sm">Active</a>
      <a href="?status=cancelled" class="btn btn-ghost btn-sm">Cancelled</a>
    </div>

    <div class="glass-card" style="padding:10px 20px;overflow-x:auto;">
      <table class="admin-table">
        <tr>
          <th>Invoice</th><th>Client</th><th>Plan</th><th>Amount</th>
          <th>BM ID</th><th>Slot ID</th><th>Status</th><th>Update</th>
        </tr>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= e($o['invoice_no'] ?: '#' . $o['id']) ?></td>
          <td><?= e($o['user_name']) ?><br><span style="color:var(--mist-dim);font-size:11.5px;"><?= e($o['phone']) ?></span></td>
          <td><?= e($o['plan']) ?></td>
          <td>₹<?= (int)$o['amount'] ?></td>
          <td><?= e($o['bm_id'] ?: '—') ?></td>
          <td><?= e($o['slot_id'] ?: '—') ?></td>
          <td><span class="<?= status_class($o['status']) ?>"><?= status_label($o['status']) ?></span></td>
          <td>
            <form class="inline-form" method="post" action="update_order.php">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <input type="text" name="slot_id" placeholder="Slot (A7)" value="<?= e($o['slot_id'] ?? '') ?>" style="width:80px;">
              <input type="text" name="ad_account_id" placeholder="Ad Acc ID" value="<?= e($o['ad_account_id'] ?? '') ?>" style="width:100px;">
              <select name="status">
                <option value="pending_payment" <?= $o['status']==='pending_payment'?'selected':'' ?>>Pending</option>
                <option value="paid_preparing" <?= $o['status']==='paid_preparing'?'selected':'' ?>>Preparing</option>
                <option value="active" <?= $o['status']==='active'?'selected':'' ?>>Active</option>
                <option value="cancelled" <?= $o['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
              </select>
              <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--mist);padding:30px;">No orders found.</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</section>

</body>
</html>
