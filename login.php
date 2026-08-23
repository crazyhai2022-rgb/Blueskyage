<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = get_db()->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && password_verify($pass, $u['password_hash'])) {
        login_user((int)$u['id']);
        $redirect = $_GET['redirect'] ?? 'dashboard.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In — BlueSky Agency</title>
<link rel="icon" href="assets/img/logo-mark.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<header class="navbar scrolled">
  <div class="container nav-inner">
    <a href="index.html" class="brand">
      <img src="assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
  </div>
</header>

<section class="section" style="padding-top:60px;">
  <div class="container app-wrap">
    <div class="eyebrow">Client Login</div>
    <h1 style="margin-bottom:24px;">Log In</h1>

    <div class="glass-card form-wrap">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Log In</button>
      </form>
      <p style="margin-top:18px;font-size:13.5px;color:var(--mist);text-align:center;">
        New here? <a href="signup.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" style="color:var(--blue-light);">Create an Account</a>
      </p>
    </div>
  </div>
</section>

</body>
</html>
