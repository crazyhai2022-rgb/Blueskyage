<?php
require_once __DIR__ . '/db.php';

/**
 * Look up a coupon and decide whether it can be used right now.
 * Returns ['ok' => bool, 'error' => string, 'coupon' => array|null].
 */
function coupon_lookup(string $code): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'error' => 'Enter a coupon code.', 'coupon' => null];

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$code]);
    $c = $stmt->fetch();

    if (!$c)                        return ['ok' => false, 'error' => 'That coupon code is not valid.', 'coupon' => null];
    if (!filter_var($c['active'], FILTER_VALIDATE_BOOLEAN))
                                    return ['ok' => false, 'error' => 'This coupon is no longer active.', 'coupon' => null];
    if (!empty($c['expires_at']) && strtotime($c['expires_at']) < time())
                                    return ['ok' => false, 'error' => 'This coupon has expired.', 'coupon' => null];
    if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses'])
                                    return ['ok' => false, 'error' => 'This coupon has been fully used.', 'coupon' => null];

    return ['ok' => true, 'error' => '', 'coupon' => $c];
}

/**
 * Apply a coupon to an amount. Always leaves at least ₹1 so the payment
 * gateway still has something to charge.
 */
function coupon_apply(int $amount, array $coupon): array {
    $percent  = max(1, min(100, (int)$coupon['percent_off']));
    $discount = (int)floor($amount * $percent / 100);
    $final    = max(1, $amount - $discount);
    return [
        'percent'  => $percent,
        'discount' => $amount - $final,
        'final'    => $final,
    ];
}

/** Record one more use of a coupon. Best-effort — never blocks an order. */
function coupon_mark_used(string $code): void {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT used_count FROM coupons WHERE code = ?");
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch();
        if (!$row) return;

        $upd = $db->prepare("UPDATE coupons SET used_count = ? WHERE code = ?");
        $upd->execute([(int)$row['used_count'] + 1, strtoupper(trim($code))]);
    } catch (Throwable $e) {
        error_log('coupon_mark_used failed: ' . $e->getMessage());
    }
}
