<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

/* The browser finishes the Google/X/Apple hand-off with Supabase and sends us
   the resulting access token. We never trust what the browser says about the
   user — we ask Supabase who that token belongs to, then match or create a
   local account for them. */

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = trim($input['access_token'] ?? '');

if ($token === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing sign-in token.']);
    exit;
}

if (!function_exists('curl_init')) {
    echo json_encode(['ok' => false, 'error' => 'Server is missing the curl extension.']);
    exit;
}

$ch = curl_init(rtrim(SUPABASE_URL, '/') . '/auth/v1/user');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . $token,
    ],
]);
$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $code >= 400) {
    echo json_encode(['ok' => false, 'error' => 'Could not verify that sign-in. Please try again.']);
    exit;
}

$authUser = json_decode($response, true) ?: [];
$email    = strtolower(trim($authUser['email'] ?? ''));

if ($email === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'That account did not share an email address, so we cannot create your profile.',
    ]);
    exit;
}

$meta       = $authUser['user_metadata'] ?? [];
$appMeta    = $authUser['app_metadata'] ?? [];
$provider   = $appMeta['provider'] ?? 'social';
$providerId = $authUser['id'] ?? null;
$name       = trim($meta['full_name'] ?? $meta['name'] ?? '') ?: strstr($email, '@', true);
$avatar     = $meta['avatar_url'] ?? $meta['picture'] ?? null;
$phone      = trim($meta['phone'] ?? $authUser['phone'] ?? '');

$db = get_db();

// Existing account with this email? Log them into it — one person, one account,
// whichever way they choose to sign in.
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    login_user((int)$existing['id']);
    echo json_encode(['ok' => true, 'new' => false]);
    exit;
}

$stmt = $db->prepare(
    "INSERT INTO users (name, email, phone, password_hash, provider, provider_id, avatar_url)
     VALUES (?, ?, ?, NULL, ?, ?, ?)"
);
$stmt->execute([$name, $email, $phone, $provider, $providerId, $avatar]);

login_user((int)$db->lastInsertId());
echo json_encode(['ok' => true, 'new' => true]);
