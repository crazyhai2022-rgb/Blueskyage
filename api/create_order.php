<?php
require_once __DIR__ . '/../config.php';
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

// Resolve against the server-side catalog — never trust a price sent by the browser.
$item = catalog_item($input['plan'] ?? '');
if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Unknown plan or product.']);
    exit;
}
$plan     = $item['name'];
$amount   = $item['amount'];
$bmid     = trim($input['bmid'] ?? '');
$business = trim($input['business'] ?? '');

$db = get_db();

// 1. Create a pending order row
$stmt = $db->prepare("INSERT INTO orders (user_id, plan, amount, bm_id, business_name, status) VALUES (?, ?, ?, ?, ?, 'pending_payment')");
$stmt->execute([$user['id'], $plan, $amount, $bmid, $business]);
$orderId = (int)$db->lastInsertId();

// 2. Create a matching order on Razorpay (only if curl is available and keys are set)
$razorpayOrderId = null;

if (function_exists('curl_init') && RAZORPAY_KEY_ID !== 'YOUR_RAZORPAY_KEY_ID') {
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'amount'   => $amount * 100, // paise
            'currency' => 'INR',
            'receipt'  => 'order_' . $orderId,
            'notes'    => ['order_id' => $orderId, 'plan' => $plan],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $rzpOrder = json_decode($response, true);
        if (!empty($rzpOrder['id'])) {
            $razorpayOrderId = $rzpOrder['id'];
            $stmt = $db->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?");
            $stmt->execute([$razorpayOrderId, $orderId]);
        }
    }
    // If Razorpay call failed (bad keys, network issue, curl missing), we
    // fall through and return ok:true with razorpay_order_id = null —
    // the frontend then shows a friendly "payments not set up" message
    // instead of a broken/blank error.
}

echo json_encode([
    'ok' => true,
    'order_id' => $orderId,
    'razorpay_order_id' => $razorpayOrderId,
    'razorpay_amount' => $amount * 100,
]);
