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
    <h1 style="margin-bottom:20px;">Sign Up</h1>

    <!-- Waves hello on arrival, covers its eyes while you type a password -->
    <div class="monkey" id="monkey" aria-hidden="true">
      <div class="monkey-face">
        <div class="m-ear m-ear-l"></div>
        <div class="m-ear m-ear-r"></div>
        <div class="m-head">
          <div class="m-eye m-eye-l"><span class="m-pupil"></span></div>
          <div class="m-eye m-eye-r"><span class="m-pupil"></span></div>
          <div class="m-muzzle">
            <div class="m-nose m-nose-l"></div>
            <div class="m-nose m-nose-r"></div>
            <div class="m-mouth"></div>
          </div>
        </div>
        <div class="m-hand m-hand-l"></div>
        <div class="m-hand m-hand-r"></div>
      </div>
      <p class="monkey-say" id="monkeySay">Welcome! 👋</p>
    </div>

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


<script>
/* Monkey reacts to the form: hands over the eyes while a password is being
   typed, a quick wave on arrival. Purely decorative. */
(function () {
  const monkey = document.getElementById('monkey');
  const say    = document.getElementById('monkeySay');
  if (!monkey || !say) return;

  const pass = document.querySelector('input[type="password"]');
  const name = document.getElementById('name');

  monkey.classList.add('waving');
  setTimeout(function () { monkey.classList.remove('waving'); }, 2200);

  function greeting() {
    const v = name && name.value.trim().split(' ')[0];
    return v ? 'Hi ' + v + '! \uD83D\uDC4B' : 'Welcome! \uD83D\uDC4B';
  }

  function cover(on) {
    // A wave in progress would otherwise animate over the covering pose.
    if (on) monkey.classList.remove('waving');
    monkey.classList.toggle('covering', on);
    say.textContent = on ? "Not looking, promise! \uD83D\uDE48" : greeting();
  }

  if (pass) {
    pass.addEventListener('focus', function () { cover(true); });
    pass.addEventListener('blur',  function () { cover(false); });
  }

  if (name) {
    name.addEventListener('input', function () {
      if (!monkey.classList.contains('covering')) say.textContent = greeting();
    });
  }

  document.addEventListener('mousemove', function (e) {
    if (monkey.classList.contains('covering')) return;
    const r = monkey.getBoundingClientRect();
    const dx = Math.max(-3, Math.min(3, (e.clientX - (r.left + r.width / 2)) / 55));
    const dy = Math.max(-3, Math.min(3, (e.clientY - (r.top + r.height / 2)) / 55));
    monkey.querySelectorAll('.m-pupil').forEach(function (pu) {
      pu.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
    });
  });
})();
</script>
</body>
</html>
