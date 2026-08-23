<?php
require_once __DIR__ . '/../includes/auth.php';

if (current_admin()) { header('Location: index.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';

    $stmt = get_db()->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $a = $stmt->fetch();

    if ($a && password_verify($pass, $a['password_hash'])) {
        login_admin((int)$a['id']);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — BlueSky Agency</title>
<link rel="icon" href="../assets/img/logo-mark.png">
<link rel="stylesheet" href="../assets/css/style.css">
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
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Log In</button>
      </form>
    </div>
  </div>
</section>

</body>
</html>
