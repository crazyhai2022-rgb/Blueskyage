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

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Price always comes from the server-side catalog, never the request body.
$item = catalog_item($input['plan'] ?? '');
if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Unknown plan or product.']);
    exit;
}

$db = get_db();

/* Ad funds: only the amounts we actually offer are accepted, and the rate
   comes from the catalog — never from the browser. */
$depositUsd = 0;
$depositInr = 0;
if (in_array('deposit', $item['fields'] ?? [], true)) {
    $requested = (int)($input['deposit_usd'] ?? 0);
    if ($requested > 0 && in_array($requested, deposit_options(), true)) {
        $depositUsd = $requested;
        $depositInr = $depositUsd * (int)($item['usd_rate'] ?? 105);
    }
}

$original = $item['amount'];
$discount = 0;
$couponCode = null;
$coupon   = null;

// Re-validate the coupon here — the browser's word isn't enough.
if (!empty($input['coupon'])) {
    $res = coupon_lookup($db, $input['coupon']);
    if (!$res['ok']) {
        echo json_encode(['ok' => false, 'error' => $res['error']]);
        exit;
    }
    $coupon     = $res['coupon'];
    $couponCode = strtoupper(trim($input['coupon']));
    $discount   = coupon_discount($original, (int)$coupon['percent_off']);
}

// The coupon discounts the plan only; ad funds are added at face value.
$amount = ($original - $discount) + $depositInr;

$stmt = $db->prepare(
    "INSERT INTO orders (user_id, plan, amount, bm_id, business_name, status, coupon_code, discount, original_amount)
     VALUES (?, ?, ?, ?, ?, 'pending_payment', ?, ?, ?)"
);
$planLabel = $item['name'];
if ($depositUsd > 0) {
    $planLabel .= ' + $' . $depositUsd . ' ad funds';
}

$stmt->execute([
    $user['id'],
    $planLabel,
    $amount,
    trim($input['bmid'] ?? ''),
    trim($input['business'] ?? ''),
    $couponCode,
    $discount,
    $original + $depositInr,
]);
$orderId = (int)$db->lastInsertId();

if ($coupon) {
    $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$coupon['id']]);
}

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

echo json_encode([
    'ok'                => true,
    'order_id'          => $orderId,
    'amount'            => $amount,
    'razorpay_order_id' => $razorpayOrderId,
    'razorpay_amount'   => $amount * 100,
]);
