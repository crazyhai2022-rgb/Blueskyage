<?php
require_once __DIR__ . '/../includes/cart.php';
header('Content-Type: application/json');
echo json_encode(['count' => cart_count()]);
