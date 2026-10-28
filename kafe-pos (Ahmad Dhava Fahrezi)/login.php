<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $row = $st->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $error = 'Username atau password tidak sesuai.';
        } elseif ((int)$row['status'] !== 1) {
            $error = 'Akun Anda sedang dinonaktifkan. Hubungi administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'nama_lengkap' => $row['nama_lengkap'],
                'role' => $row['role'],
            ];
            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-wrap">
    <div class="login-logo" aria-hidden="true">T</div>
    <div class="login-box">
      <h1>Login</h1>
      <p class="login-sub"><?= e(APP_NAME) ?> - Sistem Kasir</p>
      <?= flash_render() ?>
      <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
        </div>
        <div class="field">
          <div class="label-row">
            <label for="password">Password</label>
            <span class="muted small">Lupa password? Hubungi admin</span>
          </div>
          <input type="password" id="password" name="password" required>
        </div>
        <label class="check"><input type="checkbox" id="showPw"> Tampilkan password</label>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
      </form>
    </div>
  </div>
  <script>
    document.getElementById('showPw').addEventListener('change', function () {
      document.getElementById('password').type = this.checked ? 'text' : 'password';
    });
  </script>
</body>
</html>
