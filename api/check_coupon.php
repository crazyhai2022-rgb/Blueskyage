<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coupons.php';

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

$check = coupon_lookup($input['code'] ?? '');
if (!$check['ok']) {
    echo json_encode(['ok' => false, 'error' => $check['error']]);
    exit;
}

$result = coupon_apply($item['amount'], $check['coupon']);

echo json_encode([
    'ok'       => true,
    'percent'  => $result['percent'],
    'discount' => $result['discount'],
    'final'    => $result['final'],
]);
