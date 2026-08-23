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
