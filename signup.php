<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$error = '';
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$name || !$email || !$phone || !$pass) {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $hash]);
            login_user((int)$db->lastInsertId());
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up — BlueSky Agency</title>
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
    <div class="eyebrow">Create Account</div>
    <h1 style="margin-bottom:24px;">Sign Up</h1>

    <div class="glass-card form-wrap">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="signup.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        </div>
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
      </form>
      <p style="margin-top:18px;font-size:13.5px;color:var(--mist);text-align:center;">
        Already have an account? <a href="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" style="color:var(--blue-light);">Log In</a>
      </p>
    </div>
  </div>
</section>

</body>
</html>
