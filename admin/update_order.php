<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();
csrf_check();

$orderId     = (int)($_POST['order_id'] ?? 0);
$slotId      = trim($_POST['slot_id'] ?? '');
$adAccountId = trim($_POST['ad_account_id'] ?? '');
$status      = $_POST['status'] ?? 'pending_payment';

$allowed = ['pending_payment', 'paid_preparing', 'active', 'cancelled'];
if (!in_array($status, $allowed, true)) $status = 'pending_payment';

$stmt = get_db()->prepare("UPDATE orders SET slot_id = ?, ad_account_id = ?, status = ? WHERE id = ?");
$stmt->execute([$slotId ?: null, $adAccountId ?: null, $status, $orderId]);

header('Location: /admin');
exit;
