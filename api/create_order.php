<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Please log in first.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$bmid     = trim($input['bmid'] ?? '');
$business = trim($input['business'] ?? '');

// Prices come from the session cart expanded against the server-side catalog,
// so nothing the browser sends can change what is charged.
$items = cart_items();
if (!$items) {
    echo json_encode(['ok' => false, 'error' => 'Your cart is empty.']);
    exit;
}
$total = cart_total();

$db = get_db();

// One row per cart line, tied together by a shared group id so a single
// payment can settle the whole cart.
$groupId  = 'G' . date('ymdHis') . random_int(100, 999);
$orderIds = [];

foreach ($items as $i) {
    $stmt = $db->prepare(
        "INSERT INTO orders (user_id, plan, amount, bm_id, business_name, status, razorpay_order_id)
         VALUES (?, ?, ?, ?, ?, 'pending_payment', ?)"
    );
    $stmt->execute([
        $user['id'],
        $i['qty'] > 1 ? $i['name'] . ' × ' . $i['qty'] : $i['name'],
        $i['subtotal'],
        $i['recurring'] ? $bmid : '',
        $business,
        $groupId,
    ]);
    $orderIds[] = (int)$db->lastInsertId();
}

// Ask Razorpay for a payment order — skipped cleanly when keys aren't set yet.
$razorpayOrderId = null;
$keysReady = defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_ID !== 'YOUR_RAZORPAY_KEY_ID' && RAZORPAY_KEY_ID !== '';

if (function_exists('curl_init') && $keysReady) {
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'   => $total * 100, // paise
            'currency' => 'INR',
            'receipt'  => $groupId,
            'notes'    => ['group_id' => $groupId, 'user_id' => (string)$user['id']],
        ]),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $code < 400) {
        $decoded = json_decode($response, true);
        $razorpayOrderId = $decoded['id'] ?? null;
    }
}

// Cart is now an order — empty it either way.
cart_clear();

echo json_encode([
    'ok'                => true,
    'group_id'          => $groupId,
    'order_ids'         => $orderIds,
    'razorpay_order_id' => $razorpayOrderId,
    'razorpay_amount'   => $total * 100,
]);
