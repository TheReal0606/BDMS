<?php
// expects $title, $admin (optional), $page (optional)
$page = $page ?? '';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'BDMS') ?> - BDMS</title>
<link rel="stylesheet" href="assets/style.css">
</head><body>
<?php if (!empty($admin)): ?>
<header class="topbar">
  <a class="brand" href="dashboard.php"><i></i>BDMS</a>
  <nav>
    <a href="dashboard.php" class="<?= $page==='map'?'on':'' ?>">Live Map</a>
    <a href="buses.php" class="<?= $page==='buses'?'on':'' ?>">Buses</a>
    <a href="history.php" class="<?= $page==='history'?'on':'' ?>">History</a>
  </nav>
  <span class="who"><?= e($admin['company']) ?> &middot; <?= e($admin['name']) ?></span>
  <a class="btn ghost" href="logout.php">Logout</a>
</header>
<?php endif; ?>
