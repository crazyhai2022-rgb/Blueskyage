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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$orderId          = (int)($input['order_id'] ?? 0);
$razorpayOrderId  = $input['razorpay_order_id'] ?? '';
$razorpayPaymentId= $input['razorpay_payment_id'] ?? '';
$razorpaySignature= $input['razorpay_signature'] ?? '';

$db = get_db();
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Order not found.']);
    exit;
}

// Verify Razorpay's signature: HMAC_SHA256(order_id + "|" + payment_id, key_secret)
$expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);

if (!hash_equals($expectedSignature, $razorpaySignature)) {
    echo json_encode(['ok' => false, 'error' => 'Payment signature could not be verified.']);
    exit;
}

$invoiceNo = generate_invoice_no($db);

$stmt = $db->prepare("UPDATE orders SET status = 'paid_preparing', razorpay_payment_id = ?, invoice_no = ? WHERE id = ?");
$stmt->execute([$razorpayPaymentId, $invoiceNo, $orderId]);

echo json_encode(['ok' => true]);
