<?php
require __DIR__ . '/lib/auth.php';
start_session();
$db = db();
if (!(int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn()) { header('Location: setup.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Session expired. Reload the page.';
    else {
        $s = $db->prepare('SELECT id, password_hash FROM admins WHERE email = ?'); $s->execute([$email]);
        $row = $s->fetch();
        if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            header('Location: dashboard.php'); exit;
        }
        $err = 'Wrong email or password.';
    }
}
$title = 'Login';
include __DIR__ . '/partials/header.php';
?>
<div class="center card">
  <h1>BDMS Login</h1>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Email</label><input type="email" name="email" required autofocus>
    <label>Password</label><input type="password" name="password" required>
    <p><button class="btn">Sign in</button></p>
  </form>
</div></body></html>
