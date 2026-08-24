<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!current_user()) {
    echo json_encode(['ok' => false, 'error' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$item  = catalog_item($input['plan'] ?? '');
if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Unknown plan or product.']);
    exit;
}

$result = coupon_lookup(get_db(), $input['code'] ?? '');
if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$percent  = (int)$result['coupon']['percent_off'];
$discount = coupon_discount($item['amount'], $percent);

echo json_encode([
    'ok'       => true,
    'code'     => strtoupper(trim($input['code'])),
    'percent'  => $percent,
    'discount' => $discount,
    'total'    => $item['amount'] - $discount,
]);
