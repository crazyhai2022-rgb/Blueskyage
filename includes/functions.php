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

function plan_amount(string $plan): int {
    return match (strtolower($plan)) {
        'pro' => 2499,
        default => 1499,
    };
}
