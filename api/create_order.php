<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coupons.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Price always comes from the server-side catalog, never from the browser.
$item = catalog_item($input['plan'] ?? '');
if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Unknown plan or product.']);
    exit;
}

$slug     = strtolower(trim($input['plan']));
$original = (int)$item['amount'];
$amount   = $original;
$discount = 0;
$couponCode = null;

// Re-check the coupon here — the browser's number is never trusted.
if (!empty($input['coupon'])) {
    $check = coupon_lookup($input['coupon']);
    if ($check['ok']) {
        $applied    = coupon_apply($original, $check['coupon']);
        $amount     = $applied['final'];
        $discount   = $applied['discount'];
        $couponCode = strtoupper(trim($input['coupon']));
    }
}

// Keep only the fields this item actually asks for, plus free-text notes.
$allowed = array_column(item_fields($slug), 0);
$allowed[] = 'notes';
$posted  = is_array($input['details'] ?? null) ? $input['details'] : [];

$details = [];
foreach ($allowed as $key) {
    // mb_* isn't guaranteed on shared hosting, so fall back to substr.
    if (!empty($posted[$key])) {
        $val = trim((string)$posted[$key]);
        $details[$key] = function_exists('mb_substr') ? mb_substr($val, 0, 500) : substr($val, 0, 500);
    }
}

$bmid     = $details['bmid'] ?? '';
$business = $details['business'] ?? '';

$db = get_db();

$stmt = $db->prepare(
    "INSERT INTO orders (user_id, plan, amount, bm_id, business_name, status, details, coupon_code, discount, original_amount)
     VALUES (?, ?, ?, ?, ?, 'pending_payment', ?, ?, ?, ?)"
);
$stmt->execute([
    $user['id'], $item['name'], $amount, $bmid, $business,
    json_encode($details, JSON_UNESCAPED_UNICODE), $couponCode, $discount, $original,
]);
$orderId = (int)$db->lastInsertId();

// Ask Razorpay for a payment order — skipped cleanly when keys aren't set.
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
            'amount'   => $amount * 100, // paise
            'currency' => 'INR',
            'receipt'  => 'order_' . $orderId,
            'notes'    => ['order_id' => (string)$orderId, 'plan' => $item['name']],
        ]),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $code < 400) {
        $decoded = json_decode($response, true);
        $razorpayOrderId = $decoded['id'] ?? null;
        if ($razorpayOrderId) {
            $db->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?")
               ->execute([$razorpayOrderId, $orderId]);
        }
    }
}

if ($couponCode) coupon_mark_used($couponCode);

echo json_encode([
    'ok'                => true,
    'order_id'          => $orderId,
    'amount'            => $amount,
    'razorpay_order_id' => $razorpayOrderId,
    'razorpay_amount'   => $amount * 100,
]);
