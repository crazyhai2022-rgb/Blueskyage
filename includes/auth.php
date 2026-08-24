<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- Client auth ---------------- */

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = get_db()->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        $current = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header('Location: /login?redirect=' . urlencode($current));
        exit;
    }
    return $u;
}

function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

/* ---------------- Admin auth ---------------- */

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    $stmt = get_db()->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $a = $stmt->fetch();
    return $a ?: null;
}

function require_admin(): array {
    $a = current_admin();
    if (!$a) {
        header('Location: /admin/login');
        exit;
    }
    return $a;
}

function login_admin(int $adminId): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $adminId;
}

function logout_admin(): void {
    unset($_SESSION['admin_id']);
}

/* ---------------- Helpers ---------------- */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
