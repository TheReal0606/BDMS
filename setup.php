<?php
require __DIR__ . '/lib/auth.php';
start_session();
$err = '';
$db = db();
$exists = (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
// First company is open to setup. Afterwards only a logged-in admin may add another company.
$admin = current_admin();
if ($exists && !$admin) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = trim($_POST['company'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Session expired. Reload the page.';
    elseif ($company === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Fill in all fields with a valid email.';
    elseif (strlen($pw) < 8) $err = 'Password must be at least 8 characters.';
    else {
        $q = $db->prepare('SELECT 1 FROM admins WHERE email = ?'); $q->execute([$email]);
        if ($q->fetchColumn()) $err = 'That email is already used.';
        else {
            $db->beginTransaction();
            $db->prepare('INSERT INTO companies(name) VALUES (?)')->execute([$company]);
            $cid = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO admins(company_id,name,email,password_hash) VALUES (?,?,?,?)')
               ->execute([$cid, $name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            $newAdmin = (int)$db->lastInsertId();
            $db->commit();
            if (!$admin) { session_regenerate_id(true); $_SESSION['admin_id'] = $newAdmin; }
            header('Location: ' . ($admin ? 'buses.php' : 'dashboard.php')); exit;
        }
    }
}
$title = 'Setup';
include __DIR__ . '/partials/header.php';
?>
<div class="center card">
  <h1>Create bus company</h1>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Company name</label><input name="company" required value="<?= e($_POST['company'] ?? '') ?>">
    <label>Admin name</label><input name="name" required value="<?= e($_POST['name'] ?? '') ?>">
    <label>Admin email</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
    <label>Password (8+ characters)</label><input type="password" name="password" required minlength="8">
    <p><button class="btn">Create</button></p>
  </form>
</div></body></html>
