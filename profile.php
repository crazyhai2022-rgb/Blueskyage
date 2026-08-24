<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/account_nav.php';

$user = require_login();
$db   = get_db();

$ok  = '';
$err = '';
$openTab = 'picture';

/* Social accounts have no password of their own, so those forms can't ask
   for one. They can still change their picture. */
$hasPassword = !empty($user['provider']) ? $user['provider'] === 'email' : true;

const AVATAR_DIR = __DIR__ . '/assets/uploads/avatars';
const AVATAR_URL = '/assets/uploads/avatars';
const MAX_AVATAR = 2 * 1024 * 1024;   // 2 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $openTab = $action ?: 'picture';

    // fetch the stored hash once — needed by both credential forms
    $stmt = $db->prepare("SELECT id, email, password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $account = $stmt->fetch() ?: [];

    /* ---------------- profile picture ---------------- */
    if ($action === 'picture') {
        $file = $_FILES['avatar'] ?? null;

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $err = 'Choose an image first.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'That file could not be uploaded. Please try again.';
        } elseif ($file['size'] > MAX_AVATAR) {
            $err = 'That image is larger than 2 MB. Please pick a smaller one.';
        } else {
            // Trust the file's actual contents, not the name or the browser's
            // reported type — either can be faked.
            $info = @getimagesize($file['tmp_name']);
            $allowed = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp',
            ];

            if (!$info || !isset($allowed[$info[2]])) {
                $err = 'Only JPG, PNG or WebP images are allowed.';
            } else {
                if (!is_dir(AVATAR_DIR)) @mkdir(AVATAR_DIR, 0775, true);

                $ext      = $allowed[$info[2]];
                $filename = 'u' . (int)$user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $dest     = AVATAR_DIR . '/' . $filename;

                if (!@move_uploaded_file($file['tmp_name'], $dest)) {
                    $err = 'Could not save the image. Check that the uploads folder is writable.';
                } else {
                    @chmod($dest, 0644);

                    // remove the previous upload so the folder doesn't grow forever
                    if (!empty($user['avatar_url']) && str_starts_with($user['avatar_url'], AVATAR_URL)) {
                        $old = __DIR__ . $user['avatar_url'];
                        if (is_file($old)) @unlink($old);
                    }

                    $db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?")
                       ->execute([AVATAR_URL . '/' . $filename, $user['id']]);

                    $ok   = 'Your profile picture has been updated.';
                    $user = current_user();
                }
            }
        }
    }

    /* ---------------- email ---------------- */
    elseif ($action === 'email') {
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $password = $_POST['current_password'] ?? '';

        if (!$hasPassword) {
            $err = 'Your account signs in with Google, so the email cannot be changed here.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $err = 'Enter a valid email address.';
        } elseif ($newEmail === strtolower($account['email'] ?? '')) {
            $err = 'That is already your email address.';
        } elseif (!password_verify($password, $account['password_hash'] ?? '')) {
            $err = 'Your current password is incorrect.';
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $check->execute([$newEmail, $user['id']]);

            if ($check->fetch()) {
                $err = 'Another account already uses that email address.';
            } else {
                $db->prepare("UPDATE users SET email = ? WHERE id = ?")
                   ->execute([$newEmail, $user['id']]);
                $ok   = 'Your email address has been updated.';
                $user = current_user();
            }
        }
    }

    /* ---------------- password ---------------- */
    elseif ($action === 'password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!$hasPassword) {
            $err = 'Your account signs in with Google, so there is no password to change.';
        } elseif (!password_verify($currentPw, $account['password_hash'] ?? '')) {
            $err = 'Your current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $err = 'Your new password must be at least 6 characters.';
        } elseif ($newPw !== $confirmPw) {
            $err = 'The two new passwords do not match.';
        } elseif (password_verify($newPw, $account['password_hash'] ?? '')) {
            $err = 'Your new password must be different from the current one.';
        } else {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
            $ok = 'Your password has been changed.';
        }
    }

    /* ---------------- name & phone ---------------- */
    elseif ($action === 'details') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $err = 'Your name cannot be empty.';
        } else {
            $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")
               ->execute([$name, $phone, $user['id']]);
            $ok   = 'Your details have been saved.';
            $user = current_user();
        }
    }
}

$memberSince = !empty($user['created_at'])
    ? date('j M Y', strtotime($user['created_at']))
    : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — BlueSky Agency</title>
<link rel="icon" href="/assets/img/logo-mark.png">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<?= account_navbar($user, 'profile') ?>

<section class="section app-page">
  <div class="container app-wrap wide">

    <div class="app-nav">
      <div>
        <div class="eyebrow">Your Account</div>
        <h1 style="margin-bottom:0;">My Profile</h1>
      </div>
      <a href="/dashboard" class="btn btn-ghost btn-sm">← Dashboard</a>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= e($ok) ?></div>
    <?php elseif ($err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <!-- summary -->
    <div class="glass-card profile-summary">
      <?= avatar_markup($user, 'lg') ?>
      <div class="profile-meta">
        <h2><?= e($user['name']) ?></h2>
        <div class="profile-facts">
          <div><span>Email</span><b><?= e($user['email']) ?></b></div>
          <div><span>Phone</span><b><?= e($user['phone'] ?: '—') ?></b></div>
          <div><span>Member since</span><b><?= e($memberSince) ?></b></div>
        </div>
      </div>
    </div>

    <div class="profile-grid">

      <!-- picture -->
      <div class="glass-card profile-card">
        <h3 class="ck-heading">Profile Picture</h3>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="picture">

          <div class="avatar-pick">
            <?= avatar_markup($user, 'md') ?>
            <div>
              <label for="avatar" class="btn btn-ghost btn-sm">Choose image</label>
              <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" hidden>
              <p class="field-hint" id="avatarName">JPG, PNG or WebP · up to 2 MB</p>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">Save Picture</button>
        </form>
      </div>

      <!-- name & phone -->
      <div class="glass-card profile-card">
        <h3 class="ck-heading">Your Details</h3>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="details">
          <div class="form-group">
            <label for="p_name">Full Name</label>
            <input type="text" id="p_name" name="name" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label for="p_phone">WhatsApp Number</label>
            <input type="tel" id="p_phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Save Details</button>
        </form>
      </div>

      <!-- email -->
      <div class="glass-card profile-card">
        <h3 class="ck-heading">Change Email</h3>
        <?php if (!$hasPassword): ?>
          <p class="profile-note">You sign in with Google, so your email is managed there.</p>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="email">
            <div class="form-group">
              <label for="new_email">New Email</label>
              <input type="email" id="new_email" name="new_email" required>
            </div>
            <div class="form-group">
              <label for="email_pw">Confirm with Current Password</label>
              <div class="pw-field">
                <input type="password" id="email_pw" name="current_password" required>
                <button type="button" class="pw-toggle" aria-label="Show password" title="Show password">
                  <svg class="pw-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="pw-shut" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Update Email</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- password -->
      <div class="glass-card profile-card">
        <h3 class="ck-heading">Change Password</h3>
        <?php if (!$hasPassword): ?>
          <p class="profile-note">You sign in with Google, so there is no password on this account.</p>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <div class="form-group">
              <label for="cur_pw">Current Password</label>
              <div class="pw-field">
                <input type="password" id="cur_pw" name="current_password" required>
                <button type="button" class="pw-toggle" aria-label="Show password" title="Show password">
                  <svg class="pw-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="pw-shut" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label for="new_pw">New Password</label>
              <div class="pw-field">
                <input type="password" id="new_pw" name="new_password" minlength="6" required>
                <button type="button" class="pw-toggle" aria-label="Show password" title="Show password">
                  <svg class="pw-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="pw-shut" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <small class="field-hint">At least 6 characters.</small>
            </div>
            <div class="form-group">
              <label for="conf_pw">Confirm New Password</label>
              <input type="password" id="conf_pw" name="confirm_password" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Update Password</button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<script>
/* Live preview of the chosen picture, before it is uploaded. */
(function () {
  const input = document.getElementById('avatar');
  if (!input) return;
  const preview = document.querySelector('.avatar-pick .avatar');
  const label   = document.getElementById('avatarName');

  input.addEventListener('change', function () {
    const file = input.files && input.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      label.textContent = 'That image is over 2 MB — pick a smaller one.';
      label.classList.add('bad');
      input.value = '';
      return;
    }

    label.textContent = file.name;
    label.classList.remove('bad');

    const url = URL.createObjectURL(file);
    preview.classList.remove('avatar-initials');
    preview.textContent = '';
    const img = document.createElement('img');
    img.src = url;
    img.alt = 'Selected picture';
    img.onload = () => URL.revokeObjectURL(url);
    preview.appendChild(img);
  });
})();
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>
