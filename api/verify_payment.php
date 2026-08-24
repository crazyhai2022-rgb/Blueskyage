<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Please log in first.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId   = (int)($input['order_id'] ?? 0);
$rzpOrder  = $input['razorpay_order_id'] ?? '';
$rzpPay    = $input['razorpay_payment_id'] ?? '';
$signature = $input['razorpay_signature'] ?? '';

if (!$orderId || !$rzpOrder || !$rzpPay || !$signature) {
    echo json_encode(['ok' => false, 'error' => 'Incomplete payment details.']);
    exit;
}

// Confirm the payment really came from Razorpay.
$expected = hash_hmac('sha256', $rzpOrder . '|' . $rzpPay, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
    echo json_encode(['ok' => false, 'error' => 'Payment verification failed.']);
    exit;
}

$db = get_db();

// Only mark rows that belong to this user and this checkout group.
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['id']]);
$orders = $stmt->fetchAll();

if (!$orders) {
    echo json_encode(['ok' => false, 'error' => 'Order not found.']);
    exit;
}

foreach ($orders as $o) {
    $invoiceNo = generate_invoice_no($db);
    $upd = $db->prepare(
        "UPDATE orders SET status = 'paid_preparing', razorpay_payment_id = ?, invoice_no = ? WHERE id = ?"
    );
    $upd->execute([$rzpPay, $invoiceNo, $o['id']]);
}

echo json_encode(['ok' => true]);
