<?php
require_once __DIR__ . '/functions.php';

/* Cart lives in the session as [slug => qty]. Prices are never stored
   here — they're looked up from the catalog every time, so a stale or
   tampered cart can't change what someone is charged. */

function cart_start(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
}

function cart_add(string $slug, int $qty = 1): bool {
    cart_start();
    $item = catalog_item($slug);
    if (!$item) return false;

    $slug = strtolower(trim($slug));

    // Monthly plans are one-per-account, so they never stack up.
    if ($item['recurring']) {
        $_SESSION['cart'][$slug] = 1;
        return true;
    }

    $current = $_SESSION['cart'][$slug] ?? 0;
    $_SESSION['cart'][$slug] = min(10, $current + max(1, $qty));
    return true;
}

function cart_set(string $slug, int $qty): void {
    cart_start();
    $slug = strtolower(trim($slug));
    if (!catalog_item($slug)) return;

    if ($qty < 1) {
        unset($_SESSION['cart'][$slug]);
        return;
    }
    $_SESSION['cart'][$slug] = min(10, $qty);
}

function cart_remove(string $slug): void {
    cart_start();
    unset($_SESSION['cart'][strtolower(trim($slug))]);
}

function cart_clear(): void {
    cart_start();
    $_SESSION['cart'] = [];
}

/** Cart contents expanded with live catalog prices. */
function cart_items(): array {
    cart_start();
    $out = [];
    foreach ($_SESSION['cart'] as $slug => $qty) {
        $item = catalog_item($slug);
        if (!$item) continue;                 // catalog changed — drop silently
        $qty = max(1, min(10, (int)$qty));
        $out[] = [
            'slug'      => $slug,
            'name'      => $item['name'],
            'price'     => $item['amount'],
            'recurring' => $item['recurring'],
            'qty'       => $qty,
            'subtotal'  => $item['amount'] * $qty,
        ];
    }
    return $out;
}

function cart_total(): int {
    return array_sum(array_column(cart_items(), 'subtotal'));
}

function cart_count(): int {
    return array_sum(array_column(cart_items(), 'qty'));
}

function cart_is_empty(): bool {
    return cart_count() === 0;
}

/** True if the cart holds a monthly plan (those need a BM ID at checkout). */
function cart_has_recurring(): bool {
    foreach (cart_items() as $i) if ($i['recurring']) return true;
    return false;
}
