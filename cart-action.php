<?php
require_once __DIR__ . '/includes/cart.php';

/* Handles Add / Update / Remove from any page, then sends the visitor back
   where they came from. Works without JavaScript. */

$action = $_REQUEST['action'] ?? 'add';
$slug   = $_REQUEST['item'] ?? '';

switch ($action) {
    case 'add':
        cart_add($slug, (int)($_REQUEST['qty'] ?? 1));
        $back = 'cart.php';
        break;
    case 'update':
        cart_set($slug, (int)($_REQUEST['qty'] ?? 1));
        $back = 'cart.php';
        break;
    case 'remove':
        cart_remove($slug);
        $back = 'cart.php';
        break;
    case 'clear':
        cart_clear();
        $back = 'cart.php';
        break;
    default:
        $back = 'cart.php';
}

// Allow an explicit local redirect (e.g. straight to checkout after adding)
$to = $_REQUEST['redirect'] ?? $back;
if (!preg_match('~^[a-z0-9_\-]+\.php(\?.*)?$~i', $to)) $to = $back;

header('Location: ' . $to);
exit;
