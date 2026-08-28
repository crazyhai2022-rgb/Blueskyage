<?php
require_once __DIR__ . '/../includes/auth.php';

if (current_admin()) { header('Location: /admin'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['admin_user'] ?? '');
    $pass     = $_POST['admin_pass'] ?? '';

    $stmt = get_db()->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $a = $stmt->fetch();

    if ($a && password_verify($pass, $a['password_hash'])) {
        login_admin((int)$a['id']);
        header('Location: /admin');
        exit;
    } else {
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
/* Runs before paint so a light-theme user never sees a dark flash. */
(function(){try{var t=localStorage.getItem("bluesky-theme");
if(!t)t="light";
document.documentElement.setAttribute("data-theme",t);
document.documentElement.style.colorScheme=t;}catch(e){}})();
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — BlueSky Agency</title>
<link rel="icon" href="../assets/img/logo-mark.png">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<section class="section" style="padding-top:100px;">
  <div class="container app-wrap">
    <div style="text-align:center;margin-bottom:24px;">
      <img src="../assets/img/logo-mark.png" style="height:44px;margin:0 auto 14px;">
      <div class="eyebrow">Admin Panel</div>
      <h1>BlueSky Agency</h1>
    </div>

    <div class="glass-card form-wrap">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <!-- Decoys: the browser fills these instead of the real fields. -->
        <input type="text" name="fake_user" tabindex="-1" aria-hidden="true"
               style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">
        <input type="password" name="fake_pass" tabindex="-1" aria-hidden="true"
               style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">
        <div class="form-group">
          <label for="admin_user">Username</label>
          <input type="text" id="admin_user" name="admin_user" required
                 autocomplete="off" autocapitalize="none" spellcheck="false"
                 readonly data-no-autofill>
        </div>
        <div class="form-group">
          <label for="admin_pass">Password</label>
          <div class="pw-field">
            <input type="password" id="admin_pass" name="admin_pass" required
                   autocomplete="new-password" readonly data-no-autofill>
            <button type="button" class="pw-toggle" aria-label="Show password" title="Show password">
              <svg class="pw-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="pw-shut" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Log In</button>
      </form>
    </div>
  </div>
</section>

<script src="../assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
<script>
/* Fields open up the moment the admin actually interacts with them,
   which is after Chrome has already decided not to autofill. */
document.querySelectorAll('[data-no-autofill]').forEach(function (el) {
  var free = function () { el.removeAttribute('readonly'); };
  el.addEventListener('focus', free);
  el.addEventListener('click', free);
});
</script>
</body>
</html>
