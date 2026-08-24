<?php

function generate_invoice_no($db): string {
    $stmt = $db->query("SELECT COUNT(*) AS c FROM orders WHERE invoice_no IS NOT NULL");
    $n = (int)$stmt->fetch()['c'] + 1;
    return 'BSA-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function status_label(string $status): string {
    return match ($status) {
        'pending_payment' => 'Pending Payment',
        'paid_preparing'  => 'Preparing',
        'active'          => 'Active',
        'cancelled'       => 'Cancelled',
        default           => ucfirst($status),
    };
}

function status_class(string $status): string {
    return match ($status) {
        'pending_payment' => 'badge badge-red',
        'paid_preparing'  => 'badge badge-amber',
        'active'          => 'badge badge-green',
        'cancelled'       => 'badge badge-grey',
        default           => 'badge',
    };
}

/**
 * Everything that can be bought, keyed by the slug used in checkout URLs.
 * 'recurring' marks a monthly subscription; products are one-time.
 */
function catalog(): array {
    return [
        // --- Ad account rental plans (monthly) ---
        'basic'              => ['name' => 'Basic Plan — Meta Agency Ad Account', 'amount' => 1499, 'recurring' => true],
        'pro'                => ['name' => 'Pro Plan — Meta Agency Ad Account',   'amount' => 2499, 'recurring' => true],
        // --- One-time products ---
        'facebook-page'      => ['name' => 'Facebook Page',              'amount' => 1499, 'recurring' => false],
        'name-change-page'   => ['name' => 'Name Changeable Page',       'amount' => 1999, 'recurring' => false],
        'old-profile'        => ['name' => '15 Years Old Profile',       'amount' => 2499, 'recurring' => false],
        'verified-profile'   => ['name' => 'Identity Confirmed Profile', 'amount' => 8999, 'recurring' => false],
        'instagram-account'  => ['name' => 'Instagram Account',          'amount' => 999,  'recurring' => false],
    ];
}

/** Look up one catalog item, or null if the slug is unknown. */
function catalog_item(string $slug): ?array {
    $c = catalog();
    $slug = strtolower(trim($slug));
    return $c[$slug] ?? null;
}

function plan_amount(string $plan): int {
    $item = catalog_item($plan);
    return $item ? $item['amount'] : 1499;
}

/**
 * What we need to ask the buyer, per catalog item. Each field is
 * [name, label, placeholder, required]. Kept server-side so the form and
 * the saved order can never drift apart.
 */
function item_fields(string $slug): array {
    $common = [
        ['business', 'Business / Brand Name', 'Your business name', true],
    ];

    switch (strtolower(trim($slug))) {
        case 'basic':
        case 'pro':
            return [
                ['bmid', 'Business Portfolio ID (BM ID)', 'e.g. 123456789012345', true],
                ['business', 'Business Name', 'Your business name', true],
                ['website', 'Website or Page Link', 'https://…', false],
                ['spend', 'Expected Monthly Ad Spend', 'e.g. $500 - $2000', false],
            ];

        case 'facebook-page':
            return array_merge($common, [
                ['page_name', 'Page Name You Want', 'Exact name for the page', true],
                ['category', 'Page Category', 'e.g. Clothing Brand, Local Business', true],
            ]);

        case 'name-change-page':
            return array_merge($common, [
                ['page_name', 'Desired Page Name', 'Name you want on the page', true],
                ['category', 'Page Category', 'e.g. E-commerce, Services', false],
            ]);

        case 'old-profile':
            return array_merge($common, [
                ['region', 'Preferred Country / Region', 'e.g. USA, India, any', false],
                ['purpose', 'What will you use it for?', 'e.g. running ads, managing a BM', true],
            ]);

        case 'verified-profile':
            return array_merge($common, [
                ['region', 'Preferred Country / Region', 'e.g. USA, India, any', false],
                ['purpose', 'What will you use it for?', 'e.g. BM verification, ad account', true],
            ]);

        case 'instagram-account':
            return array_merge($common, [
                ['username', 'Preferred Username', '@yourbrand', false],
                ['niche', 'Account Niche', 'e.g. fashion, fitness, food', true],
            ]);
    }

    return $common;
}

/* ------------------------------ COUPONS ------------------------------ */

/**
 * Look up a coupon and check it can still be used.
 * Returns ['ok'=>bool, 'error'=>string, 'coupon'=>array|null].
 */
function coupon_lookup($db, string $code): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'error' => 'Enter a coupon code.', 'coupon' => null];

    $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$code]);
    $c = $stmt->fetch();

    if (!$c)                                   return ['ok' => false, 'error' => 'That coupon code is not valid.', 'coupon' => null];
    if (!$c['active'] || $c['active'] === 'f') return ['ok' => false, 'error' => 'This coupon is no longer active.', 'coupon' => null];

    if (!empty($c['expires_at']) && strtotime($c['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'This coupon has expired.', 'coupon' => null];
    }
    if ($c['max_uses'] !== null && $c['max_uses'] !== '' && (int)$c['used_count'] >= (int)$c['max_uses']) {
        return ['ok' => false, 'error' => 'This coupon has reached its usage limit.', 'coupon' => null];
    }

    return ['ok' => true, 'error' => '', 'coupon' => $c];
}

/** Discount in rupees for a given amount, rounded down, never the full price. */
function coupon_discount(int $amount, int $percentOff): int {
    $off = (int)floor($amount * $percentOff / 100);
    return max(0, min($off, $amount - 1)); // always leave at least ₹1 to charge
}
